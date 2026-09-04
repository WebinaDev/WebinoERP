'use client';

import { useTranslations } from 'next-intl';
import { Check, Sparkles } from 'lucide-react';
import { Switch } from '@/components/ui/switch';
import type { BusinessType, PackageRow } from '@/lib/api/site-builder';
import { StepHeroArt } from './illustrations';
import { SelectableCard } from './WizardShell';

type Props = {
  packages: PackageRow[];
  packageId: number | null;
  onSelectPackage: (pkg: PackageRow) => void;
  selectedType: BusinessType | null;
  selectedFeatures: string[];
  onToggleFeature: (slug: string, on: boolean) => void;
};

export function StepPackage({
  packages,
  packageId,
  onSelectPackage,
  selectedType,
  selectedFeatures,
  onToggleFeature,
}: Props) {
  const t = useTranslations('siteBuilder');
  const addons = (selectedType?.features ?? []).filter((f) => f.is_addon);

  return (
    <div className="grid gap-6" data-testid="wizard-step-package">
      <StepHeroArt step={3} />
      <div className="space-y-1 text-center">
        <h2 className="text-xl font-semibold">{t('stepPackageTitle')}</h2>
        <p className="text-muted-foreground text-sm">{t('stepPackageSubtitle')}</p>
      </div>

      {packages.length === 0 ? (
        <p className="text-muted-foreground text-sm">{t('noPackages')}</p>
      ) : (
        <div className="grid gap-3">
          {packages.map((p) => {
            const selected = packageId === p.id;
            const features = p.features ?? selectedType?.features?.filter((f) => !f.is_addon) ?? [];
            return (
              <SelectableCard
                key={p.id}
                selected={selected}
                onClick={() => onSelectPackage(p)}
                testId={`package-card-${p.sku}`}
                className="w-full"
              >
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="text-lg font-semibold">{p.name_fa}</p>
                    <p className="text-muted-foreground font-mono text-xs">{p.sku}</p>
                  </div>
                  <div className="from-primary/15 to-accent/20 rounded-xl bg-gradient-to-br px-3 py-2 text-sm font-semibold">
                    {Number(p.price || 0).toLocaleString('fa-IR')}
                  </div>
                </div>
                {features.length ? (
                  <ul className="mt-3 grid gap-1.5 sm:grid-cols-2">
                    {features.map((f) => (
                      <li key={f.id} className="text-muted-foreground flex items-center gap-2 text-xs">
                        <Check className="text-primary h-3.5 w-3.5 shrink-0" />
                        {f.name_fa}
                      </li>
                    ))}
                  </ul>
                ) : null}
              </SelectableCard>
            );
          })}
        </div>
      )}

      {addons.length ? (
        <div className="space-y-3">
          <p className="flex items-center gap-2 text-sm font-medium">
            <Sparkles className="text-primary h-4 w-4" />
            {t('addons')}
          </p>
          {addons.map((f) => (
            <div
              key={f.id}
              className="border-border/70 bg-card/60 flex items-center justify-between rounded-xl border p-3"
            >
              <span className="text-sm">{f.name_fa}</span>
              <Switch
                checked={selectedFeatures.includes(f.slug)}
                onCheckedChange={(on) => onToggleFeature(f.slug, on)}
              />
            </div>
          ))}
        </div>
      ) : null}
    </div>
  );
}
