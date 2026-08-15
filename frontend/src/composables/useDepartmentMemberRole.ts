import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

/**
 * Department-Rollen mit Basissicht wie «u» (ohne MW/DC-Rechte).
 * L1–L3: gleiche Menüs, Aktivitäten-Filter, Einstellungen — Rechte später per Matrix.
 */
export const DEPARTMENT_BASIC_MEMBER_ROLES = ['u', 'user', 'coach', 'l1', 'l2', 'l3'] as const

/** MW/DC — u. a. Fixe Daten verwalten und sehen. */
export const DEPARTMENT_MW_DC_ROLES = ['mw', 'matwart', 'dc', 'depchef'] as const

export function isDepartmentMwOrDcRole(role: string | null | undefined): boolean {
  return DEPARTMENT_MW_DC_ROLES.includes(
    String(role || '')
      .toLowerCase()
      .trim() as (typeof DEPARTMENT_MW_DC_ROLES)[number],
  )
}

export function isDepartmentBasicMemberRole(role: string | null | undefined): boolean {
  return DEPARTMENT_BASIC_MEMBER_ROLES.includes(
    String(role || '')
      .toLowerCase()
      .trim() as (typeof DEPARTMENT_BASIC_MEMBER_ROLES)[number],
  )
}

/** Department-Leiter L1–L3 (ohne Gruppenchef ★). */
export function isDepartmentLeaderRole(role: string | null | undefined): boolean {
  return ['l1', 'l2', 'l3'].includes(String(role || '').toLowerCase().trim())
}

/** Depchef (DC) — Einlagern / Aktivität abschliessen. */
export function isDepartmentDcRole(role: string | null | undefined): boolean {
  return ['dc', 'depchef'].includes(String(role || '').toLowerCase().trim())
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

  /** MW/DC: Material anlegen, Druckkorb, QR-Kontakt-Verwaltung, Fixe Daten, … */
  const canManageMaterials = computed(() => isDepartmentMwOrDcRole(departmentRole.value))

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
export const USER_CONTACT_VIEW_TYPES = ['general', 'storage', 'event', 'meeting', 'event_delivery', 'event_poi'] as const

/** Kontakte: User-Rolle darf nur diese Typen anlegen/bearbeiten/löschen. */
export const USER_CONTACT_CREATE_TYPES = ['meeting', 'event', 'event_delivery', 'event_poi'] as const

/** @deprecated Alias – bitte USER_CONTACT_VIEW_TYPES verwenden */
export const USER_CONTACT_ADDRESS_TYPES = USER_CONTACT_VIEW_TYPES

export function canUserManageContactType(type: string, isUser: boolean): boolean {
  if (!isUser) return true
  return USER_CONTACT_CREATE_TYPES.includes(type as (typeof USER_CONTACT_CREATE_TYPES)[number])
}
