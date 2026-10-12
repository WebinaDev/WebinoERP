/**
 * «وبینا» page-builder widgets: every section of the corporate homepage, contact and about pages
 * is a builder widget with a schema (editor form) and a brand-styled renderer (CorporateWidget).
 */
import type { WidgetDef, WidgetNode } from '@/builder/types';
import { ABOUT_SECTION_SCHEMAS, type AboutType } from '../about-content';
import { CONTACT_PAGE_SCHEMA, HOME_BLOCK_ORDER, HOME_BLOCK_SCHEMAS, type FieldSpec } from '../site-content';

export const CORPORATE_PREFIX = 'webina-';

const ABOUT_ORDER: AboutType[] = ['hero', 'intro', 'story', 'mission', 'values', 'approach', 'team', 'different', 'cta'];

function pick(keys: (k: string) => boolean): FieldSpec[] {
  return CONTACT_PAGE_SCHEMA.filter((f) => keys(f.key));
}

/** Contact widgets share ContactPageData keys; each widget owns a slice of them. */
export const CONTACT_WIDGETS: { id: string; label: string; schema: FieldSpec[] }[] = [
  { id: 'hero', label: 'هیرو تماس', schema: pick((k) => k.startsWith('hero_') || k === 'channels') },
  { id: 'cards', label: 'کارت‌های تماس', schema: pick((k) => k.startsWith('channels') || k.startsWith('cop')) },
  { id: 'form', label: 'فرم تماس', schema: pick((k) => k.startsWith('form_') || ['submit_label', 'success_title', 'success_text', 'again_label', 'privacy_note'].includes(k)) },
  { id: 'hours', label: 'ساعت پاسخ‌گویی', schema: pick((k) => k.startsWith('hours')) },
  { id: 'map', label: 'نقشه و مسیر', schema: pick((k) => k.startsWith('map_')) },
  { id: 'socials', label: 'شبکه‌های اجتماعی', schema: pick((k) => k.startsWith('social_')) },
  { id: 'faq', label: 'پرسش‌های پرتکرار', schema: pick((k) => k.startsWith('faq_')) },
];

const CTA_SCHEMA = pick((k) => k.startsWith('cta_'));

export type CorporateDef = { type: string; label: string; schema: FieldSpec[] };

export const CORPORATE_DEFS: CorporateDef[] = [
  ...HOME_BLOCK_ORDER.map((type) => ({ type: `webina-home-${type}`, label: `خانه: ${HOME_BLOCK_SCHEMAS[type].label}`, schema: HOME_BLOCK_SCHEMAS[type].fields })),
  ...CONTACT_WIDGETS.map((w) => ({ type: `webina-contact-${w.id}`, label: w.label, schema: w.schema })),
  { type: 'webina-cta-band', label: 'نوار دعوت به اقدام', schema: CTA_SCHEMA },
  ...ABOUT_ORDER.map((type) => ({ type: `webina-about-${type}`, label: `درباره: ${ABOUT_SECTION_SCHEMAS[type].label}`, schema: ABOUT_SECTION_SCHEMAS[type].fields })),
];

let seq = 0;
function uid() {
  seq += 1;
  return `w_${Date.now().toString(36)}${seq.toString(36)}`;
}

export const CORPORATE_WIDGETS: WidgetDef[] = CORPORATE_DEFS.map((def) => ({
  type: def.type,
  label: def.label,
  category: 'corporate',
  fields: [],
  schema: def.schema,
  // Empty props render the verbatim defaults; the editor shows them once edited or seeded.
  create: (): WidgetNode => ({ id: uid(), type: def.type, props: {} }),
}));

export function isCorporateType(type: string) {
  return type.startsWith(CORPORATE_PREFIX);
}
