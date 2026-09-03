'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { unwrapData, getAxiosMessage } from '@/lib/api-helpers';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';

const ENDPOINTS: Record<string, (id: string) => string | null> = {
  'pm/projects': (id) => `/v1/projects/projects/${id}/details`,
  projects: (id) => `/v1/projects/projects/${id}/details`,
  'docs/contracts': (id) => `/v1/docs/contracts/${id}`,
  contracts: (id) => `/v1/projects/contracts/${id}/details`,
  tickets: (id) => `/v1/projects/tickets/${id}`,
  invoices: (id) => `/v1/projects/invoices/${id}`,
  appointments: (id) => `/v1/projects/appointments/${id}`,
  leads: (id) => `/v1/crm/leads/${id}`,
  'hrm/staff': (id) => `/v1/hrm/employees/${id}`,
  staff: (id) => `/v1/hrm/employees/${id}`,
  'hrm/payroll': (id) => `/v1/hrm/payroll/${id}`,
  payroll: (id) => `/v1/hrm/payroll/${id}`,
  'crm/customers': (id) => `/v1/crm/accounts/${id}`,
  customers: (id) => `/v1/crm/accounts/${id}`,
  tasks: () => null,
  services: () => null,
  reports: () => null,
  settings: () => null,
};

type Props = {
  root: string;
  id: string;
};

function ProjectDetailView({ data }: { data: Record<string, unknown> }) {
  const t = useTranslations();

  const tasks = (Array.isArray(data.tasks) ? data.tasks : []) as Record<string, unknown>[];

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">{String(data.name ?? t('auto.EntityDetailPage.s_55da48c5'))}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          <p>
            <span className="text-muted-foreground">{t('auto.EntityDetailPage.s_372c3f95')} </span>
            <Badge variant="secondary">{String(data.status ?? '—')}</Badge>
          </p>
          {data.description ? (
            <p>
              <span className="text-muted-foreground">{t('auto.EntityDetailPage.s_d793c505')} </span>
              {String(data.description)}
            </p>
          ) : null}
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('common.tasksCount', { count: tasks.length })}</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-muted-foreground">
                <th className="py-2 text-start">{t('auto.EntityDetailPage.s_1a9bdb20')}</th>
                <th className="py-2 text-start">{t('auto.EntityDetailPage.s_55518965')}</th>
                <th className="py-2 text-start">{t('auto.EntityDetailPage.s_390db738')}</th>
              </tr>
            </thead>
            <tbody>
              {tasks.map((t) => (
                <tr key={String(t.id)} className="border-b border-border/60">
                  <td className="py-2">{String(t.title ?? '—')}</td>
                  <td className="py-2">{String(t.status ?? '—')}</td>
                  <td className="py-2">{String(t.priority ?? '—')}</td>
                </tr>
              ))}
              {!tasks.length ? (
                <tr>
                  <td colSpan={3} className="py-4 text-center text-muted-foreground">
                    {t('auto.EntityDetailPage.s_f0dce633')}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('common.contractsCount', { count: ((Array.isArray(data.contracts) ? data.contracts : []) as Record<string, unknown>[]).length })}</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-muted-foreground">
                <th className="py-2 text-start">{t('auto.EntityDetailPage.s_1a9bdb20')}</th>
                <th className="py-2 text-start">{t('auto.EntityDetailPage.s_f5668e5b')}</th>
                <th className="py-2 text-start">{t('auto.EntityDetailPage.s_55518965')}</th>
              </tr>
            </thead>
            <tbody>
              {((Array.isArray(data.contracts) ? data.contracts : []) as Record<string, unknown>[]).map((c) => (
                <tr key={String(c.id)} className="border-b border-border/60">
                  <td className="py-2">{String(c.title ?? '—')}</td>
                  <td className="py-2">{String(c.amount ?? '—')}</td>
                  <td className="py-2">{String(c.status ?? '—')}</td>
                </tr>
              ))}
              {!((Array.isArray(data.contracts) ? data.contracts : []) as Record<string, unknown>[]).length ? (
                <tr>
                  <td colSpan={3} className="py-4 text-center text-muted-foreground">
                    {t('auto.EntityDetailPage.s_3db70cf0')}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t('common.ticketsCount', { count: ((Array.isArray(data.tickets) ? data.tickets : []) as Record<string, unknown>[]).length })}</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-muted-foreground">
                <th className="py-2 text-start">{t('auto.EntityDetailPage.s_3931f207')}</th>
                <th className="py-2 text-start">{t('auto.EntityDetailPage.s_55518965')}</th>
                <th className="py-2 text-start">{t('auto.EntityDetailPage.s_4b90b688')}</th>
              </tr>
            </thead>
            <tbody>
              {((Array.isArray(data.tickets) ? data.tickets : []) as Record<string, unknown>[]).map((t) => (
                <tr key={String(t.id)} className="border-b border-border/60">
                  <td className="py-2">{String(t.subject ?? '—')}</td>
                  <td className="py-2">{String(t.status ?? '—')}</td>
                  <td className="py-2">{String(t.created_at ?? '—')}</td>
                </tr>
              ))}
              {!((Array.isArray(data.tickets) ? data.tickets : []) as Record<string, unknown>[]).length ? (
                <tr>
                  <td colSpan={3} className="py-4 text-center text-muted-foreground">
                    {t('auto.EntityDetailPage.s_b7853e11')}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>
  );
}

function ContractDetailView({ data }: { data: Record<string, unknown> }) {
  const t = useTranslations();

  const installments = (Array.isArray(data.installments) ? data.installments : []) as Record<string, unknown>[];

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">{String(data.title ?? t('auto.EntityDetailPage.s_9b539f3e'))}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm">
          <p>
            {t('auto.EntityDetailPage.s_752c66d7')} <strong>{String(data.amount ?? '—')}</strong> — {t('common.statusColon')}{' '}
            <Badge variant="secondary">{String(data.status ?? '—')}</Badge>
          </p>
        </CardContent>
      </Card>
      {installments.length > 0 ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t('auto.EntityDetailPage.s_5e82aa42')}</CardTitle>
          </CardHeader>
          <CardContent className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b">
                  <th className="py-2 text-start">{t('auto.EntityDetailPage.s_f5668e5b')}</th>
                  <th className="py-2 text-start">{t('auto.EntityDetailPage.s_8b0f305f')}</th>
                  <th className="py-2 text-start">{t('auto.EntityDetailPage.s_29444d5c')}</th>
                </tr>
              </thead>
              <tbody>
                {installments.map((row) => (
                  <tr key={String(row.id ?? Math.random())} className="border-b border-border/60">
                    <td className="py-2">{String(row.amount ?? '—')}</td>
                    <td className="py-2">{String(row.due_date ?? '—')}</td>
                    <td className="py-2">{row.paid_at ? String(row.paid_at) : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      ) : null}
      {(() => {
        const lead = data.lead as Record<string, unknown> | undefined;
        if (!lead) return null;
        return (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">{t('auto.EntityDetailPage.s_0c9ad868')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <p>
                <span className="text-muted-foreground">{t('auto.EntityDetailPage.s_20974cc2')} </span>
                {String(lead.topic ?? '—')}
              </p>
              <p>
                <span className="text-muted-foreground">{t('auto.EntityDetailPage.s_8b5dcef4')} </span>
                {String(lead.email ?? '—')}
              </p>
              <p>
                <span className="text-muted-foreground">{t('auto.EntityDetailPage.s_c583452b')} </span>
                {String(lead.mobile ?? '—')}
              </p>
            </CardContent>
          </Card>
        );
      })()}
    </div>
  );
}

function resolveEndpoint(root: string, id: string): string | null {
  if (ENDPOINTS[root]) return ENDPOINTS[root](id);
  const leaf = root.split('/').pop() ?? root;
  if (ENDPOINTS[leaf]) return ENDPOINTS[leaf](id);
  return null;
}

export function EntityDetailPage({ root, id }: Props) {
  const t = useTranslations();
  const params = useParams();
  const locale = (params?.locale as string) || 'fa';
  const [parsed, setParsed] = useState<unknown>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const path = resolveEndpoint(root, id);

  const load = useCallback(async () => {
    if (!path) {
      setError(t('errors.notFoundBody'));
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const res = await apiClient.get(path);
      const data = unwrapData<unknown>(res);
      setParsed(data);
    } catch (e) {
      setError(getAxiosMessage(e));
    } finally {
      setLoading(false);
    }
  }, [path, t]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="rounded-lg border bg-card shadow-sm">
      <div className="border-b px-4 py-3">
        <h2 className="text-sm font-medium">
          {locale === 'fa' ? t('common.detailTitleFa', { root, id }) : `${root} #${id}`}
        </h2>
        {path ? (
          <p className="text-xs text-muted-foreground mt-1" dir="ltr">
            GET {path}
          </p>
        ) : null}
      </div>
      <div className="p-4">
        {loading ? <p className="text-sm text-muted-foreground">{t('common.loading')}</p> : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {!loading && !error && path && parsed ? (
          <div className="space-y-4">
            {root === 'projects' && typeof parsed === 'object' && parsed !== null ? (
              <ProjectDetailView data={parsed as Record<string, unknown>} />
            ) : null}
            {root === 'contracts' && typeof parsed === 'object' && parsed !== null ? (
              <ContractDetailView data={parsed as Record<string, unknown>} />
            ) : null}
            {root !== 'projects' && root !== 'contracts' ? (
              <pre className="max-h-[560px] overflow-auto rounded-md bg-muted p-3 text-xs" dir="ltr">
                {JSON.stringify(parsed, null, 2)}
              </pre>
            ) : null}
          </div>
        ) : null}
      </div>
    </div>
  );
}
