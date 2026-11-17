<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformDnsRecord;
use Modules\Platform\Entities\PlatformDomain;
use Modules\Platform\Services\DomainOperationsService;

class DomainOperationsController extends Controller
{
    public function records(PlatformDomain $domain): JsonResponse
    {
        $records = $domain->dnsRecords()->orderBy('type')->orderBy('name')->get();

        return response()->json(['data' => $records]);
    }

    public function upsertRecord(Request $request, PlatformDomain $domain, DomainOperationsService $service): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|in:A,AAAA,CNAME,TXT,MX,SRV,NS',
            'name' => 'required|string|max:191',
            'value' => 'required|string',
            'ttl' => 'nullable|integer|min:60',
            'priority' => 'nullable|integer',
            'is_proxied' => 'nullable|boolean',
        ]);

        $record = $service->upsertDnsRecord($domain, $data);

        return response()->json([
            'data' => $record,
            'message' => 'رکورد DNS با موفقیت ثبت/به‌روزرسانی شد.',
        ], 200);
    }

    public function deleteRecord(PlatformDomain $domain, PlatformDnsRecord $record): JsonResponse
    {
        if ($record->domain_id !== $domain->id) {
            return response()->json(['message' => 'رکورد متعلق به این دامنه نیست.'], 403);
        }

        $record->delete();

        return response()->json(['message' => 'رکورد DNS حذف شد.']);
    }

    public function checkUptime(PlatformDomain $domain, DomainOperationsService $service): JsonResponse
    {
        $check = $service->checkDomainUptime($domain);

        return response()->json([
            'data' => $check,
            'message' => 'تست سلامت و آپ‌تایم دامنه با موفقیت انجام شد.',
        ]);
    }

    public function inspectSsl(PlatformDomain $domain, DomainOperationsService $service): JsonResponse
    {
        $sslInfo = $service->inspectSslCertificate($domain);

        return response()->json([
            'data' => $sslInfo,
            'message' => 'اطلاعات گواهی SSL و تاریخ انقضا با موفقیت استعلام شد.',
        ]);
    }

    public function customerDomains(Request $request, int $customerId): JsonResponse
    {
        $domains = PlatformDomain::query()
            ->where('crm_customer_id', $customerId)
            ->with(['dnsRecords', 'mailDomain'])
            ->get();

        return response()->json(['data' => $domains]);
    }
}
