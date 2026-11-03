import { describe, expect, it } from "vitest"

import { fromRial, toGatewayRial, toRial } from "./currencies"
import { formatMoney, fromRialAmount, setDisplayCurrency, toRialAmount } from "./money"

describe("currency conversion", () => {
  it("converts Iranian display units ↔ rial", () => {
    expect(toRial(120000, "IRT")).toBe(1_200_000)
    expect(fromRial(1_200_000, "IRT")).toBe(120_000)
    expect(toRial(120, "IRHT")).toBe(1_200_000)
    expect(fromRial(1_200_000, "IRHT")).toBe(120)
    expect(toGatewayRial(120000, "IRT")).toBe(1_200_000)
    expect(toGatewayRial(1_200_000, "IRR")).toBe(1_200_000)
  })

  it("formats IRR amounts for display settings", () => {
    setDisplayCurrency("IRT")
    expect(fromRialAmount(1_200_000)).toBe(120_000)
    expect(formatMoney(1_200_000, { locale: "en", currency: "IRT" })).toBe("120,000 Toman")
    setDisplayCurrency("IRR")
    expect(formatMoney(1_200_000, { locale: "en", currency: "IRR" })).toContain("1,200,000")
    setDisplayCurrency("IRHT")
    expect(fromRialAmount(1_200_000, "IRHT")).toBe(120)
    setDisplayCurrency("IRT")
  })

  it("converts display entry back to rial for storage/gateways", () => {
    expect(toRialAmount(120000, "IRT")).toBe(1_200_000)
    expect(toGatewayRial(120, "IRHT")).toBe(1_200_000)
  })
})
