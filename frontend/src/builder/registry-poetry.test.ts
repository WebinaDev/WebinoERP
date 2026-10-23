import { describe, expect, it, vi } from "vitest"
vi.mock("./ids", () => ({ uid: (p: string) => `${p}-test` }))
import { POETRY_WIDGET_TYPES, POETRY_WIDGETS } from "./registry-poetry"
import { formatToman, safeHref } from "./render/poetry-widgets"

describe("poetry widgets", () => {
  it("registers all nine types with Persian labels", () => {
    expect(POETRY_WIDGETS.map((w) => w.type)).toEqual([...POETRY_WIDGET_TYPES])
    for (const w of POETRY_WIDGETS) {
      expect(w.label).toMatch(/[\u0600-\u06FF]/)
      expect(w.create().type).toBe(w.type)
    }
  })
  it("formats toman and blocks unsafe links", () => {
    expect(formatToman("1250000")).toContain("تومان")
    expect(safeHref("javascript:alert(1)")).not.toContain("javascript")
  })
})
