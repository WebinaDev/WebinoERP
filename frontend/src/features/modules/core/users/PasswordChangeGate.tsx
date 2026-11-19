'use client';

import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { ShieldAlert } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { getCurrentUser } from '@/lib/auth';
import { ChangePasswordForm } from './MyAccountPage';

/** Blocks the dashboard with a password form while the account still uses a temporary password. */
export function PasswordChangeGate() {
  const t = useTranslations('userAdmin');
  const [open, setOpen] = useState(false);

  useEffect(() => {
    void getCurrentUser().then((u) => {
      if (u && (u as { must_change_password?: boolean }).must_change_password) setOpen(true);
    });
  }, []);

  return (
    <Dialog open={open}>
      <DialogContent dir="rtl" className="sm:max-w-md [&>button]:hidden" onEscapeKeyDown={(e) => e.preventDefault()} onPointerDownOutside={(e) => e.preventDefault()}>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2"><ShieldAlert className="size-5 text-amber-600" />{t('me.forceTitle')}</DialogTitle>
          <DialogDescription>{t('me.forceBody')}</DialogDescription>
        </DialogHeader>
        <ChangePasswordForm onDone={() => setOpen(false)} />
      </DialogContent>
    </Dialog>
  );
}
