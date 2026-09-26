'use client';

import { useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { toast } from 'sonner';
import { ArrowDown, ArrowUp, Pencil, Plus, Save, Trash2 } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import {
  DEFAULT_WIZARD_STEPS,
  RAHN_API,
  RAHN_DURATION_OPTIONS,
  fractionToPercentInput,
  percentInputToFraction,
  type RahnBilling,
  type RahnCatalogItem,
  type RahnDomain,
  type RahnServiceCategory,
  type RahnSettings,
  type RahnTopic,
  type RahnWizardStepConfig,
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
    categories: (s.categories ?? []).map((c) => ({
      ...c,
      topic_id: c.topic_id ?? '',
      domain_id: c.domain_id ?? '',
    })),
    duration_options: s.duration_options?.length ? s.duration_options : [...RAHN_DURATION_OPTIONS],
    wizard_steps: s.wizard_steps?.length ? s.wizard_steps : [...DEFAULT_WIZARD_STEPS],
    catalog: (s.catalog ?? []).map((c) => ({
      ...c,
      category_id: c.category_id ?? '',
      choice_group: c.choice_group ?? '',
      choice_value: c.choice_value ?? '',
      fee_label: c.fee_label ?? '',
      show_when_item_id: c.show_when_item_id ?? '',
    })),
  };
}

function newCatalogItem(sort: number): RahnCatalogItem {
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
    show_when_item_id: '',
    sort_order: sort,
  };
}

export function RahnSettingsTab({ settings, onSaved }: Props) {
  const t = useTranslations('sales.rahn');
  const [draft, setDraft] = useState<RahnSettings>(() => normalizeSettings(settings));
  const [saving, setSaving] = useState(false);

  const [topicDialog, setTopicDialog] = useState<RahnTopic | null>(null);
  const [domainDialog, setDomainDialog] = useState<RahnDomain | null>(null);
  const [categoryDialog, setCategoryDialog] = useState<RahnServiceCategory | null>(null);
  const [catalogDialog, setCatalogDialog] = useState<RahnCatalogItem | null>(null);
  const [stepDialog, setStepDialog] = useState<RahnWizardStepConfig | null>(null);
  const [newDuration, setNewDuration] = useState('');

  const topicName = (id: string) => draft.topics.find((x) => x.id === id)?.name || '—';
  const domainName = (id: string) => draft.domains.find((x) => x.id === id)?.name || '—';
  const categoryName = (id: string) => draft.categories.find((x) => x.id === id)?.name || '—';
  const catalogName = (id: string) => draft.catalog.find((x) => x.id === id)?.name || '—';

  const save = async () => {
    setSaving(true);
    try {
      const res = await apiClient.put(`${RAHN_API}/settings`, draft);
      const body = res.data as {
        settings?: RahnSettings;
        data?: { settings?: RahnSettings; message?: string };
        message?: string;
      };
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

  const setPercentField = (key: 'p_min' | 'p_max' | 'p_default' | 'm' | 'k', display: number) => {
    setDraft((prev) => ({ ...prev, [key]: percentInputToFraction(display) }));
  };

  const sortedSteps = useMemo(
    () => [...draft.wizard_steps].sort((a, b) => a.sort_order - b.sort_order),
    [draft.wizard_steps],
  );

  const moveStep = (id: string, dir: -1 | 1) => {
    const ordered = [...sortedSteps];
    const idx = ordered.findIndex((s) => s.id === id);
    const swap = idx + dir;
    if (idx < 0 || swap < 0 || swap >= ordered.length) return;
    const a = ordered[idx]!;
    const b = ordered[swap]!;
    const aOrder = a.sort_order;
    ordered[idx] = { ...a, sort_order: b.sort_order };
    ordered[swap] = { ...b, sort_order: aOrder };
    setDraft((prev) => ({ ...prev, wizard_steps: ordered }));
  };

  return (
    <div className="space-y-4 text-right" dir="rtl">
      <Tabs defaultValue="formula">
        <TabsList className="flex h-auto flex-wrap gap-1" dir="rtl">
          <TabsTrigger value="formula">{t('formulaSettings')}</TabsTrigger>
          <TabsTrigger value="topics">{t('wizard.topics')}</TabsTrigger>
          <TabsTrigger value="domains">{t('wizard.domains')}</TabsTrigger>
          <TabsTrigger value="categories">{t('wizard.categories')}</TabsTrigger>
          <TabsTrigger value="catalog">{t('catalog')}</TabsTrigger>
          <TabsTrigger value="wizard">{t('settingsWizardSteps')}</TabsTrigger>
        </TabsList>

        <TabsContent value="formula" dir="rtl" className="mt-4 text-right space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('formulaSettings')}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div className="grid gap-2">
                <Label>{t('duration')}</Label>
                <Input
                  type="number"
                  dir="ltr"
                  value={draft.T}
                  onChange={(e) => setDraft((p) => ({ ...p, T: Number(e.target.value) || 0 }))}
                />
              </div>
              {(
                [
                  ['m', 'margin'],
                  ['k', 'floorRatio'],
                  ['p_min', 'pMin'],
                  ['p_max', 'pMax'],
                  ['p_default', 'pDefault'],
                ] as const
              ).map(([key, labelKey]) => (
                <div key={key} className="grid gap-2">
                  <Label>{t(labelKey)}</Label>
                  <Input
                    type="number"
                    step="any"
                    dir="ltr"
                    value={fractionToPercentInput(draft[key])}
                    onChange={(e) => setPercentField(key, Number(e.target.value) || 0)}
                  />
                  <p className="text-xs text-muted-foreground">{t('percentHint')}</p>
                </div>
              ))}
              <div className="grid gap-2">
                <Label>{t('sHatDefault')}</Label>
                <Input
                  type="number"
                  dir="ltr"
                  value={draft.s_hat_default}
                  onChange={(e) =>
                    setDraft((p) => ({ ...p, s_hat_default: Number(e.target.value) || 0 }))
                  }
                />
              </div>
              <div className="grid gap-2 sm:col-span-2 lg:col-span-3">
                <Label>{t('clauseTemplate')}</Label>
                <Textarea
                  rows={3}
                  value={draft.clause_template}
                  onChange={(e) => setDraft((p) => ({ ...p, clause_template: e.target.value }))}
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
                  dir="ltr"
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
                  dir="ltr"
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
        </TabsContent>

        <TabsContent value="topics" dir="rtl" className="mt-4 text-right">
          <EntityList
            title={t('wizard.topics')}
            addLabel={t('wizard.addTopic')}
            onAdd={() =>
              setTopicDialog({
                id: `topic_${Date.now()}`,
                name: '',
                description: '',
                sort_order: draft.topics.length + 1,
                active: true,
              })
            }
            rows={draft.topics}
            columns={[
              { key: 'name', label: t('serviceName'), render: (r) => r.name || '—' },
              { key: 'description', label: t('wizard.description'), render: (r) => r.description || '—' },
              { key: 'sort', label: t('wizard.sortOrder'), render: (r) => String(r.sort_order) },
            ]}
            onEdit={(row) => setTopicDialog({ ...row })}
            onDelete={(id) => setDraft((p) => ({ ...p, topics: p.topics.filter((x) => x.id !== id) }))}
          />
        </TabsContent>

        <TabsContent value="domains" dir="rtl" className="mt-4 text-right">
          <EntityList
            title={t('wizard.domains')}
            addLabel={t('wizard.addDomain')}
            onAdd={() =>
              setDomainDialog({
                id: `dom_${Date.now()}`,
                topic_id: draft.topics[0]?.id || '',
                name: '',
                description: '',
                p_suggest: 0.1,
                sort_order: draft.domains.length + 1,
                active: true,
              })
            }
            rows={draft.domains}
            columns={[
              { key: 'name', label: t('serviceName'), render: (r) => r.name || '—' },
              { key: 'topic', label: t('wizard.stepTopic'), render: (r) => topicName(r.topic_id) },
              {
                key: 'p',
                label: t('wizard.pSuggest'),
                render: (r) => `${fractionToPercentInput(r.p_suggest)}`,
              },
            ]}
            onEdit={(row) => setDomainDialog({ ...row })}
            onDelete={(id) => setDraft((p) => ({ ...p, domains: p.domains.filter((x) => x.id !== id) }))}
          />
        </TabsContent>

        <TabsContent value="categories" dir="rtl" className="mt-4 text-right">
          <EntityList
            title={t('wizard.categories')}
            addLabel={t('wizard.addCategory')}
            onAdd={() =>
              setCategoryDialog({
                id: `cat_${Date.now()}`,
                name: '',
                description: '',
                sort_order: draft.categories.length + 1,
                active: true,
                topic_id: '',
                domain_id: '',
              })
            }
            rows={draft.categories}
            columns={[
              { key: 'name', label: t('serviceName'), render: (r) => r.name || '—' },
              {
                key: 'topic',
                label: t('wizard.stepTopic'),
                render: (r) => (r.topic_id ? topicName(r.topic_id) : t('wizard.anyTopic')),
              },
              {
                key: 'domain',
                label: t('wizard.stepDomain'),
                render: (r) => (r.domain_id ? domainName(r.domain_id) : t('wizard.anyDomain')),
              },
            ]}
            onEdit={(row) => setCategoryDialog({ ...row })}
            onDelete={(id) =>
              setDraft((p) => ({ ...p, categories: p.categories.filter((x) => x.id !== id) }))
            }
          />
        </TabsContent>

        <TabsContent value="catalog" dir="rtl" className="mt-4 text-right">
          <EntityList
            title={t('catalog')}
            addLabel={t('addService')}
            onAdd={() => setCatalogDialog(newCatalogItem(draft.catalog.length + 1))}
            rows={draft.catalog}
            columns={[
              { key: 'name', label: t('serviceName'), render: (r) => r.name || '—' },
              {
                key: 'cat',
                label: t('category'),
                render: (r) => (r.category_id ? categoryName(r.category_id) : r.category || '—'),
              },
              { key: 'amount', label: t('amount'), render: (r) => String(r.amount) },
              {
                key: 'when',
                label: t('wizard.showWhen'),
                render: (r) =>
                  r.show_when_item_id ? catalogName(r.show_when_item_id) : t('wizard.showAlways'),
              },
            ]}
            onEdit={(row) => setCatalogDialog({ ...row })}
            onDelete={(id) => setDraft((p) => ({ ...p, catalog: p.catalog.filter((x) => x.id !== id) }))}
          />
        </TabsContent>

        <TabsContent value="wizard" dir="rtl" className="mt-4 text-right space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3" dir="rtl">
              <CardTitle className="text-base text-right">{t('settingsWizardSteps')}</CardTitle>
              <Button
                type="button"
                size="sm"
                variant="secondary"
                onClick={() =>
                  setStepDialog({
                    id: `note_${Date.now()}`,
                    type: 'note',
                    label: '',
                    body: '',
                    sort_order: draft.wizard_steps.length + 1,
                    active: true,
                    system: false,
                  })
                }
              >
                <Plus className="h-4 w-4" />
                {t('wizard.addNoteStep')}
              </Button>
            </CardHeader>
            <CardContent className="space-y-2">
              {sortedSteps.map((step) => (
                <div
                  key={step.id}
                  className="flex flex-wrap items-center gap-2 rounded-lg border p-3"
                >
                  <div className="min-w-0 flex-1">
                    <div className="font-medium">{step.label || step.type}</div>
                    <div className="text-xs text-muted-foreground">
                      {step.type}
                      {step.type === 'note' && step.body ? ` — ${step.body.slice(0, 60)}` : ''}
                    </div>
                  </div>
                  <Switch
                    checked={step.active !== false}
                    onCheckedChange={(v) =>
                      setDraft((prev) => ({
                        ...prev,
                        wizard_steps: prev.wizard_steps.map((s) =>
                          s.id === step.id ? { ...s, active: !!v } : s,
                        ),
                      }))
                    }
                  />
                  <Button type="button" size="icon" variant="ghost" onClick={() => moveStep(step.id, -1)}>
                    <ArrowUp className="h-4 w-4" />
                  </Button>
                  <Button type="button" size="icon" variant="ghost" onClick={() => moveStep(step.id, 1)}>
                    <ArrowDown className="h-4 w-4" />
                  </Button>
                  <Button type="button" size="icon" variant="ghost" onClick={() => setStepDialog({ ...step })}>
                    <Pencil className="h-4 w-4" />
                  </Button>
                  {!step.system && step.type === 'note' ? (
                    <Button
                      type="button"
                      size="icon"
                      variant="ghost"
                      className="text-destructive"
                      onClick={() =>
                        setDraft((p) => ({
                          ...p,
                          wizard_steps: p.wizard_steps.filter((s) => s.id !== step.id),
                        }))
                      }
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  ) : null}
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('wizard.durationOptions')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex flex-wrap gap-2">
                {draft.duration_options.map((n) => (
                  <div key={n} className="flex items-center gap-1 rounded-full border px-3 py-1 text-sm">
                    <span dir="ltr">{n}</span>
                    <Button
                      type="button"
                      size="icon"
                      variant="ghost"
                      className="size-6 text-destructive"
                      onClick={() =>
                        setDraft((p) => ({
                          ...p,
                          duration_options: p.duration_options.filter((x) => x !== n),
                        }))
                      }
                    >
                      <Trash2 className="h-3 w-3" />
                    </Button>
                  </div>
                ))}
              </div>
              <div className="flex max-w-xs items-end gap-2">
                <div className="grid flex-1 gap-2">
                  <Label>{t('wizard.addDuration')}</Label>
                  <Input
                    type="number"
                    dir="ltr"
                    min={1}
                    value={newDuration}
                    onChange={(e) => setNewDuration(e.target.value)}
                  />
                </div>
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => {
                    const n = Number(newDuration);
                    if (!n || n < 1) return;
                    setDraft((p) => ({
                      ...p,
                      duration_options: Array.from(new Set([...p.duration_options, n])).sort(
                        (a, b) => a - b,
                      ),
                    }));
                    setNewDuration('');
                  }}
                >
                  <Plus className="h-4 w-4" />
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <div className="flex justify-end">
        <Button type="button" onClick={() => void save()} disabled={saving}>
          <Save className="h-4 w-4" />
          {t('saveSettings')}
        </Button>
      </div>

      {/* Topic dialog */}
      <Dialog open={!!topicDialog} onOpenChange={(o) => !o && setTopicDialog(null)}>
        <DialogContent dir="rtl" className="max-h-[90vh] overflow-y-auto text-right sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{topicDialog && draft.topics.some((x) => x.id === topicDialog.id) ? t('wizard.editTopic') : t('wizard.addTopic')}</DialogTitle>
          </DialogHeader>
          {topicDialog ? (
            <div className="grid gap-3">
              <div className="grid gap-2">
                <Label>{t('serviceName')}</Label>
                <Input
                  value={topicDialog.name}
                  onChange={(e) => setTopicDialog({ ...topicDialog, name: e.target.value })}
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.description')}</Label>
                <Textarea
                  value={topicDialog.description || ''}
                  onChange={(e) => setTopicDialog({ ...topicDialog, description: e.target.value })}
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.sortOrder')}</Label>
                <Input
                  type="number"
                  dir="ltr"
                  value={topicDialog.sort_order}
                  onChange={(e) =>
                    setTopicDialog({ ...topicDialog, sort_order: Number(e.target.value) || 0 })
                  }
                />
              </div>
              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={topicDialog.active !== false}
                  onCheckedChange={(v) => setTopicDialog({ ...topicDialog, active: !!v })}
                />
                {t('active')}
              </label>
            </div>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setTopicDialog(null)}>
              {t('cancel')}
            </Button>
            <Button
              type="button"
              onClick={() => {
                if (!topicDialog?.name.trim()) return;
                setDraft((prev) => {
                  const exists = prev.topics.some((x) => x.id === topicDialog.id);
                  return {
                    ...prev,
                    topics: exists
                      ? prev.topics.map((x) => (x.id === topicDialog.id ? topicDialog : x))
                      : [...prev.topics, topicDialog],
                  };
                });
                setTopicDialog(null);
              }}
            >
              {t('apply')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Domain dialog */}
      <Dialog open={!!domainDialog} onOpenChange={(o) => !o && setDomainDialog(null)}>
        <DialogContent dir="rtl" className="max-h-[90vh] overflow-y-auto text-right sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {domainDialog && draft.domains.some((x) => x.id === domainDialog.id)
                ? t('wizard.editDomain')
                : t('wizard.addDomain')}
            </DialogTitle>
          </DialogHeader>
          {domainDialog ? (
            <div className="grid gap-3">
              <div className="grid gap-2">
                <Label>{t('serviceName')}</Label>
                <Input
                  value={domainDialog.name}
                  onChange={(e) => setDomainDialog({ ...domainDialog, name: e.target.value })}
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.stepTopic')}</Label>
                <Select
                  value={domainDialog.topic_id || '__none'}
                  onValueChange={(v) =>
                    setDomainDialog({ ...domainDialog, topic_id: v === '__none' ? '' : v })
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none">—</SelectItem>
                    {draft.topics.map((topic) => (
                      <SelectItem key={topic.id} value={topic.id}>
                        {topic.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.pSuggest')}</Label>
                <Input
                  type="number"
                  step="any"
                  dir="ltr"
                  value={fractionToPercentInput(domainDialog.p_suggest)}
                  onChange={(e) =>
                    setDomainDialog({
                      ...domainDialog,
                      p_suggest: percentInputToFraction(Number(e.target.value) || 0),
                    })
                  }
                />
                <p className="text-xs text-muted-foreground">{t('percentHint')}</p>
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.description')}</Label>
                <Textarea
                  value={domainDialog.description || ''}
                  onChange={(e) => setDomainDialog({ ...domainDialog, description: e.target.value })}
                />
              </div>
              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={domainDialog.active !== false}
                  onCheckedChange={(v) => setDomainDialog({ ...domainDialog, active: !!v })}
                />
                {t('active')}
              </label>
            </div>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDomainDialog(null)}>
              {t('cancel')}
            </Button>
            <Button
              type="button"
              onClick={() => {
                if (!domainDialog?.name.trim()) return;
                setDraft((prev) => {
                  const exists = prev.domains.some((x) => x.id === domainDialog.id);
                  return {
                    ...prev,
                    domains: exists
                      ? prev.domains.map((x) => (x.id === domainDialog.id ? domainDialog : x))
                      : [...prev.domains, domainDialog],
                  };
                });
                setDomainDialog(null);
              }}
            >
              {t('apply')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Category dialog */}
      <Dialog open={!!categoryDialog} onOpenChange={(o) => !o && setCategoryDialog(null)}>
        <DialogContent dir="rtl" className="max-h-[90vh] overflow-y-auto text-right sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {categoryDialog && draft.categories.some((x) => x.id === categoryDialog.id)
                ? t('wizard.editCategory')
                : t('wizard.addCategory')}
            </DialogTitle>
          </DialogHeader>
          {categoryDialog ? (
            <div className="grid gap-3">
              <div className="grid gap-2">
                <Label>{t('serviceName')}</Label>
                <Input
                  value={categoryDialog.name}
                  onChange={(e) => setCategoryDialog({ ...categoryDialog, name: e.target.value })}
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.description')}</Label>
                <Textarea
                  value={categoryDialog.description || ''}
                  onChange={(e) =>
                    setCategoryDialog({ ...categoryDialog, description: e.target.value })
                  }
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.stepTopic')}</Label>
                <Select
                  value={categoryDialog.topic_id || '__any'}
                  onValueChange={(v) =>
                    setCategoryDialog({
                      ...categoryDialog,
                      topic_id: v === '__any' ? '' : v,
                      domain_id: '',
                    })
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__any">{t('wizard.anyTopic')}</SelectItem>
                    {draft.topics.map((topic) => (
                      <SelectItem key={topic.id} value={topic.id}>
                        {topic.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.stepDomain')}</Label>
                <Select
                  value={categoryDialog.domain_id || '__any'}
                  onValueChange={(v) =>
                    setCategoryDialog({
                      ...categoryDialog,
                      domain_id: v === '__any' ? '' : v,
                    })
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__any">{t('wizard.anyDomain')}</SelectItem>
                    {draft.domains
                      .filter(
                        (d) =>
                          !categoryDialog.topic_id || d.topic_id === categoryDialog.topic_id,
                      )
                      .map((domain) => (
                        <SelectItem key={domain.id} value={domain.id}>
                          {domain.name}
                        </SelectItem>
                      ))}
                  </SelectContent>
                </Select>
              </div>
              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={categoryDialog.active !== false}
                  onCheckedChange={(v) => setCategoryDialog({ ...categoryDialog, active: !!v })}
                />
                {t('active')}
              </label>
            </div>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setCategoryDialog(null)}>
              {t('cancel')}
            </Button>
            <Button
              type="button"
              onClick={() => {
                if (!categoryDialog?.name.trim()) return;
                setDraft((prev) => {
                  const exists = prev.categories.some((x) => x.id === categoryDialog.id);
                  return {
                    ...prev,
                    categories: exists
                      ? prev.categories.map((x) =>
                          x.id === categoryDialog.id ? categoryDialog : x,
                        )
                      : [...prev.categories, categoryDialog],
                  };
                });
                setCategoryDialog(null);
              }}
            >
              {t('apply')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Catalog dialog */}
      <Dialog open={!!catalogDialog} onOpenChange={(o) => !o && setCatalogDialog(null)}>
        <DialogContent dir="rtl" className="max-h-[90vh] overflow-y-auto text-right sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>
              {catalogDialog && draft.catalog.some((x) => x.id === catalogDialog.id)
                ? t('wizard.editService')
                : t('addService')}
            </DialogTitle>
          </DialogHeader>
          {catalogDialog ? (
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="grid gap-2 sm:col-span-2">
                <Label>{t('serviceName')}</Label>
                <Input
                  value={catalogDialog.name}
                  onChange={(e) => setCatalogDialog({ ...catalogDialog, name: e.target.value })}
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('billingLabel')}</Label>
                <Select
                  value={catalogDialog.billing}
                  onValueChange={(v) =>
                    setCatalogDialog({ ...catalogDialog, billing: v as RahnBilling })
                  }
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
                  value={catalogDialog.period_months}
                  onChange={(e) =>
                    setCatalogDialog({
                      ...catalogDialog,
                      period_months: Math.max(1, Number(e.target.value) || 1),
                    })
                  }
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('amount')}</Label>
                <Input
                  type="number"
                  dir="ltr"
                  value={catalogDialog.amount}
                  onChange={(e) =>
                    setCatalogDialog({ ...catalogDialog, amount: Number(e.target.value) || 0 })
                  }
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('category')}</Label>
                <Select
                  value={catalogDialog.category_id || '__none'}
                  onValueChange={(v) =>
                    setCatalogDialog({
                      ...catalogDialog,
                      category_id: v === '__none' ? '' : v,
                      category:
                        v === '__none'
                          ? catalogDialog.category
                          : draft.categories.find((c) => c.id === v)?.name || catalogDialog.category,
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
              <div className="grid gap-2 sm:col-span-2">
                <Label>{t('wizard.showWhen')}</Label>
                <Select
                  value={catalogDialog.show_when_item_id || '__always'}
                  onValueChange={(v) =>
                    setCatalogDialog({
                      ...catalogDialog,
                      show_when_item_id: v === '__always' ? '' : v,
                    })
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__always">{t('wizard.showAlways')}</SelectItem>
                    {draft.catalog
                      .filter((c) => c.id !== catalogDialog.id)
                      .map((c) => (
                        <SelectItem key={c.id} value={c.id}>
                          {c.name || c.id}
                        </SelectItem>
                      ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.choiceGroup')}</Label>
                <Input
                  value={catalogDialog.choice_group || ''}
                  dir="ltr"
                  onChange={(e) =>
                    setCatalogDialog({ ...catalogDialog, choice_group: e.target.value })
                  }
                />
              </div>
              <div className="grid gap-2">
                <Label>{t('wizard.feeLabel')}</Label>
                <Input
                  value={catalogDialog.fee_label || ''}
                  onChange={(e) =>
                    setCatalogDialog({ ...catalogDialog, fee_label: e.target.value })
                  }
                />
              </div>
              <div className="grid gap-2 sm:col-span-2">
                <Label>{t('wizard.description')}</Label>
                <Input
                  value={catalogDialog.description}
                  onChange={(e) =>
                    setCatalogDialog({ ...catalogDialog, description: e.target.value })
                  }
                />
              </div>
              <div className="flex flex-wrap gap-4 sm:col-span-2">
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={catalogDialog.renewable}
                    onCheckedChange={(v) =>
                      setCatalogDialog({ ...catalogDialog, renewable: !!v })
                    }
                  />
                  {t('renewable')}
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={catalogDialog.active}
                    onCheckedChange={(v) => setCatalogDialog({ ...catalogDialog, active: !!v })}
                  />
                  {t('active')}
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={catalogDialog.default_selected}
                    onCheckedChange={(v) =>
                      setCatalogDialog({ ...catalogDialog, default_selected: !!v })
                    }
                  />
                  {t('defaultSelected')}
                </label>
              </div>
            </div>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setCatalogDialog(null)}>
              {t('cancel')}
            </Button>
            <Button
              type="button"
              onClick={() => {
                if (!catalogDialog?.name.trim()) return;
                setDraft((prev) => {
                  const exists = prev.catalog.some((x) => x.id === catalogDialog.id);
                  return {
                    ...prev,
                    catalog: exists
                      ? prev.catalog.map((x) => (x.id === catalogDialog.id ? catalogDialog : x))
                      : [...prev.catalog, catalogDialog],
                  };
                });
                setCatalogDialog(null);
              }}
            >
              {t('apply')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Wizard step dialog */}
      <Dialog open={!!stepDialog} onOpenChange={(o) => !o && setStepDialog(null)}>
        <DialogContent dir="rtl" className="max-h-[90vh] overflow-y-auto text-right sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t('wizard.editStep')}</DialogTitle>
          </DialogHeader>
          {stepDialog ? (
            <div className="grid gap-3">
              <div className="grid gap-2">
                <Label>{t('serviceName')}</Label>
                <Input
                  value={stepDialog.label}
                  onChange={(e) => setStepDialog({ ...stepDialog, label: e.target.value })}
                />
              </div>
              {stepDialog.type === 'note' ? (
                <div className="grid gap-2">
                  <Label>{t('wizard.noteBody')}</Label>
                  <Textarea
                    rows={4}
                    value={stepDialog.body || ''}
                    onChange={(e) => setStepDialog({ ...stepDialog, body: e.target.value })}
                  />
                </div>
              ) : null}
              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={stepDialog.active !== false}
                  onCheckedChange={(v) => setStepDialog({ ...stepDialog, active: !!v })}
                />
                {t('active')}
              </label>
            </div>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setStepDialog(null)}>
              {t('cancel')}
            </Button>
            <Button
              type="button"
              onClick={() => {
                if (!stepDialog?.label.trim()) return;
                setDraft((prev) => {
                  const exists = prev.wizard_steps.some((x) => x.id === stepDialog.id);
                  return {
                    ...prev,
                    wizard_steps: exists
                      ? prev.wizard_steps.map((x) => (x.id === stepDialog.id ? stepDialog : x))
                      : [...prev.wizard_steps, stepDialog],
                  };
                });
                setStepDialog(null);
              }}
            >
              {t('apply')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function EntityList<T extends { id: string }>({
  title,
  addLabel,
  onAdd,
  rows,
  columns,
  onEdit,
  onDelete,
}: {
  title: string;
  addLabel: string;
  onAdd: () => void;
  rows: T[];
  columns: Array<{ key: string; label: string; render: (row: T) => string }>;
  onEdit: (row: T) => void;
  onDelete: (id: string) => void;
}) {
  return (
    <Card dir="rtl">
      <CardHeader className="flex flex-row items-center justify-between gap-3" dir="rtl">
        <CardTitle className="text-base text-right">{title}</CardTitle>
        <Button type="button" size="sm" variant="secondary" onClick={onAdd}>
          <Plus className="h-4 w-4" />
          {addLabel}
        </Button>
      </CardHeader>
      <CardContent className="space-y-2" dir="rtl">
        {rows.length === 0 ? (
          <p className="text-sm text-muted-foreground text-right">—</p>
        ) : (
          <div className="overflow-x-auto rounded-lg border" dir="rtl">
            <table className="w-full text-sm text-right">
              <thead className="bg-muted/40 text-muted-foreground">
                <tr>
                  {columns.map((c) => (
                    <th key={c.key} className="px-3 py-2 text-right font-medium">
                      {c.label}
                    </th>
                  ))}
                  <th className="w-24 px-3 py-2 text-right font-medium" />
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-t">
                    {columns.map((c) => (
                      <td key={c.key} className="px-3 py-2 text-right">
                        {c.render(row)}
                      </td>
                    ))}
                    <td className="px-3 py-2">
                      <div className="flex justify-start gap-1">
                        <Button type="button" size="icon" variant="ghost" onClick={() => onEdit(row)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          type="button"
                          size="icon"
                          variant="ghost"
                          className="text-destructive"
                          onClick={() => onDelete(row.id)}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
