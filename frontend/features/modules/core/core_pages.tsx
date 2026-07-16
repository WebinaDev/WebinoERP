'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { getCurrentUser } from '@/lib/auth';

export function ProfilePage() {
  const t = useTranslations('profile');
  const tCommon = useTranslations('common');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [locale, setLocale] = useState('fa');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    void (async () => {
      setLoading(true);
      const u = await getCurrentUser();
      if (u) {
        setName(u.name ?? '');
        setEmail(u.email ?? '');
      }
      try {
        const res = await apiClient.get('/v1/core/auth/user');
        const body = res.data as { data?: { user?: { phone?: string } } };
        const usr = body.data?.user;
        if (usr?.phone) setPhone(String(usr.phone));
      } catch { /* ignore */ }
      setLoading(false);
    })();
  }, []);

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setMsg(null);
    setError(null);
    try {
      const payload: Record<string, string> = { name, email, phone };
      if (password) {
        payload.password = password;
        payload.password_confirmation = passwordConfirmation;
      }
      await apiClient.patch('/v1/core/users/me', payload);
      await apiClient.patch('/v1/core/users/me/preferences', { preferences: { locale } });
      setMsg(t('saved'));
      setPassword('');
      setPasswordConfirmation('');
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  if (loading) return <p className="text-sm text-muted-foreground">{tCommon('loading')}</p>;

  const initials = name.split(' ').map((x) => x[0]).join('').slice(0, 2).toUpperCase();

  return (
    <form onSubmit={save} className="max-w-lg space-y-4 rounded-lg border bg-card p-6 shadow-sm">
      <div className="flex items-center gap-4">
        <Avatar className="h-16 w-16"><AvatarFallback>{initials || '?'}</AvatarFallback></Avatar>
        <div>
          <h2 className="text-sm font-medium">{t('title')}</h2>
          <p className="text-xs text-muted-foreground">{t('avatarHint')}</p>
        </div>
      </div>
      <div className="space-y-2">
        <label className="text-sm">{t('avatar')}</label>
        <input type="file" accept="image/*" className="block w-full text-sm" onChange={async (e) => {
          const file = e.target.files?.[0];
          if (!file) return;
          const fd = new FormData();
          fd.append('avatar', file);
          try {
            await apiClient.post('/v1/core/users/me/avatar', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
            setMsg(t('avatarUploaded'));
          } catch (err) {
            setError(getAxiosMessage(err));
          }
        }} />
      </div>
      <div className="space-y-2"><label className="text-sm">{t('name')}</label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
      <div className="space-y-2"><label className="text-sm">{t('email')}</label><Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
      <div className="space-y-2"><label className="text-sm">{t('phone')}</label><Input value={phone} onChange={(e) => setPhone(e.target.value)} dir="ltr" /></div>
      <div className="space-y-2">
        <label className="text-sm">{t('locale')}</label>
        <Select value={locale} onValueChange={setLocale}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="fa">{t('localeFa')}</SelectItem>
            <SelectItem value="en">{t('localeEn')}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2"><label className="text-sm">{t('newPassword')}</label><Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="new-password" /></div>
      <div className="space-y-2"><label className="text-sm">{t('confirmPassword')}</label><Input type="password" value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} autoComplete="new-password" /></div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {msg ? <p className="text-sm text-green-600">{msg}</p> : null}
      <Button type="submit">{t('save')}</Button>
    </form>
  );
}

export function ReportsPage() {
  const t = useTranslations('reports');
  const tCommon = useTranslations('common');
  const [from, setFrom] = useState<string | null>(null);
  const [to, setTo] = useState<string | null>(null);
  const [report, setReport] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/core/reports', { params: { from: from ?? undefined, to: to ?? undefined } });
      setReport(unwrapData<Record<string, unknown>>(res));
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [from, to]);

  const metrics = report ?? {};
  const range = metrics.range as { from?: string; to?: string } | undefined;
  const metricKeys = ['contracts_total', 'tasks_completed', 'leads_new', 'tickets_closed', 'sprints_started'] as const;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('title')}</CardTitle>
        <CardDescription>{t('description', { from: range?.from ?? '—', to: range?.to ?? '—' })}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap gap-3">
          <div className="w-48"><p className="mb-1 text-xs">{t('fromDate')}</p><LocaleDatePicker value={from} onChange={setFrom} /></div>
          <div className="w-48"><p className="mb-1 text-xs">{t('toDate')}</p><LocaleDatePicker value={to} onChange={setTo} /></div>
          <Button type="button" className="self-end" onClick={() => void load()} disabled={loading}>{t('load')}</Button>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" size="sm" onClick={async () => {
            try {
              const res = await apiClient.get('/v1/core/reports/export.csv', { params: { from: from ?? undefined, to: to ?? undefined }, responseType: 'blob' });
              const url = window.URL.createObjectURL(res.data as Blob);
              const a = document.createElement('a');
              a.href = url; a.download = 'reports.csv'; a.click();
              window.URL.revokeObjectURL(url);
            } catch (e) { setError(getAxiosMessage(e)); }
          }}>{t('downloadCsv')}</Button>
          <Button type="button" variant="secondary" size="sm" onClick={() => window.print()}>{t('printPdf')}</Button>
        </div>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-3">
          {metricKeys.map((key) => (
            <Card key={key}>
              <CardHeader className="py-3"><CardTitle className="text-sm text-muted-foreground">{t(`metrics.${key}`)}</CardTitle></CardHeader>
              <CardContent className="text-2xl font-semibold tabular-nums">{loading ? '…' : String(metrics[key] ?? tCommon('emptyValue'))}</CardContent>
            </Card>
          ))}
        </div>
        <Tabs defaultValue="overview">
          <TabsList>
            <TabsTrigger value="overview">{t('tabs.overview')}</TabsTrigger>
            <TabsTrigger value="finance">{t('tabs.finance')}</TabsTrigger>
            <TabsTrigger value="tasks">{t('tabs.tasks')}</TabsTrigger>
            <TabsTrigger value="tickets">{t('tabs.tickets')}</TabsTrigger>
            <TabsTrigger value="agile">{t('tabs.agile')}</TabsTrigger>
          </TabsList>
          <TabsContent value="overview">
            <div className="grid gap-3 sm:grid-cols-2">
              {Object.entries(metrics).filter(([k]) => k !== 'range').map(([k, v]) => (
                <div key={k} className="rounded border p-3">
                  <p className="text-xs text-muted-foreground">{k}</p>
                  <p className="text-lg font-semibold">{String(v ?? tCommon('emptyValue'))}</p>
                </div>
              ))}
            </div>
          </TabsContent>
          <TabsContent value="finance"><Card><CardHeader className="py-3"><CardTitle className="text-sm">{t('financeTab')}</CardTitle></CardHeader><CardContent className="text-2xl font-semibold tabular-nums">{String(metrics.contracts_total ?? tCommon('emptyValue'))}</CardContent></Card></TabsContent>
          <TabsContent value="tasks"><Card><CardHeader className="py-3"><CardTitle className="text-sm">{t('tasksTab')}</CardTitle></CardHeader><CardContent className="text-2xl font-semibold tabular-nums">{String(metrics.tasks_completed ?? tCommon('emptyValue'))}</CardContent></Card></TabsContent>
          <TabsContent value="tickets"><Card><CardHeader className="py-3"><CardTitle className="text-sm">{t('ticketsTab')}</CardTitle></CardHeader><CardContent className="text-2xl font-semibold tabular-nums">{String(metrics.tickets_closed ?? tCommon('emptyValue'))}</CardContent></Card></TabsContent>
          <TabsContent value="agile"><Card><CardHeader className="py-3"><CardTitle className="text-sm">{t('agileTab')}</CardTitle></CardHeader><CardContent className="text-2xl font-semibold tabular-nums">{String(metrics.sprints_started ?? tCommon('emptyValue'))}</CardContent></Card></TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  );
}
