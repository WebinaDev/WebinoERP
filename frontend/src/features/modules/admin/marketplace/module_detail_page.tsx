'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  getModule,
  listModuleReleases,
  publishRelease,
  saveModule,
  syncModuleReadme,
  syncModuleRepo,
  createModuleRelease,
  deleteRelease,
} from '@/lib/api/marketplace';
import { dashboardHref } from '@/lib/route-resolver';
import { normalizeListPayload } from '@/lib/list-utils';

export function ModuleDetailPage({ moduleId: moduleIdProp }: { moduleId?: string }) {
  const t = useTranslations('marketplace.modules');
  const tNav = useTranslations();
  const params = useParams();
  const router = useRouter();
  const locale = (params?.locale as string) || 'fa';
  const rawId = moduleIdProp;
  const isCreate = rawId === 'new';
  const moduleId = isCreate ? null : rawId;
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [form, setForm] = useState({ name: '', slug: '', version: '1.0.0', description: '', price: '0', readme_md: '' });
  const [releases, setReleases] = useState<Record<string, unknown>[]>([]);
  const [releaseVersion, setReleaseVersion] = useState('');

  const load = useCallback(async () => {
    if (!moduleId) return;
    try {
      const mod = await getModule(moduleId);
      const m = mod as Record<string, unknown>;
      setForm({
        name: String(m.name ?? ''),
        slug: String(m.slug ?? ''),
        version: String(m.version ?? '1.0.0'),
        description: String(m.description ?? ''),
        price: String(m.price ?? '0'),
        readme_md: String(m.readme_md ?? ''),
      });
      const rel = await listModuleReleases(moduleId);
      setReleases(normalizeListPayload(rel as { data?: unknown }));
    } catch (err) {
      applyAxiosError(err);
    }
  }, [moduleId, applyAxiosError]);

  useEffect(() => {
    if (!isCreate) void load();
  }, [isCreate, load]);

  const save = async () => {
    try {
      const res = await saveModule(form, moduleId ?? undefined);
      setSuccess(t('saved'));
      if (isCreate) {
        const id = (res as { id?: number })?.id;
        if (id) router.push(dashboardHref(locale, `admin/marketplace/modules/${id}`));
      } else {
        void load();
      }
    } catch (err) {
      applyAxiosError(err);
    }
  };

  const addRelease = async () => {
    if (!moduleId) return;
    try {
      await createModuleRelease(moduleId, { version: releaseVersion });
      setReleaseVersion('');
      void load();
    } catch (err) {
      applyAxiosError(err);
    }
  };

  return (
    <CrmPageLayout
      title={isCreate ? t('new') : t('edit')}
      actions={
        <>
          <Button variant="outline" asChild>
            <Link href={dashboardHref(locale, 'admin/marketplace/products')}>{tNav('common.back')}</Link>
          </Button>
          <Button onClick={() => void save()}>{tNav('common.save')}</Button>
        </>
      }
      {...layoutProps}
    >
      <Card>
        <CardHeader><CardTitle>{tNav('nav.erp.distribution.marketplaceProducts')}</CardTitle></CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-2">
          <Input placeholder={tNav('common.search')} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <Input placeholder={t('slug')} value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} />
          <Input placeholder={t('version')} value={form.version} onChange={(e) => setForm({ ...form, version: e.target.value })} />
          <Input placeholder="Price" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} />
          <Textarea className="md:col-span-2" placeholder="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={3} />
          <Textarea className="md:col-span-2" placeholder="README" value={form.readme_md} onChange={(e) => setForm({ ...form, readme_md: e.target.value })} rows={6} />
        </CardContent>
      </Card>

      {!isCreate && moduleId ? (
        <>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle className="text-base">Gitea</CardTitle>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={() => void syncModuleRepo(moduleId).then(() => setSuccess(t('syncRepo')))}>{t('syncRepo')}</Button>
                <Button variant="outline" size="sm" onClick={() => void syncModuleReadme(moduleId).then(() => void load())}>{t('syncReadme')}</Button>
              </div>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader><CardTitle className="text-base">{t('releases')}</CardTitle></CardHeader>
            <CardContent className="space-y-4">
              <div className="flex gap-2">
                <Input placeholder={t('version')} value={releaseVersion} onChange={(e) => setReleaseVersion(e.target.value)} />
                <Button onClick={() => void addRelease()}>{tNav('common.add')}</Button>
              </div>
              <Table>
                <TableHeader><TableRow><TableHead>Version</TableHead><TableHead>Status</TableHead><TableHead /></TableRow></TableHeader>
                <TableBody>
                  {releases.map((r) => (
                    <TableRow key={String(r.id)}>
                      <TableCell>{String(r.version ?? '')}</TableCell>
                      <TableCell>{String(r.status ?? '')}</TableCell>
                      <TableCell className="flex gap-2">
                        <Button size="sm" variant="outline" onClick={() => void publishRelease(r.id as number).then(load)}>{t('publish')}</Button>
                        <Button size="sm" variant="ghost" onClick={() => void deleteRelease(r.id as number).then(load)}>{tNav('common.delete')}</Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </>
      ) : null}
    </CrmPageLayout>
  );
}
