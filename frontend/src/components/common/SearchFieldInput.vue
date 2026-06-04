<template>
  <div
    class="search-field"
    :class="{
      'is-focused': isFocused,
      'has-value': hasValue,
      'has-clear': clearable,
    }"
  >
    <div class="search-field__frame">
      <label v-if="label" class="search-field__label" :for="inputId">{{ label }}</label>
      <span class="search-field__icon" aria-hidden="true">
        <v-icon icon="mdi-magnify" size="18" />
      </span>
      <input
        :id="inputId"
        ref="inputRef"
        :value="modelValue"
        type="search"
        class="search-field__input"
        autocomplete="off"
        :aria-label="label || undefined"
        @input="onInput"
        @focus="onFocus"
        @blur="onBlur"
        @keydown="$emit('keydown', $event)"
      />
      <button
        v-if="clearable && hasValue"
        type="button"
        class="search-field__clear"
        :aria-label="clearAriaLabel"
        @mousedown.prevent
        @click="clear"
      >
        <v-icon icon="mdi-close" size="18" />
      </button>
    </div>
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
const props = withDefaults(
  defineProps<{
    modelValue?: string
    /** Hilfetext – innen wenn leer, auf dem Rahmen bei Fokus/Wert */
    label?: string
    inputId?: string
    clearable?: boolean
    clearAriaLabel?: string
  }>(),
  {
    modelValue: '',
    label: '',
    clearable: true,
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'focus', event: FocusEvent): void
  (e: 'blur', event: FocusEvent): void
  (e: 'input', event: Event): void
  (e: 'keydown', event: KeyboardEvent): void
  (e: 'clear'): void
}>()

const { t } = useI18n()

const inputRef = ref<HTMLInputElement | null>(null)
const isFocused = ref(false)
const fallbackInputId = useId()

const inputId = computed(() => props.inputId ?? fallbackInputId)
const hasValue = computed(() => String(props.modelValue ?? '').length > 0)
const clearAriaLabel = computed(
  () => props.clearAriaLabel ?? t('common.searchClear'),
)

function onInput(e: Event) {
  const target = e.target as HTMLInputElement
  emit('update:modelValue', target.value)
  emit('input', e)
}

function onFocus(e: FocusEvent) {
  isFocused.value = true
  emit('focus', e)
}

function onBlur(e: FocusEvent) {
  isFocused.value = false
  emit('blur', e)
}

function clear() {
  emit('update:modelValue', '')
  emit('clear')
  inputRef.value?.focus()
}

defineExpose({
  focus: () => inputRef.value?.focus(),
  blur: () => inputRef.value?.blur(),
  inputRef,
})
</script>
