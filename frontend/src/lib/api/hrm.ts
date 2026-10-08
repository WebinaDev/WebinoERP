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
  contract_end_date?: string | null;
  contract_status?: string | null;
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
  compensation?: { loan_balance?: number; advance_balance?: number };
  missing_documents?: string[];
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

export async function getLoans(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/payroll/loans`, { params });
  return unwrapData(res);
}

export async function saveLoan(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/payroll/loans`, data);
  return unwrapData(res);
}

export async function settleLoan(id: number | string) {
  const res = await apiClient.post(`${BASE}/payroll/loans/${id}/settle`);
  return unwrapData(res);
}

export async function getDocumentTemplates() {
  const res = await apiClient.get(`${BASE}/payroll/templates`);
  return unwrapData<{ data?: Array<{ id: number; slug: string; name: string; html?: string }> } | Array<{ id: number; slug: string; name: string; html?: string }>>(res);
}

export async function saveDocumentTemplate(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/payroll/templates`, data);
  return unwrapData(res);
}

export async function renderDocumentTemplate(slug: string, params: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/payroll/templates/${slug}/render`, { params });
  return unwrapData<{ html: string }>(res);
}

export async function getMyRequests() {
  const res = await apiClient.get(`${BASE}/me/requests`);
  return unwrapData<{ requests: Array<{ id: number; type: string; status: string; notes?: string; payload?: Record<string, unknown> }> }>(res);
}

export async function saveMyRequest(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/me/requests`, data);
  return unwrapData(res);
}

export async function approveHrmRequest(id: number | string) {
  const res = await apiClient.post(`${BASE}/requests/${id}/approve`);
  return unwrapData(res);
}

export async function rejectHrmRequest(id: number | string) {
  const res = await apiClient.post(`${BASE}/requests/${id}/reject`);
  return unwrapData(res);
}

export async function getMyDocuments() {
  const res = await apiClient.get(`${BASE}/me/documents`);
  return unwrapData<{ documents: Array<{ id: number; title: string; category?: string; original_name?: string }> }>(res);
}

export async function saveMyDocument(body: FormData) {
  const res = await apiClient.post(`${BASE}/me/documents`, body, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return unwrapData(res);
}

export async function getStaffDocuments(employeeId: number | string) {
  const res = await apiClient.get(`${BASE}/staff/${employeeId}/documents`);
  return unwrapData<{ documents: Array<{ id: number; title: string; category?: string; original_name?: string }> }>(res);
}

export async function saveStaffDocument(employeeId: number | string, body: FormData) {
  const res = await apiClient.post(`${BASE}/staff/${employeeId}/documents`, body, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return unwrapData(res);
}

export async function getStaffDependents(employeeId: number | string) {
  const res = await apiClient.get(`${BASE}/staff/${employeeId}/dependents`);
  return unwrapData(res);
}

export async function saveStaffDependent(employeeId: number | string, data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/staff/${employeeId}/dependents`, data);
  return unwrapData(res);
}

export async function issueEmployeePayslip(data: { employee_id: number; year: number; month: number; title?: string }) {
  const res = await apiClient.post(`${BASE}/payroll/payslips`, data);
  return unwrapData<{ data?: { html?: string; item?: Record<string, unknown>; run?: Record<string, unknown> }; html?: string; item?: Record<string, unknown> }>(res);
}


// ── Suite: shifts, onboarding, OKR, 360, LMS, succession, timesheets, analytics ──

export async function getShiftTemplates() {
  const res = await apiClient.get(`${BASE}/shifts/templates`);
  return unwrapData(res);
}
export async function saveShiftTemplate(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/shifts/templates`, data);
  return unwrapData(res);
}
export async function getShiftRotations() {
  const res = await apiClient.get(`${BASE}/shifts/rotations`);
  return unwrapData(res);
}
export async function saveShiftRotation(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/shifts/rotations`, data);
  return unwrapData(res);
}
export async function applyShiftRotation(id: number | string, data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/shifts/rotations/${id}/apply`, data);
  return unwrapData(res);
}
export async function saveShiftAssignment(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/shifts/assignments`, data);
  return unwrapData(res);
}
export async function getShiftCalendar(params: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/shifts/calendar`, { params });
  return unwrapData<Record<string, unknown>[]>(res);
}
export async function getMyShiftCalendar(params: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/me/shift-calendar`, { params });
  return unwrapData<Record<string, unknown>[]>(res);
}

export async function getOnboardingTemplates() {
  const res = await apiClient.get(`${BASE}/onboarding/templates`);
  return unwrapData(res);
}
export async function saveOnboardingTemplate(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/onboarding/templates`, data);
  return unwrapData(res);
}
export async function getOnboardings() {
  const res = await apiClient.get(`${BASE}/onboarding`);
  return unwrapData(res);
}
export async function startOnboarding(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/onboarding/start`, data);
  return unwrapData(res);
}
export async function getMyOnboarding() {
  const res = await apiClient.get(`${BASE}/me/onboarding`);
  return unwrapData<{ data?: Record<string, unknown> | null; progress?: { done: number; total: number } } | Record<string, unknown>>(res);
}
export async function completeMyOnboardingTask(id: number | string) {
  const res = await apiClient.post(`${BASE}/me/onboarding/tasks/${id}/complete`);
  return unwrapData(res);
}

export async function getObjectives(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/objectives`, { params });
  return unwrapData(res);
}
export async function saveObjective(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/objectives`, data);
  return unwrapData(res);
}
export async function updateMyKeyResult(id: number | string, current_value: number) {
  const res = await apiClient.patch(`${BASE}/me/objectives/key-results/${id}`, { current_value });
  return unwrapData(res);
}
export async function getMyObjectives() {
  const res = await apiClient.get(`${BASE}/me/objectives`);
  return unwrapData(res);
}

export async function getReviews360() {
  const res = await apiClient.get(`${BASE}/reviews-360`);
  return unwrapData(res);
}
export async function saveReview360(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/reviews-360`, data);
  return unwrapData(res);
}
export async function submitReview360(id: number | string, data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/me/reviews-360/ratings/${id}/submit`, data);
  return unwrapData(res);
}
export async function getMyReviews360() {
  const res = await apiClient.get(`${BASE}/me/reviews-360`);
  return unwrapData(res);
}

export async function getLessons(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/training/lessons`, { params });
  return unwrapData(res);
}
export async function saveLesson(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/training/lessons`, data);
  return unwrapData(res);
}
export async function completeLesson(id: number | string, data?: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/training/lessons/${id}/complete`, data ?? { progress_percent: 100 });
  return unwrapData(res);
}
export async function completeMyLesson(id: number | string) {
  const res = await apiClient.post(`${BASE}/me/learning/lessons/${id}/complete`, { progress_percent: 100 });
  return unwrapData(res);
}
export async function getMyLearning() {
  const res = await apiClient.get(`${BASE}/me/learning`);
  return unwrapData(res);
}
export async function issueCertificate(enrollmentId: number | string) {
  const res = await apiClient.post(`${BASE}/training/enrollments/${enrollmentId}/certificate`, {});
  return unwrapData(res);
}

export async function getSuccessionChart() {
  const res = await apiClient.get(`${BASE}/succession/chart`);
  return unwrapData(res);
}
export async function saveSuccessor(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/succession/plans`, data);
  return unwrapData(res);
}

export async function getTimesheets(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/timesheets`, { params });
  return unwrapData(res);
}
export async function getMyTimesheets() {
  const res = await apiClient.get(`${BASE}/me/timesheets`);
  return unwrapData(res);
}
export async function saveMyTimesheet(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/me/timesheets`, data);
  return unwrapData(res);
}
export async function submitMyTimesheet(id: number | string) {
  const res = await apiClient.post(`${BASE}/me/timesheets/${id}/submit`);
  return unwrapData(res);
}
export async function decideTimesheet(id: number | string, status: 'approved' | 'rejected') {
  const res = await apiClient.post(`${BASE}/timesheets/${id}/decide`, { status });
  return unwrapData(res);
}

export async function getHrAnalytics(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/analytics/summary`, { params });
  return unwrapData(res);
}

export async function signMyDocument(id: number | string, signer_name: string) {
  const res = await apiClient.post(`${BASE}/me/documents/${id}/sign`, { signer_name });
  return unwrapData(res);
}
export async function signStaffDocument(employeeId: number | string, id: number | string, signer_name: string) {
  const res = await apiClient.post(`${BASE}/staff/${employeeId}/documents/${id}/sign`, { signer_name });
  return unwrapData(res);
}

export async function downloadHrmFile(path: string, filename: string) {
  const res = await apiClient.get(`${BASE}/${path}`, { responseType: 'blob' });
  const blob = new Blob([res.data as BlobPart]);
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

// ── Attendance devices (ZKTeco & co.) ──────────────────────────────────────

export async function getAttendanceDevices() {
  const res = await apiClient.get(`${BASE}/attendance/devices`, { params: { per_page: 100 } });
  return unwrapData(res);
}

export async function createAttendanceDevice(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/attendance/devices`, data);
  return unwrapData<Record<string, unknown>>(res);
}

export async function updateAttendanceDevice(id: number | string, data: Record<string, unknown>) {
  const res = await apiClient.patch(`${BASE}/attendance/devices/${id}`, data);
  return unwrapData(res);
}

export async function rotateAttendanceDeviceKey(id: number | string) {
  const res = await apiClient.post(`${BASE}/attendance/devices/${id}/rotate-key`);
  return unwrapData<Record<string, unknown>>(res);
}

export async function deleteAttendanceDevice(id: number | string) {
  const res = await apiClient.delete(`${BASE}/attendance/devices/${id}`);
  return unwrapData(res);
}

export async function getDeviceUserMappings(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/attendance/device-users`, { params: { per_page: 200, ...params } });
  return unwrapData(res);
}

export async function saveDeviceUserMapping(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/attendance/device-users`, data);
  return unwrapData(res);
}

export async function deleteDeviceUserMapping(id: number | string) {
  const res = await apiClient.delete(`${BASE}/attendance/device-users/${id}`);
  return unwrapData(res);
}

export async function getAttendancePunches(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/attendance/punches`, { params: { per_page: 100, ...params } });
  return unwrapData(res);
}

export async function assignAttendancePunch(id: number | string, employee_id: number, remember = true) {
  const res = await apiClient.post(`${BASE}/attendance/punches/${id}/assign`, { employee_id, remember });
  return unwrapData(res);
}

export async function importAttendanceCsv(file: File, deviceId?: string) {
  const form = new FormData();
  form.append('file', file);
  if (deviceId) form.append('device_id', deviceId);
  const res = await apiClient.post(`${BASE}/attendance/import-csv`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return unwrapData<Record<string, unknown>>(res);
}

// ── SSO (تأمین اجتماعی) list ───────────────────────────────────────────────

export async function getInsuranceListForPeriod(year: string, month: string) {
  const res = await apiClient.get(`${BASE}/payroll/insurance-list`, { params: { year, month } });
  return unwrapData<Record<string, unknown>>(res);
}

// ── Offboarding ────────────────────────────────────────────────────────────

export async function getOffboardingTemplates() {
  const res = await apiClient.get(`${BASE}/offboarding/templates`, { params: { per_page: 100 } });
  return unwrapData(res);
}

export async function saveOffboardingTemplate(data: Record<string, unknown>, id?: number | string) {
  const res = id
    ? await apiClient.put(`${BASE}/offboarding/templates/${id}`, data)
    : await apiClient.post(`${BASE}/offboarding/templates`, data);
  return unwrapData(res);
}

export async function deleteOffboardingTemplate(id: number | string) {
  const res = await apiClient.delete(`${BASE}/offboarding/templates/${id}`);
  return unwrapData(res);
}

export async function getOffboardings(params?: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/offboarding`, { params: { per_page: 100, ...params } });
  return unwrapData(res);
}

export async function startOffboarding(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/offboarding/start`, data);
  return unwrapData(res);
}

export async function updateOffboarding(id: number | string, data: Record<string, unknown>) {
  const res = await apiClient.patch(`${BASE}/offboarding/${id}`, data);
  return unwrapData(res);
}

export async function setOffboardingTaskStatus(id: number | string, status: string, extra?: Record<string, unknown>) {
  const res = await apiClient.patch(`${BASE}/offboarding/tasks/${id}`, { status, ...extra });
  return unwrapData(res);
}

export async function getMyOffboarding() {
  const res = await apiClient.get(`${BASE}/me/offboarding`);
  return unwrapData(res);
}

export async function completeMyOffboardingTask(id: number | string, data?: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/me/offboarding/tasks/${id}/complete`, data ?? {});
  return unwrapData(res);
}

// ── Approval flows ─────────────────────────────────────────────────────────

export async function getApprovalFlows() {
  const res = await apiClient.get(`${BASE}/approval-flows`);
  return unwrapData<Record<string, unknown>>(res);
}

export async function saveApprovalFlow(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/approval-flows`, data);
  return unwrapData(res);
}

export async function deleteApprovalFlow(id: number | string) {
  const res = await apiClient.delete(`${BASE}/approval-flows/${id}`);
  return unwrapData(res);
}

export async function searchApprovalUsers(search: string) {
  const res = await apiClient.get(`${BASE}/approval-flows/users`, { params: { search } });
  return unwrapData(res);
}

export async function approveLeaveFromCartable(id: number | string) {
  const res = await apiClient.post(`${BASE}/requests/leaves/${id}/approve`);
  return unwrapData(res);
}

export async function rejectLeaveFromCartable(id: number | string, reason?: string) {
  const res = await apiClient.post(`${BASE}/requests/leaves/${id}/reject`, { reason });
  return unwrapData(res);
}

export async function getApprovalInbox() {
  const res = await apiClient.get(`${BASE}/requests/inbox`);
  return unwrapData<{ requests?: Record<string, unknown>[]; leaves?: Record<string, unknown>[] }>(res);
}

// ── HR notification settings ───────────────────────────────────────────────

export async function getHrNotificationSettings() {
  const res = await apiClient.get(`${BASE}/settings/notifications`);
  return unwrapData<Record<string, unknown>>(res);
}

export async function saveHrNotificationSettings(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/settings/notifications`, data);
  return unwrapData<Record<string, unknown>>(res);
}

export async function testHrNotification(employee_id: number) {
  const res = await apiClient.post(`${BASE}/settings/notifications/test`, { employee_id });
  return unwrapData<{ results?: Record<string, string> }>(res);
}

export async function getMyNotificationChannels() {
  const res = await apiClient.get(`${BASE}/me/notification-channels`);
  return unwrapData<Record<string, unknown>>(res);
}

export async function saveMyNotificationChannels(data: Record<string, unknown>) {
  const res = await apiClient.put(`${BASE}/me/notification-channels`, data);
  return unwrapData<Record<string, unknown>>(res);
}

// ── Staff spreadsheet import ───────────────────────────────────────────────

export async function importStaffSpreadsheet(file: File, dryRun: boolean) {
  const form = new FormData();
  form.append('file', file);
  form.append('dry_run', dryRun ? '1' : '0');
  const res = await apiClient.post(`${BASE}/staff/import`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return unwrapData<Record<string, unknown>>(res);
}

// ── Calendar feeds ─────────────────────────────────────────────────────────

export async function getMyCalendarFeed() {
  const res = await apiClient.get(`${BASE}/me/calendar/feed`);
  return unwrapData<Record<string, string>>(res);
}

export async function regenerateMyCalendarFeed() {
  const res = await apiClient.post(`${BASE}/me/calendar/feed/regenerate`);
  return unwrapData<Record<string, string>>(res);
}

// ── Workforce budget ───────────────────────────────────────────────────────

export async function getWorkforceBudgets(params: Record<string, unknown>) {
  const res = await apiClient.get(`${BASE}/analytics/budgets`, { params });
  return unwrapData<Record<string, unknown>>(res);
}

export async function saveWorkforceBudget(data: Record<string, unknown>) {
  const res = await apiClient.post(`${BASE}/analytics/budgets`, data);
  return unwrapData(res);
}

export async function deleteWorkforceBudget(id: number | string) {
  const res = await apiClient.delete(`${BASE}/analytics/budgets/${id}`);
  return unwrapData(res);
}

export async function updateMyOffboarding(id: number | string, data: Record<string, unknown>) {
  const res = await apiClient.patch(`${BASE}/me/offboarding/${id}`, data);
  return unwrapData(res);
}
