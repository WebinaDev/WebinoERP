'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Bell, BellOff, Clock, Lock, Mail, MessageSquare, MonitorSmartphone, Send, Smartphone } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { getAxiosMessage } from '@/lib/api-helpers';
import { getNotificationMatrix, saveNotificationMatrix, type NotificationMatrix } from '@/lib/api/users';

const CHANNEL_ICONS: Record<string, React.ReactNode> = {
  in_app: <Bell className="size-4" />,
  sms: <Smartphone className="size-4" />,
  email: <Mail className="size-4" />,
  push: <MonitorSmartphone className="size-4" />,
  chat: <Send className="size-4" />,
};

/** Per-person channel and category switches. `scope` is a user id (admin) or "me". */
export function NotificationPreferencesPanel({ scope }: { scope: number | string | 'me' }) {
  const t = useTranslations('userAdmin');
  const [matrix, setMatrix] = useState<NotificationMatrix | null>(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    void getNotificationMatrix(scope).then(setMatrix).catch((e) => toast.error(getAxiosMessage(e)));
  }, [scope]);

  if (!matrix) return <p className="text-sm text-muted-foreground">{t('loading')}</p>;

  const channels = Object.keys(matrix.channels);

  function setChannel(c: string, v: boolean) {
    setMatrix((m) => (m ? { ...m, channels: { ...m.channels, [c]: v } } : m));
  }
  function setCategory(cat: string, patch: Partial<{ enabled: boolean; channels: Record<string, boolean> }>) {
    setMatrix((m) => {
      if (!m) return m;
      const cur = m.categories[cat]!;
      return { ...m, categories: { ...m.categories, [cat]: { ...cur, ...patch, channels: { ...cur.channels, ...(patch.channels ?? {}) } } } };
    });
  }

  async function save() {
    if (!matrix) return;
    setSaving(true);
    try {
      const next = await saveNotificationMatrix(scope, matrix);
      setMatrix(next);
      toast.success(t('notif.saved'));
    } catch (e) {
      toast.error(getAxiosMessage(e));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="space-y-4">
      <Card className="shadow-sm">
        <CardContent className="space-y-3 p-4">
          <div className="flex items-center gap-2">
            <MessageSquare className="size-4 text-primary" />
            <h3 className="text-sm font-semibold">{t('notif.channelsTitle')}</h3>
          </div>
          <p className="text-xs text-muted-foreground">{t('notif.channelsHint')}</p>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            {channels.map((c) => (
              <label key={c} className="flex items-center justify-between gap-3 rounded-lg border p-3">
                <span className="flex items-center gap-2 text-sm">
                  <span className="grid size-8 place-items-center rounded-md bg-primary/10 text-primary">{CHANNEL_ICONS[c] ?? <Bell className="size-4" />}</span>
                  {t(`notif.channels.${c}`)}
                </span>
                <Switch checked={matrix.channels[c]} onCheckedChange={(v) => setChannel(c, Boolean(v))} />
              </label>
            ))}
          </div>
        </CardContent>
      </Card>

      <Card className="shadow-sm">
        <CardContent className="space-y-3 p-4">
          <div className="flex items-center gap-2">
            <BellOff className="size-4 text-primary" />
            <h3 className="text-sm font-semibold">{t('notif.categoriesTitle')}</h3>
          </div>
          <p className="text-xs text-muted-foreground">{t('notif.categoriesHint')}</p>
          <div className="divide-y rounded-lg border">
            {Object.entries(matrix.categories).map(([cat, conf]) => (
              <div key={cat} className="flex flex-col gap-3 p-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex min-w-0 items-center gap-3">
                  {conf.locked ? (
                    <span className="flex items-center gap-1 text-xs text-muted-foreground"><Lock className="size-3.5" />{t('notif.locked')}</span>
                  ) : (
                    <Switch checked={conf.enabled} onCheckedChange={(v) => setCategory(cat, { enabled: Boolean(v) })} />
                  )}
                  <span className="text-sm font-medium">{t(`notif.categories.${cat}`)}</span>
                </div>
                <div className="flex flex-wrap gap-2">
                  {channels.map((c) => {
                    const on = conf.channels[c] && conf.enabled && matrix.channels[c];
                    return (
                      <button
                        key={c}
                        type="button"
                        disabled={conf.locked || !conf.enabled || !matrix.channels[c]}
                        onClick={() => setCategory(cat, { channels: { [c]: !conf.channels[c] } })}
                        className={`flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs transition ${on ? 'border-primary/40 bg-primary/10 text-primary' : 'text-muted-foreground'} disabled:cursor-not-allowed disabled:opacity-60`}
                      >
                        {CHANNEL_ICONS[c]}
                        {t(`notif.channels.${c}`)}
                      </button>
                    );
                  })}
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      <Card className="shadow-sm">
        <CardContent className="space-y-3 p-4">
          <div className="flex items-center gap-2">
            <Clock className="size-4 text-primary" />
            <h3 className="text-sm font-semibold">{t('notif.quietTitle')}</h3>
          </div>
          <p className="text-xs text-muted-foreground">{t('notif.quietHint')}</p>
          <div className="flex flex-wrap items-center gap-3">
            <label className="flex items-center gap-2 text-sm">
              {t('notif.from')}
              <Input type="time" dir="ltr" className="w-32" value={matrix.quiet_hours.start ?? ''} onChange={(e) => setMatrix({ ...matrix, quiet_hours: { ...matrix.quiet_hours, start: e.target.value || null } })} />
            </label>
            <label className="flex items-center gap-2 text-sm">
              {t('notif.to')}
              <Input type="time" dir="ltr" className="w-32" value={matrix.quiet_hours.end ?? ''} onChange={(e) => setMatrix({ ...matrix, quiet_hours: { ...matrix.quiet_hours, end: e.target.value || null } })} />
            </label>
          </div>
        </CardContent>
      </Card>

      <div className="flex justify-end">
        <Button type="button" disabled={saving} onClick={() => void save()}>{t('notif.save')}</Button>
      </div>
    </div>
  );
}
