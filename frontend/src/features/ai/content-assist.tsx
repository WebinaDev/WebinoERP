'use client';

/* AI suggest/apply for any text field. Calls POST /v1/ai-content/content-assist (shared AiContent
 * provider config; every call is logged in ai_jobs with cost). The suggestion is shown for review and
 * edit; only «اعمال» copies it into the field — saving/publishing stays with the editor. */

import { useState } from 'react';
import { Sparkles } from 'lucide-react';
import apiClient from '@/lib/api-client';

export type AssistAction =
  | 'generate' | 'rewrite' | 'shorten' | 'expand' | 'tone' | 'translate' | 'seo_title' | 'seo_description'
  | 'slug' | 'excerpt' | 'read_time' | 'alt_text' | 'faq' | 'outline' | 'case_study' | 'page_draft';

export const ACTION_LABELS: Record<AssistAction, string> = {
  generate: 'نوشتن پیش‌نویس', rewrite: 'بازنویسی', shorten: 'کوتاه‌تر', expand: 'مفصل‌تر', tone: 'تغییر لحن',
  translate: 'ترجمه', seo_title: 'عنوان سئو', seo_description: 'توضیح متا', slug: 'نامک', excerpt: 'خلاصه',
  read_time: 'زمان مطالعه', alt_text: 'متن جایگزین تصویر', faq: 'ساخت پرسش‌های متداول', outline: 'سرفصل و خروجی دوره',
  case_study: 'چالش / راهکار / نتیجه', page_draft: 'پیش‌نویس صفحه از بریف',
};

const TONES = [['formal', 'رسمی'], ['friendly', 'صمیمی'], ['persuasive', 'متقاعدکننده'], ['simple', 'ساده'], ['expert', 'تخصصی']] as const;

export type AssistResult = { suggestion: string; structured: Record<string, unknown>; source: string; job_id: number; cost_toman?: number };

export async function contentAssist(body: { action: AssistAction; surface: string; field?: string; text?: string; format?: 'text' | 'html'; tone?: string; target_locale?: 'fa' | 'en'; target_id?: number | null; context?: Record<string, unknown> }): Promise<AssistResult> {
  const res = await apiClient.post('/v1/ai-content/content-assist', body);
  return (res.data?.data ?? res.data) as AssistResult;
}

/** Which actions make sense for a field, from its key and kind. */
export function actionsFor(key: string, kind: 'text' | 'textarea' | 'html' = 'text'): AssistAction[] {
  const k = key.toLowerCase();
  if (k === 'slug') return ['slug'];
  if (k === 'alt' || k.endsWith('_alt') || k.endsWith('alt')) return ['alt_text', 'translate'];
  if (/(url|href|link|icon|color|anchor|formkey|token|^id$|_id$|email|phone)/.test(k)) return [];
  if (/seo_?title|meta_?title/.test(k)) return ['seo_title', 'translate'];
  if (/seo_?desc|meta_?desc/.test(k)) return ['seo_description', 'translate'];
  if (/(excerpt|summary)/.test(k)) return ['excerpt', 'rewrite', 'shorten', 'tone', 'translate', 'seo_description'];
  if (/(challenge|solution|results)/.test(k)) return ['generate', 'case_study', 'rewrite', 'shorten', 'expand', 'tone', 'translate'];
  if (k === 'outcomes') return ['outline', 'rewrite', 'translate'];
  if (k === 'answer') return ['generate', 'rewrite', 'shorten', 'expand', 'tone', 'translate'];
  if (k === 'items' || k === 'faq' || k === 'faqs') return ['faq', 'rewrite', 'translate'];
  const base: AssistAction[] = kind === 'text' ? ['generate', 'rewrite', 'shorten', 'tone', 'translate'] : ['generate', 'rewrite', 'shorten', 'expand', 'tone', 'translate'];
  if (/(^title$|heading|^name$|question)/.test(k)) base.push('seo_title');
  if (k === 'body' || k === 'content') base.push('excerpt', 'seo_description', 'read_time');
  if (k === 'description' || k === 'text') base.push('excerpt', 'seo_description');
  return base;
}

export function AiAssist({ value, onApply, surface, field, kind = 'text', context, targetId, compact }: {
  value: string; onApply: (next: string) => void; surface: string; field: string; kind?: 'text' | 'textarea' | 'html';
  context?: Record<string, unknown>; targetId?: number | null; compact?: boolean;
}) {
  const actions = actionsFor(field, kind);
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [tone, setTone] = useState('formal');
  const [locale, setLocale] = useState<'fa' | 'en'>('en');
  const [result, setResult] = useState<{ action: AssistAction; text: string; note?: string } | null>(null);
  const [error, setError] = useState('');
  if (!actions.length) return null;

  async function run(action: AssistAction) {
    setBusy(true);
    setError('');
    try {
      const r = await contentAssist({ action, surface, field, text: value, format: kind === 'html' ? 'html' : 'text', tone, target_locale: locale, target_id: targetId ?? undefined, context });
      const note = action === 'read_time' ? `حدود ${r.suggestion} دقیقه مطالعه (فقط نمایش؛ خودکار محاسبه می‌شود)` : r.source === 'local' ? 'نتیجه محلی (بدون سرویس هوش مصنوعی)' : undefined;
      setResult({ action, text: r.suggestion, note });
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message;
      setError(msg || 'دریافت پیشنهاد انجام نشد.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <span className="relative inline-block" dir="rtl">
      <button type="button" title="دستیار هوش مصنوعی" aria-label="دستیار هوش مصنوعی" onClick={() => setOpen((o) => !o)}
        className={`inline-flex items-center gap-1 rounded-md border border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100 ${compact ? 'px-1 py-0.5 text-[10px]' : 'px-2 py-0.5 text-xs'}`}>
        <Sparkles className="size-3" aria-hidden />
        {compact ? null : 'AI'}
      </button>
      {open ? (
        <div className="absolute end-0 z-50 mt-1 grid w-80 gap-2 rounded-xl border bg-white p-3 text-xs text-slate-800 shadow-xl">
          {result ? (
            <>
              <p className="font-bold">{ACTION_LABELS[result.action]} — پیشنهاد (قابل ویرایش)</p>
              {result.action === 'read_time' ? <p>{result.note}</p> : (
                <textarea className="min-h-28 w-full rounded-md border p-2" value={result.text} onChange={(e) => setResult({ ...result, text: e.target.value })} dir={result.action === 'translate' && locale === 'en' || result.action === 'slug' ? 'ltr' : 'rtl'} />
              )}
              {result.note && result.action !== 'read_time' ? <p className="text-slate-500">{result.note}</p> : null}
              <div className="flex gap-2">
                {result.action !== 'read_time' ? <button type="button" className="rounded-md bg-violet-600 px-3 py-1 text-white" onClick={() => { onApply(result.text); setResult(null); setOpen(false); }}>اعمال در فیلد</button> : null}
                <button type="button" className="rounded-md border px-3 py-1" onClick={() => setResult(null)}>بازگشت</button>
              </div>
              <p className="text-[10px] text-slate-500">اعمال فقط فیلد را پر می‌کند؛ ذخیره و انتشار با شماست.</p>
            </>
          ) : (
            <>
              <div className="flex flex-wrap gap-1">
                {actions.map((a) => (
                  <button key={a} type="button" disabled={busy} onClick={() => void run(a)} className="rounded-full border px-2 py-0.5 hover:border-violet-500 disabled:opacity-50">{ACTION_LABELS[a]}</button>
                ))}
              </div>
              {actions.includes('tone') ? (
                <label className="flex items-center gap-2">لحن
                  <select className="rounded border px-1" value={tone} onChange={(e) => setTone(e.target.value)}>{TONES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
                </label>
              ) : null}
              {actions.includes('translate') ? (
                <label className="flex items-center gap-2">ترجمه به
                  <select className="rounded border px-1" value={locale} onChange={(e) => setLocale(e.target.value as 'fa' | 'en')}><option value="en">انگلیسی</option><option value="fa">فارسی</option></select>
                </label>
              ) : null}
              {busy ? <p className="text-violet-700">در حال آماده‌سازی پیشنهاد…</p> : null}
              {error ? <p className="text-red-600">{error}</p> : null}
            </>
          )}
        </div>
      ) : null}
    </span>
  );
}
