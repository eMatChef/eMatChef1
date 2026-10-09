// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'

const KEYS = [
  'grossanlass.setup.title', 'grossanlass.setup.lead', 'grossanlass.setup.release', 'grossanlass.setup.pendingForRole',
  'grossanlass.setup.steps.stammdaten.title', 'grossanlass.setup.steps.ressorts.hint', 'grossanlass.setup.steps.mitglieder.title',
  'grossanlass.setup.missing.location', 'grossanlass.setup.missing.no_ok',
  'onboarding.tours.gaSetup.title', 'onboarding.tours.gaSetup.description',
  ...Array.from({ length: 8 }, (_, i) => [`onboarding.tours.gaSetup.step${i + 1}Title`, `onboarding.tours.gaSetup.step${i + 1}Body`]).flat(),
]

describe('Grossanlass setup texts', () => {
  it.each(['de', 'en', 'fr', 'it', 'de-cevi', 'fr-pfadi', 'ch-rm'])('are present in %s (own text or fallback)', (locale) => {
    i18n.global.locale.value = locale as never
    for (const key of KEYS) expect(i18n.global.t(key), `${locale}: ${key}`).not.toBe(key)
    i18n.global.locale.value = 'de'
  })

  it('welcomes with the agreed sentence and names the ressort in the hint', () => {
    i18n.global.locale.value = 'de'
    expect(i18n.global.t('onboarding.tours.gaSetup.step1Body')).toContain('Lerne zuerst das System kennen')
    expect(i18n.global.t('grossanlass.setup.missing.ressort_without_leader', { name: 'Zelte' })).toContain('Zelte')
  })
})
