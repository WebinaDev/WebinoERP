<?php

namespace Modules\Crm\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\CoreNotification;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmPipeline;
use Modules\Crm\Entities\CrmStage;

class LeadConversionService
{
    public function convert(CrmLead $lead, array $options = []): array
    {
        if ($lead->converted_at) {
            throw new \InvalidArgumentException('Lead already converted');
        }

        return DB::transaction(function () use ($lead, $options) {
            $existingId = $options['existing_account_id'] ?? null;
            if ($existingId) {
                $account = CrmAccount::query()->findOrFail($existingId);
            } else {
                $account = CrmAccount::query()->create([
                    'name' => $lead->company ?: trim($lead->first_name.' '.$lead->last_name),
                    'type' => 'customer',
                    'owner_id' => $lead->assigned_to,
                    'created_by' => auth()->id(),
                    'industry' => $lead->industry,
                ]);
            }

            $contact = null;
            if ($options['create_contact'] ?? true) {
                $contact = CrmContact::query()->create([
                    'account_id' => $account->id,
                    'first_name' => $lead->first_name,
                    'last_name' => $lead->last_name,
                    'email' => $lead->email,
                    'phone' => $lead->mobile ?: $lead->phone,
                    'created_by' => auth()->id(),
                ]);
            }

            $deal = null;
            if ($options['create_deal'] ?? false) {
                $pipeline = CrmPipeline::query()->where('is_active', true)->orderBy('id')->first();
                $stage = $pipeline
                    ? CrmStage::query()->where('pipeline_id', $pipeline->id)->orderBy('sort_order')->first()
                    : null;
                if ($pipeline && $stage) {
                    $deal = CrmDeal::query()->create([
                        'name' => $lead->topic ?: ($account->name.' deal'),
                        'account_id' => $account->id,
                        'pipeline_id' => $pipeline->id,
                        'stage_id' => $stage->id,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $lead->update([
                'converted_at' => now(),
                'converted_to_account_id' => $account->id,
            ]);

            $userId = auth()->id() ?? $lead->assigned_to;
            if ($userId) {
                CoreNotification::query()->create([
                    'user_id' => $userId,
                    'type' => 'crm.lead.converted',
                    'data' => [
                        'title' => 'Lead converted',
                        'body' => sprintf('Lead #%d converted to account #%d', $lead->id, $account->id),
                        'lead_id' => $lead->id,
                        'account_id' => $account->id,
                    ],
                    'is_read' => false,
                ]);
            }

            return [
                'account' => $account,
                'contact' => $contact,
                'deal' => $deal,
            ];
        });
    }
}
