'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { ArrowUpLeft, CircleCheck, LoaderCircle } from 'lucide-react';

const API_BASE = process.env.NEXT_PUBLIC_API_URL?.trim() || '/api';

export type LeadField = {
  name: string;
  label?: string;
  type?: string;
  required?: boolean;
  placeholder?: string;
  options?: (string | { value: string; label: string })[];
};

type Copy = {
  submit: string;
  successTitle: string;
  successText: string;
  again: string;
  privacy: string;
};

const toLatin = (s: string) => s.replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));

/** Renders a Marketing form (dashboard → بازاریابی → فرم‌ها) and posts to /v1/public/forms/{slug}. */
export function ContactLeadForm({ slug, fields, copy }: { slug: string; fields: LeadField[]; copy: Copy }) {
  const t = useTranslations('site.contactPage');
  const [values, setValues] = useState<Record<string, string>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [failed, setFailed] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);

  function validate(): Record<string, string> {
    const out: Record<string, string> = {};
    for (const f of fields) {
      const v = (values[f.name] ?? '').trim();
      if (f.required && !v) out[f.name] = t('required');
      else if (v && f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) out[f.name] = t('invalidEmail');
      else if (v && f.type === 'tel' && !/^\+?[0-9\s-]{8,16}$/.test(toLatin(v))) out[f.name] = t('invalidPhone');
    }
    return out;
  }

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const found = validate();
    setErrors(found);
    setFailed(false);
    if (Object.keys(found).length) {
      document.getElementById(`cf-${Object.keys(found)[0]}`)?.focus();
      return;
    }
    setPending(true);
    try {
      const payload: Record<string, string> = {};
      for (const f of fields) {
        const v = (values[f.name] ?? '').trim();
        if (v) payload[f.name] = f.type === 'tel' ? toLatin(v) : v;
      }
      const res = await fetch(`${API_BASE}/v1/public/forms/${encodeURIComponent(slug)}`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ payload, source: 'contact-page' }),
      });
      const body = (await res.json().catch(() => ({}))) as { data?: { message?: string }; errors?: Record<string, string[]> };
      if (res.status === 422 && body.errors) {
        const mapped: Record<string, string> = {};
        for (const [key, msgs] of Object.entries(body.errors)) mapped[key.replace(/^payload\./, '')] = msgs[0] ?? t('required');
        setErrors(mapped);
        return;
      }
      if (!res.ok) throw new Error(String(res.status));
      setSuccess(copy.successText || body.data?.message || '');
      setValues({});
    } catch {
      setFailed(true);
    } finally {
      setPending(false);
    }
  }

  if (success !== null) {
    return (
      <div className="cf-success" role="status">
        <CircleCheck className="size-10" aria-hidden />
        <h3>{copy.successTitle}</h3>
        {success ? <p>{success}</p> : null}
        <button type="button" className="btn-ghost mt-2" onClick={() => setSuccess(null)}>
          {copy.again}
        </button>
      </div>
    );
  }

  return (
    <form className="cf-form" onSubmit={onSubmit} noValidate>
      <div className="cf-grid">
        {fields.map((f) => {
          const id = `cf-${f.name}`;
          const err = errors[f.name];
          const wide = f.type === 'textarea' || f.type === 'select';
          const common = {
            id,
            name: f.name,
            value: values[f.name] ?? '',
            'aria-invalid': Boolean(err),
            'aria-describedby': err ? `${id}-err` : undefined,
            placeholder: f.placeholder,
            onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
              setValues((v) => ({ ...v, [f.name]: e.target.value }));
              if (errors[f.name]) setErrors((x) => ({ ...x, [f.name]: '' }));
            },
          };
          return (
            <label key={f.name} htmlFor={id} className={`cf-field${wide ? ' is-wide' : ''}${err ? ' has-error' : ''}`}>
              <span>
                {f.label || f.name}
                {f.required ? <b aria-hidden> *</b> : null}
              </span>
              {f.type === 'textarea' ? (
                <textarea rows={5} {...common} />
              ) : f.type === 'select' ? (
                <select {...common}>
                  <option value="">—</option>
                  {(f.options ?? []).map((o) => {
                    const opt = typeof o === 'string' ? { value: o, label: o } : o;
                    return (
                      <option key={opt.value} value={opt.value}>
                        {opt.label}
                      </option>
                    );
                  })}
                </select>
              ) : (
                <input
                  type={f.type === 'email' ? 'email' : f.type === 'tel' ? 'tel' : 'text'}
                  dir={f.type === 'email' || f.type === 'tel' ? 'ltr' : undefined}
                  inputMode={f.type === 'tel' ? 'tel' : undefined}
                  autoComplete={f.type === 'email' ? 'email' : f.type === 'tel' ? 'tel' : f.name === 'name' ? 'name' : undefined}
                  {...common}
                />
              )}
              {err ? (
                <em id={`${id}-err`} className="cf-error">
                  {err}
                </em>
              ) : null}
            </label>
          );
        })}
      </div>
      {failed ? (
        <p className="cf-alert" role="alert">
          {t('sendFailed')}
        </p>
      ) : null}
      <div className="cf-actions">
        <button type="submit" className="btn-primary" disabled={pending}>
          {pending ? <LoaderCircle className="size-4 animate-spin" aria-hidden /> : null}
          {pending ? t('sending') : copy.submit}
          {!pending ? <ArrowUpLeft className="size-4 ltr:-scale-x-100" aria-hidden /> : null}
        </button>
        {copy.privacy ? <small>{copy.privacy}</small> : null}
      </div>
    </form>
  );
}
