<?php

namespace Tests\Unit;

use Modules\Projects\Services\CriticalPathService;
use PHPUnit\Framework\TestCase;

class CriticalPathServiceTest extends TestCase
{
    public function test_marks_zero_slack_tasks_as_critical(): void
    {
        $result = (new CriticalPathService)->compute([
            ['id' => 1, 'title' => 'A', 'duration_days' => 2],
            ['id' => 2, 'title' => 'B', 'duration_days' => 3],
            ['id' => 3, 'title' => 'C', 'duration_days' => 1],
        ], [
            ['source_id' => 1, 'target_id' => 2, 'type' => 'finish_to_start'],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(5, $result['project_duration']);
        $this->assertEqualsCanonicalizing([1, 2], $result['critical_ids']);
        $byId = collect($result['tasks'])->keyBy('id');
        $this->assertSame(0, $byId[1]['slack']);
        $this->assertSame(4, $byId[3]['slack']);
        $this->assertFalse($byId[3]['critical']);
    }

    public function test_rejects_cycles(): void
    {
        $result = (new CriticalPathService)->compute([
            ['id' => 1, 'duration_days' => 1],
            ['id' => 2, 'duration_days' => 1],
        ], [
            ['source_id' => 1, 'target_id' => 2, 'type' => 'blocks'],
            ['source_id' => 2, 'target_id' => 1, 'type' => 'fs'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('cycle', $result['error']);
    }
}
