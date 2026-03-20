<template>
  <div class="global-toast-container">
    <TransitionGroup name="global-toast">
      <div
        v-for="toast in toastStore.items"
        :key="toast.id"
        class="global-toast"
        :class="`global-toast--${toast.type}`"
        role="status"
        aria-live="polite"
      >
        <span class="global-toast__icon">{{ iconForType(toast.type) }}</span>
        <span class="global-toast__message">{{ toast.message }}</span>
        <button class="global-toast__close" @click="toastStore.remove(toast.id)" aria-label="Schließen">
          ×
        </button>
        <div
          v-if="toast.duration > 0"
          class="global-toast__progress"
          :style="{ animationDuration: toast.duration + 'ms' }"
        />
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
import { useToastStore, type ToastType } from '@/stores/toast'

const toastStore = useToastStore()

function iconForType(type: ToastType): string {
  switch (type) {
    case 'success':
      return '✓'
    case 'error':
      return '!'
    case 'warning':
      return '!'
    default:
      return 'i'
  }
}
</script>

<style scoped>
.global-toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 5000;
  display: flex;
  flex-direction: column;
  gap: 10px;
  pointer-events: none;
}

.global-toast {
  min-width: 300px;
  max-width: 460px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  box-shadow: 0 10px 26px rgba(0, 0, 0, 0.12);
  border: 1px solid transparent;
  background: #ffffff;
  color: #111827;
  pointer-events: auto;
  position: relative;
  overflow: hidden;
}

.global-toast__progress {
  position: absolute;
  bottom: 0;
  left: 0;
  height: 3px;
  background: currentColor;
  opacity: 0.35;
  transform-origin: left;
  animation: global-toast-progress linear forwards;
}

@keyframes global-toast-progress {
  from { transform: scaleX(1); }
  to { transform: scaleX(0); }
}

.global-toast__icon {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 800;
  flex-shrink: 0;
}

.global-toast__message {
  font-size: 14px;
  line-height: 1.35;
  flex: 1;
}

.global-toast__close {
  border: none;
  background: transparent;
  color: inherit;
  opacity: 0.75;
  cursor: pointer;
  font-size: 20px;
  line-height: 1;
  padding: 0 2px;
}

.global-toast__close:hover {
  opacity: 1;
}

.global-toast--success {
  border-color: #86efac;
  background: #f0fdf4;
  color: #166534;
}

.global-toast--success .global-toast__icon {
  background: #dcfce7;
  color: #166534;
}

.global-toast--error {
  border-color: #fca5a5;
  background: #fef2f2;
  color: #991b1b;
}

.global-toast--error .global-toast__icon {
  background: #fee2e2;
  color: #991b1b;
}

.global-toast--warning {
  border-color: #fcd34d;
  background: #fffbeb;
  color: #92400e;
}

.global-toast--warning .global-toast__icon {
  background: #fef3c7;
  color: #92400e;
}

.global-toast--info {
  border-color: #93c5fd;
  background: #eff6ff;
  color: #1e40af;
}

.global-toast--info .global-toast__icon {
  background: #dbeafe;
  color: #1e40af;
}

.global-toast-enter-active,
.global-toast-leave-active {
  transition: all 0.22s ease;
}

.global-toast-enter-from,
.global-toast-leave-to {
  opacity: 0;
  transform: translateY(-8px) translateX(12px);
}

@media (max-width: 640px) {
  .global-toast-container {
    right: 10px;
    left: 10px;
    top: 10px;
  }

  .global-toast {
    min-width: 0;
    width: 100%;
  }
}
</style>
