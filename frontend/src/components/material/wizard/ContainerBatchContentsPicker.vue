<template>
  <div class="rack-contents-picker">
    <label class="rack-contents-label">{{ t('components.containerBatchContentsPicker.sectionLabel') }}</label>
    <div class="rack-contents-row">
      <select
        :value="containerBatchId"
        class="form-select rack-select"
        :disabled="isLoading"
        @change="onBatchChange"
      >
        <option value="">{{ t('components.containerBatchContentsPicker.selectPlaceholder') }}</option>
        <option
          v-for="cb in containerBatches"
          :key="cb.id"
          :value="cb.id"
          :title="formatContainerBatchOptionFullLabel(cb)"
        >
          {{ formatContainerBatchOptionFullLabel(cb) }}
        </option>
      </select>
      <button
        v-if="containerBatchId"
        type="button"
        class="btn-outline-small"
        :disabled="isLoading"
        :title="selectedContents ? t('components.containerBatchContentsPicker.titleReloadFromServer') : undefined"
        @click="emit('load')"
      >
        {{
          isLoading
            ? t('components.containerBatchContentsPicker.loading')
            : selectedContents
              ? t('components.containerBatchContentsPicker.refresh')
              : t('components.containerBatchContentsPicker.applyContents')
        }}
      </button>
    </div>
    <p v-if="selectedContents && selectedContents.contents.length" class="rack-contents-hint">
      {{
        t('components.containerBatchContentsPicker.itemsTakenHint', {
          count: selectedContents.contents.length,
          name: selectedContents.container_label
        })
      }}
    </p>
    <p v-else-if="selectedContents && !selectedContents.contents.length" class="rack-contents-hint rack-contents-hint--empty">
      {{ t('components.containerBatchContentsPicker.emptyBoxHint') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ContainerBatch, ContainerBatchContentsResponse } from '@/api/storageLocations'

const { t } = useI18n()
import { formatContainerBatchOptionFullLabel } from '@/utils/containerBatchLabel'

defineProps<{
  containerBatchId: string
  containerBatches: ContainerBatch[]
  isLoading: boolean
  selectedContents: ContainerBatchContentsResponse | null
}>()

const emit = defineEmits<{
  'update:containerBatchId': [value: string]
  load: []
}>()

function onBatchChange(e: Event) {
  const el = e.target as HTMLSelectElement
  emit('update:containerBatchId', el.value)
}
</script>
