/* Widget definitions shared with WebinoDashboard, plus ERP form wiring.
 * CONTENT_WIDGETS: landing-page widgets (section heading, steps, icon cards, badges, CTA, comparison, rahn). */

import { uid } from "./ids"
import { sharedContentWidgets, sharedFormWidget, sharedGapWidgets } from "@webina/ui/builder"
import { ICON_OPTIONS } from "./render/icon-options"
import { DETAIL_ICON_OPTIONS } from "./render/portfolio-detail-icons"
import { createSection } from "./tree"
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
    type: "sale-product-hero",
    label: "محصول فروشی: هیرو + گالری + باکس قیمت و افزودن به سبد",
    category: "commerce",
    fields: [
      { kind: "text", key: "slug", label: "شناسه محصول (برای سبد)" },
      { kind: "text", key: "title", label: "نام محصول" },
      { kind: "textarea", key: "subtitle", label: "زیرعنوان" },
      { kind: "text", key: "category", label: "دسته" },
      { kind: "text", key: "badge", label: "برچسب" },
      { kind: "text", key: "price", label: "قیمت (تومان)" },
      { kind: "text", key: "oldPrice", label: "قیمت قبل از تخفیف (تومان)" },
      { kind: "text", key: "priceNote", label: "توضیح قیمت" },
      { kind: "text", key: "license", label: "عنوان لایسنس" },
      { kind: "textarea", key: "images", label: "تصاویر گالری: آدرس|متن جایگزین در هر خط" },
      { kind: "textarea", key: "features", label: "ویژگی‌های کلیدی (هر خط یکی)" },
      { kind: "text", key: "version", label: "نسخه" },
      { kind: "text", key: "updated", label: "آخرین به‌روزرسانی" },
      { kind: "text", key: "sales", label: "تعداد فروش" },
      { kind: "text", key: "rating", label: "امتیاز (۰ تا ۵)" },
      { kind: "text", key: "ratingCount", label: "متن تعداد امتیاز" },
      { kind: "textarea", key: "guarantees", label: "ضمانت‌ها (هر خط یکی)" },
      { kind: "text", key: "buttonLabel", label: "متن دکمه خرید" },
      { kind: "link", key: "cartHref", label: "پیوند سبد خرید" },
      { kind: "text", key: "demoLabel", label: "متن دکمه دمو" },
      { kind: "link", key: "demoHref", label: "پیوند دمو" },
    ],
    create: () => node("sale-product-hero", { title: "نام محصول", price: "0", cartHref: "/pages/cart", buttonLabel: "افزودن به سبد خرید" }),
  },
  {
    type: "sale-product-tabs",
    label: "محصول فروشی: تب‌ها (توضیحات، مشخصات، تغییرات، سوالات، نظرات)",
    category: "commerce",
    fields: [
      { kind: "textarea", key: "description", label: "توضیحات (## تیتر، ### زیرتیتر، - فهرست)" },
      { kind: "textarea", key: "specs", label: "مشخصات: عنوان|مقدار در هر خط" },
      { kind: "textarea", key: "changelog", label: "تغییرات: نسخه|تاریخ|موارد جدا با ؛" },
      { kind: "textarea", key: "faq", label: "سوالات: پرسش|پاسخ در هر خط" },
      { kind: "textarea", key: "reviews", label: "نظرات: نام|امتیاز|متن|نقش در هر خط" },
    ],
    create: () => node("sale-product-tabs", { description: "", specs: "", changelog: "", faq: "", reviews: "" }),
  },
  {
    type: "sale-product-grid",
    label: "فهرست محصولات فروشی (دسته‌بندی + قیمت + خرید)",
    category: "commerce",
    fields: [
      { kind: "text", key: "title", label: "عنوان" },
      { kind: "textarea", key: "intro", label: "توضیح" },
      { kind: "textarea", key: "items", label: "در هر خط: شناسه|نام|دسته|قیمت|قیمت قبل|تصویر|پیوند|توضیح کوتاه" },
      { kind: "select", key: "showFilter", label: "فیلتر دسته", options: [{ value: "true", label: "نمایش" }, { value: "false", label: "پنهان" }] },
      { kind: "link", key: "cartHref", label: "پیوند سبد خرید" },
    ],
    create: () => node("sale-product-grid", { title: "محصولات", items: "", showFilter: "true", cartHref: "/pages/cart" }),
  },
  {
    type: "sale-cart",
    label: "سبد خرید محصولات فروشی",
    category: "commerce",
    fields: [
      { kind: "text", key: "title", label: "عنوان" },
      { kind: "text", key: "emptyText", label: "متن سبد خالی" },
      { kind: "textarea", key: "note", label: "توضیح زیر جمع کل" },
      { kind: "text", key: "checkoutLabel", label: "متن دکمه ثبت سفارش" },
      { kind: "link", key: "checkoutHref", label: "پیوند ثبت سفارش" },
    ],
    create: () => node("sale-cart", { title: "سبد خرید", checkoutLabel: "ثبت سفارش", checkoutHref: "/proposal" }),
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
    type: "academy-course-hero",
    label: "دوره آکادمی: هیرو (کاور + آمار + ثبت‌نام)",
    category: "content",
    fields: [
      { kind: "text", key: "course", label: "نامک دوره (آکادمی؛ خالی = از آدرس صفحه)" },
      { kind: "text", key: "kicker", label: "روتیتر" },
      { kind: "text", key: "title", label: "عنوان H1 (خالی = نام دوره)" },
      { kind: "text", key: "tagline", label: "شعار (خالی = از دوره)" },
      { kind: "textarea", key: "subtitle", label: "متن معرفی (خالی = خط اول توضیح دوره)" },
      { kind: "text", key: "buttonLabel", label: "متن دکمه ثبت‌نام" },
      { kind: "link", key: "enrollHref", label: "پیوند ثبت‌نام (خالی = از دوره)" },
      { kind: "text", key: "secondaryLabel", label: "متن دکمه سرفصل‌ها" },
      { kind: "switch", key: "showStats", label: "نمایش آمار" },
      { kind: "switch", key: "breadcrumb", label: "مسیر راهنما" },
    ],
    create: () => node("academy-course-hero", { course: "", kicker: "دوره آنلاین آکادمی وبینا", secondaryLabel: "مشاهده سرفصل‌ها" }),
  },
  {
    type: "academy-course-about",
    label: "دوره آکادمی: معرفی + دستاوردها + مخاطب و پیش‌نیاز",
    category: "content",
    fields: [{ kind: "text", key: "course", label: "نامک دوره (آکادمی؛ خالی = از آدرس صفحه)" }, { kind: "text", key: "title", label: "عنوان" }, { kind: "textarea", key: "text", label: "متن تکمیلی سئو (هر خط یک پاراگراف)" }, { kind: "text", key: "outcomesTitle", label: "عنوان دستاوردها" }, { kind: "text", key: "audienceTitle", label: "عنوان مخاطبان" }, { kind: "text", key: "prereqTitle", label: "عنوان پیش‌نیازها" }],
    create: () => node("academy-course-about", { course: "", title: "درباره این دوره" }),
  },
  {
    type: "academy-course-syllabus",
    label: "دوره آکادمی: سرفصل‌ها (فصل و درس، پیش‌نمایش رایگان)",
    category: "content",
    fields: [{ kind: "text", key: "course", label: "نامک دوره (آکادمی؛ خالی = از آدرس صفحه)" }, { kind: "text", key: "title", label: "عنوان" }, { kind: "text", key: "previewLabel", label: "برچسب پیش‌نمایش" }, { kind: "switch", key: "openAll", label: "باز بودن همه فصل‌ها" }],
    create: () => node("academy-course-syllabus", { course: "", title: "سرفصل‌های دوره" }),
  },
  {
    type: "academy-course-instructor",
    label: "دوره آکادمی: مدرس",
    category: "content",
    fields: [{ kind: "text", key: "course", label: "نامک دوره (آکادمی؛ خالی = از آدرس صفحه)" }, { kind: "text", key: "title", label: "عنوان" }, { kind: "media", accept: "image", key: "photo", label: "عکس مدرس (اختیاری)" }],
    create: () => node("academy-course-instructor", { course: "", title: "مدرس دوره" }),
  },
  {
    type: "academy-course-quizzes",
    label: "دوره آکادمی: آزمون‌ها، پروژه‌ها و منابع",
    category: "content",
    fields: [{ kind: "text", key: "course", label: "نامک دوره (آکادمی؛ خالی = از آدرس صفحه)" }, { kind: "text", key: "title", label: "عنوان" }, { kind: "textarea", key: "text", label: "توضیح" }, { kind: "switch", key: "showSample", label: "نمایش نمونه سؤال" }, { kind: "text", key: "projectsTitle", label: "عنوان پروژه‌ها" }, { kind: "text", key: "materialsTitle", label: "عنوان منابع" }],
    create: () => node("academy-course-quizzes", { course: "", title: "آزمون‌ها و ارزیابی" }),
  },
  {
    type: "academy-course-reviews",
    label: "دوره آکادمی: نظرات دانشجویان + پرسش‌ها (FAQ schema)",
    category: "content",
    fields: [{ kind: "text", key: "course", label: "نامک دوره (آکادمی؛ خالی = از آدرس صفحه)" }, { kind: "text", key: "title", label: "عنوان نظرات" }, { kind: "text", key: "faqTitle", label: "عنوان پرسش‌ها" }, { kind: "switch", key: "showFaq", label: "نمایش پرسش‌ها" }],
    create: () => node("academy-course-reviews", { course: "", title: "نظر دانشجویان", faqTitle: "پرسش‌های پرتکرار" }),
  },
  {
    type: "academy-course-enroll",
    label: "دوره آکادمی: دعوت به ثبت‌نام (باکس یا بنر) + Course schema",
    category: "content",
    fields: [{ kind: "text", key: "course", label: "نامک دوره (آکادمی؛ خالی = از آدرس صفحه)" }, { kind: "select", key: "variant", label: "نوع", options: [{ value: "box", label: "باکس ثبت‌نام" }, { value: "banner", label: "بنر آبی" }] }, { kind: "text", key: "title", label: "عنوان (بنر)" }, { kind: "textarea", key: "text", label: "متن" }, { kind: "text", key: "priceLabel", label: "متن قیمت" }, { kind: "text", key: "buttonLabel", label: "متن دکمه" }, { kind: "link", key: "href", label: "پیوند (خالی = از دوره)" }, { kind: "text", key: "includesTitle", label: "عنوان «شامل»" }, { kind: "switch", key: "schema", label: "اسکیمای Course" }, { kind: "text", key: "price", label: "قیمت (تومان؛ خالی = بدون سبد خرید)" }, { kind: "switch", key: "cart", label: "دکمه افزودن به سبد" }, { kind: "text", key: "cartSlug", label: "شناسه محصول فروشگاه (پیش‌فرض course-نامک)" }, { kind: "text", key: "cartLabel", label: "متن دکمه سبد" }, { kind: "link", key: "cartHref", label: "پیوند سبد خرید" }],
    create: () => node("academy-course-enroll", { course: "", variant: "box" }),
  },
  {
    type: "academy-related-courses",
    label: "دوره آکادمی: دوره‌های مرتبط",
    category: "content",
    fields: [{ kind: "text", key: "course", label: "نامک دوره فعلی (حذف از فهرست)" }, { kind: "text", key: "title", label: "عنوان" }, { kind: "number", key: "limit", label: "تعداد", min: 1, max: 12 }, { kind: "text", key: "detailLabel", label: "متن پیوند کارت" }],
    create: () => node("academy-related-courses", { course: "", title: "دوره‌های مرتبط", limit: 3 }),
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

/** Advanced product archive filters (shared with WebinoDashboard); feeds product Loop Grids of the same filter group. */
export const ARCHIVE_FILTERS_WIDGET: WidgetDef = {
  type: "archive-filters",
  label: "فیلتر پیشرفته آرشیو",
  category: "commerce",
  fields: [
    { kind: "text", key: "filterGroup", label: "گروه فیلتر (همان مقدار در ویجت لوپ محصولات)" },
    { kind: "text", key: "title", label: "عنوان" },
    { kind: "switch", key: "syncUrl", label: "همگام با نشانی صفحه (قابل اشتراک)" },
    { kind: "switch", key: "showSearch", label: "جستجو" },
    { kind: "switch", key: "showSort", label: "مرتب‌سازی" },
    { kind: "switch", key: "showCategory", label: "دسته‌بندی" },
    { kind: "switch", key: "showPrice", label: "بازه قیمت" },
    { kind: "text", key: "currencyLabel", label: "واحد قیمت" },
    { kind: "switch", key: "showStock", label: "فقط موجود" },
    { kind: "switch", key: "showOnSale", label: "فقط تخفیف‌دار" },
    { kind: "textarea", key: "tags", label: "برچسب‌ها (نامک|عنوان در هر خط)" },
    { kind: "textarea", key: "attributes", label: "ویژگی‌ها (ویژگی:مقدار|عنوان، مثل color:red|قرمز)" },
  ],
  create: () => node("archive-filters", { filterGroup: "shop", title: "فیلترها", syncUrl: true, showSearch: true, showSort: true, showCategory: true, showPrice: true, currencyLabel: "تومان", showStock: true, showOnSale: true, tags: "", attributes: "" }),
}

const SECTION_PRESETS: Record<string, number[]> = { "12": [12], "6-6": [6, 6], "4-4-4": [4, 4, 4], "3-3-3-3": [3, 3, 3, 3], "8-4": [8, 4], "4-8": [4, 8] }
/**
 * @webina/ui v0.2 gap pack (team, media, auth forms, layout containers, general header/footer), shared with
 * WebinoDashboard. Layout types get nested sections here, as the shared package only ships schemas/defaults.
 */
export const GAP_WIDGETS: WidgetDef[] = sharedGapWidgets(ICON_OPTIONS).map((def) => {
  const base = def as unknown as WidgetDef
  if (!["section", "columns", "flex-container", "grid-container"].includes(def.type)) return base
  return {
    ...base,
    create: () => {
      const created = base.create()
      const preset = typeof created.props.columnPreset === "string" ? SECTION_PRESETS[created.props.columnPreset] : undefined
      return { ...created, children: [createSection(preset ?? [12])] }
    },
  }
})
