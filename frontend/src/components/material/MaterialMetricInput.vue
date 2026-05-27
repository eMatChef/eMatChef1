<template>
  <div class="form-group">
    <label>{{ label }}</label>
    <div class="input-with-suffix input-with-suffix--fluid">
      <input
        :value="modelValue"
        type="number"
        min="0"
        step="any"
        inputmode="decimal"
        class="form-input"
        :aria-label="ariaLabel ?? `${label} (${unit})`"
        @input="onInput"
      />
      <span class="input-suffix" aria-hidden="true">{{ unit }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { MaterialMetricUnit } from '@/utils/materialMetricUnits'

defineProps<{
  modelValue: string
  label: string
  unit: MaterialMetricUnit
  ariaLabel?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

function onInput(event: Event) {
  const el = event.target as HTMLInputElement
  emit('update:modelValue', el.value)
}
</script>
