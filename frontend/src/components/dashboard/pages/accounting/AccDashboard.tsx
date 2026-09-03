'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { useAccountingGet } from './useAccountingGet';

const LINKS: { segment: string; title: string; desc: string }[] = [
  { segment: 'chart', title: 'نمودار حساب‌ها', desc: 'Chart of accounts' },
  { segment: 'journals', title: 'اسناد / دفتر روزنامه', desc: 'Journals' },
  { segment: 'ledger', title: 'دفتر کل', desc: 'Ledger' },
  { segment: 'reports', title: 'گزارش‌های مالی', desc: 'Reports' },
  { segment: 'fiscal-year', title: 'سال مالی', desc: 'Fiscal years' },
  { segment: 'settings', title: 'تنظیمات', desc: 'Settings' },
  { segment: 'persons', title: 'اشخاص', desc: 'Persons' },
  { segment: 'products', title: 'کالا و خدمات', desc: 'Products' },
  { segment: 'invoices', title: 'فاکتورها', desc: 'Invoices' },
  { segment: 'cash-accounts', title: 'بانک و صندوق', desc: 'Cash accounts' },
  { segment: 'receipts', title: 'رسیدها', desc: 'Receipts' },
  { segment: 'checks', title: 'چک‌ها', desc: 'Checks' },
  { segment: 'warehouses', title: 'انبارها', desc: 'Warehouses' },
  { segment: 'warehouse-stock', title: 'موجودی', desc: 'Stock' },
  { segment: 'warehouse-inbound', title: 'ورود کالا', desc: 'Inbound' },
  { segment: 'warehouse-outbound', title: 'خروج کالا', desc: 'Outbound' },
  { segment: 'warehouse-audit', title: 'انبارگردانی', desc: 'Audit' },
];

export default function AccDashboard() {
  const pathname = usePathname();
  const locale = pathname?.match(/^\/(fa|en)/)?.[1] ?? 'fa';
  const base = `/dashboard/accounting`;
  const { data, error, loading, reload } = useAccountingGet('/v1/accounting/summary');

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-lg font-semibold">حسابداری — داشبورد</h2>
          <p className="text-xs text-muted-foreground" dir="ltr">
            GET /v1/accounting/summary
          </p>
        </div>
        <Button type="button" variant="outline" size="sm" onClick={() => void reload()} disabled={loading}>
          بروزرسانی
        </Button>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {data && typeof data === 'object' ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {Object.entries(data as Record<string, unknown>).map(([k, v]) => (
            <Card key={k}>
              <CardHeader className="py-3">
                <CardDescription className="text-xs">{k}</CardDescription>
                <CardTitle className="text-xl tabular-nums">{String(v)}</CardTitle>
              </CardHeader>
            </Card>
          ))}
        </div>
      ) : (
        !loading && <p className="text-sm text-muted-foreground">خلاصه‌ای در دسترس نیست</p>
      )}

      <div>
        <h3 className="mb-3 text-sm font-medium">بخش‌ها</h3>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {LINKS.map((l) => (
            <Link key={l.segment} href={l.segment ? `${base}/${l.segment}` : base} className="block">
              <Card className="h-full transition-colors hover:bg-muted/40">
                <CardHeader>
                  <CardTitle className="text-base">{l.title}</CardTitle>
                  <CardDescription dir="ltr">{l.desc}</CardDescription>
                </CardHeader>
              </Card>
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}
