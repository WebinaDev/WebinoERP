'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useFormatter, useTranslations } from 'next-intl';
import { Eye, Link2, Pencil, Plus, Send, Trash2 } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmConfirmDialog, PmEmptyState, PmPagination } from '@/features/shared/pm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  attachModirPayamakPattern,
  detachModirPayamakPattern,
  getModirPayamakCustomers,
  getModirPayamakDomainPatternRegistry,
  type ModirPayamakPatternScope,
  type ModirPayamakRegistryRow,
} from '@/lib/api/modirpayamak';
import {
  edgeDeletePattern,
  edgeField,
  edgeGetPattern,
  edgeListPatterns,
  edgeSavePattern,
  type EdgeRow,
} from '@/lib/api/modirpayamak-edge';
import { getAxiosMessage } from '@/lib/api-helpers';
import { dashboardHref } from '@/lib/route-resolver';
import {
  emptyPatternForm,
  ModirPayamakPatternForm,
  patternFormToPayload,
  validatePatternForm,
  type PatternFormValue,
} from './components/pattern-form';
import { ModirPayamakStatusBadge } from './components/status-badge';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';
import { useModirPayamakEdge } from './hooks/useModirPayamakEdge';

const ORDER_EVENTS = [
  'pending_on_create',
  'pending_on_status',
  'on-hold',
  'processing',
  'packaged',
  'sent-to-warehouse',
  'courier',
  'post',
  'tipax',
  'completed',
  'cancelled',
  'failed',
  'refunded',
  'checkout-draft',
  'cart-abandoned',
  'order-abandoned',
  'user-welcome',
  'stock-low',
  'stock-out',
];

const SITE_EVENTS = ['otp_login', 'otp_register'];

function eventLabel(t: (key: string) => string, key: string): string {
  if (key === 'otp_login') return t('eventOtpLogin');
  if (key === 'otp_register') return t('eventOtpRegister');
  return key;
}

function SyncBadge({ status }: { status?: string }) {
  const t = useTranslations('modirpayamak');
  if (status === 'synced') return <Badge>{t('syncSynced')}</Badge>;
  if (status === 'pending') return <Badge variant="secondary">{t('syncPending')}</Badge>;
  if (status === 'failed') return <Badge variant="destructive">{t('syncFailed')}</Badge>;
  return <Badge variant="outline">{t('syncNone')}</Badge>;
}

export function ModirpayamakPatternsPage() {
  const t = useTranslations('modirpayamak');
  const tNav = useTranslations();
  const tCommon = useTranslations('common');
  const format = useFormatter();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const router = useRouter();
  const { layoutProps, setError, setSuccess } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const [page, setPage] = useState(1);
  const loader = useCallback(() => edgeListPatterns(page, 20), [page]);
  const { items, loading, error, reload } = useModirPayamakEdge(loader, {
    enabled: Boolean(configured),
    deps: [page],
  });

  const [detailOpen, setDetailOpen] = useState(false);
  const [detailRow, setDetailRow] = useState<EdgeRow | null>(null);
  const [editOpen, setEditOpen] = useState(false);
  const [editCode, setEditCode] = useState<string | null>(null);
  const [form, setForm] = useState<PatternFormValue>(emptyPatternForm());
  const [saving, setSaving] = useState(false);
  const [deleteCode, setDeleteCode] = useState<string | null>(null);
  const [deleting, setDeleting] = useState(false);

  const [attachOpen, setAttachOpen] = useState(false);
  const [attachCode, setAttachCode] = useState('');
  const [attachDomain, setAttachDomain] = useState('');
  const [attachScope, setAttachScope] = useState<ModirPayamakPatternScope>('order_customer');
  const [attachEvent, setAttachEvent] = useState('processing');
  const [domains, setDomains] = useState<string[]>([]);
  const [attaching, setAttaching] = useState(false);
  const [domainRegistry, setDomainRegistry] = useState<ModirPayamakRegistryRow[]>([]);
  const [registryFilter, setRegistryFilter] = useState('');
  const [allRegistry, setAllRegistry] = useState<ModirPayamakRegistryRow[]>([]);
  const [registryLoading, setRegistryLoading] = useState(false);

  const eventOptions = attachScope === 'site' ? SITE_EVENTS : ORDER_EVENTS;
  const lastPage = Math.max(1, page + (items.length >= 20 ? 1 : 0));

  const loadAllRegistry = useCallback(async (domain = '') => {
    setRegistryLoading(true);
    try {
      const res = await getModirPayamakDomainPatternRegistry(domain.trim());
      setAllRegistry(res.registry ?? []);
    } catch {
      setAllRegistry([]);
    } finally {
      setRegistryLoading(false);
    }
  }, []);

  useEffect(() => {
    if (error) setError(error);
  }, [error, setError]);

  useEffect(() => {
    if (!configured) return;
    void loadAllRegistry('');
    void (async () => {
      try {
        const res = await getModirPayamakCustomers();
        setDomains(res.accounts.map((a) => a.domain).filter(Boolean));
      } catch {
        /* ignore */
      }
    })();
  }, [configured, loadAllRegistry]);

  const openDetail = async (row: EdgeRow) => {
    const code = edgeField(row, 'code', 'pattern_code', 'id');
    if (code === '—') {
      setDetailRow(row);
      setDetailOpen(true);
      return;
    }
    const res = await edgeGetPattern(code);
    setDetailRow(res.item ?? row);
    setDetailOpen(true);
  };

  const openCreate = () => {
    setEditCode(null);
    setForm(emptyPatternForm());
    setEditOpen(true);
  };

  const openEdit = (row: EdgeRow) => {
    setEditCode(edgeField(row, 'code', 'pattern_code', 'id'));
    const message = edgeField(row, 'message', 'text', 'body', 'pattern');
    const vars: PatternFormValue['variable'] = [];
    const rawVars = row.variable ?? row.vars;
    if (Array.isArray(rawVars)) {
      for (const v of rawVars) {
        if (v && typeof v === 'object') {
          const obj = v as Record<string, unknown>;
          vars.push({
            name: String(obj.name ?? obj.var ?? ''),
            type: String(obj.type ?? 'string') === 'integer' ? 'integer' : 'string',
          });
        }
      }
    }
    setForm({
      ...emptyPatternForm(),
      title: edgeField(row, 'title', 'name'),
      description: edgeField(row, 'description', 'title', 'name'),
      website: edgeField(row, 'website') === '—' ? '' : edgeField(row, 'website'),
      message: message === '—' ? '' : message,
      is_share: Boolean(row.is_share),
      variable: vars,
      checklist: { brandInMessage: true, nonPromotional: true, enamadIfLink: true, testWordIfTest: true },
    });
    setEditOpen(true);
  };

  const openAttach = async (row: EdgeRow) => {
    const code = edgeField(row, 'code', 'pattern_code', 'id');
    setAttachCode(code !== '—' ? code : '');
    setAttachDomain('');
    setAttachScope('order_customer');
    setAttachEvent('processing');
    setDomainRegistry([]);
    setAttachOpen(true);
    try {
      const res = await getModirPayamakCustomers();
      setDomains(res.accounts.map((a) => a.domain).filter(Boolean));
    } catch {
      /* ignore */
    }
  };

  const loadDomainRegistry = async (domain: string) => {
    if (!domain) {
      setDomainRegistry([]);
      return;
    }
    try {
      const res = await getModirPayamakDomainPatternRegistry(domain);
      setDomainRegistry(res.registry ?? []);
    } catch {
      setDomainRegistry([]);
    }
  };

  const submitAttach = async () => {
    if (!attachDomain.trim() || !attachCode.trim() || !attachEvent.trim()) {
      setError(t('domainRequired'));
      return;
    }
    setAttaching(true);
    setError(null);
    try {
      const res = await attachModirPayamakPattern({
        domain: attachDomain.trim(),
        scope: attachScope,
        event_key: attachEvent,
        pattern_code: attachCode.trim(),
      });
      setSuccess(res.message ?? t('patternAttached'));
      if (res.registry) setDomainRegistry(res.registry);
      else void loadDomainRegistry(attachDomain.trim());
      void loadAllRegistry(registryFilter);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setAttaching(false);
    }
  };

  const detachRow = async (r: ModirPayamakRegistryRow, domainOverride?: string) => {
    const domain = (domainOverride ?? r.domain ?? '').trim();
    if (!domain || !r.scope || !r.event_key) return;
    setAttaching(true);
    setError(null);
    try {
      const res = await detachModirPayamakPattern({
        domain,
        scope: (r.scope as ModirPayamakPatternScope) || 'order_customer',
        event_key: r.event_key,
      });
      setSuccess(res.message ?? t('patternDetached'));
      if (domainOverride && res.registry) setDomainRegistry(res.registry);
      else if (domainOverride) void loadDomainRegistry(domain);
      void loadAllRegistry(registryFilter);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setAttaching(false);
    }
  };

  const save = async () => {
    const err = validatePatternForm(form, t);
    if (err) {
      setError(err);
      return;
    }
    setSaving(true);
    setError(null);
    const res = await edgeSavePattern(editCode, patternFormToPayload(form));
    setSaving(false);
    if (res.ok) {
      setSuccess(tCommon('saved'));
      setEditOpen(false);
      void reload();
    } else {
      setError(res.message || t('saveError'));
    }
  };

  const confirmDelete = async () => {
    if (!deleteCode) return;
    setDeleting(true);
    const res = await edgeDeletePattern(deleteCode);
    setDeleting(false);
    if (res.ok) {
      setSuccess(tCommon('deleted'));
      setDeleteCode(null);
      void reload();
    } else {
      setError(res.message || t('saveError'));
    }
  };

  const sendHref = (code: string) =>
    dashboardHref(locale, `admin/integrations/modirpayamak/send?pattern=${encodeURIComponent(code)}&mode=pattern`);

  const formatDate = (raw: string) => {
    if (!raw || raw === '—') return '—';
    try {
      return format.dateTime(new Date(raw.replace(/-/g, '/').slice(0, 16)), {
        dateStyle: 'short',
        timeStyle: 'short',
      });
    } catch {
      return raw.slice(0, 16);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.admin.mpPatterns')}
      {...layoutProps}
      actions={
        <Button size="sm" onClick={openCreate} disabled={!configured}>
          <Plus className="me-2 h-4 w-4" />
          {t('addPattern')}
        </Button>
      }
    >
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpPatterns')} />
      {configLoading ? null : !configured ? <ModirPayamakNotConfigured /> : null}

      {configured ? (
        <>
          <Card className="mb-4">
            <CardContent className="space-y-3 p-4">
              <div className="flex flex-wrap items-end gap-2">
                <div className="min-w-[14rem] flex-1">
                  <Label>{t('filterByDomain')}</Label>
                  {domains.length > 0 ? (
                    <Select
                      value={registryFilter || '__all__'}
                      onValueChange={(v) => {
                        const next = v === '__all__' ? '' : v;
                        setRegistryFilter(next);
                        void loadAllRegistry(next);
                      }}
                    >
                      <SelectTrigger className="mt-1">
                        <SelectValue placeholder={t('allDomains')} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="__all__">{t('allDomains')}</SelectItem>
                        {domains.map((d) => (
                          <SelectItem key={d} value={d}>
                            {d}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  ) : (
                    <div className="mt-1 flex gap-2">
                      <Input
                        value={registryFilter}
                        onChange={(e) => setRegistryFilter(e.target.value)}
                        placeholder="example.com"
                      />
                      <Button type="button" variant="secondary" onClick={() => void loadAllRegistry(registryFilter)}>
                        {tCommon('apply')}
                      </Button>
                    </div>
                  )}
                </div>
                <Button type="button" variant="outline" onClick={() => void loadAllRegistry(registryFilter)}>
                  {tCommon('refresh')}
                </Button>
              </div>
              <p className="text-sm font-medium">{t('registryAllTitle')}</p>
              {registryLoading ? (
                <div className="h-24 animate-pulse rounded bg-muted/40" />
              ) : allRegistry.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('registryEmpty')}</p>
              ) : (
                <div className="max-h-72 overflow-auto rounded border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{t('domain')}</TableHead>
                        <TableHead>{t('patternScope')}</TableHead>
                        <TableHead>{t('patternEvent')}</TableHead>
                        <TableHead>{t('patternCode')}</TableHead>
                        <TableHead>{t('status')}</TableHead>
                        <TableHead className="w-[90px]">{tCommon('actions')}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {allRegistry.map((r, i) => (
                        <TableRow key={`${r.domain}-${r.scope}-${r.event_key}-${i}`}>
                          <TableCell className="font-mono text-xs" dir="ltr">
                            {r.domain || '—'}
                          </TableCell>
                          <TableCell className="text-xs">{r.scope}</TableCell>
                          <TableCell className="text-xs">{eventLabel(t, r.event_key ?? '')}</TableCell>
                          <TableCell className="font-mono text-xs" dir="ltr">
                            {r.ippanel_code || '—'}
                          </TableCell>
                          <TableCell>
                            <SyncBadge status={r.sync_status} />
                          </TableCell>
                          <TableCell>
                            <Button
                              type="button"
                              size="sm"
                              variant="ghost"
                              disabled={attaching || !r.domain || !r.scope || !r.event_key}
                              onClick={() => void detachRow(r)}
                            >
                              {t('detachPattern')}
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              )}
            </CardContent>
          </Card>

          {loading ? (
            <Card>
              <CardContent className="h-40 animate-pulse bg-muted/30" />
            </Card>
          ) : items.length === 0 ? (
            <PmEmptyState title={t('patternsEmpty')} />
          ) : (
            <Card>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('patternCode')}</TableHead>
                      <TableHead>{t('name')}</TableHead>
                      <TableHead>{t('patternStatus')}</TableHead>
                      <TableHead>{t('colDate')}</TableHead>
                      <TableHead className="w-[180px]">{tCommon('actions')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {items.map((row, i) => {
                      const code = edgeField(row, 'code', 'pattern_code', 'id');
                      return (
                        <TableRow key={code !== '—' ? code : i}>
                          <TableCell className="font-mono">{code}</TableCell>
                          <TableCell className="max-w-xs truncate">
                            {edgeField(row, 'title', 'name', 'description')}
                          </TableCell>
                          <TableCell>
                            <ModirPayamakStatusBadge
                              status={edgeField(row, 'pattern_status', 'status', 'state')}
                            />
                          </TableCell>
                          <TableCell className="text-sm text-muted-foreground">
                            {formatDate(edgeField(row, 'created_at', 'updated_at'))}
                          </TableCell>
                          <TableCell>
                            <div className="flex gap-1">
                              <Button type="button" variant="ghost" size="icon" onClick={() => void openDetail(row)}>
                                <Eye className="h-4 w-4" />
                              </Button>
                              <Button type="button" variant="ghost" size="icon" onClick={() => openEdit(row)}>
                                <Pencil className="h-4 w-4" />
                              </Button>
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                title={t('attachPatternToShop')}
                                onClick={() => void openAttach(row)}
                              >
                                <Link2 className="h-4 w-4" />
                              </Button>
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => router.push(sendHref(code))}
                              >
                                <Send className="h-4 w-4" />
                              </Button>
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-destructive"
                                onClick={() => setDeleteCode(code !== '—' ? code : null)}
                              >
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}

          <PmPagination page={page} lastPage={lastPage} onPage={setPage} />
        </>
      ) : null}

      <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
        <SheetContent className="overflow-y-auto sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>{edgeField(detailRow, 'code', 'pattern_code')}</SheetTitle>
          </SheetHeader>
          {detailRow ? (
            <div className="mt-4 space-y-3 text-sm">
              <p>
                <span className="text-muted-foreground">{t('name')}: </span>
                {edgeField(detailRow, 'title', 'name')}
              </p>
              <p>
                <span className="text-muted-foreground">{t('patternStatus')}: </span>
                <ModirPayamakStatusBadge status={edgeField(detailRow, 'pattern_status', 'status')} />
              </p>
              {edgeField(detailRow, 'reject_text', 'rejection_reason') !== '—' ? (
                <p>
                  <span className="text-muted-foreground">{t('rejectText')}: </span>
                  {edgeField(detailRow, 'reject_text', 'rejection_reason')}
                </p>
              ) : null}
              <div>
                <Label>{t('message')}</Label>
                <pre className="mt-1 whitespace-pre-wrap rounded bg-muted p-3 text-xs">
                  {edgeField(detailRow, 'message', 'text', 'body', 'pattern')}
                </pre>
              </div>
              <Button size="sm" asChild>
                <Link href={sendHref(edgeField(detailRow, 'code', 'pattern_code'))}>{t('useInSend')}</Link>
              </Button>
            </div>
          ) : null}
        </SheetContent>
      </Sheet>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editCode ? tCommon('edit') : t('addPattern')}</DialogTitle>
          </DialogHeader>
          <ModirPayamakPatternForm value={form} onChange={setForm} disabled={saving} />
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditOpen(false)}>
              {tCommon('cancel')}
            </Button>
            <Button onClick={() => void save()} disabled={saving}>
              {tCommon('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={attachOpen} onOpenChange={setAttachOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{t('attachPatternToShop')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>{t('patternCode')}</Label>
              <Input
                className="mt-1 font-mono"
                dir="ltr"
                value={attachCode}
                onChange={(e) => setAttachCode(e.target.value)}
              />
            </div>
            <div>
              <Label>{t('domain')}</Label>
              {domains.length > 0 ? (
                <Select
                  value={attachDomain}
                  onValueChange={(d) => {
                    setAttachDomain(d);
                    void loadDomainRegistry(d);
                  }}
                >
                  <SelectTrigger className="mt-1">
                    <SelectValue placeholder="example.com" />
                  </SelectTrigger>
                  <SelectContent>
                    {domains.map((d) => (
                      <SelectItem key={d} value={d}>
                        {d}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ) : (
                <Input
                  className="mt-1"
                  value={attachDomain}
                  onChange={(e) => setAttachDomain(e.target.value)}
                  onBlur={() => void loadDomainRegistry(attachDomain)}
                />
              )}
            </div>
            <div>
              <Label>{t('patternScope')}</Label>
              <Select
                value={attachScope}
                onValueChange={(v) => {
                  const scope = v as ModirPayamakPatternScope;
                  setAttachScope(scope);
                  setAttachEvent(scope === 'site' ? 'otp_login' : 'processing');
                }}
              >
                <SelectTrigger className="mt-1">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="order_customer">{t('scopeOrderCustomer')}</SelectItem>
                  <SelectItem value="order_admin">{t('scopeOrderAdmin')}</SelectItem>
                  <SelectItem value="site">{t('scopeSiteOtp')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>{t('patternEvent')}</Label>
              <Select value={attachEvent} onValueChange={setAttachEvent}>
                <SelectTrigger className="mt-1">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {eventOptions.map((ev) => (
                    <SelectItem key={ev} value={ev}>
                      {eventLabel(t, ev)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            {domainRegistry.length > 0 ? (
              <div className="max-h-48 overflow-auto rounded border">
                <p className="border-b px-3 py-2 text-sm font-medium">{t('domainRegistry')}</p>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('patternScope')}</TableHead>
                      <TableHead>{t('patternEvent')}</TableHead>
                      <TableHead>{t('patternCode')}</TableHead>
                      <TableHead>{t('status')}</TableHead>
                      <TableHead className="w-[90px]">{tCommon('actions')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {domainRegistry.map((r, i) => (
                      <TableRow key={`${r.scope}-${r.event_key}-${i}`}>
                        <TableCell className="text-xs">{r.scope}</TableCell>
                        <TableCell className="text-xs">{eventLabel(t, r.event_key ?? '')}</TableCell>
                        <TableCell className="font-mono text-xs" dir="ltr">
                          {r.ippanel_code || '—'}
                        </TableCell>
                        <TableCell>
                          <SyncBadge status={r.sync_status} />
                        </TableCell>
                        <TableCell>
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={attaching || !attachDomain.trim() || !r.scope || !r.event_key}
                            onClick={() => void detachRow(r, attachDomain.trim())}
                          >
                            {t('detachPattern')}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            ) : null}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setAttachOpen(false)}>
              {tCommon('cancel')}
            </Button>
            <Button type="button" disabled={attaching} onClick={() => void submitAttach()}>
              {tCommon('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteCode != null}
        title={tCommon('delete')}
        description={t('confirmDeletePattern')}
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteCode(null)}
        pending={deleting}
      />
    </CrmPageLayout>
  );
}
