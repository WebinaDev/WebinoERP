# مستند فنی ماژول مدیریت ارتباط با مشتری (CRM Module Specification)

**کد ماژول:** Module_CRM  
**وابستگی‌ها:** Core Module (برای کاربران و تنظیمات), Product Module (برای لیست کالاها - اختیاری)

---

## ۱. دیاگرام موجودیت‌ها (ERD Overview)

این ماژول شامل ۶ موجودیت (Entity) اصلی است که با هم ارتباط تنگاتنگ دارند:

- **Leads (سرنخ‌ها):** داده‌های خام ورودی.
- **Accounts (حساب‌ها/شرکت‌ها):** مشتریان حقوقی تثبیت شده.
- **Contacts (اشخاص):** افراد مرتبط با حساب‌ها.
- **Deals / Opportunities (فرصت‌ها):** پروسه فروش و مذاکره.
- **Activities (فعالیت‌ها):** تماس، جلسه، تسک (Polymorphic).
- **Stages (مراحل فروش):** تنظیمات داینامیک پایپ‌لاین.

---

## ۲. تشریح ریز موجودیت‌ها (Database Schema & Field Defs)

### ۲.۱ جدول سرنخ‌ها (crm_leads)

سرنخ‌ها داده‌های موقتی هستند. هدف نهایی "Convert" کردن آن‌هاست.

| نام فیلد | نوع داده (SQL) | اعتبارسنجی (Validation) | نوع ورودی (UI) | توضیحات فنی |
|---------|---------------|-------------------------|---------------|------------|
| id | BIGINT (PK) | - | - | Auto Increment |
| topic | VARCHAR(255) | Required | Text Input | موضوع سرنخ (مثلاً: علاقه به خرید سرور) |
| first_name | VARCHAR(100) | Required | Text Input | - |
| last_name | VARCHAR(100) | Required | Text Input | - |
| company | VARCHAR(150) | Nullable | Text Input | نام شرکت (هنوز به جدول Accounts وصل نیست) |
| job_title | VARCHAR(100) | Nullable | Text Input | سمت شغلی |
| email | VARCHAR(150) | Email \| Unique (Active) | Input (Email) | چک کردن تکراری بودن حیاتی است |
| mobile | VARCHAR(20) | Required \| Regex | Input (Tel) | با فرمت استاندارد E.164 ذخیره شود |
| phone | VARCHAR(20) | Nullable | Input (Tel) | تلفن ثابت |
| source_id | INT (FK) | Exists:crm_sources | Select (Combobox) | منبع: وب‌سایت، لینکدین، نمایشگاه |
| status_id | INT (FK) | Exists:crm_statuses | Select (Badge) | وضعیت: جدید، تماس گرفته، بیهوده، تبدیل شده |
| industry | ENUM | Nullable | Select | صنعت: IT، نفت، بازرگانی |
| rating | TINYINT | 1-5 | Star Rating | میزان حرارت لید (Hot, Warm, Cold) |
| lead_score | INT | Default 0 | Readonly | محاسبه اتوماتیک بر اساس فعالیت‌ها |
| assigned_to | BIGINT (FK) | Exists:users | User Select | کارشناس مسئول پیگیری |
| description | TEXT | Nullable | Textarea | توضیحات تکمیلی |
| address_json | JSONB | Nullable | Address Form | شامل خیابان، شهر، استان، کدپستی |
| converted_at | TIMESTAMP | Nullable | - | اگر پر باشد یعنی تبدیل شده است |

**منطق بیزینس (Business Logic):**

- **تشخیص تکراری:** قبل از اینسرت، اگر موبایل یا ایمیل وجود داشت، سیستم باید هشدار "Possible Duplicate" بدهد و لینک رکورد قبلی را نمایش دهد.
- **تبدیل (Conversion):** وقتی دکمه Convert زده می‌شود:
  - یک رکورد در `crm_accounts` ساخته شود (از روی فیلد company).
  - یک رکورد در `crm_contacts` ساخته شود (نام و موبایل).
  - (اختیاری) یک رکورد در `crm_deals` ساخته شود.
  - رکورد Lead آرشیو می‌شود (پاک نمی‌شود).

---

### ۲.۲ جدول شرکت‌ها (crm_accounts)

هسته مرکزی دیتای B2B.

| نام فیلد | نوع داده | اعتبارسنجی | نوع ورودی | توضیحات فنی |
|---------|---------|-----------|----------|------------|
| id | BIGINT (PK) | - | - | - |
| name | VARCHAR(200) | Required | Text Input | نام حقوقی کامل |
| account_code | VARCHAR(50) | Unique | Text Input | کد حسابداری (برای سینک با ماژول حسابداری) |
| website | VARCHAR(200) | URL | Input | - |
| parent_id | BIGINT (FK) | Exists:crm_accounts | Select (Search) | برای ساختار هلدینگی (Self-Referencing) |
| type | ENUM | Required | Select | Customer, Partner, Competitor, Vendor |
| tax_id | VARCHAR(50) | Nullable | Input | شناسه ملی / کد اقتصادی |
| employees_count | INT | Nullable | Number Input | تعداد پرسنل |
| annual_revenue | DECIMAL(15,2) | Nullable | Currency Input | درآمد سالانه |
| billing_address | JSONB | - | Form Group | آدرس فاکتور |
| shipping_address | JSONB | - | Form Group | آدرس ارسال بار |
| owner_id | BIGINT (FK) | Exists:users | User Select | اکانت منیجر (Account Manager) |
| created_at | TIMESTAMP | - | - | - |
| updated_at | TIMESTAMP | - | - | - |

---

### ۲.۳ جدول اشخاص (crm_contacts)

افرادی که در شرکت‌ها کار می‌کنند.

| نام فیلد | نوع داده | توضیحات فنی |
|---------|---------|------------|
| id | BIGINT (PK) | - |
| account_id | BIGINT (FK) | ارتباط با جدول شرکت‌ها (Cascade on Delete) |
| first_name | VARCHAR(100) | - |
| last_name | VARCHAR(100) | - |
| email | VARCHAR(150) | - |
| mobile | VARCHAR(20) | - |
| phone | VARCHAR(20) | - |
| job_title | VARCHAR(100) | - |
| reports_to | BIGINT (FK) | ارتباط با خود جدول Contacts (مدیر مستقیم شخص) |
| decision_role | ENUM | نقش در خرید: Decision Maker, Influencer, End User, Gatekeeper |
| is_primary | BOOLEAN | آیا این شخص رابط اصلی شرکت است؟ |
| social_profiles | JSONB | لینک‌های لینکدین، توییتر و... |
| created_at | TIMESTAMP | - |
| updated_at | TIMESTAMP | - |

---

### ۲.۴ جدول فرصت‌های فروش (crm_deals)

مهم‌ترین بخش برای گزارش‌گیری مالی.

| نام فیلد | نوع داده | نوع ورودی | توضیحات فنی |
|---------|---------|----------|------------|
| id | BIGINT (PK) | - | - |
| name | VARCHAR(255) | Text | عنوان معامله |
| account_id | BIGINT (FK) | Select | مربوط به کدام شرکت؟ |
| contact_id | BIGINT (FK) | Select | طرف حساب مذاکره کیست؟ |
| pipeline_id | INT (FK) | Select | کدام قیف فروش؟ (شاید چند پایپ‌لاین داشته باشیم) |
| stage_id | INT (FK) | Select | مرحله فروش (مذاکره، پروپوزال،...) |
| amount | DECIMAL(15,2) | Currency | مبلغ کل معامله |
| probability | TINYINT | 0-100% | احتمال موفقیت (معمولاً از Stage خوانده می‌شود اما قابل ویرایش است) |
| close_date | DATE | DatePicker | تاریخ تخمینی بستن قرارداد |
| type | ENUM | Select | New Business, Existing Business (Upsell) |
| loss_reason | TEXT | Modal Input | اگر Stage=Lost شد، این فیلد اجباری می‌شود |
| campaign_source | VARCHAR(255) | Text | نام کمپین تبلیغاتی ورودی |
| assigned_to | BIGINT (FK) | User Select | مسئول فروش |
| created_at | TIMESTAMP | - | - |
| updated_at | TIMESTAMP | - | - |

#### زیر-جدول محصولات معامله (crm_deal_products)

رابطه Many-to-Many بین Deal و Product.

| نام فیلد | نوع داده | توضیحات |
|---------|---------|---------|
| id | BIGINT (PK) | - |
| deal_id | BIGINT (FK) | شناسه معامله |
| product_id | BIGINT (FK) | شناسه محصول (از ماژول انبار/محصول) |
| quantity | DECIMAL(10,2) | تعداد |
| unit_price | DECIMAL(15,2) | قیمت واحد (در لحظه افزودن کپی می‌شود تا تغییر قیمت در آینده روی این اثر نگذارد) |
| discount | DECIMAL(15,2) | تخفیف روی این ردیف |
| tax | DECIMAL(15,2) | مالیات |
| total | DECIMAL(15,2) | (Quantity * UnitPrice) - Discount + Tax |

---

### ۲.۵ جدول فعالیت‌ها (crm_activities)

فعالیت‌های مرتبط با موجودیت‌های مختلف (Polymorphic).

| نام فیلد | نوع داده | توضیحات فنی |
|---------|---------|------------|
| id | BIGINT (PK) | - |
| type | ENUM | call, meeting, email, task |
| subject | VARCHAR(255) | موضوع فعالیت |
| description | TEXT | توضیحات |
| related_model | VARCHAR(255) | مدل مرتبط (مثلاً: App\Modules\CRM\Models\Deal) |
| related_id | BIGINT | شناسه رکورد مرتبط |
| outcome | VARCHAR(100) | نتیجه: Success, Failed, Pending |
| scheduled_at | TIMESTAMP | زمان برنامه‌ریزی شده |
| completed_at | TIMESTAMP | زمان تکمیل |
| assigned_to | BIGINT (FK) | Exists:users |
| created_by | BIGINT (FK) | Exists:users |
| created_at | TIMESTAMP | - |
| updated_at | TIMESTAMP | - |

---

### ۲.۶ جدول مراحل فروش (crm_stages)

مراحل پایپ‌لاین فروش.

| نام فیلد | نوع داده | توضیحات فنی |
|---------|---------|------------|
| id | INT (PK) | - |
| pipeline_id | INT (FK) | Exists:crm_pipelines |
| name | VARCHAR(100) | نام مرحله |
| order | INT | ترتیب نمایش |
| probability | TINYINT | احتمال موفقیت پیش‌فرض (0-100) |
| color | VARCHAR(7) | رنگ نمایش در کانبان |
| is_closed | BOOLEAN | آیا این مرحله بسته شده است؟ (Won/Lost) |
| created_at | TIMESTAMP | - |
| updated_at | TIMESTAMP | - |

---

### ۲.۷ جدول پایپ‌لاین‌ها (crm_pipelines)

تعریف پایپ‌لاین‌های مختلف فروش.

| نام فیلد | نوع داده | توضیحات فنی |
|---------|---------|------------|
| id | INT (PK) | - |
| name | VARCHAR(100) | نام پایپ‌لاین |
| description | TEXT | توضیحات |
| is_default | BOOLEAN | آیا پایپ‌لاین پیش‌فرض است؟ |
| created_at | TIMESTAMP | - |
| updated_at | TIMESTAMP | - |

---

### ۲.۸ جدول منابع (crm_sources)

منابع ورود لیدها.

| نام فیلد | نوع داده | توضیحات فنی |
|---------|---------|------------|
| id | INT (PK) | - |
| name | VARCHAR(100) | نام منبع |
| description | TEXT | توضیحات |
| created_at | TIMESTAMP | - |
| updated_at | TIMESTAMP | - |

---

### ۲.۹ جدول وضعیت‌ها (crm_statuses)

وضعیت‌های مختلف لیدها.

| نام فیلد | نوع داده | توضیحات فنی |
|---------|---------|------------|
| id | INT (PK) | - |
| name | VARCHAR(100) | نام وضعیت |
| color | VARCHAR(7) | رنگ نمایش |
| order | INT | ترتیب نمایش |
| created_at | TIMESTAMP | - |
| updated_at | TIMESTAMP | - |

---

## ۳. ریز امکانات و لاجیک‌های فرانت‌اند (UX/UI Logic)

### ۳.۱ نمای کانبان (Kanban View for Deals)

- **کامپوننت:** استفاده از `dnd-kit` یا `react-beautiful-dnd` در Next.js.
- **ساختار:** هر ستون معادل یک Stage است.
- **عملکرد:**
  - با کشیدن کارت از ستون "مذاکره" به "ارسال پیش‌فاکتور"، یک API call به `/api/v1/crm/deals/{id}/move` ارسال می‌شود.
  - محاسبه جمع ستون: در هدر هر ستون، جمع مبلغ (SUM(amount)) تمام کارت‌های آن ستون نمایش داده شود.
  - رنگ‌بندی: اگر `close_date` گذشته باشد و معامله باز باشد، کارت به رنگ قرمز (Warning) درآید.

### ۳.۲ تایم‌لاین فعالیت‌ها (Activity Timeline)

- در صفحه جزئیات هر Lead، Account یا Deal، یک تب به نام "History" وجود دارد.
- این بخش باید Polymorphic باشد. یعنی تمام رکوردهای جدول `crm_activities` که `related_model` و `related_id` آن‌ها با رکورد فعلی مچ است را لود کند.
- **آیکون‌ها:** برای تماس (تلفن)، جلسه (آدمک)، ایمیل (نامه) و تسک (تیک) آیکون‌های متفاوت نمایش داده شود.
- **قابلیت Quick Add:** در بالای تایم‌لاین دکمه‌های "Log Call"، "New Task" وجود داشته باشد که بدون رفرش صفحه رکورد اضافه کنند.

---

## ۴. API Endpoints کلیدی

این لیست روت‌هایی است که باید در لاراول پیاده‌سازی شوند:

### Leads

- `GET /api/v1/crm/leads` - لیست با فیلتر، جستجو و صفحه‌بندی
- `POST /api/v1/crm/leads` - ایجاد
- `GET /api/v1/crm/leads/{id}` - جزئیات
- `PUT /api/v1/crm/leads/{id}` - ویرایش
- `DELETE /api/v1/crm/leads/{id}` - حذف
- `POST /api/v1/crm/leads/{id}/convert` - تبدیل به مشتری (پیچیده ترین متد)

  **Body:**
  ```json
  {
    "create_deal": true,
    "deal_name": "...",
    "account_name": "..."
  }
  ```

### Accounts

- `GET /api/v1/crm/accounts` - لیست با فیلتر، جستجو و صفحه‌بندی
- `POST /api/v1/crm/accounts` - ایجاد
- `GET /api/v1/crm/accounts/{id}` - جزئیات
- `PUT /api/v1/crm/accounts/{id}` - ویرایش
- `DELETE /api/v1/crm/accounts/{id}` - حذف

### Contacts

- `GET /api/v1/crm/contacts` - لیست با فیلتر، جستجو و صفحه‌بندی
- `POST /api/v1/crm/contacts` - ایجاد
- `GET /api/v1/crm/contacts/{id}` - جزئیات
- `PUT /api/v1/crm/contacts/{id}` - ویرایش
- `DELETE /api/v1/crm/contacts/{id}` - حذف

### Deals

- `GET /api/v1/crm/deals` - لیست با فیلتر، جستجو و صفحه‌بندی
- `POST /api/v1/crm/deals` - ایجاد
- `GET /api/v1/crm/deals/{id}` - جزئیات
- `PUT /api/v1/crm/deals/{id}` - ویرایش
- `DELETE /api/v1/crm/deals/{id}` - حذف
- `GET /api/v1/crm/pipelines/{id}/kanban` - گرفتن دیتا مخصوص ویو کانبان
- `PATCH /api/v1/crm/deals/{id}/move` - تغییر استیج

  **Body:**
  ```json
  {
    "new_stage_id": 5,
    "position": 2
  }
  ```

### Activities

- `GET /api/v1/crm/activities` - لیست فعالیت‌ها
- `POST /api/v1/crm/activities` - ایجاد فعالیت

  **Body:**
  ```json
  {
    "type": "call",
    "related_model": "App\\Modules\\CRM\\Models\\Deal",
    "related_id": 10,
    "outcome": "Success"
  }
  ```

- `GET /api/v1/crm/activities/{id}` - جزئیات
- `PUT /api/v1/crm/activities/{id}` - ویرایش
- `DELETE /api/v1/crm/activities/{id}` - حذف

### Pipelines & Stages

- `GET /api/v1/crm/pipelines` - لیست پایپ‌لاین‌ها
- `POST /api/v1/crm/pipelines` - ایجاد پایپ‌لاین
- `GET /api/v1/crm/pipelines/{id}/stages` - لیست مراحل یک پایپ‌لاین
- `POST /api/v1/crm/stages` - ایجاد مرحله
- `PUT /api/v1/crm/stages/{id}` - ویرایش مرحله
- `DELETE /api/v1/crm/stages/{id}` - حذف مرحله

### Sources & Statuses

- `GET /api/v1/crm/sources` - لیست منابع
- `GET /api/v1/crm/statuses` - لیست وضعیت‌ها

---

## ۵. سناریوهای اتوماسیون (Workflows & Logic)

این بخش "هوشمندی" سیستم را تعریف می‌کند.

### ۵.۱ سناریوی تغییر استیج (Stage Change Trigger)

**رخداد:** وقتی استیج یک Deal به "پیش‌فاکتور" تغییر کرد.

**اکشن سیستم:**
1. چک کن آیا برای این Deal، محصولی (`crm_deal_products`) ثبت شده است؟
2. اگر نه، خطا بده: "لطفا ابتدا محصولات را به معامله اضافه کنید."
3. اگر بله، به صورت خودکار یک "تسک پیگیری" برای ۳ روز آینده برای مسئول فروش ست کن.

### ۵.۲ سناریوی امتیازدهی (Lead Scoring)

یک Observer روی مدل‌های مختلف ست می‌شود:

- باز کردن ایمیل مارکتینگ: +5 امتیاز.
- کلیک روی لینک داخل ایمیل: +10 امتیاز.
- ثبت اطلاعات ناقص (نداشتن ایمیل سازمانی): -10 امتیاز.
- اگر امتیاز > 50 شد -> وضعیت لید را به "Hot" تغییر بده و به مدیر فروش نوتیفیکیشن بفرست.

---

## ۶. دسترسی‌ها و امنیت (Permissions)

علاوه بر نقش‌های کلی، در CRM ریز دسترسی‌های زیر نیاز است:

- `crm.leads.view_all` - دیدن همه سرنخ‌ها (مخصوص مدیر)
- `crm.leads.view_own` - دیدن سرنخ‌های خود (مخصوص کارشناس)
- `crm.leads.create` - ایجاد سرنخ
- `crm.leads.update` - ویرایش سرنخ
- `crm.leads.delete` - حذف سرنخ
- `crm.leads.convert` - تبدیل سرنخ به مشتری
- `crm.accounts.view_all` - دیدن همه حساب‌ها
- `crm.accounts.view_own` - دیدن حساب‌های خود
- `crm.accounts.create` - ایجاد حساب
- `crm.accounts.update` - ویرایش حساب
- `crm.accounts.delete` - حذف حساب
- `crm.deals.view_all` - دیدن همه معاملات
- `crm.deals.view_own` - دیدن معاملات خود
- `crm.deals.create` - ایجاد معامله
- `crm.deals.update` - ویرایش معامله
- `crm.deals.delete` - حذف معامله
- `crm.deals.approve_discount` - توانایی تایید تخفیف بالای ۲۰٪
- `crm.export` - توانایی خروجی اکسل گرفتن (بسیار حساس)
- `crm.settings.manage` - تعریف استیج‌ها و پایپ‌لاین‌ها

---

## ۷. نکات پیاده‌سازی وایت‌لیبل (White-label Details)

برای اینکه CRM کاملاً حس سیستم اختصاصی مشتری را بدهد:

- **Prefix شماره‌ها:** در تنظیمات ماژول CRM، فیلدی باشد به نام `quote_prefix`. مثلا مشتری ست می‌کند `INV-`. سیستم فاکتورها را `INV-1001` شماره می‌زند.
- **Terms & Conditions:** متون پیش‌فرض پایین پیش‌فاکتورها باید در تنظیمات ماژول قابل ویرایش باشد.
- **PDF Template:** تمپلیت پیش‌فاکتور باید لوگو و رنگ سازمانی (که در ماژول Core ست شده) را بخواند و هدر/فوتر را بر اساس آن بسازد.
