<template>
  <div class="material-wizard-footer">
    <label class="checkbox-label">
      <input type="checkbox" :checked="createAnother" @change="$emit('update:createAnother', ($event.target as HTMLInputElement).checked)" />
      <span>{{ t('components.materialWizardFooter.createAnother') }}</span>
    </label>
    <div class="footer-actions">
      <div v-if="missingSteps.length > 0" class="missing-steps">
        <span class="missing-icon">⚠️</span>
        <button type="button" class="missing-link" @click="$emit('jumpToMissing', missingSteps[0].step)">
          {{ missingSteps[0].label }}
        </button>
      </div>
      <button class="btn-secondary btn-sm" @click="$emit('close')">{{
        t('components.materialWizardFooter.discard')
      }}</button>
      <button 
        class="btn-primary btn-sm" 
        data-onboarding="material-wizard-submit"
        :disabled="!canSubmit || isSubmitting"
        :title="missingSteps.length > 0 ? missingSteps.map((s) => s.label).join(', ') : ''"
        @click="$emit('submit')"
      >
        {{ submitButtonText }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

export type MaterialWizardMissingHint = { step: string; label: string }

const props = defineProps<{
  createAnother: boolean
  missingSteps: MaterialWizardMissingHint[]
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
  if (props.isSubmitting) return t('components.materialWizardFooter.saving')
  if (props.isAddBatchMode) return t('components.materialWizardFooter.addBatch')
  if (props.isFromTemplate) {
    if (props.creationMode === 'individual') return t('components.materialWizardFooter.createArticle')
    if (props.creationMode === 'physical_combo') return t('components.materialWizardFooter.createCombo')
    if (props.creationMode === 'virtual_combo') return t('components.materialWizardFooter.createVirtualCombo')
    return t('common.create')
  }
  return t('components.materialWizardFooter.addMaterial')
})
</script>
