import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

/** Department-Mitgliedschaftsrolle (u, l1–l3, mw, dc, …). */
export function useDepartmentMemberRole() {
  const authStore = useAuthStore()

  const departmentRole = computed(() =>
    String(authStore.currentDepartmentRole || 'u').toLowerCase().trim()
  )

  const isUserRole = computed(() => ['u', 'user'].includes(departmentRole.value))

  /** MW/DC: Material anlegen, Druckkorb, QR-Kontakt-Verwaltung, … */
  const canManageMaterials = computed(() =>
    ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value)
  )

  /** QR-Kontakt / Abteilungs-Druckkorb: nicht für reine User-Rolle */
  const canManageQrContact = computed(() => !isUserRole.value)

  /** Kontakte: MW/DC/L1–L3 volle Verwaltung */
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
    canManageMaterials,
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
