import { test, expect } from "vitest"
import { fieldsForPanel } from "./field-panels"
import { readFileSync } from "node:fs"
import { dirname, resolve } from "node:path"
import { fileURLToPath } from "node:url"
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

test("divider design labels exist in both supported locales", () => {
  const fields = widgetDef("divider")!.fields ?? []
  const keys = fields.flatMap((field) => [
    ...(field.labelKey ? [field.labelKey] : []),
    ...(field.kind === "select" ? field.options.flatMap((option) => option.labelKey ? [option.labelKey] : []) : []),
  ])
  for (const locale of ["en", "fa"]) {
    const messages = JSON.parse(readFileSync(resolve(dirname(fileURLToPath(import.meta.url)), "../../messages", `${locale}.json`), "utf8")).builder
    for (const key of keys) expect(messages[key], `${locale}:${key}`).toBeTruthy()
  }
})

test("common controls use their panel without hiding content or overriding explicit assignments", () => {
  const fields: FieldDef[] = [
    { kind: "repeater", key: "columns", label: "Content columns", fields: [] },
    { kind: "number", key: "columns", label: "Column count" },
    { kind: "color", key: "background", label: "Background" },
    { kind: "color", key: "accent", label: "Accent", panel: "content" },
    { kind: "switch", key: "autoplay", label: "Autoplay" },
    { kind: "number", key: "limit", label: "Limit" },
    { kind: "switch", key: "mobileFullWidth", label: "Mobile width" },
    { kind: "select", key: "layout", label: "Layout", options: [] },
    { kind: "text", key: "anchor_id", label: "Anchor" },
  ]
  expect(fieldsForPanel(fields, "content").map((field) => field.key)).toEqual(["columns", "accent"])
  expect(fieldsForPanel(fields, "design").map((field) => field.key)).toEqual(["columns", "background", "mobileFullWidth", "layout"])
  expect(fieldsForPanel(fields, "settings").map((field) => field.key)).toEqual(["autoplay", "limit", "anchor_id"])
})
