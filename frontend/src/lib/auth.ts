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

export interface LoginResult {
  user: User;
  requires_2fa?: boolean;
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

export async function login(email: string, password: string): Promise<LoginResult> {
  const response = await apiClient.post('/v1/core/auth/login', {
    email,
    password,
  });
  const payload = asRecord(response.data);
  return {
    user: (payload.user ?? payload) as User,
    requires_2fa: Boolean(payload.requires_2fa),
  };
}

export async function refreshSession(): Promise<{ user: User }> {
  const response = await apiClient.post('/v1/core/auth/refresh');
  const payload = asRecord(response.data);
  return { user: (payload.user ?? payload) as User };
}

export async function sendLoginOtp(mobile: string): Promise<{ sent?: boolean; message?: string }> {
  const response = await apiClient.post('/v1/core/auth/otp/send', { mobile });
  return asRecord(response.data) as { sent?: boolean; message?: string };
}

export async function verifyLoginOtp(mobile: string, code: string): Promise<{ verified?: boolean }> {
  const response = await apiClient.post('/v1/core/auth/otp/verify', { mobile, code });
  return asRecord(response.data) as { verified?: boolean };
}

export async function registerUser(body: {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<{ user_id?: number | null }> {
  const response = await apiClient.post('/v1/core/auth/register', body);
  return asRecord(response.data) as { user_id?: number | null };
}

export async function logout(): Promise<void> {
  await apiClient.post('/v1/core/auth/logout');
}

export async function getCurrentUser(): Promise<User | null> {
  try {
    const response = await apiClient.get('/v1/core/auth/user');
    const payload = asRecord(response.data);
    const nested = asRecord(payload.user);
    const user = (nested.id ? nested : payload) as User;
    if (!user?.id) {
      return null;
    }
    return {
      ...user,
      dashboard_role: (payload.dashboard_role as string | undefined) ?? user.dashboard_role,
      licensed_modules:
        (payload.licensed_modules as string[] | undefined) ??
        (payload.active_modules as string[] | undefined) ??
        user.active_modules,
      active_modules:
        (payload.active_modules as string[] | undefined) ??
        (payload.licensed_modules as string[] | undefined) ??
        user.active_modules,
      roles: (payload.roles as string[] | undefined) ?? user.roles,
      permissions: (payload.permissions as string[] | undefined) ?? user.permissions,
    };
  } catch {
    return null;
  }
}
