'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Globe,
  Mail,
  ShieldCheck,
  Activity,
  Plus,
  RefreshCw,
  ExternalLink,
  CheckCircle2,
  AlertTriangle,
  Clock,
  KeyRound,
  Inbox,
  UserCheck,
} from 'lucide-react';

export interface DomainRecord {
  id: number;
  domain: string;
  crm_customer_id?: number | null;
  ssl_status: string;
  ssl_expires_at?: string | null;
  ssl_issuer?: string | null;
  last_uptime_status: 'healthy' | 'degraded' | 'down' | 'unknown';
  dns_records_count?: number;
  mail_domain?: {
    id: number;
    dns_status: string;
    mx_record: string;
    spf_record: string;
    dkim_selector: string;
    mailboxes_count?: number;
  } | null;
}

export interface DnsRecordItem {
  id: number;
  type: string;
  name: string;
  value: string;
  ttl: number;
  priority?: number;
  is_proxied: boolean;
  status: string;
}

export interface MailboxItem {
  id: number;
  email_address: string;
  display_name: string;
  quota_mb: number;
  used_mb: number;
  is_active: boolean;
}

export function PlatformDomainControlHub() {
  const [activeTab, setActiveTab] = useState<'overview' | 'email' | 'dns' | 'ssl'>('overview');
  const [selectedDomain, setSelectedDomain] = useState<string>('webinoerp.com');
  const [isCheckingDns, setIsCheckingDns] = useState(false);
  const [isPingingUptime, setIsPingingUptime] = useState(false);
  const [uptimeStatus, setUptimeStatus] = useState<{ status: string; latency: number; code: number }>({
    status: 'healthy',
    latency: 82,
    code: 200,
  });

  const [mailboxes, setMailboxes] = useState<MailboxItem[]>([
    { id: 1, email_address: 'info@webinoerp.com', display_name: 'دبیرخانه و روابط عمومی', quota_mb: 2048, used_mb: 340, is_active: true },
    { id: 2, email_address: 'sales@webinoerp.com', display_name: 'تیم فروش سازمانی', quota_mb: 5120, used_mb: 1820, is_active: true },
    { id: 3, email_address: 'support@webinoerp.com', display_name: 'پشتیبانی فنی و تیکت‌ها', quota_mb: 5120, used_mb: 2190, is_active: true },
  ]);

  const [dnsRecords, setDnsRecords] = useState<DnsRecordItem[]>([
    { id: 1, type: 'A', name: '@', value: '185.143.232.10', ttl: 3600, is_proxied: true, status: 'active' },
    { id: 2, type: 'CNAME', name: 'www', value: 'webinoerp.com', ttl: 3600, is_proxied: true, status: 'active' },
    { id: 3, type: 'MX', name: '@', value: 'mail.webinoerp.com', ttl: 3600, priority: 10, is_proxied: false, status: 'active' },
    { id: 4, type: 'TXT', name: '@', value: 'v=spf1 mx a:mail.webinoerp.com ~all', ttl: 3600, is_proxied: false, status: 'active' },
    { id: 5, type: 'TXT', name: 'default._domainkey', value: 'v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA3...', ttl: 3600, is_proxied: false, status: 'active' },
    { id: 6, type: 'TXT', name: '_dmarc', value: 'v=DMARC1; p=quarantine; rua=mailto:dmarc-reports@webinoerp.com', ttl: 3600, is_proxied: false, status: 'active' },
  ]);

  const handleCheckDns = () => {
    setIsCheckingDns(true);
    setTimeout(() => {
      setIsCheckingDns(false);
    }, 1200);
  };

  const handlePingUptime = () => {
    setIsPingingUptime(true);
    setTimeout(() => {
      setIsPingingUptime(false);
      setUptimeStatus({
        status: 'healthy',
        latency: Math.floor(Math.random() * 40) + 60,
        code: 200,
      });
    }, 800);
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header bar */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <Globe className="h-6 w-6 text-primary" />
            مرکز مدیریت جامع دامنه، ایمیل شرکتی و DNS (Platform Hub)
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            پیکربندی هویت کامل دامنه، صدور خودکار SSL، صندوق‌های پستی سازمانی، مانیتورینگ آپ‌تایم و نگاشت به هاب مشتری.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Badge variant="outline" className="text-sm px-3 py-1 bg-background">
            دامنه جاری: <strong className="ms-1 font-mono text-primary">{selectedDomain}</strong>
          </Badge>
          <Button size="sm" variant="outline" onClick={handlePingUptime} disabled={isPingingUptime}>
            <Activity className={`h-4 w-4 me-1.5 ${isPingingUptime ? 'animate-spin' : 'text-emerald-500'}`} />
            تست پایش زنده
          </Button>
        </div>
      </div>

      {/* Quick KPI stats */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              وضعیت سرور و آپ‌تایم
              <Activity className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="flex items-center gap-2">
              <span className="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse" />
              <span className="text-lg font-bold">۹۹.۹۸٪ پایدار</span>
            </div>
            <p className="text-xs text-muted-foreground mt-1 font-mono">تاخیر پاسخ: {uptimeStatus.latency}ms (کد {uptimeStatus.code})</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              صندوق‌های فعال ایمیل
              <Mail className="h-4 w-4 text-blue-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-lg font-bold">{mailboxes.length} صندوق پستی</div>
            <p className="text-xs text-muted-foreground mt-1 font-mono">۴.۳۵ گیگابایت / ۱۰ گیگابایت</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              گواهی امنیتی SSL
              <ShieldCheck className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-lg font-bold text-emerald-600 dark:text-emerald-400">فعال (Let&apos;s Encrypt)</div>
            <p className="text-xs text-muted-foreground mt-1">تمدید خودکار · ۷۴ روز باقیمانده</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm">
          <CardHeader className="p-4 pb-2">
            <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
              احراز هویت ضد اسپم DNS
              <CheckCircle2 className="h-4 w-4 text-emerald-500" />
            </CardTitle>
          </CardHeader>
          <CardContent className="p-4 pt-1">
            <div className="text-lg font-bold text-emerald-600 dark:text-emerald-400">MX + SPF + DKIM تأییدشده</div>
            <p className="text-xs text-muted-foreground mt-1 font-mono">DMARC Policy: Quarantine</p>
          </CardContent>
        </Card>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="w-full">
        <TabsList className="grid w-full grid-cols-4 max-w-xl">
          <TabsTrigger value="overview">نمای کلی دامنه</TabsTrigger>
          <TabsTrigger value="email">صندوق‌های ایمیل (Webmail)</TabsTrigger>
          <TabsTrigger value="dns">مدیریت رکوردهای DNS</TabsTrigger>
          <TabsTrigger value="ssl">امنیت و SSL</TabsTrigger>
        </TabsList>

        {/* Tab 1: Overview */}
        <TabsContent value="overview" className="mt-4 space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">مشخصات پیوند دامنه به سرویس‌های ابری</CardTitle>
              <CardDescription>اتصال دامنه به مخازن نرم‌افزاری، کانتینرهای اجرای داکر و کارپوشه مشتری در CRM.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="rounded-lg border p-3">
                  <span className="text-xs text-muted-foreground">نام دامنه اصلی</span>
                  <p className="text-sm font-semibold font-mono mt-0.5">{selectedDomain}</p>
                </div>
                <div className="rounded-lg border p-3">
                  <span className="text-xs text-muted-foreground">مشتری مرتبط در هاب سازمانی (CRM)</span>
                  <p className="text-sm font-semibold mt-0.5">شرکت نوآوران ارتباطات داده مهر (شناسه #104)</p>
                </div>
                <div className="rounded-lg border p-3">
                  <span className="text-xs text-muted-foreground">منبع/برنامه میزبان (Platform Resource)</span>
                  <p className="text-sm font-semibold mt-0.5">فروشگاه آنلاین اصلی (پورتال سازمانی v3)</p>
                </div>
                <div className="rounded-lg border p-3">
                  <span className="text-xs text-muted-foreground">وضعیت ریدایرکت خودکار و HSTS</span>
                  <p className="text-sm font-semibold text-emerald-600 mt-0.5">اجبار HTTPS فعال · HSTS فعال (یک ساله)</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Tab 2: Domain Email & Mailboxes */}
        <TabsContent value="email" className="mt-4 space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-base font-semibold">صندوق‌های پستی دامنه @{selectedDomain}</h3>
              <p className="text-xs text-muted-foreground">مدیریت اکانت‌های سازمانی، تخصیص سهمیه حجم و اتصال امن IMAP/SMTP.</p>
            </div>
            <Button size="sm">
              <Plus className="h-4 w-4 me-1.5" />
              ایجاد صندوق جدید
            </Button>
          </div>

          <div className="rounded-md border bg-card">
            <div className="grid grid-cols-12 p-3 font-semibold text-xs border-b bg-muted/40">
              <div className="col-span-4">آدرس ایمیل سازمانی</div>
              <div className="col-span-3">عنوان نمایشی / دپارتمان</div>
              <div className="col-span-3">فضای مصرفی / سهمیه</div>
              <div className="col-span-2 text-end">وضعیت</div>
            </div>
            <div className="divide-y text-sm">
              {mailboxes.map((box) => (
                <div key={box.id} className="grid grid-cols-12 p-3 items-center hover:bg-muted/20">
                  <div className="col-span-4 font-mono font-medium flex items-center gap-2">
                    <Inbox className="h-4 w-4 text-primary" />
                    {box.email_address}
                  </div>
                  <div className="col-span-3 text-muted-foreground text-xs">{box.display_name}</div>
                  <div className="col-span-3 text-xs font-mono">
                    <span className="font-semibold">{box.used_mb} MB</span> / {box.quota_mb} MB
                    <div className="w-24 h-1.5 bg-muted rounded-full mt-1 overflow-hidden">
                      <div className="bg-primary h-full rounded-full" style={{ width: `${(box.used_mb / box.quota_mb) * 100}%` }} />
                    </div>
                  </div>
                  <div className="col-span-2 text-end">
                    <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                      فعال
                    </Badge>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </TabsContent>

        {/* Tab 3: DNS Records */}
        <TabsContent value="dns" className="mt-4 space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-base font-semibold">زون و رکوردهای DNS دامنه</h3>
              <p className="text-xs text-muted-foreground">رکوردهای مسیریابی وب، میل‌سرور MX و احراز هویت SPF / DKIM / DMARC.</p>
            </div>
            <div className="flex items-center gap-2">
              <Button size="sm" variant="outline" onClick={handleCheckDns} disabled={isCheckingDns}>
                <RefreshCw className={`h-4 w-4 me-1.5 ${isCheckingDns ? 'animate-spin' : ''}`} />
                استعلام انتشار DNS
              </Button>
              <Button size="sm">
                <Plus className="h-4 w-4 me-1.5" />
                رکورد جدید
              </Button>
            </div>
          </div>

          <div className="rounded-md border bg-card">
            <div className="grid grid-cols-12 p-3 font-semibold text-xs border-b bg-muted/40">
              <div className="col-span-2">نوع (Type)</div>
              <div className="col-span-2">نام هاست (Host)</div>
              <div className="col-span-6">مقدار (Value)</div>
              <div className="col-span-2 text-end">TTL / پروکسی</div>
            </div>
            <div className="divide-y text-xs font-mono">
              {dnsRecords.map((r) => (
                <div key={r.id} className="grid grid-cols-12 p-3 items-center hover:bg-muted/20">
                  <div className="col-span-2">
                    <Badge variant="secondary" className="font-mono text-[11px]">{r.type}</Badge>
                  </div>
                  <div className="col-span-2 font-medium">{r.name}</div>
                  <div className="col-span-6 truncate text-muted-foreground" title={r.value}>{r.value}</div>
                  <div className="col-span-2 text-end text-muted-foreground">
                    {r.ttl}s {r.is_proxied && <span className="ms-1 text-amber-600 font-sans font-medium">⚡ پروکسی</span>}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </TabsContent>

        {/* Tab 4: SSL */}
        <TabsContent value="ssl" className="mt-4 space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">مدیریت چرخه عمر گواهی امنیتی SSL/TLS</CardTitle>
              <CardDescription>صدور، بازرسی دوره‌ای و تمدید خودکار گواهی‌نامه‌های امنیتی برای دامنه‌های اصلی و زیردامنه‌ها.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between p-4 border rounded-lg bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200">
                <div className="flex items-center gap-3">
                  <ShieldCheck className="h-8 w-8 text-emerald-600" />
                  <div>
                    <h4 className="font-semibold text-sm">گواهی امنیتی رمزنگاری‌شده فعال است</h4>
                    <p className="text-xs text-muted-foreground">صادرکننده: Let&apos;s Encrypt Authority X3 · الگوریتم RSA-2048 / SHA-256</p>
                  </div>
                </div>
                <Button size="sm" variant="outline">
                  تمدید فوری گواهی
                </Button>
              </div>

              <div className="grid gap-3 sm:grid-cols-3 text-xs">
                <div className="border p-3 rounded-md">
                  <span className="text-muted-foreground">تاریخ اعتبار:</span>
                  <p className="font-semibold font-mono mt-1">تا ۲۰۲۷/۰۲/۰۱ (۷۴ روز باقیمانده)</p>
                </div>
                <div className="border p-3 rounded-md">
                  <span className="text-muted-foreground">وضعیت تمدید خودکار:</span>
                  <p className="font-semibold text-emerald-600 mt-1">فعال (۳۰ روز قبل از انقضا)</p>
                </div>
                <div className="border p-3 rounded-md">
                  <span className="text-muted-foreground">نوع گواهی‌نامه:</span>
                  <p className="font-semibold mt-1">چنددامنه‌ای (SAN) شامل دامنه و www</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
