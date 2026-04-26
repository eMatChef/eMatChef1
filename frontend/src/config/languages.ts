export const DEFAULT_LANGUAGE = 'de'

export const SUPPORTED_LANGUAGE_CODES = ['de', 'de-pfadi', 'de-cevi', 'en', 'fr', 'it', 'ch-rm'] as const
export type SupportedLanguageCode = (typeof SUPPORTED_LANGUAGE_CODES)[number]

/**
 * Dialekte/Organisationsschreibweisen (z. B. de-pfadi, de-cevi) hängen an genau
 * einer Basis-Locale. In den JSON-Dateien reicht ein Delta: fehlende Keys nutzen
 * in vue-i18n den definierten Fallback (siehe `I18N_FALLBACK_LOCALE` in `i18n.ts`).
 * Später z. B. fr-pfadi → 'fr', it-xyz → 'it'.
 */
export const LOCALE_BASE_FOR_VARIANT: Partial<Record<SupportedLanguageCode, 'de' | 'fr' | 'it'>> = {
  'de-pfadi': 'de',
  'de-cevi': 'de'
} as const

/**
 * Liefert die Eltern-Locale, wenn `code` eine bekannte Untervariante ist (sonst null).
 * Echte Hauptsprachen inkl. `ch-rm` liefern null — ihren Lücken-Fallback steuert i18n.
 */
export function getBaseLocaleForLanguageVariant(code: string | null | undefined): 'de' | 'fr' | 'it' | null {
  if (!code) return null
  const c = code.trim().toLowerCase() as SupportedLanguageCode
  if (c in LOCALE_BASE_FOR_VARIANT) {
    return LOCALE_BASE_FOR_VARIANT[c]!
  }
  return null
}

export function isSupportedLanguageCode(value: string | null | undefined): value is SupportedLanguageCode {
  if (!value) return false
  const normalized = value.trim().toLowerCase()
  return SUPPORTED_LANGUAGE_CODES.includes(normalized as SupportedLanguageCode)
}

export function normalizeLanguageCode(value: string | null | undefined): SupportedLanguageCode {
  const normalized = (value || '').trim().toLowerCase()
  if (isSupportedLanguageCode(normalized)) return normalized
  const short = normalized.split('-')[0]
  if (isSupportedLanguageCode(short)) return short
  return DEFAULT_LANGUAGE
}
