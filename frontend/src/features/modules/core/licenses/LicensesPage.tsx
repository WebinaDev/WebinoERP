'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Key, Plus } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
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
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { LicenseCard, type LicenseCardRow } from './components/LicenseCard';

const SITE_TYPES = ['ecommerce', 'magazine', 'cafe', 'resume', 'corporate'] as const;

export function LicensesPageView() {
  const t = useTranslations();
  const tl = useTranslations('licenses');
  const { layoutProps, setError, applyAxiosError } = useCrmFeedback();

  const [rows, setRows] = useState<LicenseCardRow[]>([]);
  const [loading, setLoading] = useState(true);

  const [addOpen, setAddOpen] = useState(false);
  const [createdKey, setCreatedKey] = useState<string | null>(null);
  const [renewId, setRenewId] = useState<number | null>(null);
  const [renewDate, setRenewDate] = useState('');
  const [cancelId, setCancelId] = useState<number | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);

  const [editMetaRow, setEditMetaRow] = useState<LicenseCardRow | null>(null);
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
      setRows(normalizeListPayload(unwrapData<unknown>(res)) as LicenseCardRow[]);
      setError(null);
    } catch (e) {
      applyAxiosError(e);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, setError]);

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
      const created = unwrapData<LicenseCardRow>(res);
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
      applyAxiosError(e);
    }
  }

  async function cancelLic() {
    if (!cancelId) return;
    try {
      await apiClient.post(`/v1/core/licenses/${cancelId}/cancel`);
      setCancelId(null);
      void load();
    } catch (e) {
      applyAxiosError(e);
    }
  }

  function openEditMeta(lic: LicenseCardRow) {
    setMetaErr(null);
    setEditMetaRow(lic);
    const meta = (lic.meta ?? {}) as Record<string, unknown>;
    const mods = meta.modules ?? meta.licensed_modules;
    setMetaModules(Array.isArray(mods) ? mods.filter((x) => typeof x === 'string').join(', ') : '');
    setMetaSiteType(
      typeof meta.site_type === 'string'
        ? meta.site_type
        : typeof meta.vertical === 'string'
          ? meta.vertical
          : '',
    );
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
      applyAxiosError(e);
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
    <CrmPageLayout
      title={t('nav.erp.distribution.licenses')}
      {...layoutProps}
      actions={
        <Button
          type="button"
          onClick={() => {
            setFormStart(todayIso);
            setAddOpen(true);
          }}
        >
          <Plus className="me-2 h-4 w-4" />
          {tl('addLicense')}
        </Button>
      }
    >
      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="bg-muted/40 h-64 animate-pulse rounded-xl" />
          ))}
        </div>
      ) : rows.length === 0 ? (
        <div className="text-muted-foreground flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed py-12 text-center text-sm">
          <Key className="h-10 w-10 opacity-40" />
          <p>{tl('empty')}</p>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {rows.map((lic) => (
            <LicenseCard
              key={String(lic.id ?? lic.license_key)}
              license={lic}
              onEdit={openEditMeta}
              onRenew={(id) => {
                setRenewDate('');
                setRenewId(id);
              }}
              onCancel={setCancelId}
              onDelete={setDeleteId}
              onCopyKey={(k) => void copyKey(k)}
            />
          ))}
        </div>
      )}

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
              <Input
                value={metaModules}
                onChange={(e) => setMetaModules(e.target.value)}
                dir="ltr"
                className="font-mono text-xs"
              />
            </div>
            <div className="grid gap-1">
              <Label>SKU</Label>
              <Input
                value={metaSku}
                onChange={(e) => setMetaSku(e.target.value)}
                dir="ltr"
                className="font-mono text-xs"
              />
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
              <Input
                value={formDomain}
                onChange={(e) => setFormDomain(e.target.value)}
                dir="ltr"
                placeholder="example.com"
              />
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
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => createdKey && void copyKey(createdKey)}
            >
              Copy
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
    </CrmPageLayout>
  );
}
