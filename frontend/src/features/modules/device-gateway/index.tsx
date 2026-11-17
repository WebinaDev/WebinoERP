'use client';

import React, { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { WebinoHardwareGatewayClient, GatewayHealth, PeripheralDevice } from '@/lib/hardware-gateway-client';

export function DeviceGatewayOverviewPage() {
  const t = useTranslations('erp.device_gateway');
  const [health, setHealth] = useState<GatewayHealth | null>(null);
  const [devices, setDevices] = useState<PeripheralDevice[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const client = new WebinoHardwareGatewayClient();
    client.checkHealth().then((h) => {
      setHealth(h);
      if (h.status !== 'offline') {
        client.getDevices().then(setDevices).catch(() => setDevices([]));
      }
      setIsLoading(false);
    });
  }, []);

  const isOffline = !health || health.status === 'offline';

  return (
    <div className="p-6 space-y-6" dir="rtl">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">{t('title')}</h1>
          <p className="text-muted-foreground">{t('description')}</p>
        </div>
        <div className="flex items-center gap-2">
          <span
            className={`inline-block w-3 h-3 rounded-full ${
              isOffline ? 'bg-amber-500' : 'bg-emerald-500 animate-pulse'
            }`}
          />
          <span className="text-sm font-medium">
            {isOffline ? 'درگاه محلی آفلاین است' : `متصل به نسخه ${health.version}`}
          </span>
        </div>
      </div>

      {isOffline && !isLoading && (
        <div className="p-4 rounded-xl border border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-200">
          <div className="flex items-start gap-3">
            <span className="text-2xl">⚠️</span>
            <div className="space-y-1">
              <h4 className="font-semibold text-base">درگاه سخت‌افزار Webino در دسترس نیست</h4>
              <p className="text-sm opacity-90">
                برای استفاده از کارتخوان بانکی (PC-POS)، چاپ مستقیم فیش، کشوی پول، بارکدخوان و ترازو، لطفاً برنامه عامل محلی را روی سیستم اجرا کنید.
              </p>
              <div className="pt-2 flex gap-3">
                <a
                  href="#download-agent"
                  className="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white transition-colors"
                >
                  دانلود نرم‌افزار Webino Hardware Gateway
                </a>
                <button
                  type="button"
                  onClick={() => window.location.reload()}
                  className="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold border border-amber-600 text-amber-700 dark:text-amber-300 hover:bg-amber-500/20 transition-colors"
                >
                  بررسی مجدد اتصال
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="grid gap-4 md:grid-cols-3">
        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <div className="flex items-center justify-between">
            <h3 className="font-semibold">{t('gateways')}</h3>
            <span className="text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary font-medium">
              پورت 9100
            </span>
          </div>
          <p className="text-sm text-muted-foreground mt-2">{t('gatewaysDesc')}</p>
        </div>

        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <div className="flex items-center justify-between">
            <h3 className="font-semibold">{t('peripherals')}</h3>
            <span className="text-xs px-2 py-0.5 rounded-full bg-secondary font-medium">
              {devices.length} دستگاه متصل
            </span>
          </div>
          <p className="text-sm text-muted-foreground mt-2">{t('peripheralsDesc')}</p>
        </div>

        <div className="p-4 rounded-xl border bg-card text-card-foreground shadow-sm">
          <div className="flex items-center justify-between">
            <h3 className="font-semibold">{t('printJobs')}</h3>
            <span className="text-xs px-2 py-0.5 rounded-full bg-secondary font-medium">ESC/POS + ZPL</span>
          </div>
          <p className="text-sm text-muted-foreground mt-2">{t('printJobsDesc')}</p>
        </div>
      </div>

      <div className="rounded-xl border bg-card text-card-foreground shadow-sm overflow-hidden">
        <div className="p-4 border-b bg-muted/30">
          <h3 className="font-semibold text-sm">سخت‌افزارهای فعال در این ایستگاه کاری</h3>
        </div>
        <div className="divide-y text-sm">
          {devices.length === 0 ? (
            <div className="p-6 text-center text-muted-foreground">
              دستگاه فعالی یافت نشد یا درگاه در حال حاضر متصل نیست.
            </div>
          ) : (
            devices.map((dev) => (
              <div key={dev.id} className="p-4 flex items-center justify-between">
                <div>
                  <div className="font-medium">{dev.name}</div>
                  <div className="text-xs text-muted-foreground">
                    نوع: {dev.type} | درایور: {dev.driver} | اتصال: {dev.connectionType}
                  </div>
                </div>
                <span
                  className={`text-xs px-2 py-1 rounded-full ${
                    dev.status === 'online'
                      ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                      : 'bg-muted text-muted-foreground'
                  }`}
                >
                  {dev.status}
                </span>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}

export function DeviceGatewayGatewaysPage() {
  const t = useTranslations('erp.device_gateway');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('gateways')}</h1>
      <p className="text-sm text-muted-foreground">{t('gatewaysDesc')}</p>
    </div>
  );
}

export function DeviceGatewayPeripheralsPage() {
  const t = useTranslations('erp.device_gateway');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('peripherals')}</h1>
      <p className="text-sm text-muted-foreground">{t('peripheralsDesc')}</p>
    </div>
  );
}

export function DeviceGatewayPrintJobsPage() {
  const t = useTranslations('erp.device_gateway');
  return (
    <div className="p-6 space-y-4" dir="rtl">
      <h1 className="text-xl font-bold">{t('printJobs')}</h1>
      <p className="text-sm text-muted-foreground">{t('printJobsDesc')}</p>
    </div>
  );
}
