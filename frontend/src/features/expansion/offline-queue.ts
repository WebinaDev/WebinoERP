const KEY = 'webino.offline.ops';

export type OfflineOp = {
  client_id: string;
  action: 'create_task' | 'update_task_status';
  payload: Record<string, unknown>;
};

export function readOfflineOps(): OfflineOp[] {
  if (typeof window === 'undefined') return [];
  try {
    const raw = window.localStorage.getItem(KEY);
    const parsed = raw ? (JSON.parse(raw) as OfflineOp[]) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

export function enqueueOfflineOp(op: Omit<OfflineOp, 'client_id'> & { client_id?: string }): OfflineOp {
  const next: OfflineOp = {
    client_id: op.client_id ?? `op-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    action: op.action,
    payload: op.payload,
  };
  const rows = readOfflineOps();
  rows.push(next);
  window.localStorage.setItem(KEY, JSON.stringify(rows));
  return next;
}

export function replaceOfflineOps(rows: OfflineOp[]): void {
  window.localStorage.setItem(KEY, JSON.stringify(rows));
}
