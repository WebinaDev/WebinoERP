'use client';

import { useTranslations } from 'next-intl';
import { LayoutTemplate } from 'lucide-react';
import type { BusinessCategory } from '@/lib/api/site-builder';
import { SiteTypeIllustration, StepHeroArt } from './illustrations';
import { SelectableCard } from './WizardShell';

type Props = {
  categories: BusinessCategory[];
  categoryId: number | null;
  onSelect: (id: number) => void;
};

export function StepCategory({ categories, categoryId, onSelect }: Props) {
  const t = useTranslations('siteBuilder');
  return (
    <div className="grid gap-6" data-testid="wizard-step-category">
      <StepHeroArt step={1} />
      <div className="space-y-1 text-center">
        <h2 className="text-xl font-semibold">{t('stepCategoryTitle')}</h2>
        <p className="text-muted-foreground text-sm">{t('stepCategorySubtitle')}</p>
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        {categories.map((c) => (
          <SelectableCard
            key={c.id}
            selected={categoryId === c.id}
            onClick={() => onSelect(c.id)}
            testId={`category-card-${c.slug}`}
          >
            <SiteTypeIllustration slug={c.slug} className="mb-3 h-20" />
            <div className="flex items-center gap-2">
              <LayoutTemplate className="text-primary h-4 w-4" />
              <p className="font-semibold">{c.name_fa}</p>
            </div>
            <p className="text-muted-foreground mt-1 text-xs">{c.name_en}</p>
          </SelectableCard>
        ))}
      </div>
    </div>
  );
}
