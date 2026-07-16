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
