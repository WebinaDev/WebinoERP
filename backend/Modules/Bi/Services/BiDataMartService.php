<?php

namespace Modules\Bi\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BiDataMartService
{
    /**
     * KPI snapshot computed from live tables only. A value the data cannot
     * support (no cost data, no tickets, ...) is returned as null, never a guess.
     */
    public function getKpiSummary(array $filters = []): array
    {
        $companyId = $filters['company_id'] ?? null;
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        // 1. Sales in period (issue date, falling back to creation date)
        $totalSales = 0.0;
        $grossMargin = null;
        if (Schema::hasTable('sales_invoices')) {
            $salesQuery = DB::table('sales_invoices')->where('status', '!=', 'cancelled')
                ->whereRaw('COALESCE(issue_date, DATE(created_at)) BETWEEN ? AND ?', [$from, $to]);
            if ($companyId && Schema::hasColumn('sales_invoices', 'company_id')) {
                $salesQuery->where('company_id', $companyId);
            }
            $totalSales = (float) (clone $salesQuery)->sum('total');

            // Gross margin only when product cost prices exist for the invoiced lines.
            if (Schema::hasTable('sales_invoice_lines') && Schema::hasTable('acc_products') && Schema::hasColumn('acc_products', 'buy_price')) {
                $invoiceIds = (clone $salesQuery)->pluck('id');
                $lines = DB::table('sales_invoice_lines as l')
                    ->join('acc_products as p', 'p.id', '=', 'l.product_id')
                    ->whereIn('l.invoice_id', $invoiceIds)
                    ->selectRaw('SUM(l.line_total - l.tax_amount) as revenue, SUM(l.quantity * p.buy_price) as cost, COUNT(*) as n')
                    ->first();
                if ($lines && (int) $lines->n > 0 && (float) $lines->cost > 0) {
                    $grossMargin = (float) $lines->revenue - (float) $lines->cost;
                }
            }
        }

        // 2. Inventory on hand
        $totalStockQty = 0.0;
        if (Schema::hasTable('scm_warehouse_stock')) {
            $totalStockQty = (float) DB::table('scm_warehouse_stock')->sum('quantity');
        }

        // 3. Receivables: unpaid part of sales invoices, and invoices more than 90 days past due
        $totalAr = 0.0;
        $agingCritical = 0;
        if (Schema::hasTable('sales_invoices') && Schema::hasColumn('sales_invoices', 'paid_amount')) {
            $ar = DB::table('sales_invoices')->where('status', '!=', 'cancelled')->where('payment_status', '!=', 'paid');
            if ($companyId && Schema::hasColumn('sales_invoices', 'company_id')) {
                $ar->where('company_id', $companyId);
            }
            $totalAr = (float) (clone $ar)->sum(DB::raw('total - paid_amount'));
            if (Schema::hasColumn('sales_invoices', 'due_date')) {
                $agingCritical = (clone $ar)->whereNotNull('due_date')->where('due_date', '<', now()->subDays(90)->toDateString())->count();
            }
        }

        // 4. Lead conversion
        $leadTotal = 0;
        $leadConverted = 0;
        if (Schema::hasTable('crm_leads')) {
            $leadTotal = DB::table('crm_leads')->count();
            $leadConverted = DB::table('crm_leads')->where('status', 'converted')->count();
        }

        // 5. Projects
        $projectCount = Schema::hasTable('prj_projects') ? DB::table('prj_projects')->count() : 0;

        // 6. Support: share of tickets closed
        $ticketCount = 0;
        $ticketClosed = 0;
        if (Schema::hasTable('prj_tickets')) {
            $ticketCount = DB::table('prj_tickets')->count();
            $ticketClosed = DB::table('prj_tickets')->where('status', 'closed')->count();
        }

        return [
            'period' => ['from' => $from, 'to' => $to],
            'sales_margin' => [
                'total_sales' => $totalSales,
                'gross_margin' => $grossMargin,
                'margin_percent' => ($grossMargin !== null && $totalSales > 0) ? round(($grossMargin / $totalSales) * 100, 1) : null,
            ],
            'inventory_turnover' => [
                'total_units' => $totalStockQty,
                'turnover_ratio' => null,
            ],
            'ar_collection' => [
                'outstanding_ar' => $totalAr,
                'aging_critical_count' => $agingCritical,
            ],
            'lead_conversion' => [
                'total_leads' => $leadTotal,
                'converted_leads' => $leadConverted,
                'conversion_rate' => $leadTotal > 0 ? round(($leadConverted / $leadTotal) * 100, 1) : null,
            ],
            'project_profitability' => [
                'active_projects' => $projectCount,
                'average_margin_percent' => null,
            ],
            'support_sla' => [
                'total_tickets' => $ticketCount,
                'compliance_rate' => $ticketCount > 0 ? round(($ticketClosed / $ticketCount) * 100, 1) : null,
            ],
        ];
    }

    public function getExecutiveDashboard(string $role, array $filters = []): array
    {
        $kpis = $this->getKpiSummary($filters);

        return match ($role) {
            'ceo' => [
                'role' => 'ceo',
                'title' => 'داشبورد مدیریتی مدیرعامل (CEO)',
                'cards' => [
                    ['key' => 'total_sales', 'title' => 'فروش کل', 'value' => $kpis['sales_margin']['total_sales'], 'unit' => 'currency'],
                    ['key' => 'margin_percent', 'title' => 'درصد سود ناخالص', 'value' => $kpis['sales_margin']['margin_percent'], 'unit' => '%'],
                    ['key' => 'conversion_rate', 'title' => 'نرخ تبدیل سرنخ به مشتری', 'value' => $kpis['lead_conversion']['conversion_rate'], 'unit' => '%'],
                    ['key' => 'sla_compliance', 'title' => 'پایبندی به SLA خدمات و پشتیبانی', 'value' => $kpis['support_sla']['compliance_rate'], 'unit' => '%'],
                ],
                'highlights' => $kpis,
            ],
            'sales' => [
                'role' => 'sales',
                'title' => 'داشبورد اجرایی فروش و بازاریابی',
                'cards' => [
                    ['key' => 'total_sales', 'title' => 'فروش کل دوره‌ای', 'value' => $kpis['sales_margin']['total_sales'], 'unit' => 'currency'],
                    ['key' => 'conversion_rate', 'title' => 'نرخ تبدیل لید', 'value' => $kpis['lead_conversion']['conversion_rate'], 'unit' => '%'],
                    ['key' => 'total_leads', 'title' => 'تعداد سرنخ‌ها', 'value' => $kpis['lead_conversion']['total_leads'], 'unit' => 'مورد'],
                ],
                'highlights' => $kpis['lead_conversion'],
            ],
            'operations' => [
                'role' => 'operations',
                'title' => 'داشبورد عملیات، تولید و انبار',
                'cards' => [
                    ['key' => 'inventory_units', 'title' => 'موجودی کالای انبارها', 'value' => $kpis['inventory_turnover']['total_units'], 'unit' => 'عدد'],
                    ['key' => 'turnover_ratio', 'title' => 'نرخ گردش موجودی', 'value' => $kpis['inventory_turnover']['turnover_ratio'], 'unit' => 'بار'],
                    ['key' => 'active_projects', 'title' => 'پروژه‌های فعال', 'value' => $kpis['project_profitability']['active_projects'], 'unit' => 'پروژه'],
                ],
                'highlights' => $kpis['inventory_turnover'],
            ],
            'finance' => [
                'role' => 'finance',
                'title' => 'داشبورد مدیریت مالی و وصول مطالبات',
                'cards' => [
                    ['key' => 'total_sales', 'title' => 'کل فروش صادره', 'value' => $kpis['sales_margin']['total_sales'], 'unit' => 'currency'],
                    ['key' => 'outstanding_ar', 'title' => 'مطالبات وصول‌نشده (AR)', 'value' => $kpis['ar_collection']['outstanding_ar'], 'unit' => 'currency'],
                    ['key' => 'gross_margin', 'title' => 'سود ناخالص عملیاتی', 'value' => $kpis['sales_margin']['gross_margin'], 'unit' => 'currency'],
                ],
                'highlights' => $kpis['ar_collection'],
            ],
            default => [
                'role' => 'general',
                'title' => 'داشبورد کلی شاخص‌های کلیدی',
                'cards' => [],
                'highlights' => $kpis,
            ],
        };
    }
}
