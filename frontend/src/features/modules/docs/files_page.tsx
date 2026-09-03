'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';

export function FilesPage() {
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const fileRef = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/docs/files');
      setRows(normalizeListPayload(res.data));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  const upload = async (file: File) => {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('name', file.name);
    try {
      await apiClient.post('/v1/docs/files', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      setSuccess(tNav('common.saved'));
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.docs.files')}
      actions={
        <>
          <input ref={fileRef} type="file" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) void upload(f); }} />
          <Button onClick={() => fileRef.current?.click()}>{tNav('common.add')}</Button>
        </>
      }
      {...layoutProps}
    >
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Type</TableHead><TableHead>Size</TableHead><TableHead /></TableRow></TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={String(r.id)}>
                  <TableCell>{String(r.name ?? '')}</TableCell>
                  <TableCell>{String(r.mime_type ?? '')}</TableCell>
                  <TableCell>{String(r.size ?? '')}</TableCell>
                  <TableCell>
                    <Button size="sm" variant="link" asChild>
                      <a href={`${process.env.NEXT_PUBLIC_API_URL || ''}/v1/docs/files/${r.id}/download`} target="_blank" rel="noreferrer">Download</a>
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
