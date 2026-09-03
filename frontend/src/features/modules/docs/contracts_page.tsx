'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useParams, usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { List, Loader2, Pencil, Plus, Trash2, XCircle } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale } from '@/hooks/use-locale';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { WizardStepper, PmConfirmDialog, PmEmptyState } from '@/features/shared/pm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

type ContractRow = Record<string, unknown>;
type Installment = { amount: string; due_date: string; status: string };
type LeadForContract = {
  id?: number;
  topic?: string;
  first_name?: string;
  last_name?: string;
  business_name?: string;
  email?: string;
  mobile?: string;
  company?: string;
  existing_customer_id?: number | null;
  converted_to_account_id?: number | null;
};

type FormState = {
  title: string;
  party_name: string;
  status: string;
  start_date: string;
  amount: string;
  installments: Installment[];
  product_note: string;
  project_id: string;
};

const emptyForm = (): FormState => ({
  title: '',
  party_name: '',
  status: 'draft',
  start_date: new Date().toISOString().slice(0, 10),
  amount: '',
  installments: [],
  product_note: '',
  project_id: '',
});

export function ContractsPage() {
  const t = useTranslations('docs.contracts');
  const tNav = useTranslations();
  const { isRtl } = useLocale();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { layoutProps, setError, setSuccess, applyAxiosError } = useCrmFeedback();

  const action = searchParams.get('action');
  const contractIdParam = searchParams.get('contract_id');
  const editId = contractIdParam ? Number(contractIdParam) : null;
  const fromLeadId = searchParams.get('from_lead') ? Number(searchParams.get('from_lead')) : null;
  const initialTab = action === 'new' || (editId && !Number.isNaN(editId)) ? 'new' : 'list';

  const [tab, setTab] = useState<'list' | 'new'>(initialTab);
  const [rows, setRows] = useState<ContractRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [wizardStep, setWizardStep] = useState(1);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [projects, setProjects] = useState<{ id: number; name: string }[]>([]);
  const [catalog, setCatalog] = useState<{ id: number; name: string; price?: number }[]>([]);
  const [productId, setProductId] = useState('');
  const [lead, setLead] = useState<LeadForContract | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ContractRow | null>(null);
  const [cancelConfirm, setCancelConfirm] = useState(false);

  const setQuery = useCallback(
    (next: Record<string, string | null>) => {
      const sp = new URLSearchParams(searchParams.toString());
      Object.entries(next).forEach(([k, v]) => {
        if (v == null || v === '') sp.delete(k);
        else sp.set(k, v);
      });
      const q = sp.toString();
      router.replace(q ? `${pathname}?${q}` : pathname);
    },
    [pathname, router, searchParams],
  );

  const loadList = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/v1/docs/contracts');
      setRows(normalizeListPayload(res.data));
      setError(null);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, setError]);

  const loadProjects = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/projects/projects', { params: { per_page: 100 } });
      const list = normalizeListPayload(unwrapData(res));
      setProjects(
        list.map((p) => ({
          id: Number(p.id),
          name: String(p.name ?? p.title ?? p.id),
        })),
      );
    } catch {
      setProjects([]);
    }
  }, []);

  const loadCatalog = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/sales/catalog', { params: { per_page: 100 } });
      const list = normalizeListPayload(res.data);
      setCatalog(
        list.map((p) => ({
          id: Number(p.id),
          name: String(p.name ?? p.id),
          price: p.price != null ? Number(p.price) : undefined,
        })),
      );
    } catch {
      setCatalog([]);
    }
  }, []);

  const loadContract = useCallback(
    async (id: number) => {
      setLoading(true);
      try {
        const res = await apiClient.get(`/v1/docs/contracts/${id}`);
        const c = unwrapData(res) as ContractRow;
        const meta = (c.meta && typeof c.meta === 'object' ? c.meta : {}) as Record<string, unknown>;
        const installments = Array.isArray(meta.installments)
          ? (meta.installments as Record<string, unknown>[]).map((i) => ({
              amount: String(i.amount ?? ''),
              due_date: String(i.due_date ?? '').slice(0, 10),
              status: String(i.status ?? 'pending'),
            }))
          : [];
        const projectIds = Array.isArray(meta.project_ids) ? (meta.project_ids as number[]) : [];
        setEditingId(Number(c.id));
        setForm({
          title: String(c.title ?? ''),
          party_name: String(c.party_name ?? ''),
          status: String(c.status ?? 'draft'),
          start_date: String(c.signed_at ?? meta.start_date ?? '').slice(0, 10) || new Date().toISOString().slice(0, 10),
          amount: String(meta.amount ?? ''),
          installments,
          product_note: String(meta.product_note ?? ''),
          project_id: projectIds[0] ? String(projectIds[0]) : '',
        });
        setWizardStep(1);
        setError(null);
      } catch (err) {
        applyAxiosError(err);
      } finally {
        setLoading(false);
      }
    },
    [applyAxiosError, setError],
  );

  useEffect(() => {
    if (action === 'new' || (editId && !Number.isNaN(editId))) setTab('new');
    else setTab('list');
  }, [action, editId]);

  useEffect(() => {
    if (tab === 'list') void loadList();
  }, [tab, loadList]);

  useEffect(() => {
    if (tab !== 'new') return;
    void loadProjects();
    void loadCatalog();
    if (editId && !Number.isNaN(editId)) {
      void loadContract(editId);
    } else {
      setEditingId(null);
      setForm(emptyForm());
      setWizardStep(1);
      setProductId('');
    }
  }, [tab, editId, loadContract, loadProjects, loadCatalog]);

  useEffect(() => {
    if (!fromLeadId || tab !== 'new' || editingId) {
      if (!fromLeadId) setLead(null);
      return;
    }
    void (async () => {
      try {
        const res = await apiClient.get(`/v1/crm/leads/${fromLeadId}/for-contract`);
        const body = res.data as { data?: LeadForContract; existing_customer_id?: number | null };
        const d = body.data ?? null;
        if (d) {
          const existingId = body.existing_customer_id ?? d.converted_to_account_id ?? null;
          setLead({ ...d, existing_customer_id: existingId });
          const name =
            d.business_name ||
            d.company ||
            `${d.first_name ?? ''} ${d.last_name ?? ''}`.trim() ||
            d.topic ||
            '';
          setForm((f) => ({
            ...f,
            party_name: f.party_name || name,
            title: f.title || (d.topic ? String(d.topic) : f.title),
          }));
        }
      } catch {
        setLead(null);
      }
    })();
  }, [fromLeadId, tab, editingId]);

  const wizardSteps = useMemo(
    () => [
      { id: 1, label: t('stepInfo') },
      { id: 2, label: t('stepInstallments') },
      { id: 3, label: t('stepProducts') },
      { id: 4, label: t('stepProject') },
    ],
    [t],
  );

  const openNew = () => {
    setTab('new');
    setEditingId(null);
    setForm(emptyForm());
    setWizardStep(1);
    setProductId('');
    setQuery({ action: 'new', contract_id: null, from_lead: fromLeadId ? String(fromLeadId) : null });
  };

  const openEdit = (row: ContractRow) => {
    const id = Number(row.id);
    setTab('new');
    setQuery({ action: 'new', contract_id: String(id), from_lead: null });
    void loadContract(id);
  };

  const backToList = () => {
    setTab('list');
    setQuery({ action: null, contract_id: null, from_lead: null });
    void loadList();
  };

  const addInstallment = () => {
    setForm((f) => ({
      ...f,
      installments: [...f.installments, { amount: '', due_date: f.start_date, status: 'pending' }],
    }));
  };

  const updateInstallment = (idx: number, field: keyof Installment, value: string) => {
    setForm((f) => {
      const next = [...f.installments];
      next[idx] = { ...next[idx], [field]: value };
      return { ...f, installments: next };
    });
  };

  const removeInstallment = (idx: number) => {
    setForm((f) => ({ ...f, installments: f.installments.filter((_, i) => i !== idx) }));
  };

  const calculateInstallments = () => {
    const total = Number(form.amount.replace(/[^\d.]/g, '')) || 0;
    const count = Math.max(1, form.installments.length || 1);
    if (total <= 0) return;
    const n = form.installments.length || count;
    const per = Math.floor(total / n);
    const rem = total - per * n;
    const start = form.start_date ? new Date(form.start_date) : new Date();
    const rowsInst: Installment[] = [];
    for (let i = 0; i < n; i++) {
      const d = new Date(start);
      d.setMonth(d.getMonth() + i);
      rowsInst.push({
        amount: String(i === n - 1 ? per + rem : per),
        due_date: d.toISOString().slice(0, 10),
        status: 'pending',
      });
    }
    setForm((f) => ({ ...f, installments: rowsInst }));
  };

  const onProductPick = (id: string) => {
    setProductId(id);
    const item = catalog.find((c) => String(c.id) === id);
    if (!item) return;
    setForm((f) => ({
      ...f,
      product_note: f.product_note || item.name,
      amount: f.amount || (item.price != null ? String(item.price) : f.amount),
      title: f.title || item.name,
    }));
  };

  const handleSubmit = async () => {
    if (!form.title.trim()) {
      setError(t('titleRequired'));
      return;
    }
    if (form.installments.length === 0) {
      setError(t('installmentsRequired'));
      return;
    }
    if (form.installments.some((i) => !i.amount || !i.due_date)) {
      setError(t('installmentsInvalid'));
      return;
    }
    setSubmitting(true);
    try {
      const payload = {
        title: form.title.trim(),
        party_name: form.party_name.trim() || null,
        status: form.status,
        signed_at: form.start_date || null,
        amount: form.amount ? Number(form.amount) : null,
        product_note: form.product_note.trim() || null,
        project_id: form.project_id ? Number(form.project_id) : null,
        lead_id: !editingId && fromLeadId ? fromLeadId : undefined,
        installments: form.installments.map((i) => ({
          amount: Number(i.amount),
          due_date: i.due_date,
          status: i.status,
        })),
        meta: {
          start_date: form.start_date,
          catalog_product_id: productId ? Number(productId) : null,
        },
      };
      if (editingId) {
        await apiClient.patch(`/v1/docs/contracts/${editingId}`, payload);
        setSuccess(tNav('common.saved'));
        backToList();
      } else {
        const res = await apiClient.post('/v1/docs/contracts', payload);
        const created = unwrapData(res) as ContractRow;
        setSuccess(tNav('common.saved'));
        if (created?.id) {
          setQuery({ action: 'new', contract_id: String(created.id), from_lead: null });
          void loadContract(Number(created.id));
        } else {
          backToList();
        }
      }
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget?.id) return;
    setSubmitting(true);
    try {
      await apiClient.delete(`/v1/docs/contracts/${deleteTarget.id}`);
      setDeleteTarget(null);
      setSuccess(tNav('common.deleted'));
      void loadList();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleCancel = async () => {
    if (!editingId) return;
    setSubmitting(true);
    try {
      await apiClient.post(`/v1/docs/contracts/${editingId}/cancel`);
      setCancelConfirm(false);
      setSuccess(t('cancelled'));
      void loadContract(editingId);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const leadName =
    lead?.business_name ||
    lead?.company ||
    `${lead?.first_name ?? ''} ${lead?.last_name ?? ''}`.trim() ||
    lead?.topic ||
    '';

  return (
    <CrmPageLayout
      title={tNav('nav.erp.docs.contracts')}
      actions={
        <div className="flex gap-2">
          <Button variant={tab === 'list' ? 'default' : 'outline'} size="sm" onClick={backToList}>
            <List className="size-4 me-2" />
            {t('listTab')}
          </Button>
          <Button variant={tab === 'new' ? 'default' : 'outline'} size="sm" onClick={openNew}>
            <Plus className="size-4 me-2" />
            {t('newContract')}
          </Button>
        </div>
      }
      {...layoutProps}
    >
      {tab === 'list' ? (
        <Card>
          <CardContent className="pt-6">
            {loading ? (
              <div className="flex justify-center py-10">
                <Loader2 className="size-6 animate-spin text-muted-foreground" />
              </div>
            ) : rows.length === 0 ? (
              <PmEmptyState title={t('empty')} />
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('title')}</TableHead>
                    <TableHead>{t('party')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                    <TableHead>{t('amount')}</TableHead>
                    <TableHead className="text-end">{tNav('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((r) => {
                    const meta = (r.meta && typeof r.meta === 'object' ? r.meta : {}) as Record<string, unknown>;
                    return (
                      <TableRow key={String(r.id)}>
                        <TableCell>
                          <Link
                            href={dashboardHref(locale, `docs/contracts/${r.id}`)}
                            className="underline"
                          >
                            {String(r.title ?? r.id)}
                          </Link>
                          {String(r.status) === 'cancelled' ? (
                            <Badge variant="destructive" className="ms-2">
                              {t('cancelledBadge')}
                            </Badge>
                          ) : null}
                        </TableCell>
                        <TableCell>{String(r.party_name ?? '—')}</TableCell>
                        <TableCell>
                          <Badge variant="secondary">{String(r.status ?? '')}</Badge>
                        </TableCell>
                        <TableCell dir="ltr">{String(meta.amount ?? '—')}</TableCell>
                        <TableCell className="text-end">
                          <div className="flex justify-end gap-2">
                            <Button size="sm" variant="outline" onClick={() => openEdit(r)}>
                              <Pencil className="size-4" />
                            </Button>
                            {String(r.status) !== 'cancelled' ? (
                              <Button
                                size="sm"
                                variant="outline"
                                className="text-destructive"
                                onClick={() => setDeleteTarget(r)}
                              >
                                <Trash2 className="size-4" />
                              </Button>
                            ) : null}
                          </div>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardContent className="space-y-4 pt-6">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h3 className="text-lg font-semibold">{editingId ? t('editContract') : t('newContract')}</h3>
              <div className="flex gap-2">
                {editingId && form.status !== 'cancelled' ? (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setCancelConfirm(true)}
                    disabled={submitting}
                  >
                    <XCircle className="size-4 me-1" />
                    {t('cancelContract')}
                  </Button>
                ) : null}
                <Button size="sm" variant="outline" onClick={backToList}>
                  {tNav('common.back')}
                </Button>
              </div>
            </div>

            {form.status === 'cancelled' ? (
              <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {t('cancelledNotice')}
              </div>
            ) : null}

            {fromLeadId && lead && !editingId ? (
              <div className="rounded-md border bg-muted/40 px-3 py-2 text-sm">
                {t('fromLeadBanner', { name: leadName || `#${fromLeadId}` })}
              </div>
            ) : null}

            {!editingId ? <WizardStepper steps={wizardSteps} current={wizardStep} isRtl={isRtl} /> : null}

            {(editingId || wizardStep === 1) && (
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label>{t('title')} *</Label>
                  <Input value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} />
                </div>
                <div className="space-y-2">
                  <Label>{t('party')}</Label>
                  <Input
                    value={form.party_name}
                    onChange={(e) => setForm((f) => ({ ...f, party_name: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('startDate')}</Label>
                  <LocaleDatePicker
                    value={form.start_date}
                    onChange={(v) => setForm((f) => ({ ...f, start_date: v }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('amount')}</Label>
                  <Input
                    dir="ltr"
                    type="number"
                    value={form.amount}
                    onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('status')}</Label>
                  <Select value={form.status} onValueChange={(v) => setForm((f) => ({ ...f, status: v }))}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="draft">{t('statusDraft')}</SelectItem>
                      <SelectItem value="active">{t('statusActive')}</SelectItem>
                      <SelectItem value="cancelled">{t('statusCancelled')}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            )}

            {(editingId || wizardStep === 2) && (
              <div className="space-y-3">
                <div className="flex flex-wrap gap-2">
                  <Button type="button" size="sm" variant="secondary" onClick={addInstallment}>
                    {t('addInstallment')}
                  </Button>
                  <Button type="button" size="sm" variant="outline" onClick={calculateInstallments}>
                    {t('calcInstallments')}
                  </Button>
                </div>
                {form.installments.length === 0 ? (
                  <p className="text-sm text-muted-foreground">{t('noInstallments')}</p>
                ) : (
                  form.installments.map((inst, idx) => (
                    <div key={idx} className="grid gap-2 sm:grid-cols-4">
                      <Input
                        dir="ltr"
                        placeholder={t('amount')}
                        value={inst.amount}
                        onChange={(e) => updateInstallment(idx, 'amount', e.target.value)}
                      />
                      <LocaleDatePicker
                        value={inst.due_date}
                        onChange={(v) => updateInstallment(idx, 'due_date', v)}
                      />
                      <Select value={inst.status} onValueChange={(v) => updateInstallment(idx, 'status', v)}>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="pending">{t('pending')}</SelectItem>
                          <SelectItem value="paid">{t('paid')}</SelectItem>
                        </SelectContent>
                      </Select>
                      <Button type="button" variant="ghost" onClick={() => removeInstallment(idx)}>
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                  ))
                )}
              </div>
            )}

            {(editingId || wizardStep === 3) && (
              <div className="space-y-3">
                <div className="space-y-2">
                  <Label>{t('catalogProduct')}</Label>
                  <Select value={productId || 'none'} onValueChange={(v) => onProductPick(v === 'none' ? '' : v)}>
                    <SelectTrigger>
                      <SelectValue placeholder={t('selectProduct')} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">{t('none')}</SelectItem>
                      {catalog.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>
                          {p.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t('productNote')}</Label>
                  <Textarea
                    rows={3}
                    value={form.product_note}
                    onChange={(e) => setForm((f) => ({ ...f, product_note: e.target.value }))}
                  />
                </div>
              </div>
            )}

            {(editingId || wizardStep === 4) && (
              <div className="space-y-2">
                <Label>{t('selectProject')}</Label>
                <Select
                  value={form.project_id || 'none'}
                  onValueChange={(v) => setForm((f) => ({ ...f, project_id: v === 'none' ? '' : v }))}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t('selectProject')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">{t('none')}</SelectItem>
                    {projects.map((p) => (
                      <SelectItem key={p.id} value={String(p.id)}>
                        {p.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}

            <div className="flex flex-wrap justify-end gap-2 pt-2">
              {!editingId && wizardStep > 1 ? (
                <Button type="button" variant="outline" onClick={() => setWizardStep((s) => s - 1)}>
                  {t('prev')}
                </Button>
              ) : null}
              {!editingId && wizardStep < 4 ? (
                <Button type="button" onClick={() => setWizardStep((s) => s + 1)}>
                  {t('next')}
                </Button>
              ) : (
                <Button type="button" onClick={() => void handleSubmit()} disabled={submitting || form.status === 'cancelled'}>
                  {submitting ? <Loader2 className="size-4 animate-spin me-2" /> : null}
                  {tNav('common.save')}
                </Button>
              )}
            </div>
          </CardContent>
        </Card>
      )}

      <PmConfirmDialog
        open={deleteTarget !== null}
        title={tNav('common.delete')}
        description={t('confirmDelete')}
        onConfirm={() => void handleDelete()}
        onCancel={() => setDeleteTarget(null)}
        pending={submitting}
      />
      <PmConfirmDialog
        open={cancelConfirm}
        title={t('cancelContract')}
        description={t('confirmCancel')}
        onConfirm={() => void handleCancel()}
        onCancel={() => setCancelConfirm(false)}
        pending={submitting}
      />
    </CrmPageLayout>
  );
}
