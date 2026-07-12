<?php

namespace Modules\Core\Services;

use App\Models\User;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemSetting;

/**
 * Builds sidebar menu parity with webinocrm WebinoCRM_Sidebar_Menu_Builder (manager / sales / finance / team / customer).
 */
class DashboardNavigationService
{
    /**
     * @return list<array{type?:string, id?:string, title?:string, href?:string, icon?:string}>
     */
    public function menuForUser(User $user): array
    {
        $role = $this->resolveDashboardRole($user);

        return match ($role) {
            RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER => $this->managerMenu(),
            RolesAndPermissionsSeeder::ROLE_FINANCE_MANAGER => $this->financeMenu(),
            RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER => $this->teamMemberMenu(),
            RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT => $this->salesMenu(),
            RolesAndPermissionsSeeder::ROLE_CLIENT => $this->customerMenu(),
            default => [],
        };
    }

    public function resolveDashboardRole(User $user): string
    {
        $order = [
            RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER,
            RolesAndPermissionsSeeder::ROLE_FINANCE_MANAGER,
            RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER,
            RolesAndPermissionsSeeder::ROLE_SALES_CONSULTANT,
            RolesAndPermissionsSeeder::ROLE_CLIENT,
        ];

        foreach ($order as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return 'guest';
    }

    /**
     * Parity with webinocrm `WebinoCRM_Sidebar_Menu_Builder::is_module_enabled()`.
     */
    private function moduleEnabled(string $moduleKey): bool
    {
        $key = 'module_'.$moduleKey.'_enabled';
        $v = SystemSetting::get($key, '1');

        return (string) $v === '1';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function managerMenu(): array
    {
        $base = '/dashboard';

        $items = [];

        if ($this->moduleEnabled('dashboard')) {
            $items[] = ['type' => 'category', 'title' => 'داشبورد پیشفرض'];
            $items[] = ['id' => 'dashboard', 'title' => 'داشبورد', 'href' => $base, 'icon' => 'Home'];
            $items[] = ['id' => 'reports', 'title' => 'گزارشات کلی', 'href' => $base.'/reports', 'icon' => 'BarChart3'];
        }

        if ($this->moduleEnabled('projects')) {
            $items[] = ['type' => 'category', 'title' => 'مدیریت پروژه'];
            $items[] = ['id' => 'projects', 'title' => 'پروژه‌ها', 'href' => $base.'/projects', 'icon' => 'FolderOpen'];
            $items[] = ['id' => 'contracts', 'title' => 'قراردادها', 'href' => $base.'/contracts', 'icon' => 'FileText'];
            $items[] = ['id' => 'tasks', 'title' => 'وظایف', 'href' => $base.'/tasks', 'icon' => 'CheckSquare'];
            $items[] = ['id' => 'appointments', 'title' => 'قرار ملاقات‌ها', 'href' => $base.'/appointments', 'icon' => 'CalendarCheck'];
        }

        if ($this->moduleEnabled('crm')) {
            $items[] = ['type' => 'category', 'title' => 'ارتباط با مشتری'];
            $items[] = ['id' => 'leads', 'title' => 'سرنخ‌ها', 'href' => $base.'/leads', 'icon' => 'UserPlus'];
            $items[] = ['id' => 'customers', 'title' => 'مشتریان', 'href' => $base.'/customers', 'icon' => 'Users'];
            $items[] = ['id' => 'tickets', 'title' => 'تیکت‌ها', 'href' => $base.'/tickets', 'icon' => 'Headphones'];
            $items[] = ['id' => 'invoices', 'title' => 'پیش‌فاکتورها', 'href' => $base.'/invoices', 'icon' => 'FileSpreadsheet'];
            $items[] = ['id' => 'services', 'title' => 'خدمات و محصولات', 'href' => $base.'/services', 'icon' => 'Package'];
            $items[] = ['id' => 'campaigns', 'title' => 'کمپین‌ها', 'href' => $base.'/campaigns', 'icon' => 'Megaphone'];
            $items[] = ['id' => 'consultations', 'title' => 'مشاوره‌ها', 'href' => $base.'/consultations', 'icon' => 'MessageCircle'];
            $items[] = ['id' => 'staff', 'title' => 'کارکنان', 'href' => $base.'/staff', 'icon' => 'UserCog'];
            $items[] = ['id' => 'bale-business', 'title' => 'ربات بله / کسب‌وکار', 'href' => $base.'/bale-business', 'icon' => 'Bot'];
        }

        if ($this->moduleEnabled('accounting')) {
            $items[] = ['type' => 'category', 'title' => 'حسابداری'];
            $items[] = ['id' => 'accounting', 'title' => 'داشبورد حسابداری', 'href' => $base.'/accounting', 'icon' => 'Calculator'];
            $items[] = ['id' => 'accounting-persons', 'title' => 'اشخاص (طرف‌های حساب)', 'href' => $base.'/accounting/persons', 'icon' => 'User'];
            $items[] = ['id' => 'accounting-products', 'title' => 'کالا و خدمات', 'href' => $base.'/accounting/products', 'icon' => 'Box'];
            $items[] = ['id' => 'accounting-invoices', 'title' => 'فاکتورها', 'href' => $base.'/accounting/invoices', 'icon' => 'FileText'];
            $items[] = ['id' => 'accounting-cash-accounts', 'title' => 'حساب‌های بانک/صندوق', 'href' => $base.'/accounting/cash-accounts', 'icon' => 'Landmark'];
            $items[] = ['id' => 'accounting-receipts', 'title' => 'رسید و پرداخت', 'href' => $base.'/accounting/receipts', 'icon' => 'ArrowLeftRight'];
            $items[] = ['id' => 'accounting-checks', 'title' => 'چک‌ها', 'href' => $base.'/accounting/checks', 'icon' => 'CreditCard'];
            $items[] = ['id' => 'accounting-chart', 'title' => 'نمودار حساب‌ها', 'href' => $base.'/accounting/chart', 'icon' => 'BookOpen'];
            $items[] = ['id' => 'accounting-journals', 'title' => 'اسناد حسابداری', 'href' => $base.'/accounting/journals', 'icon' => 'FileStack'];
            $items[] = ['id' => 'accounting-ledger', 'title' => 'دفتر کل / معین', 'href' => $base.'/accounting/ledger', 'icon' => 'BookMarked'];
            $items[] = ['id' => 'accounting-reports', 'title' => 'گزارشات مالی', 'href' => $base.'/accounting/reports', 'icon' => 'LineChart'];
            $items[] = ['id' => 'accounting-fiscal-year', 'title' => 'سال مالی', 'href' => $base.'/accounting/fiscal-year', 'icon' => 'Calendar'];
            $items[] = ['id' => 'accounting-warehouses', 'title' => 'انبارها', 'href' => $base.'/accounting/warehouses', 'icon' => 'Warehouse'];
            $items[] = ['id' => 'accounting-warehouse-stock', 'title' => 'موجودی انبار', 'href' => $base.'/accounting/warehouse-stock', 'icon' => 'Boxes'];
            $items[] = ['id' => 'accounting-warehouse-inbound', 'title' => 'ورود کالا', 'href' => $base.'/accounting/warehouse-inbound', 'icon' => 'ArrowDownToLine'];
            $items[] = ['id' => 'accounting-warehouse-outbound', 'title' => 'خروج کالا', 'href' => $base.'/accounting/warehouse-outbound', 'icon' => 'ArrowUpFromLine'];
            $items[] = ['id' => 'accounting-warehouse-audit', 'title' => 'انبارگردانی', 'href' => $base.'/accounting/warehouse-audit', 'icon' => 'ClipboardCheck'];
            $items[] = ['id' => 'accounting-settings', 'title' => 'تنظیمات حسابداری', 'href' => $base.'/accounting/settings', 'icon' => 'Settings'];
        }

        $items[] = ['type' => 'category', 'title' => 'حساب کاربری'];
        $items[] = ['id' => 'profile', 'title' => 'پروفایل من', 'href' => $base.'/profile', 'icon' => 'UserCircle'];

        $items[] = ['type' => 'category', 'title' => 'سیستم و تنظیمات'];
        $items[] = ['id' => 'licenses', 'title' => 'لایسنس‌ها', 'href' => $base.'/licenses', 'icon' => 'Key'];
        $items[] = ['id' => 'hosting-infra', 'title' => 'میزبانی و زیرساخت', 'href' => $base.'/hosting-infra', 'icon' => 'Server'];
        $items[] = ['id' => 'logs', 'title' => 'لاگ‌ها', 'href' => $base.'/logs', 'icon' => 'ScrollText'];
        $items[] = ['id' => 'visitor-statistics', 'title' => 'آمار بازدید', 'href' => $base.'/visitor-statistics', 'icon' => 'Activity'];
        $items[] = ['id' => 'settings', 'title' => 'تنظیمات عمومی', 'href' => $base.'/settings', 'icon' => 'Settings'];

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function financeMenu(): array
    {
        $base = '/dashboard';

        $items = [
            ['type' => 'category', 'title' => 'داشبورد پیشفرض'],
            ['id' => 'dashboard', 'title' => 'داشبورد', 'href' => $base, 'icon' => 'Home'],
            ['id' => 'reports', 'title' => 'گزارشات', 'href' => $base.'/reports', 'icon' => 'BarChart3'],
            ['type' => 'category', 'title' => 'ارتباط با مشتری'],
            ['id' => 'contracts', 'title' => 'قراردادها', 'href' => $base.'/contracts', 'icon' => 'FileText'],
            ['id' => 'invoices', 'title' => 'پیش‌فاکتورها', 'href' => $base.'/invoices', 'icon' => 'FileSpreadsheet'],
        ];

        if ($this->moduleEnabled('accounting')) {
            $items[] = ['type' => 'category', 'title' => 'حسابداری'];
            $items[] = ['id' => 'accounting', 'title' => 'داشبورد حسابداری', 'href' => $base.'/accounting', 'icon' => 'Calculator'];
            $items[] = ['id' => 'accounting-persons', 'title' => 'اشخاص (طرف‌های حساب)', 'href' => $base.'/accounting/persons', 'icon' => 'User'];
            $items[] = ['id' => 'accounting-products', 'title' => 'کالا و خدمات', 'href' => $base.'/accounting/products', 'icon' => 'Box'];
            $items[] = ['id' => 'accounting-invoices', 'title' => 'فاکتورها', 'href' => $base.'/accounting/invoices', 'icon' => 'FileText'];
            $items[] = ['id' => 'accounting-cash-accounts', 'title' => 'حساب‌های بانک/صندوق', 'href' => $base.'/accounting/cash-accounts', 'icon' => 'Landmark'];
            $items[] = ['id' => 'accounting-receipts', 'title' => 'رسید و پرداخت', 'href' => $base.'/accounting/receipts', 'icon' => 'ArrowLeftRight'];
            $items[] = ['id' => 'accounting-checks', 'title' => 'چک‌ها', 'href' => $base.'/accounting/checks', 'icon' => 'CreditCard'];
            $items[] = ['id' => 'accounting-chart', 'title' => 'نمودار حساب‌ها', 'href' => $base.'/accounting/chart', 'icon' => 'BookOpen'];
            $items[] = ['id' => 'accounting-journals', 'title' => 'اسناد حسابداری', 'href' => $base.'/accounting/journals', 'icon' => 'FileStack'];
            $items[] = ['id' => 'accounting-ledger', 'title' => 'دفتر کل / معین', 'href' => $base.'/accounting/ledger', 'icon' => 'BookMarked'];
            $items[] = ['id' => 'accounting-reports', 'title' => 'گزارشات مالی', 'href' => $base.'/accounting/reports', 'icon' => 'LineChart'];
            $items[] = ['id' => 'accounting-fiscal-year', 'title' => 'سال مالی', 'href' => $base.'/accounting/fiscal-year', 'icon' => 'Calendar'];
            $items[] = ['id' => 'accounting-warehouses', 'title' => 'انبارها', 'href' => $base.'/accounting/warehouses', 'icon' => 'Warehouse'];
            $items[] = ['id' => 'accounting-warehouse-stock', 'title' => 'موجودی انبار', 'href' => $base.'/accounting/warehouse-stock', 'icon' => 'Boxes'];
            $items[] = ['id' => 'accounting-warehouse-inbound', 'title' => 'ورود کالا', 'href' => $base.'/accounting/warehouse-inbound', 'icon' => 'ArrowDownToLine'];
            $items[] = ['id' => 'accounting-warehouse-outbound', 'title' => 'خروج کالا', 'href' => $base.'/accounting/warehouse-outbound', 'icon' => 'ArrowUpFromLine'];
            $items[] = ['id' => 'accounting-warehouse-audit', 'title' => 'انبارگردانی', 'href' => $base.'/accounting/warehouse-audit', 'icon' => 'ClipboardCheck'];
            $items[] = ['id' => 'accounting-settings', 'title' => 'تنظیمات حسابداری', 'href' => $base.'/accounting/settings', 'icon' => 'Settings'];
        }

        $items[] = ['type' => 'category', 'title' => 'حساب کاربری'];
        $items[] = ['id' => 'profile', 'title' => 'پروفایل من', 'href' => $base.'/profile', 'icon' => 'UserCircle'];

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function teamMemberMenu(): array
    {
        $base = '/dashboard';

        $items = [];

        if ($this->moduleEnabled('dashboard')) {
            $items[] = ['type' => 'category', 'title' => 'داشبورد پیشفرض'];
            $items[] = ['id' => 'dashboard', 'title' => 'داشبورد', 'href' => $base, 'icon' => 'Home'];
        }

        if ($this->moduleEnabled('projects') || $this->moduleEnabled('crm')) {
            $items[] = ['type' => 'category', 'title' => 'مدیریت پروژه'];
            if ($this->moduleEnabled('projects')) {
                $items[] = ['id' => 'projects', 'title' => 'پروژه‌های من', 'href' => $base.'/projects', 'icon' => 'FolderOpen'];
                $items[] = ['id' => 'tasks', 'title' => 'وظایف من', 'href' => $base.'/tasks', 'icon' => 'CheckSquare'];
            }
            if ($this->moduleEnabled('crm')) {
                $items[] = ['id' => 'tickets', 'title' => 'تیکت‌ها', 'href' => $base.'/tickets', 'icon' => 'Headphones'];
            }
        }

        $items[] = ['type' => 'category', 'title' => 'حساب کاربری'];
        $items[] = ['id' => 'profile', 'title' => 'پروفایل من', 'href' => $base.'/profile', 'icon' => 'UserCircle'];

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesMenu(): array
    {
        $base = '/dashboard';

        $items = [];

        if ($this->moduleEnabled('dashboard')) {
            $items[] = ['type' => 'category', 'title' => 'داشبورد پیشفرض'];
            $items[] = ['id' => 'dashboard', 'title' => 'داشبورد', 'href' => $base, 'icon' => 'Home'];
        }

        if ($this->moduleEnabled('crm')) {
            $items[] = ['type' => 'category', 'title' => 'ارتباط با مشتری'];
            $items[] = ['id' => 'leads', 'title' => 'سرنخ‌ها', 'href' => $base.'/leads', 'icon' => 'UserPlus'];
            $items[] = ['id' => 'contracts', 'title' => 'قراردادها', 'href' => $base.'/contracts', 'icon' => 'FileText'];
            $items[] = ['id' => 'customers', 'title' => 'مشتریان', 'href' => $base.'/customers', 'icon' => 'Users'];
        }

        $items[] = ['type' => 'category', 'title' => 'حساب کاربری'];
        $items[] = ['id' => 'profile', 'title' => 'پروفایل من', 'href' => $base.'/profile', 'icon' => 'UserCircle'];

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customerMenu(): array
    {
        $base = '/dashboard';

        $items = [];

        if ($this->moduleEnabled('dashboard')) {
            $items[] = ['id' => 'dashboard', 'title' => 'داشبورد', 'href' => $base, 'icon' => 'Home'];
        }
        if ($this->moduleEnabled('projects')) {
            $items[] = ['id' => 'projects', 'title' => 'پروژه‌های من', 'href' => $base.'/projects', 'icon' => 'FolderOpen'];
            $items[] = ['id' => 'contracts', 'title' => 'قراردادهای من', 'href' => $base.'/contracts', 'icon' => 'FileText'];
            $items[] = ['id' => 'invoices', 'title' => 'پیش‌فاکتورهای من', 'href' => $base.'/invoices', 'icon' => 'FileSpreadsheet'];
        }
        if ($this->moduleEnabled('crm')) {
            $items[] = ['id' => 'tickets', 'title' => 'تیکت‌های پشتیبانی', 'href' => $base.'/tickets', 'icon' => 'Headphones'];
        }

        $items[] = ['type' => 'category', 'title' => 'حساب کاربری'];
        $items[] = ['id' => 'profile', 'title' => 'پروفایل من', 'href' => $base.'/profile', 'icon' => 'UserCircle'];

        return $items;
    }
}
