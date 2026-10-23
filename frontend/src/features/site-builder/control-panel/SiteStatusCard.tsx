'use client';

import { useCallback, useEffect, useState } from 'react';
import { Clock, Power, ShieldOff, RefreshCw, Globe } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { getAxiosMessage } from '@/lib/api-helpers';
import {
  fetchSiteStatus,
  updateSiteStatus,
  type SiteLiveStatus,
  type SiteStatusPayload,
} from '@/lib/api/site-builder';

const LABEL: Record<SiteLiveStatus, string> = {
  active: 'فعال',
  coming_soon: 'به‌زودی',
  suspended: 'تعلیق‌شده',
};

const BADGE: Record<SiteLiveStatus, string> = {
  active: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
  coming_soon: 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30',
  suspended: 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30',
};

const REASON_LABEL: Record<string, string> = {
  license_expired: 'پایان اعتبار لایسنس',
};

function fmt(iso: string | null | undefined) {
  if (!iso) return '—';
  try {
    return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
  } catch {
    return iso;
  }
}

export function SiteStatusCard({ provisionId, ready }: { provisionId: number; ready: boolean }) {
  const [data, setData] = useState<SiteStatusPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [target, setTarget] = useState<SiteLiveStatus | null>(null);
  const [launchAt, setLaunchAt] = useState('');
  const [reason, setReason] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setData(await fetchSiteStatus(provisionId));
    } catch (e) {
      setError(getAxiosMessage(e) || 'خطا در دریافت وضعیت');
    } finally {
      setLoading(false);
    }
  }, [provisionId]);

  useEffect(() => {
    if (ready) void load();
    else setLoading(false);
  }, [load, ready]);

  const confirm = async () => {
    if (!target) return;
    setBusy(true);
    setError(null);
    try {
      const next = await updateSiteStatus(provisionId, {
        status: target,
        reason: target === 'suspended' ? reason.trim() : null,
        launch_at: target === 'coming_soon' && launchAt ? new Date(launchAt).toISOString() : null,
      });
      setData(next);
      setTarget(null);
      setReason('');
      setLaunchAt('');
    } catch (e) {
      setError(getAxiosMessage(e) || 'تغییر وضعیت ناموفق بود');
    } finally {
      setBusy(false);
    }
  };

  const live = (data?.remote?.status ?? data?.status ?? null) as SiteLiveStatus | null;
  const reasonText = data?.remote?.reason ?? data?.reason;

  return (
    <Card variant="glass" className="border-border/70 overflow-hidden" dir="rtl" data-testid="control-site-status">
      <CardHeader className="border-b bg-muted/30 pb-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-start gap-3">
            <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
              <Globe className="size-5" />
            </div>
            <div>
              <CardTitle className="text-lg">وضعیت نمایش سایت</CardTitle>
              <CardDescription className="mt-1">
                فعال، صفحهٔ «به‌زودی» یا صفحهٔ «تعلیق» برای بازدیدکنندگان سایت
              </CardDescription>
            </div>
          </div>
          {live ? (
            <Badge variant="outline" className={BADGE[live]} data-testid="site-status-badge">
              {LABEL[live]}
            </Badge>
          ) : (
            <Badge variant="outline">نامشخص</Badge>
          )}
        </div>
      </CardHeader>
      <CardContent className="space-y-4 pt-5">
        {!ready ? (
          <p className="text-muted-foreground text-sm">تغییر وضعیت پس از آماده شدن سایت ممکن است.</p>
        ) : (
          <>
            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
              <dt className="text-muted-foreground">آخرین تغییر توسط</dt>
              <dd>{data?.remote?.updated_by === 'admin' || data?.updated_by === 'admin' ? 'مدیر سایت' : data?.updated_by ? 'پلتفرم' : '—'}</dd>
              {live === 'suspended' ? (
                <>
                  <dt className="text-muted-foreground">دلیل تعلیق</dt>
                  <dd>{reasonText ? (REASON_LABEL[reasonText] ?? reasonText) : '—'}</dd>
                </>
              ) : null}
              {live === 'coming_soon' ? (
                <>
                  <dt className="text-muted-foreground">تاریخ راه‌اندازی</dt>
                  <dd>{fmt(data?.remote?.launch_at ?? data?.launch_at)}</dd>
                </>
              ) : null}
              <dt className="text-muted-foreground">آخرین همگام‌سازی</dt>
              <dd>{fmt(data?.synced_at)}</dd>
            </dl>
            {data?.remote_error ? (
              <p className="rounded-lg bg-amber-500/10 p-2 text-xs text-amber-700 dark:text-amber-300">
                وضعیت زنده از سایت دریافت نشد؛ آخرین وضعیت ذخیره‌شده نمایش داده می‌شود. ({data.remote_error})
              </p>
            ) : null}
            {error ? <p className="text-destructive text-sm">{error}</p> : null}
            <div className="flex flex-wrap gap-2">
              <Button size="sm" variant={live === 'active' ? 'default' : 'outline'} disabled={busy || loading || live === 'active'} onClick={() => setTarget('active')}>
                <Power className="size-4" /> فعال
              </Button>
              <Button size="sm" variant={live === 'coming_soon' ? 'default' : 'outline'} disabled={busy || loading} onClick={() => setTarget('coming_soon')}>
                <Clock className="size-4" /> به‌زودی
              </Button>
              <Button size="sm" variant={live === 'suspended' ? 'destructive' : 'outline'} disabled={busy || loading || live === 'suspended'} onClick={() => setTarget('suspended')}>
                <ShieldOff className="size-4" /> تعلیق
              </Button>
              <Button size="sm" variant="ghost" disabled={loading} onClick={() => void load()}>
                <RefreshCw className={loading ? 'size-4 animate-spin' : 'size-4'} /> بروزرسانی
              </Button>
            </div>
          </>
        )}
      </CardContent>

      <Dialog open={target !== null} onOpenChange={(o) => !o && !busy && setTarget(null)}>
        <DialogContent dir="rtl">
          <DialogHeader>
            <DialogTitle>تغییر وضعیت به «{target ? LABEL[target] : ''}»</DialogTitle>
            <DialogDescription>
              {target === 'suspended'
                ? 'سایت برای بازدیدکنندگان بسته می‌شود و فقط پلتفرم می‌تواند تعلیق را بردارد.'
                : target === 'coming_soon'
                  ? 'بازدیدکنندگان صفحهٔ «به‌زودی» را می‌بینند.'
                  : 'سایت برای همهٔ بازدیدکنندگان باز می‌شود.'}
            </DialogDescription>
          </DialogHeader>
          {target === 'coming_soon' ? (
            <div className="space-y-2">
              <Label htmlFor="site-launch-at">تاریخ راه‌اندازی (اختیاری، برای شمارش معکوس)</Label>
              <Input id="site-launch-at" type="datetime-local" value={launchAt} onChange={(e) => setLaunchAt(e.target.value)} dir="ltr" />
            </div>
          ) : null}
          {target === 'suspended' ? (
            <div className="space-y-2">
              <Label htmlFor="site-suspend-reason">دلیل تعلیق</Label>
              <Textarea id="site-suspend-reason" value={reason} onChange={(e) => setReason(e.target.value)} maxLength={191} placeholder="مثلاً: عدم پرداخت" />
            </div>
          ) : null}
          <DialogFooter className="gap-2">
            <Button variant="outline" disabled={busy} onClick={() => setTarget(null)}>انصراف</Button>
            <Button
              variant={target === 'suspended' ? 'destructive' : 'default'}
              disabled={busy || (target === 'suspended' && reason.trim() === '')}
              onClick={() => void confirm()}
            >
              {busy ? 'در حال اعمال…' : 'تأیید'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
