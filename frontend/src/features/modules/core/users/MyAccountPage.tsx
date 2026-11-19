'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { Bell, Camera, FileText, KeyRound, Save, User } from 'lucide-react';
import { toast } from 'sonner';
import apiClient from '@/lib/api-client';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { getAxiosMessage } from '@/lib/api-helpers';
import {
  changeMyPassword, getMyAccount, getMySessions, revokeMyOtherSessions, revokeMySession, updateMyAccount,
  type UserProfile, type UserSession,
} from '@/lib/api/users';
import { NotificationPreferencesPanel } from './NotificationPreferencesPanel';
import { ProfileFieldsForm } from './ProfileFieldsForm';
import { SessionsList } from './SessionsActivity';
import { UserDocumentsPanel } from './UserDocumentsPanel';
import { initials } from './UsersAdminPage';

type Me = Awaited<ReturnType<typeof getMyAccount>>;

/** Self-service account page (dashboard /profile) for staff, customers and every user. */
export function MyAccountPage() {
  const t = useTranslations('userAdmin');
  const [me, setMe] = useState<Me | null>(null);
  const [tab, setTab] = useState('profile');

  useEffect(() => { void getMyAccount().then(setMe).catch((e) => toast.error(getAxiosMessage(e))); }, []);
  if (!me) return <p className="text-sm text-muted-foreground">{t('loading')}</p>;

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">{t('me.intro')}</p>
      <Tabs value={tab} onValueChange={setTab} dir="rtl">
        <TabsList className="flex h-auto w-full flex-wrap justify-start gap-1">
          <TabsTrigger value="profile"><User className="size-4" />{t('me.tabs.profile')}</TabsTrigger>
          <TabsTrigger value="security"><KeyRound className="size-4" />{t('me.tabs.security')}</TabsTrigger>
          <TabsTrigger value="documents"><FileText className="size-4" />{t('me.tabs.documents')}</TabsTrigger>
          <TabsTrigger value="notifications"><Bell className="size-4" />{t('me.tabs.notifications')}</TabsTrigger>
        </TabsList>
        <TabsContent value="profile" className="mt-4"><MyProfileTab me={me} onSaved={setMe} /></TabsContent>
        <TabsContent value="security" className="mt-4">{tab === 'security' ? <MySecurityTab /> : null}</TabsContent>
        <TabsContent value="documents" className="mt-4">{tab === 'documents' ? <UserDocumentsPanel scope="me" /> : null}</TabsContent>
        <TabsContent value="notifications" className="mt-4">{tab === 'notifications' ? <NotificationPreferencesPanel scope="me" /> : null}</TabsContent>
      </Tabs>
    </div>
  );
}

function MyProfileTab({ me, onSaved }: { me: Me; onSaved: (m: Me) => void }) {
  const t = useTranslations('userAdmin');
  const tp = useTranslations('profile');
  const [f, setF] = useState({ name: me.name, first_name: me.first_name ?? '', last_name: me.last_name ?? '', email: me.email, username: me.username ?? '', phone: me.phone ?? '' });
  const [profile, setProfile] = useState<UserProfile>(me.profile ?? {});
  const [locale, setLocale] = useState('fa');
  const [avatar, setAvatar] = useState<string | null>(me.avatar_url ?? null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    void apiClient.get('/v1/core/users/me/preferences').then((r) => {
      const prefs = (r.data as { preferences?: { locale?: string } })?.preferences;
      if (prefs?.locale) setLocale(prefs.locale);
    }).catch(() => undefined);
  }, []);

  async function save() {
    setBusy(true);
    try {
      const { admin_notes: _ignored, ...own } = profile;
      void _ignored;
      const next = await updateMyAccount({ ...f, username: f.username || null, phone: f.phone || null, ...own });
      await apiClient.patch('/v1/core/users/me/preferences', { preferences: { locale } }).catch(() => undefined);
      onSaved(next as Me);
      toast.success(t('me.saved'));
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function uploadAvatar(file: File) {
    const fd = new FormData();
    fd.append('avatar', file);
    try {
      const res = await apiClient.post('/v1/core/users/me/avatar', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      setAvatar((res.data as { avatar_url?: string })?.avatar_url ?? avatar);
      toast.success(t('me.avatarUploaded'));
    } catch (err) {
      toast.error(getAxiosMessage(err));
    }
  }

  return (
    <div className="space-y-4">
      <Card className="shadow-sm">
        <CardContent className="grid gap-4 p-4 lg:grid-cols-[auto_1fr]">
          <div className="flex flex-col items-center gap-2">
            <Avatar className="size-20">{avatar ? <AvatarImage src={avatar} alt="" /> : null}<AvatarFallback className="text-xl">{initials(f.name)}</AvatarFallback></Avatar>
            <label className="inline-flex cursor-pointer items-center gap-1 text-xs text-primary">
              <Camera className="size-3.5" />{t('me.avatar')}
              <input type="file" accept="image/*" className="hidden" onChange={(e) => { const file = e.target.files?.[0]; if (file) void uploadAvatar(file); }} />
            </label>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="grid gap-1 text-sm sm:col-span-2">{t('account.name')}<Input value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('account.firstName')}<Input value={f.first_name} onChange={(e) => setF({ ...f, first_name: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('account.lastName')}<Input value={f.last_name} onChange={(e) => setF({ ...f, last_name: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('account.email')}<Input type="email" dir="ltr" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('account.username')}<Input dir="ltr" value={f.username} onChange={(e) => setF({ ...f, username: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">{t('account.phone')}<Input dir="ltr" value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} /></label>
            <label className="grid gap-1 text-sm">
              {t('me.locale')}
              <Select value={locale} onValueChange={setLocale}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent><SelectItem value="fa">{tp('localeFa')}</SelectItem><SelectItem value="en">{tp('localeEn')}</SelectItem></SelectContent>
              </Select>
            </label>
          </div>
        </CardContent>
      </Card>
      <ProfileFieldsForm value={profile} onChange={setProfile} />
      <div className="flex justify-end"><Button type="button" disabled={busy} onClick={() => void save()}><Save className="size-4" />{t('me.save')}</Button></div>
    </div>
  );
}

export function ChangePasswordForm({ onDone }: { onDone?: () => void }) {
  const t = useTranslations('userAdmin');
  const [f, setF] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [busy, setBusy] = useState(false);
  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (f.password.length < 8) return toast.error(t('password.minLength'));
    if (f.password !== f.password_confirmation) return toast.error(t('password.mismatch'));
    setBusy(true);
    try {
      await changeMyPassword(f);
      setF({ current_password: '', password: '', password_confirmation: '' });
      toast.success(t('me.passwordChanged'));
      onDone?.();
    } catch (err) {
      toast.error(getAxiosMessage(err));
    } finally {
      setBusy(false);
    }
  }
  return (
    <form className="grid gap-3" onSubmit={(e) => void submit(e)}>
      <label className="grid gap-1 text-sm">{t('password.current')}<Input type="password" dir="ltr" autoComplete="current-password" required value={f.current_password} onChange={(e) => setF({ ...f, current_password: e.target.value })} /></label>
      <label className="grid gap-1 text-sm">{t('password.new')}<Input type="password" dir="ltr" autoComplete="new-password" required value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} /></label>
      <label className="grid gap-1 text-sm">{t('password.confirm')}<Input type="password" dir="ltr" autoComplete="new-password" required value={f.password_confirmation} onChange={(e) => setF({ ...f, password_confirmation: e.target.value })} /></label>
      <div className="flex justify-end"><Button type="submit" disabled={busy}><KeyRound className="size-4" />{t('password.change')}</Button></div>
    </form>
  );
}

function MySecurityTab() {
  const t = useTranslations('userAdmin');
  const [sessions, setSessions] = useState<UserSession[] | null>(null);
  const load = useCallback(() => { void getMySessions().then(setSessions).catch(() => setSessions([])); }, []);
  useEffect(load, [load]);
  return (
    <div className="grid gap-4 xl:grid-cols-2">
      <Card className="shadow-sm">
        <CardContent className="space-y-3 p-4">
          <h3 className="flex items-center gap-2 text-sm font-semibold"><KeyRound className="size-4 text-primary" />{t('password.change')}</h3>
          <ChangePasswordForm onDone={load} />
        </CardContent>
      </Card>
      <SessionsList
        rows={sessions}
        revokeAllLabel={t('sessions.revokeOthers')}
        onRevoke={(id) => void revokeMySession(id).then(() => { toast.success(t('sessions.revoked')); load(); }).catch((e) => toast.error(getAxiosMessage(e)))}
        onRevokeAll={() => void revokeMyOtherSessions().then(() => { toast.success(t('sessions.revoked')); load(); }).catch((e) => toast.error(getAxiosMessage(e)))}
      />
    </div>
  );
}
