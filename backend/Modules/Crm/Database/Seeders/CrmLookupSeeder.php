<?php

namespace Modules\Crm\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Crm\Entities\CrmSource;
use Modules\Crm\Entities\CrmStatus;
use Illuminate\Support\Facades\DB;

class CrmLookupSeeder extends Seeder
{
    public function run(): void
    {
        $sources = ['Website', 'LinkedIn', 'Exhibition', 'Referral', 'Cold Call', 'Email Campaign'];
        foreach ($sources as $i => $name) {
            CrmSource::firstOrCreate(
                ['name' => $name],
                ['sort_order' => $i, 'is_active' => true, 'color' => '#64748b']
            );
        }

        $statuses = [
            ['name' => 'New', 'color' => '#3b82f6'],
            ['name' => 'Contacted', 'color' => '#eab308'],
            ['name' => 'Qualified', 'color' => '#22c55e'],
            ['name' => 'Unqualified', 'color' => '#ef4444'],
            ['name' => 'Converted', 'color' => '#a855f7'],
        ];
        foreach ($statuses as $i => $row) {
            CrmStatus::firstOrCreate(
                ['name' => $row['name']],
                ['sort_order' => $i, 'is_active' => true, 'color' => $row['color']]
            );
        }

        $existing = DB::table('crm_pipelines')->where('is_default', true)->first();
        if ($existing) {
            $pipelineId = $existing->id;
        } else {
            $pipelineId = DB::table('crm_pipelines')->insertGetId([
                'name' => 'Default Pipeline',
                'description' => null,
                'is_default' => true,
                'is_active' => true,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('crm_stages')->where('pipeline_id', $pipelineId)->exists()) {
            return;
        }

        $stages = [
            ['name' => 'Lead', 'sort_order' => 1, 'probability' => 10, 'color' => '#94a3b8', 'is_closed' => false, 'is_won' => false],
            ['name' => 'Qualification', 'sort_order' => 2, 'probability' => 25, 'color' => '#60a5fa', 'is_closed' => false, 'is_won' => false],
            ['name' => 'Proposal', 'sort_order' => 3, 'probability' => 50, 'color' => '#fbbf24', 'is_closed' => false, 'is_won' => false],
            ['name' => 'Negotiation', 'sort_order' => 4, 'probability' => 75, 'color' => '#f97316', 'is_closed' => false, 'is_won' => false],
            ['name' => 'Won', 'sort_order' => 5, 'probability' => 100, 'color' => '#10b981', 'is_closed' => true, 'is_won' => true],
            ['name' => 'Lost', 'sort_order' => 6, 'probability' => 0, 'color' => '#ef4444', 'is_closed' => true, 'is_won' => false],
        ];

        foreach ($stages as $s) {
            DB::table('crm_stages')->insert([
                'pipeline_id' => $pipelineId,
                'name' => $s['name'],
                'description' => null,
                'sort_order' => $s['sort_order'],
                'probability' => $s['probability'],
                'color' => $s['color'],
                'is_closed' => $s['is_closed'],
                'is_won' => $s['is_won'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
