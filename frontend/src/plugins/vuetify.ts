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
  /** Standard-Breakpoints (Plan: md = 960) — Vuetify-4-Default lg=1145 würde sonst Hamburger/Drawer auseinanderlaufen. */
  display: {
    mobileBreakpoint: 'md',
    thresholds: {
      xs: 0,
      sm: 600,
      md: 960,
      lg: 1280,
      xl: 1920,
      xxl: 2560,
    },
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
  defaults: {
    VTextField: { variant: 'outlined', density: 'comfortable' },
    VSelect: { variant: 'outlined', density: 'comfortable' },
    VTextarea: { variant: 'outlined', density: 'comfortable' },
  },
})
