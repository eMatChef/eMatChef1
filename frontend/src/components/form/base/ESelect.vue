<template>
  <div
    class="e-form-field autosave-field"
    :class="{ 'e-form-field--error': hasError, 'is-error': hasError }"
  >
    <div class="autosave-control">
      <div class="autosave-field-frame">
        <label v-if="label" class="field-outline-label autosave-label" :for="fieldId">{{ label }}</label>
        <v-select
          variant="outlined"
          density="comfortable"
          v-bind="passthroughAttrs"
          :id="fieldId"
          :model-value="model"
          :items="items"
          :placeholder="placeholder"
          :hint="hint"
          :persistent-hint="persistentHint"
          :rules="rules"
          :error-messages="errorMessages"
          :disabled="disabled"
          :readonly="readonly"
          :multiple="multiple"
          :clearable="clearable"
          :hide-details="hideDetails"
          :menu-props="mergedMenuProps"
          class="e-select"
          @update:model-value="onUpdate"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue'

defineOptions({ inheritAttrs: false, name: 'ESelect' })

const props = withDefaults(
  defineProps<{
    id?: string
    label?: string
    items?: readonly unknown[]
    placeholder?: string
    hint?: string
    persistentHint?: boolean
    rules?: readonly ((value: unknown) => boolean | string)[]
    errorMessages?: string | readonly string[]
    disabled?: boolean
    readonly?: boolean
    multiple?: boolean
    clearable?: boolean
    hideDetails?: boolean | 'auto'
    menuProps?: Record<string, unknown>
  }>(),
  {
    items: () => [],
    hideDetails: 'auto',
  }
)

const model = defineModel<unknown>({ default: null })
const attrs = useAttrs()
const generatedId = useId()
const fieldId = computed(() => props.id ?? generatedId)

const passthroughAttrs = computed(() => {
  const { variant: _variant, ...rest } = attrs
  return rest
})

const mergedMenuProps = computed(() => {
  const fromProps = props.menuProps ?? {}
  const contentClass = [fromProps.contentClass, 'onboarding-tour-menu-union']
    .flat()
    .filter(Boolean)
    .join(' ')
  return {
    maxHeight: 280,
    zIndex: 2500,
    ...fromProps,
    contentClass,
  }
})

const hasError = computed(() => {
  if (props.errorMessages) {
    const messages = Array.isArray(props.errorMessages) ? props.errorMessages : [props.errorMessages]
    if (messages.some(Boolean)) return true
  }
  return false
})

function onUpdate(value: unknown) {
  model.value = value
}
</script>
