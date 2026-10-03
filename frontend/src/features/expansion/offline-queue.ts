const KEY = 'webino.offline.ops';

export type OfflineOp = {
  client_id: string;
  action: 'create_task' | 'update_task_status' | 'shift_task';
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
  void mirrorIndexedDb(rows);
  void registerSync();
  return next;
}

export function replaceOfflineOps(rows: OfflineOp[]): void {
  window.localStorage.setItem(KEY, JSON.stringify(rows));
  void mirrorIndexedDb(rows);
}

async function mirrorIndexedDb(rows: OfflineOp[]): Promise<void> {
  if (typeof indexedDB === 'undefined') return;
  await new Promise<void>((resolve) => {
    const request = indexedDB.open('webino-offline', 1);
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains('ops')) db.createObjectStore('ops');
    };
    request.onsuccess = () => {
      const db = request.result;
      const tx = db.transaction('ops', 'readwrite');
      tx.objectStore('ops').put(rows, 'queue');
      tx.oncomplete = () => {
        db.close();
        resolve();
      };
      tx.onerror = () => resolve();
    };
    request.onerror = () => resolve();
  });
}

export async function restoreOfflineOps(): Promise<OfflineOp[]> {
  const local = readOfflineOps();
  if (local.length || typeof indexedDB === 'undefined') return local;
  const restored = await new Promise<OfflineOp[]>((resolve) => {
    const request = indexedDB.open('webino-offline', 1);
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains('ops')) db.createObjectStore('ops');
    };
    request.onsuccess = () => {
      const db = request.result;
      const tx = db.transaction('ops', 'readonly');
      const get = tx.objectStore('ops').get('queue');
      get.onsuccess = () => {
        const value = get.result;
        db.close();
        resolve(Array.isArray(value) ? value : []);
      };
      get.onerror = () => resolve([]);
    };
    request.onerror = () => resolve([]);
  });
  if (restored.length) window.localStorage.setItem(KEY, JSON.stringify(restored));
  return restored;
}

async function registerSync(): Promise<void> {
  if (!('serviceWorker' in navigator)) return;
  const registration = await navigator.serviceWorker.ready.catch(() => null);
  const sync = registration && 'sync' in registration ? (registration as ServiceWorkerRegistration & { sync?: { register: (tag: string) => Promise<void> } }).sync : undefined;
  if (sync) await sync.register('webino-offline').catch(() => undefined);
}
