'use client';

import { useEffect, useState } from 'react';
import { Bell } from 'lucide-react';
import { useTranslations } from 'next-intl';
import { useLocale } from '@/hooks/use-locale';
import { Button } from '@/components/ui/button';
import { expansionApi, type Row } from '@/features/expansion/api';

export function NotificationBell() {
  const t = useTranslations('expansion');
  const { formatNumber, formatDigits } = useLocale();
  const [open, setOpen] = useState(false);
  const [rows, setRows] = useState<Row[]>([]);

  useEffect(() => {
    let stop = false;
    async function load() {
      try {
        const feed = await expansionApi.feed();
        if (!stop) setRows(feed.notifications ?? []);
      } catch {
        /* bell stays quiet when the feed is unavailable */
      }
    }
    void load();
    const timer = window.setInterval(() => void load(), 60_000);
    return () => {
      stop = true;
      window.clearInterval(timer);
    };
  }, []);

  const unread = rows.filter((row) => !row.is_read).length;

  return (
    <div className="relative">
      <Button type="button" variant="outline" size="icon" aria-label={t('bell.label')} onClick={() => setOpen((value) => !value)}>
        <Bell className="size-4" />
        {unread > 0 ? (
          <span className="absolute -top-1 end-0 min-w-4 rounded-full bg-primary px-1 text-[10px] leading-4 text-primary-foreground">
            {unread > 9 ? formatDigits('9+') : formatNumber(unread)}
          </span>
        ) : null}
      </Button>
      {open ? (
        <div className="absolute end-0 z-40 mt-2 w-80 rounded-lg border border-border bg-popover p-2 text-popover-foreground shadow-md">
          <p className="px-2 py-1 text-sm font-medium">{t('bell.title')}</p>
          <div className="max-h-72 space-y-1 overflow-auto">
            {rows.length === 0 ? <p className="px-2 py-3 text-sm text-muted-foreground">{t('bell.empty')}</p> : null}
            {rows.slice(0, 12).map((row) => {
              const data = (row.data && typeof row.data === 'object' ? row.data : {}) as Row;
              return (
                <div key={String(row.id)} className="rounded-md px-2 py-2 text-sm hover:bg-muted">
                  <p className="font-medium">{String(data.title || row.type || t('bell.item'))}</p>
                  {data.body ? <p className="text-xs text-muted-foreground">{String(data.body)}</p> : null}
                </div>
              );
            })}
          </div>
        </div>
      ) : null}
    </div>
  );
}
