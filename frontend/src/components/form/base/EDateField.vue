<template>
  <div
    class="e-form-field autosave-field e-date-field"
    :class="{ 'e-form-field--error': hasError, 'is-error': hasError }"
  >
    <div class="autosave-control">
      <div class="autosave-field-frame">
        <label v-if="label" class="field-outline-label autosave-label" :for="fieldId">{{ label }}</label>
        <ActivityDateField
          v-model="dateModel"
          class="e-date-field__picker"
          :department-id="departmentId"
          :disabled="disabled"
          density="comfortable"
          :allow-past="allowPast"
          :block-closed-dates="blockClosedDates"
          :show-presets="showPresets"
          :show-markers="showMarkers"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue'
import ActivityDateField from '@/components/activities/wizard/ActivityDateField.vue'
import { isoDateStringToLocalDate, localDateToIsoDateString } from '@/utils/activityDateIso'
import '@/styles/components/activity-datetime-field.css'

defineOptions({ inheritAttrs: false, name: 'EDateField' })

const props = withDefaults(
  defineProps<{
    id?: string
    label?: string
    departmentId?: string | null
    disabled?: boolean
    /** API-Format yyyy-mm-dd */
    allowPast?: boolean
    blockClosedDates?: boolean
    showPresets?: boolean
    showMarkers?: boolean
    errorMessages?: string | readonly string[]
  }>(),
  {
    departmentId: null,
    disabled: false,
    allowPast: true,
    blockClosedDates: false,
    showPresets: false,
    showMarkers: true,
  },
)

const model = defineModel<string>({ default: '' })
const generatedId = useId()
const fieldId = computed(() => props.id ?? generatedId)

const hasError = computed(() => {
  const m = props.errorMessages
  if (!m) return false
  return Array.isArray(m) ? m.length > 0 : m.length > 0
})

const dateModel = computed({
  get: () => isoDateStringToLocalDate(model.value),
  set: (d) => {
    model.value = localDateToIsoDateString(d)
  },
})
</script>

<style scoped>
.e-date-field__picker.activity-date-field-wrap {
  width: 100%;
}
</style>
