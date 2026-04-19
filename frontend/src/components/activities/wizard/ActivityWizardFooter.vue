<template>
  <div class="material-wizard-footer activity-create-footer">
    <div v-if="showDraftStatus" class="activity-create-footer-draft">
      <span class="activity-draft-badge" aria-hidden="true">Entwurf</span>
      <span v-if="lastSavedAt" class="activity-draft-saved-at">{{ savedAtLabel }}</span>
    </div>
    <div class="footer-spacer" />
    <div class="footer-actions-wrap">
      <p v-if="submitError" class="activity-create-submit-error">{{ submitError }}</p>
      <div class="footer-actions">
        <div v-if="missingSteps.length > 0" class="missing-steps">
          <span class="missing-icon" aria-hidden="true">⚠️</span>
          <button type="button" class="missing-link" @click="$emit('jumpMissing', missingSteps[0])">
            {{ missingSteps[0] }}
          </button>
        </div>
        <button type="button" class="btn-secondary btn-sm" @click="$emit('close')">Verwerfen</button>
        <button
          v-if="layoutMode === 'stepper' && selectedActivityType && wizardStepIndex > 0"
          type="button"
          class="btn-secondary btn-sm"
          @click="$emit('prev')"
        >
          Zurück
        </button>
        <button
          v-if="layoutMode === 'stepper' && selectedActivityType && !isLastStep"
          type="button"
          class="btn-primary btn-sm"
          :disabled="!canAdvanceFromCurrentStep || isSavingDraft"
          @click="$emit('weiter')"
        >
          {{ isSavingDraft ? 'Speichern…' : 'Weiter' }}
        </button>
        <button
          v-if="showSubmitButton"
          type="button"
          class="btn-primary btn-sm"
          :disabled="!canSubmit || isSubmitting"
          :title="submitButtonTitle"
          @click="$emit('submit')"
        >
          {{ isSubmitting ? 'Wird gespeichert…' : submitButtonLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ActivityCreateLayoutMode } from '@/composables/useActivityCreateWizard'
import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'

const props = withDefaults(
  defineProps<{
    submitError: string
    missingSteps: string[]
    layoutMode: ActivityCreateLayoutMode
    selectedActivityType: ActivityCreateType | null
    wizardStepIndex: number
    isLastStep: boolean
    canAdvanceFromCurrentStep: boolean
    canSubmit: boolean
    isSubmitting: boolean
    /** Stepper: Entwurf auf Server speichern vor Schrittwechsel */
    isSavingDraft?: boolean
    showSubmitButton: boolean
    submitButtonTitle: string
    /** Sichtbarer Text des Absenden-Buttons (je nach Aktivitätstyp) */
    submitButtonLabel?: string
    /** Stepper: Entwurf-Badge + Zeit nach Speichern / finalem Speichern */
    showDraftStatus?: boolean
    lastSavedAt?: Date | null
  }>(),
  { isSavingDraft: false, submitButtonLabel: 'Aktivität anlegen', showDraftStatus: false, lastSavedAt: null },
)

const savedAtLabel = computed(() => {
  const d = props.lastSavedAt
  if (!d) return ''
  return `Zuletzt gespeichert: ${d.toLocaleString('de-CH', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })}`
})

defineEmits<{
  close: []
  prev: []
  weiter: []
  submit: []
  jumpMissing: [label: string]
}>()
</script>
