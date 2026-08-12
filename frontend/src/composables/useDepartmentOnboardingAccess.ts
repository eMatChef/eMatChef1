import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  isDepartmentBasicMemberRole,
  isDepartmentMwOrDcRole,
} from '@/composables/useDepartmentMemberRole'
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

  const departmentRole = computed(() => normalizeDeptRole(authStore.currentDepartmentRole))

  const hasSetupChecklistRole = computed(() => isDepartmentMwOrDcRole(departmentRole.value))

  const hasTourRole = computed(
    () => isDepartmentMwOrDcRole(departmentRole.value) || isDepartmentBasicMemberRole(departmentRole.value)
  )

  /** @deprecated use hasSetupChecklistRole — historically MW/DC only */
  const hasOnboardingRole = hasSetupChecklistRole

  const skipsPersonalDepartmentOnboarding = computed(() => {
    const role = departmentRole.value
    if (['sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(role)) {
      return true
    }
    return authStore.userRoles.includes('ROLE_SUPERADMIN')
  })

  const isGrossanlassDepartment = computed(() =>
    authStore.isDepartmentGrossanlass(departmentId.value)
  )

  const baseAccess = computed(
    () =>
      authStore.isLoggedIn &&
      !!departmentId.value &&
      !!profileId.value &&
      !skipsPersonalDepartmentOnboarding.value &&
      !isGrossanlassDepartment.value
  )

  /** Setup-Checkliste (MW/DC). */
  const canUseSetupChecklist = computed(() => baseAccess.value && hasSetupChecklistRole.value)

  /** Spotlight-Touren (MW/DC + User/L1–L3). */
  const canUseTours = computed(() => baseAccess.value && hasTourRole.value)

  /** Hub Hilfe → Touren. */
  const canUseHelpTours = computed(() => canUseTours.value)
  /** @deprecated use canUseHelpTours */
  const canUseHelpEinrichtung = canUseHelpTours

  /** @deprecated alias for canUseSetupChecklist (badge / checklist refresh) */
  const canUseOnboarding = canUseSetupChecklist

  const isPausedLocally = computed(() => {
    if (!canUseSetupChecklist.value) return false
    return isOnboardingPaused(profileId.value, departmentId.value)
  })

  const isDoneLocally = computed(() => {
    if (!canUseSetupChecklist.value) return true
    return isOnboardingDone(profileId.value, departmentId.value)
  })

  return {
    departmentId,
    profileId,
    departmentRole,
    canUseOnboarding,
    canUseSetupChecklist,
    canUseTours,
    canUseHelpTours,
    canUseHelpEinrichtung,
    hasOnboardingRole,
    hasSetupChecklistRole,
    hasTourRole,
    skipsPersonalDepartmentOnboarding,
    isGrossanlassDepartment,
    isPausedLocally,
    isDoneLocally,
  }
}
