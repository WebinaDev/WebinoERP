'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';

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
  services: Array<{ id: string; name: string; billing: string; description: string }>;
  clause: string;
};

type CatalogItem = {
  id: string;
  name: string;
  billing: string;
  period_months: number;
  renewable: boolean;
  category: string;
  description: string;
  default_selected: boolean;
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

export default function RahnPublicPage() {
  const params = useParams();
  const token = String(params?.token ?? '');

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [title, setTitle] = useState('');
  const [locked, setLocked] = useState(false);
  const [catalog, setCatalog] = useState<CatalogItem[]>([]);
  const [selected, setSelected] = useState<string[]>([]);
  const [pub, setPub] = useState<PublicPayload | null>(null);
  const [pMin, setPMin] = useState(0.05);
  const [pMax, setPMax] = useState(0.25);
  const [sHat, setSHat] = useState(0);
  const [pPercent, setPPercent] = useState(10);
  const [mode, setMode] = useState<'from_p' | 'from_f'>('from_p');
  const [fWanted, setFWanted] = useState(0);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [note, setNote] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [siteName, setSiteName] = useState('Webino');

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    try {
      const res = await rahnPublicApi<{
        title: string;
        locked: boolean;
        catalog: CatalogItem[];
        selected_ids: string[];
        public: PublicPayload;
        p_min: number;
        p_max: number;
        s_hat: number;
        site_name?: string;
      }>(token);
      if (!res.ok || !res.data) {
        setError(res.message || 'پیش‌نویس یافت نشد.');
        return;
      }
      setTitle(res.data.title);
      setLocked(!!res.data.locked);
      setCatalog(res.data.catalog || []);
      setSelected(res.data.selected_ids || []);
      setPub(res.data.public);
      setPMin(res.data.p_min);
      setPMax(res.data.p_max);
      setSHat(res.data.s_hat);
      setPPercent(res.data.public.p_percent);
      setFWanted(res.data.public.F);
      if (res.data.site_name) setSiteName(res.data.site_name);
    } catch {
      setError('خطا در دریافت اطلاعات.');
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void load();
  }, [load]);

  const recalc = useCallback(
    async (next: { selected?: string[]; pPercent?: number; fWanted?: number; mode?: 'from_p' | 'from_f' }) => {
      if (locked || !token) return;
      const body = {
        selected_ids: next.selected ?? selected,
        s_hat: sHat,
        mode: next.mode ?? mode,
        p_percent: next.pPercent ?? pPercent,
        F_wanted: next.fWanted ?? fWanted,
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
    [locked, token, selected, sHat, mode, pPercent, fWanted],
  );

  const toggle = (id: string) => {
    if (locked) return;
    const next = selected.includes(id) ? selected.filter((x) => x !== id) : [...selected, id];
    setSelected(next);
    void recalc({ selected: next });
  };

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
        mode,
        p_percent: pPercent,
        F_wanted: fWanted,
      }),
    });
    if (!res.ok) {
      setError(res.message || 'ثبت درخواست ناموفق بود.');
      return;
    }
    setSubmitted(true);
  };

  if (loading) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center text-muted-foreground" dir="rtl">
        در حال بارگذاری…
      </div>
    );
  }

  if (error) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center text-destructive" dir="rtl">
        {error}
      </div>
    );
  }

  const pMinPct = pMin * 100;
  const pMaxPct = pMax * 100;

  return (
    <div className="mx-auto max-w-3xl space-y-8 px-4 py-10" dir="rtl">
      <header className="space-y-2 text-center">
        <p className="text-sm text-muted-foreground">{siteName}</p>
        <h1 className="text-2xl font-semibold tracking-tight">{title || 'پیشنهاد رهن‌درصد'}</h1>
        {locked ? (
          <p className="text-sm text-amber-700">این پیشنهاد قفل شده و فقط قابل مشاهده است.</p>
        ) : null}
      </header>

      <section className="space-y-3 rounded-xl border p-4">
        <h2 className="font-medium">خدمات</h2>
        {catalog.map((item) => (
          <label key={item.id} className="flex items-start gap-3 rounded-lg border p-3">
            <input
              type="checkbox"
              checked={selected.includes(item.id)}
              disabled={locked}
              onChange={() => toggle(item.id)}
              className="mt-1"
            />
            <div>
              <div className="font-medium">{item.name}</div>
              {item.category ? <div className="text-xs text-muted-foreground">{item.category}</div> : null}
            </div>
          </label>
        ))}
      </section>

      {!locked ? (
        <section className="space-y-4 rounded-xl border p-4">
          <div>
            <div className="mb-2 flex justify-between text-sm">
              <span>درصد</span>
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
              <span>ثابت ماهانه</span>
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

      {pub ? (
        <section className="grid gap-3 sm:grid-cols-2">
          <div className="rounded-xl border bg-primary/5 p-4">
            <div className="text-xs text-muted-foreground">ثابت ماهانه</div>
            <div className="text-2xl font-bold">{money(pub.F)}</div>
          </div>
          <div className="rounded-xl border bg-emerald-500/10 p-4">
            <div className="text-xs text-muted-foreground">درصد از فروش</div>
            <div className="text-2xl font-bold">{pub.p_percent.toFixed(1)}%</div>
          </div>
          {pub.clause ? (
            <div className="sm:col-span-2 rounded-xl border border-dashed p-4 text-sm leading-7">{pub.clause}</div>
          ) : null}
        </section>
      ) : null}

      {submitted ? (
        <div className="rounded-xl border bg-emerald-500/10 p-4 text-center">درخواست شما ثبت شد. به‌زودی تماس می‌گیریم.</div>
      ) : (
        <section className="space-y-3 rounded-xl border p-4">
          <h2 className="font-medium">ثبت علاقه‌مندی</h2>
          <input
            className="w-full rounded-md border px-3 py-2"
            placeholder="نام"
            value={name}
            onChange={(e) => setName(e.target.value)}
          />
          <input
            className="w-full rounded-md border px-3 py-2"
            placeholder="تلفن"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
          />
          <input
            className="w-full rounded-md border px-3 py-2"
            placeholder="ایمیل"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          <textarea
            className="w-full rounded-md border px-3 py-2"
            rows={3}
            placeholder="توضیح"
            value={note}
            onChange={(e) => setNote(e.target.value)}
          />
          <button
            type="button"
            className="w-full rounded-md bg-primary px-4 py-2 text-primary-foreground"
            onClick={() => void submit()}
          >
            ارسال درخواست
          </button>
        </section>
      )}
    </div>
  );
}
