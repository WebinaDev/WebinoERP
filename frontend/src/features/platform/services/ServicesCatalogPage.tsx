'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useLocale, useTranslations } from 'next-intl';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboardHref } from '@/lib/route-resolver';
import { fetchServiceTemplates, type PlatformServiceTemplate } from '@/lib/api/platform';
import { PlatformPageLayout, RefreshButton } from '@/features/platform/PlatformPageLayout';

export function ServicesCatalogPage() {
  const t = useTranslations('platform.services');
  const tP = useTranslations('platform');
  const locale = useLocale();
  const [rows, setRows] = useState<PlatformServiceTemplate[]>([]);
  const [categoryFilter, setCategoryFilter] = useState('');
  const [search, setSearch] = useState('');
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setRows(await fetchServiceTemplates());
    } catch (e) {
      setError(e instanceof Error ? e.message : tP('loadError'));
    }
  }, [tP]);

  useEffect(() => {
    void load();
  }, [load]);

  const categories = useMemo(() => {
    const set = new Set<string>();
    rows.forEach((r) => { if (r.category) set.add(r.category); });
    return Array.from(set).sort();
  }, [rows]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return rows.filter((r) => {
      if (categoryFilter && r.category !== categoryFilter) return false;
      if (!q) return true;
      return r.name.toLowerCase().includes(q) || r.slug.toLowerCase().includes(q) || (r.description ?? '').toLowerCase().includes(q);
    });
  }, [rows, categoryFilter, search]);

  const grouped = useMemo(() => {
    const map = new Map<string, PlatformServiceTemplate[]>();
    filtered.forEach((row) => {
      const cat = row.category || t('uncategorized');
      const list = map.get(cat) ?? [];
      list.push(row);
      map.set(cat, list);
    });
    return Array.from(map.entries()).sort(([a], [b]) => a.localeCompare(b));
  }, [filtered, t]);

  return (
    <PlatformPageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      error={error}
      actions={<RefreshButton onClick={() => void load()} label={tP('refresh')} />}
    >
      <div className="mb-4 grid gap-3 md:grid-cols-2">
        <div className="grid gap-2">
          <Label>{t('filterCategory')}</Label>
          <select className="border rounded-md h-10 px-3 bg-background" value={categoryFilter} onChange={(e) => setCategoryFilter(e.target.value)}>
            <option value="">{t('allCategories')}</option>
            {categories.map((c) => <option key={c} value={c}>{c}</option>)}
          </select>
        </div>
        <div className="grid gap-2">
          <Label>{t('search')}</Label>
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('searchPlaceholder')} />
        </div>
      </div>

      <div className="space-y-8">
        {grouped.map(([category, items]) => (
          <section key={category}>
            <h2 className="mb-3 text-lg font-semibold">{category}</h2>
            <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
              {items.map((row) => (
                <Card key={row.slug}>
                  <CardHeader className="pb-2">
                    <CardTitle className="text-base">
                      <Link href={dashboardHref(locale, `admin/platform/services/${row.slug}`)} className="hover:underline">{row.name}</Link>
                    </CardTitle>
                    {row.category ? <Badge variant="outline">{row.category}</Badge> : null}
                  </CardHeader>
                  <CardContent className="text-sm text-muted-foreground">{row.description ?? row.slug}</CardContent>
                </Card>
              ))}
            </div>
          </section>
        ))}
        {!filtered.length ? <p className="text-sm text-muted-foreground">{tP('noData')}</p> : null}
      </div>
    </PlatformPageLayout>
  );
}
