<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\AccChartAccount;

class AccChartIranSeeder
{
    /**
     * @return array{inserted:int, skipped:int}
     */
    public static function run(bool $truncateFirst = false): array
    {
        $rows = require module_path('Accounting', 'Database/data/chart-iran.php');
        if ($truncateFirst) {
            if (Schema::hasTable('acc_journal_lines')) {
                DB::table('acc_journal_lines')->delete();
            }
            if (Schema::hasTable('acc_journal_entries')) {
                DB::table('acc_journal_entries')->delete();
            }
            AccChartAccount::query()->delete();
        }

        $codeToId = [];
        $inserted = 0;
        $skipped = 0;

        usort($rows, function ($a, $b) {
            return strlen((string) $a['code']) <=> strlen((string) $b['code']);
        });

        foreach ($rows as $row) {
            if (AccChartAccount::query()->where('code', $row['code'])->exists()) {
                $skipped++;
                $codeToId[$row['code']] = (int) AccChartAccount::query()->where('code', $row['code'])->value('id');

                continue;
            }
            $parentId = null;
            if (! empty($row['parent_code'])) {
                $parentId = $codeToId[$row['parent_code']] ?? AccChartAccount::query()->where('code', $row['parent_code'])->value('id');
            }
            $m = AccChartAccount::query()->create([
                'code' => $row['code'],
                'name' => $row['name'],
                'parent_id' => $parentId,
                'type' => $row['type'],
                'is_postable' => (bool) $row['is_postable'],
            ]);
            $codeToId[$row['code']] = $m->id;
            $inserted++;
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }
}
