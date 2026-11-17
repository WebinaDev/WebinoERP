'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Building,
  Calculator,
  CalendarCheck,
  Shield,
  Layers,
  MapPin,
  UserCheck,
  Plus,
  TrendingDown,
  Wrench,
  CheckCircle2,
  Clock,
  QrCode,
} from 'lucide-react';

export function AssetsOverviewPage() {
  const [activeTab, setActiveTab] = useState<'registry' | 'depreciation' | 'maintenance' | 'insurance'>('registry');

  const [assets, setAssets] = useState([
    {
      id: 1,
      asset_code: 'AST-1403-0104',
      name: 'سرور محاسباتی لبه‌ای Dell PowerEdge R750',
      category: 'تجهیزات فناوری اطلاعات (IT)',
      location: 'ساختمان مرکزی · اتاق سرور رک A2',
      custodian: 'مهندس آرش کریمی',
      purchase_date: '۱۴۰۳/۰۴/۱۵',
      purchase_cost: 1200000000,
      book_value: 840000000,
      status: 'active',
    },
    {
      id: 2,
      asset_code: 'AST-1402-0042',
      name: 'دستگاه تراش CNC پنج‌محوره صنعتی Haas',
      category: 'ماشین‌آلات و خطوط تولید',
      location: 'کارخانه آوان · سالن ماشین‌کاری قطعه ۲',
      custodian: 'مهندس وحید مرادی',
      purchase_date: '۱۴۰۲/۰۸/۱۰',
      purchase_cost: 4500000000,
      book_value: 2700000000,
      status: 'maintenance',
    },
    {
      id: 3,
      asset_code: 'AST-1404-0312',
      name: 'خودرو وانت هایلوکس پشتیبانی فنی و دیسپچ',
      category: 'وسایل نقلیه و ترابری',
      location: 'پارکینگ شعبه جنوب (عسلویه)',
      custodian: 'آرش طاهری',
      purchase_date: '۱۴۰۴/۰۲/۲۰',
      purchase_cost: 3200000000,
      book_value: 2880000000,
      status: 'active',
    },
  ]);

  const [depreciations, setDepreciations] = useState([
    {
      id: 1,
      run_number: 'DEP-1405-M08',
      period_date: 'آبان‌ماه ۱۴۰۵',
      assets_count: 28,
      total_depreciation: 184500000,
      journal_entry: 'سند شماره JV-1405-8842',
      status: 'posted',
    },
    {
      id: 2,
      run_number: 'DEP-1405-M07',
      period_date: 'مهرماه ۱۴۰۵',
      assets_count: 28,
      total_depreciation: 184500000,
      journal_entry: 'سند شماره JV-1405-7910',
      status: 'posted',
    },
  ]);

  const [maintenanceOrders, setMaintenanceOrders] = useState([
    {
      id: 1,
      order_number: 'PM-20261116-01',
      asset_name: 'دستگاه تراش CNC پنج‌محوره صنعتی Haas',
      plan_title: 'سرویس پیشگیرانه ماهانه کالیبراسیون و روغن‌کاری اسپیندل',
      scheduled_date: '۱۴۰۵/۰۸/۲۸',
      status: 'scheduled',
      priority: 'high',
      technician: 'تیم نگهداری و تعمیرات (PM)',
    },
    {
      id: 2,
      order_number: 'PM-20261110-03',
      asset_name: 'سرور محاسباتی لبه‌ای Dell PowerEdge R750',
      plan_title: 'غبارزدایی و تست سلامت باتری‌های UPS اتاق رک',
      scheduled_date: '۱۴۰۵/۰۸/۲۰',
      status: 'completed',
      priority: 'medium',
      technician: 'واحد زیرساخت IT',
    },
  ]);

  const [insurances, setInsurances] = useState([
    {
      id: 1,
      policy_number: 'POL-ALBORZ-882190',
      asset_name: 'دستگاه تراش CNC پنج‌محوره صنعتی Haas',
      insurer: 'بیمه البرز (شکست ماشین‌آلات تمام‌خطر)',
      start_date: '۱۴۰۵/۰۱/۰۱',
      end_date: '۱۴۰۵/۱۲/۲۹',
      coverage_amount: 5000000000,
      status: 'active',
    },
    {
      id: 2,
      policy_number: 'POL-DANA-102931',
      asset_name: 'خودرو وانت هایلوکس پشتیبانی فنی و دیسپچ',
      insurer: 'بیمه دانا (بیمه بدنه و شخص ثالث)',
      start_date: '۱۴۰۵/۰۳/۱۵',
      end_date: '۱۴۰۶/۰۳/۱۵',
      coverage_amount: 3500000000,
      status: 'active',
    },
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
            <Building className="h-6 w-6 text-primary" />
            سامانه مدیریت دارایی‌های ثابت و اموال سرمایه‌ای (Fixed Asset Management)
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            شناسنامه اموال، کارت استقرار و تحویل‌گیرنده، استهلاک دوره‌ای مستقیم به دفاتر کل، و برنامه نگهداری پیشگیرانه (PM).
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs px-3 py-1.5 flex items-center gap-1.5">
            <Calculator className="h-3.5 w-3.5" />
            اتصال قطعی به دفترکل: استهلاک ماهانه آبان سند زده شد
          </Badge>
          <Button size="sm">
            <Plus className="h-4 w-4 me-1.5" />
            ثبت دارایی سرمایه‌ای جدید
          </Button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              ارزش ناخالص دفتری دارایی‌ها
              <Building className="h-4 w-4 text-primary" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-primary">{formatCurrency(8900000000)}</div>
            <p className="text-xs text-muted-foreground mt-0.5">بهای تمام‌شده خرید اولیه اموال</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              ارزش دفتری خالص روز (Book Value)
              <TrendingDown className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-emerald-600">{formatCurrency(6420000000)}</div>
            <p className="text-xs text-muted-foreground mt-0.5">پس از کسر استهلاک انباشته</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              دستورکارهای نگهداری پیشگیرانه (PM)
              <Wrench className="h-4 w-4 text-amber-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-amber-600">۱ برنامه در موعد</div>
            <p className="text-xs text-muted-foreground mt-0.5">سرویس کالیبراسیون و اورهال قطعات</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              پوشش کامل بیمه‌نامه‌ها
              <Shield className="h-4 w-4 text-purple-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-purple-600">۱۰۰٪ تحت پوشش</div>
            <p className="text-xs text-muted-foreground mt-0.5">شکست ماشین‌آلات و بیمه بدنه معتبر</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-4 max-w-xl">
          <TabsTrigger value="registry">دفتر اموال و دارایی‌ها</TabsTrigger>
          <TabsTrigger value="depreciation">استهلاک دوره‌ای</TabsTrigger>
          <TabsTrigger value="maintenance">نگهداری و تعمیرات (PM)</TabsTrigger>
          <TabsTrigger value="insurance">بیمه‌نامه‌ها و اسناد</TabsTrigger>
        </TabsList>

        {/* Tab 1: Asset Registry */}
        <TabsContent value="registry" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>پلاک / کد دارایی</TableHead>
                    <TableHead>عنوان دارایی و دسته</TableHead>
                    <TableHead>محل استقرار فیزیکی</TableHead>
                    <TableHead>تحویل‌گیرنده / امین اموال</TableHead>
                    <TableHead>بهای تمام‌شده خرید</TableHead>
                    <TableHead>ارزش دفتری خالص</TableHead>
                    <TableHead className="text-end">وضعیت بهره‌برداری</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {assets.map((ast) => (
                    <TableRow key={ast.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs flex items-center gap-1.5">
                        <QrCode className="h-3.5 w-3.5 text-muted-foreground" />
                        {ast.asset_code}
                      </TableCell>
                      <TableCell>
                        <div className="font-semibold text-xs">{ast.name}</div>
                        <div className="text-[11px] text-muted-foreground">{ast.category}</div>
                      </TableCell>
                      <TableCell className="text-xs">{ast.location}</TableCell>
                      <TableCell className="text-xs">{ast.custodian}</TableCell>
                      <TableCell className="font-mono text-xs">{formatCurrency(ast.purchase_cost)}</TableCell>
                      <TableCell className="font-mono text-xs font-semibold text-emerald-600">
                        {formatCurrency(ast.book_value)}
                      </TableCell>
                      <TableCell className="text-end">
                        {ast.status === 'active' ? (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                            در حال بهره‌برداری ✓
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 text-xs">
                            سرویس دوره‌ای
                          </Badge>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 2: Depreciation */}
        <TabsContent value="depreciation" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                دفتر محاسبات و ثبت اسناد استهلاک ماهانه (Depreciation Runs)
                <Calculator className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                محاسبه خودکار استهلاک خط مستقیم طبق استاندارد حسابداری و صدور مکانیزه سند روزنامه (Journal Entry).
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شماره دوره</TableHead>
                    <TableHead>دوره مالی</TableHead>
                    <TableHead>تعداد دارایی‌های مشمول</TableHead>
                    <TableHead>مجموع هزینه استهلاک</TableHead>
                    <TableHead>سند حسابداری معین</TableHead>
                    <TableHead className="text-end">وضعیت قطعی‌سازی</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {depreciations.map((dep) => (
                    <TableRow key={dep.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{dep.run_number}</TableCell>
                      <TableCell className="font-semibold text-xs">{dep.period_date}</TableCell>
                      <TableCell className="font-mono text-xs">{dep.assets_count} دارایی</TableCell>
                      <TableCell className="font-mono text-xs font-bold text-primary">
                        {formatCurrency(dep.total_depreciation)}
                      </TableCell>
                      <TableCell className="text-xs font-mono text-muted-foreground">{dep.journal_entry}</TableCell>
                      <TableCell className="text-end">
                        <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                          سند قطعی شد ✓
                        </Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 3: Maintenance Orders */}
        <TabsContent value="maintenance" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                برنامه‌های نگهداری پیشگیرانه و دستورکارها (PM & CM Orders)
                <CalendarCheck className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                جلوگیری از خرابی‌های ناگهانی تجهیزات، زمان‌بندی سرویس‌های دوره‌ای و ثبت چک‌لیست‌های فنی.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شماره دستورکار</TableHead>
                    <TableHead>نام دارایی سرمایه‌ای</TableHead>
                    <TableHead>شرح عملیات سرویس</TableHead>
                    <TableHead>تاریخ زمان‌بندی</TableHead>
                    <TableHead>تیم فنی مجری</TableHead>
                    <TableHead className="text-end">وضعیت اجرا</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {maintenanceOrders.map((mo) => (
                    <TableRow key={mo.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{mo.order_number}</TableCell>
                      <TableCell className="font-semibold text-xs">{mo.asset_name}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{mo.plan_title}</TableCell>
                      <TableCell className="text-xs font-mono">{mo.scheduled_date}</TableCell>
                      <TableCell className="text-xs">{mo.technician}</TableCell>
                      <TableCell className="text-end">
                        {mo.status === 'completed' ? (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                            تکمیل شد ✓
                          </Badge>
                        ) : (
                          <Badge variant="secondary" className="text-xs">در نوبت اجرا</Badge>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 4: Insurance */}
        <TabsContent value="insurance" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                بیمه‌نامه‌ها و مدارک پوشش ریسک دارایی‌ها (Asset Insurance)
                <Shield className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                رهگیری تاریخ انقضای بیمه‌نامه‌های صنعتی و مدیریت سرمایه تحت پوشش حوادث.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شماره بیمه‌نامه</TableHead>
                    <TableHead>دارایی موضوع بیمه</TableHead>
                    <TableHead>شرکت بیمه‌گر و نوع پوشش</TableHead>
                    <TableHead>تاریخ انقضا</TableHead>
                    <TableHead>سرمایه تحت پوشش</TableHead>
                    <TableHead className="text-end">وضعیت اعتبار</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {insurances.map((ins) => (
                    <TableRow key={ins.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{ins.policy_number}</TableCell>
                      <TableCell className="font-semibold text-xs">{ins.asset_name}</TableCell>
                      <TableCell className="text-xs">{ins.insurer}</TableCell>
                      <TableCell className="text-xs font-mono">{ins.end_date}</TableCell>
                      <TableCell className="font-mono text-xs font-bold text-primary">
                        {formatCurrency(ins.coverage_amount)}
                      </TableCell>
                      <TableCell className="text-end">
                        <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                          معتبر و فعال ✓
                        </Badge>
                      </TableCell>
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

export function AssetsItemsPage() {
  return <AssetsOverviewPage />;
}

export function AssetsDepreciationPage() {
  return <AssetsOverviewPage />;
}

export function AssetsMaintenancePlansPage() {
  return <AssetsOverviewPage />;
}

export function AssetsMaintenanceOrdersPage() {
  return <AssetsOverviewPage />;
}

export function AssetsInsurancesPage() {
  return <AssetsOverviewPage />;
}
