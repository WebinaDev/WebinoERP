'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Copy, MoreHorizontal } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { useLocale } from '@/hooks/use-locale-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { cn } from '@/lib/utils';

type LicenseRow = {
  id?: number;
  license_key?: string;
  project_name?: string | null;
  domain?: string | null;
  logo_url?: string | null;
  status?: string;
  start_date?: string | null;
  expires_at?: string | null;
  created_at?: string;
  max_users?: number;
  meta?: Record<string, unknown> | null;
};

const SITE_TYPES = ['ecommerce', 'magazine', 'cafe', 'resume', 'corporate'] as const;

function licenseModulesSummary(lic: LicenseRow): string {
  const m = lic.meta;
  if (!m || typeof m !== 'object') return '—';
  const mods = (m.modules ?? m.licensed_modules) as unknown;
  if (Array.isArray(mods)) return mods.filter((x) => typeof x === 'string').join(', ');
  return '—';
}

function remainingDays(lic: LicenseRow): number | null {
  if (!lic.expires_at) return null;
  const exp = new Date(String(lic.expires_at)).getTime();
  return Math.ceil((exp - Date.now()) / 86400000);
}

function licenseProgress(lic: LicenseRow): number {
  if (!lic.expires_at) return 100;
  const exp = new Date(String(lic.expires_at)).getTime();
  const start = lic.start_date
    ? new Date(String(lic.start_date)).getTime()
    : lic.created_at
      ? new Date(String(lic.created_at)).getTime()
      : exp - 365 * 86400000;
  if (exp <= start) return 50;
  const t = (exp - Date.now()) / (exp - start);
  return Math.max(0, Math.min(100, Math.round(t * 100)));
}

function cardTone(lic: LicenseRow): string {
  const days = remainingDays(lic);
  if (lic.status === 'cancelled' || lic.status === 'revoked' || lic.status === 'inactive') {
    return 'border-muted-foreground/40';
  }
  if (days === null) return 'border-emerald-500/60';
  if (days < 0) return 'border-destructive';
  if (days <= 30) return 'border-amber-500';
  return 'border-emerald-500/60';
}

export function LicensesPageView() {
  const t = useTranslations();
  const tl = useTranslations('licenses');
  const { formatDate } = useLocale();

  const [rows, setRows] = useState<LicenseRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const [addOpen, setAddOpen] = useState(false);
  const [createdKey, setCreatedKey] = useState<string | null>(null);
  const [renewId, setRenewId] = useState<number | null>(null);
  const [renewDate, setRenewDate] = useState('');
  const [cancelId, setCancelId] = useState<number | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);

  const [editMetaRow, setEditMetaRow] = useState<LicenseRow | null>(null);
  const [metaModules, setMetaModules] = useState('');
  const [metaSiteType, setMetaSiteType] = useState('');
  const [metaSku, setMetaSku] = useState('');
  const [metaErr, setMetaErr] = useState<string | null>(null);

  const [formProject, setFormProject] = useState('');
  const [formDomain, setFormDomain] = useState('');
  const [formLogo, setFormLogo] = useState('');
  const [formStart, setFormStart] = useState('');
  const [formExpires, setFormExpires] = useState('');
  const [formStatus, setFormStatus] = useState('active');
  const [formSiteType, setFormSiteType] = useState('corporate');
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

  const todayIso = useMemo(() => new Date().toISOString().slice(0, 10), []);

  async function addLicense() {
    setFormErr(null);
    try {
      const res = await apiClient.post('/v1/core/licenses', {
        project_name: formProject,
        domain: formDomain,
        logo_url: formLogo || null,
        start_date: formStart || todayIso,
        expires_at: formExpires || null,
        status: formStatus,
        site_type: formSiteType || null,
      });
      const created = unwrapData<LicenseRow>(res);
      setAddOpen(false);
      setFormProject('');
      setFormDomain('');
      setFormLogo('');
      setFormStart('');
      setFormExpires('');
      setFormStatus('active');
      setFormSiteType('corporate');
      setCreatedKey(String(created?.license_key ?? ''));
      void load();
    } catch (e) {
      setFormErr(getAxiosMessage(e));
    }
  }

  async function renew() {
    if (!renewId) return;
    try {
      await apiClient.post(`/v1/core/licenses/${renewId}/renew`, {
        expires_at: renewDate || undefined,
      });
      setRenewId(null);
      setRenewDate('');
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function cancelLic() {
    if (!cancelId) return;
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
    setMetaModules(Array.isArray(mods) ? mods.filter((x) => typeof x === 'string').join(', ') : '');
    setMetaSiteType(typeof meta.site_type === 'string' ? meta.site_type : typeof meta.vertical === 'string' ? meta.vertical : '');
    setMetaSku(typeof meta.sku === 'string' ? meta.sku : '');
  }

  async function saveMeta() {
    if (!editMetaRow?.id) return;
    setMetaErr(null);
    const modules = metaModules
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);
    const metaPayload: Record<string, unknown> = {};
    if (modules.length) metaPayload.modules = modules;
    if (metaSiteType.trim()) {
      metaPayload.site_type = metaSiteType.trim();
      metaPayload.vertical = metaSiteType.trim();
    }
    if (metaSku.trim()) metaPayload.sku = metaSku.trim();
    try {
      await apiClient.patch(`/v1/core/licenses/${editMetaRow.id}`, {
        replace_meta: false,
        meta: metaPayload,
        project_name: editMetaRow.project_name,
        logo_url: editMetaRow.logo_url,
      });
      setEditMetaRow(null);
      void load();
    } catch (e) {
      setMetaErr(getAxiosMessage(e));
    }
  }

  async function destroyLic() {
    if (!deleteId) return;
    try {
      await apiClient.delete(`/v1/core/licenses/${deleteId}`);
      setDeleteId(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function copyKey(key: string) {
    try {
      await navigator.clipboard.writeText(key);
    } catch {
      /* ignore */
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button
          type="button"
          size="sm"
          onClick={() => {
            setFormStart(todayIso);
            setAddOpen(true);
          }}
        >
          {tl('addLicense')}
        </Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {loading ? <p className="text-muted-foreground text-sm">{t('common.loading')}</p> : null}
        {error ? <p className="text-destructive">{error}</p> : null}
        {rows.map((lic) => {
          const days = remainingDays(lic);
          return (
            <Card key={String(lic.id ?? lic.license_key)} className={cn('border-2', cardTone(lic))}>
              <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
                <div className="flex min-w-0 items-start gap-3">
                  {lic.logo_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={String(lic.logo_url)} alt="" className="size-10 rounded object-cover" />
                  ) : (
                    <div className="bg-muted text-muted-foreground flex size-10 items-center justify-center rounded text-xs">
                      LIC
                    </div>
                  )}
                  <div className="min-w-0">
                    <CardTitle className="truncate text-base">{String(lic.project_name || lic.domain || '—')}</CardTitle>
                    <CardDescription className="font-mono" dir="ltr">
                      {String(lic.domain ?? '')}
                    </CardDescription>
                    <p className="text-muted-foreground mt-1 text-xs">{String(lic.status ?? '')}</p>
                  </div>
                </div>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button type="button" variant="ghost" size="icon" aria-label="actions">
                      <MoreHorizontal className="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={() => openEditMeta(lic)}>{tl('editMeta')}</DropdownMenuItem>
                    <DropdownMenuItem
                      onClick={() => {
                        setRenewDate('');
                        lic.id && setRenewId(lic.id);
                      }}
                    >
                      {tl('renew')}
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => lic.id && setCancelId(lic.id)}>{tl('cancel')}</DropdownMenuItem>
                    <DropdownMenuItem className="text-destructive" onClick={() => lic.id && setDeleteId(lic.id)}>
                      {t('common.delete')}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </CardHeader>
              <CardContent className="space-y-2">
                <div className="flex items-center gap-2">
                  <p className="text-muted-foreground truncate font-mono text-xs" dir="ltr">
                    {String(lic.license_key ?? '—')}
                  </p>
                  {lic.license_key ? (
                    <Button type="button" size="icon" variant="ghost" className="size-7" onClick={() => void copyKey(String(lic.license_key))}>
                      <Copy className="size-3.5" />
                    </Button>
                  ) : null}
                </div>
                <p className="text-muted-foreground text-xs">
                  {tl('expires')}: {lic.expires_at ? formatDate(String(lic.expires_at)) : tl('vipUnlimited')}
                </p>
                <p className="text-muted-foreground text-xs">
                  {days === null ? tl('vipUnlimited') : tl('remainingDays', { days })}
                </p>
                <p className="text-muted-foreground line-clamp-2 text-xs">{tl('modules')}: {licenseModulesSummary(lic)}</p>
                <Progress value={licenseProgress(lic)} />
              </CardContent>
            </Card>
          );
        })}
        {!rows.length && !loading ? <p className="text-muted-foreground text-sm">{tl('empty')}</p> : null}
      </div>

      <Dialog open={editMetaRow !== null} onOpenChange={(o) => !o && setEditMetaRow(null)}>
        <DialogContent className="max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{tl('editMeta')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 text-sm">
            <div className="grid gap-1">
              <Label>{tl('projectName')}</Label>
              <Input
                value={String(editMetaRow?.project_name ?? '')}
                onChange={(e) => setEditMetaRow((r) => (r ? { ...r, project_name: e.target.value } : r))}
              />
            </div>
            <div className="grid gap-1">
              <Label>{tl('logoUrl')}</Label>
              <Input
                value={String(editMetaRow?.logo_url ?? '')}
                onChange={(e) => setEditMetaRow((r) => (r ? { ...r, logo_url: e.target.value } : r))}
                dir="ltr"
              />
            </div>
            <div className="grid gap-1">
              <Label>{tl('siteType')}</Label>
              <Select value={metaSiteType || undefined} onValueChange={setMetaSiteType}>
                <SelectTrigger>
                  <SelectValue placeholder={tl('siteType')} />
                </SelectTrigger>
                <SelectContent>
                  {SITE_TYPES.map((st) => (
                    <SelectItem key={st} value={st}>
                      {st}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid gap-1">
              <Label>{tl('modules')}</Label>
              <Input value={metaModules} onChange={(e) => setMetaModules(e.target.value)} dir="ltr" className="font-mono text-xs" />
            </div>
            <div className="grid gap-1">
              <Label>SKU</Label>
              <Input value={metaSku} onChange={(e) => setMetaSku(e.target.value)} dir="ltr" className="font-mono text-xs" />
            </div>
            {metaErr ? <p className="text-destructive text-sm">{metaErr}</p> : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="secondary" onClick={() => setEditMetaRow(null)}>
              {t('common.cancel')}
            </Button>
            <Button type="button" onClick={() => void saveMeta()}>
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={addOpen} onOpenChange={setAddOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{tl('addLicense')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <p className="bg-muted/50 rounded-md border px-3 py-2 text-xs">{tl('domainIsLicenseHint')}</p>
            <div className="grid gap-1">
              <Label>{tl('projectName')}</Label>
              <Input value={formProject} onChange={(e) => setFormProject(e.target.value)} required />
            </div>
            <div className="grid gap-1">
              <Label>{tl('domain')}</Label>
              <Input value={formDomain} onChange={(e) => setFormDomain(e.target.value)} dir="ltr" placeholder="example.com" />
            </div>
            <div className="grid gap-1">
              <Label>{tl('logoUrl')}</Label>
              <Input value={formLogo} onChange={(e) => setFormLogo(e.target.value)} dir="ltr" />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="grid gap-1">
                <Label>{tl('startDate')}</Label>
                <LocaleDatePicker value={formStart} onChange={setFormStart} />
              </div>
              <div className="grid gap-1">
                <Label>{tl('expiryDate')}</Label>
                <LocaleDatePicker value={formExpires} onChange={setFormExpires} />
              </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="grid gap-1">
                <Label>{tl('status')}</Label>
                <Select value={formStatus} onValueChange={setFormStatus}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">active</SelectItem>
                    <SelectItem value="inactive">inactive</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-1">
                <Label>{tl('siteType')}</Label>
                <Select value={formSiteType} onValueChange={setFormSiteType}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {SITE_TYPES.map((st) => (
                      <SelectItem key={st} value={st}>
                        {st}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            {formErr ? <p className="text-destructive text-sm">{formErr}</p> : null}
          </div>
          <DialogFooter>
            <Button type="button" onClick={() => void addLicense()}>
              {t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!createdKey} onOpenChange={(o) => !o && setCreatedKey(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{tl('keyCreated')}</DialogTitle>
          </DialogHeader>
          <p className="text-muted-foreground text-sm">{tl('keyCreatedHint')}</p>
          <div className="flex items-center gap-2">
            <code className="bg-muted flex-1 truncate rounded px-2 py-1 text-xs" dir="ltr">
              {createdKey}
            </code>
            <Button type="button" size="sm" variant="outline" onClick={() => createdKey && void copyKey(createdKey)}>
              <Copy className="size-3.5" />
            </Button>
          </div>
          <DialogFooter>
            <Button type="button" onClick={() => setCreatedKey(null)}>
              {t('common.close')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={renewId !== null} onOpenChange={(o) => !o && setRenewId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{tl('renew')}</AlertDialogTitle>
            <AlertDialogDescription>{tl('renewHint')}</AlertDialogDescription>
          </AlertDialogHeader>
          <LocaleDatePicker value={renewDate} onChange={setRenewDate} />
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void renew()}>{t('common.confirm')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={cancelId !== null} onOpenChange={(o) => !o && setCancelId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{tl('cancel')}</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void cancelLic()}>{t('common.confirm')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('common.confirmDelete')}</AlertDialogTitle>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void destroyLic()}>{t('common.delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
