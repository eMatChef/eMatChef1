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
      <v-textarea
        v-if="type === 'textarea'"
        :id="inputId"
        :model-value="stringValue"
        :disabled="disabled"
        :rows="rows"
        :placeholder="placeholder"
        variant="outlined"
        density="comfortable"
        hide-details
        class="e-textarea"
        @update:model-value="onVuetifyUpdate"
        @focus="handleFocus"
        @blur="onBlur"
      />
      <v-select
        v-else-if="type === 'select'"
        :id="inputId"
        :model-value="modelValue"
        :items="selectItems"
        :disabled="disabled"
        variant="outlined"
        density="comfortable"
        hide-details
        class="e-select"
        @update:model-value="onVuetifyUpdate"
        @focus="handleFocus"
        @blur="onBlur"
      />
      <div v-else-if="type === 'checkbox'" class="autosave-checkbox-wrap">
        <v-checkbox
          :id="inputId"
          :model-value="!!modelValue"
          :label="checkboxLabel"
          :disabled="disabled"
          hide-details
          color="primary"
          density="comfortable"
          class="e-checkbox autosave-v-checkbox"
          @update:model-value="onVuetifyUpdate"
          @focus="handleFocus"
          @blur="onBlur"
        />
      </div>
      <v-text-field
        v-else
        :id="inputId"
        :model-value="textFieldModel"
        :type="nativeInputType"
        :disabled="disabled"
        :placeholder="placeholder"
        :min="min"
        :max="max"
        :step="step"
        variant="outlined"
        density="comfortable"
        hide-details
        class="e-text-field"
        @update:model-value="onVuetifyUpdate"
        @focus="handleFocus"
        @blur="onBlur"
      >
        <template v-if="suffix" #append-inner>
          <span class="autosave-suffix-inner">{{ suffix }}</span>
        </template>
      </v-text-field>
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
  handleVuetifyUpdate,
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

const textFieldModel = computed(() => {
  if (props.type === 'number') {
    if (props.modelValue == null || props.modelValue === '') return null
    return props.modelValue
  }
  return stringValue.value
})

const nativeInputType = computed(() => {
  if (props.type === 'number') return 'number'
  if (props.type === 'date') return 'date'
  return 'text'
})

const selectItems = computed(() =>
  props.options.map((opt) => ({
    title: opt.label,
    value: opt.value,
  })),
)

function emitUpdate(value: AutoSaveFieldValue) {
  emit('update:modelValue', value)
}

function onVuetifyUpdate(value: AutoSaveFieldValue) {
  handleVuetifyUpdate(value, emitUpdate)
}

function onSlotInput(event: Event) {
  handleInput(event, emitUpdate)
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
