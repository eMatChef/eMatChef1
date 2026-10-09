// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'

const KEYS = [
  'settings.myDepartment.removePrimary',
  'settings.myDepartment.toastPrimaryRemoved',
  'settings.myDepartment.primaryChange.confirmTitle',
  'settings.myDepartment.primaryChange.confirmMessage',
  'settings.myDepartment.primaryChange.confirmAction',
  'settings.myDepartment.primaryRemove.confirmTitle',
  'settings.myDepartment.primaryRemove.confirmMessage',
  'settings.myDepartment.primaryRemove.confirmAction',
]

describe('primary department texts', () => {
  it.each(['de', 'en', 'fr', 'it', 'de-cevi', 'fr-pfadi', 'ch-rm'])('are present in %s (own text or fallback)', (locale) => {
    i18n.global.locale.value = locale as never
    for (const key of KEYS) {
      expect(i18n.global.t(key), `${locale}: ${key}`).not.toBe(key)
    }
    i18n.global.locale.value = 'de'
  })

  it('names the old and the new department in every language', () => {
    for (const locale of ['de', 'en', 'fr', 'it']) {
      i18n.global.locale.value = locale as never
      const text = i18n.global.t('settings.myDepartment.primaryChange.confirmMessage', { from: 'ALT', to: 'NEU' })
      expect(text).toContain('ALT')
      expect(text).toContain('NEU')
    }
    i18n.global.locale.value = 'de'
  })
})
