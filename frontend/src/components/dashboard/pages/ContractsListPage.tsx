'use client';

import { useTranslations } from 'next-intl';

import { useCallback, useEffect, useState } from 'react';
import apiClient from '@/lib/api-client';
import { getAxiosMessage } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
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
import { Pagination } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { usePathname } from 'next/navigation';

type Row = Record<string, unknown>;
type Meta = { current_page?: number; last_page?: number; total?: number };

export function ContractsListPage() {
  const t = useTranslations();

  const pathname = usePathname();
  const locale = pathname.split('/')[1] || 'fa';
  const [rows, setRows] = useState<Row[]>([]);
  const [meta, setMeta] = useState<Meta>({});
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [detail, setDetail] = useState<Row | null>(null);
  const [detailFull, setDetailFull] = useState<Row | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [emailOpen, setEmailOpen] = useState(false);
  const [emailTo, setEmailTo] = useState('');
  const [cancelId, setCancelId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [wizard, setWizard] = useState(false);
  const [step, setStep] = useState(1);
  const [form, setForm] = useState({
    title: '',
    amount: '',
    status: 'draft',
    installments: '',
    product_note: '',
    project_id: '',
  });

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/projects/contracts', {
        params: { page, per_page: 15, status: statusFilter || undefined },
      });
      const body = res.data as { data?: unknown; meta?: Meta };
      const raw = unwrapContracts(body);
      setRows(raw.rows);
      setMeta(raw.meta);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [page, statusFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  async function openDetail(r: Row) {
    setDetail(r);
    setDetailFull(null);
    try {
      const res = await apiClient.get(`/v1/projects/contracts/${String(r.id)}/details`);
      const body = res.data as { data?: Row };
      setDetailFull(body.data ?? r);
    } catch {
      setDetailFull(r);
    }
  }

  async function saveContract() {
    setError(null);
    let installmentsData: unknown[] | undefined;
    try {
      installmentsData = form.installments.trim() ? (JSON.parse(form.installments) as unknown[]) : undefined;
    } catch {
      setError(t('common.invalidInstallmentsJson'));
      return;
    }
    try {
      await apiClient.post('/v1/projects/contracts', {
        title: form.title,
        amount: form.amount ? Number(form.amount) : undefined,
        status: form.status,
        project_id: form.project_id ? Number(form.project_id) : undefined,
        installments_data: installmentsData,
        notes: form.product_note.trim() || undefined,
      });
      setWizard(false);
      setStep(1);
      setForm({
        title: '',
        amount: '',
        status: 'draft',
        installments: '',
        product_note: '',
        project_id: '',
      });
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  const lastPage = meta.last_page ?? 1;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
          <CardTitle className="text-base">{t('auto.ContractsListPage.s_1fb3fcaa')}</CardTitle>
          <div className="flex flex-wrap items-center gap-2">
            <Select value={statusFilter || 'all'} onValueChange={(v) => setStatusFilter(v === 'all' ? '' : v)}>
              <SelectTrigger className="w-[160px]">
                <SelectValue placeholder={t('auto.ContractsListPage.s_55518965')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('auto.ContractsListPage.s_bf742c5a')}</SelectItem>
                <SelectItem value="draft">{t('auto.ContractsListPage.s_7d739ea1')}</SelectItem>
                <SelectItem value="active">{t('auto.ContractsListPage.s_6f637966')}</SelectItem>
                <SelectItem value="cancelled">{t('auto.ContractsListPage.s_eba946ea')}</SelectItem>
              </SelectContent>
            </Select>
            <Button type="button" size="sm" variant="secondary" onClick={() => void load()}>
              {t('auto.ContractsListPage.s_e0cd6db1')}
            </Button>
            <Button type="button" size="sm" onClick={() => setWizard(true)}>
              {t('auto.ContractsListPage.s_56de94cf')}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {error ? <p className="mb-2 text-sm text-destructive">{error}</p> : null}
          <div className="overflow-x-auto rounded-md border">
            <table className="w-full min-w-[560px] text-sm">
              <thead>
                <tr className="border-b bg-muted/40">
                  <th className="px-3 py-2 text-start">#</th>
                  <th className="px-3 py-2 text-start">{t('auto.ContractsListPage.s_1a9bdb20')}</th>
                  <th className="px-3 py-2 text-start">{t('auto.ContractsListPage.s_f5668e5b')}</th>
                  <th className="px-3 py-2 text-start">{t('auto.ContractsListPage.s_55518965')}</th>
                  <th className="px-3 py-2 text-start">{t('auto.ContractsListPage.s_8d1cc546')}</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                      {t('auto.ContractsListPage.s_fbac73dc')}
                    </td>
                  </tr>
                ) : (
                  rows.map((r) => (
                    <tr key={String(r.id)} className="border-b border-border/60">
                      <td className="px-3 py-2">{String(r.id)}</td>
                      <td className="px-3 py-2">{String(r.title ?? '—')}</td>
                      <td className="px-3 py-2">{String(r.amount ?? '—')}</td>
                      <td className="px-3 py-2">{String(r.status ?? '—')}</td>
                      <td className="px-3 py-2">
                        <div className="flex flex-wrap gap-1">
                          <Button type="button" variant="outline" size="sm" onClick={() => void openDetail(r)}>
                            {t('auto.ContractsListPage.s_5bccf844')}
                          </Button>
                          <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={async () => {
                              try {
                                await apiClient.post(`/v1/projects/contracts/${String(r.id)}/pdf`);
                              } catch (e) {
                                setError(getAxiosMessage(e));
                              }
                            }}
                          >
                            PDF
                          </Button>
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              setDetail(r);
                              setEmailOpen(true);
                            }}>
                          
                            {t('auto.ContractsListPage.s_f1ad423d')}
                          </Button>
                          <Button type="button" variant="destructive" size="sm" onClick={() => setCancelId(Number(r.id))}>
                            {t('auto.ContractsListPage.s_e409bf47')}
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
          <Pagination page={meta.current_page ?? page} pageCount={lastPage} total={meta.total} onPageChange={setPage} />
        </CardContent>
      </Card>

      <Dialog open={wizard} onOpenChange={setWizard}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{t('common.wizardContract', { step: step })}</DialogTitle>
          </DialogHeader>
          <Tabs value={String(step)} onValueChange={(v) => setStep(Number(v))}>
            <TabsList className="grid w-full grid-cols-4">
              <TabsTrigger value="1">{t('auto.ContractsListPage.s_b874f93c')}</TabsTrigger>
              <TabsTrigger value="2">{t('auto.ContractsListPage.s_5e82aa42')}</TabsTrigger>
              <TabsTrigger value="3">{t('auto.ContractsListPage.s_2e05931c')}</TabsTrigger>
              <TabsTrigger value="4">{t('auto.ContractsListPage.s_55da48c5')}</TabsTrigger>
            </TabsList>
            <TabsContent value="1" className="space-y-3 pt-4">
              <div>
                <label className="text-sm">{t('auto.ContractsListPage.s_1a9bdb20')}</label>
                <Input value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} />
              </div>
              <div>
                <label className="text-sm">{t('auto.ContractsListPage.s_f5668e5b')}</label>
                <Input type="number" value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))} />
              </div>
              <div>
                <label className="text-sm">{t('auto.ContractsListPage.s_55518965')}</label>
                <Input value={form.status} onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))} />
              </div>
            </TabsContent>
            <TabsContent value="2" className="space-y-3 pt-4">
              <p className="text-xs text-muted-foreground">{t('auto.ContractsListPage.s_bdb58d91')}</p>
              <Textarea
                rows={6}
                className="font-mono text-xs"
                dir="ltr"
                value={form.installments}
                onChange={(e) => setForm((f) => ({ ...f, installments: e.target.value }))}
                placeholder='[{"amount":1000000,"due_date":"2026-05-01"}]'
              />
            </TabsContent>
            <TabsContent value="3" className="space-y-3 pt-4">
              <Textarea
                rows={4}
                placeholder={t('auto.ContractsListPage.s_03778d07')}
                value={form.product_note}
                onChange={(e) => setForm((f) => ({ ...f, product_note: e.target.value }))}
              />
            </TabsContent>
            <TabsContent value="4" className="space-y-3 pt-4">
              <div>
                <label className="text-sm">{t('auto.ContractsListPage.s_8a4cf981')}</label>
                <Input value={form.project_id} onChange={(e) => setForm((f) => ({ ...f, project_id: e.target.value }))} />
              </div>
            </TabsContent>
          </Tabs>
          <DialogFooter className="gap-2">
            {step > 1 ? (
              <Button type="button" variant="outline" onClick={() => setStep((s) => Math.max(1, s - 1))}>
                {t('auto.ContractsListPage.s_1a592f6b')}
              </Button>
            ) : null}
            {step < 4 ? (
              <Button type="button" onClick={() => setStep((s) => Math.min(4, s + 1))}>
                {t('auto.ContractsListPage.s_54ee927e')}
              </Button>
            ) : (
              <Button type="button" onClick={() => void saveContract()} disabled={!form.title.trim()}>
                {t('auto.ContractsListPage.s_a8ba0698')}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!detail && !emailOpen} onOpenChange={(o) => { if (!o) { setDetail(null); setDetailFull(null); } }}>
        <DialogContent className="max-w-lg max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('auto.ContractsListPage.s_c6b8881a')}</DialogTitle>
          </DialogHeader>
          {detail ? (
            <div className="space-y-3 text-sm">
              <p>
                <strong>{t('auto.ContractsListPage.s_659bbbd1')}</strong> {String(detail.title)}
              </p>
              <p>
                <strong>{t('auto.ContractsListPage.s_752c66d7')}</strong> {String(detail.amount ?? '—')}
              </p>
              <p>
                <strong>{t('auto.ContractsListPage.s_372c3f95')}</strong> {String(detail.status ?? '—')}
              </p>
              <p>
                <strong>{t('auto.ContractsListPage.s_3f3bb2b4')}</strong> {String(detail.notes ?? '—')}
              </p>
              <p>
                <strong>{t('auto.ContractsListPage.s_1cf3b496')}</strong>{' '}
                {(detailFull ?? detail)?.project_id ? (
                  <a
                    href={`/dashboard/projects/${String((detailFull ?? detail)?.project_id)}`}
                    className="text-primary underline"
                  >
                    {t('common.projectHash', { id: String((detailFull ?? detail)?.project_id) })}
                  </a>
                ) : '—'}
              </p>

              {detailFull?.lead && typeof detailFull.lead === 'object' ? (
                <div className="rounded-md border p-3 space-y-1">
                  <p className="font-semibold text-xs text-muted-foreground">{t('auto.ContractsListPage.s_ad8f339e')}</p>
                  <p><strong>{t('auto.ContractsListPage.s_20974cc2')}</strong> {String((detailFull.lead as Record<string, unknown>).topic ?? '—')}</p>
                  <p><strong>{t('auto.ContractsListPage.s_8b5dcef4')}</strong> {String((detailFull.lead as Record<string, unknown>).email ?? '—')}</p>
                  <p><strong>{t('auto.ContractsListPage.s_c583452b')}</strong> {String((detailFull.lead as Record<string, unknown>).mobile ?? '—')}</p>
                </div>
              ) : null}

              {Array.isArray(detailFull?.installments) && (detailFull.installments as unknown[]).length > 0 ? (
                <div className="space-y-1">
                  <p className="font-semibold text-xs text-muted-foreground">{t('auto.ContractsListPage.s_5e82aa42')}</p>
                  <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-xs">
                      <thead>
                        <tr className="border-b bg-muted/40">
                          <th className="px-2 py-1 text-start">{t('auto.ContractsListPage.s_f5668e5b')}</th>
                          <th className="px-2 py-1 text-start">{t('auto.ContractsListPage.s_f1f03406')}</th>
                          <th className="px-2 py-1 text-start">{t('auto.ContractsListPage.s_a8b128af')}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(detailFull.installments as Record<string, unknown>[]).map((inst, idx) => (
                          <tr key={idx} className="border-b border-border/60">
                            <td className="px-2 py-1">{String(inst.amount ?? '—')}</td>
                            <td className="px-2 py-1">{String(inst.due_date ?? '—')}</td>
                            <td className="px-2 py-1">{String(inst.paid_at ?? '—')}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              ) : null}
            </div>
          ) : null}
          <DialogFooter className="gap-2">
            <Button type="button" variant="destructive" size="sm" onClick={() => { if (detail?.id) setDeleteId(Number(detail.id)); }}>
              {t('auto.ContractsListPage.s_c57a9b40')}
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={() => { setDetail(null); setDetailFull(null); }}>
              {t('auto.ContractsListPage.s_4a0f283e')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={emailOpen} onOpenChange={setEmailOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('auto.ContractsListPage.s_30eec346')}</DialogTitle>
          </DialogHeader>
          <Input
            type="email"
            placeholder={t('auto.ContractsListPage.s_c2331cdf')}
            value={emailTo}
            onChange={(e) => setEmailTo(e.target.value)}
            dir="ltr"
          />
          <DialogFooter>
            <Button
              type="button"
              onClick={async () => {
                if (!detail?.id || !emailTo.trim()) return;
                try {
                  await apiClient.post(`/v1/projects/contracts/${String(detail.id)}/email`, { to: emailTo.trim() });
                  setEmailOpen(false);
                  setEmailTo('');
                  setDetail(null);
                } catch (e) {
                  setError(getAxiosMessage(e));
                }
              }}>
            
              {t('auto.ContractsListPage.s_f26c55d9')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={cancelId !== null} onOpenChange={(o) => !o && setCancelId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('auto.ContractsListPage.s_18f1fd37')}</AlertDialogTitle>
            <AlertDialogDescription>{t('auto.ContractsListPage.s_38f544f3')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('auto.ContractsListPage.s_106dfb4e')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={async () => {
                if (!cancelId) return;
                try {
                  await apiClient.post(`/v1/projects/contracts/${cancelId}/cancel`);
                  setCancelId(null);
                  void load();
                } catch (e) {
                  setError(getAxiosMessage(e));
                }
              }}>
            
              {t('auto.ContractsListPage.s_4f47b8c9')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('auto.ContractsListPage.s_a9a5eb07')}</AlertDialogTitle>
            <AlertDialogDescription>{t('auto.ContractsListPage.s_e52ae4a7')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('auto.ContractsListPage.s_106dfb4e')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={async () => {
                if (!deleteId) return;
                try {
                  await apiClient.delete(`/v1/projects/contracts/${deleteId}`);
                  setDeleteId(null);
                  setDetail(null);
                  setDetailFull(null);
                  void load();
                } catch (e) {
                  setError(getAxiosMessage(e));
                }
              }}>
            
              {t('auto.ContractsListPage.s_481e8243')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function unwrapContracts(body: { data?: unknown; meta?: Meta }): { rows: Row[]; meta: Meta } {
  const raw = body.data;
  if (raw && typeof raw === 'object' && 'data' in raw && Array.isArray((raw as { data: Row[] }).data)) {
    return { rows: (raw as { data: Row[] }).data, meta: (raw as { meta?: Meta }).meta ?? body.meta ?? {} };
  }
  return { rows: normalizeListPayload(body), meta: body.meta ?? {} };
}
