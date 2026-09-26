'use client';

import { useMemo } from 'react';
import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type { RahnCatalogItem, RahnServiceCategory } from './types';

export const RAHN_WIZARD_STEPS = [
  'business',
  'topic',
  'domain',
  'services',
  'deal',
  'summary',
] as const;

export type RahnWizardStep = (typeof RAHN_WIZARD_STEPS)[number];

type StepMeta = { id: RahnWizardStep; label: string };

export function RahnWizardProgress({
  steps,
  current,
  onSelect,
}: {
  steps: StepMeta[];
  current: RahnWizardStep;
  onSelect?: (id: RahnWizardStep) => void;
}) {
  const idx = steps.findIndex((s) => s.id === current);
  return (
    <ol className="flex flex-wrap gap-2" dir="rtl">
      {steps.map((step, i) => {
        const done = i < idx;
        const active = step.id === current;
        return (
          <li key={step.id}>
            <button
              type="button"
              disabled={!onSelect}
              onClick={() => onSelect?.(step.id)}
              className={cn(
                'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs transition-colors',
                active && 'border-primary bg-primary text-primary-foreground',
                done && !active && 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
                !active && !done && 'bg-muted/40 text-muted-foreground',
              )}
            >
              <span
                className={cn(
                  'flex size-5 items-center justify-center rounded-full text-[10px] font-semibold',
                  active ? 'bg-primary-foreground/20' : 'bg-background/80',
                )}
              >
                {done ? <Check className="size-3" /> : i + 1}
              </span>
              {step.label}
            </button>
          </li>
        );
      })}
    </ol>
  );
}

export function RahnChoiceCard({
  selected,
  title,
  description,
  badge,
  onClick,
  disabled,
}: {
  selected?: boolean;
  title: string;
  description?: string;
  badge?: string;
  onClick?: () => void;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={cn(
        'w-full rounded-2xl border p-4 text-start transition-all',
        selected
          ? 'border-primary bg-primary/10 shadow-sm ring-1 ring-primary/30'
          : 'hover:border-primary/40 hover:bg-muted/30',
        disabled && 'opacity-60 cursor-not-allowed',
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0 space-y-1">
          <div className="font-medium leading-6">{title}</div>
          {description ? <p className="text-sm text-muted-foreground leading-6">{description}</p> : null}
        </div>
        {badge ? <Badge variant="secondary">{badge}</Badge> : null}
      </div>
    </button>
  );
}

export function groupCatalogByCategory(
  items: RahnCatalogItem[],
  categories: RahnServiceCategory[],
): Array<{ category: RahnServiceCategory | { id: string; name: string }; items: RahnCatalogItem[] }> {
  const catMap = new Map(categories.map((c) => [c.id, c]));
  const buckets = new Map<string, RahnCatalogItem[]>();
  for (const item of items) {
    const key = item.category_id || item.category || 'other';
    const list = buckets.get(key) ?? [];
    list.push(item);
    buckets.set(key, list);
  }
  const ordered: Array<{ category: RahnServiceCategory | { id: string; name: string }; items: RahnCatalogItem[] }> = [];
  for (const cat of [...categories].sort((a, b) => a.sort_order - b.sort_order)) {
    const list = buckets.get(cat.id);
    if (list?.length) {
      ordered.push({ category: cat, items: list });
      buckets.delete(cat.id);
    }
  }
  for (const [key, list] of buckets) {
    ordered.push({
      category: catMap.get(key) ?? { id: key, name: list[0]?.category || key },
      items: list,
    });
  }
  return ordered;
}

/** Apply radio semantics for choice_group when toggling a catalog id. */
export function toggleCatalogSelection(
  catalog: RahnCatalogItem[],
  selected: string[],
  id: string,
  checked: boolean,
): string[] {
  const item = catalog.find((c) => c.id === id);
  if (!item) return selected;
  const group = item.choice_group?.trim();
  if (group) {
    const groupIds = new Set(catalog.filter((c) => c.choice_group === group).map((c) => c.id));
    const without = selected.filter((x) => !groupIds.has(x));
    return checked ? [...without, id] : without;
  }
  return checked ? (selected.includes(id) ? selected : [...selected, id]) : selected.filter((x) => x !== id);
}

export function defaultSelectedIds(catalog: RahnCatalogItem[]): string[] {
  const active = catalog.filter((c) => c.active !== false);
  const selected: string[] = [];
  const seenGroups = new Set<string>();
  for (const item of active) {
    if (!item.default_selected) continue;
    const group = item.choice_group?.trim();
    if (group) {
      if (seenGroups.has(group)) continue;
      seenGroups.add(group);
    }
    selected.push(item.id);
  }
  // Ensure each choice_group has one selection if any default existed, else first option.
  const groups = new Set(active.map((c) => c.choice_group).filter(Boolean) as string[]);
  for (const group of groups) {
    const inGroup = active.filter((c) => c.choice_group === group);
    if (!inGroup.some((c) => selected.includes(c.id))) {
      const pick = inGroup.find((c) => c.default_selected) ?? inGroup[0];
      if (pick) selected.push(pick.id);
    }
  }
  return selected;
}

export function RahnCategorizedSummary({
  items,
  categories,
  formatMoney,
  emptyLabel,
}: {
  items: RahnCatalogItem[];
  categories: RahnServiceCategory[];
  formatMoney: (n: number) => string;
  emptyLabel: string;
}) {
  const groups = useMemo(() => groupCatalogByCategory(items, categories), [items, categories]);
  if (!items.length) {
    return <p className="text-sm text-muted-foreground">{emptyLabel}</p>;
  }
  return (
    <div className="space-y-4" dir="rtl">
      {groups.map(({ category, items: rows }) => (
        <Card key={category.id} className="overflow-hidden">
          <div className="border-b bg-muted/40 px-4 py-2 text-sm font-medium">{category.name}</div>
          <CardContent className="divide-y p-0">
            {rows.map((row) => (
              <div key={row.id} className="flex flex-wrap items-start justify-between gap-3 px-4 py-3 text-sm">
                <div className="min-w-0 space-y-1">
                  <div className="font-medium">{row.name}</div>
                  {row.description ? <p className="text-muted-foreground leading-6">{row.description}</p> : null}
                  {row.fee_label ? <Badge variant="outline">{row.fee_label}</Badge> : null}
                </div>
                <div className="tabular-nums text-muted-foreground" dir="ltr">
                  {formatMoney(row.amount)}
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
