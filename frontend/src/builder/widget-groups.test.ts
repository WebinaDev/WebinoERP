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
  it("legacy alias types still resolve", () => {
    expect(widgetDef("checkout-stub")?.type).toBe("checkout")
  })
  it("switching keeps user props", () => {
    const w = { id: "w1", type: "blog-posts", props: { limit: 7 } }
    const next = switchGroupMember(w, "blog-list", (t) => ({ id: "x", type: t, props: { limit: 12, allLabel: "همه" } }))
    expect(next).toMatchObject({ id: "w1", type: "blog-list", props: { limit: 7, allLabel: "همه" } })
  })
})
