import { describe, expect, it } from 'vitest'
import {
  layoutViewKey,
  profileEntryQuery,
  profileFallbackPath,
  profileFromForEntry,
  profileFromQuery,
  sanitizeProfileFrom,
} from './profileReturn'

describe('sanitizeProfileFrom', () => {
  it('accepts internal paths and keeps query and hash', () => {
    expect(sanitizeProfileFrom('/b57aa6184ef5/ga/planning')).toBe('/b57aa6184ef5/ga/planning')
    expect(sanitizeProfileFrom('/dept/materials?tab=stock#row-3')).toBe('/dept/materials?tab=stock#row-3')
  })

  it.each([
    'https://evil.example/x',
    'http://evil.example',
    '//evil.example/x',
    '/\\evil.example',
    '/a\\b',
    'javascript:alert(1)',
    'evil.example',
    '',
    '   ',
    '/%5Cevil.example',
    '/%2F%2Fevil.example',
    '/a\nb',
    '/a\u0000b',
    `/${'a'.repeat(2100)}`,
    '/%E0%A4%A',
  ])('rejects %j', (value) => {
    expect(sanitizeProfileFrom(value)).toBeNull()
  })

  it('rejects non-string values', () => {
    expect(sanitizeProfileFrom(undefined)).toBeNull()
    expect(sanitizeProfileFrom(null)).toBeNull()
    expect(sanitizeProfileFrom(42)).toBeNull()
    expect(sanitizeProfileFrom({})).toBeNull()
  })

  it('takes the first value of a repeated parameter', () => {
    expect(sanitizeProfileFrom(['/dept/a', 'https://evil.example'])).toBe('/dept/a')
    expect(sanitizeProfileFrom(['https://evil.example', '/dept/a'])).toBeNull()
  })

  it('never returns to a profile or login route (no loops)', () => {
    expect(sanitizeProfileFrom('/profile')).toBeNull()
    expect(sanitizeProfileFrom('/profile/security?x=1')).toBeNull()
    expect(sanitizeProfileFrom('/dept/../profile/emails')).toBeNull()
    expect(sanitizeProfileFrom('/login')).toBeNull()
    expect(sanitizeProfileFrom('/profiles-overview')).toBe('/profiles-overview')
  })

  it('removes server-reserved OAuth and tour parameters', () => {
    expect(sanitizeProfileFrom('/dept?profile_security=1&oauth=linked&provider=google&reason=x&keep=1')).toBe('/dept?keep=1')
    expect(sanitizeProfileFrom('/dept?onboardingTour=profile-overview&onboardingTourStep=12')).toBe('/dept')
  })
})

describe('profile return helpers', () => {
  it('reads from the route query', () => {
    expect(profileFromQuery({ from: '/dept/x' })).toBe('/dept/x')
    expect(profileFromQuery({ from: 'https://evil.example' })).toBeNull()
    expect(profileFromQuery({})).toBeNull()
  })

  it('falls back to the active department or the dashboard redirect', () => {
    expect(profileFallbackPath('abc')).toBe('/abc')
    expect(profileFallbackPath(null)).toBe('/dashboard')
    expect(profileFallbackPath('')).toBe('/dashboard')
  })

  it('uses the current page as from outside the profile and keeps it inside', () => {
    expect(profileEntryQuery({ path: '/dept/ga/planning', fullPath: '/dept/ga/planning?x=1', query: {} })).toEqual({
      from: '/dept/ga/planning?x=1',
    })
    expect(profileEntryQuery({ path: '/profile/security', fullPath: '/profile/security?from=%2Fdept', query: { from: '/dept' } })).toEqual({
      from: '/dept',
    })
    expect(profileEntryQuery({ path: '/profile', fullPath: '/profile?from=https%3A%2F%2Fevil', query: { from: 'https://evil' } })).toEqual({})
    expect(profileEntryQuery({ path: '/login', fullPath: '/login', query: {} })).toEqual({})
  })
})

describe('profile entry without from (tour start, direct link from the app)', () => {
  const dept = { matched: [{}], path: '/dept/ga/planning', fullPath: '/dept/ga/planning?onboardingTour=profile-overview&onboardingTourStep=12' }

  it('uses the previous page, without tour parameters', () => {
    expect(profileFromForEntry({ query: { onboardingTour: 'profile-overview', onboardingTourStep: '13' } }, dept)).toBe('/dept/ga/planning')
  })

  it('keeps an existing from and does not add one on a fresh load or between profile pages', () => {
    expect(profileFromForEntry({ query: { from: '/x' } }, dept)).toBeNull()
    expect(profileFromForEntry({ query: {} }, { matched: [], path: '/', fullPath: '/' })).toBeNull()
    expect(profileFromForEntry({ query: {} }, { matched: [{}], path: '/profile/security', fullPath: '/profile/security?from=%2Fx' })).toBeNull()
    expect(profileFromForEntry({ query: {} }, { matched: [{}], path: '/login', fullPath: '/login' })).toBeNull()
  })
})

describe('layoutViewKey', () => {
  it('shares one key across the profile tabs so the profile page is not re-created', () => {
    const a = layoutViewKey({ path: '/profile', meta: { globalProfile: true } }, 3)
    const b = layoutViewKey({ path: '/profile/security', meta: { globalProfile: true } }, 3)
    expect(a).toBe(b)
    expect(layoutViewKey({ path: '/profile', meta: { globalProfile: true } }, 4)).not.toBe(a)
  })

  it('keeps one key per path for every other page', () => {
    expect(layoutViewKey({ path: '/dept/a', meta: {} }, 1)).toBe('/dept/a:1')
    expect(layoutViewKey({ path: '/dept/b', meta: {} }, 1)).not.toBe(layoutViewKey({ path: '/dept/a', meta: {} }, 1))
  })
})
