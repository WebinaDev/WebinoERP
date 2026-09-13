import { modirPayamakProxy } from '@/lib/api/modirpayamak';

export type EdgeMeta = {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
  status?: boolean;
  message?: string;
};

export type EdgeRow = Record<string, unknown>;

export type EdgeListResult = {
  items: EdgeRow[];
  meta: EdgeMeta;
  raw: unknown;
  error?: string | null;
};

export type EdgeItemResult = {
  item: EdgeRow | null;
  meta: EdgeMeta;
  raw: unknown;
  error?: string | null;
};

const LIST_KEYS = [
  'entries',
  'items',
  'data',
  'list',
  'patterns',
  'phonebooks',
  'numbers',
  'users',
  'tickets',
  'drafts',
  'messages',
  'records',
  'rows',
  'groups',
] as const;

export function edgeField(row: EdgeRow | null | undefined, ...keys: string[]): string {
  if (!row) return '—';
  for (const key of keys) {
    const v = row[key];
    if (v != null && v !== '') return String(v);
  }
  return '—';
}

export function edgeNumber(row: EdgeRow | null | undefined, ...keys: string[]): number {
  const s = edgeField(row, ...keys);
  if (s === '—') return 0;
  const n = Number(s);
  return Number.isFinite(n) ? n : 0;
}

export function unwrapEdgeList(payload: unknown): EdgeListResult {
  if (payload == null) return { items: [], meta: {}, raw: payload };

  if (Array.isArray(payload)) {
    return { items: payload as EdgeRow[], meta: {}, raw: payload };
  }

  const root = payload as Record<string, unknown>;
  const meta = (root.meta && typeof root.meta === 'object' ? root.meta : {}) as EdgeMeta;
  let data: unknown = root.data ?? root;

  if (Array.isArray(data)) {
    return { items: data as EdgeRow[], meta, raw: payload };
  }

  if (data && typeof data === 'object') {
    const obj = data as Record<string, unknown>;
    for (const key of LIST_KEYS) {
      if (Array.isArray(obj[key])) {
        return {
          items: obj[key] as EdgeRow[],
          meta: (obj.meta && typeof obj.meta === 'object' ? obj.meta : meta) as EdgeMeta,
          raw: payload,
        };
      }
    }
    if (Array.isArray(obj.data)) {
      return { items: obj.data as EdgeRow[], meta, raw: payload };
    }
    if (obj.mock === true && typeof obj.path === 'string') {
      return { items: [], meta, raw: payload };
    }
  }

  return { items: [], meta, raw: payload };
}

export function unwrapEdgeItem(payload: unknown): EdgeItemResult {
  if (payload == null) return { item: null, meta: {}, raw: payload };
  if (Array.isArray(payload)) {
    return { item: (payload[0] as EdgeRow) ?? null, meta: {}, raw: payload };
  }
  const root = payload as Record<string, unknown>;
  const meta = (root.meta && typeof root.meta === 'object' ? root.meta : {}) as EdgeMeta;
  const data = root.data ?? root;
  if (data && typeof data === 'object' && !Array.isArray(data)) {
    return { item: data as EdgeRow, meta, raw: payload };
  }
  return { item: null, meta, raw: payload };
}

type EdgeRequestResult = {
  ok: boolean;
  message: string;
  data: unknown;
  meta: EdgeMeta;
  raw: unknown;
};

async function edgeRequest(
  method: string,
  path: string,
  body?: Record<string, unknown>,
  query?: Record<string, unknown>,
): Promise<EdgeRequestResult> {
  try {
    const res = await modirPayamakProxy(method, path, body, query);
    const envelope = res as { ok: boolean; data?: unknown; meta?: EdgeMeta; message?: string };
    const meta = (envelope.meta ?? {}) as EdgeMeta;
    const data =
      envelope.data !== undefined ? { data: envelope.data, meta } : envelope;
    return {
      ok: envelope.ok !== false,
      message: typeof envelope.message === 'string' ? envelope.message : meta.message ?? '',
      data,
      meta,
      raw: envelope,
    };
  } catch (err) {
    const message = err instanceof Error ? err.message : 'Request failed';
    return { ok: false, message, data: null, meta: {}, raw: null };
  }
}

function asList(res: EdgeRequestResult): EdgeListResult {
  if (!res.ok) return { ...unwrapEdgeList(null), error: res.message };
  const list = unwrapEdgeList(res.data);
  return { ...list, meta: { ...list.meta, ...res.meta }, error: null };
}

function asItem(res: EdgeRequestResult): EdgeItemResult {
  if (!res.ok) return { ...unwrapEdgeItem(null), error: res.message };
  const item = unwrapEdgeItem(res.data);
  return { ...item, meta: { ...item.meta, ...res.meta }, error: null };
}

function withPage(query?: Record<string, unknown>, page = 1, perPage = 50) {
  return { page, per_page: perPage, ...(query ?? {}) };
}

export async function edgeListPatterns(page = 1, perPage = 50) {
  return asList(await edgeRequest('GET', 'api/patterns', undefined, withPage({}, page, perPage)));
}

export async function edgeGetPattern(code: string) {
  return asItem(await edgeRequest('GET', `api/patterns/${encodeURIComponent(code)}`));
}

export async function edgeSavePattern(code: string | null, body: Record<string, unknown>) {
  if (code) {
    return edgeRequest('PUT', `api/patterns/${encodeURIComponent(code)}`, body);
  }
  return edgeRequest('POST', 'api/patterns/normal', body);
}

export async function edgeDeletePattern(code: string) {
  return edgeRequest('DELETE', `api/patterns/${encodeURIComponent(code)}`);
}

export async function edgeListPhonebooks(query?: Record<string, unknown>) {
  return asList(await edgeRequest('GET', 'api/phonebooks/list-new', undefined, withPage(query)));
}

export async function edgeSavePhonebook(id: number | null, body: Record<string, unknown>) {
  if (id) return edgeRequest('PUT', `api/phonebooks/${id}`, body);
  return edgeRequest('POST', 'api/phonebooks', body);
}

export async function edgeDeletePhonebook(id: number) {
  return edgeRequest('POST', 'api/phonebooks/delete-list', { listPhonebooks: [id] });
}

export async function edgeListPhonebookContacts(phonebookId: number, query?: Record<string, unknown>) {
  return asList(
    await edgeRequest(
      'GET',
      'api/phonebooks/numbers/contact-list',
      undefined,
      withPage({ ...(query ?? {}), phonebook_id: String(phonebookId) }),
    ),
  );
}

export async function edgeAddPhonebookContact(phonebookId: number, body: Record<string, unknown>) {
  const item = { ...body, phonebook_id: body.phonebook_id ?? String(phonebookId) };
  return edgeRequest('POST', 'api/phonebooks/numbers/add-list-new', { list: [item] });
}

export async function edgeListNumbers(query?: Record<string, unknown>) {
  return asList(await edgeRequest('GET', 'api/number/numbers', undefined, withPage(query)));
}

export async function edgeListUsers(query?: Record<string, unknown>) {
  return asList(await edgeRequest('GET', 'api/user/list', undefined, withPage(query)));
}

export async function edgeGetUser(userId: string) {
  return asItem(await edgeRequest('GET', 'api/user/show', undefined, { user_id: userId }));
}

export async function edgeCreateUser(body: Record<string, unknown>) {
  return edgeRequest('POST', 'api/user/create', body);
}

export async function edgeUpdateUser(userId: string, body: Record<string, unknown>) {
  return edgeRequest('POST', 'api/user/update', {
    ...body,
    user_id: body.user_id ?? (Number(userId) || userId),
  });
}

export async function edgeListTickets(query?: Record<string, unknown>) {
  return asList(await edgeRequest('GET', 'api/ticket', undefined, withPage(query)));
}

export async function edgeGetTicket(id: number) {
  return asItem(await edgeRequest('GET', 'api/ticket/show', undefined, { ticket_id: id }));
}

export async function edgeCreateTicket(body: Record<string, unknown>) {
  return edgeRequest('POST', 'api/ticket', body);
}

export async function edgeReplyTicket(id: number, body: Record<string, unknown>) {
  return edgeRequest('POST', 'api/ticket/interaction', { ...body, ticket_id: body.ticket_id ?? id });
}

export async function edgeListDraftGroups(query?: Record<string, unknown>) {
  return asList(await edgeRequest('GET', 'api/user/draft/group/list', undefined, withPage(query)));
}

export async function edgeListDrafts(query?: Record<string, unknown>) {
  return asList(await edgeRequest('GET', 'api/user/draft/list', undefined, query ?? {}));
}

export async function edgeCreateDraft(body: Record<string, unknown>) {
  return edgeRequest('POST', 'api/user/draft', body);
}

export async function edgeDeleteDraft(id: number) {
  return edgeRequest('POST', 'api/user/draft/delete', { draft_ids: [id] });
}

export async function edgeListPackages(query?: Record<string, unknown>) {
  return asList(await edgeRequest('GET', 'api/acl/package/list', undefined, withPage(query)));
}

export async function edgeReportOutbox(page = 1, limit = 20, filters: Record<string, unknown> = {}) {
  return asList(await edgeRequest('POST', 'api/report/new_list', { page, limit, filters }));
}

export async function edgeReportInbox(page = 1, limit = 20, filters: Record<string, unknown> = {}) {
  return asList(await edgeRequest('POST', 'api/report/messages-inbox', { page, per_page: limit, filters }));
}

export async function edgeReportOutboxDetail(id: string) {
  return asItem(await edgeRequest('GET', 'api/report/by_bulk', undefined, { messages_outbox_id: id }));
}

export async function edgeMyCredit() {
  return edgeRequest('GET', 'api/payment/credit/mine');
}
