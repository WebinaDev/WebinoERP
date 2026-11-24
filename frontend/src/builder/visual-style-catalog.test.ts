import { describe, expect, it } from "vitest"
import { PLANNED_VISUAL_STYLES } from "./visual-style-catalog"

describe("planned visual styles", () => {
  it("reserves unique, stable identifiers across all requested families", () => {
    const ids = PLANNED_VISUAL_STYLES.map((style) => style.id)
    expect(new Set(ids).size).toBe(ids.length)
    expect(ids).toHaveLength(77)
    expect(new Set(PLANNED_VISUAL_STYLES.map((style) => style.family)).size).toBe(7)
  })
})
