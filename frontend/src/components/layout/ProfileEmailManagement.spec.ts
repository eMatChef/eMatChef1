// @vitest-environment jsdom
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, type Component } from 'vue'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'

const api = vi.hoisted(() => ({
  getProfileEmails: vi.fn(),
  addProfileEmail: vi.fn(),
  makeProfileEmailPrimary: vi.fn(),
  removeProfileEmail: vi.fn(),
  resendProfileEmailVerification: vi.fn(),
  getDepartmentEmailAssignments: vi.fn(),
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
vi.mock('@/components/form/base', () => ({
  EButton: {
    inheritAttrs: false,
    props: ['loading', 'disabled', 'variant', 'size'],
    template: '<button v-bind="$attrs" :disabled="disabled || undefined"><slot /></button>',
  },
  ETextField: { props: ['modelValue', 'label'], template: '<div class="etext" />' },
  ESelect: {
    props: ['modelValue', 'items', 'label', 'disabled'],
    emits: ['update:modelValue'],
    template:
      '<select class="eselect" :value="modelValue" :disabled="disabled || undefined" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="i in items" :key="i.value" :value="i.value">{{ i.title }}</option></select>',
  },
}))

import EmailsAccordion from './ProfileEmailsSection.vue'
import NotificationEmailPanel from '@/components/settings/DepartmentNotificationEmailPanel.vue'

const emails = (primary: string) => ({
  primary: { email: primary, verified: true },
  emails: [{ id: 'a1', email: 'neu@example.ch', verified: true, verified_at: null, login_enabled: true, verification_expires_at: null }],
})

const mountedApps: ReturnType<typeof createApp>[] = []
afterEach(() => mountedApps.splice(0).forEach((app) => app.unmount()))

async function mount(component: Component, props: Record<string, unknown> = { open: true }) {
  const el = document.createElement('div')
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } as never })
  const app = createApp(component, props).use(i18n)
  mountedApps.push(app)
  app.mount(el)
  await new Promise((r) => setTimeout(r, 0))
  await nextTick()
  return el
}

describe('E-Mail-Verwaltung im Profil', () => {
  beforeEach(() => {
    Object.values(api).forEach((f) => f.mockReset())
    Object.values(notif).forEach((f) => f.mockReset())
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

  const assignment = (id: string, name: string, effective: string, selected: string | null, extra: Record<string, unknown> = {}) => ({
    department_id: id,
    name,
    parent_name: null,
    is_grossanlass: false,
    effective_email: effective,
    selected_email: selected,
    ...extra,
  })
  const assignmentsResponse = () => ({
    assignments: [
      assignment('dA', 'Abteilung A', 'alt@example.ch', null),
      assignment('dB', 'Abteilung B', 'neu@example.ch', 'neu@example.ch', { parent_name: 'Region Nord' }),
      assignment('dC', 'Abteilung C', 'alt@example.ch', null, { is_grossanlass: true }),
    ],
    options: ['alt@example.ch', 'neu@example.ch'],
  })

  it('zeigt je Adresse, für welche Departments sie als Benachrichtigungsadresse verwendet wird', async () => {
    api.getProfileEmails.mockResolvedValue(emails('alt@example.ch'))
    api.getDepartmentEmailAssignments.mockResolvedValue(assignmentsResponse())

    const el = await mount(EmailsAccordion)

    expect(el.querySelector('[data-testid="used-for-primary"]')!.textContent).toContain('Verwendet für: Abteilung A, Abteilung C')
    expect(el.querySelector('[data-testid="used-for-a1"]')!.textContent).toContain('Verwendet für: Abteilung B')
    expect(el.querySelectorAll('[data-testid^="assignment-"]')).toHaveLength(3)
    expect(el.querySelector('[data-testid="assignment-dB"]')!.textContent).toContain('Region Nord')
    expect(el.querySelector('[data-testid="assignment-dC"]')!.textContent).toContain('Grossanlass')
    const options = [...el.querySelectorAll('[data-testid="assignment-dA"] option')].map((o) => o.textContent)
    expect(options).toEqual(['Standard (Hauptadresse: alt@example.ch)', 'neu@example.ch'])
  })

  it('zeigt «nicht ausgewählt», wenn eine Adresse keinem Department dient, und blendet den Bereich ohne Mitgliedschaften aus', async () => {
    api.getProfileEmails.mockResolvedValue(emails('alt@example.ch'))
    api.getDepartmentEmailAssignments.mockResolvedValue({ assignments: [assignment('dA', 'Abteilung A', 'alt@example.ch', null)], options: ['alt@example.ch'] })
    let el = await mount(EmailsAccordion)
    expect(el.querySelector('[data-testid="used-for-a1"]')!.textContent).toContain('Nicht für Departments ausgewählt')

    api.getDepartmentEmailAssignments.mockResolvedValue({ assignments: [], options: [] })
    el = await mount(EmailsAccordion)
    expect(el.querySelector('[data-testid="department-emails"]')).toBeNull()
  })

  it('ändert die Zuordnung über die bestehende Department-API, lädt neu und meldet die Änderung', async () => {
    api.getProfileEmails.mockResolvedValue(emails('alt@example.ch'))
    api.getDepartmentEmailAssignments.mockResolvedValueOnce(assignmentsResponse()).mockResolvedValue({
      ...assignmentsResponse(),
      assignments: [assignment('dA', 'Abteilung A', 'neu@example.ch', 'neu@example.ch'), ...assignmentsResponse().assignments.slice(1)],
    })
    notif.setDepartmentNotificationEmail.mockResolvedValue({})
    const changed = vi.fn()
    window.addEventListener('emc-profile-emails-changed', changed)
    const el = await mount(EmailsAccordion)

    const select = el.querySelector<HTMLSelectElement>('[data-testid="assignment-dA"] select')!
    select.value = 'neu@example.ch'
    select.dispatchEvent(new Event('change'))
    await new Promise((r) => setTimeout(r, 0))

    expect(notif.setDepartmentNotificationEmail).toHaveBeenCalledWith('dA', 'neu@example.ch')
    expect(api.getDepartmentEmailAssignments).toHaveBeenCalledTimes(2)
    expect(el.querySelector('[data-testid="used-for-a1"]')!.textContent).toContain('Abteilung A, Abteilung B')
    expect((changed.mock.calls[0]![0] as CustomEvent).detail).toEqual({ source: 'security' })

    // «Standard» setzt auf die Hauptadresse zurück (null)
    const again = el.querySelector<HTMLSelectElement>('[data-testid="assignment-dA"] select')!
    again.value = '__primary__'
    again.dispatchEvent(new Event('change'))
    await new Promise((r) => setTimeout(r, 0))
    expect(notif.setDepartmentNotificationEmail).toHaveBeenLastCalledWith('dA', null)
    window.removeEventListener('emc-profile-emails-changed', changed)
  })

  it('übernimmt Änderungen aus den Department-Einstellungen sofort, ohne auf die eigenen Ereignisse zu reagieren', async () => {
    api.getProfileEmails.mockResolvedValue(emails('alt@example.ch'))
    api.getDepartmentEmailAssignments.mockResolvedValue(assignmentsResponse())
    await mount(EmailsAccordion)
    expect(api.getDepartmentEmailAssignments).toHaveBeenCalledTimes(1)

    window.dispatchEvent(new CustomEvent('emc-profile-emails-changed', { detail: { source: 'department-panel' } }))
    await new Promise((r) => setTimeout(r, 0))
    expect(api.getDepartmentEmailAssignments).toHaveBeenCalledTimes(2)

    window.dispatchEvent(new CustomEvent('emc-profile-emails-changed', { detail: { source: 'security' } }))
    await new Promise((r) => setTimeout(r, 0))
    expect(api.getDepartmentEmailAssignments).toHaveBeenCalledTimes(2)
  })

  it('die Department-Einstellungen melden eigene Änderungen an Profil → Sicherheit, ohne sich selbst neu zu laden', async () => {
    const data = { effective_email: 'alt@example.ch', primary_email: 'alt@example.ch', selected_email: null, options: ['alt@example.ch', 'neu@example.ch'] }
    notif.getDepartmentNotificationEmail.mockResolvedValue(data)
    notif.setDepartmentNotificationEmail.mockResolvedValue({ ...data, effective_email: 'neu@example.ch', selected_email: 'neu@example.ch' })
    const changed = vi.fn()
    window.addEventListener('emc-profile-emails-changed', changed)
    const el = await mount(NotificationEmailPanel, { departmentId: 'd1' })

    const select = el.querySelector<HTMLSelectElement>('select')!
    select.value = 'neu@example.ch'
    select.dispatchEvent(new Event('change'))
    await new Promise((r) => setTimeout(r, 0))

    expect(notif.setDepartmentNotificationEmail).toHaveBeenCalledWith('d1', 'neu@example.ch')
    expect((changed.mock.calls[0]![0] as CustomEvent).detail).toEqual({ source: 'department-panel' })
    expect(notif.getDepartmentNotificationEmail).toHaveBeenCalledTimes(1)
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

  it('wertet den OAuth-Rückweg auf /profile/identities und abgelehnte MiData-Links in TopHeader aus (Quelltext-Wächter)', () => {
    const view = readFileSync(resolve(__dirname, '../../views/profile/ProfileIdentitiesView.vue'), 'utf8')
    const header = readFileSync(resolve(__dirname, 'TopHeader.vue'), 'utf8')

    expect(view).toContain('handleLinkReturn')
    expect(view).toContain("'emc-profile-security-link-result'")
    // Der Server liefert das Ergebnis einmalig; die URL allein löst nichts aus
    expect(view).toContain('takeExternalIdentityLinkResult(profileId)')
    expect(view).toContain('if (!result) return')
    expect(view.indexOf('if (!result) return')).toBeLessThan(view.indexOf("new CustomEvent('emc-profile-security-link-result'"))
    // Parameter werden nach dem Auswerten aus der Adresse entfernt (kein erneutes Auswerten beim Neuladen)
    expect(view).toContain('router.replace({ path: route.path, query: nextQuery, hash: route.hash })')
    // Abgelehnte MiData-Links (Step-up bzw. neu anmelden) bleiben im Header
    expect(header).toContain("['reauth_required', 'step_up_required', 'mfa_required']")
    expect(header).not.toContain('takeExternalIdentityLinkResult')
  })

  it('bietet im Profil keinen zweiten Bearbeitungsweg: oberes Feld ist schreibgeschützt, der Stift führt zur E-Mail-Seite', () => {
    const source = readFileSync(resolve(__dirname, '../../views/profile/ProfileBasicsView.vue'), 'utf8')

    expect(source).not.toContain('toggleEmailEdit')
    expect(source).not.toContain('isEmailEditEnabled')
    expect(source).toMatch(/v-model="profileForm\.email"[\s\S]{0,200}\bdisabled\b/)
    expect(source).toContain("context.openTab('emails')")
    // Gespeichert wird immer die bestehende Hauptadresse; Änderungen laufen über /profile/emails
    const form = readFileSync(resolve(__dirname, '../../composables/useProfileForm.ts'), 'utf8')
    expect(form).toContain('email: authStore.profile?.email || email')
  })
})
