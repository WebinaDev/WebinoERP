'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { HrmDigits, HrmPageLayout, HrmStatus } from '@/features/modules/hrm/HrmPageLayout';
import { StaffOnboardingWizard } from '@/features/modules/hrm/staff_onboarding_wizard';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import { deleteStaff, getOrgPositions, getStaff, saveOrgPosition } from '@/lib/api/hrm';
import { dashboardHref } from '@/lib/route-resolver';
import { normalizeListPayload } from '@/lib/list-utils';

export function StaffPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [orgRows, setOrgRows] = useState<Record<string, unknown>[]>([]);
  const [loading, setLoading] = useState(true);
  const [wizardOpen, setWizardOpen] = useState(false);
  const [editing, setEditing] = useState<Record<string, unknown> | null>(null);
  const [orgTitle, setOrgTitle] = useState('');
  const [orgDept, setOrgDept] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [staffRes, orgRes] = await Promise.all([
        getStaff({ per_page: 100 }),
        getOrgPositions({ per_page: 100 }),
      ]);
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
    <HrmPageLayout
      title={tNav('nav.erp.hrm.staff')}
      actions={<Button onClick={() => { setEditing(null); setWizardOpen(true); }}>{t('addEmployee')}</Button>}
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
                    <TableHead>{t('nationalId')}</TableHead>
                    <TableHead>{t('firstName')}</TableHead>
                    <TableHead>{t('lastName')}</TableHead>
                    <TableHead>{t('department')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                    <TableHead />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loading ? (
                    <TableRow><TableCell colSpan={6}><PageLoadingState /></TableCell></TableRow>
                  ) : rows.length === 0 ? (
                    <TableRow><TableCell colSpan={6}><PageEmptyState /></TableCell></TableRow>
                  ) : rows.map((r) => (
                    <TableRow key={String(r.id)}>
                      <TableCell><HrmDigits value={r.employee_code ?? r.id} /></TableCell>
                      <TableCell>{String(r.first_name ?? '')}</TableCell>
                      <TableCell>{String(r.last_name ?? '')}</TableCell>
                      <TableCell>{String(r.department ?? '')}</TableCell>
                      <TableCell><HrmStatus value={r.status} /></TableCell>
                      <TableCell className="flex flex-wrap gap-2">
                        <Button variant="link" size="sm" asChild>
                          <Link href={dashboardHref(locale, `hrm/staff/${r.id}`)}>{tNav('common.view')}</Link>
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => { setEditing(r); setWizardOpen(true); }}>{tNav('common.edit')}</Button>
                        <Button variant="outline" size="sm" className="text-destructive" onClick={() => void handleDelete(Number(r.id))}>
                          {tNav('common.delete')}
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
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
                    <TableRow><TableCell colSpan={2}><PageEmptyState /></TableCell></TableRow>
                  ) : orgRows.map((r) => (
                    <TableRow key={String(r.id)}>
                      <TableCell>{String(r.title ?? '')}</TableCell>
                      <TableCell>{String(r.department ?? '')}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
      <StaffOnboardingWizard
        open={wizardOpen}
        editing={editing}
        orgRows={orgRows}
        onClose={() => setWizardOpen(false)}
        onSaved={() => void load()}
      />
    </HrmPageLayout>
  );
}
