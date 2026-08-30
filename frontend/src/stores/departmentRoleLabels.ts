import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  EMPTY_DEPARTMENT_ROLE_LABELS,
  getDepartmentRoleLabels,
  type DepartmentRoleLabels,
} from '@/api/departmentSettings'

const ROLE_ALIASES: Record<string, string> = {
  matwart: 'mw',
  co_matwart: 'cmw',
  depchef: 'dc',
  kommunikation: 'komm',
  sponsoring: 'spon',
  leader1: 'l1',
  leader2: 'l2',
  leader3: 'l3',
  user: 'u',
}

const LEADER_ROLES = ['l1', 'l2', 'l3'] as const

type LeaderRole = (typeof LEADER_ROLES)[number]

function normalizeRole(role: string | null | undefined): string {
  const raw = String(role || '').toLowerCase().trim()
  return ROLE_ALIASES[raw] || raw
}

function isLeaderRole(role: string): role is LeaderRole {
  return (LEADER_ROLES as readonly string[]).includes(role)
}

/**
 * Cached Anzeige-Namen für Department-Rollen (L1–L3 customizable).
 */
export const useDepartmentRoleLabelsStore = defineStore('departmentRoleLabels', () => {
  const labelsByDepartmentId = ref<Record<string, DepartmentRoleLabels>>({})
  const loadingByDepartmentId = ref<Record<string, boolean>>({})
  const loadedDepartmentId = ref<string | null>(null)

  function getCached(departmentId: string | null | undefined): DepartmentRoleLabels {
    if (!departmentId) return { ...EMPTY_DEPARTMENT_ROLE_LABELS }
    return labelsByDepartmentId.value[departmentId] || { ...EMPTY_DEPARTMENT_ROLE_LABELS }
  }

  async function load(departmentId: string | null | undefined): Promise<void> {
    if (!departmentId) return
    if (loadingByDepartmentId.value[departmentId]) return

    loadingByDepartmentId.value = { ...loadingByDepartmentId.value, [departmentId]: true }
    try {
      const labels = await getDepartmentRoleLabels(departmentId)
      labelsByDepartmentId.value = {
        ...labelsByDepartmentId.value,
        [departmentId]: labels,
      }
      loadedDepartmentId.value = departmentId
    } catch (err) {
      console.error('Failed to load department role labels:', err)
      if (!labelsByDepartmentId.value[departmentId]) {
        labelsByDepartmentId.value = {
          ...labelsByDepartmentId.value,
          [departmentId]: { ...EMPTY_DEPARTMENT_ROLE_LABELS },
        }
      }
    } finally {
      loadingByDepartmentId.value = { ...loadingByDepartmentId.value, [departmentId]: false }
    }
  }

  function setLocal(departmentId: string, labels: DepartmentRoleLabels): void {
    labelsByDepartmentId.value = {
      ...labelsByDepartmentId.value,
      [departmentId]: {
        l1: labels.l1.trim(),
        l2: labels.l2.trim(),
        l3: labels.l3.trim(),
      },
    }
  }

  /**
   * Label für eine Rolle: Custom (L1–L3) oder i18n-Fallback.
   * `i18nNamespace` steuert den Fallback-Key (departmentUsers vs adminUsers).
   */
  function labelFor(
    role: string | null | undefined,
    departmentId: string | null | undefined,
    t: (key: string) => string,
    options?: { i18nNamespace?: 'departmentUsers' | 'adminUsers' },
  ): string {
    const code = normalizeRole(role)
    const ns = options?.i18nNamespace || 'departmentUsers'
    const cached = getCached(departmentId)

    if (isLeaderRole(code)) {
      const custom = cached[code]
      if (custom) return custom
    }

    if (code === 'dc' && departmentId) {
      const auth = useAuthStore()
      if (auth.isDepartmentGrossanlass(departmentId)) {
        return t(`settings.${ns}.roles.dcGa`)
      }
    }

    if (['mw', 'cmw', 'dc', 'komm', 'spon', 'l1', 'l2', 'l3', 'u'].includes(code)) {
      return t(`settings.${ns}.roles.${code}`)
    }

    const globalFallback: Record<string, string> = {
      sa: 'Superadmin',
      superadmin: 'Superadmin',
      org: 'Organisationschef',
      organisationschef: 'Organisationschef',
      sub: 'Suborgchef',
      suborgchef: 'Suborgchef',
    }
    if (globalFallback[code]) return globalFallback[code]

    return code || String(role || '')
  }

  return {
    labelsByDepartmentId,
    loadedDepartmentId,
    getCached,
    load,
    setLocal,
    labelFor,
    normalizeRole,
  }
})
