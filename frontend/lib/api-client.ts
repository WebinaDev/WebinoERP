import axios, { AxiosInstance, AxiosError } from 'axios';
import { unwrapApiData } from '@webina/ui';

/** Base URL without trailing slash; paths include `/v1/...` (e.g. `/v1/core/auth/login`). */
const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost/api';

export const apiClient: AxiosInstance = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
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
    if (error.response?.status === 401) {
      if (typeof window !== 'undefined') {
        const path = window.location.pathname;
        if (!path.includes('/login')) {
          window.location.href = '/login';
        }
      }
    }
    return Promise.reject(error);
  }
);

export default apiClient;
