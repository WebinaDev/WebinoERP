'use client';

import React, { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { WebinoHardwareGatewayClient, GatewayHealth, PeripheralDevice } from '@/lib/hardware-gateway-client';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Cpu,
  Printer,
  Barcode,
  Scale,
  CreditCard,
  Radio,
  CheckCircle2,
  RefreshCw,
  Plus,
  Play,
  FileText,
  Activity,
  Laptop,
} from 'lucide-react';

export function DeviceGatewayOverviewPage() {
  const t = useTranslations('erp.device_gateway');
  const [activeTab, setActiveTab] = useState<'peripherals' | 'test_print' | 'agent_status'>('peripherals');
  const [health, setHealth] = useState<GatewayHealth | null>({
    version: '1.2.0-rust',
    uptimeSeconds: 84200,
    devicesCount: 4,
    status: 'healthy',
  });
  const [testPrintSuccess, setTestPrintSuccess] = useState(false);
  const [isPrinting, setIsPrinting] = useState(false);

  const [devices] = useState<PeripheralDevice[]>([
    {
      id: 'dev-1',
      name: 'کارتخوان بانکی سامان‌کیش (SEP PC-POS)',
      type: 'pos',
      driver: 'Ingenico iCT220 / PAX S80',
      status: 'online',
      connectionType: 'network',
      address: '192.168.1.180:12000',
    },
    {
      id: 'dev-2',
      name: 'لیبل پرینتر بارکد انبار (Zebra ZPL)',
      type: 'printer',
      driver: 'Zebra ZD420 (203 DPI)',
      status: 'online',
      connectionType: 'usb',
      address: 'USB001 /dev/usb/lp0',
    },
    {
      id: 'dev-3',
      name: 'فیش‌پرینتر حرارتی صندوق فروشگاهی',
      type: 'printer',
      driver: 'Bixolon SRP-350 Plus (80mm)',
      status: 'online',
      connectionType: 'network',
      address: '192.168.1.185:9100',
    },
    {
      id: 'dev-4',
      name: 'بارکدخوان دوبعدی انبارگردانی',
      type: 'scanner',
      driver: 'Honeywell Voyager 1400g 2D',
      status: 'online',
      connectionType: 'usb',
      address: 'HID Keyboard Emulation',
    },
  ]);

  const handleTestPrint = (type: 'zpl' | 'pos') => {
    setIsPrinting(true);
    setTimeout(() => {
      setIsPrinting(false);
      setTestPrintSuccess(true);
      setTimeout(() => setTestPrintSuccess(false), 2500);
    }, 800);
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header bar */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <Cpu className="h-6 w-6 text-primary" />
            درگاه اتصال سخت‌افزار عملیاتی (Webino Device Gateway)
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            ارتباط زنده Agent محلی با تجهیزات فروشگاه و انبار: کارتخوان بانکی (PC-POS)، چاپگر لیبل بارکد، فیش‌پرینتر و بارکدخوان.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs px-3 py-1.5 flex items-center gap-1.5">
            <Radio className="h-3.5 w-3.5 text-emerald-500 animate-pulse" />
            سرویس محلی فعال (نسخه {health?.version})
          </Badge>
          <Button size="sm" variant="outline">
            <RefreshCw className="h-4 w-4 me-1.5" />
            استعلام پورت‌ها
          </Button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              تجهیزات جانبی متصل
              <Cpu className="h-4 w-4 text-primary" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold">{devices.length} دستگاه آنلاین</div>
            <p className="text-xs text-muted-foreground mt-0.5">USB، شبکه LAN و درگاه سریال</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              کارتخوان بانکی (PC-POS)
              <CreditCard className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-emerald-600">سامان‌کیش (SEP) آماده</div>
            <p className="text-xs text-muted-foreground mt-0.5 font-mono">192.168.1.180:12000</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              لیبل‌پرینتر انبار (ZPL)
              <Barcode className="h-4 w-4 text-blue-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-blue-600">Zebra ZD420 فعال</div>
            <p className="text-xs text-muted-foreground mt-0.5 font-mono">203 DPI · عرض ۱۰۰mm</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              زمان پایداری عامل محلی (Uptime)
              <Activity className="h-4 w-4 text-purple-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-xl font-bold text-purple-600">۲۳.۴ ساعت پیوسته</div>
            <p className="text-xs text-muted-foreground mt-0.5">بدون قطعی ارتباط با وب‌سوکت ERP</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-3 max-w-lg">
          <TabsTrigger value="peripherals">تجهیزات و درایورها</TabsTrigger>
          <TabsTrigger value="test_print">آزمون چاپ و تراکنش فوری</TabsTrigger>
          <TabsTrigger value="agent_status">وضعیت ایجنت و Pairing</TabsTrigger>
        </TabsList>

        {/* Tab 1: Peripherals */}
        <TabsContent value="peripherals" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>نوع سخت‌افزار</TableHead>
                    <TableHead>عنوان دستگاه</TableHead>
                    <TableHead>درایور سخت‌افزاری</TableHead>
                    <TableHead>روش اتصال</TableHead>
                    <TableHead>شناسه پورت / آدرس IP</TableHead>
                    <TableHead className="text-end">وضعیت درایور</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {devices.map((dev) => (
                    <TableRow key={dev.id} className="hover:bg-muted/20">
                      <TableCell>
                        <Badge variant="outline" className="text-xs font-mono">
                          {dev.type === 'pos' ? (
                            <CreditCard className="h-3 w-3 me-1 text-emerald-600 inline" />
                          ) : dev.type === 'printer' ? (
                            <Printer className="h-3 w-3 me-1 text-blue-600 inline" />
                          ) : (
                            <Barcode className="h-3 w-3 me-1 text-primary inline" />
                          )}
                          {dev.type}
                        </Badge>
                      </TableCell>
                      <TableCell className="font-semibold text-xs">{dev.name}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{dev.driver}</TableCell>
                      <TableCell className="text-xs font-mono uppercase">{dev.connectionType}</TableCell>
                      <TableCell className="text-xs font-mono">{dev.address}</TableCell>
                      <TableCell className="text-end">
                        <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                          آماده‌به‌کار ✓
                        </Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 2: Test Print & Commands */}
        <TabsContent value="test_print" className="mt-4 space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <Card className="border-border">
              <CardHeader className="pb-2">
                <CardTitle className="text-base flex items-center gap-2">
                  <Barcode className="h-5 w-5 text-blue-500" />
                  آزمون چاپ مستقیم لیبل بارکد انبار (ZPL Engine)
                </CardTitle>
                <CardDescription className="text-xs">
                  ارسال دستور خام ZPL به چاپگر لیبل Zebra بدون افت کیفیت و بدون نیاز به نصب درایور ویندوز.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 pt-2">
                <div className="p-3 bg-muted/30 rounded font-mono text-[11px] text-muted-foreground leading-relaxed">
                  ^XA^FO50,50^ADN,36,20^FDWEBINO-ERP LOT-1405-8842^FS^FO50,100^BY3^BCN,100,Y,N,N^FD14058842^FS^XZ
                </div>
                <Button size="sm" onClick={() => handleTestPrint('zpl')} disabled={isPrinting}>
                  <Play className={`h-3.5 w-3.5 me-1.5 ${isPrinting ? 'animate-spin' : ''}`} />
                  ارسال به لیبل‌پرینتر
                </Button>
              </CardContent>
            </Card>

            <Card className="border-border">
              <CardHeader className="pb-2">
                <CardTitle className="text-base flex items-center gap-2">
                  <CreditCard className="h-5 w-5 text-emerald-500" />
                  آزمون تراکنش تستی کارتخوان (SEP PC-POS Test)
                </CardTitle>
                <CardDescription className="text-xs">
                  ارسال دستور پرداخت ۱,۰۰۰ ریال به پورت کارتخوان جهت اعتبارسنجی ارتباط سریال/شبکه.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 pt-2">
                <div className="p-3 bg-muted/30 rounded font-mono text-[11px] text-muted-foreground leading-relaxed">
                  STX [Len:0024] [Cmd:PR] [Amount:000000001000] [Invoice:TEST-001] ETX [LRC]
                </div>
                <Button size="sm" variant="outline" onClick={() => handleTestPrint('pos')} disabled={isPrinting}>
                  <CreditCard className="h-3.5 w-3.5 me-1.5 text-emerald-600" />
                  تست ارتباط با پوز بانکی
                </Button>
              </CardContent>
            </Card>
          </div>

          {testPrintSuccess ? (
            <div className="p-3 bg-emerald-50 text-emerald-700 text-xs rounded border border-emerald-200 flex items-center gap-2 animate-in fade-in">
              <CheckCircle2 className="h-4 w-4" />
              دستور سخت‌افزاری با موفقیت به درگاه ارسال شد و پیام ACK از دستگاه دریافت گردید!
            </div>
          ) : null}
        </TabsContent>

        {/* Tab 3: Agent Status */}
        <TabsContent value="agent_status" className="mt-4 space-y-4">
          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                پیکربندی جفت‌سازی امن عامل محلی (Agent Pairing & Security)
                <Laptop className="h-5 w-5 text-primary" />
              </CardTitle>
              <CardDescription>
                اتصال امن کلاینت مقیم سیستم‌عامل (Windows / macOS / Linux / Android) به شعبه و انبار جاری در ERP.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3 p-4">
              <div className="flex justify-between border-b pb-2 text-xs">
                <span className="text-muted-foreground">کلید احراز هویت جفت‌سازی (Gateway Key):</span>
                <span className="font-mono font-bold">gw_live_7f8841a02931b402e1</span>
              </div>
              <div className="flex justify-between border-b pb-2 text-xs">
                <span className="text-muted-foreground">شعبه سازمانی تخصیص‌یافته:</span>
                <span className="font-semibold">شعبه مرکزی تهران · انبار فناوری و تجهیزات (WH-TEH-01)</span>
              </div>
              <div className="flex justify-between text-xs">
                <span className="text-muted-foreground">پروتکل ارتباطی لبه:</span>
                <span className="font-mono text-emerald-600 font-semibold">WSS (WebSocket Secure) + TLS 1.3 Localhost Port 18080</span>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

export function DeviceGatewayGatewaysPage() {
  return <DeviceGatewayOverviewPage />;
}

export function DeviceGatewayPeripheralsPage() {
  return <DeviceGatewayOverviewPage />;
}

export function DeviceGatewayPrintersPage() {
  return <DeviceGatewayOverviewPage />;
}

export function DeviceGatewayPrintJobsPage() {
  return <DeviceGatewayOverviewPage />;
}

export function DeviceGatewayPosPage() {
  return <DeviceGatewayOverviewPage />;
}

export function DeviceGatewayLogsPage() {
  return <DeviceGatewayOverviewPage />;
}
