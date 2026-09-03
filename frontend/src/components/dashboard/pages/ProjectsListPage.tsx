'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { useLocale } from '@/hooks/use-locale';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { WizardStepper } from '@/features/shared/pm';
import { AccountSelect } from '@/features/shared/crm/AccountSelect';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';

type ProjectRow = Record<string, unknown>;
type Meta = { current_page?: number; last_page?: number; total?: number };
type TemplateOpt = { id: number; name: string; description?: string | null; status?: string };
type UserOpt = { id: number; name: string; email?: string };

const EMPTY_FORM = {
  name: '',
  description: '',
  status: 'active',
  customer_account_id: '',
  manager_user_id: '',
  template_id: '',
};

export function ProjectsListPage() {
  const t = useTranslations('pm.projects');
  const tCommon = useTranslations('common');
  const tNav = useTranslations();
  const { isRtl } = useLocale();
  const { layoutProps, setError, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<ProjectRow[]>([]);
  const [meta, setMeta] = useState<Meta>({});
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);

  const [wizardOpen, setWizardOpen] = useState(false);
  const [step, setStep] = useState(1);
  const [form, setForm] = useState(EMPTY_FORM);
  const [submitting, setSubmitting] = useState(false);

  const [templates, setTemplates] = useState<TemplateOpt[]>([]);
  const [managers, setManagers] = useState<UserOpt[]>([]);

  const [editOpen, setEditOpen] = useState(false);
  const [editRow, setEditRow] = useState<ProjectRow | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);

  const wizardSteps = useMemo(
    () => [
      { id: 1, label: t('wizard.stepBasic') },
      { id: 2, label: t('wizard.stepTemplate') },
      { id: 3, label: t('wizard.stepAssign') },
      { id: 4, label: t('wizard.stepConfirm') },
    ],
    [t],
  );

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/v1/projects/projects', {
        params: { page, per_page: 12, search: search || undefined },
      });
      const body = res.data as { data?: unknown; meta?: Meta };
      setRows(normalizeListPayload(body));
      setMeta(body.meta ?? {});
    } catch (e) {
      applyAxiosError(e);
    } finally {
      setLoading(false);
    }
  }, [page, search, applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const loadWizardLookups = useCallback(async () => {
    try {
      const [tplRes, userRes] = await Promise.all([
        apiClient.get('/v1/projects/project-templates'),
        apiClient.get('/v1/projects/assignable-users'),
      ]);
      const tplRows = normalizeListPayload(unwrapData(tplRes));
      setTemplates(
        tplRows.map((r) => ({
          id: Number(r.id),
          name: String(r.name ?? r.id),
          description: r.description != null ? String(r.description) : null,
          status: r.status != null ? String(r.status) : undefined,
        })),
      );
      const userRows = normalizeListPayload(unwrapData(userRes));
      setManagers(
        userRows.map((r) => ({
          id: Number(r.id),
          name: String(r.name ?? r.email ?? r.id),
          email: r.email != null ? String(r.email) : undefined,
        })),
      );
    } catch {
      setTemplates([]);
      setManagers([]);
    }
  }, []);

  useEffect(() => {
    if (wizardOpen || editOpen) void loadWizardLookups();
  }, [wizardOpen, editOpen, loadWizardLookups]);

  function openWizard() {
    setForm(EMPTY_FORM);
    setStep(1);
    setWizardOpen(true);
  }

  function applyTemplate(templateId: string) {
    setForm((f) => {
      if (!templateId || templateId === 'none') {
        return { ...f, template_id: '' };
      }
      const tpl = templates.find((x) => String(x.id) === templateId);
      if (!tpl) return { ...f, template_id: templateId };
      return {
        ...f,
        template_id: templateId,
        name: f.name.trim() ? f.name : tpl.name,
        description: f.description.trim() ? f.description : (tpl.description ?? ''),
        status: tpl.status || f.status,
      };
    });
  }

  async function submitProject() {
    if (!form.name.trim()) {
      setError(t('wizard.nameRequired'));
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      await apiClient.post('/v1/projects/projects', {
        name: form.name.trim(),
        description: form.description || undefined,
        status: form.status,
        customer_account_id: form.customer_account_id ? Number(form.customer_account_id) : undefined,
      });
      setWizardOpen(false);
      setStep(1);
      setForm(EMPTY_FORM);
      setSuccess(t('wizard.created'));
      void load();
    } catch (e) {
      applyAxiosError(e);
    } finally {
      setSubmitting(false);
    }
  }

  async function submitEdit() {
    if (!editRow?.id) return;
    setSubmitting(true);
    setError(null);
    try {
      await apiClient.patch(`/v1/projects/projects/${String(editRow.id)}`, {
        name: form.name,
        description: form.description || undefined,
        status: form.status,
        customer_account_id: form.customer_account_id ? Number(form.customer_account_id) : null,
      });
      setEditOpen(false);
      setEditRow(null);
      setSuccess(tCommon('saved'));
      void load();
    } catch (e) {
      applyAxiosError(e);
    } finally {
      setSubmitting(false);
    }
  }

  async function confirmDelete() {
    if (!deleteId) return;
    try {
      await apiClient.delete(`/v1/projects/projects/${deleteId}`);
      setDeleteId(null);
      setSuccess(tCommon('deleted'));
      void load();
    } catch (e) {
      applyAxiosError(e);
    }
  }

  function openEdit(p: ProjectRow) {
    setEditRow(p);
    setForm({
      name: String(p.name ?? ''),
      description: String(p.description ?? ''),
      status: String(p.status ?? 'active'),
      customer_account_id: p.customer_account_id != null ? String(p.customer_account_id) : '',
      manager_user_id: '',
      template_id: '',
    });
    setEditOpen(true);
  }

  const selectedTemplate = templates.find((x) => String(x.id) === form.template_id);
  const selectedManager = managers.find((x) => String(x.id) === form.manager_user_id);
  const lastPage = meta.last_page ?? 1;

  const canNextBasic = Boolean(form.name.trim());

  return (
    <CrmPageLayout
      title={tNav('nav.erp.pm.projects')}
      description={t('listDescription')}
      {...layoutProps}
      actions={
        <>
          <Input
            placeholder={tCommon('search')}
            className="w-48"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
          />
          <Button type="button" variant="secondary" size="sm" onClick={() => void load()}>
            {tCommon('refresh')}
          </Button>
          <Button type="button" size="sm" onClick={openWizard}>
            {t('create')}
          </Button>
        </>
      }
    >
      {loading ? (
        <p className="text-sm text-muted-foreground">{tCommon('loading')}</p>
      ) : rows.length === 0 ? (
        <p className="text-sm text-muted-foreground">{tCommon('noData')}</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {rows.map((p) => (
            <Card key={String(p.id)} className="flex flex-col">
              <CardHeader>
                <CardTitle className="text-base">{String(p.name ?? '—')}</CardTitle>
                <CardDescription className="line-clamp-2">{String(p.description ?? '')}</CardDescription>
              </CardHeader>
              <CardContent className="mt-auto flex flex-wrap gap-2">
                <span className="rounded-full bg-muted px-2 py-0.5 text-xs">{String(p.status ?? '—')}</span>
                <Link href={`/dashboard/projects/${String(p.id)}`}>
                  <Button variant="outline" size="sm">
                    {tCommon('view')}
                  </Button>
                </Link>
                <Button type="button" variant="secondary" size="sm" onClick={() => openEdit(p)}>
                  {tCommon('edit')}
                </Button>
                <Button type="button" variant="destructive" size="sm" onClick={() => setDeleteId(Number(p.id))}>
                  {tCommon('delete')}
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Pagination
        page={meta.current_page ?? page}
        pageCount={lastPage}
        total={meta.total}
        onPageChange={setPage}
      />

      <Dialog
        open={wizardOpen}
        onOpenChange={(open) => {
          setWizardOpen(open);
          if (!open) {
            setStep(1);
            setForm(EMPTY_FORM);
          }
        }}
      >
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{t('wizard.title')}</DialogTitle>
          </DialogHeader>
          <WizardStepper steps={wizardSteps} current={step} isRtl={isRtl} />

          {step === 1 ? (
            <div className="space-y-3 py-2">
              <div className="space-y-2">
                <Label>{t('name')}</Label>
                <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label>{t('description')}</Label>
                <Textarea
                  value={form.description}
                  onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
                />
              </div>
              <div className="space-y-2">
                <Label>{t('status')}</Label>
                <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">{t('statusActive')}</SelectItem>
                    <SelectItem value="on_hold">{t('statusOnHold')}</SelectItem>
                    <SelectItem value="completed">{t('statusCompleted')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          ) : null}

          {step === 2 ? (
            <div className="space-y-3 py-2">
              <p className="text-sm text-muted-foreground">{t('wizard.templateHint')}</p>
              <div className="space-y-2">
                <Label>{t('wizard.template')}</Label>
                <Select
                  value={form.template_id || 'none'}
                  onValueChange={(v) => applyTemplate(v === 'none' ? '' : v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t('wizard.noTemplate')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">{t('wizard.noTemplate')}</SelectItem>
                    {templates.map((tpl) => (
                      <SelectItem key={tpl.id} value={String(tpl.id)}>
                        {tpl.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          ) : null}

          {step === 3 ? (
            <div className="space-y-3 py-2">
              <div className="space-y-2">
                <Label>{t('customer')}</Label>
                <AccountSelect
                  value={form.customer_account_id || 'none'}
                  onChange={(v) =>
                    setForm((f) => ({ ...f, customer_account_id: v === 'none' ? '' : v }))
                  }
                  allowEmpty
                  placeholder={t('selectCustomer')}
                />
              </div>
              <div className="space-y-2">
                <Label>{t('manager')}</Label>
                <Select
                  value={form.manager_user_id || 'none'}
                  onValueChange={(v) =>
                    setForm((f) => ({ ...f, manager_user_id: v === 'none' ? '' : v }))
                  }
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t('selectManager')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">{tCommon('emptyValue')}</SelectItem>
                    {managers.map((m) => (
                      <SelectItem key={m.id} value={String(m.id)}>
                        {m.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          ) : null}

          {step === 4 ? (
            <div className="space-y-2 rounded-md border bg-muted/30 p-4 text-sm">
              <p>
                <span className="text-muted-foreground">{t('name')}: </span>
                {form.name || '—'}
              </p>
              <p>
                <span className="text-muted-foreground">{t('status')}: </span>
                {form.status}
              </p>
              <p>
                <span className="text-muted-foreground">{t('wizard.template')}: </span>
                {selectedTemplate?.name ?? t('wizard.noTemplate')}
              </p>
              <p>
                <span className="text-muted-foreground">{t('customer')}: </span>
                {form.customer_account_id || tCommon('emptyValue')}
              </p>
              <p>
                <span className="text-muted-foreground">{t('manager')}: </span>
                {selectedManager?.name ?? tCommon('emptyValue')}
              </p>
              {form.description ? (
                <p className="line-clamp-3 text-muted-foreground">{form.description}</p>
              ) : null}
            </div>
          ) : null}

          <DialogFooter className="gap-2 sm:gap-0">
            {step > 1 ? (
              <Button type="button" variant="outline" onClick={() => setStep((s) => s - 1)}>
                {tCommon('back')}
              </Button>
            ) : null}
            {step < 4 ? (
              <Button
                type="button"
                onClick={() => setStep((s) => s + 1)}
                disabled={step === 1 && !canNextBasic}
              >
                {tCommon('next')}
              </Button>
            ) : (
              <Button type="button" onClick={() => void submitProject()} disabled={submitting}>
                {t('create')}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{t('edit')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div className="space-y-2">
              <Label>{t('name')}</Label>
              <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
            </div>
            <div className="space-y-2">
              <Label>{t('description')}</Label>
              <Textarea
                value={form.description}
                onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t('status')}</Label>
              <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">{t('statusActive')}</SelectItem>
                  <SelectItem value="on_hold">{t('statusOnHold')}</SelectItem>
                  <SelectItem value="completed">{t('statusCompleted')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t('customer')}</Label>
              <AccountSelect
                value={form.customer_account_id || 'none'}
                onChange={(v) =>
                  setForm((f) => ({ ...f, customer_account_id: v === 'none' ? '' : v }))
                }
                allowEmpty
                placeholder={t('selectCustomer')}
              />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" onClick={() => void submitEdit()} disabled={submitting}>
              {tCommon('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('deleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{tCommon('confirmDelete')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tCommon('cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmDelete()}>{tCommon('delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </CrmPageLayout>
  );
}
