'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Wrench,
  Cpu,
  Clock,
  MapPin,
  CheckCircle2,
  AlertCircle,
  Plus,
  ShieldCheck,
  UserCheck,
  Package,
  Calendar,
  Layers,
  FileText,
} from 'lucide-react';

export function FieldServiceOverviewPage() {
  const [activeTab, setActiveTab] = useState<'orders' | 'install_base' | 'technicians' | 'parts'>('orders');

  const [orders, setOrders] = useState([
    {
      id: 1,
      order_number: 'FSM-20261116-08',
      customer: 'شرکت پتروشیمی زاگرس',
      device: 'سرور لبه‌ای و گیت‌وی صنعتی IoT v2 (SN: SRV-1405-9921)',
      technician: 'مهندس آرش طاهری',
      priority: 'critical',
      status: 'in_progress',
      sla_due: 'امروز ۱۶:۰۰ (۲ ساعت باقیمانده)',
      coverage: 'گارانتی رسمی طلایی',
      parts_count: 1,
    },
    {
      id: 2,
      order_number: 'FSM-20261115-02',
      customer: 'صنایع غذایی مینو',
      device: 'دستگاه جت پرینتر خط بسته‌بندی B (SN: PRN-1404-1029)',
      technician: 'مهندس وحید مرادی',
      priority: 'high',
      status: 'completed',
      sla_due: 'انجام شد (رعایت SLA)',
      coverage: 'قرارداد پشتیبانی سالانه',
      parts_count: 2,
    },
    {
      id: 3,
      order_number: 'FSM-20261114-05',
      customer: 'هلدینگ دارویی برکت',
      device: 'سیستم قرائت بارکد دو‌بعدی انبار (SN: SCN-1405-4420)',
      technician: 'در انتظار اعزام',
      priority: 'medium',
      status: 'dispatched',
      sla_due: 'فردا ۱۲:۰۰',
      coverage: 'خارج از گارانتی (فاکتور مشتری)',
      parts_count: 0,
    },
  ]);

  const [installBase, setInstallBase] = useState([
    {
      id: 1,
      serial_number: 'SRV-1405-9921',
      device_model: 'سرور لبه‌ای و گیت‌وی صنعتی IoT v2',
      customer: 'شرکت پتروشیمی زاگرس',
      install_date: '۱۴۰۵/۰۱/۱۵',
      warranty_end: '۱۴۰۶/۰۱/۱۵ (۱۵۰ روز باقیمانده)',
      status: 'in_repair',
      address: 'عسلویه، فاز ۲ پتروشیمی، اتاق مانیتورینگ',
    },
    {
      id: 2,
      serial_number: 'PRN-1404-1029',
      device_model: 'دستگاه جت پرینتر صنعتی خط تولید B',
      customer: 'صنایع غذایی مینو',
      install_date: '۱۴۰۴/۰۶/۲۰',
      warranty_end: 'پایان یافته · دارای قرارداد پشتیبانی',
      status: 'active',
      address: 'تهران، کیلومتر ۱۰ جاده مخصوص، سالن تولید ۳',
    },
    {
      id: 3,
      serial_number: 'SCN-1405-4420',
      device_model: 'اسکنر دوبعدی صنعتی بارکدخوان Datalogic',
      customer: 'هلدینگ دارویی برکت',
      install_date: '۱۴۰۵/۰۳/۱۰',
      warranty_end: '۱۴۰۶/۰۳/۱۰ (۲۱۰ روز باقیمانده)',
      status: 'active',
      address: 'شهرک دارویی برکت، انبار مرکزی توزیع',
    },
  ]);

  const [technicians, setTechnicians] = useState([
    {
      id: 1,
      name: 'مهندس آرش طاهری',
      zone: 'منطقه صنعتی جنوب (عسلویه و بوشهر)',
      skills: ['سخت‌افزار سرور', 'شبکه صنعتی', 'پروتکل Modbus'],
      is_available: false,
      active_job: 'FSM-20261116-08',
      phone: '09123456789',
    },
    {
      id: 2,
      name: 'مهندس وحید مرادی',
      zone: 'منطقه صنعتی تهران و البرز',
      skills: ['اتوماسیون صنعتی', 'سنسور و ابزاردقیق', 'PLC'],
      is_available: true,
      active_job: 'آزاد جهت اعزام',
      phone: '09129876543',
    },
  ]);

  const [partsUsed, setPartsUsed] = useState([
    {
      id: 1,
      order_number: 'FSM-20261116-08',
      part_name: 'منبع تغذیه صنعتی ۲۴ ولت ریل دوبل',
      quantity: 1,
      unit_cost: 14500000,
      coverage: 'گارانتی سازنده (بدون هزینه مشتری)',
      warehouse: 'انبار قطعات یدکی خدمات پس از فروش',
    },
    {
      id: 2,
      order_number: 'FSM-20261115-02',
      part_name: 'هد نازل پیزوالکتریک جت پرینتر',
      quantity: 1,
      unit_cost: 28000000,
      coverage: 'پوشش قرارداد پشتیبانی',
      warehouse: 'انبار مرکزی غرب',
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
            <Wrench className="h-6 w-6 text-primary" />
            سامانه خدمات پس از فروش و اعزام تکنسین (Field Service Management)
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            چرخه کامل پشتیبانی میدانی: شناسنامه دستگاه‌های مشتری (Install Base)، اعزام، مانیتورینگ SLA، مصرف قطعات و تشخیص گارانتی.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs px-3 py-1.5 flex items-center gap-1.5">
            <Clock className="h-3.5 w-3.5" />
            پایش هوشمند SLA: ۹۶.۴٪ رعایت تعهد زمانی
          </Badge>
          <Button size="sm">
            <Plus className="h-4 w-4 me-1.5" />
            درخواست سرویس جدید
          </Button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              دستورکارهای میدانی فعال
              <Wrench className="h-4 w-4 text-primary" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold">۲ کار در جریان</div>
            <p className="text-xs text-muted-foreground mt-0.5">۱ مورد اضطراری با اولویت Critical</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              دستگاه‌های فعال نزد مشتریان
              <Cpu className="h-4 w-4 text-blue-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold">۱۴۲ دستگاه (Install Base)</div>
            <p className="text-xs text-muted-foreground mt-0.5">دارای سریال نامبر و سابقه گارانتی</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              تکنسین‌های میدانی آماده‌به‌کار
              <UserCheck className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-emerald-600">۸ نفر فعال</div>
            <p className="text-xs text-muted-foreground mt-0.5">پوشش مناطق تهران، عسلویه و تبریز</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              حل مشکل در مراجعه اول (FTFR)
              <CheckCircle2 className="h-4 w-4 text-purple-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-purple-600">۸۸.۵٪ نرخ موفقیت</div>
            <p className="text-xs text-muted-foreground mt-0.5">بدون نیاز به بازگشت یا قطعه ثانویه</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-4 max-w-xl">
          <TabsTrigger value="orders">دستورکارهای سرویس</TabsTrigger>
          <TabsTrigger value="install_base">پرونده دستگاه‌ها (Install Base)</TabsTrigger>
          <TabsTrigger value="technicians">تکنسین‌ها و دیسپچ</TabsTrigger>
          <TabsTrigger value="parts">قطعات یدکی مصرفی</TabsTrigger>
        </TabsList>

        {/* Tab 1: Service Orders */}
        <TabsContent value="orders" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شماره دستورکار</TableHead>
                    <TableHead>مشتری و دستگاه</TableHead>
                    <TableHead>تکنسین اعزامی</TableHead>
                    <TableHead>اولویت</TableHead>
                    <TableHead>وضعیت کار</TableHead>
                    <TableHead>مهلت SLA</TableHead>
                    <TableHead className="text-end">پوشش هزینه</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {orders.map((ord) => (
                    <TableRow key={ord.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{ord.order_number}</TableCell>
                      <TableCell>
                        <div className="font-semibold text-xs">{ord.customer}</div>
                        <div className="text-[11px] text-muted-foreground">{ord.device}</div>
                      </TableCell>
                      <TableCell className="text-xs">{ord.technician}</TableCell>
                      <TableCell>
                        <Badge
                          variant={ord.priority === 'critical' ? 'destructive' : ord.priority === 'high' ? 'outline' : 'secondary'}
                          className="text-[11px]"
                        >
                          {ord.priority}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        {ord.status === 'in_progress' ? (
                          <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 text-xs flex items-center gap-1 w-fit">
                            در دست انجام
                          </Badge>
                        ) : ord.status === 'completed' ? (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs flex items-center gap-1 w-fit">
                            تکمیل شد ✓
                          </Badge>
                        ) : (
                          <Badge variant="secondary" className="text-xs">اعزام‌شده</Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-xs font-mono">{ord.sla_due}</TableCell>
                      <TableCell className="text-end text-xs font-semibold">{ord.coverage}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 2: Install Base */}
        <TabsContent value="install_base" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                شناسنامه تجهیزات و دستگاه‌های نزد مشتری (Customer Install Base)
                <Cpu className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                رهگیری سریال نامبر، تاریخ نصب فیزیکی، آدرس محل استقرار و وضعیت پوشش گارانتی و پشتیبانی.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>سریال نامبر</TableHead>
                    <TableHead>مدل دستگاه / تجهیز</TableHead>
                    <TableHead>مشتری کارفرما</TableHead>
                    <TableHead>تاریخ نصب</TableHead>
                    <TableHead>وضعیت گارانتی</TableHead>
                    <TableHead>موقعیت فیزیکی</TableHead>
                    <TableHead className="text-end">وضعیت عملیاتی</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {installBase.map((ib) => (
                    <TableRow key={ib.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{ib.serial_number}</TableCell>
                      <TableCell className="font-semibold text-xs">{ib.device_model}</TableCell>
                      <TableCell className="text-xs">{ib.customer}</TableCell>
                      <TableCell className="text-xs font-mono">{ib.install_date}</TableCell>
                      <TableCell className="text-xs font-mono text-emerald-600">{ib.warranty_end}</TableCell>
                      <TableCell className="text-xs text-muted-foreground max-w-xs truncate" title={ib.address}>{ib.address}</TableCell>
                      <TableCell className="text-end">
                        {ib.status === 'active' ? (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                            در حال کار ✓
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 text-xs">
                            در دست تعمیر
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

        {/* Tab 3: Technicians */}
        <TabsContent value="technicians" className="mt-4 space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            {technicians.map((tech) => (
              <Card key={tech.id} className="border-border">
                <CardHeader className="pb-2">
                  <div className="flex items-center justify-between">
                    <Badge variant={tech.is_available ? 'outline' : 'secondary'} className={tech.is_available ? 'bg-emerald-50 text-emerald-700 border-emerald-200 text-xs' : 'text-xs'}>
                      {tech.is_available ? 'آماده اعزام' : 'مشغول در مأموریت'}
                    </Badge>
                    <UserCheck className="h-4 w-4 text-muted-foreground" />
                  </div>
                  <CardTitle className="text-base mt-2">{tech.name}</CardTitle>
                  <CardDescription className="text-xs flex items-center gap-1 mt-0.5">
                    <MapPin className="h-3.5 w-3.5 text-muted-foreground" />
                    {tech.zone}
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-2 text-xs">
                  <div className="flex flex-wrap gap-1 pt-1">
                    {tech.skills.map((skill) => (
                      <Badge key={skill} variant="secondary" className="text-[10px]">
                        {skill}
                      </Badge>
                    ))}
                  </div>
                  <div className="flex justify-between border-t pt-2 mt-2">
                    <span className="text-muted-foreground">دستورکار جاری:</span>
                    <span className="font-mono font-semibold">{tech.active_job}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">شماره تماس اضطراری:</span>
                    <span className="font-mono">{tech.phone}</span>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>

        {/* Tab 4: Parts */}
        <TabsContent value="parts" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                گزارش مصرف قطعات یدکی و اتصال به انبار (Spare Parts Consumption)
                <Package className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                ثبت خروج قطعات از کاردکس انبار و تفکیک هزینه قطعه تحت گارانتی یا فاکتور مشتری.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>دستورکار</TableHead>
                    <TableHead>عنوان قطعه یدکی مصرفی</TableHead>
                    <TableHead>تعداد</TableHead>
                    <TableHead>بهای تمام‌شده واحد</TableHead>
                    <TableHead>انبار تأمین‌کننده</TableHead>
                    <TableHead className="text-end">تشخیص پوشش هزینه</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {partsUsed.map((p) => (
                    <TableRow key={p.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{p.order_number}</TableCell>
                      <TableCell className="font-semibold text-xs">{p.part_name}</TableCell>
                      <TableCell className="font-mono text-xs">{p.quantity} عدد</TableCell>
                      <TableCell className="font-mono text-xs">{formatCurrency(p.unit_cost)}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{p.warehouse}</TableCell>
                      <TableCell className="text-end text-xs font-semibold text-emerald-600">{p.coverage}</TableCell>
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

export function FieldServiceInstallBasePage() {
  return <FieldServiceOverviewPage />;
}

export function FieldServiceOrdersPage() {
  return <FieldServiceOverviewPage />;
}

export function FieldServiceTechniciansPage() {
  return <FieldServiceOverviewPage />;
}

export function FieldServiceReportsPage() {
  return <FieldServiceOverviewPage />;
}
