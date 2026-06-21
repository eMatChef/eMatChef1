import { onBeforeUnmount, watch, type ComponentPublicInstance, type MaybeRefOrGetter, type Ref, toValue } from 'vue'

const DEFAULT_INSIDE_SELECTOR =
  '.activity-date-picker-menu, .activity-date-picker-bottom-sheet__content, .activity-date-picker-menu__shell'

function resolveActivatorEl(
  activator: ComponentPublicInstance | HTMLElement | null | undefined,
): HTMLElement | null {
  if (!activator) return null
  if (activator instanceof HTMLElement) return activator
  const el = (activator as ComponentPublicInstance).$el
  return el instanceof HTMLElement ? el : null
}

/**
 * Schliesst Activity-Picker bei Klick/Tipp ausserhalb — auch innerhalb eines persistent v-dialog
 * (Vuetify zählt dort alle .v-overlay__content als «innen»).
 */
export function useActivityPickerOutsideClose(options: {
  open: Ref<boolean>
  activator?: MaybeRefOrGetter<ComponentPublicInstance | HTMLElement | null | undefined>
  insideSelector?: string
}) {
  const insideSelector = options.insideSelector ?? DEFAULT_INSIDE_SELECTOR

  function isInsidePicker(target: Node): boolean {
    return Array.from(document.querySelectorAll(insideSelector)).some((el) => el.contains(target))
  }

  function onPointerDownOutside(e: PointerEvent) {
    const target = e.target
    if (!(target instanceof Node)) return

    const activatorEl = resolveActivatorEl(toValue(options.activator))
    if (activatorEl?.contains(target)) return
    if (isInsidePicker(target)) return

    options.open.value = false
  }

  watch(
    () => options.open.value,
    (isOpen) => {
      if (isOpen) {
        requestAnimationFrame(() => {
          document.addEventListener('pointerdown', onPointerDownOutside, true)
        })
      } else {
        document.removeEventListener('pointerdown', onPointerDownOutside, true)
      }
    },
    { flush: 'post' },
  )

  onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDownOutside, true)
  })

  function closeOnOutside() {
    options.open.value = false
  }

  return { closeOnOutside }
}
