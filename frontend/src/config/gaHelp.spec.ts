// @vitest-environment jsdom
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'
import {
  GA_HELP_FALLBACK_TOPIC,
  GA_HELP_GROUPS,
  GA_HELP_PAGE_ROUTE,
  GA_HELP_TOPICS,
  gaHelpKey,
  gaHelpTopic,
  gaHelpTopicIdForRoute,
} from './gaHelp'

const FIELDS = ['enoughOnHand', 'cash', 'netto']
const LOCALES = ['de', 'en', 'fr', 'it', 'de-pfadi', 'de-cevi', 'de-jubla', 'fr-pfadi', 'it-cevi', 'ch-rm']

function keysOf(): string[] {
  const keys = ['gaHelp.modal.eyebrow', 'gaHelp.modal.faqTitle', 'gaHelp.modal.more', 'gaHelp.modal.close']
  keys.push('gaHelp.page.title', 'gaHelp.page.subtitle', 'gaHelp.page.openPage', 'gaHelp.hint.aria')
  for (const group of GA_HELP_GROUPS) keys.push(`gaHelp.groups.${group}`)
  for (const field of FIELDS) keys.push(gaHelpKey.field(field))
  for (const topic of GA_HELP_TOPICS) {
    keys.push(gaHelpKey.title(topic.id), gaHelpKey.summary(topic.id))
    for (const faq of topic.faq) keys.push(gaHelpKey.question(topic.id, faq), gaHelpKey.answer(topic.id, faq))
  }
  return keys
}

describe('GA help registry', () => {
  it('has unique topic ids and unique route names', () => {
    const ids = GA_HELP_TOPICS.map((t) => t.id)
    expect(new Set(ids).size).toBe(ids.length)
    const routes = GA_HELP_TOPICS.flatMap((t) => t.routeNames)
    expect(new Set(routes).size).toBe(routes.length)
    expect(gaHelpTopic(GA_HELP_FALLBACK_TOPIC)).not.toBeNull()
  })

  it('only references route names that exist in the router', () => {
    const router = readFileSync(resolve(process.cwd(), 'src/router/index.ts'), 'utf8')
    for (const name of [...GA_HELP_TOPICS.flatMap((t) => t.routeNames), GA_HELP_PAGE_ROUTE]) {
      expect(router, name).toContain(`name: '${name}'`)
    }
  })

  it('resolves GA pages to their own chapter', () => {
    expect(gaHelpTopicIdForRoute('GrossanlassPlanungFreigabe')).toBe('freigabe')
    expect(gaHelpTopicIdForRoute('GrossanlassGastVorschau')).toBe('gastVorschau')
    expect(gaHelpTopicIdForRoute('GrossanlassBeschaffungOfferten')).toBe('offerten')
    expect(gaHelpTopicIdForRoute('Dashboard')).toBe('overview')
  })

  it('falls back to the overview for GA pages without own chapter', () => {
    expect(gaHelpTopicIdForRoute('GrossanlassPlanungTransporte')).toBe(GA_HELP_FALLBACK_TOPIC)
    expect(gaHelpTopicIdForRoute('GrossanlassLogistik')).toBe(GA_HELP_FALLBACK_TOPIC)
  })

  it('leaves department pages and the GA help page itself alone', () => {
    for (const name of ['Activities', 'ActivityDetail', 'HelpTours', 'HelpDokumentation', 'DepartmentVerwaltungPermissions', GA_HELP_PAGE_ROUTE]) {
      expect(gaHelpTopicIdForRoute(name), name).toBeNull()
    }
    expect(gaHelpTopicIdForRoute(undefined)).toBeNull()
    expect(gaHelpTopicIdForRoute(Symbol('x'))).toBeNull()
  })
})

describe('GA help texts', () => {
  it.each(LOCALES)('are present in %s (own text or fallback)', (locale) => {
    i18n.global.locale.value = locale as never
    for (const key of keysOf()) {
      expect(i18n.global.t(key), `${locale}: ${key}`).not.toBe(key)
    }
    i18n.global.locale.value = 'de'
  })

  it('have German source and English text for every key', () => {
    for (const locale of ['de', 'en']) {
      for (const key of keysOf()) {
        expect(i18n.global.te(key, locale as never), `${locale}: ${key}`).toBe(true)
      }
    }
  })

  it('let an organisation variant override a single key and fall back for the rest', () => {
    i18n.global.mergeLocaleMessage('de-pfadi', { gaHelp: { modal: { close: 'Zu' } } })
    i18n.global.locale.value = 'de-pfadi' as never
    expect(i18n.global.t('gaHelp.modal.close')).toBe('Zu')
    expect(i18n.global.t('gaHelp.modal.more')).toBe(i18n.global.t('gaHelp.modal.more', {}, { locale: 'de' }))
    i18n.global.locale.value = 'de'
    i18n.global.mergeLocaleMessage('de-pfadi', { gaHelp: { modal: { close: undefined } } })
  })
})
