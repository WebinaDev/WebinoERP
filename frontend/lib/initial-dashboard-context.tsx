'use client';

import { createContext, useContext } from 'react';

export type InitialDashboardStats = {
  leads_total?: number;
  projects_active?: number;
  tasks_open?: number;
  tickets_open?: number;
};

const InitialDashboardStatsContext = createContext<InitialDashboardStats | null>(null);

export function InitialDashboardStatsProvider({
  value,
  children,
}: {
  value: InitialDashboardStats | null;
  children: React.ReactNode;
}) {
  return (
    <InitialDashboardStatsContext.Provider value={value}>
      {children}
    </InitialDashboardStatsContext.Provider>
  );
}

export function useInitialDashboardStats(): InitialDashboardStats | null {
  return useContext(InitialDashboardStatsContext);
}
