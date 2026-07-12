'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { accountingFiscalYears, type FiscalYear } from '@/lib/api/accounting';

type FiscalYearContextValue = {
  years: FiscalYear[];
  fiscalYearId: number;
  setFiscalYearId: (id: number) => void;
  loading: boolean;
  reload: () => Promise<void>;
};

const FiscalYearContext = createContext<FiscalYearContextValue | null>(null);

export function FiscalYearProvider({ children }: { children: ReactNode }) {
  const [years, setYears] = useState<FiscalYear[]>([]);
  const [fiscalYearId, setFiscalYearId] = useState(0);
  const [loading, setLoading] = useState(true);

  const reload = useCallback(async () => {
    setLoading(true);
    try {
      const res = await accountingFiscalYears();
      const items = res.data?.items ?? [];
      setYears(items);
      const active = items.find((y) => y.is_active === 1 || y.is_active === true);
      const resolved = active?.id ?? items[0]?.id ?? 0;
      setFiscalYearId((prev) => (prev > 0 ? prev : resolved));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const value = useMemo(
    () => ({ years, fiscalYearId, setFiscalYearId, loading, reload }),
    [years, fiscalYearId, loading, reload],
  );

  return <FiscalYearContext.Provider value={value}>{children}</FiscalYearContext.Provider>;
}

export function useFiscalYear() {
  const ctx = useContext(FiscalYearContext);
  if (!ctx) throw new Error('useFiscalYear must be used within FiscalYearProvider');
  return ctx;
}
