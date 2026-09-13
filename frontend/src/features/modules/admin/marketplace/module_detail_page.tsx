'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import {
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  GitBranch,
  Link2,
  RefreshCw,
  Tag,
  Trash2,
} from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  createMarketplaceModuleRepo,
  deleteMarketplaceRelease,
  getMarketplaceCategories,
  getMarketplaceModule,
  getMarketplaceModules,
  publishMarketplaceRelease,
  saveMarketplaceModule,
  saveMarketplaceRelease,
  syncMarketplaceModuleReadme,
  syncMarketplaceModuleRepo,
  type MarketplaceCategory,
  type MarketplaceModule,
  type MarketplaceRelease,
} from '@/lib/api/marketplace';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale } from '@/hooks/use-locale-next';
import { ModuleIconField } from './ModuleIconField';
import { PmConfirmDialog } from './components/PmConfirmDialog';

export function ModuleDetailPage({ moduleId: moduleIdProp }: { moduleId?: string }) {
  const t = useTranslations('marketplace');
  const tNav = useTranslations();
  const params = useParams();
  const router = useRouter();
  const locale = (params?.locale as string) || 'fa';
  const { isRtl, formatDateTime } = useLocale();
  const { layoutProps, setError, setSuccess, error, applyAxiosError } = useCrmFeedback();

  const rawId = moduleIdProp ?? 'new';
  const isCreateMode = rawId === 'new';
  const moduleId = isCreateMode ? 0 : Number(rawId);

  const [module, setModule] = useState<MarketplaceModule | null>(null);
  const [categories, setCategories] = useState<MarketplaceCategory[]>([]);
  const [parentCandidates, setParentCandidates] = useState<MarketplaceModule[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [createSlug, setCreateSlug] = useState('');
  const [createVersion, setCreateVersion] = useState('1.0.0');
  const [useGitea, setUseGitea] = useState(true);
  const [repoUrlInput, setRepoUrlInput] = useState('');
  const [settingsForm, setSettingsForm] = useState({
    name: '',
    description: '',
    price: '0',
    category_id: '',
    detail_url: '',
    icon_url: '',
    settings_route: '',
    readme_md: '',
    module_git_source_id: '',
  });
  const [isFree, setIsFree] = useState(true);
  const [isBuiltin, setIsBuiltin] = useState(false);
  const [parentModuleId, setParentModuleId] = useState('');
  const [saveWarnings, setSaveWarnings] = useState<Record<string, string> | null>(null);
  const [releaseForm, setReleaseForm] = useState({ version: '', tag_name: '', changelog: '' });
  const [deleteReleaseId, setDeleteReleaseId] = useState<number | null>(null);
  const [deletingRelease, setDeletingRelease] = useState(false);

  const BackIcon = isRtl ? ChevronRight : ChevronLeft;
  const isGitea = module?.package_source === 'gitea' || module?.distribution === 'git';
  const repoLinked = Boolean(module?.gitea_repo_linked);
  const categorySelectValue = settingsForm.category_id || '__none__';

  const categoryLabel = useMemo(() => {
    if (!settingsForm.category_id) return '';
    const found = categories.find((c) => String(c.id) === settingsForm.category_id);
    if (found) return found.name;
    return module?.category_name ?? '';
  }, [categories, settingsForm.category_id, module?.category_name]);

  const orphanCategory =
    settingsForm.category_id &&
    !categories.some((c) => String(c.id) === settingsForm.category_id) &&
    module?.category_name
      ? { id: Number(settingsForm.category_id), name: module.category_name }
      : null;

  const populateSettings = useCallback((m: MarketplaceModule) => {
    setSettingsForm({
      name: m.name ?? '',
      description: m.description ?? '',
      price: String(m.price ?? 0),
      category_id: m.category_id != null && Number(m.category_id) > 0 ? String(m.category_id) : '',
      detail_url: m.detail_url ?? '',
      icon_url: m.icon_url ?? '',
      settings_route: m.settings_route ?? '',
      readme_md: m.readme_md ?? '',
      module_git_source_id: m.module_git_source_id != null ? String(m.module_git_source_id) : '',
    });
    setIsFree(Boolean(m.is_free));
    setIsBuiltin(Boolean(m.is_builtin));
    setParentModuleId(m.parent_module_id ? String(m.parent_module_id) : '');
    setRepoUrlInput(m.gitea_repo_url ?? '');
  }, []);

  const loadParentCandidates = useCallback(async (excludeId?: number) => {
    const res = await getMarketplaceModules(true);
    setParentCandidates(
      (res.modules ?? []).filter(
        (m) => !m.parent_module_id && !m.is_core && (!excludeId || m.id !== excludeId),
      ),
    );
  }, []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      if (isCreateMode) {
        const catRes = await getMarketplaceCategories();
        setCategories(catRes.categories ?? []);
        await loadParentCandidates();
        setModule(null);
        return;
      }
      if (!moduleId) return;
      const [modRes, catRes] = await Promise.all([
        getMarketplaceModule(moduleId),
        getMarketplaceCategories(),
      ]);
      setCategories(catRes.categories ?? []);
      if (modRes.module) {
        setModule(modRes.module);
        populateSettings(modRes.module);
        await loadParentCandidates(modRes.module.id);
        if (modRes.sync_warning) setError(modRes.sync_warning);
      } else {
        setError(t('loadError'));
        setModule(null);
      }
    } catch (err) {
      applyAxiosError(err, t('loadError'));
      setModule(null);
    } finally {
      setLoading(false);
    }
  }, [
    applyAxiosError,
    isCreateMode,
    loadParentCandidates,
    moduleId,
    populateSettings,
    setError,
    t,
  ]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleCreateRepo = async () => {
    if (!moduleId || !repoUrlInput.trim()) {
      setError(t('saveError'));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const res = await createMarketplaceModuleRepo(moduleId, { repo_url: repoUrlInput.trim() });
      setModule(res.module);
      populateSettings(res.module);
      setSuccess(t('repoReady'));
    } catch (err) {
      applyAxiosError(err, t('saveError'));
    } finally {
      setBusy(false);
    }
  };

  const handleSyncRepo = async () => {
    if (!moduleId) return;
    setBusy(true);
    setError(null);
    try {
      const res = await syncMarketplaceModuleRepo(moduleId);
      setModule(res.module);
      populateSettings(res.module);
      setSuccess(t('repoSynced'));
    } catch (err) {
      applyAxiosError(err, t('saveError'));
    } finally {
      setBusy(false);
    }
  };

  const handlePullReadme = async () => {
    if (!moduleId) return;
    setBusy(true);
    setError(null);
    try {
      const res = await syncMarketplaceModuleReadme(moduleId);
      setModule(res.module);
      populateSettings(res.module);
      setSuccess(t('readmeSynced'));
    } catch (err) {
      applyAxiosError(err, t('saveError'));
    } finally {
      setBusy(false);
    }
  };

  const handleSaveSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const distribution = isBuiltin ? 'bundled' : useGitea || isGitea ? 'git' : 'bundled';
      const payload: Record<string, unknown> = {
        name: settingsForm.name.trim() || createSlug.trim(),
        description: settingsForm.description,
        price: isFree ? 0 : Number(settingsForm.price) || 0,
        category_id: settingsForm.category_id ? Number(settingsForm.category_id) : null,
        status: module?.status === 'inactive' ? 'inactive' : 'active',
        distribution,
        requires_license: distribution === 'git',
      };
      if (settingsForm.module_git_source_id.trim()) {
        payload.module_git_source_id = Number(settingsForm.module_git_source_id);
      }

      if (isCreateMode) {
        const slug = createSlug.trim();
        if (!slug) {
          setError(t('slug'));
          setBusy(false);
          return;
        }
        payload.slug = slug;
        const res = await saveMarketplaceModule(payload);
        if (res.id) {
          router.push(dashboardHref(locale, `admin/marketplace/modules/${res.id}`));
          return;
        }
      } else {
        if (!module) {
          setBusy(false);
          return;
        }
        payload.id = module.id;
        payload.slug = module.slug;
        const res = await saveMarketplaceModule(payload);
        setSuccess(t('saved'));
        setSaveWarnings(res.warnings ?? null);
        void load();
      }
    } catch (err) {
      setSaveWarnings(null);
      applyAxiosError(err, t('saveError'));
    } finally {
      setBusy(false);
    }
  };

  const handleAddRelease = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!moduleId) return;
    setBusy(true);
    setError(null);
    try {
      await saveMarketplaceRelease(moduleId, {
        version: releaseForm.version.trim(),
        tag_name: releaseForm.tag_name.trim() || `v${releaseForm.version.trim()}`,
        changelog: releaseForm.changelog,
      });
      setSuccess(t('api.releaseSaved'));
      setReleaseForm({ version: '', tag_name: '', changelog: '' });
      void load();
    } catch (err) {
      applyAxiosError(err, t('saveError'));
    } finally {
      setBusy(false);
    }
  };

  const handlePublish = async (release: MarketplaceRelease) => {
    setBusy(true);
    setError(null);
    try {
      await publishMarketplaceRelease(release.id);
      setSuccess(t('releasePublished'));
      void load();
    } catch (err) {
      applyAxiosError(err, t('saveError'));
    } finally {
      setBusy(false);
    }
  };

  const confirmDeleteRelease = async () => {
    if (deleteReleaseId == null) return;
    setDeletingRelease(true);
    try {
      await deleteMarketplaceRelease(deleteReleaseId);
      setDeleteReleaseId(null);
      setSuccess(t('api.releaseDeleted'));
      void load();
    } catch (err) {
      applyAxiosError(err, t('deleteError'));
    } finally {
      setDeletingRelease(false);
    }
  };

  const repoUrl = module?.gitea_html_url || module?.gitea_repo_url || '';

  if (loading) {
    return (
      <CrmPageLayout
        title={isCreateMode ? t('createProductTitle') : t('moduleDetailTitle')}
        {...layoutProps}
      >
        <div className="bg-muted/40 h-64 animate-pulse rounded-xl" />
      </CrmPageLayout>
    );
  }

  if (!isCreateMode && !module) {
    return (
      <CrmPageLayout
        title={t('moduleDetailTitle')}
        error={error ?? t('loadError')}
        onDismissError={layoutProps.onDismissError}
      >
        <div />
      </CrmPageLayout>
    );
  }

  const releases = module?.releases ?? [];

  return (
    <CrmPageLayout
      title={isCreateMode ? t('createProductTitle') : (module?.name ?? '')}
      description={isCreateMode ? t('createProductDesc') : undefined}
      {...layoutProps}
    >
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link href={dashboardHref(locale, 'admin/marketplace/products')}>
            <BackIcon className="me-2 h-4 w-4" />
            {t('productsTitle')}
          </Link>
        </Button>
        {!isCreateMode && module ? (
          <p className="text-muted-foreground text-sm">
            {module.slug} · v{module.version} · {module.package_source ?? module.distribution ?? 'local'}
          </p>
        ) : null}
      </div>

      {!isCreateMode && module ? (
        <Card>
          <CardHeader>
            <CardTitle>{t('giteaRepo')}</CardTitle>
            <CardDescription>{t('repoToolbarDesc')}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              {repoUrl ? (
                <Button variant="outline" size="sm" asChild>
                  <a href={repoUrl} target="_blank" rel="noreferrer">
                    <ExternalLink className="me-2 h-4 w-4" />
                    {t('openGitea')}
                  </a>
                </Button>
              ) : null}
              {repoLinked ? (
                <>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={busy}
                    onClick={() => void handleSyncRepo()}
                  >
                    <RefreshCw className="me-2 h-4 w-4" />
                    {t('syncRepo')}
                  </Button>
                  {module.detail_url ? (
                    <Button variant="outline" size="sm" asChild>
                      <a href={module.detail_url} target="_blank" rel="noreferrer">
                        <Link2 className="me-2 h-4 w-4" />
                        {t('detailUrl')}
                      </a>
                    </Button>
                  ) : null}
                  <Button variant="outline" size="sm" asChild>
                    <a href="#releases">
                      <Tag className="me-2 h-4 w-4" />
                      {t('releasesTitle')}
                    </a>
                  </Button>
                </>
              ) : (
                <div className="flex w-full flex-wrap items-end gap-2">
                  <div className="min-w-[16rem] flex-1 space-y-1">
                    <Label>Repo URL</Label>
                    <Input
                      value={repoUrlInput}
                      onChange={(e) => setRepoUrlInput(e.target.value)}
                      placeholder="https://…"
                      dir="ltr"
                    />
                  </div>
                  <Button type="button" size="sm" disabled={busy} onClick={() => void handleCreateRepo()}>
                    <GitBranch className="me-2 h-4 w-4" />
                    {t('createGiteaRepo')}
                  </Button>
                </div>
              )}
            </div>
            {repoUrl ? (
              <p className="text-muted-foreground break-all text-sm">{repoUrl}</p>
            ) : (
              <span className="text-muted-foreground text-sm">—</span>
            )}
            {repoLinked && module.gitea_default_branch ? (
              <p className="text-muted-foreground text-xs">
                {t('defaultBranch')}: {module.gitea_default_branch}
              </p>
            ) : null}
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle>{t('moduleSettings')}</CardTitle>
        </CardHeader>
        <CardContent>
          {!isCreateMode && saveWarnings ? (
            <div className="mb-4 space-y-1 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm">
              <p className="font-medium">{t('giteaSaveWarningTitle')}</p>
              {Object.entries(saveWarnings).map(([key, msg]) => (
                <p key={key} className="text-muted-foreground">
                  {key}: {msg}
                </p>
              ))}
            </div>
          ) : null}
          <form onSubmit={handleSaveSettings} className="grid gap-4 md:grid-cols-2">
            {isCreateMode ? (
              <>
                <div className="space-y-2">
                  <Label>{t('slug')}</Label>
                  <Input
                    value={createSlug}
                    onChange={(e) => setCreateSlug(e.target.value)}
                    placeholder="my-module"
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('slugVersion')}</Label>
                  <Input
                    value={createVersion}
                    onChange={(e) => setCreateVersion(e.target.value)}
                    placeholder="1.0.0"
                  />
                </div>
              </>
            ) : null}
            <div className="space-y-2">
              <Label>{t('name')}</Label>
              <Input
                value={settingsForm.name}
                onChange={(e) => setSettingsForm((p) => ({ ...p, name: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t('price')}</Label>
              <Input
                type="number"
                value={settingsForm.price}
                onChange={(e) => setSettingsForm((p) => ({ ...p, price: e.target.value }))}
                disabled={isFree}
              />
            </div>
            <div className="space-y-2 md:col-span-2">
              <Label>{t('description')}</Label>
              <Textarea
                value={settingsForm.description}
                onChange={(e) => setSettingsForm((p) => ({ ...p, description: e.target.value }))}
                rows={3}
              />
            </div>
            <div className="space-y-2">
              <Label>{t('category')}</Label>
              <Select
                value={categorySelectValue}
                onValueChange={(v) =>
                  setSettingsForm((p) => ({ ...p, category_id: v === '__none__' ? '' : v }))
                }
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('noCategory')}>
                    {categorySelectValue === '__none__'
                      ? t('noCategory')
                      : categoryLabel || t('noCategory')}
                  </SelectValue>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">{t('noCategory')}</SelectItem>
                  {orphanCategory ? (
                    <SelectItem value={String(orphanCategory.id)}>{orphanCategory.name}</SelectItem>
                  ) : null}
                  {categories.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t('detailUrl')}</Label>
              <Input
                value={settingsForm.detail_url}
                onChange={(e) => setSettingsForm((p) => ({ ...p, detail_url: e.target.value }))}
              />
            </div>
            <div className="md:col-span-2">
              <ModuleIconField
                iconUrl={settingsForm.icon_url}
                resetKey={isCreateMode ? 'create' : module?.id}
                onChange={(v) => setSettingsForm((p) => ({ ...p, icon_url: v }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t('settingsRoute')}</Label>
              <Input
                value={settingsForm.settings_route}
                onChange={(e) => setSettingsForm((p) => ({ ...p, settings_route: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>module_git_source_id</Label>
              <Input
                value={settingsForm.module_git_source_id}
                onChange={(e) =>
                  setSettingsForm((p) => ({ ...p, module_git_source_id: e.target.value }))
                }
                dir="ltr"
              />
            </div>
            <div className="space-y-2">
              <Label>{t('parentProduct')}</Label>
              <Select
                value={parentModuleId || '__none__'}
                onValueChange={(v) => setParentModuleId(v === '__none__' ? '' : v)}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('noParentProduct')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">{t('noParentProduct')}</SelectItem>
                  {parentCandidates.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.name} ({p.slug})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-center gap-2">
              <Checkbox checked={isFree} onCheckedChange={(v) => setIsFree(Boolean(v))} id="md-free" />
              <Label htmlFor="md-free">{t('isFree')}</Label>
            </div>
            <div className="flex items-start gap-2 md:col-span-2">
              <Checkbox
                checked={isBuiltin}
                onCheckedChange={(v) => setIsBuiltin(Boolean(v))}
                id="md-builtin"
              />
              <div className="space-y-1">
                <Label htmlFor="md-builtin">{t('isBuiltin')}</Label>
                <p className="text-muted-foreground text-xs">{t('isBuiltinHelp')}</p>
              </div>
            </div>
            {isCreateMode ? (
              <div className="flex items-center gap-2 md:col-span-2">
                <Checkbox
                  checked={useGitea}
                  onCheckedChange={(v) => setUseGitea(Boolean(v))}
                  id="md-gitea"
                />
                <Label htmlFor="md-gitea">{t('useGitea')}</Label>
              </div>
            ) : null}
            <Button type="submit" disabled={busy} className="w-fit md:col-span-2">
              {tNav('common.save')}
            </Button>
          </form>
        </CardContent>
      </Card>

      {!isCreateMode ? (
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-4">
            <div>
              <CardTitle>{t('readmeTitle')}</CardTitle>
              <CardDescription>{t('readmeDesc')}</CardDescription>
            </div>
            {repoLinked ? (
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={busy}
                onClick={() => void handlePullReadme()}
              >
                <RefreshCw className="me-2 h-4 w-4" />
                {t('pullReadme')}
              </Button>
            ) : null}
          </CardHeader>
          <CardContent>
            <Textarea
              value={settingsForm.readme_md}
              onChange={(e) => setSettingsForm((p) => ({ ...p, readme_md: e.target.value }))}
              rows={10}
              className="font-mono text-sm"
              dir="ltr"
            />
          </CardContent>
        </Card>
      ) : null}

      {!isCreateMode && module ? (
        <Card id="releases">
          <CardHeader>
            <CardTitle>{t('releasesTitle')}</CardTitle>
            {isGitea ? <CardDescription>{t('giteaReleaseHint')}</CardDescription> : null}
          </CardHeader>
          <CardContent className="space-y-6">
            <form onSubmit={handleAddRelease} className="grid gap-3 md:grid-cols-2">
              <div className="space-y-2">
                <Label>{t('version')}</Label>
                <Input
                  value={releaseForm.version}
                  onChange={(e) => setReleaseForm((p) => ({ ...p, version: e.target.value }))}
                  placeholder="1.2.0"
                />
              </div>
              <div className="space-y-2">
                <Label>{t('tagName')}</Label>
                <Input
                  value={releaseForm.tag_name}
                  onChange={(e) => setReleaseForm((p) => ({ ...p, tag_name: e.target.value }))}
                  placeholder="v1.2.0"
                />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label>{t('changelog')}</Label>
                <Textarea
                  value={releaseForm.changelog}
                  onChange={(e) => setReleaseForm((p) => ({ ...p, changelog: e.target.value }))}
                  rows={6}
                />
              </div>
              <Button type="submit" disabled={busy}>
                {t('addRelease')}
              </Button>
            </form>

            <div className="space-y-2">
              {releases.map((r) => (
                <div
                  key={r.id}
                  className="border-border flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
                >
                  <div>
                    <div className="font-medium">
                      {r.version} {r.tag_name ? `(${r.tag_name})` : ''}
                    </div>
                    <div className="text-muted-foreground text-xs">
                      {t('releaseStatus')}: {r.status}
                      {r.published_at ? ` · ${formatDateTime(r.published_at)}` : ''}
                    </div>
                  </div>
                  <div className="flex gap-2">
                    {r.status === 'draft' ? (
                      <Button
                        type="button"
                        size="sm"
                        disabled={busy}
                        onClick={() => void handlePublish(r)}
                      >
                        {t('publishRelease')}
                      </Button>
                    ) : null}
                    {repoUrl && r.tag_name ? (
                      <Button variant="outline" size="sm" asChild>
                        <a
                          href={`${repoUrl}/releases/tag/${encodeURIComponent(r.tag_name)}`}
                          target="_blank"
                          rel="noreferrer"
                        >
                          <ExternalLink className="h-4 w-4" />
                        </a>
                      </Button>
                    ) : null}
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      className="text-destructive"
                      disabled={busy}
                      onClick={() => setDeleteReleaseId(r.id)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              ))}
              {releases.length === 0 ? <p className="text-muted-foreground text-sm">—</p> : null}
            </div>
          </CardContent>
        </Card>
      ) : null}

      <PmConfirmDialog
        open={deleteReleaseId != null}
        onOpenChange={(open) => !open && setDeleteReleaseId(null)}
        title={tNav('common.delete')}
        description={t('confirm.deleteRelease')}
        confirmLabel={tNav('common.delete')}
        cancelLabel={tNav('common.cancel')}
        onConfirm={confirmDeleteRelease}
        loading={deletingRelease}
        isRtl={isRtl}
      />
    </CrmPageLayout>
  );
}
