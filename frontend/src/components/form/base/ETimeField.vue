<template>
  <div
    class="e-form-field autosave-field e-time-field"
    :class="{ 'e-form-field--error': hasError, 'is-error': hasError }"
  >
    <div class="autosave-control">
      <div class="autosave-field-frame">
        <label v-if="label" class="field-outline-label autosave-label" :for="fieldId">{{ label }}</label>
        <ActivityTimeField
          v-model="timeModel"
          class="e-time-field__picker"
          density="comfortable"
          :aria-label="label || ariaLabel"
          :disabled="disabled"
        />
      </div>
    </div>
    <p v-if="hint" class="e-time-field__hint">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue'
import ActivityTimeField from '@/components/activities/wizard/ActivityTimeField.vue'
import { dayAtTime, normalizeDepartmentTimeHHMM } from '@/utils/activityPlanningFromDefaults'
import '@/styles/components/activity-datetime-field.css'

defineOptions({ inheritAttrs: false, name: 'ETimeField' })

/** Referenztag nur für VTimePicker (Modell bleibt HH:mm). */
const TIME_ANCHOR = new Date(2000, 0, 1)

const props = withDefaults(
  defineProps<{
    id?: string
    label?: string
    hint?: string
    ariaLabel?: string
    disabled?: boolean
    errorMessages?: string | readonly string[]
  }>(),
  { disabled: false },
)

const model = defineModel<string>({ default: '00:00' })
const generatedId = useId()
const fieldId = computed(() => props.id ?? generatedId)

const hasError = computed(() => {
  const m = props.errorMessages
  if (!m) return false
  return Array.isArray(m) ? m.length > 0 : m.length > 0
})

const timeModel = computed({
  get(): Date {
    const raw = model.value?.trim() || '00:00'
    return dayAtTime(TIME_ANCHOR, raw)
  },
  set(d: Date | null) {
    if (!d || !Number.isFinite(d.getTime())) return
    const hh = String(d.getHours()).padStart(2, '0')
    const mm = String(d.getMinutes()).padStart(2, '0')
    model.value = normalizeDepartmentTimeHHMM(`${hh}:${mm}`)
  },
})
</script>

<style scoped>
.e-time-field__picker.activity-v-time-picker-field {
  width: 100%;
}

.e-time-field__hint {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--color-text-muted, #6b7280);
}
</style>
