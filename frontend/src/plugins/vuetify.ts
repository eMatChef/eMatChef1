import { createVuetify } from 'vuetify'
import { createVueI18nAdapter } from 'vuetify/locale/adapters/vue-i18n'
import { useI18n, type I18n } from 'vue-i18n'
import { i18n } from '@/i18n'

/** Markenfarben aus brand-tokens.css — Vuetify liest keine CSS-Variablen im Theme-Objekt. */
const BRAND_COLORS = {
  primary: '#059669',
  error: '#dc2626',
} as const

export default createVuetify({
  icons: {
    defaultSet: 'mdi',
  },
  locale: {
    adapter: createVueI18nAdapter({ i18n: i18n as I18n, useI18n }),
  },
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        colors: {
          primary: BRAND_COLORS.primary,
          error: BRAND_COLORS.error,
        },
      },
    },
  },
})
