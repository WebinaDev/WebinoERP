<?php

namespace Modules\Marketing\Services;

use Modules\Marketing\Entities\MarketingPage;

/**
 * Page-builder documents for the corporate site (home, contact, about), built from «وبینا»
 * builder widgets with the verbatim default content in webina-site-defaults.json.
 *
 * apply() only fills pages that have no builder content yet; published or edited documents
 * are never overwritten (the old one-heading placeholder draft counts as empty).
 */
class CorporatePageDocuments
{
    private const TITLES = [
        'home' => ['صفحه اصلی', 'Home'],
        'contact' => ['تماس با ما', 'Contact'],
        'about' => ['درباره ما', 'About'],
    ];

    /** @return array<string, array> slug => document */
    public static function documents(): array
    {
        $d = SiteContentDefaults::load();

        return [
            'home' => self::home($d['home_blocks'] ?? []),
            'contact' => self::contact($d['contact_page'] ?? []),
            'about' => self::about($d['about_page']['sections'] ?? []),
        ];
    }

    /** @return string[] slugs that were filled */
    public static function apply(): array
    {
        $filled = [];
        foreach (self::documents() as $slug => $doc) {
            if (! $doc['sections']) {
                continue;
            }
            $page = MarketingPage::query()->where('slug', $slug)->first();
            if ($page && is_array($page->builder_published) && ! empty($page->builder_published['sections'])) {
                continue;
            }
            if ($page && is_array($page->builder_draft) && ! self::isPlaceholder($page->builder_draft)) {
                continue;
            }
            $page ??= new MarketingPage(['slug' => $slug, 'title_fa' => self::TITLES[$slug][0], 'title_en' => self::TITLES[$slug][1]]);
            $page->builder_draft = $doc;
            $page->builder_published = $doc;
            $page->published = true;
            $page->status = 'published';
            $page->save();
            $filled[] = $slug;
        }

        return $filled;
    }

    public static function isPlaceholder(?array $doc): bool
    {
        $sections = $doc['sections'] ?? [];
        if (! $sections) {
            return true;
        }
        $widgets = [];
        foreach ($sections as $section) {
            foreach ($section['columns'] ?? [] as $column) {
                foreach ($column['widgets'] ?? [] as $widget) {
                    $widgets[] = $widget['id'] ?? '';
                }
            }
        }

        return $widgets === ['w_home_hero'] || $widgets === [];
    }

    private static function widget(string $id, string $type, array $props): array
    {
        return ['id' => 'w_'.$id, 'type' => $type, 'props' => $props];
    }

    private static function full(string $id, array $widget): array
    {
        return [
            'id' => 'sec_'.$id,
            'fullWidth' => true,
            'columns' => [['id' => 'col_'.$id, 'span' => 12, 'widgets' => [$widget]]],
        ];
    }

    private static function doc(array $sections): array
    {
        return ['version' => 1, 'sections' => array_values($sections), 'page_settings' => ['theme' => 'webina-corporate-v1']];
    }

    private static function home(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if (($block['enabled'] ?? true) === false || empty($block['type'])) {
                continue;
            }
            $id = 'home_'.$block['type'];
            $out[] = self::full($id, self::widget($id, 'webina-home-'.$block['type'], $block['props'] ?? []));
        }

        return self::doc($out);
    }

    private static function slice(array $p, callable $keep): array
    {
        return array_filter($p, fn ($v, $k) => $keep((string) $k), ARRAY_FILTER_USE_BOTH);
    }

    private static function contact(array $p): array
    {
        if (! $p) {
            return self::doc([]);
        }
        $starts = fn (string ...$prefixes) => fn (string $k) => array_filter($prefixes, fn ($x) => str_starts_with($k, $x)) !== [];
        $hero = self::slice($p, $starts('hero_'));
        $hero['channels'] = array_slice($p['channels'] ?? [], 0, 2);
        $form = self::slice($p, fn ($k) => str_starts_with($k, 'form_') || in_array($k, ['submit_label', 'success_title', 'success_text', 'again_label', 'privacy_note'], true));

        return self::doc([
            self::full('contact_hero', self::widget('contact_hero', 'webina-contact-hero', $hero)),
            self::full('contact_cards', self::widget('contact_cards', 'webina-contact-cards', self::slice($p, $starts('channels', 'cop')))),
            [
                'id' => 'sec_contact_split',
                'columns' => [
                    ['id' => 'col_contact_form', 'span' => 7, 'mobileSpan' => 12, 'widgets' => [self::widget('contact_form', 'webina-contact-form', $form)]],
                    ['id' => 'col_contact_aside', 'span' => 5, 'mobileSpan' => 12, 'widgets' => [
                        self::widget('contact_hours', 'webina-contact-hours', self::slice($p, $starts('hours'))),
                        self::widget('contact_map', 'webina-contact-map', self::slice($p, $starts('map_'))),
                    ]],
                ],
            ],
            self::full('contact_socials', self::widget('contact_socials', 'webina-contact-socials', self::slice($p, $starts('social_')))),
            self::full('contact_faq', self::widget('contact_faq', 'webina-contact-faq', self::slice($p, $starts('faq_')))),
            self::full('contact_cta', self::widget('contact_cta', 'webina-cta-band', self::slice($p, $starts('cta_')))),
        ]);
    }

    private static function about(array $sections): array
    {
        $out = [];
        foreach ($sections as $s) {
            if (($s['enabled'] ?? true) === false || empty($s['type'])) {
                continue;
            }
            $id = 'about_'.$s['type'];
            $out[] = self::full($id, self::widget($id, 'webina-about-'.$s['type'], $s['props'] ?? []));
        }

        return self::doc($out);
    }
}
