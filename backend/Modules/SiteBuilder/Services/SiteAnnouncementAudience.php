<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Platform\Entities\PlatformResource;
use Modules\SiteBuilder\Entities\WebinoBusinessCategory;
use Modules\SiteBuilder\Entities\WebinoBusinessType;
use Modules\SiteBuilder\Entities\WebinoSiteAnnouncement;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

class SiteAnnouncementAudience
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        $mode = (string) ($input['audience_mode'] ?? $input['mode'] ?? WebinoSiteAnnouncement::AUDIENCE_ALL);
        $siteIds = $this->ints($input['site_ids'] ?? []);
        $categoryIds = $this->ints($input['category_ids'] ?? []);
        $typeIds = $this->ints($input['type_ids'] ?? []);
        $tags = $this->tags($input['tags'] ?? []);

        $categorySlugs = $categoryIds === []
            ? []
            : WebinoBusinessCategory::query()->whereIn('id', $categoryIds)->pluck('slug')->map(fn ($s) => strtolower((string) $s))->all();
        $typeSlugs = $typeIds === []
            ? []
            : WebinoBusinessType::query()->whereIn('id', $typeIds)->pluck('slug')->map(fn ($s) => strtolower((string) $s))->all();

        return [
            'mode' => $mode,
            'site_ids' => $siteIds,
            'category_ids' => $categoryIds,
            'category_slugs' => array_values($categorySlugs),
            'type_ids' => $typeIds,
            'type_slugs' => array_values($typeSlugs),
            'tags' => $tags,
        ];
    }

    /**
     * @return Collection<int, WebinoSiteProvision>
     */
    public function matchingSites(WebinoSiteAnnouncement $announcement): Collection
    {
        $audience = is_array($announcement->audience) ? $announcement->audience : [];
        $mode = (string) ($audience['mode'] ?? WebinoSiteAnnouncement::AUDIENCE_ALL);

        $query = WebinoSiteProvision::query()->with(['package.businessType.category']);
        if ($mode === WebinoSiteAnnouncement::AUDIENCE_SITES) {
            $ids = $this->ints($audience['site_ids'] ?? []);
            if ($ids === []) {
                return collect();
            }
            $query->whereIn('id', $ids);
        } else {
            $query->whereNotIn('status', [
                WebinoSiteProvision::STATUS_DRAFT,
                WebinoSiteProvision::STATUS_CANCELLED,
            ]);
        }

        return $query->orderBy('id')->get()->filter(
            fn (WebinoSiteProvision $site) => $this->matches($announcement, $site)
        )->values();
    }

    public function matches(WebinoSiteAnnouncement $announcement, WebinoSiteProvision $site): bool
    {
        $audience = is_array($announcement->audience) ? $announcement->audience : [];
        $mode = (string) ($audience['mode'] ?? WebinoSiteAnnouncement::AUDIENCE_ALL);

        return match ($mode) {
            WebinoSiteAnnouncement::AUDIENCE_SITES => in_array($site->id, $this->ints($audience['site_ids'] ?? []), true),
            WebinoSiteAnnouncement::AUDIENCE_CATEGORY => $this->categoryMatches($audience, $site),
            WebinoSiteAnnouncement::AUDIENCE_TYPE => $this->typeMatches($audience, $site),
            WebinoSiteAnnouncement::AUDIENCE_TAG => $this->tagMatches($audience, $site),
            default => ! in_array($site->status, [
                WebinoSiteProvision::STATUS_DRAFT,
                WebinoSiteProvision::STATUS_CANCELLED,
            ], true),
        };
    }

    /**
     * @param  array<string, mixed>  $audience
     */
    private function categoryMatches(array $audience, WebinoSiteProvision $site): bool
    {
        $ids = $this->ints($audience['category_ids'] ?? []);
        $slugs = $this->lowerStrings($audience['category_slugs'] ?? []);
        $wizard = is_array($site->wizard_payload) ? $site->wizard_payload : [];
        $siteId = (int) ($wizard['business_category_id'] ?? 0);
        if ($siteId === 0) {
            $siteId = (int) ($site->package?->businessType?->category_id ?? 0);
        }
        $siteSlug = strtolower((string) ($wizard['business_category_slug'] ?? ''));
        if ($siteSlug === '') {
            $siteSlug = strtolower((string) ($site->package?->businessType?->category?->slug ?? ''));
        }

        return ($siteId !== 0 && in_array($siteId, $ids, true))
            || ($siteSlug !== '' && in_array($siteSlug, $slugs, true));
    }

    /**
     * @param  array<string, mixed>  $audience
     */
    private function typeMatches(array $audience, WebinoSiteProvision $site): bool
    {
        $ids = $this->ints($audience['type_ids'] ?? []);
        $slugs = $this->lowerStrings($audience['type_slugs'] ?? []);
        $wizard = is_array($site->wizard_payload) ? $site->wizard_payload : [];
        $siteId = (int) ($wizard['business_type_id'] ?? 0);
        if ($siteId === 0) {
            $siteId = (int) ($site->package?->business_type_id ?? 0);
        }
        $siteSlug = strtolower((string) ($wizard['site_type_slug'] ?? $wizard['business_type_slug'] ?? ''));
        if ($siteSlug === '') {
            $siteSlug = strtolower((string) ($site->package?->businessType?->slug ?? ''));
        }

        return ($siteId !== 0 && in_array($siteId, $ids, true))
            || ($siteSlug !== '' && in_array($siteSlug, $slugs, true));
    }

    /**
     * @param  array<string, mixed>  $audience
     */
    private function tagMatches(array $audience, WebinoSiteProvision $site): bool
    {
        $wanted = $this->tags($audience['tags'] ?? []);
        if ($wanted === []) {
            return false;
        }

        return count(array_intersect($wanted, $this->siteTags($site))) > 0;
    }

    /**
     * @return list<string>
     */
    public function siteTags(WebinoSiteProvision $site): array
    {
        $names = [];
        $wizard = is_array($site->wizard_payload) ? $site->wizard_payload : [];
        $raw = $wizard['tags'] ?? $wizard['tag'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/\s*,\s*/', $raw) ?: [];
        }
        if (is_array($raw)) {
            foreach ($raw as $tag) {
                if (is_string($tag) && trim($tag) !== '') {
                    $names[] = mb_strtolower(trim($tag));
                }
            }
        }

        if (Schema::hasTable('platform_taggables') && Schema::hasTable('platform_tags')) {
            $resourceIds = [];
            if (Schema::hasTable('platform_resources')) {
                $resourceIds = DB::table('platform_resources')
                    ->where('provision_id', $site->id)
                    ->pluck('id')
                    ->all();
            }
            $tagIds = DB::table('platform_taggables')
                ->where(function ($query) use ($site, $resourceIds) {
                    $query->where(function ($query) use ($site) {
                        $query->where('taggable_id', $site->id)
                            ->whereIn('taggable_type', [
                                WebinoSiteProvision::class,
                                'site_provision',
                                'webino_site_provision',
                            ]);
                    });
                    if ($resourceIds !== []) {
                        $query->orWhere(function ($query) use ($resourceIds) {
                            $query->whereIn('taggable_id', $resourceIds)
                                ->whereIn('taggable_type', [
                                    PlatformResource::class,
                                    'platform_resource',
                                    'resource',
                                ]);
                        });
                    }
                })
                ->pluck('tag_id');
            foreach (DB::table('platform_tags')->whereIn('id', $tagIds)->pluck('name') as $name) {
                $names[] = mb_strtolower(trim((string) $name));
            }
        }

        return array_values(array_unique(array_filter($names, fn ($name) => $name !== '')));
    }

    /**
     * @return list<int>
     */
    private function ints(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private function tags(mixed $values): array
    {
        return $this->lowerStrings($values);
    }

    /**
     * @return list<string>
     */
    private function lowerStrings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }
        $out = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }
            $value = mb_strtolower(trim($value));
            if ($value !== '') {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }
}
