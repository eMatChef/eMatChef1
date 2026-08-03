import { describe, expect, it } from 'vitest'
import { formatMarketingVersion } from './appVersion'

describe('formatMarketingVersion', () => {
  it('maps semver 4.0.1 to v4.01', () => {
    expect(formatMarketingVersion('4.0.1')).toBe('v4.01')
  })

  it('maps 4.1.2 to v4.12', () => {
    expect(formatMarketingVersion('4.1.2')).toBe('v4.12')
  })

  it('keeps already-prefixed or freeform labels', () => {
    expect(formatMarketingVersion('v9')).toBe('v9')
    expect(formatMarketingVersion('beta')).toBe('vbeta')
  })
})
