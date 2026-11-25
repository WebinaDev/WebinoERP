import { test, expect } from "vitest"
import { fieldsForPanel } from "./field-panels"
import { widgetDef } from "./registry"
import type { FieldDef } from "./types"

test("legacy fields remain editable in content and explicitly assigned fields appear in their panels", () => {
  const fields: FieldDef[] = [
    { kind: "text", key: "title", label: "Title" },
    { kind: "color", key: "tone", label: "Tone", panel: "design" },
    { kind: "number", key: "limit", label: "Limit", panel: "settings" },
  ]
  const actual = (["content", "design", "settings"] as const).map((panel) => fieldsForPanel(fields, panel).map((field) => field.key))
  expect(actual).toEqual([["title"], ["tone"], ["limit"]])
})

test("basic widget controls appear in their intended panels", () => {
  const popup = widgetDef("popup")!
  expect(fieldsForPanel(popup.fields ?? [], "content").map((field) => field.key)).toContain("title")
  expect(fieldsForPanel(popup.fields ?? [], "design").map((field) => field.key)).toContain("layout")
  expect(fieldsForPanel(popup.fields ?? [], "settings").map((field) => field.key)).toContain("trigger")
  expect(fieldsForPanel(widgetDef("spacer")!.fields ?? [], "design").map((field) => field.key)).toContain("size")
  expect(fieldsForPanel(widgetDef("divider")!.fields ?? [], "design").map((field) => field.key)).toContain("color")
})
