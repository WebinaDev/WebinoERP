<?php

namespace Modules\Crm\Services;

use App\Support\MutationAudit;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmActivity;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Support\CrmRelation;

class CrmMergeService
{
    public function mergeAccounts(int $primaryId, int $duplicateId, ?int $userId): CrmAccount
    {
        abort_if($primaryId === $duplicateId, 422, 'Choose two different accounts');
        $primary = CrmAccount::query()->findOrFail($primaryId);
        $duplicate = CrmAccount::query()->findOrFail($duplicateId);

        DB::transaction(function () use ($primary, $duplicate, $userId) {
            CrmContact::query()->where('account_id', $duplicate->id)->update(['account_id' => $primary->id]);
            CrmDeal::query()->where('account_id', $duplicate->id)->update(['account_id' => $primary->id]);
            DB::table('crm_consultations')->where('account_id', $duplicate->id)->update(['account_id' => $primary->id]);
            DB::table('prj_projects')->where('customer_account_id', $duplicate->id)->update(['customer_account_id' => $primary->id]);
            if (DB::getSchemaBuilder()->hasColumn('prj_tickets', 'customer_account_id')) {
                DB::table('prj_tickets')->where('customer_account_id', $duplicate->id)->update(['customer_account_id' => $primary->id]);
            }
            DB::table('prj_appointments')->where('customer_account_id', $duplicate->id)->update(['customer_account_id' => $primary->id]);
            if (DB::getSchemaBuilder()->hasTable('crm_account_users')) {
                $links = DB::table('crm_account_users')->where('account_id', $duplicate->id)->get();
                foreach ($links as $link) {
                    $exists = DB::table('crm_account_users')
                        ->where('account_id', $primary->id)
                        ->where('user_id', $link->user_id)
                        ->exists();
                    if ($exists) {
                        DB::table('crm_account_users')->where('id', $link->id)->delete();
                    } else {
                        DB::table('crm_account_users')->where('id', $link->id)->update(['account_id' => $primary->id]);
                    }
                }
            }
            CrmActivity::query()
                ->where('related_id', $duplicate->id)
                ->whereIn('related_model', CrmRelation::storedNames('account'))
                ->update(['related_id' => $primary->id, 'related_model' => CrmAccount::class]);

            $duplicate->delete();
            MutationAudit::record($userId, 'crm', 'account.merge', CrmAccount::class, $primary->id, [
                'duplicate_id' => $duplicate->id,
                'duplicate_name' => $duplicate->name,
            ]);
        });

        return $primary->fresh();
    }

    public function mergeContacts(int $primaryId, int $duplicateId, ?int $userId): CrmContact
    {
        abort_if($primaryId === $duplicateId, 422, 'Choose two different contacts');
        $primary = CrmContact::query()->findOrFail($primaryId);
        $duplicate = CrmContact::query()->findOrFail($duplicateId);

        DB::transaction(function () use ($primary, $duplicate, $userId) {
            CrmDeal::query()->where('contact_id', $duplicate->id)->update(['contact_id' => $primary->id]);
            CrmActivity::query()
                ->where('related_id', $duplicate->id)
                ->whereIn('related_model', CrmRelation::storedNames('contact'))
                ->update(['related_id' => $primary->id, 'related_model' => CrmContact::class]);
            if (! $primary->email && $duplicate->email) {
                $primary->email = $duplicate->email;
            }
            if (! $primary->mobile && $duplicate->mobile) {
                $primary->mobile = $duplicate->mobile;
            }
            if (! $primary->phone && $duplicate->phone) {
                $primary->phone = $duplicate->phone;
            }
            $primary->save();
            $duplicate->delete();
            MutationAudit::record($userId, 'crm', 'contact.merge', CrmContact::class, $primary->id, [
                'duplicate_id' => $duplicate->id,
            ]);
        });

        return $primary->fresh();
    }
}
