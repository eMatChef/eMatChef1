<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div
        v-if="confirmStore.isOpen && confirmStore.options"
        class="confirm-overlay"
      >
        <div class="confirm-dialog" :class="`confirm-dialog--${confirmStore.options.variant}`">
          <div class="confirm-icon" :class="`confirm-icon--${confirmStore.options.variant}`">
            <svg v-if="confirmStore.options.variant === 'danger'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="15" y1="9" x2="9" y2="15"/>
              <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            <svg v-else-if="confirmStore.options.variant === 'warning'" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <line x1="12" y1="16" x2="12" y2="12"/>
              <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
          </div>
          <h3 class="confirm-title">{{ confirmStore.options.title }}</h3>
          <p v-if="confirmStore.options.message" class="confirm-message">{{ confirmStore.options.message }}</p>
          <div class="confirm-actions">
            <button type="button" class="confirm-btn confirm-btn--cancel" @click="confirmStore.cancel()">
              {{ confirmStore.options.cancelText }}
            </button>
            <button type="button" class="confirm-btn confirm-btn--confirm" :class="`confirm-btn--${confirmStore.options.variant}`" @click="confirmStore.confirm()">
              {{ confirmStore.options.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { watch, onUnmounted } from 'vue'
import { useConfirmStore } from '@/stores/confirm'

const confirmStore = useConfirmStore()

function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') confirmStore.cancel()
}

watch(() => confirmStore.isOpen, (open) => {
  if (open) {
    document.addEventListener('keydown', onEscape)
  } else {
    document.removeEventListener('keydown', onEscape)
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', onEscape)
})
</script>

<style scoped>
.confirm-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2300;
  padding: 20px;
}

.confirm-dialog {
  background: white;
  border-radius: 16px;
  padding: 28px;
  max-width: 420px;
  width: 100%;
  text-align: center;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  animation: confirm-scale-in 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes confirm-scale-in {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.confirm-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  margin: 0 auto 16px;
}

.confirm-icon--info {
  background: #dbeafe;
  color: #1d4ed8;
}

.confirm-icon--warning {
  background: #fef3c7;
  color: #d97706;
}

.confirm-icon--danger {
  background: #fee2e2;
  color: #dc2626;
}

.confirm-title {
  font-size: 1.15rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 8px 0;
  line-height: 1.35;
}

.confirm-message {
  font-size: 0.95rem;
  color: #64748b;
  margin: 0 0 24px 0;
  line-height: 1.5;
  white-space: pre-line;
}

.confirm-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

.confirm-btn {
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  border: none;
}

.confirm-btn--cancel {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.confirm-btn--cancel:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.confirm-btn--confirm {
  color: white;
}

.confirm-btn--info {
  background: #3b82f6;
}

.confirm-btn--info:hover {
  background: #2563eb;
}

.confirm-btn--warning {
  background: #f59e0b;
}

.confirm-btn--warning:hover {
  background: #d97706;
}

.confirm-btn--danger {
  background: #ef4444;
}

.confirm-btn--danger:hover {
  background: #dc2626;
}

.confirm-fade-enter-active,
.confirm-fade-leave-active {
  transition: opacity 0.2s ease;
}

.confirm-fade-enter-from,
.confirm-fade-leave-to {
  opacity: 0;
}

.confirm-fade-enter-active .confirm-dialog,
.confirm-fade-leave-active .confirm-dialog {
  transition: transform 0.2s ease;
}

.confirm-fade-enter-from .confirm-dialog,
.confirm-fade-leave-to .confirm-dialog {
  transform: scale(0.95);
}
</style>
