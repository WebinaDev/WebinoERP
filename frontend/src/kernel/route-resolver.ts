import { MODULE_MANIFESTS } from "./registry"
import type { ResolvedAdminRoute, ResolvedSiteRoute, TenantActivation } from "./types"

export function isSubmoduleEnabled(
  activations: TenantActivation[],
  moduleSlug: string,
  submoduleSlug: string,
): boolean {
  if (moduleSlug === "core") return true
  if (activations.length === 0) return true
  return activations.some(
    (a) =>
      a.module_slug === moduleSlug &&
      a.submodule_slug === submoduleSlug &&
      a.enabled,
  )
}

export function resolveAdminRoute(
  segments: string[],
  activations: TenantActivation[],
): ResolvedAdminRoute | null {
  if (segments.length === 0) {
    return {
      moduleSlug: "core",
      path: "",
      submodule: "dashboard",
      labelKey: "nav.erp.dashboard",
      section: "overview",
      order: 0,
      fullPath: "/admin",
    }
  }

  const path = segments.join("/")

  for (const mod of MODULE_MANIFESTS) {
    for (const route of mod.adminRoutes) {
      if (route.path === path) {
        if (!isSubmoduleEnabled(activations, mod.slug, route.submodule)) {
          return null
        }
        return {
          ...route,
          moduleSlug: mod.slug,
          fullPath: `/admin/${route.path}`,
        }
      }
    }
  }

  return null
}

export function resolveSiteRoute(
  segments: string[],
  activations: TenantActivation[],
): ResolvedSiteRoute | null {
  if (segments.length === 0) {
    return {
      moduleSlug: "marketing",
      path: "",
      submodule: "home",
      labelKey: "site.home",
      fullPath: "/",
    }
  }

  const path = segments.join("/")

  for (const mod of MODULE_MANIFESTS) {
    for (const route of mod.siteRoutes) {
      if (route.path === path || matchDynamic(route.path, path)) {
        if (!isSubmoduleEnabled(activations, mod.slug, route.submodule)) {
          return null
        }
        return {
          ...route,
          moduleSlug: mod.slug,
          fullPath: `/${path}`,
        }
      }
    }
  }

  return null
}

function matchDynamic(pattern: string, actual: string): boolean {
  const patternParts = pattern.split("/")
  const actualParts = actual.split("/")
  if (patternParts.length !== actualParts.length) return false
  return patternParts.every((p, i) => p.startsWith(":") || p === actualParts[i])
}
