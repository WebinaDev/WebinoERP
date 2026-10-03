# ERP marketing builder sync with WebinoDashboard

Source of truth for the visual builder: `WebinaDev/WebinoDashboard` branch `cursor/theme-builder-724d` (commit on that branch at the time of this port).

ERP exposes the same HTTP paths and JSON shapes:

| Surface | Path |
| --- | --- |
| Page list / chrome | `GET/POST /api/v1/builder`, `/api/v1/builder/pages`, `/api/v1/builder/templates/{header\|footer}` |
| Global settings | `GET/PUT /api/v1/builder/globals`, `POST /api/v1/builder/globals/publish` |
| Theme builder | `GET /api/v1/theme-builder`, templates CRUD, `POST /api/v1/theme-builder/library/apply` |
| Storefront resolve | `GET /api/v1/public/builder/resolve`, `/pages/{slug}`, `/templates/{kind}`, `/globals` |
| Dashboard UI | `/dashboard/builder`, `/dashboard/builder/settings`, `/dashboard/theme-builder`, `/dashboard/theme-builder/{kind}/{id}`, `/dashboard/builder/chrome/{header\|footer}` |

Document schema is unchanged: `{ version, sections: [{ id, columns: [{ id, span, widgets: [{ id, type, props }] }] }] }`. Draft stays off the public site until publish. Resolution order matches Dashboard: published template whose include/exclude conditions match (highest priority, then newer id) → default published template of that kind → theme fallback.

Template kinds: `header`, `footer`, `single_post`, `single_page`, `single_product`, `archive`, `search`, `product_archive`, `loop_item`, `not_found`. The 404 screen is kind `not_found` (label «صفحه ۴۰۴»). Public resolve also accepts `kind=404` as an alias of `not_found`.

Library presets include `ishop-kit`, `beauty-kit`, and an ERP-only `webina-kit`.

Widgets read tokens such as `var(--wb-color-primary)` from published global settings.

## Where operators edit

| Content | Dashboard |
| --- | --- |
| Portfolio, categories, case studies | `/dashboard/marketing/portfolio` |
| Pages (also open in the page builder by id) | `/dashboard/marketing/pages` and `/dashboard/builder` |
| Services and solutions | `/dashboard/marketing/services`, `/dashboard/marketing/solutions` |
| Menus | `/dashboard/marketing/menus` |
| Forms | `/dashboard/marketing/forms` |
| Header, footer, 404, archives, loop | `/dashboard/theme-builder` |
| Colors, type, buttons, images, forms, layout, CSS variables | `/dashboard/builder/settings` |

Publishing a page whose slug is `home` replaces the designed homepage. Until then the corporate landing stays. Publishing a header or footer template replaces the designed chrome for matching URLs. The request path is sent as `x-webino-path`.

## Sync gaps

- Dashboard scopes `builder_templates` and `builder_globals` with a `tenant_id` foreign key. ERP is the single company site: the column exists and is always `1`, with no `tenants` table.
- Builder pages live on `marketing_pages` (`title` in the builder API is `title_fa`). Dashboard uses `cms_pages`.
- Theme fallback: a missing published document falls back to the Webina corporate theme. Dashboard only falls back when the active theme is `ecommerce-ishop`.
- Product, cart, and shop widgets render the shared sample catalog. ERP marketing is not a storefront tenant.
- Sidebar entries still use the ERP remix icon set. Inside the builder, theme builder, and global settings, icons are the same Lucide glyphs as Dashboard.
- WordPress/Elementor import stays on Dashboard. ERP keeps `marketing:import-wordpress` for classic content, not Elementor documents.
- WebinoDocs (`WebinaDev/WebinoDocs`) was not readable here. Seeded pages, services, solutions, and menus follow `Website-SiteMap-Complete.md` and `frontend/src/themes/webina-corporate-v1/site-nav.ts` (the Docs xmind map already in this repo).
- Form field schemas are stored as JSON on `marketing_forms.fields`. The dashboard form screen edits title, slug, and publish; field arrays are accepted by `PUT /api/v1/marketing/forms/{id}`.
