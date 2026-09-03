'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import {
  createPayrollRun,
  getPayrollComponents,
  getPayrollRuns,
  getPayrollSettings,
  savePayrollComponent,
  savePayrollSettings,
} from '@/lib/api/hrm';
import { dashboardHref } from '@/lib/route-resolver';
import { normalizeListPayload } from '@/lib/list-utils';

export function PayrollPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [tab, setTab] = useState('runs');
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [components, setComponents] = useState<Record<string, unknown>[]>([]);
  const [settings, setSettings] = useState<Record<string, string>>({
    tax_rate: '',
    insurance_rate: '',
    overtime_rate: '',
  });
  const [loading, setLoading] = useState(true);
  const [runDialog, setRunDialog] = useState(false);
  const [compDialog, setCompDialog] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const now = new Date();
  const [runForm, setRunForm] = useState({
    title: '',
    year: String(now.getFullYear()),
    month: String(now.getMonth() + 1),
  });
  const [compForm, setCompForm] = useState({
    name: '',
    type: 'earning',
    calculation: 'fixed',
    default_amount: '',
  });

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [runsRes, compRes, settingsRes] = await Promise.all([
        getPayrollRuns(),
        getPayrollComponents(),
        getPayrollSettings(),
      ]);
      setRows(normalizeListPayload(runsRes as { data?: unknown }));
      setComponents(normalizeListPayload(compRes as { data?: unknown }));
      const s = (settingsRes as Record<string, unknown>) ?? {};
      setSettings({
        tax_rate: String(s.tax_rate ?? ''),
        insurance_rate: String(s.insurance_rate ?? ''),
        overtime_rate: String(s.overtime_rate ?? ''),
      });
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleCreateRun = async () => {
    if (!runForm.title.trim()) return;
    setSubmitting(true);
    try {
      await createPayrollRun({
        title: runForm.title.trim(),
        year: Number(runForm.year),
        month: Number(runForm.month),
      });
      setRunDialog(false);
      setRunForm({ title: '', year: String(now.getFullYear()), month: String(now.getMonth() + 1) });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveSettings = async () => {
    setSubmitting(true);
    try {
      const payload: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(settings)) {
        if (v !== '') payload[k] = Number(v);
      }
      await savePayrollSettings(payload);
      setSuccess(tNav('common.saved'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const handleSaveComponent = async () => {
    if (!compForm.name.trim()) return;
    setSubmitting(true);
    try {
      await savePayrollComponent({
        name: compForm.name.trim(),
        type: compForm.type,
        calculation: compForm.calculation || null,
        default_amount: compForm.default_amount !== '' ? Number(compForm.default_amount) : 0,
        is_active: true,
      });
      setCompDialog(false);
      setCompForm({ name: '', type: 'earning', calculation: 'fixed', default_amount: '' });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.hrm.payroll')}
      actions={
        tab === 'runs' ? (
          <Button onClick={() => setRunDialog(true)}>{t('newPayrollRun')}</Button>
        ) : tab === 'components' ? (
          <Button onClick={() => setCompDialog(true)}>{t('newComponent')}</Button>
        ) : null
      }
      {...layoutProps}
    >
      <Tabs value={tab} onValueChange={setTab}>
        <TabsList>
          <TabsTrigger value="runs">{t('payrollRuns')}</TabsTrigger>
          <TabsTrigger value="settings">{t('payrollSettings')}</TabsTrigger>
          <TabsTrigger value="components">{t('payrollComponents')}</TabsTrigger>
        </TabsList>

        <TabsContent value="runs">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('payrollRun')}</TableHead>
                    <TableHead>{t('period')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                    <TableHead>{t('net')}</TableHead>
                    <TableHead />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loading ? (
                    <TableRow><TableCell colSpan={5}><PageLoadingState /></TableCell></TableRow>
                  ) : rows.length === 0 ? (
                    <TableRow><TableCell colSpan={5}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    rows.map((r) => (
                      <TableRow key={String(r.id)}>
                        <TableCell>{String(r.title ?? r.period ?? r.name ?? r.id)}</TableCell>
                        <TableCell>
                          {r.year != null && r.month != null
                            ? `${r.year}/${String(r.month).padStart(2, '0')}`
                            : String(r.period ?? '—')}
                        </TableCell>
                        <TableCell>{String(r.status ?? '')}</TableCell>
                        <TableCell>{String(r.total_amount ?? r.total_net ?? r.total ?? '')}</TableCell>
                        <TableCell>
                          <Button variant="link" size="sm" asChild>
                            <Link href={dashboardHref(locale, `hrm/payroll/${r.id}`)}>{tNav('common.view')}</Link>
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

        <TabsContent value="settings">
          <Card>
            <CardContent className="grid max-w-lg gap-4 pt-6">
              <div className="space-y-1">
                <Label>{t('taxRate')}</Label>
                <Input type="number" value={settings.tax_rate} onChange={(e) => setSettings((s) => ({ ...s, tax_rate: e.target.value }))} />
              </div>
              <div className="space-y-1">
                <Label>{t('insuranceRate')}</Label>
                <Input type="number" value={settings.insurance_rate} onChange={(e) => setSettings((s) => ({ ...s, insurance_rate: e.target.value }))} />
              </div>
              <div className="space-y-1">
                <Label>{t('overtimeRate')}</Label>
                <Input type="number" value={settings.overtime_rate} onChange={(e) => setSettings((s) => ({ ...s, overtime_rate: e.target.value }))} />
              </div>
              <Button className="w-fit" onClick={() => void handleSaveSettings()} disabled={submitting}>
                {tNav('common.save')}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="components">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('componentName')}</TableHead>
                    <TableHead>{t('componentType')}</TableHead>
                    <TableHead>{t('defaultAmount')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {components.length === 0 ? (
                    <TableRow><TableCell colSpan={4}><PageEmptyState /></TableCell></TableRow>
                  ) : (
                    components.map((c) => (
                      <TableRow key={String(c.id)}>
                        <TableCell>{String(c.name ?? '')}</TableCell>
                        <TableCell>{String(c.type ?? '')}</TableCell>
                        <TableCell>{String(c.default_amount ?? '')}</TableCell>
                        <TableCell>{c.is_active === false ? t('inactive') : t('active')}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={runDialog} onOpenChange={setRunDialog}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newPayrollRun')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Input placeholder={t('runTitle')} value={runForm.title} onChange={(e) => setRunForm((f) => ({ ...f, title: e.target.value }))} />
            <div className="grid grid-cols-2 gap-2">
              <Input type="number" placeholder={t('year')} value={runForm.year} onChange={(e) => setRunForm((f) => ({ ...f, year: e.target.value }))} />
              <Input type="number" min={1} max={12} placeholder={t('month')} value={runForm.month} onChange={(e) => setRunForm((f) => ({ ...f, month: e.target.value }))} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setRunDialog(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleCreateRun()} disabled={submitting}>{tNav('common.add')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={compDialog} onOpenChange={setCompDialog}>
        <DialogContent>
          <DialogHeader><DialogTitle>{t('newComponent')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2">
            <Input placeholder={t('componentName')} value={compForm.name} onChange={(e) => setCompForm((f) => ({ ...f, name: e.target.value }))} />
            <Select value={compForm.type} onValueChange={(v) => setCompForm((f) => ({ ...f, type: v }))}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="earning">{t('earning')}</SelectItem>
                <SelectItem value="deduction">{t('deduction')}</SelectItem>
              </SelectContent>
            </Select>
            <Input placeholder={t('defaultAmount')} type="number" value={compForm.default_amount} onChange={(e) => setCompForm((f) => ({ ...f, default_amount: e.target.value }))} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCompDialog(false)}>{tNav('common.cancel')}</Button>
            <Button onClick={() => void handleSaveComponent()} disabled={submitting}>{tNav('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
