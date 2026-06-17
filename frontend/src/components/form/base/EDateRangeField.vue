<template>
  <div
    class="e-form-field autosave-field e-date-range-field"
    :class="{ 'e-form-field--error': hasError, 'is-error': hasError }"
  >
    <div class="autosave-control">
      <div class="autosave-field-frame">
        <label v-if="label" class="field-outline-label autosave-label" :for="fieldId">{{ label }}</label>
        <ActivityDateRangeField
          v-model="rangeModel"
          class="e-date-range-field__picker"
          :department-id="departmentId"
          :disabled="disabled"
          density="comfortable"
          :allow-past="allowPast"
          :block-closed-dates="blockClosedDates"
          :show-presets="showPresets"
          :show-markers="showMarkers"
          :preset-mode="presetMode"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue'
import ActivityDateRangeField from '@/components/activities/wizard/ActivityDateRangeField.vue'
import { isoDateStringToLocalDate, localDateToIsoDateString } from '@/utils/activityDateIso'
import '@/styles/components/activity-datetime-field.css'

defineOptions({ inheritAttrs: false, name: 'EDateRangeField' })

const props = withDefaults(
  defineProps<{
    id?: string
    label?: string
    departmentId?: string | null
    disabled?: boolean
    allowPast?: boolean
    blockClosedDates?: boolean
    showPresets?: boolean
    showMarkers?: boolean
    presetMode?: 'range' | 'fixed-periods'
    errorMessages?: string | readonly string[]
  }>(),
  {
    departmentId: null,
    disabled: false,
    allowPast: true,
    blockClosedDates: false,
    showPresets: false,
    showMarkers: true,
    presetMode: 'range',
  },
)

const start = defineModel<string>('start', { default: '' })
const end = defineModel<string>('end', { default: '' })

const generatedId = useId()
const fieldId = computed(() => props.id ?? generatedId)

const hasError = computed(() => {
  const m = props.errorMessages
  if (!m) return false
  return Array.isArray(m) ? m.length > 0 : m.length > 0
})

const rangeModel = computed({
  get(): [Date, Date] | null {
    const s = isoDateStringToLocalDate(start.value)
    const e = isoDateStringToLocalDate(end.value)
    if (!s && !e) return null
    if (s && e) {
      return s.getTime() <= e.getTime() ? [s, e] : [e, s]
    }
    const one = s ?? e!
    return [one, one]
  },
  set(range: [Date, Date] | null) {
    if (!range?.[0] || !range[1]) {
      start.value = ''
      end.value = ''
      return
    }
    const [a, b] = range[0].getTime() <= range[1].getTime() ? range : [range[1], range[0]]
    start.value = localDateToIsoDateString(a)
    end.value = localDateToIsoDateString(b)
  },
})
</script>

<style scoped>
.e-date-range-field__picker.activity-date-range-field-wrap {
  width: 100%;
}
</style>
