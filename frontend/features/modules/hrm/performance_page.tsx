'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { normalizeListPayload } from '@/lib/list-utils';

export function PerformancePage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [templates, setTemplates] = useState<Record<string, unknown>[]>([]);
  const [cycles, setCycles] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const [t, c] = await Promise.all([
        apiClient.get('/v1/hrm/performance/kpi-templates'),
        apiClient.get('/v1/hrm/performance/cycles'),
      ]);
      setTemplates(normalizeListPayload(t.data));
      setCycles(normalizeListPayload(c.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.performance')} {...layoutProps}>
      <Tabs defaultValue="templates">
        <TabsList><TabsTrigger value="templates">{t('performanceTemplates')}</TabsTrigger><TabsTrigger value="cycles">{t('performanceCycles')}</TabsTrigger></TabsList>
        <TabsContent value="templates">
          <Card><CardContent className="pt-6">
            <Table>
              <TableHeader><TableRow><TableHead>{t('firstName')}</TableHead></TableRow></TableHeader>
              <TableBody>{templates.map((r) => <TableRow key={String(r.id)}><TableCell>{String(r.name ?? '')}</TableCell></TableRow>)}</TableBody>
            </Table>
          </CardContent></Card>
        </TabsContent>
        <TabsContent value="cycles">
          <Card><CardContent className="pt-6">
            <Table>
              <TableHeader><TableRow><TableHead>{t('firstName')}</TableHead><TableHead>{t('status')}</TableHead></TableRow></TableHeader>
              <TableBody>{cycles.map((r) => <TableRow key={String(r.id)}><TableCell>{String(r.name ?? '')}</TableCell><TableCell>{String(r.status ?? '')}</TableCell></TableRow>)}</TableBody>
            </Table>
          </CardContent></Card>
        </TabsContent>
      </Tabs>
    </CrmPageLayout>
  );
}
