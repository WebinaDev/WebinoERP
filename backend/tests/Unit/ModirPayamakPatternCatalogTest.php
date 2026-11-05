<?php

namespace Tests\Unit;

use Modules\Integrations\Services\ModirPayamakPatternCatalog;
use Modules\Integrations\Services\ModirPayamakReportRows;
use PHPUnit\Framework\TestCase;

class ModirPayamakPatternCatalogTest extends TestCase
{
    public function test_parses_canonical_and_legacy_titles(): void
    {
        $canonical = ModirPayamakPatternCatalog::parseTitle('پریسما گالری | مدیر | تکمیل شده');
        $this->assertSame('completed', $canonical['event_key']);
        $this->assertSame('مدیر', $canonical['role']);
        $this->assertSame('پریسما گالری', $canonical['site']);

        $legacy = ModirPayamakPatternCatalog::parseTitle('تکمیل سفارش - مشتری - رستری کافئین');
        $this->assertSame('completed', $legacy['event_key']);
        $this->assertSame('مشتری', $legacy['role']);
        $this->assertSame('رستری کافئین', $legacy['site']);

        $this->assertNull(ModirPayamakPatternCatalog::parseTitle('یادداشت داخلی'));
    }

    public function test_site_names_match_without_matching_short_words(): void
    {
        $this->assertTrue(ModirPayamakPatternCatalog::namesMatch('پریسما', 'پریسما گالری'));
        $this->assertTrue(ModirPayamakPatternCatalog::namesMatch('کافئین', 'رستری کافئین'));
        $this->assertFalse(ModirPayamakPatternCatalog::namesMatch('گالری', 'پریسما گالری'));
    }

    public function test_variables_mark_otp_as_integer(): void
    {
        $vars = ModirPayamakPatternCatalog::variablesFromMessage('کد شما %otp% است. سفارش %name%');
        $this->assertSame([
            ['name' => 'otp', 'type' => 'integer'],
            ['name' => 'name', 'type' => 'string'],
        ], $vars);
    }

    public function test_report_rows_unwrap_nested_outbox_and_unix_time(): void
    {
        $rows = ModirPayamakReportRows::messages([
            'data' => [
                'entries' => [[
                    'messages_outbox_id' => 42,
                    'number' => '+983000505',
                    'message' => 'سلام',
                    'recipient' => '09120000000',
                    'time' => 1730000000,
                    'cost' => '500.00',
                    'state' => 'finish',
                ]],
            ],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('سلام', $rows[0]['message']);
        $this->assertSame(['09120000000'], $rows[0]['recipients']);
        $this->assertSame(500.0, $rows[0]['cost']);
        $this->assertNotSame('', $rows[0]['created_at']);
    }
}
