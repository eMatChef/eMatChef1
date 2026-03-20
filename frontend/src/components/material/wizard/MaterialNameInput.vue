<template>
  <div class="form-group">
    <label>{{ label }}</label>
    <div class="input-with-status">
      <input
        :ref="(el) => { if (el) inputRef = el as HTMLInputElement }"
        :model-value="modelValue"
        type="text"
        class="form-input"
        :class="{
          'is-valid': modelValue && !isCheckingName && !nameExists,
          'is-invalid': modelValue && !isCheckingName && nameExists
        }"
        :placeholder="placeholder"
        @input="(e) => { $emit('update:modelValue', (e.target as HTMLInputElement).value); $emit('input') }"
        @focus="$emit('focus')"
        @blur="$emit('blur')"
      />
      <span v-if="isCheckingName" class="status-icon checking">
        <svg class="spinner-small" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="30 70"/></svg>
      </span>
      <span v-else-if="modelValue && !nameExists" class="status-icon valid">✓</span>
      <span v-else-if="modelValue && nameExists" class="status-icon invalid">✗</span>

      <div v-if="showSuggestions && nameSuggestions.length > 0" class="name-suggestions">
        <div class="suggestions-header">Ähnliche Materialien:</div>
        <div
          v-for="mat in nameSuggestions"
          :key="mat.id"
          class="suggestion-item"
          :class="{ 'is-exact': mat.name.toLowerCase() === (modelValue || '').trim().toLowerCase() }"
          @mousedown="$emit('selectSuggestion', mat)"
        >
          <div class="suggestion-info">
            <span class="suggestion-name">{{ mat.name }}</span>
            <span class="suggestion-cat">{{ mat.category?.name || 'Ohne Kategorie' }}</span>
          </div>
          <span class="suggestion-stock">{{ mat.total_stock }} Stk.</span>
        </div>
      </div>
    </div>
    <p v-if="nameExists" class="field-error">Dieser Name existiert bereits!</p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  modelValue: string
  label: string
  placeholder?: string
  isCheckingName: boolean
  nameExists: boolean
  showSuggestions: boolean
  nameSuggestions: Array<{ id: string; name: string; category?: { name: string }; total_stock: number }>
}>()

defineEmits<{
  'update:modelValue': [value: string]
  input: []
  focus: []
  blur: []
  selectSuggestion: [mat: any]
}>()

const inputRef = ref<HTMLInputElement | null>(null)
defineExpose({ focus: () => inputRef.value?.focus(), select: () => inputRef.value?.select() })
</script>
