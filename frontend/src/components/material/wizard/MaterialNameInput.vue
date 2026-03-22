<template>
  <div class="form-group">
    <label>{{ label }}</label>
    <div class="input-with-status">
      <input
        ref="inputRef"
        :model-value="modelValue"
        type="text"
        class="form-input"
        :class="{
          'is-valid': modelValue && !isCheckingName && !nameExists,
          'is-invalid': modelValue && !isCheckingName && nameExists
        }"
        :placeholder="placeholder"
        @input="onInput"
        @focus="$emit('focus')"
        @blur="$emit('blur')"
      />
      <span v-if="isCheckingName" class="status-icon checking">
        <svg class="spinner-small" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="30 70"/></svg>
      </span>
      <span v-else-if="modelValue && !nameExists" class="status-icon valid">✓</span>
      <span v-else-if="modelValue && nameExists" class="status-icon invalid">✗</span>
    </div>
    <p v-if="nameExists" class="field-error">Dieser Name existiert bereits!</p>

    <Teleport to="body">
      <div
        v-if="showDropdown"
        class="name-suggestions name-suggestions--teleported"
        :style="dropdownStyle"
      >
        <div class="suggestions-header">Passende Artikel</div>
        <div
          v-for="mat in nameSuggestions"
          :key="mat.id"
          class="suggestion-item"
          :class="{ 'is-exact': mat.name.toLowerCase() === (modelValue || '').trim().toLowerCase() }"
          @mousedown.prevent="$emit('selectSuggestion', mat)"
        >
          <div class="suggestion-info">
            <span class="suggestion-name">{{ mat.name }}</span>
            <span class="suggestion-cat">{{ mat.category?.name || 'Ohne Kategorie' }}</span>
          </div>
          <span class="suggestion-stock">{{ mat.total_stock }} Stk.</span>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps<{
  modelValue: string
  label: string
  placeholder?: string
  isCheckingName: boolean
  nameExists: boolean
  showSuggestions: boolean
  nameSuggestions: Array<{ id: string; name: string; category?: { name: string }; total_stock: number }>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  input: []
  focus: []
  blur: []
  selectSuggestion: [mat: any]
}>()

function onInput(e: Event) {
  const t = e.target as HTMLInputElement
  emit('update:modelValue', t.value)
  emit('input')
}

const inputRef = ref<HTMLInputElement | null>(null)
const dropdownStyle = ref<Record<string, string>>({
  position: 'fixed',
  top: '0px',
  left: '0px',
  width: '0px',
  zIndex: '99999',
})

const showDropdown = computed(
  () => props.showSuggestions && props.nameSuggestions.length > 0
)

function updateDropdownPosition() {
  const el = inputRef.value
  if (!el) return
  const r = el.getBoundingClientRect()
  dropdownStyle.value = {
    position: 'fixed',
    top: `${Math.round(r.bottom + 4)}px`,
    left: `${Math.round(r.left)}px`,
    width: `${Math.round(r.width)}px`,
    zIndex: '99999',
  }
}

function onScrollOrResize() {
  if (showDropdown.value) {
    updateDropdownPosition()
  }
}

watch(
  () => [showDropdown.value, props.nameSuggestions.length, props.modelValue] as const,
  () => {
    nextTick(() => {
      if (showDropdown.value) {
        updateDropdownPosition()
      }
    })
  }
)

onMounted(() => {
  window.addEventListener('scroll', onScrollOrResize, true)
  window.addEventListener('resize', onScrollOrResize)
})

onUnmounted(() => {
  window.removeEventListener('scroll', onScrollOrResize, true)
  window.removeEventListener('resize', onScrollOrResize)
})

defineExpose({ focus: () => inputRef.value?.focus(), select: () => inputRef.value?.select() })
</script>
