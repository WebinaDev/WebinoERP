'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { Package, Plus } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
  deleteMarketplaceModule,
  getMarketplaceModules,
  saveMarketplaceModule,
  type MarketplaceModule,
} from '@/lib/api/marketplace';
import { dashboardHref } from '@/lib/route-resolver';
import { useLocale } from '@/hooks/use-locale-next';
import { MarketplaceProductCard } from './MarketplaceProductCard';
import { PmConfirmDialog } from './components/PmConfirmDialog';
import { PmEmptyState } from './components/PmEmptyState';

export function ProductsPage() {
  const t = useTranslations('marketplace');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const { isRtl } = useLocale();
  const { layoutProps, setError, applyAxiosError } = useCrmFeedback();
  const [modules, setModules] = useState<MarketplaceModule[]>([]);
  const [loading, setLoading] = useState(true);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [showCore, setShowCore] = useState(false);
  const [statusBusyId, setStatusBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getMarketplaceModules(showCore);
      setModules(res.modules ?? []);
    } catch (err) {
      applyAxiosError(err, t('api.modulesLoadError'));
      setModules([]);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, setError, showCore, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const handleStatusToggle = async (m: MarketplaceModule, active: boolean) => {
    if (m.is_core) return;
    setStatusBusyId(m.id);
    setError(null);
    try {
      await saveMarketplaceModule({
        id: m.id,
        slug: m.slug,
        name: m.name,
        status: active ? 'active' : 'inactive',
        distribution: m.distribution ?? (m.package_source === 'gitea' ? 'git' : 'bundled'),
        price: m.price,
      });
      void load();
    } catch (err) {
      applyAxiosError(err, t('saveError'));
    } finally {
      setStatusBusyId(null);
    }
  };

  const confirmDelete = async () => {
    if (deleteId == null) return;
    setDeleting(true);
    try {
      await deleteMarketplaceModule(deleteId);
      setDeleteId(null);
      void load();
    } catch (err) {
      applyAxiosError(err, t('deleteError'));
    } finally {
      setDeleting(false);
    }
  };

  const sortedModules = modules
    .slice()
    .sort((a, b) => Number(Boolean(b.is_core)) - Number(Boolean(a.is_core)));

  return (
    <CrmPageLayout
      title={t('productsTitle')}
      description={t('productsDesc')}
      actions={
        <Button asChild>
          <Link href={dashboardHref(locale, 'admin/marketplace/modules/new')}>
            <Plus className="me-2 h-4 w-4" />
            {t('addProduct')}
          </Link>
        </Button>
      }
      {...layoutProps}
    >
      <div className="flex items-center gap-2">
        <Checkbox
          checked={showCore}
          onCheckedChange={(v) => setShowCore(Boolean(v))}
          id="mp-show-core"
        />
        <Label htmlFor="mp-show-core">{t('showCoreProducts')}</Label>
      </div>

      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="bg-muted/40 h-64 animate-pulse rounded-xl" />
          ))}
        </div>
      ) : sortedModules.length === 0 ? (
        <PmEmptyState icon={Package} message={t('productsDesc')} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {sortedModules.map((m) => (
            <MarketplaceProductCard
              key={m.id}
              module={m}
              statusBusy={statusBusyId === m.id}
              onStatusToggle={(active) => void handleStatusToggle(m, active)}
              onDelete={() => setDeleteId(m.id)}
            />
          ))}
        </div>
      )}

      <PmConfirmDialog
        open={deleteId != null}
        onOpenChange={(open) => !open && setDeleteId(null)}
        title={tNav('common.delete')}
        description={t('confirm.deleteProduct')}
        confirmLabel={tNav('common.delete')}
        cancelLabel={tNav('common.cancel')}
        onConfirm={confirmDelete}
        loading={deleting}
        isRtl={isRtl}
      />
    </CrmPageLayout>
  );
}
