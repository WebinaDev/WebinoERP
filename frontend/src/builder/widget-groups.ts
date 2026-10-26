/* Unified widget groups (ERP side of the @webina/ui merge plan): post lists, galleries, banners, sale and academy.
 * Each group shows one canonical widget in the library; the other types become its "display style" options in the
 * inspector. Every member type stays registered with its own renderer, so saved pages render exactly as before. */
import type { WidgetNode } from "./types"

export type WidgetGroup = { id: string; label: string; canonical: string; members: { type: string; label: string }[] }

export const WIDGET_GROUPS: WidgetGroup[] = [
  { id: "post-list", label: "فهرست مطالب", canonical: "blog-posts", members: [
    { type: "blog-posts", label: "شبکه یا فهرست ساده" },
    { type: "blog-list", label: "کارت با زمان مطالعه و فیلتر موضوع" },
    { type: "blog-slider", label: "اسلایدر" },
    { type: "blog-teasers", label: "پیش‌نمایش کوتاه" },
    { type: "magazine-feature", label: "مجله‌ای با مطلب ویژه" },
    { type: "announcements-list", label: "اطلاعیه‌ها" },
    { type: "downloads-list", label: "دانلودها" },
  ] },
  { id: "gallery", label: "گالری", canonical: "image-gallery", members: [
    { type: "image-gallery", label: "شبکه تصاویر" },
    { type: "image-carousel", label: "چرخ‌فلک تصاویر" },
    { type: "portfolio-gallery", label: "گالری نمونه‌کار" },
    { type: "portfolio-grid", label: "شبکه نمونه‌کارها" },
    { type: "media-archive", label: "آرشیو صوت و ویدیو" },
  ] },
  { id: "banner", label: "بنر", canonical: "promo-banner", members: [
    { type: "promo-banner", label: "بنر تبلیغاتی" },
    { type: "cta-banner", label: "بنر دعوت به اقدام" },
    { type: "banner-grid", label: "شبکه بنرها" },
    { type: "special-banners", label: "بنرهای ویژه" },
    { type: "deal-bar", label: "نوار پیشنهاد" },
    { type: "book-banner", label: "بنر کتاب با «خبرم کن»" },
  ] },
  { id: "sale", label: "فروش محصول", canonical: "sale-product-grid", members: [
    { type: "sale-product-grid", label: "شبکه محصولات" },
    { type: "sale-product-hero", label: "معرفی محصول" },
    { type: "sale-product-tabs", label: "زبانه‌های محصول" },
    { type: "sale-cart", label: "سبد خرید" },
  ] },
  { id: "academy-list", label: "دوره‌های آکادمی", canonical: "academy-grid", members: [
    { type: "academy-grid", label: "شبکه دوره‌ها" },
    { type: "academy-related-courses", label: "دوره‌های مرتبط" },
  ] },
  { id: "academy-course", label: "بخش‌های صفحه دوره", canonical: "academy-course-hero", members: [
    { type: "academy-course-hero", label: "سربرگ دوره" },
    { type: "academy-course-about", label: "درباره دوره" },
    { type: "academy-course-syllabus", label: "سرفصل‌ها" },
    { type: "academy-course-instructor", label: "مدرس" },
    { type: "academy-course-quizzes", label: "آزمون‌ها" },
    { type: "academy-course-reviews", label: "نظرها" },
    { type: "academy-course-enroll", label: "ثبت‌نام" },
  ] },
]

const groupOf = new Map<string, WidgetGroup>()
for (const g of WIDGET_GROUPS) for (const m of g.members) groupOf.set(m.type, g)

export function widgetGroup(type: string): WidgetGroup | undefined {
  return groupOf.get(type)
}

/** Library/Finder filter: hide group members other than the canonical one (they stay reachable via the inspector). */
export function isLibraryWidget(type: string): boolean {
  const g = groupOf.get(type)
  return !g || g.canonical === type
}

/** Switch a widget to another style in its group, keeping the props the user already set. */
export function switchGroupMember(widget: WidgetNode, nextType: string, create: (type: string) => WidgetNode | null): WidgetNode {
  const fresh = create(nextType)
  if (!fresh) return widget
  return { ...widget, type: nextType, props: { ...fresh.props, ...widget.props }, children: widget.children ?? fresh.children }
}
