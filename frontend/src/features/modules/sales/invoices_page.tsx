'use client';

import { useCallback, useEffect, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { useLocale } from '@/hooks/use-locale-next';
import { FileDown, List, Loader2, Mail, Pencil, Plus } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmEmptyState, PmPagination } from '@/features/shared/pm';
import { usePmPagination } from '@/features/shared/hooks/usePmPagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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

type ItemRow = { title: string; desc: string; price: string; discount: string };

type InvoiceRow = Record<string, unknown> & {
  id: number;
  number?: string;
  invoice_number?: string;
  customer_name?: string;
  customer_id?: number | null;
  project_id?: number | null;
  project_title?: string | null;
  total?: number | string;
  final_total?: number | string;
  status?: string;
  issue_date?: string | null;
  date_display?: string | null;
  payment_method?: string | null;
  notes?: string | null;
  items?: { title?: string; desc?: string; price?: number | string; discount?: number | string }[];
  subtotal?: number | string;
  discount?: number | string;
};

type CustomerOpt = { id: number; display_name: string };
type ProjectOpt = { id: number; title: string };

const emptyItem = (): ItemRow => ({ title: '', desc: '', price: '', discount: '0' });

export function InvoicesPage() {
  const t = useTranslations('sales.invoices');
  const tNav = useTranslations();
  const { formatNumber } = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { layoutProps, setSuccess, setError, applyAxiosError } = useCrmFeedback();
  const { currentPage, setCurrentPage, totalPages, setTotalPages } = usePmPagination();

  const initialTab = searchParams.get('action') === 'new' ? 'new' : 'list';
  const editId = searchParams.get('invoice_id')
    ? parseInt(searchParams.get('invoice_id')!, 10)
    : null;

  const [tab, setTab] = useState<'list' | 'new'>(initialTab);
  const [rows, setRows] = useState<InvoiceRow[]>([]);
  const [customers, setCustomers] = useState<CustomerOpt[]>([]);
  const [projects, setProjects] = useState<ProjectOpt[]>([]);
  const [loading, setLoading] = useState(true);
  const [editingInvoice, setEditingInvoice] = useState<InvoiceRow | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [pdfBusyId, setPdfBusyId] = useState<number | null>(null);
  const [emailTarget, setEmailTarget] = useState<InvoiceRow | null>(null);
  const [emailValue, setEmailValue] = useState('');
  const [emailBusy, setEmailBusy] = useState(false);
  const [form, setForm] = useState({
    customer_id: 0,
    project_id: 0,
    issue_date: new Date().toISOString().slice(0, 10),
    items: [emptyItem()] as ItemRow[],
    payment_method: '',
    notes: '',
    status: 'draft',
  });

  const replaceQuery = useCallback(
    (next: Record<string, string | null>) => {
      const sp = new URLSearchParams(searchParams.toString());
      Object.entries(next).forEach(([k, v]) => {
        if (v === null || v === '') sp.delete(k);
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
      const res = await apiClient.get('/v1/sales/invoices', { params: { page: currentPage, per_page: 15 } });
      setRows(normalizeListPayload(res.data) as InvoiceRow[]);
      const body = res.data as {
        meta?: { last_page?: number; customers?: CustomerOpt[] };
        customers?: CustomerOpt[];
      };
      setTotalPages(body.meta?.last_page ?? 1);
      const cust = body.meta?.customers ?? body.customers ?? [];
      if (cust.length) {
        setCustomers(
          cust.map((c) => ({
            id: Number(c.id),
            display_name: String((c as CustomerOpt).display_name ?? (c as { name?: string }).name ?? c.id),
          })),
        );
      }
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, currentPage, setTotalPages]);

  const loadProjectsForCustomer = async (customerId: number) => {
    if (!customerId) {
      setProjects([]);
      return;
    }
    try {
      const res = await apiClient.get('/v1/sales/invoices-meta/projects', {
        params: { customer_id: customerId },
      });
      const list = normalizeListPayload(res.data) as { id: number; title?: string; name?: string }[];
      setProjects(
        list.map((p) => ({
          id: Number(p.id),
          title: String(p.title ?? p.name ?? p.id),
        })),
      );
    } catch {
      setProjects([]);
    }
  };

  const loadInvoice = useCallback(
    async (id: number) => {
      setLoading(true);
      try {
        const res = await apiClient.get(`/v1/sales/invoices/${id}`);
        const inv = unwrapData(res) as InvoiceRow;
        setEditingInvoice(inv);
        setForm({
          customer_id: Number(inv.customer_id ?? 0),
          project_id: Number(inv.project_id ?? 0),
          issue_date: String(inv.issue_date ?? '').slice(0, 10) || new Date().toISOString().slice(0, 10),
          items:
            (inv.items ?? []).length > 0
              ? (inv.items ?? []).map((i) => ({
                  title: String(i.title ?? ''),
                  desc: String(i.desc ?? ''),
                  price: String(i.price ?? 0),
                  discount: String(i.discount ?? 0),
                }))
              : [emptyItem()],
          payment_method: String(inv.payment_method ?? ''),
          notes: String(inv.notes ?? ''),
          status: String(inv.status ?? 'draft'),
        });
        if (inv.customer_id) void loadProjectsForCustomer(Number(inv.customer_id));
      } catch (err) {
        applyAxiosError(err);
      } finally {
        setLoading(false);
      }
    },
    [applyAxiosError],
  );

  useEffect(() => {
    const action = searchParams.get('action');
    const invId = searchParams.get('invoice_id');
    if (action === 'new') setTab('new');
    else setTab('list');
    if (invId && action === 'new') {
      const id = parseInt(invId, 10);
      if (!Number.isNaN(id)) void loadInvoice(id);
    }
  }, [searchParams, loadInvoice]);

  useEffect(() => {
    if (tab === 'list') void loadList();
  }, [tab, loadList]);

  useEffect(() => {
    if (tab !== 'new') return;
    if (editId) {
      void loadInvoice(editId);
      return;
    }
    setEditingInvoice(null);
    setForm({
      customer_id: 0,
      project_id: 0,
      issue_date: new Date().toISOString().slice(0, 10),
      items: [emptyItem()],
      payment_method: '',
      notes: '',
      status: 'draft',
    });
    setProjects([]);
    void apiClient.get('/v1/sales/invoices', { params: { per_page: 1 } }).then((res) => {
      const body = res.data as { meta?: { customers?: CustomerOpt[] }; customers?: CustomerOpt[] };
      const cust = body.meta?.customers ?? body.customers ?? [];
      if (cust.length) {
        setCustomers(
          cust.map((c) => ({
            id: Number(c.id),
            display_name: String((c as CustomerOpt).display_name ?? (c as { name?: string }).name ?? c.id),
          })),
        );
      }
    });
  }, [tab, editId, loadInvoice]);

  const openNew = () => {
    replaceQuery({ action: 'new', invoice_id: null });
    setTab('new');
    setEditingInvoice(null);
  };

  const openEdit = (inv: InvoiceRow) => {
    replaceQuery({ action: 'new', invoice_id: String(inv.id) });
    setTab('new');
    void loadInvoice(inv.id);
  };

  const backToList = () => {
    replaceQuery({ action: null, invoice_id: null });
    setTab('list');
    void loadList();
  };

  const onCustomerChange = (customerId: number) => {
    setForm((f) => ({ ...f, customer_id: customerId, project_id: 0 }));
    void loadProjectsForCustomer(customerId);
  };

  const addItemRow = () => setForm((f) => ({ ...f, items: [...f.items, emptyItem()] }));
  const removeItemRow = (idx: number) =>
    setForm((f) => ({ ...f, items: f.items.filter((_, i) => i !== idx) }));
  const updateItem = (idx: number, field: keyof ItemRow, value: string) => {
    setForm((f) => {
      const next = [...f.items];
      next[idx] = { ...next[idx], [field]: value };
      return { ...f, items: next };
    });
  };

  const digits = (v: string) => parseFloat(v.replace(/[^\d.]/g, '')) || 0;
  const subtotal = form.items.reduce((s, i) => s + digits(i.price), 0);
  const totalDiscount = form.items.reduce((s, i) => s + digits(i.discount), 0);
  const finalTotal = subtotal - totalDiscount;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.customer_id || !form.issue_date) {
      setError(t('requiredCustomerDate'));
      return;
    }
    const validItems = form.items.filter((i) => i.title.trim());
    if (validItems.length === 0) {
      setError(t('requiredItems'));
      return;
    }
    setSubmitting(true);
    try {
      const customer = customers.find((c) => c.id === form.customer_id);
      const project = projects.find((p) => p.id === form.project_id);
      const payload = {
        customer_id: form.customer_id,
        customer_name: customer?.display_name ?? '',
        project_id: form.project_id || null,
        project_title: project?.title ?? null,
        issue_date: form.issue_date,
        payment_method: form.payment_method || null,
        notes: form.notes || null,
        status: form.status,
        items: validItems.map((i) => ({
          title: i.title.trim(),
          desc: i.desc,
          price: digits(i.price),
          discount: digits(i.discount),
        })),
      };
      if (editingInvoice?.id) {
        await apiClient.patch(`/v1/sales/invoices/${editingInvoice.id}`, payload);
      } else {
        await apiClient.post('/v1/sales/invoices', payload);
      }
      setSuccess(tNav('common.saved'));
      backToList();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const pdf = async (inv: InvoiceRow) => {
    setPdfBusyId(inv.id);
    try {
      const res = await apiClient.post(`/v1/sales/invoices/${inv.id}/pdf`);
      const data = unwrapData(res) as { content_base64?: string; filename?: string };
      if (data.content_base64) {
        const bin = atob(data.content_base64);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const blob = new Blob([bytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = data.filename || `invoice-${inv.id}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
      }
      setSuccess(t('pdfReady'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setPdfBusyId(null);
    }
  };

  const sendEmail = async () => {
    if (!emailTarget?.id) return;
    if (!emailValue.trim() || !emailValue.includes('@')) {
      setError(t('emailRequired'));
      return;
    }
    setEmailBusy(true);
    try {
      await apiClient.post(`/v1/sales/invoices/${emailTarget.id}/email`, { email: emailValue.trim() });
      setEmailTarget(null);
      setEmailValue('');
      setSuccess(t('emailSent'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setEmailBusy(false);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.sales.invoices')}
      actions={
        <div className="flex gap-2">
          <Button
            variant={tab === 'list' ? 'default' : 'outline'}
            size="sm"
            onClick={() => {
              replaceQuery({ action: null, invoice_id: null });
              setTab('list');
            }}
          >
            <List className="size-4 me-2" />
            {t('list')}
          </Button>
          <Button variant={tab === 'new' ? 'default' : 'outline'} size="sm" onClick={openNew}>
            <Plus className="size-4 me-2" />
            {tNav('common.add')}
          </Button>
        </div>
      }
      {...layoutProps}
    >
      {tab === 'list' ? (
        <>
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
                      <TableHead>{t('number')}</TableHead>
                      <TableHead>{t('customer')}</TableHead>
                      <TableHead>{t('project')}</TableHead>
                      <TableHead>{t('finalTotal')}</TableHead>
                      <TableHead>{t('issueDate')}</TableHead>
                      <TableHead className="text-end">{tNav('common.actions')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {rows.map((inv) => (
                      <TableRow key={inv.id}>
                        <TableCell className="font-mono">
                          {String(inv.invoice_number ?? inv.number ?? inv.id)}
                        </TableCell>
                        <TableCell>{String(inv.customer_name ?? '')}</TableCell>
                        <TableCell>{String(inv.project_title ?? '—')}</TableCell>
                        <TableCell dir="ltr">
                          {formatNumber(Number(inv.final_total ?? inv.total ?? 0))}
                        </TableCell>
                        <TableCell>
                          {String(inv.date_display ?? String(inv.issue_date ?? '').slice(0, 10) || '—')}
                        </TableCell>
                        <TableCell className="text-end">
                          <div className="flex flex-wrap justify-end gap-2">
                            <Button size="sm" variant="outline" onClick={() => openEdit(inv)} title={tNav('common.edit')}>
                              <Pencil className="size-4" />
                            </Button>
                            <Button
                              size="sm"
                              variant="outline"
                              disabled={pdfBusyId === inv.id}
                              onClick={() => void pdf(inv)}
                              title={t('downloadPdf')}
                            >
                              {pdfBusyId === inv.id ? (
                                <Loader2 className="size-4 animate-spin" />
                              ) : (
                                <FileDown className="size-4" />
                              )}
                            </Button>
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => {
                                setEmailTarget(inv);
                                setEmailValue('');
                              }}
                              title={t('sendEmail')}
                            >
                              <Mail className="size-4" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
          <PmPagination page={currentPage} lastPage={totalPages} onPage={setCurrentPage} />
        </>
      ) : (
        <Card>
          <CardContent className="pt-6">
            <div className="mb-4 flex items-center justify-between">
              <h3 className="text-lg font-semibold">
                {editingInvoice ? t('editInvoice') : t('newInvoice')}
              </h3>
              <Button variant="outline" size="sm" onClick={backToList}>
                {t('backToList')}
              </Button>
            </div>

            {loading ? (
              <div className="flex justify-center py-10">
                <Loader2 className="size-6 animate-spin text-muted-foreground" />
              </div>
            ) : (
              <form onSubmit={(e) => void handleSubmit(e)} className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label>{t('customer')}</Label>
                    <Select
                      value={form.customer_id ? String(form.customer_id) : undefined}
                      onValueChange={(v) => onCustomerChange(parseInt(v, 10) || 0)}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder={t('selectCustomer')} />
                      </SelectTrigger>
                      <SelectContent>
                        {customers.map((c) => (
                          <SelectItem key={c.id} value={String(c.id)}>
                            {c.display_name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t('project')}</Label>
                    <Select
                      value={form.project_id ? String(form.project_id) : undefined}
                      onValueChange={(v) => setForm((f) => ({ ...f, project_id: parseInt(v, 10) || 0 }))}
                      disabled={!form.customer_id}
                    >
                      <SelectTrigger>
                        <SelectValue
                          placeholder={form.customer_id ? t('selectProject') : t('selectCustomerFirst')}
                        />
                      </SelectTrigger>
                      <SelectContent>
                        {projects.map((p) => (
                          <SelectItem key={p.id} value={String(p.id)}>
                            {p.title}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t('issueDate')}</Label>
                    <LocaleDatePicker
                      value={form.issue_date}
                      onChange={(v) => setForm((f) => ({ ...f, issue_date: v }))}
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
                        <SelectItem value="sent">{t('statusSent')}</SelectItem>
                        <SelectItem value="accepted">{t('statusAccepted')}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <Label>{t('lineItems')}</Label>
                    <Button type="button" variant="outline" size="sm" onClick={addItemRow}>
                      {t('addRow')}
                    </Button>
                  </div>
                  <div className="space-y-2">
                    {form.items.map((row, idx) => (
                      <div key={idx} className="grid grid-cols-12 items-end gap-2">
                        <div className="col-span-3 space-y-1">
                          <Label>{t('itemTitle')}</Label>
                          <Input
                            value={row.title}
                            onChange={(e) => updateItem(idx, 'title', e.target.value)}
                            placeholder={t('itemTitlePlaceholder')}
                          />
                        </div>
                        <div className="col-span-3 space-y-1">
                          <Label>{t('itemDesc')}</Label>
                          <Input value={row.desc} onChange={(e) => updateItem(idx, 'desc', e.target.value)} />
                        </div>
                        <div className="col-span-2 space-y-1">
                          <Label>{t('price')}</Label>
                          <Input
                            dir="ltr"
                            value={row.price}
                            onChange={(e) => updateItem(idx, 'price', e.target.value)}
                          />
                        </div>
                        <div className="col-span-2 space-y-1">
                          <Label>{t('discount')}</Label>
                          <Input
                            dir="ltr"
                            value={row.discount}
                            onChange={(e) => updateItem(idx, 'discount', e.target.value)}
                          />
                        </div>
                        <div className="col-span-2">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => removeItemRow(idx)}
                            disabled={form.items.length <= 1}
                          >
                            ×
                          </Button>
                        </div>
                      </div>
                    ))}
                  </div>
                  <div className="flex flex-wrap gap-4 text-sm">
                    <span>
                      {t('subtotal')}: <strong dir="ltr">{formatNumber(subtotal)}</strong>
                    </span>
                    <span>
                      {t('discount')}: <strong dir="ltr">{formatNumber(totalDiscount)}</strong>
                    </span>
                    <span>
                      {t('finalTotal')}: <strong dir="ltr">{formatNumber(finalTotal)}</strong>
                    </span>
                  </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label>{t('paymentMethod')}</Label>
                    <Textarea
                      rows={4}
                      value={form.payment_method}
                      onChange={(e) => setForm((f) => ({ ...f, payment_method: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>{t('notes')}</Label>
                    <Textarea
                      rows={4}
                      value={form.notes}
                      onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
                    />
                  </div>
                </div>

                <Button type="submit" disabled={submitting}>
                  {submitting ? <Loader2 className="size-4 animate-spin me-2" /> : null}
                  {editingInvoice ? tNav('common.save') : t('createAndSend')}
                </Button>
              </form>
            )}
          </CardContent>
        </Card>
      )}

      <Dialog open={emailTarget !== null} onOpenChange={(o) => !o && setEmailTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('sendEmail')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label>{t('email')}</Label>
            <Input
              type="email"
              dir="ltr"
              value={emailValue}
              onChange={(e) => setEmailValue(e.target.value)}
              placeholder={t('emailPlaceholder')}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEmailTarget(null)}>
              {tNav('common.cancel')}
            </Button>
            <Button onClick={() => void sendEmail()} disabled={emailBusy}>
              {emailBusy ? <Loader2 className="size-4 animate-spin me-2" /> : null}
              {t('send')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
