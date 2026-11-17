<?php

namespace Modules\Assets\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\AccFiscalYear;
use Modules\Accounting\Entities\AccJournalEntry;
use Modules\Accounting\Entities\AccJournalLine;
use Modules\Assets\Entities\AstAsset;
use Modules\Assets\Entities\AstDepreciationRun;

class AssetDepreciationService
{
    public function calculateMonthlyDepreciation(AstAsset $asset): float
    {
        if ($asset->useful_life_months <= 0 || $asset->status !== 'active') {
            return 0.0;
        }

        $depreciableBase = max(0, (float) $asset->purchase_cost - (float) $asset->salvage_value);
        if ($asset->accumulated_depreciation >= $depreciableBase) {
            return 0.0;
        }

        $monthly = $depreciableBase / $asset->useful_life_months;
        $remaining = $depreciableBase - (float) $asset->accumulated_depreciation;

        return round(min($monthly, $remaining), 2);
    }

    public function runPeriodicDepreciation(string $periodDate, ?int $companyId = null, ?User $user = null): AstDepreciationRun
    {
        return DB::transaction(function () use ($periodDate, $companyId, $user) {
            $query = AstAsset::query()->where('status', 'active');
            if ($companyId) {
                $query->where('company_id', $companyId);
            }
            $assets = $query->get();

            $totalDepr = 0.0;
            $details = [];

            foreach ($assets as $asset) {
                $amt = $this->calculateMonthlyDepreciation($asset);
                if ($amt > 0) {
                    $newAccum = (float) $asset->accumulated_depreciation + $amt;
                    $newBook = max((float) $asset->salvage_value, (float) $asset->purchase_cost - $newAccum);

                    $asset->update([
                        'accumulated_depreciation' => $newAccum,
                        'book_value' => $newBook,
                    ]);

                    $totalDepr += $amt;
                    $details[] = [
                        'asset_id' => $asset->id,
                        'asset_code' => $asset->asset_code,
                        'amount' => $amt,
                        'accumulated_depreciation' => $newAccum,
                        'book_value' => $newBook,
                    ];
                }
            }

            $runNumber = 'DEP-'.date('Ym', strtotime($periodDate)).'-'.strtoupper(bin2hex(random_bytes(2)));

            // Post to Accounting GL if module exists
            $journalEntryId = null;
            if ($totalDepr > 0 && class_exists(AccJournalEntry::class) && Schema::hasTable('acc_journal_entries')) {
                $fy = AccFiscalYear::query()->where('is_closed', false)->latest('id')->first();
                if ($fy) {
                    $entry = AccJournalEntry::query()->create([
                        'fiscal_year_id' => $fy->id,
                        'document_no' => $runNumber,
                        'document_date' => $periodDate,
                        'description' => "سند استهلاک دوره {$periodDate} به مبلغ ".number_format($totalDepr),
                        'status' => 'posted',
                        'created_by' => $user?->id,
                    ]);
                    $journalEntryId = $entry->id;
                }
            }

            return AstDepreciationRun::query()->create([
                'run_number' => $runNumber,
                'company_id' => $companyId,
                'period_date' => $periodDate,
                'total_depreciation' => $totalDepr,
                'assets_count' => count($details),
                'journal_entry_id' => $journalEntryId,
                'status' => 'posted',
                'details' => $details,
                'created_by' => $user?->id,
            ]);
        });
    }
}
