'use client';

import { useCallback, useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { CrmPageLayout } from '@/features/shared/layout/CrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { expansionApi, type Row } from '@/features/expansion/api';

const selectClass = 'h-9 rounded-md border border-input bg-background px-3 text-sm';
const SOURCES: Record<string, string[]> = {
  deals: ['id', 'name', 'amount', 'currency_code', 'company_id', 'probability'],
  leads: ['id', 'topic', 'first_name', 'last_name', 'email', 'company', 'lead_score'],
  tasks: ['id', 'title', 'status', 'priority', 'project_id', 'assignee_id', 'duration_days'],
  content: ['id', 'title', 'status', 'calendar_id', 'assignee_id'],
};
const NODE_TYPES = ['trigger', 'condition', 'notify', 'create_task', 'set_flag'];

type NodeDraft = { id: string; type: string; title: string };

export function StudioPage() {
  const t = useTranslations('expansion');
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [workflows, setWorkflows] = useState<Row[]>([]);
  const [name, setName] = useState('');
  const [nodes, setNodes] = useState<NodeDraft[]>([{ id: 't', type: 'trigger', title: 'start' }]);
  const [edgeTo, setEdgeTo] = useState('');
  const [reports, setReports] = useState<Row[]>([]);
  const [report, setReport] = useState({ name: '', source: 'deals', columns: ['id', 'name'] as string[] });
  const [preview, setPreview] = useState<Row | null>(null);
  const [dragCol, setDragCol] = useState('');
  const [flags, setFlags] = useState<Row[]>([]);
  const [flag, setFlag] = useState({ key: '', enabled: true, rollout_percent: '100', sandbox_only: false });
  const [flagResult, setFlagResult] = useState('');
  const [providers, setProviders] = useState<Row[]>([]);
  const [sso, setSso] = useState({ name: '', protocol: 'oidc', issuer: '', client_id: '' });
  const [scim, setScim] = useState('');

  const load = useCallback(async () => {
    try {
      const [w, r, f, s] = await Promise.all([expansionApi.workflows(), expansionApi.reports(), expansionApi.flags(), expansionApi.sso()]);
      setWorkflows(w);
      setReports(r);
      setFlags(f);
      setProviders(s);
    } catch (err) {
      applyAxiosError(err);
    }
  }, [applyAxiosError]);

  useEffect(() => {
    void load();
  }, [load]);

  function addNode() {
    const id = `n${nodes.length}`;
    setNodes([...nodes, { id, type: 'notify', title: id }]);
    if (!edgeTo) setEdgeTo(id);
  }

  function onDropColumn(column: string) {
    if (!column || report.columns.includes(column)) return;
    setReport({ ...report, columns: [...report.columns, column] });
  }

  return (
    <CrmPageLayout title={t('studio.title')} description={t('studio.description')} {...layoutProps}>
      <Tabs defaultValue="flow">
        <TabsList className="flex h-auto flex-wrap">
          <TabsTrigger value="flow">{t('studio.tabs.flow')}</TabsTrigger>
          <TabsTrigger value="bi">{t('studio.tabs.bi')}</TabsTrigger>
          <TabsTrigger value="flags">{t('studio.tabs.flags')}</TabsTrigger>
          <TabsTrigger value="sso">{t('studio.tabs.sso')}</TabsTrigger>
        </TabsList>
        <TabsContent value="flow" className="space-y-4">
          <div className="flex flex-wrap gap-2">
            <Input className="max-w-xs" placeholder={t('studio.flowName')} value={name} onChange={(e) => setName(e.target.value)} />
            <Button type="button" variant="outline" onClick={addNode}>{t('studio.addNode')}</Button>
            <Button
              type="button"
              onClick={() => {
                const graph = {
                  nodes: nodes.map((node) => ({ id: node.id, type: node.type, config: { title: node.title, user_id: undefined } })),
                  edges: edgeTo ? [{ from: nodes[0]?.id ?? 't', to: edgeTo }] : [],
                };
                void expansionApi.saveWorkflow({ name, status: 'published', graph }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError);
              }}
            >
              {t('common.save')}
            </Button>
          </div>
          <div className="grid gap-3 md:grid-cols-3">
            {nodes.map((node, index) => (
              <Card
                key={node.id}
                draggable
                onDragStart={(e) => e.dataTransfer.setData('text/node', String(index))}
                onDragOver={(e) => e.preventDefault()}
                onDrop={(e) => {
                  const from = Number(e.dataTransfer.getData('text/node'));
                  if (Number.isNaN(from) || from === index) return;
                  const next = [...nodes];
                  const [moved] = next.splice(from, 1);
                  if (!moved) return;
                  next.splice(index, 0, moved);
                  setNodes(next);
                }}
              >
                <CardContent className="space-y-2 py-4">
                  <p className="text-xs text-muted-foreground">{node.id}</p>
                  <select className={selectClass} value={node.type} onChange={(e) => setNodes(nodes.map((item) => item.id === node.id ? { ...item, type: e.target.value } : item))}>
                    {NODE_TYPES.map((type) => <option key={type} value={type}>{t(`studio.nodes.${type}`)}</option>)}
                  </select>
                  <Input value={node.title} onChange={(e) => setNodes(nodes.map((item) => item.id === node.id ? { ...item, title: e.target.value } : item))} />
                </CardContent>
              </Card>
            ))}
          </div>
          <label className="block max-w-xs space-y-1 text-sm">
            <span>{t('studio.next')}</span>
            <select className={`${selectClass} w-full`} value={edgeTo} onChange={(e) => setEdgeTo(e.target.value)}>
              <option value="">{t('studio.none')}</option>
              {nodes.slice(1).map((node) => <option key={node.id} value={node.id}>{node.id}</option>)}
            </select>
          </label>
          <ul className="space-y-2 text-sm">
            {workflows.map((row) => (
              <li key={String(row.id)} className="flex items-center justify-between gap-2 rounded-md border border-border/60 px-3 py-2">
                <span>{String(row.name)}</span>
                <Button type="button" size="sm" variant="outline" onClick={() => void expansionApi.runWorkflow(Number(row.id)).then(() => setSuccess(t('studio.ran'))).catch(applyAxiosError)}>{t('studio.run')}</Button>
              </li>
            ))}
          </ul>
        </TabsContent>
        <TabsContent value="bi" className="space-y-4">
          <div className="flex flex-wrap gap-2">
            <Input className="max-w-xs" placeholder={t('studio.reportName')} value={report.name} onChange={(e) => setReport({ ...report, name: e.target.value })} />
            <select className={selectClass} value={report.source} onChange={(e) => setReport({ ...report, source: e.target.value, columns: ['id'] })}>
              {Object.keys(SOURCES).map((source) => <option key={source} value={source}>{source}</option>)}
            </select>
          </div>
          <div className="flex flex-wrap gap-2" onDragOver={(e) => e.preventDefault()} onDrop={() => onDropColumn(dragCol)}>
            {SOURCES[report.source]?.map((column) => (
              <button
                key={column}
                type="button"
                draggable
                onDragStart={() => setDragCol(column)}
                className={`rounded-full border px-3 py-1 text-xs ${report.columns.includes(column) ? 'bg-primary text-primary-foreground' : 'bg-background'}`}
                onClick={() => setReport({
                  ...report,
                  columns: report.columns.includes(column) ? report.columns.filter((item) => item !== column) : [...report.columns, column],
                })}
              >
                {column}
              </button>
            ))}
          </div>
          <p className="text-xs text-muted-foreground">{t('studio.dragHint')}</p>
          <Button type="button" onClick={() => void expansionApi.saveReport({ name: report.name, source: report.source, columns: report.columns, layout: { chart: 'bar' } }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}>{t('common.save')}</Button>
          <ul className="space-y-2 text-sm">
            {reports.map((row) => (
              <li key={String(row.id)} className="flex flex-wrap items-center gap-2">
                <span>{String(row.name)}</span>
                <Button type="button" size="sm" variant="outline" onClick={() => void expansionApi.runReport(Number(row.id)).then(setPreview).catch(applyAxiosError)}>{t('studio.run')}</Button>
                <a className="text-xs underline" href={`/api/v1/core/bi-reports/${String(row.id)}/export.csv`}>{t('studio.export')}</a>
              </li>
            ))}
          </ul>
          {preview ? <pre className="max-h-72 overflow-auto rounded-md bg-muted p-3 text-xs">{JSON.stringify(preview, null, 2)}</pre> : null}
        </TabsContent>
        <TabsContent value="flags" className="max-w-lg space-y-3">
          <Input placeholder={t('studio.flagKey')} value={flag.key} onChange={(e) => setFlag({ ...flag, key: e.target.value })} />
          <Input placeholder={t('studio.rollout')} value={flag.rollout_percent} onChange={(e) => setFlag({ ...flag, rollout_percent: e.target.value })} />
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={flag.sandbox_only} onChange={(e) => setFlag({ ...flag, sandbox_only: e.target.checked })} />
            {t('studio.sandbox')}
          </label>
          <div className="flex gap-2">
            <Button type="button" onClick={() => void expansionApi.saveFlag({ ...flag, enabled: true, rollout_percent: Number(flag.rollout_percent) }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}>{t('common.save')}</Button>
            <Button type="button" variant="outline" onClick={() => void expansionApi.evaluateFlags([flag.key], true).then((row) => setFlagResult(JSON.stringify(row.flags))).catch(applyAxiosError)}>{t('studio.evaluate')}</Button>
          </div>
          {flagResult ? <p className="text-sm">{flagResult}</p> : null}
          <ul className="text-sm">{flags.map((row) => <li key={String(row.key)}>{String(row.key)} · {String(row.rollout_percent)}%</li>)}</ul>
        </TabsContent>
        <TabsContent value="sso" className="max-w-lg space-y-3">
          <Input placeholder={t('studio.provider')} value={sso.name} onChange={(e) => setSso({ ...sso, name: e.target.value })} />
          <select className={`${selectClass} w-full`} value={sso.protocol} onChange={(e) => setSso({ ...sso, protocol: e.target.value })}>
            <option value="oidc">OIDC</option>
            <option value="saml">SAML</option>
          </select>
          <Input placeholder="Issuer" value={sso.issuer} onChange={(e) => setSso({ ...sso, issuer: e.target.value })} />
          <Input placeholder="Client ID" value={sso.client_id} onChange={(e) => setSso({ ...sso, client_id: e.target.value })} />
          <Button type="button" onClick={() => void expansionApi.saveSso({ ...sso, enabled: true }).then(() => { setSuccess(t('common.saved')); return load(); }).catch(applyAxiosError)}>{t('common.save')}</Button>
          <ul className="space-y-2 text-sm">
            {providers.map((row) => (
              <li key={String(row.id)}>
                {String(row.name)} · {String(row.protocol)}
                {row.protocol === 'saml' ? <span className="ms-2 text-xs text-muted-foreground">/api/v1/core/sso/saml/{String(row.id)}/metadata</span> : null}
              </li>
            ))}
          </ul>
          <Button type="button" variant="outline" onClick={() => void expansionApi.issueScim('default').then((row) => setScim(row.token)).catch(applyAxiosError)}>{t('studio.scim')}</Button>
          {scim ? <p className="break-all text-xs">{scim}</p> : null}
          <p className="text-xs text-muted-foreground">{t('studio.ssoHint')}</p>
        </TabsContent>
      </Tabs>
    </CrmPageLayout>
  );
}
