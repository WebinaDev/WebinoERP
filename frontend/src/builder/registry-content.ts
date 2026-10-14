/* Widget definitions added to the ERP builder in the Dashboard sync.
 * PORTED_WIDGETS: copied from WebinoDashboard registry.ts (content widgets; storefront-only ones stay there).
 * CONTENT_WIDGETS: ERP-native landing-page widgets (section heading, steps, icon cards, badges, CTA). */

import { uid } from "./ids"
import { FORM_FIELD_TYPES, type FormFieldType } from "./forms"
import { ICON_OPTIONS } from "./render/icon-options"
import type { WidgetDef, WidgetNode } from "./types"

const FORM_TYPE_LABELS: Record<FormFieldType, string> = { text: "متن", email: "ایمیل", tel: "تلفن", number: "عدد", textarea: "متن بلند", select: "فهرست کشویی", radio: "تک‌انتخابی", checkbox: "چندانتخابی", date: "تاریخ", file: "فایل (پیوست)", heading: "عنوان گروه" }

function node(type: string, props: Record<string, unknown>): WidgetNode {
  return { id: uid("w"), type, props }
}

const ALIGN = { kind: "select" as const, key: "align", label: "تراز", options: [
  { value: "center", label: "وسط" },
  { value: "start", label: "راست (شروع)" },
  { value: "end", label: "چپ (پایان)" },
] }

/** Pro form (Dashboard «فرم»): submissions land in the Marketing form whose slug is the form key. */
export const FORM_WIDGET: WidgetDef = {
  type: "form",
  label: "فرم",
  category: "basic",
  fields: [
    { kind: "text", key: "title", label: "عنوان" },
    { kind: "textarea", key: "intro", label: "متن بالای فرم" },
    { kind: "text", key: "submit", label: "دکمه" },
    { kind: "text", key: "formKey", label: "نامک فرم (بازاریابی ← فرم‌ها)" },
    {
      kind: "repeater",
      key: "fields",
      label: "فیلدها (خالی = فیلدهای خود فرم در بازاریابی ← فرم‌ها)",
      fields: [
        { key: "label", label: "برچسب", kind: "text" },
        { key: "name", label: "نام فنی (name)", kind: "text" },
        { key: "type", label: "نوع", kind: "select", options: FORM_FIELD_TYPES.map((value) => ({ value, label: FORM_TYPE_LABELS[value] })) },
        { key: "options", label: "گزینه‌ها (با ، جدا)", kind: "text" },
        { key: "required", label: "الزامی", kind: "select", options: [{ value: "", label: "بله" }, { value: "no", label: "خیر" }] },
        { key: "step", label: "مرحله", kind: "text" },
        { key: "showIf", label: "نمایش فقط اگر (name=مقدار)", kind: "text" },
      ],
    },
    { kind: "select", key: "columns", label: "ستون فیلدها", options: [{ value: "1", label: "یک ستون" }, { value: "2", label: "دو ستون" }] },
    { kind: "text", key: "success", label: "پیام موفقیت" },
    { kind: "textarea", key: "note", label: "متن زیر فرم" },
    { kind: "text", key: "anchor", label: "شناسه لنگر (#)" },
    { kind: "textarea", key: "stepTitles", label: "عنوان مراحل (هر خط یک مرحله)" },
    { kind: "switch", key: "review", label: "مرحله بررسی نهایی (خلاصه قابل ویرایش)" },
    { kind: "repeater", key: "confirms", label: "تأییدهای الزامی", fields: [{ key: "text", label: "متن", kind: "text" }] },
    { kind: "switch", key: "showPhone", label: "فیلد تلفن" },
    { kind: "switch", key: "showEmail", label: "فیلد ایمیل" },
  ],
  create: () =>
    node("form", {
      title: "ارسال پیام",
      submit: "ارسال",
      formKey: "contact",
      fields: "",
      success: "پیام شما ثبت شد.",
      columns: "1",
    }),
}

export const PORTED_WIDGETS: WidgetDef[] = [
  {
    type: "banner-grid",
    label: "شبکه بنر",
    category: "basic",
    fields: [
      { kind: "repeater", key: "items", label: "بنرها", fields: [{ key: "image", label: "تصویر", kind: "media" }, { key: "href", label: "پیوند", kind: "url" }, { key: "alt", label: "متن جایگزین", kind: "text" }] },
      { kind: "number", key: "columns", label: "ستون‌ها", min: 1, max: 4 },
      { kind: "select", key: "ratio", label: "نسبت تصویر", options: [
        { value: "16/7", label: "۱۶:۷" },
        { value: "16/9", label: "۱۶:۹" },
        { value: "4/3", label: "۴:۳" },
        { value: "1/1", label: "مربع" },
        { value: "3/4", label: "عمودی" },
      ] },
      { kind: "number", key: "radius", label: "گردی گوشه", min: 0, max: 40 },
    ],
    create: () => node("banner-grid", { items: "", columns: 2, ratio: "16/7", radius: 16 }),
  },
  {
    type: "tabs",
    label: "زبانه‌ها",
    category: "basic",
    fields: [{ kind: "repeater", key: "items", label: "زبانه‌ها", fields: [{ key: "title", label: "عنوان", kind: "text" }, { key: "body", label: "محتوا", kind: "textarea" }] }],
    create: () => node("tabs", { items: "زبانه اول|محتوای زبانه اول\nزبانه دوم|محتوای زبانه دوم" }),
  },
  {
    type: "accordion",
    label: "آکاردئون / سؤالات متداول",
    category: "basic",
    fields: [
      { kind: "text", key: "title", label: "عنوان" },
      { kind: "repeater", key: "items", label: "پرسش‌ها", fields: [{ key: "q", label: "پرسش", kind: "text" }, { key: "a", label: "پاسخ", kind: "textarea" }] },
      { kind: "switch", key: "firstOpen", label: "باز بودن مورد اول" },
      { kind: "switch", key: "faqSchema", label: "داده ساختاریافته FAQ (سئو)" },
    ],
    create: () => node("accordion", { title: "سؤالات متداول", items: "", firstOpen: true, faqSchema: true }),
  },
  {
    type: "icon-box",
    label: "باکس آیکن",
    category: "basic",
    fields: [
      { kind: "icon", key: "icon", label: "آیکن", options: ICON_OPTIONS },
      { kind: "text", key: "title", label: "عنوان" },
      { kind: "textarea", key: "text", label: "متن" },
      { kind: "link", key: "href", label: "پیوند" },
      { kind: "select", key: "layout", label: "چیدمان", options: [
        { value: "top", label: "آیکن بالا" },
        { value: "side", label: "آیکن کنار" },
      ] },
    ],
    create: () => node("icon-box", { icon: "truck", title: "ارسال سریع", text: "", layout: "top" }),
  },
  {
    type: "icon-list",
    label: "فهرست آیکن‌دار",
    category: "basic",
    fields: [
      { kind: "repeater", key: "items", label: "آیتم‌ها", fields: [{ key: "icon", label: "آیکن", kind: "icon", options: ICON_OPTIONS }, { key: "text", label: "متن", kind: "text" }, { key: "href", label: "پیوند", kind: "url" }] },
      { kind: "select", key: "direction", label: "جهت", options: [
        { value: "column", label: "عمودی" },
        { value: "row", label: "افقی" },
      ] },
    ],
    create: () => node("icon-list", { items: "", direction: "column" }),
  },
  {
    type: "testimonials",
    label: "نظرات مشتریان",
    category: "basic",
    fields: [
      { kind: "text", key: "title", label: "عنوان" },
      { kind: "repeater", key: "items", label: "نظرها", fields: [{ key: "name", label: "نام", kind: "text" }, { key: "text", label: "متن", kind: "textarea" }, { key: "rating", label: "امتیاز ۱ تا ۵", kind: "text" }, { key: "image", label: "تصویر", kind: "media" }] },
    ],
    create: () => node("testimonials", { title: "نظر مشتریان", items: "" }),
  },
  {
    type: "image-gallery",
    label: "گالری تصاویر",
    category: "basic",
    fields: [
      { kind: "textarea", key: "images", label: "تصویر|متن جایگزین در هر خط" },
      { kind: "number", key: "columns", label: "ستون‌ها", min: 1, max: 6 },
      { kind: "switch", key: "lightbox", label: "بزرگ‌نمایی با کلیک" },
    ],
    create: () => node("image-gallery", { images: "", columns: 3, lightbox: true }),
  },
  {
    type: "image-carousel",
    label: "کاروسل تصاویر",
    category: "basic",
    fields: [
      { kind: "textarea", key: "images", label: "تصویر|پیوند|متن جایگزین در هر خط" },
      { kind: "number", key: "autoplay", label: "پخش خودکار (ثانیه، ۰ = خاموش)", min: 0, max: 30 },
      { kind: "select", key: "ratio", label: "نسبت", options: [
        { value: "16/6", label: "پهن" },
        { value: "16/9", label: "۱۶:۹" },
        { value: "4/3", label: "۴:۳" },
        { value: "1/1", label: "مربع" },
      ] },
    ],
    create: () => node("image-carousel", { images: "", autoplay: 5, ratio: "16/6" }),
  },
  {
    type: "announcement-bar",
    label: "نوار اطلاعیه",
    category: "basic",
    fields: [
      { kind: "text", key: "text", label: "متن" },
      { kind: "link", key: "href", label: "پیوند" },
      { kind: "color", key: "background", label: "رنگ زمینه" },
      { kind: "color", key: "color", label: "رنگ متن" },
      { kind: "switch", key: "dismissible", label: "قابل بستن" },
    ],
    create: () => node("announcement-bar", { text: "", dismissible: true }),
  },
  {
    type: "map",
    label: "نقشه",
    category: "basic",
    fields: [
      { kind: "text", key: "lat", label: "عرض جغرافیایی" },
      { kind: "text", key: "lng", label: "طول جغرافیایی" },
      { kind: "number", key: "zoom", label: "بزرگ‌نمایی", min: 3, max: 18 },
      { kind: "number", key: "height", label: "ارتفاع (px)", min: 160, max: 800 },
      { kind: "text", key: "address", label: "نشانی" },
    ],
    create: () => node("map", { lat: "35.6892", lng: "51.389", zoom: 15, height: 320, address: "" }),
  },
  {
    type: "counter",
    label: "شمارنده",
    category: "basic",
    fields: [{ kind: "repeater", key: "items", label: "شمارنده‌ها", fields: [{ key: "value", label: "عدد", kind: "text" }, { key: "label", label: "برچسب", kind: "text" }, { key: "suffix", label: "پسوند", kind: "text" }] }],
    create: () => node("counter", { items: "" }),
  },
  {
    type: "social-icons",
    label: "شبکه‌های اجتماعی",
    category: "basic",
    fields: [{ kind: "repeater", key: "items", label: "شبکه‌ها", fields: [{ key: "network", label: "شبکه", kind: "select", options: ["instagram", "telegram", "whatsapp", "x", "linkedin", "youtube", "aparat", "eitaa", "bale", "rubika"].map((value) => ({ value, label: value })) }, { key: "href", label: "پیوند", kind: "url" }] }],
    create: () => node("social-icons", { items: "" }),
  },
  {
    type: "share-buttons",
    label: "دکمه‌های اشتراک‌گذاری",
    category: "basic",
    fields: [{ kind: "text", key: "title", label: "عنوان" }],
    create: () => node("share-buttons", { title: "اشتراک‌گذاری" }),
  },
  {
    type: "pricing-table",
    label: "جدول قیمت",
    category: "basic",
    fields: [
      { kind: "textarea", key: "plans", label: "نام|قیمت|ویژگی‌ها با ; |پیوند|ویژه (1) در هر خط" },
      { kind: "text", key: "cta", label: "متن دکمه" },
    ],
    create: () => node("pricing-table", { plans: "", cta: "انتخاب" }),
  },
  {
    type: "blog-posts",
    label: "نوشته‌های وبلاگ (کوئری)",
    category: "basic",
    fields: [
      { kind: "text", key: "title", label: "عنوان" },
      { kind: "number", key: "limit", label: "تعداد", min: 1, max: 12 },
      { kind: "text", key: "category", label: "نامک دسته (اختیاری)" },
      { kind: "select", key: "layout", label: "چیدمان", options: [
        { value: "grid", label: "شبکه" },
        { value: "list", label: "فهرست" },
      ] },
    ],
    create: () => node("blog-posts", { title: "از مجله", limit: 3, layout: "grid" }),
  },
  {
    type: "feed",
    label: "فید اینستاگرام",
    category: "basic",
    fields: [{ kind: "text", key: "title", label: "عنوان" }, { kind: "text", key: "handle", label: "نام کاربری (@)" }, { kind: "url", key: "profileUrl", label: "پیوند پروفایل" }, { kind: "repeater", key: "items", label: "پست‌ها", fields: [{ key: "image", label: "تصویر", kind: "media" }, { key: "href", label: "پیوند پست", kind: "url" }, { key: "caption", label: "توضیح", kind: "text" }] }, { kind: "number", key: "columns", label: "ستون‌ها", min: 2, max: 6 }],
    create: () => node("feed", { title: "ما را در اینستاگرام دنبال کنید", items: "", columns: 4 }),
  },
]

export const CONTENT_WIDGETS: WidgetDef[] = [
  {
    type: "section-heading",
    label: "سرتیتر بخش",
    category: "content",
    fields: [
      { kind: "text", key: "eyebrow", label: "برچسب بالا" },
      { kind: "text", key: "title", label: "عنوان" },
      { kind: "select", key: "tag", label: "سطح", options: [
        { value: "h1", label: "عنوان اصلی (h1)" },
        { value: "h2", label: "عنوان (h2)" },
        { value: "h3", label: "زیرعنوان (h3)" },
      ] },
      { kind: "text", key: "subtitle", label: "زیرعنوان" },
      { kind: "textarea", key: "text", label: "متن" },
      { kind: "text", key: "linkLabel", label: "متن پیوند" },
      { kind: "link", key: "linkHref", label: "پیوند" },
      ALIGN,
    ],
    create: () => node("section-heading", { title: "عنوان بخش", tag: "h2", align: "center" }),
  },
  {
    type: "steps",
    label: "مراحل / فرآیند",
    category: "content",
    fields: [
      { kind: "repeater", key: "items", label: "مراحل", fields: [{ key: "title", label: "عنوان", kind: "text" }, { key: "text", label: "توضیح", kind: "textarea" }] },
      { kind: "select", key: "layout", label: "چیدمان", options: [
        { value: "horizontal", label: "افقی با خط اتصال" },
        { value: "vertical", label: "عمودی (تایم‌لاین)" },
      ] },
      { kind: "text", key: "note", label: "نکته زیر مراحل" },
    ],
    create: () => node("steps", { items: "مرحله اول|توضیح مرحله\nمرحله دوم|توضیح مرحله\nمرحله سوم|توضیح مرحله", layout: "horizontal" }),
  },
  {
    type: "icon-cards",
    label: "کارت‌های آیکن‌دار",
    category: "content",
    fields: [
      { kind: "repeater", key: "items", label: "کارت‌ها", fields: [{ key: "icon", label: "آیکن", kind: "icon", options: ICON_OPTIONS }, { key: "title", label: "عنوان", kind: "text" }, { key: "text", label: "متن", kind: "textarea" }, { key: "prefill", label: "پیش‌پرکردن فرم (name=مقدار)", kind: "text" }] },
      { kind: "text", key: "anchor", label: "فرم مقصد کلیک کارت (شناسه لنگر)" },
      { kind: "select", key: "columns", label: "ستون‌ها", options: [
        { value: "1", label: "۱" },
        { value: "2", label: "۲" },
        { value: "3", label: "۳" },
        { value: "4", label: "۴" },
      ] },
      { kind: "select", key: "variant", label: "ظاهر", options: [
        { value: "card", label: "کارت افقی" },
        { value: "stacked", label: "کارت عمودی" },
        { value: "check", label: "فهرست تیک‌دار" },
        { value: "minimal", label: "مینیمال" },
      ] },
    ],
    create: () => node("icon-cards", { items: "badge|عنوان کارت|توضیح کوتاه", columns: "3", variant: "card" }),
  },
  {
    type: "badges",
    label: "نشان‌های اعتماد",
    category: "content",
    fields: [
      { kind: "repeater", key: "items", label: "نشان‌ها", fields: [{ key: "icon", label: "آیکن", kind: "icon", options: ICON_OPTIONS }, { key: "text", label: "متن", kind: "text" }] },
      ALIGN,
    ],
    create: () => node("badges", { items: "clock|جلسه ۳۰ تا ۴۵ دقیقه‌ای\nshield|بدون تعهد خرید", align: "center" }),
  },
  {
    type: "cta-banner",
    label: "بنر فراخوان (CTA)",
    category: "content",
    fields: [
      { kind: "text", key: "title", label: "عنوان" },
      { kind: "textarea", key: "text", label: "متن" },
      { kind: "text", key: "label", label: "متن دکمه" },
      { kind: "link", key: "href", label: "پیوند دکمه" },
      { kind: "text", key: "secondaryLabel", label: "متن پیوند دوم" },
      { kind: "link", key: "secondaryHref", label: "پیوند دوم" },
      { kind: "select", key: "tone", label: "رنگ", options: [
        { value: "primary", label: "اصلی" },
        { value: "dark", label: "تیره" },
        { value: "light", label: "روشن" },
      ] },
    ],
    create: () => node("cta-banner", { title: "آماده شروع هستید؟", text: "", label: "تماس با ما", href: "/contact", tone: "primary" }),
  },
  {
    type: "comparison-table",
    label: "جدول مقایسه",
    category: "content",
    fields: [
      { kind: "text", key: "colA", label: "عنوان ستون اول" },
      { kind: "text", key: "colB", label: "عنوان ستون دوم" },
      { kind: "repeater", key: "rows", label: "ردیف‌ها", fields: [{ key: "a", label: "ستون اول", kind: "text" }, { key: "b", label: "ستون دوم", kind: "text" }] },
      { kind: "select", key: "highlight", label: "ستون برجسته", options: [{ value: "b", label: "ستون دوم" }, { value: "a", label: "ستون اول" }, { value: "", label: "هیچ‌کدام" }] },
    ],
    create: () => node("comparison-table", { colA: "گزینه الف", colB: "گزینه ب", rows: "|", highlight: "b" }),
  },
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
