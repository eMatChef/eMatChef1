// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'

const route = vi.hoisted(() => ({ value: null as null | { name: string; params: Record<string, string>; path: string } }))
const push = vi.hoisted(() => vi.fn())
const resolveHref = vi.hoisted(() => vi.fn(() => '/x'))
const auth = vi.hoisted(() => ({
  value: {
    activeDepartmentId: 'ga1',
    profileId: 'p1',
    isLoggedIn: true,
    isDepartmentGrossanlass: (id: string) => id === 'ga1',
  },
}))
const detailTabs = vi.hoisted(() => ({ tabs: [] as Array<{ hasUnsavedChanges: boolean }> }))

vi.mock('vue-router', () => ({
  useRoute: () => route.value,
  useRouter: () => ({ push, resolve: resolveHref }),
}))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => auth.value }))
vi.mock('@/stores/detailTabs', () => ({ useDetailTabsStore: () => detailTabs }))
vi.mock('@/utils/onboardingGate', () => ({
  canUseDepartmentOnboarding: () => false,
  canUseHelpTours: () => false,
}))

import { useGaHelp } from './useGaHelp'
import { useHelpShortcut } from './useHelpShortcut'

function at(name: string, departmentId = 'ga1') {
  route.value = reactive({ name, params: { departmentId }, path: `/${departmentId}/x` })
}

beforeEach(() => {
  push.mockClear()
  detailTabs.tabs = []
  useGaHelp().close()
})

describe('useGaHelp', () => {
  it('offers a chapter on a GA page of a Grossanlass department', () => {
    at('GrossanlassPlanungFreigabe')
    expect(useGaHelp().topicId.value).toBe('freigabe')
  })

  it('offers nothing on the same page names in a normal department', () => {
    at('GrossanlassPlanungFreigabe', 'pfadi1')
    expect(useGaHelp().topicId.value).toBeNull()
  })

  it('offers nothing on department pages, also in a Grossanlass department', () => {
    at('Activities')
    expect(useGaHelp().topicId.value).toBeNull()
  })

  it('links to the chapter on the GA help page', () => {
    at('GrossanlassKosten')
    expect(useGaHelp().helpPageLocation('kosten')).toEqual({
      name: 'GrossanlassHilfe',
      params: { departmentId: 'ga1', topic: 'kosten' },
    })
  })
})

describe('help button', () => {
  it('opens the modal on a GA page without navigating', () => {
    at('GrossanlassBeschaffungBedarf')
    detailTabs.tabs = [{ hasUnsavedChanges: true }]
    const open = vi.spyOn(window, 'open').mockImplementation(() => null)
    useHelpShortcut().openHelp()
    expect(useGaHelp().modalOpen.value).toBe(true)
    expect(push).not.toHaveBeenCalled()
    expect(open).not.toHaveBeenCalled()
    open.mockRestore()
  })

  it('keeps the department help on department pages', () => {
    at('Activities')
    useHelpShortcut().openHelp()
    expect(useGaHelp().modalOpen.value).toBe(false)
    expect(push).toHaveBeenCalledWith('/ga1/help/department')
  })

  it('keeps the department help in a guest department, also on GA-named pages', () => {
    at('GrossanlassGastVorschau', 'pfadi1')
    useHelpShortcut().openHelp()
    expect(useGaHelp().modalOpen.value).toBe(false)
    expect(push).toHaveBeenCalledWith('/pfadi1/help/department')
  })

  it('hides the floating button on the GA help page', () => {
    at('GrossanlassHilfe')
    expect(useHelpShortcut().showFloatingButton.value).toBe(false)
  })
})
