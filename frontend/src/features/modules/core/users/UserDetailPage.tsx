'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useLocale, useTranslations } from 'next-intl';
import { ArrowRight, Bell, Briefcase, FileText, History, KeyRound, Lock, LockOpen, Save, Shield, ShieldCheck, Smartphone, User, UserCog } from 'lucide-react';
import { toast } from 'sonner';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { getAxiosMessage } from '@/lib/api-helpers';
import { formatDate } from '@/lib/locale/format-date';
import { dashboardHref } from '@/lib/route-resolver';
import {
  getUser, getUserActivity, getUserSessions, getUsersMeta, lockUser, revokeAllUserSessions, revokeUserSession,
  setUserPassword, setUserRoles, updateUserAccount, updateUserProfile,
  type ActivityRow, type UserDetail, type UserProfile, type UserSession, type UsersMeta,
} from '@/lib/api/users';
import { NotificationPreferencesPanel } from './NotificationPreferencesPanel';
import { ProfileFieldsForm } from './ProfileFieldsForm';
import { ActivityList, SessionsList } from './SessionsActivity';
import { TemporaryPasswordNotice } from './TemporaryPasswordNotice';
import { UserDocumentsPanel } from './UserDocumentsPanel';
import { StatusBadges, initials } from './UsersAdminPage';

function dt(iso: string | null | undefined, locale: string) {
  return iso ? formatDate(iso, { locale, includeTime: true }) : '-';
}

export function UserDetailPage({ id }: { id: string }) {
  const t = useTranslations('userAdmin');
  const locale = useLocale();
  const [user, setUser] = useState<UserDetail | null>(null);
  const [meta, setMeta] = useState<UsersMeta | null>(null);
  const [tab, setTab] = useState('account');
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(() => {
    void getUser(id).then(setUser).catch((e) => setError(getAxiosMessage(e)));
  }, [id]);
  useEffect(reload, [reload]);
  useEffect(() => { void getUsersMeta().then(setMeta).catch(() => undefined); }, []);

  if (error) return <p className="text-sm text-destructive">{error}</p>;
  if (!user) return <p className="text-sm text-muted-foreground">{t('loading')}</p>;

  const roleLabel = (name: string) => meta?.roles.find((r) => r.name === name)?.label ?? name;

  return (
    <div className="space-y-4">
      <Link href={dashboardHref(locale, 'admin/users')} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ArrowRight className="size-4 rtl:rotate-0 ltr:rotate-180" />{t('back')}
      </Link>

      <Card className="shadow-sm">
        <CardContent className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
          <Avatar className="size-16">{user.avatar_url ? <AvatarImage src={user.avatar_url} alt="" /> : null}<AvatarFallback className="text-lg">{initials(user.name)}</AvatarFallback></Avatar>
          <div className="min-w-0 flex-1 space-y-1">
            <p className="truncate text-lg font-bold">{user.name}</p>
            <p className="truncate text-sm text-muted-foreground" dir="ltr">{user.username ? `@${user.username} · ` : ''}{user.email}{user.phone ? ` · ${user.phone}` : ''}</p>
            <div className="flex flex-wrap gap-1">
              <Badge variant="outline">{t(`types.${user.type}`)}</Badge>
              {user.roles.map((r) => <Badge key={r} variant="secondary">{roleLabel(r)}</Badge>)}
              <StatusBadges u={user} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-muted-foreground">
            <span>{t('account.lastLogin')}</span><span>{user.last_login_at ? dt(user.last_login_at, locale) : t('never')}</span>
            <span>{t('account.lastIp')}</span><span dir="ltr">{user.last_login_ip ?? '-'}</span>
            <span>{t('account.createdAt')}</span><span>{dt(user.created_at, locale)}</span>
          </div>
        </CardContent>
      </Card>

      <Tabs value={tab} onValueChange={setTab} dir="rtl">
        <TabsList className="flex h-auto w-full flex-wrap justify-start gap-1 overflow-x-auto">
          <TabsTrigger value="account"><User className="size-4" />{t('tabs.account')}</TabsTrigger>
          <TabsTrigger value="profile"><UserCog className="size-4" />{t('tabs.profile')}</TabsTrigger>
          <TabsTrigger value="documents"><FileText className="size-4" />{t('tabs.documents')}{user.documents_count ? ` (${user.documents_count})` : ''}</TabsTrigger>
          <TabsTrigger value="roles"><Shield className="size-4" />{t('tabs.roles')}</TabsTrigger>
          <TabsTrigger value="notifications"><Bell className="size-4" />{t('tabs.notifications')}</TabsTrigger>
          <TabsTrigger value="security"><KeyRound className="size-4" />{t('tabs.security')}</TabsTrigger>
          <TabsTrigger value="activity"><History className="size-4" />{t('tabs.activity')}</TabsTrigger>
        </TabsList>
        <TabsContent value="account" className="mt-4"><AccountTab user={user} onSaved={setUser} /></TabsContent>
        <TabsContent value="profile" className="mt-4"><ProfileTab user={user} /></TabsContent>
        <TabsContent value="documents" className="mt-4">{tab === 'documents' ? <UserDocumentsPanel scope={user.id} admin /> : null}</TabsContent>
        <TabsContent value="roles" className="mt-4"><RolesTab user={user} meta={meta} onSaved={setUser} /></TabsContent>
        <TabsContent value="notifications" className="mt-4">{tab === 'notifications' ? <NotificationPreferencesPanel scope={user.id} /> : null}</TabsContent>
        <TabsContent value="security" className="mt-4">{tab === 'security' ? <SecurityTab user={user} onChanged={reload} /> : null}</TabsContent>
        <TabsContent value="activity" className="mt-4">{tab === 'activity' ? <ActivityTab id={user.id} /> : null}</TabsContent>
      </Tabs>
    </div>
  );
}

function AccountTab({ user, onSaved }: { user: UserDetail; onSaved: (u: UserDetail) => void }) {
  const t = useTranslations('userAdmin');
  const locale = useLocale();
  const [f, setF] = useState({
    name: user.name, first_name: user.first_name ?? '', last_name: user.last_name ?? '', email: user.email,
    username: user.username ?? '', phone: user.phone ?? '', is_active: user.is_active,
    must_change_password: user.must_change_password, email_verified: Boolean(user.email_verified_at),
  });
  const [busy, setBusy] = useState(false);

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      const next = await updateUserAccount(user.id, { ...f, username: f.username || null, phone: f.phone || null });
      onSaved(next);
      toast.success(t('account.saved'));
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="grid gap-4 xl:grid-cols-3">
      <Card className="shadow-sm xl:col-span-2">
        <CardContent className="p-4">
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={(e) => void save(e)}>
            <label className="grid gap-1 text-sm sm:col-span-2">{t('account.name')}<Input required value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('account.firstName')}<Input value={f.first_name} onChange={(e) => setF({ ...f, first_name: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('account.lastName')}<Input value={f.last_name} onChange={(e) => setF({ ...f, last_name: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('account.email')}<Input required type="email" dir="ltr" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('account.username')}<Input dir="ltr" value={f.username} onChange={(e) => setF({ ...f, username: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('account.phone')}<Input dir="ltr" value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} /></label>
            <span />
            <label className="flex items-start justify-between gap-3 rounded-lg border p-3 text-sm sm:col-span-2">
              <span><span className="block font-medium">{t('account.active')}</span><span className="text-xs text-muted-foreground">{t('account.activeHint')}</span></span>
              <Switch checked={f.is_active} onCheckedChange={(v) => setF({ ...f, is_active: Boolean(v) })} />
            </label>
            <label className="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm">
              {t('account.emailVerified')}<Switch checked={f.email_verified} onCheckedChange={(v) => setF({ ...f, email_verified: Boolean(v) })} />
            </label>
            <label className="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm">
              {t('account.mustChange')}<Switch checked={f.must_change_password} onCheckedChange={(v) => setF({ ...f, must_change_password: Boolean(v) })} />
            </label>
            <div className="flex justify-end sm:col-span-2"><Button type="submit" disabled={busy}><Save className="size-4" />{t('account.save')}</Button></div>
          </form>
        </CardContent>
      </Card>
      <div className="space-y-4">
        <Card className="shadow-sm">
          <CardContent className="space-y-2 p-4 text-sm">
            <h3 className="flex items-center gap-2 font-semibold"><Briefcase className="size-4 text-primary" />{t('account.linked')}</h3>
            {user.employee ? (
              <Link className="block rounded-lg border p-2 hover:bg-muted/40" href={dashboardHref(locale, `hrm/staff/${user.employee.id}`)}>
                {t('account.employee')}: {user.employee.employee_code ?? `#${user.employee.id}`} {user.employee.position ? `· ${user.employee.position}` : ''}
              </Link>
            ) : null}
            {user.crm_accounts.map((a) => (
              <Link key={a.id} className="block rounded-lg border p-2 hover:bg-muted/40" href={dashboardHref(locale, `crm/customers/${a.id}`)}>
                {t('account.crmAccount')}: {a.name}
              </Link>
            ))}
            {!user.employee && user.crm_accounts.length === 0 ? <p className="text-xs text-muted-foreground">{t('account.noLinks')}</p> : null}
          </CardContent>
        </Card>
        <Card className="shadow-sm">
          <CardContent className="space-y-1 p-4 text-sm">
            <h3 className="flex items-center gap-2 font-semibold"><Smartphone className="size-4 text-primary" />{t('twoFactor.title')}</h3>
            <p className="text-xs text-muted-foreground">{user.two_factor.required ? t('twoFactor.required') : t('twoFactor.notRequired')}</p>
            {!user.two_factor.has_phone ? <p className="text-xs text-amber-600">{t('twoFactor.noPhone')}</p> : null}
            <p className="pt-2 text-xs text-muted-foreground">{t('account.passwordChanged')}: {dt(user.password_changed_at, locale)}</p>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function ProfileTab({ user }: { user: UserDetail }) {
  const t = useTranslations('userAdmin');
  const [p, setP] = useState<UserProfile>(user.profile ?? {});
  const [busy, setBusy] = useState(false);
  async function save() {
    setBusy(true);
    try {
      setP(await updateUserProfile(user.id, p));
      toast.success(t('profile.saved'));
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }
  return (
    <div className="space-y-4">
      <ProfileFieldsForm value={p} onChange={setP} admin />
      <div className="flex justify-end"><Button type="button" disabled={busy} onClick={() => void save()}><Save className="size-4" />{t('profile.save')}</Button></div>
    </div>
  );
}

function RolesTab({ user, meta, onSaved }: { user: UserDetail; meta: UsersMeta | null; onSaved: (u: UserDetail) => void }) {
  const t = useTranslations('userAdmin');
  const locale = useLocale();
  const [roles, setRoles] = useState<string[]>(user.roles);
  const [busy, setBusy] = useState(false);
  async function save() {
    setBusy(true);
    try {
      onSaved(await setUserRoles(user.id, roles));
      toast.success(t('roles.saved'));
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }
  return (
    <div className="grid gap-4 xl:grid-cols-3">
      <Card className="shadow-sm xl:col-span-2">
        <CardContent className="space-y-3 p-4">
          <h3 className="flex items-center gap-2 text-sm font-semibold"><ShieldCheck className="size-4 text-primary" />{t('roles.title')}</h3>
          <p className="text-xs text-muted-foreground">{t('roles.hint')}</p>
          <div className="grid gap-2 sm:grid-cols-2">
            {(meta?.roles ?? []).map((r) => (
              <label key={r.name} className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                <Checkbox checked={roles.includes(r.name)} onCheckedChange={(v) => setRoles(v ? [...roles, r.name] : roles.filter((x) => x !== r.name))} />
                <span className="min-w-0"><span className="block font-medium">{r.label}</span><span className="block text-xs text-muted-foreground" dir="ltr">{r.name}</span></span>
              </label>
            ))}
          </div>
          <div className="flex justify-end"><Button type="button" disabled={busy} onClick={() => void save()}><Save className="size-4" />{t('roles.save')}</Button></div>
        </CardContent>
      </Card>
      <Card className="shadow-sm">
        <CardContent className="space-y-2 p-4">
          <h3 className="text-sm font-semibold">{t('roles.effective')}</h3>
          <p className="text-xs text-muted-foreground">{t('roles.count', { n: user.permissions.length.toLocaleString(locale === 'fa' ? 'fa-IR' : 'en-US') })}</p>
          <div className="flex max-h-80 flex-wrap gap-1 overflow-y-auto">
            {user.permissions.map((p) => <Badge key={p} variant="outline" className="font-mono text-[10px]" dir="ltr">{p}</Badge>)}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function SecurityTab({ user, onChanged }: { user: UserDetail; onChanged: () => void }) {
  const t = useTranslations('userAdmin');
  const locale = useLocale();
  const [mode, setMode] = useState<'generate' | 'set'>('generate');
  const [pw, setPw] = useState({ password: '', password_confirmation: '', must_change_password: true, revoke_sessions: true });
  const [temp, setTemp] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [lock, setLock] = useState({ until: '', reason: '' });
  const [sessions, setSessions] = useState<UserSession[] | null>(null);

  const loadSessions = useCallback(() => {
    void getUserSessions(user.id).then(setSessions).catch((e) => toast.error(getAxiosMessage(e)));
  }, [user.id]);
  useEffect(loadSessions, [loadSessions]);

  async function submitPassword(e: React.FormEvent) {
    e.preventDefault();
    if (mode === 'set') {
      if (pw.password.length < 8) return toast.error(t('password.minLength'));
      if (pw.password !== pw.password_confirmation) return toast.error(t('password.mismatch'));
    }
    setBusy(true);
    setTemp(null);
    try {
      const res = await setUserPassword(user.id, mode === 'generate' ? { mode, revoke_sessions: pw.revoke_sessions } : { mode, ...pw });
      setTemp(res.temporary_password);
      setPw({ ...pw, password: '', password_confirmation: '' });
      toast.success(t('password.done'));
      loadSessions();
      onChanged();
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function toggleLock(locked: boolean) {
    setBusy(true);
    try {
      await lockUser(user.id, locked ? { locked, until: lock.until ? `${lock.until} 23:59:59` : null, reason: lock.reason || null } : { locked });
      toast.success(locked ? t('lock.lockedNow') : t('lock.unlockedNow'));
      onChanged();
      loadSessions();
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="grid gap-4 xl:grid-cols-2">
      <Card className="shadow-sm">
        <CardContent className="space-y-3 p-4">
          <h3 className="flex items-center gap-2 text-sm font-semibold"><KeyRound className="size-4 text-primary" />{t('password.title')}</h3>
          <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant={mode === 'generate' ? 'default' : 'outline'} onClick={() => setMode('generate')}>{t('password.generate')}</Button>
            <Button type="button" size="sm" variant={mode === 'set' ? 'default' : 'outline'} onClick={() => setMode('set')}>{t('password.set')}</Button>
          </div>
          <form className="grid gap-3" onSubmit={(e) => void submitPassword(e)}>
            {mode === 'set' ? (
              <>
                <label className="grid gap-1 text-sm">{t('password.new')}<Input type="password" dir="ltr" autoComplete="new-password" value={pw.password} onChange={(e) => setPw({ ...pw, password: e.target.value })} /></label>
                <label className="grid gap-1 text-sm">{t('password.confirm')}<Input type="password" dir="ltr" autoComplete="new-password" value={pw.password_confirmation} onChange={(e) => setPw({ ...pw, password_confirmation: e.target.value })} /></label>
                <label className="flex items-center gap-2 text-sm"><Checkbox checked={pw.must_change_password} onCheckedChange={(v) => setPw({ ...pw, must_change_password: Boolean(v) })} />{t('password.mustChange')}</label>
              </>
            ) : null}
            <label className="flex items-center gap-2 text-sm"><Checkbox checked={pw.revoke_sessions} onCheckedChange={(v) => setPw({ ...pw, revoke_sessions: Boolean(v) })} />{t('password.revoke')}</label>
            <div className="flex justify-end"><Button type="submit" disabled={busy}>{t('password.submit')}</Button></div>
          </form>
          {temp ? <TemporaryPasswordNotice password={temp} /> : null}
        </CardContent>
      </Card>

      <Card className="shadow-sm">
        <CardContent className="space-y-3 p-4">
          <h3 className="flex items-center gap-2 text-sm font-semibold"><Lock className="size-4 text-primary" />{t('lock.title')}</h3>
          {user.locked ? (
            <div className="space-y-2 rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm">
              <p>{t('lock.lockedUntil')}: {user.locked_until && new Date(user.locked_until).getFullYear() < 2100 ? dt(user.locked_until, locale) : t('lock.forever')}</p>
              {user.lock_reason ? <p className="text-xs text-muted-foreground">{t('lock.reason')}: {user.lock_reason}</p> : null}
              <Button type="button" variant="outline" disabled={busy} onClick={() => void toggleLock(false)}><LockOpen className="size-4" />{t('lock.unlock')}</Button>
            </div>
          ) : (
            <div className="grid gap-3">
              <div className="grid gap-1 text-sm">{t('lock.until')}<LocaleDatePicker value={lock.until || null} onChange={(v) => setLock({ ...lock, until: v })} /></div>
              <label className="grid gap-1 text-sm">{t('lock.reason')}<Input value={lock.reason} onChange={(e) => setLock({ ...lock, reason: e.target.value })} /></label>
              <div className="flex justify-end"><Button type="button" variant="outline" className="text-destructive" disabled={busy} onClick={() => void toggleLock(true)}><Lock className="size-4" />{t('lock.lock')}</Button></div>
            </div>
          )}
        </CardContent>
      </Card>

      <div className="xl:col-span-2">
        <SessionsList
          rows={sessions}
          onRevoke={(sid) => void revokeUserSession(user.id, sid).then(() => { toast.success(t('sessions.revoked')); loadSessions(); }).catch((e) => toast.error(getAxiosMessage(e)))}
          onRevokeAll={() => void revokeAllUserSessions(user.id).then(() => { toast.success(t('sessions.revoked')); loadSessions(); }).catch((e) => toast.error(getAxiosMessage(e)))}
        />
      </div>
    </div>
  );
}

function ActivityTab({ id }: { id: number }) {
  const [rows, setRows] = useState<ActivityRow[] | null>(null);
  useEffect(() => { void getUserActivity(id).then(setRows).catch(() => setRows([])); }, [id]);
  return <ActivityList rows={rows} />;
}
