/** Corporate sitemap — source: WebinoDocs company-website-and-bmc.xmind */

export type NavLink = { href: string; labelKey: string }
export type NavColumn = { titleKey: string; href?: string; items: NavLink[] }
export type MegaDef = { id: string; labelKey: string; href: string; columns: NavColumn[] }

export const SERVICE_MEGA: MegaDef = {
  id: 'services',
  labelKey: 'site.nav.services',
  href: 'services',
  columns: [
    {
      titleKey: 'site.mega.tech',
      href: 'services/tech',
      items: [
        { href: 'services/custom-web', labelKey: 'site.mega.customWeb' },
        { href: 'services/wordpress', labelKey: 'site.mega.wordpress' },
        { href: 'services/redesign', labelKey: 'site.mega.redesign' },
        { href: 'services/landing', labelKey: 'site.mega.landing' },
        { href: 'services/ecommerce', labelKey: 'site.mega.ecommerce' },
        { href: 'services/marketplace', labelKey: 'site.mega.marketplace' },
        { href: 'services/pwa', labelKey: 'site.mega.pwa' },
        { href: 'services/native-app', labelKey: 'site.mega.nativeApp' },
        { href: 'services/ai', labelKey: 'site.mega.ai' },
      ],
    },
    {
      titleKey: 'site.mega.growth',
      href: 'services/growth',
      items: [
        { href: 'services/seo', labelKey: 'site.mega.seo' },
        { href: 'services/seo-technical', labelKey: 'site.mega.seoTechnical' },
        { href: 'services/seo-local', labelKey: 'site.mega.seoLocal' },
        { href: 'services/google-ads', labelKey: 'site.mega.googleAds' },
        { href: 'services/retargeting', labelKey: 'site.mega.retargeting' },
        { href: 'services/social', labelKey: 'site.mega.social' },
        { href: 'services/content', labelKey: 'site.mega.content' },
        { href: 'services/copywriting', labelKey: 'site.mega.copywriting' },
      ],
    },
    {
      titleKey: 'site.mega.branding',
      href: 'services/branding',
      items: [
        { href: 'services/logo', labelKey: 'site.mega.logo' },
        { href: 'services/brandbook', labelKey: 'site.mega.brandbook' },
        { href: 'services/ux-research', labelKey: 'site.mega.uxResearch' },
        { href: 'services/ui-web', labelKey: 'site.mega.uiWeb' },
        { href: 'services/ui-mobile', labelKey: 'site.mega.uiMobile' },
        { href: 'services/ads-graphic', labelKey: 'site.mega.adsGraphic' },
      ],
    },
    {
      titleKey: 'site.mega.strategy',
      href: 'services/strategy',
      items: [
        { href: 'services/digital-transformation', labelKey: 'site.mega.digitalTransform' },
        { href: 'services/bmc', labelKey: 'site.mega.bmc' },
        { href: 'services/business-plan', labelKey: 'site.mega.businessPlan' },
        { href: 'services/systemize', labelKey: 'site.mega.systemize' },
        { href: 'services/market-research', labelKey: 'site.mega.marketResearch' },
        { href: 'services/journey-map', labelKey: 'site.mega.journeyMap' },
      ],
    },
    {
      titleKey: 'site.mega.support',
      href: 'services/support',
      items: [
        { href: 'services/support-desk', labelKey: 'site.mega.supportDesk' },
        { href: 'services/security', labelKey: 'site.mega.security' },
        { href: 'services/backup', labelKey: 'site.mega.backup' },
        { href: 'services/performance', labelKey: 'site.mega.performance' },
        { href: 'services/hosting', labelKey: 'site.mega.hosting' },
      ],
    },
  ],
}

export const SOLUTION_MEGA: MegaDef = {
  id: 'solutions',
  labelKey: 'site.nav.solutions',
  href: 'solutions',
  columns: [
    {
      titleKey: 'site.mega.retail',
      href: 'solutions/retail',
      items: [
        { href: 'solutions/retail/fashion', labelKey: 'site.mega.fashion' },
        { href: 'solutions/retail/electronics', labelKey: 'site.mega.electronics' },
        { href: 'solutions/retail/fmcg', labelKey: 'site.mega.fmcg' },
      ],
    },
    {
      titleKey: 'site.mega.health',
      href: 'solutions/health',
      items: [
        { href: 'solutions/health/doctors', labelKey: 'site.mega.doctors' },
        { href: 'solutions/health/clinics', labelKey: 'site.mega.clinics' },
        { href: 'solutions/health/pharma', labelKey: 'site.mega.pharma' },
      ],
    },
    {
      titleKey: 'site.mega.corporate',
      href: 'solutions/corporate',
      items: [
        { href: 'solutions/corporate/real-estate', labelKey: 'site.mega.realEstate' },
        { href: 'solutions/corporate/legal', labelKey: 'site.mega.legal' },
        { href: 'solutions/corporate/general', labelKey: 'site.mega.generalServices' },
      ],
    },
    {
      titleKey: 'site.mega.education',
      href: 'solutions/education',
      items: [
        { href: 'solutions/education/institutes', labelKey: 'site.mega.institutes' },
        { href: 'solutions/education/instructors', labelKey: 'site.mega.instructors' },
      ],
    },
    {
      titleKey: 'site.mega.hospitality',
      href: 'solutions/hospitality',
      items: [
        { href: 'solutions/hospitality/cafe', labelKey: 'site.mega.cafe' },
        { href: 'solutions/hospitality/travel', labelKey: 'site.mega.travel' },
      ],
    },
  ],
}

export const RESOURCE_MEGA: MegaDef = {
  id: 'resources',
  labelKey: 'site.nav.resources',
  href: 'blog',
  columns: [
    {
      titleKey: 'site.mega.knowledge',
      items: [
        { href: 'blog', labelKey: 'site.nav.blog' },
        { href: 'academy', labelKey: 'site.nav.academy' },
        { href: 'magazine', labelKey: 'site.nav.magazine' },
        { href: 'downloads', labelKey: 'site.nav.downloads' },
        { href: 'faq', labelKey: 'site.nav.faq' },
      ],
    },
  ],
}

export const COMPANY_MEGA: MegaDef = {
  id: 'company',
  labelKey: 'site.nav.company',
  href: 'about',
  columns: [
    {
      titleKey: 'site.mega.companyCol',
      items: [
        { href: 'about', labelKey: 'site.nav.about' },
        { href: 'team', labelKey: 'site.nav.team' },
        { href: 'cooperation', labelKey: 'site.nav.cooperation' },
        { href: 'contact', labelKey: 'site.nav.contact' },
      ],
    },
    {
      titleKey: 'site.mega.convertCol',
      items: [
        { href: 'consultation', labelKey: 'site.nav.consultation' },
        { href: 'proposal', labelKey: 'site.nav.proposal' },
        { href: 'pricing', labelKey: 'site.nav.pricing' },
      ],
    },
  ],
}

export const MEGA_MENUS = [SERVICE_MEGA, SOLUTION_MEGA, RESOURCE_MEGA, COMPANY_MEGA]
