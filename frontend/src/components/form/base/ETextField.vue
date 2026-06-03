<template>
  <div
    class="e-form-field autosave-field"
    :class="{ 'e-form-field--error': hasError, 'is-error': hasError }"
  >
    <div class="autosave-control">
      <div class="autosave-field-frame">
        <label v-if="label" class="field-outline-label autosave-label" :for="fieldId">{{ label }}</label>
        <v-text-field
          variant="outlined"
          density="comfortable"
          v-bind="passthroughAttrs"
          :id="fieldId"
          :model-value="model"
          :type="type"
          :placeholder="placeholder"
          :hint="hint"
          :persistent-hint="persistentHint"
          :rules="rules"
          :error-messages="errorMessages"
          :disabled="disabled"
          :readonly="readonly"
          :autocomplete="autocomplete"
          :hide-details="hideDetails"
          class="e-text-field"
          @update:model-value="onUpdate"
        >
          <template v-if="$slots.prepend" #prepend>
            <slot name="prepend" />
          </template>
          <template v-if="$slots.append" #append>
            <slot name="append" />
          </template>
          <template v-if="$slots['append-inner']" #append-inner>
            <slot name="append-inner" />
          </template>
          <template v-if="$slots['prepend-inner']" #prepend-inner>
            <slot name="prepend-inner" />
          </template>
          <template v-if="$slots.message" #message="messageProps">
            <slot name="message" v-bind="messageProps" />
          </template>
        </v-text-field>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue'

defineOptions({ inheritAttrs: false, name: 'ETextField' })

const props = withDefaults(
  defineProps<{
    id?: string
    label?: string
    type?: string
    placeholder?: string
    hint?: string
    persistentHint?: boolean
    rules?: readonly ((value: unknown) => boolean | string)[]
    errorMessages?: string | readonly string[]
    disabled?: boolean
    readonly?: boolean
    autocomplete?: string
    hideDetails?: boolean | 'auto'
  }>(),
  {
    type: 'text',
    hideDetails: 'auto',
  }
)

const model = defineModel<string | number | null>({ default: null })
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

function onUpdate(value: string | number | null) {
  model.value = value
}
</script>
