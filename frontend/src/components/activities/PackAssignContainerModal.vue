<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'

export type AssignContainerChoice = { value: string; label: string }

defineProps<{
  open: boolean
  materialName: string
  assignHint: string
  choicesLoading: boolean
  choicesFromActivity: AssignContainerChoice[]
  choicesPack: AssignContainerChoice[]
  hasContainerChoice: boolean
  containerId: string
  qty: number
  maxQty: number
  submitting: boolean
}>()

const emit = defineEmits<{
  cancel: []
  submit: []
  'update:containerId': [value: string]
  'update:qty': [value: number]
}>()

const { t } = useI18n()
</script>

<template>
  <PackWorkflowModal :open="open" size="md" @cancel="emit('cancel')">
    <template #title>{{ t('activities.packList.modalAssignTitle') }}</template>
    <template #intro>
      <p class="pack-modal-material">{{ materialName }}</p>
      <p class="pack-modal-hint text-muted">{{ assignHint }}</p>
    </template>

    <p v-if="choicesLoading" class="pack-modal-loading text-muted">
      {{ t('activities.packList.modalAssignLoadingChoices') }}
    </p>
    <label v-else class="pack-modal-label">
      <span>{{ t('activities.packList.modalAssignContainerLabel') }}</span>
      <select
        :value="containerId"
        class="form-select"
        @change="emit('update:containerId', ($event.target as HTMLSelectElement).value)"
      >
        <option value="" disabled>{{ t('activities.packList.modalAssignSelectContainer') }}</option>
        <optgroup
          v-if="choicesFromActivity.length > 0"
          :label="t('activities.packList.modalAssignOptgroupActivity')"
        >
          <option v-for="o in choicesFromActivity" :key="o.value" :value="o.value">{{ o.label }}</option>
        </optgroup>
        <optgroup v-if="choicesPack.length > 0" :label="t('activities.packList.modalAssignOptgroupPackList')">
          <option v-for="o in choicesPack" :key="o.value" :value="o.value">{{ o.label }}</option>
        </optgroup>
      </select>
    </label>
    <p v-if="!choicesLoading && !hasContainerChoice" class="text-muted pack-modal-hint">
      {{ t('activities.packList.modalAssignNoContainers') }}
    </p>
    <label class="pack-modal-label">
      <span>{{ t('activities.packList.modalQty') }}</span>
      <input
        :value="qty"
        type="number"
        class="form-input"
        min="1"
        :max="maxQty"
        @input="emit('update:qty', Math.max(1, parseInt(($event.target as HTMLInputElement).value, 10) || 1))"
      />
    </label>

    <template #footer>
      <PackModalFooter
        :primary-label="t('activities.packList.modalAssignSubmit')"
        :primary-disabled="choicesLoading || submitting || qty < 1 || qty > maxQty || !containerId"
        :cancel-disabled="submitting"
        @cancel="emit('cancel')"
        @primary="emit('submit')"
      />
    </template>
  </PackWorkflowModal>
</template>
