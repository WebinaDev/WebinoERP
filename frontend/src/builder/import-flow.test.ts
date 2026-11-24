import { describe, expect, it, vi } from "vitest"
import { appendImportedDocument, chooseImportMode, missingDocumentDependencies, unknownWidgetTypes } from "./import-flow"
import type { BuilderDocument } from "./types"

describe("JSON import choice", () => {
  it("replaces an empty document without a prompt", () => {
    const confirm = vi.fn(() => false)
    expect(chooseImportMode(false, confirm, confirm)).toBe("replace")
    expect(confirm).not.toHaveBeenCalled()
  })
  it("replaces only after confirmation", () => {
    const append = vi.fn(() => false)
    expect(chooseImportMode(true, () => true, append)).toBe("replace")
    expect(append).not.toHaveBeenCalled()
  })
  it("requires separate consent to append and can cancel", () => {
    expect(chooseImportMode(true, () => false, () => true)).toBe("append")
    expect(chooseImportMode(true, () => false, () => false)).toBe("cancel")
  })
})

describe("append import", () => {
  const current: BuilderDocument = { version: 2, css: ".old{}", sections: [{ id: "old", columns: [{ id: "col-old", span: 12, widgets: [] }] }] }
  const imported: BuilderDocument = { version: 2, css: "#new{}", sections: [{ id: "new", columns: [{ id: "col-new", span: 12, widgets: [] }] }] }
  it("preserves IDs and both CSS blocks while invalidating compiled CSS", () => {
    const result = appendImportedDocument({ ...current, compiled: { v: 1, bp: "", hash: "", css: "stale" } }, imported)!
    expect(result.sections.map((s) => s.id)).toEqual(["old", "new"])
    expect(result.css).toBe(".old{}\n#new{}")
    expect(result.compiled).toBeUndefined()
    expect(current.sections).toHaveLength(1)
  })
  it("rejects collisions, including nested node IDs, without modifying either document", () => {
    const nested = { ...imported, sections: [{ ...imported.sections[0], columns: [{ id: "col-old", span: 12, widgets: [] }] }] }
    expect(appendImportedDocument(current, nested)).toBeNull()
    expect(current.sections).toHaveLength(1)
  })
})

it("finds unknown types in nested sections without listing duplicates", () => {
  const document: BuilderDocument = { version: 2, sections: [{ id: "s", columns: [{ id: "c", span: 12, widgets: [{ id: "w", type: "container", props: {}, children: [{ id: "s2", columns: [{ id: "c2", span: 12, widgets: [{ id: "w2", type: "unknown", props: {} }, { id: "w3", type: "unknown", props: {} }] }] }] }] }] }] }
  expect(unknownWidgetTypes(document, (type) => type === "container")).toEqual(["unknown"])
})

it("finds missing global dependencies through reusable components without looping", () => {
  const componentSections = [{ id: "cs", columns: [{ id: "cc", span: 12, widgets: [{ id: "cw", type: "component", props: { component: "shared", queryId: "missing-query" }, advanced: { classes: ["missing-class"] } }] }] }]
  const document: BuilderDocument = { version: 2, sections: [{ id: "s", advanced: { preset: "missing-preset" }, columns: [{ id: "c", span: 12, widgets: [{ id: "w", type: "component", props: { component: "shared" } }] }] }] }
  const globals = { components: [{ id: "shared", name: "Shared", sections: componentSections, props: [] }], classes: [], presets: [], queries: [] }
  expect(new Set(missingDocumentDependencies(document, globals))).toEqual(new Set(["preset:missing-preset", "class:missing-class", "query:missing-query"]))
  expect(missingDocumentDependencies(document, { ...globals, components: [] })).toContain("component:shared")
})
