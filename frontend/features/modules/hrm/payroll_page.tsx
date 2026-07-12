'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { getPayrollRuns } from '@/lib/api/hrm';
import { dashboardHref } from '@/lib/route-resolver';
import { normalizeListPayload } from '@/lib/list-utils';

export function PayrollPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const res = await getPayrollRuns();
      setRows(normalizeListPayload(res as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.payroll')} {...layoutProps}>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader><TableRow><TableHead>{t('payrollRun')}</TableHead><TableHead>{t('status')}</TableHead><TableHead>{t('net')}</TableHead><TableHead /></TableRow></TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  <TableCell>{String(r.period ?? r.name ?? r.id)}</TableCell>
                  <TableCell>{String(r.status ?? '')}</TableCell>
                  <TableCell>{String(r.total_net ?? r.total ?? '')}</TableCell>
                  <TableCell>
                    <Button variant="link" size="sm" asChild>
                      <Link href={dashboardHref(locale, `hrm/payroll/${r.id}`)}>{tNav('common.view')}</Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
