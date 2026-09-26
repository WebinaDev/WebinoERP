'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { toast } from 'sonner';
import { Plus, Trash2, Save } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
  RAHN_API,
  RAHN_DURATION_OPTIONS,
  type RahnBilling,
  type RahnCatalogItem,
  type RahnDomain,
  type RahnServiceCategory,
  type RahnSettings,
  type RahnTopic,
} from './types';

type Props = {
  settings: RahnSettings;
  onSaved: (next: RahnSettings) => void;
};

function normalizeSettings(s: RahnSettings): RahnSettings {
  return {
    ...s,
    topics: s.topics ?? [],
    domains: s.domains ?? [],
    categories: s.categories ?? [],
    duration_options: s.duration_options?.length ? s.duration_options : [...RAHN_DURATION_OPTIONS],
    catalog: (s.catalog ?? []).map((c) => ({
      ...c,
      category_id: c.category_id ?? '',
      choice_group: c.choice_group ?? '',
      choice_value: c.choice_value ?? '',
      fee_label: c.fee_label ?? '',
    })),
  };
}

function newItem(sort: number): RahnCatalogItem {
  return {
    id: `svc_${Date.now()}_${sort}`,
    name: '',
    billing: 'once',
    period_months: 1,
    renewable: false,
    amount: 0,
    active: true,
    default_selected: false,
    category: '',
    category_id: '',
    description: '',
    choice_group: '',
    choice_value: '',
    fee_label: '',
    sort_order: sort,
  };
}

export function RahnSettingsTab({ settings, onSaved }: Props) {
  const t = useTranslations('sales.rahn');
  const [draft, setDraft] = useState<RahnSettings>(() => normalizeSettings(settings));
  const [saving, setSaving] = useState(false);

  const updateCatalog = (id: string, patch: Partial<RahnCatalogItem>) => {
    setDraft((prev) => ({
      ...prev,
      catalog: prev.catalog.map((row) => (row.id === id ? { ...row, ...patch } : row)),
    }));
  };

  const removeItem = (id: string) => {
    setDraft((prev) => ({
      ...prev,
      catalog: prev.catalog.filter((row) => row.id !== id),
    }));
  };

  const addItem = () => {
    setDraft((prev) => ({
      ...prev,
      catalog: [...prev.catalog, newItem(prev.catalog.length + 1)],
    }));
  };

  const save = async () => {
    setSaving(true);
    try {
      const res = await apiClient.put(`${RAHN_API}/settings`, draft);
      const body = res.data as { settings?: RahnSettings; data?: { settings?: RahnSettings; message?: string }; message?: string };
      const next = body?.settings ?? body?.data?.settings;
      if (!next) {
        toast.error(t('saveError'));
        return;
      }
      const normalized = normalizeSettings(next);
      onSaved(normalized);
      setDraft(normalized);
      toast.success(body.data?.message || body.message || t('settingsSaved'));
    } catch {
      toast.error(t('saveError'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6 text-start" dir="rtl">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('formulaSettings')}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {(
            [
              ['T', 'duration'],
              ['m', 'margin'],
              ['k', 'floorRatio'],
              ['p_min', 'pMin'],
              ['p_max', 'pMax'],
              ['p_default', 'pDefault'],
              ['s_hat_default', 'sHatDefault'],
            ] as const
          ).map(([key, labelKey]) => (
            <div key={key} className="grid gap-2">
              <Label>{t(labelKey)}</Label>
              <Input
                type="number"
                step="any"
                value={draft[key]}
                dir="ltr"
                onChange={(e) =>
                  setDraft((prev) => ({
                    ...prev,
                    [key]: Number(e.target.value) || 0,
                  }))
                }
              />
            </div>
          ))}
          <div className="grid gap-2 sm:col-span-2 lg:col-span-3">
            <Label>{t('clauseTemplate')}</Label>
            <Textarea
              rows={3}
              value={draft.clause_template}
              onChange={(e) => setDraft((prev) => ({ ...prev, clause_template: e.target.value }))}
            />
            <p className="text-xs text-muted-foreground">{t('clauseHints')}</p>
          </div>
        </CardContent>
      </Card>

      <TaxonomyEditor
        title={t('wizard.topics')}
        addLabel={t('wizard.addTopic')}
        rows={draft.topics}
        onChange={(topics) => setDraft((prev) => ({ ...prev, topics }))}
      />

      <DomainEditor
        title={t('wizard.domains')}
        addLabel={t('wizard.addDomain')}
        rows={draft.domains}
        topics={draft.topics}
        pSuggestLabel={t('wizard.pSuggest')}
        onChange={(domains) => setDraft((prev) => ({ ...prev, domains }))}
      />

      <TaxonomyEditor
        title={t('wizard.categories')}
        addLabel={t('wizard.addCategory')}
        rows={draft.categories}
        onChange={(categories) => setDraft((prev) => ({ ...prev, categories }))}
      />

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('salesDefinition')}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3 sm:grid-cols-2">
          {(['G', 'R', 'D', 'X'] as const).map((key) => (
            <div key={key} className="flex items-center gap-3 rounded-lg border p-3">
              <Switch
                checked={draft.sales_definition[key].enabled}
                onCheckedChange={(v) =>
                  setDraft((prev) => ({
                    ...prev,
                    sales_definition: {
                      ...prev.sales_definition,
                      [key]: { ...prev.sales_definition[key], enabled: !!v },
                    },
                  }))
                }
              />
              <div className="grid flex-1 gap-1">
                <Label className="text-xs text-muted-foreground">{key}</Label>
                <Input
                  value={draft.sales_definition[key].label}
                  onChange={(e) =>
                    setDraft((prev) => ({
                      ...prev,
                      sales_definition: {
                        ...prev.sales_definition,
                        [key]: { ...prev.sales_definition[key], label: e.target.value },
                      },
                    }))
                  }
                />
              </div>
            </div>
          ))}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('reviewSettings')}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-3">
          <div className="flex items-center gap-3">
            <Switch
              checked={draft.review.enabled}
              onCheckedChange={(v) =>
                setDraft((prev) => ({
                  ...prev,
                  review: { ...prev.review, enabled: !!v },
                }))
              }
            />
            <Label>{t('reviewEnabled')}</Label>
          </div>
          <div className="grid gap-2">
            <Label>{t('deviationPercent')}</Label>
            <Input
              type="number"
              value={draft.review.deviation_percent}
              dir="ltr"
              onChange={(e) =>
                setDraft((prev) => ({
                  ...prev,
                  review: { ...prev.review, deviation_percent: Number(e.target.value) || 0 },
                }))
              }
            />
          </div>
          <div className="grid gap-2">
            <Label>{t('consecutiveMonths')}</Label>
            <Input
              type="number"
              value={draft.review.consecutive_months}
              dir="ltr"
              onChange={(e) =>
                setDraft((prev) => ({
                  ...prev,
                  review: { ...prev.review, consecutive_months: Number(e.target.value) || 1 },
                }))
              }
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-base">{t('catalog')}</CardTitle>
          <Button type="button" size="sm" variant="secondary" onClick={addItem}>
            <Plus className="h-4 w-4" />
            {t('addService')}
          </Button>
        </CardHeader>
        <CardContent className="space-y-4">
          {draft.catalog.map((item) => (
            <div key={item.id} className="grid gap-3 rounded-xl border p-4 md:grid-cols-6">
              <div className="grid gap-2 md:col-span-2">
                <Label>{t('serviceName')}</Label>
                <Input value={item.name} onChange={(e) => updateCatalog(item.id, { name: e.target.value })} />
              </div>
              <div className="grid gap-2">
                <Label>{t('billingLabel')}</Label>
                <Select
                  value={item.billing}
                  onValueChange={(v) => updateCatalog(item.id, { billing: v as RahnBilling })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="once">{t('billing.once')}</SelectItem>
                    <SelectItem value="monthly">{t('billing.monthly')}</SelectItem>
                    <SelectItem value="yearly">{t('billing.yearly')}</SelectItem>
                    <SelectItem value="custom">{t('billing.custom')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>{t('periodMonths')}</Label>
                <Input
                  type="number"
                  min={1}
                  dir="ltr"
                  value={item.period_months}
                  onChange={(e) =>
                    updateCatalog(item.id, { period_months: Math.max(1, Number(e.target.value) || 1) })
                  }
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('amount')}</Label>
                <Input
                  type="number"
                  dir="ltr"
                  value={item.amount}
                  onChange={(e) => updateCatalog(item.id, { amount: Number(e.target.value) || 0 })}
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('category')}</Label>
                <Input
                  value={item.category}
                  onChange={(e) => updateCatalog(item.id, { category: e.target.value })}
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.categoryId')}</Label>
                <Select
                  value={item.category_id || '__none'}
                  onValueChange={(v) =>
                    updateCatalog(item.id, {
                      category_id: v === '__none' ? '' : v,
                      category:
                        v === '__none'
                          ? item.category
                          : draft.categories.find((c) => c.id === v)?.name || item.category,
                    })
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none">—</SelectItem>
                    {draft.categories.map((c) => (
                      <SelectItem key={c.id} value={c.id}>
                        {c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.choiceGroup')}</Label>
                <Input
                  value={item.choice_group || ''}
                  dir="ltr"
                  onChange={(e) => updateCatalog(item.id, { choice_group: e.target.value })}
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.feeLabel')}</Label>
                <Input
                  value={item.fee_label || ''}
                  onChange={(e) => updateCatalog(item.id, { fee_label: e.target.value })}
                />
              </div>
              <div className="grid gap-2 md:col-span-2">
                <Label>توضیح مشتری</Label>
                <Input
                  value={item.description}
                  onChange={(e) => updateCatalog(item.id, { description: e.target.value })}
                />
              </div>
              <div className="flex flex-wrap items-center gap-4 md:col-span-6">
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={item.renewable}
                    onCheckedChange={(v) => updateCatalog(item.id, { renewable: !!v })}
                  />
                  {t('renewable')}
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox checked={item.active} onCheckedChange={(v) => updateCatalog(item.id, { active: !!v })} />
                  {t('active')}
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={item.default_selected}
                    onCheckedChange={(v) => updateCatalog(item.id, { default_selected: !!v })}
                  />
                  {t('defaultSelected')}
                </label>
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  className="ms-auto text-destructive"
                  onClick={() => removeItem(item.id)}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            </div>
          ))}
        </CardContent>
      </Card>

      <div className="flex justify-end">
        <Button type="button" onClick={() => void save()} disabled={saving}>
          <Save className="h-4 w-4" />
          {t('saveSettings')}
        </Button>
      </div>
    </div>
  );
}

function TaxonomyEditor({
  title,
  addLabel,
  rows,
  onChange,
}: {
  title: string;
  addLabel: string;
  rows: Array<RahnTopic | RahnServiceCategory>;
  onChange: (rows: Array<RahnTopic | RahnServiceCategory>) => void;
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="text-base">{title}</CardTitle>
        <Button
          type="button"
          size="sm"
          variant="secondary"
          onClick={() =>
            onChange([
              ...rows,
              {
                id: `item_${Date.now()}`,
                name: '',
                description: '',
                sort_order: rows.length + 1,
                active: true,
              },
            ])
          }
        >
          <Plus className="h-4 w-4" />
          {addLabel}
        </Button>
      </CardHeader>
      <CardContent className="space-y-3">
        {rows.map((row, idx) => (
          <div key={row.id} className="grid gap-2 rounded-lg border p-3 sm:grid-cols-4">
            <Input
              value={row.name}
              placeholder="نام"
              onChange={(e) => {
                const next = [...rows];
                next[idx] = { ...row, name: e.target.value };
                onChange(next);
              }}
            />
            <Input
              className="sm:col-span-2"
              value={row.description || ''}
              placeholder="توضیح"
              onChange={(e) => {
                const next = [...rows];
                next[idx] = { ...row, description: e.target.value };
                onChange(next);
              }}
            />
            <div className="flex items-center gap-2">
              <Input
                type="number"
                dir="ltr"
                value={row.sort_order}
                onChange={(e) => {
                  const next = [...rows];
                  next[idx] = { ...row, sort_order: Number(e.target.value) || 0 };
                  onChange(next);
                }}
              />
              <Button type="button" size="sm" variant="ghost" className="text-destructive" onClick={() => onChange(rows.filter((r) => r.id !== row.id))}>
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}

function DomainEditor({
  title,
  addLabel,
  rows,
  topics,
  pSuggestLabel,
  onChange,
}: {
  title: string;
  addLabel: string;
  rows: RahnDomain[];
  topics: RahnTopic[];
  pSuggestLabel: string;
  onChange: (rows: RahnDomain[]) => void;
}) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="text-base">{title}</CardTitle>
        <Button
          type="button"
          size="sm"
          variant="secondary"
          onClick={() =>
            onChange([
              ...rows,
              {
                id: `dom_${Date.now()}`,
                topic_id: topics[0]?.id || '',
                name: '',
                description: '',
                p_suggest: 0.1,
                sort_order: rows.length + 1,
                active: true,
              },
            ])
          }
        >
          <Plus className="h-4 w-4" />
          {addLabel}
        </Button>
      </CardHeader>
      <CardContent className="space-y-3">
        {rows.map((row, idx) => (
          <div key={row.id} className="grid gap-2 rounded-lg border p-3 md:grid-cols-6">
            <Input
              className="md:col-span-2"
              value={row.name}
              onChange={(e) => {
                const next = [...rows];
                next[idx] = { ...row, name: e.target.value };
                onChange(next);
              }}
            />
            <Select
              value={row.topic_id || '__none'}
              onValueChange={(v) => {
                const next = [...rows];
                next[idx] = { ...row, topic_id: v === '__none' ? '' : v };
                onChange(next);
              }}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none">—</SelectItem>
                {topics.map((t) => (
                  <SelectItem key={t.id} value={t.id}>
                    {t.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <div className="grid gap-1">
              <Label className="text-xs">{pSuggestLabel}</Label>
              <Input
                type="number"
                step="0.01"
                dir="ltr"
                value={row.p_suggest}
                onChange={(e) => {
                  const next = [...rows];
                  next[idx] = { ...row, p_suggest: Number(e.target.value) || 0 };
                  onChange(next);
                }}
              />
            </div>
            <Input
              className="md:col-span-2"
              value={row.description || ''}
              onChange={(e) => {
                const next = [...rows];
                next[idx] = { ...row, description: e.target.value };
                onChange(next);
              }}
            />
            <Button
              type="button"
              size="sm"
              variant="ghost"
              className="text-destructive"
              onClick={() => onChange(rows.filter((r) => r.id !== row.id))}
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
