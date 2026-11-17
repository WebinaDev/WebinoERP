'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  ShieldCheck,
  AlertTriangle,
  ClipboardList,
  Boxes,
  Lock,
  CheckCircle2,
  XCircle,
  Plus,
  RefreshCw,
  Eye,
  FileCheck2,
  TrendingUp,
} from 'lucide-react';

export function QmOverviewPage() {
  const [activeTab, setActiveTab] = useState<'inspections' | 'lots' | 'ncrs' | 'plans'>('inspections');

  const [inspections, setInspections] = useState([
    {
      id: 1,
      number: 'INS-20261116-01',
      plan: 'طرح کنترل ورودی پودر رزین اپوکسی (IQC)',
      product: 'رزین اپوکسی صنعتی گرید A',
      lot: 'LOT-1405-8842',
      type: 'incoming',
      sample_size: '۲۵ کیلوگرم از ۱۰۰۰ کیلو',
      verdict: 'pass',
      inspector: 'مهندس حسینی',
      date: '۱۴۰۵/۰۸/۲۶',
    },
    {
      id: 2,
      number: 'INS-20261115-04',
      plan: 'طرح تست ابعادی و استحکام مکانیکی قطعات',
      product: 'فلنج فولادی کلاس ۱۵۰',
      lot: 'LOT-1405-9011',
      type: 'in_process',
      sample_size: '۱۰ عدد از ۲۰۰ عدد',
      verdict: 'fail',
      inspector: 'مهندس رضایی',
      date: '۱۴۰۵/۰۸/۲۵',
    },
    {
      id: 3,
      number: 'INS-20261114-02',
      plan: 'بازرسی نهایی قبل از ارسال به مشتری (OQC)',
      product: 'شیر برقی هیدرولیک صنعتی',
      lot: 'LOT-1405-7730',
      type: 'final',
      sample_size: '۵ عدد از ۵۰ عدد',
      verdict: 'conditional',
      inspector: 'مهندس علیزاده',
      date: '۱۴۰۵/۰۸/۲۴',
    },
  ]);

  const [lots, setLots] = useState([
    {
      id: 1,
      lot_number: 'LOT-1405-8842',
      product: 'رزین اپوکسی صنعتی گرید A',
      quantity: '۱,۰۰۰ کیلوگرم',
      status: 'released',
      location: 'انبار مواد اولیه - ردیف B',
      notes: 'کیفیت ورودی تأیید شد · مجوز مصرف صادر شد',
    },
    {
      id: 2,
      lot_number: 'LOT-1405-9011',
      product: 'فلنج فولادی کلاس ۱۵۰',
      quantity: '۲۰۰ عدد',
      status: 'quarantined',
      location: 'انبار قرنطینه کیفیت (QM Quarantine Zone)',
      notes: 'انحراف ابعادی در ضخامت لبه · عدم انطباق NCR-20261115-01 صادر شد',
    },
    {
      id: 3,
      lot_number: 'LOT-1405-7730',
      product: 'شیر برقی هیدرولیک صنعتی',
      quantity: '۵۰ عدد',
      status: 'released',
      location: 'انبار محصول نهایی - قفسه C3',
      notes: 'آزادسازی مشروط با مجوز مدیر کیفیت',
    },
  ]);

  const [ncrs, setNcrs] = useState([
    {
      id: 1,
      ncr_number: 'NCR-20261115-01',
      inspection: 'INS-20261115-04',
      product: 'فلنج فولادی کلاس ۱۵۰',
      severity: 'major',
      defect: 'انحراف تلرانس ضخامت به میزان +1.8mm فراتر از نقشه ساخت',
      disposition: 'return_to_vendor',
      affected_qty: '۲۰۰ عدد',
      status: 'open',
    },
    {
      id: 2,
      ncr_number: 'NCR-20261110-02',
      inspection: 'INS-20261110-01',
      product: 'بسته‌بندی کارتن مادر صادرانی',
      severity: 'minor',
      defect: 'پارگی جزئی در چاپ بارکد بدون آسیب به محصول داخل',
      disposition: 'rework',
      affected_qty: '۵۰ کارتن',
      status: 'closed',
    },
  ]);

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header bar */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <ShieldCheck className="h-6 w-6 text-primary" />
            سامانه جامع کنترل کیفیت و قرنطینه (Quality Management - QM)
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            طرح‌های بازرسی IQC/IPQC/OQC، قرنطینه هوشمند کالا تا اخذ مجوز، عدم انطباق (NCR) و اقدامات اصلاحی (CAPA).
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs px-3 py-1.5 flex items-center gap-1.5">
            <Lock className="h-3.5 w-3.5" />
            گیت کیفی فعال: بلوک خروج کالای نامنطبق
          </Badge>
          <Button size="sm">
            <Plus className="h-4 w-4 me-1.5" />
            دستور بازرسی جدید
          </Button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              نرخ پاس در اولین بازرسی (FPY)
              <TrendingUp className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-emerald-600">۹۲.۸٪ پذیرش</div>
            <p className="text-xs text-muted-foreground mt-0.5">میانگین ۳۰ روز اخیر خطوط</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              بچ‌های در قرنطینه (Quarantined)
              <Lock className="h-4 w-4 text-amber-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-amber-600">۱ بچ معلق</div>
            <p className="text-xs text-muted-foreground mt-0.5">غیرقابل تخصیص و فروش در انبار</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              پرونده‌های عدم انطباق باز (Open NCR)
              <AlertTriangle className="h-4 w-4 text-destructive" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-destructive">۱ مورد بحرانی</div>
            <p className="text-xs text-muted-foreground mt-0.5">تعیین تکلیف: برگشت به تأمین‌کننده</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              طرح‌های بازرسی فعال
              <ClipboardList className="h-4 w-4 text-primary" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold">۱۴ طرح استاندارد</div>
            <p className="text-xs text-muted-foreground mt-0.5">IQC ورودی، حین فرآیند و تست نهایی</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-4 max-w-xl">
          <TabsTrigger value="inspections">دستورهای بازرسی</TabsTrigger>
          <TabsTrigger value="lots">بچ‌ها و قرنطینه انبار</TabsTrigger>
          <TabsTrigger value="ncrs">عدم انطباق‌ها (NCR)</TabsTrigger>
          <TabsTrigger value="plans">طرح‌های کنترل کیفی</TabsTrigger>
        </TabsList>

        {/* Tab 1: Inspections */}
        <TabsContent value="inspections" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شماره بازرسی</TableHead>
                    <TableHead>نام کالا / بچ</TableHead>
                    <TableHead>نوع بازرسی</TableHead>
                    <TableHead>طرح کنترل کیفیت</TableHead>
                    <TableHead>اندازه نمونه بازرسی</TableHead>
                    <TableHead>نتیجه ارزیابی</TableHead>
                    <TableHead>بازرس فنی</TableHead>
                    <TableHead className="text-end">تاریخ</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {inspections.map((ins) => (
                    <TableRow key={ins.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{ins.number}</TableCell>
                      <TableCell>
                        <div className="font-semibold text-xs">{ins.product}</div>
                        <div className="text-[11px] font-mono text-muted-foreground">{ins.lot}</div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className="text-[11px]">
                          {ins.type === 'incoming' ? 'ورودی IQC' : ins.type === 'in_process' ? 'حین تولید IPQC' : 'نهایی OQC'}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">{ins.plan}</TableCell>
                      <TableCell className="text-xs font-mono">{ins.sample_size}</TableCell>
                      <TableCell>
                        {ins.verdict === 'pass' ? (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs flex items-center gap-1 w-fit">
                            <CheckCircle2 className="h-3 w-3" /> پذیرفته شد (Pass)
                          </Badge>
                        ) : ins.verdict === 'fail' ? (
                          <Badge variant="destructive" className="text-xs flex items-center gap-1 w-fit">
                            <XCircle className="h-3 w-3" /> مردود (Fail)
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 text-xs flex items-center gap-1 w-fit">
                            آزادسازی مشروط
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-xs">{ins.inspector}</TableCell>
                      <TableCell className="text-end text-xs font-mono">{ins.date}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 2: Quarantined Lots */}
        <TabsContent value="lots" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                وضعیت بچ‌ها و انبار قرنطینه کیفیت (Quarantine Stock Ledger)
                <Boxes className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                موجودی‌های دارای وضعیت Quarantined توسط گیت کیفی سیستم قفل شده و امکان خروج یا تحویل به مشتری را ندارند.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شماره بچ / لات</TableHead>
                    <TableHead>نام کالا</TableHead>
                    <TableHead>تعداد / مقدار</TableHead>
                    <TableHead>موقعیت فیزیکی در انبار</TableHead>
                    <TableHead>وضعیت کیفی گیت</TableHead>
                    <TableHead className="text-end">توضیحات و مجوزها</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {lots.map((lot) => (
                    <TableRow key={lot.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{lot.lot_number}</TableCell>
                      <TableCell className="font-semibold text-xs">{lot.product}</TableCell>
                      <TableCell className="font-mono text-xs">{lot.quantity}</TableCell>
                      <TableCell className="text-xs">{lot.location}</TableCell>
                      <TableCell>
                        {lot.status === 'released' ? (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                            آزاد برای مصرف/فروش ✓
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 text-xs flex items-center gap-1 w-fit">
                            <Lock className="h-3 w-3" /> در قرنطینه کیفی (بلوکه)
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-end text-xs text-muted-foreground">{lot.notes}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 3: NCRs */}
        <TabsContent value="ncrs" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                پرونده‌های گزارش عدم انطباق (Non-Conformance Reports - NCR)
                <AlertTriangle className="h-5 w-5 text-destructive" />
              </CardTitle>
              <CardDescription>ثبت انحرافات کیفی، تعیین تکلیف (Disposition) و ارجاع به اقدامات اصلاحی (CAPA).</CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شماره NCR</TableHead>
                    <TableHead>بازرسی متناظر</TableHead>
                    <TableHead>کالا و مقدار متأثر</TableHead>
                    <TableHead>شرح عیب / انحراف</TableHead>
                    <TableHead>سطح اهمیت</TableHead>
                    <TableHead>تصمیم نهایی (Disposition)</TableHead>
                    <TableHead className="text-end">وضعیت پرونده</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {ncrs.map((ncr) => (
                    <TableRow key={ncr.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{ncr.ncr_number}</TableCell>
                      <TableCell className="font-mono text-xs text-muted-foreground">{ncr.inspection}</TableCell>
                      <TableCell>
                        <div className="font-semibold text-xs">{ncr.product}</div>
                        <div className="text-[11px] font-mono text-muted-foreground">{ncr.affected_qty}</div>
                      </TableCell>
                      <TableCell className="text-xs max-w-xs truncate" title={ncr.defect}>{ncr.defect}</TableCell>
                      <TableCell>
                        <Badge variant={ncr.severity === 'critical' ? 'destructive' : 'secondary'} className="text-[11px]">
                          {ncr.severity}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className="text-xs font-mono">
                          {ncr.disposition === 'return_to_vendor' ? 'برگشت به تأمین‌کننده' : 'بازکاری (Rework)'}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-end">
                        {ncr.status === 'open' ? (
                          <Badge variant="destructive" className="text-xs">جاری / در دست اقدام</Badge>
                        ) : (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">مختومه ✓</Badge>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 4: Inspection Plans */}
        <TabsContent value="plans" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">طرح‌های بازرسی و استانداردها (Inspection Plans)</CardTitle>
              <CardDescription>چک‌لیست‌های آزمون، مقادیر مجاز تلرانس و چک‌پوینت‌های اجباری.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3 p-4">
              <div className="rounded-lg border p-3 flex items-center justify-between hover:bg-muted/20">
                <div>
                  <h4 className="font-semibold text-sm">طرح کنترل ورودی پودر رزین اپوکسی (IQC-EPOXY-01)</h4>
                  <p className="text-xs text-muted-foreground mt-0.5">آزمون‌های ویسکوزیته، رنگ، خلوص شیمیایی و درصد رطوبت</p>
                </div>
                <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">فعال</Badge>
              </div>
              <div className="rounded-lg border p-3 flex items-center justify-between hover:bg-muted/20">
                <div>
                  <h4 className="font-semibold text-sm">طرح بازرسی تست ابعادی قطعات صنعتی (IPQC-DIM-02)</h4>
                  <p className="text-xs text-muted-foreground mt-0.5">تلرانس قطرهای داخلی و خارجی با کولیس و میکرومتر دیجیتال</p>
                </div>
                <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">فعال</Badge>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

export function QmPlansPage() {
  return <QmOverviewPage />;
}

export function QmLotsPage() {
  return <QmOverviewPage />;
}

export function QmInspectionsPage() {
  return <QmOverviewPage />;
}

export function QmNcrsPage() {
  return <QmOverviewPage />;
}

export function QmCapasPage() {
  return <QmOverviewPage />;
}

export function QmAnalyticsPage() {
  return <QmOverviewPage />;
}
