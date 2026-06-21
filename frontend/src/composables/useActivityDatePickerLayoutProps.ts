import { computed } from 'vue'
import { useSmAndUp } from '@/composables/useSmAndUp'

/** Mobile Bottom Sheet: VDatePicker-Breite 100% statt Vuetify-Default 328px */
export function useActivityDatePickerLayoutProps() {
  const smAndUp = useSmAndUp()
  const pickerWidth = computed(() => (smAndUp.value ? undefined : '100%'))
  return { pickerWidth }
}
