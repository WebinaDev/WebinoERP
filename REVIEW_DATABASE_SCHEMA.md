# Complete Database Schema - CRM Module

**تاریخ:** ژانویه 2026  
**نسخه:** 1.0.0

این سند شامل تعریف کامل تمام جداول ماژول CRM است که در مستندات اصلی ناقص یا تعریف نشده بودند.

---

## ۱. جداول اصلی (Core Tables)

### ۱.۱ جدول منابع (crm_sources)

```sql
CREATE TABLE crm_sources (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    color VARCHAR(7) NULL, -- Hex color for UI display
    order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_crm_sources_active ON crm_sources(is_active);
```

**داده‌های پیش‌فرض:**
- Website
- LinkedIn
- Exhibition
- Referral
- Cold Call
- Email Campaign

---

### ۱.۲ جدول وضعیت‌ها (crm_statuses)

```sql
CREATE TABLE crm_statuses (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) NOT NULL, -- Hex color for badge display
    order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_crm_statuses_active ON crm_statuses(is_active);
CREATE INDEX idx_crm_statuses_order ON crm_statuses(order);
```

**داده‌های پیش‌فرض:**
- New (رنگ: آبی)
- Contacted (رنگ: زرد)
- Qualified (رنگ: سبز)
- Unqualified (رنگ: قرمز)
- Converted (رنگ: بنفش)

---

### ۱.۳ جدول پایپ‌لاین‌ها (crm_pipelines)

```sql
CREATE TABLE crm_pipelines (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by BIGINT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_crm_pipelines_default ON crm_pipelines(is_default);
CREATE INDEX idx_crm_pipelines_active ON crm_pipelines(is_active);
```

**Constraint:** فقط یک pipeline می‌تواند `is_default = TRUE` باشد.

---

### ۱.۴ جدول مراحل فروش (crm_stages)

```sql
CREATE TABLE crm_stages (
    id SERIAL PRIMARY KEY,
    pipeline_id INT NOT NULL REFERENCES crm_pipelines(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    order INT NOT NULL,
    probability TINYINT NOT NULL DEFAULT 0 CHECK (probability >= 0 AND probability <= 100),
    color VARCHAR(7) NOT NULL, -- Hex color for kanban column
    is_closed BOOLEAN DEFAULT FALSE, -- True for Won/Lost stages
    is_won BOOLEAN DEFAULT FALSE, -- True if this is a "Won" stage
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(pipeline_id, order)
);

CREATE INDEX idx_crm_stages_pipeline ON crm_stages(pipeline_id);
CREATE INDEX idx_crm_stages_order ON crm_stages(pipeline_id, order);
```

**داده‌های پیش‌فرض برای Default Pipeline:**
1. Lead (probability: 10%, color: #94a3b8)
2. Qualification (probability: 25%, color: #60a5fa)
3. Proposal (probability: 50%, color: #fbbf24)
4. Negotiation (probability: 75%, color: #f97316)
5. Won (probability: 100%, color: #10b981, is_closed: TRUE, is_won: TRUE)
6. Lost (probability: 0%, color: #ef4444, is_closed: TRUE, is_won: FALSE)

---

## ۲. جداول موجودیت‌های اصلی (Entity Tables)

### ۲.۱ جدول سرنخ‌ها (crm_leads) - تکمیل شده

```sql
CREATE TABLE crm_leads (
    id BIGSERIAL PRIMARY KEY,
    topic VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    company VARCHAR(150) NULL,
    job_title VARCHAR(100) NULL,
    email VARCHAR(150) NULL,
    mobile VARCHAR(20) NOT NULL,
    phone VARCHAR(20) NULL,
    source_id INT NULL REFERENCES crm_sources(id),
    status_id INT NOT NULL REFERENCES crm_statuses(id),
    industry VARCHAR(50) NULL,
    rating TINYINT NULL CHECK (rating >= 1 AND rating <= 5),
    lead_score INT DEFAULT 0,
    assigned_to BIGINT NULL REFERENCES users(id),
    description TEXT NULL,
    address_json JSONB NULL,
    converted_at TIMESTAMP NULL,
    converted_to_account_id BIGINT NULL REFERENCES crm_accounts(id),
    created_by BIGINT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX idx_crm_leads_email ON crm_leads(email) WHERE email IS NOT NULL;
CREATE INDEX idx_crm_leads_mobile ON crm_leads(mobile);
CREATE INDEX idx_crm_leads_assigned_to ON crm_leads(assigned_to);
CREATE INDEX idx_crm_leads_status ON crm_leads(status_id);
CREATE INDEX idx_crm_leads_source ON crm_leads(source_id);
CREATE INDEX idx_crm_leads_deleted ON crm_leads(deleted_at) WHERE deleted_at IS NULL;
CREATE INDEX idx_crm_leads_created_at ON crm_leads(created_at DESC);

-- Unique constraint for active leads (not deleted, not converted)
CREATE UNIQUE INDEX uq_crm_leads_email_active ON crm_leads(email) 
    WHERE email IS NOT NULL AND deleted_at IS NULL AND converted_at IS NULL;
```

**نکات:**
- `email` می‌تواند NULL باشد اما اگر مقدار داشته باشد باید unique باشد (فقط برای leads فعال)
- `mobile` اجباری است و باید با فرمت E.164 ذخیره شود
- `converted_to_account_id` برای track کردن اینکه lead به کدام account تبدیل شده

---

### ۲.۲ جدول شرکت‌ها (crm_accounts) - تکمیل شده

```sql
CREATE TABLE crm_accounts (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    account_code VARCHAR(50) NULL UNIQUE,
    website VARCHAR(200) NULL,
    parent_id BIGINT NULL REFERENCES crm_accounts(id) ON DELETE SET NULL,
    type VARCHAR(20) NOT NULL CHECK (type IN ('Customer', 'Partner', 'Competitor', 'Vendor')),
    tax_id VARCHAR(50) NULL,
    industry VARCHAR(50) NULL,
    employees_count INT NULL CHECK (employees_count >= 0),
    annual_revenue DECIMAL(15,2) NULL CHECK (annual_revenue >= 0),
    billing_address JSONB NULL,
    shipping_address JSONB NULL,
    owner_id BIGINT NULL REFERENCES users(id),
    description TEXT NULL,
    created_by BIGINT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX idx_crm_accounts_name ON crm_accounts(name);
CREATE INDEX idx_crm_accounts_account_code ON crm_accounts(account_code) WHERE account_code IS NOT NULL;
CREATE INDEX idx_crm_accounts_parent ON crm_accounts(parent_id);
CREATE INDEX idx_crm_accounts_type ON crm_accounts(type);
CREATE INDEX idx_crm_accounts_owner ON crm_accounts(owner_id);
CREATE INDEX idx_crm_accounts_deleted ON crm_accounts(deleted_at) WHERE deleted_at IS NULL;

-- Full-text search index
CREATE INDEX idx_crm_accounts_search ON crm_accounts USING gin(to_tsvector('persian', name || ' ' || COALESCE(description, '')));
```

**ساختار JSONB برای آدرس:**
```json
{
  "street": "خیابان ولیعصر",
  "city": "تهران",
  "province": "تهران",
  "postal_code": "1234567890",
  "country": "ایران"
}
```

---

### ۲.۳ جدول اشخاص (crm_contacts) - تکمیل شده

```sql
CREATE TABLE crm_contacts (
    id BIGSERIAL PRIMARY KEY,
    account_id BIGINT NOT NULL REFERENCES crm_accounts(id) ON DELETE CASCADE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NULL,
    mobile VARCHAR(20) NULL,
    phone VARCHAR(20) NULL,
    job_title VARCHAR(100) NULL,
    reports_to BIGINT NULL REFERENCES crm_contacts(id) ON DELETE SET NULL,
    decision_role VARCHAR(20) NULL CHECK (decision_role IN ('Decision Maker', 'Influencer', 'End User', 'Gatekeeper')),
    is_primary BOOLEAN DEFAULT FALSE,
    social_profiles JSONB NULL,
    assigned_to BIGINT NULL REFERENCES users(id),
    description TEXT NULL,
    created_by BIGINT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX idx_crm_contacts_account ON crm_contacts(account_id);
CREATE INDEX idx_crm_contacts_email ON crm_contacts(email) WHERE email IS NOT NULL;
CREATE INDEX idx_crm_contacts_mobile ON crm_contacts(mobile) WHERE mobile IS NOT NULL;
CREATE INDEX idx_crm_contacts_reports_to ON crm_contacts(reports_to);
CREATE INDEX idx_crm_contacts_primary ON crm_contacts(account_id, is_primary) WHERE is_primary = TRUE;
CREATE INDEX idx_crm_contacts_deleted ON crm_contacts(deleted_at) WHERE deleted_at IS NULL;

-- Constraint: Only one primary contact per account
CREATE UNIQUE INDEX uq_crm_contacts_primary ON crm_contacts(account_id) 
    WHERE is_primary = TRUE AND deleted_at IS NULL;
```

**ساختار JSONB برای social_profiles:**
```json
{
  "linkedin": "https://linkedin.com/in/username",
  "twitter": "https://twitter.com/username",
  "facebook": "https://facebook.com/username"
}
```

---

### ۲.۴ جدول فرصت‌های فروش (crm_deals) - تکمیل شده

```sql
CREATE TABLE crm_deals (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    account_id BIGINT NOT NULL REFERENCES crm_accounts(id) ON DELETE RESTRICT,
    contact_id BIGINT NULL REFERENCES crm_contacts(id) ON DELETE SET NULL,
    pipeline_id INT NOT NULL REFERENCES crm_pipelines(id) ON DELETE RESTRICT,
    stage_id INT NOT NULL REFERENCES crm_stages(id) ON DELETE RESTRICT,
    amount DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (amount >= 0),
    probability TINYINT NOT NULL DEFAULT 0 CHECK (probability >= 0 AND probability <= 100),
    close_date DATE NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'New Business' CHECK (type IN ('New Business', 'Existing Business')),
    loss_reason TEXT NULL,
    campaign_source VARCHAR(255) NULL,
    assigned_to BIGINT NULL REFERENCES users(id),
    description TEXT NULL,
    won_at TIMESTAMP NULL,
    lost_at TIMESTAMP NULL,
    created_by BIGINT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    CONSTRAINT chk_deal_loss_reason CHECK (
        (loss_reason IS NOT NULL AND lost_at IS NOT NULL) OR 
        (loss_reason IS NULL)
    )
);

CREATE INDEX idx_crm_deals_account ON crm_deals(account_id);
CREATE INDEX idx_crm_deals_contact ON crm_deals(contact_id);
CREATE INDEX idx_crm_deals_pipeline ON crm_deals(pipeline_id);
CREATE INDEX idx_crm_deals_stage ON crm_deals(stage_id);
CREATE INDEX idx_crm_deals_assigned ON crm_deals(assigned_to);
CREATE INDEX idx_crm_deals_close_date ON crm_deals(close_date);
CREATE INDEX idx_crm_deals_created_at ON crm_deals(created_at DESC);
CREATE INDEX idx_crm_deals_deleted ON crm_deals(deleted_at) WHERE deleted_at IS NULL;
```

---

### ۲.۵ جدول محصولات معامله (crm_deal_products)

```sql
CREATE TABLE crm_deal_products (
    id BIGSERIAL PRIMARY KEY,
    deal_id BIGINT NOT NULL REFERENCES crm_deals(id) ON DELETE CASCADE,
    product_id BIGINT NULL, -- FK to products module (nullable for flexibility)
    product_name VARCHAR(255) NOT NULL, -- Snapshot of product name
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1 CHECK (quantity > 0),
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (unit_price >= 0),
    discount DECIMAL(15,2) DEFAULT 0 CHECK (discount >= 0),
    tax DECIMAL(15,2) DEFAULT 0 CHECK (tax >= 0),
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_crm_deal_products_deal ON crm_deal_products(deal_id);
CREATE INDEX idx_crm_deal_products_product ON crm_deal_products(product_id) WHERE product_id IS NOT NULL;

-- Trigger to calculate total automatically
CREATE OR REPLACE FUNCTION calculate_deal_product_total()
RETURNS TRIGGER AS $$
BEGIN
    NEW.total := (NEW.quantity * NEW.unit_price) - NEW.discount + NEW.tax;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_calculate_deal_product_total
    BEFORE INSERT OR UPDATE ON crm_deal_products
    FOR EACH ROW
    EXECUTE FUNCTION calculate_deal_product_total();
```

---

### ۲.۶ جدول فعالیت‌ها (crm_activities) - تکمیل شده

```sql
CREATE TABLE crm_activities (
    id BIGSERIAL PRIMARY KEY,
    type VARCHAR(20) NOT NULL CHECK (type IN ('call', 'meeting', 'email', 'task', 'note')),
    subject VARCHAR(255) NOT NULL,
    description TEXT NULL,
    related_model VARCHAR(255) NOT NULL, -- Polymorphic: App\Modules\CRM\Models\Lead
    related_id BIGINT NOT NULL,
    outcome VARCHAR(100) NULL,
    scheduled_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    due_date DATE NULL,
    priority VARCHAR(10) NULL CHECK (priority IN ('low', 'medium', 'high')),
    assigned_to BIGINT NULL REFERENCES users(id),
    created_by BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX idx_crm_activities_related ON crm_activities(related_model, related_id);
CREATE INDEX idx_crm_activities_type ON crm_activities(type);
CREATE INDEX idx_crm_activities_assigned ON crm_activities(assigned_to);
CREATE INDEX idx_crm_activities_scheduled ON crm_activities(scheduled_at);
CREATE INDEX idx_crm_activities_due_date ON crm_activities(due_date);
CREATE INDEX idx_crm_activities_deleted ON crm_activities(deleted_at) WHERE deleted_at IS NULL;

-- Composite index for common queries
CREATE INDEX idx_crm_activities_related_type ON crm_activities(related_model, related_id, type);
```

---

## ۳. جداول پشتیبانی (Support Tables)

### ۳.۱ جدول تنظیمات ماژول CRM (crm_module_settings)

```sql
CREATE TABLE crm_module_settings (
    key VARCHAR(100) PRIMARY KEY,
    value TEXT NULL,
    group VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**کلیدهای پیش‌فرض:**
- `quote_prefix` - پیشوند شماره پیش‌فاکتور (مثلاً: INV-)
- `quote_terms_conditions` - متن شرایط و ضوابط پیش‌فاکتور
- `default_pipeline_id` - شناسه پایپ‌لاین پیش‌فرض
- `lead_scoring_enabled` - فعال/غیرفعال بودن امتیازدهی خودکار
- `auto_assignment_enabled` - تخصیص خودکار لیدها

---

## ۴. Views و Materialized Views

### ۴.۱ View: Lead Statistics

```sql
CREATE VIEW vw_lead_statistics AS
SELECT 
    DATE(created_at) as date,
    status_id,
    source_id,
    assigned_to,
    COUNT(*) as total_leads,
    COUNT(CASE WHEN converted_at IS NOT NULL THEN 1 END) as converted_count,
    AVG(lead_score) as avg_lead_score
FROM crm_leads
WHERE deleted_at IS NULL
GROUP BY DATE(created_at), status_id, source_id, assigned_to;
```

### ۴.۲ View: Deal Pipeline Summary

```sql
CREATE VIEW vw_deal_pipeline_summary AS
SELECT 
    p.id as pipeline_id,
    p.name as pipeline_name,
    s.id as stage_id,
    s.name as stage_name,
    COUNT(d.id) as deal_count,
    SUM(d.amount) as total_amount,
    AVG(d.probability) as avg_probability
FROM crm_pipelines p
LEFT JOIN crm_stages s ON s.pipeline_id = p.id
LEFT JOIN crm_deals d ON d.stage_id = s.id AND d.deleted_at IS NULL
WHERE p.is_active = TRUE
GROUP BY p.id, p.name, s.id, s.name, s.order
ORDER BY p.id, s.order;
```

---

## ۵. Functions و Procedures

### ۵.۱ Function: Calculate Lead Score

```sql
CREATE OR REPLACE FUNCTION calculate_lead_score(lead_id BIGINT)
RETURNS INT AS $$
DECLARE
    score INT := 0;
BEGIN
    -- Base score from activities
    SELECT COALESCE(SUM(
        CASE 
            WHEN type = 'email' AND outcome = 'opened' THEN 5
            WHEN type = 'email' AND outcome = 'clicked' THEN 10
            WHEN type = 'call' AND outcome = 'success' THEN 15
            WHEN type = 'meeting' AND outcome = 'completed' THEN 20
            ELSE 0
        END
    ), 0) INTO score
    FROM crm_activities
    WHERE related_model = 'App\\Modules\\CRM\\Models\\Lead'
    AND related_id = lead_id;
    
    -- Deduct for incomplete data
    SELECT score - 
        CASE WHEN email IS NULL THEN 10 ELSE 0 END -
        CASE WHEN company IS NULL THEN 5 ELSE 0 END
    INTO score
    FROM crm_leads
    WHERE id = lead_id;
    
    RETURN GREATEST(0, score);
END;
$$ LANGUAGE plpgsql;
```

---

## ۶. Triggers

### ۶.۱ Trigger: Update Lead Score

```sql
CREATE OR REPLACE FUNCTION update_lead_score()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.related_model = 'App\\Modules\\CRM\\Models\\Lead' THEN
        UPDATE crm_leads
        SET lead_score = calculate_lead_score(NEW.related_id)
        WHERE id = NEW.related_id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_update_lead_score
    AFTER INSERT OR UPDATE ON crm_activities
    FOR EACH ROW
    WHEN (NEW.related_model = 'App\\Modules\\CRM\\Models\\Lead')
    EXECUTE FUNCTION update_lead_score();
```

### ۶.۲ Trigger: Update Deal Probability from Stage

```sql
CREATE OR REPLACE FUNCTION sync_deal_probability_from_stage()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.stage_id IS NOT NULL THEN
        UPDATE crm_deals
        SET probability = (SELECT probability FROM crm_stages WHERE id = NEW.stage_id)
        WHERE id = NEW.id AND probability IS NULL OR probability = OLD.probability;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sync_deal_probability
    AFTER INSERT OR UPDATE OF stage_id ON crm_deals
    FOR EACH ROW
    EXECUTE FUNCTION sync_deal_probability_from_stage();
```

---

## ۷. نکات مهم پیاده‌سازی

### ۷.۱ Soft Delete
- تمام جداول اصلی از soft delete استفاده می‌کنند (`deleted_at`)
- Indexes باید `WHERE deleted_at IS NULL` داشته باشند

### ۷.۲ Audit Trail
- تمام جداول اصلی `created_at`, `updated_at` دارند
- جداول مهم `created_by` دارند

### ۷.۳ Performance
- Indexes برای foreign keys
- Indexes برای frequently queried columns
- Composite indexes برای common query patterns

### ۷.۴ Data Integrity
- Check constraints برای data validation
- Foreign key constraints با appropriate CASCADE/SET NULL
- Unique constraints برای business rules

---

## ۸. Migration Order

1. `crm_sources`
2. `crm_statuses`
3. `crm_pipelines`
4. `crm_stages`
5. `crm_accounts`
6. `crm_leads`
7. `crm_contacts`
8. `crm_deals`
9. `crm_deal_products`
10. `crm_activities`
11. `crm_module_settings`
12. Views
13. Functions
14. Triggers
15. Seeders (default data)

