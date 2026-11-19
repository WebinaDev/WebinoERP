'use client';

import { useTranslations } from 'next-intl';
import { Copy, KeyRound } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';

export function TemporaryPasswordNotice({ password }: { password: string }) {
  const t = useTranslations('userAdmin');
  return (
    <div className="space-y-2 rounded-lg border border-amber-500/40 bg-amber-50 p-3 text-sm dark:bg-amber-950/20">
      <p className="flex items-center gap-2 font-medium"><KeyRound className="size-4" />{t('password.once')}</p>
      <div className="flex flex-wrap items-center gap-2">
        <code dir="ltr" className="rounded bg-background px-2 py-1 text-base">{password}</code>
        <Button type="button" size="sm" variant="outline" onClick={() => { void navigator.clipboard?.writeText(password); toast.success(t('password.copied')); }}>
          <Copy className="size-3.5" />{t('password.copy')}
        </Button>
      </div>
    </div>
  );
}
