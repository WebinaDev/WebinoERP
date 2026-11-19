'use client';

import { useLocale, useTranslations } from 'next-intl';
import { History, Laptop, LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDate } from '@/lib/locale/format-date';
import type { ActivityRow, UserSession } from '@/lib/api/users';

function when(iso: string | null | undefined, locale: string) {
  if (!iso) return '-';
  try {
    return formatDate(iso, { locale, includeTime: true });
  } catch {
    return iso;
  }
}

export function SessionsList({ rows, onRevoke, onRevokeAll, revokeAllLabel }: {
  rows: UserSession[] | null;
  onRevoke: (id: number) => void;
  onRevokeAll?: () => void;
  revokeAllLabel?: string;
}) {
  const t = useTranslations('userAdmin');
  const locale = useLocale();
  return (
    <Card className="shadow-sm">
      <CardContent className="space-y-3 p-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h3 className="flex items-center gap-2 text-sm font-semibold"><Laptop className="size-4 text-primary" />{t('sessions.title')}</h3>
          {onRevokeAll && rows && rows.length > 0 ? (
            <Button type="button" size="sm" variant="outline" className="text-destructive" onClick={onRevokeAll}><LogOut className="size-3.5" />{revokeAllLabel ?? t('sessions.revokeAll')}</Button>
          ) : null}
        </div>
        {rows === null ? <p className="text-sm text-muted-foreground">{t('loading')}</p> : null}
        {rows && rows.length === 0 ? <p className="text-sm text-muted-foreground">{t('sessions.empty')}</p> : null}
        <div className="divide-y rounded-lg border">
          {(rows ?? []).map((s) => (
            <div key={s.id} className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="min-w-0 text-sm">
                <p className="truncate font-medium">{s.device_name || s.name}</p>
                <p className="truncate text-xs text-muted-foreground" dir="ltr">{s.ip ?? ''} {s.user_agent ? `· ${s.user_agent.slice(0, 80)}` : ''}</p>
                <p className="text-xs text-muted-foreground">{t('sessions.lastActive')}: {when(s.last_activity_at ?? s.last_used_at ?? s.created_at, locale)}</p>
              </div>
              <Button type="button" size="sm" variant="outline" onClick={() => onRevoke(s.id)}>{t('sessions.revoke')}</Button>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}

export function ActivityList({ rows }: { rows: ActivityRow[] | null }) {
  const t = useTranslations('userAdmin');
  const locale = useLocale();
  const label = (a: string) => {
    const key = `activity.actions.${a.replace(/\./g, '_')}`;
    return t.has(key) ? t(key) : a;
  };
  return (
    <Card className="shadow-sm">
      <CardContent className="p-4">
        {rows === null ? <p className="text-sm text-muted-foreground">{t('loading')}</p> : null}
        {rows && rows.length === 0 ? <p className="text-sm text-muted-foreground">{t('activity.empty')}</p> : null}
        <ol className="relative space-y-4 border-s ps-5">
          {(rows ?? []).map((a) => (
            <li key={a.id} className="relative">
              <span className="absolute -start-[1.65rem] top-0.5 grid size-5 place-items-center rounded-full bg-primary/10 text-primary"><History className="size-3" /></span>
              <p className="text-sm font-medium">{label(a.action)}</p>
              <p className="text-xs text-muted-foreground">
                {when(a.created_at, locale)} · {a.actor ? t('activity.by', { name: a.actor.name }) : t('activity.system')}
              </p>
            </li>
          ))}
        </ol>
      </CardContent>
    </Card>
  );
}
