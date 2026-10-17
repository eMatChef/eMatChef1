import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

const css = readFileSync(new URL('./brand-tokens.css', import.meta.url), 'utf-8')

describe('brand tokens', () => {
  it('colours the text selection with existing theme tokens only', () => {
    const block = /::selection\s*\{([^}]*)\}/.exec(css)?.[1] ?? ''
    expect(block).toContain('background: var(--color-primary-subtle-bg)')
    expect(block).toContain('color: var(--color-text)')
    // keine zusätzliche feste Farbe
    expect(block).not.toMatch(/#[0-9a-fA-F]{3,8}|rgba?\(|hsla?\(/)
  })
})
