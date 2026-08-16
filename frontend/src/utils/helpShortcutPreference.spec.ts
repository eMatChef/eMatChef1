import { describe, it, expect, beforeEach } from 'vitest'
import {
  isHelpShortcutHidden,
  isHelpShortcutVisible,
  setHelpShortcutHidden,
  helpShortcutHiddenTick,
} from '@/utils/helpShortcutPreference'

function installMemoryLocalStorage() {
  const map = new Map<string, string>()
  const storage = {
    getItem: (key: string) => map.get(key) ?? null,
    setItem: (key: string, value: string) => {
      map.set(key, String(value))
    },
    removeItem: (key: string) => {
      map.delete(key)
    },
    clear: () => {
      map.clear()
    },
  }
  Object.defineProperty(globalThis, 'localStorage', {
    value: storage,
    configurable: true,
  })
}

describe('helpShortcutPreference', () => {
  beforeEach(() => {
    installMemoryLocalStorage()
    helpShortcutHiddenTick.value = 0
  })

  it('defaults to visible', () => {
    expect(isHelpShortcutVisible('p1')).toBe(true)
    expect(isHelpShortcutHidden('p1')).toBe(false)
  })

  it('persists hide and bumps tick', () => {
    setHelpShortcutHidden('p1', true)
    expect(isHelpShortcutHidden('p1')).toBe(true)
    expect(isHelpShortcutVisible('p1')).toBe(false)
    expect(helpShortcutHiddenTick.value).toBe(1)

    setHelpShortcutHidden('p1', false)
    expect(isHelpShortcutVisible('p1')).toBe(true)
    expect(helpShortcutHiddenTick.value).toBe(2)
  })

  it('scopes by profile', () => {
    setHelpShortcutHidden('p1', true)
    expect(isHelpShortcutHidden('p2')).toBe(false)
  })
})
