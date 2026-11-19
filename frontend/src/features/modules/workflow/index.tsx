'use client';

import { Inbox, GitPullRequest, UserCheck, History } from 'lucide-react';
import { useTranslations } from 'next-intl';
import { ErpModuleHub, ErpResourcePage, ErpStatGrid, SRC, useErpData } from '@/features/shared/erp-resource';

const NS = 'erp.workflow';

export function WorkflowOverviewPage() {
  const t = useTranslations(NS);
  const inbox = useErpData<unknown[]>('/v1/workflow/inbox');
  const defs = useErpData<unknown[]>('/v1/workflow/definitions');
  const count = (d: unknown) => (Array.isArray(d) ? d.length : Array.isArray((d as { data?: unknown[] })?.data) ? (d as { data: unknown[] }).data.length : '-');
  return (
    <ErpModuleHub
      ns={NS}
      stats={
        <ErpStatGrid
          items={[
            { label: t('pendingForMe'), value: count(inbox.data), icon: <Inbox className="size-4" /> },
            { label: t('definitions'), value: count(defs.data), icon: <GitPullRequest className="size-4" /> },
          ]}
        />
      }
      links={[
        { route: 'workflow/inbox', titleKey: 'inbox', descKey: 'inboxDesc', icon: <Inbox className="size-5" /> },
        { route: 'workflow/definitions', titleKey: 'definitions', descKey: 'definitionsDesc', icon: <GitPullRequest className="size-5" /> },
        { route: 'workflow/delegations', titleKey: 'delegations', descKey: 'delegationsDesc', icon: <UserCheck className="size-5" /> },
        { route: 'workflow/history', titleKey: 'history', descKey: 'historyDesc', icon: <History className="size-5" /> },
      ]}
    />
  );
}

export function WorkflowInboxPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="inboxDesc"
      endpoint="/v1/workflow/inbox"
      columns={[
        { key: 'instance.title', label: 'title' },
        { key: 'instance.definition.name', label: 'definition' },
        { key: 'instance.initiator.name', label: 'initiator' },
        { key: 'instance.amount', label: 'amount', type: 'money' },
        { key: 'step_order', label: 'step', type: 'number' },
        { key: 'sla_due_at', label: 'sla_due_at', type: 'datetime' },
        { key: 'status', label: 'status', type: 'badge' },
      ]}
      rowActions={[
        { label: 'approve', endpoint: (r) => `/v1/workflow/approvals/${r.id}/decide`, body: () => ({ decision: 'approve' }), fields: [{ name: 'comment', label: 'comment', type: 'textarea' }] },
        { label: 'reject', endpoint: (r) => `/v1/workflow/approvals/${r.id}/decide`, body: () => ({ decision: 'reject' }), fields: [{ name: 'comment', label: 'comment', type: 'textarea' }] },
      ]}
    />
  );
}

export function WorkflowDefinitionsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="definitionsDesc"
      endpoint="/v1/workflow/definitions"
      createLabel="newDefinition"
      columns={[
        { key: 'name', label: 'name' },
        { key: 'module_slug', label: 'module', type: 'badge' },
        { key: 'entity_type', label: 'entity_type' },
        { key: 'is_active', label: 'is_active', type: 'bool' },
        { key: 'creator.name', label: 'created_by' },
      ]}
      createFields={[
        { name: 'name', label: 'name', required: true },
        { name: 'module_slug', label: 'module', type: 'select', required: true, options: ['sales', 'accounting', 'procurement', 'hrm', 'scm', 'projects', 'docs', 'assets'] },
        { name: 'entity_type', label: 'entity_type', required: true },
        { name: 'is_active', label: 'is_active', type: 'bool', defaultValue: true },
        {
          name: 'steps',
          label: 'steps',
          type: 'lines',
          required: true,
          lineFields: [
            { name: 'order', label: 'step', type: 'number' },
            { name: 'type', label: 'approver_type', type: 'select', options: ['role', 'user', 'direct_manager'] },
            { name: 'target', label: 'target', type: 'text' },
            { name: 'sla_hours', label: 'sla_hours', type: 'number' },
          ],
        },
      ]}
    />
  );
}

export function WorkflowDelegationsPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="delegationsDesc"
      endpoint="/v1/workflow/delegations"
      createLabel="newDelegation"
      columns={[
        { key: 'delegator.name', label: 'delegator' },
        { key: 'delegate.name', label: 'delegate' },
        { key: 'module_slug', label: 'module', type: 'badge' },
        { key: 'start_date', label: 'start_date', type: 'date' },
        { key: 'end_date', label: 'end_date', type: 'date' },
        { key: 'reason', label: 'reason' },
      ]}
      createFields={[
        { name: 'delegate_id', label: 'delegate', type: 'remote', remote: SRC.users, required: true },
        { name: 'module_slug', label: 'module', type: 'select', options: ['sales', 'accounting', 'procurement', 'hrm', 'scm', 'projects', 'docs', 'assets'] },
        { name: 'start_date', label: 'start_date', type: 'date', required: true },
        { name: 'end_date', label: 'end_date', type: 'date', required: true },
        { name: 'reason', label: 'reason', type: 'textarea' },
      ]}
    />
  );
}

export function WorkflowHistoryPage() {
  return (
    <ErpResourcePage
      ns={NS}
      descriptionKey="historyDesc"
      endpoint="/v1/workflow/history"
      columns={[
        { key: 'title', label: 'title' },
        { key: 'definition.name', label: 'definition' },
        { key: 'initiator.name', label: 'initiator' },
        { key: 'amount', label: 'amount', type: 'money' },
        { key: 'current_step', label: 'step', type: 'number' },
        { key: 'status', label: 'status', type: 'badge' },
        { key: 'created_at', label: 'date', type: 'datetime' },
      ]}
    />
  );
}
