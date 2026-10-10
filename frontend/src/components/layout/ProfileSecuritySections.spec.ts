// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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
  getExternalIdentities: vi.fn(),
  disconnectExternalIdentity: vi.fn(),
  startExternalIdentityLink: vi.fn(),
}))
const confirmAnswer = vi.hoisted(() => ({ value: true }))
vi.mock('@/api/profileSecurity', () => api)
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ profileId: 'p1', profile: { id: 'p1' } }) }))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))
vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm: async () => confirmAnswer.value }) }))
vi.mock('@/components/form/base', () => ({
  EDialog: {
    props: ['modelValue', 'title'],
    emits: ['update:modelValue'],
    template: '<div v-if="modelValue" data-testid="dialog" role="dialog"><h2>{{ title }}</h2><slot /><slot name="actions" /></div>',
  },
  ESelect: {
    props: ['modelValue', 'items', 'label'],
    emits: ['update:modelValue'],
    template:
      '<select data-testid="filter-action" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="i in items" :key="i.value" :value="i.value">{{ i.title }}</option></select>',
  },
  ETextField: {
    props: ['modelValue', 'type', 'label'],
    emits: ['update:modelValue'],
    template: '<input :type="type" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
  },
  EButton: {
    inheritAttrs: false,
    props: ['loading', 'disabled', 'variant', 'size'],
    template: '<button v-bind="$attrs" :disabled="disabled || undefined"><slot /></button>',
  },
}))

import SessionsSection from './ProfileSecuritySessionsSection.vue'
import ActivitySection from './ProfileSecurityActivitySection.vue'
import ExternalIdentitiesSection from './ProfileSecurityExternalIdentitiesSection.vue'

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

const mountedApps: ReturnType<typeof createApp>[] = []
afterEach(() => mountedApps.splice(0).forEach((app) => app.unmount()))

async function mount(component: Component, locale = 'de') {
  const el = document.createElement('div')
  const i18n = createI18n({ legacy: false, locale, fallbackLocale: 'de', messages: { de, en } as never })
  const app = createApp(component, { open: true }).use(i18n)
  mountedApps.push(app)
  app.mount(el)
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

const activityEvent = (over: Record<string, unknown>) => ({
  id: 'ae1',
  action: 'login_success',
  created_at: '2026-10-06T10:00:00+00:00',
  detail: null,
  auth_method: null,
  mfa_source: null,
  browser: null,
  os: null,
  ip_address: null,
  changed_fields: [],
  actor: { type: 'self', name: null },
  ...over,
})

describe('Profile security: activity', () => {
  beforeEach(() => api.getSecurityActivity.mockReset())

  it('requests 20 events and shows translated labels', async () => {
    api.getSecurityActivity.mockResolvedValue({
      events: [
        activityEvent({ id: 'a', action: 'login_success', auth_method: 'google' }),
        activityEvent({ id: 'b', action: 'totp_enabled' }),
        activityEvent({ id: 'c', action: 'unknown_action' }),
        activityEvent({ id: 'd', action: 'external_identity_linked', detail: 'midata' }),
      ],
      next_cursor: null,
    })

    const el = await mount(ActivitySection)

    expect(api.getSecurityActivity).toHaveBeenCalledWith('p1', 20, null)
    const items = [...el.querySelectorAll('[data-testid="events"] li')].map((li) => li.textContent ?? '')
    expect(items[0]).toContain('Anmeldung')
    expect(items[0]).toContain('Anmeldung via Google')
    expect(items[1]).toContain('Zwei-Faktor-Authentifizierung aktiviert')
    expect(items[2]).toContain('unknown_action')
    expect(items[3]).toContain('Anmeldung verknüpft (MiData)')
    expect(el.querySelector('[data-testid="load-more"]')).toBeNull()
  })

  it('shows MFA source, browser/OS and IP only when available (historic events stay visible)', async () => {
    api.getSecurityActivity.mockResolvedValue({
      events: [
        activityEvent({ id: 'a', auth_method: 'password', mfa_source: 'recovery_code', browser: 'Chrome', os: 'Windows', ip_address: '203.0.113.9' }),
        activityEvent({ id: 'b', auth_method: 'password' }),
      ],
      next_cursor: null,
    })

    const el = await mount(ActivitySection)

    const meta = [...el.querySelectorAll('[data-testid="event-meta"]')].map((m) => m.textContent ?? '')
    expect(meta[0]).toContain('MFA: Recovery Code')
    expect(meta[0]).toContain('Chrome / Windows')
    expect(meta[0]).toContain('IP 203.0.113.9')
    expect(meta[1]).toContain('Anmeldung via Passwort')
    expect(meta[1]).not.toContain('IP')
  })

  it('shows changed profile field names and the acting administrator', async () => {
    api.getSecurityActivity.mockResolvedValue({
      events: [
        activityEvent({
          action: 'profile_updated',
          changed_fields: ['first_name', 'language'],
          actor: { type: 'other', name: 'Anna Admin' },
        }),
      ],
      next_cursor: null,
    })

    const el = await mount(ActivitySection)

    const meta = el.querySelector('[data-testid="event-meta"]')!.textContent ?? ''
    expect(meta).toContain('Geändert: Vorname, Sprache')
    expect(meta).toContain('Ausgelöst von Anna Admin')
  })

  it('loads more with the cursor and hides the button at the end', async () => {
    api.getSecurityActivity
      .mockResolvedValueOnce({ events: [activityEvent({ id: 'a' })], next_cursor: 'CUR1' })
      .mockResolvedValueOnce({ events: [activityEvent({ id: 'b', action: 'totp_enabled' })], next_cursor: null })
    const el = await mount(ActivitySection)
    expect(el.querySelectorAll('[data-testid="events"] li')).toHaveLength(1)

    el.querySelector<HTMLButtonElement>('[data-testid="load-more"]')!.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(api.getSecurityActivity).toHaveBeenLastCalledWith('p1', 20, 'CUR1')
    expect(el.querySelectorAll('[data-testid="events"] li')).toHaveLength(2)
    expect(el.querySelector('[data-testid="load-more"]')).toBeNull()
  })

  it('shows an empty state', async () => {
    api.getSecurityActivity.mockResolvedValue({ events: [], next_cursor: null })
    const el = await mount(ActivitySection)
    expect(el.querySelector('[data-testid="no-events"]')).not.toBeNull()
  })
})

describe('Profile security: compact list and full log modal', () => {
  beforeEach(() => api.getSecurityActivity.mockReset())

  const many = (n: number, prefix = 'e') =>
    Array.from({ length: n }, (_, i) => activityEvent({ id: `${prefix}${i}`, action: 'totp_enabled' }))

  it('keeps the list to five visible rows with internal scrolling', async () => {
    api.getSecurityActivity.mockResolvedValue({ events: many(20), next_cursor: 'C1' })

    const el = await mount(ActivitySection)

    const list = el.querySelector('[data-testid="events"]')!
    // 5 Zeilen × 3rem + 4 Lücken × 0.25rem = 16rem = max-h-64; Rest scrollt in der Liste
    expect(list.className).toContain('max-h-64')
    expect(list.className).toContain('overflow-y-auto')
    expect(list.querySelectorAll('li')).toHaveLength(20)
    for (const li of list.querySelectorAll('li')) expect(li.className).toContain('h-12')
  })

  it('loads the next page by cursor when the list is scrolled to its end', async () => {
    api.getSecurityActivity
      .mockResolvedValueOnce({ events: many(20), next_cursor: 'C1' })
      .mockResolvedValueOnce({ events: many(5, 'x'), next_cursor: null })
    const el = await mount(ActivitySection)
    const list = el.querySelector<HTMLElement>('[data-testid="events"]')!
    Object.defineProperty(list, 'scrollHeight', { value: 1000 })
    Object.defineProperty(list, 'clientHeight', { value: 256 })
    list.scrollTop = 760

    list.dispatchEvent(new Event('scroll'))
    await new Promise((r) => setTimeout(r, 0))

    expect(api.getSecurityActivity).toHaveBeenLastCalledWith('p1', 20, 'C1')
    expect(el.querySelectorAll('[data-testid="events"] li')).toHaveLength(25)
  })

  it('opens the full log with 40 entries per page, cursor paging and filters', async () => {
    api.getSecurityActivity.mockResolvedValue({ events: [], next_cursor: null })
    const el = await mount(ActivitySection)
    expect(el.querySelector('[data-testid="log-modal"]')).toBeNull()

    api.getSecurityActivity.mockReset()
    api.getSecurityActivity
      .mockResolvedValueOnce({ events: many(40, 'a'), next_cursor: 'M1', actions: ['login_success', 'totp_enabled'] })
      .mockResolvedValueOnce({ events: many(3, 'b'), next_cursor: null, actions: ['login_success', 'totp_enabled'] })
      .mockResolvedValue({ events: many(1, 'f'), next_cursor: null })
    el.querySelector<HTMLButtonElement>('[data-testid="open-full-log"]')!.click()
    await new Promise((r) => setTimeout(r, 0))
    await nextTick()

    expect(api.getSecurityActivity).toHaveBeenNthCalledWith(1, 'p1', 40, null, {})
    expect(el.querySelectorAll('[data-testid="log-events"] > li')).toHaveLength(40)

    el.querySelector<HTMLButtonElement>('[data-testid="log-load-more"]')!.click()
    await new Promise((r) => setTimeout(r, 0))
    expect(api.getSecurityActivity).toHaveBeenNthCalledWith(2, 'p1', 40, 'M1', {})
    expect(el.querySelectorAll('[data-testid="log-events"] > li')).toHaveLength(43)
    expect(el.querySelector('[data-testid="log-load-more"]')).toBeNull()

    const select = el.querySelector<HTMLSelectElement>('[data-testid="filter-action"]')!
    select.value = 'login_success'
    select.dispatchEvent(new Event('change'))
    const dates = el.querySelectorAll<HTMLInputElement>('[data-testid="log-filters"] input[type="date"]')
    dates[0]!.value = '2026-10-01'
    dates[0]!.dispatchEvent(new Event('input'))
    dates[1]!.value = '2026-10-09'
    dates[1]!.dispatchEvent(new Event('input'))
    await nextTick()
    // Das Element ist nicht im Dokument (jsdom löst Submit-Klicks nur dort aus): Submit direkt auslösen.
    el.querySelector('[data-testid="log-filters"]')!.dispatchEvent(new Event('submit', { cancelable: true }))
    await new Promise((r) => setTimeout(r, 0))

    expect(api.getSecurityActivity).toHaveBeenLastCalledWith('p1', 40, null, { action: 'login_success', from: '2026-10-01', to: '2026-10-09' })
    expect(el.querySelectorAll('[data-testid="log-events"] > li')).toHaveLength(1)
  })

  it('shows IP, device, login method, MFA source, actor and changed field names in the log', async () => {
    api.getSecurityActivity.mockResolvedValue({ events: [], next_cursor: null })
    const el = await mount(ActivitySection)
    api.getSecurityActivity.mockResolvedValue({
      events: [
        activityEvent({ id: 'a', auth_method: 'midata', mfa_source: 'totp', browser: 'Firefox', os: 'Linux', ip_address: '203.0.113.9' }),
        activityEvent({ id: 'b', action: 'profile_updated', changed_fields: ['nickname'], actor: { type: 'other', name: 'Anna Admin' } }),
      ],
      next_cursor: null,
      actions: [],
    })

    el.querySelector<HTMLButtonElement>('[data-testid="open-full-log"]')!.click()
    await new Promise((r) => setTimeout(r, 0))
    await nextTick()

    const text = el.querySelector('[data-testid="log-events"]')!.textContent ?? ''
    for (const part of ['Anmeldung via MiData', 'MFA: Authenticator-Code', 'Firefox / Linux', 'IP 203.0.113.9', 'Geändert: Pfadiname', 'Ausgelöst von Anna Admin']) {
      expect(text).toContain(part)
    }
  })

  it('closes the log modal with the close button', async () => {
    api.getSecurityActivity.mockResolvedValue({ events: [], next_cursor: null })
    const el = await mount(ActivitySection)
    el.querySelector<HTMLButtonElement>('[data-testid="open-full-log"]')!.click()
    await nextTick()
    expect(el.querySelector('[data-testid="dialog"]')).not.toBeNull()

    el.querySelector<HTMLButtonElement>('[data-testid="log-close"]')!.click()
    await nextTick()

    expect(el.querySelector('[data-testid="dialog"]')).toBeNull()
  })
})

describe('Profile security: linked sign-ins', () => {
  const identity = (over: Record<string, unknown>) => ({
    id: 'idn1',
    provider: 'midata',
    label: 'MiData / db.scout.ch',
    display_name: 'Anna Muster',
    email: 'anna@midata.test',
    external_id_hint: '…a1b2',
    linked_at: '2026-10-01T10:00:00+00:00',
    can_disconnect: true,
    ...over,
  })
  const providers = [
    { provider: 'google', label: 'Google', configured: true },
    { provider: 'midata', label: 'MiData / db.scout.ch', configured: true },
  ]

  beforeEach(() => {
    api.getExternalIdentities.mockReset()
    api.disconnectExternalIdentity.mockReset()
    api.startExternalIdentityLink.mockReset()
  })

  it('shows only actually linked identities, no permanent "not connected" rows', async () => {
    api.getExternalIdentities.mockResolvedValue({ identities: [identity({ id: 'g1', provider: 'google', label: 'Google' })], providers })

    const el = await mount(ExternalIdentitiesSection)

    expect(el.querySelectorAll('[data-testid="identities"] li')).toHaveLength(1)
    expect(el.textContent).toContain('Google')
    expect(el.textContent).toContain('Anna Muster')
    expect(el.textContent).toContain('anna@midata.test')
    expect(el.textContent).toContain('Konto …a1b2')
    expect(el.textContent).not.toContain('Nicht verbunden')
    expect(el.querySelector('[data-testid="identity-midata"]')).toBeNull()
  })

  it('shows a hint and the connect button for an empty list', async () => {
    api.getExternalIdentities.mockResolvedValue({ identities: [], providers })

    const el = await mount(ExternalIdentitiesSection)

    expect(el.querySelector('[data-testid="no-identities"]')).not.toBeNull()
    expect(el.querySelector('[data-testid="connect-toggle"]')!.textContent).toContain('Konto verbinden mit')
    expect(el.querySelector('[data-testid="identities"]')).toBeNull()
  })

  it('lists several connections of one provider individually and disconnects exactly the chosen one', async () => {
    const both = [
      identity({ id: 'g1', provider: 'google', label: 'Google', email: 'a@gmail.test' }),
      identity({ id: 'g2', provider: 'google', label: 'Google', email: 'b@gmail.test', external_id_hint: '…c3d4' }),
    ]
    api.getExternalIdentities.mockResolvedValue({ identities: both, providers })
    api.disconnectExternalIdentity.mockResolvedValue({ identities: [both[1]], providers })
    const el = await mount(ExternalIdentitiesSection)
    expect(el.querySelectorAll('[data-testid="identities"] li')).toHaveLength(2)

    el.querySelector<HTMLButtonElement>('[data-testid="identity-g1"] [data-testid="disconnect"]')!.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(api.disconnectExternalIdentity).toHaveBeenCalledWith('p1', 'g1')
    expect(el.querySelectorAll('[data-testid="identities"] li')).toHaveLength(1)
    expect(el.textContent).toContain('b@gmail.test')
    expect(el.textContent).not.toContain('a@gmail.test')
  })

  it('does not disconnect without confirmation', async () => {
    confirmAnswer.value = false
    api.getExternalIdentities.mockResolvedValue({ identities: [identity({})], providers })
    const el = await mount(ExternalIdentitiesSection)

    el.querySelector<HTMLButtonElement>('[data-testid="disconnect"]')!.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(api.disconnectExternalIdentity).not.toHaveBeenCalled()
    confirmAnswer.value = true
  })

  it('protects the last login method in the UI', async () => {
    api.getExternalIdentities.mockResolvedValue({ identities: [identity({ can_disconnect: false })], providers })

    const el = await mount(ExternalIdentitiesSection)

    expect(el.querySelector<HTMLButtonElement>('[data-testid="disconnect"]')!.disabled).toBe(true)
    expect(el.querySelector('[data-testid="last-method"]')).not.toBeNull()
  })

  it('shows backend errors when disconnecting fails (e.g. last login method) and reloads', async () => {
    api.getExternalIdentities.mockResolvedValue({ identities: [identity({})], providers })
    api.disconnectExternalIdentity.mockRejectedValue({ response: { data: { error: 'last_login_method' } } })
    const el = await mount(ExternalIdentitiesSection)

    el.querySelector<HTMLButtonElement>('[data-testid="disconnect"]')!.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(el.querySelector('[data-testid="identities-notice"]')!.textContent).toContain('einzige Anmeldemöglichkeit')
    expect(api.getExternalIdentities).toHaveBeenCalledTimes(2)
  })

  it('offers all supported providers in the connect menu, even when one is already linked', async () => {
    api.getExternalIdentities.mockResolvedValue({
      identities: [identity({ id: 'g1', provider: 'google', label: 'Google' })],
      providers: [...providers, { provider: 'microsoft', label: 'Microsoft', configured: false }],
    })
    const el = await mount(ExternalIdentitiesSection)
    expect(el.querySelector('[data-testid="connect-menu"]')).toBeNull()

    el.querySelector<HTMLButtonElement>('[data-testid="connect-toggle"]')!.click()
    await nextTick()

    expect(el.querySelector('[data-testid="connect-google"]')).not.toBeNull()
    expect(el.querySelector('[data-testid="connect-midata"]')).not.toBeNull()
    expect(el.querySelector<HTMLButtonElement>('[data-testid="connect-microsoft"]')!.disabled).toBe(true)
  })

  it('starts the link flow through the apiClient module and navigates to the provider URL', async () => {
    api.getExternalIdentities.mockResolvedValue({ identities: [], providers })
    api.startExternalIdentityLink.mockResolvedValue('https://accounts.google.com/o/oauth2/v2/auth?x=1')
    const assign = vi.fn()
    vi.stubGlobal('location', { ...window.location, pathname: '/profile', assign })
    const el = await mount(ExternalIdentitiesSection)
    el.querySelector<HTMLButtonElement>('[data-testid="connect-toggle"]')!.click()
    await nextTick()

    el.querySelector<HTMLButtonElement>('[data-testid="connect-google"]')!.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(api.startExternalIdentityLink).toHaveBeenCalledWith('google', '/profile')
    expect(assign).toHaveBeenCalledWith('https://accounts.google.com/o/oauth2/v2/auth?x=1')
    vi.unstubAllGlobals()
  })

  it('shows an error and does not navigate when the link start is refused (e.g. re-login needed)', async () => {
    api.getExternalIdentities.mockResolvedValue({ identities: [], providers })
    api.startExternalIdentityLink.mockRejectedValue({ response: { data: { error: 'reauth_required' } } })
    const assign = vi.fn()
    vi.stubGlobal('location', { ...window.location, pathname: '/profile', assign })
    const el = await mount(ExternalIdentitiesSection)
    el.querySelector<HTMLButtonElement>('[data-testid="connect-toggle"]')!.click()
    await nextTick()

    el.querySelector<HTMLButtonElement>('[data-testid="connect-midata"]')!.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(assign).not.toHaveBeenCalled()
    expect(el.querySelector('[data-testid="identities-notice"]')!.textContent).toContain('neu anmelden')
    vi.unstubAllGlobals()
  })

  it('reports the result of the return from the provider and refreshes without reloading the profile', async () => {
    api.getExternalIdentities.mockResolvedValue({ identities: [], providers })
    const el = await mount(ExternalIdentitiesSection)
    api.getExternalIdentities.mockResolvedValue({ identities: [identity({ id: 'g1', provider: 'google', label: 'Google' })], providers })

    window.dispatchEvent(new CustomEvent('emc-profile-security-link-result', { detail: { status: 'error', reason: 'link_conflict' } }))
    await new Promise((r) => setTimeout(r, 0))

    expect(el.querySelector('[data-testid="identities-notice"]')!.textContent).toContain('bereits mit einem anderen eMatChef-Konto')
    expect(api.getExternalIdentities).toHaveBeenCalledTimes(2)
    expect(el.querySelectorAll('[data-testid="identities"] li')).toHaveLength(1)
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
