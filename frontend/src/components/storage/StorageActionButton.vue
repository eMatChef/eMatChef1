<template>
  <button
    type="button"
    class="storage-action-btn"
    :class="[sizeClass, variantClass]"
    :title="title"
    :disabled="disabled"
    @click="onClick"
  >
    <svg v-if="icon === 'edit'" :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M12 20h9"/>
      <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
    </svg>
    <svg v-else-if="icon === 'add'" :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <line x1="12" y1="5" x2="12" y2="19"/>
      <line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    <svg v-else-if="icon === 'delete'" :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <polyline points="3 6 5 6 21 6"/>
      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
      <line x1="10" y1="11" x2="10" y2="17"/>
      <line x1="14" y1="11" x2="14" y2="17"/>
    </svg>
    <svg v-else-if="icon === 'move'" :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/>
    </svg>
    <svg v-else-if="icon === 'qr'" :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <rect x="3" y="3" width="7" height="7" rx="1"/>
      <rect x="14" y="3" width="7" height="7" rx="1"/>
      <rect x="3" y="14" width="7" height="7" rx="1"/>
      <path d="M14 14h2v2h-2zM18 14h3v3h-3zM14 18h2v3h-2zM18 21h3"/>
    </svg>
    <svg v-else :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  title: string
  icon: 'edit' | 'add' | 'delete' | 'move' | 'open' | 'qr'
  size?: 'sm' | 'md'
  variant?: 'default' | 'add' | 'delete'
  disabled?: boolean
}>(), {
  size: 'md',
  variant: 'default',
  disabled: false,
})

const emit = defineEmits<{ click: [event: MouseEvent] }>()

const sizeClass = computed(() => props.size === 'sm' ? 'is-sm' : 'is-md')
const variantClass = computed(() => {
  if (props.variant === 'add') return 'is-add'
  if (props.variant === 'delete') return 'is-delete'
  return 'is-default'
})
const iconSize = computed(() => props.size === 'sm' ? 14 : 16)

function onClick(event: MouseEvent) {
  emit('click', event)
}
</script>

<style scoped>
.storage-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  color: #6b7280;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.storage-action-btn.is-sm {
  width: 28px;
  height: 28px;
}

.storage-action-btn.is-md {
  width: 32px;
  height: 32px;
}

.storage-action-btn.is-default:hover {
  background: #f3f4f6;
  color: #374151;
}

.storage-action-btn.is-add:hover {
  background: #ccfbf1;
  color: #0d9488;
  border-color: #99f6e4;
}

.storage-action-btn.is-delete:hover {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fecaca;
}

.storage-action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>

