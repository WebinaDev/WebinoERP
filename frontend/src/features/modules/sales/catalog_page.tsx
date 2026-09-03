'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useParams, usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import { FileText, Loader2, Pencil, Plus, RefreshCw, ShoppingBag } from 'lucide-react';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { dashboardHref } from '@/lib/route-resolver';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { PmEmptyState } from '@/features/shared/pm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

type CatalogItem = Record<string, unknown> & {
  id: number;
  name?: string;
  sku?: string;
  price?: number | string;
  status?: string;
  type?: string;
  task_template_id?: number | null;
  service_task_type?: string;
  task_template_title?: string | null;
  meta?: { converted_contract_id?: number };
};

type TaskTemplate = { id: number; title?: string; name?: string; is_recurring?: boolean; recurring_type?: string };

export function CatalogPage() {
  const t = useTranslations('sales.catalog');
  const tNav = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();

  const tabFromUrl = searchParams.get('tab');
  const [activeTab, setActiveTab] = useState(
    tabFromUrl === 'products' || tabFromUrl === 'subscriptions' ? tabFromUrl : 'subscriptions',
  );
  const [subscriptions, setSubscriptions] = useState<CatalogItem[]>([]);
  const [products, setProducts] = useState<CatalogItem[]>([]);
  const [taskTemplates, setTaskTemplates] = useState<TaskTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [convertingId, setConvertingId] = useState<number | null>(null);
  const [addOpen, setAddOpen] = useState(false);
  const [addForm, setAddForm] = useState({ name: '', sku: '', price: '0', type: 'product' });
  const [editProduct, setEditProduct] = useState<CatalogItem | null>(null);
  const [editForm, setEditForm] = useState({ task_template_id: null as number | null, service_task_type: 'onetime' });
  const [submitting, setSubmitting] = useState(false);

  const serviceTypes = useMemo(
    () => [
      { value: 'onetime', label: t('typeOnetime') },
      { value: 'daily', label: t('typeDaily') },
      { value: 'weekly', label: t('typeWeekly') },
      { value: 'monthly', label: t('typeMonthly') },
    ],
    [t],
  );

  const loadSubscriptions = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiClient.get('/v1/sales/services/subscriptions');
      setSubscriptions(normalizeListPayload(res.data) as CatalogItem[]);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  const loadProducts = useCallback(async () => {
    setLoading(true);
    try {
      const [prodRes, tplRes] = await Promise.all([
        apiClient.get('/v1/sales/services/products'),
        apiClient.get('/v1/sales/services/task-templates'),
      ]);
      setProducts(normalizeListPayload(prodRes.data) as CatalogItem[]);
      setTaskTemplates(normalizeListPayload(tplRes.data) as TaskTemplate[]);
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    if (tabFromUrl === 'products' || tabFromUrl === 'subscriptions') setActiveTab(tabFromUrl);
  }, [tabFromUrl]);

  useEffect(() => {
    if (activeTab === 'subscriptions') void loadSubscriptions();
    else void loadProducts();
  }, [activeTab, loadSubscriptions, loadProducts]);

  const handleTabChange = (tab: string) => {
    setActiveTab(tab);
    const sp = new URLSearchParams(searchParams.toString());
    if (tab === 'subscriptions') sp.delete('tab');
    else sp.set('tab', tab);
    const q = sp.toString();
    router.replace(q ? `${pathname}?${q}` : pathname);
  };

  const handleConvert = async (item: CatalogItem) => {
    const existing = item.meta?.converted_contract_id;
    if (existing) {
      router.push(dashboardHref(locale, `docs/contracts?contract_id=${existing}&action=new`));
      return;
    }
    setConvertingId(item.id);
    try {
      const res = await apiClient.post(`/v1/sales/services/subscriptions/${item.id}/convert-contract`);
      const data = unwrapData(res) as { contract_id?: number };
      setSuccess(t('converted'));
      if (data.contract_id) {
        router.push(dashboardHref(locale, `docs/contracts?contract_id=${data.contract_id}&action=new`));
      } else {
        void loadSubscriptions();
      }
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setConvertingId(null);
    }
  };

  const saveItem = async () => {
    setSubmitting(true);
    try {
      await apiClient.post('/v1/sales/catalog', {
        name: addForm.name,
        sku: addForm.sku || null,
        price: Number(addForm.price) || 0,
        type: addForm.type,
        status: 'active',
      });
      setAddOpen(false);
      setAddForm({ name: '', sku: '', price: '0', type: activeTab === 'subscriptions' ? 'subscription' : 'product' });
      setSuccess(tNav('common.saved'));
      if (activeTab === 'subscriptions') void loadSubscriptions();
      else void loadProducts();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  const openEditProduct = (p: CatalogItem) => {
    setEditProduct(p);
    setEditForm({
      task_template_id: p.task_template_id ?? null,
      service_task_type: p.service_task_type || 'onetime',
    });
  };

  const saveTaskTemplate = async () => {
    if (!editProduct) return;
    setSubmitting(true);
    try {
      await apiClient.patch(`/v1/sales/services/products/${editProduct.id}/task-template`, editForm);
      setEditProduct(null);
      setSuccess(tNav('common.saved'));
      void loadProducts();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <CrmPageLayout
      title={tNav('nav.erp.sales.catalog')}
      actions={
        <Button
          onClick={() => {
            setAddForm({
              name: '',
              sku: '',
              price: '0',
              type: activeTab === 'subscriptions' ? 'subscription' : 'product',
            });
            setAddOpen(true);
          }}
        >
          <Plus className="size-4 me-2" />
          {tNav('common.add')}
        </Button>
      }
      {...layoutProps}
    >
      <Card>
        <CardContent className="pt-6">
          <Tabs value={activeTab} onValueChange={handleTabChange}>
            <TabsList>
              <TabsTrigger value="subscriptions" className="gap-2">
                <RefreshCw className="size-4" />
                {t('tabSubscriptions')}
              </TabsTrigger>
              <TabsTrigger value="products" className="gap-2">
                <ShoppingBag className="size-4" />
                {t('tabProducts')}
              </TabsTrigger>
            </TabsList>

            <TabsContent value="subscriptions" className="mt-4">
              {loading ? (
                <div className="flex justify-center py-10">
                  <Loader2 className="size-6 animate-spin text-muted-foreground" />
                </div>
              ) : subscriptions.length === 0 ? (
                <PmEmptyState title={t('emptySubscriptions')} />
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('name')}</TableHead>
                      <TableHead>SKU</TableHead>
                      <TableHead>{t('price')}</TableHead>
                      <TableHead>{tNav('common.status')}</TableHead>
                      <TableHead className="text-end">{tNav('common.actions')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {subscriptions.map((s) => (
                      <TableRow key={s.id}>
                        <TableCell>{String(s.name ?? '')}</TableCell>
                        <TableCell>{String(s.sku ?? '—')}</TableCell>
                        <TableCell dir="ltr">{String(s.price ?? '')}</TableCell>
                        <TableCell>
                          <Badge variant="secondary">{String(s.status ?? '')}</Badge>
                        </TableCell>
                        <TableCell className="text-end">
                          {s.meta?.converted_contract_id ? (
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() =>
                                router.push(
                                  dashboardHref(
                                    locale,
                                    `docs/contracts?contract_id=${s.meta!.converted_contract_id}&action=new`,
                                  ),
                                )
                              }
                            >
                              <FileText className="size-4 me-1" />
                              {t('viewContract')}
                            </Button>
                          ) : (
                            <Button
                              size="sm"
                              disabled={convertingId === s.id}
                              onClick={() => void handleConvert(s)}
                            >
                              {convertingId === s.id ? (
                                <Loader2 className="size-4 animate-spin me-1" />
                              ) : null}
                              {t('convertToContract')}
                            </Button>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </TabsContent>

            <TabsContent value="products" className="mt-4">
              {loading ? (
                <div className="flex justify-center py-10">
                  <Loader2 className="size-6 animate-spin text-muted-foreground" />
                </div>
              ) : products.length === 0 ? (
                <PmEmptyState title={t('emptyProducts')} />
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('name')}</TableHead>
                      <TableHead>{t('price')}</TableHead>
                      <TableHead>{t('taskTemplate')}</TableHead>
                      <TableHead>{t('serviceType')}</TableHead>
                      <TableHead className="text-end">{tNav('common.actions')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {products.map((p) => (
                      <TableRow key={p.id}>
                        <TableCell>{String(p.name ?? '')}</TableCell>
                        <TableCell dir="ltr">{String(p.price ?? '')}</TableCell>
                        <TableCell>{String(p.task_template_title ?? '—')}</TableCell>
                        <TableCell>
                          {serviceTypes.find((s) => s.value === p.service_task_type)?.label ??
                            p.service_task_type ??
                            '—'}
                        </TableCell>
                        <TableCell className="text-end">
                          <Button size="sm" variant="outline" onClick={() => openEditProduct(p)}>
                            <Pencil className="size-4 me-1" />
                            {tNav('common.edit')}
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>

      <Dialog open={addOpen} onOpenChange={setAddOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('newItem')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="space-y-2">
              <Label>{t('name')}</Label>
              <Input value={addForm.name} onChange={(e) => setAddForm({ ...addForm, name: e.target.value })} />
            </div>
            <div className="space-y-2">
              <Label>SKU</Label>
              <Input value={addForm.sku} onChange={(e) => setAddForm({ ...addForm, sku: e.target.value })} />
            </div>
            <div className="space-y-2">
              <Label>{t('price')}</Label>
              <Input
                dir="ltr"
                type="number"
                value={addForm.price}
                onChange={(e) => setAddForm({ ...addForm, price: e.target.value })}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAddOpen(false)}>
              {tNav('common.cancel')}
            </Button>
            <Button onClick={() => void saveItem()} disabled={submitting || !addForm.name.trim()}>
              {tNav('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={editProduct !== null} onOpenChange={(o) => !o && setEditProduct(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('linkTaskTemplate')}</DialogTitle>
          </DialogHeader>
          {editProduct ? (
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">{String(editProduct.name)}</p>
              <div className="space-y-2">
                <Label>{t('taskTemplate')}</Label>
                <Select
                  value={editForm.task_template_id ? String(editForm.task_template_id) : '0'}
                  onValueChange={(v) =>
                    setEditForm((f) => ({ ...f, task_template_id: v === '0' ? null : Number(v) }))
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="0">{t('none')}</SelectItem>
                    {taskTemplates.map((tpl) => (
                      <SelectItem key={tpl.id} value={String(tpl.id)}>
                        {tpl.title || tpl.name || tpl.id}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t('serviceType')}</Label>
                <Select
                  value={editForm.service_task_type}
                  onValueChange={(v) => setEditForm((f) => ({ ...f, service_task_type: v }))}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {serviceTypes.map((s) => (
                      <SelectItem key={s.value} value={s.value}>
                        {s.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          ) : null}
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditProduct(null)}>
              {tNav('common.cancel')}
            </Button>
            <Button onClick={() => void saveTaskTemplate()} disabled={submitting}>
              {tNav('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CrmPageLayout>
  );
}
