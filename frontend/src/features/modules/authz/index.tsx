'use client';

import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useLocale, useTranslations } from 'next-intl';
import { ShieldCheck, Users, KeyRound, FileClock, FlaskConical, Search, Save, Plus, Trash2, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import apiClient from '@/lib/api-client';

type Permission = { id: number; code: string; module: string; resource: string; action: string; title_fa: string; title_en?: string; risk_level: 'low'|'medium'|'high'|'critical'; requires_mfa: boolean; requires_reason: boolean };
type Grant = { permission_id: number; scope: string; effect: string; conditions_json?: Record<string, unknown> | null; permission?: Permission };
type Role = { id: number; code: string; title_fa: string; title_en?: string; is_system: boolean; users_count?: number; role_permissions?: Grant[] };
type Membership = { id: number; company_id: number; branch_id: number|null; status: string; user?: { id: number; name: string; email: string }; assigned_roles?: Role[] };
type Decision = { allowed: boolean; decision: 'allow'|'deny'|'allow_with_approval'; reason: string; effective_scope: string; obligations: Record<string, unknown> };
type ApiPayload<T> = T | { data: T };
const payload = <T,>(value: ApiPayload<T>): T => {
  let result: unknown = value;
  while (result && typeof result === 'object' && !Array.isArray(result) && Object.keys(result).length === 1 && 'data' in result) result = (result as {data:unknown}).data;
  return result as T;
};

function useAuthzData() {
  const roles = useQuery({ queryKey: ['authz','roles'], queryFn: async () => payload(await apiClient.get('/v1/authz/roles').then(r => r.data) as ApiPayload<Role[]>) });
  const catalog = useQuery({ queryKey: ['authz','permissions'], queryFn: async () => {
    const result = payload(await apiClient.get('/v1/authz/permissions').then(r => r.data) as ApiPayload<{list: Permission[]}>);
    return result.list;
  }});
  const me = useQuery({ queryKey: ['authz','me'], queryFn: async () => payload(await apiClient.get('/v1/authz/me').then(r => r.data) as ApiPayload<{company_id:number|null;branch_id:number|null}>) });
  const members = useQuery({ queryKey: ['authz','memberships'], queryFn: async () => payload(await apiClient.get('/v1/authz/memberships').then(r => r.data) as ApiPayload<Membership[]>) });
  const policies = useQuery({ queryKey: ['authz','policies'], queryFn: async () => payload(await apiClient.get('/v1/authz/policies').then(r => r.data) as ApiPayload<Array<Record<string, unknown>>>) });
  const fields = useQuery({ queryKey: ['authz','field-policies'], queryFn: async () => payload(await apiClient.get('/v1/authz/field-policies').then(r => r.data) as ApiPayload<Array<Record<string, unknown>>>) });
  const audit = useQuery({ queryKey: ['authz','audit'], queryFn: async () => payload(await apiClient.get('/v1/authz/audit-logs').then(r => r.data) as ApiPayload<{data:Array<Record<string, unknown>>}>) });
  return { roles, catalog, me, members, policies, fields, audit };
}

export function AuthzOverviewPage({ initialTab = 'roles' }: { initialTab?: string }) {
  const t = useTranslations('erp.authz');
  const ui = useTranslations('erp.authz.ui');
  const locale = useLocale();
  const qc = useQueryClient();
  const data = useAuthzData();
  const [tab, setTab] = useState(initialTab);
  const [search, setSearch] = useState('');
  const [selectedId, setSelectedId] = useState<number|null>(null);
  const [newRole, setNewRole] = useState({ code: '', title_fa: '', title_en: '' });
  const [copySource, setCopySource] = useState<Role|null>(null);
  const [roleDraft, setRoleDraft] = useState({ title_fa: '', title_en: '' });
  const [draftGrants, setDraftGrants] = useState<Record<number, Grant>>({});
  const [memberRole, setMemberRole] = useState<Record<number, number>>({});
  const [sim, setSim] = useState({ user_id: '', permission: '', amount: '', branch_id: '', record_company_id: '' });
  const [decision, setDecision] = useState<Decision|null>(null);
  const [policy, setPolicy] = useState({ name: '', name_en: '', target_permission: '', condition_expression: 'amount <= 10000000', effect: 'allow', priority: '10' });

  const roleList = data.roles.data ?? [];
  const permissions = data.catalog.data ?? [];
  const members = data.members.data ?? [];
  const selectedRole = roleList.find(role => role.id === selectedId) ?? null;
  useEffect(() => {
    if (!selectedRole) return;
    const initial: Record<number, Grant> = {};
    for (const grant of selectedRole.role_permissions ?? []) initial[grant.permission_id] = { ...grant };
    setDraftGrants(initial);
    setRoleDraft({ title_fa: selectedRole.title_fa, title_en: selectedRole.title_en ?? selectedRole.code });
  }, [selectedId, data.roles.dataUpdatedAt]);

  const refresh = () => qc.invalidateQueries({ queryKey: ['authz'] });
  const createRole = useMutation({ mutationFn: async () => apiClient.post('/v1/authz/roles', { ...newRole, company_id: data.me.data?.company_id, permissions: copySource ? (copySource.role_permissions ?? []).map(grant => ({ permission_id: grant.permission_id, scope: grant.scope, effect: grant.effect, conditions_json: grant.conditions_json ?? null })) : [] }), onSuccess: (res) => { const role = payload(res.data as ApiPayload<Role>); setSelectedId(role.id); setNewRole({ code: '', title_fa: '', title_en: '' }); setCopySource(null); toast.success(ui('saved')); refresh(); }, onError: () => toast.error(ui('saveError')) });
  const saveRole = useMutation({ mutationFn: async () => {
    if (!selectedRole) return;
    const grants = Object.values(draftGrants).map(grant => ({ permission_id: grant.permission_id, scope: grant.scope, effect: grant.effect, conditions_json: grant.conditions_json ?? null }));
    return apiClient.put(`/v1/authz/roles/${selectedRole.id}`, { ...roleDraft, company_id: data.me.data?.company_id, permissions: grants });
  }, onSuccess: refresh, onError: () => toast.error(ui('saveError')) });
  const assignRole = useMutation({ mutationFn: ({ membershipId, roleId }: {membershipId:number;roleId:number}) => apiClient.post(`/v1/authz/memberships/${membershipId}/roles`, { role_id: roleId, company_id: data.me.data?.company_id }), onSuccess: refresh, onError: () => toast.error(ui('saveError')) });
  const removeRole = useMutation({ mutationFn: ({ membershipId, roleId }: {membershipId:number;roleId:number}) => apiClient.delete(`/v1/authz/memberships/${membershipId}/roles/${roleId}`, { params: { company_id: data.me.data?.company_id } }), onSuccess: refresh, onError: () => toast.error(ui('saveError')) });
  const runSimulation = useMutation({ mutationFn: async () => {
    const companyId = Number(sim.record_company_id || data.me.data?.company_id || 0);
    const record: Record<string, unknown> = { company_id: companyId };
    if (sim.branch_id) record.branch_id = Number(sim.branch_id);
    if (sim.amount) record.amount = Number(sim.amount);
    const response = await apiClient.post('/v1/authz/simulate', { user_id: Number(sim.user_id), permission: sim.permission, company_id: companyId, branch_id: data.me.data?.branch_id, record, context: { amount: Number(sim.amount || 0) } });
    return payload(response.data as ApiPayload<{ evaluation: Decision }>).evaluation;
  }, onSuccess: setDecision, onError: () => toast.error(ui('simulationError')) });
  const savePolicy = useMutation({ mutationFn: () => apiClient.post('/v1/authz/policies', { ...policy, target_permission: policy.target_permission || null, priority: Number(policy.priority), is_active: true }), onSuccess: () => { setPolicy({ name: '', name_en: '', target_permission: '', condition_expression: 'amount <= 10000000', effect: 'allow', priority: '10' }); refresh(); }, onError: () => toast.error(ui('saveError')) });

  const filteredPermissions = useMemo(() => permissions.filter(p => `${p.code} ${p.title_fa} ${p.title_en ?? ''}`.toLowerCase().includes(search.toLowerCase())), [permissions, search]);
  const groupedPermissions = useMemo(() => filteredPermissions.reduce<Record<string, Permission[]>>((groups, p) => ((groups[p.module] ??= []).push(p), groups), {}), [filteredPermissions]);
  const error = [data.roles, data.catalog, data.me].find(query => query.isError);

  return <div className="space-y-5" dir="auto">
    <div className="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
      <div><h1 className="flex items-center gap-2 text-2xl font-bold"><ShieldCheck className="size-6 text-primary" />{t('title')}</h1><p className="mt-1 text-sm text-muted-foreground">{t('description')}</p></div>
      <Badge variant="outline">{ui('serverEnforced')}</Badge>
    </div>
    {error && <Card><CardContent className="p-4 text-sm text-destructive">{ui('loadError')} <Button variant="outline" size="sm" onClick={refresh}>{ui('retry')}</Button></CardContent></Card>}
    <Tabs value={tab} onValueChange={setTab}>
      <TabsList className="grid h-auto w-full grid-cols-2 gap-1 md:grid-cols-3 xl:grid-cols-6">
        <TabsTrigger value="roles">{t('roles')}</TabsTrigger><TabsTrigger value="members">{ui('members')}</TabsTrigger><TabsTrigger value="permissions">{t('permissions')}</TabsTrigger><TabsTrigger value="policies">{t('policies')}</TabsTrigger><TabsTrigger value="simulator">{t('simulator')}</TabsTrigger><TabsTrigger value="audit">{t('auditLogs')}</TabsTrigger>
      </TabsList>
      <TabsContent value="roles" className="space-y-4">
        <div className="grid gap-4 xl:grid-cols-[minmax(250px,1fr)_minmax(0,2fr)]">
          <Card><CardHeader><CardTitle className="flex items-center gap-2 text-base"><Users className="size-4" />{t('roles')}</CardTitle><CardDescription>{t('rolesDesc')}</CardDescription></CardHeader><CardContent className="space-y-2">
            {roleList.map(role => <button key={role.id} onClick={() => setSelectedId(role.id)} className={`w-full rounded-md border p-3 text-start hover:bg-muted ${selectedId === role.id ? 'border-primary bg-muted' : ''}`}><span className="block font-medium">{locale==='en' ? (role.title_en || role.code) : role.title_fa}</span><span className="font-mono text-xs text-muted-foreground">{role.code} · {role.role_permissions?.length ?? 0} {ui('permissionsCount')}</span></button>)}
            <div className="space-y-2 border-t pt-3"><Label>{ui('createRole')}</Label>{selectedRole && <Button className="w-full" variant="outline" onClick={()=>{setCopySource(selectedRole);setNewRole({code:`${selectedRole.code}_copy`,title_fa:`${selectedRole.title_fa} - ${ui('copySuffix')}`,title_en:`${selectedRole.title_en||selectedRole.code} ${ui('copySuffix')}`})}}><Copy className="me-2 size-4"/>{ui('copyRole')}</Button>}<Input placeholder={ui('roleCode')} value={newRole.code} onChange={e => setNewRole({...newRole, code:e.target.value})}/><Input placeholder={ui('titleFa')} value={newRole.title_fa} onChange={e => setNewRole({...newRole, title_fa:e.target.value})}/><Input placeholder={ui('titleEn')} dir="ltr" value={newRole.title_en} onChange={e => setNewRole({...newRole, title_en:e.target.value})}/><Button className="w-full" disabled={!newRole.code || !newRole.title_fa || !newRole.title_en || createRole.isPending} onClick={() => createRole.mutate()}><Plus className="me-2 size-4"/>{ui('createRole')}</Button>{copySource&&<p className="text-xs text-muted-foreground">{ui('copySource')}: {locale==='en'?copySource.title_en:copySource.title_fa}</p>}</div>
          </CardContent></Card>
          <Card><CardHeader><CardTitle className="flex items-center justify-between gap-2 text-base"><span>{selectedRole ? ui('editMatrix') : ui('chooseRole')}</span>{selectedRole && <Button size="sm" onClick={() => saveRole.mutate()} disabled={selectedRole.is_system || saveRole.isPending}><Save className="me-2 size-4"/>{ui('save')}</Button>}</CardTitle><CardDescription>{selectedRole?.code ?? ui('chooseRoleHelp')}</CardDescription></CardHeader><CardContent className="space-y-3">
            {selectedRole && <><div className="grid gap-2 sm:grid-cols-2"><div><Label>{ui('titleFa')}</Label><Input value={roleDraft.title_fa} disabled={selectedRole.is_system} onChange={e => setRoleDraft({...roleDraft,title_fa:e.target.value})}/></div><div><Label>{ui('titleEn')}</Label><Input dir="ltr" value={roleDraft.title_en} disabled={selectedRole.is_system} onChange={e => setRoleDraft({...roleDraft,title_en:e.target.value})}/></div></div>
              <div className="flex gap-2"><Search className="mt-2 size-4 text-muted-foreground"/><Input value={search} onChange={e => setSearch(e.target.value)} placeholder={ui('searchPermissions')}/></div>
              <div className="max-h-[55vh] space-y-4 overflow-auto rounded-md border p-3">{Object.entries(groupedPermissions).map(([module, list]) => <section key={module}><h3 className="sticky top-0 bg-background py-1 font-semibold">{module}</h3>{(list as Permission[]).map(permission => { const grant = draftGrants[permission.id]; return <div key={permission.id} className="grid gap-2 border-t py-2 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center"><label className="flex items-start gap-2 text-sm"><input type="checkbox" className="mt-1" checked={!!grant} disabled={selectedRole.is_system} onChange={e => setDraftGrants(current => { const next={...current}; if(e.target.checked) next[permission.id]={permission_id:permission.id,scope:'all',effect:'allow'}; else delete next[permission.id]; return next; })}/><span><span className="block">{locale === 'en' ? (permission.title_en || permission.code) : permission.title_fa}</span><code className="text-xs text-muted-foreground">{permission.code}</code></span><Badge variant="outline" className="ms-1 text-[10px]">{permission.risk_level}</Badge></label>{grant && <><select aria-label={ui('scope')} className="h-9 rounded-md border bg-background px-2 text-sm" value={grant.scope} disabled={selectedRole.is_system} onChange={e => setDraftGrants({...draftGrants,[permission.id]:{...grant,scope:e.target.value}})}>{['all','branch','own','team','warehouse_ids','custom_filter'].map(s=><option key={s} value={s}>{ui(`scope_${s}`)}</option>)}</select><select aria-label={ui('effect')} className="h-9 rounded-md border bg-background px-2 text-sm" value={grant.effect} disabled={selectedRole.is_system} onChange={e => setDraftGrants({...draftGrants,[permission.id]:{...grant,effect:e.target.value}})}>{['allow','deny','allow_with_approval'].map(s=><option key={s} value={s}>{ui(`effect_${s}`)}</option>)}</select></>}</div>; })}</section>)}</div></>}
          </CardContent></Card>
        </div>
      </TabsContent>
      <TabsContent value="members"><Card><CardHeader><CardTitle className="flex items-center gap-2 text-base"><Users className="size-4"/>{ui('members')}</CardTitle><CardDescription>{ui('membersDesc')}</CardDescription></CardHeader><CardContent className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>{ui('user')}</TableHead><TableHead>{ui('branch')}</TableHead><TableHead>{ui('assignedRoles')}</TableHead><TableHead>{ui('addRole')}</TableHead></TableRow></TableHeader><TableBody>{members.map(member => <TableRow key={member.id}><TableCell>{member.user?.name}<div className="text-xs text-muted-foreground">{member.user?.email}</div></TableCell><TableCell>{member.branch_id ?? ui('companyWide')}</TableCell><TableCell><div className="flex flex-wrap gap-1">{member.assigned_roles?.map(role=><span key={role.id} className="flex items-center gap-1"><Badge variant="secondary">{locale === 'en' ? (role.title_en || role.code) : role.title_fa}</Badge><Button size="icon" variant="ghost" aria-label={ui('removeRole')} onClick={()=>removeRole.mutate({membershipId:member.id,roleId:role.id})}><Trash2 className="size-3"/></Button></span>)}</div></TableCell><TableCell><div className="flex gap-2"><select aria-label={ui('addRole')} className="h-9 rounded-md border bg-background px-2" value={memberRole[member.id] ?? ''} onChange={e=>setMemberRole({...memberRole,[member.id]:Number(e.target.value)})}><option value="">{ui('chooseRole')}</option>{roleList.filter(role=>role.code!=='super_admin').map(role=><option key={role.id} value={role.id}>{locale === 'en' ? (role.title_en || role.code) : role.title_fa}</option>)}</select><Button size="sm" disabled={!memberRole[member.id]} onClick={()=>assignRole.mutate({membershipId:member.id,roleId:memberRole[member.id]})}>{ui('assign')}</Button></div></TableCell></TableRow>)}</TableBody></Table></CardContent></Card></TabsContent>
      <TabsContent value="permissions"><Card><CardHeader><CardTitle className="flex items-center gap-2 text-base"><KeyRound className="size-4"/>{t('permissions')}</CardTitle><CardDescription>{t('permissionsDesc')}</CardDescription><Input value={search} onChange={e=>setSearch(e.target.value)} placeholder={ui('searchPermissions')}/></CardHeader><CardContent className="space-y-4">{Object.entries(groupedPermissions).map(([module,list])=><section key={module}><h3 className="mb-2 font-semibold">{module}</h3><div className="grid gap-2 md:grid-cols-2">{(list as Permission[]).map(permission=><div key={permission.id} className="flex items-center justify-between gap-2 rounded-md border p-3"><div><div className="text-sm font-medium">{locale === 'en' ? (permission.title_en || permission.code) : permission.title_fa}</div><code className="text-xs text-muted-foreground">{permission.code}</code></div><Badge variant={permission.risk_level==='critical'?'destructive':'outline'}>{permission.risk_level}</Badge></div>)}</div></section>)}</CardContent></Card></TabsContent>
      <TabsContent value="policies" className="space-y-4"><Card><CardHeader><CardTitle>{ui('policies')}</CardTitle><CardDescription>{t('policiesDesc')}</CardDescription></CardHeader><CardContent className="space-y-3"><div className="grid gap-2 md:grid-cols-2 xl:grid-cols-7"><Input placeholder={ui('policyNameFa')} value={policy.name} onChange={e=>setPolicy({...policy,name:e.target.value})}/><Input dir="ltr" placeholder={ui('policyNameEn')} value={policy.name_en} onChange={e=>setPolicy({...policy,name_en:e.target.value})}/><select aria-label={ui('permission')} className="h-10 rounded-md border bg-background px-2" value={policy.target_permission} onChange={e=>setPolicy({...policy,target_permission:e.target.value})}><option value="">{ui('allPermissions')}</option>{permissions.map(p=><option key={p.id} value={p.code}>{p.code}</option>)}</select><Input dir="ltr" aria-label={ui('condition')} value={policy.condition_expression} onChange={e=>setPolicy({...policy,condition_expression:e.target.value})}/><select aria-label={ui('effect')} className="h-10 rounded-md border bg-background px-2" value={policy.effect} onChange={e=>setPolicy({...policy,effect:e.target.value})}>{['allow','deny','allow_with_approval'].map(effect=><option key={effect} value={effect}>{ui(`effect_${effect}`)}</option>)}</select><Input aria-label={ui('priority')} inputMode="numeric" value={policy.priority} onChange={e=>setPolicy({...policy,priority:e.target.value})}/><Button disabled={!policy.name||!policy.name_en||savePolicy.isPending} onClick={()=>savePolicy.mutate()}>{ui('savePolicy')}</Button></div><div className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>{ui('policyNameFa')}</TableHead><TableHead>{ui('permission')}</TableHead><TableHead>{ui('condition')}</TableHead><TableHead>{ui('effect')}</TableHead><TableHead>{ui('priority')}</TableHead></TableRow></TableHeader><TableBody>{(data.policies.data??[]).map((row,i)=><TableRow key={String(row.id??i)}><TableCell>{String(locale === 'en' ? (row.name_en ?? row.name ?? '') : (row.name ?? ''))}</TableCell><TableCell className="font-mono">{String(row.target_permission??'—')}</TableCell><TableCell className="font-mono" dir="ltr">{String(row.condition_expression??'')}</TableCell><TableCell>{String(row.effect??'')}</TableCell><TableCell>{String(row.priority??'')}</TableCell></TableRow>)}</TableBody></Table></div><h3 className="pt-3 font-semibold">{ui('fieldSecurity')}</h3><div className="grid gap-2 md:grid-cols-2">{(data.fields.data??[]).map((row,i)=><div key={String(row.id??i)} className="rounded-md border p-3 text-sm"><code>{String(row.resource)}.{String(row.field)}</code><div className="text-muted-foreground">{ui('requiredPermission')}: {String(row.permission_needed)} · {String(row.effect)}</div></div>)}</div></CardContent></Card></TabsContent>
      <TabsContent value="simulator"><Card><CardHeader><CardTitle className="flex items-center gap-2 text-base"><FlaskConical className="size-4"/>{t('simulator')}</CardTitle><CardDescription>{t('simulatorDesc')}</CardDescription></CardHeader><CardContent className="space-y-4"><div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"><div><Label>{ui('userId')}</Label><Input inputMode="numeric" value={sim.user_id} onChange={e=>setSim({...sim,user_id:e.target.value})}/></div><div><Label>{ui('permission')}</Label><select aria-label={ui('permission')} className="h-10 w-full rounded-md border bg-background px-2" value={sim.permission} onChange={e=>setSim({...sim,permission:e.target.value})}><option value="">{ui('choosePermission')}</option>{permissions.map(p=><option key={p.id} value={p.code}>{p.code}</option>)}</select></div><div><Label>{ui('amount')}</Label><Input inputMode="decimal" value={sim.amount} onChange={e=>setSim({...sim,amount:e.target.value})}/></div><div><Label>{ui('branchId')}</Label><Input inputMode="numeric" value={sim.branch_id} onChange={e=>setSim({...sim,branch_id:e.target.value})}/></div></div><Button disabled={!sim.user_id||!sim.permission||runSimulation.isPending} onClick={()=>runSimulation.mutate()}><FlaskConical className="me-2 size-4"/>{ui('runSimulation')}</Button>{decision&&<div className={`rounded-md border p-4 ${decision.decision==='allow'?'border-emerald-500 bg-emerald-50/50':decision.decision==='allow_with_approval'?'border-amber-500 bg-amber-50/50':'border-destructive bg-destructive/5'}`}><div className="font-semibold">{ui(`decision_${decision.decision}`)}</div><p className="mt-1 text-sm">{decision.reason}</p><div className="mt-2 text-xs text-muted-foreground">{ui('scope')}: {ui(`scope_${decision.effective_scope}`)}</div>{Object.entries(decision.obligations??{}).map(([key,value])=><Badge key={key} className="me-1 mt-2" variant="outline">{ui(key)}: {String(value)}</Badge>)}</div>}</CardContent></Card></TabsContent>
    </Tabs>
      <TabsContent value="audit"><Card><CardHeader><CardTitle className="flex items-center gap-2 text-sm"><FileClock className="size-4"/>{t('auditLogs')}</CardTitle><CardDescription>{t('auditLogsDesc')}</CardDescription></CardHeader><CardContent className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>{ui('time')}</TableHead><TableHead>{ui('user')}</TableHead><TableHead>{ui('permission')}</TableHead><TableHead>{ui('decision')}</TableHead><TableHead>{ui('reason')}</TableHead></TableRow></TableHeader><TableBody>{(data.audit.data?.data??[]).slice(0,8).map((row,i)=><TableRow key={String(row.id??i)}><TableCell>{row.created_at ? new Intl.DateTimeFormat(locale === 'fa' ? 'fa-IR-u-ca-persian' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(String(row.created_at))) : '—'}</TableCell><TableCell>{String((row.user as {name?:string}|null)?.name??row.user_id??'')}</TableCell><TableCell className="font-mono">{String(row.permission_code??'')}</TableCell><TableCell><Badge variant={row.decision==='deny'?'destructive':'outline'}>{String(row.decision??'')}</Badge></TableCell><TableCell className="max-w-sm truncate">{String(row.reason??'')}</TableCell></TableRow>)}</TableBody></Table></CardContent></Card></TabsContent>
  </div>;
}

export function AuthzRolesPage() { return <AuthzOverviewPage initialTab="roles" />; }
export function AuthzPermissionsPage() { return <AuthzOverviewPage initialTab="permissions" />; }
export function AuthzPoliciesPage() { return <AuthzOverviewPage initialTab="policies" />; }
export function AuthzSimulatorPage() { return <AuthzOverviewPage initialTab="simulator" />; }
export function AuthzAuditLogsPage() { return <AuthzOverviewPage initialTab="audit" />; }
