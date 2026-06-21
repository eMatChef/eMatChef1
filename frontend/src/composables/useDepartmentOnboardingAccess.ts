import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  isOnboardingDone,
  isOnboardingPaused,
} from '@/utils/departmentOnboarding'

function normalizeDeptRole(role: string): string {
  return String(role || '').toLowerCase().trim()
}

export function useDepartmentOnboardingAccess() {
  const route = useRoute()
  const authStore = useAuthStore()

  const departmentId = computed(() => {
    const value = route.params.departmentId
    return typeof value === 'string' ? value : ''
  })

  const profileId = computed(() => authStore.profileId || '')

  const hasOnboardingRole = computed(() => {
    const role = normalizeDeptRole(authStore.currentDepartmentRole)
    return ['dc', 'depchef', 'mw', 'matwart'].includes(role)
  })

  const skipsPersonalDepartmentOnboarding = computed(() => {
    const role = normalizeDeptRole(authStore.currentDepartmentRole)
    if (['sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(role)) {
      return true
    }
    return authStore.userRoles.includes('ROLE_SUPERADMIN')
  })

  const isGrossanlassDepartment = computed(() =>
    authStore.isDepartmentGrossanlass(departmentId.value)
  )

  const canUseOnboarding = computed(() => {
    return (
      authStore.isLoggedIn &&
      !!departmentId.value &&
      !!profileId.value &&
      hasOnboardingRole.value &&
      !skipsPersonalDepartmentOnboarding.value &&
      !isGrossanlassDepartment.value
    )
  })

  const isPausedLocally = computed(() => {
    if (!canUseOnboarding.value) return false
    return isOnboardingPaused(profileId.value, departmentId.value)
  })

  const isDoneLocally = computed(() => {
    if (!canUseOnboarding.value) return true
    return isOnboardingDone(profileId.value, departmentId.value)
  })

  return {
    departmentId,
    profileId,
    canUseOnboarding,
    hasOnboardingRole,
    skipsPersonalDepartmentOnboarding,
    isGrossanlassDepartment,
    isPausedLocally,
    isDoneLocally,
  }
}
