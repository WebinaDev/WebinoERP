'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Plus, Trash2 } from 'lucide-react';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';

type Item = { id: number; label: string; href: string; parent_id?: number | null; published?: boolean };
type Menu = { id: number; name: string; location: string; published?: boolean; items?: Item[] };

export function MarketingMenusPage() {
  const t = useTranslations();
  const { layoutProps, applyAxiosError, setSuccess } = useCrmFeedback();
  const [menus, setMenus] = useState<Menu[]>([]);
  const [menuDraft, setMenuDraft] = useState({ location: 'header', name: '', published: true });
  const [itemDraft, setItemDraft] = useState({ menu_id: '', label: '', href: '/', parent_id: '' });

  const load = useCallback(async () => {
    try {
      const res = await apiClient.get('/v1/marketing/menus');
      setMenus(normalizeListPayload(res.data) as Menu[]);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  async function createMenu() {
    try {
      await apiClient.post('/v1/marketing/menus', menuDraft);
      setSuccess(t('common.saved'));
      setMenuDraft({ location: 'footer', name: '', published: true });
      await load();
    } catch (err) {
      applyAxiosError(err);
    }
  }

  async function createItem() {
    try {
      await apiClient.post(`/v1/marketing/menus/${itemDraft.menu_id}/items`, {
        label: itemDraft.label,
        href: itemDraft.href,
        parent_id: itemDraft.parent_id ? Number(itemDraft.parent_id) : null,
        published: true,
      });
      setSuccess(t('common.saved'));
      setItemDraft({ ...itemDraft, label: '', href: '/' });
      await load();
    } catch (err) {
      applyAxiosError(err);
    }
  }

  async function removeItem(id: number) {
    try {
      await apiClient.delete(`/v1/marketing/menu-items/${id}`);
      setSuccess(t('common.deleted'));
      await load();
    } catch (err) {
      applyAxiosError(err);
    }
  }

  return (
    <CrmPageLayout title={t('nav.erp.marketing.menus')} {...layoutProps}>
      <Card>
        <CardContent className="grid gap-3 pt-6 md:grid-cols-4">
          <input className="border-input h-9 rounded-md border px-3" placeholder={t('marketing.location')} value={menuDraft.location} onChange={(e) => setMenuDraft({ ...menuDraft, location: e.target.value })} />
          <input className="border-input h-9 rounded-md border px-3" placeholder={t('marketing.name')} value={menuDraft.name} onChange={(e) => setMenuDraft({ ...menuDraft, name: e.target.value })} />
          <Button onClick={() => void createMenu()}><Plus className="size-4" />{t('common.create')}</Button>
        </CardContent>
      </Card>
      {menus.map((menu) => (
        <Card key={menu.id}>
          <CardContent className="space-y-3 pt-6">
            <h2 className="font-semibold">{menu.name} · {menu.location}</h2>
            <ul className="space-y-1 text-sm">
              {(menu.items ?? []).map((item) => (
                <li key={item.id} className="flex items-center justify-between gap-2">
                  <span>{item.parent_id ? '↳ ' : ''}{item.label} <span className="text-muted-foreground">{item.href}</span></span>
                  <Button size="icon" variant="ghost" onClick={() => void removeItem(item.id)} aria-label={t('common.delete')}><Trash2 className="size-4" /></Button>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      ))}
      <Card>
        <CardContent className="grid gap-3 pt-6 md:grid-cols-4">
          <select className="border-input h-9 rounded-md border px-2" value={itemDraft.menu_id} onChange={(e) => setItemDraft({ ...itemDraft, menu_id: e.target.value })}>
            <option value="">{t('marketing.name')}</option>
            {menus.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
          </select>
          <input className="border-input h-9 rounded-md border px-3" placeholder={t('marketing.title')} value={itemDraft.label} onChange={(e) => setItemDraft({ ...itemDraft, label: e.target.value })} />
          <input className="border-input h-9 rounded-md border px-3" placeholder={t('marketing.path')} value={itemDraft.href} onChange={(e) => setItemDraft({ ...itemDraft, href: e.target.value })} />
          <Button onClick={() => void createItem()} disabled={!itemDraft.menu_id || !itemDraft.label}><Plus className="size-4" />{t('common.add')}</Button>
        </CardContent>
      </Card>
    </CrmPageLayout>
  );
}
