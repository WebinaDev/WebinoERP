'use client';

import React, { useState, useMemo } from 'react';
import { useTranslations } from 'next-intl';
import {
  ArrowLeft,
  BarChart3,
  Building2,
  Calendar,
  CheckCircle2,
  ChevronRight,
  Compass,
  Cpu,
  Download,
  FileSpreadsheet,
  FileText,
  Filter,
  Layers,
  LayoutGrid,
  Package,
  Play,
  Printer,
  RefreshCw,
  Search,
  ShoppingCart,
  Star,
  Table as TableIcon,
  TrendingUp,
  Users,
  Wallet,
  Zap,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  ENTERPRISE_REPORTS,
  REPORT_CATEGORIES,
  ReportDefinition,
} from './catalog';

interface EnterpriseReportsSuiteProps {
  initialReportId?: string;
}

export function EnterpriseReportsSuite({ initialReportId }: EnterpriseReportsSuiteProps) {
  const t = useTranslations('reports');

  // حالت‌ها: کاتالوگ (catalog) یا اجرای گزارش (runner)
  const [activeReportId, setActiveReportId] = useState<string | null>(initialReportId || null);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [favorites, setFavorites] = useState<string[]>(['fin-ar-aging', 'inv-stock-valuation-rop']);
  const [viewMode, setViewMode] = useState<'table' | 'chart'>('table');

  // فیلترهای اجرای گزارش (Runner Filters)
  const [branchFilter, setBranchFilter] = useState<'all' | 'central' | 'factory' | 'shiraz'>('all');
  const [dateRange, setDateRange] = useState<'month' | 'quarter' | 'year'>('month');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [isExecuting, setIsExecuting] = useState(false);

  // گزارش فعال
  const activeReport = useMemo(() => {
    return ENTERPRISE_REPORTS.find((r) => r.id === activeReportId) || null;
  }, [activeReportId]);

  // گزارش‌های فیلترشده در کاتالوگ
  const filteredReports = useMemo(() => {
    return ENTERPRISE_REPORTS.filter((rep) => {
      const matchCategory = selectedCategory === 'all' || rep.category === selectedCategory;
      const matchSearch =
        searchQuery.trim() === '' ||
        rep.titleKey.toLowerCase().includes(searchQuery.toLowerCase()) ||
        rep.descKey.toLowerCase().includes(searchQuery.toLowerCase()) ||
        rep.tags.some((tag) => tag.toLowerCase().includes(searchQuery.toLowerCase()));
      return matchCategory && matchSearch;
    });
  }, [selectedCategory, searchQuery]);

  const toggleFavorite = (id: string, e: React.MouseEvent) => {
    e.stopPropagation();
    setFavorites((prev) =>
      prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
    );
  };

  const handleRunReport = (id: string) => {
    setActiveReportId(id);
    setIsExecuting(true);
    setTimeout(() => setIsExecuting(false), 300);
  };

  const handleExportCsv = () => {
    if (!activeReport) return;
    const headers = activeReport.columns.map((c) => c.labelKey).join(',');
    const rows = activeReport.mockData.map((row) =>
      activeReport.columns.map((c) => `"${row[c.key] || ''}"`).join(',')
    );
    const csvContent = '\uFEFF' + [headers, ...rows].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${activeReport.id}_report.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* هدر ماژول گزارش‌ها */}
      <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 p-5 rounded-2xl bg-card border border-border shadow-sm">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <span className="flex h-2.5 w-2.5 rounded-full bg-primary" />
            <h1 className="text-xl font-bold tracking-tight">سامانه جامع گزارش‌های سازمانی (Enterprise Reports)</h1>
            <span className="text-xs px-2.5 py-0.5 rounded-full bg-primary/10 text-primary font-semibold">
              Business Intelligence & Analytics
            </span>
          </div>
          <p className="text-xs text-muted-foreground">
            استخراج، پایش، فیلتر چندبعدی و صدور گزارش‌های مالی، بازرگانی، تدارکات، انبار و عملکرد
          </p>
        </div>

        {activeReport ? (
          <Button
            variant="outline"
            size="sm"
            onClick={() => setActiveReportId(null)}
            className="gap-2 text-xs"
          >
            <ArrowLeft className="size-4" />
            بازگشت به فهرست گزارش‌ها
          </Button>
        ) : (
          <div className="flex items-center gap-2">
            <span className="text-xs text-muted-foreground">
              {filteredReports.length} گزارش فعال در کاتالوگ
            </span>
          </div>
        )}
      </div>

      {/* ۱. حالت دوم: صفحه اجرای گزارش (Report Runner Mode) */}
      {activeReport ? (
        <div className="space-y-6">
          {/* عنوان گزارش و نوار فیلترهای پیشرفته */}
          <div className="p-5 rounded-xl border bg-card shadow-sm space-y-4">
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b pb-4">
              <div>
                <div className="flex items-center gap-2">
                  <h2 className="text-lg font-bold">{activeReport.titleKey}</h2>
                  <button
                    type="button"
                    onClick={(e) => toggleFavorite(activeReport.id, e)}
                    className="text-amber-500 hover:text-amber-600 transition-colors"
                  >
                    <Star
                      className={`size-4 ${
                        favorites.includes(activeReport.id) ? 'fill-amber-400' : ''
                      }`}
                    />
                  </button>
                </div>
                <p className="text-xs text-muted-foreground mt-1">{activeReport.descKey}</p>
              </div>

              <div className="flex items-center gap-2 flex-wrap">
                {/* سوئیچ نمایش جدول یا نمودار */}
                <div className="flex items-center rounded-lg border bg-muted/40 p-1 text-xs">
                  <button
                    type="button"
                    onClick={() => setViewMode('table')}
                    className={`flex items-center gap-1 px-2.5 py-1 rounded-md font-medium transition-all ${
                      viewMode === 'table' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground'
                    }`}
                  >
                    <TableIcon className="size-3.5" />
                    جدول
                  </button>
                  <button
                    type="button"
                    onClick={() => setViewMode('chart')}
                    className={`flex items-center gap-1 px-2.5 py-1 rounded-md font-medium transition-all ${
                      viewMode === 'chart' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground'
                    }`}
                  >
                    <BarChart3 className="size-3.5" />
                    نمودار
                  </button>
                </div>

                {/* خروجی اکسل / CSV */}
                <Button
                  variant="outline"
                  size="sm"
                  onClick={handleExportCsv}
                  className="gap-1.5 text-xs font-semibold"
                >
                  <FileSpreadsheet className="size-3.5 text-emerald-600" />
                  خروجی اکسل (CSV)
                </Button>

                {/* چاپ */}
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => window.print()}
                  className="gap-1.5 text-xs font-semibold"
                >
                  <Printer className="size-3.5" />
                  چاپ
                </Button>
              </div>
            </div>

            {/* کنترل‌های فیلتر افق زمانی و سازمانی */}
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
              <div className="space-y-1">
                <label className="font-medium text-muted-foreground">افق زمانی گزارش:</label>
                <select
                  value={dateRange}
                  onChange={(e) => setDateRange(e.target.value as any)}
                  className="w-full h-9 rounded-lg border bg-background px-2.5 text-xs"
                >
                  <option value="month">ماه جاری (۱ تا ۳۰)</option>
                  <option value="quarter">فصل جاری</option>
                  <option value="year">سال مالی ۱۴۰۵</option>
                </select>
              </div>

              <div className="space-y-1">
                <label className="font-medium text-muted-foreground">شعبه / شرکت هلدینگ:</label>
                <select
                  value={branchFilter}
                  onChange={(e) => setBranchFilter(e.target.value as any)}
                  className="w-full h-9 rounded-lg border bg-background px-2.5 text-xs"
                >
                  <option value="all">تلفیقی تمام شعبات</option>
                  <option value="central">دفتر مرکزی تهران</option>
                  <option value="factory">کارخانه و خطوط تولید</option>
                  <option value="shiraz">شعبه توزیع جنوب</option>
                </select>
              </div>

              <div className="space-y-1">
                <label className="font-medium text-muted-foreground">وضعیت اسناد:</label>
                <select
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value)}
                  className="w-full h-9 rounded-lg border bg-background px-2.5 text-xs"
                >
                  <option value="all">همه اسناد قطعی و در جریان</option>
                  <option value="posted">فقط اسناد نهایی‌شده (Posted)</option>
                  <option value="draft">پیش‌نویس‌ها و تاییدنشده</option>
                </select>
              </div>

              <div className="flex items-end">
                <Button
                  onClick={() => {
                    setIsExecuting(true);
                    setTimeout(() => setIsExecuting(false), 400);
                  }}
                  className="w-full h-9 gap-1.5 text-xs font-semibold"
                >
                  <RefreshCw className={`size-3.5 ${isExecuting ? 'animate-spin' : ''}`} />
                  اعمال فیلترها و محاسبه
                </Button>
              </div>
            </div>
          </div>

          {/* خلاصه آماری و KPIهای بالای گزارش */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {Object.entries(activeReport.mockSummary).map(([key, val], idx) => (
              <div key={idx} className="p-4 rounded-xl border bg-card shadow-sm space-y-1">
                <div className="text-xs text-muted-foreground font-medium">{key}</div>
                <div className="text-xl font-bold tracking-tight text-foreground">{val}</div>
              </div>
            ))}
          </div>

          {/* بدنه داده‌ها: جدول یا نمودار */}
          {viewMode === 'table' ? (
            <div className="rounded-xl border bg-card shadow-sm overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-right divide-y">
                  <thead className="bg-muted/50 text-muted-foreground font-semibold">
                    <tr>
                      <th className="py-3 px-4">ردیف</th>
                      {activeReport.columns.map((col) => (
                        <th key={col.key} className="py-3 px-4">
                          {col.labelKey}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {activeReport.mockData.map((row, idx) => (
                      <tr key={idx} className="hover:bg-muted/20 transition-colors">
                        <td className="py-3 px-4 font-mono text-muted-foreground">{idx + 1}</td>
                        {activeReport.columns.map((col) => (
                          <td
                            key={col.key}
                            className={`py-3 px-4 ${
                              col.isMoney ? 'font-mono font-medium text-foreground' : ''
                            }`}
                          >
                            {row[col.key] || '—'}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : (
            <div className="p-8 rounded-xl border bg-card shadow-sm text-center space-y-4">
              <div className="h-64 flex items-end justify-center gap-6 border-b pb-6">
                {activeReport.mockData.map((row, idx) => (
                  <div key={idx} className="flex flex-col items-center gap-2">
                    <div
                      style={{ height: `${(idx + 1) * 35 + 40}%` }}
                      className="w-16 bg-primary rounded-t-md transition-all hover:opacity-80"
                    />
                    <span className="text-xs text-muted-foreground font-medium max-w-[100px] truncate">
                      {row[activeReport.columns[0].key]}
                    </span>
                  </div>
                ))}
              </div>
              <p className="text-xs text-muted-foreground">
                نمایش توزیع مقایسه‌ای شاخص‌های کلیدی بر اساس داده‌های استخراج‌شده
              </p>
            </div>
          )}
        </div>
      ) : (
        /* ۲. حالت اول: کاتالوگ و کاوشگر گزارش‌ها (Reports Catalog) */
        <div className="space-y-6">
          {/* نوار جستجو و دسته‌بندی موضوعی */}
          <div className="p-4 rounded-xl border bg-card shadow-sm space-y-4">
            <div className="relative">
              <Search className="absolute right-3.5 top-3 size-4 text-muted-foreground" />
              <Input
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="جستجوی عنوان گزارش، کد، موضوع مالی، انبار، پرسنل یا تدارکات..."
                className="pr-10 text-xs h-10"
              />
            </div>

            {/* برگه‌های دسته‌بندی افقی */}
            <div className="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
              {REPORT_CATEGORIES.map((cat) => (
                <button
                  key={cat.id}
                  type="button"
                  onClick={() => setSelectedCategory(cat.id)}
                  className={`px-3 py-1.5 rounded-lg whitespace-nowrap font-medium transition-all ${
                    selectedCategory === cat.id
                      ? 'bg-primary text-primary-foreground shadow-xs'
                      : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground'
                  }`}
                >
                  {cat.labelFa}
                </button>
              ))}
            </div>
          </div>

          {/* گزارش‌های پرکاربرد و محبوب */}
          {searchQuery === '' && selectedCategory === 'all' && (
            <div className="space-y-3">
              <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                <Star className="size-3.5 text-amber-500 fill-amber-400" />
                گزارش‌های پرکاربرد و برگزیده سازمانی
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {ENTERPRISE_REPORTS.filter((r) => r.isPopular).map((rep) => (
                  <div
                    key={rep.id}
                    onClick={() => handleRunReport(rep.id)}
                    className="p-4 rounded-xl border bg-card hover:border-primary/50 hover:shadow-md cursor-pointer transition-all space-y-3 group"
                  >
                    <div className="flex items-start justify-between">
                      <div className="p-2 rounded-lg bg-primary/10 text-primary group-hover:scale-105 transition-transform">
                        <FileText className="size-5" />
                      </div>
                      <button
                        type="button"
                        onClick={(e) => toggleFavorite(rep.id, e)}
                        className="text-muted-foreground hover:text-amber-500 transition-colors"
                      >
                        <Star
                          className={`size-4 ${
                            favorites.includes(rep.id) ? 'fill-amber-400 text-amber-500' : ''
                          }`}
                        />
                      </button>
                    </div>

                    <div>
                      <h4 className="font-semibold text-sm group-hover:text-primary transition-colors">
                        {rep.titleKey}
                      </h4>
                      <p className="text-xs text-muted-foreground line-clamp-2 mt-1">
                        {rep.descKey}
                      </p>
                    </div>

                    <div className="flex items-center justify-between pt-2 border-t text-[11px] text-muted-foreground">
                      <div className="flex items-center gap-1">
                        {rep.tags.slice(0, 2).map((tag, tIdx) => (
                          <span
                            key={tIdx}
                            className="px-1.5 py-0.5 rounded bg-muted font-medium"
                          >
                            {tag}
                          </span>
                        ))}
                      </div>
                      <span className="flex items-center gap-1 font-semibold text-primary">
                        اجرای گزارش
                        <ChevronRight className="size-3 rotate-180" />
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* کلیه گزارش‌های کاتالوگ */}
          <div className="space-y-3">
            <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
              <Layers className="size-3.5 text-primary" />
              کاتالوگ گزارش‌ها ({filteredReports.length} مورد)
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {filteredReports.map((rep) => (
                <div
                  key={rep.id}
                  onClick={() => handleRunReport(rep.id)}
                  className="p-4 rounded-xl border bg-card hover:border-primary/50 hover:shadow-md cursor-pointer transition-all space-y-3 group"
                >
                  <div className="flex items-start justify-between">
                    <div className="p-2 rounded-lg bg-muted text-foreground group-hover:bg-primary/10 group-hover:text-primary transition-colors">
                      <BarChart3 className="size-4" />
                    </div>
                    <button
                      type="button"
                      onClick={(e) => toggleFavorite(rep.id, e)}
                      className="text-muted-foreground hover:text-amber-500 transition-colors"
                    >
                      <Star
                        className={`size-4 ${
                          favorites.includes(rep.id) ? 'fill-amber-400 text-amber-500' : ''
                        }`}
                      />
                    </button>
                  </div>

                  <div>
                    <h4 className="font-semibold text-sm group-hover:text-primary transition-colors">
                      {rep.titleKey}
                    </h4>
                    <p className="text-xs text-muted-foreground line-clamp-2 mt-1">
                      {rep.descKey}
                    </p>
                  </div>

                  <div className="flex items-center justify-between pt-2 border-t text-[11px] text-muted-foreground">
                    <div className="flex items-center gap-1">
                      {rep.tags.slice(0, 2).map((tag, tIdx) => (
                        <span key={tIdx} className="px-1.5 py-0.5 rounded bg-muted font-medium">
                          {tag}
                        </span>
                      ))}
                    </div>
                    <span className="flex items-center gap-1 font-semibold text-primary">
                      اجرا
                      <ChevronRight className="size-3 rotate-180" />
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
