<template>
  <div class="material-wizard-footer activity-create-footer">
    <div v-if="showDraftStatus" class="activity-create-footer-draft">
      <span class="activity-draft-badge" aria-hidden="true">{{ t('activities.wizard.draftBadge') }}</span>
      <span v-if="lastSavedAt" class="activity-draft-saved-at">{{ savedAtLabel }}</span>
    </div>
    <div class="footer-spacer" />
    <div class="footer-actions-wrap">
      <p v-if="submitError" class="activity-create-submit-error">{{ submitError }}</p>
      <div class="footer-actions">
        <div v-if="missingSteps.length > 0" class="missing-steps">
          <span class="missing-icon" aria-hidden="true">⚠️</span>
          <button type="button" class="missing-link" @click="$emit('jumpMissing', missingSteps[0])">
            {{ t('activities.wizard.missing.' + missingSteps[0]) }}
          </button>
        </div>
        <button
          v-if="showCloseSavedButton"
          type="button"
          class="btn-secondary btn-sm"
          @click="$emit('closeSaved')"
        >
          {{ t('activities.common.close') }}
        </button>
        <button v-else type="button" class="btn-secondary btn-sm" @click="$emit('close')">
          {{ t('activities.common.discard') }}
        </button>
        <button
          v-if="layoutMode === 'stepper' && selectedActivityType && wizardStepIndex > 0"
          type="button"
          class="btn-secondary btn-sm"
          @click="$emit('prev')"
        >
          {{ t('activities.common.backTitle') }}
        </button>
        <button
          v-if="layoutMode === 'stepper' && selectedActivityType && !isLastStep"
          type="button"
          class="btn-primary btn-sm"
          :disabled="!canAdvanceFromCurrentStep || isSavingDraft"
          @click="$emit('weiter')"
        >
          {{ isSavingDraft ? t('common.saving') : t('activities.wizard.footerNext') }}
        </button>
        <button
          v-if="showSubmitButton"
          type="button"
          class="btn-primary btn-sm"
          :disabled="!canSubmit || isSubmitting"
          :title="submitButtonTitle"
          @click="$emit('submit')"
        >
          {{ isSubmitting ? t('activities.wizard.footerSubmitting') : (submitButtonLabel || t('activities.wizard.defaultSubmit')) }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  ActivityCreateLayoutMode,
  ActivityCreateType,
  ActivityMissingStepKey,
} from '@/composables/useActivityCreateWizard'

const { t, locale } = useI18n()

const props = withDefaults(
  defineProps<{
    submitError: string
    missingSteps: ActivityMissingStepKey[]
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
    submitButtonLabel?: string | undefined
    /** Stepper: Entwurf-Badge + Zeit nach Speichern / finalem Speichern */
    showDraftStatus?: boolean
    lastSavedAt?: Date | null
    /** camp/event/external: Entwurf auf Server → «Schliessen» statt «Verwerfen» */
    showCloseSavedButton?: boolean
  }>(),
  {
    isSavingDraft: false,
    submitButtonLabel: undefined,
    showDraftStatus: false,
    lastSavedAt: null,
    showCloseSavedButton: false,
  },
)

const savedAtLabel = computed(() => {
  const d = props.lastSavedAt
  if (!d) return ''
  const locTag = String(locale.value || '').startsWith('en') ? 'en-GB' : 'de-CH'
  return t('activities.wizard.lastSaved', {
    datetime: d.toLocaleString(locTag, {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }),
  })
})

defineEmits<{
  close: []
  closeSaved: []
  prev: []
  weiter: []
  submit: []
  jumpMissing: [key: ActivityMissingStepKey]
}>()
</script>
