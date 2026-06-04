<template>
  <v-checkbox
    v-bind="attrs"
    :id="id"
    :model-value="model"
    :label="label"
    :hint="hint"
    :persistent-hint="persistentHint"
    :rules="rules"
    :disabled="disabled"
    :readonly="readonly"
    :hide-details="hideDetails"
    color="primary"
    class="e-checkbox"
    @update:model-value="onUpdate"
  />
</template>

<script setup lang="ts">
import { useAttrs } from 'vue'

defineOptions({ inheritAttrs: false, name: 'ECheckbox' })

withDefaults(
  defineProps<{
    id?: string
    label?: string
    hint?: string
    persistentHint?: boolean
    rules?: readonly ((value: unknown) => boolean | string)[]
    disabled?: boolean
    readonly?: boolean
    hideDetails?: boolean | 'auto'
  }>(),
  {
    hideDetails: 'auto',
  }
)

const model = defineModel<boolean | null>({ default: null })
const attrs = useAttrs()

function onUpdate(value: boolean | null) {
  model.value = value
}
</script>
