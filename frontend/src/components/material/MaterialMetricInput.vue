<template>
  <div :class="hideLabel ? undefined : 'form-group'">
    <label v-if="!hideLabel">{{ label }}</label>
    <div class="material-metric-input">
      <div
        class="input-with-suffix input-with-suffix--fluid"
        :class="{ 'autosave-input-wrap': autosaveStyle }"
      >
        <input
          :id="inputId"
          :value="displayValue"
          type="number"
          min="0"
          step="any"
          inputmode="decimal"
          :class="inputClass"
          :disabled="disabled"
          :aria-label="ariaLabel ?? `${label} (${displayUnitLabel})`"
          @input="onInput"
          @focus="emit('focus')"
          @blur="emit('blur')"
        />
        <span
          class="input-suffix"
          :class="{ 'autosave-suffix': autosaveStyle }"
          aria-hidden="true"
        >{{ displayUnitLabel }}</span>
      </div>
      <button
        v-if="showGramToggle"
        type="button"
        class="material-metric-input__unit-toggle"
        :disabled="disabled"
        @click="toggleInputUnit"
      >
        {{ inputUnit === 'kg' ? t('components.materialMetricInput.enterGrams') : t('components.materialMetricInput.enterKg') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  gramsToKgString,
  kgToGramsDisplay,
  type MaterialMetricUnit,
} from '@/utils/materialMetricUnits'

const props = withDefaults(
  defineProps<{
    modelValue: string
    label: string
    unit: MaterialMetricUnit
    ariaLabel?: string
    hideLabel?: boolean
    inputId?: string
    disabled?: boolean
    allowGramInput?: boolean
    autosaveStyle?: boolean
    inputClass?: string
  }>(),
  {
    hideLabel: false,
    disabled: false,
    allowGramInput: undefined,
    autosaveStyle: false,
    inputClass: 'form-input',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  focus: []
  blur: []
}>()

const { t } = useI18n()
const inputUnit = ref<'kg' | 'g'>('kg')

const showGramToggle = computed(
  () => (props.allowGramInput ?? props.unit === 'kg') && props.unit === 'kg',
)

const displayUnitLabel = computed(() =>
  showGramToggle.value && inputUnit.value === 'g' ? 'g' : props.unit,
)

const displayValue = computed(() => {
  if (!showGramToggle.value || inputUnit.value === 'kg') {
    return props.modelValue
  }
  return kgToGramsDisplay(props.modelValue)
})

watch(
  () => props.modelValue,
  (value) => {
    if (!value && inputUnit.value === 'g') {
      inputUnit.value = 'kg'
    }
  },
)

function toggleInputUnit(): void {
  inputUnit.value = inputUnit.value === 'kg' ? 'g' : 'kg'
}

function onInput(event: Event): void {
  const raw = (event.target as HTMLInputElement).value
  if (showGramToggle.value && inputUnit.value === 'g') {
    emit('update:modelValue', gramsToKgString(raw) ?? '')
    return
  }
  emit('update:modelValue', raw)
}
</script>

<style scoped>
.material-metric-input {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;
}

.material-metric-input__unit-toggle {
  align-self: flex-start;
  padding: 4px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #f8fafc;
  font: inherit;
  font-size: 12px;
  color: #475569;
  cursor: pointer;
}

.material-metric-input__unit-toggle:hover:not(:disabled) {
  background: #f1f5f9;
  border-color: #94a3b8;
}

.material-metric-input__unit-toggle:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
