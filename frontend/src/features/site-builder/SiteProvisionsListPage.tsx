'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import {
  Coffee,
  ExternalLink,
  Globe2,
  LayoutGrid,
  Plus,
  Power,
  PowerOff,
  Settings2,
  ShieldCheck,
  Sparkles,
  Store,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { dashboardHref } from '@/lib/route-resolver';
import { formatProvisionError, getAxiosMessage } from '@/lib/api-helpers';
import {
  fetchProvisions,
  startProvision,
  stopProvision,
  type SiteProvision,
} from '@/lib/api/site-builder';
import { cn } from '@/lib/utils';

function siteTypeIcon(type?: string) {
  switch ((type || '').toLowerCase()) {
    case 'cafe':
      return Coffee;
    case 'ecommerce':
    case 'shop':
      return Store;
    default:
      return LayoutGrid;
  }
}

function statusTone(status: string): string {
  switch (status) {
    case 'ready':
      return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
    case 'failed':
    case 'cancelled':
      return 'bg-destructive/10 text-destructive border-destructive/30';
    case 'provisioning':
    case 'pending':
    case 'ssl_pending':
      return 'bg-amber-500/15 text-amber-800 dark:text-amber-200 border-amber-500/30';
    default:
      return 'bg-muted text-muted-foreground border-border';
  }
}

function licenseLabel(row: SiteProvision, t: ReturnType<typeof useTranslations>) {
  const exp = row.license?.expires_at;
  if (!exp) return t('controlLicenseOpen');
  const d = new Date(exp);
  if (Number.isNaN(d.getTime())) return t('controlLicenseOpen');
  if (d.getTime() < Date.now()) return t('controlLicenseExpired');
  return t('controlLicenseUntil', { date: d.toLocaleDateString() });
}

export function SiteProvisionsListPage() {
  const t = useTranslations('siteBuilder');
  const locale = useLocale();
  const [rows, setRows] = useState<SiteProvision[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchProvisions());
    } catch (e) {
      setError(getAxiosMessage(e) || t('loadError'));
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const readyCount = useMemo(() => rows.filter((r) => r.status === 'ready').length, [rows]);

  async function power(id: number, action: 'start' | 'stop') {
    setBusyId(id);
    setError(null);
    try {
      if (action === 'start') await startProvision(id);
      else await stopProvision(id);
      await load();
    } catch (e) {
      setError(getAxiosMessage(e) || t('loadError'));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div className="space-y-6" data-testid="sites-fleet">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="space-y-1">
          <div className="text-primary flex items-center gap-2 text-sm font-medium">
            <Sparkles className="size-4" />
            {t('fleetBadge')}
          </div>
          <h1 className="text-3xl font-semibold tracking-tight">{t('provisionsTitle')}</h1>
          <p className="text-muted-foreground max-w-2xl text-sm">{t('provisionsSubtitle')}</p>
          <p className="text-muted-foreground text-xs">
            {t('fleetStats', { total: rows.length, ready: readyCount })}
          </p>
        </div>
        <Button asChild size="lg" className="gap-2 shadow-sm">
          <Link href={dashboardHref(locale, 'admin/platform/sites/new')}>
            <Plus className="size-4" />
            {t('newProvision')}
          </Link>
        </Button>
      </div>

      {error ? <p className="text-destructive text-sm">{error}</p> : null}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {rows.map((row) => {
          const type = String(row.wizard_payload?.site_type_slug ?? row.package?.business_type_id ?? '');
          const Icon = siteTypeIcon(type);
          const channel = String(row.wizard_payload?.channel ?? 'beta');
          const detailHref = dashboardHref(locale, `admin/platform/sites/${row.id}`);
          const customerName =
            (row as { crm_account?: { name?: string }; crmAccount?: { name?: string } }).crm_account?.name
            || (row as { crmAccount?: { name?: string } }).crmAccount?.name
            || (typeof row.wizard_payload?.site_name === 'string' ? row.wizard_payload.site_name : null);

          return (
            <Card
              key={row.id}
              className="group border-border/70 from-card to-muted/20 relative overflow-hidden bg-gradient-to-br transition-shadow hover:shadow-lg"
              data-testid={`site-card-${row.id}`}
            >
              <div className="pointer-events-none absolute -top-10 -right-10 size-32 rounded-full bg-primary/10 blur-2xl transition group-hover:bg-primary/20" />
              <CardHeader className="relative pb-3">
                <div className="flex items-start justify-between gap-3">
                  <div className="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-xl">
                    <Icon className="size-5" />
                  </div>
                  <Badge variant="outline" className={cn('border capitalize', statusTone(row.status))}>
                    {row.status}
                  </Badge>
                </div>
                <CardTitle className="mt-3 flex items-center gap-2 text-lg">
                  <Globe2 className="text-muted-foreground size-4 shrink-0" />
                  <Link href={detailHref} className="hover:text-primary truncate transition-colors">
                    {row.domain}
                  </Link>
                </CardTitle>
              </CardHeader>
              <CardContent className="relative space-y-3 text-sm">
                <div className="text-muted-foreground flex flex-wrap gap-x-3 gap-y-1">
                  <span className="font-mono text-xs">{row.slug}</span>
                  {customerName ? <span>· {customerName}</span> : null}
                </div>
                <div className="flex flex-wrap gap-2">
                  <Badge variant="secondary" className="gap-1 capitalize">
                    <Sparkles className="size-3" />
                    {channel}
                  </Badge>
                  <Badge variant="outline" className="gap-1">
                    <ShieldCheck className="size-3" />
                    {licenseLabel(row, t)}
                  </Badge>
                </div>
                {row.status === 'failed' && row.error_log ? (
                  <pre className="bg-muted/80 max-h-20 overflow-auto rounded-md p-2 text-[11px] whitespace-pre-wrap">
                    {formatProvisionError(row.error_log).slice(0, 280)}
                  </pre>
                ) : null}
                <div className="flex flex-wrap gap-2 pt-1">
                  <Button asChild size="sm" className="gap-1.5">
                    <Link href={detailHref} data-testid={`site-open-panel-${row.id}`}>
                      <Settings2 className="size-3.5" />
                      {t('controlOpenPanel')}
                    </Link>
                  </Button>
                  {row.status === 'ready' ? (
                    <Button asChild size="sm" variant="outline" className="gap-1.5">
                      <a href={`https://${row.domain}`} target="_blank" rel="noopener noreferrer">
                        <ExternalLink className="size-3.5" />
                        {t('openSite')}
                      </a>
                    </Button>
                  ) : null}
                  <Button
                    size="sm"
                    variant="outline"
                    type="button"
                    className="gap-1.5"
                    disabled={busyId === row.id}
                    onClick={() => void power(row.id, 'start')}
                    data-testid={`site-start-${row.id}`}
                  >
                    <Power className="size-3.5" />
                    {t('start')}
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    type="button"
                    className="gap-1.5"
                    disabled={busyId === row.id}
                    onClick={() => void power(row.id, 'stop')}
                    data-testid={`site-stop-${row.id}`}
                  >
                    <PowerOff className="size-3.5" />
                    {t('stop')}
                  </Button>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {rows.length === 0 && !error ? (
        <Card className="border-dashed">
          <CardContent className="text-muted-foreground flex flex-col items-center gap-3 py-16 text-center">
            <Globe2 className="size-10 opacity-40" />
            <p>{t('fleetEmpty')}</p>
            <Button asChild>
              <Link href={dashboardHref(locale, 'admin/platform/sites/new')}>{t('newProvision')}</Link>
            </Button>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
