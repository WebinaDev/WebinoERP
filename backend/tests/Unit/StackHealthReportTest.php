<?php

namespace Tests\Unit;

use Modules\Platform\Support\StackHealthReport;
use PHPUnit\Framework\TestCase;

class StackHealthReportTest extends TestCase
{
    public function test_not_run_does_not_mark_probes_failed(): void
    {
        $report = StackHealthReport::notRun();

        $this->assertFalse($report['diagnostics_ran']);
        $this->assertNull($report['db_auth_ok']);
        $this->assertSame('not_run', $report['checks']['db_auth']);
        $this->assertSame('not_run', $report['checks']['caddy_to_backend']);
        $this->assertSame('not_run', $report['checks']['on_webino_sites_backend']);
    }

    public function test_skipped_is_distinct_from_fail(): void
    {
        $this->assertSame('skipped', StackHealthReport::state(false, false));
        $this->assertSame('fail', StackHealthReport::state(true, false));
        $this->assertSame('ok', StackHealthReport::state(true, true));
    }

    public function test_cold_start_timeout_is_not_a_hard_failure(): void
    {
        $this->assertFalse(StackHealthReport::backendStartupFailed('running', 0, 0, false, ''));
        $this->assertFalse(StackHealthReport::backendStartupFailed('created', 0, 0, false, 'booting'));
    }

    public function test_crash_loop_and_password_mismatch_are_failures(): void
    {
        $this->assertTrue(StackHealthReport::backendStartupFailed('restarting', 1, 4, false, ''));
        $this->assertTrue(StackHealthReport::backendStartupFailed('exited', 1, 1, false, 'entrypoint failed'));
        $this->assertTrue(StackHealthReport::backendStartupFailed('running', 0, 0, true, ''));
        $this->assertTrue(StackHealthReport::backendStartupFailed(
            'running',
            0,
            0,
            false,
            'SQLSTATE[28P01]: password authentication failed for user "webino"',
        ));
        $this->assertTrue(StackHealthReport::backendStartupFailed('running', 0, 3, false, ''));
    }
}
