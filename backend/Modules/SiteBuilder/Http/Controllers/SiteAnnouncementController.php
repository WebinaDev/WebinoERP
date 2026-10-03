<?php

namespace Modules\SiteBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Platform\Entities\PlatformTag;
use Modules\SiteBuilder\Entities\WebinoBusinessCategory;
use Modules\SiteBuilder\Entities\WebinoBusinessType;
use Modules\SiteBuilder\Entities\WebinoSiteAnnouncement;
use Modules\SiteBuilder\Entities\WebinoSiteAnnouncementDelivery;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteAnnouncementAudience;
use Modules\SiteBuilder\Services\SiteAnnouncementDispatcher;

class SiteAnnouncementController extends Controller
{
    public function __construct(
        private readonly SiteAnnouncementAudience $audience,
        private readonly SiteAnnouncementDispatcher $dispatcher,
    ) {}

    public function options(): JsonResponse
    {
        $sites = WebinoSiteProvision::query()
            ->with(['package.businessType.category'])
            ->where('status', '!=', WebinoSiteProvision::STATUS_CANCELLED)
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (WebinoSiteProvision $site) => $this->siteOption($site));

        $tags = [];
        if (class_exists(PlatformTag::class)) {
            $tags = PlatformTag::query()->orderBy('name')->pluck('name')->all();
        }
        foreach ($sites as $site) {
            foreach ($site['tags'] as $tag) {
                $tags[] = $tag;
            }
        }
        $tags = array_values(array_unique(array_map(fn ($tag) => (string) $tag, $tags)));
        sort($tags);

        return response()->json([
            'data' => [
                'sites' => $sites,
                'categories' => WebinoBusinessCategory::query()->orderBy('sort_order')->get(['id', 'slug', 'name_fa', 'name_en']),
                'types' => WebinoBusinessType::query()->orderBy('sort_order')->get(['id', 'category_id', 'slug', 'name_fa', 'name_en']),
                'tags' => $tags,
            ],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate($this->audienceRules());
        $announcement = new WebinoSiteAnnouncement([
            'audience' => $this->audience->normalize($data),
            'status' => WebinoSiteAnnouncement::STATUS_PUBLISHED,
        ]);
        $sites = $this->audience->matchingSites($announcement)->map(fn (WebinoSiteProvision $site) => $this->siteOption($site))->values();

        return response()->json([
            'data' => [
                'count' => $sites->count(),
                'sites' => $sites,
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        $rows = $this->queryWithCounts()->orderByDesc('id')->limit(100)->get();

        return response()->json([
            'data' => $rows->map(fn (WebinoSiteAnnouncement $row) => $this->present($row))->values(),
        ]);
    }

    public function show(WebinoSiteAnnouncement $siteAnnouncement): JsonResponse
    {
        $siteAnnouncement = $this->queryWithCounts()->findOrFail($siteAnnouncement->id);
        $siteAnnouncement->load('deliveries.site');

        $payload = $this->present($siteAnnouncement);
        $payload['deliveries'] = $siteAnnouncement->deliveries->map(function (WebinoSiteAnnouncementDelivery $delivery) {
            return [
                'id' => $delivery->id,
                'site_id' => $delivery->site_provision_id,
                'slug' => $delivery->site?->slug,
                'domain' => $delivery->site?->domain,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'last_error' => $delivery->last_error,
                'delivered_at' => optional($delivery->delivered_at)?->toIso8601String(),
                'read_at' => optional($delivery->read_at)?->toIso8601String(),
                'dismissed_at' => optional($delivery->dismissed_at)?->toIso8601String(),
            ];
        })->values();

        return response()->json(['data' => $payload]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $audience = $this->audience->normalize($data);
        if (($data['publish'] ?? false) === true) {
            $probe = new WebinoSiteAnnouncement(['audience' => $audience, 'status' => WebinoSiteAnnouncement::STATUS_PUBLISHED]);
            if ($this->audience->matchingSites($probe)->isEmpty()) {
                return response()->json(['message' => 'هیچ سایتی با این مخاطب پیدا نشد.'], 422);
            }
        }

        $row = WebinoSiteAnnouncement::query()->create([
            'title_fa' => $data['title_fa'],
            'title_en' => $data['title_en'] ?? null,
            'body_fa' => $data['body_fa'],
            'body_en' => $data['body_en'] ?? null,
            'level' => $data['level'] ?? 'info',
            'status' => WebinoSiteAnnouncement::STATUS_DRAFT,
            'audience' => $audience,
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        if (($data['publish'] ?? false) === true) {
            return $this->publishRow($row);
        }

        return response()->json(['data' => $this->present($this->reload($row))], 201);
    }

    public function update(Request $request, WebinoSiteAnnouncement $siteAnnouncement): JsonResponse
    {
        $data = $this->validated($request);
        $siteAnnouncement->fill([
            'title_fa' => $data['title_fa'],
            'title_en' => $data['title_en'] ?? null,
            'body_fa' => $data['body_fa'],
            'body_en' => $data['body_en'] ?? null,
            'level' => $data['level'] ?? $siteAnnouncement->level,
            'audience' => $this->audience->normalize($data),
            'expires_at' => $data['expires_at'] ?? null,
        ]);
        $siteAnnouncement->save();

        if ($siteAnnouncement->status === WebinoSiteAnnouncement::STATUS_PUBLISHED) {
            $this->dispatcher->retry($siteAnnouncement);
        }

        return response()->json(['data' => $this->present($this->reload($siteAnnouncement))]);
    }

    public function destroy(WebinoSiteAnnouncement $siteAnnouncement): JsonResponse
    {
        if ($siteAnnouncement->status !== WebinoSiteAnnouncement::STATUS_DRAFT) {
            return response()->json(['message' => 'فقط پیش‌نویس حذف می‌شود. اطلاعیه منتشرشده را بایگانی کنید.'], 422);
        }
        $siteAnnouncement->delete();

        return response()->json(['message' => 'حذف شد.']);
    }

    public function publish(WebinoSiteAnnouncement $siteAnnouncement): JsonResponse
    {
        return $this->publishRow($siteAnnouncement);
    }

    public function retry(WebinoSiteAnnouncement $siteAnnouncement): JsonResponse
    {
        if ($siteAnnouncement->status !== WebinoSiteAnnouncement::STATUS_PUBLISHED) {
            return response()->json(['message' => 'فقط اطلاعیه منتشرشده دوباره ارسال می‌شود.'], 422);
        }
        $this->dispatcher->retry($siteAnnouncement);

        return response()->json(['data' => $this->present($this->reload($siteAnnouncement))]);
    }

    public function archive(WebinoSiteAnnouncement $siteAnnouncement): JsonResponse
    {
        if ($siteAnnouncement->status === WebinoSiteAnnouncement::STATUS_DRAFT) {
            $siteAnnouncement->delete();

            return response()->json(['message' => 'پیش‌نویس حذف شد.']);
        }
        $this->dispatcher->archive($siteAnnouncement);

        return response()->json(['data' => $this->present($this->reload($siteAnnouncement))]);
    }

    private function publishRow(WebinoSiteAnnouncement $row): JsonResponse
    {
        $matches = $this->audience->matchingSites($row);
        if ($matches->isEmpty()) {
            return response()->json(['message' => 'هیچ سایتی با این مخاطب پیدا نشد.'], 422);
        }

        $this->dispatcher->publish($row);

        return response()->json(['data' => $this->present($this->reload($row))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate(array_merge([
            'title_fa' => 'required|string|max:191',
            'title_en' => 'nullable|string|max:191',
            'body_fa' => 'required|string|max:20000',
            'body_en' => 'nullable|string|max:20000',
            'level' => ['nullable', Rule::in(WebinoSiteAnnouncement::LEVELS)],
            'expires_at' => 'nullable|date',
            'publish' => 'nullable|boolean',
        ], $this->audienceRules()));
    }

    /**
     * @return array<string, mixed>
     */
    private function audienceRules(): array
    {
        return [
            'audience_mode' => ['required', Rule::in([
                WebinoSiteAnnouncement::AUDIENCE_ALL,
                WebinoSiteAnnouncement::AUDIENCE_SITES,
                WebinoSiteAnnouncement::AUDIENCE_CATEGORY,
                WebinoSiteAnnouncement::AUDIENCE_TYPE,
                WebinoSiteAnnouncement::AUDIENCE_TAG,
            ])],
            'site_ids' => 'required_if:audience_mode,sites|array',
            'site_ids.*' => 'integer|exists:webino_site_provisions,id',
            'category_ids' => 'required_if:audience_mode,category|array|min:1',
            'category_ids.*' => 'integer|exists:webino_business_categories,id',
            'type_ids' => 'required_if:audience_mode,type|array|min:1',
            'type_ids.*' => 'integer|exists:webino_business_types,id',
            'tags' => 'required_if:audience_mode,tag|array|min:1',
            'tags.*' => 'string|max:64',
        ];
    }

    private function queryWithCounts()
    {
        return WebinoSiteAnnouncement::query()->withCount([
            'deliveries',
            'deliveries as delivered_count' => fn ($q) => $q->where('status', WebinoSiteAnnouncementDelivery::STATUS_DELIVERED),
            'deliveries as pending_count' => fn ($q) => $q->where('status', WebinoSiteAnnouncementDelivery::STATUS_PENDING),
            'deliveries as failed_count' => fn ($q) => $q->where('status', WebinoSiteAnnouncementDelivery::STATUS_FAILED),
            'deliveries as revoked_count' => fn ($q) => $q->where('status', WebinoSiteAnnouncementDelivery::STATUS_REVOKED),
            'deliveries as read_count' => fn ($q) => $q->whereNotNull('read_at'),
            'deliveries as dismissed_count' => fn ($q) => $q->whereNotNull('dismissed_at'),
        ]);
    }

    private function reload(WebinoSiteAnnouncement $row): WebinoSiteAnnouncement
    {
        return $this->queryWithCounts()->findOrFail($row->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WebinoSiteAnnouncement $row): array
    {
        return [
            'id' => $row->id,
            'title_fa' => $row->title_fa,
            'title_en' => $row->title_en,
            'body_fa' => $row->body_fa,
            'body_en' => $row->body_en,
            'level' => $row->level,
            'status' => $row->status,
            'audience' => $row->audience,
            'published_at' => optional($row->published_at)?->toIso8601String(),
            'expires_at' => optional($row->expires_at)?->toIso8601String(),
            'created_at' => optional($row->created_at)?->toIso8601String(),
            'updated_at' => optional($row->updated_at)?->toIso8601String(),
            'delivery' => [
                'total' => (int) ($row->deliveries_count ?? 0),
                'delivered' => (int) ($row->delivered_count ?? 0),
                'pending' => (int) ($row->pending_count ?? 0),
                'failed' => (int) ($row->failed_count ?? 0),
                'revoked' => (int) ($row->revoked_count ?? 0),
                'read' => (int) ($row->read_count ?? 0),
                'dismissed' => (int) ($row->dismissed_count ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function siteOption(WebinoSiteProvision $site): array
    {
        $wizard = is_array($site->wizard_payload) ? $site->wizard_payload : [];

        return [
            'id' => $site->id,
            'slug' => $site->slug,
            'domain' => $site->domain,
            'status' => $site->status,
            'site_type_slug' => $wizard['site_type_slug'] ?? $site->package?->businessType?->slug,
            'category_id' => $wizard['business_category_id'] ?? $site->package?->businessType?->category_id,
            'category_slug' => $wizard['business_category_slug'] ?? $site->package?->businessType?->category?->slug,
            'tags' => $this->audience->siteTags($site),
        ];
    }
}
