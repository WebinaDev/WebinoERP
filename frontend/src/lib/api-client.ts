import axios, { AxiosInstance, AxiosError } from 'axios';
import { unwrapApiResponse } from '@webina/ui';

/**
 * Browser calls must be same-origin `/api` so Caddy can proxy to Laravel.
 * `http://localhost/api` is wrong on a remote server and causes Axios "Network Error".
 */
function resolveApiBase(): string {
  const raw = process.env.NEXT_PUBLIC_API_URL?.trim()
  if (raw) {
    return raw.replace(/\/$/, '')
  }
  return '/api'
}

const API_URL = resolveApiBase()

/** Sibling fields Site Builder (and similar) attach next to `data` before/after envelope. */
const RESPONSE_SIBLING_KEYS = [
  'compose',
  'power_state',
  'tenant',
  'queued',
  'install',
  'license_sync_error',
] as const

export const apiClient: AxiosInstance = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
});

apiClient.interceptors.response.use(
  (response) => {
    if (response.data && typeof response.data === 'object') {
      const raw = response.data as Record<string, unknown>
      const unwrapped = unwrapApiResponse(response.data)
      const meta: Record<string, unknown> = {
        ...(unwrapped.meta && typeof unwrapped.meta === 'object' ? unwrapped.meta : {}),
      }
      for (const key of RESPONSE_SIBLING_KEYS) {
        if (key in raw && !(key in meta)) {
          meta[key] = raw[key]
        }
      }
      const hasMeta = Object.keys(meta).length > 0
      if (hasMeta || unwrapped.message != null) {
        response.data = {
          data: unwrapped.data,
          ...(hasMeta ? { meta } : {}),
          ...(unwrapped.message != null ? { message: unwrapped.message } : {}),
        }
      } else {
        response.data = unwrapped.data
      }
    }
    return response;
  },
  (error: AxiosError) => {
    if (typeof window !== 'undefined') {
      const path = window.location.pathname;
      const body = error.response?.data as { errors?: { code?: string } } | undefined;
      if (body?.errors?.code === '2FA_REQUIRED' && !path.includes('/login')) {
        const flag = 'webino_2fa_redirect';
        if (!sessionStorage.getItem(flag)) {
          sessionStorage.setItem(flag, '1');
          window.location.href = '/login?challenge=2fa';
        }
        return Promise.reject(error);
      }
      if (error.response?.status === 401 && !path.includes('/login')) {
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

export default apiClient;
