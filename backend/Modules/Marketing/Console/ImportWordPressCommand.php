<?php

namespace Modules\Marketing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingMagazinePost;
use Modules\Marketing\Entities\MarketingMedia;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingSiteSetting;

class ImportWordPressCommand extends Command
{
    protected $signature = 'marketing:import-wordpress
                            {--url=https://webina.dev : WordPress site base URL}
                            {--dry-run : Preview without writing}';

    protected $description = 'Import pages, posts, and media from WordPress (webina.dev) into marketing tables';

    public function handle(): int
    {
        $base = rtrim($this->option('url'), '/');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Importing from {$base}".($dryRun ? ' (dry run)' : ''));

        $this->importCollection($base.'/wp-json/wp/v2/pages?per_page=100', 'page', $dryRun);
        $this->importCollection($base.'/wp-json/wp/v2/posts?per_page=100&categories=', 'post', $dryRun);

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function importCollection(string $url, string $type, bool $dryRun): void
    {
        $page = 1;
        do {
            $response = Http::timeout(30)->get($url.($page > 1 ? '&page='.$page : ''));
            if (! $response->ok()) {
                $this->warn("Failed to fetch {$url}: HTTP {$response->status()}");

                return;
            }
            $items = $response->json();
            if (! is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                $this->importItem($item, $type, $dryRun);
            }

            $page++;
        } while (count($items) >= 100);
    }

    private function importItem(array $item, string $type, bool $dryRun): void
    {
        $wpId = $item['id'] ?? null;
        $slug = $item['slug'] ?? null;
        $title = is_array($item['title'] ?? null) ? ($item['title']['rendered'] ?? '') : (string) ($item['title'] ?? '');
        $body = is_array($item['content'] ?? null) ? ($item['content']['rendered'] ?? '') : (string) ($item['content'] ?? '');
        $excerpt = is_array($item['excerpt'] ?? null) ? ($item['excerpt']['rendered'] ?? '') : (string) ($item['excerpt'] ?? '');

        if (! $wpId || ! $slug) {
            return;
        }

        $this->line("  [{$type}] {$slug} (wp:{$wpId})");

        if ($dryRun) {
            return;
        }

        $meta = ['wp' => ['id' => $wpId, 'link' => $item['link'] ?? null, 'acf' => $item['acf'] ?? null]];

        if ($type === 'page') {
            MarketingPage::query()->updateOrCreate(
                ['wp_id' => $wpId],
                [
                    'slug' => $slug,
                    'title_fa' => strip_tags($title),
                    'body_fa' => $body,
                    'published' => ($item['status'] ?? '') === 'publish',
                    'meta' => $meta,
                ]
            );

            return;
        }

        $categories = $item['categories'] ?? [];
        $isMagazine = in_array('magazine', $this->resolveCategorySlugs($item), true);

        if ($isMagazine) {
            MarketingMagazinePost::query()->updateOrCreate(
                ['wp_id' => $wpId],
                [
                    'slug' => $slug,
                    'title' => strip_tags($title),
                    'excerpt' => strip_tags($excerpt),
                    'body' => $body,
                    'status' => ($item['status'] ?? '') === 'publish' ? 'published' : 'draft',
                    'published_at' => ($item['status'] ?? '') === 'publish' ? ($item['date'] ?? now()) : null,
                    'meta' => $meta + ['categories' => $categories],
                ]
            );
        } else {
            MarketingBlogPost::query()->updateOrCreate(
                ['wp_id' => $wpId],
                [
                    'slug' => $slug,
                    'title' => strip_tags($title),
                    'excerpt' => strip_tags($excerpt),
                    'body' => $body,
                    'status' => ($item['status'] ?? '') === 'publish' ? 'published' : 'draft',
                    'published_at' => ($item['status'] ?? '') === 'publish' ? ($item['date'] ?? now()) : null,
                    'meta' => $meta + ['categories' => $categories],
                ]
            );
        }
    }

    /** @return list<string> */
    private function resolveCategorySlugs(array $item): array
    {
        return [];
    }
}
