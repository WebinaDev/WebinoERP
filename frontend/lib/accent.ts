export const ACCENT_PRESETS = [
  'default',
  'red',
  'rose',
  'orange',
  'green',
  'blue',
  'yellow',
  'violet',
] as const;

export type AccentPreset = (typeof ACCENT_PRESETS)[number];

export const ACCENT_MENU_ITEMS = [
  { value: 'default', labelKey: 'settings.accentDefault' },
  { value: 'red', labelKey: 'settings.accentRed' },
  { value: 'rose', labelKey: 'settings.accentRose' },
  { value: 'orange', labelKey: 'settings.accentOrange' },
  { value: 'green', labelKey: 'settings.accentGreen' },
  { value: 'blue', labelKey: 'settings.accentBlue' },
  { value: 'yellow', labelKey: 'settings.accentYellow' },
  { value: 'violet', labelKey: 'settings.accentViolet' },
] as const;

export function normalizeAccent(accent?: string | null): AccentPreset {
  if (!accent || accent === 'default') return 'default';
  if (accent === 'amber') return 'orange';
  if ((ACCENT_PRESETS as readonly string[]).includes(accent)) return accent as AccentPreset;
  return 'default';
}
