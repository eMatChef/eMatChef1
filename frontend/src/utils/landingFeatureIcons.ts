/** MDI-Icons für Landing-Feature-Karten (öffentliche Marketing-Seite). */
export const DEFAULT_LANDING_FEATURE_ICONS = [
  'mdi-clipboard-check-outline',
  'mdi-account-group-outline',
  'mdi-book-sync-outline',
  'mdi-warehouse',
  'mdi-qrcode-scan',
  'mdi-laptop',
] as const

/** Alte Ein-Zeichen-Symbole aus DB/Defaults → MDI. */
const LEGACY_LANDING_ICON_MAP: Record<string, string> = {
  '⊙': 'mdi-clipboard-check-outline',
  '◎': 'mdi-account-group-outline',
  '⇄': 'mdi-book-sync-outline',
  '⌗': 'mdi-warehouse',
  '◇': 'mdi-qrcode-scan',
  '○': 'mdi-laptop',
}

export function resolveLandingFeatureIcon(icon: string, index = 0): string {
  const trimmed = icon.trim()
  if (trimmed.startsWith('mdi-')) {
    return trimmed
  }
  if (trimmed && LEGACY_LANDING_ICON_MAP[trimmed]) {
    return LEGACY_LANDING_ICON_MAP[trimmed]
  }
  return DEFAULT_LANDING_FEATURE_ICONS[index % DEFAULT_LANDING_FEATURE_ICONS.length]
}
