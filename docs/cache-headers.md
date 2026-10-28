# Edge cache

## Purging the edge cache on revalidation

`POST /webino-revalidate` (called by the backend after builder/catalog/SEO saves) clears Next's data cache
with `revalidateTag`/`revalidatePath`, but that never reaches the edge nginx `proxy_cache pages`, so edited
products/categories could stay stale for up to the edge TTL. The route now also calls
`frontend/src/lib/edge-purge.ts`, which is a no-op unless configured:

| Env (frontend) | Default | Meaning |
|---|---|---|
| `EDGE_PURGE_URL` | unset (disabled) | Purge endpoint base, e.g. `http://edge:8080/purge`; the page path is appended |
| `EDGE_PURGE_METHOD` | `PURGE` | Method (ngx_cache_purge style); use `GET`/`POST` for a custom endpoint |
| `EDGE_PURGE_SECRET` | unset | Sent as `x-purge-secret`; the edge should reject requests without it |
| `EDGE_PURGE_ALL` | `/*` | Purged when only tags (no `paths`) were revalidated |

Optional body field `host` is forwarded as `x-purge-host` so the edge can build the per-tenant cache key.
Paths are sent percent-encoded (nginx keys on the raw URI), so Persian slugs purge correctly.
Example edge location (ngx_cache_purge):

```nginx
location ~ ^/purge(/.*) {
    allow 172.16.0.0/12; deny all;
    if ($http_x_purge_secret != "CHANGE_ME") { return 403; }
    proxy_cache_purge pages "$scheme$http_x_purge_host$1$is_args$args";
}
```

Without the purge module, an alternative is lowering the edge TTL or bumping a cache-key version;
until `EDGE_PURGE_URL` is set, edge pages expire on their normal 30s TTL.
