/**
 * Normalize Laravel paginated or array API payloads to a row array.
 */
export function normalizeListPayload(raw: unknown): Record<string, unknown>[] {
  if (Array.isArray(raw)) {
    return raw as Record<string, unknown>[];
  }
  if (raw && typeof raw === 'object' && 'data' in raw) {
    const inner = (raw as { data: unknown }).data;
    if (Array.isArray(inner)) {
      return inner as Record<string, unknown>[];
    }
  }
  return [];
}

/** Read pagination meta from a list payload (after api-client interceptor). */
export function readListMeta(raw: unknown): Record<string, unknown> {
  if (raw && typeof raw === 'object' && 'meta' in raw) {
    const meta = (raw as { meta?: unknown }).meta;
    if (meta && typeof meta === 'object') {
      return meta as Record<string, unknown>;
    }
  }
  // Laravel-flat paginator shape after unwrap (no top-level meta)
  if (raw && typeof raw === 'object') {
    const o = raw as Record<string, unknown>;
    if ('current_page' in o || 'last_page' in o || 'total' in o) {
      return {
        current_page: o.current_page,
        last_page: o.last_page,
        per_page: o.per_page,
        total: o.total,
        from: o.from,
        to: o.to,
      };
    }
  }
  return {};
}

/**
 * After api-client interceptor: entity may be T or still nested `{ data: T }`.
 */
export function readEntity<T = unknown>(raw: unknown): T | null {
  if (raw == null) return null;
  if (typeof raw !== 'object') return raw as T;
  const o = raw as Record<string, unknown>;
  if ('data' in o && o.data != null && typeof o.data === 'object' && !Array.isArray(o.data)) {
    const inner = o.data as Record<string, unknown>;
    // Prefer nested entity when it looks richer than a bare envelope wrapper
    if ('id' in inner || 'task' in inner || 'columns' in inner || 'preferences' in inner) {
      return inner as T;
    }
    // Nested `{ data: Entity }` where Entity is the domain object
    if (!('meta' in o) && !('success' in o)) {
      return (o.data as T) ?? (raw as T);
    }
  }
  return raw as T;
}

/** List rows after interceptor (array or `{ data: [] }` or `{ data, meta }`). */
export function readList<T = Record<string, unknown>>(raw: unknown): T[] {
  return normalizeListPayload(raw) as T[];
}

/** Page payload: rows + meta after interceptor. */
export function readPage<T = Record<string, unknown>>(raw: unknown): {
  rows: T[];
  meta: Record<string, unknown>;
} {
  return {
    rows: readList<T>(raw),
    meta: readListMeta(raw),
  };
}
