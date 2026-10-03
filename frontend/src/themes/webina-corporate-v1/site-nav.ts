/** Corporate sitemap. Labels live in messages; icons are Lucide names. */

export type NavLink = { href: string; labelKey: string; icon: string }
export type NavColumn = { titleKey: string; leadKey: string; href?: string; icon: string; items: NavLink[] }
export type MegaDef = { id: string; labelKey: string; href: string; columns: NavColumn[] }

export const SERVICE_MEGA: MegaDef = {
  id: 'services',
  labelKey: 'site.nav.services',
  href: 'services',
  columns: [
    {
      titleKey: 'site.mega.tech',
      leadKey: 'site.mega.techLead',
      href: 'services/tech',
      icon: 'Code',
      items: [
        { href: 'services/custom-web', labelKey: 'site.mega.customWeb', icon: 'PanelsTopLeft' },
        { href: 'services/wordpress', labelKey: 'site.mega.wordpress', icon: 'FileCode' },
        { href: 'services/redesign', labelKey: 'site.mega.redesign', icon: 'RefreshCw' },
        { href: 'services/landing', labelKey: 'site.mega.landing', icon: 'MousePointerClick' },
        { href: 'services/ecommerce', labelKey: 'site.mega.ecommerce', icon: 'ShoppingBag' },
        { href: 'services/marketplace', labelKey: 'site.mega.marketplace', icon: 'Store' },
        { href: 'services/pwa', labelKey: 'site.mega.pwa', icon: 'Smartphone' },
        { href: 'services/native-app', labelKey: 'site.mega.nativeApp', icon: 'AppWindow' },
        { href: 'services/ai', labelKey: 'site.mega.ai', icon: 'Bot' },
      ],
    },
    {
      titleKey: 'site.mega.growth',
      leadKey: 'site.mega.growthLead',
      href: 'services/growth',
      icon: 'TrendingUp',
      items: [
        { href: 'services/seo', labelKey: 'site.mega.seo', icon: 'Search' },
        { href: 'services/seo-technical', labelKey: 'site.mega.seoTechnical', icon: 'Wrench' },
        { href: 'services/seo-local', labelKey: 'site.mega.seoLocal', icon: 'MapPin' },
        { href: 'services/google-ads', labelKey: 'site.mega.googleAds', icon: 'Megaphone' },
        { href: 'services/retargeting', labelKey: 'site.mega.retargeting', icon: 'Repeat' },
        { href: 'services/social', labelKey: 'site.mega.social', icon: 'Share2' },
        { href: 'services/content', labelKey: 'site.mega.content', icon: 'PenLine' },
        { href: 'services/copywriting', labelKey: 'site.mega.copywriting', icon: 'FileText' },
      ],
    },
    {
      titleKey: 'site.mega.branding',
      leadKey: 'site.mega.brandingLead',
      href: 'services/branding',
      icon: 'PenTool',
      items: [
        { href: 'services/logo', labelKey: 'site.mega.logo', icon: 'Sparkles' },
        { href: 'services/brandbook', labelKey: 'site.mega.brandbook', icon: 'BookOpen' },
        { href: 'services/ux-research', labelKey: 'site.mega.uxResearch', icon: 'Users' },
        { href: 'services/ui-web', labelKey: 'site.mega.uiWeb', icon: 'Monitor' },
        { href: 'services/ui-mobile', labelKey: 'site.mega.uiMobile', icon: 'Tablet' },
        { href: 'services/ads-graphic', labelKey: 'site.mega.adsGraphic', icon: 'Image' },
      ],
    },
    {
      titleKey: 'site.mega.strategy',
      leadKey: 'site.mega.strategyLead',
      href: 'services/strategy',
      icon: 'Compass',
      items: [
        { href: 'services/digital-transformation', labelKey: 'site.mega.digitalTransform', icon: 'Orbit' },
        { href: 'services/bmc', labelKey: 'site.mega.bmc', icon: 'LayoutGrid' },
        { href: 'services/business-plan', labelKey: 'site.mega.businessPlan', icon: 'ClipboardList' },
        { href: 'services/systemize', labelKey: 'site.mega.systemize', icon: 'Workflow' },
        { href: 'services/market-research', labelKey: 'site.mega.marketResearch', icon: 'LineChart' },
        { href: 'services/journey-map', labelKey: 'site.mega.journeyMap', icon: 'Route' },
      ],
    },
    {
      titleKey: 'site.mega.support',
      leadKey: 'site.mega.supportLead',
      href: 'services/support',
      icon: 'LifeBuoy',
      items: [
        { href: 'services/support-desk', labelKey: 'site.mega.supportDesk', icon: 'Headset' },
        { href: 'services/security', labelKey: 'site.mega.security', icon: 'Shield' },
        { href: 'services/backup', labelKey: 'site.mega.backup', icon: 'Cloud' },
        { href: 'services/performance', labelKey: 'site.mega.performance', icon: 'Gauge' },
        { href: 'services/hosting', labelKey: 'site.mega.hosting', icon: 'Server' },
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
      leadKey: 'site.mega.retailLead',
      href: 'solutions/retail',
      icon: 'ShoppingCart',
      items: [
        { href: 'solutions/retail/fashion', labelKey: 'site.mega.fashion', icon: 'Shirt' },
        { href: 'solutions/retail/electronics', labelKey: 'site.mega.electronics', icon: 'Cpu' },
        { href: 'solutions/retail/fmcg', labelKey: 'site.mega.fmcg', icon: 'ShoppingBasket' },
      ],
    },
    {
      titleKey: 'site.mega.health',
      leadKey: 'site.mega.healthLead',
      href: 'solutions/health',
      icon: 'HeartPulse',
      items: [
        { href: 'solutions/health/doctors', labelKey: 'site.mega.doctors', icon: 'Stethoscope' },
        { href: 'solutions/health/clinics', labelKey: 'site.mega.clinics', icon: 'Building2' },
        { href: 'solutions/health/pharma', labelKey: 'site.mega.pharma', icon: 'Pill' },
      ],
    },
    {
      titleKey: 'site.mega.corporate',
      leadKey: 'site.mega.corporateLead',
      href: 'solutions/corporate',
      icon: 'Building',
      items: [
        { href: 'solutions/corporate/real-estate', labelKey: 'site.mega.realEstate', icon: 'Landmark' },
        { href: 'solutions/corporate/legal', labelKey: 'site.mega.legal', icon: 'Scale' },
        { href: 'solutions/corporate/general', labelKey: 'site.mega.generalServices', icon: 'Briefcase' },
      ],
    },
    {
      titleKey: 'site.mega.education',
      leadKey: 'site.mega.educationLead',
      href: 'solutions/education',
      icon: 'GraduationCap',
      items: [
        { href: 'solutions/education/institutes', labelKey: 'site.mega.institutes', icon: 'Library' },
        { href: 'solutions/education/instructors', labelKey: 'site.mega.instructors', icon: 'UserRound' },
      ],
    },
    {
      titleKey: 'site.mega.hospitality',
      leadKey: 'site.mega.hospitalityLead',
      href: 'solutions/hospitality',
      icon: 'Utensils',
      items: [
        { href: 'solutions/hospitality/cafe', labelKey: 'site.mega.cafe', icon: 'Coffee' },
        { href: 'solutions/hospitality/travel', labelKey: 'site.mega.travel', icon: 'Plane' },
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
      leadKey: 'site.mega.knowledgeLead',
      icon: 'Library',
      items: [
        { href: 'blog', labelKey: 'site.nav.blog', icon: 'Newspaper' },
        { href: 'academy', labelKey: 'site.nav.academy', icon: 'GraduationCap' },
        { href: 'magazine', labelKey: 'site.nav.magazine', icon: 'BookMarked' },
        { href: 'downloads', labelKey: 'site.nav.downloads', icon: 'Download' },
        { href: 'faq', labelKey: 'site.nav.faq', icon: 'CircleHelp' },
        { href: 'announcements', labelKey: 'site.home.announcements', icon: 'Bell' },
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
      leadKey: 'site.mega.companyLead',
      icon: 'Building2',
      items: [
        { href: 'about', labelKey: 'site.nav.about', icon: 'Info' },
        { href: 'products', labelKey: 'site.nav.products', icon: 'Layers' },
        { href: 'team', labelKey: 'site.nav.team', icon: 'Users' },
        { href: 'cooperation', labelKey: 'site.nav.cooperation', icon: 'Handshake' },
        { href: 'contact', labelKey: 'site.nav.contact', icon: 'Phone' },
      ],
    },
    {
      titleKey: 'site.mega.convertCol',
      leadKey: 'site.mega.convertLead',
      icon: 'ArrowUpRight',
      items: [
        { href: 'consultation', labelKey: 'site.nav.consultation', icon: 'Calendar' },
        { href: 'proposal', labelKey: 'site.nav.proposal', icon: 'FileSpreadsheet' },
        { href: 'pricing', labelKey: 'site.nav.pricing', icon: 'BadgeDollarSign' },
      ],
    },
  ],
}

export const MEGA_MENUS = [SERVICE_MEGA, SOLUTION_MEGA, RESOURCE_MEGA, COMPANY_MEGA]

export const SERVICE_COUNT = SERVICE_MEGA.columns.reduce((sum, col) => sum + col.items.length, 0)
export const INDUSTRY_COUNT = SOLUTION_MEGA.columns.length
