import { computed, ref, watch } from 'vue'
import { getGroups, type Group } from '@/api/groups'
import { expandGroupsForMemberPicker } from '@/utils/groupHierarchy'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'

/** Department-weit geteilte Gruppenliste (ein Cache für alle Composable-Aufrufer). */
const groups = ref<Group[]>([])
const groupsLoaded = ref(false)
const groupsLoading = ref(false)

/**
 * Basissicht (u, l1–l3): eingeschränkte Aktivitäten-Sicht (eigene Gruppe).
 * Camp/Event anlegen: l1–l3 oder «u» mit Gruppenchef (★); reines «u» nur Typ «activity».
 */
export function useActivityGroupMemberScope() {
  const authStore = useAuthStore()
  const { isUserRole, departmentRole, isDepartmentLeader } = useDepartmentMemberRole()

  /** MW/DC: department-weit alle Gruppen (ohne Gruppenmitgliedschaft). */
  const isDepartmentGroupManager = computed(() =>
    ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value),
  )

  const isGroupLeaderInDepartment = computed(() => {
    const userId = authStore.userId
    if (!userId) return false
    return groups.value.some((g) =>
      g.members?.some((m) => m.user_id === userId && m.is_leader),
    )
  })

  /** u / l1–l3 — eingeschränkte Listen-/Workflow-Sicht (nicht MW/DC). */
  const isRestrictedGroupMember = computed(() => isUserRole.value)

  /** Camp/Event anlegen & einreichen: l1–l3 oder «u»/«user» + Gruppenchef (★). */
  const canCreateCampAndEvent = computed(() => {
    const role = departmentRole.value
    if (['l1', 'l2', 'l3'].includes(role)) return true
    if (!['u', 'user'].includes(role)) return false
    return isGroupLeaderInDepartment.value
  })

  const allowedCreateActivityTypes = computed((): ActivityCreateType[] => {
    const role = departmentRole.value
    const types: ActivityCreateType[] = ['activity']
    if (isUserRole.value) {
      if (canCreateCampAndEvent.value) {
        types.push('camp', 'event')
      }
      return types
    }
    if (['mw', 'dc', 'matwart', 'depchef'].includes(role)) {
      return ['activity', 'camp', 'event', 'external']
    }
    if (['sa', 'org', 'sub'].includes(role)) {
      types.push('camp', 'event')
    }
    return types
  })

  const showActivityTypePicker = computed(() => allowedCreateActivityTypes.value.length > 1)

  async function loadGroupsForDepartment(departmentId: string): Promise<void> {
    if (!departmentId || groupsLoading.value) return
    groupsLoading.value = true
    try {
      groups.value = await getGroups(departmentId)
      groupsLoaded.value = true
    } catch {
      groups.value = []
      groupsLoaded.value = true
    } finally {
      groupsLoading.value = false
    }
  }

  function resetGroupsCache(): void {
    groups.value = []
    groupsLoaded.value = false
  }

  function setGroups(list: Group[]): void {
    groups.value = list
    groupsLoaded.value = true
  }

  function canSubmitActivityType(activityType: string, canSubmitFromApi?: boolean): boolean {
    if (canSubmitFromApi === false) return false
    if (!isRestrictedGroupMember.value) return true
    if (canCreateCampAndEvent.value) {
      return ['activity', 'camp', 'event'].includes(activityType)
    }
    return activityType === 'activity'
  }

  /** Eigene Gruppe(n) + Untergruppen; ohne Mitgliedschaft MW/DC: alle Gruppen des Departments. */
  function wizardGroupsForUser(allDepartmentGroups: Group[]): Group[] {
    const userId = authStore.userId
    if (!userId) return []
    const scoped = expandGroupsForMemberPicker(allDepartmentGroups, userId)
    if (scoped.length > 0) return scoped
    if (isDepartmentGroupManager.value || isDepartmentLeader.value) return allDepartmentGroups
    return []
  }

  watch(
    () => authStore.activeDepartmentId,
    (deptId, prev) => {
      if (deptId !== prev) {
        resetGroupsCache()
        if (deptId) {
          void loadGroupsForDepartment(deptId)
        }
      }
    },
  )

  return {
    groups,
    groupsLoaded,
    groupsLoading,
    isGroupLeaderInDepartment,
    isDepartmentGroupManager,
    isRestrictedGroupMember,
    canCreateCampAndEvent,
    allowedCreateActivityTypes,
    showActivityTypePicker,
    loadGroupsForDepartment,
    resetGroupsCache,
    setGroups,
    wizardGroupsForUser,
    canSubmitActivityType,
  }
}
