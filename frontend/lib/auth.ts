'use client';

import apiClient from './api-client';

export interface User {
  id: number;
  name: string;
  email: string;
  licensed_modules?: string[];
  active_modules?: string[];
  dashboard_role?: string;
  roles?: string[];
  permissions?: string[];
}

export interface AuthResponse {
  data: {
    user: User;
  };
}

export async function login(email: string, password: string): Promise<AuthResponse> {
  const response = await apiClient.post<AuthResponse>('/v1/core/auth/login', {
    email,
    password,
  });
  return response.data;
}

export async function refreshSession(): Promise<AuthResponse> {
  const response = await apiClient.post<AuthResponse>('/v1/core/auth/refresh');
  return response.data;
}

export async function sendLoginOtp(mobile: string): Promise<{ sent?: boolean; message?: string }> {
  const response = await apiClient.post<{ data: { sent?: boolean; message?: string } }>(
    '/v1/core/auth/otp/send',
    { mobile }
  );
  return response.data.data;
}

export async function verifyLoginOtp(mobile: string, code: string): Promise<{ verified?: boolean }> {
  const response = await apiClient.post<{ data: { verified?: boolean } }>(
    '/v1/core/auth/otp/verify',
    { mobile, code }
  );
  return response.data.data;
}

export async function registerUser(body: {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<{ user_id?: number | null }> {
  const response = await apiClient.post<{ data: { user_id?: number | null } }>(
    '/v1/core/auth/register',
    body
  );
  return response.data.data;
}

export async function logout(): Promise<void> {
  await apiClient.post('/v1/core/auth/logout');
}

export async function getCurrentUser(): Promise<User | null> {
  try {
    const response = await apiClient.get<{
      data: {
        user: User;
        dashboard_role?: string;
        licensed_modules?: string[];
        active_modules?: string[];
        roles?: string[];
        permissions?: string[];
      };
    }>('/v1/core/auth/user');
    const { user, dashboard_role, licensed_modules, active_modules, roles, permissions } = response.data.data;
    return {
      ...user,
      dashboard_role,
      licensed_modules: licensed_modules ?? active_modules ?? user.active_modules,
      active_modules: active_modules ?? licensed_modules ?? user.active_modules,
      roles: roles ?? user.roles,
      permissions: permissions ?? user.permissions,
    };
  } catch {
    return null;
  }
}
