'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { HrmDigits, HrmPageLayout, HrmStatus } from '@/features/modules/hrm/HrmPageLayout';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { useLocale } from '@/hooks/use-locale-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { PageEmptyState, PageLoadingState } from '@/features/shared/ui/PageStates';
import { buildMonthGrid, shiftMonth } from '@/lib/locale/month-grid';
import { toJalali } from '@/lib/locale/calendar-date';
import { normalizeListPayload } from '@/lib/list-utils';
import {
  applyShiftRotation,
  completeLesson,
  completeMyLesson,
  completeMyOnboardingTask,
  decideTimesheet,
  getHrAnalytics,
  getLessons,
  getMyLearning,
  getMyObjectives,
  getMyOnboarding,
  getMyReviews360,
  getMyShiftCalendar,
  getMyTimesheets,
  getObjectives,
  getOnboardingTemplates,
  getOnboardings,
  getReviews360,
  getShiftCalendar,
  getShiftRotations,
  getShiftTemplates,
  getStaff,
  getSuccessionChart,
  getTimesheets,
  getTrainingCourses,
  saveLesson,
  saveObjective,
  saveOnboardingTemplate,
  saveReview360,
  saveShiftAssignment,
  saveShiftRotation,
  saveShiftTemplate,
  saveSuccessor,
  saveMyTimesheet,
  startOnboarding,
  submitMyTimesheet,
  submitReview360,
  updateMyKeyResult,
} from '@/lib/api/hrm';

type Row = Record<string, unknown>;

function useRows(loader: () => Promise<unknown>) {
  const { applyAxiosError } = useCrmFeedback();
  const [rows, setRows] = useState<Row[]>([]);
  const [loading, setLoading] = useState(true);
  const load = useCallback(async () => {
    setLoading(true);
    try {
      setRows(normalizeListPayload(await loader()));
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setLoading(false);
    }
  }, [applyAxiosError, loader]);
  useEffect(() => { void load(); }, [load]);
  return { rows, loading, load };
}

export function ShiftsPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatDigits, formatDate, locale } = useLocale();
  const [cursor, setCursor] = useState(() => new Date());
  const [templates, setTemplates] = useState<Row[]>([]);
  const [assignments, setAssignments] = useState<Row[]>([]);
  const [employees, setEmployees] = useState<Row[]>([]);
  const [form, setForm] = useState({ name: '', start_time: '08:00', end_time: '16:00', employee_id: '', work_date: '', shift_template_id: '', rotation_name: 'چرخش هفتگی' });
  const weeks = useMemo(() => buildMonthGrid(cursor, locale), [cursor, locale]);
  const range = useMemo(() => {
    const days = weeks.flat().filter((d) => d.iso);
    return { from: days[0]?.iso, to: days[days.length - 1]?.iso };
  }, [weeks]);
  const load = useCallback(async () => {
    if (!range.from) return;
    try {
      const [tmpl, cal, staff] = await Promise.all([
        getShiftTemplates(),
        getShiftCalendar({ from: range.from, to: range.to }),
        getStaff(),
      ]);
      setTemplates(normalizeListPayload(tmpl));
      setAssignments(Array.isArray(cal) ? cal as Row[] : []);
      setEmployees(normalizeListPayload(staff));
    } catch (err) { applyAxiosError(err); }
  }, [applyAxiosError, range.from, range.to]);
  useEffect(() => { void load(); }, [load]);
  const byDate = new Map<string, Row[]>();
  assignments.forEach((a) => {
    const key = String(a.work_date).slice(0, 10);
    byDate.set(key, [...(byDate.get(key) ?? []), a]);
  });
  const j = toJalali(cursor.getFullYear(), cursor.getMonth() + 1, cursor.getDate());
  const title = locale === 'fa' ? formatDigits(`${j.jy}/${String(j.jm).padStart(2, '0')}`) : `${cursor.getFullYear()}/${cursor.getMonth() + 1}`;
  const weekdays = locale === 'fa' ? ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'] : ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.shifts')} description={t('suite.shifts.hint')} {...layoutProps} actions={
      <div className="flex gap-2">
        <Button variant="outline" onClick={() => setCursor((c) => shiftMonth(c, -1, locale))}>{tNav('date.prevMonth')}</Button>
        <Button variant="outline" onClick={() => setCursor((c) => shiftMonth(c, 1, locale))}>{tNav('date.nextMonth')}</Button>
      </div>
    }>
      <Card><CardHeader><CardTitle className="text-base">{title}</CardTitle></CardHeader>
        <CardContent>
          <div className="grid grid-cols-7 gap-1 text-center text-xs">
            {weekdays.map((w) => <div key={w} className="font-medium text-muted-foreground">{w}</div>)}
            {weeks.flat().map((d, i) => (
              <div key={i} className="min-h-16 rounded border p-1 text-start">
                <div>{d.day ? formatDigits(d.day) : ''}</div>
                {(byDate.get(d.iso) ?? []).map((a) => (
                  <div key={String(a.id)} className="truncate text-[10px]">
                    {String((a.employee as Row | undefined)?.first_name ?? '')} {a.is_off ? t('suite.shifts.off') : String((a.template as Row | undefined)?.name ?? '')}
                  </div>
                ))}
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
      <div className="grid gap-4 md:grid-cols-2">
        <Card><CardHeader><CardTitle className="text-base">{t('suite.shifts.newTemplate')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('shiftName')} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            <Input dir="ltr" value={form.start_time} onChange={(e) => setForm({ ...form, start_time: e.target.value })} />
            <Input dir="ltr" value={form.end_time} onChange={(e) => setForm({ ...form, end_time: e.target.value })} />
            <Button onClick={() => void saveShiftTemplate({ name: form.name, start_time: form.start_time, end_time: form.end_time, is_active: true }).then(() => { setSuccess(tNav('common.saved')); void load(); }).catch(applyAxiosError)}>{tNav('common.save')}</Button>
            <div className="text-sm text-muted-foreground">{templates.map((x) => String(x.name)).join(' · ') || tNav('common.empty')}</div>
          </CardContent>
        </Card>
        <Card><CardHeader><CardTitle className="text-base">{t('suite.shifts.assign')}</CardTitle></CardHeader>
          <CardContent className="space-y-2">
            <Input placeholder={t('suite.shifts.employeeId')} value={form.employee_id} onChange={(e) => setForm({ ...form, employee_id: e.target.value })} />
            <Input placeholder={t('suite.shifts.templateId')} value={form.shift_template_id} onChange={(e) => setForm({ ...form, shift_template_id: e.target.value })} />
            <LocaleDatePicker value={form.work_date} onChange={(v) => setForm({ ...form, work_date: v })} />
            <Button onClick={() => void saveShiftAssignment({ employee_id: Number(form.employee_id), shift_template_id: Number(form.shift_template_id), work_date: form.work_date }).then(() => { setSuccess(tNav('common.saved')); void load(); }).catch(applyAxiosError)}>{t('suite.shifts.assign')}</Button>
            <Button variant="outline" onClick={() => void (async () => {
              const rot = await saveShiftRotation({ name: form.rotation_name, pattern: templates.slice(0, 2).map((x) => ({ shift_template_id: x.id })) });
              const id = (rot as Row).id ?? (rot as { data?: Row }).data?.id;
              if (!id) return;
              await applyShiftRotation(id as number, { employee_id: Number(form.employee_id), start_date: form.work_date, days: 14 });
              setSuccess(t('suite.shifts.rotated'));
              void load();
            })().catch(applyAxiosError)}>{t('suite.shifts.rotate')}</Button>
            <div className="text-xs text-muted-foreground">{t('suite.shifts.conflictHint')} · {formatDigits(employees.length)}</div>
          </CardContent>
        </Card>
      </div>
    </HrmPageLayout>
  );
}

export function OnboardingAdminPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [name, setName] = useState('');
  const [task, setTask] = useState('');
  const [category, setCategory] = useState('national_card');
  const [employeeId, setEmployeeId] = useState('');
  const [templateId, setTemplateId] = useState('');
  const templates = useRows(getOnboardingTemplates);
  const rows = useRows(getOnboardings);
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.onboarding')} description={t('suite.onboarding.hint')} {...layoutProps}>
      <Card><CardContent className="space-y-2 pt-6">
        <Input placeholder={t('suite.onboarding.template')} value={name} onChange={(e) => setName(e.target.value)} />
        <Input placeholder={t('suite.onboarding.task')} value={task} onChange={(e) => setTask(e.target.value)} />
        <Input placeholder={t('documents.category')} value={category} onChange={(e) => setCategory(e.target.value)} />
        <Button onClick={() => void saveOnboardingTemplate({ name, items: [{ title: task, document_category: category, sort_order: 0 }] }).then(() => { setSuccess(tNav('common.saved')); void templates.load(); }).catch(applyAxiosError)}>{tNav('common.save')}</Button>
      </CardContent></Card>
      <Card><CardHeader><CardTitle className="text-base">{t('suite.onboarding.start')}</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          <Input placeholder={t('suite.shifts.employeeId')} value={employeeId} onChange={(e) => setEmployeeId(e.target.value)} />
          <Input placeholder={t('suite.onboarding.templateId')} value={templateId} onChange={(e) => setTemplateId(e.target.value)} />
          <Button onClick={() => void startOnboarding({ employee_id: Number(employeeId), template_id: Number(templateId) }).then(() => { setSuccess(tNav('common.saved')); void rows.load(); }).catch(applyAxiosError)}>{t('suite.onboarding.start')}</Button>
          {templates.loading || rows.loading ? <PageLoadingState /> : (
            <Table><TableHeader><TableRow><TableHead>{t('employee')}</TableHead><TableHead>{t('status')}</TableHead><TableHead>{t('suite.onboarding.tasks')}</TableHead></TableRow></TableHeader>
              <TableBody>
                {rows.rows.length === 0 ? <TableRow><TableCell colSpan={3}><PageEmptyState /></TableCell></TableRow> : rows.rows.map((r) => (
                  <TableRow key={String(r.id)}>
                    <TableCell><HrmDigits value={(r.employee as Row | undefined)?.employee_code ?? r.employee_id} /></TableCell>
                    <TableCell><HrmStatus value={r.status} /></TableCell>
                    <TableCell><HrmDigits value={Array.isArray(r.tasks) ? r.tasks.length : 0} /></TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </HrmPageLayout>
  );
}

export function TimesheetsAdminPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatNumber, formatDate } = useLocale();
  const list = useRows(getTimesheets);
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.timesheets')} description={t('suite.time.hint')} {...layoutProps}>
      {list.loading ? <PageLoadingState /> : (
        <Table><TableHeader><TableRow>
          <TableHead>{t('employee')}</TableHead><TableHead>{t('suite.time.project')}</TableHead><TableHead>{t('dateFrom')}</TableHead><TableHead>{t('suite.time.hours')}</TableHead><TableHead>{t('status')}</TableHead><TableHead />
        </TableRow></TableHeader><TableBody>
          {list.rows.length === 0 ? <TableRow><TableCell colSpan={6}><PageEmptyState /></TableCell></TableRow> : list.rows.map((r) => (
            <TableRow key={String(r.id)}>
              <TableCell>{String((r.employee as Row | undefined)?.first_name ?? r.employee_id ?? '')}</TableCell>
              <TableCell>{String(r.project_name ?? r.project_id ?? '—')}</TableCell>
              <TableCell>{formatDate(String(r.work_date ?? ''))}</TableCell>
              <TableCell>{formatNumber(Number(r.hours ?? 0))}</TableCell>
              <TableCell><HrmStatus value={r.status} /></TableCell>
              <TableCell className="space-x-2 space-x-reverse">
                {r.status === 'submitted' ? <>
                  <Button size="sm" onClick={() => void decideTimesheet(r.id as number, 'approved').then(() => { setSuccess(tNav('common.saved')); void list.load(); }).catch(applyAxiosError)}>{t('approve')}</Button>
                  <Button size="sm" variant="outline" onClick={() => void decideTimesheet(r.id as number, 'rejected').then(() => void list.load()).catch(applyAxiosError)}>{t('reject')}</Button>
                </> : null}
              </TableCell>
            </TableRow>
          ))}
        </TableBody></Table>
      )}
    </HrmPageLayout>
  );
}

export function AnalyticsPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const { formatNumber, formatDigits, formatDate, locale } = useLocale();
  const [data, setData] = useState<Row | null>(null);
  useEffect(() => {
    void getHrAnalytics().then((res) => setData(res as Row)).catch(applyAxiosError);
  }, [applyAxiosError]);
  const turnover = (data?.turnover ?? {}) as Row;
  const cost = (data?.labor_cost ?? {}) as Row;
  const heat = (data?.attendance_heatmap ?? []) as Row[];
  const max = Math.max(1, ...heat.map((h) => Number(h.present ?? 0)));
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.analytics')} description={t('suite.analytics.hint')} {...layoutProps}>
      {!data ? <PageLoadingState /> : (
        <div className="space-y-4">
          <div className="grid gap-3 md:grid-cols-3">
            <Card><CardHeader><CardTitle className="text-base">{t('suite.analytics.turnover')}</CardTitle></CardHeader><CardContent>{formatNumber(Number(turnover.rate ?? 0))}٪ · {t('suite.analytics.leavers')} <HrmDigits value={turnover.leavers} /></CardContent></Card>
            <Card><CardHeader><CardTitle className="text-base">{t('suite.analytics.headcount')}</CardTitle></CardHeader><CardContent><HrmDigits value={turnover.headcount} /></CardContent></Card>
            <Card><CardHeader><CardTitle className="text-base">{t('suite.analytics.labor')}</CardTitle></CardHeader><CardContent>{formatNumber(Number(cost.net ?? 0))}</CardContent></Card>
          </div>
          <Card><CardHeader><CardTitle className="text-base">{t('suite.analytics.heatmap')}</CardTitle></CardHeader>
            <CardContent className="grid grid-cols-7 gap-1">
              {heat.slice(-35).map((h) => (
                <div key={String(h.date)} title={formatDate(String(h.date))} className="h-8 rounded" style={{ background: `rgba(30,64,175,${0.15 + (Number(h.present ?? 0) / max) * 0.85})` }}>
                  <span className="text-[10px] text-white">{locale === 'fa' ? formatDigits(String(h.date).slice(-2)) : String(h.date).slice(-2)}</span>
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      )}
    </HrmPageLayout>
  );
}

export function OkrAdminPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatNumber } = useLocale();
  const list = useRows(getObjectives);
  const [form, setForm] = useState({ employee_id: '', title: '', kr: '', target: '100' });
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.okrs')} description={t('suite.okr.hint')} {...layoutProps}>
      <Card><CardContent className="grid gap-2 pt-6 md:grid-cols-2">
        <Input placeholder={t('suite.shifts.employeeId')} value={form.employee_id} onChange={(e) => setForm({ ...form, employee_id: e.target.value })} />
        <Input placeholder={t('suite.okr.objective')} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
        <Input placeholder={t('suite.okr.kr')} value={form.kr} onChange={(e) => setForm({ ...form, kr: e.target.value })} />
        <Input dir="ltr" placeholder={t('suite.okr.target')} value={form.target} onChange={(e) => setForm({ ...form, target: e.target.value })} />
        <Button onClick={() => void saveObjective({ employee_id: Number(form.employee_id), title: form.title, key_results: [{ title: form.kr, target_value: Number(form.target), current_value: 0 }] }).then(() => { setSuccess(tNav('common.saved')); void list.load(); }).catch(applyAxiosError)}>{tNav('common.save')}</Button>
      </CardContent></Card>
      {list.loading ? <PageLoadingState /> : list.rows.length === 0 ? <PageEmptyState /> : list.rows.map((r) => (
        <Card key={String(r.id)}><CardHeader><CardTitle className="text-base">{String(r.title)} · {formatNumber(Number(r.progress ?? 0))}٪</CardTitle></CardHeader>
          <CardContent className="text-sm">{(r.key_results as Row[] | undefined)?.map((k) => <div key={String(k.id)}>{String(k.title)} · {formatNumber(Number(k.current_value ?? 0))}/{formatNumber(Number(k.target_value ?? 0))}</div>)}</CardContent>
        </Card>
      ))}
    </HrmPageLayout>
  );
}

export function Review360Page() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatNumber } = useLocale();
  const list = useRows(getReviews360);
  const [employeeId, setEmployeeId] = useState('');
  const [raterId, setRaterId] = useState('');
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.review360')} description={t('suite.review.hint')} {...layoutProps}>
      <Card><CardContent className="space-y-2 pt-6">
        <Input placeholder={t('suite.review.subject')} value={employeeId} onChange={(e) => setEmployeeId(e.target.value)} />
        <Input placeholder={t('suite.review.rater')} value={raterId} onChange={(e) => setRaterId(e.target.value)} />
        <Button onClick={() => void saveReview360({ employee_id: Number(employeeId), raters: [{ employee_id: Number(employeeId), relationship: 'self' }, ...(raterId ? [{ employee_id: Number(raterId), relationship: 'peer' }] : [])] }).then(() => { setSuccess(tNav('common.saved')); void list.load(); }).catch(applyAxiosError)}>{t('suite.review.open')}</Button>
      </CardContent></Card>
      {list.loading ? <PageLoadingState /> : (
        <Table><TableHeader><TableRow><TableHead>{t('employee')}</TableHead><TableHead>{t('status')}</TableHead><TableHead>{t('score')}</TableHead></TableRow></TableHeader>
          <TableBody>{list.rows.map((r) => (
            <TableRow key={String(r.id)}><TableCell><HrmDigits value={r.employee_id} /></TableCell><TableCell><HrmStatus value={r.status} /></TableCell><TableCell>{r.average_score != null ? formatNumber(Number(r.average_score)) : '—'}</TableCell></TableRow>
          ))}</TableBody></Table>
      )}
    </HrmPageLayout>
  );
}

export function LearningAdminPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [courses, setCourses] = useState<Row[]>([]);
  const lessons = useRows(getLessons);
  const [form, setForm] = useState({ course_id: '', title: '', material_url: '', content_type: 'link', employee_id: '' });
  useEffect(() => { void getTrainingCourses().then((r) => setCourses(normalizeListPayload(r))).catch(applyAxiosError); }, [applyAxiosError]);
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.learning')} description={t('suite.lms.hint')} {...layoutProps}>
      <Card><CardContent className="space-y-2 pt-6">
        <Input placeholder={t('suite.lms.courseId')} value={form.course_id} onChange={(e) => setForm({ ...form, course_id: e.target.value })} />
        <Input placeholder={t('suite.shifts.employeeId')} value={form.employee_id} onChange={(e) => setForm({ ...form, employee_id: e.target.value })} />
        <Input placeholder={t('suite.lms.lesson')} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
        <Input dir="ltr" placeholder={t('suite.lms.material')} value={form.material_url} onChange={(e) => setForm({ ...form, material_url: e.target.value })} />
        <Button onClick={() => void saveLesson({ ...form, course_id: Number(form.course_id), is_required: true }).then(() => { setSuccess(tNav('common.saved')); void lessons.load(); }).catch(applyAxiosError)}>{tNav('common.save')}</Button>
        <div className="text-xs text-muted-foreground">{courses.map((c) => `${c.id}:${c.title}`).join(' | ')}</div>
      </CardContent></Card>
      {lessons.loading ? <PageLoadingState /> : (
        <Table><TableHeader><TableRow><TableHead>{t('suite.lms.lesson')}</TableHead><TableHead>{t('courseTitle')}</TableHead><TableHead /></TableRow></TableHeader>
          <TableBody>{lessons.rows.map((r) => (
            <TableRow key={String(r.id)}>
              <TableCell>{String(r.title)}</TableCell>
              <TableCell><HrmDigits value={r.course_id} /></TableCell>
              <TableCell><Button size="sm" variant="outline" onClick={() => void completeLesson(r.id as number, { employee_id: Number(form.employee_id), progress_percent: 100 }).then(() => setSuccess(t('suite.lms.certificateReady'))).catch(applyAxiosError)}>{t('suite.lms.complete')}</Button></TableCell>
            </TableRow>
          ))}</TableBody></Table>
      )}
    </HrmPageLayout>
  );
}

type OrgNode = { id: number; title: string; incumbent?: { name: string } | null; successors?: { id: number; name: string; readiness: string }[]; children?: OrgNode[] };

function OrgTree({ nodes, onPick }: { nodes: OrgNode[]; onPick: (id: number) => void }) {
  return (
    <ul className="space-y-2 border-s ps-4">
      {nodes.map((n) => (
        <li key={n.id}>
          <button type="button" className="rounded border px-3 py-2 text-start" onClick={() => onPick(n.id)}>
            <div className="font-medium">{n.title}</div>
            <div className="text-xs text-muted-foreground">{n.incumbent?.name ?? '—'}</div>
            {(n.successors ?? []).map((s) => <div key={s.id} className="text-xs">{s.name} · {s.readiness}</div>)}
          </button>
          {n.children?.length ? <OrgTree nodes={n.children} onPick={onPick} /> : null}
        </li>
      ))}
    </ul>
  );
}

export function OrgChartAdminPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const [nodes, setNodes] = useState<OrgNode[]>([]);
  const [sel, setSel] = useState('');
  const [emp, setEmp] = useState('');
  const [ready, setReady] = useState('1_year');
  const load = useCallback(async () => {
    try { setNodes((await getSuccessionChart()) as OrgNode[]); } catch (e) { applyAxiosError(e); }
  }, [applyAxiosError]);
  useEffect(() => { void load(); }, [load]);
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.orgChart')} description={t('suite.org.hint')} {...layoutProps}>
      {nodes.length === 0 ? <PageEmptyState /> : <OrgTree nodes={nodes} onPick={(id) => setSel(String(id))} />}
      <Card><CardContent className="space-y-2 pt-6">
        <Input placeholder={t('suite.org.positionId')} value={sel} onChange={(e) => setSel(e.target.value)} />
        <Input placeholder={t('suite.shifts.employeeId')} value={emp} onChange={(e) => setEmp(e.target.value)} />
        <Input placeholder={t('suite.org.readiness')} value={ready} onChange={(e) => setReady(e.target.value)} />
        <Button onClick={() => void saveSuccessor({ position_id: Number(sel), successor_employee_id: Number(emp), readiness: ready, incumbent_employee_id: Number(emp) }).then(() => { setSuccess(tNav('common.saved')); void load(); }).catch(applyAxiosError)}>{t('suite.org.save')}</Button>
      </CardContent></Card>
    </HrmPageLayout>
  );
}

export function MyOnboardingPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatDigits } = useLocale();
  const [row, setRow] = useState<Row | null>(null);
  const load = useCallback(async () => {
    try {
      const res = await getMyOnboarding() as Row;
      setRow(res);
    } catch (e) { applyAxiosError(e); }
  }, [applyAxiosError]);
  useEffect(() => { void load(); }, [load]);
  const ob = (row?.onboarding ?? null) as Row | null;
  const progress = (row?.progress ?? {}) as Row;
  const tasks = (ob?.tasks ?? []) as Row[];
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.myOnboarding')} {...layoutProps}>
      {!ob ? <PageEmptyState /> : (
        <Card><CardHeader><CardTitle className="text-base">{t('suite.onboarding.progress')} {formatDigits(String(progress.done ?? 0))}/{formatDigits(String(progress.total ?? 0))}</CardTitle></CardHeader>
          <CardContent className="space-y-2 text-sm">
            {tasks.map((task) => (
              <div key={String(task.id)} className="flex items-center justify-between rounded border px-3 py-2">
                <span>{String(task.title)} · <HrmStatus value={task.status} /></span>
                {task.status !== 'done' && !task.document_category ? <Button size="sm" onClick={() => void completeMyOnboardingTask(task.id as number).then(() => { setSuccess(tNav('common.saved')); void load(); }).catch(applyAxiosError)}>{t('suite.lms.complete')}</Button> : null}
                {task.document_category ? <span className="text-muted-foreground">{t('suite.onboarding.uploadHint')} · {String(task.document_category)}</span> : null}
              </div>
            ))}
          </CardContent>
        </Card>
      )}
    </HrmPageLayout>
  );
}

export function MyLearningPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatDigits } = useLocale();
  const [data, setData] = useState<Row | null>(null);
  const load = useCallback(async () => { try { setData(await getMyLearning() as Row); } catch (e) { applyAxiosError(e); } }, [applyAxiosError]);
  useEffect(() => { void load(); }, [load]);
  const lessons = (data?.lessons ?? []) as Row[];
  const certs = (data?.certificates ?? []) as Row[];
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.myLearning')} {...layoutProps}>
      {lessons.length === 0 ? <PageEmptyState /> : lessons.map((l) => (
        <Card key={String(l.id)}><CardContent className="flex items-center justify-between pt-6 text-sm">
          <div><div className="font-medium">{String(l.title)}</div><div className="text-muted-foreground" dir="ltr">{String(l.material_url ?? '')}</div><HrmDigits value={`${l.progress ?? 0}%`} /></div>
          <Button size="sm" disabled={Boolean(l.completed_at)} onClick={() => void completeMyLesson(l.id as number).then(() => { setSuccess(t('suite.lms.certificateReady')); void load(); }).catch(applyAxiosError)}>{t('suite.lms.complete')}</Button>
        </CardContent></Card>
      ))}
      {certs.map((c) => <Card key={String(c.id)}><CardContent className="pt-6 text-sm">{t('suite.lms.certificate')} <HrmDigits value={c.serial_no} /> {c.pdf_url ? <a className="underline" href={String(c.pdf_url)}>{t('suite.lms.pdf')}</a> : <span dangerouslySetInnerHTML={{ __html: '' }} />} <span className="text-muted-foreground">{formatDigits(String(c.issued_at ?? '').slice(0, 10))}</span></CardContent></Card>)}
    </HrmPageLayout>
  );
}

export function MyTimesheetPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatNumber, formatDate } = useLocale();
  const [rows, setRows] = useState<Row[]>([]);
  const [form, setForm] = useState({ work_date: '', hours: '8', project_name: '', task_name: '', project_id: '', description: '' });
  const load = useCallback(async () => { try { setRows(normalizeListPayload(await getMyTimesheets())); } catch (e) { applyAxiosError(e); } }, [applyAxiosError]);
  useEffect(() => { void load(); }, [load]);
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.myTimesheets')} {...layoutProps}>
      <Card><CardContent className="grid gap-2 pt-6 md:grid-cols-2">
        <LocaleDatePicker value={form.work_date} onChange={(v) => setForm({ ...form, work_date: v })} />
        <Input dir="ltr" placeholder={t('suite.time.hours')} value={form.hours} onChange={(e) => setForm({ ...form, hours: e.target.value })} />
        <Input placeholder={t('suite.time.project')} value={form.project_name} onChange={(e) => setForm({ ...form, project_name: e.target.value })} />
        <Input placeholder={t('suite.time.task')} value={form.task_name} onChange={(e) => setForm({ ...form, task_name: e.target.value })} />
        <Input dir="ltr" placeholder={t('suite.time.projectId')} value={form.project_id} onChange={(e) => setForm({ ...form, project_id: e.target.value })} />
        <Textarea placeholder={t('description')} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
        <Button onClick={() => void saveMyTimesheet({ ...form, hours: Number(form.hours), project_id: form.project_id ? Number(form.project_id) : null }).then(() => { setSuccess(tNav('common.saved')); void load(); }).catch(applyAxiosError)}>{tNav('common.save')}</Button>
      </CardContent></Card>
      <Table><TableBody>{rows.map((r) => (
        <TableRow key={String(r.id)}>
          <TableCell>{formatDate(String(r.work_date ?? ''))}</TableCell>
          <TableCell>{String(r.project_name ?? '—')}</TableCell>
          <TableCell>{formatNumber(Number(r.hours ?? 0))}</TableCell>
          <TableCell><HrmStatus value={r.status} /></TableCell>
          <TableCell>{r.status === 'draft' || r.status === 'rejected' ? <Button size="sm" onClick={() => void submitMyTimesheet(r.id as number).then(() => void load()).catch(applyAxiosError)}>{t('suite.time.submit')}</Button> : null}</TableCell>
        </TableRow>
      ))}</TableBody></Table>
    </HrmPageLayout>
  );
}

export function MyOkrPage() {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { layoutProps, setSuccess, applyAxiosError } = useCrmFeedback();
  const { formatNumber } = useLocale();
  const [rows, setRows] = useState<Row[]>([]);
  const [pending, setPending] = useState<Row[]>([]);
  const [score, setScore] = useState('80');
  const load = useCallback(async () => {
    try {
      setRows(normalizeListPayload(await getMyObjectives()));
      const rev = await getMyReviews360() as Row;
      setPending((rev.pending as Row[]) ?? []);
    } catch (e) { applyAxiosError(e); }
  }, [applyAxiosError]);
  useEffect(() => { void load(); }, [load]);
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.myOkrs')} {...layoutProps}>
      {rows.length === 0 ? <PageEmptyState /> : rows.map((r) => (
        <Card key={String(r.id)}><CardHeader><CardTitle className="text-base">{String(r.title)} · {formatNumber(Number(r.progress ?? 0))}٪</CardTitle></CardHeader>
          <CardContent className="space-y-2">{((r.key_results ?? r.keyResults) as Row[] | undefined)?.map((k) => (
            <div key={String(k.id)} className="flex gap-2">
              <span className="flex-1">{String(k.title)}</span>
              <Button size="sm" variant="outline" onClick={() => void updateMyKeyResult(k.id as number, Number(k.current_value ?? 0) + 10).then(() => { setSuccess(tNav('common.saved')); void load(); }).catch(applyAxiosError)}>+۱۰</Button>
            </div>
          ))}</CardContent>
        </Card>
      ))}
      <Card><CardHeader><CardTitle className="text-base">{t('suite.review.pending')}</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          <Input dir="ltr" value={score} onChange={(e) => setScore(e.target.value)} />
          {pending.map((p) => <Button key={String(p.id)} onClick={() => void submitReview360(p.id as number, { score: Number(score), feedback: '' }).then(() => void load()).catch(applyAxiosError)}>{t('suite.review.submit')} #{String(p.id)}</Button>)}
        </CardContent>
      </Card>
    </HrmPageLayout>
  );
}

export function MyShiftCalendarPage() {
  const tNav = useTranslations();
  const t = useTranslations('hrm');
  const { locale, formatDigits, formatDate } = useLocale();
  const { layoutProps, applyAxiosError } = useCrmFeedback();
  const [cursor, setCursor] = useState(() => new Date());
  const [rows, setRows] = useState<Row[]>([]);
  const weeks = useMemo(() => buildMonthGrid(cursor, locale), [cursor, locale]);
  const days = weeks.flat().filter((d) => d.iso);
  useEffect(() => {
    if (!days[0]) return;
    void getMyShiftCalendar({ from: days[0].iso, to: days[days.length - 1].iso }).then((r) => setRows(Array.isArray(r) ? r as Row[] : [])).catch(applyAxiosError);
  }, [applyAxiosError, days[0]?.iso, days[days.length - 1]?.iso]);
  const map = new Map(rows.map((r) => [String(r.work_date).slice(0, 10), r]));
  return (
    <HrmPageLayout title={tNav('nav.erp.hrm.myShifts')} {...layoutProps} actions={<Button variant="outline" onClick={() => setCursor((c) => shiftMonth(c, 1, locale))}>{tNav('date.nextMonth')}</Button>}>
      <div className="grid grid-cols-7 gap-1 text-sm">
        {weeks.flat().map((d, i) => {
          const a = map.get(d.iso);
          return <div key={i} className="min-h-14 rounded border p-1">{d.day ? formatDigits(d.day) : ''} {a ? <div className="text-xs">{a.is_off ? t('suite.shifts.off') : String((a.template as Row | undefined)?.name ?? formatDate(d.iso))}</div> : null}</div>;
        })}
      </div>
    </HrmPageLayout>
  );
}
