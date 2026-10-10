// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import en from '@/locales/en.json'

const api = vi.hoisted(() => ({ getDemoResetStatus: vi.fn(), previewDemoReset: vi.fn(), executeDemoReset: vi.fn() }))
vi.mock('@/api/demoReset', () => api)
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ isLoggedIn: true }) }))
vi.mock('vue-router', () => ({ useRoute: () => ({ params: { departmentId: 'dep1' } }) }))
vi.mock('@/components/form/base', () => ({
  EButton: { inheritAttrs: false, props: ['loading', 'disabled', 'variant', 'size'], template: '<button v-bind="$attrs" :disabled="disabled || undefined"><slot /></button>' },
  ECheckbox: { props: ['modelValue', 'label'], emits: ['update:modelValue'], template: '<input type="checkbox" class="confirm" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />' },
  EDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="actions" /></div>' },
}))

import DemoResetControl from './DemoResetControl.vue'

const plan = (blocked: string[] = []) => ({
  message: 'ok',
  plan_hash: 'h1',
  notes: [],
  plan: {
    scenario: 'grossanlass-setup',
    department: { id: 'dep1', name: 'Demo Grossanlass Einrichtung' },
    delete: { groups: [{ id: 'g1', name: 'Zelte' }], group_members: 2, group_shares: 0, places: 0, addresses: [], join_requests: 0 },
    restore: { config: { location_text: { from: 'Wiese', to: '' } }, managed: ['grossanlass-setup:membership:ga-ok (role)'], recreated: 0 },
    keep: { memberships_extra: [] },
    blocked,
  },
})

const apps: ReturnType<typeof createApp>[] = []
afterEach(() => {
  apps.splice(0).forEach((a) => a.unmount())
  document.body.innerHTML = ''
})

async function mount(locale: 'de' | 'en' = 'de') {
  const el = document.createElement('div')
  document.body.appendChild(el)
  const i18n = createI18n({ legacy: false, locale, messages: { de, en } as never })
  const app = createApp(DemoResetControl).use(i18n)
  apps.push(app)
  app.mount(el)
  await new Promise((r) => setTimeout(r, 0))
  await nextTick()
  return el
}

describe('Demo-Reset im Testumgebungs-Balken', () => {
  beforeEach(() => Object.values(api).forEach((f) => f.mockReset()))

  it('shows no button unless the server reports a supported demo scenario', async () => {
    api.getDemoResetStatus.mockResolvedValue({ supported: false, scenario: null, label: null, reason: 'x' })
    expect((await mount()).querySelector('[data-testid="demo-reset-open"]')).toBeNull()
    api.getDemoResetStatus.mockRejectedValue(new Error('403'))
    expect((await mount()).querySelector('[data-testid="demo-reset-open"]')).toBeNull()
  })

  it('shows the dry-run and only executes after the explicit confirmation', async () => {
    api.getDemoResetStatus.mockResolvedValue({ supported: true, scenario: 'grossanlass-setup', label: 'Grossanlass Einrichtung', reason: null })
    api.previewDemoReset.mockResolvedValue(plan())
    api.executeDemoReset.mockResolvedValue(plan())
    const assign = vi.fn()
    vi.stubGlobal('location', { ...window.location, assign })
    const el = await mount()
    expect(el.textContent).toContain('Demo zurücksetzen')

    ;(el.querySelector('[data-testid="demo-reset-open"]') as HTMLElement).click()
    await new Promise((r) => setTimeout(r, 0))
    await nextTick()
    expect(api.previewDemoReset).toHaveBeenCalledWith('dep1')
    expect(el.textContent).toContain('Demo Grossanlass Einrichtung')
    expect(el.textContent).toContain('1 Ressorts und Bereiche (Zelte)')
    expect(el.textContent).toContain('Ort: Wiese → leer')
    expect(el.textContent).toContain('Rolle von ga-ok')

    const execute = el.querySelector('[data-testid="demo-reset-execute"]') as HTMLButtonElement
    expect(execute.disabled).toBe(true)
    execute.click()
    expect(api.executeDemoReset).not.toHaveBeenCalled()

    const confirm = el.querySelector('input.confirm') as HTMLInputElement
    confirm.checked = true
    confirm.dispatchEvent(new Event('change'))
    await nextTick()
    expect(execute.disabled).toBe(false)
    execute.click()
    await new Promise((r) => setTimeout(r, 0))
    expect(api.executeDemoReset).toHaveBeenCalledWith('dep1', 'grossanlass-setup', 'h1')
    expect(assign).toHaveBeenCalledWith('/dep1/dept/dashboard')
    vi.unstubAllGlobals()
  })

  it('offers no confirmation when the preview is blocked', async () => {
    api.getDemoResetStatus.mockResolvedValue({ supported: true, scenario: 'grossanlass-setup', label: 'Grossanlass Einrichtung', reason: null })
    api.previewDemoReset.mockResolvedValue(plan(['Tabelle «inventory_task» enthält 1 Datensatz']))
    const el = await mount()
    ;(el.querySelector('[data-testid="demo-reset-open"]') as HTMLElement).click()
    await new Promise((r) => setTimeout(r, 0))
    await nextTick()
    expect(el.textContent).toContain('inventory_task')
    expect(el.querySelector('input.confirm')).toBeNull()
    expect((el.querySelector('[data-testid="demo-reset-execute"]') as HTMLButtonElement).disabled).toBe(true)
  })

  it('has the texts in German and English', () => {
    for (const messages of [de, en] as Record<string, any>[]) {
      for (const key of ['button', 'title', 'lead', 'deleteTitle', 'restoreTitle', 'keepTitle', 'confirm', 'execute', 'blockedTitle']) {
        expect(messages.demoReset[key], key).toBeTruthy()
      }
    }
  })
})
