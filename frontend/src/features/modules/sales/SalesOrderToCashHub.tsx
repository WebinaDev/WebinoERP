'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  FileText,
  TrendingUp,
  DollarSign,
  ArrowRightLeft,
  CheckCircle2,
  Clock,
  Plus,
  Send,
  Building2,
  Calendar,
  Layers,
} from 'lucide-react';

export interface SalesQuoteItem {
  id: number;
  quote_number: string;
  customer_name: string;
  issue_date: string;
  valid_until: string;
  subtotal: number;
  tax_amount: number;
  total_amount: number;
  status: 'draft' | 'sent' | 'accepted' | 'converted';
  lines_count: number;
}

export interface GrowthCampaignAttribution {
  utm_campaign: string;
  campaign_source: string;
  total_leads: number;
  total_deals: number;
  total_invoices: number;
  total_revenue: number;
}

export function SalesOrderToCashHub() {
  const [activeView, setActiveView] = useState<'quotes' | 'growth'>('quotes');

  const [quotes, setQuotes] = useState<SalesQuoteItem[]>([
    {
      id: 1,
      quote_number: 'SQ-20261116-4192',
      customer_name: 'شرکت پتروشیمی زاگرس',
      issue_date: '۱۴۰۵/۰۸/۲۶',
      valid_until: '۱۴۰۵/۰۹/۲۶',
      subtotal: 185000000,
      tax_amount: 18500000,
      total_amount: 203500000,
      status: 'accepted',
      lines_count: 3,
    },
    {
      id: 2,
      quote_number: 'SQ-20261114-8821',
      customer_name: 'صنایع فولاد پاسارگاد',
      issue_date: '۱۴۰۵/۰۸/۲۴',
      valid_until: '۱۴۰۵/۰۹/۲۴',
      subtotal: 92000000,
      tax_amount: 9200000,
      total_amount: 101200000,
      status: 'converted',
      lines_count: 2,
    },
    {
      id: 3,
      quote_number: 'SQ-20261110-1049',
      customer_name: 'داروسازی سیناژن',
      issue_date: '۱۴۰۵/۰۸/۲۰',
      valid_until: '۱۴۰۵/۰۹/۲۰',
      subtotal: 340000000,
      tax_amount: 34000000,
      total_amount: 374000000,
      status: 'sent',
      lines_count: 5,
    },
  ]);

  const [attributions, setAttributions] = useState<GrowthCampaignAttribution[]>([
    {
      utm_campaign: 'enterprise_erp_autumn',
      campaign_source: 'Google Search Ads',
      total_leads: 142,
      total_deals: 28,
      total_invoices: 14,
      total_revenue: 840000000,
    },
    {
      utm_campaign: 'wms_logistics_b2b',
      campaign_source: 'LinkedIn InMail',
      total_leads: 89,
      total_deals: 19,
      total_invoices: 8,
      total_revenue: 520000000,
    },
    {
      utm_campaign: 'saas_cloud_webina',
      campaign_source: 'Direct / Organic Blog',
      total_leads: 310,
      total_deals: 42,
      total_invoices: 21,
      total_revenue: 690000000,
    },
  ]);

  const formatCurrency = (val: number) => {
    return val.toLocaleString('fa-IR') + ' ریال';
  };

  const handleConvert = (quoteId: number) => {
    setQuotes((prev) =>
      prev.map((q) => (q.id === quoteId ? { ...q, status: 'converted' } : q))
    );
  };

  return (
    <div className="space-y-6" dir="rtl">
      {/* Sub-header navigation */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4">
        <div>
          <h2 className="text-xl font-bold tracking-tight flex items-center gap-2">
            <FileText className="h-5 w-5 text-primary" />
            چرخه فروش B2B و پیش‌فاکتور رسمی (Order-to-Cash Hub)
          </h2>
          <p className="text-xs text-muted-foreground mt-0.5">
            ثبت پیش‌فاکتور با ردیف‌های تفکیکی کالا و مالیات بر ارزش افزوده (VAT ۱۰٪) و تبدیل مکانیزه به فاکتور رسمی فروش.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Button
            size="sm"
            variant={activeView === 'quotes' ? 'default' : 'outline'}
            onClick={() => setActiveView('quotes')}
          >
            پیش‌فاکتورهای رسمی B2B
          </Button>
          <Button
            size="sm"
            variant={activeView === 'growth' ? 'default' : 'outline'}
            onClick={() => setActiveView('growth')}
          >
            <TrendingUp className="h-4 w-4 me-1.5 text-emerald-500" />
            انتساب رشد بازاریابی (Attribution)
          </Button>
        </div>
      </div>

      {activeView === 'quotes' ? (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <Badge variant="outline" className="text-xs">
                مجموع پیش‌فاکتورهای فعال: <strong>۳ فقره</strong>
              </Badge>
              <Badge variant="outline" className="text-xs bg-emerald-50 text-emerald-700 border-emerald-200">
                ارزش پایپ‌لاین: <strong>{formatCurrency(678700000)}</strong>
              </Badge>
            </div>
            <Button size="sm">
              <Plus className="h-4 w-4 me-1.5" />
              صدور پیش‌فاکتور جدید
            </Button>
          </div>

          <Card className="shadow-sm">
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>شماره پیش‌فاکتور</TableHead>
                    <TableHead>نام مشتری سازمانی</TableHead>
                    <TableHead>تاریخ صدور</TableHead>
                    <TableHead>مهلت اعتبار</TableHead>
                    <TableHead>تعداد اقلام</TableHead>
                    <TableHead>مالیات (VAT)</TableHead>
                    <TableHead>مبلغ کل</TableHead>
                    <TableHead>وضعیت</TableHead>
                    <TableHead className="text-end">عملیات</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {quotes.map((q) => (
                    <TableRow key={q.id} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium">{q.quote_number}</TableCell>
                      <TableCell className="font-semibold">{q.customer_name}</TableCell>
                      <TableCell className="text-xs font-mono">{q.issue_date}</TableCell>
                      <TableCell className="text-xs font-mono">{q.valid_until}</TableCell>
                      <TableCell className="text-xs font-mono">{q.lines_count} ردیف</TableCell>
                      <TableCell className="text-xs font-mono">{formatCurrency(q.tax_amount)}</TableCell>
                      <TableCell className="font-mono font-bold text-primary">{formatCurrency(q.total_amount)}</TableCell>
                      <TableCell>
                        {q.status === 'converted' ? (
                          <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-xs">
                            تبدیل‌شده به فاکتور
                          </Badge>
                        ) : q.status === 'accepted' ? (
                          <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 text-xs">
                            تأیید مشتری
                          </Badge>
                        ) : (
                          <Badge variant="secondary" className="text-xs">
                            ارسال‌شده به مشتری
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-end">
                        {q.status !== 'converted' ? (
                          <Button size="sm" variant="outline" onClick={() => handleConvert(q.id)} className="text-xs">
                            <ArrowRightLeft className="h-3.5 w-3.5 me-1 text-emerald-600" />
                            تبدیل به فاکتور
                          </Button>
                        ) : (
                          <span className="text-xs text-muted-foreground">صادر شد ✓</span>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      ) : (
        /* Growth Attribution View */
        <div className="space-y-4">
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                تحلیل بازگشت سرمایه و تبدیل بازاریابی به فروش (Marketing-to-Sales Funnel)
                <TrendingUp className="h-5 w-5 text-emerald-600" />
              </CardTitle>
              <CardDescription>
                رهگیری کامل لیدهای جذب‌شده از کمپین‌ها و لندینگ‌پیج‌ها تا عقد قرارداد و درآمد محقق‌شده فاکتورها.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/40 text-xs">
                    <TableHead>نام کمپین (UTM Campaign)</TableHead>
                    <TableHead>کانال ورودی (Source)</TableHead>
                    <TableHead>لیدهای جذب‌شده</TableHead>
                    <TableHead>معاملات CRM</TableHead>
                    <TableHead>فاکتورهای پرداخت‌شده</TableHead>
                    <TableHead className="text-end">درآمد محقق‌شده قطعی</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody className="text-sm">
                  {attributions.map((a, idx) => (
                    <TableRow key={idx} className="hover:bg-muted/20">
                      <TableCell className="font-mono font-medium text-xs">{a.utm_campaign}</TableCell>
                      <TableCell className="text-xs">{a.campaign_source}</TableCell>
                      <TableCell className="font-mono text-xs">{a.total_leads} لید</TableCell>
                      <TableCell className="font-mono text-xs">{a.total_deals} معامله</TableCell>
                      <TableCell className="font-mono text-xs font-semibold text-emerald-600">{a.total_invoices} فاکتور</TableCell>
                      <TableCell className="text-end font-mono font-bold text-primary">
                        {formatCurrency(a.total_revenue)}
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
