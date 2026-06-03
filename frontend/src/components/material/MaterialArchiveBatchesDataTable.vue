<template>
  <v-data-table
    class="batch-table batch-data-table archive-table"
    :headers="headers"
    :items="items"
    item-value="id"
    :items-per-page="-1"
    hide-default-footer
    hover
    :row-props="() => ({ class: 'archived-row' })"
  >
    <template #item.acquired_on="{ item }">
      {{ formatDate(item.acquired_on) }}
    </template>
    <template #item.qty="{ item }">
      <span class="qty-cell">{{ displayQty(item) }}</span>
    </template>
    <template #item.unit_price="{ item }">
      {{
        item.unit_price
          ? `${currencyFr} ${item.unit_price}`
          : emDash
      }}
    </template>
    <template #item.status="{ item }">
      <span class="status-badge" :class="item.status">
        {{ statusLabels[item.status] ?? item.status }}
      </span>
    </template>
    <template #item.notes="{ item }">
      <span class="notes-cell">{{ item.notes || emDash }}</span>
    </template>
    <template #item.actions />
  </v-data-table>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { MaterialBatch } from '@/api/materials'

defineOptions({ name: 'MaterialArchiveBatchesDataTable' })

const props = defineProps<{
  items: MaterialBatch[]
  statusLabels: Record<string, string>
  emDash: string
  currencyFr: string
  formatDate: (value: string | null | undefined) => string
  displayQty: (batch: MaterialBatch) => number | string
}>()

const { t } = useI18n()

const headers = computed(() => [
  { title: t('components.materialDetail.thAcquiredOn'), key: 'acquired_on', sortable: false },
  { title: t('components.materialDetail.thQty'), key: 'qty', sortable: false, width: '72px' },
  { title: t('components.materialDetail.thPricePerPc'), key: 'unit_price', sortable: false },
  { title: t('components.materialDetail.thReason'), key: 'status', sortable: false },
  { title: t('components.materialDetail.thNote'), key: 'notes', sortable: false },
  { title: '', key: 'actions', sortable: false, width: '24px' },
])
</script>
