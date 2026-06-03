<template>
  <div class="material-wizard-footer activity-create-footer">
    <div v-if="showDraftStatus" class="activity-create-footer-draft">
      <v-chip size="small" color="primary" variant="tonal" class="activity-draft-badge">
        {{ t('activities.wizard.draftBadge') }}
      </v-chip>
      <span v-if="lastSavedAt" class="activity-draft-saved-at">{{ savedAtLabel }}</span>
    </div>
    <div class="footer-spacer" aria-hidden="true" />
    <div class="activity-create-footer-actions">
      <p v-if="submitError" class="activity-create-submit-error">{{ submitError }}</p>
      <div class="footer-actions">
        <v-alert
          v-if="missingSteps.length > 0"
          variant="outlined"
          density="compact"
          icon="mdi-alert-circle-outline"
          class="e-alert-warning activity-wizard-missing-alert"
          role="button"
          tabindex="0"
          :aria-label="t('activities.wizard.missing.' + missingSteps[0])"
          @click="$emit('jumpMissing', missingSteps[0])"
          @keydown.enter.prevent="$emit('jumpMissing', missingSteps[0])"
        >
          {{ t('activities.wizard.missing.' + missingSteps[0]) }}
        </v-alert>
        <EButton
          v-if="showCloseSavedButton"
          variant="secondary"
          size="small"
          @click="$emit('closeSaved')"
        >
          {{ t('activities.common.close') }}
        </EButton>
        <EButton v-else variant="secondary" size="small" @click="$emit('close')">
          {{ t('activities.common.discard') }}
        </EButton>
        <EButton
          v-if="showDiscardDraftButton"
          variant="secondary"
          size="small"
          class="activity-discard-draft-btn"
          :loading="isDiscardingDraft"
          @click="$emit('discardDraft')"
        >
          {{ t('activities.wizard.discardDraft') }}
        </EButton>
        <EButton
          v-if="layoutMode === 'stepper' && selectedActivityType && wizardStepIndex > 0"
          variant="secondary"
          size="small"
          @click="$emit('prev')"
        >
          {{ t('activities.common.backTitle') }}
        </EButton>
        <EButton
          v-if="layoutMode === 'stepper' && selectedActivityType && !isLastStep"
          variant="primary"
          size="small"
          :disabled="!canAdvanceFromCurrentStep"
          :loading="isSavingDraft"
          @click="$emit('weiter')"
        >
          {{ isSavingDraft ? t('common.saving') : t('activities.wizard.footerNext') }}
        </EButton>
        <EButton
          v-if="showSubmitButton"
          variant="primary"
          size="small"
          :disabled="!canSubmit"
          :loading="isSubmitting"
          :title="submitButtonTitle"
          @click="$emit('submit')"
        >
          {{ isSubmitting ? t('activities.wizard.footerSubmitting') : (submitButtonLabel || t('activities.wizard.defaultSubmit')) }}
        </EButton>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
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
    /** Server-Entwurf löschen (nach Schritt 1 «Weiter») */
    showDiscardDraftButton?: boolean
    isDiscardingDraft?: boolean
  }>(),
  {
    isSavingDraft: false,
    submitButtonLabel: undefined,
    showDraftStatus: false,
    lastSavedAt: null,
    showCloseSavedButton: false,
    showDiscardDraftButton: false,
    isDiscardingDraft: false,
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
  discardDraft: []
  prev: []
  weiter: []
  submit: []
  jumpMissing: [key: ActivityMissingStepKey]
}>()
</script>

<style scoped>
.activity-create-footer {
  flex-wrap: wrap;
  gap: 10px 16px;
}

.activity-create-footer-draft {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.activity-create-footer-actions {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 8px 12px;
  min-width: 0;
  flex: 1 1 auto;
}

.activity-create-submit-error {
  margin: 0;
  flex: 1 1 100%;
  font-size: 12px;
  color: #b91c1c;
  text-align: right;
}

.footer-actions {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

</style>
