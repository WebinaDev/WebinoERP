'use client';

import { useCallback, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Headphones, Loader2, Plus } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmEmptyState } from '@/features/shared/pm';
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
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { PermissionGate } from '@/components/auth/PermissionGate';
import {
  edgeCreateTicket,
  edgeField,
  edgeGetTicket,
  edgeListTickets,
  edgeReplyTicket,
  type EdgeRow,
} from '@/lib/api/modirpayamak-edge';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { ModirPayamakStatusBadge } from './components/status-badge';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';
import { useModirPayamakEdge } from './hooks/useModirPayamakEdge';

export function ModirpayamakTicketsPage() {
  const t = useTranslations('modirpayamak.tickets');
  const tCommon = useTranslations('common');
  const tNav = useTranslations();
  const { layoutProps, setError, setSuccess } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const loader = useCallback(() => edgeListTickets(), []);
  const { items, loading, reload } = useModirPayamakEdge(loader);

  const [createOpen, setCreateOpen] = useState(false);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [creating, setCreating] = useState(false);

  const [detailOpen, setDetailOpen] = useState(false);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [detail, setDetail] = useState<EdgeRow | null>(null);
  const [reply, setReply] = useState('');
  const [replying, setReplying] = useState(false);

  const ticketId = (row: EdgeRow) => Number(edgeField(row, 'id', 'ticket_id')) || 0;

  const openTicket = async (row: EdgeRow) => {
    const id = ticketId(row);
    if (!id) return;
    setDetailId(id);
    const res = await edgeGetTicket(id);
    setDetail(res.item ?? row);
    setDetailOpen(true);
  };

  const createTicket = async () => {
    setCreating(true);
    const res = await edgeCreateTicket({ subject: subject.trim(), body: body.trim(), message: body.trim() });
    setCreating(false);
    if (res.ok) {
      setSuccess(tCommon('saved'));
      setCreateOpen(false);
      setSubject('');
      setBody('');
      void reload();
    } else {
      setError(res.message || tCommon('error'));
    }
  };

  const sendReply = async () => {
    if (!detailId || !reply.trim()) return;
    setReplying(true);
    const res = await edgeReplyTicket(detailId, { message: reply.trim(), body: reply.trim() });
    setReplying(false);
    if (res.ok) {
      setSuccess(tCommon('saved'));
      setReply('');
      const refreshed = await edgeGetTicket(detailId);
      setDetail(refreshed.item ?? detail);
    } else {
      setError(res.message || tCommon('error'));
    }
  };

  const messages = (detail?.messages ?? detail?.replies ?? detail?.thread) as unknown;
  const messageList = Array.isArray(messages) ? (messages as EdgeRow[]) : [];

  return (
    <CrmPageLayout
      title={tNav('nav.erp.admin.mpTickets')}
      {...layoutProps}
      actions={
        configured ? (
          <Button size="sm" onClick={() => setCreateOpen(true)}>
            <Plus className="me-2 h-4 w-4" />
            {t('newTicket')}
          </Button>
        ) : undefined
      }
    >
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpTickets')} />
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
                    <TableHead>{t('subject')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                    <TableHead>{t('date')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.map((row, i) => (
                    <TableRow
                      key={ticketId(row) || i}
                      className="cursor-pointer hover:bg-muted/50"
                      onClick={() => void openTicket(row)}
                    >
                      <TableCell>{edgeField(row, 'subject', 'title')}</TableCell>
                      <TableCell>
                        <ModirPayamakStatusBadge status={edgeField(row, 'status', 'state')} />
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {edgeField(row, 'updated_at', 'created_at')}
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
              <DialogTitle>{t('newTicket')}</DialogTitle>
            </DialogHeader>
            <div className="space-y-3">
              <div className="space-y-2">
                <Label>{t('subject')}</Label>
                <Input value={subject} onChange={(e) => setSubject(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label>{t('message')}</Label>
                <Textarea rows={4} value={body} onChange={(e) => setBody(e.target.value)} />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setCreateOpen(false)}>
                {tCommon('cancel')}
              </Button>
              <Button disabled={creating} onClick={() => void createTicket()}>
                {tCommon('save')}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
          <SheetContent className="flex flex-col sm:max-w-lg">
            <SheetHeader>
              <SheetTitle>{edgeField(detail, 'subject', 'title')}</SheetTitle>
            </SheetHeader>
            <div className="flex-1 space-y-3 overflow-y-auto py-4">
              {messageList.length > 0 ? (
                messageList.map((m, i) => (
                  <div key={i} className="rounded-lg border bg-muted/40 p-3 text-sm">
                    <p className="whitespace-pre-wrap">{edgeField(m, 'message', 'body', 'text')}</p>
                  </div>
                ))
              ) : (
                <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                  {edgeField(detail, 'body', 'message', 'description')}
                </p>
              )}
            </div>
            <div className="space-y-2 border-t pt-4">
              <Label>{t('reply')}</Label>
              <Textarea rows={3} value={reply} onChange={(e) => setReply(e.target.value)} />
              <Button disabled={replying} onClick={() => void sendReply()}>
                {t('sendReply')}
              </Button>
            </div>
          </SheetContent>
        </Sheet>
      </PermissionGate>
    </CrmPageLayout>
  );
}
