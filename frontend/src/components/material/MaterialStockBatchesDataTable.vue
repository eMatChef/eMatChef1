<template>
  <v-data-table
    class="batch-table batch-data-table"
    :headers="headers"
    :items="items"
    item-value="id"
    :items-per-page="-1"
    hide-default-footer
    hover
  >
    <template #header.acquired_on>
      <SortHeaderButton
        :label="t('components.materialDetail.thAcquiredOn')"
        :title="t('components.materialDetail.sortByAcquired')"
        sort-key="acquired_on"
        :active-key="sortKey"
        :direction="sortDir"
        @toggle="$emit('toggle-sort', $event)"
      />
    </template>
    <template v-if="showExpiry" #header.expiry_date>
      <SortHeaderButton
        :label="t('materialsView.colExpiry')"
        :title="t('materialsView.colExpiry')"
        sort-key="expiry_date"
        :active-key="sortKey"
        :direction="sortDir"
        @toggle="$emit('toggle-sort', $event)"
      />
    </template>
    <template #header.qty>
      <SortHeaderButton
        :label="qtyColumnLabel"
        :title="t('components.materialDetail.sortByQty')"
        sort-key="qty"
        :active-key="sortKey"
        :direction="sortDir"
        @toggle="$emit('toggle-sort', $event)"
      />
    </template>
    <template #header.label>
      <SortHeaderButton
        :label="t('components.materialDetail.thLabel')"
        :title="t('components.materialDetail.sortByLabel')"
        sort-key="label"
        :active-key="sortKey"
        :direction="sortDir"
        @toggle="$emit('toggle-sort', $event)"
      />
    </template>
    <template #header.unit_price>
      <SortHeaderButton
        :label="t('components.materialDetail.thPricePerUnit', { unit: stockUnitLabel })"
        :title="t('components.materialDetail.sortByUnitPrice')"
        sort-key="unit_price"
        :active-key="sortKey"
        :direction="sortDir"
        @toggle="$emit('toggle-sort', $event)"
      />
    </template>
    <template #header.location>
      <SortHeaderButton
        :label="t('components.materialDetail.thStorageSlot')"
        :title="t('components.materialDetail.sortByLocation')"
        sort-key="location"
        :active-key="sortKey"
        :direction="sortDir"
        @toggle="$emit('toggle-sort', $event)"
      />
    </template>
    <template #header.status>
      <SortHeaderButton
        :label="t('common.status')"
        :title="t('components.materialDetail.sortByStatus')"
        sort-key="status"
        :active-key="sortKey"
        :direction="sortDir"
        @toggle="$emit('toggle-sort', $event)"
      />
    </template>
    <template #header.notes>
      <SortHeaderButton
        :label="t('components.materialDetail.thNote')"
        :title="t('components.materialDetail.sortByNotes')"
        sort-key="notes"
        :active-key="sortKey"
        :direction="sortDir"
        @toggle="$emit('toggle-sort', $event)"
      />
    </template>

    <template #item.acquired_on="{ item }">
      {{ formatDate(item.acquired_on) }}
    </template>
    <template v-if="showExpiry" #item.expiry_date="{ item }">
      <span
        v-if="item.expiry_date"
        class="expiry-date"
        :class="expiryToneClass(daysUntilExpiry(item.expiry_date))"
      >
        {{ formatExpiryDate(item.expiry_date) }}
      </span>
      <span v-else>{{ emDash }}</span>
    </template>
    <template #item.qty="{ item }">
      <span class="qty-cell">{{ formatBatchQty(item.qty) }}</span>
    </template>
    <template v-if="canManageMaterials" #item.qr="{ item }">
      <PublicQrTag
        :url="item.public_url"
        :code="item.public_code"
        :size="48"
        :clickable="!!item.public_url"
        :image-label="materialName"
        :image-entity-id="item.id"
        @activate="$emit('qr-activate', item)"
      />
    </template>
    <template #item.label="{ item }">
      {{ item.label || emDash }}
    </template>
    <template #item.unit_price="{ item }">
      {{
        item.unit_price
          ? `${currencyFr} ${item.unit_price}`
          : emDash
      }}
    </template>
    <template #item.location="{ item }">
      <div class="location-lines">
        <div
          v-for="(entry, locationIndex) in locationEntries(item)"
          :key="`${item.id}-loc-${locationIndex}`"
          class="location-line"
        >
          <button
            v-if="entry.containerMaterialId"
            type="button"
            class="location-link-text"
            @click="$emit('open-container', entry)"
          >
            {{ entry.text }}
          </button>
          <span v-else>{{ entry.text }}</span>
        </div>
      </div>
    </template>
    <template #item.status="{ item }">
      <span class="status-badge" :class="item.status">
        {{ statusLabels[item.status] ?? item.status }}
      </span>
    </template>
    <template #item.notes="{ item }">
      <span class="notes-cell">{{ item.notes || emDash }}</span>
    </template>
    <template #item.actions="{ item }">
      <div class="actions-cell">
        <TableIconButton
          v-if="showMoveQty"
          icon="mdi-arrow-all"
          :title="t('components.materialDetail.titleMoveQtyBatch')"
          @click="$emit('move', item)"
        />
        <TableIconButton
          icon="mdi-pencil"
          :title="t('components.materialDetail.titleEditBatch')"
          @click="$emit('edit', item)"
        />
      </div>
    </template>
  </v-data-table>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { MaterialBatch } from '@/api/materials'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import TableIconButton from '@/components/common/TableIconButton.vue'
import SortHeaderButton from '@/components/material/SortHeaderButton.vue'
import {
  canDisplayMeterStockAsPieces,
  formatStockQtyWithPackHint,
  getStockUnitLabel,
} from '@/utils/materialStockUnit'
import { daysUntilExpiry, expiryToneClass, formatExpiryDate } from '@/utils/materialExpiry'

defineOptions({ name: 'MaterialStockBatchesDataTable' })

export type BatchLocationEntry = {
  text: string
  containerMaterialId?: string
  containerBatchId?: string | null
  containerSearchSeed?: string
}

const props = withDefaults(
  defineProps<{
    items: MaterialBatch[]
    canManageMaterials: boolean
    showMoveQty: boolean
    materialName: string
    packUnit?: string | null
    packSize?: number | null
    sizeLengthCm?: string | number | null
    statusLabels: Record<string, string>
    sortKey: string | null
    sortDir: 'asc' | 'desc'
    emDash: string
    currencyFr: string
    formatDate: (value: string | null | undefined) => string
    locationEntries: (batch: MaterialBatch) => BatchLocationEntry[]
    /** Ablaufdatum-Spalte (Esswaren / Chargen mit Verfall) */
    showExpiry?: boolean
  }>(),
  { showExpiry: false },
)

const { t } = useI18n()

const stockUnitLabel = computed(() => getStockUnitLabel(props.packUnit))

const qtyColumnLabel = computed(() => {
  if (canDisplayMeterStockAsPieces(props.packUnit, props.sizeLengthCm)) {
    return t('components.batchModal.quantityCountLabel')
  }
  return t('components.materialDetail.thQtyWithUnit', { unit: stockUnitLabel.value })
})

function formatPiecesAtLength(count: number, per: string, _total: string): string {
  return t('components.materialDetail.stockQtyPiecesAtLength', { count, per })
}

function formatBatchQty(qty: number | null | undefined): string {
  const n = Number(qty)
  if (!Number.isFinite(n)) return props.emDash
  return formatStockQtyWithPackHint(
    n,
    props.packUnit,
    props.packSize,
    'm',
    props.sizeLengthCm,
    formatPiecesAtLength,
  )
}

defineEmits<{
  'toggle-sort': [key: string]
  edit: [batch: MaterialBatch]
  move: [batch: MaterialBatch]
  'qr-activate': [batch: MaterialBatch]
  'open-container': [entry: BatchLocationEntry]
}>()

const headers = computed(() => {
  const h: { title: string; key: string; sortable: boolean; width?: string }[] = [
    { title: '', key: 'acquired_on', sortable: false, width: '110px' },
  ]
  if (props.showExpiry) {
    h.push({ title: '', key: 'expiry_date', sortable: false, width: '110px' })
  }
  h.push({ title: '', key: 'qty', sortable: false, width: '72px' })
  if (props.canManageMaterials) {
    h.push({ title: t('components.materialDetail.thQr'), key: 'qr', sortable: false, width: '72px' })
  }
  h.push(
    { title: '', key: 'label', sortable: false },
    { title: '', key: 'unit_price', sortable: false, width: '120px' },
    { title: '', key: 'location', sortable: false },
    { title: '', key: 'status', sortable: false, width: '100px' },
    { title: '', key: 'notes', sortable: false },
    { title: '', key: 'actions', sortable: false, width: '88px' },
  )
  return h
})
</script>
