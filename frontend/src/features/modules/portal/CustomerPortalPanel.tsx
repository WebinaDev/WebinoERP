'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';
import { useLocale } from '@/hooks/use-locale-next';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { dashboardHref } from '@/lib/route-resolver';

type ProjectRow = {
  id: number;
  name: string;
  status: string;
  progress_percent: number;
  due_date?: string | null;
};
type TicketRow = { id: number; subject: string; status: string; created_at?: string };
type AppointmentRow = { id: number; title: string; status: string; starts_at: string };
type SiteRow = { id: number; domain?: string; slug?: string; status?: string };
type FileRow = { id: number; name: string; version?: number };
type ApprovalRow = { id: number; title: string; status: string };
type Summary = {
  account?: { id: number; name: string; website?: string | null } | null;
  projects: ProjectRow[];
  open_tickets: number;
  tickets: TicketRow[];
  appointments: AppointmentRow[];
  sites: SiteRow[];
  files?: FileRow[];
  approvals?: ApprovalRow[];
};

function approvalLabel(status: string, suite: (key: 'approved' | 'changes' | 'rejected' | 'pending') => string) {
  if (status === 'approved') return suite('approved');
  if (status === 'changes') return suite('changes');
  if (status === 'rejected') return suite('rejected');
  return suite('pending');
}

export function CustomerPortalPanel() {
  const t = useTranslations('dashboard.client');
  const suite = useTranslations('suite');
  const { formatDate, formatDateTime, formatNumber } = useLocale();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const [summary, setSummary] = useState<Summary | null>(null);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [title, setTitle] = useState('');
  const [day, setDay] = useState<string | null>(null);
  const [time, setTime] = useState('10:00');
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    const res = await apiClient.get('/v1/projects/portal/summary');
    setSummary(unwrapData<Summary>(res));
  }, []);

  useEffect(() => {
    void load().catch(() => setSummary(null));
  }, [load]);

  async function sendTicket() {
    if (!subject.trim()) return;
    await apiClient.post('/v1/projects/portal/tickets', { subject: subject.trim(), body });
    setSubject('');
    setBody('');
    setNotice(t('ticketSent'));
    await load();
  }

  async function openFile(file: FileRow) {
    const res = await apiClient.get(`/v1/projects/portal/files/${file.id}`, { responseType: 'blob' });
    const url = URL.createObjectURL(res.data as Blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = file.name;
    link.click();
    URL.revokeObjectURL(url);
  }

  async function decide(id: number, status: 'approved' | 'changes' | 'rejected') {
    await apiClient.post(`/v1/projects/portal/approvals/${id}/decide`, { status });
    setNotice(suite('decide'));
    await load();
  }

  async function book() {
    if (!title.trim() || !day) return;
    await apiClient.post('/v1/projects/portal/appointments', {
      title: title.trim(),
      starts_at: `${day}T${time}:00`,
    });
    setTitle('');
    setNotice(t('requestSent'));
    await load();
  }

  if (!summary) return null;

  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-lg font-semibold">{t('portalTitle')}</h2>
        {summary.account?.name ? <p className="text-sm text-muted-foreground">{summary.account.name}</p> : null}
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t('projects')}</CardDescription>
            <CardTitle className="text-3xl">{formatNumber(summary.projects.length)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t('openTickets')}</CardDescription>
            <CardTitle className="text-3xl">{formatNumber(summary.open_tickets)}</CardTitle>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader className="pb-2">
            <CardDescription>{t('sites')}</CardDescription>
            <CardTitle className="text-3xl">{formatNumber(summary.sites.length)}</CardTitle>
          </CardHeader>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('projects')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {summary.projects.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('noProjects')}</p>
          ) : (
            summary.projects.map((project) => (
              <div key={project.id} className="space-y-1 rounded-md border p-3 text-sm">
                <div className="flex items-center justify-between gap-2">
                  <Link className="font-medium hover:underline" href={dashboardHref(locale, `pm/projects/${project.id}`)}>
                    {project.name}
                  </Link>
                  <Badge variant="secondary">{project.status}</Badge>
                </div>
                <div className="h-1.5 overflow-hidden rounded bg-muted">
                  <div className="h-full bg-primary" style={{ width: `${project.progress_percent}%` }} />
                </div>
                <p className="text-xs text-muted-foreground">
                  {t('progress')} {formatNumber(project.progress_percent)}%
                  {project.due_date ? ` · ${formatDate(project.due_date)}` : ''}
                </p>
              </div>
            ))
          )}
        </CardContent>
      </Card>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('tickets')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {summary.tickets.length === 0 ? <p className="text-sm text-muted-foreground">{t('noTickets')}</p> : null}
            {summary.tickets.map((ticket) => (
              <div key={ticket.id} className="rounded-md border p-3 text-sm">
                <p className="font-medium">{ticket.subject}</p>
                <p className="text-xs text-muted-foreground">
                  {ticket.status}
                  {ticket.created_at ? ` · ${formatDateTime(ticket.created_at)}` : ''}
                </p>
              </div>
            ))}
            <Input value={subject} onChange={(e) => setSubject(e.target.value)} placeholder={t('subject')} />
            <Textarea value={body} onChange={(e) => setBody(e.target.value)} placeholder={t('message')} rows={3} />
            <Button type="button" size="sm" onClick={() => void sendTicket()}>{t('tickets')}</Button>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('appointments')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {summary.appointments.map((appointment) => (
              <div key={appointment.id} className="rounded-md border p-3 text-sm">
                <p className="font-medium">{appointment.title}</p>
                <p className="text-xs text-muted-foreground">
                  {appointment.status} · {formatDateTime(appointment.starts_at)}
                </p>
              </div>
            ))}
            <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder={t('appointmentTitle')} />
            <LocaleDatePicker value={day} onChange={setDay} />
            <Input type="time" value={time} onChange={(e) => setTime(e.target.value)} dir="ltr" />
            <Button type="button" size="sm" onClick={() => void book()}>{t('book')}</Button>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{suite('portalFiles')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {(summary.files ?? []).length === 0 ? <p className="text-sm text-muted-foreground">{suite('noFiles')}</p> : null}
            {(summary.files ?? []).map((file) => (
              <div key={file.id} className="flex items-center justify-between gap-2 rounded-md border p-3 text-sm">
                <div>
                  <p className="font-medium">{file.name}</p>
                  <p className="text-xs text-muted-foreground">{suite('version')} {formatNumber(file.version ?? 1)}</p>
                </div>
                <Button type="button" size="sm" variant="outline" onClick={() => void openFile(file)}>
                  {suite('download')}
                </Button>
              </div>
            ))}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{suite('portalApprovals')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {(summary.approvals ?? []).length === 0 ? <p className="text-sm text-muted-foreground">{suite('noApprovals')}</p> : null}
            {(summary.approvals ?? []).map((approval) => (
              <div key={approval.id} className="space-y-2 rounded-md border p-3 text-sm">
                <div className="flex items-center justify-between gap-2">
                  <p className="font-medium">{approval.title}</p>
                  <Badge variant="secondary">{approvalLabel(approval.status, suite)}</Badge>
                </div>
                {approval.status === 'pending' ? (
                  <div className="flex flex-wrap gap-2">
                    <Button type="button" size="sm" onClick={() => void decide(approval.id, 'approved')}>{suite('approved')}</Button>
                    <Button type="button" size="sm" variant="outline" onClick={() => void decide(approval.id, 'changes')}>{suite('changes')}</Button>
                    <Button type="button" size="sm" variant="outline" onClick={() => void decide(approval.id, 'rejected')}>{suite('rejected')}</Button>
                  </div>
                ) : null}
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('sites')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          {summary.sites.length === 0 ? <p className="text-sm text-muted-foreground">{t('noSites')}</p> : null}
          {summary.sites.map((site) => (
            <div key={site.id} className="flex items-center justify-between rounded-md border p-3 text-sm">
              <span className="font-mono text-xs">{site.domain || site.slug}</span>
              <Badge variant="outline">{site.status}</Badge>
            </div>
          ))}
        </CardContent>
      </Card>
      {notice ? <p className="text-sm text-muted-foreground">{notice}</p> : null}
    </div>
  );
}
