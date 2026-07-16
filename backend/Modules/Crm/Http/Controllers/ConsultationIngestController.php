<?php

namespace Modules\Crm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmConsultation;

class ConsultationIngestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $secret = (string) config('services.webino.provision_hmac_secret', env('WEBINO_PROVISION_HMAC_SECRET', ''));
        if ($secret === '') {
            $secret = (string) config('app.webinocrm_license_hmac_secret', env('WEBINOCRM_LICENSE_HMAC_SECRET', ''));
        }

        $token = (string) $request->header('X-Site-Token', '');
        $sig = (string) $request->header('X-Webino-Signature', '');
        $raw = $request->getContent();

        if ($secret === '') {
            return response()->json(['message' => 'HMAC secret not configured'], 503);
        }

        if ($sig === '' || ! hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        if ($token === '') {
            return response()->json(['message' => 'Missing site token'], 401);
        }

        $data = $request->validate([
            'tenant_domain' => 'nullable|string|max:255',
            'crm_account_id' => 'nullable|integer',
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:32',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:5000',
            'site_consultation_id' => 'nullable|integer',
        ]);

        $title = $data['subject'] ?: ('Consultation from '.$data['name']);
        $notes = collect([
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? null,
            'tenant_domain' => $data['tenant_domain'] ?? null,
            'site_consultation_id' => $data['site_consultation_id'] ?? null,
        ])->filter()->toJson(JSON_UNESCAPED_UNICODE);

        $row = CrmConsultation::query()->create([
            'title' => $title,
            'account_id' => $data['crm_account_id'] ?? null,
            'status' => 'new',
            'notes' => $notes,
            'created_by' => null,
        ]);

        return response()->json([
            'data' => [
                'consultation_id' => $row->id,
                'duplicate' => false,
            ],
        ], 201);
    }
}
