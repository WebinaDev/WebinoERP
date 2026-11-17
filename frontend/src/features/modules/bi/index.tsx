'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  TrendingUp,
  PieChart,
  BarChart3,
  DollarSign,
  AlertOctagon,
  Clock,
  ArrowUpRight,
  ArrowDownRight,
  ShieldAlert,
  Send,
  FileSpreadsheet,
  Download,
  Calendar,
  Layers,
  Sparkles,
} from 'lucide-react';

export function BiOverviewPage() {
  const [activeCockpit, setActiveCockpit] = useState<'executive' | 'sales_war_room' | 'finance_aging' | 'ops_risk'>('executive');

  const [kpis] = useState({
    monthly_revenue: 4850000000,
    revenue_growth_mom: 18.4,
    gross_margin_pct: 34.2,
    net_cash_flow: 1240000000,
    ar_aging_overdue: 420000000,
    open_pipeline_val: 12800000000,
    sla_compliance_pct: 96.8,
    inventory_turnover: 5.2,
  });

  const [alerts] = useState([
    {
      id: 1,
      title: 'موجودی بحرانی قطعات یدکی خط بسته‌بندی',
      metric: 'low_stock',
      severity: 'critical',
      threshold: '< ۵ عدد',
      actual: '۲ عدد در انبار',
      status: 'active',
      time: 'امروز ۱۰:۳۰',
    },
    {
      id: 2,
      title: 'مطالبات معوق بالای ۶۰ روز شرکت صنایع فولاد',
      metric: 'high_ar_aging',
      severity: 'warning',
      threshold: '> ۳۰۰ میلیون ریال',
      actual: '۴۲۰ میلیون ریال',
      status: 'active',
      time: 'دیروز ۱۷:۱۵',
    },
    {
      id: 3,
      title: 'سررسید انقضای قرارداد پشتیبانی پتروشیمی',
      metric: 'expiring_contracts',
      severity: 'warning',
      threshold: '< ۳۰ روز',
      actual: '۱۴ روز باقیمانده',
      status: 'active',
      time: '۲ روز پیش',
    },
  ]);

  const [funnelData] = useState([
    { stage: 'لیدهای ورودی از لندینگ و فرم‌ها', count: '۳۱۰ لید', conversion: '۱۰۰٪', val: '—' },
    { stage: 'معاملات فرصت‌سنجی‌شده (MQL/SQL)', count: '۸۴ معامله', conversion: '۲۷.۱٪', val: '۱۲,۸۰۰,۰۰۰,۰۰۰ ریال' },
    { stage: 'پیش‌فاکتور رسمی صادرشده (Quotes)', count: '۳۸ پیش‌فاکتور', conversion: '۱۲.۲٪', val: '۷,۴۵۰,۰۰۰,۰۰۰ ریال' },
    { stage: 'فاکتور قطعی و وصول مطالبات (Won)', count: '۲۱ قرارداد', conversion: '۶.۷٪', val: '۴,۸۵۰,۰۰۰,۰۰۰ ریال' },
  ]);

  const formatCurrency = (val: number) => {
    return val.toLocaleString('fa-IR') + ' ریال';
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header bar */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <BarChart3 className="h-6 w-6 text-primary" />
            داشبورد هوش تجاری و تصمیم‌سازی مدیریتی (Executive Management Cockpit)
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            یکپارچه‌سازی شاخص‌های حیاتی رشد، جریان نقدینگی، قیف فروش، کنترل ریسک موجودی و رعایت SLA هلدینگ در یک نگاه.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs px-3 py-1.5 flex items-center gap-1.5">
            <Sparkles className="h-3.5 w-3.5" />
            داده‌های بلادرنگ (Real-Time Fact Sync)
          </Badge>
          <Button size="sm" variant="outline">
            <Download className="h-4 w-4 me-1.5" />
            خروجی اکسل مدیریتی
          </Button>
        </div>
      </div>

      {/* Cockpit Top KPI Metric Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              درآمد تحقق‌یافته ماه جاری
              <DollarSign className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-primary">{formatCurrency(kpis.monthly_revenue)}</div>
            <div className="text-xs text-emerald-600 flex items-center gap-1 mt-1 font-semibold">
              <ArrowUpRight className="h-3.5 w-3.5" />
              +{kpis.revenue_growth_mom}٪ رشد نسبت به ماه قبل (MoM)
            </div>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              حاشیه سود ناخالص عملیاتی
              <PieChart className="h-4 w-4 text-blue-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-blue-600">{kpis.gross_margin_pct}٪</div>
            <p className="text-xs text-muted-foreground mt-1">پس از تسهیم کامل بهای تمام‌شده و سربار</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              ارزش پایپ‌لاین جاری معاملات
              <TrendingUp className="h-4 w-4 text-purple-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-purple-600">{formatCurrency(kpis.open_pipeline_val)}</div>
            <p className="text-xs text-muted-foreground mt-1">۳۸ فرصت فروش فعال با احتمال بستن بالای ۶۰٪</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              پایبندی به SLA خدمات و تیکت‌ها
              <Clock className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-emerald-600">{kpis.sla_compliance_pct}٪</div>
            <p className="text-xs text-muted-foreground mt-1">میانگین زمان حل بحران: ۴۲ دقیقه</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeCockpit} onValueChange={(v) => setActiveCockpit(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-4 max-w-2xl">
          <TabsTrigger value="executive">داشبورد مدیرعامل (Cockpit)</TabsTrigger>
          <TabsTrigger value="sales_war_room">اتاق جنگ فروش و قیف (War Room)</TabsTrigger>
          <TabsTrigger value="finance_aging">سنی مطالبات و نقدینگی (Aging)</TabsTrigger>
          <TabsTrigger value="ops_risk">هشدارهای آستانه و ریسک عملیاتی</TabsTrigger>
        </TabsList>

        {/* Tab 1: Executive Cockpit */}
        <TabsContent value="executive" className="mt-4 space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <Card className="shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-base flex items-center justify-between">
                  ترکیب درآمدهای هلدینگ به تفکیک کانال
                  <PieChart className="h-4 w-4 text-muted-foreground" />
                </CardTitle>
                <CardDescription>تفکیک فروش B2B سازمانی، قراردادهای خدمات و لایسنس پلتفرم.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3 pt-2">
                <div>
                  <div className="flex justify-between text-xs mb-1">
                    <span>فروش B2B سازمانی و پروژه‌های سفارشی</span>
                    <span className="font-semibold font-mono">۵۸٪ ({formatCurrency(2813000000)})</span>
                  </div>
                  <div className="w-full h-2 bg-muted rounded-full overflow-hidden">
                    <div className="bg-primary h-full rounded-full" style={{ width: '58%' }} />
                  </div>
                </div>

                <div>
                  <div className="flex justify-between text-xs mb-1">
                    <span>قراردادهای پشتیبانی دوره‌ای و اعزام FSM</span>
                    <span className="font-semibold font-mono">۲۶٪ ({formatCurrency(1261000000)})</span>
                  </div>
                  <div className="w-full h-2 bg-muted rounded-full overflow-hidden">
                    <div className="bg-blue-500 h-full rounded-full" style={{ width: '26%' }} />
                  </div>
                </div>

                <div>
                  <div className="flex justify-between text-xs mb-1">
                    <span>مارکت‌پلیس و لایسنس پلتفرم ابری</span>
                    <span className="font-semibold font-mono">۱۶٪ ({formatCurrency(776000000)})</span>
                  </div>
                  <div className="w-full h-2 bg-muted rounded-full overflow-hidden">
                    <div className="bg-emerald-500 h-full rounded-full" style={{ width: '16%' }} />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card className="shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-base flex items-center justify-between">
                  جریان نقدینگی خالص و وصول مطالبات
                  <DollarSign className="h-4 w-4 text-emerald-500" />
                </CardTitle>
                <CardDescription>ورودی‌های خزانه‌داری در برابر سررسید هزینه‌های عملیاتی ماه.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 pt-2">
                <div className="flex justify-between border-b pb-2 text-xs">
                  <span className="text-muted-foreground">جریان نقدینگی عملیاتی خالص:</span>
                  <span className="font-bold text-emerald-600 font-mono">+{formatCurrency(kpis.net_cash_flow)}</span>
                </div>
                <div className="flex justify-between border-b pb-2 text-xs">
                  <span className="text-muted-foreground">شاخص گردش موجودی انبار (Turnover):</span>
                  <span className="font-semibold font-mono">{kpis.inventory_turnover} بار در سال</span>
                </div>
                <div className="flex justify-between text-xs">
                  <span className="text-muted-foreground">میانگین دوره وصول مطالبات (DSO):</span>
                  <span className="font-semibold font-mono">۲۴ روز کاری</span>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* Tab 2: Sales War Room */}
        <TabsContent value="sales_war_room" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                قیف تبدیل فروش سازمانی (Full-Funnel Sales Conversion)
                <TrendingUp className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                رهگیری ریزش و نرخ تبدیل لیدهای ورودی تا انعقاد قرارداد و پرداخت نهایی فاکتور.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>مرحله قیف فروش</TableHead>
                    <TableHead>تعداد رکوردها</TableHead>
                    <TableHead>نرخ تبدیل مرحله‌ای</TableHead>
                    <TableHead className="text-end">ارزش ریالی پایپ‌لاین</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {funnelData.map((f, idx) => (
                    <TableRow key={idx} className="hover:bg-muted/20">
                      <TableCell className="font-medium text-xs flex items-center gap-2">
                        <span className="h-2 w-2 rounded-full bg-primary" />
                        {f.stage}
                      </TableCell>
                      <TableCell className="font-mono text-xs">{f.count}</TableCell>
                      <TableCell className="font-mono text-xs font-semibold text-emerald-600">{f.conversion}</TableCell>
                      <TableCell className="text-end font-mono text-xs font-bold text-primary">{f.val}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 3: Finance Aging */}
        <TabsContent value="finance_aging" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                جدول سنی مطالبات تجاری (Accounts Receivable Aging)
                <Clock className="h-5 w-5 text-amber-500" />
              </CardTitle>
              <CardDescription>طبقه‌بندی سررسید فاکتورهای معوق جهت پیگیری وصول و جلوگیری از نکول مشتریان.</CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>طبقه سنی سررسید</TableHead>
                    <TableHead>تعداد فاکتورها</TableHead>
                    <TableHead>درصد از کل مطالبات</TableHead>
                    <TableHead className="text-end">مبلغ کل ریالی</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  <TableRow className="hover:bg-muted/20">
                    <TableCell className="font-semibold text-xs">جاری و زیر ۳۰ روز (سررسید نشده)</TableCell>
                    <TableCell className="font-mono text-xs">۱۸ فاکتور</TableCell>
                    <TableCell className="font-mono text-xs text-emerald-600">۶۸٪</TableCell>
                    <TableCell className="text-end font-mono text-xs font-bold">{formatCurrency(1820000000)}</TableCell>
                  </TableRow>
                  <TableRow className="hover:bg-muted/20">
                    <TableCell className="font-semibold text-xs">۳۱ تا ۶۰ روز معوق</TableCell>
                    <TableCell className="font-mono text-xs">۵ فاکتور</TableCell>
                    <TableCell className="font-mono text-xs text-amber-600">۱۸٪</TableCell>
                    <TableCell className="text-end font-mono text-xs font-bold">{formatCurrency(480000000)}</TableCell>
                  </TableRow>
                  <TableRow className="hover:bg-muted/20">
                    <TableCell className="font-semibold text-xs text-destructive">۶۱ تا ۹۰ روز معوق (ریسک وصول)</TableCell>
                    <TableCell className="font-mono text-xs">۳ فاکتور</TableCell>
                    <TableCell className="font-mono text-xs text-destructive">۱۴٪</TableCell>
                    <TableCell className="text-end font-mono text-xs font-bold text-destructive">{formatCurrency(kpis.ar_aging_overdue)}</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 4: Ops Risk & Threshold Alerts */}
        <TabsContent value="ops_risk" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                هشدارهای آستانه‌ای و پیشگیری از بحران (Threshold Early-Warning)
                <ShieldAlert className="h-5 w-5 text-destructive" />
              </CardTitle>
              <CardDescription>تشخیص خودکار کسری‌های انبار، نقض SLA و قراردادهای در آستانه انقضا.</CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>عنوان هشدار ریسک</TableHead>
                    <TableHead>نوع متغیر (Metric)</TableHead>
                    <TableHead>سطح اهمیت</TableHead>
                    <TableHead>حد آستانه مجاز</TableHead>
                    <TableHead>مقدار واقعی سیستم</TableHead>
                    <TableHead className="text-end">زمان بروز</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {alerts.map((al) => (
                    <TableRow key={al.id} className="hover:bg-muted/20">
                      <TableCell className="font-semibold text-xs flex items-center gap-1.5">
                        <AlertOctagon className="h-3.5 w-3.5 text-destructive" />
                        {al.title}
                      </TableCell>
                      <TableCell className="font-mono text-xs text-muted-foreground">{al.metric}</TableCell>
                      <TableCell>
                        <Badge variant={al.severity === 'critical' ? 'destructive' : 'outline'} className="text-[11px]">
                          {al.severity}
                        </Badge>
                      </TableCell>
                      <TableCell className="font-mono text-xs">{al.threshold}</TableCell>
                      <TableCell className="font-mono text-xs font-bold text-destructive">{al.actual}</TableCell>
                      <TableCell className="text-end text-xs font-mono text-muted-foreground">{al.time}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

export function BiKpisPage() {
  return <BiOverviewPage />;
}

export function BiDashboardsPage() {
  return <BiOverviewPage />;
}

export function BiSubscriptionsPage() {
  return <BiOverviewPage />;
}

export function BiAlertsPage() {
  return <BiOverviewPage />;
}
