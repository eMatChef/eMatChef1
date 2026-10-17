// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import en from '@/locales/en.json'

const api = vi.hoisted(() => ({
  createGrossanlassProcurementOrder: vi.fn(),
  updateGrossanlassProcurementOrder: vi.fn(),
  cancelGrossanlassProcurementOrder: vi.fn(),
}))
vi.mock('@/api/grossanlassProcurement', async () => {
  const actual = await vi.importActual<typeof import('@/api/grossanlassProcurement')>('@/api/grossanlassProcurement')
  return { ...actual, ...api }
})
vi.mock('@/stores/businessClock', () => ({
  formatWallClock: (d: Date) => d.toISOString().slice(0, 19),
  useBusinessClockStore: () => ({ displayNow: new Date('2027-04-10T12:29:00Z') }),
}))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))
vi.mock('@/components/form/base', () => ({
  EButton: { inheritAttrs: false, props: ['loading', 'disabled', 'variant', 'size', 'type'], template: '<button v-bind="$attrs" :type="type || \'button\'" :disabled="disabled || undefined"><slot /></button>' },
  ETextField: { inheritAttrs: false, props: ['modelValue', 'label', 'type'], emits: ['update:modelValue'], template: '<input v-bind="$attrs" :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
  ESelect: { inheritAttrs: false, props: ['modelValue', 'items', 'label'], emits: ['update:modelValue'], template: '<select v-bind="$attrs" :aria-label="label" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="i in items" :key="i.value" :value="i.value">{{ i.title }}</option></select>' },
  ECheckbox: { props: ['modelValue', 'label'], emits: ['update:modelValue'], template: '<input type="checkbox" :aria-label="label" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />' },
  EDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="actions" /></div>' },
}))

import GrossanlassProcurementOrdersPanel from './GrossanlassProcurementOrdersPanel.vue'

const order = (over: Record<string, unknown> = {}) => ({
  id: 'o1', procurement_line_id: 'l1', status: 'confirmed', agreement_kind: 'phone', quote_id: null, supplier: 'Müller',
  ordered_at: '2027-04-01T12:00:00+00:00', delivery_at: null, cost_chf: 100, order_ref: null, quantity: 60, ordered_quantity: 60,
  received_quantity: 20, missing_quantity: 40, commitment_id: 'c1', notes: null, created_at: '', updated_at: '', ...over,
})

const line = (over: Record<string, unknown> = {}) => ({
  id: 'l1', department_id: 'd1', quantity: 100, quantity_unit: 'Stk', quantity_ordered: 60, quantity_open: 40, quantity_over: 0,
  quantity_received: 20, quotes: [], orders: [order()], order: order(), ...over,
}) as never

const apps: ReturnType<typeof createApp>[] = []
afterEach(() => {
  apps.splice(0).forEach((a) => a.unmount())
  document.body.innerHTML = ''
})

async function mount(props: Record<string, unknown>) {
  const el = document.createElement('div')
  document.body.appendChild(el)
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de, en } as never })
  const app = createApp(GrossanlassProcurementOrdersPanel, { departmentId: 'd1', ...props }).use(i18n)
  apps.push(app)
  app.mount(el)
  await nextTick()
  return el
}

async function fill(el: HTMLElement, label: string, value: string) {
  const input = el.querySelector<HTMLInputElement>(`input[aria-label="${label}"]`)!
  input.value = value
  input.dispatchEvent(new Event('input'))
  await nextTick()
}

describe('Bestell-Panel einer Bedarfsposition', () => {
  beforeEach(() => Object.values(api).forEach((f) => f.mockReset()))

  it('defaults the order date to the business clock, not the system date', async () => {
    const el = await mount({ line: line() })

    expect(el.querySelector<HTMLInputElement>('input[aria-label="Bestellt am"]')?.value).toBe('2027-04-10')
  })

  it('preselects the only quote of a line without orders as the basis of the first order', async () => {
    const quote = { id: 'q1', supplier: 'Holzhandel', amount_chf: 300, selected: false, delivery_at: null, return_needed: false }
    const el = await mount({ line: line({ orders: [], order: null, quotes: [quote], quantity_ordered: 0, quantity_open: 100 }) })

    expect(el.querySelector<HTMLSelectElement>('select[aria-label="Grundlage"]')?.value).toBe('q1')
    expect(el.querySelector<HTMLInputElement>('input[aria-label="Kosten CHF"]')?.value).toBe('300')
  })

  it('shows every order with its own received progress and the open remainder', async () => {
    const el = await mount({ line: line() })

    expect(el.querySelector('[data-testid="order-o1"]')?.textContent).toContain('20 von 60 eingegangen')
    expect(el.textContent).toContain('Müller')
  })

  it('places a partial order from a phone agreement without a quote', async () => {
    api.createGrossanlassProcurementOrder.mockResolvedValue(line())
    const el = await mount({ line: line() })

    await fill(el, 'Lieferant', 'Zimmerei Muri')
    await fill(el, 'Bestellte Menge', '40')
    await fill(el, 'Kosten CHF', '140')
    el.querySelector<HTMLFormElement>('form')!.dispatchEvent(new Event('submit'))
    await nextTick()

    expect(api.createGrossanlassProcurementOrder).toHaveBeenCalledWith('d1', 'l1', expect.objectContaining({
      supplier: 'Zimmerei Muri', quote_id: null, quantity: 40, cost_chf: 140,
    }))
    expect(api.createGrossanlassProcurementOrder.mock.calls[0][2]).not.toHaveProperty('confirm_overcoverage')
  })

  it('asks for a deliberate confirmation before over-covering the demand, then resubmits', async () => {
    const overcoverage = { response: { status: 409, data: { code: 'overcoverage_confirmation_required', need: 100, already_covered: 100, requested: 10, open: 0, over: 10 } } }
    api.createGrossanlassProcurementOrder.mockRejectedValueOnce(overcoverage).mockResolvedValueOnce(line())
    const el = await mount({ line: line() })

    await fill(el, 'Lieferant', 'Dritte AG')
    await fill(el, 'Bestellte Menge', '10')
    await fill(el, 'Kosten CHF', '50')
    el.querySelector<HTMLFormElement>('form')!.dispatchEvent(new Event('submit'))
    await new Promise((r) => setTimeout(r, 0))
    await nextTick()

    expect(el.querySelector('[data-testid="overcoverage-text"]')?.textContent).toContain('10 zu viel')
    el.querySelector<HTMLButtonElement>('[data-testid="overcoverage-confirm"]')!.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(api.createGrossanlassProcurementOrder).toHaveBeenLastCalledWith('d1', 'l1', expect.objectContaining({ confirm_overcoverage: true }))
  })

  it('cancels an order only with a reason', async () => {
    api.cancelGrossanlassProcurementOrder.mockResolvedValue(line({ orders: [order({ status: 'cancelled' })], order: null }))
    const el = await mount({ line: line({ orders: [order({ received_quantity: 0 })] }) })

    const cancel = [...el.querySelectorAll('button')].find((b) => b.textContent?.trim() === 'Stornieren')!
    cancel.click()
    await nextTick()
    const confirm = [...el.querySelectorAll<HTMLButtonElement>('.dialog button')].find((b) => b.textContent?.includes('Stornieren'))!
    expect(confirm.disabled).toBe(true)

    await fill(el, 'Grund', 'Lieferant fällt aus')
    expect(confirm.disabled).toBe(false)
    confirm.click()
    await new Promise((r) => setTimeout(r, 0))

    expect(api.cancelGrossanlassProcurementOrder).toHaveBeenCalledWith('d1', 'l1', 'o1', 'Lieferant fällt aus')
  })
})
