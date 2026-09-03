'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  fetchCatalog,
  fetchFeatures,
  fetchPackages,
  type BusinessCategory,
  type DashboardFeature,
  type PackageRow,
} from '@/lib/api/site-builder';

export function SiteBuilderCatalogPage() {
  const t = useTranslations('siteBuilder');
  const [categories, setCategories] = useState<BusinessCategory[]>([]);
  const [features, setFeatures] = useState<DashboardFeature[]>([]);
  const [packages, setPackages] = useState<PackageRow[]>([]);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const [c, f, p] = await Promise.all([fetchCatalog(), fetchFeatures(), fetchPackages()]);
      setCategories(c);
      setFeatures(f);
      setPackages(p);
    } catch (e) {
      setError(e instanceof Error ? e.message : t('loadError'));
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-4 p-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('catalogTitle')}</h1>
        <p className="text-muted-foreground text-sm">{t('catalogSubtitle')}</p>
      </div>
      {error ? <p className="text-destructive text-sm">{error}</p> : null}
      <Tabs defaultValue="categories">
        <TabsList>
          <TabsTrigger value="categories">{t('tabCategories')}</TabsTrigger>
          <TabsTrigger value="features">{t('tabFeatures')}</TabsTrigger>
          <TabsTrigger value="packages">{t('tabPackages')}</TabsTrigger>
        </TabsList>
        <TabsContent value="categories" className="grid gap-3 md:grid-cols-2">
          {categories.map((cat) => (
            <Card key={cat.id}>
              <CardHeader>
                <CardTitle>{cat.name_fa}</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-sm">
                <p className="text-muted-foreground">{cat.name_en}</p>
                <ul className="list-disc ps-5">
                  {(cat.types ?? []).map((type) => (
                    <li key={type.id}>
                      {type.name_fa} <span className="text-muted-foreground">({type.slug})</span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          ))}
        </TabsContent>
        <TabsContent value="features" className="grid gap-2">
          {features.map((f) => (
            <div key={f.id} className="flex items-center justify-between rounded border p-3 text-sm">
              <span>{f.name_fa}</span>
              <span className="text-muted-foreground font-mono">{f.slug}</span>
            </div>
          ))}
        </TabsContent>
        <TabsContent value="packages" className="grid gap-2">
          {packages.map((p) => (
            <div key={p.id} className="rounded border p-3 text-sm">
              <div className="font-medium">{p.name_fa}</div>
              <div className="text-muted-foreground font-mono">{p.sku}</div>
            </div>
          ))}
        </TabsContent>
      </Tabs>
      <Button type="button" variant="outline" onClick={() => void load()}>
        {t('refresh')}
      </Button>
    </div>
  );
}
