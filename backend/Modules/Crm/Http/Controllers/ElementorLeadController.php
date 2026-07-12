<?php

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmStatus;

class ElementorLeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $secret = (string) config('integrations.elementor.secret', env('WEBINOCRM_ELEMENTOR_SECRET', ''));
        $sig = (string) $request->header('X-Webino-Signature', '');
        $raw = $request->getContent();
        if ($secret !== '' && ! hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $data = $request->validate([
            'email' => 'nullable|email',
            'topic' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'mobile' => 'nullable|string|max:30',
            'source' => 'nullable|string|max:100',
        ]);

        $dup = null;
        if (! empty($data['email'])) {
            $dup = CrmLead::query()->where('email', $data['email'])->first();
        }
        if ($dup) {
            return response()->json(['data' => ['lead_id' => $dup->id, 'duplicate' => true]], 200);
        }

        $statusId = CrmStatus::query()->orderBy('id')->value('id');
        if (! $statusId) {
            return response()->json(['message' => 'No CRM status configured'], 500);
        }

        $lead = CrmLead::query()->create([
            'topic' => $data['topic'] ?? 'Elementor',
            'email' => $data['email'] ?? null,
            'first_name' => $data['first_name'] ?? '-',
            'last_name' => $data['last_name'] ?? '-',
            'mobile' => $data['mobile'] ?? '-',
            'description' => json_encode($request->except(['recaptcha_token']), JSON_UNESCAPED_UNICODE),
            'status_id' => $statusId,
            'created_by' => null,
        ]);

        return response()->json(['data' => ['lead_id' => $lead->id, 'duplicate' => false]], 201);
    }
}
