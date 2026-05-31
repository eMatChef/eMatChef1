<template>
  <AutoSaveFieldShell
    :input-id="inputId"
    :label="label"
    :show-label="type !== 'checkbox'"
    :span-class="spanClass"
    :status="status"
    :is-saving="isSaving"
    :is-pending="isPreSaving"
    :show-success-icon="showSuccessIcon"
    :is-focused="isFocused"
    :is-dirty="isDirty"
    :has-display-value="hasDisplayValue"
    :disabled="disabled"
    :error-message="errorMessage"
    :saved-label="t('common.autoSaveField.saved')"
    :retry-label="t('common.autoSaveField.retry')"
    :cancel-label="t('common.autoSaveField.cancel')"
    @retry="trySave"
    @cancel="onCancel"
  >
    <slot
      :input-id="inputId"
      :disabled="disabled"
      input-class="autosave-input"
      :on-focus="handleFocus"
      :on-blur="onBlur"
      :on-input="onSlotInput"
      :on-change="onCustomChange"
    >
      <textarea
        v-if="type === 'textarea'"
        :id="inputId"
        :value="stringValue"
        :disabled="disabled"
        :rows="rows"
        :placeholder="placeholder"
        class="autosave-input autosave-textarea"
        @focus="handleFocus"
        @blur="onBlur"
        @input="onNativeInput"
      />
      <select
        v-else-if="type === 'select'"
        :id="inputId"
        :value="stringValue"
        :disabled="disabled"
        class="autosave-input autosave-select"
        @focus="handleFocus"
        @blur="onBlur"
        @change="onSelectChange"
      >
        <option v-for="opt in options" :key="String(opt.value)" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>
      <label v-else-if="type === 'checkbox'" class="autosave-checkbox-wrap">
        <input
          :id="inputId"
          type="checkbox"
          :checked="!!modelValue"
          :disabled="disabled"
          @focus="handleFocus"
          @blur="onBlur"
          @change="onCheckboxChange"
        />
        <span v-if="checkboxLabel" class="autosave-checkbox-text">{{ checkboxLabel }}</span>
      </label>
      <div v-else class="autosave-input-wrap">
        <input
          :id="inputId"
          :type="type === 'number' ? 'number' : type === 'date' ? 'date' : 'text'"
          :value="stringValue"
          :disabled="disabled"
          :placeholder="placeholder"
          :min="min"
          :max="max"
          :step="step"
          class="autosave-input"
          @focus="handleFocus"
          @blur="onBlur"
          @input="onNativeInput"
        />
        <span v-if="suffix" class="autosave-suffix">{{ suffix }}</span>
      </div>
    </slot>
  </AutoSaveFieldShell>
</template>

<script setup lang="ts">
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAutoSaveField } from '@/composables/useAutoSaveField'
import AutoSaveFieldShell from '@/components/common/autoSave/AutoSaveFieldShell.vue'
import type {
  AutoSaveFieldSaveFn,
  AutoSaveFieldType,
  AutoSaveFieldValue,
  AutoSaveSelectOption,
} from '@/components/common/autoSave/types'

const props = withDefaults(
  defineProps<{
    modelValue: AutoSaveFieldValue
    /** Letzter DB-Stand – bei externem Reload via `:baseline` aktualisieren */
    baseline?: AutoSaveFieldValue
    label: string
    type?: AutoSaveFieldType
    disabled?: boolean
    placeholder?: string
    suffix?: string
    checkboxLabel?: string
    rows?: number
    min?: number
    max?: number
    step?: number | string
    options?: AutoSaveSelectOption[]
    spanClass?: string
    autoSaveDelay?: number
    save: AutoSaveFieldSaveFn
  }>(),
  {
    baseline: undefined,
    type: 'text',
    disabled: false,
    placeholder: '',
    suffix: '',
    checkboxLabel: '',
    rows: 3,
    options: () => [],
    spanClass: 'form-group',
    autoSaveDelay: undefined,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: AutoSaveFieldValue]
  saved: [value: AutoSaveFieldValue]
  error: [message: string]
}>()

const { t } = useI18n()
const inputId = `autosave-${Math.random().toString(36).slice(2, 9)}`
const modelRef = toRef(props, 'modelValue')
const baselineRef = toRef(props, 'baseline')

const {
  isFocused,
  status,
  errorMessage,
  isDirty,
  hasDisplayValue,
  isSaving,
  isPreSaving,
  showSuccessIcon,
  handleFocus,
  handleInput,
  handleSelectChange,
  handleCheckboxChange,
  handleBlur,
  notifyValueChange,
  revertToBaseline,
  trySave,
} = useAutoSaveField({
  modelValue: modelRef,
  baseline: baselineRef,
  type: props.type,
  disabled: toRef(props, 'disabled'),
  autoSaveDelay: props.autoSaveDelay,
  save: async (value) => {
    await props.save(value)
    emit('saved', value)
  },
})

const stringValue = computed(() => {
  if (props.modelValue == null) return ''
  return String(props.modelValue)
})

function emitUpdate(value: AutoSaveFieldValue) {
  emit('update:modelValue', value)
}

function onNativeInput(event: Event) {
  handleInput(event, emitUpdate)
}

function onSlotInput(event: Event) {
  handleInput(event, emitUpdate)
}

function onSelectChange(event: Event) {
  handleSelectChange(event, emitUpdate)
}

function onCheckboxChange(event: Event) {
  handleCheckboxChange(event, emitUpdate)
}

function onBlur() {
  void handleBlur(emitUpdate)
}

function onCustomChange() {
  notifyValueChange()
}

function onCancel() {
  revertToBaseline(emitUpdate)
}
</script>
