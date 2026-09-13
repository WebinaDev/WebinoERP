<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmLead;
use Modules\Docs\Entities\DocsContract;
use Modules\Sales\Entities\SalesInvoice;
use Modules\Sales\Entities\SalesRahnQuote;
use Modules\Sales\Entities\SalesRahnStatement;

/**
 * Quotes, statements, public share, contract lock — ported from WebinoCRM_Rahn_Service.
 */
class RahnService
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function runCalc(array $params, array $settings, bool $internal): array
    {
        $selected = $this->parseSelectedIds($params);
        $override = null;
        if (! empty($params['items_snapshot'])) {
            $snap = $params['items_snapshot'];
            if (is_string($snap)) {
                $decoded = json_decode($snap, true);
                $snap = is_array($decoded) ? $decoded : null;
            }
            if (is_array($snap)) {
                $override = $snap;
            }
        }
        $items = RahnSettingsService::resolveItems($selected, $override);

        $T = isset($params['T']) ? (int) $params['T'] : (int) $settings['T'];
        $sHat = isset($params['s_hat']) ? (float) $params['s_hat'] : (float) $settings['s_hat_default'];
        $mode = strtolower((string) ($params['mode'] ?? 'from_p'));

        $lockArgs = [
            'items' => $items,
            'T' => $T,
            'm' => (float) $settings['m'],
            'k' => (float) $settings['k'],
            's_hat' => $sHat,
            'p_min' => (float) $settings['p_min'],
            'p_max' => (float) $settings['p_max'],
            'mode' => $mode,
            'p_wanted' => isset($params['p_wanted']) ? (float) $params['p_wanted'] : (float) $settings['p_default'],
            'F_wanted' => isset($params['F_wanted']) ? (float) $params['F_wanted'] : 0.0,
        ];

        if (isset($params['p_percent'])) {
            $lockArgs['p_wanted'] = ((float) $params['p_percent']) / 100.0;
        }

        $lock = RahnCalculator::lockContract($lockArgs);
        $clause = RahnCalculator::renderClause(
            (string) $settings['clause_template'],
            [
                'F' => $lock['F'],
                'p' => $lock['p'],
                'S_hat' => $lock['S_hat'],
                'T' => $lock['T'],
                'alpha' => $lock['alpha'],
            ]
        );

        $public = RahnCalculator::publicPayload($lock, $items, $clause);

        $out = [
            'selected_ids' => $selected,
            'items' => $items,
            'clause' => $clause,
            'public' => $public,
            'lock' => $internal ? $lock : null,
        ];

        if ($internal) {
            $out['internal'] = [
                'C' => $lock['C'],
                'V_star' => $lock['V_star'],
                'F_min' => $lock['F_min'],
                'S_BE' => $lock['S_BE'],
                'm' => $lock['m'],
                'k' => $lock['k'],
                'breakdown' => $lock['breakdown'],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, string>
     */
    public function parseSelectedIds(array $params): array
    {
        $raw = $params['selected_ids'] ?? $params['service_ids'] ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_filter(array_map('trim', explode(',', $raw)));
            }
        }
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            $id = preg_replace('/[^a-z0-9_\-]/i', '', (string) $id) ?: '';
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, mixed>
     */
    public function formatQuote(SalesRahnQuote $quote): array
    {
        $shareUrl = rtrim((string) config('app.url'), '/').'/rahn/'.$quote->token;

        return [
            'id' => (int) $quote->id,
            'token' => (string) $quote->token,
            'title' => (string) $quote->title,
            'status' => (string) $quote->status,
            'customer_id' => (int) $quote->customer_id,
            'lead_id' => (int) $quote->lead_id,
            'contract_id' => (int) $quote->contract_id,
            'F' => (float) $quote->F,
            'p' => (float) $quote->p,
            'p_percent' => (float) $quote->p * 100.0,
            's_hat' => (float) $quote->s_hat,
            'duration' => (int) $quote->duration,
            'locked_at' => $quote->locked_at?->toDateTimeString(),
            'clause' => (string) ($quote->clause ?? ''),
            'share_url' => $shareUrl,
            'created_at' => $quote->created_at?->toDateTimeString(),
            'updated_at' => $quote->updated_at?->toDateTimeString(),
            'selected_ids' => is_array($quote->selected_ids) ? $quote->selected_ids : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{quotes: array<int, array<string, mixed>>, total_pages: int, current_page: int, total: int}
     */
    public function listQuotes(array $params): array
    {
        $paged = max(1, (int) ($params['paged'] ?? $params['page'] ?? 1));
        $per = 20;
        $status = strtolower(trim((string) ($params['status'] ?? '')));

        $query = SalesRahnQuote::query()->orderByDesc('id');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query->forPage($paged, $per)->get();

        return [
            'quotes' => $rows->map(fn (SalesRahnQuote $q) => $this->formatQuote($q))->all(),
            'total_pages' => max(1, (int) ceil($total / $per)),
            'current_page' => $paged,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{quote: array<string, mixed>, calculation: array<string, mixed>}
     */
    public function saveQuote(array $params, ?int $userId = null): array
    {
        $settings = RahnSettingsService::get();
        $calc = $this->runCalc($params, $settings, true);
        $lock = $calc['lock'];

        $quoteId = (int) ($params['id'] ?? $params['quote_id'] ?? 0);
        $title = trim((string) ($params['title'] ?? ''));
        if ($title === '') {
            $title = 'پیش‌نویس رهن‌درصد — '.RahnCalculator::formatMoney((float) $lock['F']);
        }

        $data = [
            'title' => $title,
            'customer_id' => (int) ($params['customer_id'] ?? 0),
            'lead_id' => (int) ($params['lead_id'] ?? 0),
            'selected_ids' => $calc['selected_ids'],
            'items_snapshot' => $calc['items'],
            'calc_snapshot' => $lock,
            's_hat' => (float) $lock['S_hat'],
            'duration' => (int) $lock['T'],
            'mode' => (string) $lock['mode'],
            'p_wanted' => (float) ($params['p_wanted'] ?? $lock['p']),
            'f_wanted' => (float) ($params['F_wanted'] ?? $lock['F']),
            'F' => (float) $lock['F'],
            'p' => (float) $lock['p'],
            'clause' => (string) $calc['clause'],
        ];

        if ($quoteId > 0) {
            $quote = SalesRahnQuote::query()->findOrFail($quoteId);
            if (in_array($quote->status, ['locked', 'contracted'], true)) {
                abort(400, 'این پیش‌نویس قفل شده و قابل ویرایش نیست.');
            }
            $quote->update($data);
        } else {
            $quote = SalesRahnQuote::query()->create(array_merge($data, [
                'token' => bin2hex(random_bytes(16)),
                'status' => 'draft',
                'created_by' => $userId,
            ]));
        }

        return [
            'quote' => $this->formatQuote($quote->fresh()),
            'calculation' => $calc,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{quote: array<string, mixed>, calculation: array<string, mixed>}
     */
    public function lockQuote(int $quoteId, array $params = []): array
    {
        $quote = SalesRahnQuote::query()->findOrFail($quoteId);
        $settings = RahnSettingsService::get();

        $calcParams = [
            'selected_ids' => $quote->selected_ids ?? [],
            'items_snapshot' => $quote->items_snapshot ?? [],
            'T' => (int) ($params['T'] ?? $quote->duration),
            's_hat' => (float) ($params['s_hat'] ?? $quote->s_hat),
            'mode' => strtolower((string) ($params['mode'] ?? $quote->mode)),
            'p_wanted' => (float) ($params['p_wanted'] ?? $quote->p_wanted),
            'F_wanted' => (float) ($params['F_wanted'] ?? $quote->f_wanted),
        ];
        if (isset($params['p_percent'])) {
            $calcParams['p_percent'] = (float) $params['p_percent'];
        }
        if (! empty($params['selected_ids'])) {
            $calcParams['selected_ids'] = $this->parseSelectedIds($params);
            unset($calcParams['items_snapshot']);
        }

        $calc = $this->runCalc($calcParams, $settings, true);
        $lock = $calc['lock'];

        $quote->update([
            'status' => 'locked',
            'selected_ids' => $calc['selected_ids'],
            'items_snapshot' => $calc['items'],
            'calc_snapshot' => $lock,
            's_hat' => (float) $lock['S_hat'],
            'duration' => (int) $lock['T'],
            'mode' => (string) $lock['mode'],
            'F' => (float) $lock['F'],
            'p' => (float) $lock['p'],
            'clause' => (string) $calc['clause'],
            'locked_at' => now(),
        ]);

        return [
            'quote' => $this->formatQuote($quote->fresh()),
            'calculation' => $calc,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{quote: array<string, mixed>, contract_id: int}
     */
    public function quoteToContract(int $quoteId, array $params, ?int $userId = null): array
    {
        $quote = SalesRahnQuote::query()->findOrFail($quoteId);
        if (! in_array($quote->status, ['locked', 'contracted'], true)) {
            abort(400, 'ابتدا ثابت و درصد را قفل کنید.');
        }

        $customerId = (int) ($params['customer_id'] ?? $quote->customer_id);
        if ($customerId <= 0) {
            abort(400, 'انتخاب مشتری الزامی است.');
        }

        $start = trim((string) ($params['start_date'] ?? ''));
        if ($start === '') {
            $start = now()->toDateString();
        }

        $title = trim((string) ($params['contract_title'] ?? $quote->title));
        if ($title === '') {
            $title = 'قرارداد رهن‌درصد';
        }

        $F = (float) $quote->F;
        $p = (float) $quote->p;
        $T = (int) $quote->duration;

        $partyName = '';
        if (Schema::hasTable('crm_accounts')) {
            $partyName = (string) (CrmAccount::query()->find($customerId)?->name ?? '');
        }

        $installments = [];
        $base = strtotime($start.' 12:00:00') ?: time();
        for ($i = 0; $i < max(1, $T); $i++) {
            $due = date('Y-m-d', strtotime('+'.$i.' months', $base));
            $installments[] = [
                'amount' => (string) (int) round($F),
                'due_date' => $due,
                'status' => 'pending',
                'note' => 'ثابت ماهانه رهن‌درصد',
            ];
        }

        $meta = [
            'subscription_model' => 'rahn_percent',
            'customer_id' => $customerId,
            'rahn_fixed' => $F,
            'rahn_percent' => $p,
            'rahn_locked_at' => $quote->locked_at?->toDateTimeString() ?? now()->toDateTimeString(),
            'rahn_quote_id' => $quote->id,
            'rahn_cost_snapshot' => $quote->calc_snapshot,
            'rahn_s_hat' => (float) $quote->s_hat,
            'rahn_duration' => $T,
            'rahn_clause' => (string) $quote->clause,
            'amount' => (int) round($F * max(1, $T)),
            'installments' => $installments,
            'project_start_date' => $start,
            'contract_number' => null,
        ];

        $contractId = (int) $quote->contract_id;
        if ($contractId > 0 && Schema::hasTable('docs_contracts')) {
            $contract = DocsContract::query()->find($contractId);
            if ($contract) {
                $contract->update([
                    'title' => $title,
                    'party_name' => $partyName ?: $contract->party_name,
                    'body' => (string) $quote->clause,
                    'meta' => array_merge($contract->meta ?? [], $meta),
                    'status' => 'active',
                ]);
            } else {
                $contractId = 0;
            }
        }

        if ($contractId <= 0) {
            if (! Schema::hasTable('docs_contracts')) {
                abort(500, 'جدول قراردادها در دسترس نیست.');
            }
            $contract = DocsContract::query()->create([
                'title' => $title,
                'party_name' => $partyName ?: ('Customer #'.$customerId),
                'status' => 'active',
                'body' => (string) $quote->clause,
                'meta' => $meta,
                'created_by' => $userId,
            ]);
            $contractId = (int) $contract->id;
            $number = 'RAHN-'.$contractId.'-'.now()->format('ymd');
            $contract->update(['meta' => array_merge($meta, ['contract_number' => $number])]);
        }

        $quote->update([
            'status' => 'contracted',
            'contract_id' => $contractId,
            'customer_id' => $customerId,
        ]);

        return [
            'quote' => $this->formatQuote($quote->fresh()),
            'contract_id' => $contractId,
        ];
    }

    public function deleteQuote(int $quoteId): void
    {
        $quote = SalesRahnQuote::query()->findOrFail($quoteId);
        if ($quote->status === 'contracted') {
            abort(400, 'پیش‌نویس تبدیل‌شده به قرارداد قابل حذف نیست.');
        }
        $quote->delete();
    }

    /**
     * @return array{contracts: array<int, array<string, mixed>>}
     */
    public function listContracts(): array
    {
        if (! Schema::hasTable('docs_contracts')) {
            return ['contracts' => []];
        }

        $rows = DocsContract::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->filter(function (DocsContract $c) {
                $meta = $c->meta ?? [];

                return ($meta['subscription_model'] ?? null) === 'rahn_percent';
            });

        $items = [];
        foreach ($rows as $post) {
            $meta = $post->meta ?? [];
            $p = (float) ($meta['rahn_percent'] ?? 0);
            $customerId = (int) ($meta['customer_id'] ?? 0);
            $customerName = (string) $post->party_name;
            if ($customerId > 0 && Schema::hasTable('crm_accounts')) {
                $customerName = (string) (CrmAccount::query()->find($customerId)?->name ?? $customerName);
            }
            $items[] = [
                'id' => (int) $post->id,
                'title' => (string) $post->title,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'F' => (float) ($meta['rahn_fixed'] ?? 0),
                'p' => $p,
                'p_percent' => $p * 100.0,
                'clause' => (string) ($meta['rahn_clause'] ?? $post->body ?? ''),
                'duration' => (int) ($meta['rahn_duration'] ?? 0),
                's_hat' => (float) ($meta['rahn_s_hat'] ?? 0),
                'locked_at' => (string) ($meta['rahn_locked_at'] ?? ''),
            ];
        }

        return ['contracts' => array_values($items)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function statementCalculate(array $params): array
    {
        $contractId = (int) ($params['contract_id'] ?? 0);
        if (! Schema::hasTable('docs_contracts')) {
            abort(404, 'قرارداد یافت نشد.');
        }
        $post = DocsContract::query()->find($contractId);
        if (! $post) {
            abort(404, 'قرارداد یافت نشد.');
        }
        $meta = $post->meta ?? [];
        if (($meta['subscription_model'] ?? null) !== 'rahn_percent') {
            abort(400, 'این قرارداد مدل رهن‌درصد نیست.');
        }

        $F = (float) ($meta['rahn_fixed'] ?? 0);
        $p = (float) ($meta['rahn_percent'] ?? 0);
        $C = null;
        $snap = $meta['rahn_cost_snapshot'] ?? null;
        if (is_string($snap) && $snap !== '') {
            $decoded = json_decode($snap, true);
            $snap = is_array($decoded) ? $decoded : null;
        }
        if (is_array($snap) && isset($snap['C'])) {
            $C = (float) $snap['C'];
        }

        $settings = RahnSettingsService::get();
        $sales = [
            'G' => (float) ($params['G'] ?? 0),
            'R' => (float) ($params['R'] ?? 0),
            'D' => (float) ($params['D'] ?? 0),
            'X' => (float) ($params['X'] ?? 0),
        ];
        foreach (['G', 'R', 'D', 'X'] as $key) {
            if (empty($settings['sales_definition'][$key]['enabled'])) {
                $sales[$key] = 0.0;
            }
        }

        $bill = RahnCalculator::monthlyBill($F, $p, $sales, $C);

        $yearMonth = trim((string) ($params['year_month'] ?? now()->format('Y-m')));
        if (! preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
            $yearMonth = now()->format('Y-m');
        }

        return [
            'bill' => $bill,
            'year_month' => $yearMonth,
            'contract_id' => $contractId,
            'clause' => (string) ($meta['rahn_clause'] ?? $post->body ?? ''),
            'review_alert' => $this->checkReviewAlert($meta, $bill, $settings),
            'sales_definition' => $settings['sales_definition'],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function statementSave(array $params, ?int $userId = null): array
    {
        $data = $this->statementCalculate($params);
        $bill = $data['bill'];
        $contractId = (int) $data['contract_id'];
        $yearMonth = (string) $data['year_month'];

        $existing = SalesRahnStatement::query()
            ->where('contract_id', $contractId)
            ->where('year_month', $yearMonth)
            ->first();

        $meta = DocsContract::query()->find($contractId)?->meta ?? [];

        $rowData = [
            'contract_id' => $contractId,
            'quote_id' => (int) ($meta['rahn_quote_id'] ?? 0),
            'year_month' => $yearMonth,
            'G' => (float) $bill['G'],
            'R' => (float) $bill['R'],
            'D' => (float) $bill['D'],
            'X' => (float) $bill['X'],
            'S' => (float) $bill['S'],
            'F' => (float) $bill['F'],
            'p' => (float) $bill['p'],
            'V' => (float) $bill['V'],
            'C' => isset($bill['C']) ? (float) $bill['C'] : null,
            'Pi' => isset($bill['Pi']) ? (float) $bill['Pi'] : null,
            'notes' => trim((string) ($params['notes'] ?? '')),
        ];

        $invoiceId = $existing ? (int) $existing->invoice_id : 0;
        $createInvoice = ! empty($params['create_invoice']);

        if ($createInvoice && $invoiceId <= 0) {
            $invoiceId = $this->createProInvoice($contractId, $bill, $yearMonth, $userId);
            $rowData['invoice_id'] = $invoiceId;
        } elseif ($existing) {
            $rowData['invoice_id'] = $invoiceId;
        }

        if ($existing) {
            $existing->update($rowData);
            $statementId = (int) $existing->id;
        } else {
            $rowData['created_by'] = $userId;
            $statement = SalesRahnStatement::query()->create($rowData);
            $statementId = (int) $statement->id;
        }

        return [
            'statement_id' => $statementId,
            'invoice_id' => $invoiceId,
            'bill' => $bill,
            'year_month' => $yearMonth,
            'review_alert' => $data['review_alert'],
        ];
    }

    /**
     * @return array{statements: array<int, array<string, mixed>>}
     */
    public function listStatements(int $contractId): array
    {
        $rows = SalesRahnStatement::query()
            ->where('contract_id', $contractId)
            ->orderByDesc('year_month')
            ->get()
            ->toArray();

        return ['statements' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicGet(string $token): array
    {
        $quote = SalesRahnQuote::query()->where('token', $token)->firstOrFail();
        $settings = RahnSettingsService::get();
        $calc = $this->runCalc(
            [
                'selected_ids' => $quote->selected_ids ?? [],
                'items_snapshot' => $quote->items_snapshot ?? [],
                'T' => (int) $quote->duration,
                's_hat' => (float) $quote->s_hat,
                'mode' => (string) $quote->mode,
                'p_wanted' => (float) $quote->p,
                'F_wanted' => (float) $quote->F,
            ],
            $settings,
            false
        );

        $catalogPublic = [];
        foreach ((array) $settings['catalog'] as $item) {
            if (empty($item['active'])) {
                continue;
            }
            $catalogPublic[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'billing' => $item['billing'],
                'period_months' => $item['period_months'],
                'renewable' => ! empty($item['renewable']),
                'category' => $item['category'],
                'description' => $item['description'],
                'default_selected' => ! empty($item['default_selected']),
            ];
        }

        return [
            'token' => $token,
            'title' => (string) $quote->title,
            'status' => (string) $quote->status,
            'locked' => in_array($quote->status, ['locked', 'contracted'], true),
            'selected_ids' => $quote->selected_ids ?? [],
            'catalog' => $catalogPublic,
            'public' => $calc['public'],
            'p_min' => (float) $settings['p_min'],
            'p_max' => (float) $settings['p_max'],
            's_hat' => (float) $quote->s_hat,
            'T' => (int) $quote->duration,
            'site_name' => (string) config('app.name', 'Webino'),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function publicCalculate(string $token, array $params): array
    {
        $quote = SalesRahnQuote::query()->where('token', $token)->firstOrFail();
        if (in_array($quote->status, ['locked', 'contracted'], true)) {
            return $this->publicGet($token);
        }

        $settings = RahnSettingsService::get();
        $selected = $this->parseSelectedIds($params);
        if ($selected === []) {
            $selected = $quote->selected_ids ?? [];
        }

        $calc = $this->runCalc(
            [
                'selected_ids' => $selected,
                'T' => (int) ($params['T'] ?? $quote->duration),
                's_hat' => (float) ($params['s_hat'] ?? $quote->s_hat),
                'mode' => strtolower((string) ($params['mode'] ?? 'from_p')),
                'p_wanted' => (float) ($params['p_wanted'] ?? $quote->p_wanted),
                'F_wanted' => (float) ($params['F_wanted'] ?? $quote->f_wanted),
                'p_percent' => $params['p_percent'] ?? null,
            ],
            $settings,
            false
        );

        return [
            'public' => $calc['public'],
            'selected_ids' => $selected,
            'clause' => $calc['clause'],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function publicSubmit(string $token, array $params): array
    {
        $quote = SalesRahnQuote::query()->where('token', $token)->firstOrFail();

        $name = trim((string) ($params['name'] ?? ''));
        $phone = trim((string) ($params['phone'] ?? ''));
        $email = trim((string) ($params['email'] ?? ''));
        $note = trim((string) ($params['note'] ?? ''));

        if ($name === '' || ($phone === '' && $email === '')) {
            abort(400, 'نام و شماره تماس یا ایمیل الزامی است.');
        }

        $settings = RahnSettingsService::get();
        $selected = $this->parseSelectedIds($params);
        if ($selected === []) {
            $selected = $quote->selected_ids ?? [];
        }

        $calc = $this->runCalc(
            [
                'selected_ids' => $selected,
                'T' => (int) ($params['T'] ?? $quote->duration),
                's_hat' => (float) ($params['s_hat'] ?? $quote->s_hat),
                'mode' => strtolower((string) ($params['mode'] ?? $quote->mode)),
                'p_wanted' => (float) ($params['p_wanted'] ?? $quote->p),
                'F_wanted' => (float) ($params['F_wanted'] ?? $quote->F),
                'p_percent' => $params['p_percent'] ?? null,
            ],
            $settings,
            true
        );
        $lock = $calc['lock'];

        $leadContent = sprintf(
            "درخواست رهن‌درصد\nنام: %s\nتلفن: %s\nایمیل: %s\nثابت: %s\nدرصد: %s\n%s\n%s",
            $name,
            $phone,
            $email,
            RahnCalculator::formatMoney((float) $lock['F']),
            RahnCalculator::formatPercent((float) $lock['p']).'%',
            (string) $calc['clause'],
            $note
        );

        $leadId = 0;
        if (Schema::hasTable('crm_leads')) {
            $parts = preg_split('/\s+/', $name, 2) ?: [$name];
            $lead = CrmLead::query()->create([
                'topic' => 'لید رهن‌درصد — '.$name,
                'first_name' => $parts[0] ?? $name,
                'last_name' => $parts[1] ?? '',
                'email' => $email ?: null,
                'mobile' => $phone ?: null,
                'phone' => $phone ?: null,
                'description' => $leadContent,
                'industry' => 'rahn_percent',
            ]);
            $leadId = (int) $lead->id;
        }

        $quote->update([
            'lead_id' => $leadId,
            'selected_ids' => $calc['selected_ids'],
            'items_snapshot' => $calc['items'],
            'calc_snapshot' => $lock,
            's_hat' => (float) $lock['S_hat'],
            'duration' => (int) $lock['T'],
            'F' => (float) $lock['F'],
            'p' => (float) $lock['p'],
            'clause' => (string) $calc['clause'],
        ]);

        return [
            'message' => 'درخواست شما ثبت شد. به‌زودی با شما تماس می‌گیریم.',
            'lead_id' => $leadId,
            'public' => $calc['public'],
        ];
    }

    /**
     * @return array<int, array{id: int, display_name: string}>
     */
    public function listCustomers(): array
    {
        if (! Schema::hasTable('crm_accounts')) {
            return [];
        }

        return CrmAccount::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name'])
            ->map(fn (CrmAccount $a) => [
                'id' => (int) $a->id,
                'display_name' => (string) $a->name,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $bill
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>|null
     */
    private function checkReviewAlert(array $meta, array $bill, array $settings): ?array
    {
        $review = $settings['review'] ?? [];
        if (empty($review['enabled'])) {
            return null;
        }
        $sHat = (float) ($meta['rahn_s_hat'] ?? 0);
        if ($sHat <= 0) {
            return null;
        }
        $deviation = abs((((float) $bill['S']) - $sHat) / $sHat) * 100.0;
        $threshold = (float) ($review['deviation_percent'] ?? 25);
        if ($deviation < $threshold) {
            return null;
        }

        return [
            'message' => sprintf(
                'انحراف فروش از مبنای قرارداد حدود %s%% است (آستانه %s%%). در صورت تداوم، قرارداد جدید پیشنهاد شود — F و p ماه جاری تغییر نمی‌کنند.',
                RahnCalculator::formatPercent($deviation / 100.0),
                RahnCalculator::formatPercent($threshold / 100.0)
            ),
            'deviation_percent' => $deviation,
            'threshold' => $threshold,
        ];
    }

    /**
     * @param  array<string, mixed>  $bill
     */
    private function createProInvoice(int $contractId, array $bill, string $yearMonth, ?int $userId = null): int
    {
        $contract = DocsContract::query()->find($contractId);
        $customerName = (string) ($contract?->party_name ?? 'Customer');
        $number = 'rahn-'.$contractId.'-'.str_replace('-', '', $yearMonth);
        if (SalesInvoice::query()->where('number', $number)->exists()) {
            $number .= '-'.Str::lower(Str::random(4));
        }

        $invoice = SalesInvoice::query()->create([
            'number' => $number,
            'customer_name' => $customerName,
            'total' => (float) $bill['V'],
            'status' => 'draft',
            'issue_date' => $yearMonth.'-01',
            'created_by' => $userId,
        ]);

        return (int) $invoice->id;
    }
}
