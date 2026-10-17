import { onBeforeUnmount, watch, type Ref } from 'vue'

/**
 * Hält höchstens ein Dropdown/Kalender/Popover offen: Öffnet ein Overlay, schliessen sich alle zuvor
 * registrierten. `closeAllExclusiveOverlays()` schliesst alle (z. B. beim Reiterwechsel), ohne Formularwerte zu berühren.
 */
const closers = new Map<symbol, () => void>()

export function closeAllExclusiveOverlays(exceptId?: symbol): void {
  for (const [id, close] of closers) {
    if (id !== exceptId) close()
  }
}

export function useExclusiveOverlay(isOpen: Ref<boolean>): void {
  const id = Symbol('overlay')
  closers.set(id, () => {
    if (isOpen.value) isOpen.value = false
  })
  watch(isOpen, (open) => {
    if (open) closeAllExclusiveOverlays(id)
  })
  onBeforeUnmount(() => closers.delete(id))
}
