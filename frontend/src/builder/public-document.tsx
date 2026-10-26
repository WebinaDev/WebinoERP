import { cache, Suspense, type ReactNode } from "react"

import { apiServer, nullIfNotFound } from "@/lib/public-api-server"
import { hoistDataUrls } from "@/lib/inline-media"
import { prefetchForDocuments } from "./server-prefetch"
import { asGlobals, type BuilderGlobals } from "./globals"
import { DocumentView } from "./render/DocumentView"
import { themeQueryString, type ThemeQuery } from "./theme/context"
import { requestThemeContext } from "./theme/request"
import { isDocument } from "./tree"
import type { BuilderDocument, RuntimeContext, SectionNode } from "./types"

// Published builder output is cached for a few seconds so each page view does not
// trigger several backend round-trips. Edits still show up within this window.
const PUBLIC_DOC_TTL = 10
import { storefrontThemeClass } from "@/themes/shared/storefront-skin"

/** Drop hidden nodes and editor-only metadata so they are not serialized into the RSC payload of public pages. */
function pruneForView(doc: BuilderDocument): BuilderDocument {
  const strip = <T extends { hidden?: boolean; label?: string; locked?: boolean }>(n: T): T => {
    const { label: _l, locked: _k, ...rest } = n
    void _l; void _k
    return rest as T
  }
  const sections = (list: SectionNode[]): SectionNode[] =>
    list.filter((s) => !s.hidden).map((s) => ({
      ...strip(s),
      columns: s.columns.filter((c) => !c.hidden).map((c) => ({
        ...strip(c),
        widgets: c.widgets.filter((w) => !w.hidden).map((w) => ({ ...strip(w), ...(w.children ? { children: sections(w.children) } : {}) })),
      })),
    }))
  const { source: _src, ...rest } = doc
  void _src
  return { ...rest, sections: sections(doc.sections ?? []) }
}

async function readDocument(path: string): Promise<BuilderDocument | null> {
  try {
    const res = await apiServer<{ data?: { document?: unknown } }>(path, { revalidate: PUBLIC_DOC_TTL, tags: ["builder"] })
    const document = res?.data?.document
    return isDocument(document) ? hoistDataUrls(pruneForView(document)) : null
  } catch (err) {
    // Real 404 = no published document. Anything else (backend restarting) rethrows so a valid
    // route never renders/caches a 404; ISR keeps serving the last good page instead.
    return nullIfNotFound(err)
  }
}

export function loadPublishedPage(slug: string): Promise<BuilderDocument | null> {
  return readDocument(`/v1/public/builder/pages/${encodeURIComponent(slug)}`)
}

export function loadPublishedTemplate(kind: "header" | "footer"): Promise<BuilderDocument | null> {
  return loadResolvedTemplate(kind, { path: "/" })
}

export const loadPublishedGlobals = cache(async (): Promise<BuilderGlobals | null> => {
  try {
    const res = await apiServer<{ data?: { settings?: unknown } }>("/v1/public/builder/globals", { revalidate: PUBLIC_DOC_TTL, tags: ["builder"] })
    return res?.data?.settings ? asGlobals(hoistDataUrls(res.data.settings)) : null
  } catch {
    return null
  }
})

export const loadResolvedTemplate = cache(async (kind: string, query: ThemeQuery): Promise<BuilderDocument | null> => {
  return readDocument(`/v1/public/builder/resolve?${themeQueryString(kind, query)}`)
})

export async function activeThemeSlug(): Promise<string | null> {
  try {
    const res = await apiServer<{ data?: { active_theme_slug?: string | null } }>("/v1/public/site", {
      revalidate: 30,
    })
    return res?.data?.active_theme_slug ?? null
  } catch {
    return null
  }
}

export function PublishedDocument({
  document,
  runtime,
  globals,
  themeClass,
}: {
  document: BuilderDocument
  runtime?: RuntimeContext
  globals?: BuilderGlobals | null
  themeClass?: string
}) {
  return (
    <Suspense fallback={null}>
      <DocumentView document={document} mode="view" device="desktop" runtime={runtime} globals={globals ?? undefined} themeClass={themeClass ?? (storefrontThemeClass(runtime?.themeSlug) || "sf-classic")} />
    </Suspense>
  )
}

export async function StorefrontDocument({
  document,
  runtime,
  context,
  themeClass,
}: {
  document: BuilderDocument
  runtime?: RuntimeContext
  context?: ThemeQuery
  themeClass?: string
}) {
  // Parallel instead of four sequential round-trips.
  const [globals, query] = await Promise.all([loadPublishedGlobals(), context ?? requestThemeContext()])
  const [loop, themeSlug, prefetch] = await Promise.all([
    runtime?.loopDocument ?? loadResolvedTemplate("loop_item", query),
    runtime?.themeSlug ?? activeThemeSlug(),
    prefetchForDocuments([document]),
  ])
  return (
    <PublishedDocument
      document={document}
      globals={globals}
      themeClass={themeClass}
      runtime={{ ...runtime, themeSlug, loopDocument: loop ?? undefined, ...(prefetch ? { prefetch: { ...runtime?.prefetch, ...prefetch } } : {}) }}
    />
  )
}

export async function resolveStorefrontDocument(
  slug: string,
  themeFallback: BuilderDocument | null,
): Promise<BuilderDocument | null> {
  const published = await loadPublishedPage(slug)
  if (published) return published
  // ERP company site always falls back to the corporate theme until a document is published.
  return themeFallback
}

export async function StorefrontBody({
  slug,
  fallback,
  runtime,
  empty,
}: {
  slug: string
  fallback: BuilderDocument | null
  runtime?: RuntimeContext
  empty?: ReactNode
}) {
  const document = await resolveStorefrontDocument(slug, fallback)
  if (!document) return empty ?? null
  return <StorefrontDocument document={document} runtime={runtime} />
}
