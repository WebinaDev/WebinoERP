import type { ModuleManifest, SiteTypeSlug } from "./types"

import { accountingManifest } from "../../modules/accounting/manifest"
import { coreManifest } from "../../modules/core/manifest"
import { crmManifest } from "../../modules/crm/manifest"
import { docsManifest } from "../../modules/docs/manifest"
import { hrmManifest } from "../../modules/hrm/manifest"
import { integrationsManifest } from "../../modules/integrations/manifest"
import { marketingManifest } from "../../modules/marketing/manifest"
import { marketplaceManifest } from "../../modules/marketplace/manifest"
import { mfgManifest } from "../../modules/mfg/manifest"
import { projectsManifest } from "../../modules/projects/manifest"
import { salesManifest } from "../../modules/sales/manifest"
import { scmManifest } from "../../modules/scm/manifest"
import { platformManifest } from "../../modules/platform/manifest"

export const SITE_TYPES: {
  slug: SiteTypeSlug
  name_fa: string
  name_en: string
  default_theme_slug: string
}[] = [
  { slug: "erp", name_fa: "ERP", name_en: "ERP", default_theme_slug: "webina-corporate-v1" },
  {
    slug: "corporate",
    name_fa: "شرکتی",
    name_en: "Corporate",
    default_theme_slug: "webina-corporate-v1",
  },
]

export const MODULE_MANIFESTS: ModuleManifest[] = [
  coreManifest,
  hrmManifest,
  accountingManifest,
  crmManifest,
  projectsManifest,
  scmManifest,
  salesManifest,
  mfgManifest,
  platformManifest,
  marketingManifest,
  docsManifest,
  marketplaceManifest,
  integrationsManifest,
]

export function getModuleManifest(slug: string): ModuleManifest | undefined {
  return MODULE_MANIFESTS.find((m) => m.slug === slug)
}

export function getSiteType(slug: string) {
  return SITE_TYPES.find((t) => t.slug === slug)
}
