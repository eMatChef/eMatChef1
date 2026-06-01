import { computed, type MaybeRefOrGetter } from 'vue'

/** Über v-dialog / Wizard — VDateInput-Menü an body (Vuetify-Standard). */
export const ACTIVITY_PICKER_MENU_Z_INDEX = 10100

/** `menu-props` für labs/VDateInput (siehe Vuetify-Doku). */
export function useActivityDateMenuProps(_teleportTo?: MaybeRefOrGetter<string>) {
  void _teleportTo
  return computed(() => ({
    attach: 'body' as const,
    zIndex: ACTIVITY_PICKER_MENU_Z_INDEX,
    contentClass: 'activity-date-picker-menu',
  }))
}

export function useActivityTimeMenuProps(_teleportTo?: MaybeRefOrGetter<string>) {
  void _teleportTo
  return useActivityDateMenuProps(_teleportTo)
}
