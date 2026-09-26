/** Unwrap list payloads after api-client envelope handling. */
export function unwrapRahnList<T>(body: unknown, key: string): T[] {
  if (Array.isArray(body)) return body as T[];
  if (!body || typeof body !== 'object') return [];
  const root = body as Record<string, unknown>;
  if (Array.isArray(root[key])) return root[key] as T[];
  const data = root.data;
  if (Array.isArray(data)) return data as T[];
  if (data && typeof data === 'object' && !Array.isArray(data)) {
    const inner = data as Record<string, unknown>;
    if (Array.isArray(inner[key])) return inner[key] as T[];
  }
  return [];
}
