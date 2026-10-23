<?php

namespace Modules\Crm\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Events\Customer;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\Project;
use Modules\Sales\Entities\SalesInvoice;

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
