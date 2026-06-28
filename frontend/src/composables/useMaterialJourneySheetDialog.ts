import { computed } from 'vue'
import { useSmAndUp } from '@/composables/useSmAndUp'

/** Fullscreen auf Mobile, zentriertes Modal ab sm (600px). */
export function useMaterialJourneySheetDialog(options?: { maxWidth?: number }) {
  const smAndUp = useSmAndUp()
  const maxWidth = options?.maxWidth ?? 720

  const sheetFullscreen = computed(() => !smAndUp.value)
  const sheetMaxWidth = computed(() => (smAndUp.value ? maxWidth : undefined))

  return {
    sheetFullscreen,
    sheetMaxWidth,
  }
}
