<?php

namespace Tests\Unit;

use Modules\Marketing\Services\Builder\BuilderGlobalsNormalizer;
use Tests\TestCase;

class BuilderGlobalsNormalizerTest extends TestCase
{
    public function test_defaults_are_webina_brand_not_blush(): void
    {
        $colors = (new BuilderGlobalsNormalizer)->defaults()['colors'];
        $this->assertSame('#0054ff', $colors['primary']);
        $this->assertSame('#22242a', $colors['secondary']);
        $this->assertSame('#0054ff', $colors['accent']);
    }

    public function test_empty_and_seed_input_derive_from_brand(): void
    {
        $n = new BuilderGlobalsNormalizer;
        $brand = ['primary' => '#1122AA', 'ink' => '#101218', 'paper' => '#F7F8FA', 'foreground' => '#101218'];
        $empty = $n->normalize([], $brand);
        $this->assertSame('#1122aa', $empty['colors']['primary']);
        $this->assertSame('#101218', $empty['colors']['text']);
        $this->assertSame('#f7f8fa', $empty['colors']['background']);

        $seed = $n->normalize(['colors' => BuilderGlobalsNormalizer::SEED_COLORS], $brand);
        $this->assertSame('#1122aa', $seed['colors']['primary']);
        $this->assertSame('#101218', $seed['colors']['secondary']);
    }

    public function test_partial_custom_colors_are_kept(): void
    {
        $n = new BuilderGlobalsNormalizer;
        $out = $n->normalize(['colors' => ['primary' => '#abcdef']], ['primary' => '#0054ff', 'ink' => '#22242a']);
        $this->assertSame('#abcdef', $out['colors']['primary']);
        $this->assertSame('#22242a', $out['colors']['secondary']);
    }
}
