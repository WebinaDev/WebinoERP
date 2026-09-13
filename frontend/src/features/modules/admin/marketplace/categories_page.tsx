'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { FolderTree, Pencil, Plus, Trash2, X } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  deleteMarketplaceCategory,
  getMarketplaceCategories,
  saveMarketplaceCategory,
  type MarketplaceCategory,
} from '@/lib/api/marketplace';
import { useLocale } from '@/hooks/use-locale-next';
import { PmConfirmDialog } from './components/PmConfirmDialog';
import { PmEmptyState } from './components/PmEmptyState';

const EMPTY_FORM = { slug: '', name: '', sort: '0', status: 'active' };

export function CategoriesPage() {
  const t = useTranslations('marketplace');
  const tNav = useTranslations();
  const { isRtl } = useLocale();
  const { layoutProps, setError, setSuccess, applyAxiosError } = useCrmFeedback();
  const [items, setItems] = useState<MarketplaceCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState(EMPTY_FORM);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getMarketplaceCategories();
      setItems(res.categories ?? []);
    } catch (err) {
      applyAxiosError(err, t('api.categoriesLoadError'));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, setError, t]);

  useEffect(() => {
    void load();
  }, [load]);

  const resetForm = () => {
    setEditingId(null);
    setForm(EMPTY_FORM);
  };

  const startEdit = (item: MarketplaceCategory) => {
    setEditingId(item.id);
    setForm({
      slug: item.slug,
      name: item.name,
      sort: String(item.sort ?? 0),
      status: item.status ?? 'active',
    });
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);
    const name = form.name.trim();
    if (!name) {
      setError(t('categoryNameRequired'));
      return;
    }
    try {
      await saveMarketplaceCategory({
        id: editingId ?? undefined,
        slug: form.slug.trim(),
        name,
        sort: Number(form.sort) || 0,
        status: form.status,
      });
      setSuccess(t('saved'));
      resetForm();
      void load();
    } catch (err) {
      applyAxiosError(err, t('api.categorySaveFailed'));
    }
  };

  const confirmDelete = async () => {
    if (deleteId == null) return;
    setDeleting(true);
    try {
      await deleteMarketplaceCategory(deleteId);
      setDeleteId(null);
      setSuccess(t('api.deleted'));
      if (editingId === deleteId) resetForm();
      void load();
    } catch (err) {
      applyAxiosError(err, t('deleteError'));
    } finally {
      setDeleting(false);
    }
  };

  return (
    <CrmPageLayout title={t('categoriesTitle')} description={t('categoriesDesc')} {...layoutProps}>
      <Card>
        <CardContent className="pt-6">
          <form onSubmit={handleSave} className="grid gap-4 md:grid-cols-4">
            <div className="space-y-2">
              <Label>{t('name')}</Label>
              <Input
                value={form.name}
                onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                required
              />
            </div>
            <div className="space-y-2">
              <Label>{t('slugOptional')}</Label>
              <Input
                value={form.slug}
                readOnly={editingId != null}
                placeholder={t('slugAutoHint')}
                onChange={(e) => setForm((p) => ({ ...p, slug: e.target.value }))}
              />
              <p className="text-muted-foreground text-xs">{t('slugAutoHint')}</p>
            </div>
            <div className="space-y-2">
              <Label>{t('sort')}</Label>
              <Input
                type="number"
                value={form.sort}
                onChange={(e) => setForm((p) => ({ ...p, sort: e.target.value }))}
              />
            </div>
            <div className="flex items-end gap-2">
              <Button type="submit">
                {editingId != null ? (
                  tNav('common.save')
                ) : (
                  <>
                    <Plus className="me-2 h-4 w-4" />
                    {t('addCategory')}
                  </>
                )}
              </Button>
              {editingId != null ? (
                <Button type="button" variant="outline" onClick={resetForm}>
                  <X className="me-2 h-4 w-4" />
                  {tNav('common.cancel')}
                </Button>
              ) : null}
            </div>
          </form>
        </CardContent>
      </Card>

      {loading ? (
        <div className="space-y-3">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="bg-muted/40 h-16 animate-pulse rounded-xl" />
          ))}
        </div>
      ) : items.length === 0 ? (
        <PmEmptyState icon={FolderTree} message={t('categoriesDesc')} />
      ) : (
        <div className="grid gap-3">
          {items.map((item) => (
            <Card key={item.id}>
              <CardContent className="flex items-center justify-between py-4">
                <div>
                  <div className="font-medium">{item.name}</div>
                  <div className="text-muted-foreground text-sm">
                    {item.slug} · {t('sort')}: {item.sort ?? 0}
                  </div>
                </div>
                <div className="flex gap-1">
                  <Button type="button" variant="ghost" size="icon" onClick={() => startEdit(item)}>
                    <Pencil className="h-4 w-4" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="text-destructive"
                    onClick={() => setDeleteId(item.id)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <PmConfirmDialog
        open={deleteId != null}
        onOpenChange={(open) => !open && setDeleteId(null)}
        title={tNav('common.delete')}
        description={t('confirm.deleteCategory')}
        confirmLabel={tNav('common.delete')}
        cancelLabel={tNav('common.cancel')}
        onConfirm={confirmDelete}
        loading={deleting}
        isRtl={isRtl}
      />
    </CrmPageLayout>
  );
}
