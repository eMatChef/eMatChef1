import { describe, expect, it } from 'vitest'
import { resolveKioskHomeUrl } from './displayHost'

describe('resolveKioskHomeUrl', () => {
  it('uses the origin configured by the backend for existing screens', () => {
    expect(resolveKioskHomeUrl(['https://display.ematchef.ch/display/dsiabc123def'])).toBe('https://display.ematchef.ch')
    expect(resolveKioskHomeUrl(['https://display.ematchef.test/display/dsi1'])).toBe('https://display.ematchef.test')
  })

  it('skips relative or invalid display urls', () => {
    expect(resolveKioskHomeUrl(['/display/dsi1', 'not a url', 'https://display.ematchef.ch/display/x'])).toBe('https://display.ematchef.ch')
  })

  it('falls back to a derived address when no screen offers one', () => {
    expect(resolveKioskHomeUrl([])).toMatch(/\/display$|^https?:\/\//)
  })
})
