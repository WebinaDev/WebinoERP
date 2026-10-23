'use client';

import { useTranslations } from 'next-intl';
import { AlertTriangle, Banknote, CalendarClock, CreditCard, Globe, KeyRound, LifeBuoy, Mail, MessageSquare, Phone, TrendingUp, Wallet } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useHub, type ActionName } from './HubContext';
import { useFmt } from './ui';
import type { Row } from './hub-api';

export function HubHeader() {
  const t = useTranslations('crm.hub');
  const { summary, canDo, open } = useHub();
  const h = summary.header;
  const health = summary.health;
  const kindKey = h.kind ? `kind.${h.kind}` : 'kind.unknown';
  const statusKey = `status.${h.status ?? 'active'}`;
  return (
    <Card>
      <CardContent className="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex items-start gap-4">
          {h.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={String(h.logo_url)} alt="" className="h-14 w-14 rounded-lg border object-contain" />
          ) : (
            <div className="flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10 text-xl font-bold text-primary">{String(h.name ?? '?').slice(0, 1)}</div>
          )}
          <div className="space-y-1">
            <h2 className="text-xl font-semibold">
              {String(h.name ?? '')}
              {h.brand_name ? <span className="ms-2 text-sm font-normal text-muted-foreground">({String(h.brand_name)})</span> : null}
            </h2>
            <div className="flex flex-wrap items-center gap-2 text-xs">
              <Badge variant="outline">{t(kindKey as never)}</Badge>
              <Badge variant={h.status === 'blocked' || h.status === 'debtor' ? 'destructive' : h.status === 'active' ? 'default' : 'secondary'}>{t(statusKey as never)}</Badge>
              <span className="text-muted-foreground">{t('code')}: <span dir="ltr">{String(h.code)}</span></span>
              <span className="text-muted-foreground">{t('owner')}: {h.owner?.name ?? t('noOwner')}</span>
              {(h.tags as Row[] | undefined)?.map((tag) => (
                <Badge key={String(tag.id)} variant="secondary" style={tag.color ? { backgroundColor: String(tag.color), color: '#fff' } : undefined}>{String(tag.name)}</Badge>
              ))}
            </div>
          </div>
        </div>
        <div className="flex flex-col gap-3 lg:items-end">
          {health ? (
            <div className="w-full min-w-[220px] space-y-1 lg:w-64">
              <div className="flex items-center justify-between text-sm">
                <span>{t('health.title')}</span>
                <span className="font-semibold">
                  {health.score}/100 · {t(`health.${health.grade}` as never)}
                </span>
              </div>
              <Progress value={Number(health.score)} className={health.grade === 'at_risk' ? '[&>div]:bg-destructive' : health.grade === 'fair' ? '[&>div]:bg-amber-500' : '[&>div]:bg-emerald-500'} />
            </div>
          ) : null}
          <div className="flex flex-wrap gap-2">
            {h.primary_phone ? (
              <Button size="sm" variant="outline" asChild>
                <a href={`tel:${h.primary_phone}`}><Phone className="me-1 h-4 w-4" />{t('quick.call')}</a>
              </Button>
            ) : null}
            {canDo('message') && h.primary_phone ? (
              <Button size="sm" variant="outline" onClick={() => open('message', { channel: 'sms', to: String(h.primary_phone) })}><MessageSquare className="me-1 h-4 w-4" />{t('quick.sms')}</Button>
            ) : null}
            {canDo('message') && h.primary_email ? (
              <Button size="sm" variant="outline" onClick={() => open('message', { channel: 'email', to: String(h.primary_email) })}><Mail className="me-1 h-4 w-4" />{t('quick.email')}</Button>
            ) : null}
            {canDo('ticket') ? <Button size="sm" variant="outline" onClick={() => open('ticket')}><LifeBuoy className="me-1 h-4 w-4" />{t('quick.ticket')}</Button> : null}
            {canDo('invoice') ? <Button size="sm" onClick={() => open('invoice')}><CreditCard className="me-1 h-4 w-4" />{t('quick.invoice')}</Button> : null}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

export function HubAlerts() {
  const t = useTranslations('crm.hub');
  const { summary } = useHub();
  const alerts = (summary.alerts ?? []).filter((a) => a.kind === 'risk');
  if (!alerts.length) return null;
  return (
    <div className="flex flex-wrap gap-2">
      {alerts.map((a, i) => (
        <div
          key={String(a.id ?? i)}
          className={`flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs ${a.severity === 'critical' ? 'border-destructive/40 bg-destructive/10 text-destructive' : 'border-amber-400/40 bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300'}`}
        >
          <AlertTriangle className="h-3.5 w-3.5" />
          <span>{a.code ? t(`alert.${a.code}` as never) : String(a.body)}</span>
          {a.value !== undefined && a.value !== null && typeof a.value !== 'object' ? <span dir="ltr">({String(a.value)})</span> : null}
          {a.days !== undefined ? <span>{t('l.daysLeft', { days: Number(a.days) })}</span> : null}
        </div>
      ))}
    </div>
  );
}

export function HubKpis() {
  const t = useTranslations('crm.hub');
  const f = useFmt();
  const { summary } = useHub();
  const k = summary.kpis;
  const hidden = <span className="text-sm text-muted-foreground">{t('kpi.hidden')}</span>;
  const money = (v: unknown) => (v === null || v === undefined ? hidden : f.money(v));
  const cards = [
    { icon: TrendingUp, label: t('kpi.lifetime'), value: money(k.lifetime_revenue) },
    { icon: Banknote, label: t('kpi.revenue12m'), value: money(k.revenue_12m) },
    { icon: Wallet, label: t('kpi.balance'), value: money(k.balance), sub: k.wallet_credit !== null && k.wallet_credit !== undefined ? `${t('b.generalWallet')}: ${f.money(k.wallet_credit)}` : undefined },
    { icon: Globe, label: t('kpi.sites'), value: `${f.num(k.active_sites)} / ${f.num(k.domains)}` },
    { icon: KeyRound, label: t('kpi.licenses'), value: f.num(k.active_licenses) },
    { icon: LifeBuoy, label: t('kpi.tickets'), value: f.num(k.open_tickets) },
    { icon: CreditCard, label: t('kpi.lastPayment'), value: k.last_payment ? f.money(k.last_payment.amount) : k.lifetime_revenue === null ? hidden : '—', sub: k.last_payment ? f.date(k.last_payment.date) : undefined },
    { icon: CalendarClock, label: t('kpi.lastActivity'), value: f.date(k.last_activity_at) },
  ];
  return (
    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
      {cards.map((c) => (
        <Card key={c.label}>
          <CardContent className="space-y-1 p-4">
            <div className="flex items-center gap-2 text-xs text-muted-foreground"><c.icon className="h-4 w-4" />{c.label}</div>
            <div className="text-lg font-semibold">{c.value}</div>
            {c.sub ? <div className="text-xs text-muted-foreground">{c.sub}</div> : null}
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

const BAR: ActionName[] = ['invoice', 'payment', 'license', 'ticket', 'project', 'message', 'domain', 'service', 'note', 'owner'];

export function HubActionBar() {
  const t = useTranslations('crm.hub');
  const { summary, canDo, open } = useHub();
  const blocked = summary.header.status === 'blocked';
  const items = BAR.filter((a) => canDo(a));
  if (!items.length && !canDo('suspend')) return null;
  return (
    <div className="flex flex-wrap gap-2 rounded-lg border bg-muted/30 p-2">
      <span className="self-center px-2 text-xs font-medium text-muted-foreground">{t('act.title')}:</span>
      {items.map((a) => (
        <Button key={a} size="sm" variant="secondary" onClick={() => open(a, a === 'message' && summary.header.primary_phone ? { channel: 'sms', to: String(summary.header.primary_phone) } : undefined)}>
          {t(`act.${a}` as never)}
        </Button>
      ))}
      {canDo('suspend') ? (
        blocked ? (
          <Button size="sm" variant="default" onClick={() => open('activate')}>{t('act.activate')}</Button>
        ) : (
          <Button size="sm" variant="destructive" onClick={() => open('suspend')}>{t('act.suspend')}</Button>
        )
      ) : null}
    </div>
  );
}
