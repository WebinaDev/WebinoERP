'use client';

import React, { useState, useEffect } from 'react';
import { useTranslations } from 'next-intl';
import AccReceipts from '@/components/dashboard/pages/accounting/AccReceipts';
import { FinanceSection } from '../components/FinanceSection';
import { WebinoHardwareGatewayClient, GatewayHealth } from '@/lib/hardware-gateway-client';
import { CreditCard, Printer, Layers, AlertCircle, CheckCircle2, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';

export function ReceiptsPage() {
  const t = useTranslations('nav.erp.finance');
  const [gatewayHealth, setGatewayHealth] = useState<GatewayHealth | null>(null);
  const [isPosOpen, setIsPosOpen] = useState(false);
  const [posAmount, setPosAmount] = useState('1500000');
  const [posInvoice, setPosInvoice] = useState('REC-' + Date.now().toString().slice(-6));
  const [isCharging, setIsCharging] = useState(false);
  const [posResult, setPosResult] = useState<any>(null);

  useEffect(() => {
    const client = new WebinoHardwareGatewayClient();
    client.checkHealth().then(setGatewayHealth).catch(() => {
      setGatewayHealth({ version: '0.0.0', uptimeSeconds: 0, devicesCount: 0, status: 'offline' });
    });
  }, []);

  const isOnline = gatewayHealth && gatewayHealth.status !== 'offline';

  const handleChargePos = async () => {
    setIsCharging(true);
    setPosResult(null);
    try {
      const client = new WebinoHardwareGatewayClient();
      const res = await client.chargePos({
        amount: Number(posAmount),
        invoiceNumber: posInvoice,
      });
      setPosResult(res);
    } catch (err: any) {
      setPosResult({ success: false, message: err.message || 'خطا در ارتباط با دستگاه کارتخوان' });
    } finally {
      setIsCharging(false);
    }
  };

  return (
    <FinanceSection title={t('receipts')}>
      {/* نوار وضعیت درگاه سخت‌افزار مالی (Hardware POS Strip) */}
      <div className="mb-6 p-4 rounded-xl border bg-card shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4" dir="rtl">
        <div className="flex items-center gap-3">
          <span className={`flex h-3 w-3 rounded-full ${isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'}`} />
          <div>
            <div className="text-sm font-semibold flex items-center gap-2">
              <span>درگاه سخت‌افزار دریافت و پرداخت (PC-POS & Cash Drawer)</span>
              <span className={`text-[11px] px-2 py-0.5 rounded-full font-medium ${isOnline ? 'bg-emerald-500/10 text-emerald-600' : 'bg-amber-500/10 text-amber-700'}`}>
                {isOnline ? 'متصل و آماده دریافت' : 'آفلاین (عامل محلی یافت نشد)'}
              </span>
            </div>
            <p className="text-xs text-muted-foreground mt-0.5">
              اتصال مستقیم به کارتخوان‌های شاپرک (SEP، به‌پرداخت، سداد، آپ) و چاپگر فیش از طریق پورت ۹۱۰۰
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          {isOnline ? (
            <Button
              size="sm"
              onClick={() => setIsPosOpen(true)}
              className="gap-1.5 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              <CreditCard className="size-3.5" />
              دریافت وجه با کارتخوان (PC-POS)
            </Button>
          ) : (
            <Button
              size="sm"
              variant="outline"
              onClick={() => window.open('/fa/dashboard/device-gateway', '_blank')}
              className="gap-1.5 text-xs font-semibold border-amber-500/50 text-amber-700 dark:text-amber-400 hover:bg-amber-500/10"
            >
              <Layers className="size-3.5" />
              راهنمای راه‌اندازی عامل سخت‌افزار
            </Button>
          )}
        </div>
      </div>

      {/* مدال تعامل مستقیم با کارتخوان بانکی */}
      <Dialog open={isPosOpen} onOpenChange={setIsPosOpen}>
        <DialogContent className="sm:max-w-md" dir="rtl">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-base">
              <CreditCard className="size-5 text-emerald-600" />
              ارسال مبلغ به پایانه فروشگاهی (PC-POS)
            </DialogTitle>
            <DialogDescription className="text-xs">
              مبلغ فاکتور مستقیماً به کارتخوان بانکی ارسال شده و دستگاه تا زمان کشیدن کارت و ورود رمز قفل می‌شود.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2 text-xs">
            <div className="space-y-1.5">
              <label className="font-medium text-muted-foreground">شماره رسید / فاکتور حسابداری:</label>
              <Input
                value={posInvoice}
                onChange={(e) => setPosInvoice(e.target.value)}
                className="h-9 text-xs font-mono"
              />
            </div>

            <div className="space-y-1.5">
              <label className="font-medium text-muted-foreground">مبلغ قابل دریافت (ریال):</label>
              <Input
                type="number"
                value={posAmount}
                onChange={(e) => setPosAmount(e.target.value)}
                className="h-9 text-xs font-mono font-bold text-emerald-600"
              />
            </div>

            {posResult && (
              <div className={`p-3 rounded-lg border ${posResult.success ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-900 dark:text-emerald-200' : 'bg-rose-500/10 border-rose-500/30 text-rose-900 dark:text-rose-200'}`}>
                <div className="flex items-center gap-2 font-bold mb-1">
                  {posResult.success ? <CheckCircle2 className="size-4 text-emerald-600" /> : <AlertCircle className="size-4 text-rose-600" />}
                  <span>{posResult.success ? 'تراکنش با موفقیت انجام شد' : 'خطا در تراکنش'}</span>
                </div>
                {posResult.success ? (
                  <div className="space-y-0.5 text-[11px] font-mono mt-1">
                    <div>شماره پیگیری (Trace): {posResult.traceNo}</div>
                    <div>شماره مرجع (RRN): {posResult.rrn}</div>
                    <div>کارت: {posResult.maskedPan}</div>
                    <div>ترمینال: {posResult.terminalId}</div>
                  </div>
                ) : (
                  <p className="text-[11px]">{posResult.message}</p>
                )}
              </div>
            )}
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setIsPosOpen(false)}
              className="text-xs"
            >
              بستن
            </Button>
            <Button
              size="sm"
              onClick={handleChargePos}
              disabled={isCharging}
              className="text-xs font-semibold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              {isCharging ? <RefreshCw className="size-3.5 animate-spin" /> : <CreditCard className="size-3.5" />}
              {isCharging ? 'منتظر کشیدن کارت و رمز...' : 'ارسال به کارتخوان'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AccReceipts />
    </FinanceSection>
  );
}
