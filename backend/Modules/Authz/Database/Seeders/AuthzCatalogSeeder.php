<?php

namespace Modules\Authz\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Authz\Entities\AuthzFieldPolicy;
use Modules\Authz\Entities\AuthzPermission;
use Modules\Authz\Entities\AuthzPolicy;
use Modules\Authz\Entities\AuthzRole;
use Modules\Authz\Entities\AuthzRolePermission;

class AuthzCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // 1. Sales
            ['code' => 'sales.quote.view', 'module' => 'sales', 'resource' => 'quote', 'action' => 'view', 'title_fa' => 'مشاهده پیش‌فاکتور', 'risk_level' => 'low'],
            ['code' => 'sales.quote.create', 'module' => 'sales', 'resource' => 'quote', 'action' => 'create', 'title_fa' => 'ایجاد پیش‌فاکتور', 'risk_level' => 'low'],
            ['code' => 'sales.quote.update', 'module' => 'sales', 'resource' => 'quote', 'action' => 'update', 'title_fa' => 'ویرایش پیش‌فاکتور', 'risk_level' => 'medium'],
            ['code' => 'sales.quote.approve', 'module' => 'sales', 'resource' => 'quote', 'action' => 'approve', 'title_fa' => 'تأیید پیش‌فاکتور', 'risk_level' => 'high', 'requires_reason' => true],
            ['code' => 'sales.quote.convert', 'module' => 'sales', 'resource' => 'quote', 'action' => 'convert', 'title_fa' => 'تبدیل پیش‌فاکتور به سفارش', 'risk_level' => 'medium'],
            ['code' => 'sales.quote.export', 'module' => 'sales', 'resource' => 'quote', 'action' => 'export', 'title_fa' => 'خروجی اکسل پیش‌فاکتورها', 'risk_level' => 'medium'],
            ['code' => 'sales.order.view', 'module' => 'sales', 'resource' => 'order', 'action' => 'view', 'title_fa' => 'مشاهده سفارش فروش', 'risk_level' => 'low'],
            ['code' => 'sales.order.create', 'module' => 'sales', 'resource' => 'order', 'action' => 'create', 'title_fa' => 'ثبت سفارش فروش جدید', 'risk_level' => 'low'],
            ['code' => 'sales.order.update', 'module' => 'sales', 'resource' => 'order', 'action' => 'update', 'title_fa' => 'ویرایش اقلام سفارش فروش', 'risk_level' => 'medium'],
            ['code' => 'sales.order.cancel', 'module' => 'sales', 'resource' => 'order', 'action' => 'cancel', 'title_fa' => 'لغو سفارش فروش', 'risk_level' => 'high', 'requires_reason' => true],
            ['code' => 'sales.invoice.view', 'module' => 'sales', 'resource' => 'invoice', 'action' => 'view', 'title_fa' => 'مشاهده فاکتور فروش', 'risk_level' => 'low'],
            ['code' => 'sales.invoice.create', 'module' => 'sales', 'resource' => 'invoice', 'action' => 'create', 'title_fa' => 'صدور فاکتور فروش', 'risk_level' => 'medium'],
            ['code' => 'sales.invoice.approve', 'module' => 'sales', 'resource' => 'invoice', 'action' => 'approve', 'title_fa' => 'تأیید فاکتور فروش', 'risk_level' => 'high'],
            ['code' => 'sales.invoice.void', 'module' => 'sales', 'resource' => 'invoice', 'action' => 'void', 'title_fa' => 'ابطال فاکتور فروش رسمی', 'risk_level' => 'critical', 'requires_mfa' => true, 'requires_reason' => true],
            ['code' => 'sales.invoice.record_payment', 'module' => 'sales', 'resource' => 'invoice', 'action' => 'record_payment', 'title_fa' => 'ثبت وصولی/پرداخت فاکتور', 'risk_level' => 'medium'],
            ['code' => 'sales.invoice.export', 'module' => 'sales', 'resource' => 'invoice', 'action' => 'export', 'title_fa' => 'خروجی اکسل فاکتورها', 'risk_level' => 'high', 'requires_reason' => true],
            ['code' => 'sales.cost.view', 'module' => 'sales', 'resource' => 'cost', 'action' => 'view_cost', 'title_fa' => 'مشاهده بهای تمام‌شده و قیمت خرید', 'risk_level' => 'critical'],
            ['code' => 'sales.price.edit', 'module' => 'sales', 'resource' => 'price', 'action' => 'edit_price', 'title_fa' => 'تغییر قیمت فروش محصولات', 'risk_level' => 'high'],
            ['code' => 'sales.discount.edit', 'module' => 'sales', 'resource' => 'discount', 'action' => 'edit_discount', 'title_fa' => 'اعمال یا تغییر تخفیف تجاری', 'risk_level' => 'medium'],

            // 2. SCM & Inventory
            ['code' => 'scm.stock.view', 'module' => 'scm', 'resource' => 'stock', 'action' => 'view', 'title_fa' => 'مشاهده موجودی و کاردکس کالا', 'risk_level' => 'low'],
            ['code' => 'scm.receipt.create', 'module' => 'scm', 'resource' => 'receipt', 'action' => 'create', 'title_fa' => 'ثبت رسید ورود کالا', 'risk_level' => 'medium'],
            ['code' => 'scm.receipt.confirm', 'module' => 'scm', 'resource' => 'receipt', 'action' => 'approve', 'title_fa' => 'تأیید نهایی رسید انبار', 'risk_level' => 'high'],
            ['code' => 'scm.issue.create', 'module' => 'scm', 'resource' => 'issue', 'action' => 'create', 'title_fa' => 'ثبت حواله خروج کالا', 'risk_level' => 'medium'],
            ['code' => 'scm.issue.confirm', 'module' => 'scm', 'resource' => 'issue', 'action' => 'approve', 'title_fa' => 'تأیید حواله خروج از انبار', 'risk_level' => 'high'],
            ['code' => 'scm.transfer.create', 'module' => 'scm', 'resource' => 'transfer', 'action' => 'create', 'title_fa' => 'انتقال بین انبارها و شعب', 'risk_level' => 'high'],
            ['code' => 'scm.transfer.confirm', 'module' => 'scm', 'resource' => 'transfer', 'action' => 'approve', 'title_fa' => 'تأیید تحویل انتقال بین‌انباری', 'risk_level' => 'high'],
            ['code' => 'scm.inventory.count', 'module' => 'scm', 'resource' => 'inventory', 'action' => 'manage', 'title_fa' => 'شمارش و ثبت مغایرت انبارگردانی', 'risk_level' => 'high'],
            ['code' => 'scm.serial.view', 'module' => 'scm', 'resource' => 'serial', 'action' => 'view', 'title_fa' => 'رهگیری شماره سریال و بچ‌نامبر', 'risk_level' => 'low'],
            ['code' => 'scm.serial.manage', 'module' => 'scm', 'resource' => 'serial', 'action' => 'manage', 'title_fa' => 'تولید و ابطال بارکد/سریال کالا', 'risk_level' => 'medium'],

            // 3. Finance & Accounting
            ['code' => 'fin.gl.view', 'module' => 'finance', 'resource' => 'gl', 'action' => 'view', 'title_fa' => 'مشاهده اسناد دفتر کل و تراز', 'risk_level' => 'medium'],
            ['code' => 'fin.gl.post', 'module' => 'finance', 'resource' => 'gl', 'action' => 'approve', 'title_fa' => 'ثبت قطعی و ارسال اسناد حسابداری', 'risk_level' => 'critical', 'requires_mfa' => true],
            ['code' => 'fin.ar.view', 'module' => 'finance', 'resource' => 'ar', 'action' => 'view', 'title_fa' => 'مشاهده حساب‌های دریافتنی (بدهکاران)', 'risk_level' => 'medium'],
            ['code' => 'fin.ar.collect', 'module' => 'finance', 'resource' => 'ar', 'action' => 'update', 'title_fa' => 'وصول مطالبات و ثبت دریافت چک/نقد', 'risk_level' => 'medium'],
            ['code' => 'fin.ap.view', 'module' => 'finance', 'resource' => 'ap', 'action' => 'view', 'title_fa' => 'مشاهده بدهی به بستانکاران', 'risk_level' => 'medium'],
            ['code' => 'fin.ap.pay', 'module' => 'finance', 'resource' => 'ap', 'action' => 'approve', 'title_fa' => 'دستور پرداخت و تسویه تأمین‌کننده', 'risk_level' => 'high', 'requires_reason' => true],
            ['code' => 'fin.cheque.manage', 'module' => 'finance', 'resource' => 'cheque', 'action' => 'manage', 'title_fa' => 'مدیریت و صیادی‌سازی چک‌های بانکی', 'risk_level' => 'high'],
            ['code' => 'fin.report.view', 'module' => 'finance', 'resource' => 'report', 'action' => 'view', 'title_fa' => 'مشاهده گزارش سودوزیان و ترازنامه', 'risk_level' => 'high'],
            ['code' => 'fin.tax.export', 'module' => 'finance', 'resource' => 'tax', 'action' => 'export', 'title_fa' => 'خروجی سامانه مودیان و اظهارنامه ارزش‌افزوده', 'risk_level' => 'critical'],

            // 4. Quality Management (QM)
            ['code' => 'qm.inspection.execute', 'module' => 'qm', 'resource' => 'inspection', 'action' => 'create', 'title_fa' => 'ثبت نتایج بازرسی کیفیت (IQC/FQC)', 'risk_level' => 'medium'],
            ['code' => 'qm.lot.release', 'module' => 'qm', 'resource' => 'lot', 'action' => 'approve', 'title_fa' => 'آزادسازی محموله از قرنطینه به موجودی قابل‌فروش', 'risk_level' => 'critical', 'requires_reason' => true],
            ['code' => 'qm.ncr.raise', 'module' => 'qm', 'resource' => 'ncr', 'action' => 'create', 'title_fa' => 'ثبت گزارش عدم انطباق (NCR)', 'risk_level' => 'medium'],
            ['code' => 'qm.capa.approve', 'module' => 'qm', 'resource' => 'capa', 'action' => 'approve', 'title_fa' => 'تصویب اقدام اصلاحی/پیشگیرانه (CAPA)', 'risk_level' => 'high'],

            // 5. Field Service Management (FSM)
            ['code' => 'fsm.request.view', 'module' => 'fsm', 'resource' => 'request', 'action' => 'view', 'title_fa' => 'مشاهده تیکت‌ها و درخواست‌های خدمات', 'risk_level' => 'low'],
            ['code' => 'fsm.request.create', 'module' => 'fsm', 'resource' => 'request', 'action' => 'create', 'title_fa' => 'ثبت درخواست گارانتی و خدمات در محل', 'risk_level' => 'low'],
            ['code' => 'fsm.request.assign', 'module' => 'fsm', 'resource' => 'request', 'action' => 'assign', 'title_fa' => 'تخصیص تیکت خدمات به تکنسین', 'risk_level' => 'medium'],
            ['code' => 'fsm.workorder.dispatch', 'module' => 'fsm', 'resource' => 'workorder', 'action' => 'update', 'title_fa' => 'اعزام تکنسین و صدور دستورکار FSM', 'risk_level' => 'medium'],
            ['code' => 'fsm.workorder.complete', 'module' => 'fsm', 'resource' => 'workorder', 'action' => 'approve', 'title_fa' => 'تأیید اتمام خدمات و امضای الکترونیک مشتری', 'risk_level' => 'medium'],
            ['code' => 'fsm.parts.consume', 'module' => 'fsm', 'resource' => 'parts', 'action' => 'create', 'title_fa' => 'مصرف قطعه یدکی از انبار سیار تکنسین', 'risk_level' => 'medium'],
            ['code' => 'fsm.warranty.decide', 'module' => 'fsm', 'resource' => 'warranty', 'action' => 'approve', 'title_fa' => 'تصمیم‌گیری پذیرش یا ابطال گارانتی کالا', 'risk_level' => 'high'],

            // 6. Asset Management (AM)
            ['code' => 'assets.item.view', 'module' => 'assets', 'resource' => 'item', 'action' => 'view', 'title_fa' => 'مشاهده اموال و دارایی‌های ثابت', 'risk_level' => 'low'],
            ['code' => 'assets.item.manage', 'module' => 'assets', 'resource' => 'item', 'action' => 'manage', 'title_fa' => 'مدیریت و پلاک‌کوبی اموال سازمانی', 'risk_level' => 'medium'],
            ['code' => 'assets.depr.run', 'module' => 'assets', 'resource' => 'depreciation', 'action' => 'approve', 'title_fa' => 'محاسبه دوره و صدور سند استهلاک', 'risk_level' => 'high'],
            ['code' => 'assets.transfer.confirm', 'module' => 'assets', 'resource' => 'transfer', 'action' => 'approve', 'title_fa' => 'تأیید انتقال تحویل‌داری اموال بین پرسنل/شعب', 'risk_level' => 'medium'],

            // 7. Business Intelligence (BI)
            ['code' => 'bi.dashboard.view', 'module' => 'bi', 'resource' => 'dashboard', 'action' => 'view', 'title_fa' => 'مشاهده داشبوردهای تحلیلی و KPI', 'risk_level' => 'medium'],
            ['code' => 'bi.dashboard.manage', 'module' => 'bi', 'resource' => 'dashboard', 'action' => 'manage', 'title_fa' => 'طراحی و ساخت ویجت‌های تحلیلی', 'risk_level' => 'high'],
            ['code' => 'bi.report.export', 'module' => 'bi', 'resource' => 'report', 'action' => 'export', 'title_fa' => 'خروجی اکسل/CSV کلان داده‌های BI', 'risk_level' => 'high', 'requires_reason' => true],
            ['code' => 'bi.holding.view', 'module' => 'bi', 'resource' => 'holding', 'action' => 'view', 'title_fa' => 'مشاهده گزارش‌های تلفیقی کل هلدینگ', 'risk_level' => 'critical'],

            // 8. Workflow & Automation
            ['code' => 'workflow.definition.manage', 'module' => 'workflow', 'resource' => 'definition', 'action' => 'manage', 'title_fa' => 'طراحی و ویرایش گراف فرآیندهای کسب‌وکار', 'risk_level' => 'high'],
            ['code' => 'workflow.instance.action', 'module' => 'workflow', 'resource' => 'instance', 'action' => 'approve', 'title_fa' => 'تأیید یا رد کارتابل الکترونیک (Inbox)', 'risk_level' => 'medium'],
            ['code' => 'workflow.override.force', 'module' => 'workflow', 'resource' => 'override', 'action' => 'force_status', 'title_fa' => 'شکستن بن‌بست فرآیند (Admin Override)', 'risk_level' => 'critical', 'requires_mfa' => true],

            // 9. Platform & Foundation
            ['code' => 'platform.site.deploy', 'module' => 'platform', 'resource' => 'site', 'action' => 'manage', 'title_fa' => 'استقرار و انتشار وب‌سایت‌ها و پورتال‌ها', 'risk_level' => 'high'],
            ['code' => 'platform.domain.manage', 'module' => 'platform', 'resource' => 'domain', 'action' => 'manage', 'title_fa' => 'مدیریت دامنه‌ها و گواهی SSL', 'risk_level' => 'critical'],
            ['code' => 'platform.member.manage', 'module' => 'platform', 'resource' => 'member', 'action' => 'manage', 'title_fa' => 'مدیریت کاربران، عضویت و نقش‌ها', 'risk_level' => 'critical'],
        ];

        foreach ($permissions as $p) {
            AuthzPermission::firstOrCreate(['code' => $p['code']], $p);
        }

        // Standard seed roles
        $roles = [
            ['code' => 'super_admin', 'title_fa' => 'مدیر ارشد هلدینگ (System Super Admin)', 'is_system' => true],
            ['code' => 'company_admin', 'title_fa' => 'مدیر اجرایی شرکت (Company Admin)', 'is_system' => true],
            ['code' => 'sales_manager', 'title_fa' => 'مدیر فروش', 'is_system' => true],
            ['code' => 'sales_agent', 'title_fa' => 'کارشناس فروش', 'is_system' => true],
            ['code' => 'warehouse_operator', 'title_fa' => 'انباردار و کارشناس لجستیک', 'is_system' => true],
            ['code' => 'finance_manager', 'title_fa' => 'مدیر مالی و خزانه', 'is_system' => true],
            ['code' => 'finance_clerk', 'title_fa' => 'کمک‌حسابدار و دریافت/پرداخت', 'is_system' => true],
            ['code' => 'technician', 'title_fa' => 'تکنسین فنی خدمات پس از فروش', 'is_system' => true],
            ['code' => 'auditor', 'title_fa' => 'حسابرس و ناظر دسترسی (Read-Only Auditor)', 'is_system' => true],
        ];

        foreach ($roles as $r) {
            AuthzRole::firstOrCreate(['code' => $r['code'], 'company_id' => null], $r);
        }

        // Standard Field-level Security rules
        $fieldPolicies = [
            ['resource' => 'sales.invoice', 'field' => 'cost_price', 'permission_needed' => 'sales.cost.view', 'effect' => 'hide'],
            ['resource' => 'sales.invoice', 'field' => 'buy_price', 'permission_needed' => 'sales.cost.view', 'effect' => 'hide'],
            ['resource' => 'sales.invoice', 'field' => 'profit_margin', 'permission_needed' => 'sales.cost.view', 'effect' => 'hide'],
            ['resource' => 'acc_cash_accounts', 'field' => 'sheba', 'permission_needed' => 'fin.gl.view', 'effect' => 'mask'],
            ['resource' => 'hrm_payroll', 'field' => 'base_salary', 'permission_needed' => 'platform.member.manage', 'effect' => 'hide'],
        ];

        foreach ($fieldPolicies as $fp) {
            AuthzFieldPolicy::firstOrCreate(['resource' => $fp['resource'], 'field' => $fp['field']], $fp);
        }

        // Standard ABAC conditional policies
        $policies = [
            [
                'name' => 'سقف تأیید تخفیف کارشناس فروش (حداکثر ۱۰ میلیون ریال)',
                'target_permission' => 'sales.discount.edit',
                'target_resource' => 'sales.discount',
                'condition_expression' => 'amount <= 10000000',
                'effect' => 'allow',
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'تأیید فاکتورهای بالای ۵۰ میلیون ریال نیازمند گردش کار',
                'target_permission' => 'sales.invoice.approve',
                'target_resource' => 'sales.invoice',
                'condition_expression' => 'amount <= 50000000',
                'effect' => 'allow',
                'priority' => 20,
                'is_active' => true,
            ],
        ];

        foreach ($policies as $pol) {
            AuthzPolicy::firstOrCreate(['name' => $pol['name']], $pol);
        }
    }
}
