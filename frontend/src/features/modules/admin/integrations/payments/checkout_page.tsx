'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getPaymentBills, listPaymentIntents, type PaymentBill, type PaymentIntentRow } from '@/lib/api/payments';
import { useLocale } from '@/hooks/use-locale-next';
import { PaymentStartDialog } from './payment-dialog';

const TYPES = [
  'sales_invoice',
  'accounting_invoice',
  'project_invoice',
  'marketplace_order',
  'sms_credit',
  'sms_order',
  'license',
  'subscription',
  'wallet_topup',
] as const;

export function PaymentCheckoutPage() {
  const t = useTranslations('payments');
  const tNav = useTranslations();
  const { formatNumber } = useLocale();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [domain, setDomain] = useState('');
  const [bills, setBills] = useState<PaymentBill[]>([]);
  const [offers, setOffers] = useState<PaymentBill[]>([]);
  const [history, setHistory] = useState<PaymentIntentRow[]>([]);
  const [payableType, setPayableType] = useState<string>('wallet_topup');
  const [payableId, setPayableId] = useState('');
  const [amount, setAmount] = useState('100000');
  const [open, setOpen] = useState(false);
  const [target, setTarget] = useState<PaymentBill | null>(null);

  const load = useCallback(async () => {
    try {
      const [listed, intents] = await Promise.all([
        getPaymentBills(domain.trim() || undefined),
        listPaymentIntents(domain.trim() ? { domain: domain.trim() } : undefined),
      ]);
      setBills(listed.bills);
      setOffers(listed.offers);
      setHistory(intents);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError, domain]);

  useEffect(() => {
    void load();
  }, [load]);

  const startCustom = () => {
    setTarget({
      payable_type: payableType,
      payable_id: payableId || domain || 'wallet',
      title: t(`types.${payableType}`),
      amount: Number(amount) || 0,
      currency: 'IRR',
      domain: domain.trim() || null,
      status: 'draft',
    });
    setOpen(true);
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.admin.paymentCheckout')} description={t('checkoutLead')} {...layoutProps}>
      <div className="space-y-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('startPayment')}</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t('domain')}</Label>
              <Input dir="ltr" value={domain} onChange={(e) => setDomain(e.target.value)} placeholder="example.com" />
            </div>
            <div className="space-y-2">
              <Label>{t('payableType')}</Label>
              <select className="border-input bg-background h-9 w-full rounded-md border px-2 text-sm" value={payableType} onChange={(e) => setPayableType(e.target.value)}>
                {TYPES.map((type) => (
                  <option key={type} value={type}>{t(`types.${type}`)}</option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label>{t('payableId')}</Label>
              <Input dir="ltr" value={payableId} onChange={(e) => setPayableId(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>{t('amountRial')}</Label>
              <Input dir="ltr" inputMode="numeric" value={amount} onChange={(e) => setAmount(e.target.value)} />
            </div>
            <div className="sm:col-span-2 flex gap-2">
              <Button type="button" variant="outline" onClick={() => void load()}>{t('refresh')}</Button>
              <Button type="button" onClick={startCustom}>{t('pay')}</Button>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('outstanding')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {bills.length === 0 ? <p className="text-sm text-muted-foreground">{t('emptyBills')}</p> : bills.map((bill) => (
              <div key={`${bill.payable_type}-${bill.payable_id}`} className="flex items-center justify-between gap-2 border-b py-2 text-sm">
                <span>{bill.title} · {formatNumber(bill.amount)} {t('rial')}</span>
                <Button type="button" size="sm" onClick={() => { setTarget(bill); setOpen(true); }}>{t('pay')}</Button>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('offers')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            {offers.length === 0 ? <p className="text-sm text-muted-foreground">{t('emptyOffers')}</p> : offers.map((bill) => (
              <div key={`${bill.payable_type}-${bill.payable_id}`} className="flex items-center justify-between gap-2 border-b py-2 text-sm">
                <span>{bill.title} · {formatNumber(bill.amount)} {t('rial')}</span>
                <Button type="button" size="sm" variant="outline" onClick={() => { setTarget(bill); setOpen(true); }}>{t('pay')}</Button>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('history')}</CardTitle></CardHeader>
          <CardContent className="space-y-1 text-sm">
            {history.length === 0 ? <p className="text-muted-foreground">{t('emptyHistory')}</p> : history.map((row) => (
              <p key={row.payment_id}>{row.gateway} · {row.mode} · {row.status} · {formatNumber(row.total_amount)} {t('rial')}</p>
            ))}
          </CardContent>
        </Card>
      </div>

      {target ? (
        <PaymentStartDialog
          open={open}
          onOpenChange={setOpen}
          payableType={target.payable_type}
          payableId={target.payable_id}
          amount={target.amount}
          title={target.title}
          domain={target.domain ?? (domain.trim() || undefined)}
        />
      ) : null}
    </CrmPageLayout>
  );
}
