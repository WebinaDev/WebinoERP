'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { Briefcase, KeyRound, Lock, Search, ShieldCheck, UserCheck, UserPlus, Users } from 'lucide-react';
import { toast } from 'sonner';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ErpStatGrid } from '@/features/shared/erp-resource';
import { getAxiosMessage } from '@/lib/api-helpers';
import { formatDate } from '@/lib/locale/format-date';
import { dashboardHref } from '@/lib/route-resolver';
import { createUser, getUsersDirectory, getUsersMeta, type DirectoryResult, type ManagedUser, type UsersMeta } from '@/lib/api/users';
import { TemporaryPasswordNotice } from './TemporaryPasswordNotice';

export function initials(name: string) {
  return name.split(/\s+/).filter(Boolean).map((x) => x[0]).join('').slice(0, 2).toUpperCase() || '?';
}

export function StatusBadges({ u }: { u: Pick<ManagedUser, 'is_active' | 'locked' | 'must_change_password'> }) {
  const t = useTranslations('userAdmin');
  return (
    <div className="flex flex-wrap gap-1">
      {u.locked ? <Badge variant="destructive">{t('badge.locked')}</Badge> : u.is_active ? <Badge>{t('badge.active')}</Badge> : <Badge variant="secondary">{t('badge.inactive')}</Badge>}
      {u.must_change_password ? <Badge variant="outline">{t('badge.mustChange')}</Badge> : null}
    </div>
  );
}

export function UsersAdminPage() {
  const t = useTranslations('userAdmin');
  const locale = useLocale();
  const nf = (n?: number) => (n ?? 0).toLocaleString(locale === 'fa' ? 'fa-IR' : 'en-US');
  const [meta, setMeta] = useState<UsersMeta | null>(null);
  const [data, setData] = useState<DirectoryResult | null>(null);
  const [type, setType] = useState('all');
  const [status, setStatus] = useState('all');
  const [role, setRole] = useState('all');
  const [sort, setSort] = useState('newest');
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [createOpen, setCreateOpen] = useState(false);
  const [createKey, setCreateKey] = useState(0);

  useEffect(() => {
    void getUsersMeta().then(setMeta).catch(() => undefined);
  }, []);
  useEffect(() => {
    const h = window.setTimeout(() => { setQuery(search.trim()); setPage(1); }, 350);
    return () => window.clearTimeout(h);
  }, [search]);

  const load = useCallback(() => {
    void getUsersDirectory({ type, status, role: role === 'all' ? undefined : role, sort, search: query || undefined, page, per_page: 20 })
      .then(setData)
      .catch((e) => toast.error(getAxiosMessage(e)));
  }, [type, status, role, sort, query, page]);
  useEffect(load, [load]);

  const s = data?.meta.stats;
  const roleLabel = (name: string) => meta?.roles.find((r) => r.name === name)?.label ?? name;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">{t('intro')}</p>
        <Button type="button" onClick={() => { setCreateKey((k) => k + 1); setCreateOpen(true); }}><UserPlus className="size-4" />{t('newUser')}</Button>
      </div>

      <ErpStatGrid items={[
        { label: t('stats.total'), value: nf(s?.total), icon: <Users className="size-4" /> },
        { label: t('stats.staff'), value: nf(s?.staff), icon: <Briefcase className="size-4" /> },
        { label: t('stats.customers'), value: nf(s?.customers), icon: <UserCheck className="size-4" /> },
        { label: t('stats.locked'), value: `${nf(s?.locked)} / ${nf(s?.inactive)}`, icon: <Lock className="size-4" /> },
      ]} />

      <Card className="shadow-sm">
        <CardContent className="space-y-3 p-4">
          <div className="flex flex-wrap gap-2">
            {(['all', 'staff', 'customer', 'other'] as const).map((x) => (
              <Button key={x} type="button" size="sm" variant={type === x ? 'default' : 'outline'} onClick={() => { setType(x); setPage(1); }}>{t(`types.${x}`)}</Button>
            ))}
          </div>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <div className="relative sm:col-span-2 lg:col-span-1">
              <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input className="ps-9" placeholder={t('search')} value={search} onChange={(e) => setSearch(e.target.value)} />
            </div>
            <Select value={status} onValueChange={(v) => { setStatus(v); setPage(1); }}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{['all', 'active', 'inactive', 'locked', 'must_change'].map((x) => <SelectItem key={x} value={x}>{t(`statusFilter.${x}`)}</SelectItem>)}</SelectContent>
            </Select>
            <Select value={role} onValueChange={(v) => { setRole(v); setPage(1); }}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('allRoles')}</SelectItem>
                {(meta?.roles ?? []).map((r) => <SelectItem key={r.name} value={r.name}>{r.label}</SelectItem>)}
              </SelectContent>
            </Select>
            <Select value={sort} onValueChange={setSort}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{['newest', 'oldest', 'name', 'last_login'].map((x) => <SelectItem key={x} value={x}>{t(`sort.${x}`)}</SelectItem>)}</SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {data === null ? <p className="text-sm text-muted-foreground">{t('loading')}</p> : null}
      {data && data.rows.length === 0 ? (
        <Card className="border-dashed shadow-none"><CardContent className="p-8 text-center text-sm text-muted-foreground">{t('empty')}</CardContent></Card>
      ) : null}

      {data && data.rows.length > 0 ? (
        <Card className="overflow-hidden shadow-sm">
          <div className="hidden overflow-x-auto md:block">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-xs text-muted-foreground">
                <tr>
                  <th className="p-3 text-start font-medium">{t('col.user')}</th>
                  <th className="p-3 text-start font-medium">{t('col.type')}</th>
                  <th className="p-3 text-start font-medium">{t('col.roles')}</th>
                  <th className="p-3 text-start font-medium">{t('col.status')}</th>
                  <th className="p-3 text-start font-medium">{t('col.lastLogin')}</th>
                  <th className="p-3 text-end font-medium">{t('col.actions')}</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {data.rows.map((u) => (
                  <tr key={u.id} className="hover:bg-muted/30">
                    <td className="p-3">
                      <Link href={dashboardHref(locale, `admin/users/${u.id}`)} className="flex items-center gap-3">
                        <Avatar className="size-9">{u.avatar_url ? <AvatarImage src={u.avatar_url} alt="" /> : null}<AvatarFallback>{initials(u.name)}</AvatarFallback></Avatar>
                        <span className="min-w-0">
                          <span className="block truncate font-medium">{u.name}</span>
                          <span className="block truncate text-xs text-muted-foreground" dir="ltr">{u.username ? `@${u.username} · ` : ''}{u.email}</span>
                        </span>
                      </Link>
                    </td>
                    <td className="p-3"><Badge variant="outline">{t(`types.${u.type}`)}</Badge></td>
                    <td className="p-3"><div className="flex max-w-56 flex-wrap gap-1">{u.roles.map((r) => <Badge key={r} variant="secondary">{roleLabel(r)}</Badge>)}</div></td>
                    <td className="p-3"><StatusBadges u={u} /></td>
                    <td className="p-3 text-xs text-muted-foreground">{u.last_login_at ? formatDate(u.last_login_at, { locale, includeTime: true }) : t('never')}</td>
                    <td className="p-3 text-end">
                      <Button asChild size="sm" variant="outline"><Link href={dashboardHref(locale, `admin/users/${u.id}`)}><ShieldCheck className="size-3.5" />{t('manage')}</Link></Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="divide-y md:hidden">
            {data.rows.map((u) => (
              <Link key={u.id} href={dashboardHref(locale, `admin/users/${u.id}`)} className="flex items-center gap-3 p-3">
                <Avatar className="size-10">{u.avatar_url ? <AvatarImage src={u.avatar_url} alt="" /> : null}<AvatarFallback>{initials(u.name)}</AvatarFallback></Avatar>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-medium">{u.name}</span>
                  <span className="block truncate text-xs text-muted-foreground" dir="ltr">{u.email}</span>
                  <span className="mt-1 flex flex-wrap gap-1"><Badge variant="outline">{t(`types.${u.type}`)}</Badge><StatusBadges u={u} /></span>
                </span>
              </Link>
            ))}
          </div>
        </Card>
      ) : null}

      {data && data.meta.last_page > 1 ? (
        <div className="flex items-center justify-center gap-3 text-sm">
          <Button type="button" size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>{t('prev')}</Button>
          <span>{t('pageOf', { page: nf(data.meta.current_page), pages: nf(data.meta.last_page) })}</span>
          <Button type="button" size="sm" variant="outline" disabled={page >= data.meta.last_page} onClick={() => setPage(page + 1)}>{t('next')}</Button>
        </div>
      ) : null}

      <CreateUserDialog key={createKey} open={createOpen} meta={meta} onClose={() => setCreateOpen(false)} onCreated={load} />
    </div>
  );
}

function CreateUserDialog({ open, meta, onClose, onCreated }: { open: boolean; meta: UsersMeta | null; onClose: () => void; onCreated: () => void }) {
  const t = useTranslations('userAdmin');
  const [form, setForm] = useState({ name: '', email: '', username: '', phone: '', password: '', must_change_password: true });
  const [roles, setRoles] = useState<string[]>(['team_member']);
  const [busy, setBusy] = useState(false);
  const [temp, setTemp] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      const res = await createUser({
        ...form,
        username: form.username || undefined,
        phone: form.phone || undefined,
        password: form.password || undefined,
        roles,
      });
      toast.success(t('create.created'));
      onCreated();
      if (res.temporary_password) setTemp(res.temporary_password);
      else onClose();
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
      <DialogContent dir="rtl" className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader><DialogTitle>{t('create.title')}</DialogTitle></DialogHeader>
        {temp ? (
          <TemporaryPasswordNotice password={temp} />
        ) : (
          <form id="create-user-form" className="grid gap-3 sm:grid-cols-2" onSubmit={(e) => void submit(e)}>
            <label className="grid gap-1 text-sm sm:col-span-2">{t('create.name')}<Input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('create.email')}<Input required type="email" dir="ltr" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('create.username')}<Input dir="ltr" value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('create.phone')}<Input dir="ltr" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('create.password')}<Input type="password" dir="ltr" autoComplete="new-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></label>
            <p className="text-xs text-muted-foreground sm:col-span-2"><KeyRound className="me-1 inline size-3.5" />{t('create.passwordHint')}</p>
            {form.password ? (
              <label className="flex items-center gap-2 text-sm sm:col-span-2">
                <Checkbox checked={form.must_change_password} onCheckedChange={(v) => setForm({ ...form, must_change_password: Boolean(v) })} />{t('create.mustChange')}
              </label>
            ) : null}
            <div className="grid gap-2 text-sm sm:col-span-2">
              {t('create.roles')}
              <div className="flex flex-wrap gap-2">
                {(meta?.roles ?? []).map((r) => {
                  const on = roles.includes(r.name);
                  return (
                    <button key={r.name} type="button" onClick={() => setRoles(on ? roles.filter((x) => x !== r.name) : [...roles, r.name])}
                      className={`rounded-full border px-3 py-1 text-xs ${on ? 'border-primary bg-primary/10 text-primary' : 'text-muted-foreground'}`}>
                      {r.label}
                    </button>
                  );
                })}
              </div>
            </div>
          </form>
        )}
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose}>{temp ? t('close') : t('cancel')}</Button>
          {temp ? null : <Button type="submit" form="create-user-form" disabled={busy}>{t('create.submit')}</Button>}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
