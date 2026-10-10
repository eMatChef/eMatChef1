// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, h } from 'vue'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'

// vite-plugin-vuetify importiert VIcon/VTooltip samt CSS; im Node-Test durch einfache Komponenten ersetzt.
vi.mock('vuetify/components/VTooltip', async () => {
  const { h } = await import('vue')
  return {
    VTooltip: {
      props: ['openOnHover', 'openOnFocus', 'openOnClick'],
      setup(props: Record<string, boolean>, { slots }: { slots: { default?: () => unknown } }) {
        return () =>
          h(
            'div',
            {
              'data-testid': 'tip',
              'data-hover': String(props.openOnHover),
              'data-focus': String(props.openOnFocus),
              'data-click': String(props.openOnClick),
            },
            slots.default?.() as never,
          )
      },
    },
  }
})
vi.mock('vuetify/components/VIcon', async () => {
  const { h } = await import('vue')
  return { VIcon: { render: () => h('i') } }
})

import GaHelpHint from './GaHelpHint.vue'

const mounted: Array<() => void> = []

function mount() {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp({ render: () => h('div', { class: 'field' }, [h(GaHelpHint, { field: 'cash', label: 'Cash' })]) })
  app.use(createI18n({ legacy: false, locale: 'de', messages: { de } }))
  app.mount(host)
  mounted.push(() => {
    app.unmount()
    host.remove()
  })
  return host
}

function mockHover(hover: boolean) {
  vi.stubGlobal('matchMedia', (q: string) => ({ matches: !hover && q === '(hover: none)' }))
}

afterEach(() => {
  mounted.splice(0).forEach((fn) => fn())
  vi.unstubAllGlobals()
})

describe('GaHelpHint', () => {
  it('opens on hover and keyboard focus with a mouse', () => {
    mockHover(true)
    const tip = mount().querySelector('[data-testid="tip"]')!
    expect(tip.getAttribute('data-hover')).toBe('true')
    expect(tip.getAttribute('data-focus')).toBe('true')
    expect(tip.getAttribute('data-click')).toBe('false')
  })

  it('opens by tap on touch devices instead of hover', () => {
    mockHover(false)
    const tip = mount().querySelector('[data-testid="tip"]')!
    expect(tip.getAttribute('data-hover')).toBe('false')
    expect(tip.getAttribute('data-focus')).toBe('false')
    expect(tip.getAttribute('data-click')).toBe('true')
  })

  it('shows the field explanation text', () => {
    mockHover(true)
    expect(mount().querySelector('[data-testid="tip"]')?.textContent).toContain('Cash ist das Geld')
  })

  it('is a labelled button that never submits or toggles the surrounding field', () => {
    mockHover(true)
    const btn = mount().querySelector('button')!
    expect(btn.getAttribute('type')).toBe('button')
    expect(btn.getAttribute('aria-label')).toContain('Erklärung anzeigen: Cash')
    const click = new MouseEvent('click', { bubbles: true, cancelable: true })
    btn.dispatchEvent(click)
    expect(click.defaultPrevented).toBe(true)
  })
})
