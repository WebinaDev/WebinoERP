import { act, cleanup, render, screen } from "@testing-library/react"
import { afterEach, describe, expect, it, vi } from "vitest"

import { applyCurrencyDisplay } from "@/lib/display-currency"
import { MoneyDisplay } from "./MoneyDisplay"

vi.mock("next-intl", () => ({ useLocale: () => "en" }))

afterEach(() => {
  cleanup()
  applyCurrencyDisplay({ currency: "IRT", currency_symbol: "default" })
})

describe("MoneyDisplay", () => {
  it("follows the configured symbol and display currency", () => {
    applyCurrencyDisplay({ currency: "IRT", currency_symbol: "toman-2" })
    render(<MoneyDisplay amount={101} />)

    expect(screen.getByRole("img", { name: "Toman" })).toHaveAttribute("src", "/currency/currency-toman-2.svg")
    expect(screen.getByText("10.1")).toBeInTheDocument()

    act(() => applyCurrencyDisplay({ currency: "IRR", currency_symbol: "rial-2" }))
    expect(screen.getByRole("img", { name: "Rial" })).toHaveAttribute("src", "/currency/currency-rial-2.svg")
    expect(screen.getByText("101")).toBeInTheDocument()
  })
})
