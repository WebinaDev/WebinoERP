<?php

namespace Modules\Crm\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Events\Customer\ContractSigned;
use Modules\Crm\Events\Customer\CustomerActivityEvent;
use Modules\Crm\Events\Customer\InvoiceIssued;
use Modules\Crm\Events\Customer\InvoiceOverdue;
use Modules\Crm\Events\Customer\InvoicePaid;
use Modules\Crm\Events\CustomerHubUpdated;
use Modules\Crm\Services\Hub\CustomerActivityRecorder;

/**
 * One queued, idempotent sink for every customer sync event (PLAN §4.4).
 * Re-delivery is harmless: writes go through dedupe_key, aggregates are recomputed not incremented.
 */
class RecordCustomerActivity implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private CustomerActivityRecorder $recorder) {}

    public function handle(CustomerActivityEvent $e): void
    {
        $this->recorder->record($e->activity());

        if ($e instanceof InvoicePaid || $e instanceof InvoiceIssued) {
            $this->refreshLifetimeValue($e->accountId);
        }
        if ($e instanceof InvoiceOverdue && Schema::hasColumn('crm_accounts', 'hub_status')) {
            DB::table('crm_accounts')->where('id', $e->accountId)->whereIn('hub_status', ['active', 'lead'])->update(['hub_status' => 'debtor']);
        }
    }

    public function contractSigned(ContractSigned $e): void
    {
        $title = Schema::hasTable('prj_contracts') ? DB::table('prj_contracts')->where('id', $e->contractId)->value('title') : null;
        $this->recorder->record([
            'account_id' => $e->accountId, 'module' => 'contracts', 'type' => 'contract.signed', 'title' => 'امضای قرارداد'.($title ? ': '.$title : ''),
            'subject_type' => 'prj_contract', 'subject_id' => $e->contractId, 'actor_id' => $e->byUserId,
            'meta' => array_filter(['document_id' => $e->documentId]),
        ]);
    }

    /** Every hub write already audits + broadcasts; mirror it into the timeline keyed by the audit row. */
    public function hubUpdated(CustomerHubUpdated $e): void
    {
        if ($e->action === 'customer.merge' || ! Schema::hasTable('crm_customer_audit')) {
            return; // merge is recorded via CustomerMerged
        }
        $audit = DB::table('crm_customer_audit')->where('account_id', $e->accountId)->where('action', $e->action)->orderByDesc('id')->first();
        $this->recorder->record([
            'account_id' => $e->accountId, 'module' => 'crm', 'type' => 'hub.'.$e->action, 'title' => $e->action,
            'subject_type' => $audit ? 'crm_customer_audit' : null, 'subject_id' => $audit?->id, 'actor_id' => $e->byUserId,
            'occurred_at' => $audit?->created_at, 'dedupe_key' => $audit ? CustomerActivityRecorder::key('crm_customer_audit', $audit->id, 'hub.'.$e->action) : null,
        ]);
    }

    private function refreshLifetimeValue(int $accountId): void
    {
        if (! Schema::hasColumn('crm_accounts', 'lifetime_value') || ! Schema::hasColumn('sales_invoices', 'customer_id')) {
            return;
        }
        $col = Schema::hasColumn('sales_invoices', 'total') ? 'total' : (Schema::hasColumn('sales_invoices', 'grand_total') ? 'grand_total' : null);
        if (! $col) {
            return;
        }
        $sum = (float) DB::table('sales_invoices')->where('customer_id', $accountId)->where('status', 'paid')->sum($col);
        DB::table('crm_accounts')->where('id', $accountId)->update(['lifetime_value' => $sum]);
    }
}
