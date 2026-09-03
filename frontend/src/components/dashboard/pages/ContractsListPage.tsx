'use client';

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
      setError('JSON اقساط نامعتبر است');
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
          <CardTitle className="text-base">قراردادها</CardTitle>
          <div className="flex flex-wrap items-center gap-2">
            <Select value={statusFilter || 'all'} onValueChange={(v) => setStatusFilter(v === 'all' ? '' : v)}>
              <SelectTrigger className="w-[160px]">
                <SelectValue placeholder="وضعیت" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">همه</SelectItem>
                <SelectItem value="draft">پیش‌نویس</SelectItem>
                <SelectItem value="active">فعال</SelectItem>
                <SelectItem value="cancelled">لغو شده</SelectItem>
              </SelectContent>
            </Select>
            <Button type="button" size="sm" variant="secondary" onClick={() => void load()}>
              اعمال فیلتر
            </Button>
            <Button type="button" size="sm" onClick={() => setWizard(true)}>
              قرارداد جدید (ویزارد)
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
                  <th className="px-3 py-2 text-start">عنوان</th>
                  <th className="px-3 py-2 text-start">مبلغ</th>
                  <th className="px-3 py-2 text-start">وضعیت</th>
                  <th className="px-3 py-2 text-start">عملیات</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                      بارگذاری…
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
                            جزئیات
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
                            }}
                          >
                            ایمیل
                          </Button>
                          <Button type="button" variant="destructive" size="sm" onClick={() => setCancelId(Number(r.id))}>
                            لغو
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
            <DialogTitle>ویزارد قرارداد — مرحله {step} از ۴</DialogTitle>
          </DialogHeader>
          <Tabs value={String(step)} onValueChange={(v) => setStep(Number(v))}>
            <TabsList className="grid w-full grid-cols-4">
              <TabsTrigger value="1">اطلاعات</TabsTrigger>
              <TabsTrigger value="2">اقساط</TabsTrigger>
              <TabsTrigger value="3">محصول</TabsTrigger>
              <TabsTrigger value="4">پروژه</TabsTrigger>
            </TabsList>
            <TabsContent value="1" className="space-y-3 pt-4">
              <div>
                <label className="text-sm">عنوان</label>
                <Input value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} />
              </div>
              <div>
                <label className="text-sm">مبلغ</label>
                <Input type="number" value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))} />
              </div>
              <div>
                <label className="text-sm">وضعیت</label>
                <Input value={form.status} onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))} />
              </div>
            </TabsContent>
            <TabsContent value="2" className="space-y-3 pt-4">
              <p className="text-xs text-muted-foreground">آرایه JSON اقساط — نمونه در placeholder</p>
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
                placeholder="یادداشت محصول / سرویس مرتبط"
                value={form.product_note}
                onChange={(e) => setForm((f) => ({ ...f, product_note: e.target.value }))}
              />
            </TabsContent>
            <TabsContent value="4" className="space-y-3 pt-4">
              <div>
                <label className="text-sm">شناسه پروژه (اختیاری)</label>
                <Input value={form.project_id} onChange={(e) => setForm((f) => ({ ...f, project_id: e.target.value }))} />
              </div>
            </TabsContent>
          </Tabs>
          <DialogFooter className="gap-2">
            {step > 1 ? (
              <Button type="button" variant="outline" onClick={() => setStep((s) => Math.max(1, s - 1))}>
                قبلی
              </Button>
            ) : null}
            {step < 4 ? (
              <Button type="button" onClick={() => setStep((s) => Math.min(4, s + 1))}>
                بعدی
              </Button>
            ) : (
              <Button type="button" onClick={() => void saveContract()} disabled={!form.title.trim()}>
                ذخیره قرارداد
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!detail && !emailOpen} onOpenChange={(o) => { if (!o) { setDetail(null); setDetailFull(null); } }}>
        <DialogContent className="max-w-lg max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>جزئیات قرارداد</DialogTitle>
          </DialogHeader>
          {detail ? (
            <div className="space-y-3 text-sm">
              <p>
                <strong>عنوان:</strong> {String(detail.title)}
              </p>
              <p>
                <strong>مبلغ:</strong> {String(detail.amount ?? '—')}
              </p>
              <p>
                <strong>وضعیت:</strong> {String(detail.status ?? '—')}
              </p>
              <p>
                <strong>یادداشت محصول:</strong> {String(detail.notes ?? '—')}
              </p>
              <p>
                <strong>پروژه:</strong>{' '}
                {(detailFull ?? detail)?.project_id ? (
                  <a
                    href={`/dashboard/projects/${String((detailFull ?? detail)?.project_id)}`}
                    className="text-primary underline"
                  >
                    پروژه #{String((detailFull ?? detail)?.project_id)}
                  </a>
                ) : '—'}
              </p>

              {detailFull?.lead && typeof detailFull.lead === 'object' ? (
                <div className="rounded-md border p-3 space-y-1">
                  <p className="font-semibold text-xs text-muted-foreground">اطلاعات لید</p>
                  <p><strong>موضوع:</strong> {String((detailFull.lead as Record<string, unknown>).topic ?? '—')}</p>
                  <p><strong>ایمیل:</strong> {String((detailFull.lead as Record<string, unknown>).email ?? '—')}</p>
                  <p><strong>موبایل:</strong> {String((detailFull.lead as Record<string, unknown>).mobile ?? '—')}</p>
                </div>
              ) : null}

              {Array.isArray(detailFull?.installments) && (detailFull.installments as unknown[]).length > 0 ? (
                <div className="space-y-1">
                  <p className="font-semibold text-xs text-muted-foreground">اقساط</p>
                  <div className="overflow-x-auto rounded-md border">
                    <table className="w-full text-xs">
                      <thead>
                        <tr className="border-b bg-muted/40">
                          <th className="px-2 py-1 text-start">مبلغ</th>
                          <th className="px-2 py-1 text-start">تاریخ سررسید</th>
                          <th className="px-2 py-1 text-start">تاریخ پرداخت</th>
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
              حذف قرارداد
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={() => { setDetail(null); setDetailFull(null); }}>
              بستن
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={emailOpen} onOpenChange={setEmailOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>ارسال ایمیل قرارداد</DialogTitle>
          </DialogHeader>
          <Input
            type="email"
            placeholder="ایمیل گیرنده"
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
              }}
            >
              ارسال
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={cancelId !== null} onOpenChange={(o) => !o && setCancelId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>لغو قرارداد؟</AlertDialogTitle>
            <AlertDialogDescription>وضعیت به cancelled تغییر می‌کند.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>انصراف</AlertDialogCancel>
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
              }}
            >
              تأیید لغو
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف قرارداد؟</AlertDialogTitle>
            <AlertDialogDescription>این عملیات غیرقابل بازگشت است.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>انصراف</AlertDialogCancel>
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
              }}
            >
              تأیید حذف
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
