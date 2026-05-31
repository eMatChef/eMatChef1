<template>
  <div
    class="e-form-field e-form-field--textarea autosave-field"
    :class="{ 'e-form-field--error': hasError, 'is-error': hasError }"
  >
    <div class="autosave-control">
      <div class="autosave-field-frame">
        <label v-if="label" class="field-outline-label autosave-label" :for="fieldId">{{ label }}</label>
        <v-textarea
          variant="outlined"
          density="comfortable"
          v-bind="passthroughAttrs"
          :id="fieldId"
          :model-value="model"
          :placeholder="placeholder"
          :hint="hint"
          :persistent-hint="persistentHint"
          :rules="rules"
          :error-messages="errorMessages"
          :disabled="disabled"
          :readonly="readonly"
          :rows="rows"
          :auto-grow="autoGrow"
          :hide-details="hideDetails"
          class="e-textarea"
          @update:model-value="onUpdate"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue'

defineOptions({ inheritAttrs: false, name: 'ETextarea' })

const props = withDefaults(
  defineProps<{
    id?: string
    label?: string
    placeholder?: string
    hint?: string
    persistentHint?: boolean
    rules?: readonly ((value: unknown) => boolean | string)[]
    errorMessages?: string | readonly string[]
    disabled?: boolean
    readonly?: boolean
    rows?: number | string
    autoGrow?: boolean
    hideDetails?: boolean | 'auto'
  }>(),
  {
    rows: 3,
    hideDetails: 'auto',
  }
)

const model = defineModel<string | null>({ default: null })
const attrs = useAttrs()
const generatedId = useId()
const fieldId = computed(() => props.id ?? generatedId)

const passthroughAttrs = computed(() => {
  const { variant: _variant, ...rest } = attrs
  return rest
})

const hasError = computed(() => {
  if (props.errorMessages) {
    const messages = Array.isArray(props.errorMessages) ? props.errorMessages : [props.errorMessages]
    if (messages.some(Boolean)) return true
  }
  return false
})

function onUpdate(value: string | null) {
  model.value = value
}
</script>
