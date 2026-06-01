import { computed, type MaybeRefOrGetter, toValue } from 'vue'

/** Menü/Overlay für VDateInput — Wizard-Modal vs. Detail (`body`). */
export function useActivityDateMenuProps(teleportTo: MaybeRefOrGetter<string>) {
  return computed(() => {
    const target = toValue(teleportTo)
    if (target === 'body') {
      return {
        attach: 'body' as const,
        zIndex: 2400,
        contentClass: 'activity-date-picker-menu',
      }
    }
    return {
      zIndex: 2400,
      contentClass: 'activity-date-picker-menu',
    }
  })
}
