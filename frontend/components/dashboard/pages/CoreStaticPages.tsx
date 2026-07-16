'use client';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Progress } from '@/components/ui/progress';
import { getCurrentUser } from '@/lib/auth';
import { normalizeListPayload } from '@/lib/list-utils';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreHorizontal } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Textarea } from '@/components/ui/textarea';
import { SettingsPageView } from '@/components/dashboard/pages/settings-view';

export { SettingsPageView };

export { ProfilePage as ProfilePageView, ReportsPage as ReportsPageView } from '@/features/modules/core/core_pages';

export function LogsPageView() {
  const [tab, setTab] = useState<'events' | 'system' | 'user' | 'bale'>('events');
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [page, setPage] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const endpoint =
    tab === 'system'
      ? '/v1/core/logs/system'
      : tab === 'user'
        ? '/v1/core/logs/user'
        : tab === 'bale'
          ? '/v1/core/logs'
          : '/v1/core/logs';

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string | number> = { limit: 80 };
      if (tab === 'bale') {
        params.type = 'bale';
      }
      const res = await apiClient.get(endpoint, { params });
      const data = unwrapData<unknown>(res);
      const list = Array.isArray(data) ? data : normalizeListPayload(data);
      setRows(list);
    } catch (e) {
      setError(getAxiosMessage(e));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [endpoint, tab]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    setPage(1);
  }, [tab]);

  const pageSize = 15;
  const slice = rows.slice((page - 1) * pageSize, page * pageSize);
  const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));

  const cols =
    slice[0] && typeof slice[0] === 'object'
      ? Object.keys(slice[0] as object).filter((k) => k !== 'context')
      : ['id', 'level', 'message', 'created_at'];

  return (
    <Card>
      <CardHeader>
        <CardTitle>لاگ‌ها</CardTitle>
        <CardDescription>رویدادها، سیستم، کاربر، بله</CardDescription>
      </CardHeader>
      <CardContent>
        <Tabs value={tab} onValueChange={(v) => setTab(v as typeof tab)}>
          <TabsList>
            <TabsTrigger value="events">رویدادها</TabsTrigger>
            <TabsTrigger value="system">سیستم</TabsTrigger>
            <TabsTrigger value="user">کاربر</TabsTrigger>
            <TabsTrigger value="bale">بله</TabsTrigger>
          </TabsList>
        </Tabs>
        <p className="mt-2 text-xs text-muted-foreground" dir="ltr">
          GET {endpoint}
          {tab === 'bale' ? '?type=bale' : ''}
        </p>
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <div className="mt-3 overflow-x-auto rounded-md border">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b bg-muted/40">
                {cols.map((c) => (
                  <th key={c} className="px-2 py-2 text-start font-medium">
                    {c}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={cols.length} className="py-6 text-center text-muted-foreground">
                    …
                  </td>
                </tr>
              ) : (
                slice.map((r, i) => (
                  <tr key={i} className="border-b border-border/60">
                    {cols.map((c) => (
                      <td key={c} className="max-w-[200px] truncate px-2 py-1" dir="ltr">
                        {formatCell((r as Record<string, unknown>)[c])}
                      </td>
                    ))}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <div className="mt-3 flex items-center justify-between gap-2 text-sm">
          <span className="text-muted-foreground">
            {rows.length} ردیف — صفحه {page} / {pageCount}
          </span>
          <div className="flex gap-1">
            <Button type="button" size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              قبلی
            </Button>
            <Button type="button" size="sm" variant="outline" disabled={page >= pageCount} onClick={() => setPage((p) => p + 1)}>
              بعدی
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function formatCell(v: unknown): string {
  if (v === null || v === undefined) {
    return '';
  }
  if (typeof v === 'object') {
    return JSON.stringify(v).slice(0, 120);
  }
  return String(v);
}

type LicenseRow = {
  id?: number;
  license_key?: string;
  domain?: string;
  status?: string;
  expires_at?: string;
  created_at?: string;
  max_users?: number;
  meta?: Record<string, unknown> | null;
};

function licenseModulesSummary(lic: LicenseRow): string {
  const m = lic.meta;
  if (!m || typeof m !== 'object') {
    return '—';
  }
  const mods = (m.modules ?? m.licensed_modules) as unknown;
  if (Array.isArray(mods)) {
    return mods.filter((x) => typeof x === 'string').join(', ');
  }
  return '—';
}

function licenseProgress(lic: LicenseRow): number {
  if (!lic.expires_at) {
    return 0;
  }
  const exp = new Date(String(lic.expires_at)).getTime();
  const start = lic.created_at ? new Date(String(lic.created_at)).getTime() : exp - 365 * 86400000;
  const now = Date.now();
  if (exp <= start) {
    return 50;
  }
  const t = (exp - now) / (exp - start);
  return Math.max(0, Math.min(100, Math.round(t * 100)));
}

export function LicensesPageView() {
  const [rows, setRows] = useState<LicenseRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const [addOpen, setAddOpen] = useState(false);
  const [renewId, setRenewId] = useState<number | null>(null);
  const [cancelId, setCancelId] = useState<number | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);

  const [editMetaRow, setEditMetaRow] = useState<LicenseRow | null>(null);
  const [metaModules, setMetaModules] = useState('');
  const [metaVertical, setMetaVertical] = useState('');
  const [metaSku, setMetaSku] = useState('');
  const [metaModuleReposJson, setMetaModuleReposJson] = useState('[]');
  const [metaGitJson, setMetaGitJson] = useState('');
  const [metaErr, setMetaErr] = useState<string | null>(null);

  const [formKey, setFormKey] = useState('');
  const [formDomain, setFormDomain] = useState('');
  const [formErr, setFormErr] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/v1/core/licenses');
      setRows(normalizeListPayload(unwrapData<unknown>(res)) as LicenseRow[]);
      setError(null);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function addLicense() {
    setFormErr(null);
    try {
      await apiClient.post('/v1/core/licenses', {
        license_key: formKey,
        domain: formDomain || null,
        status: 'active',
      });
      setAddOpen(false);
      setFormKey('');
      setFormDomain('');
      void load();
    } catch (e) {
      setFormErr(getAxiosMessage(e));
    }
  }

  async function renew() {
    if (!renewId) {
      return;
    }
    try {
      await apiClient.post(`/v1/core/licenses/${renewId}/renew`);
      setRenewId(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function cancelLic() {
    if (!cancelId) {
      return;
    }
    try {
      await apiClient.post(`/v1/core/licenses/${cancelId}/cancel`);
      setCancelId(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  function openEditMeta(lic: LicenseRow) {
    setMetaErr(null);
    setEditMetaRow(lic);
    const meta = (lic.meta ?? {}) as Record<string, unknown>;
    const mods = meta.modules ?? meta.licensed_modules;
    if (Array.isArray(mods)) {
      setMetaModules(mods.filter((x) => typeof x === 'string').join(', '));
    } else {
      setMetaModules('');
    }
    setMetaVertical(typeof meta.vertical === 'string' ? meta.vertical : '');
    setMetaSku(typeof meta.sku === 'string' ? meta.sku : '');
    const repos = meta.module_repos;
    try {
      setMetaModuleReposJson(JSON.stringify(Array.isArray(repos) ? repos : [], null, 2));
    } catch {
      setMetaModuleReposJson('[]');
    }
    const git = meta.git;
    try {
      setMetaGitJson(git && typeof git === 'object' ? JSON.stringify(git, null, 2) : '');
    } catch {
      setMetaGitJson('');
    }
  }

  async function saveMeta() {
    if (!editMetaRow?.id) {
      return;
    }
    setMetaErr(null);
    let moduleRepos: unknown = [];
    try {
      moduleRepos = JSON.parse(metaModuleReposJson || '[]') as unknown;
    } catch {
      setMetaErr('module_repos JSON نامعتبر است.');
      return;
    }
    let gitVal: unknown = undefined;
    if (metaGitJson.trim()) {
      try {
        gitVal = JSON.parse(metaGitJson) as unknown;
      } catch {
        setMetaErr('git JSON نامعتبر است.');
        return;
      }
    }
    const modules = metaModules
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);
    const metaPayload: Record<string, unknown> = {};
    if (modules.length) {
      metaPayload.modules = modules;
    }
    if (metaVertical.trim()) {
      metaPayload.vertical = metaVertical.trim();
    }
    if (metaSku.trim()) {
      metaPayload.sku = metaSku.trim();
    }
    if (Array.isArray(moduleRepos) && moduleRepos.length > 0) {
      metaPayload.module_repos = moduleRepos;
    }
    if (gitVal !== undefined && gitVal !== null && typeof gitVal === 'object') {
      metaPayload.git = gitVal;
    }
    try {
      await apiClient.patch(`/v1/core/licenses/${editMetaRow.id}`, {
        replace_meta: true,
        meta: metaPayload,
      });
      setEditMetaRow(null);
      void load();
    } catch (e) {
      setMetaErr(getAxiosMessage(e));
    }
  }

  async function destroyLic() {
    if (!deleteId) {
      return;
    }
    try {
      await apiClient.delete(`/v1/core/licenses/${deleteId}`);
      setDeleteId(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button type="button" size="sm" onClick={() => setAddOpen(true)}>
          افزودن لایسنس
        </Button>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {loading ? <p className="text-sm text-muted-foreground">بارگذاری…</p> : null}
        {error ? <p className="text-destructive">{error}</p> : null}
        {rows.map((lic) => (
          <Card key={String(lic.id ?? lic.license_key)}>
            <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
              <div>
                <CardTitle className="text-base font-mono">{String(lic.domain ?? lic.license_key ?? '—')}</CardTitle>
                <CardDescription>{String(lic.status ?? '')}</CardDescription>
              </div>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button type="button" variant="ghost" size="icon" aria-label="actions">
                    <MoreHorizontal className="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onClick={() => openEditMeta(lic)}>ویرایش meta</DropdownMenuItem>
                  <DropdownMenuItem onClick={() => lic.id && setRenewId(lic.id)}>تمدید</DropdownMenuItem>
                  <DropdownMenuItem onClick={() => lic.id && setCancelId(lic.id)}>لغو</DropdownMenuItem>
                  <DropdownMenuItem className="text-destructive" onClick={() => lic.id && setDeleteId(lic.id)}>
                    حذف
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </CardHeader>
            <CardContent className="space-y-2">
              <p className="text-xs text-muted-foreground">کلید: {String(lic.license_key ?? '—')}</p>
              <p className="text-xs text-muted-foreground">انقضا: {String(lic.expires_at ?? '—')}</p>
              <p className="text-xs text-muted-foreground line-clamp-2">ماژول‌ها: {licenseModulesSummary(lic)}</p>
              <Progress value={licenseProgress(lic)} />
            </CardContent>
          </Card>
        ))}
        {!rows.length && !loading ? <p className="text-sm text-muted-foreground">لاینسسی ثبت نشده</p> : null}
      </div>

      <Dialog open={editMetaRow !== null} onOpenChange={(o) => !o && setEditMetaRow(null)}>
        <DialogContent className="max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>قرارداد meta لایسنس</DialogTitle>
          </DialogHeader>
          <div className="space-y-2 text-sm">
            <p className="text-xs text-muted-foreground">ماژول‌ها (با ویرگول)</p>
            <Input value={metaModules} onChange={(e) => setMetaModules(e.target.value)} dir="ltr" className="font-mono text-xs" />
            <p className="text-xs text-muted-foreground">vertical</p>
            <Input value={metaVertical} onChange={(e) => setMetaVertical(e.target.value)} dir="ltr" className="font-mono text-xs" />
            <p className="text-xs text-muted-foreground">sku</p>
            <Input value={metaSku} onChange={(e) => setMetaSku(e.target.value)} dir="ltr" className="font-mono text-xs" />
            <p className="text-xs text-muted-foreground">module_repos (JSON آرایه)</p>
            <Textarea value={metaModuleReposJson} onChange={(e) => setMetaModuleReposJson(e.target.value)} rows={6} dir="ltr" className="font-mono text-xs" />
            <p className="text-xs text-muted-foreground">git (آبجکت تکی، اختیاری — خالی برای حذف)</p>
            <Textarea value={metaGitJson} onChange={(e) => setMetaGitJson(e.target.value)} rows={5} dir="ltr" className="font-mono text-xs" />
            {metaErr ? <p className="text-sm text-destructive">{metaErr}</p> : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="secondary" onClick={() => setEditMetaRow(null)}>
              انصراف
            </Button>
            <Button type="button" onClick={() => void saveMeta()}>
              ذخیره (replace_meta)
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={addOpen} onOpenChange={setAddOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>لایسنس جدید</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Input placeholder="کلید لایسنس" value={formKey} onChange={(e) => setFormKey(e.target.value)} dir="ltr" />
            <Input placeholder="دامنه" value={formDomain} onChange={(e) => setFormDomain(e.target.value)} dir="ltr" />
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
          </div>
          <DialogFooter>
            <Button type="button" onClick={() => void addLicense()}>
              ثبت
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={renewId !== null} onOpenChange={(o) => !o && setRenewId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>تمدید یک سال؟</AlertDialogTitle>
            <AlertDialogDescription>تاریخ انقضا به‌روز می‌شود.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>خیر</AlertDialogCancel>
            <AlertDialogAction onClick={() => void renew()}>بله</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={cancelId !== null} onOpenChange={(o) => !o && setCancelId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>لغو لایسنس؟</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>خیر</AlertDialogCancel>
            <AlertDialogAction onClick={() => void cancelLic()}>بله</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف قطعی؟</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>خیر</AlertDialogCancel>
            <AlertDialogAction onClick={() => void destroyLic()}>حذف</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

type VisitorPayload = {
  total_visits?: number;
  unique_visitors?: number;
  period_days?: number;
  bounce_rate?: number;
  visits_by_day?: { day?: string; visits?: number }[];
  top_pages?: { path?: string; visits?: number }[];
  recent_visits?: Record<string, unknown>[];
  browsers?: { name: string; count: number }[];
  os?: { name: string; count: number }[];
  devices?: { name: string; count: number }[];
};

export function VisitorStatsPageView() {
  const [days, setDays] = useState(14);
  const [data, setData] = useState<VisitorPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/v1/core/visitor-stats', { params: { days } });
      setData(unwrapData<VisitorPayload>(res));
      setError(null);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [days]);

  useEffect(() => {
    void load();
  }, [load]);

  const byDay = data?.visits_by_day ?? [];
  const maxV = Math.max(1, ...byDay.map((d) => Number(d.visits ?? 0)));

  return (
    <Card>
      <CardHeader>
        <CardTitle>آمار بازدید</CardTitle>
        <CardDescription>رویدادهای core_visitor_events</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-end gap-3">
          <div>
            <p className="mb-1 text-xs text-muted-foreground">بازه (روز)</p>
            <Input
              type="number"
              min={1}
              max={90}
              className="w-24"
              value={days}
              onChange={(e) => setDays(Number(e.target.value) || 7)}
              dir="ltr"
            />
          </div>
          <Button type="button" onClick={() => void load()} disabled={loading}>
            اعمال
          </Button>
        </div>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-sm text-muted-foreground">کل بازدید</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
              {loading ? '…' : data?.total_visits ?? '—'}
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-sm text-muted-foreground">بازدیدکنندهٔ یکتا (IP)</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
              {loading ? '…' : data?.unique_visitors ?? '—'}
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-sm text-muted-foreground">نرخ پرش (تقریبی)</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
              {loading ? '…' : data?.bounce_rate != null ? `${data.bounce_rate}%` : '—'}
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-sm text-muted-foreground">دوره</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
              {loading ? '…' : data?.period_days ?? '—'} روز
            </CardContent>
          </Card>
        </div>

        <div>
          <p className="mb-2 text-sm font-medium">بازدید روزانه</p>
          <div className="flex h-36 items-end gap-1 overflow-x-auto">
            {byDay.map((d, i) => (
              <div key={String(d.day ?? i)} className="flex min-w-[20px] flex-1 flex-col items-center gap-1">
                <div
                  className="w-full max-w-[24px] rounded-t bg-primary/80"
                  style={{ height: `${Math.max(4, (Number(d.visits ?? 0) / maxV) * 120)}px` }}
                />
                <span className="max-w-[48px] truncate text-[10px] text-muted-foreground" dir="ltr">
                  {String(d.day ?? '').slice(5)}
                </span>
              </div>
            ))}
          </div>
        </div>

        <div className="grid gap-4 lg:grid-cols-2">
          <div>
            <p className="mb-2 text-sm font-medium">صفحات پربازدید</p>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-xs">
                <thead>
                  <tr className="border-b bg-muted/40">
                    <th className="px-2 py-2 text-start">مسیر</th>
                    <th className="px-2 py-2 text-start">بازدید</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.top_pages ?? []).map((r, i) => (
                    <tr key={i} className="border-b">
                      <td className="max-w-xs truncate px-2 py-1" dir="ltr">
                        {String(r.path ?? '—')}
                      </td>
                      <td className="px-2 py-1">{String(r.visits ?? '')}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <div>
            <p className="mb-2 text-sm font-medium">آخرین بازدیدها</p>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-xs">
                <thead>
                  <tr className="border-b bg-muted/40">
                    <th className="px-2 py-2 text-start">زمان</th>
                    <th className="px-2 py-2 text-start">مسیر</th>
                  </tr>
                </thead>
                <tbody>
                  {(data?.recent_visits ?? []).map((r, i) => (
                    <tr key={i} className="border-b">
                      <td className="px-2 py-1" dir="ltr">
                        {String(r.visited_at ?? r.created_at ?? '—')}
                      </td>
                      <td className="max-w-[200px] truncate px-2 py-1" dir="ltr">
                        {String(r.path ?? '—')}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div className="grid gap-4 lg:grid-cols-3">
          <div>
            <p className="mb-2 text-sm font-medium">مرورگرها</p>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-xs">
                <thead><tr className="border-b bg-muted/40"><th className="px-2 py-2 text-start">مرورگر</th><th className="px-2 py-2 text-start">تعداد</th></tr></thead>
                <tbody>
                  {(data?.browsers ?? []).map((r, i) => (
                    <tr key={i} className="border-b"><td className="px-2 py-1">{String(r.name)}</td><td className="px-2 py-1">{String(r.count)}</td></tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <div>
            <p className="mb-2 text-sm font-medium">سیستم‌عامل</p>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-xs">
                <thead><tr className="border-b bg-muted/40"><th className="px-2 py-2 text-start">سیستم‌عامل</th><th className="px-2 py-2 text-start">تعداد</th></tr></thead>
                <tbody>
                  {(data?.os ?? []).map((r, i) => (
                    <tr key={i} className="border-b"><td className="px-2 py-1">{String(r.name)}</td><td className="px-2 py-1">{String(r.count)}</td></tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <div>
            <p className="mb-2 text-sm font-medium">دستگاه‌ها</p>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-xs">
                <thead><tr className="border-b bg-muted/40"><th className="px-2 py-2 text-start">دستگاه</th><th className="px-2 py-2 text-start">تعداد</th></tr></thead>
                <tbody>
                  {(data?.devices ?? []).map((r, i) => (
                    <tr key={i} className="border-b"><td className="px-2 py-1">{String(r.name)}</td><td className="px-2 py-1">{String(r.count)}</td></tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {error ? <p className="text-destructive text-sm">{error}</p> : null}
      </CardContent>
    </Card>
  );
}
