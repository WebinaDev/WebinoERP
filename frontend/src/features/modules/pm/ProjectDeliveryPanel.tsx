'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/hooks/use-locale-next';
import { dashboardHref } from '@/lib/route-resolver';

type Budget = {
  budget_amount?: string | number | null;
  budget_hours?: string | number | null;
  hourly_rate?: string | number | null;
  actual_hours?: number;
  actual_cost?: number;
  variance_amount?: number | null;
};
type FileRow = { id: number; name: string; version: number; shared_with_client?: boolean };
type Approval = { id: number; title: string; status: string };
type Template = { id: number; name: string };

export function ProjectDeliveryPanel({ projectId }: { projectId: string }) {
  const t = useTranslations('suite');
  const { formatNumber } = useLocale();
  const params = useParams();
  const router = useRouter();
  const locale = (params?.locale as string) || 'fa';
  const [budget, setBudget] = useState<Budget | null>(null);
  const [amount, setAmount] = useState('');
  const [hours, setHours] = useState('');
  const [rate, setRate] = useState('');
  const [files, setFiles] = useState<FileRow[]>([]);
  const [share, setShare] = useState(true);
  const [approvals, setApprovals] = useState<Approval[]>([]);
  const [title, setTitle] = useState('');
  const [templates, setTemplates] = useState<Template[]>([]);
  const [templateId, setTemplateId] = useState('');
  const [projectName, setProjectName] = useState('');

  const load = useCallback(async () => {
    const [budgetRes, fileRes, approvalRes, templateRes] = await Promise.all([
      apiClient.get(`/v1/projects/projects/${projectId}/budget`),
      apiClient.get(`/v1/projects/projects/${projectId}/files`),
      apiClient.get('/v1/projects/approvals', { params: { project_id: projectId } }),
      apiClient.get('/v1/projects/project-templates'),
    ]);
    const next = unwrapData<Budget>(budgetRes);
    setBudget(next);
    setAmount(next?.budget_amount != null ? String(next.budget_amount) : '');
    setHours(next?.budget_hours != null ? String(next.budget_hours) : '');
    setRate(next?.hourly_rate != null ? String(next.hourly_rate) : '');
    setFiles(unwrapData<FileRow[]>(fileRes) ?? []);
    setApprovals(unwrapData<Approval[]>(approvalRes) ?? []);
    setTemplates(unwrapData<Template[]>(templateRes) ?? []);
  }, [projectId]);

  useEffect(() => {
    void load().catch(() => undefined);
  }, [load]);

  async function saveBudget() {
    await apiClient.put(`/v1/projects/projects/${projectId}/budget`, {
      budget_amount: amount === '' ? null : Number(amount),
      budget_hours: hours === '' ? null : Number(hours),
      hourly_rate: rate === '' ? null : Number(rate),
    });
    await load();
  }

  async function upload(file: File, versionOf?: number) {
    const body = new FormData();
    body.append('file', file);
    if (versionOf) {
      await apiClient.post(`/v1/projects/files/${versionOf}/versions`, body);
    } else {
      body.append('project_id', projectId);
      body.append('shared_with_client', share ? '1' : '0');
      await apiClient.post('/v1/projects/files', body);
    }
    await load();
  }

  async function requestApproval() {
    if (!title.trim()) return;
    await apiClient.post('/v1/projects/approvals', { project_id: Number(projectId), title: title.trim() });
    setTitle('');
    await load();
  }

  async function fromTemplate() {
    if (!templateId || !projectName.trim()) return;
    const res = await apiClient.post(`/v1/projects/project-templates/${templateId}/instantiate`, { name: projectName.trim() });
    const created = unwrapData<{ id: number }>(res);
    if (created?.id) router.push(dashboardHref(locale, `pm/projects/${created.id}`));
  }

  const statusLabel = (status: string) => {
    if (status === 'approved' || status === 'pending' || status === 'changes' || status === 'rejected') return t(status);
    return status;
  };

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardHeader><CardTitle className="text-base">{t('budget')}</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          <form className="grid gap-2 sm:grid-cols-3" onSubmit={(event) => { event.preventDefault(); void saveBudget(); }}>
            <div className="space-y-1">
              <Label htmlFor="budget-amount">{t('budgetAmount')}</Label>
              <Input id="budget-amount" inputMode="decimal" value={amount} onChange={(event) => setAmount(event.target.value)} />
            </div>
            <div className="space-y-1">
              <Label htmlFor="budget-hours">{t('budgetHours')}</Label>
              <Input id="budget-hours" inputMode="decimal" value={hours} onChange={(event) => setHours(event.target.value)} />
            </div>
            <div className="space-y-1">
              <Label htmlFor="budget-rate">{t('rate')}</Label>
              <Input id="budget-rate" inputMode="decimal" value={rate} onChange={(event) => setRate(event.target.value)} />
            </div>
            <Button type="submit" size="sm" className="sm:col-span-3 w-fit">{t('save')}</Button>
          </form>
          <p className="text-sm text-muted-foreground">
            {t('actual')}: {formatNumber(Number(budget?.actual_hours ?? 0))} / {formatNumber(Number(budget?.actual_cost ?? 0))}
            {budget?.variance_amount != null ? ` · ${t('variance')} ${formatNumber(Number(budget.variance_amount))}` : ''}
          </p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle className="text-base">{t('files')}</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          {files.length === 0 ? <p className="text-sm text-muted-foreground">{t('noFiles')}</p> : files.map((file) => (
            <div key={file.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
              <span>{file.name} · {t('version')} {formatNumber(Number(file.version))}</span>
              <label className="text-xs text-muted-foreground">
                {t('newVersion')}
                <input className="sr-only" type="file" onChange={(event) => {
                  const picked = event.target.files?.[0];
                  if (picked) void upload(picked, file.id);
                }} />
              </label>
            </div>
          ))}
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={share} onChange={(event) => setShare(event.target.checked)} />
            {t('shareClient')}
          </label>
          <label className="inline-flex">
            <input className="sr-only" type="file" onChange={(event) => {
              const picked = event.target.files?.[0];
              if (picked) void upload(picked);
            }} />
            <span className="inline-flex h-8 cursor-pointer items-center rounded-md border px-3 text-sm">{t('upload')}</span>
          </label>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle className="text-base">{t('approvals')}</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          {approvals.length === 0 ? <p className="text-sm text-muted-foreground">{t('noApprovals')}</p> : approvals.map((row) => (
            <p key={row.id} className="text-sm">{row.title} · {statusLabel(row.status)}</p>
          ))}
          <form className="flex gap-2" onSubmit={(event) => { event.preventDefault(); void requestApproval(); }}>
            <Input value={title} onChange={(event) => setTitle(event.target.value)} placeholder={t('approvalTitle')} aria-label={t('approvalTitle')} />
            <Button type="submit" size="sm">{t('requestApproval')}</Button>
          </form>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle className="text-base">{t('template')}</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          {templates.length === 0 ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : (
            <form className="grid gap-2" onSubmit={(event) => { event.preventDefault(); void fromTemplate(); }}>
              <select className="flex h-9 w-full rounded-md border bg-transparent px-3 text-sm" value={templateId} onChange={(event) => setTemplateId(event.target.value)} aria-label={t('template')}>
                <option value="">{t('template')}</option>
                {templates.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
              <Input value={projectName} onChange={(event) => setProjectName(event.target.value)} placeholder={t('templateName')} aria-label={t('templateName')} />
              <Button type="submit" size="sm" className="w-fit">{t('createFromTemplate')}</Button>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
