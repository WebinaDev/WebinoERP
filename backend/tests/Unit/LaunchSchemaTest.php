<?php

namespace Tests\Unit;

use Modules\SiteBuilder\Support\LaunchSchema;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LaunchSchemaTest extends TestCase
{
    public function test_missing_progress_sql_is_a_schema_failure(): void
    {
        $sql = new RuntimeException(
            'SQLSTATE[42703]: column "progress" of relation "webino_site_provisions" does not exist'
        );

        $this->assertTrue(LaunchSchema::isProgressFailure($sql));
        $this->assertFalse(LaunchSchema::isProgressFailure(new RuntimeException('redis connection refused')));
        $this->assertSame('platform.schema_outdated', LaunchSchema::outdatedPayload($sql->getMessage())['message']);
        $this->assertSame('platform.queue_unavailable', LaunchSchema::queueUnavailablePayload()['errors']['code']);
    }
}
