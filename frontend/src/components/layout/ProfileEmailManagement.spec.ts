// @vitest-environment jsdom
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, type Component } from 'vue'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'

const api = vi.hoisted(() => ({
  getProfileEmails: vi.fn(),
  addProfileEmail: vi.fn(),
  makeProfileEmailPrimary: vi.fn(),
  removeProfileEmail: vi.fn(),
  resendProfileEmailVerification: vi.fn(),
}))
const notif = vi.hoisted(() => ({ getDepartmentNotificationEmail: vi.fn(), setDepartmentNotificationEmail: vi.fn() }))
const authStore = vi.hoisted(() => ({
  profileId: 'p1',
  profile: { id: 'p1', email: 'alt@example.ch' } as { id: string; email: string },
  loadUserSessionFromCookie: vi.fn(),
}))
vi.mock('@/api/profileEmails', () => api)
vi.mock('@/api/departmentNotificationEmail', () => notif)
vi.mock('@/stores/auth', () => ({ useAuthStore: () => authStore }))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))
vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm: async () => true }) }))
vi.mock('@/components/layout/ProfileSecurityExternalIdentitiesSection.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/components/layout/ProfileSecurityTotpSection.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/components/layout/ProfileSecuritySessionsSection.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/components/layout/ProfileSecurityActivitySection.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/components/form/base', () => ({
  EButton: {
    inheritAttrs: false,
    props: ['loading', 'disabled', 'variant', 'size'],
    template: '<button v-bind="$attrs" :disabled="disabled || undefined"><slot /></button>',
  },
  ETextField: { props: ['modelValue', 'label'], template: '<div class="etext" />' },
  ESelect: { props: ['modelValue', 'items', 'label'], template: '<div class="eselect" />' },
}))

import EmailsAccordion from './ProfileSecurityEmailsAccordion.vue'
import NotificationEmailPanel from '@/components/settings/DepartmentNotificationEmailPanel.vue'

const emails = (primary: string) => ({
  primary: { email: primary, verified: true },
  emails: [{ id: 'a1', email: 'neu@example.ch', verified: true, verified_at: null, login_enabled: true, verification_expires_at: null }],
})

async function mount(component: Component, props: Record<string, unknown> = { open: true }) {
  const el = document.createElement('div')
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } as never })
  createApp(component, props).use(i18n).mount(el)
  await new Promise((r) => setTimeout(r, 0))
  await nextTick()
  return el
}

describe('E-Mail-Verwaltung im Profil', () => {
  beforeEach(() => {
    Object.values(api).forEach((f) => f.mockReset())
    authStore.loadUserSessionFromCookie.mockReset()
  })

  it('zeigt die Hauptadresse (Profile.email) und die zusätzlichen Login-Adressen aus der bestehenden Verwaltung', async () => {
    api.getProfileEmails.mockResolvedValue(emails('alt@example.ch'))

    const el = await mount(EmailsAccordion)

    expect(api.getProfileEmails).toHaveBeenCalledWith('p1')
    expect(el.textContent).toContain('alt@example.ch')
    expect(el.textContent).toContain('neu@example.ch')
    expect(el.querySelector('#profile-email-management')).not.toBeNull()
  })

  it('gibt dem Hinzufügen-Feld die volle Breite (Grid statt schmalem Flex-Item)', async () => {
    api.getProfileEmails.mockResolvedValue(emails('alt@example.ch'))

    const el = await mount(EmailsAccordion)

    const form = el.querySelector('[data-testid="add-email-form"]')!
    expect(form.className).toContain('grid')
    expect(form.className).toContain('sm:grid-cols-[minmax(0,1fr)_auto]')
    expect(form.querySelector('.etext')!.className).toContain('w-full')
    expect(form.querySelector('.etext')!.className).toContain('min-w-0')
  })

  it('meldet eine geänderte Hauptadresse (oberes Feld) und lässt abhängige Anzeigen neu laden', async () => {
    api.getProfileEmails.mockResolvedValue(emails('alt@example.ch'))
    api.makeProfileEmailPrimary.mockResolvedValue(emails('neu@example.ch'))
    const changed = vi.fn()
    window.addEventListener('emc-profile-emails-changed', changed)
    const primaryChanged = vi.fn()
    const el = await mount(EmailsAccordion, { open: true, onPrimaryChanged: primaryChanged })

    const makePrimary = [...el.querySelectorAll('button')].find((b) => b.textContent?.includes('Hauptadresse'))!
    makePrimary.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(api.makeProfileEmailPrimary).toHaveBeenCalledWith('p1', 'a1')
    expect(authStore.loadUserSessionFromCookie).toHaveBeenCalledWith(true)
    expect(primaryChanged).toHaveBeenCalledTimes(1)
    expect(changed).toHaveBeenCalledTimes(1)
    window.removeEventListener('emc-profile-emails-changed', changed)
  })

  it('lädt die Benachrichtigungsadresse je Department nach Adressänderungen neu (eigenes Modell, nicht vermischt)', async () => {
    notif.getDepartmentNotificationEmail.mockResolvedValue({
      effective_email: 'alt@example.ch',
      primary_email: 'alt@example.ch',
      selected_email: null,
      options: ['alt@example.ch'],
    })
    await mount(NotificationEmailPanel, { departmentId: 'd1' })
    expect(notif.getDepartmentNotificationEmail).toHaveBeenCalledTimes(1)

    window.dispatchEvent(new CustomEvent('emc-profile-emails-changed'))
    await new Promise((r) => setTimeout(r, 0))

    expect(notif.getDepartmentNotificationEmail).toHaveBeenCalledTimes(2)
    expect(notif.getDepartmentNotificationEmail).toHaveBeenLastCalledWith('d1')
  })

  it('bietet im Profil keinen zweiten Bearbeitungsweg: oberes Feld ist schreibgeschützt, der Stift führt zur Verwaltung', () => {
    const source = readFileSync(resolve(__dirname, 'TopHeader.vue'), 'utf8')

    expect(source).not.toContain('toggleEmailEdit')
    expect(source).not.toContain('isEmailEditEnabled')
    expect(source).toMatch(/v-model="profileForm\.email"[\s\S]{0,200}\bdisabled\b/)
    expect(source).toContain('@click="openEmailManagement"')
    expect(source).toContain("getElementById('profile-email-management')")
    // Gespeichert wird immer die bestehende Hauptadresse; Änderungen laufen über /emails
    expect(source).toContain('email: authStore.profile?.email || email')
  })
})
