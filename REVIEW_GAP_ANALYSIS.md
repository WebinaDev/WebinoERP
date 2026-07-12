# Gap Analysis Report - Webina Enterprise CRM

**تاریخ بررسی:** ژانویه 2026  
**نسخه:** 1.0.0

---

## خلاصه اجرایی (Executive Summary)

این گزارش خلاصه‌ای از اطلاعات ناقص، مبهم یا مفقود در مستندات معماری و مشخصات فنی ماژول CRM را ارائه می‌دهد. این شکاف‌ها باید قبل از شروع توسعه برطرف شوند.

---

## ۱. شکاف‌های دیتابیس (Database Gaps)

### ۱.۱ جداول ناقص یا تعریف نشده

#### جدول `crm_sources`
- **وضعیت:** در CRM.MD به عنوان FK در `crm_leads.source_id` ارجاع داده شده اما تعریف نشده
- **اقدام لازم:** تعریف کامل جدول با فیلدهای id, name, description, created_at, updated_at
- **اولویت:** بالا

#### جدول `crm_statuses`
- **وضعیت:** در CRM.MD به عنوان FK در `crm_leads.status_id` ارجاع داده شده اما تعریف نشده
- **اقدام لازم:** تعریف کامل جدول با فیلدهای id, name, color, order, created_at, updated_at
- **اولویت:** بالا

#### جدول `crm_pipelines`
- **وضعیت:** در CRM.MD به عنوان FK در `crm_deals.pipeline_id` ارجاع داده شده اما تعریف نشده
- **اقدام لازم:** تعریف کامل جدول (در CRM.MD فرمت شده اضافه شد)
- **اولویت:** بالا

#### جدول `crm_stages`
- **وضعیت:** در CRM.MD به عنوان FK در `crm_deals.stage_id` ارجاع داده شده اما تعریف نشده
- **اقدام لازم:** تعریف کامل جدول (در CRM.MD فرمت شده اضافه شد)
- **اولویت:** بالا

#### جدول `crm_activities`
- **وضعیت:** در CRM.MD به عنوان موجودیت اصلی ذکر شده اما ساختار کامل آن تعریف نشده
- **اقدام لازم:** تعریف کامل جدول با فیلدهای polymorphic (در CRM.MD فرمت شده اضافه شد)
- **اولویت:** بالا

### ۱.۲ فیلدهای ناقص در جداول موجود

#### جدول `crm_leads`
- **فیلدهای مفقود:**
  - `created_at`, `updated_at` - برای audit trail
  - `deleted_at` - برای soft delete
  - `created_by` - برای track کردن سازنده
  - `notes` - برای یادداشت‌های اضافی

#### جدول `crm_accounts`
- **فیلدهای مفقود:**
  - `created_at`, `updated_at` - برای audit trail
  - `deleted_at` - برای soft delete
  - `created_by` - برای track کردن سازنده
  - `industry` - صنعت شرکت (مشابه leads)
  - `description` - توضیحات تکمیلی

#### جدول `crm_contacts`
- **فیلدهای مفقود:**
  - `created_at`, `updated_at` - برای audit trail
  - `deleted_at` - برای soft delete
  - `created_by` - برای track کردن سازنده
  - `assigned_to` - مسئول پیگیری
  - `description` - توضیحات تکمیلی

#### جدول `crm_deals`
- **فیلدهای مفقود:**
  - `created_at`, `updated_at` - برای audit trail
  - `deleted_at` - برای soft delete
  - `created_by` - برای track کردن سازنده
  - `description` - توضیحات تکمیلی
  - `won_at`, `lost_at` - تاریخ بستن معامله

### ۱.۳ روابط و Constraints ناقص

- **Foreign Key Constraints:** در مستندات ذکر نشده که کدام روابط باید CASCADE DELETE داشته باشند
- **Indexes:** هیچ اشاره‌ای به indexes برای performance optimization نشده
- **Unique Constraints:** فقط `account_code` و `email` در leads به عنوان unique ذکر شده، سایر موارد مشخص نیست
- **Check Constraints:** محدودیت‌های دامنه (مثلاً probability بین 0-100) ذکر نشده

---

## ۲. شکاف‌های API (API Gaps)

### ۲.۱ Endpoints ناقص

#### Accounts Endpoints
- **وضعیت:** در CRM.MD اصلی هیچ endpoint برای Accounts ذکر نشده بود
- **اقدام لازم:** اضافه کردن CRUD کامل (در CRM.MD فرمت شده اضافه شد)
- **اولویت:** بالا

#### Contacts Endpoints
- **وضعیت:** در CRM.MD اصلی هیچ endpoint برای Contacts ذکر نشده بود
- **اقدام لازم:** اضافه کردن CRUD کامل (در CRM.MD فرمت شده اضافه شد)
- **اولویت:** بالا

#### Pipelines & Stages Management
- **وضعیت:** فقط endpoint برای kanban view ذکر شده بود
- **اقدام لازم:** اضافه کردن CRUD برای pipelines و stages (در CRM.MD فرمت شده اضافه شد)
- **اولویت:** متوسط

### ۲.۲ Request/Response Schemas

- **مشکل:** هیچ schema یا example برای request/response bodies ارائه نشده
- **اقدام لازم:** تعریف کامل:
  - Request validation rules
  - Response structure
  - Error response format
  - Pagination structure
- **اولویت:** بالا

### ۲.۳ فیلتر، جستجو و صفحه‌بندی

- **مشکل:** ذکر شده که endpoints باید فیلتر و جستجو داشته باشند اما جزئیات ارائه نشده
- **اقدام لازم:** تعریف:
  - Query parameters برای filtering
  - Search fields
  - Sort options
  - Pagination parameters (page, per_page)
- **اولویت:** متوسط

### ۲.۴ Bulk Operations

- **مشکل:** هیچ اشاره‌ای به bulk operations نشده
- **اقدام لازم:** تعریف endpoints برای:
  - Bulk delete
  - Bulk update
  - Bulk assign
- **اولویت:** پایین

---

## ۳. شکاف‌های Frontend (Frontend Gaps)

### ۳.۱ Component Specifications

- **مشکل:** جزئیات کامپوننت‌ها ناقص است
- **اقدام لازم:** تعریف:
  - Form validation rules (client-side)
  - Error handling UI
  - Loading states
  - Empty states
  - Success notifications

### ۳.۲ User Experience Details

- **مشکل:** جزئیات UX ناقص است
- **اقدام لازم:** تعریف:
  - Navigation flow
  - Confirmation dialogs
  - Undo/Redo functionality
  - Keyboard shortcuts
  - Accessibility requirements

### ۳.۳ RTL/LTR Support

- **مشکل:** در ARCHITECTURE.MD ذکر شده اما در CRM.MD جزئیات ندارد
- **اقدام لازم:** تعریف:
  - RTL layout برای فرم‌های CRM
  - RTL support برای Kanban view
  - Date picker RTL support

---

## ۴. شکاف‌های Business Logic (Business Logic Gaps)

### ۴.۱ Lead Conversion Process

- **مشکل:** فرآیند تبدیل Lead به Account/Contact/Deal به صورت کامل تعریف نشده
- **اقدام لازم:** تعریف:
  - Validation rules قبل از conversion
  - Conflict resolution (اگر Account از قبل وجود داشته باشد)
  - Rollback mechanism در صورت خطا
  - Notification triggers

### ۴.۲ Lead Scoring Algorithm

- **مشکل:** فقط مثال‌هایی از scoring ذکر شده اما algorithm کامل نیست
- **اقدام لازم:** تعریف:
  - Scoring rules کامل
  - Weight factors
  - Decay mechanism (کاهش امتیاز با گذشت زمان)
  - Threshold values

### ۴.۳ Stage Change Automation

- **مشکل:** فقط یک مثال ذکر شده
- **اقدام لازم:** تعریف:
  - تمام automation rules
  - Conditional logic
  - Error handling
  - Notification rules

### ۴.۴ Duplicate Detection

- **مشکل:** فقط ذکر شده که باید duplicate detection باشد
- **اقدام لازم:** تعریف:
  - Matching algorithm (fuzzy matching?)
  - Confidence score
  - Merge strategy
  - User approval workflow

---

## ۵. شکاف‌های Security & Permissions

### ۵.۱ Permission Granularity

- **مشکل:** لیست permissions ناقص است
- **اقدام لازم:** تعریف کامل permissions برای:
  - View own vs view all
  - Create, Update, Delete برای هر entity
  - Export permissions
  - Settings management

### ۵.۲ Data Access Control

- **مشکل:** نحوه کنترل دسترسی به داده‌ها مشخص نیست
- **اقدام لازم:** تعریف:
  - Row-level security rules
  - Field-level permissions
  - Data masking برای sensitive fields

---

## ۶. شکاف‌های Integration

### ۶.۱ Product Module Integration

- **مشکل:** CRM.MD وابستگی به Product Module را ذکر کرده اما جزئیات ندارد
- **اقدام لازم:** تعریف:
  - API contract بین CRM و Product modules
  - Data synchronization
  - Error handling

### ۶.۲ Accounting Module Integration

- **مشکل:** `account_code` در `crm_accounts` ذکر شده اما نحوه sync مشخص نیست
- **اقدام لازم:** تعریف:
  - Sync mechanism
  - Conflict resolution
  - One-way vs two-way sync

---

## ۷. شکاف‌های Infrastructure

### ۷.۱ Migration Strategy

- **مشکل:** در ARCHITECTURE.MD ذکر نشده
- **اقدام لازم:** تعریف:
  - Migration file structure
  - Seeder strategy
  - Rollback plan

### ۷.۲ Testing Strategy

- **مشکل:** هیچ اشاره‌ای به testing نشده
- **اقدام لازم:** تعریف:
  - Unit test coverage
  - Integration test strategy
  - E2E test scenarios
  - Performance testing

### ۷.۳ Logging & Monitoring

- **مشکل:** در ARCHITECTURE.MD ذکر نشده
- **اقدام لازم:** تعریف:
  - Logging strategy
  - Monitoring tools
  - Alerting rules
  - Performance metrics

### ۷.۴ Backup & Recovery

- **مشکل:** در ARCHITECTURE.MD ذکر نشده
- **اقدام لازم:** تعریف:
  - Backup strategy
  - Recovery procedures
  - Disaster recovery plan

---

## ۸. شکاف‌های Documentation

### ۸.۱ Code Examples

- **مشکل:** کدهای مثال ناقص یا قدیمی هستند
- **اقدام لازم:** به‌روزرسانی:
  - Laravel 11 syntax
  - Next.js 14 App Router examples
  - TypeScript examples

### ۸.۲ Diagrams

- **مشکل:** ERD diagram وجود ندارد
- **اقدام لازم:** ایجاد:
  - Complete ERD diagram
  - Sequence diagrams برای workflows
  - Architecture diagrams

---

## اولویت‌بندی اقدامات

### اولویت بالا (Critical)
1. تکمیل تعریف جداول ناقص (sources, statuses, pipelines, stages, activities)
2. تکمیل API endpoints برای Accounts و Contacts
3. تعریف Request/Response schemas
4. تکمیل فیلدهای audit trail (created_at, updated_at, deleted_at)

### اولویت متوسط (Important)
1. تعریف کامل Lead Conversion process
2. تکمیل Lead Scoring algorithm
3. تعریف فیلتر و جستجو برای APIs
4. تکمیل Permission system

### اولویت پایین (Nice to Have)
1. Bulk operations
2. Advanced automation rules
3. Integration details
4. Performance optimization

---

## نتیجه‌گیری

مستندات پایه خوبی دارند اما برای شروع توسعه نیاز به تکمیل دارند. مهم‌ترین شکاف‌ها در بخش دیتابیس و API هستند که باید قبل از شروع کدنویسی برطرف شوند.

