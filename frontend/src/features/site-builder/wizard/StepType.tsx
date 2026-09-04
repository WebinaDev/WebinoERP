'use client';

import { useTranslations } from 'next-intl';
import type { BusinessType } from '@/lib/api/site-builder';
import { SiteTypeIllustration, StepHeroArt } from './illustrations';
import { SelectableCard } from './WizardShell';

type Props = {
  types: BusinessType[];
  typeId: number | null;
  onSelect: (type: BusinessType) => void;
};

export function StepType({ types, typeId, onSelect }: Props) {
  const t = useTranslations('siteBuilder');
  return (
    <div className="grid gap-6" data-testid="wizard-step-type">
      <StepHeroArt step={2} />
      <div className="space-y-1 text-center">
        <h2 className="text-xl font-semibold">{t('stepTypeTitle')}</h2>
        <p className="text-muted-foreground text-sm">{t('stepTypeSubtitle')}</p>
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        {types.map((type) => (
          <SelectableCard
            key={type.id}
            selected={typeId === type.id}
            onClick={() => onSelect(type)}
            testId={`type-card-${type.slug}`}
          >
            <SiteTypeIllustration slug={type.slug} className="mb-3 h-24" />
            <p className="font-semibold">{type.name_fa}</p>
            <p className="text-muted-foreground mt-1 text-xs">{type.name_en}</p>
            {type.theme_preset ? (
              <p className="text-muted-foreground mt-2 font-mono text-[11px]">{type.theme_preset}</p>
            ) : null}
          </SelectableCard>
        ))}
        {types.length === 0 ? (
          <p className="text-muted-foreground col-span-full text-sm">{t('noTypes')}</p>
        ) : null}
      </div>
    </div>
  );
}
