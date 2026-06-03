<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'
import { ESelect } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'

export type StockBatchOption = {
  id: string
  label: string
}

defineProps<{
  open: boolean
  loading: boolean
  batches: StockBatchOption[]
  selectedBatchId: string
  canSubmit: boolean
  submitting: boolean
}>()

const emit = defineEmits<{
  cancel: []
  submit: []
  'update:selectedBatchId': [value: string]
}>()

const { t } = useI18n()
</script>

<template>
  <PackWorkflowModal :open="open" size="md" @cancel="emit('cancel')">
    <template #title>{{ t('activities.packList.modalAddTitle') }}</template>
    <template #intro>
      <p class="pack-modal-hint pack-modal-hint--sm text-muted" v-html="t('activities.packList.modalAddHint')"></p>
    </template>

    <ELoadingState
      v-if="loading"
      variant="inline"
      class="pack-modal-loading"
      :message="t('activities.packList.modalLoadingBatches')"
    />
    <template v-else>
      <ESelect
        v-if="batches.length > 0"
        :model-value="selectedBatchId"
        :items="batches"
        item-title="label"
        item-value="id"
        :label="t('activities.packList.modalBatchLabel')"
        :placeholder="t('activities.packList.modalSelectPlaceholder')"
        clearable
        hide-details
        class="pack-add-container-select"
        @update:model-value="emit('update:selectedBatchId', $event ?? '')"
      />
      <p v-else class="pack-modal-empty text-muted">{{ t('activities.packList.modalNoBatch') }}</p>
    </template>

    <template #footer>
      <PackModalFooter
        :primary-label="t('common.add')"
        :primary-disabled="submitting || loading || !canSubmit"
        :cancel-disabled="submitting"
        @cancel="emit('cancel')"
        @primary="emit('submit')"
      />
    </template>
  </PackWorkflowModal>
</template>

<style scoped>
.pack-add-container-select {
  margin-bottom: 8px;
}
</style>
