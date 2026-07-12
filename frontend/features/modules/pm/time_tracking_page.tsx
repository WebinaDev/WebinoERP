'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import apiClient from '@/lib/api-client';

export function TimeTrackingPage() {
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [projectId, setProjectId] = useState('');
  const [hours, setHours] = useState('');
  const [note, setNote] = useState('');

  const submit = async () => {
    try {
      await apiClient.post('/v1/projects/time-entries', {
        project_id: Number(projectId),
        hours: Number(hours),
        note,
      });
      setSuccess(tNav('common.saved'));
      setHours('');
      setNote('');
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout title={tNav('nav.erp.pm.timeTracking')} {...layoutProps}>
      <Card>
        <CardContent className="grid gap-4 pt-6 md:grid-cols-2">
          <Input placeholder="Project ID" value={projectId} onChange={(e) => setProjectId(e.target.value)} />
          <Input placeholder="Hours" value={hours} onChange={(e) => setHours(e.target.value)} />
          <Input className="md:col-span-2" placeholder="Note" value={note} onChange={(e) => setNote(e.target.value)} />
          <Button onClick={() => void submit()}>{tNav('common.save')}</Button>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
