<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Marketing\Entities\MarketingMenu;
use Modules\Marketing\Entities\MarketingMenuItem;
use Modules\Marketing\Http\Controllers\MarketingNavMenuController;
use Modules\Sales\Entities\SalesCatalogItem;
use Tests\TestCase;

class MarketingNavMenuSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function hit(string $method, array $q = []): array
    {
        return app(MarketingNavMenuController::class)->{$method}(Request::create('/', 'GET', $q))->getData(true);
    }

    public function test_link_search_returns_shop_products_and_categories(): void
    {
        SalesCatalogItem::query()->create(['name' => 'قالب فروشگاهی', 'sku' => 'shop-theme', 'price' => 1000, 'status' => 'active', 'type' => 'product',
            'meta' => ['storefront' => true, 'slug' => 'shop-theme', 'category_slug' => 'products', 'category_name' => 'محصولات']]);
        SalesCatalogItem::query()->create(['name' => 'پنهان', 'sku' => 'hidden', 'price' => 1, 'status' => 'active', 'type' => 'product', 'meta' => ['storefront' => false]]);

        $p = $this->hit('sources', ['type' => 'product', 'q' => 'قالب'])['data'];
        $this->assertCount(1, $p);
        $this->assertSame('shop-theme', $p[0]['slug']);

        $c = $this->hit('sources', ['type' => 'category'])['data'];
        $this->assertSame([['id' => 1, 'label' => 'محصولات', 'slug' => 'products']], $c);

        $f = $this->hit('fromCategories');
        $this->assertTrue($f['meta']['available']);
        $this->assertSame('category', $f['data'][0]['type']);
    }

    public function test_from_categories_is_unavailable_without_shop_categories(): void
    {
        $f = $this->hit('fromCategories');
        $this->assertSame([], $f['data']);
        $this->assertFalse($f['meta']['available']);
    }

    public function test_legacy_menus_migrate_idempotently(): void
    {
        $menu = MarketingMenu::query()->create(['location' => 'header', 'name' => 'اصلی', 'published' => true]);
        $parent = MarketingMenuItem::query()->create(['menu_id' => $menu->id, 'label' => 'خدمات', 'href' => '/pages/services', 'sort_order' => 1]);
        MarketingMenuItem::query()->create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'label' => 'سئو', 'href' => 'seo', 'sort_order' => 1]);

        $m = require base_path('Modules/Marketing/Database/Migrations/2026_10_28_230000_migrate_legacy_marketing_menus_to_nav_menus.php');
        $m->up();
        $m->up();

        $rows = DB::table('marketing_nav_menus')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('legacy-header', $rows[0]->slug);
        $this->assertSame(['header_main'], json_decode($rows[0]->locations, true));
        $items = json_decode($rows[0]->items, true);
        $this->assertSame('/services', $items[0]['url']);
        $this->assertSame('/seo', $items[0]['children'][0]['url']);
    }
}
