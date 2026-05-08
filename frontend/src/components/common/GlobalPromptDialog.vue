<template>
  <Teleport to="body">
    <Transition name="prompt-fade">
      <div
        v-if="promptStore.isOpen && promptStore.options"
        class="prompt-overlay"
      >
        <div class="prompt-dialog">
          <div class="prompt-icon prompt-icon--warning">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </div>
          <h3 class="prompt-title">{{ promptStore.options.title }}</h3>
          <p v-if="promptStore.options.message" class="prompt-message">{{ promptStore.options.message }}</p>
          <input
            ref="inputRef"
            v-model="promptStore.inputValue"
            type="text"
            class="prompt-input"
            :placeholder="promptStore.options.placeholder"
            @keydown.enter="promptStore.confirm()"
            @keydown.escape="promptStore.cancel()"
          />
          <div class="prompt-actions">
            <button type="button" class="prompt-btn prompt-btn--cancel" @click="promptStore.cancel()">
              {{ promptStore.options.cancelText }}
            </button>
            <button
              type="button"
              class="prompt-btn prompt-btn--confirm"
              :disabled="promptStore.options.required && !promptStore.inputValue.trim()"
              @click="promptStore.confirm()"
            >
              {{ promptStore.options.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { watch, onUnmounted, ref, nextTick } from 'vue'
import { usePromptStore } from '@/stores/prompt'

const promptStore = usePromptStore()
const inputRef = ref<HTMLInputElement | null>(null)

function onEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') promptStore.cancel()
}

watch(() => promptStore.isOpen, async (open) => {
  if (open) {
    document.addEventListener('keydown', onEscape)
    await nextTick()
    inputRef.value?.focus()
  } else {
    document.removeEventListener('keydown', onEscape)
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', onEscape)
})
</script>

<style scoped>
.prompt-overlay {
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

.prompt-dialog {
  background: white;
  border-radius: 16px;
  padding: 28px;
  max-width: 420px;
  width: 100%;
  text-align: center;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  animation: prompt-scale-in 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes prompt-scale-in {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.prompt-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  margin: 0 auto 16px;
}

.prompt-icon--warning {
  background: #fef3c7;
  color: #d97706;
}

.prompt-title {
  font-size: 1.15rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 8px 0;
  line-height: 1.35;
}

.prompt-message {
  font-size: 0.95rem;
  color: #64748b;
  margin: 0 0 16px 0;
  line-height: 1.5;
}

.prompt-input {
  width: 100%;
  padding: 12px 16px;
  font-size: 0.95rem;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  margin-bottom: 24px;
  box-sizing: border-box;
  transition: border-color 0.15s ease;
}

.prompt-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.prompt-input::placeholder {
  color: #94a3b8;
}

.prompt-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

.prompt-btn {
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  border: none;
}

.prompt-btn--cancel {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.prompt-btn--cancel:hover {
  background: #e2e8f0;
  color: #1e293b;
}

.prompt-btn--confirm {
  background: #f59e0b;
  color: white;
}

.prompt-btn--confirm:hover:not(:disabled) {
  background: #d97706;
}

.prompt-btn--confirm:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.prompt-fade-enter-active,
.prompt-fade-leave-active {
  transition: opacity 0.2s ease;
}

.prompt-fade-enter-from,
.prompt-fade-leave-to {
  opacity: 0;
}

.prompt-fade-enter-active .prompt-dialog,
.prompt-fade-leave-active .prompt-dialog {
  transition: transform 0.2s ease;
}

.prompt-fade-enter-from .prompt-dialog,
.prompt-fade-leave-to .prompt-dialog {
  transform: scale(0.95);
}
</style>
