'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { getAxiosMessage } from '@/lib/api-helpers';
import { getStaffAccount, provisionStaffAccount, resetStaffAccountPassword, unlinkStaffAccount, type StaffLoginAccount } from '@/lib/api/hrm';

type Employee = Record<string, unknown>;

export function StaffAccountDialog({
  open,
  employee,
  onClose,
  onChanged,
}: {
  open: boolean;
  employee: Employee | null;
  onClose: () => void;
  onChanged: () => void;
}) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const [account, setAccount] = useState<StaffLoginAccount | null>(null);
  const [temporaryPassword, setTemporaryPassword] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [unlinkOpen, setUnlinkOpen] = useState(false);

  useEffect(() => {
    if (!open || !employee?.id) return;
    setError(null);
    setTemporaryPassword(null);
    setLoading(true);
    void getStaffAccount(Number(employee.id))
      .then(setAccount)
      .catch((err) => setError(getAxiosMessage(err)))
      .finally(() => setLoading(false));
  }, [open, employee?.id]);

  async function provision() {
    if (!employee?.id) return;
    setBusy(true);
    setError(null);
    try {
      const result = await provisionStaffAccount(Number(employee.id));
      setAccount(result.account);
      setTemporaryPassword(result.temporary_password);
      onChanged();
    } catch (err) {
      setError(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function resetPassword() {
    if (!employee?.id) return;
    setBusy(true);
    setError(null);
    setTemporaryPassword(null);
    try {
      const result = await resetStaffAccountPassword(Number(employee.id));
      setAccount(result.account);
      setTemporaryPassword(result.temporary_password);
    } catch (err) {
      setError(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function unlink() {
    if (!employee?.id) return;
    setBusy(true);
    setError(null);
    try {
      const result = await unlinkStaffAccount(Number(employee.id));
      setAccount(result.account);
      setTemporaryPassword(null);
      setUnlinkOpen(false);
      onChanged();
    } catch (err) {
      setError(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }

  const employeeName = [employee?.first_name, employee?.last_name].filter(Boolean).join(' ');

  return (
    <>
      <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
        <DialogContent dir="rtl">
          <DialogHeader><DialogTitle>{t('account.title')}</DialogTitle></DialogHeader>
          <div className="space-y-3 py-2 text-sm">
            <p className="font-medium">{employeeName || tNav('common.emptyValue')}</p>
            {loading ? <p>{tNav('common.loading')}</p> : null}
            {error ? <p role="alert" className="text-destructive">{error}</p> : null}
            {!loading && account?.user ? (
              <div className="space-y-3 rounded-md border p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span>{account.user.name}</span>
                  <Badge variant={account.user.is_active ? 'default' : 'secondary'}>
                    {account.user.is_active ? t('account.active') : t('account.inactive')}
                  </Badge>
                </div>
                <p dir="ltr" className="text-start">{account.user.email}</p>
                <p className="text-muted-foreground">{t('account.roles')}: {account.user.roles.join('، ') || tNav('common.none')}</p>
                <div className="flex flex-wrap gap-2">
                  <Button type="button" variant="outline" disabled={busy} onClick={() => void resetPassword()}>
                    {t('account.resetPassword')}
                  </Button>
                  <Button type="button" variant="outline" className="text-destructive" disabled={busy} onClick={() => setUnlinkOpen(true)}>
                    {t('account.unlink')}
                  </Button>
                </div>
              </div>
            ) : null}
            {!loading && account && !account.user ? (
              <div className="space-y-3">
                <p className="text-muted-foreground">{t('account.noAccount')}</p>
                <p>{t('email')}: <span dir="ltr">{String(employee?.email ?? tNav('common.none'))}</span></p>
                <Button type="button" disabled={busy || !employee?.email} onClick={() => void provision()}>
                  {t('account.createOrLink')}
                </Button>
              </div>
            ) : null}
            {temporaryPassword ? (
              <div className="space-y-2 rounded-md border border-amber-500/40 bg-amber-50 p-3 dark:bg-amber-950/20">
                <p>{t('account.passwordOnce')}</p>
                <div className="flex flex-wrap items-center gap-2">
                  <code dir="ltr" className="rounded bg-background px-2 py-1">{temporaryPassword}</code>
                  <Button type="button" size="sm" variant="outline" onClick={() => void navigator.clipboard?.writeText(temporaryPassword)}>
                    {t('account.copyPassword')}
                  </Button>
                </div>
                <p className="text-xs text-muted-foreground">{t('account.deliverSecurely')}</p>
              </div>
            ) : null}
          </div>
          <DialogFooter><Button type="button" variant="outline" onClick={onClose}>{tNav('common.close')}</Button></DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={unlinkOpen} onOpenChange={setUnlinkOpen}>
        <AlertDialogContent dir="rtl">
          <AlertDialogHeader>
            <AlertDialogTitle>{t('account.unlinkTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('account.unlinkDescription')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tNav('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction disabled={busy} onClick={(event) => { event.preventDefault(); void unlink(); }}>
              {t('account.unlink')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
