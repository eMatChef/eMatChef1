<template>
  <div class="material-wizard-footer">
    <label class="checkbox-label">
      <input type="checkbox" :checked="createAnother" @change="$emit('update:createAnother', ($event.target as HTMLInputElement).checked)" />
      <span>Weitere erstellen</span>
    </label>
    <div class="footer-actions">
      <div v-if="missingSteps.length > 0" class="missing-steps">
        <span class="missing-icon">⚠️</span>
        <button type="button" class="missing-link" @click="$emit('jumpToMissing', missingSteps[0])">
          {{ missingSteps[0] }}
        </button>
      </div>
      <button class="btn-secondary btn-sm" @click="$emit('close')">Abbrechen</button>
      <button 
        class="btn-primary btn-sm" 
        :disabled="!canSubmit || isSubmitting"
        :title="missingSteps.length > 0 ? missingSteps.join(', ') : ''"
        @click="$emit('submit')"
      >
        {{ submitButtonText }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  createAnother: boolean
  missingSteps: string[]
  canSubmit: boolean
  isSubmitting: boolean
  isAddBatchMode: boolean
  isFromTemplate: boolean
  creationMode: '' | 'individual' | 'physical_combo' | 'virtual_combo'
}>()

defineEmits<{
  'update:createAnother': [value: boolean]
  close: []
  submit: []
  jumpToMissing: [step: string]
}>()

const submitButtonText = computed(() => {
  if (props.isSubmitting) return 'Wird gespeichert...'
  if (props.isAddBatchMode) return 'Bestand hinzufügen'
  if (props.isFromTemplate) {
    if (props.creationMode === 'individual') return 'Artikel erstellen'
    if (props.creationMode === 'physical_combo') return 'Kombo erstellen'
    if (props.creationMode === 'virtual_combo') return 'Virtuelle Kombo erstellen'
    return 'Erstellen'
  }
  return 'Material hinzufügen'
})
</script>
