"use client"

import { useQuery } from '@tanstack/react-query'

import { apiFetch } from '@/lib/api'
import {
  BOOTSTRAP_QUERY_KEY,
  type BootstrapData,
} from '@/lib/bootstrapQuery'

/**
 * Fetches lightweight bootstrap/settings data (theme, accent) from the API
 * and caches it for the session.
 */
export function useBootstrapQuery() {
  return useQuery<BootstrapData>({
    queryKey: BOOTSTRAP_QUERY_KEY,
    queryFn: async () => {
      try {
        const data = await apiFetch('settings')
        return (data ?? {}) as BootstrapData
      } catch {
        // Return empty object if API is unavailable (e.g. unauthenticated)
        return {}
      }
    },
    staleTime: 5 * 60 * 1000, // 5 min
    retry: false,
  })
}
