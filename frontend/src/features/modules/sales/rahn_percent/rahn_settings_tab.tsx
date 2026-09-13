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
import { RAHN_API, type RahnBilling, type RahnCatalogItem, type RahnSettings } from './types';

type Props = {
  settings: RahnSettings;
  onSaved: (next: RahnSettings) => void;
};

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
    description: '',
    sort_order: sort,
  };
}

export function RahnSettingsTab({ settings, onSaved }: Props) {
  const t = useTranslations('sales.rahn');
  const [draft, setDraft] = useState<RahnSettings>(settings);
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
      const data = res.data as { settings: RahnSettings; message?: string };
      if (!data?.settings) {
        toast.error(t('saveError'));
        return;
      }
      onSaved(data.settings);
      setDraft(data.settings);
      toast.success(data.message || t('settingsSaved'));
    } catch {
      toast.error(t('saveError'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6">
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
              <div className="flex-1 grid gap-1">
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
            <div key={item.id} className="rounded-xl border p-4 grid gap-3 md:grid-cols-6">
              <div className="md:col-span-2 grid gap-2">
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
              <div className="md:col-span-6 flex flex-wrap items-center gap-4">
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={item.renewable}
                    onCheckedChange={(v) => updateCatalog(item.id, { renewable: !!v })}
                  />
                  {t('renewable')}
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={item.active}
                    onCheckedChange={(v) => updateCatalog(item.id, { active: !!v })}
                  />
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
                  className="text-destructive ms-auto"
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
