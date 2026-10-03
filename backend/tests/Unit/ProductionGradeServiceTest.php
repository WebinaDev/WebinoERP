<?php

namespace Tests\Unit;

use Modules\Crm\Services\AttributionService;
use Modules\Crm\Services\CpqService;
use Modules\Integrations\Services\SpamFilter;
use Tests\TestCase;

class ProductionGradeServiceTest extends TestCase
{
    public function test_discount_stack_and_spam_score_and_attribution_weights(): void
    {
        $cpq = new CpqService;
        $this->assertEquals(10.0, $cpq->stack([10, 0, 0]));
        $this->assertEquals(19.0, $cpq->stack([10, 10]));

        config(['integrations.spam.threshold' => 3, 'integrations.spam.keywords' => ['viagra', 'برنده شدید'], 'integrations.spam.domains' => []]);
        $clean = (new SpamFilter)->apply(['subject' => 'Hello', 'body' => 'A note', 'from_email' => 'a@b.com']);
        $this->assertFalse($clean['is_spam']);
        $spam = (new SpamFilter)->apply(['subject' => 'برنده شدید', 'body' => 'viagra offer', 'from_email' => 'a@b.com']);
        $this->assertTrue($spam['is_spam']);
        $this->assertSame('spam', $spam['folder']);

        $weights = (new AttributionService)->weights(3, 'position');
        $this->assertSame([40.0, 20.0, 40.0], $weights);
        $this->assertSame([100.0, 0.0], (new AttributionService)->weights(2, 'first'));
    }
}
