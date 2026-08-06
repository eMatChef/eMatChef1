export const DEFAULT_LANGUAGE = 'de'

/** Org-Typen für Sprachvarianten (Pfadi / Cevi / Jubla). */
export const ORG_LANGUAGE_VARIANTS = ['pfadi', 'cevi', 'jubla'] as const
export type OrgLanguageVariant = (typeof ORG_LANGUAGE_VARIANTS)[number]

/** CH-Hauptsprachen, die Org-Deltas haben können. */
export const VARIANT_BASE_LANGUAGES = ['de', 'fr', 'it'] as const
export type VariantBaseLanguage = (typeof VARIANT_BASE_LANGUAGES)[number]

export const SUPPORTED_LANGUAGE_CODES = [
  'de',
  'de-pfadi',
  'de-cevi',
  'de-jubla',
  'en',
  'fr',
  'fr-pfadi',
  'fr-cevi',
  'fr-jubla',
  'it',
  'it-pfadi',
  'it-cevi',
  'it-jubla',
  'ch-rm',
] as const

export type SupportedLanguageCode = (typeof SUPPORTED_LANGUAGE_CODES)[number]

export type OrgVariantLanguageCode = `${VariantBaseLanguage}-${OrgLanguageVariant}`

/**
 * Dialekte/Organisationsschreibweisen: jede Variante hängt an genau einer Basis-Locale.
 * JSON reicht als Delta; fehlende Keys → vue-i18n-Fallback.
 *
 * Matrix: de|fr|it × pfadi|cevi|jubla.
 */
export const LOCALE_BASE_FOR_VARIANT: Record<OrgVariantLanguageCode, VariantBaseLanguage> = {
  'de-pfadi': 'de',
  'de-cevi': 'de',
  'de-jubla': 'de',
  'fr-pfadi': 'fr',
  'fr-cevi': 'fr',
  'fr-jubla': 'fr',
  'it-pfadi': 'it',
  'it-cevi': 'it',
  'it-jubla': 'it',
}

/**
 * Liefert die Eltern-Locale, wenn `code` eine bekannte Untervariante ist (sonst null).
 * Echte Hauptsprachen inkl. `ch-rm` liefern null — ihren Lücken-Fallback steuert i18n.
 */
export function getBaseLocaleForLanguageVariant(code: string | null | undefined): VariantBaseLanguage | null {
  if (!code) return null
  const c = code.trim().toLowerCase()
  if (c in LOCALE_BASE_FOR_VARIANT) {
    return LOCALE_BASE_FOR_VARIANT[c as OrgVariantLanguageCode]
  }
  return null
}

/**
 * vue-i18n-Fallback-Kette pro Untervariante: zuerst die Basis-Locale, bei Bedarf App-Default.
 * (de-pfadi → nur `de`; fr-pfadi → `fr` dann `de`.)
 */
export function variantFallbackLocaleChain(variantCode: string): string[] {
  const key = variantCode.trim().toLowerCase()
  const base = (LOCALE_BASE_FOR_VARIANT as Record<string, VariantBaseLanguage>)[key]
  if (!base) return []
  if (base === DEFAULT_LANGUAGE) return [DEFAULT_LANGUAGE]
  return [base, DEFAULT_LANGUAGE]
}

/** Eintraege fuer `fallbackLocale` (nur Keys aus LOCALE_BASE_FOR_VARIANT). */
export function buildVariantFallbackLocaleMap(): Record<string, string[]> {
  const out: Record<string, string[]> = {}
  for (const variant of Object.keys(LOCALE_BASE_FOR_VARIANT) as OrgVariantLanguageCode[]) {
    out[variant] = variantFallbackLocaleChain(variant)
  }
  return out
}

/** BCP-47-Tag für `Intl` (CH-Kontext). */
export function intlLocaleForUiLanguage(code: string | null | undefined): string {
  const loc = (code || DEFAULT_LANGUAGE).trim().toLowerCase()
  const base = getBaseLocaleForLanguageVariant(loc) ?? loc
  const map: Record<string, string> = {
    de: 'de-CH',
    en: 'en-GB',
    fr: 'fr-CH',
    it: 'it-CH',
    'ch-rm': 'rm-CH',
  }
  return map[base] ?? 'de-CH'
}

export function isSupportedLanguageCode(value: string | null | undefined): value is SupportedLanguageCode {
  if (!value) return false
  const normalized = value.trim().toLowerCase()
  return (SUPPORTED_LANGUAGE_CODES as readonly string[]).includes(normalized)
}

export function normalizeLanguageCode(value: string | null | undefined): SupportedLanguageCode {
  const normalized = (value || '').trim().toLowerCase()
  if (isSupportedLanguageCode(normalized)) return normalized
  const short = normalized.split('-')[0]
  if (isSupportedLanguageCode(short)) return short
  return DEFAULT_LANGUAGE
}
