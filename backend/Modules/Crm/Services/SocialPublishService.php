<?php

namespace Modules\Crm\Services;

use App\Support\ModuleMonitor;
use Illuminate\Support\Facades\Http;
use Modules\Crm\Entities\ContentItem;
use Modules\Integrations\Entities\SocialConnector;

class SocialPublishService
{
    public function publish(ContentItem $item): ContentItem
    {
        $item->loadMissing('calendar');
        $network = (string) ($item->calendar->network ?? '');
        if (! in_array($network, ['instagram', 'linkedin'], true)) {
            $item->update([
                'publish_status' => 'failed',
                'publish_error' => 'network_unsupported',
            ]);

            return $item->fresh();
        }
        $connector = SocialConnector::query()
            ->where('network', $network)
            ->where('enabled', true)
            ->orderByDesc('id')
            ->first();
        if (! $connector || ! $connector->access_token || ! $connector->external_id) {
            $item->update([
                'publish_status' => 'failed',
                'publish_error' => 'connector_missing',
                'publish_attempts' => (int) $item->publish_attempts + 1,
            ]);

            return $item->fresh();
        }
        try {
            $external = $network === 'instagram'
                ? $this->instagram($connector, $item)
                : $this->linkedin($connector, $item);
        } catch (\Throwable $e) {
            $attempts = (int) $item->publish_attempts + 1;
            $item->update([
                'publish_status' => $attempts >= 5 ? 'failed' : 'retry',
                'publish_error' => $e->getMessage(),
                'publish_attempts' => $attempts,
            ]);
            ModuleMonitor::hook('crm', 'publish_failed', ['item_id' => $item->id, 'error' => $e->getMessage()]);
            if (config('queue.default') !== 'sync' && $attempts < 5) {
                \Modules\Crm\Jobs\PublishContentJob::dispatch($item->id)->delay(now()->addMinutes(2 ** min($attempts, 5)));
            }

            return $item->fresh();
        }
        $item->update([
            'status' => 'published',
            'publish_status' => 'published',
            'external_post_id' => $external,
            'publish_error' => null,
            'published_at' => now(),
        ]);
        ModuleMonitor::hook('crm', 'published', ['item_id' => $item->id, 'network' => $network, 'external_id' => $external]);

        return $item->fresh();
    }

    private function instagram(SocialConnector $connector, ContentItem $item): string
    {
        if (! $item->image_url) {
            throw new \RuntimeException('image_required');
        }
        $base = rtrim((string) config('integrations.social.instagram_graph'), '/');
        $user = rawurlencode((string) $connector->external_id);
        $media = Http::withToken((string) $connector->access_token)->timeout(20)->post($base.'/'.$user.'/media', [
            'image_url' => $item->image_url,
            'caption' => trim($item->title."\n".(string) $item->body),
        ]);
        if (! $media->successful() || ! $media->json('id')) {
            throw new \RuntimeException('instagram_media_'.$media->status());
        }
        $live = Http::withToken((string) $connector->access_token)->timeout(20)->post($base.'/'.$user.'/media_publish', [
            'creation_id' => (string) $media->json('id'),
        ]);
        if (! $live->successful() || ! $live->json('id')) {
            throw new \RuntimeException('instagram_publish_'.$live->status());
        }

        return (string) $live->json('id');
    }

    private function linkedin(SocialConnector $connector, ContentItem $item): string
    {
        $base = rtrim((string) config('integrations.social.linkedin_api'), '/');
        $author = str_starts_with((string) $connector->external_id, 'urn:')
            ? (string) $connector->external_id
            : 'urn:li:person:'.$connector->external_id;
        $response = Http::withToken((string) $connector->access_token)
            ->timeout(20)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->post($base.'/v2/ugcPosts', [
                'author' => $author,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => ['text' => trim($item->title."\n".(string) $item->body)],
                        'shareMediaCategory' => 'NONE',
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException('linkedin_'.$response->status());
        }
        $id = (string) ($response->json('id') ?: $response->header('x-restli-id'));
        if ($id === '') {
            throw new \RuntimeException('linkedin_missing_id');
        }

        return $id;
    }
}
