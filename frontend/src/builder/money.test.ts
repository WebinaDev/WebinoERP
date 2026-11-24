import { describe, expect, it } from "vitest"

import { formatAmountDigits } from "./format-amount"

describe("builder amount formatting", () => {
  it("uses digits and grouping of the active language", () => {
    expect(formatAmountDigits(12345.1, ",", "fa")).toBe("۱۲٬۳۴۵٫۱")
    expect(formatAmountDigits(12345.1, ",", "en")).toBe("12,345.1")
  })

  it("respects a configured grouping separator", () => {
    expect(formatAmountDigits(12345, " ", "en")).toBe("12 345")
  })
})
