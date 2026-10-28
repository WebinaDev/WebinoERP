<?php

namespace Modules\Crm\Services\Customer360;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Events\Customer\ContractSigned;
use Modules\Crm\Services\CrmOutboundMessenger;
use Modules\Crm\Services\Hub\CustomerHubService;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Services\Payments\PaymentOrchestrator;

/** Customer self-service portal (PHASE5_API.md §3). The caller has already been verified as a portal customer. */
class CustomerPortalService
{
    public const OTP_TTL = 300;

    public const OTP_RESEND = 60;

    public const OTP_MAX_ATTEMPTS = 5;

    public const SIGNABLE = ['draft', 'active', 'sent', 'pending'];

    public function __construct(private CustomerHubService $hub) {}

    /** Resolve the account the portal user is acting for; foreign ids → 403. */
    public function account(User $user, ?int $accountId): CrmAccount
    {
        $q = $user->crmAccounts()->whereNull('crm_accounts.merged_into_id');
        $a = $accountId ? $q->where('crm_accounts.id', $accountId)->first() : $q->orderByDesc('crm_account_users.is_primary')->orderBy('crm_accounts.id')->first();
        abort_unless($a instanceof CrmAccount, $accountId ? 403 : 404);

        return $a;
    }

    public function profile(User $user, CrmAccount $a): array
    {
        return [
            'account' => collect(['id', 'name', 'brand_name', 'national_id', 'economic_code', 'mobile', 'email', 'website', 'address_line', 'city', 'province', 'postal_code', 'hub_status'])
                ->mapWithKeys(fn ($k) => [$k => $a->getAttribute($k)])->all(),
            'accounts' => $user->crmAccounts()->whereNull('crm_accounts.merged_into_id')->orderBy('crm_accounts.id')->get()
                ->map(fn ($x) => ['id' => $x->id, 'name' => $x->name, 'is_primary' => (bool) $x->pivot->is_primary])->values()->all(),
        ];
    }

    public function contracts(CrmAccount $a): array
    {
        return DB::table('prj_contracts')->where('customer_account_id', $a->id)->orderByDesc('id')->get()->map(fn ($c) => $this->contractRow($c))->all();
    }

    public function contract(CrmAccount $a, int $id): array
    {
        $c = $this->findContract($a, $id);
        $inst = Schema::hasTable('prj_contract_installments')
            ? DB::table('prj_contract_installments')->where('contract_id', $id)->orderBy('due_date')->get(['id', 'amount', 'due_date', 'paid_at'])
                ->map(fn ($i) => ['id' => $i->id, 'amount' => (float) $i->amount, 'due_date' => $i->due_date, 'paid_at' => $this->iso($i->paid_at)])->all()
            : [];

        return $this->contractRow($c) + ['body' => $c->body ?? $c->notes ?? null, 'installments' => $inst];
    }

    /** @return array{sent_to:string,expires_in:int,resend_in:int} */
    public function sendOtp(User $user, CrmAccount $a, int $id): array
    {
        $c = $this->findContract($a, $id);
        abort_unless(in_array($c->status, self::SIGNABLE, true) && empty($c->signed_at), 422, __('This contract cannot be signed.'));
        $mobile = $this->mobile($user, $a);
        abort_unless($mobile !== null, 422, __('No mobile number on file.'));
        $lock = $this->key($user, $id).':resend';
        if (($until = Cache::get($lock)) && $until > time()) {
            abort(429, __('Please wait before requesting a new code.'), ['Retry-After' => (string) ($until - time())]);
        }
        $code = (string) random_int(100000, 999999);
        Cache::put($this->key($user, $id), ['hash' => Hash::make($code), 'attempts' => 0, 'mobile' => $mobile], self::OTP_TTL);
        Cache::put($lock, time() + self::OTP_RESEND, self::OTP_RESEND);
        // deliverSms does not persist the body, so the code never lands in crm_messages.
        app(CrmOutboundMessenger::class)->deliverSms($mobile, __('Contract signing code: :code', ['code' => $code]));

        return ['sent_to' => substr($mobile, 0, 4).'***'.substr($mobile, -4), 'expires_in' => self::OTP_TTL, 'resend_in' => self::OTP_RESEND];
    }

    public function sign(User $user, CrmAccount $a, int $id, string $code, ?string $ip, ?string $agent): array
    {
        $c = $this->findContract($a, $id);
        abort_unless(in_array($c->status, self::SIGNABLE, true) && empty($c->signed_at), 422, __('This contract cannot be signed.'));
        $key = $this->key($user, $id);
        $otp = Cache::get($key);
        if (! $otp || ! Hash::check($code, $otp['hash'])) {
            if ($otp) {
                $otp['attempts']++;
                $otp['attempts'] >= self::OTP_MAX_ATTEMPTS ? Cache::forget($key) : Cache::put($key, $otp, self::OTP_TTL);
            }
            throw ValidationException::withMessages(['code' => __('The verification code is invalid or expired.')]);
        }
        Cache::forget($key);
        $at = now();
        DB::table('prj_contracts')->where('id', $id)->update(['status' => 'signed', 'signed_at' => $at, 'updated_at' => $at]);
        $meta = ['contract_id' => $id, 'ip' => $ip, 'user_agent' => mb_substr((string) $agent, 0, 255), 'mobile' => $otp['mobile'], 'user_id' => $user->id, 'at' => $at->toIso8601String()];
        DB::table('crm_customer_audit')->insert(['account_id' => $a->id, 'user_id' => $user->id, 'action' => 'portal.contract.signed',
            'changes' => json_encode(['signature_meta' => $meta], JSON_UNESCAPED_UNICODE), 'ip' => $ip, 'created_at' => $at]);
        event(new ContractSigned($a->id, $id, $user->id, $c->signed_document_id ?? null));

        return $this->contractRow(DB::table('prj_contracts')->find($id));
    }

    /** @return array{0: list<array>, 1: array} rows, kpis */
    public function invoices(CrmAccount $a, string $status): array
    {
        $fin = $this->hub->financials($a);
        $rows = $this->hub->invoices($a)->reject(fn ($i) => $i->status === 'draft')
            ->filter(fn ($i) => match ($status) {
                'open' => ! in_array($i->status, CustomerHubService::CLOSED_INVOICE, true),
                'paid' => in_array($i->status, CustomerHubService::PAID_INVOICE, true),
                default => true,
            })->map(fn ($i) => $this->invoiceRow($a, $i))->values()->all();

        return [$rows, ['outstanding' => $fin['outstanding'], 'overdue' => $fin['overdue']]];
    }

    public function pay(User $user, CrmAccount $a, int $invoiceId, ?string $gateway, ?string $returnUrl): array
    {
        $inv = $this->hub->invoices($a)->firstWhere('id', $invoiceId);
        abort_unless($inv, 404);
        $row = $this->invoiceRow($a, $inv);
        abort_unless($row['payable'], 422, __('This invoice is not payable.'));
        $front = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        if ($returnUrl !== null && $returnUrl !== '') {
            $hosts = array_filter([parse_url($front, PHP_URL_HOST), parse_url((string) config('app.url'), PHP_URL_HOST)]);
            abort_unless(in_array(parse_url($returnUrl, PHP_URL_HOST), $hosts, true), 422, __('return_url must point to this application.'));
        } else {
            $returnUrl = $front.'/portal/invoices?invoice='.$invoiceId;
        }
        $intent = app(PaymentOrchestrator::class)->start(array_filter([
            'payable_type' => 'sales_invoice', 'payable_id' => (string) $invoiceId, 'mode' => 'cash', 'gateway' => $gateway,
            'return_url' => $returnUrl, 'mobile' => $this->mobile($user, $a),
        ], fn ($v) => $v !== null), $user->id);

        return ['payment_id' => $intent->public_id, 'redirect_url' => $intent->redirect_url, 'amount' => (float) $intent->total_amount, 'gateway' => $intent->gateway];
    }

    public function payment(User $user, CrmAccount $a, string $publicId): array
    {
        $intent = PaymentIntent::query()->where('public_id', $publicId)->first();
        abort_unless($intent, 404);
        $own = $intent->user_id === $user->id
            || ($intent->payable_type === 'sales_invoice' && $this->hub->invoices($a)->contains(fn ($i) => (string) $i->id === (string) $intent->payable_id));
        abort_unless($own, 404);

        return [
            'payment_id' => $intent->public_id, 'status' => $intent->status, 'paid' => in_array($intent->status, CustomerHubService::PAID_INTENT, true),
            'amount' => (float) $intent->total_amount, 'invoice_id' => $intent->payable_type === 'sales_invoice' ? (int) $intent->payable_id : null,
            'settled_at' => $this->iso($intent->settled_at),
        ];
    }

    public function services(CrmAccount $a): array
    {
        $rows = Schema::hasTable('crm_customer_services')
            ? DB::table('crm_customer_services')->where('account_id', $a->id)->orderBy('id')->get(['id', 'kind', 'name', 'plan', 'status', 'site_domain', 'renews_at'])->map(fn ($s) => (array) $s)->all() : [];
        $domains = Schema::hasTable('crm_customer_domains')
            ? DB::table('crm_customer_domains')->where('account_id', $a->id)->orderBy('domain')->get(['domain', 'expires_at', 'auto_renew'])
                ->map(fn ($d) => ['domain' => $d->domain, 'expires_at' => $d->expires_at, 'auto_renew' => (bool) $d->auto_renew])->all() : [];

        return [$rows, $domains];
    }

    public function documents(User $user, CrmAccount $a): array
    {
        $docs = DB::table('crm_customer_documents')->where('account_id', $a->id)->orderByDesc('id')->get()
            ->filter(function ($d) use ($a) {
                $vis = is_string($d->visible_to_roles) ? json_decode($d->visible_to_roles, true) : null;
                $signed = DB::table('prj_contracts')->where('customer_account_id', $a->id)->where('signed_document_id', $d->id)->exists();

                return in_array('client', (array) $vis, true) || $signed;
            });

        return $docs->map(fn ($d) => [
            'id' => $d->id, 'title' => $d->title, 'category' => $d->category, 'expires_at' => $d->expires_at, 'created_at' => $this->iso($d->created_at),
            'download_url' => ! empty($d->file_path ?? null)
                ? URL::temporarySignedRoute('crm.hub.document.file', now()->addMinutes(10), ['id' => $a->id, 'docId' => $d->id, 'u' => $user->id])
                : ($d->url ?? null),
        ])->values()->all();
    }

    private function invoiceRow(CrmAccount $a, object $i): array
    {
        $paid = in_array($i->status, CustomerHubService::PAID_INVOICE, true);
        $closed = in_array($i->status, CustomerHubService::CLOSED_INVOICE, true);
        $due = $i->issue_date ? CarbonImmutable::parse($i->issue_date)->addDays((int) ($a->payment_terms_days ?? 30))->toDateString() : null;

        return [
            'id' => (int) $i->id, 'number' => $i->number ?? null, 'status' => $i->status, 'issue_date' => $i->issue_date, 'due_date' => $due,
            'total' => (float) $i->total, 'remaining' => $closed ? 0.0 : (float) $i->total, 'payable' => ! $paid && ! $closed && (float) $i->total > 0,
        ];
    }

    private function contractRow(object $c): array
    {
        return [
            'id' => (int) $c->id, 'title' => $c->title, 'status' => $c->status, 'amount' => (float) $c->amount,
            'starts_at' => $c->start_date ?? null, 'ends_at' => $c->end_date ?? null, 'signed_at' => $this->iso($c->signed_at ?? null),
            'can_sign' => in_array($c->status, self::SIGNABLE, true) && empty($c->signed_at), 'document_id' => $c->signed_document_id ?? null,
        ];
    }

    private function findContract(CrmAccount $a, int $id): object
    {
        $c = DB::table('prj_contracts')->where('id', $id)->where('customer_account_id', $a->id)->first();
        abort_unless($c, 404);

        return $c;
    }

    private function mobile(User $user, CrmAccount $a): ?string
    {
        foreach ([$user->mobile ?? null, $user->phone ?? null, $a->mobile] as $m) {
            if ($n = CustomerDuplicateService::normalizeMobile($m)) {
                return '0'.$n;
            }
        }

        return null;
    }

    private function key(User $user, int $contractId): string
    {
        return 'portal:contract-otp:'.$user->id.':'.$contractId;
    }

    private function iso($v): ?string
    {
        return $v ? CarbonImmutable::parse($v)->toIso8601String() : null;
    }
}
