<?php

namespace Tests\Unit;

use Modules\Integrations\Services\MailboxService;
use PHPUnit\Framework\TestCase;

class MailboxParseTest extends TestCase
{
    public function test_parses_raw_headers_and_body(): void
    {
        $raw = "From: Sara <sara@example.com>\r\nSubject: Hello\r\nDate: Sat, 3 Oct 2026 10:00:00 +0330\r\nMessage-ID: <abc@example.com>\r\n\r\nBody line";
        $parsed = (new MailboxService)->parseRaw($raw);

        $this->assertSame('sara@example.com', $parsed['from']);
        $this->assertSame('Hello', $parsed['subject']);
        $this->assertSame('abc@example.com', $parsed['message_id']);
        $this->assertSame('Body line', $parsed['body']);
    }
}
