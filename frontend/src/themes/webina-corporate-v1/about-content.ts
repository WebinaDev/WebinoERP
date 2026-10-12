/**
 * About page (/about) content: `branding.about_page.sections` (page builder → درباره ما).
 * Defaults live in messages under `site.aboutPage` with the same shape.
 */
import type { ButtonItem, FieldSpec } from './site-content';

type Icon = { icon?: string };
export type AboutProps = {
  hero: { kicker: string; title: string; text: string; buttons: ButtonItem[]; tags: { text: string }[] };
  intro: { title: string; lead: string; logo_caption: string; facts: ({ label: string; value: string } & Icon)[] };
  story: { title: string; paragraphs: { text: string }[]; quote: string; path: { text: string }[] };
  mission: { title: string; items: ({ title: string; text: string } & Icon)[] };
  values: { title: string; items: ({ title: string; text: string } & Icon)[] };
  approach: { title: string; lead: string; steps: { title: string; text?: string }[]; outro: string };
  team: {
    title: string;
    text: string;
    roles: ({ title: string } & Icon)[];
    members: { name: string; role?: string; photo?: string; quote?: string }[];
    culture_title: string;
    culture: { text: string }[];
  };
  different: { title: string; items: { text: string }[] };
  cta: { title: string; text: string; buttons: ButtonItem[] };
};
export type AboutType = keyof AboutProps;
export type AboutSection = { [K in AboutType]: { type: K; enabled?: boolean; props: AboutProps[K] } }[AboutType];
export type AboutPageData = { sections: AboutSection[] };

const BTN: FieldSpec[] = [
  { key: 'label', label: 'متن دکمه', kind: 'text' },
  { key: 'href', label: 'پیوند', kind: 'url' },
  { key: 'style', label: 'سبک', kind: 'select', options: [{ value: 'primary', label: 'اصلی' }, { value: 'ghost', label: 'خطی' }, { value: 'link', label: 'پیوند متنی' }] },
];
const TEXT_LIST = (key: string, label: string): FieldSpec => ({ key, label, kind: 'list', itemTitle: 'text', fields: [{ key: 'text', label: 'متن', kind: 'textarea' }] });
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

export const ABOUT_SECTION_SCHEMAS: Record<AboutType, { label: string; hint?: string; fields: FieldSpec[] }> = {
  hero: {
    label: 'هیرو',
    fields: [
      { key: 'kicker', label: 'زیرعنوان', kind: 'text' },
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'text', label: 'متن کوتاه', kind: 'textarea' },
      { key: 'buttons', label: 'دکمه‌ها', kind: 'list', itemTitle: 'label', fields: BTN },
      TEXT_LIST('tags', 'برچسب‌های شناور'),
    ],
  },
  intro: {
    label: 'معرفی رسمی',
    hint: 'اطلاعات ثبتی از زبانه «اطلاعات شرکت» هم به کارت‌ها اضافه می‌شود.',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'lead', label: 'متن معرفی', kind: 'textarea' },
      { key: 'logo_caption', label: 'متن زیر لوگو', kind: 'text' },
      {
        key: 'facts',
        label: 'کارت‌های اطلاعات',
        kind: 'list',
        itemTitle: 'label',
        fields: [
          { key: 'label', label: 'عنوان', kind: 'text' },
          { key: 'value', label: 'مقدار', kind: 'text' },
          { key: 'icon', label: 'آیکن', kind: 'icon' },
        ],
      },
    ],
  },
  story: {
    label: 'داستان ما',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      TEXT_LIST('paragraphs', 'بندها (هر خط جدا نمایش داده می‌شود)'),
      { key: 'quote', label: 'نقل‌قول برجسته', kind: 'text' },
      TEXT_LIST('path', 'مسیر (ایده → … )'),
    ],
  },
  mission: { label: 'مأموریت و چشم‌انداز', fields: [{ key: 'title', label: 'عنوان', kind: 'text' }, CARD_LIST('items', 'کارت‌ها')] },
  values: { label: 'ارزش‌ها', fields: [{ key: 'title', label: 'عنوان', kind: 'text' }, CARD_LIST('items', 'ارزش‌ها')] },
  approach: {
    label: 'رویکرد ما',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'lead', label: 'مقدمه', kind: 'textarea' },
      {
        key: 'steps',
        label: 'مراحل',
        kind: 'list',
        itemTitle: 'title',
        fields: [
          { key: 'title', label: 'عنوان', kind: 'text' },
          { key: 'text', label: 'توضیح کوتاه (اختیاری)', kind: 'text' },
        ],
      },
      { key: 'outro', label: 'جمله پایانی', kind: 'textarea' },
    ],
  },
  team: {
    label: 'تیم وبینا',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'text', label: 'متن', kind: 'textarea' },
      {
        key: 'roles',
        label: 'نقش‌ها',
        kind: 'list',
        itemTitle: 'title',
        fields: [
          { key: 'title', label: 'عنوان', kind: 'text' },
          { key: 'icon', label: 'آیکن', kind: 'icon' },
        ],
      },
      {
        key: 'members',
        label: 'اعضای تیم (اختیاری؛ فقط افراد واقعی)',
        kind: 'list',
        itemTitle: 'name',
        fields: [
          { key: 'name', label: 'نام', kind: 'text' },
          { key: 'role', label: 'سمت', kind: 'text' },
          { key: 'photo', label: 'نشانی عکس', kind: 'url' },
          { key: 'quote', label: 'جمله', kind: 'text' },
        ],
      },
      { key: 'culture_title', label: 'عنوان فرهنگ تیمی', kind: 'text' },
      TEXT_LIST('culture', 'کلمات فرهنگ تیمی'),
    ],
  },
  different: { label: 'آنچه ما را متفاوت می‌کند', fields: [{ key: 'title', label: 'عنوان', kind: 'text' }, TEXT_LIST('items', 'موارد')] },
  cta: {
    label: 'دعوت پایانی',
    fields: [
      { key: 'title', label: 'عنوان', kind: 'text' },
      { key: 'text', label: 'متن', kind: 'textarea' },
      { key: 'buttons', label: 'دکمه‌ها', kind: 'list', itemTitle: 'label', fields: BTN },
    ],
  },
};

type Raw = { type?: string; enabled?: boolean; props?: Record<string, unknown> | null };

/** CMS sections (order + on/off + props) merged over the message defaults. */
export function resolveAbout(defaults: AboutPageData, cms: unknown, useCms: boolean): AboutSection[] {
  const base = new Map(defaults.sections.map((s) => [s.type, s]));
  const rows: Raw[] =
    useCms && cms && typeof cms === 'object' && Array.isArray((cms as { sections?: unknown }).sections)
      ? ((cms as { sections: Raw[] }).sections)
      : defaults.sections;
  const out: AboutSection[] = [];
  const seen = new Set<string>();
  for (const row of rows) {
    const def = row.type ? base.get(row.type as AboutType) : undefined;
    if (!def || seen.has(def.type)) continue;
    seen.add(def.type);
    if (row.enabled === false) continue;
    const props: Record<string, unknown> = { ...(def.props as Record<string, unknown>) };
    for (const [k, v] of Object.entries(row.props ?? {})) {
      if (v === null || v === undefined || v === '') continue;
      props[k] = v;
    }
    out.push({ type: def.type, props } as AboutSection);
  }
  return out;
}
