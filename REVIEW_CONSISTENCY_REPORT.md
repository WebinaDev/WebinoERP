# Consistency Report - Webina Enterprise CRM

**تاریخ بررسی:** ژانویه 2026  
**نسخه:** 1.0.0

---

## خلاصه اجرایی (Executive Summary)

این گزارش ناسازگاری‌ها و تناقض‌های بین مستندات ARCHITECTURE.MD و CRM.MD را بررسی می‌کند.

---

## ۱. ناسازگاری‌های Technology Stack

### ۱.۱ Frontend Framework

- **ARCHITECTURE.MD:** Next.js 14+ (App Router)
- **CRM.MD:** Next.js (بدون ذکر نسخه)
- **وضعیت:** ناسازگاری جزئی
- **توصیه:** در CRM.MD ذکر شود که از Next.js 14+ App Router استفاده می‌شود

### ۱.۲ Drag and Drop Library

- **CRM.MD:** ذکر شده `dnd-kit` یا `react-beautiful-dnd`
- **ARCHITECTURE.MD:** ذکر نشده
- **وضعیت:** ناسازگاری جزئی
- **توصیه:** در ARCHITECTURE.MD ذکر شود که برای drag-and-drop از `dnd-kit` استفاده می‌شود (react-beautiful-dnd deprecated است)

---

## ۲. ناسازگاری‌های معماری (Architecture Inconsistencies)

### ۲.۱ Module Structure

- **ARCHITECTURE.MD:** ساختار `Modules/CRM/` را تعریف کرده
- **CRM.MD:** در API examples از `App\Modules\CRM\Models\Deal` استفاده کرده
- **وضعیت:** سازگار ✓
- **یادداشت:** Namespace با ساختار دایرکتوری همخوانی دارد

### ۲.۲ API Versioning

- **ARCHITECTURE.MD:** ذکر نشده
- **CRM.MD:** از `/api/v1/crm/` استفاده می‌کند
- **وضعیت:** ناسازگاری جزئی
- **توصیه:** در ARCHITECTURE.MD ذکر شود که API versioning استفاده می‌شود

---

## ۳. ناسازگاری‌های Database Schema

### ۳.۱ Naming Conventions

- **ARCHITECTURE.MD:** جداول Core با prefix `system_` (system_modules, system_settings)
- **CRM.MD:** جداول CRM با prefix `crm_` (crm_leads, crm_accounts)
- **وضعیت:** سازگار ✓
- **یادداشت:** Naming convention یکنواخت است

### ۳.۲ Data Types

- **ARCHITECTURE.MD:** PostgreSQL 15+ با SERIAL برای PK
- **CRM.MD:** از BIGINT برای PK استفاده می‌کند
- **وضعیت:** ناسازگاری جزئی
- **توصیه:** باید تصمیم گرفته شود:
  - SERIAL (auto-increment integer) برای tables کوچک
  - BIGSERIAL (auto-increment bigint) برای tables بزرگ
  - یا BIGINT با sequence برای consistency

---

## ۴. ناسازگاری‌های Authentication & Authorization

### ۴.۱ Authentication Method

- **ARCHITECTURE.MD:** Laravel Sanctum (Stateful for Web / Token for Mobile)
- **CRM.MD:** در API examples از `auth:sanctum` middleware استفاده می‌کند
- **وضعیت:** سازگار ✓

### ۴.۲ Permission System

- **ARCHITECTURE.MD:** Spatie Laravel Permission
- **CRM.MD:** Permission naming با dot notation (`crm.leads.view_all`)
- **وضعیت:** سازگار ✓
- **یادداشت:** Spatie Permission از dot notation پشتیبانی می‌کند

---

## ۵. ناسازگاری‌های White-label Implementation

### ۵.۱ Theme Configuration

- **ARCHITECTURE.MD:** Runtime theme configuration از `/api/v1/core/config`
- **CRM.MD:** PDF template باید از Core module theme settings استفاده کند
- **وضعیت:** سازگار ✓
- **یادداشت:** CRM module به Core module برای theme settings وابسته است

### ۵.۲ Footer Copyright

- **ARCHITECTURE.MD:** Footer باید ثابت باشد و از دیتابیس خوانده نشود
- **CRM.MD:** ذکر نشده
- **وضعیت:** سازگار ✓ (عدم ذکر در CRM منطقی است چون مربوط به Core است)

---

## ۶. ناسازگاری‌های API Design

### ۶.۱ API Base Path

- **ARCHITECTURE.MD:** ذکر نشده
- **CRM.MD:** `/api/v1/crm/`
- **وضعیت:** ناسازگاری جزئی
- **توصیه:** در ARCHITECTURE.MD ذکر شود که base path `/api/v1/` است

### ۶.۲ HTTP Methods

- **ARCHITECTURE.MD:** RESTful API (JSON) - ذکر شده
- **CRM.MD:** از PUT برای update استفاده می‌کند (RESTful standard)
- **وضعیت:** سازگار ✓

---

## ۷. ناسازگاری‌های Docker Configuration

### ۷.۱ Service Names

- **ARCHITECTURE.MD:** services: nginx, app, web, db, redis
- **CRM.MD:** ذکر نشده
- **وضعیت:** سازگار ✓ (CRM.MD نیازی به ذکر Docker config ندارد)

### ۷.۲ Environment Variables

- **ARCHITECTURE.MD:** `NEXT_PUBLIC_API_URL: http://nginx/api`
- **CRM.MD:** ذکر نشده
- **وضعیت:** ناسازگاری جزئی
- **مشکل:** در ARCHITECTURE.MD `/api` ذکر شده اما در CRM.MD `/api/v1/crm/` استفاده می‌شود
- **توصیه:** باید مشخص شود که base URL چیست:
  - `NEXT_PUBLIC_API_URL=http://nginx/api/v1` یا
  - `NEXT_PUBLIC_API_URL=http://nginx/api`

---

## ۸. ناسازگاری‌های Internationalization

### ۸.۱ Locale Support

- **ARCHITECTURE.MD:** next-intl با پشتیبانی RTL/LTR
- **CRM.MD:** ذکر نشده
- **وضعیت:** ناسازگاری جزئی
- **توصیه:** در CRM.MD ذکر شود که تمام UI components باید RTL/LTR support داشته باشند

### ۸.۲ Date Format

- **ARCHITECTURE.MD:** ذکر نشده
- **CRM.MD:** از DATE و TIMESTAMP استفاده می‌کند
- **وضعیت:** نیاز به توضیح
- **توصیه:** باید مشخص شود که:
  - Date format در database: ISO 8601
  - Date format در UI: بر اساس locale
  - Timezone handling: UTC در database

---

## ۹. ناسازگاری‌های Business Logic

### ۹.۱ Module Licensing

- **ARCHITECTURE.MD:** Middleware `module:crm` برای چک کردن لایسنس
- **CRM.MD:** ذکر نشده
- **وضعیت:** سازگار ✓ (CRM.MD نیازی به ذکر middleware ندارد)

### ۹.۲ Dynamic Sidebar

- **ARCHITECTURE.MD:** Sidebar باید dynamic باشد و از `user.active_modules` استفاده کند
- **CRM.MD:** ذکر نشده
- **وضعیت:** سازگار ✓
- **یادداشت:** CRM menu items باید فقط زمانی نمایش داده شوند که module active باشد

---

## ۱۰. ناسازگاری‌های Development Roadmap

### ۱۰.۱ Phase Alignment

- **ARCHITECTURE.MD:** فاز دو: توسعه ماژول CRM
- **CRM.MD:** مستند کامل CRM module
- **وضعیت:** سازگار ✓
- **یادداشت:** CRM.MD برای فاز دو آماده است

---

## خلاصه ناسازگاری‌ها

### ناسازگاری‌های بحرانی (Critical)
هیچ ناسازگاری بحرانی وجود ندارد.

### ناسازگاری‌های مهم (Important)
1. **API Base Path:** باید در ARCHITECTURE.MD مشخص شود که `/api/v1/` است
2. **Data Types:** باید تصمیم گرفته شود SERIAL vs BIGSERIAL
3. **Environment Variables:** باید base URL مشخص شود

### ناسازگاری‌های جزئی (Minor)
1. **Frontend Library Versions:** در CRM.MD باید نسخه‌ها ذکر شوند
2. **Date/Time Handling:** باید timezone strategy مشخص شود
3. **RTL Support:** در CRM.MD باید جزئیات بیشتری ذکر شود

---

## توصیه‌های اصلاحی

### ۱. تکمیل ARCHITECTURE.MD
- اضافه کردن بخش API Design شامل base path و versioning
- اضافه کردن بخش Data Types Strategy
- اضافه کردن بخش Date/Time Handling

### ۲. تکمیل CRM.MD
- اضافه کردن نسخه‌های دقیق libraries
- اضافه کردن جزئیات RTL/LTR support
- اضافه کردن timezone handling strategy

### ۳. ایجاد مستند مرجع
- ایجاد یک مستند مرجع برای:
  - Naming conventions
  - API standards
  - Database standards
  - Code style guide

---

## نتیجه‌گیری

به طور کلی، مستندات با هم سازگار هستند و ناسازگاری‌های موجود جزئی و قابل رفع هستند. بیشتر ناسازگاری‌ها مربوط به جزئیات فنی است که باید در مستندات تکمیل شوند.

