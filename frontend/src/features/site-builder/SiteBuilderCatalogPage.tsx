'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { getAxiosMessage } from '@/lib/api-helpers';
import {
  fetchCatalog,
  fetchFeatures,
  fetchPackages,
  saveCategory,
  saveFeature,
  savePackage,
  saveType,
  type BusinessCategory,
  type DashboardFeature,
  type PackageRow,
} from '@/lib/api/site-builder';

type DialogKind = 'category' | 'type' | 'feature' | 'package' | null;

export function SiteBuilderCatalogPage() {
  const t = useTranslations('siteBuilder');
  const [categories, setCategories] = useState<BusinessCategory[]>([]);
  const [features, setFeatures] = useState<DashboardFeature[]>([]);
  const [packages, setPackages] = useState<PackageRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [dialog, setDialog] = useState<DialogKind>(null);
  const [saving, setSaving] = useState(false);

  const [slug, setSlug] = useState('');
  const [nameFa, setNameFa] = useState('');
  const [nameEn, setNameEn] = useState('');
  const [categoryId, setCategoryId] = useState<number | ''>('');
  const [businessTypeId, setBusinessTypeId] = useState<number | ''>('');
  const [sku, setSku] = useState('');
  const [price, setPrice] = useState('0');

  const allTypes = useMemo(
    () => categories.flatMap((c) => (c.types ?? []).map((type) => ({ ...type, category_name: c.name_fa }))),
    [categories],
  );

  const load = useCallback(async () => {
    setError(null);
    try {
      const [c, f, p] = await Promise.all([fetchCatalog(), fetchFeatures(), fetchPackages()]);
      setCategories(c);
      setFeatures(f);
      setPackages(p);
    } catch (e) {
      setError(getAxiosMessage(e) || t('loadError'));
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  function resetForm() {
    setSlug('');
    setNameFa('');
    setNameEn('');
    setCategoryId('');
    setBusinessTypeId('');
    setSku('');
    setPrice('0');
  }

  function openDialog(kind: DialogKind, preset?: { categoryId?: number }) {
    resetForm();
    if (preset?.categoryId) setCategoryId(preset.categoryId);
    setDialog(kind);
  }

  async function handleSave() {
    if (!dialog) return;
    setSaving(true);
    setError(null);
    try {
      if (dialog === 'category') {
        await saveCategory({ slug, name_fa: nameFa, name_en: nameEn });
      } else if (dialog === 'type') {
        if (!categoryId) throw new Error(t('saveError'));
        await saveType({
          category_id: Number(categoryId),
          slug,
          name_fa: nameFa,
          name_en: nameEn,
        });
      } else if (dialog === 'feature') {
        await saveFeature({ slug, name_fa: nameFa, name_en: nameEn, is_addon: false });
      } else if (dialog === 'package') {
        if (!businessTypeId || !sku.trim()) throw new Error(t('saveError'));
        await savePackage({
          business_type_id: Number(businessTypeId),
          sku,
          name_fa: nameFa,
          name_en: nameEn,
          price: Number(price) || 0,
        });
      }
      setDialog(null);
      resetForm();
      await load();
    } catch (e) {
      setError(getAxiosMessage(e) || t('saveError'));
    } finally {
      setSaving(false);
    }
  }

  const dialogTitle =
    dialog === 'category'
      ? t('addCategory')
      : dialog === 'type'
        ? t('addType')
        : dialog === 'feature'
          ? t('addFeature')
          : dialog === 'package'
            ? t('addPackage')
            : '';

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">{t('catalogTitle')}</h1>
          <p className="text-muted-foreground text-sm">{t('catalogSubtitle')}</p>
        </div>
        <Button type="button" variant="outline" onClick={() => void load()}>
          {t('refresh')}
        </Button>
      </div>
      {error ? <p className="text-destructive text-sm">{error}</p> : null}
      <Tabs defaultValue="categories">
        <TabsList>
          <TabsTrigger value="categories">{t('tabCategories')}</TabsTrigger>
          <TabsTrigger value="features">{t('tabFeatures')}</TabsTrigger>
          <TabsTrigger value="packages">{t('tabPackages')}</TabsTrigger>
        </TabsList>

        <TabsContent value="categories" className="space-y-3">
          <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" onClick={() => openDialog('category')}>
              {t('addCategory')}
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => openDialog('type')}>
              {t('addType')}
            </Button>
          </div>
          <div className="grid gap-3 md:grid-cols-2">
            {categories.map((cat) => (
              <Card key={cat.id}>
                <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
                  <div>
                    <CardTitle>{cat.name_fa}</CardTitle>
                    <p className="text-muted-foreground text-sm">{cat.name_en}</p>
                    <p className="text-muted-foreground font-mono text-xs">{cat.slug}</p>
                  </div>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => openDialog('type', { categoryId: cat.id })}
                  >
                    {t('addType')}
                  </Button>
                </CardHeader>
                <CardContent className="space-y-2 text-sm">
                  <ul className="list-disc ps-5">
                    {(cat.types ?? []).map((type) => (
                      <li key={type.id}>
                        {type.name_fa}{' '}
                        <span className="text-muted-foreground">({type.slug})</span>
                      </li>
                    ))}
                  </ul>
                  {(cat.types ?? []).length === 0 ? (
                    <p className="text-muted-foreground text-xs">—</p>
                  ) : null}
                </CardContent>
              </Card>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="features" className="space-y-3">
          <Button type="button" size="sm" onClick={() => openDialog('feature')}>
            {t('addFeature')}
          </Button>
          <div className="grid gap-2">
            {features.map((f) => (
              <div key={f.id} className="flex items-center justify-between rounded border p-3 text-sm">
                <div>
                  <div>{f.name_fa}</div>
                  <div className="text-muted-foreground text-xs">{f.name_en}</div>
                </div>
                <span className="text-muted-foreground font-mono">{f.slug}</span>
              </div>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="packages" className="space-y-3">
          <Button type="button" size="sm" onClick={() => openDialog('package')}>
            {t('addPackage')}
          </Button>
          <div className="grid gap-2">
            {packages.map((p) => (
              <div key={p.id} className="rounded border p-3 text-sm">
                <div className="font-medium">{p.name_fa}</div>
                <div className="text-muted-foreground text-xs">{p.name_en}</div>
                <div className="text-muted-foreground font-mono">{p.sku}</div>
                <div className="text-muted-foreground text-xs">{p.price}</div>
              </div>
            ))}
          </div>
        </TabsContent>
      </Tabs>

      <Dialog
        open={dialog !== null}
        onOpenChange={(open) => {
          if (!open) {
            setDialog(null);
            resetForm();
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{dialogTitle}</DialogTitle>
          </DialogHeader>
          <div className="grid gap-3">
            {dialog === 'type' ? (
              <div className="grid gap-2">
                <Label>{t('tabCategories')}</Label>
                <select
                  className="border-input bg-background h-10 rounded-md border px-3"
                  value={categoryId}
                  onChange={(e) => setCategoryId(e.target.value ? Number(e.target.value) : '')}
                >
                  <option value="">—</option>
                  {categories.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name_fa}
                    </option>
                  ))}
                </select>
              </div>
            ) : null}
            {dialog === 'package' ? (
              <>
                <div className="grid gap-2">
                  <Label>{t('stepType')}</Label>
                  <select
                    className="border-input bg-background h-10 rounded-md border px-3"
                    value={businessTypeId}
                    onChange={(e) => setBusinessTypeId(e.target.value ? Number(e.target.value) : '')}
                  >
                    <option value="">—</option>
                    {allTypes.map((type) => (
                      <option key={type.id} value={type.id}>
                        {type.category_name} / {type.name_fa}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="grid gap-2">
                  <Label>SKU</Label>
                  <Input value={sku} onChange={(e) => setSku(e.target.value)} dir="ltr" className="font-mono" />
                </div>
                <div className="grid gap-2">
                  <Label>Price</Label>
                  <Input
                    type="number"
                    value={price}
                    onChange={(e) => setPrice(e.target.value)}
                    dir="ltr"
                  />
                </div>
              </>
            ) : (
              <div className="grid gap-2">
                <Label>{t('slug')}</Label>
                <Input value={slug} onChange={(e) => setSlug(e.target.value)} dir="ltr" className="font-mono" />
              </div>
            )}
            <div className="grid gap-2">
              <Label>{t('nameFa')}</Label>
              <Input value={nameFa} onChange={(e) => setNameFa(e.target.value)} />
            </div>
            <div className="grid gap-2">
              <Label>{t('nameEn')}</Label>
              <Input value={nameEn} onChange={(e) => setNameEn(e.target.value)} dir="ltr" />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setDialog(null)}>
              {t('done')}
            </Button>
            <Button type="button" disabled={saving} onClick={() => void handleSave()}>
              {t('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
