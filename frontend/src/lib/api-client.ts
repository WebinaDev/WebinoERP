import axios, { AxiosInstance, AxiosError } from 'axios';
import { unwrapApiData } from '@webina/ui';

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
      response.data = unwrapApiData(response.data);
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
