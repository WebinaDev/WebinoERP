'use client';

import React, { useState } from 'react';
import { useTranslations } from 'next-intl';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  ShieldCheck,
  ShieldAlert,
  Users,
  Key,
  Sliders,
  PlayCircle,
  FileSpreadsheet,
  Lock,
  Eye,
  EyeOff,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Building2,
  GitBranch,
  Layers,
  ArrowRight,
  Filter,
  Search,
} from 'lucide-react';

interface MockRole {
  id: number;
  code: string;
  title_fa: string;
  is_system: boolean;
  users_count: number;
  permissions_count: number;
}

interface MockPermission {
  code: string;
  module: string;
  resource: string;
  action: string;
  title_fa: string;
  risk_level: 'low' | 'medium' | 'high' | 'critical';
  requires_mfa: boolean;
  requires_reason: boolean;
}

interface MockFieldPolicy {
  resource: string;
  field: string;
  permission_needed: string;
  effect: 'hide' | 'mask' | 'read_only';
}

const MOCK_ROLES: MockRole[] = [
  { id: 1, code: 'super_admin', title_fa: 'مدیر ارشد هلدینگ (System Super Admin)', is_system: true, users_count: 2, permissions_count: 48 },
  { id: 2, code: 'company_admin', title_fa: 'مدیر اجرایی شرکت (Company Admin)', is_system: true, users_count: 5, permissions_count: 44 },
  { id: 3, code: 'sales_manager', title_fa: 'مدیر فروش', is_system: true, users_count: 6, permissions_count: 24 },
  { id: 4, code: 'sales_agent', title_fa: 'کارشناس فروش', is_system: true, users_count: 18, permissions_count: 14 },
  { id: 5, code: 'warehouse_operator', title_fa: 'انباردار و کارشناس لجستیک', is_system: true, users_count: 12, permissions_count: 16 },
  { id: 6, code: 'finance_manager', title_fa: 'مدیر مالی و خزانه', is_system: true, users_count: 3, permissions_count: 28 },
  { id: 7, code: 'technician', title_fa: 'تکنسین فنی خدمات پس از فروش', is_system: true, users_count: 14, permissions_count: 12 },
  { id: 8, code: 'auditor', title_fa: 'حسابرس و ناظر دسترسی (Auditor)', is_system: true, users_count: 2, permissions_count: 30 },
];

const MOCK_PERMISSIONS: MockPermission[] = [
  // Sales
  { code: 'sales.quote.view', module: 'sales', resource: 'quote', action: 'view', title_fa: 'مشاهده پیش‌فاکتور', risk_level: 'low', requires_mfa: false, requires_reason: false },
  { code: 'sales.quote.approve', module: 'sales', resource: 'quote', action: 'approve', title_fa: 'تأیید پیش‌فاکتور', risk_level: 'high', requires_mfa: false, requires_reason: true },
  { code: 'sales.invoice.view', module: 'sales', resource: 'invoice', action: 'view', title_fa: 'مشاهده فاکتور فروش', risk_level: 'low', requires_mfa: false, requires_reason: false },
  { code: 'sales.invoice.approve', module: 'sales', resource: 'invoice', action: 'approve', title_fa: 'تأیید فاکتور فروش', risk_level: 'high', requires_mfa: false, requires_reason: false },
  { code: 'sales.invoice.void', module: 'sales', resource: 'invoice', action: 'void', title_fa: 'ابطال فاکتور فروش رسمی', risk_level: 'critical', requires_mfa: true, requires_reason: true },
  { code: 'sales.invoice.export', module: 'sales', resource: 'invoice', action: 'export', title_fa: 'خروجی اکسل فاکتورها', risk_level: 'high', requires_mfa: false, requires_reason: true },
  { code: 'sales.cost.view', module: 'sales', resource: 'cost', action: 'view_cost', title_fa: 'مشاهده بهای تمام‌شده و قیمت خرید', risk_level: 'critical', requires_mfa: false, requires_reason: false },
  { code: 'sales.discount.edit', module: 'sales', resource: 'discount', action: 'edit_discount', title_fa: 'اعمال یا تغییر تخفیف تجاری', risk_level: 'medium', requires_mfa: false, requires_reason: false },
  // SCM
  { code: 'scm.stock.view', module: 'scm', resource: 'stock', action: 'view', title_fa: 'مشاهده موجودی و کاردکس کالا', risk_level: 'low', requires_mfa: false, requires_reason: false },
  { code: 'scm.transfer.create', module: 'scm', resource: 'transfer', action: 'create', title_fa: 'انتقال بین انبارها و شعب', risk_level: 'high', requires_mfa: false, requires_reason: false },
  // Finance
  { code: 'fin.gl.post', module: 'finance', resource: 'gl', action: 'approve', title_fa: 'ثبت قطعی و ارسال اسناد حسابداری', risk_level: 'critical', requires_mfa: true, requires_reason: false },
  { code: 'fin.tax.export', module: 'finance', resource: 'tax', action: 'export', title_fa: 'خروجی سامانه مودیان و مالیات', risk_level: 'critical', requires_mfa: false, requires_reason: true },
  // QM
  { code: 'qm.lot.release', module: 'qm', resource: 'lot', action: 'approve', title_fa: 'آزادسازی محموله از قرنطینه به انبار فروش', risk_level: 'critical', requires_mfa: false, requires_reason: true },
  // FSM
  { code: 'fsm.workorder.dispatch', module: 'fsm', resource: 'workorder', action: 'update', title_fa: 'اعزام تکنسین و صدور دستورکار FSM', risk_level: 'medium', requires_mfa: false, requires_reason: false },
];

const MOCK_FIELD_POLICIES: MockFieldPolicy[] = [
  { resource: 'sales.invoice', field: 'cost_price', permission_needed: 'sales.cost.view', effect: 'hide' },
  { resource: 'sales.invoice', field: 'buy_price', permission_needed: 'sales.cost.view', effect: 'hide' },
  { resource: 'sales.invoice', field: 'profit_margin', permission_needed: 'sales.cost.view', effect: 'hide' },
  { resource: 'acc_cash_accounts', field: 'sheba', permission_needed: 'fin.gl.view', effect: 'mask' },
  { resource: 'hrm_payroll', field: 'base_salary', permission_needed: 'platform.member.manage', effect: 'hide' },
];

export function AuthzOverviewPage() {
  const t = useTranslations('erp.authz');
  const [activeTab, setActiveTab] = useState<'roles' | 'permissions' | 'policies' | 'simulator' | 'audit'>('roles');

  // Simulator State
  const [simUser, setSimUser] = useState('ali_rezaei (کارشناس فروش)');
  const [simBranch, setSimBranch] = useState('شعبه مرکزی تهران');
  const [simPermission, setSimPermission] = useState('sales.invoice.approve');
  const [simAmount, setSimAmount] = useState('15000000');
  const [simRecordBranch, setSimRecordBranch] = useState('شعبه مرکزی تهران');
  const [simResult, setSimResult] = useState<{
    evaluated: boolean;
    allowed: boolean;
    decision: 'allow' | 'deny' | 'allow_with_approval';
    reason: string;
    sourceRole: string;
    blockedPolicy?: string;
    scopeVerdict: string;
    fieldEffects: string[];
  } | null>({
    evaluated: true,
    allowed: false,
    decision: 'allow_with_approval',
    reason: 'مبلغ فاکتور (۱۵,۰۰۰,۰۰۰ ریال) بیش از سقف اختیار کارشناس (۱۰,۰۰۰,۰۰۰ ریال) است؛ درخواست به کارتابل تأیید مدیر فروش ارجاع شد.',
    sourceRole: 'sales_agent (کارشناس فروش)',
    blockedPolicy: 'سقف تأیید تخفیف کارشناس فروش (حداکثر ۱۰ میلیون ریال)',
    scopeVerdict: 'تطابق اسکوپ شعبه: فاکتور متعلق به شعبه تهران و کاربر در شعبه تهران است ✓',
    fieldEffects: ['فیلد cost_price و buy_price مخفی شد (نیاز به sales.cost.view)', 'خروجی اکسل فاکتور مسدود است (فاقد sales.invoice.export)'],
  });

  const runSimulation = () => {
    const amt = parseFloat(simAmount) || 0;
    const sameBranch = simBranch === simRecordBranch;

    if (!sameBranch) {
      setSimResult({
        evaluated: true,
        allowed: false,
        decision: 'deny',
        reason: 'اسکوپ رکورد خارج از حوزه مجاز کاربر است. کاربر فقط به رکوردهای شعبه خود (scope=branch) دسترسی دارد.',
        sourceRole: 'sales_agent (کارشناس فروش)',
        blockedPolicy: 'قانون تفکیک داده شعب (Branch Boundary Enforcer)',
        scopeVerdict: 'عدم انطباق اسکوپ: رکورد متعلق به شعبه دیگری است ✗',
        fieldEffects: ['دسترسی به کل سند فاکتور لغو گردید.'],
      });
      return;
    }

    if (simPermission === 'sales.invoice.export') {
      setSimResult({
        evaluated: true,
        allowed: false,
        decision: 'deny',
        reason: 'نقش کارشناس فروش فاقد مجوز اتمی خروجی اکسل (sales.invoice.export) است. اصل حداقل دسترسی (Least Privilege).',
        sourceRole: 'sales_agent',
        blockedPolicy: 'قانون منع استخراج کلان داده (Data Leak Prevention)',
        scopeVerdict: 'اسکوپ شعبه برقرار است.',
        fieldEffects: ['دکمه خروجی اکسل در UI غیرفعال و در API بلاک است.'],
      });
      return;
    }

    if (amt > 10000000) {
      setSimResult({
        evaluated: true,
        allowed: false,
        decision: 'allow_with_approval',
        reason: `مبلغ فاکتور (${amt.toLocaleString()} ریال) بیش از سقف اختیار کارشناس است؛ نیازمند چرخه تأیید کارتابل.`,
        sourceRole: 'sales_agent',
        blockedPolicy: 'سیاست ABAC سقف تأیید مالی کارشناس',
        scopeVerdict: 'تطابق اسکوپ شعبه: کاربر مجاز به ایجاد درخواست است ✓',
        fieldEffects: ['قیمت خرید و سود ناخالص برای کاربر پنهان است (hide).'],
      });
      return;
    }

    setSimResult({
      evaluated: true,
      allowed: true,
      decision: 'allow',
      reason: 'کلیه شرایط ۴ لایه احراز شد: عضویت معتبر، نقش مجاز، تطابق اسکوپ شعبه و مبلغ زیر سقف مجاز.',
      sourceRole: 'sales_agent',
      scopeVerdict: 'اسکوپ و سیاست‌های شرطی کاملاً منطبق است ✓',
      fieldEffects: ['فیلد بهای تمام‌شده بر اساس Field-level Policy مخفی است.'],
    });
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header bar */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <ShieldCheck className="h-6 w-6 text-primary" />
            {t('title')}
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            {t('description')}
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs px-3 py-1.5 flex items-center gap-1.5">
            <Lock className="h-3.5 w-3.5 text-emerald-600" />
            موتور تصمیم‌گیری مرکزی فعال (Authorize Gate)
          </Badge>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              نقش‌های سازمانی فعال
              <Users className="h-4 w-4 text-primary" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold">{MOCK_ROLES.length} نقش پیش‌فرض</div>
            <p className="text-xs text-muted-foreground mt-0.5">تفکیک per-company و هلدینگ</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              کاتالوگ مجوزهای اتمی
              <Key className="h-4 w-4 text-blue-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-blue-600">۴۸ مجوز استاندارد</div>
            <p className="text-xs text-muted-foreground mt-0.5">فرمت ماژول.منبع.اکشن</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              امنیت سطح فیلد (Field Security)
              <EyeOff className="h-4 w-4 text-purple-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-purple-600">{MOCK_FIELD_POLICIES.length} فیلد حساس</div>
            <p className="text-xs text-muted-foreground mt-0.5">مخفی‌سازی بهای خرید و شبا</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              حاکمیت تصمیم‌گیری (Deny Precedence)
              <ShieldAlert className="h-4 w-4 text-amber-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-amber-600">اولویت ۱۰۰٪ با Deny</div>
            <p className="text-xs text-muted-foreground mt-0.5">استثنائات مستقیم و سقف اختیارات</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-5 max-w-3xl">
          <TabsTrigger value="roles">نقش‌های سازمانی</TabsTrigger>
          <TabsTrigger value="permissions">درخت اتمی مجوزها</TabsTrigger>
          <TabsTrigger value="policies">سیاست‌ها و فیلدها</TabsTrigger>
          <TabsTrigger value="simulator">شبیه‌ساز دسترسی (Simulator)</TabsTrigger>
          <TabsTrigger value="audit">لاگ ممیزی و Audit</TabsTrigger>
        </TabsList>

        {/* Tab 1: Roles */}
        <TabsContent value="roles" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                <span>بسته‌های نقش و تفکیک وظایف (RBAC Matrix)</span>
                <Button size="sm" variant="outline">
                  <Key className="h-4 w-4 me-1.5" />
                  ایجاد نقش سفارشی شرکت
                </Button>
              </CardTitle>
              <CardDescription className="text-xs">
                هر کاربر می‌تواند در شعب یا شرکت‌های مختلف هلدینگ نقش‌های مجزا با حوزه‌های داده (Scope) مستقل داشته باشد.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>کد سیستمی</TableHead>
                    <TableHead>عنوان نقش سازمانی</TableHead>
                    <TableHead>نوع نقش</TableHead>
                    <TableHead>تعداد کاربران دارای نقش</TableHead>
                    <TableHead>تعداد مجوزهای اعطا شده</TableHead>
                    <TableHead className="text-end">عملیات</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {MOCK_ROLES.map((role) => (
                    <TableRow key={role.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono text-xs font-semibold">{role.code}</TableCell>
                      <TableCell className="font-medium text-xs">{role.title_fa}</TableCell>
                      <TableCell>
                        <Badge variant="outline" className={role.is_system ? 'bg-blue-50 text-blue-700 border-blue-200 text-xs' : 'text-xs'}>
                          {role.is_system ? 'الگوی سیستمی' : 'سفارشی شرکت'}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs font-mono">{role.users_count} کاربر</TableCell>
                      <TableCell className="text-xs font-mono">{role.permissions_count} مجوز</TableCell>
                      <TableCell className="text-end">
                        <Button size="sm" variant="ghost" className="h-8 text-xs text-primary">
                          ویرایش ماتریکس و Scope
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 2: Permissions Tree */}
        <TabsContent value="permissions" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                <span>کاتالوگ اتمی مجوزها (Atomic Permission Catalog)</span>
                <div className="flex items-center gap-2">
                  <Input placeholder="جستجوی کد یا ماژول..." className="h-8 w-48 text-xs" />
                </div>
              </CardTitle>
              <CardDescription className="text-xs">
                ساختار درختی استاندارد: ماژول → منبع (Resource) → اکشن (Action) با ارزیابی سطح ریسک و الزامات MFA.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>کد دسترسی (Permission Code)</TableHead>
                    <TableHead>ماژول / منبع</TableHead>
                    <TableHead>عنوان فارسی کنش</TableHead>
                    <TableHead>سطح ریسک امنیتی</TableHead>
                    <TableHead>الزامات تأیید (Obligations)</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {MOCK_PERMISSIONS.map((perm) => (
                    <TableRow key={perm.code} className="hover:bg-muted/20">
                      <TableCell className="font-mono text-xs font-bold text-primary">{perm.code}</TableCell>
                      <TableCell className="text-xs font-mono">
                        {perm.module} / {perm.resource}
                      </TableCell>
                      <TableCell className="text-xs font-medium">{perm.title_fa}</TableCell>
                      <TableCell>
                        <Badge
                          variant="outline"
                          className={
                            perm.risk_level === 'critical'
                              ? 'bg-rose-50 text-rose-700 border-rose-200 text-xs'
                              : perm.risk_level === 'high'
                              ? 'bg-amber-50 text-amber-700 border-amber-200 text-xs'
                              : 'bg-emerald-50 text-emerald-700 border-emerald-200 text-xs'
                          }
                        >
                          {perm.risk_level.toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs space-x-1 space-x-reverse">
                        {perm.requires_mfa && (
                          <Badge variant="outline" className="bg-purple-50 text-purple-700 border-purple-200 text-[10px]">
                            رمز یکبار مصرف (MFA)
                          </Badge>
                        )}
                        {perm.requires_reason && (
                          <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 text-[10px]">
                            ثبت دلیل الزامی
                          </Badge>
                        )}
                        {!perm.requires_mfa && !perm.requires_reason && (
                          <span className="text-muted-foreground text-xs">-</span>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 3: Policies & Fields */}
        <TabsContent value="policies" className="mt-4 space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            {/* Field-level security */}
            <Card className="shadow-sm">
              <CardHeader className="pb-3">
                <CardTitle className="text-base flex items-center gap-2">
                  <EyeOff className="h-5 w-5 text-purple-600" />
                  قوانین امنیت سطح فیلد (Field-Level Security)
                </CardTitle>
                <CardDescription className="text-xs">
                  حذف یا ماسک‌سازی فیلدهای حساس (بهای خرید، شماره شبا، حقوق پایه) مستقیماً در پاسخ API سرور.
                </CardDescription>
              </CardHeader>
              <CardContent className="p-0 overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-muted/40 text-xs">
                      <TableHead>منبع داده</TableHead>
                      <TableHead>فیلد حساس</TableHead>
                      <TableHead>مجوز لازم جهت رویت</TableHead>
                      <TableHead>اثر (Effect)</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody className="text-sm">
                    {MOCK_FIELD_POLICIES.map((fp) => (
                      <TableRow key={fp.resource + fp.field}>
                        <TableCell className="font-mono text-xs">{fp.resource}</TableCell>
                        <TableCell className="font-mono text-xs font-bold text-purple-700">{fp.field}</TableCell>
                        <TableCell className="font-mono text-xs">{fp.permission_needed}</TableCell>
                        <TableCell>
                          <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 text-xs">
                            {fp.effect.toUpperCase()}
                          </Badge>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>

            {/* ABAC Conditional Policies */}
            <Card className="shadow-sm">
              <CardHeader className="pb-3">
                <CardTitle className="text-base flex items-center gap-2">
                  <Sliders className="h-5 w-5 text-blue-600" />
                  سیاست‌های شرطی پویا (ABAC Dynamic Policies)
                </CardTitle>
                <CardDescription className="text-xs">
                  اعمال شرط روی صفات رکورد نظیر سقف مبلغ فاکتور، وضعیت سند و اتصال خودکار به گردش کار.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3 p-4">
                <div className="p-3 border rounded-lg bg-muted/20 space-y-1">
                  <div className="flex justify-between items-center text-xs">
                    <span className="font-bold">سقف تأیید تخفیف کارشناس فروش</span>
                    <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-[10px]">
                      فعال · اولویت ۱۰
                    </Badge>
                  </div>
                  <p className="font-mono text-xs text-muted-foreground">amount &lt;= 10,000,000 IRR &rarr; ALLOW</p>
                  <p className="text-[11px] text-muted-foreground">تخفیف‌های بالای ۱۰ میلیون ریال مستلزم ارجاع به کارتابل مدیر فروش است.</p>
                </div>

                <div className="p-3 border rounded-lg bg-muted/20 space-y-1">
                  <div className="flex justify-between items-center text-xs">
                    <span className="font-bold">تأیید فاکتورهای بالای ۵۰ میلیون ریال</span>
                    <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-[10px]">
                      فعال · اولویت ۲۰
                    </Badge>
                  </div>
                  <p className="font-mono text-xs text-muted-foreground">amount &gt; 50,000,000 IRR &rarr; ROUTE_WORKFLOW</p>
                  <p className="text-[11px] text-muted-foreground">فاکتورهای حجیم بدون تاییدیه چندمرحله‌ای مالی و بازرگانی صادر نخواهند شد.</p>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* Tab 4: Simulator */}
        <TabsContent value="simulator" className="mt-4 space-y-4">
          <Card className="shadow-sm border-primary/20">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center gap-2">
                <PlayCircle className="h-5 w-5 text-primary" />
                شبیه‌ساز و عیب‌یاب خط‌مشی‌های دسترسی (Permission Simulator)
              </CardTitle>
              <CardDescription className="text-xs">
                آزمایش فوری اینکه چرا یک کاربر می‌تواند یا نمی‌تواند رکوردی خاص را ببیند، تأیید کند یا خروجی اکسل بگیرد.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5 p-4">
              <div className="grid gap-4 sm:grid-cols-3">
                <div className="space-y-1.5">
                  <Label className="text-xs">کاربر مورد آزمون</Label>
                  <select
                    className="w-full text-xs p-2 rounded border bg-background"
                    value={simUser}
                    onChange={(e) => setSimUser(e.target.value)}
                  >
                    <option value="ali_rezaei (کارشناس فروش)">علی رضایی (کارشناس فروش - sales_agent)</option>
                    <option value="maryam_moradi (مدیر فروش)">مریم مرادی (مدیر فروش - sales_manager)</option>
                    <option value="reza_akbari (انباردار)">رضا اکبری (انباردار - warehouse_operator)</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <Label className="text-xs">شعبه سازمانی کاربر</Label>
                  <select
                    className="w-full text-xs p-2 rounded border bg-background"
                    value={simBranch}
                    onChange={(e) => setSimBranch(e.target.value)}
                  >
                    <option value="شعبه مرکزی تهران">شعبه مرکزی تهران</option>
                    <option value="شعبه اصفهان">شعبه اصفهان</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <Label className="text-xs">مجوز مورد استعلام (Action)</Label>
                  <select
                    className="w-full text-xs p-2 rounded border bg-background font-mono"
                    value={simPermission}
                    onChange={(e) => setSimPermission(e.target.value)}
                  >
                    <option value="sales.invoice.approve">sales.invoice.approve (تأیید فاکتور)</option>
                    <option value="sales.invoice.export">sales.invoice.export (خروجی اکسل)</option>
                    <option value="sales.cost.view">sales.cost.view (مشاهده قیمت خرید)</option>
                  </select>
                </div>
              </div>

              <div className="p-4 bg-muted/20 border rounded-lg grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label className="text-xs">شعبه رکورد فاکتور (Record Branch)</Label>
                  <select
                    className="w-full text-xs p-2 rounded border bg-background"
                    value={simRecordBranch}
                    onChange={(e) => setSimRecordBranch(e.target.value)}
                  >
                    <option value="شعبه مرکزی تهران">شعبه مرکزی تهران</option>
                    <option value="شعبه اصفهان">شعبه اصفهان</option>
                  </select>
                </div>

                <div className="space-y-1.5">
                  <Label className="text-xs">مبلغ فاکتور مورد بررسی (ریال)</Label>
                  <Input
                    className="text-xs font-mono h-9"
                    value={simAmount}
                    onChange={(e) => setSimAmount(e.target.value)}
                  />
                </div>
              </div>

              <div className="flex justify-end">
                <Button size="sm" onClick={runSimulation} className="gap-2">
                  <PlayCircle className="h-4 w-4" />
                  اجرای شبیه‌سازی و محاسبه زنجیره تصمیم
                </Button>
              </div>

              {/* Simulation Result */}
              {simResult && (
                <div
                  className={`p-4 rounded-lg border space-y-3 ${
                    simResult.decision === 'allow'
                      ? 'bg-emerald-50/70 border-emerald-300'
                      : simResult.decision === 'allow_with_approval'
                      ? 'bg-amber-50/70 border-amber-300'
                      : 'bg-rose-50/70 border-rose-300'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      {simResult.decision === 'allow' ? (
                        <CheckCircle2 className="h-5 w-5 text-emerald-600" />
                      ) : simResult.decision === 'allow_with_approval' ? (
                        <AlertTriangle className="h-5 w-5 text-amber-600" />
                      ) : (
                        <XCircle className="h-5 w-5 text-rose-600" />
                      )}
                      <span className="font-bold text-sm">
                        نتیجه ارزیابی امنیتی:{' '}
                        {simResult.decision === 'allow'
                          ? 'مجاز (ALLOW)'
                          : simResult.decision === 'allow_with_approval'
                          ? 'مجاز مشروط به گردش‌کار (ALLOW_WITH_APPROVAL)'
                          : 'مسدود (DENY)'}
                      </span>
                    </div>

                    <Badge variant="outline" className="font-mono text-xs">
                      منبع نقش: {simResult.sourceRole}
                    </Badge>
                  </div>

                  <p className="text-xs text-foreground/80 leading-relaxed font-medium">
                    {simResult.reason}
                  </p>

                  <div className="grid gap-2 sm:grid-cols-2 pt-2 border-t text-xs">
                    <div>
                      <span className="text-muted-foreground">ارزیابی حوزه داده (Scope): </span>
                      <span className="font-semibold">{simResult.scopeVerdict}</span>
                    </div>
                    <div>
                      <span className="text-muted-foreground">سیاست فعال: </span>
                      <span className="font-semibold">{simResult.blockedPolicy || 'هیچ سیاستی مانع نشد.'}</span>
                    </div>
                  </div>

                  {simResult.fieldEffects.length > 0 && (
                    <div className="pt-2 border-t text-xs">
                      <span className="text-muted-foreground font-semibold">اثرات سطح فیلد (Field Policies):</span>
                      <ul className="list-disc list-inside mt-1 space-y-0.5 text-muted-foreground">
                        {simResult.fieldEffects.map((fe, idx) => (
                          <li key={idx}>{fe}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 5: Audit Logs */}
        <TabsContent value="audit" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                <span>دفتر ثبت وقایع و تلاش‌های دسترسی (Access Audit Ledger)</span>
                <Button size="sm" variant="outline">
                  <FileSpreadsheet className="h-4 w-4 me-1.5" />
                  خروجی امن ممیزی
                </Button>
              </CardTitle>
              <CardDescription className="text-xs">
                ثبت غیرقابل تغییر (Append-Only) کلیه تصمیمات دسترسی، درخواست‌های مسدودشده، و عملیات حساس مالی و خروجی اکسل.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>زمان</TableHead>
                    <TableHead>کاربر / شناسه</TableHead>
                    <TableHead>کنش مورد درخواست</TableHead>
                    <TableHead>تصمیم</TableHead>
                    <TableHead>علت و مستند تصمیم</TableHead>
                    <TableHead>آدرس IP</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-xs">
                  <TableRow>
                    <TableCell className="font-mono text-muted-foreground">۱۴۰۵/۰۲/۲۶ ۱۴:۲۲</TableCell>
                    <TableCell className="font-medium">علی رضایی (sales_agent)</TableCell>
                    <TableCell className="font-mono font-bold">sales.invoice.export</TableCell>
                    <TableCell>
                      <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 text-[10px]">
                        DENY
                      </Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">فاقد مجوز خروجی اکسل در نقش کارشناس فروش</TableCell>
                    <TableCell className="font-mono text-muted-foreground">192.168.1.45</TableCell>
                  </TableRow>

                  <TableRow>
                    <TableCell className="font-mono text-muted-foreground">۱۴۰۵/۰۲/۲۶ ۱۳:۱۰</TableCell>
                    <TableCell className="font-medium">مریم مرادی (sales_manager)</TableCell>
                    <TableCell className="font-mono font-bold">sales.invoice.approve</TableCell>
                    <TableCell>
                      <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-[10px]">
                        ALLOW
                      </Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">تأیید فاکتور فاقد تخفیف مازاد</TableCell>
                    <TableCell className="font-mono text-muted-foreground">192.168.1.12</TableCell>
                  </TableRow>

                  <TableRow>
                    <TableCell className="font-mono text-muted-foreground">۱۴۰۵/۰۲/۲۶ ۱۱:۰۵</TableCell>
                    <TableCell className="font-medium">رضا اکبری (warehouse_operator)</TableCell>
                    <TableCell className="font-mono font-bold">fin.gl.view</TableCell>
                    <TableCell>
                      <Badge variant="outline" className="bg-rose-50 text-rose-700 border-rose-200 text-[10px]">
                        DENY
                      </Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground">عدم تطابق ماژول با مأموریت انبارداری</TableCell>
                    <TableCell className="font-mono text-muted-foreground">192.168.2.80</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

export function AuthzRolesPage() {
  return <AuthzOverviewPage />;
}

export function AuthzPermissionsPage() {
  return <AuthzOverviewPage />;
}

export function AuthzPoliciesPage() {
  return <AuthzOverviewPage />;
}

export function AuthzSimulatorPage() {
  return <AuthzOverviewPage />;
}

export function AuthzAuditLogsPage() {
  return <AuthzOverviewPage />;
}
