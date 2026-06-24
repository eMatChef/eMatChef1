<template>
  <AutoSaveField
    :model-value="modelValue"
    :baseline="baseline"
    :label="label"
    type="number"
    :span-class="spanClass"
    :save="save"
    @update:model-value="emit('update:modelValue', String($event ?? ''))"
    @saved="emit('saved', String($event ?? ''))"
  >
    <template #default="{ inputId, disabled, onFocus, onBlur, onChange }">
      <MaterialMetricInput
        :model-value="modelValue"
        :label="label"
        unit="kg"
        hide-label
        autosave-style
        input-class="autosave-input"
        :input-id="inputId"
        :disabled="disabled"
        @update:model-value="onValueUpdate($event, onChange)"
        @focus="onFocus"
        @blur="onBlur"
      />
    </template>
  </AutoSaveField>
</template>

<script setup lang="ts">
import AutoSaveField from '@/components/common/autoSave/AutoSaveField.vue'
import MaterialMetricInput from '@/components/material/MaterialMetricInput.vue'
import type { AutoSaveFieldSaveFn } from '@/components/common/autoSave/types'

defineProps<{
  modelValue: string
  baseline?: string
  label: string
  spanClass?: string
  save: AutoSaveFieldSaveFn
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  saved: [value: string]
}>()

function onValueUpdate(value: string, onChange: () => void): void {
  emit('update:modelValue', value)
  onChange()
}
</script>
