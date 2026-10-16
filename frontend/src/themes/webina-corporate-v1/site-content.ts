/**
 * Editable site content for the webina-corporate-v1 theme.
 *
 * The page builder (dashboard → صفحه‌ساز → صفحه اصلی) stores everything here in
 * marketing site settings: `home_blocks[].props`, `branding.header`, `branding.footer`,
 * `branding.contact`, `branding.trust_badges` and `social_links`. The functions below
 * build the same shapes from the message files; they are the fallback when a field is
 * empty and the English rendering (CMS copy is Persian).
 */
import { COMPANY_MEGA, RESOURCE_MEGA, SERVICE_MEGA, SOLUTION_MEGA, type MegaDef } from './site-nav';
import {
  FOOTER_QUICK_LINKS,
  HOME_ABOUT_POINTS,
  HOME_COURSES,
  HOME_MODULES,
  HOME_SERVICES,
  HOME_STEPS,
  HOME_WHY,
} from './home-content';

type T = (key: string) => string;

export type LinkItem = { label: string; href: string };
export type ButtonItem = LinkItem & { style?: 'primary' | 'ghost' | 'link' };
export type StatItem = {
  source: 'portfolio_count' | 'service_count' | 'industry_count' | 'highlight_result' | 'manual';
  label: string;
  value?: string;
};

export type HomeProps = {
  hero: {
    title: string;
    subtitle_1: string;
    subtitle_2: string;
    text_1: string;
    text_2: string;
    buttons: ButtonItem[];
    stats: StatItem[];
    dock_label: string;
    dock_cta_label: string;
    dock_cta_href: string;
  };
  about: { title: string; body: string; list_lead: string; points: { text: string }[]; closing: string };
  services: {
    title: string;
    subtitle: string;
    items: { title: string; body: string; href: string; icon: string; vip?: boolean }[];
    cta_label: string;
    cta_href: string;
  };
  products: {
    title: string;
    subtitle: string;
    intro: string;
    page_heading: string;
    items: { title: string; anchor: string; icon: string }[];
    cta_label: string;
    cta_href: string;
  };
  portfolio_teaser: { title: string; subtitle: string; details_label: string; cta_label: string; cta_href: string; limit: number; empty: string };
  process: { title: string; anchor: string; steps: { text: string; highlight?: boolean }[]; cta_label: string; cta_href: string };
  academy: { title: string; subtitle: string; text: string; cta_label: string; cta_href: string; fallback_courses: { title: string; slug: string }[] };
  blog: { title: string; subtitle: string; read_more: string; cta_label: string; cta_href: string; limit: number; empty: string };
  why: { title: string; items: { text: string }[] };
  testimonials: { title: string; empty: string; limit: number; autoplay: boolean; interval: number; show_stars: boolean; show_arrows: boolean };
  consultation_cta: { title: string; text_1: string; text_2: string; buttons: ButtonItem[] };
};

export type HomeBlockType = keyof HomeProps;
export const HOME_BLOCK_ORDER: HomeBlockType[] = [
  'hero',
  'about',
  'services',
  'products',
  'portfolio_teaser',
  'process',
  'academy',
  'blog',
  'why',
  'testimonials',
  'consultation_cta',
];

export type NavColumnData = { title: string; lead: string; href?: string; icon: string; items: (LinkItem & { icon: string })[] };
export type NavItemData = { label: string; href: string; columns?: NavColumnData[] };
export type HeaderData = {
  cta_label: string;
  cta_href: string;
  mobile_secondary_label: string;
  mobile_secondary_href: string;
  promo_small: string;
  promo_title: string;
  panel_hint: string;
  see_all: string;
  nav: NavItemData[];
};

export type FooterData = {
  tagline: string;
  description: string;
  cta_label: string;
  cta_href: string;
  quick_title: string;
  quick_links: LinkItem[];
  contact_title: string;
  contact_page_label: string;
  contact_page_href: string;
  social_title: string;
  social_empty: string;
  legal_links: LinkItem[];
  copyright: string;
  legal_name: string;
  trust_title: string;
  trust_lead: string;
};

export type ContactData = { items: { label: string; value: string; href?: string }[]; email?: string; address?: string };
export type SocialLink = { label: string; href: string; handle?: string; icon?: string };

export function homeDefaults(t: T): HomeProps {
  const v = (k: string) => t(`site.v2.${k}`);
  return {
    hero: {
      title: v('heroTitle'),
      subtitle_1: v('heroSubtitle1'),
      subtitle_2: v('heroSubtitle2'),
      text_1: v('heroText1'),
      text_2: v('heroText2'),
      buttons: [
        { label: v('heroCta1'), href: 'consultation', style: 'primary' },
        { label: v('heroCta2'), href: '#process', style: 'ghost' },
        { label: v('heroCta3'), href: 'proposal', style: 'link' },
      ],
      stats: [
        { source: 'portfolio_count', label: v('heroStatProjects') },
        { source: 'service_count', label: v('heroStatServices') },
        { source: 'highlight_result', label: v('heroStatResult') },
      ],
      dock_label: t('site.landing.dockLabel'),
      dock_cta_label: v('heroCta1'),
      dock_cta_href: 'consultation',
    },
    about: {
      title: v('aboutTitle'),
      body: v('aboutBody'),
      list_lead: v('aboutListLead'),
      points: HOME_ABOUT_POINTS.map((k) => ({ text: v(`about.${k}`) })),
      closing: v('aboutClosing'),
    },
    services: {
      title: v('servicesTitle'),
      subtitle: v('servicesSubtitle'),
      items: HOME_SERVICES.map((s) => ({
        title: v(`service.${s.key}Title`),
        body: v(`service.${s.key}Body`),
        href: `services/${s.slug}`,
        icon: s.icon,
        vip: s.key === 's7',
      })),
      cta_label: v('servicesCta'),
      cta_href: 'services',
    },
    products: {
      title: v('productsTitle'),
      subtitle: v('productsSubtitle'),
      intro: v('productsIntro'),
      page_heading: v('modulesHeading'),
      items: HOME_MODULES.map((m) => ({ title: v(`module.${m.key}`), anchor: m.id, icon: m.icon })),
      cta_label: v('productsCta'),
      cta_href: 'products',
    },
    portfolio_teaser: {
      title: v('workTitle'),
      subtitle: v('workSubtitle'),
      details_label: v('workDetails'),
      cta_label: v('workCta'),
      cta_href: 'portfolio',
      limit: 6,
      empty: t('site.landing.portfolioEmpty'),
    },
    process: {
      title: v('processTitle'),
      anchor: 'process',
      steps: HOME_STEPS.map((k, i) => ({ text: v(`step.${k}`), highlight: i === HOME_STEPS.length - 1 })),
      cta_label: v('processCta'),
      cta_href: 'consultation',
    },
    academy: {
      title: v('academyTitle'),
      subtitle: v('academySubtitle'),
      text: v('academyText'),
      cta_label: v('academyCta'),
      cta_href: 'academy',
      fallback_courses: HOME_COURSES.map((c) => ({ title: v(`course.${c.key}`), slug: c.slug })),
    },
    blog: {
      title: v('magazineTitle'),
      subtitle: v('magazineSubtitle'),
      read_more: v('readMore'),
      cta_label: v('magazineCta'),
      cta_href: 'blog',
      limit: 4,
      empty: v('magazineEmpty'),
    },
    why: { title: v('whyTitle'), items: HOME_WHY.map((k) => ({ text: v(`why.${k}`) })) },
    testimonials: { title: v('testimonialsTitle'), empty: v('testimonialsEmpty'), limit: 8, autoplay: true, interval: 6, show_stars: true, show_arrows: true },
    consultation_cta: {
      title: v('ctaTitle'),
      text_1: v('ctaText1'),
      text_2: v('ctaText2'),
      buttons: [
        { label: v('ctaBtn1'), href: 'consultation', style: 'primary' },
        { label: v('ctaBtn2'), href: 'proposal', style: 'ghost' },
        { label: v('ctaBtn3'), href: 'contact', style: 'ghost' },
      ],
    },
  };
}

function megaToNav(mega: MegaDef, t: T): NavItemData {
  return {
    label: t(mega.labelKey),
    href: mega.href,
    columns: mega.columns.map((c) => ({
      title: t(c.titleKey),
      lead: t(c.leadKey),
      href: c.href,
      icon: c.icon,
      items: c.items.map((i) => ({ label: t(i.labelKey), href: i.href, icon: i.icon })),
    })),
  };
}

export function headerDefaults(t: T): HeaderData {
  return {
    cta_label: t('site.nav.freeConsultation'),
    cta_href: 'consultation',
    mobile_secondary_label: t('site.nav.proposal'),
    mobile_secondary_href: 'proposal',
    promo_small: t('site.nav.freeConsultation'),
    promo_title: t('site.landing.finalTitle'),
    panel_hint: t('site.mega.panelHint'),
    see_all: t('site.mega.seeAll'),
    nav: [
      megaToNav(SERVICE_MEGA, t),
      megaToNav(SOLUTION_MEGA, t),
      { label: t('site.nav.portfolio'), href: 'portfolio', columns: [] },
      megaToNav(RESOURCE_MEGA, t),
      megaToNav(COMPANY_MEGA, t),
    ],
  };
}

export function footerDefaults(t: T): FooterData {
  return {
    tagline: t('site.footer.agencyTagline'),
    description: t('site.footer.tagline'),
    cta_label: t('site.v2.heroCta1'),
    cta_href: 'consultation',
    quick_title: t('site.v2.footerQuick'),
    quick_links: FOOTER_QUICK_LINKS.map((l) => ({ label: t(`site.v2.quick.${l.key}`), href: l.href })),
    contact_title: t('site.v2.footerContact'),
    contact_page_label: t('site.v2.footerContactPage'),
    contact_page_href: 'contact',
    social_title: t('site.v2.footerSocial'),
    social_empty: t('site.v2.footerSocialEmpty'),
    legal_links: [
      { label: t('site.footer.terms'), href: 'pages/terms' },
      { label: t('site.footer.privacy'), href: 'pages/privacy' },
      { label: t('site.footer.conflict'), href: 'pages/conflict-of-interest' },
    ],
    copyright: `© {year} {site}. ${t('site.footer.rights')}`,
    legal_name: t('site.footer.legalName'),
    trust_title: t('site.footer.trustTitle'),
    trust_lead: t('site.footer.trustLead'),
  };
}

function isObj(v: unknown): v is Record<string, unknown> {
  return Boolean(v) && typeof v === 'object' && !Array.isArray(v);
}

/** CMS values win when they are non-empty; arrays replace whole lists. */
export function mergeContent<D extends object>(defaults: D, cms: unknown): D {
  if (!isObj(cms)) return defaults;
  const out: Record<string, unknown> = { ...(defaults as Record<string, unknown>) };
  for (const [key, value] of Object.entries(cms)) {
    if (value === null || value === undefined || value === '') continue;
    if (Array.isArray(value) && value.length === 0 && Array.isArray(out[key])) continue;
    out[key] = value;
  }
  return out as D;
}

export type RawBlock = { type: string; enabled?: boolean; props?: Record<string, unknown> | null };
export type ResolvedBlock = { [K in HomeBlockType]: { type: K; props: HomeProps[K] } }[HomeBlockType];

/** Ordered, enabled homepage blocks with props merged over the defaults. */
export function resolveHomeBlocks(raw: RawBlock[] | undefined | null, t: T, useCms: boolean): ResolvedBlock[] {
  const defaults = homeDefaults(t);
  const known = new Set<string>(HOME_BLOCK_ORDER);
  const rows: RawBlock[] = raw?.length ? raw.filter((b) => known.has(b.type)) : HOME_BLOCK_ORDER.map((type) => ({ type, enabled: true }));
  const seen = new Set<string>();
  const out: ResolvedBlock[] = [];
  for (const row of rows) {
    if (seen.has(row.type) || row.enabled === false) {
      seen.add(row.type);
      continue;
    }
    seen.add(row.type);
    const type = row.type as HomeBlockType;
    const props = useCms ? mergeContent(defaults[type], row.props) : defaults[type];
    out.push({ type, props } as ResolvedBlock);
  }
  return out;
}

export function normalizeSocials(raw: unknown): SocialLink[] {
  if (Array.isArray(raw)) {
    return raw
      .map((row) => {
        if (!isObj(row)) return null;
        const href = String(row.href ?? row.url ?? '');
        const label = String(row.label ?? row.name ?? row.platform ?? href);
        return href ? { label, href, handle: row.handle ? String(row.handle) : undefined, icon: row.icon ? String(row.icon) : undefined } : null;
      })
      .filter(Boolean) as SocialLink[];
  }
  if (isObj(raw)) {
    return Object.entries(raw)
      .filter(([, v]) => typeof v === 'string' && v)
      .map(([k, v]) => ({ label: k, href: String(v) }));
  }
  return [];
}

export function normalizeContact(raw: unknown): ContactData | null {
  if (!isObj(raw)) return null;
  const items = Array.isArray(raw.items)
    ? (raw.items as unknown[]).filter(isObj).map((i) => ({ label: String(i.label ?? ''), value: String(i.value ?? ''), href: i.href ? String(i.href) : undefined }))
    : [];
  if (raw.phone) items.push({ label: '', value: String(raw.phone), href: `tel:${String(raw.phone)}` });
  return {
    items: items.filter((i) => i.value),
    email: raw.email ? String(raw.email) : undefined,
    address: raw.address ? String(raw.address) : undefined,
  };
}

/** `services/x` → site href; `#id`, `http…`, `tel:`, `mailto:` are kept as they are. */
export function isExternalHref(href: string) {
  return /^(https?:|tel:|mailto:|#)/.test(href);
}

// ---------------------------------------------------------------------------
// Page-builder schemas (labels are Persian: the editor is a Persian dashboard).
// ---------------------------------------------------------------------------

export type FieldSpec =
  | { key: string; label: string; kind: 'text' | 'textarea' | 'url' | 'icon' | 'switch' | 'number' }
  | { key: string; label: string; kind: 'select'; options: { value: string; label: string }[] }
  | { key: string; label: string; kind: 'list'; fields: FieldSpec[]; itemTitle?: string };

const BUTTON_FIELDS: FieldSpec[] = [
  { key: 'label', label: 'متن دکمه', kind: 'text' },
  { key: 'href', label: 'پیوند', kind: 'url' },
  {
    key: 'style',
    label: 'سبک',
    kind: 'select',
    options: [
      { value: 'primary', label: 'اصلی (آبی)' },
      { value: 'ghost', label: 'خطی' },
      { value: 'link', label: 'پیوند متنی' },
    ],
  },
];
const LINK_FIELDS: FieldSpec[] = [
  { key: 'label', label: 'عنوان', kind: 'text' },
  { key: 'href', label: 'پیوند', kind: 'url' },
];
const SECTION_HEAD: FieldSpec[] = [
  { key: 'title', label: 'عنوان بخش', kind: 'text' },
  { key: 'subtitle', label: 'زیرعنوان', kind: 'textarea' },
];
const CTA: FieldSpec[] = [
  { key: 'cta_label', label: 'متن دکمه پایانی', kind: 'text' },
  { key: 'cta_href', label: 'پیوند دکمه پایانی', kind: 'url' },
];

export const HOME_BLOCK_SCHEMAS: Record<HomeBlockType, { label: string; hint?: string; fields: FieldSpec[] }> = {
  hero: {
    label: 'هیرو (بالای صفحه)',
    fields: [
      { key: 'title', label: 'تیتر اصلی', kind: 'text' },
      { key: 'subtitle_1', label: 'زیرتیتر ۱', kind: 'text' },
      { key: 'subtitle_2', label: 'زیرتیتر ۲ (آبی)', kind: 'text' },
      { key: 'text_1', label: 'متن ۱', kind: 'textarea' },
      { key: 'text_2', label: 'متن ۲', kind: 'textarea' },
      { key: 'buttons', label: 'دکمه‌ها', kind: 'list', fields: BUTTON_FIELDS, itemTitle: 'label' },
      {
        key: 'stats',
        label: 'اعداد شناور',
        kind: 'list',
        itemTitle: 'label',
        fields: [
          {
            key: 'source',
            label: 'منبع عدد',
            kind: 'select',
            options: [
              { value: 'portfolio_count', label: 'تعداد نمونه‌کارهای منتشرشده' },
              { value: 'service_count', label: 'تعداد خدمات منوی خدمات' },
              { value: 'industry_count', label: 'تعداد صنایع راهکارها' },
              { value: 'highlight_result', label: 'نتیجه برترین نمونه‌کار' },
              { value: 'manual', label: 'دستی (مقدار پایین)' },
            ],
          },
          { key: 'label', label: 'برچسب', kind: 'text' },
          { key: 'value', label: 'مقدار دستی', kind: 'text' },
        ],
      },
      { key: 'dock_label', label: 'متن نوار پایین موبایل', kind: 'text' },
      { key: 'dock_cta_label', label: 'دکمه نوار موبایل', kind: 'text' },
      { key: 'dock_cta_href', label: 'پیوند نوار موبایل', kind: 'url' },
    ],
  },
  about: {
    label: 'وبینا چیست؟',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'body', label: 'متن', kind: 'textarea' },
      { key: 'list_lead', label: 'مقدمه فهرست', kind: 'text' },
      { key: 'points', label: 'موارد فهرست', kind: 'list', itemTitle: 'text', fields: [{ key: 'text', label: 'متن', kind: 'text' }] },
      { key: 'closing', label: 'جمله پایانی', kind: 'text' },
    ],
  },
  services: {
    label: 'خدمات',
    fields: [
      ...SECTION_HEAD,
      {
        key: 'items',
        label: 'کارت‌های خدمات',
        kind: 'list',
        itemTitle: 'title',
        fields: [
          { key: 'title', label: 'عنوان', kind: 'text' },
          { key: 'body', label: 'توضیح', kind: 'textarea' },
          { key: 'href', label: 'پیوند', kind: 'url' },
          { key: 'icon', label: 'آیکن', kind: 'icon' },
          { key: 'vip', label: 'کارت ویژه (تیره)', kind: 'switch' },
        ],
      },
      ...CTA,
    ],
  },
  products: {
    label: 'محصولات و ماژول‌ها',
    hint: 'این فهرست در صفحه محصولات هم نمایش داده می‌شود.',
    fields: [
      ...SECTION_HEAD,
      { key: 'intro', label: 'مقدمه', kind: 'textarea' },
      { key: 'page_heading', label: 'عنوان در صفحه محصولات', kind: 'text' },
      {
        key: 'items',
        label: 'ماژول‌ها',
        kind: 'list',
        itemTitle: 'title',
        fields: [
          { key: 'title', label: 'عنوان', kind: 'text' },
          { key: 'anchor', label: 'شناسه لنگر در صفحه محصولات', kind: 'text' },
          { key: 'icon', label: 'آیکن', kind: 'icon' },
        ],
      },
      ...CTA,
    ],
  },
  portfolio_teaser: {
    label: 'نمونه‌کارها',
    hint: 'کارت‌ها از نمونه‌کارهای منتشرشده خوانده می‌شوند.',
    fields: [
      ...SECTION_HEAD,
      { key: 'details_label', label: 'متن «مشاهده جزئیات»', kind: 'text' },
      { key: 'limit', label: 'تعداد کارت', kind: 'number' },
      { key: 'empty', label: 'متن خالی', kind: 'text' },
      ...CTA,
    ],
  },
  process: {
    label: 'مسیر همکاری',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'anchor', label: 'شناسه لنگر (برای #process)', kind: 'text' },
      {
        key: 'steps',
        label: 'مراحل',
        kind: 'list',
        itemTitle: 'text',
        fields: [
          { key: 'text', label: 'متن', kind: 'text' },
          { key: 'highlight', label: 'برجسته', kind: 'switch' },
        ],
      },
      ...CTA,
    ],
  },
  academy: {
    label: 'آکادمی',
    hint: 'دوره‌های منتشرشده آکادمی نمایش داده می‌شوند؛ فهرست زیر فقط وقتی دوره‌ای نباشد.',
    fields: [
      ...SECTION_HEAD,
      { key: 'text', label: 'متن', kind: 'textarea' },
      {
        key: 'fallback_courses',
        label: 'دوره‌های جایگزین',
        kind: 'list',
        itemTitle: 'title',
        fields: [
          { key: 'title', label: 'عنوان', kind: 'text' },
          { key: 'slug', label: 'نامک', kind: 'text' },
        ],
      },
      ...CTA,
    ],
  },
  blog: {
    label: 'مجله',
    hint: 'آخرین نوشته‌های منتشرشده بلاگ نمایش داده می‌شوند.',
    fields: [
      ...SECTION_HEAD,
      { key: 'read_more', label: 'متن «ادامه مطلب»', kind: 'text' },
      { key: 'limit', label: 'تعداد نوشته', kind: 'number' },
      { key: 'empty', label: 'متن خالی', kind: 'text' },
      ...CTA,
    ],
  },
  why: {
    label: 'چرا وبینا',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'items', label: 'دلایل', kind: 'list', itemTitle: 'text', fields: [{ key: 'text', label: 'متن', kind: 'text' }] },
    ],
  },
  testimonials: {
    label: 'نظرات مشتریان',
    hint: 'اسلایدر نظرات با عکس؛ نظرها و تصویرها از بخش «نظرات مشتریان» داشبورد خوانده می‌شوند.',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'limit', label: 'تعداد', kind: 'number' },
      { key: 'autoplay', label: 'پخش خودکار اسلایدر', kind: 'switch' },
      { key: 'interval', label: 'فاصله اسلاید (ثانیه)', kind: 'number' },
      { key: 'show_stars', label: 'نمایش ستاره‌ها', kind: 'switch' },
      { key: 'show_arrows', label: 'نمایش فلش‌ها', kind: 'switch' },
      { key: 'empty', label: 'متن خالی', kind: 'text' },
    ],
  },
  consultation_cta: {
    label: 'دعوت پایانی',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'text_1', label: 'متن ۱', kind: 'textarea' },
      { key: 'text_2', label: 'متن ۲', kind: 'textarea' },
      { key: 'buttons', label: 'دکمه‌ها', kind: 'list', fields: BUTTON_FIELDS, itemTitle: 'label' },
    ],
  },
};

export const HEADER_SCHEMA: FieldSpec[] = [
  { key: 'cta_label', label: 'دکمه سربرگ', kind: 'text' },
  { key: 'cta_href', label: 'پیوند دکمه', kind: 'url' },
  { key: 'mobile_secondary_label', label: 'دکمه دوم منوی موبایل', kind: 'text' },
  { key: 'mobile_secondary_href', label: 'پیوند دکمه دوم', kind: 'url' },
  { key: 'promo_small', label: 'کارت تبلیغ منو: برچسب', kind: 'text' },
  { key: 'promo_title', label: 'کارت تبلیغ منو: عنوان', kind: 'text' },
  { key: 'panel_hint', label: 'راهنمای پایین منو', kind: 'text' },
  { key: 'see_all', label: 'متن «مشاهده همه»', kind: 'text' },
  {
    key: 'nav',
    label: 'منوی اصلی',
    kind: 'list',
    itemTitle: 'label',
    fields: [
      { key: 'label', label: 'عنوان', kind: 'text' },
      { key: 'href', label: 'پیوند', kind: 'url' },
      {
        key: 'columns',
        label: 'ستون‌های مگامنو (خالی = پیوند ساده)',
        kind: 'list',
        itemTitle: 'title',
        fields: [
          { key: 'title', label: 'عنوان ستون', kind: 'text' },
          { key: 'lead', label: 'توضیح', kind: 'text' },
          { key: 'href', label: 'پیوند ستون', kind: 'url' },
          { key: 'icon', label: 'آیکن', kind: 'icon' },
          {
            key: 'items',
            label: 'پیوندها',
            kind: 'list',
            itemTitle: 'label',
            fields: [...LINK_FIELDS, { key: 'icon', label: 'آیکن', kind: 'icon' }],
          },
        ],
      },
    ],
  },
];

export const FOOTER_SCHEMA: FieldSpec[] = [
  { key: 'tagline', label: 'شعار زیر لوگو', kind: 'text' },
  { key: 'description', label: 'توضیح کوتاه', kind: 'textarea' },
  { key: 'cta_label', label: 'دکمه پابرگ', kind: 'text' },
  { key: 'cta_href', label: 'پیوند دکمه', kind: 'url' },
  { key: 'quick_title', label: 'عنوان لینک‌های سریع', kind: 'text' },
  { key: 'quick_links', label: 'لینک‌های سریع', kind: 'list', itemTitle: 'label', fields: LINK_FIELDS },
  { key: 'contact_title', label: 'عنوان ستون تماس', kind: 'text' },
  { key: 'contact_page_label', label: 'متن پیوند صفحه تماس', kind: 'text' },
  { key: 'contact_page_href', label: 'پیوند صفحه تماس', kind: 'url' },
  { key: 'social_title', label: 'عنوان شبکه‌ها', kind: 'text' },
  { key: 'social_empty', label: 'متن نبودِ شبکه', kind: 'text' },
  { key: 'legal_links', label: 'پیوندهای حقوقی', kind: 'list', itemTitle: 'label', fields: LINK_FIELDS },
  { key: 'copyright', label: 'کپی‌رایت ({year} و {site} جایگزین می‌شوند)', kind: 'text' },
  { key: 'legal_name', label: 'نام حقوقی', kind: 'text' },
  { key: 'trust_title', label: 'عنوان نشان‌های اعتماد', kind: 'text' },
  { key: 'trust_lead', label: 'توضیح نشان‌ها', kind: 'textarea' },
];

export const CONTACT_SCHEMA: FieldSpec[] = [
  {
    key: 'items',
    label: 'شماره‌ها و راه‌های تماس',
    kind: 'list',
    itemTitle: 'label',
    fields: [
      { key: 'label', label: 'عنوان', kind: 'text' },
      { key: 'value', label: 'مقدار نمایشی', kind: 'text' },
      { key: 'href', label: 'پیوند (tel:، https://…)', kind: 'url' },
    ],
  },
  { key: 'email', label: 'ایمیل', kind: 'text' },
  { key: 'address', label: 'نشانی', kind: 'textarea' },
];

export const SOCIAL_FIELDS: FieldSpec[] = [
  { key: 'label', label: 'نام شبکه', kind: 'text' },
  { key: 'handle', label: 'شناسه نمایشی', kind: 'text' },
  { key: 'href', label: 'پیوند', kind: 'url' },
  { key: 'icon', label: 'آیکن', kind: 'icon' },
];

export const BADGE_FIELDS: FieldSpec[] = [
  { key: 'id', label: 'شناسه', kind: 'text' },
  { key: 'label', label: 'عنوان', kind: 'text' },
  { key: 'hint', label: 'توضیح', kind: 'textarea' },
  { key: 'href', label: 'پیوند', kind: 'url' },
  { key: 'image', label: 'نشانی تصویر نماد', kind: 'url' },
  { key: 'alt', label: 'متن جایگزین تصویر', kind: 'text' },
  { key: 'referrerpolicy', label: 'referrerpolicy (برای اینماد: origin)', kind: 'text' },
  { key: 'code', label: 'کد نماد (ویژگی code تصویر، اختیاری)', kind: 'text' },
  { key: 'embed', label: 'کد جاسازی (اختیاری؛ پیوند/تصویر/کد از آن خوانده می‌شود)', kind: 'textarea' },
];

// ---------------------------------------------------------------------------
// Contact page (/contact): stored in `branding.contact_page`.
// ---------------------------------------------------------------------------

export type ContactChannel = { label: string; value: string; href?: string; icon?: string; action_label?: string; note?: string };
export type ContactPageData = {
  hero_kicker: string;
  hero_title: string;
  hero_lead: string;
  hero_buttons: ButtonItem[];
  channels_title: string;
  channels_lead: string;
  channels: ContactChannel[];
  copy_label: string;
  copied_label: string;
  form_enabled: boolean;
  form_slug: string;
  form_title: string;
  form_lead: string;
  submit_label: string;
  success_title: string;
  success_text: string;
  again_label: string;
  privacy_note: string;
  map_enabled: boolean;
  map_title: string;
  map_address: string;
  map_lat: string;
  map_lng: string;
  map_zoom: number;
  map_embed_url: string;
  map_directions_url: string;
  map_links: LinkItem[];
  map_empty_text: string;
  hours_title: string;
  hours: { days: string; time: string }[];
  hours_note: string;
  hours_empty: string;
  social_title: string;
  social_lead: string;
  faq_enabled: boolean;
  faq_title: string;
  faq_limit: number;
  faq_items: { question: string; answer: string }[];
  cta_title: string;
  cta_text: string;
  cta_buttons: ButtonItem[];
};

export function contactPageDefaults(t: T): ContactPageData {
  const c = (k: string) => t(`site.contactPage.${k}`);
  return {
    hero_kicker: c('heroKicker'),
    hero_title: c('heroTitle'),
    hero_lead: c('heroLead'),
    hero_buttons: [
      { label: c('heroPrimary'), href: '#contact-form', style: 'primary' },
      { label: c('heroSecondary'), href: 'consultation', style: 'ghost' },
    ],
    channels_title: c('channelsTitle'),
    channels_lead: c('channelsLead'),
    channels: [],
    copy_label: c('copy'),
    copied_label: c('copied'),
    form_enabled: true,
    form_slug: 'contact',
    form_title: c('formTitle'),
    form_lead: c('formLead'),
    submit_label: c('submit'),
    success_title: c('successTitle'),
    success_text: c('successText'),
    again_label: c('again'),
    privacy_note: c('privacy'),
    map_enabled: true,
    map_title: c('mapTitle'),
    map_address: '',
    map_lat: '',
    map_lng: '',
    map_zoom: 15,
    map_embed_url: '',
    map_directions_url: '',
    map_links: [],
    map_empty_text: c('mapEmpty'),
    hours_title: c('hoursTitle'),
    hours: [],
    hours_note: '',
    hours_empty: c('hoursEmpty'),
    social_title: c('socialTitle'),
    social_lead: c('socialLead'),
    faq_enabled: true,
    faq_title: c('faqTitle'),
    faq_limit: 4,
    faq_items: [],
    cta_title: c('ctaTitle'),
    cta_text: c('ctaText'),
    cta_buttons: [
      { label: c('ctaPrimary'), href: 'consultation', style: 'primary' },
      { label: c('ctaSecondary'), href: 'proposal', style: 'ghost' },
    ],
  };
}

/** Icon for a channel when none is set, guessed from its link. */
export function channelIcon(channel: { href?: string; icon?: string }): string {
  if (channel.icon) return channel.icon;
  const href = channel.href ?? '';
  if (href.startsWith('tel:')) return 'Phone';
  if (href.startsWith('mailto:')) return 'Mail';
  if (href.includes('wa.me') || href.includes('whatsapp')) return 'MessageCircle';
  if (href.includes('t.me')) return 'Send';
  if (href.includes('instagram')) return 'Instagram';
  if (href.includes('linkedin')) return 'Linkedin';
  return 'MessageSquare';
}

export const CONTACT_PAGE_SCHEMA: FieldSpec[] = [
  { key: 'hero_kicker', label: 'هیرو: برچسب بالا', kind: 'text' },
  { key: 'hero_title', label: 'هیرو: عنوان', kind: 'text' },
  { key: 'hero_lead', label: 'هیرو: متن', kind: 'textarea' },
  { key: 'hero_buttons', label: 'هیرو: دکمه‌ها (#contact-form = فرم همین صفحه)', kind: 'list', fields: BUTTON_FIELDS, itemTitle: 'label' },
  { key: 'channels_title', label: 'کارت‌های تماس: عنوان', kind: 'text' },
  { key: 'channels_lead', label: 'کارت‌های تماس: توضیح', kind: 'textarea' },
  {
    key: 'channels',
    label: 'کارت‌های تماس (خالی = از «تماس و شبکه‌ها»)',
    kind: 'list',
    itemTitle: 'label',
    fields: [
      { key: 'label', label: 'عنوان', kind: 'text' },
      { key: 'value', label: 'مقدار نمایشی (کپی می‌شود)', kind: 'text' },
      { key: 'href', label: 'پیوند (tel:، https://wa.me/…)', kind: 'url' },
      { key: 'icon', label: 'آیکن', kind: 'icon' },
      { key: 'action_label', label: 'متن دکمه', kind: 'text' },
      { key: 'note', label: 'توضیح کوتاه', kind: 'text' },
    ],
  },
  { key: 'copy_label', label: 'متن دکمه کپی', kind: 'text' },
  { key: 'copied_label', label: 'متن «کپی شد»', kind: 'text' },
  { key: 'form_enabled', label: 'نمایش فرم', kind: 'switch' },
  { key: 'form_slug', label: 'نامک فرم (بازاریابی ← فرم‌ها)', kind: 'text' },
  { key: 'form_title', label: 'فرم: عنوان', kind: 'text' },
  { key: 'form_lead', label: 'فرم: توضیح', kind: 'textarea' },
  { key: 'submit_label', label: 'فرم: دکمه ارسال', kind: 'text' },
  { key: 'success_title', label: 'فرم: عنوان موفقیت', kind: 'text' },
  { key: 'success_text', label: 'فرم: متن موفقیت (خالی = پیام فرم)', kind: 'textarea' },
  { key: 'again_label', label: 'فرم: دکمه پیام دوباره', kind: 'text' },
  { key: 'privacy_note', label: 'فرم: یادداشت حریم خصوصی', kind: 'text' },
  { key: 'map_enabled', label: 'نمایش بخش نقشه', kind: 'switch' },
  { key: 'map_title', label: 'نقشه: عنوان', kind: 'text' },
  { key: 'map_address', label: 'نقشه: نشانی', kind: 'textarea' },
  { key: 'map_lat', label: 'نقشه: عرض جغرافیایی (lat)', kind: 'text' },
  { key: 'map_lng', label: 'نقشه: طول جغرافیایی (lng)', kind: 'text' },
  { key: 'map_zoom', label: 'نقشه: بزرگ‌نمایی (۳ تا ۱۹)', kind: 'number' },
  { key: 'map_embed_url', label: 'نقشه: نشانی جاسازی نشان یا بلد (اختیاری)', kind: 'url' },
  { key: 'map_directions_url', label: 'نقشه: پیوند مسیریابی (اختیاری)', kind: 'url' },
  { key: 'map_links', label: 'نقشه: دکمه‌های مسیریابی (نشان، بلد، گوگل…)', kind: 'list', fields: LINK_FIELDS, itemTitle: 'label' },
  { key: 'map_empty_text', label: 'نقشه: متن وقتی نشانی نیست', kind: 'textarea' },
  { key: 'hours_title', label: 'ساعت کاری: عنوان', kind: 'text' },
  {
    key: 'hours',
    label: 'ساعت کاری',
    kind: 'list',
    itemTitle: 'days',
    fields: [
      { key: 'days', label: 'روزها', kind: 'text' },
      { key: 'time', label: 'ساعت', kind: 'text' },
    ],
  },
  { key: 'hours_note', label: 'ساعت کاری: یادداشت', kind: 'text' },
  { key: 'hours_empty', label: 'ساعت کاری: متن خالی', kind: 'text' },
  { key: 'social_title', label: 'شبکه‌ها: عنوان', kind: 'text' },
  { key: 'social_lead', label: 'شبکه‌ها: توضیح', kind: 'text' },
  { key: 'faq_enabled', label: 'نمایش پرسش‌ها', kind: 'switch' },
  { key: 'faq_title', label: 'پرسش‌ها: عنوان', kind: 'text' },
  { key: 'faq_limit', label: 'پرسش‌ها: تعداد', kind: 'number' },
  {
    key: 'faq_items',
    label: 'پرسش‌های اختصاصی (خالی = از بخش FAQ داشبورد)',
    kind: 'list',
    itemTitle: 'question',
    fields: [
      { key: 'question', label: 'پرسش', kind: 'text' },
      { key: 'answer', label: 'پاسخ', kind: 'textarea' },
    ],
  },
  { key: 'cta_title', label: 'دعوت پایانی: عنوان', kind: 'text' },
  { key: 'cta_text', label: 'دعوت پایانی: متن', kind: 'textarea' },
  { key: 'cta_buttons', label: 'دعوت پایانی: دکمه‌ها', kind: 'list', fields: BUTTON_FIELDS, itemTitle: 'label' },
];

// ---------------------------------------------------------------------------
// Official company record: `branding.company` (contact page, about page, footer).
// ---------------------------------------------------------------------------

export type CompanyData = {
  name: string;
  legal_type: string;
  registered_at: string;
  city: string;
  registration_no: string;
  national_id: string;
  activity: string;
  address: string;
  show_in_footer: boolean;
};

export function normalizeCompany(raw: unknown): CompanyData | null {
  if (!isObj(raw) || !raw.name) return null;
  const s = (k: string) => (raw[k] === null || raw[k] === undefined ? '' : String(raw[k]));
  return {
    name: s('name'),
    legal_type: s('legal_type'),
    registered_at: s('registered_at'),
    city: s('city'),
    registration_no: s('registration_no'),
    national_id: s('national_id'),
    activity: s('activity'),
    address: s('address'),
    show_in_footer: raw.show_in_footer !== false,
  };
}

export const COMPANY_SCHEMA: FieldSpec[] = [
  { key: 'name', label: 'نام شرکت', kind: 'text' },
  { key: 'legal_type', label: 'نوع شرکت', kind: 'text' },
  { key: 'registered_at', label: 'تاریخ ثبت', kind: 'text' },
  { key: 'city', label: 'محل ثبت', kind: 'text' },
  { key: 'registration_no', label: 'شماره ثبت', kind: 'text' },
  { key: 'national_id', label: 'شناسه ملی', kind: 'text' },
  { key: 'activity', label: 'موضوع فعالیت', kind: 'text' },
  { key: 'address', label: 'نشانی دفتر مرکزی', kind: 'textarea' },
  { key: 'show_in_footer', label: 'نمایش در پابرگ', kind: 'switch' },
];
