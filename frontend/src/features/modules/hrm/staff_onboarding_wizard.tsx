'use client';

import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LocaleDatePicker } from '@/components/ui/locale-date-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { WizardStepper } from '@/features/shared/pm/WizardStepper';
import { useCrmFeedback } from '@/features/shared/hooks/useCrmFeedback';
import { useLocale } from '@/hooks/use-locale-next';
import {
  getEmployeeSalaries,
  getPayrollComponents,
  getStaffProfile,
  saveStaff,
  saveStaffDocument,
  updateEmployee,
} from '@/lib/api/hrm';
import { normalizeListPayload } from '@/lib/list-utils';
import { isValidIranianNationalId, normalizeIranianNationalId } from '@/features/modules/hrm/iranian_national_id';

const STATUSES = ['active', 'inactive', 'terminated', 'resigned'] as const;
const CONTRACT_TYPES = ['permanent', 'fixed_term', 'temporary', 'hourly', 'project', 'internship', 'consulting'] as const;
const CONTRACT_STATUSES = ['draft', 'active', 'suspended', 'expired', 'terminated', 'renewed'] as const;
const ENGAGEMENTS = ['full_time', 'part_time', 'contractor', 'freelance', 'project', 'remote'] as const;
const PAY_BASES = ['monthly', 'daily', 'hourly', 'project_fee'] as const;
const DOC_CATEGORIES = ['national_card', 'contract', 'decree', 'certificate', 'other'] as const;

type CompLine = {
  code: string;
  name: string;
  type: string;
  calculation: string;
  amount: string;
  enabled: boolean;
};

type FormState = {
  employee_code: string;
  first_name: string;
  last_name: string;
  email: string;
  mobile: string;
  status: string;
  gender: string;
  birth_date: string;
  address: string;
  notes: string;
  org_position_id: string;
  hire_date: string;
  contract_start_date: string;
  contract_end_date: string;
  contract_type: string;
  contract_status: string;
  engagement_type: string;
  pay_basis: string;
  base_salary: string;
  daily_rate: string;
  hourly_rate: string;
  project_fee: string;
  insurance_applicable: boolean;
  tax_applicable: boolean;
  issue_decree: boolean;
  doc_title: string;
  doc_category: string;
  doc_file: File | null;
};

const emptyForm = (): FormState => ({
  employee_code: '',
  first_name: '',
  last_name: '',
  email: '',
  mobile: '',
  status: 'active',
  gender: '',
  birth_date: '',
  address: '',
  notes: '',
  org_position_id: '',
  hire_date: '',
  contract_start_date: '',
  contract_end_date: '',
  contract_type: 'permanent',
  contract_status: 'active',
  engagement_type: 'full_time',
  pay_basis: 'monthly',
  base_salary: '',
  daily_rate: '',
  hourly_rate: '',
  project_fee: '',
  insurance_applicable: true,
  tax_applicable: true,
  issue_decree: true,
  doc_title: '',
  doc_category: 'contract',
  doc_file: null,
});

function numOrNull(value: string): number | null {
  if (value.trim() === '') return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

export function StaffOnboardingWizard({
  open,
  editing,
  orgRows,
  onClose,
  onSaved,
}: {
  open: boolean;
  editing: Record<string, unknown> | null;
  orgRows: Record<string, unknown>[];
  onClose: () => void;
  onSaved: () => void;
}) {
  const t = useTranslations('hrm');
  const tNav = useTranslations();
  const { isRtl } = useLocale();
  const { setSuccess, applyAxiosError } = useCrmFeedback();
  const [step, setStep] = useState(1);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [lines, setLines] = useState<CompLine[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const positions = useMemo(
    () => orgRows.filter((r) => r.is_active !== false && r.is_active !== 0),
    [orgRows],
  );
  const departments = useMemo(() => {
    const set = new Set<string>();
    positions.forEach((r) => set.add(String(r.department ?? '')));
    return Array.from(set);
  }, [positions]);
  const selected = positions.find((r) => String(r.id) === form.org_position_id);
  const deptValue = selected ? String(selected.department ?? '') : '';
  const deptPositions = positions.filter((r) => String(r.department ?? '') === deptValue);

  useEffect(() => {
    if (!open) return;
    setStep(1);
    setError(null);
    const base = emptyForm();
    if (editing) {
      const match = positions.find(
        (r) => String(r.title ?? '') === String(editing.position ?? '') && String(r.department ?? '') === String(editing.department ?? ''),
      );
      setForm({
        ...base,
        employee_code: String(editing.employee_code ?? ''),
        first_name: String(editing.first_name ?? ''),
        last_name: String(editing.last_name ?? ''),
        email: String(editing.email ?? ''),
        mobile: String(editing.mobile ?? ''),
        status: String(editing.status ?? 'active'),
        notes: String(editing.notes ?? ''),
        org_position_id: match ? String(match.id) : '',
        hire_date: String(editing.hire_date ?? '').slice(0, 10),
        contract_start_date: String(editing.contract_start_date ?? editing.hire_date ?? '').slice(0, 10),
        contract_end_date: String(editing.contract_end_date ?? '').slice(0, 10),
        contract_type: String(editing.contract_type || 'permanent'),
        contract_status: String(editing.contract_status || 'active'),
        engagement_type: String(editing.engagement_type || 'full_time'),
        pay_basis: String(editing.pay_basis || 'monthly'),
        base_salary: editing.base_salary != null ? String(editing.base_salary) : '',
        daily_rate: editing.daily_rate != null ? String(editing.daily_rate) : '',
        hourly_rate: editing.hourly_rate != null ? String(editing.hourly_rate) : '',
        project_fee: editing.project_fee != null ? String(editing.project_fee) : '',
        insurance_applicable: editing.insurance_applicable !== false && editing.insurance_applicable !== 0,
        tax_applicable: editing.tax_applicable !== false && editing.tax_applicable !== 0,
        issue_decree: false,
      });
      void getStaffProfile(Number(editing.id))
        .then((profile) => {
          const row = (profile ?? {}) as Record<string, unknown>;
          const data = (row.data as Record<string, unknown> | undefined) ?? row;
          setForm((f) => ({
            ...f,
            employee_code: String(data.national_id || f.employee_code),
            gender: String(data.gender ?? ''),
            birth_date: String(data.birth_date ?? '').slice(0, 10),
            address: String(data.address ?? ''),
          }));
        })
        .catch(() => undefined);
    } else {
      setForm(base);
    }

    void (async () => {
      try {
        const compRes = await getPayrollComponents({ per_page: 100 });
        const comps = normalizeListPayload(compRes);
        let saved: Array<Record<string, unknown>> = [];
        if (editing?.id) {
          const sal = await getEmployeeSalaries({ employee_id: editing.id, per_page: 1 });
          const rows = normalizeListPayload(sal);
          const raw = rows[0]?.components;
          if (Array.isArray(raw)) saved = raw as Array<Record<string, unknown>>;
        }
        setLines(
          comps
            .filter((c) => c.is_active !== false)
            .map((c) => {
              const code = String(c.code ?? c.id ?? '');
              const prev = saved.find((s) => String(s.code ?? '') === code);
              return {
                code,
                name: String(c.name ?? code),
                type: String(c.type ?? 'earning'),
                calculation: String(c.calculation ?? 'fixed'),
                amount: prev?.amount != null ? String(prev.amount) : String(c.default_amount ?? '0'),
                enabled: prev ? prev.enabled !== false : true,
              };
            }),
        );
      } catch {
        setLines([]);
      }
    })();
  }, [open, editing, positions]);

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => setForm((f) => ({ ...f, [key]: value }));

  const steps = [
    { id: 1, label: t('wizard.stepIdentity') },
    { id: 2, label: t('wizard.stepOrg') },
    { id: 3, label: t('wizard.stepContract') },
    { id: 4, label: t('wizard.stepCompensation') },
    { id: 5, label: t('wizard.stepDocuments') },
  ];

  const primaryKey = form.pay_basis === 'daily' ? 'daily_rate' : form.pay_basis === 'hourly' ? 'hourly_rate' : form.pay_basis === 'project_fee' ? 'project_fee' : 'base_salary';
  const primaryLabel = form.pay_basis === 'daily' ? t('wizard.dailyRate') : form.pay_basis === 'hourly' ? t('wizard.hourlyRate') : form.pay_basis === 'project_fee' ? t('wizard.projectFee') : t('wizard.baseSalary');

  const validateStep = (current: number): boolean => {
    if (current === 1) {
      if (!form.first_name.trim() || !form.last_name.trim()) {
        setError(t('wizard.required'));
        return false;
      }
      if (!isValidIranianNationalId(form.employee_code)) {
        setError(t('wizard.nationalIdInvalid'));
        return false;
      }
    }
    if (current === 2 && !form.org_position_id) {
      setError(positions.length === 0 ? t('wizard.noPositions') : t('wizard.required'));
      return false;
    }
    if (current === 3) {
      if (form.contract_start_date && form.contract_end_date && form.contract_end_date < form.contract_start_date) {
        setError(t('wizard.dateOrder'));
        return false;
      }
    }
    if (current === 4 && numOrNull(form[primaryKey]) == null) {
      setError(t('wizard.required'));
      return false;
    }
    if (current === 5 && form.doc_file && !form.doc_title.trim()) {
      setError(t('wizard.required'));
      return false;
    }
    setError(null);
    return true;
  };

  const submit = async () => {
    if (!validateStep(5) || !validateStep(4) || !validateStep(2) || !validateStep(1)) {
      setStep(!isValidIranianNationalId(form.employee_code) || !form.first_name.trim() ? 1 : !form.org_position_id ? 2 : numOrNull(form[primaryKey]) == null ? 4 : 5);
      return;
    }
    setSubmitting(true);
    try {
      const payload: Record<string, unknown> = {
        onboarding: true,
        employee_code: normalizeIranianNationalId(form.employee_code),
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        email: form.email.trim() || null,
        mobile: form.mobile.trim() || null,
        status: form.status,
        gender: form.gender || null,
        birth_date: form.birth_date || null,
        address: form.address.trim() || null,
        notes: form.notes.trim() || null,
        org_position_id: Number(form.org_position_id),
        hire_date: form.hire_date || null,
        contract_start_date: form.contract_start_date || form.hire_date || null,
        contract_end_date: form.contract_end_date || null,
        contract_type: form.contract_type,
        contract_status: form.contract_status,
        engagement_type: form.engagement_type,
        pay_basis: form.pay_basis,
        base_salary: numOrNull(form.base_salary),
        daily_rate: numOrNull(form.daily_rate),
        hourly_rate: numOrNull(form.hourly_rate),
        project_fee: numOrNull(form.project_fee),
        insurance_applicable: form.insurance_applicable,
        tax_applicable: form.tax_applicable,
        issue_decree: form.issue_decree,
        salary_components: lines.map((line) => ({
          code: line.code,
          name: line.name,
          type: line.type,
          calculation: line.calculation,
          amount: Number(line.amount || 0),
          enabled: line.enabled,
        })),
      };
      const saved = editing
        ? await updateEmployee(Number(editing.id), payload)
        : await saveStaff(payload);
      const row = (saved ?? {}) as Record<string, unknown>;
      const nested = row.data as Record<string, unknown> | undefined;
      const id = Number(editing?.id ?? row.id ?? nested?.id);
      if (form.doc_file && form.doc_title.trim() && id) {
        const body = new FormData();
        body.append('title', form.doc_title.trim());
        body.append('category', form.doc_category);
        body.append('file', form.doc_file);
        await saveStaffDocument(id, body);
      }
      setSuccess(tNav('common.saved'));
      onSaved();
      onClose();
    } catch (err) {
      applyAxiosError(err);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Sheet open={open} onOpenChange={(next) => { if (!next) onClose(); }}>
      <SheetContent side="left" className="flex h-full w-full flex-col overflow-y-auto sm:max-w-3xl">
        <SheetHeader>
          <SheetTitle>{editing ? t('wizard.editTitle') : t('wizard.title')}</SheetTitle>
        </SheetHeader>
        <WizardStepper steps={steps} current={step} isRtl={isRtl} />
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <div className="grid flex-1 gap-3 py-2 sm:grid-cols-2">
          {step === 1 ? (
            <>
              <Field label={t('wizard.nationalIdLabel')} hint={t('wizard.nationalIdHint')}>
                <Input dir="ltr" inputMode="numeric" value={form.employee_code} onChange={(e) => set('employee_code', e.target.value)} />
              </Field>
              <Field label={t('wizard.status')}>
                <EnumSelect value={form.status} onChange={(v) => set('status', v)} options={STATUSES} label={(v) => t(`wizard.employeeStatuses.${v}`)} />
              </Field>
              <Field label={t('firstName')}><Input value={form.first_name} onChange={(e) => set('first_name', e.target.value)} /></Field>
              <Field label={t('lastName')}><Input value={form.last_name} onChange={(e) => set('last_name', e.target.value)} /></Field>
              <Field label={t('email')}><Input dir="ltr" type="email" value={form.email} onChange={(e) => set('email', e.target.value)} /></Field>
              <Field label={t('mobile')}><Input dir="ltr" value={form.mobile} onChange={(e) => set('mobile', e.target.value)} /></Field>
              <Field label={t('wizard.gender')}>
                <EnumSelect value={form.gender || 'unspecified'} onChange={(v) => set('gender', v === 'unspecified' ? '' : v)} options={['unspecified', 'female', 'male', 'other']} label={(v) => t(`wizard.genders.${v}`)} />
              </Field>
              <Field label={t('birthDate')}><LocaleDatePicker value={form.birth_date} onChange={(v) => set('birth_date', v)} /></Field>
              <div className="sm:col-span-2">
                <Field label={t('address')}><Textarea value={form.address} onChange={(e) => set('address', e.target.value)} /></Field>
              </div>
            </>
          ) : null}
          {step === 2 ? (
            <div className="space-y-3 sm:col-span-2">
              {positions.length === 0 ? <p className="text-sm text-muted-foreground">{t('wizard.noPositions')}</p> : null}
              <Field label={t('wizard.department')}>
                <EnumSelect
                  value={deptValue === '' ? '__none__' : deptValue}
                  onChange={(v) => {
                    const dept = v === '__none__' ? '' : v;
                    const next = positions.find((r) => String(r.department ?? '') === dept);
                    set('org_position_id', next ? String(next.id) : '');
                  }}
                  options={departments.map((d) => d || '__none__')}
                  label={(v) => (v === '__none__' ? t('wizard.noDepartment') : v)}
                  placeholder={t('wizard.selectDepartment')}
                />
              </Field>
              <Field label={t('wizard.position')}>
                <Select value={form.org_position_id || undefined} onValueChange={(v) => set('org_position_id', v)}>
                  <SelectTrigger><SelectValue placeholder={t('wizard.selectPosition')} /></SelectTrigger>
                  <SelectContent>
                    {deptPositions.map((r) => (
                      <SelectItem key={String(r.id)} value={String(r.id)}>{String(r.title ?? '')}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </Field>
            </div>
          ) : null}
          {step === 3 ? (
            <>
              <Field label={t('wizard.hireDateLabel')}><LocaleDatePicker value={form.hire_date} onChange={(v) => set('hire_date', v)} /></Field>
              <Field label={t('wizard.contractStartLabel')}><LocaleDatePicker value={form.contract_start_date} onChange={(v) => set('contract_start_date', v)} /></Field>
              <Field label={t('wizard.contractEndLabel')}><LocaleDatePicker value={form.contract_end_date} onChange={(v) => set('contract_end_date', v)} /></Field>
              <Field label={t('wizard.contractType')}>
                <EnumSelect value={form.contract_type} onChange={(v) => set('contract_type', v)} options={CONTRACT_TYPES} label={(v) => t(`wizard.contractTypes.${v}`)} />
              </Field>
              <Field label={t('wizard.contractStatus')}>
                <EnumSelect value={form.contract_status} onChange={(v) => set('contract_status', v)} options={CONTRACT_STATUSES} label={(v) => t(`wizard.contractStatuses.${v}`)} />
              </Field>
              <Field label={t('wizard.engagementType')}>
                <EnumSelect value={form.engagement_type} onChange={(v) => set('engagement_type', v)} options={ENGAGEMENTS} label={(v) => t(`engagement.types.${v}`)} />
              </Field>
              <div className="flex items-center justify-between rounded border px-3 py-2">
                <Label>{t('wizard.insurance')}</Label>
                <Switch checked={form.insurance_applicable} onCheckedChange={(v) => set('insurance_applicable', v)} />
              </div>
              <div className="flex items-center justify-between rounded border px-3 py-2">
                <Label>{t('wizard.tax')}</Label>
                <Switch checked={form.tax_applicable} onCheckedChange={(v) => set('tax_applicable', v)} />
              </div>
              <div className="sm:col-span-2">
                <Field label={t('notes')}><Textarea value={form.notes} onChange={(e) => set('notes', e.target.value)} /></Field>
              </div>
            </>
          ) : null}
          {step === 4 ? (
            <div className="space-y-3 sm:col-span-2">
              <Field label={t('wizard.payBasis')}>
                <EnumSelect value={form.pay_basis} onChange={(v) => set('pay_basis', v)} options={PAY_BASES} label={(v) => t(`engagement.bases.${v}`)} />
              </Field>
              <Field label={primaryLabel}>
                <Input dir="ltr" type="number" min={0} value={form[primaryKey]} onChange={(e) => set(primaryKey, e.target.value)} />
              </Field>
              <p className="text-sm text-muted-foreground">{t('wizard.componentsHint')}</p>
              {lines.length === 0 ? <p className="text-sm text-muted-foreground">{t('wizard.noComponents')}</p> : null}
              {lines.map((line, index) => (
                <div key={line.code || String(index)} className="grid items-center gap-2 rounded border p-2 sm:grid-cols-[auto_1fr_8rem]">
                  <Checkbox
                    checked={line.enabled}
                    onCheckedChange={(v) => setLines((rows) => rows.map((row, i) => i === index ? { ...row, enabled: v === true } : row))}
                    aria-label={t('wizard.includeComponent')}
                  />
                  <div className="text-sm">
                    <div>{line.name}</div>
                    <div className="text-xs text-muted-foreground">{t.has(`wizard.calc.${line.calculation}`) ? t(`wizard.calc.${line.calculation}`) : line.calculation} · {line.type === 'deduction' ? t('deduction') : t('earning')}</div>
                  </div>
                  <Input dir="ltr" type="number" min={0} value={line.amount} onChange={(e) => setLines((rows) => rows.map((row, i) => i === index ? { ...row, amount: e.target.value } : row))} aria-label={t('wizard.componentAmount')} />
                </div>
              ))}
              <div className="flex items-center justify-between rounded border px-3 py-2">
                <div>
                  <Label>{t('wizard.issueDecree')}</Label>
                  <p className="text-xs text-muted-foreground">{t('wizard.decreeHint')}</p>
                </div>
                <Switch checked={form.issue_decree} onCheckedChange={(v) => set('issue_decree', v)} />
              </div>
            </div>
          ) : null}
          {step === 5 ? (
            <div className="space-y-3 sm:col-span-2">
              <p className="text-sm text-muted-foreground">{t('wizard.documentOptional')}</p>
              <Field label={t('wizard.documentTitle')}><Input value={form.doc_title} onChange={(e) => set('doc_title', e.target.value)} /></Field>
              <Field label={t('wizard.documentCategory')}>
                <EnumSelect value={form.doc_category} onChange={(v) => set('doc_category', v)} options={DOC_CATEGORIES} label={(v) => t(`wizard.docCategories.${v}`)} />
              </Field>
              <Field label={t('wizard.documentFile')}>
                <Input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => set('doc_file', e.target.files?.[0] ?? null)} />
              </Field>
            </div>
          ) : null}
        </div>
        <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
          <Button variant="outline" onClick={() => (step === 1 ? onClose() : setStep((s) => s - 1))}>
            {step === 1 ? tNav('common.cancel') : tNav('common.back')}
          </Button>
          {step < 5 ? (
            <Button onClick={() => { if (validateStep(step)) setStep((s) => s + 1); }}>{tNav('common.next')}</Button>
          ) : (
            <Button onClick={() => void submit()} disabled={submitting}>{editing ? tNav('common.save') : t('wizard.finish')}</Button>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}

function Field({ label, hint, children }: { label: string; hint?: string; children: ReactNode }) {
  return (
    <div className="space-y-1">
      <Label>{label}</Label>
      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
      {children}
    </div>
  );
}

function EnumSelect({
  value,
  onChange,
  options,
  label,
  placeholder,
}: {
  value?: string;
  onChange: (value: string) => void;
  options: readonly string[];
  label: (value: string) => string;
  placeholder?: string;
}) {
  return (
    <Select value={value || undefined} onValueChange={onChange}>
      <SelectTrigger><SelectValue placeholder={placeholder} /></SelectTrigger>
      <SelectContent>
        {options.map((option) => (
          <SelectItem key={option || 'empty'} value={option || '__empty__'}>{label(option)}</SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}
