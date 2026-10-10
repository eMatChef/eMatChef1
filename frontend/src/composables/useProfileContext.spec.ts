import { describe, expect, it } from 'vitest'
import { PROFILE_TABS, profileTabForRouteName, useProfileModal } from './useProfileContext'

describe('profile tabs', () => {
  it('maps every tab to its named route and back, falling back to the personal area', () => {
    expect(PROFILE_TABS.map((t) => t.routeName)).toEqual(['Profile', 'ProfileSecurity', 'ProfileEmails', 'ProfileIdentities', 'ProfileDepartments'])
    for (const { tab, routeName } of PROFILE_TABS) expect(profileTabForRouteName(routeName)).toBe(tab)
    expect(profileTabForRouteName('Dashboard')).toBe('basics')
    expect(profileTabForRouteName(undefined)).toBe('basics')
  })
})

describe('profile modal state', () => {
  it('opens on the requested tab with a clean dirty flag and resets on close', () => {
    const modal = useProfileModal()
    modal.dirty.value = true
    modal.open('security')
    expect(modal.isOpen.value).toBe(true)
    expect(modal.tab.value).toBe('security')
    expect(modal.dirty.value).toBe(false)

    modal.dirty.value = true
    modal.close()
    expect(modal.isOpen.value).toBe(false)
    expect(modal.dirty.value).toBe(false)
  })

  it('is shared between all callers (header menu and layout host)', () => {
    useProfileModal().open('emails')
    expect(useProfileModal().isOpen.value).toBe(true)
    expect(useProfileModal().tab.value).toBe('emails')
    useProfileModal().close()
  })
})
