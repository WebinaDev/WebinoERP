'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmPageLayout, HrmStatus } from '@/features/modules/hrm/HrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import { useLocale } from '@/hooks/use-locale-next';
import { getLoans, saveLoan, settleLoan } from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';

export function LoansPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatNumber, formatDate } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({
    employee_id: '',
    type: 'loan',
    title: '',
    principal: '',
    installment_amount: '',
    start_date: '',
  });

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setRows(normalizeListPayload((await getLoans()) as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const create = async () => {
    if (!form.employee_id || !form.principal || !form.installment_amount) return;
    try {
      await saveLoan({
        employee_id: Number(form.employee_id),
        type: form.type,
        title: form.title || null,
        principal: Number(form.principal),
        installment_amount: Number(form.installment_amount),
        start_date: form.start_date || null,
      });
      setOpen(false);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const settle = async (id: number) => {
    try {
      await settleLoan(id);
      setSuccess(t('loans.settled'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.loans')} actions={<Button onClick={() => setOpen(true)}>{t('loans.new')}</Button>} {...layoutProps}>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('employee')}</TableHead>
                <TableHead>{t('loans.type')}</TableHead>
                <TableHead>{t('loans.principal')}</TableHead>
                <TableHead>{t('loans.installment')}</TableHead>
                <TableHead>{t('loans.balance')}</TableHead>
                <TableHead>{t('status')}</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                <TableRow><TableCell colSpan={7}><PageLoadingState /></TableCell></TableRow>
              ) : rows.length === 0 ? (
                <TableRow><TableCell colSpan={7}><PageEmptyState /></TableCell></TableRow>
              ) : rows.map((r) => {
                const emp = r.employee as { first_name?: string; last_name?: string } | undefined;
                return (
                  <TableRow key={String(r.id)}>
                    <TableCell>{emp ? `${emp.first_name ?? ''} ${emp.last_name ?? ''}` : String(r.employee_id ?? '')}</TableCell>
                    <TableCell>{String(r.type) === 'advance' ? t('loans.advance') : t('loans.loan')}</TableCell>
                    <TableCell>{formatNumber(Number(r.principal ?? 0))}</TableCell>
                    <TableCell>{formatNumber(Number(r.installment_amount ?? 0))}</TableCell>
                    <TableCell>{formatNumber(Number(r.remaining_balance ?? 0))}</TableCell>
                    <TableCell><HrmStatus value={r.status} />{r.start_date ? ` · ${formatDate(String(r.start_date)) || ''}` : ''}</TableCell>
                    <TableCell>
                      {String(r.status) === 'active' ? (
                        <Button size="sm" variant="outline" onClick={() => void settle(Number(r.id))}>{t('loans.settle')}</Button>
                      ) : null}
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('loans.new')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Input placeholder={t('loans.employeeId')} value={form.employee_id} onChange={(e) => setForm((f) => ({ ...f, employee_id: e.target.value }))} />
            <Select value={form.type} onValueChange={(v) => setForm((f) => ({ ...f, type: v }))}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="loan">{t('loans.loan')}</SelectItem>
                <SelectItem value="advance">{t('loans.advance')}</SelectItem>
              </SelectContent>
            </Select>
            <Input placeholder={t('loans.title')} value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} />
            <Input type="number" placeholder={t('loans.principal')} value={form.principal} onChange={(e) => setForm((f) => ({ ...f, principal: e.target.value }))} />
            <Input type="number" placeholder={t('loans.installment')} value={form.installment_amount} onChange={(e) => setForm((f) => ({ ...f, installment_amount: e.target.value }))} />
            <LocaleDatePicker value={form.start_date} onChange={(v) => setForm((f) => ({ ...f, start_date: v }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void create()}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </HrmPageLayout>
  );
}
