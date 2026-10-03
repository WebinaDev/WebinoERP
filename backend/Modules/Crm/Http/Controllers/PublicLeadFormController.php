<?php

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmAttribution;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmLeadForm;
use Modules\Crm\Entities\CrmStatus;
use Modules\Integrations\Services\NotificationFanout;

class PublicLeadFormController extends Controller
{
    public function store(Request $request, string $slug, NotificationFanout $fanout): JsonResponse
    {
        $form = CrmLeadForm::query()->where('slug', $slug)->where('active', true)->firstOrFail();
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email',
            'mobile' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:150',
            'topic' => 'nullable|string|max:255',
            'utm_source' => 'nullable|string|max:120',
            'utm_medium' => 'nullable|string|max:120',
            'utm_campaign' => 'nullable|string|max:120',
            'utm_content' => 'nullable|string|max:120',
            'utm_term' => 'nullable|string|max:120',
            'referrer' => 'nullable|string|max:255',
            'landing_path' => 'nullable|string|max:255',
        ]);

        $statusId = CrmStatus::query()->orderBy('sort_order')->value('id');
        if (! $statusId) {
            $statusId = CrmStatus::query()->create([
                'name' => 'New',
                'color' => '#64748b',
                'sort_order' => 0,
                'is_active' => true,
            ])->id;
        }
        $lead = CrmLead::query()->create([
            'topic' => $data['topic'] ?? $form->name,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'] ?? '-',
            'company' => $data['company'] ?? null,
            'description' => 'Public form '.$form->slug,
            'status_id' => $statusId,
        ]);
        $attribution = CrmAttribution::query()->create([
            'lead_id' => $lead->id,
            'form_id' => $form->id,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'referrer' => $data['referrer'] ?? null,
            'landing_path' => $data['landing_path'] ?? $form->landing_url,
        ]);

        if ($form->notify_user_id) {
            $fanout->dispatch([
                'title' => 'سرنخ جدید',
                'body' => $lead->first_name.' '.$lead->last_name,
                'priority' => 'normal',
                'event_key' => 'lead.created',
                'user_id' => $form->notify_user_id,
                'channels' => ['in_app', 'chat', 'sms'],
            ]);
        }

        return response()->json(['data' => ['lead_id' => $lead->id, 'attribution_id' => $attribution->id]], 201);
    }
}
