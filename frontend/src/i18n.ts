import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import deCevi from '@/locales/de-cevi.json'
import dePfadi from '@/locales/de-pfadi.json'
import en from '@/locales/en.json'
import fr from '@/locales/fr.json'
import it from '@/locales/it.json'
import chRm from '@/locales/ch-rm.json'
import {
  DEFAULT_LANGUAGE,
  SUPPORTED_LANGUAGE_CODES,
  type SupportedLanguageCode,
  normalizeLanguageCode,
  buildVariantFallbackLocaleMap
} from '@/config/languages'
import type { FallbackLocale } from 'vue-i18n'

const LOCALE_STORAGE_KEY = 'app_locale'

/**
 * Lücken-Fallback: Untervarianten kommen aus `LOCALE_BASE_FOR_VARIANT` (siehe languages.ts).
 * `ch-rm` ist keine Untervariante von de — eigene Datei, Lücken mit de/en.
 */
const I18N_FALLBACK_LOCALE: FallbackLocale = {
  ...buildVariantFallbackLocaleMap(),
  'ch-rm': [DEFAULT_LANGUAGE, 'en'],
  en: [DEFAULT_LANGUAGE],
  fr: [DEFAULT_LANGUAGE],
  it: [DEFAULT_LANGUAGE]
}

function detectInitialLocale(): SupportedLanguageCode {
  const stored = localStorage.getItem(LOCALE_STORAGE_KEY)
  if (stored) return normalizeLanguageCode(stored)
  return normalizeLanguageCode(navigator.language)
}

export const i18n = createI18n({
  legacy: false,
  locale: detectInitialLocale(),
  fallbackLocale: I18N_FALLBACK_LOCALE,
  messages: {
    de,
    'de-pfadi': dePfadi,
    'de-cevi': deCevi,
    en,
    fr,
    it,
    'ch-rm': chRm
  }
})

export function setLocale(locale: string | null | undefined): SupportedLanguageCode {
  const normalized = normalizeLanguageCode(locale)
  i18n.global.locale.value = normalized
  localStorage.setItem(LOCALE_STORAGE_KEY, normalized)
  return normalized
}

export function getCurrentLocale(): SupportedLanguageCode {
  return normalizeLanguageCode(i18n.global.locale.value)
}

export { SUPPORTED_LANGUAGE_CODES as SUPPORTED_LOCALES }
