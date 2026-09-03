'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboardHref } from '@/lib/route-resolver';
import {
  SETTINGS_HUB_TABS,
  resolveSettingsTab,
  type SettingsHubId,
} from '@/features/modules/admin/settings/settings-hub-config';
import { normalizeListPayload } from '@/lib/list-utils';

type SettingsPayload = Record<string, Record<string, string>>;

type Row = Record<string, unknown>;

export function SettingsPageView({ hub, initialTab }: { hub?: SettingsHubId; initialTab?: string } = {}) {
  const t = useTranslations('settings.tabs');
  const tAuto = useTranslations();
  const tAuth = useTranslations('settings.tabs.authFields');
  const tHub = useTranslations('settings.hub');
  const tHosting = useTranslations('hosting');
  const tCommon = useTranslations('common');
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const visibleTabs = hub ? SETTINGS_HUB_TABS[hub] : Object.values(SETTINGS_HUB_TABS).flat().filter((v, i, a) => a.indexOf(v) === i);
  const resolvedInitial = resolveSettingsTab(initialTab);
  const defaultTab = resolvedInitial && visibleTabs.includes(resolvedInitial) ? resolvedInitial : visibleTabs[0] ?? 'auth';
  const [data, setData] = useState<SettingsPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState(defaultTab);

  const [wlApp, setWlApp] = useState('');
  const [wlLogo, setWlLogo] = useState('');
  const [wlColor, setWlColor] = useState('#0ea5e9');

  const [notifEmail, setNotifEmail] = useState(true);
  const [notifTasks, setNotifTasks] = useState(true);

  const [canned, setCanned] = useState<Row[]>([]);
  const [positions, setPositions] = useState<Row[]>([]);
  const [categories, setCategories] = useState<Row[]>([]);
  const [forms, setForms] = useState<Row[]>([]);
  const [workflow, setWorkflow] = useState<Row[]>([]);
  const [automations, setAutomations] = useState<Row[]>([]);

  const [editedGeneral, setEditedGeneral] = useState<Record<string, string>>({});
  const [visitorEnabled, setVisitorEnabled] = useState(true);
  const [leadDefaultStatus, setLeadDefaultStatus] = useState('');
  const [leadAutoAssign, setLeadAutoAssign] = useState('0');

  const [smsProvider, setSmsProvider] = useState('log');
  const [smsUsername, setSmsUsername] = useState('');
  const [smsPassword, setSmsPassword] = useState('');
  const [smsApiKey, setSmsApiKey] = useState('');
  const [smsSender, setSmsSender] = useState('');
  const [smsSaving, setSmsSaving] = useState(false);

  const [dialog, setDialog] = useState<{ kind: string; row: Row | null } | null>(null);
  const [formTitle, setFormTitle] = useState('');
  const [formBody, setFormBody] = useState('');
  const [formName, setFormName] = useState('');
  const [formColor, setFormColor] = useState('#888888');
  const [formSort, setFormSort] = useState('0');
  const [formSlug, setFormSlug] = useState('');
  const [autoTrigger, setAutoTrigger] = useState('lead.status_changed');
  const [formErr, setFormErr] = useState<string | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<{ kind: string; id: number } | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get('/v1/core/settings');
      const payload = unwrapData<SettingsPayload>(res);
      setData(payload);
      const b = payload?.branding ?? {};
      setWlApp(String(b.wl_app_name ?? b.app_name ?? ''));
      setWlLogo(String(b.wl_logo_url ?? b.logo_url ?? ''));
      setWlColor(String(b.wl_primary_color ?? b.primary_color ?? '#0ea5e9'));

      const gen = payload?.general ?? {};
      const genFlat: Record<string, string> = {};
      for (const [k, v] of Object.entries(gen)) {
        genFlat[k] = String(v ?? '');
      }
      setEditedGeneral(genFlat);

      const smsPayload = payload?.sms ?? {};
      setSmsProvider(String(smsPayload.provider ?? 'log'));
      setSmsUsername(String(smsPayload.username ?? ''));
      setSmsPassword(String(smsPayload.password ?? ''));
      setSmsApiKey(String(smsPayload.api_key ?? ''));
      setSmsSender(String(smsPayload.sender ?? ''));

      const [cRes, pRes, tRes, fRes, wRes, aRes] = await Promise.all([
        apiClient.get('/v1/core/canned-responses'),
        apiClient.get('/v1/core/positions'),
        apiClient.get('/v1/core/task-categories'),
        apiClient.get('/v1/projects/forms').catch(() => ({ data: { data: [] } })),
        apiClient.get('/v1/projects/kanban/data').catch(() => ({ data: { data: { columns: [] } } })),
        apiClient.get('/v1/core/automation/rules').catch(() => ({ data: { data: [] } })),
      ]);
      setCanned(unwrapData<Row[]>(cRes) as Row[]);
      setPositions(unwrapData<Row[]>(pRes) as Row[]);
      setCategories(unwrapData<Row[]>(tRes) as Row[]);
      setForms(normalizeListPayload(unwrapData(fRes)));
      const kanban = unwrapData<{ columns?: Row[] }>(wRes as { data: unknown });
      setWorkflow(Array.isArray(kanban?.columns) ? kanban.columns : []);
      setAutomations(normalizeListPayload(unwrapData(aRes)));

      const visitor = payload?.visitor_tracking ?? {};
      setVisitorEnabled(String(visitor.enable_visitor_statistics ?? '1') !== '0');
      const leads = payload?.leads ?? {};
      setLeadDefaultStatus(String(leads.default_status ?? ''));
      setLeadAutoAssign(String(leads.auto_assign ?? '0'));

      try {
        const prefRes = await apiClient.get('/v1/core/users/me/preferences');
        const prefBody = prefRes.data as { data?: { preferences?: Record<string, boolean> } };
        const prefs = prefBody.data?.preferences;
        if (prefs) {
          setNotifEmail(!!prefs.email_digest);
          setNotifTasks(!!prefs.task_reminders);
        }
      } catch { /* ignore */ }
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function saveGroup(group: string, settings: Record<string, unknown>) {
    setError(null);
    try {
      const flat: Record<string, string> = {};
      for (const [k, v] of Object.entries(settings)) {
        flat[k] = typeof v === 'string' ? v : JSON.stringify(v);
      }
      await apiClient.put('/v1/core/settings', { group, settings: flat });
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function saveSmsSettings() {
    setSmsSaving(true);
    setError(null);
    try {
      const body: Record<string, string> = {
        provider: smsProvider,
        sender: smsSender,
      };
      if (smsProvider === 'melipayamak') {
        body.username = smsUsername;
        if (smsPassword) {
          body.password = smsPassword;
        }
      }
      if (smsProvider === 'parsgreen' && smsApiKey) {
        body.api_key = smsApiKey;
      }
      await apiClient.put('/v1/integrations/sms/settings', body);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setSmsSaving(false);
    }
  }

  async function saveWhiteLabel() {
    setError(null);
    try {
      await apiClient.put('/v1/core/settings/white-label', {
        app_name: wlApp || null,
        logo_url: wlLogo || null,
        primary_color: wlColor || null,
      });
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  async function saveNotificationPrefs() {
    setError(null);
    try {
      await apiClient.patch('/v1/core/users/me/preferences', {
        preferences: {
          email_digest: notifEmail,
          task_reminders: notifTasks,
        },
      });
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  function openCreate(kind: string) {
    setDialog({ kind, row: null });
    setFormTitle('');
    setFormBody('');
    setFormName('');
    setFormColor('#888888');
    setFormSort('0');
    setFormSlug('');
    setAutoTrigger('lead.status_changed');
    setFormErr(null);
  }

  function openEdit(kind: string, row: Row) {
    setDialog({ kind, row });
    setFormTitle(String(row.title ?? row.name ?? ''));
    setFormBody(String(row.body ?? ''));
    setFormName(String(row.name ?? ''));
    setFormColor(String(row.color ?? '#888888'));
    setFormSort(String(row.sort_order ?? 0));
    setFormSlug(String(row.slug ?? ''));
    setAutoTrigger(String(row.trigger ?? 'lead.status_changed'));
    setFormErr(null);
  }

  async function saveCrud() {
    if (!dialog) {
      return;
    }
    setFormErr(null);
    const id = dialog.row?.id ? Number(dialog.row.id) : null;
    try {
      if (dialog.kind === 'canned') {
        if (id) {
          await apiClient.put(`/v1/core/canned-responses/${id}`, { title: formTitle, body: formBody });
        } else {
          await apiClient.post('/v1/core/canned-responses', { title: formTitle, body: formBody });
        }
      } else if (dialog.kind === 'position') {
        if (id) {
          await apiClient.put(`/v1/core/positions/${id}`, { title: formTitle });
        } else {
          await apiClient.post('/v1/core/positions', { title: formTitle });
        }
      } else if (dialog.kind === 'category') {
        const payload = {
          name: formName,
          color: formColor,
          sort_order: Number(formSort || 0),
        };
        if (id) {
          await apiClient.put(`/v1/core/task-categories/${id}`, payload);
        } else {
          await apiClient.post('/v1/core/task-categories', payload);
        }
      } else if (dialog.kind === 'form') {
        const payload = {
          title: formTitle,
          slug: formSlug || formTitle.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''),
          fields: dialog.row?.fields ?? [{ name: 'name', type: 'text', label: 'Name' }],
          is_active: true,
        };
        if (id) {
          await apiClient.patch(`/v1/projects/forms/${id}`, payload);
        } else {
          await apiClient.post('/v1/projects/forms', payload);
        }
      } else if (dialog.kind === 'workflow') {
        if (id) {
          await apiClient.patch(`/v1/projects/kanban/columns/${id}`, { name: formName, color: formColor });
        } else {
          await apiClient.post('/v1/projects/workflow/statuses', { name: formName, color: formColor });
        }
      } else if (dialog.kind === 'automation') {
        const payload = {
          name: formName || formTitle,
          trigger: autoTrigger,
          actions: dialog.row?.actions ?? [{ type: 'notify', channel: 'log' }],
          is_active: true,
        };
        if (id) {
          await apiClient.patch(`/v1/core/automation/rules/${id}`, payload);
        } else {
          await apiClient.post('/v1/core/automation/rules', payload);
        }
      }
      setDialog(null);
      void load();
    } catch (e) {
      setFormErr(getAxiosMessage(e));
    }
  }

  async function confirmDelete() {
    if (!deleteTarget) {
      return;
    }
    const { kind, id } = deleteTarget;
    try {
      if (kind === 'canned') {
        await apiClient.delete(`/v1/core/canned-responses/${id}`);
      } else if (kind === 'position') {
        await apiClient.delete(`/v1/core/positions/${id}`);
      } else if (kind === 'category') {
        await apiClient.delete(`/v1/core/task-categories/${id}`);
      } else if (kind === 'form') {
        await apiClient.delete(`/v1/projects/forms/${id}`);
      } else if (kind === 'workflow') {
        await apiClient.delete(`/v1/projects/workflow/statuses/${id}`);
      } else if (kind === 'automation') {
        await apiClient.delete(`/v1/core/automation/rules/${id}`);
      }
      setDeleteTarget(null);
      void load();
    } catch (e) {
      setError(getAxiosMessage(e));
    }
  }

  const g = data?.general ?? {};
  const auth = data?.auth ?? {};
  const pay = data?.payment ?? {};

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle>{hub ? tHub(hub) : tHub('title')}</CardTitle>
          <CardDescription>{tHub('subtitle')}</CardDescription>
        </div>
        <Button type="button" variant="outline" size="sm" onClick={() => void load()} disabled={loading}>
          {tCommon('loading').replace('…', '') || 'Refresh'}
        </Button>
      </CardHeader>
      <CardContent>
        {error ? <p className="mb-2 text-sm text-destructive">{error}</p> : null}
        <Tabs value={tab} onValueChange={setTab}>
          <TabsList className="flex h-auto flex-wrap gap-1">
            {visibleTabs.includes('general') ? <TabsTrigger value="general">{t('general')}</TabsTrigger> : null}
            {visibleTabs.includes('auth') ? <TabsTrigger value="auth">{t('auth')}</TabsTrigger> : null}
            {visibleTabs.includes('style') ? <TabsTrigger value="style">{t('style')}</TabsTrigger> : null}
            {visibleTabs.includes('visitor') ? <TabsTrigger value="visitor">{t('visitor')}</TabsTrigger> : null}
            {visibleTabs.includes('notifications') ? <TabsTrigger value="notifications">{t('notifications')}</TabsTrigger> : null}
            {visibleTabs.includes('sms') ? <TabsTrigger value="sms">{t('sms')}</TabsTrigger> : null}
            {visibleTabs.includes('payment') ? <TabsTrigger value="payment">{t('payment')}</TabsTrigger> : null}
            {visibleTabs.includes('canned') ? <TabsTrigger value="canned">{t('canned')}</TabsTrigger> : null}
            {visibleTabs.includes('forms') ? <TabsTrigger value="forms">{t('forms')}</TabsTrigger> : null}
            {visibleTabs.includes('leads') ? <TabsTrigger value="leads">{t('leads')}</TabsTrigger> : null}
            {visibleTabs.includes('workflow') ? <TabsTrigger value="workflow">{t('workflow')}</TabsTrigger> : null}
            {visibleTabs.includes('positions') ? <TabsTrigger value="positions">{t('positions')}</TabsTrigger> : null}
            {visibleTabs.includes('taskcat') ? <TabsTrigger value="taskcat">{t('taskcat')}</TabsTrigger> : null}
            {visibleTabs.includes('automations') ? <TabsTrigger value="automations">{t('automations')}</TabsTrigger> : null}
            {visibleTabs.includes('coreUpdate') ? <TabsTrigger value="coreUpdate">{t('coreUpdate')}</TabsTrigger> : null}
            {visibleTabs.includes('raw') ? <TabsTrigger value="raw">{t('raw')}</TabsTrigger> : null}
            {visibleTabs.includes('hosting') ? <TabsTrigger value="hosting">{t('hosting')}</TabsTrigger> : null}
            {visibleTabs.includes('bots') ? <TabsTrigger value="bots">{t('bots')}</TabsTrigger> : null}
          </TabsList>

          <TabsContent value="general" className="space-y-3 pt-4">
            <p className="text-xs text-muted-foreground">{tAuto('auto.settings_view.s_93292ef9')}</p>
            <div className="grid gap-2 sm:grid-cols-2">
              {Object.entries(editedGeneral).map(([k, v]) => (
                <div key={k}>
                  <p className="text-xs text-muted-foreground">{k}</p>
                  <Input
                    value={v}
                    onChange={(e) => setEditedGeneral((prev) => ({ ...prev, [k]: e.target.value }))}
                    className="font-mono text-xs"
                    dir="ltr"
                  />
                </div>
              ))}
            </div>
            <Button type="button" size="sm" onClick={() => void saveGroup('general', editedGeneral)}>
              {tAuto('auto.settings_view.s_81d37fc7')}
            </Button>
          </TabsContent>

          <TabsContent value="style" className="space-y-3 pt-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{tAuto('auto.settings_view.s_f2dd5cd5')}</p>
                <Input value={wlApp} onChange={(e) => setWlApp(e.target.value)} />
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{tAuto('auto.settings_view.s_4c029f68')}</p>
                <Input value={wlLogo} onChange={(e) => setWlLogo(e.target.value)} dir="ltr" />
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{tAuto('auto.settings_view.s_7d9bff07')}</p>
                <Input type="color" className="h-10 w-24 p-1" value={wlColor} onChange={(e) => setWlColor(e.target.value)} />
              </div>
            </div>
            <Button type="button" onClick={() => void saveWhiteLabel()}>
              {tAuto('auto.settings_view.s_801dead6')}
            </Button>
          </TabsContent>

          <TabsContent value="notifications" className="space-y-3 pt-4">
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={notifEmail} onChange={(e) => setNotifEmail(e.target.checked)} />
              {tAuto('auto.settings_view.s_8a9fb6b4')}
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={notifTasks} onChange={(e) => setNotifTasks(e.target.checked)} />
              {tAuto('auto.settings_view.s_d9e95773')}
            </label>
            <Button type="button" variant="secondary" size="sm" onClick={() => void saveNotificationPrefs()}>
              {tAuto('auto.settings_view.s_2e8c970e')}
            </Button>
          </TabsContent>

          <TabsContent value="auth" className="space-y-3 pt-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{tAuth('otpEnabled')}</p>
                <Select value={String(auth.auth_otp_enabled ?? '0')} onValueChange={(v) => void saveGroup('auth', {...auth, auth_otp_enabled: v})}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="1">{tAuth('on')}</SelectItem>
                    <SelectItem value="0">{tAuth('off')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{tAuth('twoFactor')}</p>
                <Select value={String(auth.auth_2fa_required ?? '0')} onValueChange={(v) => void saveGroup('auth', {...auth, auth_2fa_required: v})}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="1">{tAuth('on')}</SelectItem>
                    <SelectItem value="0">{tAuth('off')}</SelectItem>
                  </SelectContent>
                </Select>
                <p className="mt-1 text-xs text-muted-foreground">{tAuth('twoFactorHint')}</p>
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{tAuth('passwordMin')}</p>
                <Input defaultValue={String(auth.auth_password_min_length ?? '8')} onBlur={(e) => void saveGroup('auth', {...auth, auth_password_min_length: e.target.value})} dir="ltr" />
              </div>
            </div>
          </TabsContent>
          <TabsContent value="sms" className="space-y-3 pt-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <p className="mb-1 text-xs text-muted-foreground">provider</p>
                <Input value={smsProvider} onChange={(e) => setSmsProvider(e.target.value)} dir="ltr" />
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">sender</p>
                <Input value={smsSender} onChange={(e) => setSmsSender(e.target.value)} dir="ltr" />
              </div>
              {smsProvider === 'melipayamak' ? (
                <>
                  <div>
                    <p className="mb-1 text-xs text-muted-foreground">username</p>
                    <Input value={smsUsername} onChange={(e) => setSmsUsername(e.target.value)} dir="ltr" />
                  </div>
                  <div>
                    <p className="mb-1 text-xs text-muted-foreground">password</p>
                    <Input type="password" value={smsPassword} onChange={(e) => setSmsPassword(e.target.value)} dir="ltr" />
                  </div>
                </>
              ) : null}
              {smsProvider === 'parsgreen' ? (
                <div className="sm:col-span-2">
                  <p className="mb-1 text-xs text-muted-foreground">api_key</p>
                  <Input value={smsApiKey} onChange={(e) => setSmsApiKey(e.target.value)} dir="ltr" />
                </div>
              ) : null}
            </div>
            <Button type="button" size="sm" disabled={smsSaving} onClick={() => void saveSmsSettings()}>
              {smsSaving ? tCommon('loading') : tCommon('save')}
            </Button>
          </TabsContent>
          <TabsContent value="payment" className="space-y-3 pt-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <p className="mb-1 text-xs text-muted-foreground">gateway</p>
                <Input defaultValue={String(pay.gateway ?? '')} onBlur={(e) => void saveGroup('payment', {...pay, gateway: e.target.value})} dir="ltr" />
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">merchant_id</p>
                <Input defaultValue={String(pay.merchant_id ?? '')} onBlur={(e) => void saveGroup('payment', {...pay, merchant_id: e.target.value})} dir="ltr" />
              </div>
            </div>
          </TabsContent>

          <TabsContent value="canned" className="space-y-3 pt-4">
            <Button type="button" size="sm" onClick={() => openCreate('canned')}>
              {tAuto('auto.settings_view.s_15f2d066')}
            </Button>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b bg-muted/40">
                    <th className="px-2 py-2 text-start">{tAuto('auto.settings_view.s_1a9bdb20')}</th>
                    <th className="px-2 py-2 text-start"> </th>
                  </tr>
                </thead>
                <tbody>
                  {canned.map((r) => (
                    <tr key={String(r.id)} className="border-b">
                      <td className="px-2 py-2">{String(r.title ?? '')}</td>
                      <td className="px-2 py-2">
                        <Button type="button" variant="ghost" size="sm" onClick={() => openEdit('canned', r)}>
                          {tAuto('auto.settings_view.s_ac60ae7a')}
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="text-destructive"
                          onClick={() => setDeleteTarget({ kind: 'canned', id: Number(r.id) })}>
                        
                          {tAuto('auto.settings_view.s_2d2bbdc2')}
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </TabsContent>

          <TabsContent value="positions" className="space-y-3 pt-4">
            <Button type="button" size="sm" onClick={() => openCreate('position')}>
              {tAuto('auto.settings_view.s_0f6b0b28')}
            </Button>
            <ul className="space-y-1">
              {positions.map((r) => (
                <li key={String(r.id)} className="flex items-center justify-between rounded border px-2 py-1">
                  <span>{String(r.title ?? '')}</span>
                  <span>
                    <Button type="button" variant="ghost" size="sm" onClick={() => openEdit('position', r)}>
                      {tAuto('auto.settings_view.s_ac60ae7a')}
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="text-destructive"
                      onClick={() => setDeleteTarget({ kind: 'position', id: Number(r.id) })}>
                    
                      {tAuto('auto.settings_view.s_2d2bbdc2')}
                    </Button>
                  </span>
                </li>
              ))}
            </ul>
          </TabsContent>

          <TabsContent value="taskcat" className="space-y-3 pt-4">
            <Button type="button" size="sm" onClick={() => openCreate('category')}>
              {tAuto('auto.settings_view.s_e39ce288')}
            </Button>
            <div className="overflow-x-auto rounded-md border">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b bg-muted/40">
                    <th className="px-2 py-2 text-start">{tAuto('auto.settings_view.s_45dd06ba')}</th>
                    <th className="px-2 py-2 text-start">{tAuto('auto.settings_view.s_3f13b5cf')}</th>
                    <th className="px-2 py-2 text-start"> </th>
                  </tr>
                </thead>
                <tbody>
                  {categories.map((r) => (
                    <tr key={String(r.id)} className="border-b">
                      <td className="px-2 py-2">{String(r.name ?? '')}</td>
                      <td className="px-2 py-2">
                        <span className="inline-block h-4 w-4 rounded-full border" style={{ background: String(r.color ?? '#ccc') }} />
                      </td>
                      <td className="px-2 py-2">
                        <Button type="button" variant="ghost" size="sm" onClick={() => openEdit('category', r)}>
                          {tAuto('auto.settings_view.s_ac60ae7a')}
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="text-destructive"
                          onClick={() => setDeleteTarget({ kind: 'category', id: Number(r.id) })}>
                        
                          {tAuto('auto.settings_view.s_2d2bbdc2')}
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </TabsContent>

          <TabsContent value="visitor" className="space-y-3 pt-4">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={visitorEnabled}
                onChange={(e) => setVisitorEnabled(e.target.checked)}
              />
              {t('visitorEnabled')}
            </label>
            <p className="text-xs text-muted-foreground">{t('visitorHint')}</p>
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                size="sm"
                onClick={() =>
                  void saveGroup('visitor_tracking', {
                    enable_visitor_statistics: visitorEnabled ? '1' : '0',
                  })
                }
              >
                {tCommon('save')}
              </Button>
              <Button type="button" variant="outline" size="sm" asChild>
                <Link href={dashboardHref(locale, 'admin/analytics/visitors')}>{t('openVisitorStats')}</Link>
              </Button>
            </div>
          </TabsContent>

          <TabsContent value="forms" className="space-y-3 pt-4">
            <Button type="button" size="sm" onClick={() => openCreate('form')}>
              {t('addForm')}
            </Button>
            <ul className="space-y-1">
              {forms.map((r) => (
                <li key={String(r.id)} className="flex items-center justify-between rounded border px-2 py-1">
                  <span>
                    {String(r.title ?? r.name ?? '')}{' '}
                    <span className="text-xs text-muted-foreground" dir="ltr">
                      /{String(r.slug ?? '')}
                    </span>
                  </span>
                  <span>
                    <Button type="button" variant="ghost" size="sm" onClick={() => openEdit('form', r)}>
                      {tAuto('auto.settings_view.s_ac60ae7a')}
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="text-destructive"
                      onClick={() => setDeleteTarget({ kind: 'form', id: Number(r.id) })}
                    >
                      {tAuto('auto.settings_view.s_2d2bbdc2')}
                    </Button>
                  </span>
                </li>
              ))}
              {!forms.length && !loading ? (
                <li className="text-sm text-muted-foreground">{tCommon('empty')}</li>
              ) : null}
            </ul>
          </TabsContent>

          <TabsContent value="leads" className="space-y-3 pt-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{t('leadDefaultStatus')}</p>
                <Input value={leadDefaultStatus} onChange={(e) => setLeadDefaultStatus(e.target.value)} dir="ltr" />
              </div>
              <div>
                <p className="mb-1 text-xs text-muted-foreground">{t('leadAutoAssign')}</p>
                <Select value={leadAutoAssign} onValueChange={setLeadAutoAssign}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="1">{tAuth('on')}</SelectItem>
                    <SelectItem value="0">{tAuth('off')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <Button
              type="button"
              size="sm"
              onClick={() =>
                void saveGroup('leads', {
                  default_status: leadDefaultStatus,
                  auto_assign: leadAutoAssign,
                })
              }
            >
              {tCommon('save')}
            </Button>
          </TabsContent>

          <TabsContent value="workflow" className="space-y-3 pt-4">
            <Button type="button" size="sm" onClick={() => openCreate('workflow')}>
              {t('addWorkflowStatus')}
            </Button>
            <ul className="space-y-1">
              {workflow.map((r) => (
                <li key={String(r.id)} className="flex items-center justify-between rounded border px-2 py-1">
                  <span className="flex items-center gap-2">
                    <span
                      className="inline-block h-3 w-3 rounded-full border"
                      style={{ background: String(r.color ?? '#ccc') }}
                    />
                    {String(r.name ?? r.title ?? '')}
                  </span>
                  <span>
                    <Button type="button" variant="ghost" size="sm" onClick={() => openEdit('workflow', r)}>
                      {tAuto('auto.settings_view.s_ac60ae7a')}
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="text-destructive"
                      onClick={() => setDeleteTarget({ kind: 'workflow', id: Number(r.id) })}
                    >
                      {tAuto('auto.settings_view.s_2d2bbdc2')}
                    </Button>
                  </span>
                </li>
              ))}
              {!workflow.length && !loading ? (
                <li className="text-sm text-muted-foreground">{tCommon('empty')}</li>
              ) : null}
            </ul>
          </TabsContent>

          <TabsContent value="automations" className="space-y-3 pt-4">
            <Button type="button" size="sm" onClick={() => openCreate('automation')}>
              {t('addAutomation')}
            </Button>
            <ul className="space-y-1">
              {automations.map((r) => (
                <li key={String(r.id)} className="flex items-center justify-between rounded border px-2 py-1">
                  <span>
                    {String(r.name ?? '')}{' '}
                    <span className="text-xs text-muted-foreground" dir="ltr">
                      {String(r.trigger ?? '')}
                    </span>
                  </span>
                  <span>
                    <Button type="button" variant="ghost" size="sm" onClick={() => openEdit('automation', r)}>
                      {tAuto('auto.settings_view.s_ac60ae7a')}
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="text-destructive"
                      onClick={() => setDeleteTarget({ kind: 'automation', id: Number(r.id) })}
                    >
                      {tAuto('auto.settings_view.s_2d2bbdc2')}
                    </Button>
                  </span>
                </li>
              ))}
              {!automations.length && !loading ? (
                <li className="text-sm text-muted-foreground">{tCommon('empty')}</li>
              ) : null}
            </ul>
          </TabsContent>

          <TabsContent value="coreUpdate" className="space-y-3 pt-4">
            <p className="text-sm text-muted-foreground">{t('coreUpdateHint')}</p>
            <Button type="button" variant="outline" asChild>
              <Link href={dashboardHref(locale, 'admin/marketplace/products')}>{t('openMarketplace')}</Link>
            </Button>
          </TabsContent>

          <TabsContent value="hosting" className="pt-4 space-y-3">
            <p className="text-sm text-muted-foreground">{tHosting('settingsTabHint')}</p>
            <Button type="button" variant="outline" asChild>
              <Link href={dashboardHref(locale, 'admin/hosting-infra')}>{tHosting('openHostingPage')}</Link>
            </Button>
          </TabsContent>

          <TabsContent value="bots" className="space-y-3 pt-4">
            <p className="text-sm text-muted-foreground">{tHub('bots')}</p>
            <Button type="button" variant="outline" asChild>
              <Link href={dashboardHref(locale, 'admin/integrations/bale')}>{t('bots')}</Link>
            </Button>
          </TabsContent>

          <TabsContent value="raw" className="pt-4">
            <pre className="max-h-[480px] overflow-auto rounded-md bg-muted p-3 text-xs" dir="ltr">
              {loading ? '…' : JSON.stringify(data, null, 2)}
            </pre>
          </TabsContent>
        </Tabs>
      </CardContent>

      <Dialog open={dialog !== null} onOpenChange={(o) => !o && setDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {dialog?.kind === 'canned'
                ? tAuto('auto.settings_view.s_d43b8b9a')
                : dialog?.kind === 'position'
                  ? tAuto('auto.settings_view.s_95cb3687')
                  : dialog?.kind === 'form'
                    ? t('addForm')
                    : dialog?.kind === 'workflow'
                      ? t('addWorkflowStatus')
                      : dialog?.kind === 'automation'
                        ? t('addAutomation')
                        : tAuto('auto.settings_view.s_cfb7155e')}
            </DialogTitle>
          </DialogHeader>
          {dialog?.kind === 'canned' ? (
            <div className="space-y-2">
              <Input placeholder={tAuto('auto.settings_view.s_1a9bdb20')} value={formTitle} onChange={(e) => setFormTitle(e.target.value)} />
              <Textarea placeholder={tAuto('auto.settings_view.s_40b31215')} value={formBody} onChange={(e) => setFormBody(e.target.value)} rows={5} />
            </div>
          ) : dialog?.kind === 'position' ? (
            <Input placeholder={tAuto('auto.settings_view.s_12b6b438')} value={formTitle} onChange={(e) => setFormTitle(e.target.value)} />
          ) : dialog?.kind === 'form' ? (
            <div className="space-y-2">
              <Input placeholder={t('formTitle')} value={formTitle} onChange={(e) => setFormTitle(e.target.value)} />
              <Input placeholder={t('formSlug')} value={formSlug} onChange={(e) => setFormSlug(e.target.value)} dir="ltr" />
            </div>
          ) : dialog?.kind === 'workflow' ? (
            <div className="space-y-2">
              <Input placeholder={t('workflowName')} value={formName} onChange={(e) => setFormName(e.target.value)} />
              <Input type="color" value={formColor} onChange={(e) => setFormColor(e.target.value)} className="w-24 p-1" />
            </div>
          ) : dialog?.kind === 'automation' ? (
            <div className="space-y-2">
              <Input placeholder={t('automationName')} value={formName} onChange={(e) => setFormName(e.target.value)} />
              <Input placeholder={t('automationTrigger')} value={autoTrigger} onChange={(e) => setAutoTrigger(e.target.value)} dir="ltr" />
            </div>
          ) : (
            <div className="space-y-2">
              <Input placeholder={tAuto('auto.settings_view.s_45dd06ba')} value={formName} onChange={(e) => setFormName(e.target.value)} />
              <Input type="color" value={formColor} onChange={(e) => setFormColor(e.target.value)} className="w-24 p-1" />
              <Input placeholder={tAuto('auto.settings_view.s_1444ee00')} value={formSort} onChange={(e) => setFormSort(e.target.value)} dir="ltr" />
            </div>
          )}
          {formErr ? <p className="text-sm text-destructive">{formErr}</p> : null}
          <DialogFooter>
            <Button type="button" onClick={() => void saveCrud()}>
              {tAuto('auto.settings_view.s_08545fb6')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{tAuto('auto.settings_view.s_0851be6b')}</AlertDialogTitle>
            <AlertDialogDescription>{tAuto('auto.settings_view.s_36bf80bb')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tAuto('auto.settings_view.s_106dfb4e')}</AlertDialogCancel>
            <AlertDialogAction onClick={() => void confirmDelete()}>{tAuto('auto.settings_view.s_2d2bbdc2')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  );
}
