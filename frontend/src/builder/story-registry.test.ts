import { describe, expect, it } from "vitest"
import { WIDGETS, widgetDef } from "./registry"
import { importPayload } from "./ops"

describe("story-strip compatibility", () => {
  it("registers one definition with the existing ERP defaults", () => {
    expect(WIDGETS.filter((item) => item.type === "story-strip")).toHaveLength(1)
    expect(widgetDef("story-strip")?.create().props).toMatchObject({ title: "هایلایت‌ها", limit: 0, variant: "poetry" })
    expect(widgetDef("story-strip")?.fields.map((field) => field.key)).toEqual(expect.arrayContaining(["title", "limit", "variant", "tone"]))
  })
  it("preserves existing story settings while importing legacy documents", () => {
    const props = { title: "Example", limit: 3, variant: "poetry", layout: "carousel", tone: "glass" }
    const imported = importPayload(JSON.stringify({ version: 1, sections: [{ id: "s", columns: [{ id: "c", span: 12, widgets: [{ id: "w", type: "story-strip", props }] }] }] }))
    expect(imported?.sections[0].columns[0].widgets[0].props).toEqual(props)
  })
})
