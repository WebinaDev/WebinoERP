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

export function TrainingPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [courses, setCourses] = useState<Record<string, unknown>[]>([]);
  const [sessions, setSessions] = useState<Record<string, unknown>[]>([]);

  const load = useCallback(async () => {
    try {
      const [c, s] = await Promise.all([
        apiClient.get('/v1/hrm/training/courses'),
        apiClient.get('/v1/hrm/training/sessions'),
      ]);
      setCourses(normalizeListPayload(c.data));
      setSessions(normalizeListPayload(s.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <CrmPageLayout title={tNav('nav.erp.hrm.training')} {...layoutProps}>
      <Tabs defaultValue="courses">
        <TabsList><TabsTrigger value="courses">{t('trainingCourses')}</TabsTrigger><TabsTrigger value="sessions">{t('trainingSessions')}</TabsTrigger></TabsList>
        <TabsContent value="courses">
          <Card><CardContent className="pt-6">
            <Table>
              <TableHeader><TableRow><TableHead>{t('firstName')}</TableHead></TableRow></TableHeader>
              <TableBody>{courses.map((r) => <TableRow key={String(r.id)}><TableCell>{String(r.title ?? r.name ?? '')}</TableCell></TableRow>)}</TableBody>
            </Table>
          </CardContent></Card>
        </TabsContent>
        <TabsContent value="sessions">
          <Card><CardContent className="pt-6">
            <Table>
              <TableHeader><TableRow><TableHead>{t('trainingCourses')}</TableHead><TableHead>{t('attendanceDate')}</TableHead></TableRow></TableHeader>
              <TableBody>{sessions.map((r) => <TableRow key={String(r.id)}><TableCell>{String(r.course_id ?? '')}</TableCell><TableCell>{String(r.scheduled_at ?? '')}</TableCell></TableRow>)}</TableBody>
            </Table>
          </CardContent></Card>
        </TabsContent>
      </Tabs>
    </CrmPageLayout>
  );
}
