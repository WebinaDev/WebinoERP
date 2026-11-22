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

interface ResumeMatch {
  id: number;
  applicant_id: number;
  posting_id: number;
  applicant?: {
    id: number;
    first_name?: string;
    last_name?: string;
    full_name?: string;
    email: string;
    experience_years: number;
  };
  posting?: {
    id: number;
    title: string;
  };
  overall_score: number;
  skills_match_score: number;
  experience_match_score: number;
  education_match_score: number;
  matched_skills: string[];
  missing_skills: string[];
  ai_summary_fa: string;
  recommendation: 'highly_recommended' | 'recommended' | 'review' | 'rejected';
}

interface AttritionRisk {
  id: number;
  employee_id: number;
  employee?: {
    id: number;
    first_name: string;
    last_name: string;
    department?: string;
    position?: string;
  };
  risk_level: 'low' | 'medium' | 'high' | 'critical';
  risk_probability: number;
  top_contributing_factors: string[];
  recommended_actions: string;
  calculated_date: string;
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

export function HrmEnterpriseSuitePage() {
  const t = useTranslations('hrm');
  const [activeTab, setActiveTab] = useState<'retro' | 'formula' | 'career' | 'sso_diskette' | 'recruitment_ai' | 'retention_analytics'>('retro');

  // Master lookup data
  const [employees, setEmployees] = useState<Record<string, unknown>[]>([]);
  const [decrees, setDecrees] = useState<Record<string, unknown>[]>([]);
  const [postings, setPostings] = useState<Record<string, unknown>[]>([]);
  const [applicants, setApplicants] = useState<Record<string, unknown>[]>([]);
  const [payrollRuns, setPayrollRuns] = useState<Record<string, unknown>[]>([]);

  // Retroactive state
  const [retroList, setRetroList] = useState<RetroAdjustment[]>([]);
  const [retroLoading, setRetroLoading] = useState(false);
  const [selectedDecreeId, setSelectedDecreeId] = useState<string>('');
  const [calculatingDecree, setCalculatingDecree] = useState(false);

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

  // SSO Diskette state
  const [selectedRunId, setSelectedRunId] = useState('');
  const [generatingDiskette, setGeneratingDiskette] = useState(false);
  const [disketteResult, setDisketteResult] = useState<any>(null);

  // AI Recruitment state
  const [matches, setMatches] = useState<ResumeMatch[]>([]);
  const [matchesLoading, setMatchesLoading] = useState(false);
  const [selectedApplicantId, setSelectedApplicantId] = useState<string>('');
  const [selectedPostingId, setSelectedPostingId] = useState<string>('');
  const [scoringRunning, setScoringRunning] = useState(false);

  // Attrition Risk state
  const [attritionList, setAttritionList] = useState<AttritionRisk[]>([]);
  const [attritionLoading, setAttritionLoading] = useState(false);
  const [selectedEmployeeId, setSelectedEmployeeId] = useState<string>('');
  const [evaluatingRisk, setEvaluatingRisk] = useState(false);

  // Load master entities for dropdowns
  const loadMasterData = useCallback(async () => {
    try {
      const [staffRes, decreesRes, postingsRes, applicantsRes, runsRes] = await Promise.allSettled([
        getStaff({ per_page: 100 }),
        getPayrollDecrees(),
        getJobPostings(),
        getJobApplicants(),
        getPayrollRuns(),
      ]);

      if (staffRes.status === 'fulfilled') setEmployees(normalizeListPayload(staffRes.value));
      if (decreesRes.status === 'fulfilled') {
        const d = (decreesRes.value as { decrees?: Record<string, unknown>[] })?.decrees || [];
        setDecrees(Array.isArray(d) ? d : []);
      }
      if (postingsRes.status === 'fulfilled') setPostings(normalizeListPayload(postingsRes.value));
      if (applicantsRes.status === 'fulfilled') setApplicants(normalizeListPayload(applicantsRes.value));
      if (runsRes.status === 'fulfilled') setPayrollRuns(normalizeListPayload(runsRes.value));
    } catch {
      // Non-blocking
    }
  }, []);

  // Fetch retro adjustments from live backend
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

  // Fetch Formula Components
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

  // Fetch Career Promotions
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

  // Fetch AI resume matches
  const fetchMatches = useCallback(async () => {
    setMatchesLoading(true);
    try {
      const res = await apiClient.get('/api/v1/hrm/enterprise/recruitment/resume-matches');
      const items = res.data?.data?.data || res.data?.data || [];
      setMatches(Array.isArray(items) ? items : []);
    } catch (err) {
      setMatches([]);
      toast.error(getAxiosMessage(err, 'خطا در دریافت نتایج ارزیابی هوش مصنوعی'));
    } finally {
      setMatchesLoading(false);
    }
  }, []);

  // Fetch Attrition risks
  const fetchAttrition = useCallback(async () => {
    setAttritionLoading(true);
    try {
      const res = await apiClient.get('/api/v1/hrm/enterprise/analytics/attrition-risks');
      const items = res.data?.data?.data || res.data?.data || [];
      setAttritionList(Array.isArray(items) ? items : []);
    } catch (err) {
      setAttritionList([]);
      toast.error(getAxiosMessage(err, 'خطا در دریافت شاخص‌های ریسک ترک خدمت'));
    } finally {
      setAttritionLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadMasterData();
  }, [loadMasterData]);

  useEffect(() => {
    if (activeTab === 'retro') void fetchRetro();
    if (activeTab === 'formula') void fetchFormulas();
    if (activeTab === 'career') void fetchProposals();
    if (activeTab === 'recruitment_ai') void fetchMatches();
    if (activeTab === 'retention_analytics') void fetchAttrition();
  }, [activeTab, fetchRetro, fetchFormulas, fetchProposals, fetchMatches, fetchAttrition]);

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

  const handleGenerateDiskette = async () => {
    if (!selectedRunId) {
      toast.error('لطفاً یک دوره حقوق و دستمزد را انتخاب فرمایید.');
      return;
    }
    setGeneratingDiskette(true);
    try {
      const res = await apiClient.post(`/api/v1/hrm/enterprise/payroll/runs/${selectedRunId}/generate-sso-diskette`);
      toast.success('دیسکت استاندارد تأمین اجتماعی تولید شد.');
      setDisketteResult(res.data?.data);
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در تولید دیسکت قانونی بیمه'));
    } finally {
      setGeneratingDiskette(false);
    }
  };

  const handleRunAiMatch = async () => {
    if (!selectedApplicantId || !selectedPostingId) {
      toast.error('لطفاً متقاضی و موقعیت شغلی هدف را انتخاب فرمایید.');
      return;
    }
    setScoringRunning(true);
    try {
      await apiClient.post(`/api/v1/hrm/enterprise/recruitment/match/${selectedApplicantId}/${selectedPostingId}`);
      toast.success('ارزیابی و انطباق هوش مصنوعی با موفقیت ثبت شد.');
      void fetchMatches();
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در تحلیل هوش مصنوعی رزومه'));
    } finally {
      setScoringRunning(false);
    }
  };

  const handleEvaluateAttrition = async () => {
    if (!selectedEmployeeId) {
      toast.error('لطفاً کارمند مورد نظر را انتخاب فرمایید.');
      return;
    }
    setEvaluatingRisk(true);
    try {
      await apiClient.post(`/api/v1/hrm/enterprise/analytics/attrition-risks/${selectedEmployeeId}/evaluate`);
      toast.success('شاخص ریسک ترک خدمت کارمند با موفقیت ارزیابی و ثبت شد.');
      void fetchAttrition();
    } catch (err) {
      toast.error(getAxiosMessage(err, 'خطا در ارزیابی ریسک خروج کارمند'));
    } finally {
      setEvaluatingRisk(false);
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
              Enterprise HRM & HCM Suite (Rahkaran Parity)
            </Badge>
            <span className="text-xs text-indigo-200">معماری همتراز سیستم‌های سازمانی ایران</span>
          </div>
          <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight">
            سامانه پیشرفته منابع انسانی، احکام و انطباق قانونی
          </h1>
          <p className="text-sm text-indigo-100 max-w-3xl">
            موتور فرمول‌نویسی احکام کارگزینی، معوقات حقوق (Retro Pay)، دیسکت‌های قانونی تأمین اجتماعی/مالیات، و مدیریت ارتقای شغلی
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
              if (activeTab === 'recruitment_ai') void fetchMatches();
              if (activeTab === 'retention_analytics') void fetchAttrition();
            }}
          >
            <RefreshCw className="w-4 h-4 ml-2" />
            بروزرسانی زنده
          </Button>
        </div>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as any)} className="space-y-6">
        <TabsList className="grid grid-cols-2 md:grid-cols-6 h-auto p-1 bg-slate-100 dark:bg-slate-800 rounded-xl">
          <TabsTrigger value="retro" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <History className="w-4 h-4 text-indigo-600" />
            <span>احکام معوق (Retro)</span>
          </TabsTrigger>
          <TabsTrigger value="formula" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <Code2 className="w-4 h-4 text-purple-600" />
            <span>فرمول عوامل حکمی</span>
          </TabsTrigger>
          <TabsTrigger value="career" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <Compass className="w-4 h-4 text-blue-600" />
            <span>مسیر شغلی و ارتقا</span>
          </TabsTrigger>
          <TabsTrigger value="sso_diskette" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <Download className="w-4 h-4 text-amber-600" />
            <span>دیسکت بیمه تأمین اجتماعی</span>
          </TabsTrigger>
          <TabsTrigger value="recruitment_ai" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <BrainCircuit className="w-4 h-4 text-emerald-600" />
            <span>انطباق AI رزومه</span>
          </TabsTrigger>
          <TabsTrigger value="retention_analytics" className="py-2.5 text-xs md:text-sm flex items-center gap-1.5">
            <TrendingDown className="w-4 h-4 text-rose-600" />
            <span>ریسک خروج (Attrition)</span>
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
                  در صورت صدور حکم جدید با تاریخ اجرای گذشته (Effective Dating)، مابه‌التفاوت دوره‌های قبل به صورت خودکار محاسبه می‌گردد.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>انتخاب حکم کارگزینی هدف</Label>
                  <Select value={selectedDecreeId} onValueChange={setSelectedDecreeId}>
                    <SelectTrigger>
                      <SelectValue placeholder="حکم کارگزینی را انتخاب کنید..." />
                    </SelectTrigger>
                    <SelectContent>
                      {decrees.length === 0 ? (
                        <div className="p-2 text-xs text-muted-foreground text-center">حکمی یافت نشد</div>
                      ) : (
                        decrees.map((dec) => (
                          <SelectItem key={String(dec.id)} value={String(dec.id)}>
                            {String(dec.decree_no || `حکم #${dec.id}`)} - {String(dec.decree_type || 'اداری')}
                          </SelectItem>
                        ))
                      )}
                    </SelectContent>
                  </Select>
                </div>
                <Button
                  className="w-full bg-indigo-600 hover:bg-indigo-700 text-white"
                  onClick={handleCalculateRetro}
                  disabled={calculatingDecree || !selectedDecreeId}
                >
                  <Sparkles className="w-4 h-4 ml-2" />
                  {calculatingDecree ? 'در حال شبیه‌سازی و پردازش دوره‌ها...' : 'محاسبه مابه‌التفاوت معوقه (Run Retro)'}
                </Button>
                <div className="p-3 bg-indigo-50 dark:bg-indigo-950/40 rounded-lg text-xs text-indigo-700 dark:text-indigo-300 leading-relaxed">
                  سیستم بر مبنای حقوق پایه جدید حکم، تمامی فیش‌های تاییدشده از تاریخ اجرا تا تاریخ صدور را بازمحاسبه کرده و بیمه سهم کارگر (۷٪) و مالیات تصاعدی را کسر می‌کند.
                </div>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2 shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader className="flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base">لیست مابه‌التفاوت‌های عطف‌به‌ماسبق</CardTitle>
                  <CardDescription>اقلام تسویه‌نشده ناشی از بازنگری یا ارتقای احکام شغلی</CardDescription>
                </div>
                <Badge variant="outline" className="text-xs">
                  {retroList.length} رکورد معوقه
                </Badge>
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
                              <div className="text-xs text-slate-500">
                                {item.decree?.decree_no || 'حکم اداری'}
                              </div>
                            </TableCell>
                            <TableCell>
                              <Badge variant="secondary" className="font-mono text-xs">
                                {item.target_month} / {item.target_year}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              <div className="text-xs font-mono text-slate-500 line-through">
                                {formatNumber(item.original_gross)} ریال
                              </div>
                              <div className="text-xs font-mono font-medium text-emerald-600">
                                {formatNumber(item.adjusted_gross)} ریال
                              </div>
                            </TableCell>
                            <TableCell>
                              <span className="font-bold text-sm text-indigo-600 font-mono">
                                +{formatNumber(item.differential_net)} ریال
                              </span>
                              <div className="text-[10px] text-slate-400">
                                کسر بیمه: {formatNumber(item.differential_insurance)} | مالیات: {formatNumber(item.differential_tax)}
                              </div>
                            </TableCell>
                            <TableCell>
                              <Badge
                                className={
                                  item.status === 'applied'
                                    ? 'bg-emerald-500/10 text-emerald-700 border-emerald-300'
                                    : 'bg-amber-500/10 text-amber-700 border-amber-300'
                                }
                              >
                                {item.status === 'applied' ? 'منظور در فیش آتی' : 'در انتظار تایید'}
                              </Badge>
                            </TableCell>
                            <TableCell>
                              {item.status !== 'applied' && (
                                <Button
                                  size="sm"
                                  variant="outline"
                                  className="text-xs h-8 border-indigo-200 hover:bg-indigo-50"
                                  onClick={() => handleApplyRetro(item.id)}
                                >
                                  اعمال در فیش حقوق
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

        {/* ── 2. Formula Components Tab ── */}
        <TabsContent value="formula" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Code2 className="w-5 h-5 text-purple-600" />
                  <CardTitle className="text-base">تعریف عامل حکمی فرمول‌پذیر</CardTitle>
                </div>
                <CardDescription>
                  تعریف مزایای مستمر و غیرمستمر با متغیرهای پویا (مانند حقوق پایه، سنوات و فرزندان)
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>شناسه یکتای عامل (Code)</Label>
                  <Input placeholder="مثال: JOB_DIFFICULTY" value={newCode} onChange={(e) => setNewCode(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label>عنوان عامل حکمی (Title)</Label>
                  <Input placeholder="مثال: حق سختی کار و سرپرستی" value={newTitle} onChange={(e) => setNewTitle(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label>عبارت فرمول محاسباتی (Formula)</Label>
                  <Input placeholder="مثال: BASE * 0.15 + (EXP_YEARS * 2000000)" value={newFormula} onChange={(e) => setNewFormula(e.target.value)} />
                  <p className="text-[11px] text-muted-foreground">متغیرهای مجاز: BASE, DAILY, EXP_YEARS, CHILDREN_COUNT, RANK</p>
                </div>
                <Button className="w-full bg-purple-600 hover:bg-purple-700 text-white" onClick={handleSaveFormula} disabled={savingFormula}>
                  {savingFormula ? 'در حال ثبت عامل...' : 'ذخیره عامل در موتور فرمول'}
                </Button>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2 shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader className="flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base">عوامل حکمی فعال در موتور محاسبات</CardTitle>
                  <CardDescription>فرمول‌های محاسبه اقلام حکم کارگزینی مطابق الزامات قانون کار</CardDescription>
                </div>
                <Badge variant="outline">{formulaComponents.length} عامل فعال</Badge>
              </CardHeader>
              <CardContent>
                {formulaLoading ? (
                  <div className="text-center py-8 text-sm text-slate-500">در حال دریافت عوامل...</div>
                ) : formulaComponents.length === 0 ? (
                  <div className="text-center py-8 text-sm text-slate-500">عاملی ثبت نشده است. از پنل سمت راست اقدام فرمایید.</div>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>کد عامل</TableHead>
                        <TableHead>عنوان</TableHead>
                        <TableHead>فرمول محاسباتی</TableHead>
                        <TableHead>مشمول بیمه/مالیات</TableHead>
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
                )}
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* ── 3. Career Progression Tab ── */}
        <TabsContent value="career" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <Compass className="w-5 h-5 text-blue-600" />
                  <CardTitle className="text-base">ارزیابی و پیشنهاد خودکار ترفیع</CardTitle>
                </div>
                <CardDescription>
                  پایش سوابق خدمت، ارزیابی‌های عملکرد ۳۶۰ و پیشنهاد سیستمی ارتقای شغلی
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>انتخاب کارمند هدف</Label>
                  <Select value={careerEmpId} onValueChange={setCareerEmpId}>
                    <SelectTrigger>
                      <SelectValue placeholder="کارمند را انتخاب کنید..." />
                    </SelectTrigger>
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
                  {evaluatingCareer ? 'در حال پایش ماتریس مسیر شغلی...' : 'سنجش شایستگی ارتقا'}
                </Button>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2 shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader className="flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base">پیشنهادات فعال ارتقا و مسیر شغلی</CardTitle>
                  <CardDescription>کاندیداهای احراز شرایط ارتقای رتبه و صدور حکم کارگزینی جدید</CardDescription>
                </div>
                <Badge variant="outline">{proposals.length} پیشنهاد</Badge>
              </CardHeader>
              <CardContent>
                {proposalsLoading ? (
                  <div className="text-center py-8 text-sm text-slate-500">در حال دریافت داده‌ها...</div>
                ) : proposals.length === 0 ? (
                  <div className="text-center py-8 text-sm text-slate-500">پیشنهاد ارتقایی ثبت نشده است.</div>
                ) : (
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
                        <div className="text-left">
                          <span className="text-xl font-bold font-mono text-blue-600">{p.eligibility_score}٪</span>
                          <div className="text-[10px] text-muted-foreground">امتیاز شایستگی ارتقا</div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* ── 4. SSO Diskette Tab ── */}
        <TabsContent value="sso_diskette" className="space-y-6">
          <Card className="shadow-sm border-slate-200 dark:border-slate-800">
            <CardHeader>
              <div className="flex items-center gap-2">
                <Download className="w-5 h-5 text-amber-600" />
                <CardTitle className="text-base">تولید دیسکت استاندارد بیمه تأمین اجتماعی (DSKWOR / DSKKAR)</CardTitle>
              </div>
              <CardDescription>
                تولید فایل‌های قانونی استاندارد جهت بارگذاری در سامانه eservices.tamin.ir بر مبنای کارکرد دوره
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>انتخاب دوره حقوق و دستمزد (Payroll Run)</Label>
                  <Select value={selectedRunId} onValueChange={setSelectedRunId}>
                    <SelectTrigger>
                      <SelectValue placeholder="دوره حقوق را انتخاب کنید..." />
                    </SelectTrigger>
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
                  <Button className="w-full bg-amber-600 hover:bg-amber-700 text-white" onClick={handleGenerateDiskette} disabled={generatingDiskette || !selectedRunId}>
                    {generatingDiskette ? 'در حال تولید ساختار فایل بیمه...' : 'ایجاد خروجی استاندارد دیسکت'}
                  </Button>
                </div>
              </div>

              {disketteResult && (
                <div className="mt-4 p-4 rounded-xl border border-amber-200 bg-amber-50/50 dark:bg-amber-950/20 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-sm text-amber-900 dark:text-amber-200">فایل استاندارد با موفقیت تولید شد</span>
                    <Badge variant="outline">{disketteResult.record_count} پرسنل مشمول</Badge>
                  </div>
                  <p className="text-xs text-amber-800 dark:text-amber-300">مجموع دستمزد مشمول بیمه: {formatNumber(disketteResult.total_wage)} ریال</p>
                  <pre className="p-3 bg-slate-900 text-emerald-400 font-mono text-xs rounded-lg overflow-x-auto" dir="ltr">
                    {disketteResult.content_sample}
                  </pre>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* ── 5. AI Recruitment Tab ── */}
        <TabsContent value="recruitment_ai" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <BrainCircuit className="w-5 h-5 text-emerald-600" />
                  <CardTitle className="text-base">تحلیل هوشمند تطابق رزومه</CardTitle>
                </div>
                <CardDescription>پردازش خودکار رزومه متقاضیان و مقایسه نیازمندی‌های آگهی شغلی با هوش مصنوعی</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>انتخاب موقعیت شغلی (Job Posting)</Label>
                  <Select value={selectedPostingId} onValueChange={setSelectedPostingId}>
                    <SelectTrigger><SelectValue placeholder="آگهی استخدام را انتخاب کنید..." /></SelectTrigger>
                    <SelectContent>
                      {postings.map((p) => (
                        <SelectItem key={String(p.id)} value={String(p.id)}>{String(p.title || `آگهی #${p.id}`)}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>انتخاب متقاضی استخدام (Job Applicant)</Label>
                  <Select value={selectedApplicantId} onValueChange={setSelectedApplicantId}>
                    <SelectTrigger><SelectValue placeholder="متقاضی را انتخاب کنید..." /></SelectTrigger>
                    <SelectContent>
                      {applicants.map((a) => (
                        <SelectItem key={String(a.id)} value={String(a.id)}>
                          {String(a.full_name || `${a.first_name || ''} ${a.last_name || ''}`.trim() || `متقاضی #${a.id}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <Button className="w-full bg-emerald-600 hover:bg-emerald-700 text-white" onClick={handleRunAiMatch} disabled={scoringRunning || !selectedApplicantId || !selectedPostingId}>
                  <Sparkles className="w-4 h-4 ml-2" />
                  {scoringRunning ? 'در حال استخراج و تحلیل مهارت‌ها...' : 'اجرای انطباق هوش مصنوعی'}
                </Button>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2 shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader className="flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base">رتبه‌بندی هوشمند متقاضیان (AI Ranking)</CardTitle>
                  <CardDescription>ارزیابی تطابق شرایط متقاضیان با شایستگی‌های آگهی</CardDescription>
                </div>
                <Badge variant="outline">{matches.length} ارزیابی ثبت‌شده</Badge>
              </CardHeader>
              <CardContent>
                {matchesLoading ? (
                  <div className="text-center py-12 text-sm text-slate-500">در حال دریافت نتایج هوش مصنوعی...</div>
                ) : matches.length === 0 ? (
                  <div className="text-center py-12 space-y-3">
                    <Briefcase className="w-10 h-10 mx-auto text-slate-300" />
                    <p className="text-sm text-slate-500">هیچ ارزیابی هوشمندی هنوز ثبت نشده است.</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {matches.map((match) => (
                      <div key={match.id} className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 space-y-3">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="font-bold text-slate-900 dark:text-white">
                                {match.applicant?.full_name || `${match.applicant?.first_name || ''} ${match.applicant?.last_name || ''}`.trim() || `متقاضی #${match.applicant_id}`}
                              </span>
                              <Badge className={match.recommendation === 'highly_recommended' ? 'bg-emerald-500 text-white' : 'bg-indigo-500 text-white'}>
                                {match.recommendation === 'highly_recommended' ? 'اولویت اول مصاحبه' : 'پیشنهاد مصاحبه'}
                              </Badge>
                            </div>
                            <div className="text-xs text-slate-500 mt-1">موقعیت: {match.posting?.title || 'عنوان شغلی'}</div>
                          </div>
                          <div className="text-left sm:text-right">
                            <span className="text-2xl font-black text-emerald-600 font-mono">{match.overall_score}٪</span>
                          </div>
                        </div>
                        <div className="grid grid-cols-3 gap-3 pt-2">
                          <Progress value={match.skills_match_score} className="h-1.5" />
                          <Progress value={match.experience_match_score} className="h-1.5" />
                          <Progress value={match.education_match_score} className="h-1.5" />
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        {/* ── 6. Retention Analytics Tab ── */}
        <TabsContent value="retention_analytics" className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card className="shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader>
                <div className="flex items-center gap-2">
                  <TrendingDown className="w-5 h-5 text-rose-600" />
                  <CardTitle className="text-base">سنجش ریسک ترک خدمت (Attrition)</CardTitle>
                </div>
                <CardDescription>پیش‌بینی احتمال استعفای پرسنل بر پایه حجم اضافه‌کار، ایستایی احکام و سطح حقوق</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label>انتخاب کارمند هدف</Label>
                  <Select value={selectedEmployeeId} onValueChange={setSelectedEmployeeId}>
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
                <Button className="w-full bg-rose-600 hover:bg-rose-700 text-white" onClick={handleEvaluateAttrition} disabled={evaluatingRisk || !selectedEmployeeId}>
                  <Sparkles className="w-4 h-4 ml-2" />
                  {evaluatingRisk ? 'در حال ارزیابی متغیرها...' : 'سنجش شاخص ریسک خروج'}
                </Button>
              </CardContent>
            </Card>

            <Card className="lg:col-span-2 shadow-sm border-slate-200 dark:border-slate-800">
              <CardHeader className="flex flex-row items-center justify-between">
                <div>
                  <CardTitle className="text-base">ماتریس نگهداشت و ریسک خروج پرسنل</CardTitle>
                  <CardDescription>شناسایی پرسنل در معرض خطر فرسودگی شغلی و راهکارهای نگهداشت</CardDescription>
                </div>
                <Badge variant="outline">{attritionList.length} کارمند پایش‌شده</Badge>
              </CardHeader>
              <CardContent>
                {attritionLoading ? (
                  <div className="text-center py-12 text-sm text-slate-500">در حال دریافت داده‌های تحلیلی...</div>
                ) : attritionList.length === 0 ? (
                  <div className="text-center py-12 space-y-3">
                    <Users className="w-10 h-10 mx-auto text-slate-300" />
                    <p className="text-sm text-slate-500">هنوز شاخص خروج برای هیچ کارمندی ثبت نشده است.</p>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {attritionList.map((item) => (
                      <div key={item.id} className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 space-y-3">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                          <span className="font-bold text-slate-900 dark:text-white">
                            {item.employee ? `${item.employee.first_name} ${item.employee.last_name}` : `کارمند #${item.employee_id}`}
                          </span>
                          <span className={`text-2xl font-black font-mono ${item.risk_probability > 50 ? 'text-rose-600' : 'text-emerald-600'}`}>
                            {item.risk_probability}٪
                          </span>
                        </div>
                        {item.recommended_actions && (
                          <div className="flex items-start gap-2 bg-indigo-50/70 dark:bg-indigo-950/40 p-3 rounded-lg border border-indigo-100 dark:border-indigo-900/50">
                            <ShieldCheck className="w-4 h-4 text-indigo-600 mt-0.5 shrink-0" />
                            <div className="text-xs text-indigo-900 dark:text-indigo-200">
                              <span className="font-semibold">اقدام پیشنهادی: </span>
                              {item.recommended_actions}
                            </div>
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  );
}

export default HrmEnterpriseSuitePage;
