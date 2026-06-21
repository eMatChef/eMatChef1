import { computed } from 'vue'
import { useSmAndUp } from '@/composables/useSmAndUp'

/** Fullscreen auf Mobile, zentriertes Modal ab sm (600px). */
export function useMaterialJourneySheetDialog() {
  const smAndUp = useSmAndUp()

  const sheetFullscreen = computed(() => !smAndUp.value)
  const sheetMaxWidth = computed(() => (smAndUp.value ? 720 : undefined))

  return {
    sheetFullscreen,
    sheetMaxWidth,
  }
}
