'use client';

import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import apiClient from '@/lib/api-client';
import { FormDialog, clean, type Field, type FormValues } from './FormDialog';
import { hubBase, type HubSummary } from './hub-api';

export type ActionName =
  | 'note' | 'invoice' | 'payment' | 'license' | 'renew' | 'ticket' | 'project' | 'message' | 'domain' | 'service'
  | 'owner' | 'suspend' | 'activate' | 'contact' | 'document' | 'relation' | 'risk' | 'pinned' | 'profile' | 'usage';

/** Which backend MANAGE permission key gates each dialog. */
export const ACTION_PERMISSION: Record<ActionName, string> = {
  note: 'notes', invoice: 'invoice', payment: 'payment', license: 'license', renew: 'license', ticket: 'ticket',
  project: 'project', message: 'profile', domain: 'assets', service: 'assets', owner: 'owner', suspend: 'suspend',
  activate: 'suspend', contact: 'contacts', document: 'documents', relation: 'relations', risk: 'notes', pinned: 'notes',
  profile: 'profile', usage: 'assets',
};

type Ctx = {
  id: string;
  summary: HubSummary;
  version: number;
  refresh: () => void;
  canView: (section: string) => boolean;
  canDo: (action: ActionName) => boolean;
  open: (action: ActionName, initial?: FormValues & { _id?: string }) => void;
  setNotice: (msg: string | null) => void;
};

export const DOC_MAX_BYTES = 10 * 1024 * 1024;
export const DOC_ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.zip';

const HubCtx = createContext<Ctx | null>(null);

export function useHub(): Ctx {
  const c = useContext(HubCtx);
  if (!c) throw new Error('useHub outside HubProvider');
  return c;
}

export function HubProvider({ id, summary, onRefresh, setNotice, externalVersion = 0, children }: { id: string; summary: HubSummary; onRefresh: () => void; setNotice: (m: string | null) => void; externalVersion?: number; children: ReactNode }) {
  const t = useTranslations('crm.hub');
  const [version, setVersion] = useState(0);
  const [active, setActive] = useState<{ name: ActionName; initial?: FormValues & { _id?: string } } | null>(null);
  const base = hubBase(id);

  const refresh = useCallback(() => {
    setVersion((v) => v + 1);
    onRefresh();
  }, [onRefresh]);

  const opt = (prefix: string, keys: string[]): [string, string][] => keys.map((k) => [k, t(`${prefix}.${k}` as never)]);

  const defs: Record<ActionName, { title: string; fields: Field[]; submit: (v: FormValues, rid?: string) => Promise<unknown>; danger?: boolean }> = useMemo(
    () => ({
      note: {
        title: t('act.note'),
        fields: [{ name: 'body', label: t('a.body'), type: 'textarea', required: true }, { name: 'pin', label: t('a.pin'), type: 'checkbox' }],
        submit: (v) => apiClient.post(`${base}/actions/note`, clean(v)),
      },
      invoice: {
        title: t('act.invoice'),
        fields: [
          { name: 'title', label: t('a.itemTitle'), required: true, full: true },
          { name: 'price', label: t('a.price'), type: 'number', required: true },
          { name: 'discount', label: t('b.amount') + ' −', type: 'number' },
          { name: 'issue_date', label: t('b.date'), type: 'date' },
          { name: 'status', label: t('b.status'), type: 'select', required: true, options: opt('inv', ['unpaid', 'draft']) },
          { name: 'notes', label: t('a.body'), type: 'textarea' },
        ],
        submit: (v) =>
          apiClient.post(`${base}/actions/invoice`, {
            items: [{ title: v.title, price: Number(v.price), discount: v.discount ? Number(v.discount) : 0 }],
            ...clean({ issue_date: v.issue_date, status: v.status, notes: v.notes }),
          }),
      },
      payment: {
        title: t('act.payment'),
        fields: [
          { name: 'amount', label: t('b.amount'), type: 'number', required: true },
          { name: 'method', label: t('a.method'), type: 'select', required: true, options: opt('pm', ['card_to_card', 'bank_transfer', 'cash', 'cheque', 'pos', 'other']) },
          { name: 'reference', label: t('a.reference') },
          { name: 'paid_at', label: t('b.date'), type: 'date' },
          { name: 'invoice_id', label: t('a.invoiceId'), type: 'number' },
          { name: 'note', label: t('a.body'), type: 'textarea' },
        ],
        submit: (v) => apiClient.post(`${base}/actions/payment`, clean(v, ['amount', 'invoice_id'])),
      },
      license: {
        title: t('act.license'),
        fields: [
          { name: 'domain', label: t('s.domain'), required: true, placeholder: 'example.ir' },
          { name: 'product', label: t('l.product'), placeholder: 'webino' },
          { name: 'license_type', label: t('a.licenseType'), type: 'select', required: true, options: opt('lt', ['yearly', 'monthly', 'perpetual', 'trial']) },
          { name: 'expires_at', label: t('l.expires'), type: 'date' },
          { name: 'max_users', label: t('a.maxUsers'), type: 'number' },
        ],
        submit: (v) => apiClient.post(`${base}/actions/license`, clean(v, ['max_users'])),
      },
      renew: {
        title: t('l.renew'),
        fields: [{ name: 'months', label: t('l.months'), type: 'number', required: true }, { name: 'expires_at', label: t('l.expires'), type: 'date' }],
        submit: (v, rid) => apiClient.post(`${base}/actions/license/${rid}/renew`, clean(v, ['months'])),
      },
      ticket: {
        title: t('act.ticket'),
        fields: [
          { name: 'subject', label: t('a.subject'), required: true, full: true },
          { name: 'priority', label: t('a.priority'), type: 'select', required: true, options: opt('pr', ['normal', 'low', 'high', 'urgent']) },
          { name: 'department', label: t('su.dept') },
          { name: 'body', label: t('a.body'), type: 'textarea' },
        ],
        submit: (v) => apiClient.post(`${base}/actions/ticket`, clean(v)),
      },
      project: {
        title: t('act.project'),
        fields: [
          { name: 'name', label: t('a.projectName'), required: true, full: true },
          { name: 'start_date', label: t('l.start'), type: 'date' },
          { name: 'due_date', label: t('a.dueDate'), type: 'date' },
          { name: 'budget_amount', label: t('b.amount'), type: 'number' },
          { name: 'description', label: t('a.body'), type: 'textarea' },
        ],
        submit: (v) => apiClient.post(`${base}/actions/project`, clean(v, ['budget_amount'])),
      },
      message: {
        title: t('act.message'),
        fields: [
          { name: 'channel', label: t('a.channel'), type: 'select', required: true, options: opt('ch', ['sms', 'email']) },
          { name: 'to', label: t('a.to'), required: true },
          { name: 'subject', label: t('a.subject'), full: true },
          { name: 'body', label: t('a.body'), type: 'textarea', required: true },
        ],
        submit: (v) => apiClient.post(`${base}/actions/message`, clean(v)),
      },
      domain: {
        title: t('act.domain'),
        fields: [
          { name: 'domain', label: t('s.domain'), required: true, placeholder: 'example.ir' },
          { name: 'registrar', label: t('d.registrar') },
          { name: 'registered_at', label: t('d.registered'), type: 'date' },
          { name: 'expires_at', label: t('d.expires'), type: 'date' },
          { name: 'nameservers', label: t('d.ns'), placeholder: 'ns1.example.ir, ns2.example.ir', full: true },
          { name: 'auto_renew', label: t('d.autoRenew'), type: 'checkbox' },
        ],
        submit: (v) =>
          apiClient.post(`${base}/domains`, {
            ...clean(v),
            nameservers: v.nameservers ? String(v.nameservers).split(/[,\s]+/).filter(Boolean) : undefined,
          }),
      },
      service: {
        title: t('act.service'),
        fields: [
          { name: 'kind', label: t('i.kind'), type: 'select', required: true, options: [['hosting', 'Hosting'], ['vps', 'VPS'], ['gateway', 'Payment gateway'], ['sms', 'SMS'], ['accounting', 'Accounting'], ['shipping', 'Shipping'], ['api_key', 'API key'], ['other', t('dc.other')]] },
          { name: 'name', label: t('doc.title'), required: true },
          { name: 'provider', label: t('i.provider') },
          { name: 'plan', label: t('i.plan') },
          { name: 'ip', label: 'IP' },
          { name: 'site_domain', label: t('s.domain') },
          { name: 'renews_at', label: t('i.renews'), type: 'date' },
          { name: 'last_backup_at', label: t('i.backup'), type: 'date' },
          { name: 'secret', label: t('i.secret'), type: 'password', full: true },
        ],
        submit: (v) => apiClient.post(`${base}/services`, clean(v)),
      },
      owner: {
        title: t('act.owner'),
        fields: [{ name: 'owner_id', label: t('a.ownerId'), type: 'number', required: true }],
        submit: (v) => apiClient.post(`${base}/actions/owner`, clean(v, ['owner_id'])),
      },
      suspend: {
        title: t('act.suspend'),
        danger: true,
        fields: [
          { name: 'reason', label: t('a.reason'), type: 'textarea', required: true },
          { name: 'confirm', label: t('sus.confirmSuspend'), type: 'checkbox', required: true, full: true },
        ],
        submit: (v) => apiClient.post(`${base}/actions/suspend`, { reason: v.reason, confirm: true }),
      },
      activate: {
        title: t('act.activate'),
        fields: [{ name: 'confirm', label: t('sus.confirmActivate'), type: 'checkbox', required: true, full: true }],
        submit: () => apiClient.post(`${base}/actions/activate`, { confirm: true }),
      },
      contact: {
        title: t('act.contact'),
        fields: [
          { name: 'first_name', label: t('c.firstName'), required: true },
          { name: 'last_name', label: t('c.lastName') },
          { name: 'job_title', label: t('c.jobTitle') },
          { name: 'contact_role', label: t('c.role'), type: 'select', options: opt('role', ['manager', 'finance', 'technical', 'support', 'other']) },
          { name: 'mobile', label: t('c.mobile') },
          { name: 'phone', label: t('c.phone') },
          { name: 'email', label: t('c.email') },
          { name: 'whatsapp', label: t('c.whatsapp') },
          { name: 'is_primary', label: t('c.default'), type: 'checkbox' },
        ],
        submit: (v, rid) => (rid ? apiClient.patch(`${base}/contacts/${rid}`, clean(v)) : apiClient.post(`${base}/contacts`, clean(v))),
      },
      document: {
        title: t('act.document'),
        fields: [
          { name: 'category', label: t('doc.category'), type: 'select', required: true, options: opt('dc', ['national_card', 'gazette', 'enamad', 'permit', 'contract', 'proposal', 'invoice', 'brief', 'deliverable', 'other']) },
          { name: 'title', label: t('doc.title'), required: true },
          { name: 'file', label: t('doc.file'), type: 'file', full: true, accept: DOC_ACCEPT, hint: t('doc.fileHint') },
          { name: 'url', label: t('doc.url'), full: true },
          { name: 'expires_at', label: t('doc.expires'), type: 'date' },
          { name: 'visible_to_roles', label: t('doc.visibility'), placeholder: 'accountant, crm_specialist' },
        ],
        submit: (v) => {
          const roles = v.visible_to_roles ? String(v.visible_to_roles).split(/[,\s]+/).filter(Boolean) : undefined;
          if (v.file instanceof File) {
            if (v.file.size > DOC_MAX_BYTES) return Promise.reject(new Error(t('doc.tooLarge')));
            const fd = new FormData();
            Object.entries(clean(v)).forEach(([k, val]) => k !== 'visible_to_roles' && fd.append(k, String(val)));
            fd.append('file', v.file);
            if (roles) fd.append('visible_to_roles', JSON.stringify(roles));
            return apiClient.post(`${base}/documents`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
          }
          return apiClient.post(`${base}/documents`, { ...clean(v), visible_to_roles: roles });
        },
      },
      relation: {
        title: t('act.relation'),
        fields: [
          { name: 'related_account_id', label: t('r.accountId'), type: 'number', required: true },
          { name: 'relation_type', label: t('r.type'), type: 'select', required: true, options: opt('rt', ['related', 'holding', 'partner', 'referral']) },
          { name: 'note', label: t('a.body'), full: true },
        ],
        submit: (v) => apiClient.post(`${base}/relations`, clean(v, ['related_account_id'])),
      },
      risk: {
        title: t('n.newRisk'),
        fields: [
          { name: 'severity', label: t('su.priority'), type: 'select', required: true, options: opt('sev', ['warning', 'critical', 'info']) },
          { name: 'body', label: t('a.body'), type: 'textarea', required: true },
        ],
        submit: (v) => apiClient.post(`${base}/alerts`, { ...clean(v), kind: 'risk' }),
      },
      pinned: {
        title: t('n.newPinned'),
        fields: [{ name: 'body', label: t('a.body'), type: 'textarea', required: true }],
        submit: (v) => apiClient.post(`${base}/alerts`, { ...clean(v), kind: 'pinned_note', severity: 'info' }),
      },
      profile: {
        title: t('edit'),
        fields: [
          { name: 'name', label: t('f.name'), required: true },
          { name: 'brand_name', label: t('f.brand') },
          { name: 'customer_kind', label: t('f.kind'), type: 'select', options: opt('kind', ['individual', 'legal', 'partner', 'vip']) },
          { name: 'hub_status', label: t('f.status'), type: 'select', options: opt('status', ['active', 'dormant', 'debtor', 'prospect']) },
          { name: 'national_id', label: t('f.nationalId') },
          { name: 'economic_code', label: t('f.economicCode') },
          { name: 'registration_no', label: t('f.registrationNo') },
          { name: 'postal_code', label: t('f.postalCode') },
          { name: 'province', label: t('f.province') },
          { name: 'city', label: t('f.city') },
          { name: 'country', label: t('f.country') },
          { name: 'industry', label: t('f.industry') },
          { name: 'website', label: t('f.website') },
          { name: 'logo_url', label: t('f.logo') },
          { name: 'credit_limit', label: t('f.creditLimit'), type: 'number' },
          { name: 'address_line', label: t('f.address'), type: 'textarea' },
          { name: 'kyc_status', label: t('kyc.title'), type: 'select', options: opt('kyc', ['none', 'pending', 'verified', 'rejected']) },
          { name: 'kyc_note', label: t('kyc.note') },
          { name: 'preferred_language', label: t('p.language'), type: 'select', options: [['fa', 'فارسی'], ['en', 'English']] },
          { name: 'preferred_channel', label: t('p.channel'), type: 'select', options: opt('ch', ['phone', 'sms', 'email', 'whatsapp']) },
          { name: 'timezone', label: t('p.timezone'), placeholder: 'Asia/Tehran' },
          { name: 'marketing_sms_opt_out', label: t('p.optOut'), type: 'checkbox' },
        ],
        submit: (v) => {
          const body: Record<string, unknown> = {};
          Object.entries(v).forEach(([k, val]) => {
            body[k] = val === '' ? null : val;
          });
          if (body.credit_limit !== null && body.credit_limit !== undefined) body.credit_limit = Number(body.credit_limit);
          if (!body.hub_status) delete body.hub_status;
          return apiClient.patch(`${base}/profile`, body);
        },
      },
      usage: {
        title: t('tab.usage'),
        fields: [
          { name: 'metric', label: t('i.kind'), type: 'select', required: true, options: [['storage_mb', t('u.storage')], ['traffic_mb', t('u.traffic')], ['bandwidth_mb', t('u.bandwidth')], ['cpu_pct', t('u.cpu')], ['ram_mb', t('u.ram')], ['orders', t('u.orders')], ['sms_sent', t('u.smsSent')], ['panel_users', t('u.panelUsers')]] },
          { name: 'value', label: t('b.amount'), type: 'number', required: true },
          { name: 'period', label: t('b.date'), type: 'date' },
        ],
        submit: (v) => apiClient.post(`${base}/usage-metrics`, clean(v, ['value'])),
      },
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [base, t],
  );

  const ctx: Ctx = {
    id,
    summary,
    version: version + externalVersion,
    refresh,
    canView: (s) => summary.sections.includes(s),
    canDo: (a) => Boolean(summary.actions?.[ACTION_PERMISSION[a]]),
    open: (name, initial) => setActive({ name, initial }),
    setNotice,
  };

  const def = active ? defs[active.name] : null;
  const initial = active?.initial ? Object.fromEntries(Object.entries(active.initial).filter(([k]) => k !== '_id')) : undefined;

  return (
    <HubCtx.Provider value={ctx}>
      {children}
      {def && active ? (
        <FormDialog
          open
          onOpenChange={(o) => !o && setActive(null)}
          title={def.title}
          fields={def.fields}
          initial={initial as FormValues | undefined}
          danger={def.danger}
          onSubmit={(v) => def.submit(v, active.initial?._id as string | undefined)}
          onDone={() => {
            setNotice(t('saved'));
            refresh();
          }}
        />
      ) : null}
    </HubCtx.Provider>
  );
}
