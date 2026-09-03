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

export function RecruitmentPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [postings, setPostings] = useState<Record<string, unknown>[]>([]);
  const [applicants, setApplicants] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const [p, a] = await Promise.all([
        apiClient.get('/v1/hrm/recruitment/postings'),
        apiClient.get('/v1/hrm/recruitment/applicants'),
      ]);
      setPostings(normalizeListPayload(p.data));
      setApplicants(normalizeListPayload(a.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.recruitment')} {...layoutProps}>
      <Tabs defaultValue="postings">
        <TabsList><TabsTrigger value="postings">{t('recruitmentPostings')}</TabsTrigger><TabsTrigger value="applicants">{t('recruitmentApplicants')}</TabsTrigger></TabsList>
        <TabsContent value="postings">
          <Card><CardContent className="pt-6">
            <Table>
              <TableHeader><TableRow><TableHead>{t('firstName')}</TableHead><TableHead>{t('status')}</TableHead></TableRow></TableHeader>
              <TableBody>{postings.map((r) => <TableRow key={String(r.id)}><TableCell>{String(r.title ?? '')}</TableCell><TableCell>{String(r.status ?? '')}</TableCell></TableRow>)}</TableBody>
            </Table>
          </CardContent></Card>
        </TabsContent>
        <TabsContent value="applicants">
          <Card><CardContent className="pt-6">
            <Table>
              <TableHeader><TableRow><TableHead>{t('firstName')}</TableHead><TableHead>{t('status')}</TableHead></TableRow></TableHeader>
              <TableBody>{applicants.map((r) => <TableRow key={String(r.id)}><TableCell>{String(r.name ?? '')}</TableCell><TableCell>{String(r.status ?? '')}</TableCell></TableRow>)}</TableBody>
            </Table>
          </CardContent></Card>
        </TabsContent>
      </Tabs>
    </CrmPageLayout>
  );
}
