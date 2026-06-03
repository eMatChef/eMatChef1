<template>
  <div
    class="e-form-field autosave-field e-otp-field"
    :class="{ 'e-form-field--error': hasError, 'is-error': hasError }"
  >
    <div class="autosave-control">
      <div class="autosave-field-frame e-otp-field__frame">
        <label v-if="label" class="field-outline-label autosave-label" :for="fieldId">{{ label }}</label>
        <v-otp-input
          :id="fieldId"
          :model-value="model"
          :length="length"
          type="text"
          variant="outlined"
          density="comfortable"
          :disabled="disabled"
          :autofocus="autofocus"
          class="e-otp-input"
          @update:model-value="onUpdate"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue'
import '@/components/form/base/e-form-field.css'
import '@/components/form/base/e-otp-input.css'

defineOptions({ name: 'EOtpInput' })

const props = withDefaults(
  defineProps<{
    id?: string
    label?: string
    length?: number
    disabled?: boolean
    autofocus?: boolean
    errorMessages?: string | readonly string[]
  }>(),
  {
    length: 6,
    autofocus: false,
  }
)

const model = defineModel<string>({ default: '' })
const generatedId = useId()
const fieldId = computed(() => props.id ?? generatedId)

const hasError = computed(() => {
  if (!props.errorMessages) return false
  const messages = Array.isArray(props.errorMessages) ? props.errorMessages : [props.errorMessages]
  return messages.some(Boolean)
})

function onUpdate(value: string | null) {
  const cleaned = String(value ?? '')
    .toUpperCase()
    .replace(/[^0-9A-F]/g, '')
    .slice(0, props.length)
  model.value = cleaned
}
</script>
