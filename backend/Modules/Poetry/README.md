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
