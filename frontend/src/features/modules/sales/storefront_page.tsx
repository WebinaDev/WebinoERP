'use client';

/* فروشگاه آنلاین: محصولات قابل فروش (کاتالوگ فروش با meta.storefront)، سفارش‌ها، کدهای تخفیف و تنظیمات سبد/تسویه.
 * درگاه‌ها (نقدی و اقساطی) در «یکپارچه‌سازی ← درگاه‌های پرداخت» مدیریت می‌شوند. */

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';

import apiClient from '@/lib/api-client';
import { normalizeListPayload } from '@/lib/list-utils';

type Item = { id: number; name: string; sku?: string | null; price: string | number; status: string; description?: string | null; meta?: Record<string, unknown> | null };
type Order = { id: number; number: string; status: string; total_minor: number; customer_name?: string | null; phone?: string | null; customer_email?: string | null; payment_mode?: string | null; payment_method?: string | null; created_at?: string; items: { name: string; quantity: number; total: number }[]; tracking_code?: string | null };
type Coupon = { id: number; code: string; type: string; value: string | number; min_total: string | number; usage_limit: number | null; used: number; expires_at: string | null; active: boolean };
type Settings = Record<string, string | number | boolean>;

const TABS = [['products', 'محصولات'], ['orders', 'سفارش‌ها'], ['coupons', 'کدهای تخفیف'], ['settings', 'تنظیمات سبد و تسویه']] as const;
const STATUS: Record<string, string> = { pending_payment: 'در انتظار پرداخت', paid: 'پرداخت‌شده', processing: 'در حال پردازش', shipped: 'ارسال‌شده', delivered: 'تحویل‌شده', cancelled: 'لغوشده' };
const KINDS: Record<string, string> = { license: 'لایسنس / افزونه', package: 'پکیج', course: 'دوره آموزشی', product: 'کالای فیزیکی', service: 'خدمت', digital: 'فایل دیجیتال' };
const toman = (rial: number | string) => `${new Intl.NumberFormat('fa-IR').format(Math.round(Number(rial || 0) / 10))} تومان`;
const input = 'h-10 w-full rounded-xl border border-border bg-background px-3 text-sm outline-none focus:border-primary';
const btn = 'inline-flex h-10 items-center justify-center rounded-xl bg-primary px-4 text-sm font-bold text-primary-foreground disabled:opacity-50';
const ghost = 'inline-flex h-9 items-center justify-center rounded-xl border border-border px-3 text-sm';

function errText(e: unknown) {
  const r = (e as { response?: { data?: { message?: string } } })?.response?.data;
  return r?.message || 'خطا در ذخیره';
}

export function StorefrontAdminPage() {
  const [tab, setTab] = useState<(typeof TABS)[number][0]>('products');
  const [stats, setStats] = useState<{ orders: number; pending: number; paid: number; revenue_minor: number } | null>(null);
  useEffect(() => { void apiClient.get('/v1/sales/storefront/stats').then((r) => setStats(r.data)).catch(() => undefined); }, [tab]);
  return (
    <div className="space-y-4 p-4" dir="rtl">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 className="text-xl font-black">فروشگاه آنلاین</h1>
          <p className="text-sm text-muted-foreground">محصولات، دوره‌ها، سفارش‌ها و تنظیمات سبد خرید سایت.</p>
        </div>
        <div className="flex gap-2">
          <Link href="/dashboard/admin/integrations/payments" className={ghost}>درگاه‌های پرداخت (نقدی و اقساطی)</Link>
          <a href="/products" target="_blank" rel="noreferrer" className={ghost}>مشاهده فروشگاه</a>
        </div>
      </div>
      {stats ? (
        <div className="grid gap-3 sm:grid-cols-4">
          {[['کل سفارش‌ها', stats.orders], ['در انتظار پرداخت', stats.pending], ['پرداخت‌شده', stats.paid], ['فروش', toman(stats.revenue_minor)]].map(([l, v]) => (
            <div key={String(l)} className="rounded-2xl border border-border bg-card p-4"><p className="text-xs text-muted-foreground">{l}</p><p className="mt-1 text-lg font-black">{typeof v === 'number' ? new Intl.NumberFormat('fa-IR').format(v) : v}</p></div>
          ))}
        </div>
      ) : null}
      <div className="flex gap-1 overflow-x-auto rounded-2xl bg-muted p-1">
        {TABS.map(([id, label]) => <button key={id} type="button" onClick={() => setTab(id)} className={`shrink-0 rounded-xl px-4 py-2 text-sm font-bold ${tab === id ? 'bg-background text-primary shadow' : 'text-muted-foreground'}`}>{label}</button>)}
      </div>
      {tab === 'products' ? <Products /> : tab === 'orders' ? <Orders /> : tab === 'coupons' ? <Coupons /> : <SettingsForm />}
    </div>
  );
}

/* ---------------- products ---------------- */
function toLocalInput(v: unknown): string {
  if (typeof v !== 'string' || !v) return '';
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return '';
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}
function specsToText(v: unknown): string {
  if (Array.isArray(v)) return v.map((r) => (r && typeof r === 'object' ? `${(r as { name?: string }).name ?? ''}: ${String((r as { value?: unknown }).value ?? '')}` : '')).filter((l) => l.trim() !== ':').join('\n');
  if (v && typeof v === 'object') return Object.entries(v as Record<string, unknown>).map(([k, val]) => `${k}: ${Array.isArray(val) ? val.join('، ') : String(val)}`).join('\n');
  return '';
}
function textToSpecs(t: string): Array<{ name: string; value: string }> {
  return t.split('\n').map((l) => l.trim()).filter(Boolean).map((l) => { const i = l.indexOf(':'); return i > 0 ? { name: l.slice(0, i).trim(), value: l.slice(i + 1).trim() } : { name: l, value: '' }; });
}
const emptyForm = { name: '', slug: '', kind: 'license', price: '', sale_price: '', sale_ends_at: '', specs: '', image_url: '', url: '', category_name: '', course_slug: '', description: '', stock: '', status: 'active', storefront: true };
function Products() {
  const [items, setItems] = useState<Item[] | null>(null);
  const [edit, setEdit] = useState<Item | 'new' | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [msg, setMsg] = useState('');
  const load = useCallback(async () => {
    const r = await apiClient.get('/v1/sales/catalog', { params: { per_page: 100 } });
    setItems((normalizeListPayload(r.data) as Item[]).filter((i) => i.meta && (i.meta as { storefront?: boolean }).storefront !== undefined));
  }, []);
  useEffect(() => { void load(); }, [load]);
  const open = (i: Item | 'new') => {
    setEdit(i); setMsg('');
    if (i === 'new') { setForm(emptyForm); return; }
    const m = (i.meta ?? {}) as Record<string, unknown>;
    setForm({
      name: i.name, slug: String(m.slug ?? i.sku ?? ''), kind: String(m.kind ?? 'product'),
      price: String(Math.round(Number(i.price) / 10)), sale_price: m.sale_price ? String(Math.round(Number(m.sale_price) / 10)) : '',
      sale_ends_at: toLocalInput(m.sale_ends_at), specs: specsToText(m.attributes),
      image_url: String(m.image_url ?? ''), url: String(m.url ?? ''), category_name: String(m.category_name ?? ''), course_slug: String(m.course_slug ?? ''),
      description: i.description ?? '', stock: m.stock === undefined || m.stock === null ? '' : String(m.stock), status: i.status, storefront: m.storefront !== false,
    });
  };
  const save = async () => {
    const prev = edit !== 'new' && edit ? ((edit.meta ?? {}) as Record<string, unknown>) : {};
    const digital = form.kind !== 'product';
    const meta = {
      ...prev, storefront: form.storefront, slug: form.slug.trim(), kind: form.kind, digital, single: digital,
      sale_price: form.sale_price ? Number(form.sale_price) * 10 : null,
      sale_ends_at: form.sale_price && form.sale_ends_at ? new Date(form.sale_ends_at).toISOString() : null,
      attributes: textToSpecs(form.specs), image_url: form.image_url || null, url: form.url || null,
      category_name: form.category_name || null, category_slug: form.category_name ? form.kind : null,
      course_slug: form.kind === 'course' ? form.course_slug || null : null, stock: form.stock === '' ? null : Number(form.stock),
    };
    const body = { name: form.name, sku: form.slug.trim() || null, price: Number(form.price || 0) * 10, description: form.description, status: form.status, type: 'product', meta };
    try {
      if (edit === 'new') await apiClient.post('/v1/sales/catalog', body); else if (edit) await apiClient.put(`/v1/sales/catalog/${edit.id}`, body);
      setEdit(null); void load();
    } catch (e) { setMsg(errText(e)); }
  };
  const f = (k: keyof typeof emptyForm) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => setForm({ ...form, [k]: e.target.value });
  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between"><p className="text-sm text-muted-foreground">قیمت‌ها به تومان. هر محصول با «نامک» به صفحه محصول یا دوره وصل می‌شود (دوره‌ها: course-نامک‌دوره).</p><button type="button" className={btn} onClick={() => open('new')}>افزودن محصول</button></div>
      {edit ? (
        <div className="grid gap-3 rounded-2xl border border-border bg-card p-4 md:grid-cols-3">
          <label className="grid gap-1 text-sm md:col-span-2">نام<input className={input} value={form.name} onChange={f('name')} /></label>
          <label className="grid gap-1 text-sm">نامک (slug)<input className={input} dir="ltr" value={form.slug} onChange={f('slug')} /></label>
          <label className="grid gap-1 text-sm">نوع<select className={input} value={form.kind} onChange={f('kind')}>{Object.entries(KINDS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></label>
          <label className="grid gap-1 text-sm">قیمت (تومان)<input className={input} dir="ltr" inputMode="numeric" value={form.price} onChange={f('price')} /></label>
          <label className="grid gap-1 text-sm">قیمت با تخفیف (اختیاری)<input className={input} dir="ltr" inputMode="numeric" value={form.sale_price} onChange={f('sale_price')} /></label>
          <label className="grid gap-1 text-sm">پایان تخفیف (اختیاری، برای شمارش معکوس)<input type="datetime-local" className={input} dir="ltr" value={form.sale_ends_at} onChange={f('sale_ends_at')} /></label>
          {form.kind === 'course' ? <label className="grid gap-1 text-sm">نامک دوره در آکادمی<input className={input} dir="ltr" value={form.course_slug} onChange={f('course_slug')} /></label> : null}
          <label className="grid gap-1 text-sm">دسته<input className={input} value={form.category_name} onChange={f('category_name')} /></label>
          <label className="grid gap-1 text-sm">آدرس صفحه<input className={input} dir="ltr" placeholder="/products/..." value={form.url} onChange={f('url')} /></label>
          <label className="grid gap-1 text-sm md:col-span-2">تصویر<input className={input} dir="ltr" value={form.image_url} onChange={f('image_url')} /></label>
          <label className="grid gap-1 text-sm">موجودی (خالی = نامحدود)<input className={input} dir="ltr" value={form.stock} onChange={f('stock')} /></label>
          <label className="grid gap-1 text-sm">وضعیت<select className={input} value={form.status} onChange={f('status')}><option value="active">فعال</option><option value="inactive">غیرفعال</option></select></label>
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.storefront} onChange={(e) => setForm({ ...form, storefront: e.target.checked })} />نمایش و فروش در سایت</label>
          <label className="grid gap-1 text-sm md:col-span-3">مشخصات برای جدول مقایسه (هر خط: عنوان: مقدار)<textarea className={`${input} h-24 py-2`} placeholder={'نوع لایسنس: یک دامنه\nپشتیبانی: ۱۲ ماه'} value={form.specs} onChange={f('specs')} /></label>
          <label className="grid gap-1 text-sm md:col-span-3">توضیح کوتاه<textarea className={`${input} h-20 py-2`} value={form.description} onChange={f('description')} /></label>
          {msg ? <p className="text-sm text-destructive md:col-span-3">{msg}</p> : null}
          <div className="flex gap-2 md:col-span-3"><button type="button" className={btn} onClick={() => void save()}>ذخیره</button><button type="button" className={ghost} onClick={() => setEdit(null)}>انصراف</button></div>
        </div>
      ) : null}
      <div className="overflow-x-auto rounded-2xl border border-border">
        <table className="w-full min-w-[720px] text-sm">
          <thead className="bg-muted text-muted-foreground"><tr><th className="p-3 text-start">نام</th><th className="p-3 text-start">نوع</th><th className="p-3 text-start">قیمت</th><th className="p-3 text-start">وضعیت</th><th className="p-3" /></tr></thead>
          <tbody>
            {items === null ? <tr><td colSpan={5} className="p-6 text-center text-muted-foreground">در حال بارگذاری…</td></tr> : items.map((i) => {
              const m = (i.meta ?? {}) as Record<string, unknown>;
              return (
                <tr key={i.id} className="border-t border-border">
                  <td className="p-3"><b>{i.name}</b><div className="text-xs text-muted-foreground" dir="ltr">{String(m.slug ?? i.sku ?? '')}</div></td>
                  <td className="p-3">{KINDS[String(m.kind ?? 'product')] ?? String(m.kind)}</td>
                  <td className="p-3">{m.sale_price ? <><s className="text-xs text-muted-foreground">{toman(i.price)}</s> {toman(Number(m.sale_price))}</> : toman(i.price)}</td>
                  <td className="p-3">{i.status === 'active' && m.storefront ? <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs text-emerald-600">در فروش</span> : <span className="rounded-full bg-muted px-2 py-0.5 text-xs">غیرفعال</span>}</td>
                  <td className="p-3 text-end"><button type="button" className={ghost} onClick={() => open(i)}>ویرایش</button></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

/* ---------------- orders ---------------- */
function Orders() {
  const [rows, setRows] = useState<Order[] | null>(null);
  const [status, setStatus] = useState('');
  const [q, setQ] = useState('');
  const load = useCallback(async () => {
    const r = await apiClient.get('/v1/sales/storefront/orders', { params: { status: status || undefined, q: q || undefined } });
    setRows(normalizeListPayload(r.data) as Order[]);
  }, [status, q]);
  useEffect(() => { void load(); }, [load]);
  const update = async (o: Order, patch: Partial<Order>) => { await apiClient.patch(`/v1/sales/storefront/orders/${o.id}`, patch); void load(); };
  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-2">
        <input className={`${input} max-w-xs`} placeholder="جستجو: شماره، نام، موبایل" value={q} onChange={(e) => setQ(e.target.value)} />
        <select className={`${input} max-w-[200px]`} value={status} onChange={(e) => setStatus(e.target.value)}><option value="">همه وضعیت‌ها</option>{Object.entries(STATUS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>
      </div>
      <div className="grid gap-2">
        {rows === null ? <p className="text-sm text-muted-foreground">در حال بارگذاری…</p> : !rows.length ? <p className="rounded-2xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">سفارشی وجود ندارد.</p> : rows.map((o) => (
          <div key={o.id} className="rounded-2xl border border-border bg-card p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div><b dir="ltr">{o.number}</b> <span className="text-sm text-muted-foreground">· {o.customer_name || 'مشتری'} · <span dir="ltr">{o.phone}</span>{o.customer_email ? <> · <span dir="ltr">{o.customer_email}</span></> : null}</span></div>
              <div className="flex items-center gap-2">
                <b>{toman(o.total_minor)}</b>
                <select className={`${input} h-9 w-44`} value={o.status} onChange={(e) => void update(o, { status: e.target.value })}>{Object.entries(STATUS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>
              </div>
            </div>
            <p className="mt-2 text-sm">{o.items.map((i) => `${i.name} × ${i.quantity}`).join('، ')}</p>
            <p className="mt-1 text-xs text-muted-foreground">{o.payment_mode === 'installment' ? 'اقساطی' : 'نقدی'}{o.payment_method && o.payment_method !== 'online' ? ` · ${o.payment_method}` : ''}{o.created_at ? ` · ${new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(o.created_at))}` : ''}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------------- coupons ---------------- */
function Coupons() {
  const [rows, setRows] = useState<Coupon[]>([]);
  const [form, setForm] = useState({ code: '', type: 'percent', value: '', min_total: '', usage_limit: '', expires_at: '' });
  const [msg, setMsg] = useState('');
  const load = useCallback(async () => { const r = await apiClient.get('/v1/sales/storefront/coupons'); setRows(normalizeListPayload(r.data) as Coupon[]); }, []);
  useEffect(() => { void load(); }, [load]);
  const add = async () => {
    setMsg('');
    try {
      await apiClient.post('/v1/sales/storefront/coupons', {
        code: form.code, type: form.type, value: form.type === 'fixed' ? Number(form.value) * 10 : Number(form.value),
        min_total: Number(form.min_total || 0) * 10, usage_limit: form.usage_limit ? Number(form.usage_limit) : null, expires_at: form.expires_at || null, active: true,
      });
      setForm({ code: '', type: 'percent', value: '', min_total: '', usage_limit: '', expires_at: '' }); void load();
    } catch (e) { setMsg(errText(e)); }
  };
  return (
    <div className="space-y-3">
      <div className="grid gap-2 rounded-2xl border border-border bg-card p-4 md:grid-cols-6">
        <input className={input} placeholder="کد" dir="ltr" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
        <select className={input} value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}><option value="percent">درصدی</option><option value="fixed">مبلغ ثابت (تومان)</option></select>
        <input className={input} placeholder="مقدار" dir="ltr" value={form.value} onChange={(e) => setForm({ ...form, value: e.target.value })} />
        <input className={input} placeholder="حداقل سبد (تومان)" dir="ltr" value={form.min_total} onChange={(e) => setForm({ ...form, min_total: e.target.value })} />
        <input className={input} placeholder="سقف استفاده" dir="ltr" value={form.usage_limit} onChange={(e) => setForm({ ...form, usage_limit: e.target.value })} />
        <button type="button" className={btn} onClick={() => void add()}>افزودن</button>
        {msg ? <p className="text-sm text-destructive md:col-span-6">{msg}</p> : null}
      </div>
      {rows.map((c) => (
        <div key={c.id} className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-border bg-card p-3 text-sm">
          <span><b dir="ltr">{c.code}</b> · {c.type === 'fixed' ? toman(c.value) : `${c.value}٪`} · استفاده {c.used}{c.usage_limit ? ` از ${c.usage_limit}` : ''}</span>
          <div className="flex gap-2">
            <button type="button" className={ghost} onClick={() => void apiClient.put(`/v1/sales/storefront/coupons/${c.id}`, { ...c, active: !c.active }).then(load)}>{c.active ? 'غیرفعال‌سازی' : 'فعال‌سازی'}</button>
            <button type="button" className={`${ghost} text-destructive`} onClick={() => void apiClient.delete(`/v1/sales/storefront/coupons/${c.id}`).then(load)}>حذف</button>
          </div>
        </div>
      ))}
    </div>
  );
}

/* ---------------- settings ---------------- */
function SettingsForm() {
  const [s, setS] = useState<Settings | null>(null);
  const [msg, setMsg] = useState('');
  useEffect(() => { void apiClient.get('/v1/sales/storefront/settings').then((r) => setS(r.data)); }, []);
  if (!s) return <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>;
  const bool = (k: string, label: string) => <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={!!s[k]} onChange={(e) => setS({ ...s, [k]: e.target.checked })} />{label}</label>;
  return (
    <div className="grid max-w-2xl gap-4 rounded-2xl border border-border bg-card p-4">
      <div className="grid gap-3 md:grid-cols-2">
        <label className="grid gap-1 text-sm">هزینه ارسال کالای فیزیکی (تومان)<input className={input} dir="ltr" value={Math.round(Number(s.shipping_flat_minor) / 10)} onChange={(e) => setS({ ...s, shipping_flat_minor: Number(e.target.value.replace(/\D/g, '')) * 10 })} /></label>
        <label className="grid gap-1 text-sm">ارسال رایگان برای سبد بالای (تومان، ۰ = خاموش)<input className={input} dir="ltr" value={Math.round(Number(s.free_shipping_over_minor) / 10)} onChange={(e) => setS({ ...s, free_shipping_over_minor: Number(e.target.value.replace(/\D/g, '')) * 10 })} /></label>
      </div>
      {bool('cash_enabled', 'پرداخت نقدی در تسویه حساب')}
      {bool('installment_enabled', 'پرداخت اقساطی در تسویه حساب')}
      {bool('require_login', 'خرید فقط برای کاربران واردشده')}
      {bool('require_email', 'ایمیل در تسویه حساب الزامی باشد')}
      {bool('require_address', 'نشانی برای کالای فیزیکی الزامی باشد')}
      <label className="grid gap-1 text-sm">متن کنار دکمه پرداخت<textarea className={`${input} h-20 py-2`} value={String(s.checkout_note ?? '')} onChange={(e) => setS({ ...s, checkout_note: e.target.value })} /></label>
      <p className="text-xs text-muted-foreground">کالاهای دیجیتال (لایسنس، پکیج، دوره) هزینه ارسال ندارند. درگاه‌ها و کارمزدشان را در «درگاه‌های پرداخت» تنظیم کنید.</p>
      {msg ? <p className="text-sm text-emerald-600">{msg}</p> : null}
      <button type="button" className={`${btn} w-fit`} onClick={() => void apiClient.put('/v1/sales/storefront/settings', { settings: s }).then(() => setMsg('ذخیره شد.'))}>ذخیره تنظیمات</button>
    </div>
  );
}
