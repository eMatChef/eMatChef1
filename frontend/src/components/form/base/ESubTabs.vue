<template>
  <div
    class="e-subtabs"
    role="tablist"
    :aria-label="ariaLabel"
    @keydown="onKeydown"
  >
    <button
      v-for="item in items"
      :key="item.value"
      :ref="(el) => setRef(item.value, el)"
      type="button"
      role="tab"
      class="e-subtabs__tab"
      :class="{ 'e-subtabs__tab--active': item.value === modelValue }"
      :aria-selected="item.value === modelValue"
      :tabindex="item.value === modelValue ? 0 : -1"
      :disabled="item.disabled"
      :data-testid="item.testid"
      @click="emit('update:modelValue', item.value)"
    >
      <v-icon v-if="item.icon" :icon="item.icon" size="18" class="e-subtabs__icon" />
      <span>{{ item.label }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue'

export interface ESubTabItem {
  value: string
  label: string
  icon?: string
  disabled?: boolean
  testid?: string
}

const props = defineProps<{
  modelValue: string
  items: ESubTabItem[]
  ariaLabel?: string
}>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const refs = new Map<string, HTMLElement>()
function setRef(value: string, el: Element | ComponentPublicInstance | null) {
  if (el instanceof HTMLElement) refs.set(value, el)
  else refs.delete(value)
}

function onKeydown(event: KeyboardEvent) {
  const enabled = props.items.filter((i) => !i.disabled)
  const idx = enabled.findIndex((i) => i.value === props.modelValue)
  if (idx < 0 || !enabled.length) return
  let next = idx
  if (event.key === 'ArrowRight') next = (idx + 1) % enabled.length
  else if (event.key === 'ArrowLeft') next = (idx - 1 + enabled.length) % enabled.length
  else if (event.key === 'Home') next = 0
  else if (event.key === 'End') next = enabled.length - 1
  else return
  event.preventDefault()
  const target = enabled[next]
  emit('update:modelValue', target.value)
  refs.get(target.value)?.focus()
}
</script>

<style scoped>
.e-subtabs {
  display: flex;
  gap: 28px;
  border-bottom: 1px solid var(--color-border);
  overflow-x: auto;
  scrollbar-width: none;
}
.e-subtabs__tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 2px;
  margin-bottom: -1px;
  border: 0;
  border-bottom: 3px solid transparent;
  background: transparent;
  color: var(--color-text-muted);
  font: inherit;
  font-weight: 400;
  white-space: nowrap;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
}
.e-subtabs__tab:hover:not(:disabled) {
  color: var(--color-text);
}
.e-subtabs__tab:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
  border-radius: 4px;
}
.e-subtabs__tab--active {
  color: var(--color-text);
  font-weight: 600;
  border-bottom-color: var(--color-primary);
}
.e-subtabs__tab:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
@media (max-width: 600px) {
  .e-subtabs {
    gap: 20px;
  }
}
</style>
