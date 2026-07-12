'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { toast } from 'sonner';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  importPath?: string;
  onImported?: () => void;
};

export function ImportCsvDialog({
  open,
  onOpenChange,
  importPath = '/v1/crm/accounts/import',
  onImported,
}: Props) {
  const t = useTranslations();
  const [file, setFile] = useState<File | null>(null);
  const [pending, setPending] = useState(false);

  async function onSubmit() {
    if (!file) return;
    setPending(true);
    try {
      const form = new FormData();
      form.append('file', file);
      await apiClient.post(importPath, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success(t('crm.import.success'));
      onOpenChange(false);
      setFile(null);
      onImported?.();
    } catch (e) {
      toast.error(getAxiosMessage(e));
    } finally {
      setPending(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('crm.import.title')}</DialogTitle>
        </DialogHeader>
        <Input
          type="file"
          accept=".csv,text/csv"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
        />
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={!file || pending} onClick={() => void onSubmit()}>
            {t('crm.import.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
