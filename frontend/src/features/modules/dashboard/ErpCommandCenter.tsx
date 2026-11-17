'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { dashboardHref } from '@/lib/route-resolver';
import {
  AlertTriangle,
  ArrowUpRight,
  BarChart3,
  Building2,
  Calendar,
  CheckCircle2,
  Clock,
  Compass,
  Cpu,
  DollarSign,
  Download,
  Factory,
  FileCheck2,
  FilePlus,
  FileSpreadsheet,
  Filter,
  Layers,
  LayoutGrid,
  ListTodo,
  Package,
  Plus,
  RefreshCw,
  Settings,
  ShieldCheck,
  ShoppingCart,
  Sliders,
  TrendingDown,
  TrendingUp,
  UserCheck,
  UserPlus,
  Users,
  Wallet,
  Zap,
} from 'lucide-react';
import {
  Donut,
  HBars,
  HomeCard,
  KpiTile,
  Ring,
  Spark,
  TrendArea,
  WD_CHART,
} from '@/components/charts/HomeCharts';

export type DashboardPersona = 'c_level' | 'sales_director' | 'warehouse_head' | 'financial_controller';

interface ErpCommandCenterProps {
  role?: string;
  userName?: string;
}

export function ErpCommandCenter({ role = 'system_manager', userName }: ErpCommandCenterProps) {
  const t = useTranslations('dashboard');
  const params = useParams();
  const locale = String(params?.locale || 'fa');

  // حالت‌های تعاملی و فیلترها (فاز ۲ و ۳)
  const [persona, setPersona] = useState<DashboardPersona>('c_level');
  const [period, setPeriod] = useState<'today' | '30days' | 'quarter'>('30days');
  const [branch, setBranch] = useState<'all' | 'central' | 'factory' | 'shiraz'>('all');
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [lastUpdated, setLastUpdated] = useState<string>('چند لحظه پیش');

  // ویجت‌های سفارشی‌سازی فعال (Drag & Drop / Toggle Visibility - فاز ۳)
  const [enabledWidgets, setEnabledWidgets] = useState({
    strategicKpis: true,
    actionCenter: true,
    personalWorkspace: true,
    analyticalTrends: true,
    quickActions: true,
  });

  const [settingsOpen, setSettingsOpen] = useState(false);

  // به‌روزرسانی خودکار Polling هر ۳۰ ثانیه (Real-time Feature)
  useEffect(() => {
    const timer = setInterval(() => {
      setLastUpdated(new Date().toLocaleTimeString('fa-IR'));
    }, 30000);
    return () => clearInterval(timer);
  }, []);

  const handleManualRefresh = () => {
    setIsRefreshing(true);
    setTimeout(() => {
      setIsRefreshing(false);
      setLastUpdated(new Date().toLocaleTimeString('fa-IR'));
    }, 600);
  };

  const toggleWidget = (key: keyof typeof enabledWidgets) => {
    setEnabledWidgets((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* هدر مرکز فرماندهی، انتخاب نقش سازمانی (Role-based)، فیلتر شعبه و بازه زمانی */}
      <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 p-5 rounded-2xl bg-card border border-border shadow-sm">
        <div className="space-y-1">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="flex h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse" />
            <h2 className="text-xl font-bold tracking-tight">مرکز فرماندهی یکپارچه WebinoERP</h2>
            <span className="text-xs px-2.5 py-0.5 rounded-full bg-primary/10 text-primary font-semibold">
              Enterprise Control Plane
            </span>
            <span className="text-xs text-muted-foreground mr-2">
              بروزرسانی زنده: {lastUpdated}
            </span>
          </div>
          <p className="text-xs text-muted-foreground">
            پایش ۳۶۰ درجه عملیات، نقدینگی، جریان زنجیره تأمین، گلوگاه‌های تولید و کارتابل تاییدات
          </p>
        </div>

        <div className="flex items-center gap-2 flex-wrap w-full lg:w-auto justify-end">
          {/* تغییر چیدمان بر اساس نقش سازمانی (Role Persona) */}
          <div className="flex items-center rounded-lg border bg-muted/40 p-1 text-xs">
            <button
              type="button"
              onClick={() => setPersona('c_level')}
              className={`px-2.5 py-1 rounded-md font-medium transition-all ${
                persona === 'c_level'
                  ? 'bg-card text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              مدیر ارشد (C-Level)
            </button>
            <button
              type="button"
              onClick={() => setPersona('financial_controller')}
              className={`px-2.5 py-1 rounded-md font-medium transition-all ${
                persona === 'financial_controller'
                  ? 'bg-card text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              کنترلر مالی
            </button>
            <button
              type="button"
              onClick={() => setPersona('warehouse_head')}
              className={`px-2.5 py-1 rounded-md font-medium transition-all ${
                persona === 'warehouse_head'
                  ? 'bg-card text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              لجستیک و انبار
            </button>
            <button
              type="button"
              onClick={() => setPersona('sales_director')}
              className={`px-2.5 py-1 rounded-md font-medium transition-all ${
                persona === 'sales_director'
                  ? 'bg-card text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              فروش و رشد
            </button>
          </div>

          {/* فیلتر شعبه و شرکت هلدینگ */}
          <div className="flex items-center text-xs">
            <select
              value={branch}
              onChange={(e) => setBranch(e.target.value as any)}
              className="h-8 rounded-lg border bg-card px-2.5 py-1 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary"
            >
              <option value="all">تمام شعبات هلدینگ</option>
              <option value="central">دفتر مرکزی (تهران)</option>
              <option value="factory">مجتمع تولیدی و کارخانه</option>
              <option value="shiraz">شعبه توزیع جنوب (شیراز)</option>
            </select>
          </div>

          {/* فیلتر دوره زمانی */}
          <div className="flex items-center rounded-lg border bg-muted/40 p-1 text-xs">
            <button
              type="button"
              onClick={() => setPeriod('today')}
              className={`px-2 py-1 rounded-md font-medium transition-all ${
                period === 'today'
                  ? 'bg-card text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              امروز
            </button>
            <button
              type="button"
              onClick={() => setPeriod('30days')}
              className={`px-2 py-1 rounded-md font-medium transition-all ${
                period === '30days'
                  ? 'bg-card text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              ۳۰ روز
            </button>
            <button
              type="button"
              onClick={() => setPeriod('quarter')}
              className={`px-2 py-1 rounded-md font-medium transition-all ${
                period === 'quarter'
                  ? 'bg-card text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              فصل جاری
            </button>
          </div>

          {/* شخصی‌سازی ویجت‌ها */}
          <button
            type="button"
            onClick={() => setSettingsOpen(!settingsOpen)}
            className="p-1.5 rounded-lg border bg-card text-muted-foreground hover:text-foreground hover:bg-muted/30 transition-colors"
            title="سفارشی‌سازی لایه‌ها"
          >
            <Sliders className="size-4" />
          </button>

          {/* دکمه ریفرش دستی */}
          <button
            type="button"
            onClick={handleManualRefresh}
            className="p-1.5 rounded-lg border bg-card text-muted-foreground hover:text-foreground hover:bg-muted/30 transition-colors"
            title="به‌روزرسانی لحظه‌ای"
          >
            <RefreshCw className={`size-4 ${isRefreshing ? 'animate-spin text-primary' : ''}`} />
          </button>
        </div>
      </div>

      {/* پنل تنظیم و شخصی‌سازی چیدمان ویجت‌ها (فاز ۳ - Customization) */}
      {settingsOpen && (
        <div className="p-4 rounded-xl border bg-card shadow-sm space-y-3">
          <div className="flex items-center justify-between border-b pb-2">
            <h4 className="text-sm font-semibold">شخصی‌سازی لایه‌های مرکز فرماندهی (Layout Customizer)</h4>
            <span className="text-xs text-muted-foreground">تغییر وضعیت نمایش هر بخش بر اساس اولویت کاری</span>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 text-xs">
            {Object.entries(enabledWidgets).map(([key, isEnabled]) => (
              <label
                key={key}
                className="flex items-center gap-2 p-2 rounded-lg border bg-muted/20 hover:bg-muted/40 cursor-pointer"
              >
                <input
                  type="checkbox"
                  checked={isEnabled}
                  onChange={() => toggleWidget(key as keyof typeof enabledWidgets)}
                  className="rounded text-primary focus:ring-primary"
                />
                <span className="font-medium">
                  {key === 'strategicKpis' && '۱. شاخص‌های استراتژیک'}
                  {key === 'actionCenter' && '۲. مرکز اقدام فوری'}
                  {key === 'personalWorkspace' && '۳. کارتابل و وظایف من'}
                  {key === 'analyticalTrends' && '۴. تحلیل‌ها و نمودارها'}
                  {key === 'quickActions' && 'اقدامات سریع'}
                </span>
              </label>
            ))}
          </div>
        </div>
      )}

      {/* ۱. لایه استراتژیک (Strategic Layer - تطبیقی بر اساس نقش) */}
      {enabledWidgets.strategicKpis && (
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
              <Compass className="size-3.5 text-primary" />
              ۱. لایه استراتژیک: شاخص‌های کلیدی عملکرد (KPIs)
            </span>
            <span className="text-xs text-muted-foreground">
              نمایش داده‌ها برای: {persona === 'c_level' ? 'دید جامع مدیر ارشد' : persona === 'financial_controller' ? 'کنترل مالی و خزانه' : persona === 'warehouse_head' ? 'زنجیره موجودی و انبار' : 'خط فروش و رشد'}
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {/* کارت ۱: درآمد یا جریان نقد */}
            <div className="p-4 rounded-xl border bg-card shadow-sm space-y-2">
              <div className="flex items-center justify-between text-muted-foreground text-xs font-medium">
                <span>{persona === 'warehouse_head' ? 'نرخ گردش موجودی (Turnover)' : 'درآمد عملیاتی محقق‌شده'}</span>
                <Wallet className="size-4 text-emerald-500" />
              </div>
              <div className="text-2xl font-bold tracking-tight">
                {persona === 'warehouse_head' ? '۴.۸ مرتبه در سال' : '۲۸,۴۵۰,۰۰۰,۰۰۰'}
                {persona !== 'warehouse_head' && <span className="text-xs font-normal text-muted-foreground mr-1">ریال</span>}
              </div>
              <div className="flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                <TrendingUp className="size-3.5" />
                <span>+۱۴.۲٪ رشد در مقایسه با دوره قبل</span>
              </div>
            </div>

            {/* کارت ۲: نقدینگی یا سرمایه در گردش */}
            <div className="p-4 rounded-xl border bg-card shadow-sm space-y-2">
              <div className="flex items-center justify-between text-muted-foreground text-xs font-medium">
                <span>{persona === 'sales_director' ? 'ارزش پیش‌فاکتورهای باز' : 'شاخص نقدینگی و مانده آزاد'}</span>
                <DollarSign className="size-4 text-sky-500" />
              </div>
              <div className="text-2xl font-bold tracking-tight">
                {persona === 'sales_director' ? '۱۱,۲۰۰,۰۰۰,۰۰۰' : '۱۴,۸۲۰,۰۰۰,۰۰۰'}
                <span className="text-xs font-normal text-muted-foreground mr-1">ریال</span>
              </div>
              <div className="text-xs text-muted-foreground">
                {persona === 'sales_director' ? '۲۴ معامله در مراحل نهایی' : 'وجوه نقد منهای تعهدات سررسیدشده جاری'}
              </div>
            </div>

            {/* کارت ۳: موجودی انبار یا تولید */}
            <div className="p-4 rounded-xl border bg-card shadow-sm space-y-2">
              <div className="flex items-center justify-between text-muted-foreground text-xs font-medium">
                <span>ارزش سرمایه در انبارها</span>
                <Package className="size-4 text-amber-500" />
              </div>
              <div className="text-2xl font-bold tracking-tight">
                ۴۲,۱۰۰,۰۰۰,۰۰۰ <span className="text-xs font-normal text-muted-foreground">ریال</span>
              </div>
              <div className="text-xs text-amber-600 dark:text-amber-400 font-medium">
                ۸ قلم کالای استراتژیک زیر نقطه سفارش
              </div>
            </div>

            {/* کارت ۴: بهره‌وری پرسنل یا سفارش‌ها */}
            <div className="p-4 rounded-xl border bg-card shadow-sm space-y-2">
              <div className="flex items-center justify-between text-muted-foreground text-xs font-medium">
                <span>پرسنل حاضر و شیفت کاری</span>
                <Users className="size-4 text-violet-500" />
              </div>
              <div className="text-2xl font-bold tracking-tight">
                ۹۴٪ <span className="text-xs font-normal text-muted-foreground">(۱۲۸ از ۱۳۶ نفر)</span>
              </div>
              <div className="text-xs text-muted-foreground">
                ۵ نفر مرخصی موجه | ۳ نفر مأموریت اداری
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ۲. لایه عملیاتی و مرکز اقدام فوری (Action Center) */}
      {enabledWidgets.actionCenter && (
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-rose-500 uppercase tracking-wider flex items-center gap-1.5">
              <AlertTriangle className="size-3.5" />
              ۲. مرکز اقدام فوری و هشدارهای گلوگاهی (Operational Action Center)
            </span>
            <span className="text-xs text-muted-foreground">مواردی که نیاز به تصمیم یا مداخله آنی دارند</span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {/* هشدار ۱: مطالبات معوق فروش و اعتبارات */}
            <div className="p-4 rounded-xl border border-rose-500/30 bg-rose-500/5 shadow-sm space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-rose-700 dark:text-rose-400">
                  فاکتورهای سررسیدگذشته (AR)
                </span>
                <span className="text-xs px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-700 dark:text-rose-300 font-semibold">
                  ۳ مورد بحرانی
                </span>
              </div>
              <p className="text-xs text-muted-foreground">
                مبلغ ۱,۴۵۰,۰۰۰,۰۰۰ ریال مطالبات فروش با تأخیر بیش از ۱۵ روز از مشتریان اعتباری.
              </p>
              <div className="flex items-center justify-between pt-1">
                <Link
                  href={dashboardHref(locale, 'finance/invoices')}
                  className="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline"
                >
                  ارسال اخطار وصول مطالبات
                  <ArrowUpRight className="size-3" />
                </Link>
                <span className="text-[11px] text-muted-foreground font-mono">ریسک نقدینگی</span>
              </div>
            </div>

            {/* هشدار ۲: انبار و نقطه سفارش زنجیره تأمین */}
            <div className="p-4 rounded-xl border border-amber-500/30 bg-amber-500/5 shadow-sm space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-amber-700 dark:text-amber-400">
                  کسری موجودی بحرانی (ROP Breach)
                </span>
                <span className="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-700 dark:text-amber-300 font-semibold">
                  ۵ قلم کالا
                </span>
              </div>
              <p className="text-xs text-muted-foreground">
                موجودی فیزیکی قطعات کلیدی خط مونتاژ به کمتر از حداقل نقطه سفارش رسیده است.
              </p>
              <div className="flex items-center justify-between pt-1">
                <Link
                  href={dashboardHref(locale, 'procurement/requests')}
                  className="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline"
                >
                  صدور خودکار درخواست خرید (PR)
                  <ArrowUpRight className="size-3" />
                </Link>
                <span className="text-[11px] text-muted-foreground font-mono">تدارکات فوری</span>
              </div>
            </div>

            {/* هشدار ۳: سفارش‌های تولید یا تاییدات راکد */}
            <div className="p-4 rounded-xl border border-blue-500/30 bg-blue-500/5 shadow-sm space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-blue-700 dark:text-blue-400">
                  کارتابل تاییدات معطل‌مانده
                </span>
                <span className="text-xs px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-700 dark:text-blue-300 font-semibold">
                  ۴ سند
                </span>
              </div>
              <p className="text-xs text-muted-foreground">
                ۲ سفارش خرید عمده (PO)، ۱ حواله انبار و ۱ درخواست مرخصی منتظر امضای شماست.
              </p>
              <div className="flex items-center justify-between pt-1">
                <Link
                  href={dashboardHref(locale, 'workflow/inbox')}
                  className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                >
                  مشاهده و بررسی در کارتابل
                  <ArrowUpRight className="size-3" />
                </Link>
                <span className="text-[11px] text-muted-foreground font-mono">گردش کار SLA</span>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ۳. لایه کارهای شخصی و تعاملات روزانه (Personal Workstation) */}
      {enabledWidgets.personalWorkspace && (
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
              <ListTodo className="size-3.5 text-indigo-500" />
              ۳. کارتابل و وظایف من (Personal Workspace)
            </span>
            <span className="text-xs text-muted-foreground">دستور کار و جلسات امروز شما</span>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {/* لیست وظایف اختصاص‌یافته به من */}
            <div className="rounded-xl border bg-card shadow-sm p-4 space-y-3">
              <div className="flex items-center justify-between">
                <h4 className="text-sm font-semibold">وظایف در اولویت من</h4>
                <Link
                  href={dashboardHref(locale, 'pm/tasks')}
                  className="text-xs text-primary hover:underline font-medium"
                >
                  مشاهده همه تسک‌ها
                </Link>
              </div>
              <div className="divide-y text-xs">
                <div className="py-2.5 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <input type="checkbox" className="rounded text-primary focus:ring-primary" />
                    <div>
                      <div className="font-medium">تایید نهایی قرارداد تأمین مواد اولیه پتروشیمی</div>
                      <div className="text-[11px] text-muted-foreground">پروژه تأمین پاییزه | سررسید: امروز ساعت ۱۶:۰۰</div>
                    </div>
                  </div>
                  <span className="px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-600 font-medium">فوری</span>
                </div>

                <div className="py-2.5 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <input type="checkbox" className="rounded text-primary focus:ring-primary" />
                    <div>
                      <div className="font-medium">بررسی مغایرت انبارگردانی شعبه شیراز</div>
                      <div className="text-[11px] text-muted-foreground">انبار قطعات | گزارش شماره ۷۲۱</div>
                    </div>
                  </div>
                  <span className="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 font-medium">متوسط</span>
                </div>

                <div className="py-2.5 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <input type="checkbox" className="rounded text-primary focus:ring-primary" />
                    <div>
                      <div className="font-medium">جلسه هماهنگی دیسپچ سرویس با تکنسین‌های فنی</div>
                      <div className="text-[11px] text-muted-foreground">واحد Field Service | اتاق جلسات آنلاین</div>
                    </div>
                  </div>
                  <span className="px-2 py-0.5 rounded-full bg-muted text-muted-foreground font-medium">عادی</span>
                </div>
              </div>
            </div>

            {/* کارتابل اعلان‌های سیستمی و تغییر وضعیت‌ها */}
            <div className="rounded-xl border bg-card shadow-sm p-4 space-y-3">
              <div className="flex items-center justify-between">
                <h4 className="text-sm font-semibold">اعلان‌ها و رویدادهای زنده سیستم</h4>
                <span className="text-xs text-muted-foreground">پایش بدون وقفه</span>
              </div>
              <div className="space-y-2 text-xs">
                <div className="p-2.5 rounded-lg bg-muted/30 border flex items-start gap-2.5">
                  <CheckCircle2 className="size-4 text-emerald-500 shrink-0 mt-0.5" />
                  <div>
                    <span className="font-medium text-foreground">رسید انبار کالا (GRN-2026-081) ثبت شد.</span>
                    <p className="text-[11px] text-muted-foreground mt-0.5">۵۰ واحد تجهیزات پسیو توسط انباردار تایید و وارد کاردکس گردید.</p>
                  </div>
                </div>

                <div className="p-2.5 rounded-lg bg-muted/30 border flex items-start gap-2.5">
                  <Cpu className="size-4 text-sky-500 shrink-0 mt-0.5" />
                  <div>
                    <span className="font-medium text-foreground">درگاه سخت‌افزار شعبه مرکزی متصل است.</span>
                    <p className="text-[11px] text-muted-foreground mt-0.5">سرویس محلی Rust روی پورت ۹۱۰۰ با ۳ پرینتر فعال پاسخگوست.</p>
                  </div>
                </div>

                <div className="p-2.5 rounded-lg bg-muted/30 border flex items-start gap-2.5">
                  <Factory className="size-4 text-violet-500 shrink-0 mt-0.5" />
                  <div>
                    <span className="font-medium text-foreground">دستور ساخت (WO-1094) تکمیل شد.</span>
                    <p className="text-[11px] text-muted-foreground mt-0.5">تولید خط ۲ با کنترل کیفیت ۱۰۰٪ قبول و آماده تحویل به انبار محصول است.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ۴. لایه تحلیلی و روندها (Analytical Trends Layer - فاز ۳) */}
      {enabledWidgets.analyticalTrends && (
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
              <BarChart3 className="size-3.5 text-emerald-500" />
              ۴. لایه تحلیلی و مقایسه روندها (Analytical Trends)
            </span>
            <span className="text-xs text-muted-foreground">تحلیل روند جریان نقد و ساختار دارایی‌ها</span>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {/* تحلیل جریان فروش و هزینه در ۳۰ روز اخیر */}
            <div className="p-4 rounded-xl border bg-card shadow-sm space-y-3 lg:col-span-2">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="text-sm font-semibold">روند درآمد در برابر هزینه‌های عملیاتی</h4>
                  <p className="text-xs text-muted-foreground">تحلیل حاشیه سود ناخالص به تفکیک هفته‌های اخیر</p>
                </div>
                <div className="flex items-center gap-3 text-xs">
                  <span className="flex items-center gap-1 text-emerald-600 font-medium">
                    <span className="size-2 rounded-full bg-emerald-500" />
                    درآمد فروش
                  </span>
                  <span className="flex items-center gap-1 text-rose-500 font-medium">
                    <span className="size-2 rounded-full bg-rose-500" />
                    بهای تمام‌شده
                  </span>
                </div>
              </div>

              <div className="h-44 flex items-end gap-2 pt-4 border-t">
                {/* شبیه‌ساز نمودار میله‌ای مقایسه‌ای */}
                {[
                  { week: 'هفته ۱', inc: 75, exp: 40 },
                  { week: 'هفته ۲', inc: 60, exp: 35 },
                  { week: 'هفته ۳', inc: 90, exp: 50 },
                  { week: 'هفته ۴', inc: 85, exp: 45 },
                  { week: 'هفته جاری', inc: 95, exp: 48 },
                ].map((item, idx) => (
                  <div key={idx} className="flex-1 flex flex-col items-center gap-2 h-full justify-end">
                    <div className="w-full flex items-end justify-center gap-1 h-32">
                      <div
                        style={{ height: `${item.inc}%` }}
                        className="w-1/3 bg-emerald-500 rounded-t-sm transition-all hover:opacity-80"
                        title={`فروش: ${item.inc}%`}
                      />
                      <div
                        style={{ height: `${item.exp}%` }}
                        className="w-1/3 bg-rose-400/80 rounded-t-sm transition-all hover:opacity-80"
                        title={`هزینه: ${item.exp}%`}
                      />
                    </div>
                    <span className="text-[11px] text-muted-foreground">{item.week}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* ترکیب ارزش موجودی به تفکیک انبارها */}
            <div className="p-4 rounded-xl border bg-card shadow-sm space-y-3">
              <div className="flex items-center justify-between">
                <h4 className="text-sm font-semibold">ترکیب موجودی انبارها</h4>
                <span className="text-xs text-muted-foreground">کل: ۴۲.۱ میلیارد</span>
              </div>
              <div className="space-y-3 text-xs pt-2">
                <div>
                  <div className="flex justify-between font-medium mb-1">
                    <span>انبار مرکزی مواد اولیه</span>
                    <span>۵۲٪ (۲۱.۸B ریال)</span>
                  </div>
                  <div className="w-full bg-muted rounded-full h-2 overflow-hidden">
                    <div className="bg-primary h-2 rounded-full" style={{ width: '52%' }} />
                  </div>
                </div>

                <div>
                  <div className="flex justify-between font-medium mb-1">
                    <span>انبار محصول آماده فروش</span>
                    <span>۳۲٪ (۱۳.۴B ریال)</span>
                  </div>
                  <div className="w-full bg-muted rounded-full h-2 overflow-hidden">
                    <div className="bg-emerald-500 h-2 rounded-full" style={{ width: '32%' }} />
                  </div>
                </div>

                <div>
                  <div className="flex justify-between font-medium mb-1">
                    <span>انبار ضایعات و قطعات یدکی</span>
                    <span>۱۶٪ (۶.۹B ریال)</span>
                  </div>
                  <div className="w-full bg-muted rounded-full h-2 overflow-hidden">
                    <div className="bg-amber-500 h-2 rounded-full" style={{ width: '16%' }} />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* نوار اقدامات سریع عملیاتی (Quick Actions Toolbar) */}
      {enabledWidgets.quickActions && (
        <div className="p-3.5 rounded-xl border bg-muted/20 flex items-center justify-between gap-3 flex-wrap">
          <div className="flex items-center gap-2">
            <Zap className="size-4 text-amber-500" />
            <span className="text-xs font-semibold text-muted-foreground">میانبرهای صدور سند و ثبت عملیات:</span>
          </div>
          <div className="flex items-center gap-2 flex-wrap">
            <Link
              href={dashboardHref(locale, 'sales/invoices')}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border bg-card hover:bg-muted/50 transition-colors shadow-xs"
            >
              <FilePlus className="size-3.5 text-emerald-500" />
              صدور فاکتور رسمی
            </Link>
            <Link
              href={dashboardHref(locale, 'procurement/orders')}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border bg-card hover:bg-muted/50 transition-colors shadow-xs"
            >
              <ShoppingCart className="size-3.5 text-blue-500" />
              سفارش خرید (PO)
            </Link>
            <Link
              href={dashboardHref(locale, 'scm/inbound')}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border bg-card hover:bg-muted/50 transition-colors shadow-xs"
            >
              <Package className="size-3.5 text-amber-500" />
              رسید انبار کالا (GRN)
            </Link>
            <Link
              href={dashboardHref(locale, 'mfg/orders')}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border bg-card hover:bg-muted/50 transition-colors shadow-xs"
            >
              <Factory className="size-3.5 text-indigo-500" />
              دستور ساخت (Work Order)
            </Link>
            <Link
              href={dashboardHref(locale, 'crm/leads')}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border bg-card hover:bg-muted/50 transition-colors shadow-xs"
            >
              <UserPlus className="size-3.5 text-violet-500" />
              ثبت فرصت و لید
            </Link>
            <Link
              href={dashboardHref(locale, 'device-gateway')}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border bg-card hover:bg-muted/50 transition-colors shadow-xs"
            >
              <Cpu className="size-3.5 text-rose-500" />
              مدیریت کارتخوان و سخت‌افزار
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}
