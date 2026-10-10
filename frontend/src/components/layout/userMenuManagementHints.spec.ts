// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'

// TopHeader baut den Schlüssel dynamisch: managementHint + (Org|Sub) + (Organisation|Department).
const KEYS = ['OrgOrganisation', 'OrgDepartment', 'SubOrganisation', 'SubDepartment'].map(
  (suffix) => `layout.userMenu.managementHint${suffix}`,
)
const LOCALES = [
  'de', 'en', 'fr', 'it', 'ch-rm',
  'de-pfadi', 'de-cevi', 'de-jubla', 'fr-pfadi', 'fr-cevi', 'fr-jubla', 'it-pfadi', 'it-cevi', 'it-jubla',
] as const

describe('UserNav management hints', () => {
  it.each(LOCALES)('resolves every management hint in %s (own text or fallback, never the key)', (locale) => {
    const t = i18n.global.t
    i18n.global.locale.value = locale as never
    for (const key of KEYS) {
      const text = t(key)
      expect(text, `${locale}: ${key}`).not.toBe(key)
      expect(text).not.toContain('layout.userMenu')
    }
    i18n.global.locale.value = 'de'
  })
})
