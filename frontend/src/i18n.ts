import { createI18n } from 'vue-i18n'
import { de as vuetifyDe, en as vuetifyEn, fr as vuetifyFr, it as vuetifyIt } from 'vuetify/locale'
import de from '@/locales/de.json'
import dePfadi from '@/locales/de-pfadi.json'
import deCevi from '@/locales/de-cevi.json'
import deJubla from '@/locales/de-jubla.json'
import en from '@/locales/en.json'
import fr from '@/locales/fr.json'
import frPfadi from '@/locales/fr-pfadi.json'
import frCevi from '@/locales/fr-cevi.json'
import frJubla from '@/locales/fr-jubla.json'
import it from '@/locales/it.json'
import itPfadi from '@/locales/it-pfadi.json'
import itCevi from '@/locales/it-cevi.json'
import itJubla from '@/locales/it-jubla.json'
import chRm from '@/locales/ch-rm.json'
import {
  DEFAULT_LANGUAGE,
  SUPPORTED_LANGUAGE_CODES,
  type SupportedLanguageCode,
  normalizeLanguageCode,
  buildVariantFallbackLocaleMap
} from '@/config/languages'
import type { FallbackLocale } from 'vue-i18n'
import { escapeLiteralAtSignInMessages } from '@/utils/i18nEscapeAtSign'

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
  // App-Standard DE (CH). Browser-Sprache nicht als Erstwahl — sonst EN in Dev-/Embedded-Browsern.
  return DEFAULT_LANGUAGE
}

/** Vuetify (v-skeleton-loader, v-data-table, …) via vue-i18n-Adapter → $vuetify.* */
function withVuetifyLocale<T extends Record<string, unknown>>(
  messages: T,
  vuetifyLocale: typeof vuetifyDe,
): T & { $vuetify: typeof vuetifyDe } {
  return {
    ...escapeLiteralAtSignInMessages(messages),
    $vuetify: escapeLiteralAtSignInMessages(vuetifyLocale),
  }
}

/** Vuetify (v-skeleton-loader, v-data-table, …) via vue-i18n-Adapter → $vuetify.* */
function withVuetifyLocale<T extends Record<string, unknown>>(
  messages: T,
  vuetifyLocale: typeof vuetifyDe,
): T & { $vuetify: typeof vuetifyDe } {
  return {
    ...escapeLiteralAtSignInMessages(messages),
    $vuetify: escapeLiteralAtSignInMessages(vuetifyLocale),
  }
}

export const i18n = createI18n({
  legacy: false,
  locale: detectInitialLocale(),
  fallbackLocale: I18N_FALLBACK_LOCALE,
  messages: {
    de: withVuetifyLocale(de, vuetifyDe),
    'de-pfadi': withVuetifyLocale(dePfadi, vuetifyDe),
    'de-cevi': withVuetifyLocale(deCevi, vuetifyDe),
    'de-jubla': withVuetifyLocale(deJubla, vuetifyDe),
    en: withVuetifyLocale(en, vuetifyEn),
    fr: withVuetifyLocale(fr, vuetifyFr),
    'fr-pfadi': withVuetifyLocale(frPfadi, vuetifyFr),
    'fr-cevi': withVuetifyLocale(frCevi, vuetifyFr),
    'fr-jubla': withVuetifyLocale(frJubla, vuetifyFr),
    it: withVuetifyLocale(it, vuetifyIt),
    'it-pfadi': withVuetifyLocale(itPfadi, vuetifyIt),
    'it-cevi': withVuetifyLocale(itCevi, vuetifyIt),
    'it-jubla': withVuetifyLocale(itJubla, vuetifyIt),
    'ch-rm': withVuetifyLocale(chRm, vuetifyDe),
  },
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
