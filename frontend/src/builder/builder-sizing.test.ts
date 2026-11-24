import { describe, expect, it, vi } from "vitest"
vi.mock("./ids", () => ({ safeClass: (id: string) => `wb-${id}` }))
import { compileDocument, viewCss } from "./compile"
import { mergeStyle, styleToCss } from "./style"
import type { BuilderDocument } from "./types"

describe("responsive builder sizing", () => {
  it("applies dimensions in the editor and rejects unsafe values", () => {
    const css = styleToCss(mergeStyle({ base: { height: "18rem", minWidth: "2rem", maxHeight: "50vh" }, mobile: { height: "auto" } }, "mobile"))
    expect(css).toMatchObject({ height: "auto", minWidth: "2rem", maxHeight: "50vh" })
    expect(styleToCss({ height: "url(javascript:bad)" }).height).toBeUndefined()
  })
  it("compiles responsive dimensions and discards obsolete CSS caches", () => {
    const doc: BuilderDocument = { version: 2, sections: [{ id: "s", columns: [{ id: "c", span: 12, widgets: [{ id: "w", type: "heading", props: {}, style: { base: { height: "18rem", minWidth: "2rem", maxHeight: "50vh" }, mobile: { height: "auto" } } }] }] }] }
    const compiled = compileDocument(doc)
    expect(compiled.compiled?.css).toContain("height:18rem")
    expect(compiled.compiled?.css).toContain("min-width:2rem")
    expect(compiled.compiled?.css).toContain("max-height:50vh")
    expect(compiled.compiled?.css).toContain("height:auto")
    expect(viewCss({ ...compiled, compiled: { ...compiled.compiled!, v: 3, css: "STALE" } })).not.toBe("STALE")
  })
})
