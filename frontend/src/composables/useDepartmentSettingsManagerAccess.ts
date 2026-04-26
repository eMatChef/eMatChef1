import { computed, type Ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * Abteilungs-Rollen, die sensible Department-Einstellungen bearbeiten dürfen
 * (Join-Code, öffentliche Material-Seite, Add-ons, Onboarding/DB in „Mein Department“).
 */
const DEPARTMENT_SETTINGS_MANAGER_ROLES = new Set([
  'dc',
  'depchef',
  'mw',
  'matwart',
  'mv', // Kurzform / Schreibvariante für Materialwart
  'sa',
  'superadmin',
  'org',
  'organisationschef',
  'sub',
  'suborgchef',
])

/**
 * @param selectedDepartmentId – z. B. aus Abteilungs-Dropdown; die Route hat Vorrang, falls gesetzt.
 */
export function useDepartmentSettingsManagerAccess(selectedDepartmentId: Ref<string | null>) {
  const route = useRoute()
  const authStore = useAuthStore()

  const userDepartments = computed(() => authStore.departments || [])

  /** Welche Abteilung für Berechtigung & API gilt (URL zuerst, dann Auswahl, dann Store). */
  const effectiveDepartmentId = computed(() => {
    const rid = String(route.params.departmentId || '').trim()
    if (rid && userDepartments.value.some((d) => d.department_id === rid)) {
      return rid
    }
    const sid = String(selectedDepartmentId.value || '').trim()
    if (sid && userDepartments.value.some((d) => d.department_id === sid)) {
      return sid
    }
    const aid = String(authStore.activeDepartmentId || '').trim()
    if (aid && userDepartments.value.some((d) => d.department_id === aid)) {
      return aid
    }
    return userDepartments.value[0]?.department_id ?? null
  })

  const effectiveDepartmentRole = computed(() => {
    const id = effectiveDepartmentId.value
    if (!id) return ''
    const row = userDepartments.value.find((d) => d.department_id === id)
    return String(row?.role || '').toLowerCase().trim()
  })

  const canManageDepartmentSensitiveSettings = computed(() => {
    if (authStore.userRoles.includes('ROLE_SUPERADMIN')) {
      return true
    }
    const role = effectiveDepartmentRole.value
    if (!role) {
      return false
    }
    return DEPARTMENT_SETTINGS_MANAGER_ROLES.has(role)
  })

  return {
    userDepartments,
    effectiveDepartmentId,
    effectiveDepartmentRole,
    canManageDepartmentSensitiveSettings,
  }
}
