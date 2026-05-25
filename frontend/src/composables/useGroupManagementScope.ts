import { computed, type Ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import type { Group } from '@/api/groups'

/** MW/DC: volle Gruppen-Verwaltung; Gruppenchef: nur Mitglieder hinzufügen (eigene Gruppe). */
export function useGroupManagementScope(groups: Ref<Group[]>) {
  const authStore = useAuthStore()
  const { isUserRole } = useDepartmentMemberRole()

  const canFullyManageGroups = computed(() => !isUserRole.value)

  function isLeaderOfGroup(group: Group): boolean {
    const userId = authStore.userId
    if (!userId) return false
    return group.members?.some((m) => m.user_id === userId && m.is_leader) ?? false
  }

  function canManageMembersForGroup(group: Group): boolean {
    return canFullyManageGroups.value || isLeaderOfGroup(group)
  }

  const isGroupLeaderSomewhere = computed(() => groups.value.some((g) => isLeaderOfGroup(g)))

  const showGroupManagementActions = computed(
    () => canFullyManageGroups.value || isGroupLeaderSomewhere.value,
  )

  return {
    canFullyManageGroups,
    isLeaderOfGroup,
    canManageMembersForGroup,
    isGroupLeaderSomewhere,
    showGroupManagementActions,
  }
}
