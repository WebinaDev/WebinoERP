<?php

namespace Modules\Crm\Services\Hub;

use App\Support\MutationAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Services\CrmMergeService;

/**
 * Customer 360 merge: moves every customer reference (hub tables + cross-module
 * links) from $source onto $target, then delegates contacts/deals/projects/tickets/
 * portal users to CrmMergeService, which soft-deletes the source.
 * The source keeps merged_into_id so old links can be redirected.
 */
class CustomerMergeService
{
    /** [table, column] pairs pointing at crm_accounts.id (guarded by hasColumn). */
    public const LINKS = [
        ['core_licenses', 'crm_account_id'],
        ['sales_invoices', 'customer_id'],
        ['marketplace_orders', 'crm_account_id'],
        ['marketplace_basalam_connections', 'crm_account_id'],
        ['platform_projects', 'crm_account_id'],
        ['platform_resources', 'crm_account_id'],
        ['docs_contracts', 'crm_account_id'],
        ['docs_files', 'crm_account_id'],
        ['webino_site_provisions', 'crm_account_id'],
        ['prj_contracts', 'customer_account_id'],
        ['prj_pro_invoices', 'customer_account_id'],
        ['crm_leads', 'converted_to_account_id'],
        ['crm_account_addresses', 'account_id'],
        ['crm_customer_documents', 'account_id'],
        ['crm_customer_domains', 'account_id'],
        ['crm_customer_services', 'account_id'],
        ['crm_customer_alerts', 'account_id'],
        ['crm_customer_audit', 'account_id'],
        ['crm_customer_onboarding_items', 'account_id'],
        ['crm_customer_usage_metrics', 'account_id'],
        ['crm_customer_site_links', 'account_id'],
        ['crm_customer_activities', 'account_id'],
        ['crm_accounts', 'parent_id'],
        ['crm_accounts', 'referrer_account_id'],
        ['crm_accounts', 'partner_account_id'],
        ['crm_accounts', 'merged_into_id'],
    ];

    public function __construct(private CrmMergeService $base) {}

    /** @return array{account: CrmAccount, moved: array<string,int>} */
    public function merge(int $targetId, int $sourceId, ?int $userId): array
    {
        abort_if($targetId === $sourceId, 422, __('messages.choose_two_different_accounts'));
        $target = CrmAccount::query()->findOrFail($targetId);
        $source = CrmAccount::query()->findOrFail($sourceId);
        abort_if($target->merged_into_id !== null, 422, 'Target account was itself merged.');

        $moved = [];
        $account = null;
        DB::transaction(function () use ($target, $source, $userId, &$moved, &$account) {
            // Relations: drop rows that would become self-links or duplicates, then repoint.
            if (Schema::hasTable('crm_customer_relations')) {
                $rel = DB::table('crm_customer_relations');
                (clone $rel)->where(fn ($q) => $q->where('account_id', $source->id)->where('related_account_id', $target->id))
                    ->orWhere(fn ($q) => $q->where('account_id', $target->id)->where('related_account_id', $source->id))->delete();
                foreach (['account_id' => 'related_account_id', 'related_account_id' => 'account_id'] as $col => $other) {
                    foreach (DB::table('crm_customer_relations')->where($col, $source->id)->get() as $row) {
                        $dup = DB::table('crm_customer_relations')->where($col, $target->id)
                            ->where($other, $row->{$other})->where('relation_type', $row->relation_type)->exists();
                        $dup ? DB::table('crm_customer_relations')->where('id', $row->id)->delete()
                            : DB::table('crm_customer_relations')->where('id', $row->id)->update([$col => $target->id]);
                        $moved['crm_customer_relations'] = ($moved['crm_customer_relations'] ?? 0) + 1;
                    }
                }
            }
            // Only one default address per type survives.
            if (Schema::hasTable('crm_account_addresses')) {
                $types = DB::table('crm_account_addresses')->where('account_id', $target->id)->where('is_default', true)->pluck('type');
                DB::table('crm_account_addresses')->where('account_id', $source->id)->whereIn('type', $types)->update(['is_default' => false]);
            }
            foreach (self::LINKS as [$table, $col]) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $col)) {
                    continue;
                }
                $q = DB::table($table)->where($col, $source->id);
                if ($table === 'crm_accounts') {
                    $q->where('id', '!=', $target->id);
                }
                if ($n = $q->update([$col => $target->id])) {
                    $moved[$table.'.'.$col] = $n;
                }
            }
            // Fill empty profile fields on the target from the source.
            $fill = [];
            foreach (['brand_name', 'national_id', 'economic_code', 'registration_no', 'phone', 'mobile', 'email', 'website', 'postal_code',
                'address_line', 'city', 'province', 'industry', 'logo_url', 'account_manager_id', 'source', 'segment'] as $f) {
                if (blank($target->{$f}) && filled($source->{$f})) {
                    $fill[$f] = $source->{$f};
                }
            }
            if (blank($target->iban) && filled($source->iban)) {
                $fill['iban'] = $source->iban;
            }
            $tags = array_values(array_unique(array_merge((array) $target->tags, (array) $source->tags)));
            if ($tags !== array_values((array) $target->tags)) {
                $fill['tags'] = $tags;
            }
            $fill['custom_fields'] = ((array) $target->custom_fields) + ((array) $source->custom_fields) ?: null;
            if ($source->lifetime_value !== null) {
                $fill['lifetime_value'] = (float) $target->lifetime_value + (float) $source->lifetime_value;
            }
            $target->fill($fill)->save();

            $source->forceFill(['merged_into_id' => $target->id])->save();
            DB::table('crm_customer_audit')->insert([
                'account_id' => $target->id, 'user_id' => $userId, 'action' => 'customer.merge',
                'changes' => json_encode(['source_id' => $source->id, 'source_name' => $source->name, 'moved' => $moved, 'filled' => array_keys($fill)], JSON_UNESCAPED_UNICODE),
                'ip' => request()?->ip(), 'created_at' => now(),
            ]);
            MutationAudit::record($userId, 'crm', 'customer_hub.merge', CrmAccount::class, $target->id, ['source_id' => $source->id, 'moved' => $moved]);

            // Contacts, deals, consultations, projects, tickets, appointments, portal users, CRM activities; soft-deletes source.
            $account = $this->base->mergeAccounts($target->id, $source->id, $userId);
        });

        return ['account' => $account, 'moved' => $moved];
    }
}
