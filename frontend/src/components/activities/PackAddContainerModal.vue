<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import PackWorkflowModal from '@/components/activities/PackWorkflowModal.vue'
import PackModalFooter from '@/components/activities/PackModalFooter.vue'

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

    <div v-if="loading" class="pack-modal-loading text-muted">
      {{ t('activities.packList.modalLoadingBatches') }}
    </div>
    <template v-else>
      <label v-if="batches.length > 0" class="pack-modal-label">
        <span>{{ t('activities.packList.modalBatchLabel') }}</span>
        <select
          :value="selectedBatchId"
          class="form-select"
          @change="emit('update:selectedBatchId', ($event.target as HTMLSelectElement).value)"
        >
          <option value="">{{ t('activities.packList.modalSelectPlaceholder') }}</option>
          <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.label }}</option>
        </select>
      </label>
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
