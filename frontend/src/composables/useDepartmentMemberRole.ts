import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

/**
 * Department-Rollen mit Basissicht wie «u» (ohne MW/DC-Rechte).
 * L1–L3: gleiche Menüs, Aktivitäten-Filter, Einstellungen — Rechte später per Matrix.
 */
export const DEPARTMENT_BASIC_MEMBER_ROLES = ['u', 'user', 'l1', 'l2', 'l3'] as const

export function isDepartmentBasicMemberRole(role: string | null | undefined): boolean {
  return DEPARTMENT_BASIC_MEMBER_ROLES.includes(
    String(role || '')
      .toLowerCase()
      .trim() as (typeof DEPARTMENT_BASIC_MEMBER_ROLES)[number],
  )
}

/** Department-Mitgliedschaftsrolle (u, l1–l3, mw, dc, …). */
export function useDepartmentMemberRole() {
  const authStore = useAuthStore()

  const departmentRole = computed(() =>
    String(authStore.currentDepartmentRole || 'u').toLowerCase().trim()
  )

  const isUserRole = computed(() => isDepartmentBasicMemberRole(departmentRole.value))

  /** Leiter 1–3 (Department-Ebene, nicht Gruppenchef ★). */
  const isDepartmentLeader = computed(() => ['l1', 'l2', 'l3'].includes(departmentRole.value))

  /** Aktivität für ganze Abteilung (group_id leer) oder konkrete Gruppe wählen. */
  const canSelectDepartmentGroupLevel = computed(
    () =>
      isDepartmentLeader.value ||
      ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value),
  )

  /** Nur MW: Vorlagen-Import/Export (Department). */
  const isMaterialwart = computed(() =>
    ['mw', 'matwart'].includes(departmentRole.value),
  )

  /** MW/DC: Material anlegen, Druckkorb, QR-Kontakt-Verwaltung, … */
  const canManageMaterials = computed(() =>
    ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value)
  )

  /** QR-Kontakt / Abteilungs-Druckkorb: nicht für reine User-Rolle */
  const canManageQrContact = computed(() => !isUserRole.value)

  /** Kontakte: volle Verwaltung nur MW/DC (L1–L3 = Basissicht wie u) */
  const canManageContacts = computed(() => !isUserRole.value)

  /** User darf Treffpunkt & Eventstandort anlegen */
  const canUserCreateContacts = computed(() => isUserRole.value)

  /** MW/DC: Papierkorb, Wiederherstellen, endgültig löschen */
  const canManageDeletedContacts = computed(() =>
    ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value)
  )

  return {
    departmentRole,
    isUserRole,
    isDepartmentLeader,
    canSelectDepartmentGroupLevel,
    canManageMaterials,
    isMaterialwart,
    canManageQrContact,
    canManageContacts,
    canUserCreateContacts,
    canManageDeletedContacts,
  }
}

/** Kontakte: User-Rolle darf diese Typen sehen (Lesen). */
export const USER_CONTACT_VIEW_TYPES = ['general', 'storage', 'event', 'meeting'] as const

/** Kontakte: User-Rolle darf nur diese Typen anlegen/bearbeiten/löschen. */
export const USER_CONTACT_CREATE_TYPES = ['meeting', 'event'] as const

/** @deprecated Alias – bitte USER_CONTACT_VIEW_TYPES verwenden */
export const USER_CONTACT_ADDRESS_TYPES = USER_CONTACT_VIEW_TYPES

export function canUserManageContactType(type: string, isUser: boolean): boolean {
  if (!isUser) return true
  return USER_CONTACT_CREATE_TYPES.includes(type as (typeof USER_CONTACT_CREATE_TYPES)[number])
}
