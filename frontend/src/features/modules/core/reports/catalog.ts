/**
 * Enterprise Reports Catalog Definition
 * Defines the standard enterprise reports across Finance, Sales, Procurement, Inventory, Manufacturing, HR, CRM, Field Service, QM, and Executive domains.
 */

export interface ReportDefinition {
  id: string;
  category: 'finance' | 'sales' | 'procurement' | 'inventory' | 'mfg' | 'hrm' | 'crm' | 'field_service' | 'qm' | 'executive';
  titleKey: string;
  descKey: string;
  icon: string;
  tags: string[];
  isFavorite?: boolean;
  isPopular?: boolean;
  columns: Array<{ key: string; labelKey: string; isMoney?: boolean; isDate?: boolean }>;
  mockSummary: Record<string, string | number>;
  mockData: Record<string, any>[];
}

export const REPORT_CATEGORIES = [
  { id: 'all', labelFa: 'همه گزارش‌ها', labelEn: 'All Reports' },
  { id: 'finance', labelFa: 'مالی و حسابداری', labelEn: 'Finance & Accounting' },
  { id: 'sales', labelFa: 'فروش و بازرگانی', labelEn: 'Sales & Commercial' },
  { id: 'procurement', labelFa: 'خرید و تدارکات', labelEn: 'Procurement' },
  { id: 'inventory', labelFa: 'انبار و موجودی', labelEn: 'Inventory & Stock' },
  { id: 'mfg', labelFa: 'تولید و کارخانه', labelEn: 'Manufacturing' },
  { id: 'hrm', labelFa: 'منابع انسانی', labelEn: 'HR & Personnel' },
  { id: 'crm', labelFa: 'مشتریان و تیکت‌ها', labelEn: 'CRM & Support' },
  { id: 'field_service', labelFa: 'خدمات پس از فروش', labelEn: 'Field Service' },
  { id: 'qm', labelFa: 'کنترل کیفیت و CAPA', labelEn: 'Quality & QM' },
  { id: 'executive', labelFa: 'مدیریتی و تلفیقی', labelEn: 'Executive & C-Level' },
] as const;

export const ENTERPRISE_REPORTS: ReportDefinition[] = [
  // ۱. مالی
  {
    id: 'fin-ar-aging',
    category: 'finance',
    titleKey: 'گزارش سن بدهی مشتریان (AR Aging)',
    descKey: 'تفکیک فاکتورهای معوق مشتریان در بازه‌های ۰ تا ۳۰، ۳۰ تا ۶۰، ۶۰ تا ۹۰ و بیش از ۹۰ روز.',
    icon: 'Wallet',
    tags: ['مالی', 'مطالبات', 'اعتبارات'],
    isPopular: true,
    columns: [
      { key: 'customer', labelKey: 'مشتری' },
      { key: 'current', labelKey: 'جاری (کمتر از ۳۰ روز)', isMoney: true },
      { key: 'days_30_60', labelKey: '۳۰ تا ۶۰ روز', isMoney: true },
      { key: 'days_60_90', labelKey: '۶۰ تا ۹۰ روز', isMoney: true },
      { key: 'over_90', labelKey: 'بیش از ۹۰ روز (ریسک بالا)', isMoney: true },
      { key: 'total', labelKey: 'مجموع مانده بدهی', isMoney: true },
    ],
    mockSummary: {
      'کل مطالبات باز': '۱۴,۲۵۰,۰۰۰,۰۰۰ ریال',
      'مطالبات با ریسک بحرانی (>۹۰ روز)': '۳,۱۰۰,۰۰۰,۰۰۰ ریال',
      'تعداد مشتریان دارای بدهی معوق': 18,
    },
    mockData: [
      { id: 1, customer: 'شرکت پتروشیمی آریا', current: '۴,۰۰۰,۰۰۰,۰۰۰', days_30_60: '۱,۲۰۰,۰۰۰,۰۰۰', days_60_90: '۵۰۰,۰۰۰,۰۰۰', over_90: '۰', total: '۵,۷۰۰,۰۰۰,۰۰۰' },
      { id: 2, customer: 'فروشگاه‌های زنجیره‌ای افق', current: '۲,۵۰۰,۰۰۰,۰۰۰', days_30_60: '۸۰۰,۰۰۰,۰۰۰', days_60_90: '۱,۱۰۰,۰۰۰,۰۰۰', over_90: '۱,۸۰۰,۰۰۰,۰۰۰', total: '۶,۲۰۰,۰۰۰,۰۰۰' },
      { id: 3, customer: 'گروه صنعتی پارس مکران', current: '۱,۰۵۰,۰۰۰,۰۰۰', days_30_60: '۰', days_60_90: '۰', over_90: '۱,۳۰۰,۰۰۰,۰۰۰', total: '۲,۳۵۰,۰۰۰,۰۰۰' },
    ],
  },
  {
    id: 'fin-p-and-l',
    category: 'finance',
    titleKey: 'صورت سود و زیان دوره‌ای (P&L)',
    descKey: 'تحلیل درآمدهای عملیاتی، بهای تمام‌شده کالای فروش‌رفته (COGS)، هزینه‌های عمومی و سود ناخالص.',
    icon: 'TrendingUp',
    tags: ['مالی', 'سود و زیان', 'حسابداری'],
    isPopular: true,
    columns: [
      { key: 'account_title', labelKey: 'سرفصل حسابداری' },
      { key: 'budget_allocated', labelKey: 'بودجه مصوب دوره', isMoney: true },
      { key: 'actual_amount', labelKey: 'عملکرد واقعی', isMoney: true },
      { key: 'variance_percent', labelKey: 'درصد انحراف' },
    ],
    mockSummary: {
      'درآمد فروش ناخالص': '۳۲,۸۰۰,۰۰۰,۰۰۰ ریال',
      'سود عملیاتی خالص': '۸,۴۵۰,۰۰۰,۰۰۰ ریال',
      'حاشیه سود ناخالص': '۲۵.۸٪',
    },
    mockData: [
      { id: 1, account_title: 'درآمد حاصل از فروش محصولات', budget_allocated: '۳۰,۰۰۰,۰۰۰,۰۰۰', actual_amount: '۳۲,۸۰۰,۰۰۰,۰۰۰', variance_percent: '+۹.۳٪' },
      { id: 2, account_title: 'بهای تمام‌شده کالای فروش‌رفته (COGS)', budget_allocated: '۲۰,۰۰۰,۰۰۰,۰۰۰', actual_amount: '۱۹,۲۰۰,۰۰۰,۰۰۰', variance_percent: '-۴.۰٪' },
      { id: 3, account_title: 'هزینه‌های عمومی و اداری', budget_allocated: '۵,۵۰۰,۰۰۰,۰۰۰', actual_amount: '۵,۱۵۰,۰۰۰,۰۰۰', variance_percent: '-۶.۳٪' },
    ],
  },

  // ۲. فروش
  {
    id: 'sales-performance-by-product',
    category: 'sales',
    titleKey: 'فروش دوره‌ای به تفکیک کالا و سبد محصول',
    descKey: 'حجم فروش مقداری و ریالی، بیشترین کالاهای سودآور و سهم هر دسته در درآمدهای کلان.',
    icon: 'BarChart3',
    tags: ['فروش', 'محصولات', 'سودآوری'],
    isPopular: true,
    columns: [
      { key: 'sku', labelKey: 'کد کالا' },
      { key: 'name', labelKey: 'عنوان محصول' },
      { key: 'qty_sold', labelKey: 'تعداد فروخته‌شده' },
      { key: 'revenue', labelKey: 'مجموع درآمد ریالی', isMoney: true },
      { key: 'profit_margin', labelKey: 'حاشیه سود' },
    ],
    mockSummary: {
      'پرفروش‌ترین کالا': 'سرور رک‌مونت سری X-500',
      'تعداد کل اقلام تحویل‌شده': '۱,۴۲۰ عدد',
      'میانگین حاشیه سود سبد': '۲۸.۴٪',
    },
    mockData: [
      { id: 1, sku: 'SRV-X500', name: 'سرور پردازشی رک‌مونت X-500', qty_sold: '۴۸ دستگاه', revenue: '۱۴,۴۰۰,۰۰۰,۰۰۰', profit_margin: '۳۲.۵٪' },
      { id: 2, sku: 'NET-SW24', name: 'سوئیچ شبکه ۲۴ پورت صنعتی', qty_sold: '۲۱۵ دستگاه', revenue: '۶,۸۸۰,۰۰۰,۰۰۰', profit_margin: '۲۶.۱٪' },
      { id: 3, sku: 'PWR-UPS3K', name: 'یو‌پی‌اس آنلاین ۳ کیلوولت‌آمپر', qty_sold: '۸۴ دستگاه', revenue: '۵,۰۴۰,۰۰۰,۰۰۰', profit_margin: '۲۱.۷٪' },
    ],
  },

  // ۳. خرید و تدارکات
  {
    id: 'prc-vendor-performance',
    category: 'procurement',
    titleKey: 'ارزیابی تأمین‌کنندگان و سفارش‌های خرید باز',
    descKey: 'بررسی شاخص تأخیر در تحویل، نرخ تطبیق فاکتور با رسید انبار (۳-Way Match) و خرید تجمعی.',
    icon: 'ShoppingCart',
    tags: ['خرید', 'تأمین‌کننده', 'PO'],
    isPopular: true,
    columns: [
      { key: 'vendor_name', labelKey: 'نام تأمین‌کننده' },
      { key: 'open_pos', labelKey: 'سفارش‌های خرید باز (PO)' },
      { key: 'total_purchased', labelKey: 'مجموع خرید سال', isMoney: true },
      { key: 'on_time_delivery_rate', labelKey: 'نرخ تحویل به‌موقع' },
      { key: 'rating', labelKey: 'رتبه کیفی' },
    ],
    mockSummary: {
      'تعداد تأمین‌کنندگان فعال': 34,
      'سفارش‌های باز در گردش': '۱۸ سفارش (۹.۲B ریال)',
      'میانگین نرخ تحویل به‌موقع': '۸۹.۴٪',
    },
    mockData: [
      { id: 1, vendor_name: 'صنایع الکترونیک خاورمیانه', open_pos: '۴ سفارش', total_purchased: '۱۸,۲۰۰,۰۰۰,۰۰۰', on_time_delivery_rate: '۹۴.۲٪', rating: 'A+' },
      { id: 2, vendor_name: 'فولاد و اتصالات نوین غرب', open_pos: '۲ سفارش', total_purchased: '۸,۴۵۰,۰۰۰,۰۰۰', on_time_delivery_rate: '۸۱.۰٪', rating: 'B' },
      { id: 3, vendor_name: 'بسته‌بندی آرین‌پک', open_pos: '۱ سفارش', total_purchased: '۳,۱۰۰,۰۰۰,۰۰۰', on_time_delivery_rate: '۹۸.۵٪', rating: 'A' },
    ],
  },

  // ۴. انبار و موجودی
  {
    id: 'inv-stock-valuation-rop',
    category: 'inventory',
    titleKey: 'موجودی لحظه‌ای، ارزش ریالی و اقلام بحرانی (ROP)',
    descKey: 'ارزش دارایی فیزیکی انبارها، کالاهای زیر نقطه سفارش، گردش کاردکس و موجودی آزاد/رزرو.',
    icon: 'Package',
    tags: ['انبار', 'موجودی', 'نقطه سفارش', 'ROP'],
    isPopular: true,
    columns: [
      { key: 'item_code', labelKey: 'کد کالا' },
      { key: 'item_name', labelKey: 'شرح کالا' },
      { key: 'warehouse', labelKey: 'انبار استقرار' },
      { key: 'on_hand', labelKey: 'موجودی فیزیکی' },
      { key: 'safety_stock', labelKey: 'حد اطمینان (نقطه سفارش)' },
      { key: 'status_badge', labelKey: 'وضعیت موجودی' },
    ],
    mockSummary: {
      'ارزش کل موجودی ریالی': '۴۲,۱۰۰,۰۰۰,۰۰۰ ریال',
      'تعداد کل اقلام فعال': '۸۵۰ قلم',
      'اقلام بحرانی زیر حد سفارش': 8,
    },
    mockData: [
      { id: 1, item_code: 'RAW-ST-04', item_name: 'ورق استیل ۳۰۴ ضخامت ۲ میل', warehouse: 'انبار مرکزی مواد اولیه', on_hand: '۱۲۰ کیلو', safety_stock: '۵۰۰ کیلو', status_badge: 'بحرانی (زیر حد سفارش)' },
      { id: 2, item_code: 'IC-MC-8051', item_name: 'میکروکنترلر ۸ بیتی صنعتی', warehouse: 'انبار الکترونیک', on_hand: '۴۵ عدد', safety_stock: '۲۰۰ عدد', status_badge: 'بحرانی (زیر حد سفارش)' },
      { id: 3, item_code: 'CAB-UTP-CAT6', item_name: 'کابل شبکه مس تمام شیلد', warehouse: 'انبار مرکزی محصول', on_hand: '۱,۴۰۰ متر', safety_stock: '۱,۰۰۰ متر', status_badge: 'عادی' },
    ],
  },

  // ۵. تولید
  {
    id: 'mfg-work-orders-oee',
    category: 'mfg',
    titleKey: 'وضعیت دستورات ساخت (Work Orders) و راندمان خطوط',
    descKey: 'پیشرفت فیزیکی تولید، مغایرت مصرف مواد اولیه نسبت به BOM و نرخ توقفات خط مونتاژ.',
    icon: 'Cpu',
    tags: ['تولید', 'BOM', 'Work Order'],
    isPopular: false,
    columns: [
      { key: 'wo_number', labelKey: 'شماره ساخت' },
      { key: 'product', labelKey: 'محصول نهایی' },
      { key: 'planned_qty', labelKey: 'تیراژ برنامه' },
      { key: 'produced_qty', labelKey: 'تولیدشده سالم' },
      { key: 'scrap_qty', labelKey: 'ضایعات' },
      { key: 'progress', labelKey: 'پیشرفت' },
    ],
    mockSummary: {
      'دستورهای ساخت فعال': 12,
      'راندمان کلی خطوط (OEE)': '۸۷.۲٪',
      'نرخ ضایعات مجاز': '۱.۴٪ (زیر سقف ۲٪)',
    },
    mockData: [
      { id: 1, wo_number: 'WO-2026-109', product: 'رک شبکه ۲۴ یونیت مقاومتی', planned_qty: '۱۰۰ عدد', produced_qty: '۸۵ عدد', scrap_qty: '۱ عدد', progress: '۸۵٪' },
      { id: 2, wo_number: 'WO-2026-110', product: 'کارت توسعه پورت سریال صنعتی', planned_qty: '۵۰۰ عدد', produced_qty: '۲۱۰ عدد', scrap_qty: '۳ عدد', progress: '۴۲٪' },
    ],
  },

  // ۶. منابع انسانی
  {
    id: 'hrm-attendance-payroll',
    category: 'hrm',
    titleKey: 'حضور و غیاب، اضافه کاری و خلاصه کارکرد پرسنل',
    descKey: 'تحلیل ساعت کار مفید، کسر کار، اضافه‌کاری تاییدشده و مرخصی‌های استفاده‌شده ماه.',
    icon: 'Users',
    tags: ['پرسنل', 'حقوق', 'تردد'],
    isPopular: true,
    columns: [
      { key: 'emp_code', labelKey: 'شماره پرسنلی' },
      { key: 'emp_name', labelKey: 'نام و نام خانوادگی' },
      { key: 'department', labelKey: 'واحد سازمانی' },
      { key: 'work_hours', labelKey: 'کارکرد موظف' },
      { key: 'overtime_hours', labelKey: 'اضافه‌کار تاییدشده' },
      { key: 'leave_days', labelKey: 'مرخصی' },
    ],
    mockSummary: {
      'کل پرسنل شاغل': 136,
      'مجموع ساعات کارکرد ماه': '۲۳,۴۰۰ ساعت',
      'نرخ حضور امروز': '۹۴٪',
    },
    mockData: [
      { id: 1, emp_code: 'EMP-102', emp_name: 'علیرضا شایگان', department: 'فنی و مهندسی', work_hours: '۱۷۶ ساعت', overtime_hours: '۳۲ ساعت', leave_days: '۱ روز' },
      { id: 2, emp_code: 'EMP-215', emp_name: 'مریم سهرابی', department: 'مالی و اداری', work_hours: '۱۷۶ ساعت', overtime_hours: '۱۴ ساعت', leave_days: '۲ روز' },
      { id: 3, emp_code: 'EMP-304', emp_name: 'مهدی کریمی', department: 'انبار و تدارکات', work_hours: '۱۷۶ ساعت', overtime_hours: '۴۵ ساعت', leave_days: '۰ روز' },
    ],
  },

  // ۷. CRM
  {
    id: 'crm-pipeline-conversion',
    category: 'crm',
    titleKey: 'قیف فروش (Sales Funnel) و نرخ تبدیل فرصت‌ها',
    descKey: 'بررسی مراحل سرنخ، پیش‌فاکتور، مذاکره، ارزش معاملات در جریان و نرخ پیروزی در فروش.',
    icon: 'Compass',
    tags: ['CRM', 'قیف فروش', 'لید'],
    isPopular: false,
    columns: [
      { key: 'stage_name', labelKey: 'مرحله قیف' },
      { key: 'deals_count', labelKey: 'تعداد معاملات' },
      { key: 'pipeline_value', labelKey: 'ارزش مرحله', isMoney: true },
      { key: 'conversion_rate', labelKey: 'نرخ عبور به مرحله بعد' },
    ],
    mockSummary: {
      'ارزش کل معاملات در جریان': '۲۴,۵۰۰,۰۰۰,۰۰۰ ریال',
      'نرخ تبدیل نهایی به قرارداد': '۱۹.۴٪',
      'لیدهای جدید ماه جاری': 142,
    },
    mockData: [
      { id: 1, stage_name: '۱. استعلام و تماس اولیه', deals_count: '۴۸ مورد', pipeline_value: '۳۸,۰۰۰,۰۰۰,۰۰۰', conversion_rate: '۵۴٪' },
      { id: 2, stage_name: '۲. ارسال پروپوزال و پیش‌فاکتور', deals_count: '۲۶ مورد', pipeline_value: '۲۱,۰۰۰,۰۰۰,۰۰۰', conversion_rate: '۴۲٪' },
      { id: 3, stage_name: '۳. مذاکره قرارداد و نهایی‌سازی', deals_count: '۱۲ مورد', pipeline_value: '۱۱,۲۰۰,۰۰۰,۰۰۰', conversion_rate: '۷۸٪' },
    ],
  },
];
