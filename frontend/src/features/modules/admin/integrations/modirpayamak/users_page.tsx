'use client';

import { useCallback, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Eye, Loader2, Pencil, Plus, UserCog } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmEmptyState } from '@/features/shared/pm';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
  edgeCreateUser,
  edgeField,
  edgeGetUser,
  edgeListUsers,
  edgeUpdateUser,
  type EdgeRow,
} from '@/lib/api/modirpayamak-edge';
import { ModirPayamakBreadcrumb, ModirPayamakNotConfigured } from './components/shared';
import { ModirPayamakStatusBadge } from './components/status-badge';
import { useModirPayamakConfigured } from './hooks/useModirPayamakConfigured';
import { useModirPayamakEdge } from './hooks/useModirPayamakEdge';

export function ModirpayamakUsersPage() {
  const t = useTranslations('modirpayamak.users');
  const tCommon = useTranslations('common');
  const tNav = useTranslations();
  const { layoutProps, setError, setSuccess } = useCrmFeedback();
  const { configured, loading: configLoading } = useModirPayamakConfigured();
  const loader = useCallback(() => edgeListUsers(), []);
  const { items, loading, reload } = useModirPayamakEdge(loader);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [form, setForm] = useState({ username: '', password: '', email: '', mobile: '' });
  const [saving, setSaving] = useState(false);
  const [detailOpen, setDetailOpen] = useState(false);
  const [detailRow, setDetailRow] = useState<EdgeRow | null>(null);

  const userId = (row: EdgeRow) => edgeField(row, 'id', 'user_id', 'username');

  const openCreate = () => {
    setEditId(null);
    setForm({ username: '', password: '', email: '', mobile: '' });
    setDialogOpen(true);
  };

  const openEdit = (row: EdgeRow) => {
    setEditId(userId(row));
    setForm({
      username: edgeField(row, 'username', 'name'),
      password: '',
      email: edgeField(row, 'email'),
      mobile: edgeField(row, 'mobile', 'phone'),
    });
    setDialogOpen(true);
  };

  const openDetail = async (row: EdgeRow) => {
    const id = userId(row);
    if (id === '—') {
      setDetailRow(row);
      setDetailOpen(true);
      return;
    }
    const res = await edgeGetUser(id);
    setDetailRow(res.item ?? row);
    setDetailOpen(true);
  };

  const save = async () => {
    setSaving(true);
    const body: Record<string, unknown> = {
      username: form.username.trim(),
      email: form.email.trim(),
      mobile: form.mobile.trim(),
    };
    if (form.password.trim()) body.password = form.password.trim();
    const res = editId ? await edgeUpdateUser(editId, body) : await edgeCreateUser(body);
    setSaving(false);
    if (res.ok) {
      setSuccess(tCommon('saved'));
      setDialogOpen(false);
      void reload();
    } else {
      setError(res.message || tCommon('error'));
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.admin.mpUsers')}
      {...layoutProps}
      actions={
        configured ? (
          <Button size="sm" onClick={openCreate}>
            <Plus className="me-2 h-4 w-4" />
            {t('addUser')}
          </Button>
        ) : undefined
      }
    >
      <ModirPayamakBreadcrumb current={tNav('nav.erp.admin.mpUsers')} />
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
                    <TableHead>{t('username')}</TableHead>
                    <TableHead>{t('email')}</TableHead>
                    <TableHead>{t('mobile')}</TableHead>
                    <TableHead>{t('status')}</TableHead>
                    <TableHead className="w-[100px]">{tCommon('actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.map((row, i) => (
                    <TableRow key={userId(row) !== '—' ? userId(row) : i}>
                      <TableCell>{edgeField(row, 'username', 'name')}</TableCell>
                      <TableCell dir="ltr">{edgeField(row, 'email')}</TableCell>
                      <TableCell dir="ltr">{edgeField(row, 'mobile', 'phone')}</TableCell>
                      <TableCell>
                        <ModirPayamakStatusBadge status={edgeField(row, 'status', 'state')} />
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button type="button" variant="ghost" size="icon" onClick={() => void openDetail(row)}>
                            <Eye className="h-4 w-4" />
                          </Button>
                          <Button type="button" variant="ghost" size="icon" onClick={() => openEdit(row)}>
                            <Pencil className="h-4 w-4" />
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

        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>{editId ? tCommon('edit') : t('addUser')}</DialogTitle>
            </DialogHeader>
            <div className="space-y-3">
              <div className="space-y-2">
                <Label>{t('username')}</Label>
                <Input value={form.username} onChange={(e) => setForm((f) => ({ ...f, username: e.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label>{t('password')}</Label>
                <Input type="password" value={form.password} onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label>{t('email')}</Label>
                <Input dir="ltr" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} />
              </div>
              <div className="space-y-2">
                <Label>{t('mobile')}</Label>
                <Input dir="ltr" value={form.mobile} onChange={(e) => setForm((f) => ({ ...f, mobile: e.target.value }))} />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setDialogOpen(false)}>
                {tCommon('cancel')}
              </Button>
              <Button disabled={saving} onClick={() => void save()}>
                {tCommon('save')}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
          <SheetContent className="sm:max-w-md">
            <SheetHeader>
              <SheetTitle>{edgeField(detailRow, 'username', 'name')}</SheetTitle>
            </SheetHeader>
            {detailRow ? (
              <dl className="mt-4 space-y-2 text-sm">
                <div>
                  <dt className="text-muted-foreground">{t('email')}</dt>
                  <dd dir="ltr">{edgeField(detailRow, 'email')}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">{t('mobile')}</dt>
                  <dd dir="ltr">{edgeField(detailRow, 'mobile', 'phone')}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">{t('status')}</dt>
                  <dd>
                    <ModirPayamakStatusBadge status={edgeField(detailRow, 'status')} />
                  </dd>
                </div>
              </dl>
            ) : null}
          </SheetContent>
        </Sheet>
      </PermissionGate>
    </CrmPageLayout>
  );
}
