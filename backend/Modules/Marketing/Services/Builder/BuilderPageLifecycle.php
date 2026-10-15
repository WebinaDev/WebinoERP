<?php

namespace Modules\Marketing\Services\Builder;

use Modules\Marketing\Services\Builder\BuilderSite;

use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\SeoRedirect;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/** Publish/unpublish/schedule, edit locks, preview tokens and slug redirects for builder pages. */
class BuilderPageLifecycle
{
    public const LOCK_SECONDS = 90;

    public const PREVIEW_HOURS = 48;

    public function publish(MarketingPage $page): void
    {
        abort_if(! is_array($page->builder_draft), 422, 'draft missing');
        $page->builder_published = $page->builder_draft;
        $page->published = true;
        $page->status = 'published';
        $page->builder_publish_at = null;
        $page->save();
    }

    public function unpublish(MarketingPage $page): void
    {
        $page->published = false;
        $page->status = 'draft';
        $page->builder_unpublish_at = null;
        $page->save();
    }

    /** Runs due schedules; returns [published, unpublished]. */
    public function runDue(): array
    {
        $now = now();
        $pub = 0;
        $unpub = 0;
        MarketingPage::query()->whereNotNull('builder_publish_at')->where('builder_publish_at', '<=', $now)->orderBy('id')->limit(500)->get()
            ->each(function (MarketingPage $page) use (&$pub) {
                try {
                    if (is_array($page->builder_draft) && $page->status !== 'trash') {
                        $this->publish($page);
                        $pub++;
                    } else {
                        $page->builder_publish_at = null;
                        $page->save();
                    }
                } catch (\Throwable $e) {
                    Log::warning('builder scheduled publish failed', ['page' => $page->id, 'error' => $e->getMessage()]);
                }
            });
        MarketingPage::query()->whereNotNull('builder_unpublish_at')->where('builder_unpublish_at', '<=', $now)->orderBy('id')->limit(500)->get()
            ->each(function (MarketingPage $page) use (&$unpub) {
                $this->unpublish($page);
                $unpub++;
            });

        return [$pub, $unpub];
    }

    /** @return array{user_id: int, name: string, until: string}|null */
    public function activeLock(MarketingPage $page): ?array
    {
        $lock = (is_array($page->meta) ? $page->meta : [])['builder_lock'] ?? null;
        if (! is_array($lock) || empty($lock['until']) || now()->greaterThan($lock['until'])) {
            return null;
        }

        return $lock;
    }

    public function lockedByOther(MarketingPage $page, User $user): ?array
    {
        $lock = $this->activeLock($page);

        return $lock && (int) $lock['user_id'] !== (int) $user->id ? $lock : null;
    }

    public function lock(MarketingPage $page, User $user): array
    {
        $meta = is_array($page->meta) ? $page->meta : [];
        $meta['builder_lock'] = [
            'user_id' => (int) $user->id,
            'name' => mb_substr((string) ($user->name ?? $user->email ?? ''), 0, 80),
            'until' => now()->addSeconds(self::LOCK_SECONDS)->toIso8601String(),
        ];
        $page->meta = $meta;
        $page->save();

        return $meta['builder_lock'];
    }

    public function unlock(MarketingPage $page, User $user): void
    {
        $meta = is_array($page->meta) ? $page->meta : [];
        if ((int) ($meta['builder_lock']['user_id'] ?? 0) === (int) $user->id) {
            unset($meta['builder_lock']);
            $page->meta = $meta;
            $page->save();
        }
    }

    /** @return array{token: string, expires_at: string} */
    public function issuePreviewToken(MarketingPage $page): array
    {
        $token = bin2hex(random_bytes(24));
        $meta = is_array($page->meta) ? $page->meta : [];
        $expires = now()->addHours(self::PREVIEW_HOURS);
        $meta['builder_preview'] = ['hash' => hash('sha256', $token), 'expires_at' => $expires->toIso8601String()];
        $page->meta = $meta;
        $page->save();

        return ['token' => $page->id.'.'.$token, 'expires_at' => $expires->toIso8601String()];
    }

    public function findByPreviewToken(int $tenantId, string $raw): ?MarketingPage
    {
        if (! preg_match('/^(\d+)\.([a-f0-9]{48})$/', $raw, $m)) {
            return null;
        }
        $page = MarketingPage::query()->where('tenant_id', BuilderSite::ID)->find((int) $m[1]);
        $preview = $page ? ((is_array($page->meta) ? $page->meta : [])['builder_preview'] ?? null) : null;
        if (! is_array($preview) || now()->greaterThan($preview['expires_at'] ?? '1970-01-01')) {
            return null;
        }

        return hash_equals((string) $preview['hash'], hash('sha256', $m[2])) ? $page : null;
    }

    /** Slug change on a page that has been live: 301 from the old URL, chains collapsed, no loops. */
    public function recordSlugChange(MarketingPage $page, string $oldSlug, string $newSlug): void
    {
        if ($oldSlug === '' || $oldSlug === $newSlug || ! is_array($page->builder_published)) {
            return;
        }
        // Pages are reachable at /pages/{slug} and (via the sitemap/canonical) /{slug}: redirect both.
        foreach (['/pages/', '/'] as $prefix) {
            $from = $prefix.$oldSlug;
            $to = $prefix.$newSlug;
            SeoRedirect::query()->where('tenant_id', $page->tenant_id)->where('from_path', $to)->delete();
            SeoRedirect::query()->where('tenant_id', $page->tenant_id)->where('to_path', $from)->update(['to_path' => $to]);
            SeoRedirect::query()->updateOrCreate(
                ['tenant_id' => (int) ($page->tenant_id ?: BuilderSite::ID), 'from_path' => $from],
                ['to_path' => $to, 'status_code' => 301, 'enabled' => true, 'note' => 'builder slug change'],
            );
        }
    }
}
