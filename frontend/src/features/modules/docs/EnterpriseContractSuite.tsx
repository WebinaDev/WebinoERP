'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  FileCheck,
  ShieldCheck,
  Send,
  Plus,
  Users,
  CreditCard,
  History,
  Lock,
  FileText,
  CheckCircle2,
  AlertCircle,
  Building2,
  Calendar,
  ExternalLink,
} from 'lucide-react';

export interface ContractDetail {
  id: number;
  contract_number: string;
  title: string;
  status: 'draft' | 'internal_review' | 'sent' | 'customer_review' | 'negotiation' | 'pending_signature' | 'signed' | 'active' | 'cancelled';
  total_amount: number;
  currency: string;
  start_date: string;
  end_date: string;
  version: number;
  is_locked: boolean;
  parties: {
    id: number;
    role: 'first_party' | 'second_party' | 'guarantor';
    name: string;
    national_id: string;
    signatory_name: string;
    phone: string;
  }[];
  installments: {
    id: number;
    title: string;
    amount: number;
    due_date: string;
    status: 'pending' | 'invoiced' | 'paid';
  }[];
  addenda: {
    id: number;
    addendum_number: string;
    title: string;
    type: string;
    amount_delta: number;
    effective_date: string;
  }[];
}

export function EnterpriseContractSuite() {
  const [activeTab, setActiveTab] = useState<'overview' | 'parties' | 'finance' | 'esign' | 'addenda'>('overview');
  const [isSigningModalOpen, setIsSigningModalOpen] = useState(false);
  const [otpCode, setOtpCode] = useState('');
  const [isSignedSuccess, setIsSignedSuccess] = useState(false);

  const [contract, setContract] = useState<ContractDetail>({
    id: 101,
    contract_number: 'CTR-1405-2084',
    title: 'قرارداد جامع توسعه، استقرار و پشتیبانی پلتفرم سازمانی WebinoERP',
    status: 'pending_signature',
    total_amount: 850000000,
    currency: 'IRR',
    start_date: '۱۴۰۵/۰۹/۰۱',
    end_date: '۱۴۰۶/۰۸/۳۰',
    version: 1,
    is_locked: false,
    parties: [
      {
        id: 1,
        role: 'first_party',
        name: 'شرکت فناوری اطلاعات و توسعه کسب‌وکار وبینا',
        national_id: '14010823901',
        signatory_name: 'مهندس آرش کریمی (مدیرعامل)',
        phone: '02188991200',
      },
      {
        id: 2,
        role: 'second_party',
        name: 'شرکت پتروشیمی زاگرس (سهامی عام)',
        national_id: '10102482910',
        signatory_name: 'دکتر علیرضا رضایی (معاونت فناوری)',
        phone: '09121112233',
      },
    ],
    installments: [
      { id: 1, title: 'پیش‌پرداخت اولیه حین امضای قرارداد', amount: 255000000, due_date: '۱۴۰۵/۰۹/۰۵', status: 'paid' },
      { id: 2, title: 'قسط دوم: تحویل فاز ۱ (پلتفرم و زیرساخت داکر)', amount: 297500000, due_date: '۱۴۰۵/۱۲/۲۰', status: 'pending' },
      { id: 3, title: 'قسط سوم: تحویل نهایی و آموزش پرسنل', amount: 297500000, due_date: '۱۴۰۶/۰۴/۱۵', status: 'pending' },
    ],
    addenda: [],
  });

  const formatCurrency = (val: number) => {
    return val.toLocaleString('fa-IR') + ' ' + (contract.currency === 'IRR' ? 'ریال' : contract.currency);
  };

  const handleVerifySign = () => {
    if (otpCode.length === 6) {
      setIsSignedSuccess(true);
      setContract((prev) => ({
        ...prev,
        status: 'signed',
        is_locked: true,
      }));
      setTimeout(() => {
        setIsSigningModalOpen(false);
      }, 1500);
    }
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header bar */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold tracking-tight">{contract.title}</h1>
            <Badge variant="outline" className="font-mono text-xs">
              {contract.contract_number} (نسخه v{contract.version})
            </Badge>
          </div>
          <p className="text-sm text-muted-foreground mt-1">
            سامانه مدیریت چرخه عمر قرارداد (CLM)، طرفین حقوقی، تعهدات مالی و امضای الکترونیک امن.
          </p>
        </div>

        <div className="flex items-center gap-2">
          {contract.is_locked ? (
            <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs px-3 py-1.5 flex items-center gap-1.5">
              <Lock className="h-3.5 w-3.5" />
              سند رسمی امضاشده و قفل حقوقی
            </Badge>
          ) : (
            <Button size="sm" onClick={() => setIsSigningModalOpen(true)} className="bg-primary text-primary-foreground">
              <ShieldCheck className="h-4 w-4 me-1.5" />
              امضای دیجیتال با رمز پیامکی (OTP)
            </Button>
          )}
        </div>
      </div>

      {/* Contract KPI metrics */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              وضعیت حقوقی قرارداد
              <FileCheck className="h-4 w-4 text-primary" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-lg font-bold">
              {contract.status === 'signed' ? 'امضاشده قطعی (Signed)' : 'در انتظار امضا (Pending Signature)'}
            </div>
            <p className="text-xs text-muted-foreground mt-1">
              {contract.is_locked ? 'ویرایش متن مسدود است' : 'آماده اعتبارسنجی طرفین'}
            </p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              مبلغ کل قرارداد
              <CreditCard className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-lg font-bold text-primary">{formatCurrency(contract.total_amount)}</div>
            <p className="text-xs text-muted-foreground mt-1">تسهیم در ۳ قسط زمان‌بندی‌شده</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              افق زمانی اجرا
              <Calendar className="h-4 w-4 text-blue-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-sm font-bold font-mono">{contract.start_date} تا {contract.end_date}</div>
            <p className="text-xs text-muted-foreground mt-1">دوره ۱۲ ماهه پشتیبانی طلایی</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              طرف‌های حقوقی
              <Users className="h-4 w-4 text-purple-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-lg font-bold">{contract.parties.length} شخصیت حقوقی</div>
            <p className="text-xs text-muted-foreground mt-1">شناسه ملی و امضاکنندگان ثبت‌شده</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-5 max-w-2xl">
          <TabsTrigger value="overview">متن و مفاد</TabsTrigger>
          <TabsTrigger value="parties">طرف‌های قرارداد</TabsTrigger>
          <TabsTrigger value="finance">جدول اقساط و مالی</TabsTrigger>
          <TabsTrigger value="esign">پاکت امضای الکترونیک</TabsTrigger>
          <TabsTrigger value="addenda">الحاقیه‌ها و متمم</TabsTrigger>
        </TabsList>

        {/* Tab 1: Overview */}
        <TabsContent value="overview" className="mt-4 space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">مفاد و شرایط اختصاصی قرارداد</CardTitle>
              <CardDescription>متن کامل تعهدات طرفین، محرمانگی (NDA) و تعهدات سطح خدمات (SLA).</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="p-4 rounded-lg bg-muted/30 border font-sans text-sm leading-relaxed text-foreground/90 space-y-3">
                <p>
                  <strong>ماده ۱- موضوع قرارداد:</strong> عبارت است از ارائه لایسنس، نصب و راه‌اندازی ماژول‌های پلتفرم و انبار پیشرفته سامانه جامع سازمانی WebinoERP بر روی زیرساخت داکر اختصاصی کارفرما.
                </p>
                <p>
                  <strong>ماده ۲- مبلغ و نحوه پرداخت:</strong> مبلغ کل قرارداد به میزان هشتصد و پنجاه میلیون ریال تعیین گردید که در سه مرحله طبق جدول تعهدات مالی پیوست پرداخت خواهد شد.
                </p>
                <p>
                  <strong>ماده ۳- امنیت اطلاعات و عدم افشا:</strong> طرفین متعهد می‌گردند کلیه داده‌های حسابداری، پرسنلی و فرآیندهای تجاری را کاملاً محرمانه تلقی نمایند.
                </p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 2: Parties */}
        <TabsContent value="parties" className="mt-4 space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            {contract.parties.map((p) => (
              <Card key={p.id} className="border-border">
                <CardHeader className="pb-2">
                  <div className="flex items-center justify-between">
                    <Badge variant={p.role === 'first_party' ? 'default' : 'secondary'} className="text-xs">
                      {p.role === 'first_party' ? 'طرف اول (مجری)' : 'طرف دوم (کارفرما)'}
                    </Badge>
                    <Building2 className="h-4 w-4 text-muted-foreground" />
                  </div>
                  <CardTitle className="text-base mt-2">{p.name}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-xs">
                  <div className="flex justify-between border-b pb-1.5">
                    <span className="text-muted-foreground">شناسه ملی / اقتصادی:</span>
                    <span className="font-mono font-semibold">{p.national_id}</span>
                  </div>
                  <div className="flex justify-between border-b pb-1.5">
                    <span className="text-muted-foreground">نماینده و امضاکننده مجاز:</span>
                    <span className="font-semibold">{p.signatory_name}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">شماره تماس رسمی:</span>
                    <span className="font-mono">{p.phone}</span>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>

        {/* Tab 3: Finance & Installments */}
        <TabsContent value="finance" className="mt-4 space-y-4">
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base">زمان‌بندی وصول اقساط و اتصال به فاکتور فروش</CardTitle>
              <CardDescription>ثبت سررسیدها، وصول مطالبات و صدور سند حسابداری رسمی در ماژول Sales.</CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>عنوان قسط / مرحله</TableHead>
                    <TableHead>مبلغ تعهد</TableHead>
                    <TableHead>تاریخ سررسید</TableHead>
                    <TableHead className="text-end">وضعیت تسویه</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {contract.installments.map((ins) => (
                    <TableRow key={ins.id} className="hover:bg-muted/20">
                      <TableCell className="font-medium">{ins.title}</TableCell>
                      <TableCell className="font-mono font-bold">{formatCurrency(ins.amount)}</TableCell>
                      <TableCell className="font-mono text-xs">{ins.due_date}</TableCell>
                      <TableCell className="text-end">
                        {ins.status === 'paid' ? (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                            تسویه شد ✓
                          </Badge>
                        ) : (
                          <Badge variant="secondary" className="text-xs">
                            در انتظار سررسید
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

        {/* Tab 4: E-Signature Envelopes */}
        <TabsContent value="esign" className="mt-4 space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">گواهی و پاکت امضای الکترونیک امن (Tamper-Proof E-Sign)</CardTitle>
              <CardDescription>احراز هویت پیامکی دومرحله‌ای با OTP، ثبت IP امضاکننده و گواهی رمزنگاری SHA-256.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-lg border p-4 bg-muted/20 space-y-3">
                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="text-sm font-semibold">پاکت امضای نماینده کارفرما (دکتر علیرضا رضایی)</h4>
                    <p className="text-xs text-muted-foreground mt-0.5">شماره همراه امضاکننده: ۰۹۱۲۱۱۱۲۲۳۳</p>
                  </div>
                  <Badge variant={contract.is_locked ? 'outline' : 'secondary'} className="text-xs">
                    {contract.is_locked ? 'امضاشده رسمی' : 'در انتظار دریافت کد OTP'}
                  </Badge>
                </div>
                {contract.is_locked ? (
                  <div className="text-xs font-mono text-emerald-600 bg-emerald-50 p-2.5 rounded border border-emerald-200">
                    شناسه گواهی دیجیتال (SHA-256): <span className="font-bold">e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855</span>
                  </div>
                ) : null}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 5: Addenda */}
        <TabsContent value="addenda" className="mt-4 space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-base font-semibold">متمم‌ها و الحاقیه‌های رسمی قرارداد</h3>
              <p className="text-xs text-muted-foreground">افزایش مبلغ، تمدید دوره پشتیبانی و تغییر دامنه تعهدات حقوقی.</p>
            </div>
            <Button size="sm">
              <Plus className="h-4 w-4 me-1.5" />
              ثبت الحاقیه جدید
            </Button>
          </div>

          <div className="p-8 text-center border rounded-lg bg-muted/10 text-muted-foreground text-xs">
            تاکنون الحاقیه‌ای برای این قرارداد ثبت نشده است. کلیه تعهدات طبق نسخه اصلی معتبر هستند.
          </div>
        </TabsContent>
      </Tabs>

      {/* OTP Sign Modal */}
      {isSigningModalOpen ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <Card className="w-full max-w-md shadow-xl bg-background border animate-in fade-in zoom-in-95">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <ShieldCheck className="h-5 w-5 text-primary" />
                امضای دیجیتال سند با رمز یکبارمصرف (OTP)
              </CardTitle>
              <CardDescription className="text-xs">
                کد تایید ۶ رقمی به شماره ۰۹۱۲۱۱۱۲۲۳۳ ارسال گردید. لطفاً کد را جهت قفل حقوقی قرارداد وارد نمایید.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-1.5">
                <Label htmlFor="otp">کد اعتبارسنجی پیامکی</Label>
                <Input
                  id="otp"
                  maxLength={6}
                  placeholder="123456"
                  className="font-mono text-center tracking-widest text-lg"
                  value={otpCode}
                  onChange={(e) => setOtpCode(e.target.value)}
                />
              </div>

              {isSignedSuccess ? (
                <div className="p-2.5 bg-emerald-50 text-emerald-700 text-xs rounded border border-emerald-200 flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4" />
                  قرارداد با موفقیت امضا و سند قفل حقوقی گردید!
                </div>
              ) : null}

              <div className="flex justify-end gap-2 pt-2">
                <Button variant="outline" size="sm" onClick={() => setIsSigningModalOpen(false)}>
                  انصراف
                </Button>
                <Button size="sm" onClick={handleVerifySign} disabled={otpCode.length !== 6 || isSignedSuccess}>
                  تایید و امضای رسمی سند
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      ) : null}
    </div>
  );
}
