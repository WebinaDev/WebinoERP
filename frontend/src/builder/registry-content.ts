/* Widget definitions shared with WebinoDashboard, plus ERP form wiring.
 * CONTENT_WIDGETS: landing-page widgets (section heading, steps, icon cards, badges, CTA, comparison, rahn). */

import { uid } from "./ids"
import { sharedContentWidgets, sharedFormWidget } from "@webina/ui/builder"
import { ICON_OPTIONS } from "./render/icon-options"
import { DETAIL_ICON_OPTIONS } from "./render/portfolio-detail-icons"
import type { WidgetDef, WidgetNode } from "./types"

function node(type: string, props: Record<string, unknown>): WidgetNode {
  return { id: uid("w"), type, props }
}

/** Shared form; ERP adapter: formKey = Marketing form slug (bazaryabi ← forms). */
export const FORM_WIDGET: WidgetDef = sharedFormWidget({ formKeyLabel: "نامک فرم (بازاریابی ← فرم‌ها)", fieldsLabel: "فیلدها (خالی = فیلدهای خود فرم در بازاریابی ← فرم‌ها)" })

/** Shared landing widgets + ERP-only rahn-estimate. */
export const CONTENT_WIDGETS: WidgetDef[] = [
  ...sharedContentWidgets(ICON_OPTIONS),
  {
    type: "rahn-estimate",
    label: "برآورد قیمت (رهن درصدی)",
    category: "content",
    fields: [
      { kind: "text", key: "title", label: "عنوان (اختیاری)" },
      { kind: "text", key: "token", label: "توکن اشتراک (اختیاری؛ خالی = برآورد عمومی سایت)" },
    ],
    create: () => node("rahn-estimate", { title: "", token: "" }),
  },
  {
    type: "portfolio-grid",
    label: "نمونه‌کارها (گرید با فیلتر دسته)",
    category: "content",
    fields: [
      { kind: "text", key: "allLabel", label: "برچسب «همه»" },
      { kind: "switch", key: "showSort", label: "مرتب‌سازی (جدیدترین / شاخص‌ترین)" },
      { kind: "text", key: "category", label: "فقط این دسته (نامک، اختیاری)" },
      { kind: "number", key: "limit", label: "حداکثر تعداد", min: 1, max: 50 },
      { kind: "text", key: "detailLabel", label: "متن دکمه کارت" },
      { kind: "text", key: "emptyTitle", label: "عنوان حالت خالی" },
      { kind: "textarea", key: "emptyText", label: "متن حالت خالی" },
      { kind: "text", key: "emptyPrimaryLabel", label: "دکمه اول حالت خالی" },
      { kind: "link", key: "emptyPrimaryHref", label: "پیوند دکمه اول" },
      { kind: "text", key: "emptySecondaryLabel", label: "دکمه دوم حالت خالی" },
      { kind: "link", key: "emptySecondaryHref", label: "پیوند دکمه دوم" },
    ],
    create: () => node("portfolio-grid", { allLabel: "همه", showSort: true, limit: 50, detailLabel: "مشاهده جزئیات", emptyTitle: "به‌زودی", emptyText: "", emptyPrimaryLabel: "", emptyPrimaryHref: "", emptySecondaryLabel: "", emptySecondaryHref: "" }),
  },
  {
    type: "portfolio-gallery",
    label: "گالری نمونه‌کار (تصویر اصلی + بندانگشتی + بزرگ‌نمایی)",
    category: "content",
    fields: [
      { kind: "textarea", key: "images", label: "آدرس تصویر|متن جایگزین در هر خط" },
      { kind: "text", key: "alt", label: "متن جایگزین پیش‌فرض" },
    ],
    create: () => node("portfolio-gallery", { images: "", alt: "" }),
  },
  {
    type: "info-table",
    label: "جدول اطلاعات آیکن‌دار",
    category: "content",
    fields: [
      { kind: "text", key: "title", label: "عنوان" },
      { kind: "textarea", key: "rows", label: "در هر خط: آیکن|عنوان|مقدار|پیوند (مقدار با chips: و جداکننده ، به‌صورت برچسب)؛ آیکن‌ها: client globe layers briefcase tag trend check target idea trophy calendar users wrench palette rocket badge" },
      { kind: "text", key: "buttonLabel", label: "متن دکمه" },
      { kind: "link", key: "buttonHref", label: "پیوند دکمه" },
    ],
    create: () => node("info-table", { title: "اطلاعات پروژه", rows: "client|کارفرما|\nglobe|وب‌سایت||", buttonLabel: "", buttonHref: "" }),
  },
  {
    type: "story-card",
    label: "کارت روایت (چالش / راه‌حل / نتیجه)",
    category: "content",
    fields: [
      { kind: "select", key: "icon", label: "آیکن", options: DETAIL_ICON_OPTIONS },
      { kind: "text", key: "title", label: "عنوان" },
      { kind: "textarea", key: "text", label: "متن (هر خط یک پاراگراف یا آیتم)" },
      { kind: "select", key: "tone", label: "رنگ آیکن", options: [{ value: "blue", label: "آبی" }, { value: "orange", label: "نارنجی" }, { value: "green", label: "سبز" }, { value: "dark", label: "تیره" }] },
      { kind: "select", key: "variant", label: "نوع", options: [{ value: "card", label: "کارت" }, { value: "highlight", label: "بنر نتیجه" }, { value: "checklist", label: "فهرست تیک‌دار" }, { value: "cta", label: "باکس دعوت به اقدام" }] },
      { kind: "text", key: "primaryLabel", label: "دکمه اول (باکس دعوت)" },
      { kind: "link", key: "primaryHref", label: "پیوند دکمه اول" },
      { kind: "text", key: "secondaryLabel", label: "دکمه دوم" },
      { kind: "link", key: "secondaryHref", label: "پیوند دکمه دوم" },
      { kind: "text", key: "tertiaryLabel", label: "دکمه سوم" },
      { kind: "link", key: "tertiaryHref", label: "پیوند دکمه سوم" },
    ],
    create: () => node("story-card", { icon: "target", title: "چالش", text: "", tone: "blue", variant: "card" }),
  },
  {
    type: "blog-list",
    label: "فهرست بلاگ (کارت + زمان مطالعه + فیلتر موضوع)",
    category: "content",
    fields: [
      { kind: "text", key: "allLabel", label: "برچسب «همه»" },
      { kind: "number", key: "limit", label: "حداکثر تعداد", min: 1, max: 50 },
      { kind: "text", key: "emptyText", label: "متن حالت خالی" },
    ],
    create: () => node("blog-list", {"allLabel": "همه", "limit": 12, "emptyText": "هنوز مطلبی منتشر نشده است."}),
  },
  {
    type: "academy-grid",
    label: "گرید دوره‌های آکادمی (سطح + مدت)",
    category: "content",
    fields: [
      { kind: "text", key: "detailLabel", label: "متن پیوند کارت" },
      { kind: "text", key: "emptyText", label: "متن حالت خالی" },
    ],
    create: () => node("academy-grid", {"detailLabel": "مشاهده دوره", "emptyText": "دوره‌ها به‌زودی منتشر می‌شوند."}),
  },
  {
    type: "magazine-feature",
    label: "مجله (مطلب ویژه + چیدمان سردبیری + شماره‌ها)",
    category: "content",
    fields: [
      { kind: "text", key: "allLabel", label: "برچسب «همه شماره‌ها»" },
      { kind: "text", key: "featuredLabel", label: "برچسب ویژه" },
      { kind: "text", key: "emptyText", label: "متن حالت خالی" },
    ],
    create: () => node("magazine-feature", {"allLabel": "همه شماره‌ها", "featuredLabel": "ویژه", "emptyText": "نخستین شماره مجله به‌زودی منتشر می‌شود."}),
  },
  {
    type: "downloads-list",
    label: "دانلودها (فیلتر نوع فایل + فرم دریافت ایمیل)",
    category: "content",
    fields: [
      { kind: "text", key: "allLabel", label: "برچسب «همه»" },
      { kind: "text", key: "buttonLabel", label: "دکمه کارت" },
      { kind: "text", key: "gateText", label: "متن فرم" },
      { kind: "text", key: "submitLabel", label: "دکمه فرم" },
      { kind: "text", key: "doneText", label: "متن پس از ارسال" },
      { kind: "text", key: "downloadLabel", label: "دکمه دانلود" },
      { kind: "text", key: "offerText", label: "متن پیشنهاد بعدی" },
      { kind: "text", key: "offerLabel", label: "دکمه پیشنهاد (پیوند از تنظیم هر فایل؛ پیش‌فرض مشاوره)" },
      { kind: "text", key: "emptyText", label: "متن حالت خالی" },
    ],
    create: () => node("downloads-list", {"allLabel": "همه", "buttonLabel": "دریافت رایگان", "gateText": "برای دریافت فایل، نام و ایمیل خود را وارد کنید.", "submitLabel": "دریافت فایل", "doneText": "ممنون! فایل آماده دانلود است.", "downloadLabel": "دانلود فایل", "offerText": "می‌خواهید این موضوع را برای کسب‌وکار خودتان بررسی کنیم؟", "offerLabel": "دریافت مشاوره رایگان", "emptyText": "منابع دانلودی به‌زودی اضافه می‌شوند."}),
  },
  {
    type: "faq-categorized",
    label: "سوالات متداول دسته‌بندی‌شده (جست‌وجو + FAQ schema)",
    category: "content",
    fields: [
      { kind: "text", key: "searchPlaceholder", label: "متن جست‌وجو" },
      { kind: "textarea", key: "order", label: "ترتیب دسته‌ها (هر خط یک دسته)" },
      { kind: "text", key: "ctaText", label: "متن پایین هر دسته" },
      { kind: "text", key: "ctaLabel", label: "متن پیوند" },
      { kind: "link", key: "ctaHref", label: "پیوند" },
      { kind: "text", key: "emptyText", label: "متن حالت خالی" },
    ],
    create: () => node("faq-categorized", {"searchPlaceholder": "جست‌وجو در پرسش‌ها…", "order": "عمومی\nخدمات و پروژه‌ها\nمشاوره و پروپوزال\nهمکاری با ما\nپرداخت و قرارداد", "ctaText": "جوابت را پیدا نکردی؟", "ctaLabel": "مشاوره بگیر", "ctaHref": "/consultation", "emptyText": "پرسش‌ها به‌زودی اضافه می‌شوند."}),
  },
  {
    type: "announcements-list",
    label: "اطلاعیه‌ها (برچسب نوع + آرشیو ماهانه/سالانه شمسی)",
    category: "content",
    fields: [
      { kind: "text", key: "allLabel", label: "برچسب «همه»" },
      { kind: "text", key: "archiveLabel", label: "برچسب آرشیو" },
      { kind: "text", key: "emptyText", label: "متن حالت خالی" },
    ],
    create: () => node("announcements-list", {"allLabel": "همه", "archiveLabel": "آرشیو ماهانه / سالانه", "emptyText": "اطلاعیه‌ای منتشر نشده است."}),
  },
]
