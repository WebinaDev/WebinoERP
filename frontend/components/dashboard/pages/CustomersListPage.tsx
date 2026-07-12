'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { getAxiosMessage, unwrapData } from '@/lib/api-helpers';
import { normalizeListPayload } from '@/lib/list-utils';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import { Customer360Sheet } from '@/features/modules/crm/Customer360Sheet';
import { ImportCsvDialog } from '@/features/modules/crm/ImportCsvDialog';

type Row = Record<string, unknown>;
type Summary = { total?: number; individual?: number; company?: number };
type Meta = { current_page?: number; last_page?: number; total?: number };

export function CustomersListPage() {
  const t = useTranslations('crm.customers');
  const tCommon = useTranslations('common');
  const [rows, setRows] = useState<Row[]>([]);
  const [meta, setMeta] = useState<Meta>({});
  const [summary, setSummary] = useState<Summary | null>(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const [smsOpen, setSmsOpen] = useState(false);
  const [smsTo, setSmsTo] = useState('');
  const [smsBody, setSmsBody] = useState('');
  const [smsErr, setSmsErr] = useState<string | null>(null);

  const [baleOpen, setBaleOpen] = useState(false);
  const [baleUserId, setBaleUserId] = useState('');
  const [baleMessage, setBaleMessage] = useState('');
  const [baleErr, setBaleErr] = useState<string | null>(null);

  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Row | null>(null);
  const [name, setName] = useState('');
  const [type, setType] = useState('customer');
  const [website, setWebsite] = useState('');
  const [description, setDescription] = useState('');
  const [formErr, setFormErr] = useState<string | null>(null);

  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [customer360Id, setCustomer360Id] = useState<number | null>(null);
  const [importOpen, setImportOpen] = useState(false);

  const [selected, setSelected] = useState<Record<number, boolean>>({});

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [listRes, sumRes] = await Promise.all([
        apiClient.get('/v1/crm/accounts', {
          params: {
            page,
            per_page: 15,
            search: search || undefined,
            type: typeFilter || undefined,
          },
        }),
        apiClient.get('/v1/crm/accounts/summary'),
      ]);
      const pageData = unwrapData<Record<string, unknown>>(listRes);
      setRows(normalizeListPayload(pageData));
      setMeta({
        current_page: typeof pageData.current_page === 'number' ? pageData.current_page : undefined,
        last_page: typeof pageData.last_page === 'number' ? pageData.last_page : undefined,
        total: typeof pageData.total === 'number' ? pageData.total : undefined,
      });
      setSummary(unwrapData<Summary>(sumRes));
    } catch (e) {
      setError(getAxiosMessage(e));
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [page, search, typeFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  async function sendSms() {
    setSmsErr(null);
    try {
      await apiClient.post('/v1/integrations/sms/send', { to: smsTo, message: smsBody });
      setSmsOpen(false);
    } catch (e) {
      setSmsErr(getAxiosMessage(e));
    }
  }

  async function sendBale() {
    setBaleErr(null);
    const uid = Number(baleUserId);
    if (!uid) {
      setBaleErr('شناسه کاربر را وارد کنید.');
      return;
    }
    try {
      await apiClient.post('webinocrm/v1/bale/message', { user_id: uid, message: baleMessage });
      setBaleOpen(false);
      setBaleMessage('');
    } catch (e) {
      setBaleErr(getAxiosMessage(e));
    }
  }

  async function sendBaleBulk() {
    setBaleErr(null);
    const ids = Object.keys(selected)
      .filter((k) => selected[Number(k)])
      .map((k) => Number(k));
    const owners = rows.filter((r) => ids.includes(Number(r.id)) && r.owner_id).map((r) => Number(r.owner_id));
    const uniq = [...new Set(owners)];
    if (!uniq.length) {
      setBaleErr('برای ردیف‌های انتخاب‌شده مالک (owner) تعریف نشده است.');
      return;
    }
    try {
      for (const uid of uniq) {
        await apiClient.post('webinocrm/v1/bale/message', { user_id: uid, message: baleMessage });
      }
      setSelected({});
      setBaleOpen(false);
      setBaleMessage('');
    } catch (e) {
      setBaleErr(getAxiosMessage(e));
    }
  }

  function openCreate() {
    setEditing(null);
    setName('');
    setType('customer');
    setWebsite('');
    setDescription('');
    setFormErr(null);
    setFormOpen(true);
  }

  function openEdit(r: Row) {
    setEditing(r);
    setName(String(r.name ?? ''));
    setType(String(r.type ?? 'customer'));
    setWebsite(String(r.website ?? ''));
    setDescription(String(r.description ?? ''));
    setFormErr(null);
    setFormOpen(true);
  }

  async function saveAccount() {
    setFormErr(null);
    try {
      if (editing) {
        await apiClient.patch(`/v1/crm/accounts/${String(editing.id)}`, {
          name,
          type,
          website: website || null,
          description: description || null,
        });
      } else {
        await apiClient.post('/v1/crm/accounts', {
          name: name || 'مشتری جدید',
          type,
          website: website || null,
          description: description || null,
        });
      }
      setFormOpen(false);
      await load();
    } catch (e) {
      setFormErr(getAxiosMessage(e));
    }
  }

  async function confirmDelete() {
    if (!deleteId) {
      return;
    }
    try {
      await apiClient.delete(`/v1/crm/accounts/${deleteId}`);
      setDeleteId(null);
      await load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  function toggleSelect(id: number) {
    setSelected((s) => ({ ...s, [id]: !s[id] }));
  }

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
        <CardTitle>{t('title')}</CardTitle>
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" size="sm" onClick={() => void load()}>
            {t('refresh')}
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={() => setImportOpen(true)}>
            {t('importCsv')}
          </Button>
          <Button type="button" size="sm" variant="secondary" onClick={() => setSmsOpen(true)}>
            {t('sendSms')}
          </Button>
          <Button type="button" size="sm" onClick={() => setBaleOpen(true)}>
            Bale
          </Button>
          <Button type="button" size="sm" onClick={() => openCreate()}>
            {t('newCustomer')}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <div className="mb-4 grid gap-3 sm:grid-cols-3">
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-sm text-muted-foreground">{tCommon('search').replace('…', '')}</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold">{summary?.total ?? tCommon('emptyValue')}</CardContent>
          </Card>
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-sm text-muted-foreground">{t('individual')}</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold">{summary?.individual ?? tCommon('emptyValue')}</CardContent>
          </Card>
          <Card>
            <CardHeader className="py-3">
              <CardTitle className="text-sm text-muted-foreground">{t('corporate')}</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold">{summary?.company ?? '—'}</CardContent>
          </Card>
        </div>

        <div className="mb-4 flex flex-wrap gap-2">
          <Input
            placeholder={t('searchPlaceholder')}
            className="max-w-xs"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && void load()}
          />
          <Select value={typeFilter || 'all'} onValueChange={(v) => setTypeFilter(v === 'all' ? '' : v)}>
            <SelectTrigger className="w-[160px]">
              <SelectValue placeholder="نوع" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">همه انواع</SelectItem>
              <SelectItem value="customer">customer</SelectItem>
              <SelectItem value="individual">individual</SelectItem>
              <SelectItem value="company">company</SelectItem>
            </SelectContent>
          </Select>
          <Button type="button" size="sm" variant="secondary" onClick={() => void load()}>
            اعمال
          </Button>
        </div>

        {error ? <p className="text-destructive text-sm">{error}</p> : null}
        <div className="overflow-x-auto rounded-md border">
          <table className="w-full min-w-[640px] text-sm">
            <thead>
              <tr className="border-b bg-muted/40">
                <th className="px-2 py-2 text-start">
                  <input
                    type="checkbox"
                    aria-label="select all on page"
                    onChange={(e) => {
                      const on = e.target.checked;
                      const next: Record<number, boolean> = { ...selected };
                      rows.forEach((r) => {
                        const id = Number(r.id);
                        if (Number.isFinite(id)) {
                          next[id] = on;
                        }
                      });
                      setSelected(next);
                    }}
                  />
                </th>
                <th className="px-3 py-2 text-start"> </th>
                <th className="px-3 py-2 text-start">{t('name')}</th>
                <th className="px-3 py-2 text-start">{t('type')}</th>
                <th className="px-3 py-2 text-start">{t('owner')}</th>
                <th className="px-3 py-2 text-start"> </th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan={6} className="px-3 py-6 text-center text-muted-foreground">
                    {tCommon('loading')}
                  </td>
                </tr>
              ) : (
                rows.map((r) => {
                  const nameStr = String(r.name ?? '—');
                  const initials = nameStr.slice(0, 2).toUpperCase();
                  const id = Number(r.id);
                  return (
                    <tr key={String(r.id)} className="border-b border-border/60">
                      <td className="px-2 py-2">
                        <input
                          type="checkbox"
                          checked={!!selected[id]}
                          onChange={() => toggleSelect(id)}
                          aria-label={`select ${id}`}
                        />
                      </td>
                      <td className="px-3 py-2">
                        <Avatar className="h-8 w-8">
                          <AvatarFallback>{initials}</AvatarFallback>
                        </Avatar>
                      </td>
                      <td className="px-3 py-2">{nameStr}</td>
                      <td className="px-3 py-2">{String(r.type ?? '—')}</td>
                      <td className="px-3 py-2">{String(r.owner_id ?? '—')}</td>
                      <td className="px-3 py-2">
                        <div className="flex flex-wrap gap-1">
                          <Button type="button" variant="ghost" size="sm" onClick={() => setCustomer360Id(id)}>
                            {t('view360')}
                          </Button>
                          <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(r)}>
                            {t('edit')}
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-destructive"
                            onClick={() => setDeleteId(id)}
                          >
                            {t('delete')}
                          </Button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {(meta.last_page ?? 1) > 1 ? (
          <div className="mt-4 flex justify-center">
            <Pagination
              page={meta.current_page ?? page}
              pageCount={meta.last_page ?? 1}
              total={meta.total}
              onPageChange={(p) => setPage(p)}
            />
          </div>
        ) : null}

        <p className="mt-2 text-xs text-muted-foreground">
          پیام بله به شناسه کاربر (users) نیاز دارد؛ برای ارسال گروهی، مالک حساب باید تنظیم شده باشد.
        </p>
      </CardContent>

      <Dialog open={smsOpen} onOpenChange={setSmsOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('sendSms')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Input placeholder="شماره" dir="ltr" value={smsTo} onChange={(e) => setSmsTo(e.target.value)} />
            <Input placeholder="متن" value={smsBody} onChange={(e) => setSmsBody(e.target.value)} />
            {smsErr ? <p className="text-sm text-destructive">{smsErr}</p> : null}
          </div>
          <DialogFooter>
            <Button type="button" onClick={() => void sendSms()}>
              ارسال
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={baleOpen} onOpenChange={setBaleOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>پیام در بله</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Input
              placeholder="شناسه کاربر (users.id)"
              dir="ltr"
              value={baleUserId}
              onChange={(e) => setBaleUserId(e.target.value)}
            />
            <Textarea placeholder="متن پیام" value={baleMessage} onChange={(e) => setBaleMessage(e.target.value)} rows={3} />
            {baleErr ? <p className="text-sm text-destructive">{baleErr}</p> : null}
          </div>
          <DialogFooter className="gap-2 sm:justify-between">
            <Button type="button" variant="secondary" onClick={() => void sendBaleBulk()}>
              ارسال به مالک‌های انتخاب‌شده
            </Button>
            <Button type="button" onClick={() => void sendBale()}>
              ارسال تکی
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? t('edit') : t('newCustomer')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Input placeholder="نام" value={name} onChange={(e) => setName(e.target.value)} />
            <Select value={type} onValueChange={setType}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="customer">customer</SelectItem>
                <SelectItem value="individual">individual</SelectItem>
                <SelectItem value="company">company</SelectItem>
              </SelectContent>
            </Select>
            <Input placeholder="وب‌سایت" dir="ltr" value={website} onChange={(e) => setWebsite(e.target.value)} />
            <Textarea placeholder="توضیحات" value={description} onChange={(e) => setDescription(e.target.value)} rows={3} />
            {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
          </div>
          <DialogFooter>
            <Button type="button" onClick={() => void saveAccount()}>
              {tCommon('save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Customer360Sheet
        accountId={customer360Id}
        open={customer360Id !== null}
        onOpenChange={(o) => !o && setCustomer360Id(null)}
      />
      <ImportCsvDialog open={importOpen} onOpenChange={setImportOpen} onImported={() => void load()} />

      <AlertDialog open={deleteId !== null} onOpenChange={(o) => !o && setDeleteId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('deleteConfirm')}</AlertDialogTitle>
            <AlertDialogDescription>{t('deleteDescription')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tCommon('cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmDelete()}>{t('delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}
