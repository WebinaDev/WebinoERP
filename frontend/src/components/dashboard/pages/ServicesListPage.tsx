'use client';

import { useTranslations } from 'next-intl';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage, unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ResourceListCard } from '@/components/dashboard/ResourceListCard';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';

type Row = Record<string, unknown>;

export function ServicesListPage() {
  const t = useTranslations();

  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [subRows, setSubRows] = useState<Row[]>([]);
  const [subMessage, setSubMessage] = useState<string | null>(null);
  const [subLoading, setSubLoading] = useState(true);

  const [tplOpen, setTplOpen] = useState(false);
  const [tplProduct, setTplProduct] = useState<Row | null>(null);
  const [tplJson, setTplJson] = useState('[]');
  const [tplErr, setTplErr] = useState<string | null>(null);

  const [convertOpen, setConvertOpen] = useState(false);
  const [convertId, setConvertId] = useState<number | null>(null);
  const [convertTitle, setConvertTitle] = useState('');
  const [convertErr, setConvertErr] = useState<string | null>(null);

  const loadProducts = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/projects/products', { params: { per_page: 100 } });
      const raw = unwrapData<unknown>(res);
      setRows(normalizeListPayload(raw));
    } catch (e) {
      setError(getAxiosMessage(e));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const loadSubs = useCallback(async () => {
    setSubLoading(true);
    try {
      const res = await apiClient.get('/v1/projects/subscriptions');
      const envelope = res.data as { data?: unknown; message?: string };
      const raw = envelope.data;
      setSubMessage(typeof envelope.message === 'string' ? envelope.message : null);
      if (Array.isArray(raw)) {
        setSubRows(raw as Row[]);
      } else {
        setSubRows(normalizeListPayload(raw));
      }
    } catch (e) {
      setSubMessage(getAxiosMessage(e));
      setSubRows([]);
    } finally {
      setSubLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadProducts();
    void loadSubs();
  }, [loadProducts, loadSubs]);

  function openTemplate(r: Row) {
    setTplProduct(r);
    const tt = r.task_template;
    try {
      setTplJson(JSON.stringify(tt ?? [], null, 2));
    } catch {
      setTplJson('[]');
    }
    setTplErr(null);
    setTplOpen(true);
  }

  async function saveTemplate() {
    if (!tplProduct?.id) {
      return;
    }
    setTplErr(null);
    let parsed: unknown;
    try {
      parsed = JSON.parse(tplJson || '[]');
    } catch {
      setTplErr(t('common.invalidJsonDot'));
      return;
    }
    try {
      await apiClient.put(`/v1/projects/products/${String(tplProduct.id)}/task-template`, {
        task_template: parsed,
      });
      setTplOpen(false);
      await loadProducts();
    } catch (e) {
      setTplErr(getAxiosMessage(e));
    }
  }

  async function convertSubscription() {
    if (!convertId) {
      return;
    }
    setConvertErr(null);
    try {
      await apiClient.post(`/v1/projects/subscriptions/${convertId}/convert-contract`, {
        title: convertTitle || undefined,
      });
      setConvertOpen(false);
      setConvertId(null);
    } catch (e) {
      setConvertErr(getAxiosMessage(e));
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('auto.ServicesListPage.s_b39526b6')}</CardTitle>
      </CardHeader>
      <CardContent>
        <Tabs defaultValue="products">
          <TabsList>
            <TabsTrigger value="products">{t('auto.ServicesListPage.s_83cb78bb')}</TabsTrigger>
            <TabsTrigger value="subscriptions">{t('auto.ServicesListPage.s_4cdca6e0')}</TabsTrigger>
          </TabsList>
          <TabsContent value="products" className="pt-4 space-y-4">
            <ResourceListCard
              title=""
              description=""
              loading={loading}
              error={error}
              rows={rows}
              columns={[
                { header: t('auto.ServicesListPage.s_acc84041'), cell: (r) => String(r.id ?? '') },
                { header: t('auto.ServicesListPage.s_45dd06ba'), cell: (r) => String(r.name ?? '') },
                { header: 'SKU', cell: (r) => String(r.sku ?? '—') },
                {
                  header: t('auto.ServicesListPage.s_37372d67'),
                  cell: (r) => (
                    <Button type="button" variant="outline" size="sm" onClick={() => openTemplate(r)}>
                      {t('auto.ServicesListPage.s_82a2f9d8')}
                    </Button>
                  ),
                },
              ]}
            />
          </TabsContent>
          <TabsContent value="subscriptions" className="space-y-3 pt-4">
            {subMessage ? (
              <p className="text-sm text-muted-foreground" dir="ltr">
                {subMessage}
              </p>
            ) : null}
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b bg-muted/40">
                    <th className="px-2 py-2 text-start">#</th>
                    <th className="px-2 py-2 text-start">{t('auto.ServicesListPage.s_1a9bdb20')}</th>
                    <th className="px-2 py-2 text-start">{t('auto.ServicesListPage.s_f5668e5b')}</th>
                    <th className="px-2 py-2 text-start">{t('auto.ServicesListPage.s_55518965')}</th>
                    <th className="px-2 py-2 text-start"> </th>
                  </tr>
                </thead>
                <tbody>
                  {subLoading ? (
                    <tr>
                      <td colSpan={5} className="py-6 text-center text-muted-foreground">
                        {t('auto.ServicesListPage.s_fbac73dc')}
                      </td>
                    </tr>
                  ) : subRows.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="py-6 text-center text-muted-foreground">
                        {t('auto.ServicesListPage.s_ea3cb77d')}
                      </td>
                    </tr>
                  ) : (
                    subRows.map((r, idx) => (
                      <tr key={String(r.id ?? idx)} className="border-b">
                        <td className="px-2 py-2">{String(r.id ?? idx)}</td>
                        <td className="px-2 py-2">{String(r.name ?? r.title ?? '—')}</td>
                        <td className="px-2 py-2">{String(r.amount ?? r.total ?? '—')}</td>
                        <td className="px-2 py-2">
                          <Badge variant="outline">{String(r.status ?? '—')}</Badge>
                        </td>
                        <td className="px-2 py-2">
                          <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            onClick={() => {
                              setConvertId(Number(r.id ?? idx));
                              setConvertTitle(String(r.title ?? t('common.subscriptionN', { n: idx })));
                              setConvertOpen(true);
                            }}>
                          
                            {t('auto.ServicesListPage.s_a317ac98')}
                          </Button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </TabsContent>
        </Tabs>
      </CardContent>

      <Dialog open={tplOpen} onOpenChange={setTplOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{t('auto.ServicesListPage.s_738dd09e')}</DialogTitle>
          </DialogHeader>
          <p className="text-xs text-muted-foreground">{t('common.productLabel', { name: String(tplProduct?.name ?? '') })}</p>
          <Textarea
            dir="ltr"
            className="min-h-[200px] font-mono text-xs"
            value={tplJson}
            onChange={(e) => setTplJson(e.target.value)}
          />
          {tplErr ? <p className="text-sm text-destructive">{tplErr}</p> : null}
          <DialogFooter>
            <Button type="button" onClick={() => void saveTemplate()}>
              {t('auto.ServicesListPage.s_08545fb6')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={convertOpen} onOpenChange={setConvertOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('auto.ServicesListPage.s_bf9c16a3')}</DialogTitle>
          </DialogHeader>
          <Input placeholder={t('auto.ServicesListPage.s_7bae8ca7')} value={convertTitle} onChange={(e) => setConvertTitle(e.target.value)} />
          {convertErr ? <p className="text-sm text-destructive">{convertErr}</p> : null}
          <DialogFooter>
            <Button type="button" onClick={() => void convertSubscription()}>
              {t('auto.ServicesListPage.s_b19a8426')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
