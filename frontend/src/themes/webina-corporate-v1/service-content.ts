/**
 * Service landing widgets (`webina-service-*`): schemas only.
 * All copy lives in the page-builder document props — nothing is hardcoded in theme routes.
 */
import type { ButtonItem, FieldSpec } from './site-content';

type Icon = { icon?: string };

export type ServiceProps = {
  hero: {
    kicker: string;
    title: string;
    subtitle: string;
    text: string;
    buttons: ButtonItem[];
    tags: { text: string }[];
    dock_label?: string;
    dock_cta_label?: string;
    dock_cta_href?: string;
  };
  problem: {
    title: string;
    lead: string;
    pains: { text: string }[];
    result: string;
    closing: string;
    before_label: string;
    after_label: string;
    before_items: { text: string }[];
    after_items: { text: string }[];
  };
  define: {
    title: string;
    body: string;
    closing: string;
    cards: ({ title: string; text: string } & Icon)[];
  };
  audience: {
    title: string;
    items: { text: string }[];
    soft_no: string;
    closing: string;
  };
  deliverables: {
    title: string;
    items: { text: string }[];
  };
  process: {
    title: string;
    steps: { title: string; text: string }[];
  };
  compare: {
    title: string;
    left_title: string;
    right_title: string;
    rows: { left: string; right: string }[];
  };
  portfolio: {
    title: string;
    empty: string;
    cta_label: string;
    cta_href: string;
    items: { title: string; href?: string; image?: string; tag?: string }[];
  };
  trust: {
    title: string;
    items: ({ title: string; text: string } & Icon)[];
  };
  faq: {
    title: string;
    items: { q: string; a: string }[];
  };
  cta: {
    title: string;
    text: string;
    buttons: ButtonItem[];
  };
  exclude: {
    title: string;
    items: { text: string }[];
    note: string;
  };
  quote: {
    text: string;
    attribution: string;
  };
};

export type ServiceType = keyof ServiceProps;

const BTN: FieldSpec[] = [
  { key: 'label', label: 'متن دکمه', kind: 'text' },
  { key: 'href', label: 'پیوند', kind: 'url' },
  {
    key: 'style',
    label: 'سبک',
    kind: 'select',
    options: [
      { value: 'primary', label: 'اصلی' },
      { value: 'ghost', label: 'خطی' },
      { value: 'link', label: 'پیوند متنی' },
    ],
  },
];
const TEXT_LIST = (key: string, label: string): FieldSpec => ({
  key,
  label,
  kind: 'list',
  itemTitle: 'text',
  fields: [{ key: 'text', label: 'متن', kind: 'textarea' }],
});
const CARD_LIST = (key: string, label: string): FieldSpec => ({
  key,
  label,
  kind: 'list',
  itemTitle: 'title',
  fields: [
    { key: 'title', label: 'عنوان', kind: 'text' },
    { key: 'text', label: 'توضیح', kind: 'textarea' },
    { key: 'icon', label: 'آیکن', kind: 'icon' },
  ],
});

export const SERVICE_SECTION_SCHEMAS: Record<ServiceType, { label: string; hint?: string; fields: FieldSpec[] }> = {
  hero: {
    label: 'هیرو خدمت',
    fields: [
      { key: 'kicker', label: 'کیکر', kind: 'text' },
      { key: 'title', label: 'عنوان H1', kind: 'text' },
      { key: 'subtitle', label: 'زیرعنوان', kind: 'textarea' },
      { key: 'text', label: 'متن کوتاه', kind: 'textarea' },
      { key: 'buttons', label: 'دکمه‌ها', kind: 'list', itemTitle: 'label', fields: BTN },
      TEXT_LIST('tags', 'برچسب‌های شناور'),
      { key: 'dock_label', label: 'متن نوار موبایل', kind: 'text' },
      { key: 'dock_cta_label', label: 'دکمه نوار موبایل', kind: 'text' },
      { key: 'dock_cta_href', label: 'پیوند نوار موبایل', kind: 'url' },
    ],
  },
  problem: {
    label: 'داستان مشکل',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'lead', label: 'مقدمه', kind: 'textarea' },
      TEXT_LIST('pains', 'دردها'),
      { key: 'result', label: 'نتیجه منفی', kind: 'textarea' },
      { key: 'closing', label: 'جمع‌بندی', kind: 'textarea' },
      { key: 'before_label', label: 'برچسب قبل', kind: 'text' },
      { key: 'after_label', label: 'برچسب بعد', kind: 'text' },
      TEXT_LIST('before_items', 'موارد قبل'),
      TEXT_LIST('after_items', 'موارد بعد'),
    ],
  },
  define: {
    label: 'تعریف خدمت',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'body', label: 'متن', kind: 'textarea' },
      { key: 'closing', label: 'جمله پایانی', kind: 'textarea' },
      CARD_LIST('cards', 'کارت‌های ویژگی'),
    ],
  },
  audience: {
    label: 'مخاطب هدف',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      TEXT_LIST('items', 'موارد'),
      { key: 'soft_no', label: 'جمله انصراف نرم', kind: 'textarea' },
      { key: 'closing', label: 'جمع‌بندی', kind: 'textarea' },
    ],
  },
  deliverables: {
    label: 'خروجی‌ها',
    fields: [{ key: 'title', label: 'عنوان', kind: 'text' }, TEXT_LIST('items', 'چک‌لیست')],
  },
  process: {
    label: 'فرآیند کار',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      {
        key: 'steps',
        label: 'مراحل',
        kind: 'list',
        itemTitle: 'title',
        fields: [
          { key: 'title', label: 'عنوان', kind: 'text' },
          { key: 'text', label: 'توضیح', kind: 'textarea' },
        ],
      },
    ],
  },
  compare: {
    label: 'جدول مقایسه',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'left_title', label: 'ستون چپ (قالب)', kind: 'text' },
      { key: 'right_title', label: 'ستون راست (اختصاصی)', kind: 'text' },
      {
        key: 'rows',
        label: 'ردیف‌ها',
        kind: 'list',
        itemTitle: 'left',
        fields: [
          { key: 'left', label: 'چپ', kind: 'text' },
          { key: 'right', label: 'راست', kind: 'text' },
        ],
      },
    ],
  },
  portfolio: {
    label: 'نمونه‌کار مرتبط',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'empty', label: 'متن خالی', kind: 'text' },
      { key: 'cta_label', label: 'متن CTA', kind: 'text' },
      { key: 'cta_href', label: 'پیوند CTA', kind: 'url' },
      {
        key: 'items',
        label: 'کارت‌ها',
        kind: 'list',
        itemTitle: 'title',
        fields: [
          { key: 'title', label: 'عنوان', kind: 'text' },
          { key: 'tag', label: 'برچسب', kind: 'text' },
          { key: 'href', label: 'پیوند', kind: 'url' },
          { key: 'image', label: 'تصویر', kind: 'url' },
        ],
      },
    ],
  },
  trust: {
    label: 'رویکرد و اعتماد',
    fields: [{ key: 'title', label: 'عنوان', kind: 'text' }, CARD_LIST('items', 'ارزش‌ها')],
  },
  faq: {
    label: 'سوالات متداول خدمت',
    hint: 'FAQ Schema برای سئو خودکار اضافه می‌شود.',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      {
        key: 'items',
        label: 'پرسش‌ها',
        kind: 'list',
        itemTitle: 'q',
        fields: [
          { key: 'q', label: 'پرسش', kind: 'text' },
          { key: 'a', label: 'پاسخ', kind: 'textarea' },
        ],
      },
    ],
  },
  cta: {
    label: 'CTA پایانی',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'text', label: 'متن', kind: 'textarea' },
      { key: 'buttons', label: 'دکمه‌ها', kind: 'list', itemTitle: 'label', fields: BTN },
    ],
  },
  exclude: {
    label: 'شامل نمی‌شود',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      TEXT_LIST('items', 'موارد'),
      { key: 'note', label: 'یادداشت', kind: 'textarea' },
    ],
  },
  quote: {
    label: 'نقل‌قول میانی',
    fields: [
      { key: 'text', label: 'متن', kind: 'textarea' },
      { key: 'attribution', label: 'ارجاع', kind: 'text' },
    ],
  },
};

export const SERVICE_ORDER: ServiceType[] = [
  'hero',
  'problem',
  'define',
  'audience',
  'deliverables',
  'process',
  'compare',
  'portfolio',
  'trust',
  'faq',
  'cta',
  'exclude',
  'quote',
];
