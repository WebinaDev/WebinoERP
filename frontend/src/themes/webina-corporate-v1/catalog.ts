/** Default public copy. Persian is primary. CMS bodies replace these after seed. */

export type LocaleCopy = { fa: string; en: string }

export const PRODUCTS: {
  id: string
  href: string
  portfolioSlug: string
  icon: string
  title: LocaleCopy
  body: LocaleCopy
}[] = [
  {
    id: 'dashboard',
    href: '/products#dashboard',
    portfolioSlug: 'webino-dashboard',
    icon: 'LayoutDashboard',
    title: { fa: 'Webino Dashboard', en: 'Webino Dashboard' },
    body: {
      fa: 'محصول سایت‌ساز وبینا. از Site Builder با نوع فروشگاه، مجله، کافه، رزومه یا شرکت بالا می‌آید و لایسنس همان نوع را می‌گیرد.',
      en: 'Webina’s site product. Site Builder launches it as a store, magazine, cafe, resume, or company site, with a matching license profile.',
    },
  },
  {
    id: 'erp',
    href: '/products#erp',
    portfolioSlug: 'webino-erp',
    icon: 'Database',
    title: { fa: 'WebinoERP', en: 'WebinoERP' },
    body: {
      fa: 'سامانه ماژولار همین ریپو: فروش، مشتریان، منابع انسانی و بقیه ماژول‌ها، API-first، با داشبورد مدیریت.',
      en: 'The modular system in this product: sales, customers, HR and the other modules, API-first, with an admin dashboard.',
    },
  },
  {
    id: 'docs',
    href: '/products#docs',
    portfolioSlug: 'webino-docs',
    icon: 'BookOpen',
    title: { fa: 'مستندات وبینا', en: 'Webina docs' },
    body: {
      fa: 'راهنمای API و ماژول‌ها، از جمله سکوی میزبانی، داخل docs-site همین محصول منتشر می‌شود.',
      en: 'API and module guides, including the hosting platform, ship in this product’s docs site.',
    },
  },
  {
    id: 'hosting',
    href: '/products#hosting',
    portfolioSlug: 'webino-hosting',
    icon: 'Server',
    title: { fa: 'سکوی میزبانی', en: 'Hosting platform' },
    body: {
      fa: 'ماژول Platform: سرور SSH، داکر، دامنه، گواهی و پشتیبان. همان مسیری که سایت‌های Dashboard را بالا می‌آورد.',
      en: 'The Platform module: SSH servers, Docker, domains, certificates and backups. This is how Dashboard sites are launched.',
    },
  },
]

const SERVICE_BLURBS: Record<string, LocaleCopy> = {
  tech: {
    fa: 'ساخت سایت، فروشگاه، اپ و ابزار هوش مصنوعی، از طرح تا تحویل.',
    en: 'Websites, stores, apps and AI tools, from plan to delivery.',
  },
  'custom-web': {
    fa: 'سایت اختصاصی روی استک وب، جدا از قالب آماده.',
    en: 'A custom website on a web stack, not a stock theme.',
  },
  wordpress: {
    fa: 'وردپرس با پوسته و افزونه متناسب با فروش و محتوا.',
    en: 'WordPress with a theme and plugins shaped around sales and content.',
  },
  redesign: {
    fa: 'بازطراحی سایت فعلی، از بررسی تجربه کاربر تا انتقال.',
    en: 'Rebuild of an existing site, from experience review through migration.',
  },
  landing: {
    fa: 'صفحه فروش برای کمپین، محصول یا جمع‌آوری درخواست.',
    en: 'A sales page for a campaign, a product, or lead capture.',
  },
  ecommerce: {
    fa: 'فروشگاه آنلاین با کاتالوگ، سبد و مسیر پرداخت.',
    en: 'An online store with catalog, cart and checkout.',
  },
  marketplace: {
    fa: 'بازار چندفروشنده روی زیرساخت قابل توسعه.',
    en: 'A multi-vendor marketplace on an extendable stack.',
  },
  pwa: {
    fa: 'وب‌سایتی که روی موبایل مثل اپ نصب می‌شود.',
    en: 'A website that installs on a phone like an app.',
  },
  'native-app': {
    fa: 'اپ اندروید و آیفون برای خدمات یا فروش.',
    en: 'Android and iPhone apps for a service or a store.',
  },
  ai: {
    fa: 'چت‌بات و اتوماسیون متصل به روند فروش و CRM.',
    en: 'Chatbots and automation tied to sales flow and CRM.',
  },
  growth: {
    fa: 'جستجو، تبلیغات، شبکه اجتماعی و محتوا برای دیده شدن.',
    en: 'Search, ads, social and content so the business can be found.',
  },
  seo: {
    fa: 'برنامه دیده شدن در جستجو، از ساختار سایت تا محتوا.',
    en: 'A search plan, from site structure through content.',
  },
  'seo-technical': {
    fa: 'بهبود فنی صفحات و ساختار سایت برای موتور جستجو.',
    en: 'Technical page and structure work for search engines.',
  },
  'seo-local': {
    fa: 'حضور در جستجوی محلی برای کسب‌وکار حضوری.',
    en: 'Local search presence for a business people visit in person.',
  },
  'google-ads': {
    fa: 'کمپین تبلیغات گوگل روی کلمات با قصد خرید.',
    en: 'Google Ads campaigns on terms that signal intent to buy.',
  },
  retargeting: {
    fa: 'نمایش دوباره تبلیغ به کسانی که سایت را دیده‌اند.',
    en: 'Ads shown again to people who already visited.',
  },
  social: {
    fa: 'مدیریت و محتوای شبکه‌های اجتماعی.',
    en: 'Social account management and content.',
  },
  content: {
    fa: 'برنامه محتوای بلاگ و مجله برای جذب مخاطب.',
    en: 'A blog and magazine content plan.',
  },
  copywriting: {
    fa: 'متن صفحه و محصول که مسیر درخواست را روشن می‌کند.',
    en: 'Page and product writing that makes the next step clear.',
  },
  branding: {
    fa: 'نشانه، راهنمای برند و رابط وب و موبایل.',
    en: 'Mark, brand guide, and web or mobile interface.',
  },
  logo: {
    fa: 'طراحی نشانه و قاعده استفاده از آن.',
    en: 'A logo and the rules for using it.',
  },
  brandbook: {
    fa: 'راهنمای رنگ، حروف و کاربرد هویت بصری.',
    en: 'Color, type and usage rules for the visual identity.',
  },
  'ux-research': {
    fa: 'شناخت کاربر و مسیرش قبل از طراحی رابط.',
    en: 'Learning the user and their path before interface design.',
  },
  'ui-web': {
    fa: 'طراحی رابط صفحات وب.',
    en: 'Interface design for web pages.',
  },
  'ui-mobile': {
    fa: 'طراحی رابط اپ و نمای موبایل.',
    en: 'Interface design for apps and small screens.',
  },
  'ads-graphic': {
    fa: 'بنر و گرافیک کمپین‌های تبلیغاتی.',
    en: 'Banners and graphics for ad campaigns.',
  },
  strategy: {
    fa: 'مدل کسب‌وکار، طرح، فرآیند و نقشه مسیر مشتری.',
    en: 'Business model, plan, operating process and customer journey.',
  },
  'digital-transformation': {
    fa: 'نوسازی مسیر فروش آنلاین و ابزارهای همراه آن.',
    en: 'Updating how you sell online, and the tools around that path.',
  },
  bmc: {
    fa: 'بوم مدل کسب‌وکار برای روشن شدن ارزش، کانال و مشتری.',
    en: 'A business model canvas for value, channel and customer.',
  },
  'business-plan': {
    fa: 'طرح کسب‌وکار قابل ارائه به تیم تصمیم‌گیر.',
    en: 'A business plan you can put in front of decision makers.',
  },
  systemize: {
    fa: 'تبدیل کار روزانه به فرآیند قابل تکرار.',
    en: 'Turning daily work into a process the team can repeat.',
  },
  'market-research': {
    fa: 'تصویر بازار و رقبا بر اساس داده در دسترس.',
    en: 'A picture of the market and competitors from available data.',
  },
  'journey-map': {
    fa: 'نقشه مسیر مشتری از آشنایی تا خرید.',
    en: 'A map of the customer path from first look to purchase.',
  },
  support: {
    fa: 'پشتیبانی، امنیت، پشتیبان‌گیری، سرعت و میزبانی.',
    en: 'Support, security, backup, speed and hosting.',
  },
  'support-desk': {
    fa: 'پشتیبانی فنی دوره‌ای برای سایت و سرویس‌ها.',
    en: 'Ongoing technical support for the site and its services.',
  },
  security: {
    fa: 'پیکربندی امن، گواهی و سخت‌سازی سرویس.',
    en: 'Security configuration, certificates and service hardening.',
  },
  backup: {
    fa: 'پشتیبان ابری و مسیر بازیابی.',
    en: 'Cloud backup and a restore path.',
  },
  performance: {
    fa: 'سرعت صفحات و امتیازهای عملکرد.',
    en: 'Page speed and performance scores.',
  },
  hosting: {
    fa: 'میزبانی و سرور مدیریت‌شده از سکوی Platform وبینا.',
    en: 'Managed hosting and servers on Webina’s Platform module.',
  },
}

const SOLUTION_BLURBS: Record<string, LocaleCopy> = {
  retail: {
    fa: 'فروش پوشاک، کالای دیجیتال و کالاهای مصرفی روزمره.',
    en: 'Fashion, electronics and everyday consumer goods.',
  },
  fashion: {
    fa: 'سایت و کمپین برای برند پوشاک و فروش فصلی.',
    en: 'Site and campaigns for a clothing brand and seasonal sales.',
  },
  electronics: {
    fa: 'فروشگاه کالای دیجیتال با مقایسه و مشخصات.',
    en: 'An electronics shop with specs and comparison.',
  },
  fmcg: {
    fa: 'سوپرمارکت و کالای تندمصرف، شامل معرفی و سفارش.',
    en: 'Supermarkets and fast-moving goods, including catalog and order.',
  },
  health: {
    fa: 'مطب، کلینیک و داروخانه، با نوبت و محتوای قابل انتشار.',
    en: 'Practices, clinics and pharmacies, with booking and publishable content.',
  },
  doctors: {
    fa: 'سایت پزشک، نوبت‌دهی و معرفی خدمات مطب.',
    en: 'A physician site, appointment requests and service list.',
  },
  clinics: {
    fa: 'سایت کلینیک و معرفی خدمات درمان یا زیبایی.',
    en: 'A clinic site and a clear list of treatment or beauty services.',
  },
  pharma: {
    fa: 'معرفی دارو یا تجهیزات، در چارچوب محتوای قابل انتشار.',
    en: 'Pharma or device information, within content you are allowed to publish.',
  },
  corporate: {
    fa: 'املاک، خدمات حقوقی و مالی، و خدمات عمومی شرکت‌ها.',
    en: 'Real estate, legal and finance services, and general corporate services.',
  },
  'real-estate': {
    fa: 'معرفی پروژه، فایل و درخواست بازدید.',
    en: 'Project pages, listings and visit requests.',
  },
  legal: {
    fa: 'سایت موسسه حقوقی یا مالی و مسیر درخواست مشاوره.',
    en: 'A legal or finance firm site and a consultation path.',
  },
  general: {
    fa: 'خدمات شرکتی عمومی، از معرفی تا فرم درخواست.',
    en: 'General corporate services, from the story to the request form.',
  },
  education: {
    fa: 'موسسه و مدرس، با معرفی دوره و ثبت‌نام.',
    en: 'Institutes and instructors, with course pages and signup.',
  },
  institutes: {
    fa: 'سایت آموزشگاه یا پلتفرم معرفی دوره.',
    en: 'A school or institute site for presenting courses.',
  },
  instructors: {
    fa: 'صفحه مدرس، سرفصل و راه ارتباط.',
    en: 'An instructor page, syllabus and a way to get in touch.',
  },
  hospitality: {
    fa: 'کافه، رستوران و خدمات سفر و اقامت.',
    en: 'Cafes, restaurants, and travel or stay services.',
  },
  cafe: {
    fa: 'منو، معرفی فضا و درخواست رزرو. نوع سایت کافه در Dashboard هم هست.',
    en: 'Menu, venue story and reservation requests. Dashboard also has a cafe site type.',
  },
  travel: {
    fa: 'معرفی مقصد، تور یا اقامت و جمع‌آوری درخواست.',
    en: 'Destination, tour or stay pages and a request form.',
  },
}

export function copyFor(locale: string, copy: LocaleCopy): string {
  return locale === 'en' ? copy.en : copy.fa
}

export function serviceBlurb(slug: string, locale: string): string | null {
  const row = SERVICE_BLURBS[slug]
  return row ? copyFor(locale, row) : null
}

export function solutionBlurb(slug: string, locale: string): string | null {
  const row = SOLUTION_BLURBS[slug]
  return row ? copyFor(locale, row) : null
}

export const PRODUCT_SLUGS = new Set(PRODUCTS.map((p) => p.portfolioSlug))
