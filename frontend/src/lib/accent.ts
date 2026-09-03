export {
  ACCENT_PRESETS,
  type AccentPreset,
  normalizeAccent,
  applyAccent,
  readStoredAccent,
  persistAccent,
} from "@webina/ui"

export const ACCENT_MENU_ITEMS = [
  { value: "zinc", labelKey: "settings.accentZinc" },
  { value: "slate", labelKey: "settings.accentSlate" },
  { value: "blue", labelKey: "settings.accentBlue" },
  { value: "green", labelKey: "settings.accentGreen" },
  { value: "rose", labelKey: "settings.accentRose" },
  { value: "orange", labelKey: "settings.accentOrange" },
] as const

/** Visual swatch colors for each accent preset */
export const ACCENT_SWATCH: Record<string, string> = {
  zinc: "#71717a",
  slate: "#64748b",
  blue: "#0066d6",
  green: "#22c55e",
  rose: "#f43f5e",
  orange: "#f97316",
  default: "#71717a",
}
