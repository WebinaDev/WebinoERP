'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import { deleteStaff, getOrgPositions, getStaff, saveOrgPosition, saveStaff, updateEmployee } from '@/lib/api/hrm';
import { dashboardHref } from '@/lib/route-resolver';
import { normalizeListPayload } from '@/lib/list-utils';

type StaffForm = {
  employee_code: string;
  first_name: string;
  last_name: string;
  email: string;
  mobile: string;
  department: string;
  position: string;
  hire_date: string;
  status: string;
  base_salary: string;
};

const emptyForm = (): StaffForm => ({
  employee_code: '',
  first_name: '',
  last_name: '',
  email: '',
  mobile: '',
  department: '',
  position: '',
  hire_date: '',
  status: 'active',
  base_salary: '',
});

export function StaffPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [orgRows, setOrgRows] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<StaffForm>(emptyForm);
  const [orgTitle, setOrgTitle] = useState('');
  const [orgDept, setOrgDept] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [staffRes, orgRes] = await Promise.all([getStaff(), getOrgPositions()]);
      setRows(normalizeListPayload(staffRes as { data?: unknown }));
      setOrgRows(normalizeListPayload(orgRes as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyForm());
    setDialogOpen(true);
  };

  const openEdit = (r: Record<string, unknown>) => {
    setEditingId(Number(r.id));
    setForm({
      employee_code: String(r.employee_code ?? ''),
      first_name: String(r.first_name ?? ''),
      last_name: String(r.last_name ?? ''),
      email: String(r.email ?? ''),
      mobile: String(r.mobile ?? ''),
      department: String(r.department ?? ''),
      position: String(r.position ?? ''),
      hire_date: String(r.hire_date ?? '').slice(0, 10),
      status: String(r.status ?? 'active'),
      base_salary: r.base_salary != null ? String(r.base_salary) : '',
    });
    setDialogOpen(true);
  };

  const handleSave = async () => {
    if (!form.employee_code.trim() || !form.first_name.trim() || !form.last_name.trim()) return;
    setSubmitting(true);
    try {
      const payload: Record<string, unknown> = {
        employee_code: form.employee_code.trim(),
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        email: form.email.trim() || null,
        mobile: form.mobile.trim() || null,
        department: form.department.trim() || null,
        position: form.position.trim() || null,
        hire_date: form.hire_date || null,
        status: form.status || 'active',
      };
      if (form.base_salary !== '') payload.base_salary = Number(form.base_salary);
      if (editingId != null) {
        await updateEmployee(editingId, payload);
      } else {
        await saveStaff(payload);
      }
      setDialogOpen(false);
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      await deleteStaff(id);
      setSuccess(tNav('common.deleted'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const handleOrgSave = async () => {
    if (!orgTitle.trim()) return;
    try {
      await saveOrgPosition({ title: orgTitle.trim(), department: orgDept.trim() || null });
      setOrgTitle('');
      setOrgDept('');
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.hrm.staff')}
      actions={<Button onClick={openCreate}>{t('addEmployee')}</Button>}
      {...layoutProps}
    >
      <Tabs defaultValue="staff">
        <TabsList>
          <TabsTrigger value="staff">{tNav('nav.erp.hrm.staff')}</TabsTrigger>
          <TabsTrigger value="org">{t('orgStructure')}</TabsTrigger>
        </TabsList>

        <TabsContent value="staff">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('staffCode')}</TableHead>
                    <TableHead>{t('firstName')}</TableHead>
                    <TableHead>{t('lastName')}</TableHead>
                    <TableHead>{t('department')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                    <TableHead />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loading ? (
                    <TableRow>
                      <TableCell colSpan={6}><PageLoadingState /></TableCell>
                    </TableRow>
                  ) : rows.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6}><PageEmptyState /></TableCell>
                    </TableRow>
                  ) : (
                    rows.map((r) => (
                      <TableRow key={String(r.id)}>
                        <TableCell>{String(r.employee_code ?? r.id)}</TableCell>
                        <TableCell>{String(r.first_name ?? '')}</TableCell>
                        <TableCell>{String(r.last_name ?? '')}</TableCell>
                        <TableCell>{String(r.department ?? '')}</TableCell>
                        <TableCell>{String(r.status ?? '')}</TableCell>
                        <TableCell className="flex flex-wrap gap-2">
                          <Button variant="link" size="sm" asChild>
                            <Link href={dashboardHref(locale, `hrm/staff/${r.id}`)}>{tNav('common.view')}</Link>
                          </Button>
                          <Button variant="outline" size="sm" onClick={() => openEdit(r)}>{tNav('common.edit')}</Button>
                          <Button variant="outline" size="sm" className="text-destructive" onClick={() => void handleDelete(Number(r.id))}>
                            {tNav('common.delete')}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="org">
          <Card>
            <CardContent className="space-y-4 pt-6">
              <div className="flex flex-wrap gap-2">
                <Input className="max-w-xs" placeholder={t('position')} value={orgTitle} onChange={(e) => setOrgTitle(e.target.value)} />
                <Input className="max-w-xs" placeholder={t('department')} value={orgDept} onChange={(e) => setOrgDept(e.target.value)} />
                <Button onClick={() => void handleOrgSave()}>{tNav('common.add')}</Button>
              </div>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('position')}</TableHead>
                    <TableHead>{t('department')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {orgRows.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={2}><PageEmptyState /></TableCell>
                    </TableRow>
                  ) : (
                    orgRows.map((r) => (
                      <TableRow key={String(r.id)}>
                        <TableCell>{String(r.title ?? '')}</TableCell>
                        <TableCell>{String(r.department ?? '')}</TableCell>
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
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editingId != null ? t('editEmployee') : t('addEmployee')}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3 py-2 sm:grid-cols-2">
            <Input placeholder={t('staffCode')} value={form.employee_code} onChange={(e) => setForm((f) => ({ ...f, employee_code: e.target.value }))} disabled={editingId != null} />
            <Input placeholder={t('status')} value={form.status} onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))} />
            <Input placeholder={t('firstName')} value={form.first_name} onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))} />
            <Input placeholder={t('lastName')} value={form.last_name} onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))} />
            <Input type="email" dir="ltr" placeholder={t('email')} value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} />
            <Input dir="ltr" placeholder={t('mobile')} value={form.mobile} onChange={(e) => setForm((f) => ({ ...f, mobile: e.target.value }))} />
            <Input placeholder={t('department')} value={form.department} onChange={(e) => setForm((f) => ({ ...f, department: e.target.value }))} />
            <Input placeholder={t('position')} value={form.position} onChange={(e) => setForm((f) => ({ ...f, position: e.target.value }))} />
            <Input type="date" value={form.hire_date} onChange={(e) => setForm((f) => ({ ...f, hire_date: e.target.value }))} />
            <Input type="number" placeholder={t('baseSalary')} value={form.base_salary} onChange={(e) => setForm((f) => ({ ...f, base_salary: e.target.value }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSave()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
