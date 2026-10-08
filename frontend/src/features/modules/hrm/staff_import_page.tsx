'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmDigits, HrmPageLayout } from '@/features/modules/hrm/HrmPageLayout';
import { HrmField, type HrmRow } from '@/features/modules/hrm/hrm_shared';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { useLocale } from '@/hooks/use-locale-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { downloadHrmFile, importStaffSpreadsheet } from '@/lib/api/hrm';

const COLUMNS = [
  'employee_code', 'first_name', 'last_name', 'national_id', 'father_name', 'mobile', 'email',
  'department', 'position', 'status', 'hire_date', 'base_salary', 'insurance_number', 'sheba',
] as const;
const REQUIRED = new Set(['employee_code', 'first_name', 'last_name']);
const COUNTS = ['total', 'created', 'updated', 'skipped'] as const;

export function StaffImportPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatNumber } = useLocale();
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const [report, setReport] = useState<HrmRow | null>(null);

  const run = async (dryRun: boolean) => {
    if (!file) return;
    setBusy(true);
    try {
      const res = await importStaffSpreadsheet(file, dryRun);
      setReport(res);
      setSuccess(dryRun ? t('staffImport.validated') : t('staffImport.imported'));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setBusy(false);
    }
  };

  const errors = Array.isArray(report?.errors) ? (report?.errors as HrmRow[]) : [];
  const dry = Boolean(report?.dry_run);
  const label = (field: unknown) => {
    const f = String(field ?? '');
    return t.has(`staffImport.columns.${f}`) ? t(`staffImport.columns.${f}`) : f || '—';
  };

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.staffImport')} description={t('staffImport.hint')} {...layoutProps}>
      <div className="space-y-4">
        <Card>
          <CardHeader><CardTitle className="text-base">{t('staffImport.downloads')}</CardTitle></CardHeader>
          <CardContent className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => void downloadHrmFile('staff/import-template?format=xlsx', 'staff-import-template.xlsx').catch(applyAxiosError)}>{t('staffImport.templateXlsx')}</Button>
            <Button variant="outline" onClick={() => void downloadHrmFile('staff/import-template?format=csv', 'staff-import-template.csv').catch(applyAxiosError)}>{t('staffImport.templateCsv')}</Button>
            <Button variant="outline" onClick={() => void downloadHrmFile('staff/export?format=xlsx', 'staff.xlsx').catch(applyAxiosError)}>{t('staffImport.exportXlsx')}</Button>
            <Button variant="outline" onClick={() => void downloadHrmFile('staff/export?format=csv', 'staff.csv').catch(applyAxiosError)}>{t('staffImport.exportCsv')}</Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('staffImport.columnsTitle')}</CardTitle></CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-2 text-sm">
              {COLUMNS.map((c) => (
                <span key={c} className="rounded border px-2 py-1">
                  {label(c)}{REQUIRED.has(c) ? <span className="text-destructive"> *</span> : null}
                </span>
              ))}
            </div>
            <p className="mt-2 text-xs text-muted-foreground">{t('staffImport.rules')}</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle className="text-base">{t('staffImport.upload')}</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <HrmField label={t('staffImport.file')} hint={t('staffImport.fileHint')}>
              <Input type="file" accept=".xlsx,.csv,.txt" onChange={(e) => { setFile(e.target.files?.[0] ?? null); setReport(null); }} />
            </HrmField>
            <div className="flex flex-wrap gap-2">
              <Button variant="outline" disabled={!file || busy} onClick={() => void run(true)}>{t('staffImport.validate')}</Button>
              <Button disabled={!file || busy} onClick={() => {
                if (errors.length > 0 && !window.confirm(t('staffImport.importWithErrors'))) return;
                void run(false);
              }}>{t('staffImport.import')}</Button>
            </div>
          </CardContent>
        </Card>

        {report ? (
          <Card>
            <CardHeader><CardTitle className="text-base">{dry ? t('staffImport.reportDry') : t('staffImport.report')}</CardTitle></CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-3 sm:grid-cols-4">
                {COUNTS.map((k) => (
                  <div key={k} className="rounded border p-3">
                    <div className="text-xs text-muted-foreground">{t(`staffImport.counts.${k}`)}</div>
                    <div className="text-lg font-semibold">{formatNumber(Number(report[k] ?? 0))}</div>
                  </div>
                ))}
              </div>
              {errors.length === 0 ? (
                <Alert><AlertDescription>{t('staffImport.noErrors')}</AlertDescription></Alert>
              ) : (
                <Table>
                  <TableHeader><TableRow>
                    <TableHead>{t('staffImport.row')}</TableHead>
                    <TableHead>{t('staffImport.field')}</TableHead>
                    <TableHead>{t('staffImport.error')}</TableHead>
                    <TableHead>{t('staffImport.value')}</TableHead>
                  </TableRow></TableHeader>
                  <TableBody>
                    {errors.map((e, idx) => {
                      const code = String(e.code ?? '');
                      return (
                        <TableRow key={idx}>
                          <TableCell><HrmDigits value={e.row} /></TableCell>
                          <TableCell>{label(e.field)}</TableCell>
                          <TableCell>{t.has(`staffImport.errors.${code}`) ? t(`staffImport.errors.${code}`) : code}</TableCell>
                          <TableCell><HrmDigits value={e.value} /></TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        ) : null}
      </div>
    </HrmPageLayout>
  );
}
