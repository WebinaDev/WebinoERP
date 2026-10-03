<?php

namespace Modules\SiteBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteAnnouncementDispatcher;

class SiteAnnouncementSyncController extends Controller
{
    public function __construct(private readonly SiteAnnouncementDispatcher $dispatcher) {}

    public function pull(Request $request): JsonResponse
    {
        $site = $this->site($request);

        return response()->json([
            'data' => [
                'site_id' => $site->id,
                'slug' => $site->slug,
                'announcements' => $this->dispatcher->payloadsForSite($site),
            ],
        ]);
    }

    public function receipt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'external_key' => ['required', 'string', 'regex:/^erp-site-announcement:\d+$/'],
            'event' => 'required|string|in:read,dismissed,delivered',
            'occurred_at' => 'nullable|date',
        ]);

        $delivery = $this->dispatcher->recordReceipt($this->site($request), $data['external_key'], $data['event']);
        if (! $delivery) {
            return response()->json(['message' => 'اطلاعیه برای این سایت پیدا نشد.'], 404);
        }

        return response()->json([
            'data' => [
                'ok' => true,
                'external_key' => $data['external_key'],
                'read_at' => optional($delivery->read_at)?->toIso8601String(),
                'dismissed_at' => optional($delivery->dismissed_at)?->toIso8601String(),
            ],
        ]);
    }

    private function site(Request $request): WebinoSiteProvision
    {
        $site = $request->attributes->get('site_provision');
        if (! $site instanceof WebinoSiteProvision) {
            abort(403, 'توکن پروویژن نامعتبر است.');
        }

        return $site;
    }
}
