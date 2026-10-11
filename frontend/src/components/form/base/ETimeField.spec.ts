// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, h, ref } from 'vue'

// Vuetify-Komponenten werden im Node-Test durch einfache Elemente ersetzt; geprüft wird die angezeigte Uhrzeit.
vi.mock('vuetify/components', async () => {
  const { h } = await import('vue')
  return {
    VTextField: {
      props: ['modelValue'],
      setup(props: { modelValue?: string }, { slots }: { slots: { default?: () => unknown } }) {
        return () => h('div', [h('input', { 'data-testid': 'time', value: props.modelValue }), slots.default?.() as never])
      },
    },
    VMenu: { render: () => h('span') },
    VTimePicker: { render: () => h('span') },
  }
})

import ETimeField from './ETimeField.vue'

const mounted: Array<() => void> = []

function shown(value: string): string {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const model = ref(value)
  const app = createApp({ render: () => h(ETimeField, { modelValue: model.value, 'onUpdate:modelValue': (v: string) => (model.value = v) }) })
  app.mount(host)
  mounted.push(() => {
    app.unmount()
    host.remove()
  })

  return (host.querySelector('[data-testid="time"]') as HTMLInputElement).value
}

afterEach(() => {
  mounted.splice(0).forEach((fn) => fn())
})

describe('ETimeField', () => {
  it('zeigt 23:59 unverändert und nicht als 00:00', () => {
    expect(shown('23:59')).toBe('23:59')
  })

  it('zeigt Zeiten auf dem Raster unverändert', () => {
    expect(shown('13:00')).toBe('13:00')
    expect(shown('16:15')).toBe('16:15')
  })

  it('zeigt Zeiten ausserhalb des Rasters ohne Rundung', () => {
    expect(shown('12:52')).toBe('12:52')
  })
})
