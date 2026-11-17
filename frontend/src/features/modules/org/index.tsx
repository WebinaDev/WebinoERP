'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Network,
  Building2,
  Store,
  ArrowRightLeft,
  FileDigit,
  ShieldCheck,
  Plus,
  Layers,
  MapPin,
  CheckCircle2,
  Lock,
  Globe2,
  Briefcase,
} from 'lucide-react';

export function OrgOverviewPage() {
  const [activeTab, setActiveTab] = useState<'companies' | 'branches' | 'transfers' | 'sequences'>('companies');

  const [companies, setCompanies] = useState([
    {
      id: 1,
      name: 'شرکت فناوری اطلاعات و توسعه کسب‌وکار وبینا (هلدینگ اصلی)',
      legal_id: '14010823901',
      economic_code: '411589321489',
      branches_count: 2,
      currency: 'IRR',
      fiscal_year: '۱۴۰۵ (فعال)',
      status: 'active',
      is_holding: true,
    },
    {
      id: 2,
      name: 'شرکت نوآوران ارتباطات داده مهر (شرکت تابعه A)',
      legal_id: '10103829104',
      economic_code: '411892019482',
      branches_count: 1,
      currency: 'IRR',
      fiscal_year: '۱۴۰۵ (فعال)',
      status: 'active',
      is_holding: false,
    },
  ]);

  const [branches, setBranches] = useState([
    {
      id: 1,
      company: 'وبینا (هلدینگ اصلی)',
      code: 'BR-TEH-01',
      name: 'شعبه مرکزی تهران (ونک - دفتر مرکزی)',
      type: 'دفتر مرکزی و هاب فناوری',
      manager: 'مهندس آرش کریمی',
      warehouse: 'انبار مرکزی فناوری و تجهیزات (WH-TEH-01)',
      status: 'active',
    },
    {
      id: 2,
      company: 'وبینا (هلدینگ اصلی)',
      code: 'BR-ASS-02',
      name: 'شعبه پشتیبانی فنی جنوب (عسلویه)',
      type: 'پایگاه عملیاتی و خدمات FSM',
      manager: 'آرش طاهری',
      warehouse: 'انبار قطعات یدکی جنوب (WH-ASS-01)',
      status: 'active',
    },
    {
      id: 3,
      company: 'داده مهر (تابعه A)',
      code: 'BR-ISF-01',
      name: 'شعبه منطقه‌ای اصفهان',
      type: 'دفتر فروش و استقرار منطقه‌ای',
      manager: 'مهندس حسینی',
      warehouse: 'انبار توزیع اصفهان (WH-ISF-01)',
      status: 'active',
    },
  ]);

  const [transfers, setTransfers] = useState([
    {
      id: 1,
      transfer_number: 'TRF-20261116-01',
      type: 'inter_branch',
      from: 'شعبه تهران · انبار مرکزی (WH-TEH-01)',
      to: 'شعبه عسلویه · انبار قطعات (WH-ASS-01)',
      items_summary: '۵ عدد منبع تغذیه صنعتی ۲۴ ولت',
      amount: 72500000,
      status: 'in_transit',
      date: '۱۴۰۵/۰۸/۲۶',
    },
    {
      id: 2,
      transfer_number: 'ICT-20261114-04',
      type: 'inter_company',
      from: 'شرکت وبینا (فروشنده درون‌گروهی)',
      to: 'شرکت داده مهر (خریدار درون‌گروهی)',
      items_summary: '۱۰ لایسنس ماژول پلتفرم ابری',
      amount: 320000000,
      status: 'received',
      date: '۱۴۰۵/۰۸/۲۴',
    },
  ]);

  const [sequences, setSequences] = useState([
    { id: 1, company: 'وبینا', branch: 'دفتر مرکزی تهران', type: 'فاکتور فروش (sales_invoice)', prefix: 'INV-WEB-TEH-', next: '00142' },
    { id: 2, company: 'وبینا', branch: 'شعبه عسلویه', type: 'دستورکار سرویس (work_order)', prefix: 'FSM-ASS-', next: '00089' },
    { id: 3, company: 'داده مهر', branch: 'شعبه اصفهان', type: 'فاکتور فروش (sales_invoice)', prefix: 'INV-DM-ISF-', next: '00054' },
    { id: 4, company: 'وبینا', branch: 'دفتر مرکزی تهران', type: 'سند حسابداری (journal_entry)', prefix: 'JV-1405-', next: '08843' },
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
            <Network className="h-6 w-6 text-primary" />
            مدیریت هلدینگ، چندشرکتی و چندشعبه‌ای (Multi-Company & Multi-Branch Hub)
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            جداسازی دفاتر کل مالی، شماره‌گذاری مستقل اسناد، انتقال درون‌گروهی (Intercompany) و سوییچ کانتکست فعال.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs px-3 py-1.5 flex items-center gap-1.5">
            <ShieldCheck className="h-3.5 w-3.5" />
            ایزوله‌سازی داده‌ای فعال (Tenant Scoping)
          </Badge>
          <Button size="sm">
            <Plus className="h-4 w-4 me-1.5" />
            تعریف شعبه جدید
          </Button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              شخصیت‌های حقوقی مستقل
              <Building2 className="h-4 w-4 text-primary" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold">{companies.length} شرکت ثبت‌شده</div>
            <p className="text-xs text-muted-foreground mt-0.5">دارای دفاتر کل و کد اقتصادی مستقل</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              شعب و پایگاه‌های عملیاتی
              <Store className="h-4 w-4 text-blue-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold">{branches.length} شعبه فعال</div>
            <p className="text-xs text-muted-foreground mt-0.5">تهران، عسلویه و اصفهان</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              حواله‌های انتقالی بین‌شعبه/شرکت
              <ArrowRightLeft className="h-4 w-4 text-purple-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-purple-600">۲ حواله جاری</div>
            <p className="text-xs text-muted-foreground mt-0.5">با صدور خودکار سند حسابداری تسویه</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              سری‌های شماره‌گذاری اسناد
              <FileDigit className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-emerald-600">{sequences.length} سری مستقل</div>
            <p className="text-xs text-muted-foreground mt-0.5">عدم تداخل شماره فاکتور و اسناد مالی</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-4 max-w-xl">
          <TabsTrigger value="companies">شرکت‌های هلدینگ</TabsTrigger>
          <TabsTrigger value="branches">شعب و انبارها</TabsTrigger>
          <TabsTrigger value="transfers">انتقال درون‌گروهی و بین‌شعبه</TabsTrigger>
          <TabsTrigger value="sequences">سری‌های شماره‌گذاری اسناد</TabsTrigger>
        </TabsList>

        {/* Tab 1: Companies */}
        <TabsContent value="companies" className="mt-4 space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            {companies.map((c) => (
              <Card key={c.id} className="border-border">
                <CardHeader className="pb-2">
                  <div className="flex items-center justify-between">
                    <Badge variant={c.is_holding ? 'default' : 'secondary'} className="text-xs">
                      {c.is_holding ? 'هلدینگ مادر' : 'شرکت تابعه'}
                    </Badge>
                    <Building2 className="h-4 w-4 text-muted-foreground" />
                  </div>
                  <CardTitle className="text-base mt-2">{c.name}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-xs">
                  <div className="flex justify-between border-b pb-1.5">
                    <span className="text-muted-foreground">شناسه ملی حقوقی:</span>
                    <span className="font-mono font-semibold">{c.legal_id}</span>
                  </div>
                  <div className="flex justify-between border-b pb-1.5">
                    <span className="text-muted-foreground">کد اقتصادی مالیاتی:</span>
                    <span className="font-mono">{c.economic_code}</span>
                  </div>
                  <div className="flex justify-between border-b pb-1.5">
                    <span className="text-muted-foreground">تعداد شعب زیرمجموعه:</span>
                    <span className="font-semibold">{c.branches_count} شعبه</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">سال مالی و ارز پایه:</span>
                    <span className="font-mono">{c.fiscal_year} · {c.currency}</span>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>

        {/* Tab 2: Branches */}
        <TabsContent value="branches" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>کد شعبه</TableHead>
                    <TableHead>عنوان شعبه</TableHead>
                    <TableHead>شرکت مادر</TableHead>
                    <TableHead>نوع واحد</TableHead>
                    <TableHead>مدیر شعبه</TableHead>
                    <TableHead>انبار پیش‌فرض متصل</TableHead>
                    <TableHead className="text-end">وضعیت</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {branches.map((b) => (
                    <TableRow key={b.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{b.code}</TableCell>
                      <TableCell className="font-semibold text-xs">{b.name}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{b.company}</TableCell>
                      <TableCell className="text-xs">{b.type}</TableCell>
                      <TableCell className="text-xs">{b.manager}</TableCell>
                      <TableCell className="text-xs font-mono text-muted-foreground">{b.warehouse}</TableCell>
                      <TableCell className="text-end">
                        <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                          فعال ✓
                        </Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 3: Transfers */}
        <TabsContent value="transfers" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                حواله‌های انتقال کالا بین شعب و شرکت‌ها (Intercompany & Inter-Branch Transfers)
                <ArrowRightLeft className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                تفکیک دقیق جابجایی فیزیکی درون‌شعبه‌ای از معاملات مالی حقوقی بین شرکت‌های هلدینگ با صدور سند تسویه حسابداری.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شماره حواله</TableHead>
                    <TableHead>نوع انتقال</TableHead>
                    <TableHead>مبدأ</TableHead>
                    <TableHead>مقصد</TableHead>
                    <TableHead>اقلام ارسالی</TableHead>
                    <TableHead>ارزش ریالی</TableHead>
                    <TableHead className="text-end">وضعیت انتقال</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {transfers.map((trf) => (
                    <TableRow key={trf.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{trf.transfer_number}</TableCell>
                      <TableCell>
                        <Badge variant={trf.type === 'inter_company' ? 'default' : 'secondary'} className="text-[11px]">
                          {trf.type === 'inter_company' ? 'معامله بین‌شرکتی (Intercompany)' : 'جابجایی بین‌شعبه'}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs">{trf.from}</TableCell>
                      <TableCell className="text-xs">{trf.to}</TableCell>
                      <TableCell className="text-xs">{trf.items_summary}</TableCell>
                      <TableCell className="font-mono text-xs font-bold text-primary">{formatCurrency(trf.amount)}</TableCell>
                      <TableCell className="text-end">
                        {trf.status === 'received' ? (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                            تحویل شد ✓
                          </Badge>
                        ) : (
                          <Badge variant="secondary" className="text-xs">در حال حمل (In-Transit)</Badge>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 4: Number Sequences */}
        <TabsContent value="sequences" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                سری‌های شماره‌گذاری مستقل اسناد به تفکیک شرکت و شعبه (Document Numbering Sequences)
                <FileDigit className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                تضمین استقلال حقوقی شماره فاکتورها، قراردادها و اسناد روزنامه به ازای هر شخصیت حقوقی هلدینگ.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شرکت حقوقی</TableHead>
                    <TableHead>شعبه سازمانی</TableHead>
                    <TableHead>نوع سند</TableHead>
                    <TableHead>پیشوند شماره (Prefix)</TableHead>
                    <TableHead className="text-end">شماره بعدی (Next Number)</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {sequences.map((seq) => (
                    <TableRow key={seq.id} className="hover:bg-muted/20">
                      <TableCell className="font-semibold text-xs">{seq.company}</TableCell>
                      <TableCell className="text-xs">{seq.branch}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{seq.type}</TableCell>
                      <TableCell className="font-mono text-xs font-semibold">{seq.prefix}</TableCell>
                      <TableCell className="text-end font-mono text-xs text-primary font-bold">{seq.next}</TableCell>
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

export function OrgCompaniesPage() {
  return <OrgOverviewPage />;
}

export function OrgBranchesPage() {
  return <OrgOverviewPage />;
}

export function OrgTransfersPage() {
  return <OrgOverviewPage />;
}

export function OrgSequencesPage() {
  return <OrgOverviewPage />;
}

export function OrgConsolidatedPage() {
  return <OrgOverviewPage />;
}
