import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useDepartmentRoleLabelsStore } from '@/stores/departmentRoleLabels'
import { removeDepartmentMember, type DepartmentMember } from '@/api/departments'
import {
  assignableDeptRoleKeys,
  canManageDepartmentMember,
  DEPT_ROLES,
  getDeptRoleColor,
  getDeptRoleShort,
  hasGlobalAdminPrivilege,
} from '@/utils/departmentMemberRoles'

/**
 * Zentrale Mitglieder-Verwaltung: Rechte, Entfernen mit Warnung, Rollen-Labels.
 * Nutzen: Benutzer-Tabelle, Ressorts-Mitglieder, Detail-Dialog.
 */
export function useDepartmentMemberAdmin(departmentId: MaybeRefOrGetter<string>) {
  const { t } = useI18n()
  const authStore = useAuthStore()
  const toast = useToast()
  const confirm = useConfirm()
  const roleLabelsStore = useDepartmentRoleLabelsStore()

  const deptId = computed(() => String(toValue(departmentId) || '').trim())

  const isGlobalAdmin = computed(() => hasGlobalAdminPrivilege(authStore.userRoles || []))

  const isGrossanlass = computed(() => authStore.isDepartmentGrossanlass(deptId.value))

  function canManageMember(member: Pick<DepartmentMember, 'user_id' | 'role'>): boolean {
    return canManageDepartmentMember({
      actorUserId: authStore.userId,
      actorDeptRole: authStore.currentDepartmentRole || 'u',
      actorGlobalRoles: authStore.userRoles || [],
      memberUserId: member.user_id,
      memberRole: member.role,
      isGrossanlass: isGrossanlass.value,
    })
  }

  const assignableRoles = computed(() => {
    const keys = assignableDeptRoleKeys(
      authStore.currentDepartmentRole || 'u',
      isGlobalAdmin.value,
      isGrossanlass.value,
    )
    return Object.fromEntries(keys.map((key) => [key, DEPT_ROLES[key]])) as Partial<
      typeof DEPT_ROLES
    >
  })

  function getRoleLabel(role: string): string {
    return roleLabelsStore.labelFor(role, deptId.value, t)
  }

  const editRoleSelectItems = computed(() =>
    Object.entries(assignableRoles.value).map(([key, cfg]) => ({
      title: `${cfg?.short ?? key} – ${getRoleLabel(key)}`,
      value: key,
    })),
  )

  async function removeFromDepartment(member: DepartmentMember): Promise<boolean> {
    if (!canManageMember(member)) {
      toast.error(t('settings.departmentUsers.errCannotManageMember'))
      return false
    }
    if (authStore.userId && member.user_id === authStore.userId) {
      toast.error(t('settings.departmentUsers.errCannotRemoveSelf'))
      return false
    }
    const ok = await confirm.confirm({
      title: t('settings.departmentUsers.confirmRemoveTitle'),
      message: t('settings.departmentUsers.confirmRemoveMessage', { name: member.name }),
      confirmText: t('common.remove'),
      cancelText: t('common.cancel'),
      variant: 'danger',
    })
    if (!ok) return false
    try {
      await removeDepartmentMember(deptId.value, member.user_id)
      return true
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } } }
      toast.error(e.response?.data?.error || t('settings.departmentUsers.errRemoveMember'))
      return false
    }
  }

  return {
    canManageMember,
    assignableRoles,
    editRoleSelectItems,
    getRoleLabel,
    getRoleColor: getDeptRoleColor,
    getRoleShort: (role: string) => getDeptRoleShort(role, isGrossanlass.value),
    isGlobalAdmin,
    isGrossanlass,
    removeFromDepartment,
  }
}
