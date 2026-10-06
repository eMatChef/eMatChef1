// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, type Component } from 'vue'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import en from '@/locales/en.json'

const api = vi.hoisted(() => ({
  getSessions: vi.fn(),
  revokeSession: vi.fn(),
  revokeOtherSessions: vi.fn(),
  getTrustedDevices: vi.fn(),
  revokeTrustedDevice: vi.fn(),
  getSecurityActivity: vi.fn(),
}))
vi.mock('@/api/profileSecurity', () => api)
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ profileId: 'p1', profile: { id: 'p1' } }) }))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))
vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm: async () => true }) }))
vi.mock('@/components/form/base', () => ({
  EButton: {
    inheritAttrs: false,
    props: ['loading', 'disabled', 'variant', 'size'],
    template: '<button v-bind="$attrs" :disabled="disabled || undefined"><slot /></button>',
  },
}))

import SessionsSection from './ProfileSecuritySessionsSection.vue'
import ActivitySection from './ProfileSecurityActivitySection.vue'

const session = (over: Record<string, unknown>) => ({
  id: 's1',
  current: false,
  auth_method: 'password',
  browser: 'Chrome',
  os: 'Windows',
  label: 'Chrome / Windows',
  created_at: '2026-10-01T10:00:00+00:00',
  last_seen_at: '2026-10-06T10:00:00+00:00',
  mfa_verified: true,
  mfa_source: 'totp',
  trusted: false,
  ...over,
})

async function mount(component: Component, locale = 'de') {
  const el = document.createElement('div')
  const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'de', messages: { de, en } as never })
  createApp(component, { open: true }).use(i18n).mount(el)
  await new Promise((r) => setTimeout(r, 0))
  await nextTick()
  return el
}

describe('Profile security: sessions and trusted devices', () => {
  beforeEach(() => {
    Object.values(api).forEach((f) => f.mockReset())
    api.getTrustedDevices.mockResolvedValue([])
  })

  it('loads and shows sessions with the current one marked and not signable-out', async () => {
    api.getSessions.mockResolvedValue([
      session({ id: 's1', current: true, auth_method: 'google' }),
      session({ id: 's2', label: 'Safari / iOS', mfa_verified: false, mfa_source: null }),
    ])

    const el = await mount(SessionsSection)

    expect(api.getSessions).toHaveBeenCalledWith('p1')
    expect(el.querySelectorAll('[data-testid="sessions"] li')).toHaveLength(2)
    expect(el.querySelectorAll('[data-testid="current-badge"]')).toHaveLength(1)
    expect(el.querySelectorAll('[data-testid="revoke-session"]')).toHaveLength(1)
    expect(el.textContent).toContain('Google')
    expect(el.textContent).toContain('Ohne MFA')
  })

  it('signs out another session', async () => {
    api.getSessions.mockResolvedValue([session({ id: 's1', current: true }), session({ id: 's2' })])
    api.revokeSession.mockResolvedValue([session({ id: 's1', current: true })])
    const el = await mount(SessionsSection)

    el.querySelector<HTMLButtonElement>('[data-testid="revoke-session"]')!.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(api.revokeSession).toHaveBeenCalledWith('p1', 's2')
    expect(el.querySelectorAll('[data-testid="sessions"] li')).toHaveLength(1)
  })

  it('signs out all others and is disabled without other sessions', async () => {
    api.getSessions.mockResolvedValue([session({ id: 's1', current: true })])
    let el = await mount(SessionsSection)
    expect(el.querySelector<HTMLButtonElement>('[data-testid="revoke-others"]')!.disabled).toBe(true)

    api.getSessions.mockResolvedValue([session({ id: 's1', current: true }), session({ id: 's2' })])
    api.revokeOtherSessions.mockResolvedValue({ revoked: 1, sessions: [session({ id: 's1', current: true })] })
    el = await mount(SessionsSection)
    el.querySelector<HTMLButtonElement>('[data-testid="revoke-others"]')!.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(api.revokeOtherSessions).toHaveBeenCalledWith('p1')
    expect(el.querySelectorAll('[data-testid="sessions"] li')).toHaveLength(1)
  })

  it('shows trust status and revokes trust', async () => {
    api.getSessions.mockResolvedValue([session({ id: 's1', current: true, trusted: true, mfa_source: 'trusted_device' })])
    api.getTrustedDevices.mockResolvedValue([
      { id: 'd1', label: 'Chrome / Windows', trusted_at: '2026-10-01T10:00:00+00:00', last_used_at: null, valid_until: '2026-12-30T10:00:00+00:00', current: true },
    ])
    api.revokeTrustedDevice.mockResolvedValue([])
    const el = await mount(SessionsSection)

    expect(el.textContent).toContain('Vertrauenswürdig')
    expect(el.textContent).toContain('MFA über vertrauenswürdiges Gerät')
    expect(el.querySelectorAll('[data-testid="devices"] li')).toHaveLength(1)

    el.querySelector<HTMLButtonElement>('[data-testid="revoke-device"]')!.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(api.revokeTrustedDevice).toHaveBeenCalledWith('p1', 'd1')
    expect(el.querySelector('[data-testid="no-devices"]')).not.toBeNull()
  })
})

describe('Profile security: activity', () => {
  beforeEach(() => api.getSecurityActivity.mockReset())

  it('requests 20 events and shows translated labels with details', async () => {
    api.getSecurityActivity.mockResolvedValue([
      { action: 'login_success', created_at: '2026-10-06T10:00:00+00:00', detail: 'google' },
      { action: 'totp_enabled', created_at: '2026-10-05T10:00:00+00:00', detail: null },
      { action: 'unknown_action', created_at: '2026-10-04T10:00:00+00:00', detail: null },
    ])

    const el = await mount(ActivitySection)

    expect(api.getSecurityActivity).toHaveBeenCalledWith('p1', 20)
    const items = [...el.querySelectorAll('[data-testid="events"] li')].map((li) => li.textContent ?? '')
    expect(items[0]).toContain('Anmeldung (google)')
    expect(items[1]).toContain('Zwei-Faktor-Authentifizierung aktiviert')
    expect(items[2]).toContain('unknown_action')
  })

  it('shows an empty state', async () => {
    api.getSecurityActivity.mockResolvedValue([])
    const el = await mount(ActivitySection)
    expect(el.querySelector('[data-testid="no-events"]')).not.toBeNull()
  })
})

describe('Login MFA trust hint', () => {
  type Translate = (key: string, params?: Record<string, unknown>) => string
  const translator = (messages: unknown): Translate => {
    const i18n = createI18n({ legacy: false, locale: 'x', messages: { x: messages } as never })
    return (i18n.global as unknown as { t: Translate }).t
  }

  it('states 90 days for normal users and 30 for admins, without promising "never again"', () => {
    const tDe = translator(de)
    expect(tDe('login.mfa.trustHint', { days: 90 })).toContain('90 Tage')
    expect(tDe('login.mfa.trustHint', { days: 30 })).toContain('30 Tage')
    expect(translator(en)('login.mfa.trustHint', { days: 30 })).toContain('30 days')
    expect(tDe('login.mfa.trustNote')).toContain('sicherheitskritische')
  })
})
