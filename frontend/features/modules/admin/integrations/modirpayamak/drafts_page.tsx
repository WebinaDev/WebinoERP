'use client';

import { useCallback, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { DraftingCompass, Loader2, Plus, Send, Trash2 } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmConfirmDialog, PmEmptyState } from '@/features/shared/pm';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PermissionGate } from '@/components/auth/PermissionGate';
import {
  edgeCreateDraft,
  edgeDeleteDraft,
  edgeField,
  edgeListDrafts,
  type EdgeRow,
} from '@/lib/api/modirpayamak-edge';
import { dashboardHref } from '@/lib/route-resolver';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';
import { useModirPayamakEdge } from './hooks/useModirPayamakEdge';

export function ModirpayamakDraftsPage() {
  const t = useTranslations('modirpayamak.drafts');
  const tCommon = useTranslations('common');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { layoutProps, setError, setSuccess } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const loader = useCallback(() => edgeListDrafts(), []);
  const { items, loading, reload } = useModirPayamakEdge(loader);

  const [createOpen, setCreateOpen] = useState(false);
  const [title, setTitle] = useState('');
  const [message, setMessage] = useState('');
  const [creating, setCreating] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState(false);

  const draftId = (row: EdgeRow) => Number(edgeField(row, 'id', 'draft_id')) || 0;

  const createDraft = async () => {
    setCreating(true);
    const res = await edgeCreateDraft({ title: title.trim(), message: message.trim(), body: message.trim() });
    setCreating(false);
    if (res.ok) {
      setSuccess(tCommon('saved'));
      setCreateOpen(false);
      setTitle('');
      setMessage('');
      void reload();
    } else {
      setError(res.message || tCommon('error'));
    }
  };

  const confirmDelete = async () => {
    if (!deleteId) return;
    setDeleting(true);
    const res = await edgeDeleteDraft(deleteId);
    setDeleting(false);
    if (res.ok) {
      setSuccess(tCommon('deleted'));
      setDeleteId(null);
      void reload();
    } else {
      setError(res.message || tCommon('error'));
    }
  };

  const sendHref = (row: EdgeRow) => {
    const text = edgeField(row, 'message', 'body', 'text');
    const base = dashboardHref(locale, 'admin/integrations/modirpayamak/send');
    return `${base}?message=${encodeURIComponent(text === '—' ? '' : text)}`;
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.admin.mpDrafts')}
      {...layoutProps}
      actions={
        configured ? (
          <Button size="sm" onClick={() => setCreateOpen(true)}>
            <Plus className="me-2 h-4 w-4" />
            {t('addDraft')}
          </Button>
        ) : undefined
      }
    >
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpDrafts')} />
      <PermissionGate permission="integrations.modirpayamak.manage">
        {configLoading ? null : !configured ? (
          <ModirPayamakNotConfigured />
        ) : loading ? (
          <div className="flex justify-center py-12">
            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
          </div>
        ) : items.length === 0 ? (
          <PmEmptyState title={t('empty')} />
        ) : (
          <Card>
            <CardContent className="p-0 pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('name')}</TableHead>
                    <TableHead>{t('message')}</TableHead>
                    <TableHead>{t('date')}</TableHead>
                    <TableHead className="w-[100px]">{tCommon('actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.map((row, i) => (
                    <TableRow key={draftId(row) || i}>
                      <TableCell>{edgeField(row, 'title', 'name', 'subject')}</TableCell>
                      <TableCell className="max-w-md truncate">
                        {edgeField(row, 'message', 'body', 'text')}
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {edgeField(row, 'created_at', 'updated_at')}
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button type="button" variant="ghost" size="icon" asChild>
                            <Link href={sendHref(row)}>
                              <Send className="h-4 w-4" />
                            </Link>
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-destructive"
                            onClick={() => setDeleteId(draftId(row) || null)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}

        <Dialog open={createOpen} onOpenChange={setCreateOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>{t('addDraft')}</DialogTitle>
            </DialogHeader>
            <div className="space-y-3">
              <div className="space-y-2">
                <Label>{t('name')}</Label>
                <Input value={title} onChange={(e) => setTitle(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label>{t('message')}</Label>
                <Textarea rows={4} value={message} onChange={(e) => setMessage(e.target.value)} />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setCreateOpen(false)}>
                {tCommon('cancel')}
              </Button>
              <Button disabled={creating} onClick={() => void createDraft()}>
                {tCommon('save')}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <PmConfirmDialog
          open={deleteId != null}
          title={tCommon('delete')}
          description={t('confirmDelete')}
          onConfirm={() => void confirmDelete()}
          onCancel={() => setDeleteId(null)}
          pending={deleting}
        />
      </PermissionGate>
    </CrmPageLayout>
  );
}
