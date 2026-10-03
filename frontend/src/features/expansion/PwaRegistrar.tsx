'use client';

import { useEffect } from 'react';
import { expansionApi } from '@/features/expansion/api';
import { readOfflineOps, replaceOfflineOps } from '@/features/expansion/offline-queue';

export function PwaRegistrar() {
  useEffect(() => {
    if (!('serviceWorker' in navigator)) return;
    void navigator.serviceWorker.register('/sw.js').catch(() => undefined);

    async function flush() {
      const ops = readOfflineOps();
      if (!ops.length) return;
      try {
        await expansionApi.offlineOps(ops);
        replaceOfflineOps([]);
      } catch {
        /* keep the queue until the network is back */
      }
    }

    window.addEventListener('online', () => void flush());
    if (navigator.onLine) void flush();
  }, []);

  return null;
}
