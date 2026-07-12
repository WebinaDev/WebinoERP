<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\AccWarehouse;
use Modules\Accounting\Entities\AccWarehouseDocument;
use Modules\Accounting\Entities\AccWarehouseStock;
use Modules\Scm\Entities\ScmWarehouse;
use Modules\Scm\Entities\ScmWarehouseDocument;
use Modules\Scm\Entities\ScmWarehouseStock;

class ScmMigrateFromAccountingCommand extends Command
{
    protected $signature = 'scm:migrate-from-accounting
                            {--force : Overwrite existing SCM warehouse data}';

    protected $description = 'Copy acc_warehouses, acc_warehouse_documents, and acc_warehouse_stock into SCM-owned tables with ID mapping';

    public function handle(): int
    {
        if (! Schema::hasTable('scm_warehouses')) {
            $this->error('scm_warehouses table does not exist. Run migrations first.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('acc_warehouses')) {
            $this->error('acc_warehouses table does not exist. Nothing to migrate.');

            return self::FAILURE;
        }

        $scmHasData = ScmWarehouse::query()->exists()
            || ScmWarehouseDocument::query()->exists()
            || ScmWarehouseStock::query()->exists();

        if ($scmHasData && ! $this->option('force')) {
            $this->error('SCM warehouse tables already contain data. Use --force to overwrite.');

            return self::FAILURE;
        }

        $summary = DB::transaction(function () {
            if ($this->option('force')) {
                ScmWarehouseStock::query()->delete();
                ScmWarehouseDocument::query()->delete();
                ScmWarehouse::query()->delete();
            }

            $warehouseMap = $this->migrateWarehouses();
            $documents = $this->migrateDocuments($warehouseMap);
            $stock = $this->migrateStock($warehouseMap);

            return [
                'warehouses' => count($warehouseMap),
                'documents' => $documents,
                'stock_lines' => $stock,
            ];
        });

        $this->info(sprintf(
            'Migration complete: %d warehouses, %d documents, %d stock lines.',
            $summary['warehouses'],
            $summary['documents'],
            $summary['stock_lines'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, int> acc_warehouse_id => scm_warehouse_id
     */
    private function migrateWarehouses(): array
    {
        $map = [];

        foreach (AccWarehouse::query()->orderBy('id')->get() as $acc) {
            $scm = ScmWarehouse::query()->create([
                'name' => $acc->name,
                'address' => $acc->address,
                'is_default' => $acc->is_default,
                'is_active' => $acc->is_active,
                'created_at' => $acc->created_at,
                'updated_at' => $acc->updated_at,
            ]);
            $map[$acc->id] = $scm->id;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $warehouseMap
     */
    private function migrateDocuments(array $warehouseMap): int
    {
        $count = 0;

        foreach (AccWarehouseDocument::query()->orderBy('id')->get() as $acc) {
            $scmWarehouseId = $warehouseMap[$acc->warehouse_id] ?? null;
            if (! $scmWarehouseId) {
                $this->warn("Skipping document {$acc->id}: unknown warehouse_id {$acc->warehouse_id}");

                continue;
            }

            ScmWarehouseDocument::query()->create([
                'type' => $acc->type,
                'warehouse_id' => $scmWarehouseId,
                'number' => $acc->number,
                'document_date' => $acc->document_date,
                'status' => $acc->status,
                'reference' => $acc->reference,
                'items' => $acc->items,
                'notes' => $acc->notes,
                'created_by' => $acc->created_by,
                'created_at' => $acc->created_at,
                'updated_at' => $acc->updated_at,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, int>  $warehouseMap
     */
    private function migrateStock(array $warehouseMap): int
    {
        $count = 0;

        foreach (AccWarehouseStock::query()->orderBy('id')->get() as $acc) {
            $scmWarehouseId = $warehouseMap[$acc->warehouse_id] ?? null;
            if (! $scmWarehouseId) {
                $this->warn("Skipping stock line {$acc->id}: unknown warehouse_id {$acc->warehouse_id}");

                continue;
            }

            ScmWarehouseStock::query()->create([
                'warehouse_id' => $scmWarehouseId,
                'product_id' => $acc->product_id,
                'quantity' => $acc->quantity,
                'reorder_point' => $acc->reorder_point,
                'created_at' => $acc->created_at,
                'updated_at' => $acc->updated_at,
            ]);
            $count++;
        }

        return $count;
    }
}
