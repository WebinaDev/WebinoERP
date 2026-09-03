'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { FileDown, Loader2, Mail, Pencil, Plus } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmEmptyState } from '@/features/shared/pm';
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

type InvoiceRow = Record<string, unknown>;

type FormState = {
  number: string;
  customer_name: string;
  total: string;
  status: string;
  issue_date: string;
};

const emptyForm = (): FormState => ({
  number: '',
  customer_name: '',
  total: '0',
  status: 'draft',
  issue_date: new Date().toISOString().slice(0, 10),
});

export function InvoicesPage() {
  const t = useTranslations('sales.invoices');
  const tHrm = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, setError, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<InvoiceRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [submitting, setSubmitting] = useState(false);
  const [emailTarget, setEmailTarget] = useState<InvoiceRow | null>(null);
  const [emailValue, setEmailValue] = useState('');
  const [emailBusy, setEmailBusy] = useState(false);
  const [pdfBusyId, setPdfBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/v1/sales/invoices');
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const openNew = () => {
    setEditingId(null);
    setForm(emptyForm());
    setOpen(true);
  };

  const openEdit = (row: InvoiceRow) => {
    setEditingId(Number(row.id));
    setForm({
      number: String(row.number ?? ''),
      customer_name: String(row.customer_name ?? ''),
      total: String(row.total ?? '0'),
      status: String(row.status ?? 'draft'),
      issue_date: String(row.issue_date ?? '').slice(0, 10) || new Date().toISOString().slice(0, 10),
    });
    setOpen(true);
  };

  const save = async () => {
    if (!form.number.trim() || !form.customer_name.trim()) {
      setError(t('requiredFields'));
      return;
    }
    setSubmitting(true);
    try {
      const payload = {
        number: form.number.trim(),
        customer_name: form.customer_name.trim(),
        total: Number(form.total) || 0,
        status: form.status,
        issue_date: form.issue_date || null,
      };
      if (editingId) {
        await apiClient.patch(`/v1/sales/invoices/${editingId}`, payload);
      } else {
        await apiClient.post('/v1/sales/invoices', payload);
      }
      setOpen(false);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const pdf = async (id: number) => {
    setPdfBusyId(id);
    try {
      const res = await apiClient.post(`/v1/sales/invoices/${id}/pdf`);
      const data = unwrapData(res) as { content_base64?: string; filename?: string };
      if (data.content_base64) {
        const bin = atob(data.content_base64);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const blob = new Blob([bytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = data.filename || `invoice-${id}.pdf`;
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
      setSuccess(t('emailQueued'));
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
        <Button onClick={openNew}>
          <Plus className="size-4 me-2" />
          {tNav('common.add')}
        </Button>
      }
      {...layoutProps}
    >
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
                  <TableHead>#</TableHead>
                  <TableHead>{t('customer')}</TableHead>
                  <TableHead>{t('total')}</TableHead>
                  <TableHead>{tHrm('status')}</TableHead>
                  <TableHead className="text-end">{tNav('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((r) => (
                  <TableRow key={String(r.id)}>
                    <TableCell>{String(r.number ?? r.id)}</TableCell>
                    <TableCell>{String(r.customer_name ?? '')}</TableCell>
                    <TableCell dir="ltr">{String(r.total ?? '')}</TableCell>
                    <TableCell>{String(r.status ?? '')}</TableCell>
                    <TableCell className="text-end">
                      <div className="flex flex-wrap justify-end gap-2">
                        <Button size="sm" variant="outline" onClick={() => openEdit(r)}>
                          <Pencil className="size-4" />
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          disabled={pdfBusyId === Number(r.id)}
                          onClick={() => void pdf(Number(r.id))}
                        >
                          {pdfBusyId === Number(r.id) ? (
                            <Loader2 className="size-4 animate-spin" />
                          ) : (
                            <FileDown className="size-4 me-1" />
                          )}
                          PDF
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => {
                            setEmailTarget(r);
                            setEmailValue('');
                          }}
                        >
                          <Mail className="size-4 me-1" />
                          {t('email')}
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

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingId ? t('editInvoice') : t('newInvoice')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="space-y-2">
              <Label>#</Label>
              <Input value={form.number} onChange={(e) => setForm({ ...form, number: e.target.value })} />
            </div>
            <div className="space-y-2">
              <Label>{t('customer')}</Label>
              <Input
                value={form.customer_name}
                onChange={(e) => setForm({ ...form, customer_name: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label>{t('total')}</Label>
              <Input
                dir="ltr"
                type="number"
                value={form.total}
                onChange={(e) => setForm({ ...form, total: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label>{tHrm('status')}</Label>
              <Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v })}>
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
            <div className="space-y-2">
              <Label>{t('issueDate')}</Label>
              <LocaleDatePicker
                value={form.issue_date}
                onChange={(v) => setForm({ ...form, issue_date: v })}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)}>
              {tNav('common.cancel')}
            </Button>
            <Button onClick={() => void save()} disabled={submitting}>
              {submitting ? <Loader2 className="size-4 animate-spin me-2" /> : null}
              {tNav('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

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
