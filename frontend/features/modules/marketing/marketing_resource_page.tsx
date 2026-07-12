'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';

type MarketingResourcePageProps = {
  titleKey: string;
  endpoint: string;
  columns: { key: string; labelKey: string }[];
};

export function MarketingResourcePage({ titleKey, endpoint, columns }: MarketingResourcePageProps) {
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get(endpoint);
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError, endpoint]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav(titleKey)} {...layoutProps}>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                {columns.map((c) => (
                  <TableHead key={c.key}>{tNav(c.labelKey)}</TableHead>
                ))}
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  {columns.map((c) => (
                    <TableCell key={c.key}>{String(r[c.key] ?? '')}</TableCell>
                  ))}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
