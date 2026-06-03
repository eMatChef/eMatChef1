import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  DEPARTMENT_MW_DC_ROLES,
  isDepartmentMwOrDcRole,
  useDepartmentMemberRole,
} from '@/composables/useDepartmentMemberRole'
import { getGroups, type Group } from '@/api/groups'

/** Voller Buchhaltungs-Zugriff (MW/DC). */
export function useAccountingAccess(departmentId: () => string) {
  const authStore = useAuthStore()
  const { isDepartmentLeader } = useDepartmentMemberRole()

  const groupsLoaded = ref(false)
  const groups = ref<Group[]>([])

  const canManageAccounting = computed(() => {
    const d = authStore.departments.find((x) => x.department_id === departmentId())
    const role = (d?.role || authStore.currentDepartmentRole || '').toLowerCase().trim()
    return isDepartmentMwOrDcRole(role)
  })

  const isGroupLeaderInDepartment = computed(() => {
    const userId = authStore.userId
    if (!userId) return false
    return groups.value.some((g) =>
      g.members?.some((m) => m.user_id === userId && m.is_leader),
    )
  })

  const canViewGroupCosts = computed(
    () => canManageAccounting.value || isDepartmentLeader.value || isGroupLeaderInDepartment.value,
  )

  async function ensureGroupsForAccess(): Promise<void> {
    const id = departmentId()
    if (!id || groupsLoaded.value) return
    try {
      groups.value = await getGroups(id)
    } catch {
      groups.value = []
    } finally {
      groupsLoaded.value = true
    }
  }

  return {
    canManageAccounting,
    canViewGroupCosts,
    isGroupLeaderInDepartment,
    ensureGroupsForAccess,
    mwDcRoles: DEPARTMENT_MW_DC_ROLES,
  }
}
