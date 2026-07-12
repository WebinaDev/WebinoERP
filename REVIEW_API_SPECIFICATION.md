# API Specification Addendum - CRM Module

**تاریخ:** ژانویه 2026  
**نسخه:** 1.0.0

این سند شامل تعریف کامل API endpoints، request/response schemas و جزئیات فنی است که در مستندات اصلی ناقص بودند.

---

## ۱. API Standards

### ۱.۱ Base URL

```
Production: https://api.example.com/api/v1
Development: http://localhost/api/v1
```

### ۱.۲ Authentication

تمام endpoints (به جز public endpoints) نیاز به authentication دارند:

**Header:**
```
Authorization: Bearer {token}
```

برای Web (Stateful):
```
Cookie: laravel_session={session_id}
X-XSRF-TOKEN: {csrf_token}
```

### ۱.۳ Response Format

**Success Response:**
```json
{
  "data": {...},
  "message": "Success message (optional)"
}
```

**Error Response:**
```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable error message",
    "errors": {
      "field_name": ["Validation error message"]
    }
  }
}
```

**HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `204` - No Content (for DELETE)
- `400` - Bad Request (Validation errors)
- `401` - Unauthorized
- `403` - Forbidden (Permission denied or Module not active)
- `404` - Not Found
- `422` - Unprocessable Entity (Business logic errors)
- `500` - Internal Server Error

### ۱.۴ Pagination

**Query Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15, max: 100)

**Response:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7,
    "from": 1,
    "to": 15
  },
  "links": {
    "first": "/api/v1/crm/leads?page=1",
    "last": "/api/v1/crm/leads?page=7",
    "prev": null,
    "next": "/api/v1/crm/leads?page=2"
  }
}
```

### ۱.۵ Filtering & Sorting

**Query Parameters:**
- `filter[field_name]=value` - Filter by field
- `filter[field_name][operator]=value` - Filter with operator (eq, ne, gt, gte, lt, lte, like, in)
- `sort=field_name` - Sort ascending
- `sort=-field_name` - Sort descending
- `search=keyword` - Full-text search

**Examples:**
```
GET /api/v1/crm/leads?filter[status_id]=1&filter[assigned_to][eq]=5
GET /api/v1/crm/leads?sort=-created_at&search=john
GET /api/v1/crm/deals?filter[amount][gte]=10000&sort=close_date
```

---

## ۲. Leads Endpoints

### ۲.۱ List Leads

**GET** `/api/v1/crm/leads`

**Query Parameters:**
- Standard pagination, filtering, sorting, search

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "topic": "علاقه به خرید سرور",
      "first_name": "علی",
      "last_name": "احمدی",
      "company": "شرکت فناوری",
      "email": "ali@example.com",
      "mobile": "+989123456789",
      "status": {
        "id": 1,
        "name": "جدید",
        "color": "#3b82f6"
      },
      "source": {
        "id": 1,
        "name": "وب‌سایت"
      },
      "lead_score": 45,
      "rating": 4,
      "assigned_to": {
        "id": 5,
        "name": "محمد رضایی"
      },
      "created_at": "2026-01-15T10:30:00Z"
    }
  ],
  "meta": {...},
  "links": {...}
}
```

### ۲.۲ Get Lead

**GET** `/api/v1/crm/leads/{id}`

**Response:**
```json
{
  "data": {
    "id": 1,
    "topic": "علاقه به خرید سرور",
    "first_name": "علی",
    "last_name": "احمدی",
    "company": "شرکت فناوری",
    "job_title": "مدیر IT",
    "email": "ali@example.com",
    "mobile": "+989123456789",
    "phone": "+982188888888",
    "source_id": 1,
    "source": {...},
    "status_id": 1,
    "status": {...},
    "industry": "IT",
    "rating": 4,
    "lead_score": 45,
    "assigned_to": 5,
    "assigned_user": {...},
    "description": "توضیحات تکمیلی",
    "address_json": {
      "street": "خیابان ولیعصر",
      "city": "تهران",
      "province": "تهران",
      "postal_code": "1234567890"
    },
    "converted_at": null,
    "converted_to_account_id": null,
    "created_by": 1,
    "created_at": "2026-01-15T10:30:00Z",
    "updated_at": "2026-01-15T10:30:00Z"
  }
}
```

### ۲.۳ Create Lead

**POST** `/api/v1/crm/leads`

**Request Body:**
```json
{
  "topic": "علاقه به خرید سرور",
  "first_name": "علی",
  "last_name": "احمدی",
  "company": "شرکت فناوری",
  "job_title": "مدیر IT",
  "email": "ali@example.com",
  "mobile": "+989123456789",
  "phone": "+982188888888",
  "source_id": 1,
  "status_id": 1,
  "industry": "IT",
  "rating": 4,
  "assigned_to": 5,
  "description": "توضیحات تکمیلی",
  "address_json": {
    "street": "خیابان ولیعصر",
    "city": "تهران",
    "province": "تهران",
    "postal_code": "1234567890"
  }
}
```

**Validation Rules:**
- `topic`: required, string, max:255
- `first_name`: required, string, max:100
- `last_name`: required, string, max:100
- `email`: nullable, email, unique (for active leads)
- `mobile`: required, string, regex:/^\+[1-9]\d{1,14}$/
- `source_id`: nullable, exists:crm_sources,id
- `status_id`: required, exists:crm_statuses,id

**Response:** `201 Created` with created lead data

**Special Logic:**
- اگر email یا mobile تکراری باشد، response شامل warning:
```json
{
  "data": {...},
  "warnings": [
    {
      "type": "possible_duplicate",
      "message": "Lead with similar email/mobile exists",
      "duplicate_lead_id": 10,
      "duplicate_lead_url": "/api/v1/crm/leads/10"
    }
  ]
}
```

### ۲.۴ Update Lead

**PUT** `/api/v1/crm/leads/{id}`

**Request Body:** Same as Create (all fields optional)

**Response:** `200 OK` with updated lead data

### ۲.۵ Delete Lead

**DELETE** `/api/v1/crm/leads/{id}`

**Response:** `204 No Content`

**Note:** Soft delete - sets `deleted_at` timestamp

### ۲.۶ Convert Lead

**POST** `/api/v1/crm/leads/{id}/convert`

**Request Body:**
```json
{
  "create_deal": true,
  "deal_name": "معامله سرور - شرکت فناوری",
  "account_name": "شرکت فناوری",
  "pipeline_id": 1,
  "stage_id": 1,
  "deal_amount": 50000000,
  "contact_first_name": "علی",
  "contact_last_name": "احمدی",
  "contact_email": "ali@example.com",
  "contact_mobile": "+989123456789"
}
```

**Response:**
```json
{
  "data": {
    "lead": {...},
    "account": {
      "id": 5,
      "name": "شرکت فناوری"
    },
    "contact": {
      "id": 8,
      "name": "علی احمدی"
    },
    "deal": {
      "id": 12,
      "name": "معامله سرور - شرکت فناوری"
    }
  },
  "message": "Lead converted successfully"
}
```

**Business Logic:**
1. Check if lead is already converted (return error if `converted_at` is not null)
2. Create or find existing account by name
3. Create contact linked to account
4. Optionally create deal
5. Update lead: set `converted_at`, `converted_to_account_id`
6. Archive lead (don't delete)

---

## ۳. Accounts Endpoints

### ۳.۱ List Accounts

**GET** `/api/v1/crm/accounts`

**Query Parameters:**
- Standard pagination, filtering, sorting, search
- `filter[type]=Customer` - Filter by account type
- `filter[owner_id]=5` - Filter by account owner

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "شرکت فناوری اطلاعات",
      "account_code": "ACC-001",
      "type": "Customer",
      "industry": "IT",
      "owner": {
        "id": 5,
        "name": "محمد رضایی"
      },
      "contacts_count": 3,
      "deals_count": 5,
      "total_deal_value": 150000000,
      "created_at": "2026-01-10T08:00:00Z"
    }
  ],
  "meta": {...},
  "links": {...}
}
```

### ۳.۲ Get Account

**GET** `/api/v1/crm/accounts/{id}`

**Response:**
```json
{
  "data": {
    "id": 1,
    "name": "شرکت فناوری اطلاعات",
    "account_code": "ACC-001",
    "website": "https://example.com",
    "parent_id": null,
    "parent": null,
    "type": "Customer",
    "tax_id": "1234567890",
    "industry": "IT",
    "employees_count": 50,
    "annual_revenue": 5000000000,
    "billing_address": {...},
    "shipping_address": {...},
    "owner_id": 5,
    "owner": {...},
    "contacts": [
      {
        "id": 1,
        "name": "علی احمدی",
        "is_primary": true
      }
    ],
    "deals": [
      {
        "id": 1,
        "name": "معامله سرور",
        "amount": 50000000,
        "stage": {...}
      }
    ],
    "created_at": "2026-01-10T08:00:00Z",
    "updated_at": "2026-01-15T12:00:00Z"
  }
}
```

### ۳.۳ Create Account

**POST** `/api/v1/crm/accounts`

**Request Body:**
```json
{
  "name": "شرکت فناوری اطلاعات",
  "account_code": "ACC-001",
  "website": "https://example.com",
  "parent_id": null,
  "type": "Customer",
  "tax_id": "1234567890",
  "industry": "IT",
  "employees_count": 50,
  "annual_revenue": 5000000000,
  "billing_address": {
    "street": "خیابان ولیعصر",
    "city": "تهران",
    "province": "تهران",
    "postal_code": "1234567890",
    "country": "ایران"
  },
  "shipping_address": {...},
  "owner_id": 5,
  "description": "توضیحات"
}
```

**Validation Rules:**
- `name`: required, string, max:200
- `account_code`: nullable, string, max:50, unique
- `type`: required, in:Customer,Partner,Competitor,Vendor
- `website`: nullable, url
- `owner_id`: nullable, exists:users,id

**Response:** `201 Created`

### ۳.۴ Update Account

**PUT** `/api/v1/crm/accounts/{id}`

**Request Body:** Same as Create (all fields optional)

**Response:** `200 OK`

### ۳.۵ Delete Account

**DELETE** `/api/v1/crm/accounts/{id}`

**Response:** `204 No Content`

**Note:** 
- Soft delete
- Cascade delete contacts (hard delete)
- Cannot delete if has active deals

---

## ۴. Contacts Endpoints

### ۴.۱ List Contacts

**GET** `/api/v1/crm/contacts`

**Query Parameters:**
- Standard pagination, filtering, sorting, search
- `filter[account_id]=5` - Filter by account
- `filter[is_primary]=true` - Filter primary contacts

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "first_name": "علی",
      "last_name": "احمدی",
      "email": "ali@example.com",
      "mobile": "+989123456789",
      "job_title": "مدیر IT",
      "account": {
        "id": 1,
        "name": "شرکت فناوری اطلاعات"
      },
      "is_primary": true,
      "decision_role": "Decision Maker",
      "created_at": "2026-01-10T08:00:00Z"
    }
  ],
  "meta": {...},
  "links": {...}
}
```

### ۴.۲ Get Contact

**GET** `/api/v1/crm/contacts/{id}`

**Response:** Full contact data with account and related deals

### ۴.۳ Create Contact

**POST** `/api/v1/crm/contacts`

**Request Body:**
```json
{
  "account_id": 1,
  "first_name": "علی",
  "last_name": "احمدی",
  "email": "ali@example.com",
  "mobile": "+989123456789",
  "phone": "+982188888888",
  "job_title": "مدیر IT",
  "reports_to": null,
  "decision_role": "Decision Maker",
  "is_primary": false,
  "social_profiles": {
    "linkedin": "https://linkedin.com/in/username"
  },
  "assigned_to": 5,
  "description": "توضیحات"
}
```

**Validation Rules:**
- `account_id`: required, exists:crm_accounts,id
- `first_name`: required, string, max:100
- `last_name`: required, string, max:100
- `email`: nullable, email
- `decision_role`: nullable, in:Decision Maker,Influencer,End User,Gatekeeper

**Response:** `201 Created`

**Special Logic:**
- اگر `is_primary=true` باشد، سایر primary contacts همان account به `false` تغییر می‌یابند

### ۴.۴ Update Contact

**PUT** `/api/v1/crm/contacts/{id}`

**Request Body:** Same as Create (all fields optional)

**Response:** `200 OK`

### ۴.۵ Delete Contact

**DELETE** `/api/v1/crm/contacts/{id}`

**Response:** `204 No Content`

**Note:** Hard delete (CASCADE from account)

---

## ۵. Deals Endpoints

### ۵.۱ List Deals

**GET** `/api/v1/crm/deals`

**Query Parameters:**
- Standard pagination, filtering, sorting, search
- `filter[pipeline_id]=1` - Filter by pipeline
- `filter[stage_id]=5` - Filter by stage
- `filter[assigned_to]=5` - Filter by assigned user
- `filter[close_date][gte]=2026-01-01` - Filter by close date

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "معامله سرور - شرکت فناوری",
      "account": {
        "id": 1,
        "name": "شرکت فناوری اطلاعات"
      },
      "contact": {
        "id": 1,
        "name": "علی احمدی"
      },
      "pipeline": {
        "id": 1,
        "name": "پایپ‌لاین پیش‌فرض"
      },
      "stage": {
        "id": 3,
        "name": "پروپوزال",
        "color": "#fbbf24"
      },
      "amount": 50000000,
      "probability": 50,
      "close_date": "2026-02-15",
      "type": "New Business",
      "assigned_to": {
        "id": 5,
        "name": "محمد رضایی"
      },
      "created_at": "2026-01-10T08:00:00Z"
    }
  ],
  "meta": {...},
  "links": {...}
}
```

### ۵.۲ Get Deal

**GET** `/api/v1/crm/deals/{id}`

**Response:** Full deal data with account, contact, pipeline, stage, products, activities

### ۵.۳ Create Deal

**POST** `/api/v1/crm/deals`

**Request Body:**
```json
{
  "name": "معامله سرور - شرکت فناوری",
  "account_id": 1,
  "contact_id": 1,
  "pipeline_id": 1,
  "stage_id": 1,
  "amount": 50000000,
  "probability": 25,
  "close_date": "2026-02-15",
  "type": "New Business",
  "campaign_source": "وب‌سایت",
  "assigned_to": 5,
  "description": "توضیحات"
}
```

**Validation Rules:**
- `name`: required, string, max:255
- `account_id`: required, exists:crm_accounts,id
- `pipeline_id`: required, exists:crm_pipelines,id
- `stage_id`: required, exists:crm_stages,id, must belong to pipeline_id
- `amount`: required, numeric, min:0
- `probability`: nullable, integer, min:0, max:100
- `close_date`: nullable, date
- `type`: required, in:New Business,Existing Business

**Response:** `201 Created`

### ۵.۴ Update Deal

**PUT** `/api/v1/crm/deals/{id}`

**Request Body:** Same as Create (all fields optional)

**Response:** `200 OK`

**Special Logic:**
- اگر `stage_id` تغییر کند و stage جدید `is_closed=true` باشد:
  - اگر `is_won=true`: set `won_at`
  - اگر `is_won=false`: require `loss_reason`, set `lost_at`

### ۵.۵ Delete Deal

**DELETE** `/api/v1/crm/deals/{id}`

**Response:** `204 No Content`

**Note:** Soft delete

### ۵.۶ Move Deal (Change Stage)

**PATCH** `/api/v1/crm/deals/{id}/move`

**Request Body:**
```json
{
  "new_stage_id": 5,
  "position": 2
}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "stage_id": 5,
    "stage": {...},
    "probability": 75
  },
  "message": "Deal moved successfully"
}
```

**Business Logic:**
1. Validate new_stage_id belongs to same pipeline
2. Update stage_id
3. Auto-update probability from stage (if not manually set)
4. Trigger automation rules (e.g., require products for "Proposal" stage)
5. Create activity log

### ۵.۷ Get Kanban Data

**GET** `/api/v1/crm/pipelines/{id}/kanban`

**Response:**
```json
{
  "data": {
    "pipeline": {
      "id": 1,
      "name": "پایپ‌لاین پیش‌فرض"
    },
    "stages": [
      {
        "id": 1,
        "name": "Lead",
        "order": 1,
        "color": "#94a3b8",
        "deals": [
          {
            "id": 1,
            "name": "معامله سرور",
            "amount": 50000000,
            "account": {...},
            "contact": {...},
            "close_date": "2026-02-15",
            "is_overdue": false
          }
        ],
        "total_amount": 50000000,
        "deal_count": 1
      }
    ]
  }
}
```

---

## ۶. Activities Endpoints

### ۶.۱ List Activities

**GET** `/api/v1/crm/activities`

**Query Parameters:**
- Standard pagination, filtering, sorting
- `filter[related_model]=App\\Modules\\CRM\\Models\\Deal` - Filter by related model
- `filter[related_id]=10` - Filter by related ID
- `filter[type]=call` - Filter by activity type
- `filter[assigned_to]=5` - Filter by assigned user
- `filter[due_date][gte]=2026-01-15` - Filter by due date

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "type": "call",
      "subject": "تماس با مشتری",
      "description": "توضیحات تماس",
      "related_model": "App\\Modules\\CRM\\Models\\Deal",
      "related_id": 10,
      "related": {
        "id": 10,
        "name": "معامله سرور"
      },
      "outcome": "Success",
      "scheduled_at": "2026-01-15T14:00:00Z",
      "completed_at": "2026-01-15T14:30:00Z",
      "assigned_to": {
        "id": 5,
        "name": "محمد رضایی"
      },
      "created_by": {
        "id": 1,
        "name": "مدیر سیستم"
      },
      "created_at": "2026-01-15T10:00:00Z"
    }
  ],
  "meta": {...},
  "links": {...}
}
```

### ۶.۲ Get Activity

**GET** `/api/v1/crm/activities/{id}`

**Response:** Full activity data

### ۶.۳ Create Activity

**POST** `/api/v1/crm/activities`

**Request Body:**
```json
{
  "type": "call",
  "subject": "تماس با مشتری",
  "description": "توضیحات تماس",
  "related_model": "App\\Modules\\CRM\\Models\\Deal",
  "related_id": 10,
  "outcome": "Success",
  "scheduled_at": "2026-01-15T14:00:00Z",
  "due_date": "2026-01-15",
  "priority": "high",
  "assigned_to": 5
}
```

**Validation Rules:**
- `type`: required, in:call,meeting,email,task,note
- `subject`: required, string, max:255
- `related_model`: required, string
- `related_id`: required, integer
- `outcome`: nullable, string, max:100
- `priority`: nullable, in:low,medium,high

**Response:** `201 Created`

**Business Logic:**
- اگر `type=task` و `due_date` مشخص باشد، notification ایجاد می‌شود
- اگر `type=call` یا `type=meeting` و `completed_at` set شود، lead_score به‌روزرسانی می‌شود

### ۶.۴ Update Activity

**PUT** `/api/v1/crm/activities/{id}`

**Request Body:** Same as Create (all fields optional)

**Response:** `200 OK`

### ۶.۵ Delete Activity

**DELETE** `/api/v1/crm/activities/{id}`

**Response:** `204 No Content`

**Note:** Soft delete

---

## ۷. Pipelines & Stages Endpoints

### ۷.۱ List Pipelines

**GET** `/api/v1/crm/pipelines`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "پایپ‌لاین پیش‌فرض",
      "description": "توضیحات",
      "is_default": true,
      "is_active": true,
      "stages_count": 6,
      "deals_count": 10
    }
  ]
}
```

### ۷.۲ Get Pipeline

**GET** `/api/v1/crm/pipelines/{id}`

**Response:** Pipeline with all stages

### ۷.۳ Create Pipeline

**POST** `/api/v1/crm/pipelines`

**Request Body:**
```json
{
  "name": "پایپ‌لاین جدید",
  "description": "توضیحات",
  "is_default": false,
  "stages": [
    {
      "name": "مرحله ۱",
      "order": 1,
      "probability": 10,
      "color": "#3b82f6"
    }
  ]
}
```

**Response:** `201 Created`

### ۷.۴ Update Pipeline

**PUT** `/api/v1/crm/pipelines/{id}`

**Request Body:** Same as Create

**Response:** `200 OK`

### ۷.۵ Delete Pipeline

**DELETE** `/api/v1/crm/pipelines/{id}`

**Response:** `204 No Content`

**Note:** Cannot delete if has deals or is default pipeline

### ۷.۶ List Stages

**GET** `/api/v1/crm/pipelines/{id}/stages`

**Response:** List of stages for pipeline

### ۷.۷ Create Stage

**POST** `/api/v1/crm/stages`

**Request Body:**
```json
{
  "pipeline_id": 1,
  "name": "مرحله جدید",
  "description": "توضیحات",
  "order": 3,
  "probability": 50,
  "color": "#fbbf24",
  "is_closed": false,
  "is_won": false
}
```

**Response:** `201 Created`

### ۷.۸ Update Stage

**PUT** `/api/v1/crm/stages/{id}`

**Request Body:** Same as Create

**Response:** `200 OK`

**Note:** اگر `order` تغییر کند، سایر stages باید reorder شوند

### ۷.۹ Delete Stage

**DELETE** `/api/v1/crm/stages/{id}`

**Response:** `204 No Content`

**Note:** Cannot delete if has deals

---

## ۸. Sources & Statuses Endpoints

### ۸.۱ List Sources

**GET** `/api/v1/crm/sources`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "وب‌سایت",
      "description": "منبع وب‌سایت",
      "color": "#3b82f6",
      "is_active": true
    }
  ]
}
```

### ۸.۲ List Statuses

**GET** `/api/v1/crm/statuses`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "جدید",
      "description": "لید جدید",
      "color": "#3b82f6",
      "order": 1,
      "is_active": true
    }
  ]
}
```

**Note:** این endpoints معمولاً read-only هستند و از admin panel مدیریت می‌شوند.

---

## ۹. Export Endpoints

### ۹.۱ Export Leads

**GET** `/api/v1/crm/leads/export`

**Query Parameters:**
- Same filters as List Leads
- `format=xlsx|csv` - Export format (default: xlsx)

**Response:**
- `200 OK` with file download
- Content-Type: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

**Permission Required:** `crm.export`

---

## ۱۰. Search Endpoints

### ۱۰.۱ Global Search

**GET** `/api/v1/crm/search`

**Query Parameters:**
- `q=keyword` - Search keyword
- `types[]=leads&types[]=accounts` - Entity types to search

**Response:**
```json
{
  "data": {
    "leads": [...],
    "accounts": [...],
    "contacts": [...],
    "deals": [...]
  },
  "meta": {
    "total_results": 25
  }
}
```

---

## ۱۱. Rate Limiting

- **Default:** 60 requests per minute per user
- **Export endpoints:** 10 requests per hour
- **Search endpoints:** 30 requests per minute

**Headers:**
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1642248000
```

---

## ۱۲. Webhooks (Future)

برای اطلاع‌رسانی رویدادها به سیستم‌های خارجی:

**Events:**
- `lead.created`
- `lead.converted`
- `deal.stage_changed`
- `deal.won`
- `deal.lost`

**Endpoint:** (To be defined in future)

---

## ۱۳. Error Codes

| Code | Description |
|------|-------------|
| `VALIDATION_ERROR` | Request validation failed |
| `NOT_FOUND` | Resource not found |
| `UNAUTHORIZED` | Authentication required |
| `FORBIDDEN` | Permission denied or Module not active |
| `DUPLICATE_LEAD` | Lead with same email/mobile exists |
| `INVALID_STAGE` | Stage doesn't belong to pipeline |
| `DEAL_HAS_PRODUCTS` | Cannot change stage without products |
| `CANNOT_DELETE` | Resource cannot be deleted (has dependencies) |
| `MODULE_NOT_ACTIVE` | CRM module is not active |

