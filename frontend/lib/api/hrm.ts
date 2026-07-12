import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/hrm';

export async function getStaff(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/staff`, { params });
  return unwrapData(res);
}

export async function saveStaff(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/staff`, data);
  return unwrapData(res);
}

export async function deleteStaff(id: number) {
  const res = await apiClient.delete(`${BASE}/staff/${id}`);
  return unwrapData(res);
}

export async function getStaffProfile(id: number | string) {
  const res = await apiClient.get(`${BASE}/staff/${id}/profile`);
  return unwrapData(res);
}

export async function saveStaffProfile(id: number | string, data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/staff/${id}/profile`, data);
  return unwrapData(res);
}

export async function attendanceCheckIn(data?: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/attendance/check-in`, data ?? {});
  return unwrapData(res);
}

export async function attendanceCheckOut(data?: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/attendance/check-out`, data ?? {});
  return unwrapData(res);
}

export async function getLeaveTypes() {
  const res = await apiClient.get(`${BASE}/leave/types`);
  return unwrapData(res);
}

export async function getLeaveRequests(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/leave/requests`, { params });
  return unwrapData(res);
}

export async function approveLeaveRequest(id: number | string) {
  const res = await apiClient.post(`${BASE}/leave/requests/${id}/approve`);
  return unwrapData(res);
}

export async function rejectLeaveRequest(id: number | string) {
  const res = await apiClient.post(`${BASE}/leave/requests/${id}/reject`);
  return unwrapData(res);
}

export async function getPayrollRuns(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/payroll/runs`, { params });
  return unwrapData(res);
}

export async function getPayrollRun(id: number | string) {
  const res = await apiClient.get(`${BASE}/payroll/runs/${id}`);
  return unwrapData(res);
}

export async function calculatePayrollRun(id: number | string) {
  const res = await apiClient.post(`${BASE}/payroll/runs/${id}/calculate`);
  return unwrapData(res);
}

export async function approvePayrollRun(id: number | string) {
  const res = await apiClient.post(`${BASE}/payroll/runs/${id}/approve`);
  return unwrapData(res);
}

export async function getPayrollPayslips(id: number | string) {
  const res = await apiClient.get(`${BASE}/payroll/runs/${id}/payslips`);
  return unwrapData(res);
}
