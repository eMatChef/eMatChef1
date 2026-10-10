// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick, reactive } from 'vue'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import en from '@/locales/en.json'

const mocks = vi.hoisted(() => ({
  takeExternalIdentityLinkResult: vi.fn(),
  updateProfile: vi.fn(),
  changePassword: vi.fn(),
  getAddresses: vi.fn(),
  toastSuccess: vi.fn(),
  toastError: vi.fn(),
  replace: vi.fn(),
}))
const route = vi.hoisted(() => ({ value: { path: '/profile/identities', query: {} as Record<string, unknown>, hash: '' } }))
const auth = vi.hoisted(() => ({
  value: {} as {
    profileId: string
    profile: Record<string, unknown> | null
    activeDepartmentId: string | null
  },
}))

vi.mock('vue-router', () => ({
  useRoute: () => route.value,
  useRouter: () => ({ replace: mocks.replace }),
}))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => auth.value }))
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: mocks.toastSuccess, error: mocks.toastError, info: vi.fn() }),
}))
vi.mock('@/api/profileSecurity', () => ({ takeExternalIdentityLinkResult: mocks.takeExternalIdentityLinkResult }))
vi.mock('@/api/auth', () => ({
  updateProfile: mocks.updateProfile,
  changePassword: mocks.changePassword,
  login: vi.fn(),
}))
vi.mock('@/api/addresses', () => ({
  getAddresses: mocks.getAddresses,
  createAddress: vi.fn(),
  updateAddress: vi.fn(),
  SWISS_CANTONS: { BE: 'Bern' },
}))
vi.mock('@/components/layout/ProfileSecurityExternalIdentitiesSection.vue', () => ({ default: { template: '<div />' } }))

import ProfileIdentitiesView from './ProfileIdentitiesView.vue'
import { useProfileForm } from '@/composables/useProfileForm'

const apps: ReturnType<typeof createApp>[] = []
afterEach(() => apps.splice(0).forEach((a) => a.unmount()))

function i18n() {
  return createI18n({ legacy: false, locale: 'de', fallbackLocale: 'de', messages: { de, en } as never })
}

async function mountView() {
  const app = createApp(ProfileIdentitiesView).use(i18n())
  apps.push(app)
  app.mount(document.createElement('div'))
  await new Promise((r) => setTimeout(r, 0))
  await nextTick()
}

function withForm<T>(fn: (form: ReturnType<typeof useProfileForm>) => Promise<T> | T): Promise<T> {
  let result!: Promise<T>
  const app = createApp({
    setup() {
      result = Promise.resolve(fn(useProfileForm()))
      return () => null
    },
  }).use(i18n())
  apps.push(app)
  app.mount(document.createElement('div'))
  return result
}

beforeEach(() => {
  Object.values(mocks).forEach((f) => f.mockReset())
  route.value = { path: '/profile/identities', query: {}, hash: '' }
  auth.value = reactive({
    profileId: 'p1',
    profile: {
      id: 'p1',
      email: 'main@example.ch',
      first_name: 'Ana',
      last_name: 'Muster',
      nickname: '',
      language: 'de',
      background_color: '#EC4899',
      text_color: '#FFFFFF',
    },
    activeDepartmentId: null,
  })
  sessionStorage.clear()
})

describe('profile identities: OAuth link return', () => {
  it('fetches the server-side result once, reports it and removes the hint parameters', async () => {
    route.value.query = { profile_security: '1', oauth: 'linked', provider: 'google', reason: 'x', from: '/dept' }
    mocks.takeExternalIdentityLinkResult.mockResolvedValue({ status: 'linked', provider: 'google', reason: null })
    const received = vi.fn()
    window.addEventListener('emc-profile-security-link-result', received)

    await mountView()

    expect(mocks.replace).toHaveBeenCalledWith({ path: '/profile/identities', query: { from: '/dept' }, hash: '' })
    expect(mocks.takeExternalIdentityLinkResult).toHaveBeenCalledTimes(1)
    expect(mocks.takeExternalIdentityLinkResult).toHaveBeenCalledWith('p1')
    expect((received.mock.calls[0]![0] as CustomEvent).detail).toEqual({ status: 'linked', reason: null, provider: 'google' })
    expect(mocks.toastSuccess).toHaveBeenCalledTimes(1)
    window.removeEventListener('emc-profile-security-link-result', received)
  })

  it('restores the parked return path when the provider redirect dropped it', async () => {
    sessionStorage.setItem('emc-profile-from', '/dept/ga/planning')
    route.value.query = { profile_security: '1' }
    mocks.takeExternalIdentityLinkResult.mockResolvedValue(null)

    await mountView()

    expect(mocks.replace).toHaveBeenCalledWith({
      path: '/profile/identities',
      query: { from: '/dept/ga/planning' },
      hash: '',
    })
    expect(sessionStorage.getItem('emc-profile-from')).toBeNull()
  })

  it('does nothing visible when the server has no result (prepared URL or reload)', async () => {
    route.value.query = { profile_security: '1', oauth: 'linked' }
    mocks.takeExternalIdentityLinkResult.mockResolvedValue(null)
    const received = vi.fn()
    window.addEventListener('emc-profile-security-link-result', received)

    await mountView()

    expect(received).not.toHaveBeenCalled()
    expect(mocks.toastSuccess).not.toHaveBeenCalled()
    window.removeEventListener('emc-profile-security-link-result', received)
  })

  it('never asks the server without the return hint', async () => {
    route.value.query = { oauth: 'linked', provider: 'google' }
    await mountView()
    expect(mocks.takeExternalIdentityLinkResult).not.toHaveBeenCalled()
    expect(mocks.replace).not.toHaveBeenCalled()
  })
})

describe('profile form', () => {
  it('detects unsaved changes and validates the password inline', async () => {
    await withForm(async (form) => {
      await form.load()
      expect(form.hasUnsavedChanges.value).toBe(false)

      form.profileForm.value.nickname = 'Anni'
      expect(form.hasUnsavedProfileChanges.value).toBe(true)

      form.passwordForm.value.new_password = 'short'
      expect(form.passwordInlineError.value).not.toBe('')
      form.passwordForm.value = { current_password: 'old-pass-1', new_password: 'longenough1', confirm_new_password: 'longenough1' }
      expect(form.passwordInlineError.value).toBe('')
      expect(form.passwordInlineSuccess.value).toBe(true)
    })
  })

  it('saves with the existing primary email and the chosen language', async () => {
    mocks.updateProfile.mockResolvedValue({ id: 'p1', email: 'main@example.ch' })
    await withForm(async (form) => {
      await form.load()
      form.profileForm.value.language = 'en'
      form.profileForm.value.first_name = 'Anna'

      expect(await form.save()).toBe('saved')
      expect(mocks.updateProfile).toHaveBeenCalledWith(
        'p1',
        expect.objectContaining({ email: 'main@example.ch', first_name: 'Anna', language: 'en' }),
      )
      expect(form.hasUnsavedChanges.value).toBe(false)
    })
  })

  it('reports unchanged forms without calling the API', async () => {
    await withForm(async (form) => {
      await form.load()
      expect(await form.save()).toBe('unchanged')
      expect(mocks.updateProfile).not.toHaveBeenCalled()
    })
  })

  it('works without an active department: the address is unavailable but the profile loads and saves', async () => {
    mocks.updateProfile.mockResolvedValue({ id: 'p1' })
    await withForm(async (form) => {
      await form.load()
      expect(form.addressAvailable.value).toBe(false)
      expect(mocks.getAddresses).not.toHaveBeenCalled()
      form.profileForm.value.nickname = 'Anni'
      expect(await form.save()).toBe('saved')
    })
  })
})
