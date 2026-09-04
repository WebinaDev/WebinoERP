import {
  ERP_DASHBOARD_ITEMS,
  ERP_MODULES,
  ERP_SUBMODULES,
  type ErpMenuItemDef,
  type ErpModuleDef,
} from "@/lib/module-registry"
import type { AdminRouteDef, ModuleManifest, SiteTypeSlug } from "./types"

const SLUG_MAP: Record<string, string> = {
  finance: "accounting",
  pm: "projects",
  distribution: "marketplace",
  admin: "core",
}

const NAMES: Record<string, { fa: string; en: string; order: number }> = {
  core: { fa: "هسته", en: "Core", order: 0 },
  hrm: { fa: "منابع انسانی", en: "HRM", order: 10 },
  accounting: { fa: "حسابداری", en: "Accounting", order: 20 },
  crm: { fa: "CRM", en: "CRM", order: 30 },
  projects: { fa: "پروژه‌ها", en: "Projects", order: 40 },
  scm: { fa: "زنجیره تأمین", en: "SCM", order: 50 },
  sales: { fa: "فروش", en: "Sales", order: 60 },
  mfg: { fa: "تولید", en: "Manufacturing", order: 70 },
  platform: { fa: "پلتفرم", en: "Platform", order: 75 },
  marketing: { fa: "بازاریابی", en: "Marketing", order: 90 },
  docs: { fa: "اسناد", en: "Documents", order: 100 },
  marketplace: { fa: "مارکت‌پلیس", en: "Marketplace", order: 110 },
  integrations: { fa: "یکپارچه‌سازی", en: "Integrations", order: 120 },
}

function flattenMenu(items: ErpMenuItemDef[]): ErpMenuItemDef[] {
  const out: ErpMenuItemDef[] = []
  for (const item of items) {
    if (item.children?.length) {
      out.push(...flattenMenu(item.children))
    } else {
      out.push(item)
    }
  }
  return out
}

function toAdminRoutes(mod: ErpModuleDef, section: string): AdminRouteDef[] {
  const leaves = flattenMenu(mod.menuItems)
  for (const sub of ERP_SUBMODULES) {
    if (sub.parentModuleId === mod.id) {
      leaves.push(...flattenMenu([sub.menu]))
    }
  }
  return leaves.map((item, i) => ({
    path: item.path,
    submodule: item.id,
    labelKey: item.titleKey,
    section,
    order: i,
  }))
}

export function erpModuleToManifest(mod: ErpModuleDef): ModuleManifest {
  const slug = SLUG_MAP[mod.id] ?? mod.id
  const names = NAMES[slug] ?? { fa: mod.id, en: mod.id, order: 99 }
  const adminRoutes = toAdminRoutes(mod, slug)
  const submodules = [...new Set(adminRoutes.map((r) => r.submodule))]
  const siteTypes: SiteTypeSlug[] = slug === "marketing" ? ["corporate", "erp"] : ["erp"]
  const siteRoutes =
    slug === "marketing"
      ? [
          { path: "blog", submodule: "marketing-blog", labelKey: "nav.erp.marketing.blog" },
          { path: "blog/:slug", submodule: "marketing-blog", labelKey: "nav.erp.marketing.blog" },
          { path: "academy", submodule: "marketing-academy", labelKey: "nav.erp.marketing.academy" },
          { path: "magazine", submodule: "marketing-magazine", labelKey: "nav.erp.marketing.magazine" },
          { path: "portfolio", submodule: "marketing-portfolio", labelKey: "nav.erp.marketing.portfolio" },
          { path: "services", submodule: "marketing-services", labelKey: "nav.erp.marketing.services" },
          { path: "solutions", submodule: "marketing-solutions", labelKey: "nav.erp.marketing.solutions" },
          { path: "about", submodule: "marketing-pages", labelKey: "nav.erp.marketing.pages" },
          { path: "contact", submodule: "marketing-pages", labelKey: "nav.erp.marketing.pages" },
          { path: "consultation", submodule: "marketing-pages", labelKey: "nav.erp.marketing.pages" },
        ]
      : []

  return {
    slug,
    nameFa: names.fa,
    nameEn: names.en,
    siteTypes,
    submodules,
    adminNav: { section: slug, order: names.order },
    publicRoutes: siteRoutes.map((r) => r.path),
    adminRoutes,
    siteRoutes,
  }
}

function coreManifest(): ModuleManifest {
  const adminRoutes: AdminRouteDef[] = [
    { path: "", submodule: "dashboard", labelKey: "nav.erp.dashboard", section: "core", order: 0 },
    ...ERP_DASHBOARD_ITEMS.filter((i) => i.path).map((item, i) => ({
      path: item.path,
      submodule: item.id,
      labelKey: item.titleKey,
      section: "core",
      order: i + 1,
    })),
  ]
  const adminMod = ERP_MODULES.find((m) => m.id === "admin")
  if (adminMod) {
    adminRoutes.push(...toAdminRoutes(adminMod, "core"))
  }
  return {
    slug: "core",
    nameFa: "هسته",
    nameEn: "Core",
    siteTypes: ["erp"],
    submodules: [...new Set(adminRoutes.map((r) => r.submodule))],
    adminNav: { section: "core", order: 0 },
    publicRoutes: [],
    adminRoutes,
    siteRoutes: [{ path: "", submodule: "home", labelKey: "site.home" }],
  }
}

let cachedManifests: ModuleManifest[] | null = null

export function buildErpManifests(): ModuleManifest[] {
  if (cachedManifests) return cachedManifests
  const manifests = [coreManifest()]
  for (const mod of ERP_MODULES) {
    if (mod.id === "admin") continue
    manifests.push(erpModuleToManifest(mod))
  }
  cachedManifests = manifests
  return manifests
}

export function erpManifest(slug: string): ModuleManifest {
  const found = buildErpManifests().find((m) => m.slug === slug)
  if (!found) {
    throw new Error(`Unknown ERP module manifest: ${slug}`)
  }
  return found
}
