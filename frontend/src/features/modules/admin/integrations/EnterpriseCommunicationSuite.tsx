'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Bell,
  Mail,
  MessageSquare,
  ShieldAlert,
  Send,
  CheckCircle2,
  Clock,
  VolumeX,
  Radio,
  Sliders,
  Sparkles,
} from 'lucide-react';

export interface EventCatalogItem {
  id: number;
  event_key: string;
  category: string;
  title_fa: string;
  default_priority: 'critical' | 'high' | 'normal' | 'low';
  allowed_channels: string[];
  is_transactional: boolean;
}

export interface DeliveryLedgerItem {
  id: number;
  event_key: string;
  channel: 'in_app' | 'sms' | 'email' | 'chat';
  recipient: string;
  priority: string;
  status: 'delivered' | 'sent' | 'suppressed' | 'failed';
  reason: string;
  time: string;
}

export function EnterpriseCommunicationSuite() {
  const [activeTab, setActiveTab] = useState<'events' | 'ledger' | 'test'>('ledger');
  const [quietHoursStart, setQuietHoursStart] = useState('23:00');
  const [quietHoursEnd, setQuietHoursEnd] = useState('07:00');
  const [isSavedPref, setIsSavedPref] = useState(false);

  const [events] = useState<EventCatalogItem[]>([
    {
      id: 1,
      event_key: 'order.paid',
      category: 'sales',
      title_fa: 'تسویه موفق سفارش و فاکتور',
      default_priority: 'critical',
      allowed_channels: ['in_app', 'sms', 'email'],
      is_transactional: true,
    },
    {
      id: 2,
      event_key: 'invoice.overdue',
      category: 'finance',
      title_fa: 'سررسید معوق فاکتور رسمی',
      default_priority: 'high',
      allowed_channels: ['in_app', 'sms', 'email'],
      is_transactional: true,
    },
    {
      id: 3,
      event_key: 'contract.signed',
      category: 'docs',
      title_fa: 'امضای رسمی قرارداد و قفل حقوقی',
      default_priority: 'critical',
      allowed_channels: ['in_app', 'sms', 'email', 'chat'],
      is_transactional: true,
    },
    {
      id: 4,
      event_key: 'ticket.created',
      category: 'crm',
      title_fa: 'ثبت تیکت جدید پشتیبانی مشتری',
      default_priority: 'normal',
      allowed_channels: ['in_app', 'chat'],
      is_transactional: false,
    },
  ]);

  const [ledger] = useState<DeliveryLedgerItem[]>([
    {
      id: 101,
      event_key: 'contract.signed',
      channel: 'sms',
      recipient: '09121112233',
      priority: 'critical',
      status: 'delivered',
      reason: 'modirpayamak_pattern_ok',
      time: 'هم‌اکنون',
    },
    {
      id: 102,
      event_key: 'contract.signed',
      channel: 'email',
      recipient: 'info@zagros-petro.com',
      priority: 'critical',
      status: 'sent',
      reason: 'smtp_dispatched',
      time: 'هم‌اکنون',
    },
    {
      id: 103,
      event_key: 'invoice.overdue',
      channel: 'sms',
      recipient: '09351234567',
      priority: 'normal',
      status: 'suppressed',
      reason: 'quiet_hours_active',
      time: 'دیشب ۲۳:۴۰',
    },
    {
      id: 104,
      event_key: 'ticket.created',
      channel: 'chat',
      recipient: 'Bale Bot: #support-ops',
      priority: 'normal',
      status: 'delivered',
      reason: 'bale_bridge_ok',
      time: '۲ ساعت پیش',
    },
  ]);

  const handleSavePreferences = () => {
    setIsSavedPref(true);
    setTimeout(() => setIsSavedPref(false), 2000);
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header bar */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
        <div>
          <h2 className="text-xl font-bold tracking-tight flex items-center gap-2">
            <Radio className="h-5 w-5 text-primary" />
            هاب اورکستراسیون ارتباطات چندکاناله (Communication Orchestrator)
          </h2>
          <p className="text-xs text-muted-foreground mt-0.5">
            موتور پالیسی یکپارچه: تفکیک تراکنشی از تبلیغاتی، فیلتر ساعات سکوت شبانه، جلوگیری از ارسال تکراری (Dedup) و دفتر کل تحویل.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant={activeTab === 'ledger' ? 'default' : 'outline'}
            onClick={() => setActiveTab('ledger')}
          >
            دفتر کل تحویل (Ledger)
          </Button>
          <Button
            size="sm"
            variant={activeTab === 'events' ? 'default' : 'outline'}
            onClick={() => setActiveTab('events')}
          >
            کاتالوگ رویدادها
          </Button>
        </div>
      </div>

      {activeTab === 'ledger' ? (
        <div className="space-y-4">
          <div className="grid gap-4 md:grid-cols-3">
            <Card className="shadow-sm">
              <CardHeader className="p-4 pb-2">
                <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
                  ارسال‌های موفق امروز
                  <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4 pt-1">
                <div className="text-xl font-bold text-emerald-600">۹۸.۶٪ موفق</div>
                <p className="text-xs text-muted-foreground mt-0.5 font-mono">پیامک، ایمیل، اعلان، بله</p>
              </CardContent>
            </Card>

            <Card className="shadow-sm">
              <CardHeader className="p-4 pb-2">
                <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
                  فیلتر ساعات سکوت (Quiet Hours)
                  <VolumeX className="h-4 w-4 text-amber-500" />
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4 pt-1">
                <div className="text-sm font-bold font-mono">{quietHoursStart} تا {quietHoursEnd}</div>
                <p className="text-xs text-muted-foreground mt-0.5">مسدودسازی خودکار پیام‌های غیرحیاتی</p>
              </CardContent>
            </Card>

            <Card className="shadow-sm">
              <CardHeader className="p-4 pb-2">
                <CardTitle className="text-xs font-medium text-muted-foreground flex items-center justify-between">
                  جلوگیری از ارسال تکراری (Dedup)
                  <ShieldAlert className="h-4 w-4 text-blue-500" />
                </CardTitle>
              </CardHeader>
              <CardContent className="p-4 pt-1">
                <div className="text-sm font-bold font-mono">پنجره ۱۰ دقیقه‌ای فعال</div>
                <p className="text-xs text-muted-foreground mt-0.5">الگوریتم هش SHA-256 محتوا</p>
              </CardContent>
            </Card>
          </div>

          <Card className="shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">دفتر کل تحویل پیام‌ها (Unified Delivery Ledger)</CardTitle>
              <CardDescription>لاگ تحویل در لحظه به تفکیک کانال ارتباطی، گیرنده و وضعیت نهایی اپراتور.</CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>کلید رویداد (Event)</TableHead>
                    <TableHead>کانال</TableHead>
                    <TableHead>گیرنده</TableHead>
                    <TableHead>اولویت</TableHead>
                    <TableHead>وضعیت تحویل</TableHead>
                    <TableHead>توضیحات پالیسی</TableHead>
                    <TableHead className="text-end">زمان</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {ledger.map((item) => (
                    <TableRow key={item.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{item.event_key}</TableCell>
                      <TableCell>
                        <Badge variant="outline" className="text-xs font-mono">
                          {item.channel}
                        </Badge>
                      </TableCell>
                      <TableCell className="font-mono text-xs">{item.recipient}</TableCell>
                      <TableCell>
                        <Badge
                          variant={item.priority === 'critical' ? 'destructive' : 'secondary'}
                          className="text-[11px]"
                        >
                          {item.priority}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        {item.status === 'delivered' || item.status === 'sent' ? (
                          <span className="text-xs text-emerald-600 font-semibold flex items-center gap-1">
                            <CheckCircle2 className="h-3.5 w-3.5" />
                            ارسال شد
                          </span>
                        ) : item.status === 'suppressed' ? (
                          <span className="text-xs text-amber-600 font-medium flex items-center gap-1">
                            <VolumeX className="h-3.5 w-3.5" />
                            معلق در ساعات سکوت
                          </span>
                        ) : (
                          <span className="text-xs text-destructive">خطا</span>
                        )}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground font-mono">{item.reason}</TableCell>
                      <TableCell className="text-end text-xs font-mono text-muted-foreground">{item.time}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      ) : (
        /* Event Catalog View */
        <div className="space-y-4">
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base">کاتالوگ رویدادهای سازمانی و نگاشت کانال‌ها</CardTitle>
              <CardDescription>رویدادهای معتبر تراکنشی در ماژول‌های فروش، حسابداری، قراردادها و CRM.</CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>کلید یکتای رویداد</TableHead>
                    <TableHead>دپارتمان / حوزه</TableHead>
                    <TableHead>عنوان فارسی رویداد</TableHead>
                    <TableHead>اولویت پیش‌فرض</TableHead>
                    <TableHead>کانال‌های مجاز انتشار</TableHead>
                    <TableHead className="text-end">نوع پیام</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {events.map((ev) => (
                    <TableRow key={ev.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{ev.event_key}</TableCell>
                      <TableCell className="text-xs font-semibold">{ev.category}</TableCell>
                      <TableCell className="text-xs">{ev.title_fa}</TableCell>
                      <TableCell>
                        <Badge
                          variant={ev.default_priority === 'critical' ? 'destructive' : 'secondary'}
                          className="text-[11px]"
                        >
                          {ev.default_priority}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1 flex-wrap">
                          {ev.allowed_channels.map((ch) => (
                            <Badge key={ch} variant="outline" className="text-[10px] font-mono">
                              {ch}
                            </Badge>
                          ))}
                        </div>
                      </TableCell>
                      <TableCell className="text-end">
                        {ev.is_transactional ? (
                          <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 text-xs">
                            تراکنشی (فوری)
                          </Badge>
                        ) : (
                          <Badge variant="secondary" className="text-xs">
                            اطلاع‌رسانی عمومی
                          </Badge>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
