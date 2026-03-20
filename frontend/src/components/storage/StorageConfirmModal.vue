<template>
  <div v-if="isOpen" class="modal-overlay">
    <div class="confirm-dialog">
      <h3>{{ title }}</h3>
      <p>{{ message }}</p>
      <div class="modal-actions">
        <button class="btn-secondary" @click="$emit('close')">Abbrechen</button>
        <button class="btn-danger" :disabled="isLoading" @click="$emit('confirm')">
          {{ isLoading ? loadingText : confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  isOpen: boolean
  title: string
  message: string
  confirmText?: string
  loadingText?: string
  isLoading?: boolean
}>(), {
  confirmText: 'Löschen',
  loadingText: 'Wird gelöscht...',
  isLoading: false,
})

defineEmits<{
  close: []
  confirm: []
}>()
</script>

<style scoped>
/* Modal overlay base uses shared ui/modals.css */

.confirm-dialog {
  background: white;
  border-radius: 12px;
  padding: 24px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.confirm-dialog h3 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px 0;
}

.confirm-dialog p {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 18px;
}

/* Buttons use shared ui/buttons.css */
</style>

