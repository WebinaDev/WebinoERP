import apiClient from '@/lib/api-client';
import { unwrapData } from '@/lib/api-helpers';

const BASE = '/v1/hrm';

// ── Staff ──────────────────────────────────────────────────────────────────

export async function getStaff(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/staff`, { params });
  return unwrapData(res);
}

export async function saveStaff(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/staff`, data);
  return unwrapData(res);
}

export async function updateEmployee(id: number | string, data: Record<string, unknown>) {
  const res = await apiClient.patch(`${BASE}/employees/${id}`, data);
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

export async function getOrgPositions(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/org-positions`, { params });
  return unwrapData(res);
}

export async function saveOrgPosition(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/org-positions`, data);
  return unwrapData(res);
}

export async function deleteOrgPosition(id: number | string) {
  const res = await apiClient.delete(`${BASE}/org-positions/${id}`);
  return unwrapData(res);
}

// ── Attendance ─────────────────────────────────────────────────────────────

export async function attendanceCheckIn(data?: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/attendance/check-in`, data ?? {});
  return unwrapData(res);
}

export async function attendanceCheckOut(data?: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/attendance/check-out`, data ?? {});
  return unwrapData(res);
}

// ── Leave ──────────────────────────────────────────────────────────────────

export async function getLeaveTypes(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/leave/types`, { params });
  return unwrapData(res);
}

export async function saveLeaveType(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/leave/types`, data);
  return unwrapData(res);
}

export async function getLeaveRequests(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/leave/requests`, { params });
  return unwrapData(res);
}

export async function saveLeaveRequest(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/leave/requests`, data);
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

export async function getLeaveBalances(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/leave/balances`, { params });
  return unwrapData(res);
}

// ── Payroll ────────────────────────────────────────────────────────────────

export async function getPayrollSettings() {
  const res = await apiClient.get(`${BASE}/payroll/settings`);
  return unwrapData(res);
}

export async function savePayrollSettings(settings: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/payroll/settings`, { settings });
  return unwrapData(res);
}

export async function getPayrollComponents(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/payroll/components`, { params });
  return unwrapData(res);
}

export async function savePayrollComponent(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/payroll/components`, data);
  return unwrapData(res);
}

export async function getEmployeeSalaries(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/payroll/employee-salaries`, { params });
  return unwrapData(res);
}

export async function saveEmployeeSalary(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/payroll/employee-salaries`, data);
  return unwrapData(res);
}

export async function getPayrollRuns(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/payroll/runs`, { params });
  return unwrapData(res);
}

export async function createPayrollRun(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/payroll/runs`, data);
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

/* —— Employee self-service portal —— */

export type HrmMeIdentity = {
  user_id?: number;
  employee_id?: number;
  display_name?: string;
  first_name?: string;
  last_name?: string;
  email?: string | null;
  personnel_code?: string | null;
  national_id?: string | null;
  insurance_number?: string | null;
  job_title?: string | null;
  department?: string | null;
  direct_manager?: { id?: number; name?: string } | null;
  workshop?: { id?: number; code?: string; name?: string } | null;
  hire_date?: string | null;
  contract_type?: string | null;
};

export type HrmMeResponse = {
  identity: HrmMeIdentity | null;
  decree?: Record<string, unknown> | null;
  leave_balances?: Array<{
    leave_type_id: number;
    type_name: string;
    year?: number;
    allocated?: number;
    used?: number;
    balance: number;
  }>;
  latest_payslip?: {
    id: number;
    run_title?: string;
    jalali_year?: number;
    jalali_month?: number;
    gross?: number;
    net?: number;
    deposit_date?: string | null;
  } | null;
  open_requests?: number;
};

export async function getHrmMe() {
  const res = await apiClient.get(`${BASE}/me`);
  return unwrapData<HrmMeResponse>(res);
}

export async function getMyNotices() {
  const res = await apiClient.get(`${BASE}/me/notices`);
  return unwrapData<{ notices: Array<{ id: number; title: string; body?: string }> }>(res);
}

export async function getMyShift() {
  const res = await apiClient.get(`${BASE}/me/shift`);
  return unwrapData<{ shift: { id: number; name: string; start_time?: string; end_time?: string } | null }>(res);
}

export async function getMyAttendance(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/me/attendance`, { params });
  return unwrapData<{ items: Array<{ id: number; work_date: string; check_in?: string; check_out?: string; status?: string }>; total: number }>(res);
}

export async function punchMyAttendance(data?: { action?: 'in' | 'out'; notes?: string }) {
  const res = await apiClient.post(`${BASE}/me/attendance`, data ?? {});
  return unwrapData(res);
}

export async function getMyDecrees() {
  const res = await apiClient.get(`${BASE}/me/decrees`);
  return unwrapData<{ decrees: Array<{ id: number; decree_no?: string; decree_type?: string; status?: string; effective_from?: string }> }>(res);
}

export async function getMyDependents() {
  const res = await apiClient.get(`${BASE}/me/dependents`);
  return unwrapData<{ dependents: Array<{ id: number; full_name: string; relation?: string; national_id?: string }> }>(res);
}

export async function saveMyDependent(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/me/dependents`, data);
  return unwrapData(res);
}

export async function getMyOrgChart() {
  const res = await apiClient.get(`${BASE}/me/org-chart`);
  return unwrapData<{ departments: Array<{ id: number; name: string }>; positions: Array<{ id: number; name: string; parent?: number }> }>(res);
}

export async function getMyProfile() {
  const res = await apiClient.get(`${BASE}/me/profile`);
  return unwrapData<{
    profile: {
      id?: number;
      first_name?: string;
      last_name?: string;
      email?: string;
      mobile?: string;
      address?: string;
      iban?: string;
      sections?: {
        contact_info?: { fields?: Record<string, { value?: string }> };
        financial_info?: { fields?: Record<string, { value?: string }> };
      };
    } | null;
  }>(res);
}

export async function updateMyProfile(data: Record<string, unknown>) {
  const res = await apiClient.patch(`${BASE}/me/profile`, data);
  return unwrapData(res);
}

export async function getCartableInbox() {
  const res = await apiClient.get(`${BASE}/requests/inbox`);
  return unwrapData<{ requests: Array<{ id: number; type: string; status: string; user_name?: string; notes?: string; created_at?: string }> }>(res);
}

export async function getMyPayslips() {
  const res = await apiClient.get(`${BASE}/payroll/my-payslips`);
  return unwrapData<{
    payslips: Array<{
      id: number;
      run_title?: string;
      jalali_year?: number;
      jalali_month?: number;
      gross: number;
      net: number;
      deductions?: number;
      days_worked?: number | null;
      overtime?: number | null;
      employee_insurance?: number | null;
      employer_insurance?: number | null;
      tax?: number | null;
      loan_deduction?: number | null;
      advance_deduction?: number | null;
      iban?: string | null;
      deposit_date?: string | null;
    }>;
  }>(res);
}

export async function getPayrollDecrees() {
  const res = await apiClient.get(`${BASE}/payroll/decrees`);
  return unwrapData<{ decrees: Record<string, unknown>[] }>(res);
}

export async function savePayrollDecree(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/payroll/decrees`, data);
  return unwrapData(res);
}

// ── Recruitment ────────────────────────────────────────────────────────────

export async function getJobPostings(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/recruitment/postings`, { params });
  return unwrapData(res);
}

export async function saveJobPosting(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/recruitment/postings`, data);
  return unwrapData(res);
}

export async function getApplicants(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/recruitment/applicants`, { params });
  return unwrapData(res);
}

export async function saveApplicant(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/recruitment/applicants`, data);
  return unwrapData(res);
}

export async function deleteApplicant(id: number | string) {
  const res = await apiClient.delete(`${BASE}/recruitment/applicants/${id}`);
  return unwrapData(res);
}

export async function hireApplicant(id: number | string, data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/recruitment/applicants/${id}/hire`, data);
  return unwrapData(res);
}

export async function getInterviews(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/recruitment/interviews`, { params });
  return unwrapData(res);
}

export async function saveInterview(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/recruitment/interviews`, data);
  return unwrapData(res);
}

// ── Performance ────────────────────────────────────────────────────────────

export async function getKpiTemplates(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/performance/kpi-templates`, { params });
  return unwrapData(res);
}

export async function saveKpiTemplate(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/performance/kpi-templates`, data);
  return unwrapData(res);
}

export async function getPerformanceCycles(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/performance/cycles`, { params });
  return unwrapData(res);
}

export async function savePerformanceCycle(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/performance/cycles`, data);
  return unwrapData(res);
}

export async function getPerformanceReviews(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/performance/reviews`, { params });
  return unwrapData(res);
}

export async function savePerformanceReview(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/performance/reviews`, data);
  return unwrapData(res);
}

// ── Training ───────────────────────────────────────────────────────────────

export async function getTrainingCourses(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/training/courses`, { params });
  return unwrapData(res);
}

export async function saveTrainingCourse(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/training/courses`, data);
  return unwrapData(res);
}

export async function getTrainingSessions(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/training/sessions`, { params });
  return unwrapData(res);
}

export async function saveTrainingSession(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/training/sessions`, data);
  return unwrapData(res);
}

export async function getTrainingEnrollments(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/training/enrollments`, { params });
  return unwrapData(res);
}

export async function saveTrainingEnrollment(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/training/enrollments`, data);
  return unwrapData(res);
}
