<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformMailDomain;
use Modules\Platform\Entities\PlatformMailbox;
use Modules\Platform\Services\DomainMailService;

class DomainMailController extends Controller
{
    public function domains(Request $request): JsonResponse
    {
        $rows = PlatformMailDomain::query()->withCount('mailboxes')->get();

        return response()->json(['data' => $rows]);
    }

    public function setup(Request $request, DomainMailService $service): JsonResponse
    {
        $data = $request->validate([
            'domain_name' => 'required|string|max:191',
            'domain_id' => 'nullable|integer',
            'mail_host' => 'nullable|string|max:191',
        ]);

        $mailDomain = $service->setupMailDomain(
            $data['domain_name'],
            $data['domain_id'] ?? null,
            $data['mail_host'] ?? 'mail.webinoerp.com'
        );

        return response()->json([
            'data' => $mailDomain,
            'message' => 'دامنه ایمیل با موفقیت ثبت شد و کلیدهای DKIM و رکوردهای DNS تولید گردید.',
        ], 201);
    }

    public function checkDns(PlatformMailDomain $mailDomain, DomainMailService $service): JsonResponse
    {
        $result = $service->checkDnsStatus($mailDomain);

        return response()->json(['data' => $result]);
    }

    public function mailboxes(PlatformMailDomain $mailDomain): JsonResponse
    {
        $rows = $mailDomain->mailboxes()->orderBy('local_part')->get();

        return response()->json(['data' => $rows]);
    }

    public function createMailbox(Request $request, PlatformMailDomain $mailDomain): JsonResponse
    {
        $data = $request->validate([
            'local_part' => 'required|string|max:100|alpha_dash',
            'display_name' => 'nullable|string|max:150',
            'password' => 'required|string|min:8',
            'quota_mb' => 'nullable|integer|min:100',
        ]);

        $email = strtolower($data['local_part']) . '@' . $mailDomain->domain_name;
        if (PlatformMailbox::query()->where('email_address', $email)->exists()) {
            return response()->json(['message' => 'این آدرس ایمیل قبلاً ایجاد شده است.'], 422);
        }

        $quotaBytes = ((int) ($data['quota_mb'] ?? 1024)) * 1024 * 1024;

        $mailbox = PlatformMailbox::create([
            'mail_domain_id' => $mailDomain->id,
            'email_address' => $email,
            'local_part' => strtolower($data['local_part']),
            'display_name' => $data['display_name'] ?? $data['local_part'],
            'password_hash' => bcrypt($data['password']),
            'quota_bytes' => $quotaBytes,
            'is_active' => true,
        ]);

        return response()->json([
            'data' => $mailbox,
            'message' => "صندوق پستی {$email} با موفقیت ایجاد گردید.",
        ], 201);
    }
}
