import { describe, expect, it, vi } from "vitest"

vi.mock("./ids", () => ({ uid: (p: string) => `${p}-test` }))
vi.mock("./render/runtime", () => ({ useBuilderRuntime: () => ({ siteName: "علی خبری", logoUrl: "/logo.svg" }) }))
vi.mock("./storefront/use-catalog", () => ({ fetchCatalogPayload: () => Promise.resolve(null) }))
vi.mock("./cart", () => ({ addToCart: () => undefined }))
vi.mock("next/link", () => ({ default: ({ href, children, ...rest }: { href: string; children: unknown }) => ({ type: "a", props: { href, ...rest, children }, key: null, $$typeof: Symbol.for("react.element") }) }))

import { renderToStaticMarkup } from "react-dom/server"
import * as React from "react"
import { createElement } from "react"
;(globalThis as { React?: unknown }).React = React

import { POETRY_PAGE_WIDGET_TYPES, POETRY_PAGE_WIDGETS } from "./registry-poetry-pages"
import * as R from "./render/poetry-pages-widgets"

const RENDER: Record<string, (p: { widget: never }) => unknown> = {
  "poetry-nav": R.PoetryNavWidget, "poetry-page-hero": R.PoetryPageHeroWidget, "poem-archive": R.PoemArchiveWidget, "poem-single": R.PoemSingleWidget,
  "media-archive": R.MediaArchiveWidget, "poetry-shop": R.PoetryShopWidget, "poetry-product": R.PoetryProductWidget, "poet-profile": R.PoetProfileWidget, "poetry-site-footer": R.PoetrySiteFooterWidget,
}

describe("poetry page widgets (alikhabari mockups)", () => {
  it("registers every type with defaults for every field", () => {
    expect(POETRY_PAGE_WIDGETS.map((w) => w.type)).toEqual([...POETRY_PAGE_WIDGET_TYPES])
    for (const w of POETRY_PAGE_WIDGETS) {
      const n = w.create()
      expect(n.type).toBe(w.type)
      for (const f of w.fields) expect(f.key in n.props, `${w.type}.${f.key}`).toBe(true)
      expect(n.props.accent).toBe("#B4101E")
    }
  })
  it("renders every widget server-side without a photo", () => {
    for (const w of POETRY_PAGE_WIDGETS) {
      const html = renderToStaticMarkup(createElement(RENDER[w.type] as never, { widget: w.create() } as never))
      expect(html.length, w.type).toBeGreaterThan(0)
    }
  })
  it("poet profile defaults to the logo slot, never a photo", () => {
    const n = POETRY_PAGE_WIDGETS.find((w) => w.type === "poet-profile")!.create()
    const html = renderToStaticMarkup(createElement(R.PoetProfileWidget, { widget: { ...n, props: { ...n.props, image: "/me.jpg" } } }))
    expect(html).toContain('data-mode="logo"')
    expect(html).not.toContain("/me.jpg")
    const photo = renderToStaticMarkup(createElement(R.PoetProfileWidget, { widget: { ...n, props: { ...n.props, photoMode: "photo", image: "/me.jpg" } } }))
    expect(photo).toContain("/me.jpg")
  })
  it("shop works with checkout off: manual demo items, text price, inquiry link", () => {
    const n = POETRY_PAGE_WIDGETS.find((w) => w.type === "poetry-shop")!.create()
    const html = renderToStaticMarkup(createElement(R.PoetryShopWidget, { widget: { ...n, props: { ...n.props, source: "manual", items: "قاب یا حسین||||||بزرگ|1" } } }))
    expect(html).toContain("قاب یا حسین")
    expect(html).toContain("قیمت (از مشتری)")
    expect(html).toContain("eitaa.com/ali_khabari_poet")
    expect(html).toContain("نمونه")
    expect(html).not.toContain("افزودن به سبد")
  })
  it("helpers", () => {
    expect(R.isDemoProduct("demo-frame", "x")).toBe(true)
    expect(R.isDemoProduct("frame", "قاب (نمونه)")).toBe(true)
    expect(R.isDemoProduct("frame", "قاب")).toBe(false)
    expect(R.toman("۱۲۰۰۰۰")).toBe("۱۲۰٬۰۰۰ تومان")
    expect(R.safeLink("javascript:alert(1)")).toBe("#")
    expect(R.apiV1("public/poetry/terms")).toMatch(/\/api\/v1\/public\/poetry\/terms$/)
  })
})
