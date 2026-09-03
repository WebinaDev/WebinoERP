/**
 * Bootstrap query cache helpers.
 *
 * The "bootstrap" query holds lightweight UI preferences (theme, accent)
 * fetched once from the server and cached via React Query.
 */
import type { QueryClient } from '@tanstack/react-query'

export const BOOTSTRAP_QUERY_KEY = ['bootstrap'] as const

export interface BootstrapData {
  uiTheme?: 'light' | 'dark' | 'system'
  uiAccent?: string
  [key: string]: unknown
}

/**
 * Optimistically patch the cached bootstrap data in React Query.
 */
export function patchBootstrapQuery(
  qc: QueryClient,
  patch: Partial<BootstrapData>,
): void {
  qc.setQueryData<BootstrapData>(BOOTSTRAP_QUERY_KEY, (prev) => ({
    ...(prev ?? {}),
    ...patch,
  }))
}
