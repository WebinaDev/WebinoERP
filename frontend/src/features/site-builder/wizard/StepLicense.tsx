'use client';

import { useTranslations } from 'next-intl';
import { Check, Copy, KeyRound, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import type { SiteProvision } from '@/lib/api/site-builder';
import { StepHeroArt } from './illustrations';

type Props = {
  provision: SiteProvision | null;
  pending: boolean;
  onPrepare: () => void;
};

export function StepLicense({ provision, pending, onPrepare }: Props) {
  const t = useTranslations('siteBuilder');
  const [copied, setCopied] = useState(false);
  const key = provision?.license?.license_key;
  const modules = provision?.license?.meta?.modules ?? [];

  async function copyKey() {
    if (!key) return;
    try {
      await navigator.clipboard.writeText(key);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      /* ignore */
    }
  }

  return (
    <div className="grid gap-6" data-testid="wizard-step-license">
      <StepHeroArt step={6} />
      <div className="space-y-1 text-center">
        <h2 className="text-xl font-semibold">{t('stepLicenseTitle')}</h2>
        <p className="text-muted-foreground text-sm">{t('stepLicenseSubtitle')}</p>
      </div>

      {!key ? (
        <div className="border-border/70 space-y-4 rounded-2xl border border-dashed p-6 text-center">
          <KeyRound className="text-primary mx-auto h-10 w-10" />
          <p className="text-sm">{t('licensePending')}</p>
          <p className="text-muted-foreground text-xs">{t('licenseHint')}</p>
          <Button disabled={pending} onClick={onPrepare} data-testid="prepare-license">
            {t('prepareLicense')}
          </Button>
        </div>
      ) : (
        <div className="from-primary/10 via-card to-accent/10 space-y-4 rounded-3xl border bg-gradient-to-br p-6">
          <div className="text-primary flex items-center gap-2 text-sm font-medium">
            <ShieldCheck className="h-5 w-5" />
            {t('licenseReady')}
          </div>
          <div className="bg-background/80 flex flex-wrap items-center gap-2 rounded-xl border p-3">
            <code className="flex-1 break-all font-mono text-xs" dir="ltr" data-testid="license-key">
              {key}
            </code>
            <Button type="button" size="sm" variant="outline" onClick={() => void copyKey()}>
              {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
            </Button>
          </div>
          {Array.isArray(modules) && modules.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {modules.map((m) => (
                <span
                  key={m}
                  className="bg-primary/10 text-primary rounded-full px-2.5 py-1 text-[11px] font-medium"
                >
                  {m}
                </span>
              ))}
            </div>
          ) : null}
        </div>
      )}
    </div>
  );
}
