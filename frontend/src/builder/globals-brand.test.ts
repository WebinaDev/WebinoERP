import assert from "node:assert/strict"
import { test } from "node:test"

import { applyBrandColors, SEED_GLOBAL_COLORS } from "./globals-brand.ts"
import type { BuilderGlobals } from "./globals.ts"

const catalog: BuilderGlobals = {
  colors: {
    primary: "#0054ff",
    secondary: "#22242a",
    text: "#22242a",
    accent: "#0054ff",
    background: "#ffffff",
    surface: "#ffffff",
    border: "#e6e9f2",
    muted: "#6b7080",
    success: "#1F8A70",
    danger: "#C2414A",
  },
  typography: { link: { fontSize: "0.95rem", fontWeight: "600", lineHeight: "1.5", color: "#0054ff" } },
  buttons: {
    variants: {
      primary: { background: "#0054ff", color: "#FFFFFF", hoverBackground: "#004ae0", hoverColor: "#FFFFFF", border: "transparent" },
      secondary: { background: "#22242a", color: "#FFFFFF", hoverBackground: "#1e2025", hoverColor: "#FFFFFF", border: "transparent" },
      outline: { background: "transparent", color: "#22242a", hoverBackground: "#ffffff", hoverColor: "#22242a", border: "#e6e9f2" },
      ghost: { background: "transparent", color: "#22242a", hoverBackground: "#ffffff", hoverColor: "#0054ff", border: "transparent" },
    },
  },
  forms: { labelColor: "#22242a", fieldBackground: "#ffffff", borderColor: "#e6e9f2", focusColor: "#0054ff" },
  layout: { background: "#ffffff" },
  palette: [],
} as unknown as BuilderGlobals

function merged(incoming: { colors?: Record<string, string> } = {}): BuilderGlobals {
  return { ...catalog, colors: { ...catalog.colors, ...(incoming.colors ?? {}) } }
}

test("empty or seed colors derive from marketing branding", () => {
  const brand = { primary: "#1122AA", ink: "#101218", paper: "#F7F8FA", foreground: "#101218" }
  const fromEmpty = applyBrandColors(merged(), brand, {}, catalog)
  assert.equal(fromEmpty.colors.primary, "#1122aa")
  assert.equal(fromEmpty.colors.text, "#101218")
  assert.equal(fromEmpty.colors.background, "#f7f8fa")

  const fromSeed = applyBrandColors(merged({ colors: { ...SEED_GLOBAL_COLORS } }), brand, { colors: { ...SEED_GLOBAL_COLORS } }, catalog)
  assert.equal(fromSeed.colors.primary, "#1122aa")
})

test("explicit custom colors are kept", () => {
  const incoming = { colors: { primary: "#abcdef" } }
  const out = applyBrandColors(merged(incoming), { primary: "#0054ff" }, incoming, catalog)
  assert.equal(out.colors.primary, "#abcdef")
  assert.equal(out.colors.secondary.toLowerCase(), catalog.colors.secondary.toLowerCase())
})
