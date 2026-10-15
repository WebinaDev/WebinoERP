export { RTL_LANGS, dirForLang, pageSlugFromPath, uiLocaleForLang, previewTokenFromPath } from "@webina/ui/builder"

/** ERP has a fixed set of marketing routes; single unclaimed segments map to builder pages. */
export function canonicalSiteSegments(segments: string[]): string[] {
  const reserved = new Set(["admin","dashboard","login","setup","api","_next"])
  if (segments.length === 1 && segments[0] && !reserved.has(segments[0])) return ["pages", segments[0]]
  return segments
}
