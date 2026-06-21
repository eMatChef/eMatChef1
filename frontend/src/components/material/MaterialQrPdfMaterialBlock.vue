<template>
  <div class="material-qr-pdf-material-block">
    <div v-if="material.batches.length > 1" class="material-qr-pdf-accordion-header">
      <button
        type="button"
        class="material-qr-pdf-accordion-toggle"
        :aria-expanded="expanded"
        @click="emit('toggle-expanded')"
      >
        <span class="material-qr-pdf-accordion-chevron" aria-hidden="true">{{ expanded ? '▾' : '▸' }}</span>
      </button>
      <span class="material-qr-pdf-material-title">
        <span class="material-qr-pdf-type">{{ t('tasksPrint.materialQrPdfTypeMaterial') }}</span>
        {{ material.name }}
        <span
          class="material-qr-pdf-selection-badge"
          :class="selectionBadgeClass"
        >
          {{ selectedCount }}/{{ material.batches.length }}
        </span>
      </span>
    </div>

    <label v-else-if="material.batches.length === 1" class="material-qr-pdf-node">
      <input
        type="checkbox"
        :checked="isBatchChecked(material.batches[0].id)"
        @change="emit('toggle-batch', material.batches[0].id, ($event.target as HTMLInputElement).checked)"
      />
      <span class="material-qr-pdf-node-label">
        <span class="material-qr-pdf-type">{{ t('tasksPrint.materialQrPdfTypeMaterial') }}</span>
        {{ material.name }}
        <span class="material-qr-pdf-batch-line">{{ material.batches[0].line_label }}</span>
      </span>
    </label>

    <div v-if="material.batches.length > 1 && expanded" class="material-qr-pdf-batch-list">
      <label
        v-for="batch in material.batches"
        :key="batch.id"
        class="material-qr-pdf-node material-qr-pdf-node--batch"
      >
        <input
          type="checkbox"
          :checked="isBatchChecked(batch.id)"
          @change="emit('toggle-batch', batch.id, ($event.target as HTMLInputElement).checked)"
        />
        <span class="material-qr-pdf-node-label">
          <span class="material-qr-pdf-type">{{ t('tasksPrint.materialQrPdfTypeBatch') }}</span>
          {{ batch.line_label }}
        </span>
      </label>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { MaterialQrTreeMaterial } from '@/api/tasks'

const props = defineProps<{
  material: MaterialQrTreeMaterial
  expanded: boolean
  selectedCount: number
  isBatchChecked: (batchId: string) => boolean
}>()

const emit = defineEmits<{
  'toggle-expanded': []
  'toggle-batch': [batchId: string, checked: boolean]
}>()

const { t } = useI18n()

const selectionBadgeClass = computed(() => ({
  'material-qr-pdf-selection-badge--partial': props.selectedCount > 0 && props.selectedCount < props.material.batches.length,
  'material-qr-pdf-selection-badge--full': props.selectedCount > 0 && props.selectedCount === props.material.batches.length,
}))
</script>

<style scoped>
.material-qr-pdf-material-block {
  margin-top: 4px;
}

.material-qr-pdf-accordion-header {
  display: flex;
  align-items: flex-start;
  gap: 2px;
}

.material-qr-pdf-accordion-toggle {
  flex-shrink: 0;
  width: 24px;
  height: 28px;
  margin-top: 1px;
  padding: 0;
  border: none;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
  line-height: 1;
}

.material-qr-pdf-accordion-toggle:hover {
  color: #374151;
}

.material-qr-pdf-accordion-chevron {
  font-size: 12px;
}

.material-qr-pdf-material-title {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: baseline;
  line-height: 1.35;
  padding-top: 4px;
  font-weight: 500;
}

.material-qr-pdf-type {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #9ca3af;
}

.material-qr-pdf-child-count {
  font-size: 12px;
  font-weight: 400;
  color: #9ca3af;
}

.material-qr-pdf-selection-badge {
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.4;
  color: #6b7280;
  background: #f3f4f6;
}

.material-qr-pdf-selection-badge--partial {
  color: #047857;
  background: #d1fae5;
}

.material-qr-pdf-selection-badge--full {
  color: #065f46;
  background: #a7f3d0;
}

.material-qr-pdf-node {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 6px 4px;
  cursor: pointer;
}

.material-qr-pdf-node--batch {
  margin-left: 24px;
}

.material-qr-pdf-node input {
  margin-top: 3px;
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.material-qr-pdf-node-label {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: baseline;
  line-height: 1.35;
}

.material-qr-pdf-batch-line {
  font-size: 13px;
  color: #6b7280;
}

.material-qr-pdf-batch-list {
  margin-left: 8px;
}
</style>
