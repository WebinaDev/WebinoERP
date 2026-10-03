'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getAxiosMessage } from '@/lib/api-helpers';
import {
  createPaymentIntent,
  getPaymentOptions,
  listPaymentIntents,
  quotePayment,
  type PaymentGatewaySettings,
  type PaymentIntentRow,
  type PaymentQuote,
} from '@/lib/api/payments';
import { useLocale } from '@/hooks/use-locale-next';

type Mode = 'cash' | 'installment';

export function PaymentStartDialog({
  open,
  onOpenChange,
  payableType,
  payableId,
  amount,
  title,
  domain,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  payableType: string;
  payableId: string;
  amount: number;
  title: string;
  domain?: string;
}) {
  const t = useTranslations('payments');
  const { formatNumber } = useLocale();
  const [mode, setMode] = useState<Mode>('cash');
  const [gateway, setGateway] = useState('');
  const [mobile, setMobile] = useState('');
  const [cash, setCash] = useState<PaymentGatewaySettings[]>([]);
  const [installment, setInstallment] = useState<PaymentGatewaySettings[]>([]);
  const [quote, setQuote] = useState<PaymentQuote | null>(null);
  const [history, setHistory] = useState<PaymentIntentRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const choices = mode === 'cash' ? cash : installment;

  useEffect(() => {
    if (!open) return;
    setError(null);
    setQuote(null);
    void getPaymentOptions().then((opts) => {
      setCash(opts.cash);
      setInstallment(opts.installment);
      const first = (mode === 'cash' ? opts.cash : opts.installment)[0];
      setGateway(first?.code ?? '');
    }).catch((err) => setError(getAxiosMessage(err)));
    void listPaymentIntents({ payable_type: payableType, payable_id: payableId })
      .then(setHistory)
      .catch(() => setHistory([]));
  }, [open, payableId, payableType, mode]);

  useEffect(() => {
    if (!open || !gateway || amount < 1000) {
      setQuote(null);
      return;
    }
    void quotePayment({ gateway, mode, base_amount: Math.round(amount) })
      .then(setQuote)
      .catch(() => setQuote(null));
  }, [open, gateway, mode, amount]);

  const pay = async () => {
    setBusy(true);
    setError(null);
    try {
      const intent = await createPaymentIntent({
        payable_type: payableType,
        payable_id: payableId,
        mode,
        gateway,
        domain,
        mobile: mobile.trim() || undefined,
        amount: payableType === 'wallet_topup' ? amount : undefined,
        return_url: window.location.href,
      });
      if (intent.redirect_url) {
        window.location.assign(intent.redirect_url);
        return;
      }
      setError(t('noRedirect'));
    } catch (err) {
      setError(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('payTitle')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3 text-sm">
          <p>{title}</p>
          <p>{t('baseAmount')}: <strong>{formatNumber(Math.round(amount))}</strong> {t('rial')}</p>
          <div className="flex gap-2">
            <Button type="button" size="sm" variant={mode === 'cash' ? 'default' : 'outline'} onClick={() => { setMode('cash'); setGateway(cash[0]?.code ?? ''); }}>
              {t('cash')}
            </Button>
            <Button type="button" size="sm" variant={mode === 'installment' ? 'default' : 'outline'} onClick={() => { setMode('installment'); setGateway(installment[0]?.code ?? ''); }}>
              {t('installment')}
            </Button>
          </div>
          {choices.length === 0 ? (
            <p className="text-muted-foreground">{t('noGatewayForMode')}</p>
          ) : (
            <div className="space-y-2">
              <Label>{t('gateway')}</Label>
              <div className="flex flex-wrap gap-2">
                {choices.map((item) => (
                  <Button key={item.code} type="button" size="sm" variant={gateway === item.code ? 'default' : 'outline'} onClick={() => setGateway(item.code)}>
                    {item.label_fa}
                  </Button>
                ))}
              </div>
            </div>
          )}
          {mode === 'installment' ? (
            <div className="space-y-2">
              <Label htmlFor="pay-mobile">{t('mobile')}</Label>
              <Input id="pay-mobile" dir="ltr" value={mobile} onChange={(e) => setMobile(e.target.value)} placeholder="0912…" />
            </div>
          ) : null}
          {quote ? (
            <div className="rounded-md border p-3">
              <p>{t('baseAmount')}: {formatNumber(quote.base_amount)}</p>
              <p>{t('feePercent')}: {formatNumber(quote.fee_percent)}</p>
              <p>{t('feeAmount')}: {formatNumber(quote.fee_amount)}</p>
              <p>{t('totalCharged')}: <strong>{formatNumber(quote.total_amount)}</strong></p>
            </div>
          ) : null}
          {history.length ? (
            <div>
              <p className="mb-1 font-medium">{t('history')}</p>
              <ul className="space-y-1">
                {history.slice(0, 5).map((row) => (
                  <li key={row.payment_id} className="text-muted-foreground">
                    {row.gateway} / {row.mode} / {row.status} / {formatNumber(row.total_amount)}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
          {error ? <p className="text-destructive">{error}</p> : null}
        </div>
        <DialogFooter>
          <Button type="button" disabled={busy || !gateway} onClick={() => void pay()}>
            {busy ? t('paying') : t('pay')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
