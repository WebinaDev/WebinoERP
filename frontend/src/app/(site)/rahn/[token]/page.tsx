'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useParams } from 'next/navigation';
import { ArrowLeft, ArrowRight } from 'lucide-react';

type PublicPayload = {
  F: number;
  p: number;
  p_percent: number;
  V_hat: number;
  S_hat: number;
  alpha: number;
  T: number;
  p_min: number;
  p_max: number;
  F_min: number;
  services: Array<{
    id: string;
    name: string;
    billing: string;
    description: string;
    category?: string;
    fee_label?: string;
    amount?: number;
  }>;
  clause: string;
};

type CatalogItem = {
  id: string;
  name: string;
  billing: string;
  period_months: number;
  renewable: boolean;
  amount?: number;
  category: string;
  category_id?: string;
  description: string;
  default_selected: boolean;
  choice_group?: string;
  choice_value?: string;
  fee_label?: string;
  sort_order?: number;
  active?: boolean;
  show_when_item_id?: string;
};

type Topic = { id: string; name: string; description?: string; sort_order: number };
type Domain = { id: string; topic_id: string; name: string; description?: string; p_suggest: number; sort_order: number };
type Category = {
  id: string;
  name: string;
  description?: string;
  sort_order: number;
  topic_id?: string;
  domain_id?: string;
};
type WizardStep = {
  id: string;
  type: string;
  label: string;
  body?: string;
  sort_order: number;
  active?: boolean;
};

function money(n: number) {
  return Math.round(n).toLocaleString('fa-IR');
}

async function rahnPublicApi<T>(path: string, init?: RequestInit): Promise<{ ok: boolean; data?: T; message?: string }> {
  const res = await fetch(`/api/v1/sales/rahn/public/${path.replace(/^\//, '')}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(init?.headers || {}),
    },
  });
  const json = (await res.json()) as { data?: T; message?: string };
  return { ok: res.ok, data: json.data, message: json.message };
}

function toggleSelection(catalog: CatalogItem[], selected: string[], id: string, checked: boolean): string[] {
  const item = catalog.find((c) => c.id === id);
  if (!item) return selected;
  const group = item.choice_group?.trim();
  if (group) {
    const groupIds = new Set(catalog.filter((c) => c.choice_group === group).map((c) => c.id));
    const without = selected.filter((x) => !groupIds.has(x));
    return checked ? [...without, id] : without;
  }
  return checked ? (selected.includes(id) ? selected : [...selected, id]) : selected.filter((x) => x !== id);
}

const DEFAULT_STEPS: WizardStep[] = [
  { id: 'business', type: 'business', label: 'کسب‌وکار', sort_order: 1, active: true },
  { id: 'topic', type: 'topic', label: 'موضوع', sort_order: 2, active: true },
  { id: 'domain', type: 'domain', label: 'حوزه', sort_order: 3, active: true },
  { id: 'services', type: 'services', label: 'خدمات', sort_order: 4, active: true },
  { id: 'deal', type: 'deal', label: 'تعرفه', sort_order: 5, active: true },
  { id: 'contact', type: 'summary', label: 'ثبت درخواست', sort_order: 6, active: true },
];

export default function RahnPublicPage() {
  const params = useParams();
  const token = String(params?.token ?? '');

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [title, setTitle] = useState('');
  const [locked, setLocked] = useState(false);
  const [catalog, setCatalog] = useState<CatalogItem[]>([]);
  const [topics, setTopics] = useState<Topic[]>([]);
  const [domains, setDomains] = useState<Domain[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [wizardSteps, setWizardSteps] = useState<WizardStep[]>(DEFAULT_STEPS);
  const [durationOptions, setDurationOptions] = useState<number[]>([6, 9, 12, 18, 24]);
  const [selected, setSelected] = useState<string[]>([]);
  const [pub, setPub] = useState<PublicPayload | null>(null);
  const [pMin, setPMin] = useState(0.05);
  const [pMax, setPMax] = useState(0.25);
  const [sHat, setSHat] = useState(0);
  const [duration, setDuration] = useState(12);
  const [pPercent, setPPercent] = useState(10);
  const [mode, setMode] = useState<'from_p' | 'from_f'>('from_p');
  const [fWanted, setFWanted] = useState(0);
  const [businessName, setBusinessName] = useState('');
  const [topicId, setTopicId] = useState('');
  const [domainId, setDomainId] = useState('');
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [note, setNote] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [siteName, setSiteName] = useState('Webino');
  const [stepId, setStepId] = useState('business');

  const activeSteps = useMemo(
    () =>
      [...wizardSteps]
        .filter((s) => s.active !== false)
        .sort((a, b) => a.sort_order - b.sort_order)
        .map((s) => ({
          ...s,
          type: s.type === 'summary' ? 'contact' : s.type,
        })),
    [wizardSteps],
  );
  const currentStep = activeSteps.find((s) => s.id === stepId) ?? activeSteps[0];
  const stepType = currentStep?.type ?? 'business';

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    try {
      const res = await rahnPublicApi<{
        title: string;
        locked: boolean;
        catalog: CatalogItem[];
        topics?: Topic[];
        domains?: Domain[];
        categories?: Category[];
        duration_options?: number[];
        wizard_steps?: WizardStep[];
        selected_ids: string[];
        public: PublicPayload;
        p_min: number;
        p_max: number;
        s_hat: number;
        T?: number;
        wizard?: { business_name?: string; topic_id?: string; domain_id?: string };
        site_name?: string;
      }>(token);
      if (!res.ok || !res.data) {
        setError(res.message || 'پیش‌نویس یافت نشد.');
        return;
      }
      setTitle(res.data.title);
      setLocked(!!res.data.locked);
      setCatalog(res.data.catalog || []);
      setTopics(res.data.topics || []);
      setDomains(res.data.domains || []);
      setCategories(res.data.categories || []);
      setWizardSteps(res.data.wizard_steps?.length ? res.data.wizard_steps : DEFAULT_STEPS);
      setDurationOptions(res.data.duration_options?.length ? res.data.duration_options : [6, 9, 12, 18, 24]);
      setSelected(res.data.selected_ids || []);
      setPub(res.data.public);
      setPMin(res.data.p_min);
      setPMax(res.data.p_max);
      setSHat(res.data.s_hat);
      setDuration(res.data.T || res.data.public.T || 12);
      setPPercent(res.data.public.p_percent);
      setFWanted(res.data.public.F);
      if (res.data.wizard?.business_name) setBusinessName(res.data.wizard.business_name);
      if (res.data.wizard?.topic_id) setTopicId(res.data.wizard.topic_id);
      if (res.data.wizard?.domain_id) setDomainId(res.data.wizard.domain_id);
      if (res.data.site_name) setSiteName(res.data.site_name);
      const steps = res.data.wizard_steps?.length ? res.data.wizard_steps : DEFAULT_STEPS;
      const first = [...steps].filter((s) => s.active !== false).sort((a, b) => a.sort_order - b.sort_order)[0];
      if (first) setStepId(first.id);
    } catch {
      setError('خطا در دریافت اطلاعات.');
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void load();
  }, [load]);

  const domainOptions = useMemo(
    () => domains.filter((d) => !topicId || d.topic_id === topicId),
    [domains, topicId],
  );

  const recalc = useCallback(
    async (next: {
      selected?: string[];
      pPercent?: number;
      fWanted?: number;
      mode?: 'from_p' | 'from_f';
      duration?: number;
      sHat?: number;
      topicId?: string;
      domainId?: string;
      businessName?: string;
    }) => {
      if (locked || !token) return;
      const body = {
        selected_ids: next.selected ?? selected,
        s_hat: next.sHat ?? sHat,
        T: next.duration ?? duration,
        mode: next.mode ?? mode,
        p_percent: next.pPercent ?? pPercent,
        F_wanted: next.fWanted ?? fWanted,
        business_name: next.businessName ?? businessName,
        topic_id: next.topicId ?? topicId,
        domain_id: next.domainId ?? domainId,
      };
      const res = await rahnPublicApi<{ public: PublicPayload }>(`${token}/calculate`, {
        method: 'POST',
        body: JSON.stringify(body),
      });
      if (res.ok && res.data?.public) {
        setPub(res.data.public);
        setPPercent(res.data.public.p_percent);
        setFWanted(res.data.public.F);
      }
    },
    [locked, token, selected, sHat, duration, mode, pPercent, fWanted, businessName, topicId, domainId],
  );

  useEffect(() => {
    if (!domainId || locked) return;
    const domain = domains.find((d) => d.id === domainId);
    if (!domain) return;
    const pct = domain.p_suggest * 100;
    setMode('from_p');
    setPPercent(pct);
    void recalc({ pPercent: pct, mode: 'from_p', domainId });
  }, [domainId]); // eslint-disable-line react-hooks/exhaustive-deps

  const submit = async () => {
    const res = await rahnPublicApi<{ message?: string }>(`${token}/submit`, {
      method: 'POST',
      body: JSON.stringify({
        name,
        phone,
        email,
        note,
        selected_ids: selected,
        s_hat: sHat,
        T: duration,
        mode,
        p_percent: pPercent,
        F_wanted: fWanted,
        business_name: businessName,
        topic_id: topicId,
        domain_id: domainId,
      }),
    });
    if (!res.ok) {
      setError(res.message || 'ثبت درخواست ناموفق بود.');
      return;
    }
    setSubmitted(true);
  };

  const stepIndex = activeSteps.findIndex((s) => s.id === stepId);
  const goNext = () => {
    if (stepType === 'business' && !businessName.trim()) {
      setError('نام کسب‌وکار را وارد کنید.');
      return;
    }
    if (stepType === 'topic' && !topicId) {
      setError('موضوع را انتخاب کنید.');
      return;
    }
    if (stepType === 'domain' && !domainId) {
      setError('حوزه کسب‌وکار را انتخاب کنید.');
      return;
    }
    setError(null);
    const next = activeSteps[stepIndex + 1];
    if (next) setStepId(next.id);
  };
  const goPrev = () => {
    const prev = activeSteps[stepIndex - 1];
    if (prev) setStepId(prev.id);
  };

  const visibleCategories = useMemo(() => {
    return categories.filter((c) => {
      if (c.topic_id && topicId && c.topic_id !== topicId) return false;
      if (c.domain_id && domainId && c.domain_id !== domainId) return false;
      return true;
    });
  }, [categories, topicId, domainId]);

  const visibleCatalog = useMemo(() => {
    const allowed = new Set(visibleCategories.map((c) => c.id));
    const hasScoped = categories.some((c) => c.topic_id || c.domain_id);
    return catalog.filter((item) => {
      if (item.show_when_item_id && !selected.includes(item.show_when_item_id)) return false;
      if (item.category_id && hasScoped) {
        const cat = categories.find((c) => c.id === item.category_id);
        if (cat && (cat.topic_id || cat.domain_id) && !allowed.has(cat.id)) return false;
      }
      return true;
    });
  }, [catalog, categories, visibleCategories, selected]);

  const serviceGroups = useMemo(() => {
    const map = new Map<string, CatalogItem[]>();
    for (const item of visibleCatalog) {
      const key = item.category_id || item.category || 'other';
      const list = map.get(key) ?? [];
      list.push(item);
      map.set(key, list);
    }
    const ordered: Array<{ title: string; items: CatalogItem[] }> = [];
    for (const cat of [...visibleCategories].sort((a, b) => a.sort_order - b.sort_order)) {
      const list = map.get(cat.id);
      if (list?.length) {
        ordered.push({ title: cat.name, items: list });
        map.delete(cat.id);
      }
    }
    for (const [key, list] of map) {
      ordered.push({ title: list[0]?.category || key, items: list });
    }
    return ordered;
  }, [visibleCatalog, visibleCategories]);

  const selectedItems = catalog.filter((c) => selected.includes(c.id));
  const pMinPct = pMin * 100;
  const pMaxPct = pMax * 100;

  if (loading) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center text-muted-foreground" dir="rtl">
        در حال بارگذاری…
      </div>
    );
  }

  if (error && !pub) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center text-destructive" dir="rtl">
        {error}
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl space-y-8 px-4 py-10 text-start" dir="rtl">
      <header className="space-y-3 text-center">
        <p className="text-sm text-muted-foreground">{siteName}</p>
        <h1 className="text-3xl font-bold tracking-tight">نرخ‌نامه</h1>
        <p className="text-muted-foreground">{title || 'پیشنهاد تعرفه قرارداد'}</p>
        {locked ? <p className="text-sm text-amber-700">این پیشنهاد قفل شده و فقط قابل مشاهده است.</p> : null}
      </header>

      {!locked ? (
        <ol className="flex flex-wrap justify-center gap-2">
          {activeSteps.map((s, i) => (
            <li
              key={s.id}
              className={`rounded-full border px-3 py-1 text-xs ${
                s.id === stepId ? 'border-primary bg-primary text-primary-foreground' : 'bg-muted/40 text-muted-foreground'
              }`}
            >
              {i + 1}. {s.label}
            </li>
          ))}
        </ol>
      ) : null}

      {!locked && stepType === 'note' ? (
        <section className="space-y-3 rounded-2xl border p-5">
          <h2 className="font-semibold">{currentStep?.label}</h2>
          <p className="whitespace-pre-wrap text-sm leading-7 text-muted-foreground">{currentStep?.body || ''}</p>
        </section>
      ) : null}

      {error ? <p className="text-center text-sm text-destructive">{error}</p> : null}

      {stepType === 'business' || locked ? (
        <section className="space-y-3 rounded-2xl border bg-gradient-to-bl from-primary/10 to-background p-5">
          <h2 className="font-semibold">نام کسب‌وکار</h2>
          <input
            className="w-full rounded-xl border bg-background px-3 py-2 text-start"
            placeholder="مثلاً کافه نور یا فروشگاه آرایشی گل‌رخ"
            value={businessName}
            disabled={locked}
            onChange={(e) => setBusinessName(e.target.value)}
          />
        </section>
      ) : null}

      {!locked && stepType === 'topic' ? (
        <section className="grid gap-3 sm:grid-cols-2">
          {topics.map((topic) => (
            <button
              key={topic.id}
              type="button"
              onClick={() => {
                setTopicId(topic.id);
                if (domainId && !domains.some((d) => d.id === domainId && d.topic_id === topic.id)) setDomainId('');
              }}
              className={`rounded-2xl border p-4 text-start ${
                topicId === topic.id ? 'border-primary bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-muted/30'
              }`}
            >
              <div className="font-medium">{topic.name}</div>
              {topic.description ? <p className="mt-1 text-sm text-muted-foreground">{topic.description}</p> : null}
            </button>
          ))}
        </section>
      ) : null}

      {!locked && stepType === 'domain' ? (
        <section className="grid gap-3 sm:grid-cols-2">
          {domainOptions.map((domain) => (
            <button
              key={domain.id}
              type="button"
              onClick={() => setDomainId(domain.id)}
              className={`rounded-2xl border p-4 text-start ${
                domainId === domain.id ? 'border-primary bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-muted/30'
              }`}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="font-medium">{domain.name}</div>
                <span className="text-xs tabular-nums text-muted-foreground" dir="ltr">
                  {(domain.p_suggest * 100).toFixed(1)}%
                </span>
              </div>
              {domain.description ? <p className="mt-1 text-sm text-muted-foreground">{domain.description}</p> : null}
            </button>
          ))}
        </section>
      ) : null}

      {(!locked && stepType === 'services') || locked ? (
        <section className="space-y-4">
          {serviceGroups.map((group) => (
            <div key={group.title} className="overflow-hidden rounded-2xl border">
              <div className="border-b bg-muted/40 px-4 py-2 text-sm font-medium">{group.title}</div>
              <div className="space-y-2 p-3">
                {group.items.map((item) => (
                  <label key={item.id} className="flex items-start gap-3 rounded-xl border p-3">
                    <input
                      type="checkbox"
                      checked={selected.includes(item.id)}
                      disabled={locked}
                      onChange={(e) => {
                        const next = toggleSelection(catalog, selected, item.id, e.target.checked);
                        setSelected(next);
                        void recalc({ selected: next });
                      }}
                      className="mt-1"
                    />
                    <div className="min-w-0 flex-1">
                      <div className="font-medium">{item.name}</div>
                      {item.description ? <p className="text-sm text-muted-foreground">{item.description}</p> : null}
                      {item.fee_label ? <p className="text-xs text-amber-700">{item.fee_label}</p> : null}
                    </div>
                  </label>
                ))}
              </div>
            </div>
          ))}
        </section>
      ) : null}

      {!locked && stepType === 'deal' ? (
        <section className="space-y-5 rounded-2xl border p-5">
          <div>
            <div className="mb-2 flex justify-between text-sm">
              <span>مدت قرارداد</span>
              <span className="font-semibold">{duration} ماه</span>
            </div>
            <input
              type="range"
              min={0}
              max={durationOptions.length - 1}
              step={1}
              value={Math.max(0, durationOptions.indexOf(duration))}
              onChange={(e) => {
                const d = durationOptions[Number(e.target.value)] ?? duration;
                setDuration(d);
                void recalc({ duration: d });
              }}
              className="w-full accent-primary"
            />
            <div className="mt-1 flex justify-between text-xs text-muted-foreground">
              {durationOptions.map((d) => (
                <span key={d}>{d}</span>
              ))}
            </div>
          </div>
          <div>
            <div className="mb-2 flex justify-between text-sm">
              <span>بودجه فروش ماهانه مبنا</span>
              <span className="font-semibold tabular-nums">{money(sHat)} تومان</span>
            </div>
            <input
              type="number"
              className="w-full rounded-xl border px-3 py-2"
              dir="ltr"
              value={sHat}
              onChange={(e) => {
                const v = Number(e.target.value) || 0;
                setSHat(v);
                void recalc({ sHat: v });
              }}
            />
          </div>
          <div>
            <div className="mb-2 flex justify-between text-sm">
              <span>درصد از فروش</span>
              <span className="font-semibold tabular-nums">{pPercent.toFixed(1)}%</span>
            </div>
            <input
              type="range"
              min={pMinPct}
              max={pMaxPct}
              step={0.1}
              value={Math.min(pMaxPct, Math.max(pMinPct, pPercent))}
              onChange={(e) => {
                const v = Number(e.target.value);
                setMode('from_p');
                setPPercent(v);
                void recalc({ pPercent: v, mode: 'from_p' });
              }}
              className="w-full accent-primary"
            />
          </div>
          <div>
            <div className="mb-2 flex justify-between text-sm">
              <span>ثابت ماهانه (با افزایش ثابت، درصد کمتر می‌شود)</span>
              <span className="font-semibold tabular-nums">{money(fWanted)} تومان</span>
            </div>
            <input
              type="range"
              min={0}
              max={Math.max(1, Math.ceil(((pub?.V_hat ?? fWanted) || 1) * 2))}
              step={100000}
              value={Math.max(0, fWanted)}
              onChange={(e) => {
                const v = Number(e.target.value);
                setMode('from_f');
                setFWanted(v);
                void recalc({ fWanted: v, mode: 'from_f' });
              }}
              className="w-full accent-primary"
            />
          </div>
        </section>
      ) : null}

      {pub && (stepType === 'deal' || stepType === 'contact' || locked) ? (
        <section className="grid gap-3 sm:grid-cols-2">
          <div className="rounded-2xl border bg-primary/5 p-4">
            <div className="text-xs text-muted-foreground">ثابت ماهانه</div>
            <div className="text-2xl font-bold">{money(pub.F)}</div>
          </div>
          <div className="rounded-2xl border bg-emerald-500/10 p-4">
            <div className="text-xs text-muted-foreground">درصد از فروش</div>
            <div className="text-2xl font-bold">{pub.p_percent.toFixed(1)}%</div>
          </div>
          {pub.clause ? (
            <div className="rounded-2xl border border-dashed p-4 text-sm leading-7 sm:col-span-2">{pub.clause}</div>
          ) : null}
        </section>
      ) : null}

      {(stepType === 'contact' || locked) && selectedItems.length ? (
        <section className="space-y-3">
          <h2 className="font-semibold">خدمات انتخابی</h2>
          {serviceGroups.map((group) => {
            const rows = group.items.filter((i) => selected.includes(i.id));
            if (!rows.length) return null;
            return (
              <div key={group.title} className="overflow-hidden rounded-2xl border">
                <div className="border-b bg-muted/40 px-4 py-2 text-sm font-medium">{group.title}</div>
                <ul className="divide-y">
                  {rows.map((row) => (
                    <li key={row.id} className="px-4 py-3 text-sm">
                      <div className="font-medium">{row.name}</div>
                      {row.fee_label ? <div className="text-xs text-muted-foreground">{row.fee_label}</div> : null}
                    </li>
                  ))}
                </ul>
              </div>
            );
          })}
        </section>
      ) : null}

      {!locked && stepType === 'contact' ? (
        submitted ? (
          <div className="rounded-2xl border bg-emerald-500/10 p-4 text-center">
            درخواست شما ثبت شد. به‌زودی تماس می‌گیریم.
          </div>
        ) : (
          <section className="space-y-3 rounded-2xl border p-5">
            <h2 className="font-medium">ثبت علاقه‌مندی</h2>
            <input
              className="w-full rounded-xl border px-3 py-2 text-start"
              placeholder="نام"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
            <input
              className="w-full rounded-xl border px-3 py-2 text-start"
              placeholder="تلفن"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              dir="ltr"
            />
            <input
              className="w-full rounded-xl border px-3 py-2 text-start"
              placeholder="ایمیل"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              dir="ltr"
            />
            <textarea
              className="w-full rounded-xl border px-3 py-2 text-start"
              rows={3}
              placeholder="توضیح"
              value={note}
              onChange={(e) => setNote(e.target.value)}
            />
            <button
              type="button"
              className="w-full rounded-xl bg-primary px-4 py-2.5 text-primary-foreground"
              onClick={() => void submit()}
            >
              ارسال درخواست
            </button>
          </section>
        )
      ) : null}

      {!locked ? (
        <div className="flex flex-wrap items-center justify-between gap-3">
          <button
            type="button"
            className="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm disabled:opacity-40"
            disabled={stepIndex <= 0}
            onClick={goPrev}
          >
            <ArrowRight className="size-4" />
            قبلی
          </button>
          {stepType !== 'contact' ? (
            <button
              type="button"
              className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm text-primary-foreground"
              onClick={goNext}
            >
              بعدی
              <ArrowLeft className="size-4" />
            </button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
