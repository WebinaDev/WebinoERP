<?php

namespace Modules\Crm\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmBasalamOrder;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmMessage;
use Modules\Crm\Events\Customer;
use Modules\Crm\Services\Hub\CustomerResolver;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;
use Modules\Marketplace\Entities\MarketplaceOrder;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Sales\Entities\SalesInvoice;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

/**
 * Turns model writes in other modules into customer sync events, without those modules
 * knowing about CRM. Only fires when the record can be tied to a crm_accounts row.
 */
class CustomerSyncObserver
{
    public static function register(): void
    {
        self::on(CrmDeal::class, function (Model $d, bool $created) {
            $acc = $d->account_id ? (int) $d->account_id : null;
            if ($acc && $d->won_at && ($created || $d->wasChanged('won_at'))) {
                Customer\DealWon::dispatch($acc, $d->id, ['amount' => (float) $d->amount], auth()->id(), (string) $d->won_at, 'معامله برنده شد: '.$d->name);
            }
            if ($acc && $d->lost_at && ($created || $d->wasChanged('lost_at'))) {
                Customer\DealLost::dispatch($acc, $d->id, ['reason' => $d->lost_reason ?? null], auth()->id(), (string) $d->lost_at, 'معامله از دست رفت: '.$d->name);
            }
        });

        self::on(SalesInvoice::class, function (Model $i, bool $created) {
            if (! $acc = self::invoiceAccount($i)) {
                return;
            }
            $label = 'فاکتور '.($i->invoice_number ?? $i->number ?? $i->id);
            $meta = ['total' => (float) ($i->total ?? 0), 'status' => $i->status];
            if ($created) {
                Customer\InvoiceIssued::dispatch($acc, $i->id, $meta, auth()->id(), (string) ($i->issue_date ?? now()), 'صدور '.$label);
            }
            if ($created || $i->wasChanged('status')) {
                match ($i->status) {
                    'paid' => Customer\InvoicePaid::dispatch($acc, $i->id, $meta, auth()->id(), null, 'پرداخت '.$label),
                    'overdue' => Customer\InvoiceOverdue::dispatch($acc, $i->id, $meta, null, null, $label.' معوق شد'),
                    default => null,
                };
            }
        });

        self::on(PrjTicket::class, function (Model $t, bool $created) {
            if (! $acc = self::ticketAccount($t)) {
                return;
            }
            if ($created) {
                Customer\TicketOpened::dispatch($acc, $t->id, ['status' => $t->status], auth()->id(), (string) $t->created_at, (string) $t->subject);
            } elseif ($t->wasChanged('status') && in_array($t->status, ['closed', 'resolved'], true)) {
                Customer\TicketClosed::dispatch($acc, $t->id, ['status' => $t->status], auth()->id(), null, 'بستن تیکت: '.$t->subject);
            }
        });

        self::on(Project::class, function (Model $p, bool $created) {
            if (! $acc = $p->customer_account_id ? (int) $p->customer_account_id : null) {
                return;
            }
            if ($created) {
                Customer\ProjectCreated::dispatch($acc, $p->id, ['status' => $p->status], auth()->id(), (string) $p->created_at, 'ایجاد پروژه: '.$p->name);
            } elseif ($p->wasChanged('status')) {
                Customer\ProjectStatusChanged::dispatch($acc, $p->id, ['from' => $p->getOriginal('status'), 'to' => $p->status], auth()->id(), null, 'پروژه '.$p->name.': '.$p->status);
            }
        });

        // Signing is announced by HubContractController (ContractSigned); here only cancellation.
        self::on(Contract::class, function (Model $c, bool $created) {
            if (($acc = $c->customer_account_id ? (int) $c->customer_account_id : null) && in_array($c->status, ['cancelled', 'canceled', 'terminated'], true) && ($created || $c->wasChanged('status'))) {
                Customer\ContractCancelled::dispatch($acc, $c->id, ['status' => $c->status], auth()->id(), null, 'لغو قرارداد: '.($c->title ?? $c->id));
            }
        });

        // Marketplace: new order -> OrderPlaced; first paid_at -> PaymentRecorded (same subject as backfill "mo-" refs).
        self::on(MarketplaceOrder::class, function (Model $o, bool $created) {
            $acc = $o->crm_account_id ? (int) $o->crm_account_id : CustomerResolver::byUser($o->user_id ? (int) $o->user_id : null);
            if (! $acc) {
                return;
            }
            $label = 'سفارش '.($o->order_number ?? $o->id);
            $meta = ['total' => (float) ($o->total ?? 0), 'status' => $o->status, 'order_number' => $o->order_number];
            if ($created) {
                Customer\OrderPlaced::dispatch($acc, $o->id, $meta, auth()->id(), (string) ($o->created_at ?? now()), 'ثبت '.$label);
            }
            if ($o->paid_at && ($created || $o->wasChanged('paid_at'))) {
                Customer\PaymentRecorded::dispatch($acc, $o->id, $meta + ['subject_type' => 'marketplace_order', 'module' => 'marketplace', 'gateway' => $o->payment_gateway], auth()->id(), (string) $o->paid_at, 'پرداخت '.$label);
            }
        });

        // Gateway payments: settled intents -> PaymentRecorded keyed by intent id (backfill "pi-" refs).
        self::on(PaymentIntent::class, function (Model $p, bool $created) {
            if (! in_array($p->status, ['settled', 'verified', 'paid'], true) || (! $created && ! $p->wasChanged('status'))) {
                return;
            }
            if (! $acc = self::paymentAccount($p)) {
                return;
            }
            Customer\PaymentRecorded::dispatch($acc, $p->id, [
                'amount' => (float) ($p->total_amount ?? $p->base_amount ?? 0), 'gateway' => $p->gateway, 'payable_type' => $p->payable_type, 'payable_id' => $p->payable_id,
            ], $p->user_id ? (int) $p->user_id : null, (string) ($p->settled_at ?? $p->verified_at ?? now()), 'پرداخت آنلاین '.number_format((float) ($p->total_amount ?? 0)));
        });

        self::on(WebinoSiteProvision::class, function (Model $s, bool $created) {
            $acc = $s->crm_account_id ? (int) $s->crm_account_id : null;
            if ($acc && $s->status === 'ready' && ($created || $s->wasChanged('status'))) {
                Customer\SiteProvisioned::dispatch($acc, $s->id, ['domain' => $s->domain, 'slug' => $s->slug], $s->created_by ? (int) $s->created_by : null, (string) ($s->ready_at ?? now()), 'ایجاد سایت: '.($s->domain ?: $s->slug));
            }
        });

        // SMS log -> customer. Resolve before insert so the row carries crm_account_id.
        if (class_exists(CrmMessage::class)) {
            CrmMessage::creating(function (CrmMessage $m) {
                if (! $m->crm_account_id && Schema::hasColumn('crm_messages', 'crm_account_id')) {
                    $m->crm_account_id = CustomerResolver::byRelated($m->related_type, $m->related_id)
                        ?? ($m->channel === 'sms' ? CustomerResolver::byMobile($m->to_address) : null);
                }
            });
            CrmMessage::created(function (CrmMessage $m) {
                if ($m->crm_account_id && $m->channel === 'sms') {
                    Customer\SmsSent::dispatch((int) $m->crm_account_id, $m->id, ['to' => $m->to_address, 'status' => $m->status, 'provider' => $m->provider],
                        $m->created_by ? (int) $m->created_by : null, (string) $m->created_at, 'پیامک به '.$m->to_address.': '.mb_substr((string) $m->body, 0, 60));
                }
            });
        }

        // Basalam: auto-link a store to its customer by site host; mirrored orders -> timeline.
        if (class_exists(MarketplaceBasalamConnection::class)) {
            MarketplaceBasalamConnection::saving(function (Model $c) {
                if (empty($c->crm_account_id) && Schema::hasColumn('marketplace_basalam_connections', 'crm_account_id')) {
                    $c->crm_account_id = CustomerResolver::byDomain($c->site_url);
                }
            });
        }
        self::on(CrmBasalamOrder::class, function (Model $o, bool $created) {
            if ($o->crm_account_id && ($created || $o->wasChanged('status'))) {
                Customer\BasalamOrderSynced::dispatch((int) $o->crm_account_id, $o->id, ['external_id' => $o->external_id, 'status' => $o->status, 'total' => (float) $o->total],
                    null, (string) ($o->ordered_at ?? $o->created_at), 'سفارش باسلام #'.$o->external_id.($created ? '' : ' — '.$o->status));
            }
        });
    }

    public static function paymentAccount(Model $p): ?int
    {
        if (str_contains((string) $p->payable_type, 'nvoice') && $p->payable_id) {
            if (str_contains((string) $p->payable_type, 'sales') && ($inv = SalesInvoice::query()->find($p->payable_id)) && ($acc = self::invoiceAccount($inv))) {
                return $acc;
            }
        }
        if ($p->payable_type === 'marketplace_order' && Schema::hasTable('marketplace_orders')) {
            if ($acc = DB::table('marketplace_orders')->where('id', $p->payable_id)->value('crm_account_id')) {
                return (int) $acc;
            }
        }
        $meta = is_array($p->meta) ? $p->meta : (json_decode((string) $p->meta, true) ?: []);
        if (! empty($meta['crm_account_id'])) {
            return (int) $meta['crm_account_id'];
        }

        return CustomerResolver::byDomain($p->domain) ?? CustomerResolver::byUser($p->user_id ? (int) $p->user_id : null);
    }

    private static function on(string $class, \Closure $fn): void
    {
        if (! class_exists($class)) {
            return;
        }
        $class::created(fn (Model $m) => $fn($m, true));
        $class::updated(fn (Model $m) => $fn($m, false));
    }

    public static function invoiceAccount(Model $i): ?int
    {
        if ($i->customer_id && DB::table('crm_accounts')->where('id', $i->customer_id)->exists()) {
            return (int) $i->customer_id;
        }
        if (($i->project_id ?? null) && Schema::hasTable('prj_projects')) {
            $acc = DB::table('prj_projects')->where('id', $i->project_id)->value('customer_account_id');

            return $acc ? (int) $acc : null;
        }

        return null;
    }

    public static function ticketAccount(Model $t): ?int
    {
        if ($t->customer_account_id ?? null) {
            return (int) $t->customer_account_id;
        }
        if ($t->customer_user_id && Schema::hasTable('crm_account_users')) {
            $acc = DB::table('crm_account_users')->where('user_id', $t->customer_user_id)->value('account_id');

            return $acc ? (int) $acc : null;
        }

        return null;
    }
}
