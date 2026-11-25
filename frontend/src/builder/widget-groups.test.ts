import { describe, expect, it } from "vitest"
import { WIDGETS, widgetDef } from "./registry"
import { WIDGET_GROUPS, isLibraryWidget, switchGroupMember } from "./widget-groups"

describe("widget groups", () => {
  it("every member is a registered widget and canonical is a member", () => {
    for (const g of WIDGET_GROUPS) {
      expect(g.members.map((m) => m.type)).toContain(g.canonical)
      for (const m of g.members) expect(widgetDef(m.type), m.type).toBeTruthy()
    }
  })
  it("library hides non-canonical members only", () => {
    expect(isLibraryWidget("blog-posts")).toBe(true)
    expect(isLibraryWidget("blog-list")).toBe(false)
    expect(WIDGETS.filter((w) => isLibraryWidget(w.type)).length).toBeGreaterThan(100)
  })
  it("keeps distinct sales and course capabilities independently available", () => {
    for (const type of ["sale-product-grid", "sale-product-hero", "sale-product-tabs", "sale-cart", "academy-course-hero", "academy-course-about", "academy-course-syllabus", "academy-course-instructor", "academy-course-quizzes", "academy-course-reviews", "academy-course-enroll", "academy-related-courses", "academy-grid", "announcements-list", "downloads-list", "portfolio-gallery", "portfolio-grid", "media-archive", "cta-banner", "special-banners", "deal-bar", "book-banner", "banner-grid", "promo-banner", "magazine-feature", "blog-teasers"]) {
      expect(widgetDef(type), type).toBeTruthy()
      expect(isLibraryWidget(type), type).toBe(true)
    }
  })
  it("legacy alias types still resolve", () => {
    expect(widgetDef("checkout-stub")?.type).toBe("checkout")
  })
  it("switching keeps user props", () => {
    const w = { id: "w1", type: "blog-posts", props: { limit: 7 } }
    const next = switchGroupMember(w, "blog-list", (t) => ({ id: "x", type: t, props: { limit: 12, allLabel: "همه" } }))
    expect(next).toMatchObject({ id: "w1", type: "blog-list", props: { limit: 7, allLabel: "همه" } })
  })
  it("does not switch unrelated capabilities or rewrite the current type", () => {
    const widget = { id: "w1", type: "blog-posts", props: { limit: 7 } }
    const create = () => { throw new Error("unrelated widget was created") }
    expect(switchGroupMember(widget, "image-gallery", create)).toBe(widget)
    expect(switchGroupMember(widget, "blog-posts", create)).toBe(widget)
    expect(switchGroupMember({ ...widget, type: "text" }, "blog-list", create).type).toBe("text")
  })
  it("preserves gallery alt text and carousel links across style switches", () => {
    const create = (type: string) => ({ id: "new", type, props: {} })
    const gallery = { id: "w", type: "image-gallery", props: { images: "one.jpg|description|extra" } }
    const carousel = switchGroupMember(gallery, "image-carousel", create)
    expect(carousel.props.images).toBe("one.jpg||description|extra")
    const withLink = { ...carousel, props: { ...carousel.props, images: "one.jpg|/target|description|extra" } }
    const switchedBack = switchGroupMember(withLink, "image-gallery", create)
    expect(switchedBack.props.images).toBe("one.jpg|description|/target|extra")
    expect(switchGroupMember(switchedBack, "image-carousel", create).props.images).toBe("one.jpg|/target|description|extra")
    expect(gallery.props.images).toBe("one.jpg|description|extra")
  })
})
