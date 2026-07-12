<?php

namespace Modules\Mfg\Services;

use Illuminate\Support\Facades\DB;
use Modules\Mfg\Entities\MfgBom;
use Modules\Mfg\Entities\MfgBomLine;

class MfgBomService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(array $data, array $lines = []): MfgBom
    {
        return DB::transaction(function () use ($data, $lines) {
            $bom = MfgBom::query()->create($data);
            $this->syncLines($bom, $lines);

            return $bom->load('lines');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $lines
     */
    public function update(MfgBom $bom, array $data, ?array $lines = null): MfgBom
    {
        return DB::transaction(function () use ($bom, $data, $lines) {
            $bom->update($data);
            if ($lines !== null) {
                $this->syncLines($bom, $lines);
            }

            return $bom->fresh()->load('lines');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function syncLines(MfgBom $bom, array $lines): void
    {
        MfgBomLine::query()->where('bom_id', $bom->id)->delete();
        foreach ($lines as $row) {
            MfgBomLine::query()->create([
                'bom_id' => $bom->id,
                'component_product_id' => (int) $row['component_product_id'],
                'quantity' => (float) ($row['quantity'] ?? 1),
                'unit' => $row['unit'] ?? null,
                'scrap_percent' => (float) ($row['scrap_percent'] ?? 0),
            ]);
        }
    }
}
