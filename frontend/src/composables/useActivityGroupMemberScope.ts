import { computed, ref, watch } from 'vue'
import { getGroups, type Group } from '@/api/groups'
import { expandGroupsForMemberPicker } from '@/utils/groupHierarchy'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'

/**
 * Department-Rolle «u» ohne Gruppenchef: eingeschränkte Aktivitäten-Sicht und nur Typ «activity» anlegen.
 */
export function useActivityGroupMemberScope() {
  const authStore = useAuthStore()
  const { isUserRole, departmentRole } = useDepartmentMemberRole()

  /** MW/DC: department-weit alle Gruppen (ohne Gruppenmitgliedschaft). */
  const isDepartmentGroupManager = computed(() =>
    ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value),
  )

  const groups = ref<Group[]>([])
  const groupsLoaded = ref(false)
  const groupsLoading = ref(false)

  const isGroupLeaderInDepartment = computed(() => {
    const userId = authStore.userId
    if (!userId) return false
    return groups.value.some((g) =>
      g.members?.some((m) => m.user_id === userId && m.is_leader),
    )
  })

  /** u + nur Gruppenmitglied (kein Gruppenchef im Department). */
  const isRestrictedGroupMember = computed(
    () => isUserRole.value && !isGroupLeaderInDepartment.value,
  )

  const allowedCreateActivityTypes = computed((): ActivityCreateType[] => {
    if (isRestrictedGroupMember.value) {
      return ['activity']
    }
    return ['activity', 'camp', 'event', 'external']
  })

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

  /** Gruppenmitglied (u): nur Typ «activity» selbst einreichen. */
  function canSubmitActivityType(activityType: string): boolean {
    if (!isRestrictedGroupMember.value) return true
    return activityType === 'activity'
  }

  /** Gruppen für Wizard-Dropdown (MW/DC: alle; Member: eigene + Untergruppen). */
  function wizardGroupsForUser(allDepartmentGroups: Group[]): Group[] {
    const userId = authStore.userId
    if (!userId) return []
    if (isDepartmentGroupManager.value || !isRestrictedGroupMember.value) {
      return allDepartmentGroups
    }
    return expandGroupsForMemberPicker(allDepartmentGroups, userId)
  }

  watch(
    () => authStore.activeDepartmentId,
    (deptId, prev) => {
      if (deptId !== prev) {
        resetGroupsCache()
        if (deptId && (isUserRole.value || isDepartmentGroupManager.value)) {
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
    allowedCreateActivityTypes,
    loadGroupsForDepartment,
    resetGroupsCache,
    setGroups,
    wizardGroupsForUser,
    canSubmitActivityType,
  }
}
