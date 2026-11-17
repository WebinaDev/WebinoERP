<?php

namespace Modules\Workflow\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Workflow\Entities\WfDefinition;

class WorkflowDefaultDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'name' => 'تأیید پیش‌فاکتور با تخفیف بالای سقف مجاز',
                'module_slug' => 'sales',
                'entity_type' => 'quote',
                'conditions' => ['min_discount_pct' => 15, 'min_amount' => 100000000],
                'steps' => [
                    ['order' => 1, 'type' => 'role', 'target' => 'sales_manager', 'mode' => 'sequential', 'sla_hours' => 24],
                    ['order' => 2, 'type' => 'role', 'target' => 'finance_manager', 'mode' => 'sequential', 'sla_hours' => 24],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'تأیید حقوقی و مالی قراردادهای سازمانی',
                'module_slug' => 'docs',
                'entity_type' => 'contract',
                'conditions' => ['min_amount' => 500000000],
                'steps' => [
                    ['order' => 1, 'type' => 'role', 'target' => 'legal_advisor', 'mode' => 'parallel', 'sla_hours' => 48],
                    ['order' => 2, 'type' => 'role', 'target' => 'finance_manager', 'mode' => 'sequential', 'sla_hours' => 24],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'تأیید پرداخت و خروج وجه از خزانه‌داری',
                'module_slug' => 'accounting',
                'entity_type' => 'payment',
                'conditions' => ['min_amount' => 200000000],
                'steps' => [
                    ['order' => 1, 'type' => 'role', 'target' => 'finance_manager', 'mode' => 'sequential', 'sla_hours' => 12],
                    ['order' => 2, 'type' => 'role', 'target' => 'system_manager', 'mode' => 'sequential', 'sla_hours' => 24],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'تأیید آزادسازی مشروط کالای نامنطبق (QM)',
                'module_slug' => 'qm',
                'entity_type' => 'conditional_release',
                'conditions' => null,
                'steps' => [
                    ['order' => 1, 'type' => 'role', 'target' => 'quality_manager', 'mode' => 'sequential', 'sla_hours' => 12],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'تأیید اسقاط دارایی‌های سرمایه‌ای (AM)',
                'module_slug' => 'assets',
                'entity_type' => 'asset_disposal',
                'conditions' => ['min_amount' => 100000000],
                'steps' => [
                    ['order' => 1, 'type' => 'role', 'target' => 'asset_manager', 'mode' => 'sequential', 'sla_hours' => 24],
                    ['order' => 2, 'type' => 'role', 'target' => 'finance_manager', 'mode' => 'sequential', 'sla_hours' => 24],
                ],
                'is_active' => true,
            ],
            [
                'name' => 'تأیید مرخصی و مأموریت پرسنلی (HRM)',
                'module_slug' => 'hrm',
                'entity_type' => 'leave',
                'conditions' => null,
                'steps' => [
                    ['order' => 1, 'type' => 'role', 'target' => 'hr_manager', 'mode' => 'sequential', 'sla_hours' => 24],
                ],
                'is_active' => true,
            ],
        ];

        foreach ($definitions as $def) {
            WfDefinition::updateOrCreate(
                ['module_slug' => $def['module_slug'], 'entity_type' => $def['entity_type']],
                $def
            );
        }
    }
}
