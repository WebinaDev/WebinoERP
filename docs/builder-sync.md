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

Shared landing widgets (both platforms): `section-heading`, `steps`, `icon-cards`, `badges`, `cta-banner`, `comparison-table`. Shared form extras: group `heading` fields, review step, required confirms, two-column layout, intro/note/anchor, and `wb:form-prefill` / `?prefill_`.

## Sync gaps

- Dashboard scopes `builder_templates` and `builder_globals` with a `tenant_id` foreign key. ERP is the single company site: the column exists and is always `1`, with no `tenants` table.
- Builder pages live on `marketing_pages` (`title` in the builder API is `title_fa`). Dashboard uses `cms_pages`.
- Theme fallback: a missing published document falls back to the Webina corporate theme. Dashboard only falls back when the active theme is `ecommerce-ishop`.
- Product, cart, and shop widgets render the shared sample catalog. ERP marketing is not a storefront tenant.
- Sidebar entries still use the ERP remix icon set. Inside the builder, theme builder, and global settings, icons are the same Lucide glyphs as Dashboard.
- WordPress/Elementor import stays on Dashboard. ERP keeps `marketing:import-wordpress` for classic content, not Elementor documents.
- WebinoDocs (`WebinaDev/WebinoDocs`) was not readable here. Seeded pages, services, solutions, and menus follow `Website-SiteMap-Complete.md` and `frontend/src/themes/webina-corporate-v1/site-nav.ts` (the Docs xmind map already in this repo).
- Form field schemas are stored as JSON on `marketing_forms.fields`. The dashboard form screen edits title, slug, and publish; field arrays are accepted by `PUT /api/v1/marketing/forms/{id}`.

## Shared core: `@webina/ui/builder`

Path: `Plugins/Webina/packages/webina-ui/src/builder/` (built to `dist/builder`, subpath export `@webina/ui/builder`). Both frontends depend on `@webina/ui` via `file:../../packages/webina-ui`.

Public API:
- Types: `SharedWidgetDef`, `SharedFieldDef`, `SharedRepeaterSubField`, `SharedOption`, `SharedWidgetNode`, `SharedWidgetCategory`, `SHARED_WIDGET_IDS`.
- Forms: `FORM_FIELD_TYPES`, `parseFormFields`, `isVisible`, `stepCount`, `missingOnStep`, `visiblePayload`, `fieldsFromDefinition`, `FormField`, `FormFieldType`.
- Server surface: `publicFormEndpoints(apiBase, prefix = "/v1/public/forms")` → `{ definition, submit, upload }` URLs. GET definition returns `{ data: { fields } }` for `fieldsFromDefinition`.
- Upload rule: `FORM_UPLOAD_MAX_BYTES` (10 MB), `FORM_UPLOAD_EXTENSIONS` / `FORM_UPLOAD_ACCEPT` (jpg, jpeg, png, webp, pdf, doc, docx, zip), `checkFormUpload`.
- Lang: `RTL_LANGS`, `dirForLang`, `pageSlugFromPath`, `uiLocaleForLang`, `previewTokenFromPath`.
- Ids: `uid`, `safeClass`.
- Widgets (editor schema + defaults): `sharedContentWidgets(iconOptions?)` → section-heading, steps, icon-cards, badges, cta-banner, comparison-table; `sharedFormWidget({ formKeyLabel, fieldsLabel })` → form with intro/note/anchor/columns/stepTitles/review/confirms; `DEFAULT_ICON_OPTIONS`.
- Registry: `createWidgetRegistry(core, onConflict)` + `WidgetPlugin` for theme/platform packs.

Renderers stay per platform but must honour the same props.

ERP-only (not in the package): corporate «وبینا» widgets (`themes/webina-corporate-v1/builder/blocks`), `rahn-estimate`, Marketing wiring (form slug = Marketing form, `/v1/public/forms` routes, `canonicalSiteSegments`).
Dashboard-only: Iranian commerce widgets (taste-box, amazing-offers, installment-info, …), tenant scoping.

Next step for Dashboard: replace its local `builder/forms.ts`, `lang.ts`, `ids.ts` and the six landing defs + form def with re-exports from `@webina/ui/builder`; raise its upload limit to `FORM_UPLOAD_MAX_BYTES` + zip; pass its own prefix to `publicFormEndpoints`; register commerce widgets as a plugin.

## Shared package `@webina/ui`

The shared builder core lives in its own public repo `WebinaDev/webina-ui` (exports `@webina/ui`, `@webina/ui/builder`). ERP installs a pinned tag:

```json
"@webina/ui": "git+https://github.com/WebinaDev/webina-ui.git#v0.1.0"
```

`dist/` is committed in that repo, so no build step is needed. To upgrade: tag a new version there, bump the `#vX.Y.Z` here, run `npm install`. The Docker image installs it via git (no sibling `packages/webina-ui` copy).
