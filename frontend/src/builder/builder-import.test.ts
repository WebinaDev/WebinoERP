import { describe, expect, it, vi } from "vitest"

vi.mock("./ids", () => ({ uid: (prefix: string) => `${prefix}-${Math.random()}` }))
import { exportPayload, importPayload } from "./ops"
import type { BuilderDocument } from "./types"

const doc: BuilderDocument = { version: 2, sections: [{ id: "s", columns: [{ id: "c", span: 12, widgets: [{ id: "w", type: "heading", props: { text: "Hello" } }] }] }] }

describe("builder JSON import", () => {
  it("round-trips a document and assigns fresh node ids", () => {
    const imported = importPayload(exportPayload(doc, "Example"))!
    expect(imported.sections[0].columns[0].widgets[0].props).toEqual({ text: "Hello" })
    expect(imported.sections[0].id).not.toBe("s")
    expect(imported.sections[0].columns[0].widgets[0].id).not.toBe("w")
  })
  it("migrates a bare legacy document", () => {
    expect(importPayload(JSON.stringify({ ...doc, version: 1 }))?.sections[0].legacy).toBe(true)
  })
  it("keeps unique IDs when replacing so custom CSS and links still target the same nodes", () => {
    const original = { ...doc, css: "#w { color: red }", compiled: { v: 4, bp: "1024/767", hash: "old", css: "stale" } }
    const imported = importPayload(exportPayload(original, "Example"), { preserveIds: true })!
    expect(imported.sections[0].columns[0].widgets[0].id).toBe("w")
    expect(imported.css).toBe(original.css)
    expect(imported.compiled).toBeUndefined()
    expect(importPayload(exportPayload(original, "Example"))!.sections[0].columns[0].widgets[0].id).not.toBe("w")
  })
  it("regenerates IDs if a replacement has missing or duplicate node IDs", () => {
    const invalidIds = { ...doc, sections: [{ ...doc.sections[0], id: "w" }] }
    const result = importPayload(JSON.stringify(invalidIds), { preserveIds: true })!
    expect(result.sections[0].id).not.toBe("w")
    expect(result.sections[0].columns[0].widgets[0].id).not.toBe("w")
  })
  it("rejects malformed and unsupported documents", () => {
    for (const value of ["{", JSON.stringify({ version: 3, sections: [] }), JSON.stringify({ sections: [null] }), JSON.stringify({ sections: [{ columns: [{ span: 12, widgets: [{ type: "heading" }] }] }] })]) {
      expect(importPayload(value)).toBeNull()
    }
    expect(importPayload(JSON.stringify({ format: "other-builder", document: doc }))).toBeNull()
    expect(importPayload('{"sections":[{"columns":[{"span":1e999,"widgets":[]}]}]}')).toBeNull()
    expect(importPayload(JSON.stringify({ ...doc, sections: [{ ...doc.sections[0], columns: [{ ...doc.sections[0].columns[0], widgets: [{ id: "w", type: " ", props: {} }] }] }] }))).toBeNull()
  })
})
