export const DEFAULT_LANGUAGE = 'de'

export const SUPPORTED_LANGUAGE_CODES = ['de', 'de-pfadi', 'de-cevi', 'en', 'fr', 'it', 'ch-rm'] as const
export type SupportedLanguageCode = (typeof SUPPORTED_LANGUAGE_CODES)[number]

/**
 * Dialekte/Organisationsschreibweisen: jede Variante hängt an genau einer Basis-Locale.
 * JSON reicht als Delta; fehlende Keys → vue-i18n-Fallback (siehe `buildI18nFallbackLocale()`).
 *
 * Beispiele heute: de-pfadi, de-cevi → de.
 * Geplant: fr-pfadi, fr-cevi → fr; it-pfadi, it-cevi → it (Codes zu `SUPPORTED_LANGUAGE_CODES`
 * und Backend `app.supported_languages` hinzufügen, dann `fr-pfadi.json` usw. + `i18n.ts`-messages).
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

/**
 * vue-i18n-Fallback-Kette pro Untervariante: zuerst die Basis-Locale, bei Bedarf App-Default.
 * (de-pfadi → nur `de`; fr-pfadi → `fr` dann `de`.)
 */
export function variantFallbackLocaleChain(variantCode: string): string[] {
  const key = variantCode.trim().toLowerCase()
  const base = (LOCALE_BASE_FOR_VARIANT as Record<string, 'de' | 'fr' | 'it'>)[key]
  if (!base) return []
  if (base === DEFAULT_LANGUAGE) return [DEFAULT_LANGUAGE]
  return [base, DEFAULT_LANGUAGE]
}

/** Eintraege fuer `fallbackLocale` (nur Keys aus LOCALE_BASE_FOR_VARIANT). */
export function buildVariantFallbackLocaleMap(): Record<string, string[]> {
  const out: Record<string, string[]> = {}
  for (const variant of Object.keys(LOCALE_BASE_FOR_VARIANT) as Array<keyof typeof LOCALE_BASE_FOR_VARIANT>) {
    out[variant] = variantFallbackLocaleChain(variant)
  }
  return out
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
