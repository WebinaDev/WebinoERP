import { describe, expect, it } from "vitest"

import { missingOnStep, parseFormFields, stepCount, visiblePayload } from "./forms"
import { linkAttrs, parseLinks, parseRows, rowsToLines, toLocalInput } from "./props"

describe("builder forms", () => {
  it("keeps legacy lines as required text fields", () => {
    const [field] = parseFormFields("نام|name|text")
    expect(field.required).toBe(true)
    expect(field.step).toBe(1)
  })

  it("parses steps, options, optional and conditional fields", () => {
    const fields = parseFormFields("نوع|kind|radio|فرد،شرکت||1\nشرکت|company|text||yes|2|kind=شرکت\nتوضیح|note|textarea||no|2")
    expect(fields[0].options).toEqual(["فرد", "شرکت"])
    expect(stepCount(fields)).toBe(2)
    expect(missingOnStep(fields, { kind: "فرد" }, 2)).toEqual([])
    expect(missingOnStep(fields, { kind: "شرکت" }, 2)).toEqual(["company"])
    expect(visiblePayload(fields, { kind: "فرد", company: "x", note: "n" })).toEqual({ kind: "فرد", note: "n" })
  })

  it("falls back to text for unsupported types", () => {
    expect(parseFormFields("پیوست|cv|file")[0].type).toBe("text")
  })
})

describe("builder props", () => {
  it("round-trips repeater rows in the legacy line format", () => {
    const keys = ["label", "href"]
    const rows = parseRows("خانه|/\nفروشگاه|/shop", keys)
    expect(rows).toEqual([{ label: "خانه", href: "/" }, { label: "فروشگاه", href: "/shop" }])
    const lines = rowsToLines(rows, keys)
    expect(lines).toBe("خانه|/\nفروشگاه|/shop")
    expect(parseLinks(lines, [])).toEqual(rows)
    expect(rowsToLines([{ label: "a|b", href: "/x" }], keys)).toBe("a/b|/x")
    const multi = rowsToLines([{ label: "t", href: "line1\nline2" }], keys)
    expect(multi.split("\n").length).toBe(1)
    expect(parseRows(multi, keys)).toEqual([{ label: "t", href: "line1\nline2" }])
  })

  it("converts UTC ISO datetimes to local input values", () => {
    expect(toLocalInput("2026-10-14T10:30")).toBe("2026-10-14T10:30")
    const d = new Date("2026-10-14T10:30:00Z")
    expect(toLocalInput("2026-10-14T10:30:00Z").slice(11)).toBe(`${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`)
  })

  it("builds link attrs for new tab / nofollow", () => {
    expect(linkAttrs({ href: "/" })).toEqual({})
    expect(linkAttrs({ hrefNewTab: true })).toEqual({ target: "_blank", rel: "noopener noreferrer" })
    expect(linkAttrs({ hrefNewTab: true, hrefNofollow: true })).toEqual({ target: "_blank", rel: "noopener noreferrer nofollow" })
  })
})
