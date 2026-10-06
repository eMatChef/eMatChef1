// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp } from 'vue'
import { createI18n } from 'vue-i18n'
import DevEnvironmentBanner from './DevEnvironmentBanner.vue'

describe('DevEnvironmentBanner', () => {
  afterEach(() => {
    vi.unstubAllEnvs()
  })

  it('shows the environment hint but no demo logins or credentials', () => {
    vi.stubEnv('VITE_SHOW_DEV_BANNER', '1')
    const el = document.createElement('div')
    const i18n = createI18n({ legacy: false, locale: 'de', messages: { de: { app: { devEnvironmentBanner: 'Testumgebung' } } } })
    createApp(DevEnvironmentBanner).use(i18n).mount(el)

    expect(el.textContent).toContain('Testumgebung')
    expect(el.querySelector('button')).toBeNull()
    expect(el.textContent).not.toContain('test!ematchef')
    expect(el.textContent).not.toMatch(/@(demo\.)?ematchef\.ch/)
  })
})
