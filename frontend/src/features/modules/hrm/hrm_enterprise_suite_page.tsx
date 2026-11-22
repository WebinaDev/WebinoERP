'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useTranslations } from 'next-intl';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import apiClient from '@/lib/api-client';
import { getStaff, getJobPostings, getJobApplicants, getPayrollDecrees, getPayrollRuns } from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';
import { getAxiosMessage } from '@/lib/api-helpers';
import {
  Calculator,
  History,
  BrainCircuit,
  TrendingDown,
  Sparkles,
  RefreshCw,
  ShieldCheck,
  CheckCircle2,
  Clock,
  Layers,
  Inbox,
  AlertCircle,
  Briefcase,
  Users,
  Compass,
  FileText,
  Award,
  Download,
  Code2,
  Cpu,
  UserCheck,
  Receipt,
  FileSpreadsheet,
  PlaneTakeoff,
  ScrollText,
} from 'lucide-react';

interface RetroAdjustment {
  id: number;
  employee_id: number;
  employee?: {
    id: number;
    first_name: string;
    last_name: string;
    employee_code?: string;
  };
  decree?: {
    id: number;
    decree_no: string;
    decree_type?: string;
  };
  target_year: number;
  target_month: number;
  original_gross: number;
  adjusted_gross: number;
  differential_amount: number;
  differential_insurance: number;
  differential_tax: number;
  differential_net: number;
  status: 'pending' | 'applied' | 'paid';
  reason: string;
}

interface FormulaComponent {
  id: number;
  code: string;
  title: string;
  component_type: string;
  calculation_type: string;
  formula_expression: string;
  is_taxable: boolean;
  is_insurable: boolean;
}

interface PromotionProposal {
  id: number;
  employee_id: number;
  employee?: {
    id: number;
    first_name: string;
    last_name: string;
  };
  target_career_path?: {
    id: number;
    title_fa: string;
    level: number;
  };
  eligibility_score: number;
  system_justification: string;
  status: string;
}

interface PerformanceAgreement {
  id: number;
  employee_id: number;
  employee?: {
    first_name: string;
    last_name: string;
  };
  agreement_code: string;
  start_date: string;
  end_date: string;
  status: string;
}

interface MissionOrder {
  id: number;
  mission_no: string;
  destination: string;
  mission_type: string;
  starts_at: string;
  ends_at: string;
  total_allowance: number;
  status: string;
  employee?: {
    first_name: string;
    last_name: string;
  };
}

export function HrmEnterpriseSuitePage() {
  const t = useTranslations('hrm');
  const [activeTab, setActiveTab] = useState<'retro' | 'accounting_vouchers' | 'formula' | 'career' | 'agreements' | 'missions' | 'sso_diskette'>('retro');

  // Master lookup data
  const [employees, setEmployees] = useState<Record<string, unknown>[]>([]);
  const [decrees, setDecrees] = useState<Record<string, unknown>[]>([]);
  const [payrollRuns, setPayrollRuns] = useState<Record<string, unknown>[]>([]);

  // Retroactive state
  const [retroList, setRetroList] = useState<RetroAdjustment[]>([]);
  const [retroLoading, setRetroLoading] = useState(false);
  const [selectedDecreeId, setSelectedDecreeId] = useState<string>('');
  const [calculatingDecree, setCalculatingDecree] = useState(false);

  // Accounting Vouchers state
  const [voucherRunId, setVoucherRunId] = useState('');
  const [issuingVoucher, setIssuingVoucher] = useState(false);
  const [voucherResult, setVoucherResult] = useState<any>(null);

  // Formula Components state
  const [formulaComponents, setFormulaComponents] = useState<FormulaComponent[]>([]);
  const [formulaLoading, setFormulaLoading] = useState(false);
  const [newCode, setNewCode] = useState('');
  const [newTitle, setNewTitle] = useState('');
  const [newFormula, setNewFormula] = useState('');
  const [savingFormula, setSavingFormula] = useState(false);

  // Career progression state
  const [proposals, setProposals] = useState<PromotionProposal[]>([]);
  const [proposalsLoading, setProposalsLoading] = useState(false);
  const [careerEmpId, setCareerEmpId] = useState('');
  const [evaluatingCareer, setEvaluatingCareer] = useState(false);

  // Agreements state
  const [agreements, setAgreements] = useState<PerformanceAgreement[]>([]);
  const [agreementsLoading, setAgreementsLoading] = useState(false);
  const [agrEmpId, setAgrEmpId] = useState('');
  const [agrStart, setAgrStart] = useState('1405-01-01');
  const [agrEnd, setAgrEnd] = useState('1405-12-29');
  const [submittingAgr, setSubmittingAgr] = useState(false);

  // Missions state
  const [missions, setMissions] = useState<MissionOrder[]>([]);
  const [missionsLoading, setMissionsLoading] = useState(false);
  const [misEmpId, setMisEmpId] = useState('');
  const [misDestination, setMisDestination] = useState('');
  const [misAllowance, setMisAllowance] = useState('15000000');
  const [submittingMis, setSubmittingMis] = useState(false);

  // SSO / Tax Diskette state
  const [selectedRunId, setSelectedRunId] = useState('');
  const [generatingDiskette, setGeneratingDiskette] = useState(false);
  const [disketteResult, setDisketteResult] = useState<any>(null);
  const [taxResult, setTaxResult] = useState<any>(null);

  // Load master entities
  const loadMasterData = useCallback(async () => {
    try {
      const [staffRes, decreesRes, runsRes] = await Promise.allSettled([
        getStaff({ per_page: 100 }),
        getPayrollDecrees(),
        getPayrollRuns(),
      ]);

      if (staffRes.status === 'fulfilled') setEmployees(normalizeListPayload(staffRes.value));
      if (decreesRes.status === 'fulfilled') {
        const d = (decreesRes.value as { decrees?: Record<string, unknown>[] })?.decrees || [];
        setDecrees(Array.isArray(d) ? d : []);
      }
      if (runsRes.status === 'fulfilled') setPayrollRuns(normalizeListPayload(runsRes.value));
    } catch {
      // Non-blocking
    }
  }, []);

  const fetchRetro = useCallback(async () => {
    setRetroLoading(true);
    try {
      const res = await apiClient.get('/api/v1/hrm/enterprise/payroll/retro-adjustments');
      const items = res.data?.data?.data || res.data?.data || [];
      setRetroList(Array.isArray(items) ? items : []);
    } catch (err) {
      setRetroList([]);
      toast.error(getAxiosMessage(err, 'خطا در دریافت لیست مابه‌التفاوت‌های حقوق'));
    } finally {
      setRetroLoading(false);
    }
  }, []);

  const fetchFormulas = useCallback(async () => {
    setFormulaLoading(true);
    try {
      const res = await apiClient.get('/api/v1/hrm/enterprise/decrees/formula-components');
      setFormulaComponents(res.data?.data || []);
    } catch (err) {
      setFormulaComponents([]);
      toast.error(getAxiosMessage(err, 'خطا در دریافت عوامل فرمول حکمی'));
    } finally {
      setFormulaLoading(false);
    }
  }, []);

  const fetchProposals = useCallback(async () => {
    setProposalsLoading(true);
    try {
      const res = await apiClient.get('/api/v1/hrm/enterprise/career/promotion-proposals');
      setProposals(res.data?.data || []);
    } catch (err) {
      setProposals([]);
      toast.error(getAxiosMessage(err, 'خطا در دریافت پیشنهادات ارتقا شغلی'));
    } finally {
      setProposalsLoading(false);
    }
  }, []);

  const fetchAgreements = useCallback(async () => {
    setAgreementsLoading(true);
    try {
      const res = await apiClient.get('/api/v1/hrm/enterprise/performance/agreements');
      setAgreements(res.data?.data || []);
    } catch (err) {
      setAgreements([]);
      toast.error(getAxiosMessage(err, 'خطا در دریافت موافقت‌نامه‌های عملکرد'));
    } finally {
      setAgreementsLoading(false);
    }
  }, []);

  const fetchMissions = useCallback(async () => {
    setMissionsLoading(true);
    try {
      const res = await apiClient.get('/api/v1/hrm/enterprise/missions');
      setMissions(res.data?.data || []);
    } catch (err) {
      setMissions([]);
      toast.error(getAxiosMessage(err, 'خطا در دریافت احکام مأموریت'));
    } finally {
      setMissionsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadMasterData();
  }, [loadMasterData]);

  useEffect(() => {
    if (activeTab === 'retro') void fetchRetro();
    if (activeTab === 'formula') void fetchFormulas();
    if (activeTab === 'career') void fetchProposals();
    if (activeTab === 'agreements') void fetchAgreements();
    if (activeTab === 'missions') void fetchMissions();
  }, [activeTab, fetchRetro, fetchFormulas, fetchProposals, fetchAgreements, fetchMissions]);

  const handleCalculateRetro = async () => {
    if (!selectedDecreeId) {
      toast.error('لطفاً یک حکم کارگزینی را انتخاب فرمایید.');
      return;
    }
    setCalculatingDecree(true);
    try {
      const res = await apiClient.post(`/api/v1/hrm/enterprise/payroll/decrees/${selectedDecreeId}/calculate-retro`);
      const count = res.data?.count ?? 0;
      if (count > 0) {
        toast.success(`محاسبه معوقات انجام شد (${count} رکورد مابه‌التفاوت ایجاد گردید).`);
      } else {
        toast.info('برای این حکم، تفاوت عطف‌به‌ماسبق نیازمند پرداخت در دوره‌های گذشته یافت نشد.');
      }
      void fetchRetro();
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در اجرای فرآیند محاسبه معوقات'));
    } finally {
      setCalculatingDecree(false);
    }
  };

  const handleApplyRetro = async (id: number) => {
    try {
      await apiClient.post(`/api/v1/hrm/enterprise/payroll/retro-adjustments/${id}/apply`);
      toast.success('مابه‌التفاوت جهت کسر و واریز در فیش دوره بعد منظور گردید.');
      void fetchRetro();
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در تایید و اعمال مابه‌التفاوت'));
    }
  };

  const handleIssueVouchers = async () => {
    if (!voucherRunId) {
      toast.error('لطفاً دوره حقوق را انتخاب فرمایید.');
      return;
    }
    setIssuingVoucher(true);
    try {
      const res = await apiClient.post(`/api/v1/hrm/enterprise/payroll/runs/${voucherRunId}/issue-vouchers`);
      toast.success(res.data?.message || 'سند حسابداری حقوق و معوقات صادر گردید.');
      setVoucherResult(res.data?.data);
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در صدور سند حسابداری حقوق'));
    } finally {
      setIssuingVoucher(false);
    }
  };

  const handleSaveFormula = async () => {
    if (!newCode || !newTitle || !newFormula) {
      toast.error('لطفاً تمامی فیلدهای فرمول را تکمیل نمایید.');
      return;
    }
    setSavingFormula(true);
    try {
      await apiClient.post('/api/v1/hrm/enterprise/decrees/formula-components', {
        code: newCode,
        title: newTitle,
        component_type: 'earning',
        calculation_type: 'formula',
        formula_expression: newFormula,
        is_taxable: true,
        is_insurable: true,
      });
      toast.success('عامل حکمی جدید با فرمول محاسباتی ثبت گردید.');
      setNewCode('');
      setNewTitle('');
      setNewFormula('');
      void fetchFormulas();
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در ثبت فرمول عامل حکمی'));
    } finally {
      setSavingFormula(false);
    }
  };

  const handleEvaluateCareer = async () => {
    if (!careerEmpId) {
      toast.error('لطفاً کارمند مورد نظر را انتخاب فرمایید.');
      return;
    }
    setEvaluatingCareer(true);
    try {
      const res = await apiClient.post(`/api/v1/hrm/enterprise/career/proposals/${careerEmpId}/evaluate`);
      toast.success(res.data?.message || 'ارزیابی مسیر شغلی انجام گردید.');
      void fetchProposals();
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در ارزیابی ترفیع شغلی'));
    } finally {
      setEvaluatingCareer(false);
    }
  };

  const handleApproveProposal = async (proposalId: number) => {
    try {
      const res = await apiClient.post(`/api/v1/hrm/enterprise/career/proposals/${proposalId}/approve-and-issue`);
      toast.success(res.data?.message || 'حکم ارتقای کارگزینی با موفقیت صادر شد.');
      void fetchProposals();
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در صدور حکم کارگزینی ترفیع'));
    }
  };

  const handleCreateAgreement = async () => {
    if (!agrEmpId) {
      toast.error('لطفاً کارمند را انتخاب کنید.');
      return;
    }
    setSubmittingAgr(true);
    try {
      await apiClient.post('/api/v1/hrm/enterprise/performance/agreements', {
        employee_id: agrEmpId,
        start_date: agrStart,
        end_date: agrEnd,
        kpi_targets: [{ kpi: 'دستیابی به اهداف فصلی', weight: 40 }],
      });
      toast.success('موافقت‌نامه رسمی عملکرد با موفقیت صادر و ابلاغ شد.');
      void fetchAgreements();
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در صدور موافقت‌نامه'));
    } finally {
      setSubmittingAgr(false);
    }
  };

  const handleCreateMission = async () => {
    if (!misEmpId || !misDestination) {
      toast.error('لطفاً کارمند و مقصد مأموریت را مشخص نمایید.');
      return;
    }
    setSubmittingMis(true);
    try {
      await apiClient.post('/api/v1/hrm/enterprise/missions', {
        employee_id: misEmpId,
        destination: misDestination,
        mission_type: 'daily',
        starts_at: '2026-11-23 08:00:00',
        ends_at: '2026-11-25 18:00:00',
        per_diem_rate: Number(misAllowance),
        transport_expense: 5000000,
      });
      toast.success('حکم مأموریت اداری با موفقیت صادر شد.');
      setMisDestination('');
      void fetchMissions();
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در ثبت مأموریت'));
    } finally {
      setSubmittingMis(false);
    }
  };

  const handleGenerateDiskettes = async () => {
    if (!selectedRunId) {
      toast.error('لطفاً یک دوره حقوق و دستمزد را انتخاب فرمایید.');
      return;
    }
    setGeneratingDiskette(true);
    try {
      const [ssoRes, taxRes] = await Promise.all([
        apiClient.post(`/api/v1/hrm/enterprise/payroll/runs/${selectedRunId}/generate-sso-diskette`),
        apiClient.post(`/api/v1/hrm/enterprise/payroll/runs/${selectedRunId}/generate-tax-diskette`),
      ]);
      toast.success('دیسکت‌های استاندارد بیمه تأمین اجتماعی و مالیات حقوق با موفقیت تولید شدند.');
      setDisketteResult(ssoRes.data?.data);
      setTaxResult(taxRes.data?.data);
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در تولید فایل‌های دیسکت قانونی'));
    } finally {
      setGeneratingDiskette(false);
    }
  };

  const formatNumber = (val: number | string) => {
    const num = Number(val) || 0;
    return new Intl.NumberFormat('fa-IR').format(num);
  };

  return (
    <div className="space-y-6 p-4 md:p-6" dir="rtl">
      {/* Header Banner */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-l from-indigo-950 via-slate-900 to-indigo-900 p-6 rounded-2xl text-white shadow-xl">
        <div className="space-y-1">
          <div className="flex items-center gap-2">
            <Badge className="bg-amber-500/20 text-amber-300 border-amber-400/30">
              Enterprise HRM Suite · همتراز راهکاران سیستم
            </Badge>
            <span className="text-xs text-indigo-200">یکپارچگی از کارگزینی و تردد تا سند حسابداری دفتر کل</span>
          </div>
          <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight">
            سامانه جامع مدیریت منابع انسانی و انطباق قانونی ایران
          </h1>
          <p className="text-sm text-indigo-100 max-w-3xl">
            موتور فرمول‌نویسی احکام، اسناد تفکیک‌شده حسابداری (جاری و سنواتی)، دیسکت‌های قانونی بیمه/مالیات، موافقت‌نامه رسمی عملکرد و احکام مأموریت
          </p>
        </div>
        <div className="flex items-center gap-3">
          <Button
            variant="outline"
            className="border-indigo-400/40 text-white hover:bg-indigo-800/50"
            onClick={() => {
              if (activeTab === 'retro') void fetchRetro();
              if (activeTab === 'formula') void fetchFormulas();
              if (activeTab === 'career') void fetchProposals();
              if (activeTab === 'agreements') void fetchAgreements();
              if (activeTab === 'missions') void fetchMissions();
            }}
          >
            <RefreshCw className="w-4 h-4 ml-2" />
            بروزرسانی داده‌ها
          </Button>
        </div>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="space-y-6">
        <TabsList className="grid grid-cols-2 md:grid-cols-7 h-auto p-1 bg-slate-100 dark:bg-slate-800 rounded-xl">
          <TabsTrigger value="retro" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <History className="w-4 h-4 text-indigo-600" />
            <span>احکام معوق (Retro)</span>
          </TabsTrigger>
          <TabsTrigger value="accounting_vouchers" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <Receipt className="w-4 h-4 text-emerald-600" />
            <span>اسناد حسابداری حقوق</span>
          </TabsTrigger>
          <TabsTrigger value="formula" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <Code2 className="w-4 h-4 text-purple-600" />
            <span>عوامل حکمی</span>
          </TabsTrigger>
          <TabsTrigger value="career" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <Compass className="w-4 h-4 text-blue-600" />
            <span>مسیر شغلی و صدور حکم</span>
          </TabsTrigger>
          <TabsTrigger value="agreements" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <ScrollText className="w-4 h-4 text-teal-600" />
            <span>موافقت‌نامه عملکرد</span>
          </TabsTrigger>
          <TabsTrigger value="missions" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <PlaneTakeoff className="w-4 h-4 text-cyan-600" />
            <span>احکام مأموریت</span>
          </TabsTrigger>
          <TabsTrigger value="sso_diskette" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <Download className="w-4 h-4 text-amber-600" />
            <span>دیسکت بیمه و مالیات</span>
          </TabsTrigger>
        </TabsList>

        {/* ── 1. Retroactive Pay Tab ── */}
        <TabsContent value="retro" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Calculator className="w-5 h-5 text-indigo-600" />
                  <CardTitle className="text-base">محاسبه مابه‌التفاوت حکم کارگزینی</CardTitle>
                </div>
                <CardDescription>
                  در صورت صدور حکم جدید با تاریخ اجرای گذشته (Effective Dating)، مابه‌التفاوت دوره‌های قبل محاسبه می‌گردد.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>انتخاب حکم کارگزینی هدف</Label>
                  <Select value={selectedDecreeId} onValueChange={setSelectedDecreeId}>
                    <SelectTrigger><SelectValue placeholder="حکم کارگزینی را انتخاب کنید..." /></SelectTrigger>
                    <SelectContent>
                      {decrees.map((dec) => (
                        <SelectItem key={String(dec.id)} value={String(dec.id)}>
                          {String(dec.decree_no || `حکم #${dec.id}`)} - {String(dec.decree_type || 'اداری')}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <Button className="w-full bg-indigo-600 hover:bg-indigo-700 text-white" onClick={handleCalculateRetro} disabled={calculatingDecree || !selectedDecreeId}>
                  <Sparkles className="w-4 h-4 ml-2" />
                  {calculatingDecree ? 'در حال شبیه‌سازی و پردازش دوره‌ها...' : 'محاسبه مابه‌التفاوت معوقه'}
                </Button>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2 shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader className="flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base">لیست مابه‌التفاوت‌های عطف‌به‌ماسبق</CardTitle>
                  <CardDescription>اقلام تسویه‌نشده ناشی از بازنگری یا ارتقای احکام شغلی</CardDescription>
                </div>
                <Badge variant="outline">{retroList.length} رکورد معوقه</Badge>
              </CardHeader>
              <CardContent>
                {retroLoading ? (
                  <div className="text-center py-12 text-sm text-slate-500">در حال دریافت داده‌های سیستم حقوق...</div>
                ) : retroList.length === 0 ? (
                  <div className="text-center py-12 space-y-3">
                    <Inbox className="w-10 h-10 mx-auto text-slate-300" />
                    <p className="text-sm text-slate-500">هیچ تعدیل عطف‌به‌ماسبقی در سامانه ثبت نشده است.</p>
                  </div>
                ) : (
                  <div className="overflow-x-auto">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>کارمند / شماره حکم</TableHead>
                          <TableHead>دوره هدف</TableHead>
                          <TableHead>ناخالص قبلی / جدید</TableHead>
                          <TableHead>مابه‌التفاوت خالص</TableHead>
                          <TableHead>وضعیت</TableHead>
                          <TableHead>عملیات</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {retroList.map((item) => (
                          <TableRow key={item.id}>
                            <TableCell>
                              <div className="font-semibold text-sm">
                                {item.employee ? `${item.employee.first_name} ${item.employee.last_name}` : `کارمند #${item.employee_id}`}
                              </div>
                              <div className="text-xs text-slate-500">{item.decree?.decree_no || 'حکم اداری'}</div>
                            </TableCell>
                            <TableCell>
                              <Badge variant="secondary" className="font-mono text-xs">
                                {item.target_month} / {item.target_year}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              <div className="text-xs font-mono text-slate-500 line-through">{formatNumber(item.original_gross)} ریال</div>
                              <div className="text-xs font-mono font-medium text-emerald-600">{formatNumber(item.adjusted_gross)} ریال</div>
                            </TableCell>
                            <TableCell>
                              <span className="font-bold text-sm text-indigo-600 font-mono">+{formatNumber(item.differential_net)} ریال</span>
                            </TableCell>
                            <TableCell>
                              <Badge className={item.status === 'applied' ? 'bg-emerald-500/10 text-emerald-700' : 'bg-amber-500/10 text-amber-700'}>
                                {item.status === 'applied' ? 'منظور در فیش آتی' : 'در انتظار تایید'}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              {item.status !== 'applied' && (
                                <Button size="sm" variant="outline" className="text-xs h-8" onClick={() => handleApplyRetro(item.id)}>
                                  اعمال در فیش
                                </Button>
                              )}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* ── 2. Segregated Accounting Vouchers Tab ── */}
        <TabsContent value="accounting_vouchers" className="space-y-6">
          <Card className="shadow-sm border-slate-200 dark:border-slate-800">
            <CardHeader>
              <div className="flex items-center gap-2">
                <Receipt className="w-5 h-5 text-emerald-600" />
                <CardTitle className="text-base">صدور سند حسابداری تفکیک‌شده حقوق و معوقات در دفتر کل</CardTitle>
              </div>
              <CardDescription>
                تولید آرتیکل‌های تفکیک‌شده: هزینه حقوق جاری، معوقات سال جاری، تعدیلات سنواتی، بیمه پرداختنی (۳۰٪) و مالیات تکلیفی
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>انتخاب دوره حقوق و دستمزد</Label>
                  <Select value={voucherRunId} onValueChange={setVoucherRunId}>
                    <SelectTrigger><SelectValue placeholder="دوره را انتخاب کنید..." /></SelectTrigger>
                    <SelectContent>
                      {payrollRuns.map((r) => (
                        <SelectItem key={String(r.id)} value={String(r.id)}>
                          دوره {String(r.month)} / {String(r.year)} ({String(r.title || `دوره #${r.id}`)})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="flex items-end">
                  <Button className="w-full bg-emerald-600 hover:bg-emerald-700 text-white" onClick={handleIssueVouchers} disabled={issuingVoucher || !voucherRunId}>
                    {issuingVoucher ? 'در حال صدور سند حسابداری...' : 'صدور سند در ماژول حسابداری'}
                  </Button>
                </div>
              </div>

              {voucherResult && (
                <div className="mt-4 p-4 rounded-xl border border-emerald-200 bg-emerald-50/50 dark:bg-emerald-950/20 space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-sm text-emerald-900 dark:text-emerald-200">سند حسابداری با موفقیت صادر و ثبت دفتر کل گردید</span>
                    <Badge className="bg-emerald-600 text-white">شماره سند: #{voucherResult.journal_entry_id || 'خودکار'}</Badge>
                  </div>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs pt-2">
                    <div className="p-2 bg-white dark:bg-slate-900 rounded-lg">
                      <span className="text-slate-500">حقوق ناخالص جاری:</span>
                      <div className="font-bold font-mono mt-1">{formatNumber(voucherResult.current_gross)} ریال</div>
                    </div>
                    <div className="p-2 bg-white dark:bg-slate-900 rounded-lg">
                      <span className="text-slate-500">معوقات احکام سال جاری:</span>
                      <div className="font-bold font-mono mt-1 text-indigo-600">{formatNumber(voucherResult.retro_current_year)} ریال</div>
                    </div>
                    <div className="p-2 bg-white dark:bg-slate-900 rounded-lg">
                      <span className="text-slate-500">معوقات سنواتی سنوات قبل:</span>
                      <div className="font-bold font-mono mt-1 text-purple-600">{formatNumber(voucherResult.retro_prior_years)} ریال</div>
                    </div>
                    <div className="p-2 bg-white dark:bg-slate-900 rounded-lg">
                      <span className="text-slate-500">حقوق پرداختنی پرسنل:</span>
                      <div className="font-bold font-mono mt-1 text-emerald-600">{formatNumber(voucherResult.net_payable)} ریال</div>
                    </div>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* ── 3. Formula Components Tab ── */}
        <TabsContent value="formula" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Code2 className="w-5 h-5 text-purple-600" />
                  <CardTitle className="text-base">تعریف عامل حکمی فرمول‌پذیر</CardTitle>
                </div>
                <CardDescription>تعریف مزایا و کسورات با متغیرهای BASE, DAILY, EXP_YEARS, CHILDREN_COUNT, RANK, MIN_WAGE</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>کد عامل (Code)</Label>
                  <Input placeholder="مثال: JOB_DIFFICULTY" value={newCode} onChange={(e) => setNewCode(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label>عنوان عامل (Title)</Label>
                  <Input placeholder="مثال: حق سختی کار" value={newTitle} onChange={(e) => setNewTitle(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label>فرمول محاسباتی (Formula Expression)</Label>
                  <Input placeholder="مثال: BASE * 0.15 + (EXP_YEARS * 2000000)" value={newFormula} onChange={(e) => setNewFormula(e.target.value)} />
                </div>
                <Button className="w-full bg-purple-600 hover:bg-purple-700 text-white" onClick={handleSaveFormula} disabled={savingFormula}>
                  {savingFormula ? 'در حال ثبت عامل...' : 'ذخیره عامل در موتور فرمول'}
                </Button>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2 shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <CardTitle className="text-base">عوامل حکمی فعال در موتور محاسبات</CardTitle>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>کد عامل</TableHead>
                      <TableHead>عنوان</TableHead>
                      <TableHead>فرمول</TableHead>
                      <TableHead>بیمه / مالیات</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {formulaComponents.map((c) => (
                      <TableRow key={c.id}>
                        <TableCell className="font-mono font-bold text-xs">{c.code}</TableCell>
                        <TableCell>{c.title}</TableCell>
                        <TableCell className="font-mono text-xs text-purple-600" dir="ltr">{c.formula_expression}</TableCell>
                        <TableCell>
                          <Badge variant="secondary" className="text-[10px]">
                            {c.is_insurable ? 'بیمه‌پذیر' : 'معاف'} / {c.is_taxable ? 'مشمول مالیات' : 'معاف'}
                          </Badge>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* ── 4. Career Progression Tab ── */}
        <TabsContent value="career" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Compass className="w-5 h-5 text-blue-600" />
                  <CardTitle className="text-base">ارزیابی و پیشنهاد خودکار ترفیع</CardTitle>
                </div>
                <CardDescription>پایش سوابق خدمت، ارزیابی عملکرد و پیشنهاد ارتقا به همراه صدور مستقیم حکم</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>انتخاب کارمند هدف</Label>
                  <Select value={careerEmpId} onValueChange={setCareerEmpId}>
                    <SelectTrigger><SelectValue placeholder="کارمند را انتخاب کنید..." /></SelectTrigger>
                    <SelectContent>
                      {employees.map((emp) => (
                        <SelectItem key={String(emp.id)} value={String(emp.id)}>
                          {String(`${emp.first_name || ''} ${emp.last_name || ''}`.trim() || `کارمند #${emp.id}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <Button className="w-full bg-blue-600 hover:bg-blue-700 text-white" onClick={handleEvaluateCareer} disabled={evaluatingCareer || !careerEmpId}>
                  {evaluatingCareer ? 'در حال پایش شایستگی‌ها...' : 'سنجش شایستگی ارتقا'}
                </Button>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2 shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <CardTitle className="text-base">پیشنهادات فعال ارتقا و صدور خودکار حکم کارگزینی</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {proposals.map((p) => (
                    <div key={p.id} className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col md:flex-row md:items-center justify-between gap-3">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-sm">{p.employee ? `${p.employee.first_name} ${p.employee.last_name}` : `کارمند #${p.employee_id}`}</span>
                          <Badge className="bg-blue-600 text-white">{p.target_career_path?.title_fa || 'رتبه بالاتر'}</Badge>
                        </div>
                        <p className="text-xs text-muted-foreground">{p.system_justification}</p>
                      </div>
                      <div className="flex items-center gap-3">
                        <div className="text-left">
                          <span className="text-xl font-bold font-mono text-blue-600">{p.eligibility_score}٪</span>
                          <div className="text-[10px] text-muted-foreground">امتیاز صلاحیت</div>
                        </div>
                        {p.status !== 'decree_issued' ? (
                          <Button size="sm" className="bg-blue-600 hover:bg-blue-700 text-white text-xs h-8" onClick={() => handleApproveProposal(p.id)}>
                            تایید و صدور حکم کارگزینی
                          </Button>
                        ) : (
                          <Badge className="bg-emerald-600 text-white text-xs">حکم صادر گردید</Badge>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* ── 5. Performance Agreements Tab ── */}
        <TabsContent value="agreements" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <ScrollText className="w-5 h-5 text-teal-600" />
                  <CardTitle className="text-base">تنظیم موافقت‌نامه رسمی عملکرد</CardTitle>
                </div>
                <CardDescription>انعقاد توافق‌نامه اهداف و شاخص‌های کلیدی عملکرد (KPIs) بین مدیر و کارمند</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>انتخاب کارمند</Label>
                  <Select value={agrEmpId} onValueChange={setAgrEmpId}>
                    <SelectTrigger><SelectValue placeholder="کارمند را انتخاب کنید..." /></SelectTrigger>
                    <SelectContent>
                      {employees.map((emp) => (
                        <SelectItem key={String(emp.id)} value={String(emp.id)}>
                          {String(`${emp.first_name || ''} ${emp.last_name || ''}`.trim() || `کارمند #${emp.id}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div className="space-y-1">
                    <Label className="text-xs">تاریخ آغاز</Label>
                    <Input value={agrStart} onChange={(e) => setAgrStart(e.target.value)} />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-xs">تاریخ پایان</Label>
                    <Input value={agrEnd} onChange={(e) => setAgrEnd(e.target.value)} />
                  </div>
                </div>
                <Button className="w-full bg-teal-600 hover:bg-teal-700 text-white" onClick={handleCreateAgreement} disabled={submittingAgr || !agrEmpId}>
                  {submittingAgr ? 'در حال ثبت...' : 'ابلاغ موافقت‌نامه رسمی عملکرد'}
                </Button>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2 shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <CardTitle className="text-base">موافقت‌نامه‌های فعال عملکرد در سازمان</CardTitle>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>شماره موافقت‌نامه</TableHead>
                      <TableHead>کارمند</TableHead>
                      <TableHead>دوره اعتبار</TableHead>
                      <TableHead>وضعیت</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {agreements.map((a) => (
                      <TableRow key={a.id}>
                        <TableCell className="font-mono text-xs">{a.agreement_code}</TableCell>
                        <TableCell>{a.employee ? `${a.employee.first_name} ${a.employee.last_name}` : `کارمند #${a.employee_id}`}</TableCell>
                        <TableCell className="text-xs">{a.start_date} تا {a.end_date}</TableCell>
                        <TableCell><Badge className="bg-teal-600 text-white text-xs">{a.status}</Badge></TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* ── 6. Mission Orders Tab ── */}
        <TabsContent value="missions" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <PlaneTakeoff className="w-5 h-5 text-cyan-600" />
                  <CardTitle className="text-base">صدور حکم مأموریت اداری</CardTitle>
                </div>
                <CardDescription>ثبت حکم مأموریت، مقصد، تاریخ و فوق‌العاده مأموریت جهت الصاق به فیش حقوقی</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>انتخاب کارمند</Label>
                  <Select value={misEmpId} onValueChange={setMisEmpId}>
                    <SelectTrigger><SelectValue placeholder="کارمند را انتخاب کنید..." /></SelectTrigger>
                    <SelectContent>
                      {employees.map((emp) => (
                        <SelectItem key={String(emp.id)} value={String(emp.id)}>
                          {String(`${emp.first_name || ''} ${emp.last_name || ''}`.trim() || `کارمند #${emp.id}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>مقصد مأموریت</Label>
                  <Input placeholder="مثال: عسلویه - فاز ۱۲" value={misDestination} onChange={(e) => setMisDestination(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label>فوق‌العاده مأموریت (ریال)</Label>
                  <Input value={misAllowance} onChange={(e) => setMisAllowance(e.target.value)} />
                </div>
                <Button className="w-full bg-cyan-600 hover:bg-cyan-700 text-white" onClick={handleCreateMission} disabled={submittingMis || !misEmpId}>
                  {submittingMis ? 'در حال صدور...' : 'صدور حکم مأموریت اداری'}
                </Button>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2 shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <CardTitle className="text-base">احکام مأموریت صادرشده</CardTitle>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>شماره حکم</TableHead>
                      <TableHead>کارمند</TableHead>
                      <TableHead>مقصد</TableHead>
                      <TableHead>حق مأموریت</TableHead>
                      <TableHead>وضعیت</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {missions.map((m) => (
                      <TableRow key={m.id}>
                        <TableCell className="font-mono text-xs">{m.mission_no}</TableCell>
                        <TableCell>{m.employee ? `${m.employee.first_name} ${m.employee.last_name}` : 'کارمند'}</TableCell>
                        <TableCell className="text-xs">{m.destination}</TableCell>
                        <TableCell className="font-mono text-xs">{formatNumber(m.total_allowance)} ریال</TableCell>
                        <TableCell><Badge className="bg-cyan-600 text-white text-xs">{m.status}</Badge></TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* ── 7. SSO & Tax Diskette Tab ── */}
        <TabsContent value="sso_diskette" className="space-y-6">
          <Card className="shadow-sm border-slate-200 dark:border-slate-800">
            <CardHeader>
              <div className="flex items-center gap-2">
                <Download className="w-5 h-5 text-amber-600" />
                <CardTitle className="text-base">تولید دیسکت‌های قانونی بیمه تأمین اجتماعی و مالیات بر حقوق</CardTitle>
              </div>
              <CardDescription>
                تولید همزمان فایل بیمه (DSKKAR00 / DSKWOR00) و فایل مالیات حقوق سازمان امور مالیاتی (ماده ۸۴)
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>انتخاب دوره حقوق و دستمزد</Label>
                  <Select value={selectedRunId} onValueChange={setSelectedRunId}>
                    <SelectTrigger><SelectValue placeholder="دوره حقوق را انتخاب کنید..." /></SelectTrigger>
                    <SelectContent>
                      {payrollRuns.map((r) => (
                        <SelectItem key={String(r.id)} value={String(r.id)}>
                          دوره {String(r.month)} / {String(r.year)} ({String(r.title || `دوره #${r.id}`)})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="flex items-end">
                  <Button className="w-full bg-amber-600 hover:bg-amber-700 text-white" onClick={handleGenerateDiskettes} disabled={generatingDiskette || !selectedRunId}>
                    {generatingDiskette ? 'در حال تولید فایل‌های قانونی...' : 'تولید دیسکت بیمه و مالیات'}
                  </Button>
                </div>
              </div>

              {disketteResult && (
                <div className="mt-4 p-4 rounded-xl border border-amber-200 bg-amber-50/50 dark:bg-amber-950/20 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-sm text-amber-900 dark:text-amber-200">فایل استاندارد بیمه تأمین اجتماعی (DSKWOR / DSKKAR)</span>
                    <Badge variant="outline">{disketteResult.record_count} پرسنل</Badge>
                  </div>
                  <p className="text-xs text-amber-800 dark:text-amber-300">مجموع دستمزد مشمول: {formatNumber(disketteResult.total_wage)} ریال</p>
                  <pre className="p-3 bg-slate-900 text-emerald-400 font-mono text-xs rounded-lg overflow-x-auto" dir="ltr">
                    {disketteResult.content_sample}
                  </pre>
                </div>
              )}

              {taxResult && (
                <div className="mt-4 p-4 rounded-xl border border-indigo-200 bg-indigo-50/50 dark:bg-indigo-950/20 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-sm text-indigo-900 dark:text-indigo-200">فایل استاندارد مالیات حقوق (سامانه ماده ۸۴ امور مالیاتی)</span>
                    <Badge variant="outline">{taxResult.record_count} پرسنل</Badge>
                  </div>
                  <p className="text-xs text-indigo-800 dark:text-indigo-300">مجموع مالیات تکلیفی: {formatNumber(taxResult.total_tax)} ریال</p>
                  <pre className="p-3 bg-slate-900 text-cyan-400 font-mono text-xs rounded-lg overflow-x-auto" dir="ltr">
                    {taxResult.tax_diskette_content}
                  </pre>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

export default HrmEnterpriseSuitePage;
