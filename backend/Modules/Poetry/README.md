# Poetry & Literature module (`poetry`)

Enabled for site type `blog`. Admin API requires Sanctum token + `marketing.blog.manage` (view: `marketing.blog.view`), module `marketing` licensed.

## Bulk import from Telegram/Eitaa (for SiteBuilder)
For each channel post:
1. `POST /api/v1/poetry/import/parse` `{ "text": "<raw post>" }` → `{ title, pen_name, composed_label, melody_credit, style_label, flags{is_new,not_performed,rewritten}, stanzas[{type,layout,repeat,lines[]}], warnings[] }`
   - blocks split on `...`/`…`; ═══ ✾ decorations, emoji, 🆔/@handle/t.me/eitaa lines removed; signature/date footer → `pen_name`/`composed_label`.
   - Check `warnings`; empty `title` → set one from the first line.
2. `POST /api/v1/poetry/poems` with the parsed body plus:
   `status` (`draft`|`published`, default draft), `occasion_id`, `meter_id`, `term_ids[]` (figure/style ids from `GET /api/v1/poetry/terms?kind=figure`),
   `refrain_after_each` (default true: first refrain is auto-repeated after each stanza on display), `media_ids[]` (MarketingMedia ids, audio/video),
   `external_links[{label,url}]` (http/https only, e.g. the original Telegram/Eitaa post URL), optional `slug` (auto from title, made unique), `excerpt`, `seo`.
   → 201 `{data:{id,slug,...}}`. 422 on validation errors. Throttle: 60 req/min, so pace bulk imports (~1 req/s).
3. Update: `PUT /api/v1/poetry/poems/{id}` (partial; `stanzas` replaces all). Delete: `DELETE`.

Stanza: `type` title|stanza|refrain, `layout` couplet (2 lines = 1 beyt) | single, `repeat` 1–10, `lines` string[].

## Public API (`/api/v1/public/poetry`, 120 req/min)
`GET /terms?kind=` · `GET /poems?q=&occasion=&figure=&style=&meter=&sort=popular&per_page=` (q searches title + every verse line, Arabic/Persian letter-normalised) ·
`GET /popular` · `GET /poems/{slug}` (full + `sequence` + `related`, increments view_count) · `GET /poems/{slug}/text` (plain-text download) · `GET /figures/{slug}`.

## Builder widgets
`poem-detail`, `poetry-figure`, `poem-search` (pages read `?poem=` / `?figure=` when slug prop is empty).

## Page widgets (alikhabari mockups)

`frontend/src/builder/registry-poetry-pages.ts` adds nine full-page widgets. Every colour, text, link and image is a builder field.

| type | page |
|---|---|
| `poetry-nav` | top bar: logo, links, search |
| `poetry-page-hero` | inner-page hero: breadcrumbs, title, photo/logo/arabesque |
| `poem-archive` | poem list with facet sidebar and counts (`/public/poetry/terms`) |
| `poem-single` | one poem: couplets, tools (copy, print, share, favourite, A+), media player, specs, related |
| `media-archive` | audio/video archive (repeater items) |
| `poetry-shop` | shop grid: `source=catalog|manual`, `checkoutMode=inquiry|cart|none` (default inquiry), `priceMode=auto|text` |
| `poetry-product` | product page with the same price/checkout options |
| `poet-profile` | about the poet: `photoMode` defaults to `logo`, so no portrait is shown |
| `poetry-site-footer` | footer: links, socials, copyright |

The shared colour fields are `accent` (default `#B4101E`), `ink`, `cream`, `gold`, `card` and `sectionBg`.

**No-photo mode.** Every photo slot has `photoMode = photo | logo | arabesque`. An empty logo field falls back to the site logo. `poetry-hero`, `book-banner` and `poet-about` also have this option; `poet-about` defaults to `logo`.

**Checkout off and demo products.** With `checkoutMode=inquiry` the buy button becomes a link to `inquiryHref` (Eitaa by default). With `priceMode=text`, prices show `priceText`. A product counts as demo if its slug starts with `demo-` or ends with `-demo`, or its name contains `(نمونه)`, `[demo]` or `«نمونه»`. Demo products get a «نمونه» badge, and cart mode never adds them. `shop-showcase` gained `priceLabel` for items without a price.

**New API filters.** `GET /public/poetry/poems` accepts `has_media=1` and `flag=is_new|not_performed|rewritten`. `GET /public/poetry/terms` now returns a `count` (published poems) for each term.
