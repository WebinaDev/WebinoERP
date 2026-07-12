# API پروژه (PM)

**پایه:** `/api/v1/projects`  
**احراز هویت:** `auth:sanctum` + `module:projects`  
**برچسب Explorer:** [PROJECTS](/api/explorer/#tag/PROJECTS)

## خلاصه

پروژه، وظیفه، قرارداد، تیکت، اسپرینت، کانبان، ثبت زمان.

## endpointهای کلیدی

| متد | مسیر | توضیح |
|-----|------|-------|
| GET/POST | `/projects` | پروژه‌ها |
| GET/POST | `/tasks` | وظایف |
| GET/POST | `/contracts` | قراردادها |
| GET/POST | `/time-entries` | ثبت زمان |
| GET | `/kanban/data` | کانبان |

فرم عمومی: `POST /api/v1/forms/{slug}/submit`
