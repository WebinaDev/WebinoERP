'use client';

import { useCallback, useEffect, useState } from 'react';
import { getModirPayamakCustomers } from '@/lib/api/modirpayamak';

const STORAGE_KEY = 'mp:selectedDomain';

export function useModirPayamakTenantDomain() {
  const [domain, setDomainState] = useState('');
  const [domains, setDomains] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);

  const setDomain = useCallback((next: string) => {
    const value = next.trim();
    setDomainState(value);
    try {
      if (value) sessionStorage.setItem(STORAGE_KEY, value);
      else sessionStorage.removeItem(STORAGE_KEY);
    } catch {
      /* ignore */
    }
  }, []);

  const reload = useCallback(async () => {
    setLoading(true);
    try {
      const accounts = await getModirPayamakCustomers();
      const list = accounts.map((a) => a.domain).filter(Boolean);
      setDomains(list);
      let stored = '';
      try {
        stored = sessionStorage.getItem(STORAGE_KEY) ?? '';
      } catch {
        stored = '';
      }
      if (stored && list.includes(stored)) {
        setDomainState(stored);
      } else if (list.length === 1) {
        setDomain(list[0]);
      } else if (stored && !list.includes(stored)) {
        setDomainState('');
      }
    } catch {
      setDomains([]);
    } finally {
      setLoading(false);
    }
  }, [setDomain]);

  useEffect(() => {
    void reload();
  }, [reload]);

  return { domain, setDomain, domains, loading, reload };
}
