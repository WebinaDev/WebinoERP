'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
import { useLocale } from '@/hooks/use-locale-next';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import {
  approveLeaveRequest,
  getLeaveRequests,
  getLeaveTypes,
  getStaff,
  rejectLeaveRequest,
  saveLeaveRequest,
  saveLeaveType,
} from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';

export function LeavePage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { formatDate } = useLocale();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [tab, setTab] = useState('mine');
  const [allRows, setAllRows] = useState<Record<string, unknown>[]>([]);
  const [pendingRows, setPendingRows] = useState<Record<string, unknown>[]>([]);
  const [types, setTypes] = useState<Record<string, unknown>[]>([]);
  const [employees, setEmployees] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState({
    employee_id: '',
    type: '',
    start_date: '',
    end_date: '',
    reason: '',
  });
  const [typeForm, setTypeForm] = useState({ name: '', default_days: '0' });

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [all, pending, typeRes, staffRes] = await Promise.all([
        getLeaveRequests(),
        getLeaveRequests({ 'filter.status': 'pending' }),
        getLeaveTypes(),
        getStaff(),
      ]);
      setAllRows(normalizeListPayload(all as { data?: unknown }));
      setPendingRows(normalizeListPayload(pending as { data?: unknown }));
      setTypes(normalizeListPayload(typeRes as { data?: unknown }));
      setEmployees(normalizeListPayload(staffRes as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleCreateRequest = async () => {
    if (!form.employee_id || !form.type || !form.start_date || !form.end_date) return;
    setSubmitting(true);
    try {
      await saveLeaveRequest({
        employee_id: Number(form.employee_id),
        type: form.type,
        start_date: form.start_date,
        end_date: form.end_date,
        reason: form.reason || null,
      });
      setDialogOpen(false);
      setForm({ employee_id: '', type: '', start_date: '', end_date: '', reason: '' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveType = async () => {
    if (!typeForm.name.trim()) return;
    setSubmitting(true);
    try {
      await saveLeaveType({
        name: typeForm.name.trim(),
        default_days: Number(typeForm.default_days) || 0,
        is_active: true,
      });
      setTypeForm({ name: '', default_days: '0' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const employeeLabel = (r: Record<string, unknown>) => {
    const emp = r.employee as Record<string, unknown> | undefined;
    if (emp) return `${String(emp.first_name ?? '')} ${String(emp.last_name ?? '')}`.trim() || String(emp.id);
    return String(r.employee_id ?? '');
  };

  const requestTable = (rows: Record<string, unknown>[], showActions?: boolean) => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>{t('employee')}</TableHead>
          <TableHead>{t('leaveType')}</TableHead>
          <TableHead>{t('dateFrom')}</TableHead>
          <TableHead>{t('dateTo')}</TableHead>
          <TableHead>{t('status')}</TableHead>
          {showActions ? <TableHead /> : null}
        </TableRow>
      </TableHeader>
      <TableBody>
        {loading ? (
          <TableRow><TableCell colSpan={showActions ? 6 : 5}><PageLoadingState /></TableCell></TableRow>
        ) : rows.length === 0 ? (
          <TableRow><TableCell colSpan={showActions ? 6 : 5}><PageEmptyState /></TableCell></TableRow>
        ) : (
          rows.map((r) => (
            <TableRow key={String(r.id)}>
              <TableCell>{employeeLabel(r)}</TableCell>
              <TableCell>{String(r.type ?? r.leave_type_id ?? '')}</TableCell>
              <TableCell>{formatDate(String(r.start_date ?? '')) || '—'}</TableCell>
              <TableCell>{formatDate(String(r.end_date ?? '')) || '—'}</TableCell>
              <TableCell>{String(r.status ?? '')}</TableCell>
              {showActions && String(r.status) === 'pending' ? (
                <TableCell className="flex gap-2">
                  <Button
                    size="sm"
                    onClick={() =>
                      void approveLeaveRequest(r.id as number).then(() => {
                        setSuccess(tNav('common.saved'));
                        void load();
                      }).catch(applyAxiosError)
                    }
                  >
                    {t('approve')}
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                      void rejectLeaveRequest(r.id as number).then(() => {
                        setSuccess(tNav('common.saved'));
                        void load();
                      }).catch(applyAxiosError)
                    }
                  >
                    {t('reject')}
                  </Button>
                </TableCell>
              ) : showActions ? (
                <TableCell />
              ) : null}
            </TableRow>
          ))
        )}
      </TableBody>
    </Table>
  );

  return (
    <CrmPageLayout
      title={tNav('nav.erp.hrm.leave')}
      actions={<Button onClick={() => setDialogOpen(true)}>{t('newLeaveRequest')}</Button>}
      {...layoutProps}
    >
      <Tabs value={tab} onValueChange={setTab}>
        <TabsList>
          <TabsTrigger value="mine">{t('myRequests')}</TabsTrigger>
          <TabsTrigger value="pending">{t('pendingRequests')}</TabsTrigger>
          <TabsTrigger value="types">{t('leaveTypes')}</TabsTrigger>
        </TabsList>

        <TabsContent value="mine">
          <Card><CardContent className="pt-6">{requestTable(allRows)}</CardContent></Card>
        </TabsContent>

        <TabsContent value="pending">
          <Card><CardContent className="pt-6">{requestTable(pendingRows, true)}</CardContent></Card>
        </TabsContent>

        <TabsContent value="types">
          <Card>
            <CardContent className="space-y-4 pt-6">
              <div className="flex flex-wrap gap-2">
                <Input className="max-w-xs" placeholder={t('leaveTypeName')} value={typeForm.name} onChange={(e) => setTypeForm((f) => ({ ...f, name: e.target.value }))} />
                <Input className="max-w-[8rem]" type="number" placeholder={t('defaultDays')} value={typeForm.default_days} onChange={(e) => setTypeForm((f) => ({ ...f, default_days: e.target.value }))} />
                <Button onClick={() => void handleSaveType()} disabled={submitting}>{tNav('common.add')}</Button>
              </div>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('leaveTypeName')}</TableHead>
                    <TableHead>{t('defaultDays')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {types.length === 0 ? (
                    <TableRow><TableCell colSpan={3}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    types.map((row) => (
                      <TableRow key={String(row.id)}>
                        <TableCell>{String(row.name ?? '')}</TableCell>
                        <TableCell>{String(row.default_days ?? '')}</TableCell>
                        <TableCell>{row.is_active === false ? t('inactive') : t('active')}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newLeaveRequest')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Select value={form.employee_id || undefined} onValueChange={(v) => setForm((f) => ({ ...f, employee_id: v }))}>
              <SelectTrigger><SelectValue placeholder={t('employee')} /></SelectTrigger>
              <SelectContent>
                {employees.map((e) => (
                  <SelectItem key={String(e.id)} value={String(e.id)}>
                    {`${String(e.first_name ?? '')} ${String(e.last_name ?? '')}`.trim() || String(e.employee_code)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={form.type || undefined} onValueChange={(v) => setForm((f) => ({ ...f, type: v }))}>
              <SelectTrigger><SelectValue placeholder={t('leaveType')} /></SelectTrigger>
              <SelectContent>
                {types.map((lt) => (
                  <SelectItem key={String(lt.id)} value={String(lt.name ?? lt.id)}>{String(lt.name ?? '')}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <LocaleDatePicker value={form.start_date} onChange={(v) => setForm((f) => ({ ...f, start_date: v }))} />
            <LocaleDatePicker value={form.end_date} onChange={(v) => setForm((f) => ({ ...f, end_date: v }))} />
            <Textarea placeholder={t('reason')} value={form.reason} onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleCreateRequest()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
