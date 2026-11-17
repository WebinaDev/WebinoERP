'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  GitPullRequest,
  CheckCircle2,
  XCircle,
  Clock,
  UserCheck,
  ShieldCheck,
  Plus,
  ArrowRight,
  FileCheck,
  Layers,
  AlertTriangle,
  Send,
  Eye,
} from 'lucide-react';

export function WorkflowOverviewPage() {
  const [activeTab, setActiveTab] = useState<'inbox' | 'instances' | 'definitions' | 'delegations'>('inbox');

  const [inboxTasks, setInboxTasks] = useState([
    {
      id: 1,
      title: 'تأیید پیش‌فاکتور فروش با تخفیف ۱۸٪ (بیش از سقف مجاز ۱۵٪)',
      module: 'فروش (Sales)',
      entity: 'پیش‌فاکتور SQ-20261116-4192',
      requester: 'کارشناس فروش: حمید رضایی',
      amount: 203500000,
      sla_due: 'امروز ۱۸:۰۰ (۴ ساعت باقیمانده)',
      priority: 'high',
      step: 'گام ۲: تأیید مدیر مالی هلدینگ',
    },
    {
      id: 2,
      title: 'تأیید حقوقی قرارداد توسعه و استقرار پلتفرم سازمانی',
      module: 'اسناد و قراردادها (Docs)',
      entity: 'قرارداد CTR-1405-2084',
      requester: 'واحد بازرگانی: سارا ناصری',
      amount: 850000000,
      sla_due: 'فردا ۱۰:۰۰',
      priority: 'critical',
      step: 'گام ۱: تایید کمیته عالی معاملات و مدیرعامل',
    },
    {
      id: 3,
      title: 'تأیید دستور خرید قطعات یدکی ضروری سرور و UPS',
      module: 'تدارکات (Procurement)',
      entity: 'درخواست خرید PR-20261114-11',
      requester: 'سرپرست IT: مهندس علیزاده',
      amount: 42000000,
      sla_due: 'رعایت شده (۲۴ ساعت مهلت)',
      priority: 'normal',
      step: 'گام ۲: تایید مدیر تدارکات و بودجه',
    },
  ]);

  const [instances, setInstances] = useState([
    {
      id: 101,
      title: 'پیش‌فاکتور رسمی پتروشیمی زاگرس',
      definition: 'تأیید تخفیف‌های ویژه سازمانی (>۱۵٪)',
      module: 'Sales',
      initiated_by: 'حمید رضایی',
      status: 'pending',
      current_step: 'مدیر مالی (گام ۲ از ۲)',
      started_at: '۱۴۰۵/۰۸/۲۶ ۱۰:۱۵',
    },
    {
      id: 102,
      title: 'قرارداد پشتیبانی سالانه صنایع غذایی مینو',
      definition: 'تأیید متمم و الحاقیه قراردادهای بزرگ',
      module: 'Docs',
      initiated_by: 'سارا ناصری',
      status: 'approved',
      current_step: 'تأیید نهایی شد ✓',
      started_at: '۱۴۰۵/۰۸/۲۴ ۰۹:۰۰',
    },
    {
      id: 103,
      title: 'درخواست خروج اضطراری قطعات از انبار مرکزی',
      definition: 'خروج اموال و قطعات با ارزش بالا',
      module: 'Scm',
      initiated_by: 'آرش طاهری',
      status: 'approved',
      current_step: 'تکمیل شد ✓',
      started_at: '۱۴۰۵/۰۸/۲۲ ۱۱:۳۰',
    },
  ]);

  const [definitions, setDefinitions] = useState([
    {
      id: 1,
      name: 'گردش‌کار تأیید پیش‌فاکتور با تخفیف بالای سقف',
      module: 'Sales',
      entity_type: 'SalesQuote',
      steps_count: '۲ مرحله ترتیبی (مدیر فروش → مدیر مالی)',
      sla_hours: '۲۴ ساعت',
      is_active: true,
    },
    {
      id: 2,
      name: 'گردش‌کار تأیید داخلی قرارداد قبل از ارسال به کارفرما',
      module: 'Docs',
      entity_type: 'DocsContract',
      steps_count: '۲ مرحله موازی (مشاور حقوقی + مدیر مالی)',
      sla_hours: '۴۸ ساعت',
      is_active: true,
    },
    {
      id: 3,
      name: 'گردش‌کار تأیید مرخصی و مأموریت پرسنلی',
      module: 'Hrm',
      entity_type: 'HrmLeaveRequest',
      steps_count: '۱ مرحله پویا (مدیر مستقیم پرسنل)',
      sla_hours: '۱۲ ساعت',
      is_active: true,
    },
  ]);

  const formatCurrency = (val: number) => {
    return val.toLocaleString('fa-IR') + ' ریال';
  };

  const handleApprove = (taskId: number) => {
    setInboxTasks((prev) => prev.filter((t) => t.id !== taskId));
  };

  const handleReject = (taskId: number) => {
    setInboxTasks((prev) => prev.filter((t) => t.id !== taskId));
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header bar */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <GitPullRequest className="h-6 w-6 text-primary" />
            موتور مرکزی تأییدها و گردش‌کار سازمانی (Enterprise Workflow Engine)
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            صندوق واحد کارتابل تاییدات (Universal Approval Inbox)، گردش‌کارهای چندمرحله‌ای، تشدید SLA و قفل اسناد.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs px-3 py-1.5 flex items-center gap-1.5">
            <ShieldCheck className="h-3.5 w-3.5" />
            پالیسی فعال: مسدودسازی دور زدن اختیارات مالی
          </Badge>
          <Button size="sm">
            <Plus className="h-4 w-4 me-1.5" />
            طراحی گردش‌کار جدید
          </Button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              کارهای در انتظار اقدام من (Inbox)
              <GitPullRequest className="h-4 w-4 text-primary" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-primary">{inboxTasks.length} وظیفه اقدام</div>
            <p className="text-xs text-muted-foreground mt-0.5">۱ مورد اضطراری با اولویت بالا</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              میانگین زمان تصمیم‌گیری
              <Clock className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-emerald-600">۳.۲ ساعت</div>
            <p className="text-xs text-muted-foreground mt-0.5">پایین‌تر از سقف تعهد ۲۴ ساعته</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              درصد تایید در برابر رد
              <CheckCircle2 className="h-4 w-4 text-blue-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-blue-600">۸۸٪ تایید / ۱۲٪ رد</div>
            <p className="text-xs text-muted-foreground mt-0.5">اصلاح موارد قبل از ورود به چرخه مالی</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              تعریف گردش‌کارهای فعال
              <Layers className="h-4 w-4 text-purple-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-purple-600">۱۸ گردش‌کار</div>
            <p className="text-xs text-muted-foreground mt-0.5">پوشش فروش، قرارداد، مالی و مرخصی</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-4 max-w-xl">
          <TabsTrigger value="inbox">کارتابل تاییدات من ({inboxTasks.length})</TabsTrigger>
          <TabsTrigger value="instances">درخواست‌های جاری سیستم</TabsTrigger>
          <TabsTrigger value="definitions">تعاریف و مسیرهای تصویب</TabsTrigger>
          <TabsTrigger value="delegations">تفویض اختیار و جانشینی</TabsTrigger>
        </TabsList>

        {/* Tab 1: Inbox */}
        <TabsContent value="inbox" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                کارتابل واحد تصمیم‌گیری و تایید اسناد (Universal Approval Inbox)
                <GitPullRequest className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                بررسی و تصمیم‌گیری درباره اسناد مالی، قراردادها و تخفیف‌های ارجاع‌شده به شما بدون نیاز به تغییر ماژول.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>عنوان درخواست / سند</TableHead>
                    <TableHead>ماژول و سند مرتبط</TableHead>
                    <TableHead>درخواست‌دهنده</TableHead>
                    <TableHead>مبلغ سند</TableHead>
                    <TableHead>گام و جایگاه جاری</TableHead>
                    <TableHead>مهلت SLA</TableHead>
                    <TableHead className="text-end">تصمیم‌گیری فوری</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {inboxTasks.map((task) => (
                    <TableRow key={task.id} className="hover:bg-muted/20">
                      <TableCell>
                        <div className="font-semibold text-xs">{task.title}</div>
                        <div className="text-[11px] text-muted-foreground">{task.step}</div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className="text-[11px] font-mono">
                          {task.entity}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs">{task.requester}</TableCell>
                      <TableCell className="font-mono text-xs font-bold text-primary">
                        {formatCurrency(task.amount)}
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant={task.priority === 'critical' ? 'destructive' : task.priority === 'high' ? 'outline' : 'secondary'}
                          className="text-[11px]"
                        >
                          {task.priority}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs font-mono text-amber-600">{task.sla_due}</TableCell>
                      <TableCell className="text-end">
                        <div className="flex items-center justify-end gap-1.5">
                          <Button size="sm" onClick={() => handleApprove(task.id)} className="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white">
                            <CheckCircle2 className="h-3.5 w-3.5 me-1" />
                            تأیید
                          </Button>
                          <Button size="sm" variant="outline" onClick={() => handleReject(task.id)} className="h-8 text-xs text-destructive border-destructive hover:bg-destructive/10">
                            <XCircle className="h-3.5 w-3.5 me-1" />
                            رد
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                  {inboxTasks.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-8 text-muted-foreground text-xs">
                        کارتابل شما خالی است! هیچ درخواستی در انتظار تأیید شما نیست.
                      </TableCell>
                    </TableRow>
                  ) : null}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 2: Instances */}
        <TabsContent value="instances" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                نمونه‌های در حال اجرای گردش‌کار (Active Workflow Instances)
                <Layers className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>رهگیری موقعیت اسناد ارسالی در سلسله‌مراتب سازمانی و شناسایی گلوگاه‌ها.</CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>عنوان فرآیند</TableHead>
                    <TableHead>تعریف گردش‌کار</TableHead>
                    <TableHead>ماژول</TableHead>
                    <TableHead>آغازکننده</TableHead>
                    <TableHead>گام و تصمیم‌گیرنده جاری</TableHead>
                    <TableHead>وضعیت کلی</TableHead>
                    <TableHead className="text-end">زمان آغاز</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {instances.map((inst) => (
                    <TableRow key={inst.id} className="hover:bg-muted/20">
                      <TableCell className="font-semibold text-xs">{inst.title}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{inst.definition}</TableCell>
                      <TableCell>
                        <Badge variant="outline" className="text-xs font-mono">{inst.module}</Badge>
                      </TableCell>
                      <TableCell className="text-xs">{inst.initiated_by}</TableCell>
                      <TableCell className="text-xs font-medium text-amber-600">{inst.current_step}</TableCell>
                      <TableCell>
                        {inst.status === 'approved' ? (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                            تأیید قطعی شد ✓
                          </Badge>
                        ) : (
                          <Badge variant="secondary" className="text-xs">در جریان تصویب</Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-end text-xs font-mono text-muted-foreground">{inst.started_at}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 3: Definitions */}
        <TabsContent value="definitions" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">تعاریف فرآیندها و الگوهای تصویب (Process Definitions)</CardTitle>
              <CardDescription>پیکربندی گام‌های ترتیبی/موازی، شروط مالی و سقف‌های اختیارات بدون کدنویسی.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3 p-4">
              {definitions.map((def) => (
                <div key={def.id} className="rounded-lg border p-3 flex items-center justify-between hover:bg-muted/20">
                  <div>
                    <div className="flex items-center gap-2">
                      <h4 className="font-semibold text-sm">{def.name}</h4>
                      <Badge variant="outline" className="text-[10px] font-mono">{def.entity_type}</Badge>
                    </div>
                    <p className="text-xs text-muted-foreground mt-1">
                      {def.steps_count} · سقف تعهد SLA: {def.sla_hours}
                    </p>
                  </div>
                  <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                    فعال در سیستم
                  </Badge>
                </div>
              ))}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 4: Delegations */}
        <TabsContent value="delegations" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                تفویض اختیار و جانشینی در دوران مرخصی (Approval Delegations)
                <UserCheck className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                هدایت خودکار کارتابل تاییدات به جانشین رسمی در بازه‌های زمانی عدم حضور بدون توقف فرآیندها.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3 p-4">
              <div className="rounded-lg border p-3 flex items-center justify-between bg-muted/10">
                <div>
                  <h4 className="font-semibold text-sm">تفویض اختیارات مدیر مالی (دکتر رضایی → مهندس حسینی)</h4>
                  <p className="text-xs text-muted-foreground mt-0.5">بازه: ۱۴۰۵/۰۹/۰۱ تا ۱۴۰۵/۰۹/۰۸ (دوران مأموریت کاری)</p>
                </div>
                <Badge variant="outline" className="text-xs">برنامه‌ریزی‌شده</Badge>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

export function WorkflowInboxPage() {
  return <WorkflowOverviewPage />;
}

export function WorkflowDefinitionsPage() {
  return <WorkflowOverviewPage />;
}

export function WorkflowDelegationsPage() {
  return <WorkflowOverviewPage />;
}

export function WorkflowHistoryPage() {
  return <WorkflowOverviewPage />;
}
