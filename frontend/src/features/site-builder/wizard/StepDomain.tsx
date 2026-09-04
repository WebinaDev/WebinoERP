'use client';

import { useTranslations } from 'next-intl';
import { Globe2, Link2 } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { StepHeroArt } from './illustrations';
import { SelectableCard } from './WizardShell';

type Props = {
  usesCustomDomain: boolean;
  customDomain: string;
  slug: string;
  baseDomain: string;
  finalDomain: string;
  onToggleCustom: (on: boolean) => void;
  onCustomDomain: (v: string) => void;
};

export function StepDomain({
  usesCustomDomain,
  customDomain,
  slug,
  baseDomain,
  finalDomain,
  onToggleCustom,
  onCustomDomain,
}: Props) {
  const t = useTranslations('siteBuilder');

  return (
    <div className="grid gap-6" data-testid="wizard-step-domain">
      <StepHeroArt step={5} />
      <div className="space-y-1 text-center">
        <h2 className="text-xl font-semibold">{t('stepDomainTitle')}</h2>
        <p className="text-muted-foreground text-sm">{t('stepDomainSubtitle')}</p>
      </div>

      <div className="grid gap-3 md:grid-cols-2">
        <SelectableCard
          selected={!usesCustomDomain}
          onClick={() => onToggleCustom(false)}
          testId="domain-mode-subdomain"
        >
          <div className="bg-primary/10 text-primary mb-3 flex h-11 w-11 items-center justify-center rounded-xl">
            <Link2 className="h-5 w-5" />
          </div>
          <p className="font-semibold">{t('agencySubdomain')}</p>
          <p className="text-muted-foreground mt-2 text-xs">
            {t('subdomainHint', { slug: slug || 'my-shop', domain: baseDomain })}
          </p>
        </SelectableCard>
        <SelectableCard
          selected={usesCustomDomain}
          onClick={() => onToggleCustom(true)}
          testId="domain-mode-custom"
        >
          <div className="bg-primary/10 text-primary mb-3 flex h-11 w-11 items-center justify-center rounded-xl">
            <Globe2 className="h-5 w-5" />
          </div>
          <p className="font-semibold">{t('customDomain')}</p>
          <p className="text-muted-foreground mt-2 text-xs">{t('dnsCustomHint')}</p>
        </SelectableCard>
      </div>

      {usesCustomDomain ? (
        <Input
          value={customDomain}
          onChange={(e) => onCustomDomain(e.target.value)}
          dir="ltr"
          className="font-mono"
          placeholder="shop.example.com"
          data-testid="custom-domain-input"
        />
      ) : null}

      <div className="from-primary/10 to-accent/10 rounded-2xl border bg-gradient-to-br p-5 text-center">
        <p className="text-muted-foreground text-xs">{t('finalDomain')}</p>
        <p className="mt-2 break-all font-mono text-lg font-semibold" dir="ltr" data-testid="final-domain">
          https://{finalDomain}
        </p>
      </div>
    </div>
  );
}
