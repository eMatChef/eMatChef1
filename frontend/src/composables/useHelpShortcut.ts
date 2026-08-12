import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { canUseDepartmentOnboarding, canUseHelpTours } from '@/utils/onboardingGate'
import { isOnboardingDone, readOnboardingState } from '@/utils/departmentOnboarding'
import { countOpenChecklistItems } from '@/utils/onboardingChecklist'
import {
  helpShortcutHiddenTick,
  isHelpShortcutVisible,
  setHelpShortcutHidden,
} from '@/utils/helpShortcutPreference'

/**
 * Floating Hilfe-Shortcut: Zielpfad wie Sidebar, bei ungespeicherten Detail-Tabs neuer Browser-Tab.
 */
export function useHelpShortcut() {
  const route = useRoute()
  const router = useRouter()
  const authStore = useAuthStore()
  const detailTabsStore = useDetailTabsStore()

  const departmentId = computed(() => {
    const value = route.params.departmentId
    return typeof value === 'string' ? value : authStore.activeDepartmentId || ''
  })

  const profileId = computed(() => authStore.profileId || '')

  const helpPath = computed(() => {
    const depId = departmentId.value
    if (!depId) return '/help/dokumentation'

    const preferTours =
      canUseDepartmentOnboarding(authStore, depId) && openChecklistCount(depId) > 0
    if (preferTours || canUseHelpTours(authStore, depId)) {
      return `/${depId}/help/tours`
    }
    return `/${depId}/help/dokumentation`
  })

  function openChecklistCount(depId: string): number {
    const profId = profileId.value
    if (!profId || !canUseDepartmentOnboarding(authStore, depId)) return 0
    if (isOnboardingDone(profId, depId)) return 0
    const state = readOnboardingState(profId, depId)
    return countOpenChecklistItems(state.completed, state.skipped || {})
  }

  const isOnHelpRoute = computed(() => route.path.includes('/help'))

  const showFloatingButton = computed(() => {
    void helpShortcutHiddenTick.value
    if (!authStore.isLoggedIn || !departmentId.value || !profileId.value) return false
    if (isOnHelpRoute.value) return false
    return isHelpShortcutVisible(profileId.value)
  })

  const shortcutVisible = computed({
    get() {
      void helpShortcutHiddenTick.value
      return isHelpShortcutVisible(profileId.value)
    },
    set(visible: boolean) {
      setHelpShortcutHidden(profileId.value, !visible)
    },
  })

  function hasUnsavedDetailTabs(): boolean {
    return detailTabsStore.tabs.some((tab) => tab.hasUnsavedChanges)
  }

  function openHelp(): void {
    const path = helpPath.value
    if (hasUnsavedDetailTabs()) {
      const href = router.resolve(path).href
      window.open(href, '_blank', 'noopener,noreferrer')
      return
    }
    void router.push(path)
  }

  return {
    helpPath,
    showFloatingButton,
    shortcutVisible,
    openHelp,
    hasUnsavedDetailTabs,
  }
}
