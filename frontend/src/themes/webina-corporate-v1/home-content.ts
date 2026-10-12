/**
 * Homepage v2 structure (copy lives in messages under site.v2.*).
 * Slugs here are the detail routes for each card.
 */

export const HOME_SERVICES = [
  { slug: 'business-strategy', key: 's1', icon: 'Compass', related: 'services/strategy' },
  { slug: 'brand-experience', key: 's2', icon: 'PenTool', related: 'services/branding' },
  { slug: 'digital-development', key: 's3', icon: 'Code', related: 'services/tech' },
  { slug: 'infrastructure-launch', key: 's4', icon: 'Workflow', related: 'services/support' },
  { slug: 'operations-automation', key: 's5', icon: 'Bot', related: 'services/systemize' },
  { slug: 'sales-growth', key: 's6', icon: 'TrendingUp', related: 'services/growth' },
  { slug: 'vip-growth', key: 's7', icon: 'Sparkles', related: 'services/digital-transformation' },
] as const;

export type HomeService = (typeof HOME_SERVICES)[number];

export function findHomeService(slug: string): HomeService | undefined {
  return HOME_SERVICES.find((service) => service.slug === slug);
}

export const HOME_MODULES = [
  { id: 'module-loyalty', key: 'm1', icon: 'Award' },
  { id: 'module-inventory', key: 'm2', icon: 'Boxes' },
  { id: 'module-accounting', key: 'm3', icon: 'Calculator' },
  { id: 'module-sms', key: 'm4', icon: 'MessageSquare' },
  { id: 'module-reports', key: 'm5', icon: 'ChartColumn' },
  { id: 'module-forms', key: 'm6', icon: 'ClipboardList' },
  { id: 'module-ticketing', key: 'm7', icon: 'Headset' },
  { id: 'module-installments', key: 'm8', icon: 'CreditCard' },
  { id: 'package-sales-automation', key: 'm9', icon: 'Workflow' },
  { id: 'package-cro', key: 'm10', icon: 'MousePointerClick' },
] as const;

export const HOME_STEPS = ['p1', 'p2', 'p3', 'p4', 'p5', 'p6'] as const;

/** Seeded academy courses, in the order of the brief (fallback when the API is empty). */
export const HOME_COURSES = [
  { slug: 'sales-funnel-conversion', key: 'c1' },
  { slug: 'online-store-launch', key: 'c2' },
  { slug: 'smb-automation', key: 'c3' },
  { slug: 'startup-branding', key: 'c4' },
  { slug: 'digital-growth-management', key: 'c5' },
] as const;

export const HOME_WHY = ['w1', 'w2', 'w3', 'w4', 'w5', 'w6'] as const;

export const HOME_ABOUT_POINTS = ['a1', 'a2', 'a3', 'a4'] as const;

export const FOOTER_QUICK_LINKS = [
  { href: 'services', key: 'services' },
  { href: 'products', key: 'products' },
  { href: 'portfolio', key: 'portfolio' },
  { href: 'academy', key: 'courses' },
  { href: 'blog', key: 'magazine' },
  { href: 'about', key: 'about' },
  { href: 'contact', key: 'contact' },
] as const;
