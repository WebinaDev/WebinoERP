/* Stable anonymous visitor id for per-visitor storefront lists (compare). Kept in localStorage and sent as X-Visitor-Id. */
const KEY = "webino-visitor-id"

export function visitorId(): string {
  if (typeof window === "undefined") return ""
  try {
    let id = window.localStorage.getItem(KEY) ?? ""
    if (!/^[A-Za-z0-9_-]{16,64}$/.test(id)) {
      const bytes = new Uint8Array(16)
      window.crypto.getRandomValues(bytes)
      id = Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("")
      window.localStorage.setItem(KEY, id)
    }
    return id
  } catch {
    return ""
  }
}

export function visitorHeaders(): Record<string, string> {
  const id = visitorId()
  return id ? { "X-Visitor-Id": id } : {}
}
