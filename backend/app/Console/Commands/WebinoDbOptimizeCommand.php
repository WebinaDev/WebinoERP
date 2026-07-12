<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Entities\CronRun;
use Modules\Core\Services\CoreCacheService;

/**
 * Weekly database optimizer + cache/log pruning.
 *
 * Parity with webinocrm includes/class-database-optimizer.php:
 *   - OPTIMIZE TABLE on key tables
 *   - prune `core_system_logs` older than 60 days
 *   - prune `core_visitor_events` older than 90 days
 *   - drop webinocrm-prefixed cache transients via CoreCacheService
 *
 * Each run records a row in `core_cron_runs`.
 */
class WebinoDbOptimizeCommand extends Command
{
    protected $signature = 'webino:db-optimize
                            {--prune-logs=60 : Days to keep core_system_logs}
                            {--prune-visitors=90 : Days to keep core_visitor_events}
                            {--dry-run : Log only, no writes}';

    protected $description = 'Weekly DB optimize + log/visitor/cache prune (parity with webinocrm DB optimizer cron)';

    public function handle(CoreCacheService $cache): int
    {
        $dry = (bool) $this->option('dry-run');
        $start = now();

        $summary = [
            'tables_optimized' => 0,
            'logs_pruned' => 0,
            'visitors_pruned' => 0,
            'cache_cleared' => 0,
            'dry_run' => $dry,
        ];

        try {
            $tables = $this->discoverCrmTables();
            if (! $dry) {
                foreach ($tables as $t) {
                    try {
                        DB::statement('OPTIMIZE TABLE '.$t);
                        $summary['tables_optimized']++;
                    } catch (\Throwable $e) {
                        Log::warning('db-optimize: OPTIMIZE TABLE failed', ['table' => $t, 'error' => $e->getMessage()]);
                    }
                }
            } else {
                $summary['tables_optimized'] = count($tables);
            }

            $logDays = max(1, (int) $this->option('prune-logs'));
            $logCutoff = now()->subDays($logDays);
            $logQuery = DB::table('core_system_logs')->where('created_at', '<', $logCutoff);
            $summary['logs_pruned'] = $dry ? $logQuery->count() : $logQuery->delete();

            $visDays = max(1, (int) $this->option('prune-visitors'));
            $visCutoff = now()->subDays($visDays);
            $visQuery = DB::table('core_visitor_events')->where('visited_at', '<', $visCutoff);
            $summary['visitors_pruned'] = $dry ? $visQuery->count() : $visQuery->delete();

            if (! $dry) {
                $summary['cache_cleared'] = $cache->flushAll();
            }

            $end = now();
            $this->recordRun('webino:db-optimize', 'ok', $start, $end, $summary, null);

            $this->info(sprintf(
                '[%s] optimized=%d, logs_pruned=%d, visitors_pruned=%d, cache_cleared=%d',
                $dry ? 'DRY' : 'RUN',
                $summary['tables_optimized'],
                $summary['logs_pruned'],
                $summary['visitors_pruned'],
                $summary['cache_cleared'],
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->recordRun('webino:db-optimize', 'error', $start, now(), $summary, $e->getMessage());
            Log::error('db-optimize.failed', ['error' => $e->getMessage()]);
            $this->error('db-optimize failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return list<string>
     */
    private function discoverCrmTables(): array
    {
        $candidates = [
            'users', 'personal_access_tokens',
            'core_system_logs', 'core_visitor_events', 'core_notifications', 'core_licenses',
            'core_positions', 'core_task_categories', 'core_canned_responses',
            'system_settings', 'system_modules',
            'crm_leads', 'crm_accounts', 'crm_sources', 'crm_statuses', 'crm_consultations',
            'prj_projects', 'prj_contracts', 'prj_contract_installments', 'prj_tasks', 'prj_tickets',
            'prj_appointments', 'prj_invoices',
            'acc_journal_entries', 'acc_journal_lines', 'acc_invoices', 'acc_products',
            'acc_persons', 'acc_warehouses', 'acc_warehouse_stock',
            'integration_settings',
        ];
        $out = [];
        foreach ($candidates as $t) {
            try {
                if (DB::getSchemaBuilder()->hasTable($t)) {
                    $out[] = $t;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $out;
    }

    private function recordRun(string $job, string $status, \Carbon\Carbon|\Illuminate\Support\Carbon $start, \Carbon\Carbon|\Illuminate\Support\Carbon $end, array $summary, ?string $error): void
    {
        try {
            CronRun::query()->create([
                'job' => $job,
                'status' => $status,
                'duration_ms' => max(0, $end->diffInMilliseconds($start)),
                'summary' => $summary,
                'error' => $error,
                'started_at' => $start,
                'finished_at' => $end,
            ]);
        } catch (\Throwable $e) {
            Log::warning('cron_run.record_failed', ['job' => $job, 'error' => $e->getMessage()]);
        }
    }
}
