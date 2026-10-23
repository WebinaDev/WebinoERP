import { describe, expect, it, vi } from "vitest"

vi.mock("./ids", () => ({ uid: (p: string) => `${p}-test` }))
vi.mock("./render/runtime", () => ({ useBuilderRuntime: () => ({}) }))

import { POETRY_MODULE_WIDGET_TYPES, POETRY_MODULE_WIDGETS } from "./registry-poetry-module"
import { poemPlainText, toBeyts } from "./render/poetry-module-widgets"

describe("poetry module widgets", () => {
  it("registers poem-detail, poetry-figure and poem-search", () => {
    expect(POETRY_MODULE_WIDGETS.map((w) => w.type)).toEqual([...POETRY_MODULE_WIDGET_TYPES])
    for (const w of POETRY_MODULE_WIDGETS) {
      const n = w.create()
      expect(n.type).toBe(w.type)
      for (const f of w.fields) expect(f.key in n.props).toBe(true)
    }
  })
  it("pairs hemistichs into beyts and renders plain text with repeats", () => {
    expect(toBeyts(["a", "b", "c"])).toEqual([["a", "b"], ["c"]])
    const t = poemPlainText({ title: "T", pen_name: "طه", sequence: [
      { type: "title", lines: ["T"] }, { type: "refrain", repeat: 2, lines: ["r1", "r2"] }, { type: "stanza", lines: ["s1", "s2"] }, { type: "refrain", repeat: 2, lines: ["r1", "r2"], auto: true },
    ] })
    expect(t.split("r1").length - 1).toBe(2)
    expect(t).toContain("(۲ بار)")
    expect(t.endsWith("طه")).toBe(true)
  })
})
