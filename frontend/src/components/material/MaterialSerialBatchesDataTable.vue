<template>
  <v-data-table
    class="serials-table batch-data-table"
    :headers="headers"
    :items="items"
    item-value="id"
    :items-per-page="-1"
    hide-default-footer
    hover
  >
    <template #header.serial_number>
      <SortHeaderButton
        :label="t('common.serialNumber')"
        :title="t('components.materialDetail.sortBySerial')"
        sort-key="serial_number"
        :active-key="sortKey"
        :direction="sortDir"
        @toggle="$emit('toggle-sort', $event)"
      />
    </template>
    <template #header.public_code>
      <SortHeaderButton
        :label="t('components.materialDetail.labelCode')"
        :title="t('components.materialDetail.sortByPublicCode')"
        sort-key="public_code"
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
    <template #header.is_container>
      <SortHeaderButton
        :label="t('components.materialDetail.thContainer')"
        :title="t('components.materialDetail.sortByContainerFlag')"
        sort-key="is_container"
        :active-key="sortKey"
        :direction="sortDir"
        @toggle="$emit('toggle-sort', $event)"
      />
    </template>
    <template #header.acquired_on>
      <SortHeaderButton
        :label="t('components.materialDetail.thRecordedOn')"
        :title="t('components.materialDetail.sortByRecordedDate')"
        sort-key="acquired_on"
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

    <template #item.index="{ index }">
      <span class="col-num">{{ index + 1 }}</span>
    </template>
    <template #item.serial_number="{ item }">
      <span class="serial-code col-serial">{{ item.serial_number }}</span>
    </template>
    <template #item.public_code="{ item }">
      <PublicQrTag
        :url="item.public_url"
        :code="item.public_code"
        :size="56"
        :clickable="true"
        :image-label="materialName"
        :image-entity-id="item.id"
        @activate="$emit('qr-activate', item)"
      />
    </template>
    <template #item.label="{ item }">
      {{ item.label || emDash }}
    </template>
    <template #item.is_container="{ item }">
      <label class="checkbox-label serial-behälter-cell">
        <input
          type="checkbox"
          :checked="!!item.is_container"
          :disabled="!!containerSaving[item.id]"
          @change="$emit('container-change', item, ($event.target as HTMLInputElement).checked)"
        />
      </label>
    </template>
    <template #item.acquired_on="{ item }">
      {{ formatDate(item.acquired_on) }}
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
      <TableIconButton
        icon="mdi-pencil"
        :title="t('components.materialDetail.titleEditBatch')"
        @click.stop="$emit('edit', item)"
      />
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
import type { BatchLocationEntry } from '@/components/material/MaterialStockBatchesDataTable.vue'

defineOptions({ name: 'MaterialSerialBatchesDataTable' })

const props = defineProps<{
  items: MaterialBatch[]
  materialName: string
  statusLabels: Record<string, string>
  sortKey: string | null
  sortDir: 'asc' | 'desc'
  emDash: string
  containerSaving: Record<string, boolean>
  formatDate: (value: string | null | undefined) => string
  locationEntries: (batch: MaterialBatch) => BatchLocationEntry[]
}>()

defineEmits<{
  'toggle-sort': [key: string]
  edit: [batch: MaterialBatch]
  'qr-activate': [batch: MaterialBatch]
  'container-change': [batch: MaterialBatch, value: boolean]
  'open-container': [entry: BatchLocationEntry]
}>()

const { t } = useI18n()

const headers = computed(() => [
  { title: t('components.materialDetail.thIndex'), key: 'index', sortable: false, width: '48px' },
  { title: '', key: 'serial_number', sortable: false },
  { title: '', key: 'public_code', sortable: false, width: '80px' },
  { title: '', key: 'label', sortable: false },
  { title: '', key: 'is_container', sortable: false, width: '88px' },
  { title: '', key: 'acquired_on', sortable: false, width: '110px' },
  { title: '', key: 'location', sortable: false },
  { title: '', key: 'status', sortable: false, width: '100px' },
  { title: '', key: 'notes', sortable: false },
  { title: '', key: 'actions', sortable: false, width: '56px' },
])
</script>
