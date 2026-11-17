<?php

namespace Modules\Authz\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Authz\Entities\AuthzFieldPolicy;
use Modules\Authz\Entities\AuthzPermission;
use Modules\Authz\Entities\AuthzRole;

class AuthzCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Sales
            ['code' => 'sales.quote.view', 'module' => 'sales', 'resource' => 'quote', 'action' => 'view', 'title_fa' => 'مشاهده پیش‌فاکتور', 'risk_level' => 'low'],
            ['code' => 'sales.quote.create', 'module' => 'sales', 'resource' => 'quote', 'action' => 'create', 'title_fa' => 'ایجاد پیش‌فاکتور', 'risk_level' => 'low'],
            ['code' => 'sales.quote.update', 'module' => 'sales', 'resource' => 'quote', 'action' => 'update', 'title_fa' => 'ویرایش پیش‌فاکتور', 'risk_level' => 'medium'],
            ['code' => 'sales.quote.approve', 'module' => 'sales', 'resource' => 'quote', 'action' => 'approve', 'title_fa' => 'تأیید پیش‌فاکتور', 'risk_level' => 'high', 'requires_reason' => true],
            ['code' => 'sales.invoice.view', 'module' => 'sales', 'resource' => 'invoice', 'action' => 'view', 'title_fa' => 'مشاهده فاکتور فروش', 'risk_level' => 'low'],
            ['code' => 'sales.invoice.create', 'module' => 'sales', 'resource' => 'invoice', 'action' => 'create', 'title_fa' => 'صدور فاکتور فروش', 'risk_level' => 'medium'],
            ['code' => 'sales.invoice.approve', 'module' => 'sales', 'resource' => 'invoice', 'action' => 'approve', 'title_fa' => 'تأیید فاکتور فروش', 'risk_level' => 'high'],
            ['code' => 'sales.invoice.export', 'module' => 'sales', 'resource' => 'invoice', 'action' => 'export', 'title_fa' => 'خروجی اکسل فاکتورها', 'risk_level' => 'high', 'requires_reason' => true],
            ['code' => 'sales.cost.view', 'module' => 'sales', 'resource' => 'cost', 'action' => 'view', 'title_fa' => 'مشاهده بهای تمام‌شده و قیمت خرید', 'risk_level' => 'critical'],
            ['code' => 'sales.discount.edit', 'module' => 'sales', 'resource' => 'discount', 'action' => 'update', 'title_fa' => 'اعمال یا تغییر تخفیف', 'risk_level' => 'medium'],

            // SCM
            ['code' => 'scm.stock.view', 'module' => 'scm', 'resource' => 'stock', 'action' => 'view', 'title_fa' => 'مشاهده موجودی انبار', 'risk_level' => 'low'],
            ['code' => 'scm.receipt.create', 'module' => 'scm', 'resource' => 'receipt', 'action' => 'create', 'title_fa' => 'ثبت رسید ورود کالا', 'risk_level' => 'medium'],
            ['code' => 'scm.issue.create', 'module' => 'scm', 'resource' => 'issue', 'action' => 'create', 'title_fa' => 'ثبت حواله خروج کالا', 'risk_level' => 'medium'],
            ['code' => 'scm.transfer.create', 'module' => 'scm', 'resource' => 'transfer', 'action' => 'create', 'title_fa' => 'انتقال بین انبارها/شعب', 'risk_level' => 'high'],

            // Finance
            ['code' => 'fin.gl.view', 'module' => 'finance', 'resource' => 'gl', 'action' => 'view', 'title_fa' => 'مشاهده دفتر کل و اسناد', 'risk_level' => 'medium'],
            ['code' => 'fin.gl.post', 'module' => 'finance', 'resource' => 'gl', 'action' => 'approve', 'title_fa' => 'ثبت قطعی و ارسال اسناد حسابداری', 'risk_level' => 'critical', 'requires_mfa' => true],
            ['code' => 'fin.ar.collect', 'module' => 'finance', 'resource' => 'ar', 'action' => 'update', 'title_fa' => 'وصول مطالبات و ثبت دریافت', 'risk_level' => 'medium'],

            // FSM
            ['code' => 'fsm.workorder.dispatch', 'module' => 'fsm', 'resource' => 'workorder', 'action' => 'update', 'title_fa' => 'اعزام تکنسین خدمات', 'risk_level' => 'medium'],
            ['code' => 'fsm.parts.consume', 'module' => 'fsm', 'resource' => 'parts', 'action' => 'create', 'title_fa' => 'مصرف قطعه یدکی از انبار', 'risk_level' => 'medium'],

            // QM
            ['code' => 'qm.inspection.execute', 'module' => 'qm', 'resource' => 'inspection', 'action' => 'create', 'title_fa' => 'ثبت نتایج بازرسی کیفیت', 'risk_level' => 'medium'],
            ['code' => 'qm.lot.release', 'module' => 'qm', 'resource' => 'lot', 'action' => 'approve', 'title_fa' => 'آزادسازی محموله از قرنطینه', 'risk_level' => 'critical'],

            // Assets
            ['code' => 'assets.item.manage', 'module' => 'assets', 'resource' => 'item', 'action' => 'manage', 'title_fa' => 'مدیریت و پلاک‌کوبی اموال', 'risk_level' => 'medium'],
            ['code' => 'assets.depr.run', 'module' => 'assets', 'resource' => 'depreciation', 'action' => 'approve', 'title_fa' => 'محاسبه و سند استهلاک', 'risk_level' => 'high'],
        ];

        foreach ($permissions as $p) {
            AuthzPermission::firstOrCreate(['code' => $p['code']], $p);
        }

        // Standard seed roles
        $roles = [
            ['code' => 'sales_manager', 'title_fa' => 'مدیر فروش', 'is_system' => true],
            ['code' => 'sales_agent', 'title_fa' => 'کارشناس فروش', 'is_system' => true],
            ['code' => 'warehouse_operator', 'title_fa' => 'انباردار', 'is_system' => true],
            ['code' => 'finance_manager', 'title_fa' => 'مدیر مالی', 'is_system' => true],
            ['code' => 'technician', 'title_fa' => 'تکنسین فنی', 'is_system' => true],
        ];

        foreach ($roles as $r) {
            AuthzRole::firstOrCreate(['code' => $r['code'], 'company_id' => null], $r);
        }

        // Standard Field-level Security rules
        $fieldPolicies = [
            ['resource' => 'sales.invoice', 'field' => 'cost_price', 'permission_needed' => 'sales.cost.view', 'effect' => 'hide'],
            ['resource' => 'sales.invoice', 'field' => 'buy_price', 'permission_needed' => 'sales.cost.view', 'effect' => 'hide'],
            ['resource' => 'acc_cash_accounts', 'field' => 'sheba', 'permission_needed' => 'fin.gl.view', 'effect' => 'mask'],
        ];

        foreach ($fieldPolicies as $fp) {
            AuthzFieldPolicy::firstOrCreate(['resource' => $fp['resource'], 'field' => $fp['field']], $fp);
        }
    }
}
