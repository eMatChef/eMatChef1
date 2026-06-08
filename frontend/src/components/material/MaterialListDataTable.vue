<template>
  <v-data-table
    class="material-list-dt__table"
    :headers="headers"
    :items="items"
    :items-per-page="-1"
    item-value="id"
    :expanded="expandedIds"
    :show-expand="showComboExpandColumn"
    hover
    hide-default-footer
    :row-props="rowProps"
    @update:expanded="emit('update:expandedIds', $event)"
  >
    <template v-if="showComboExpandColumn" #item.data-table-expand="{ item, internalItem, isExpanded, toggleExpand }">
      <v-btn
        v-if="isComboMaterial(item)"
        icon
        variant="text"
        size="small"
        density="compact"
        :aria-expanded="isExpanded(internalItem)"
        :aria-label="t('materialsView.expandComboTitle')"
        @click.stop="toggleExpand(internalItem)"
      >
        <v-icon
          :icon="isExpanded(internalItem) ? 'mdi-chevron-up' : 'mdi-chevron-down'"
          size="20"
        />
      </v-btn>
    </template>

    <template #item.name="{ item }">
      <div class="name-cell">
        <div class="material-icon" :class="getMaterialIconClass(item)">
          <svg v-if="item.is_container" class="table-icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
            <line x1="12" y1="22.08" x2="12" y2="12"/>
          </svg>
          <svg v-else-if="item.is_food" class="table-icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8zM6 1v3M10 1v3M14 1v3"/>
          </svg>
          <svg v-else-if="item.is_consumable" class="table-icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><path d="M8 12h8"/>
          </svg>
          <svg v-else class="table-icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
          </svg>
        </div>
        <div class="name-info">
          <span class="material-name">
            {{ formatMaterialDisplayName(item.name, item.pack_unit, item.pack_size, item.size_length) }}
            <span v-if="item.is_js_material" class="source-badge">J&amp;S</span>
            <span v-if="isComboDraft(item)" class="combo-draft-badge">{{ t('materialsView.comboDraftBadge') }}</span>
          </span>
          <span v-if="item.manufacturer" class="material-manufacturer">{{ item.manufacturer }}</span>
          <span v-if="item.open_loss_reports > 0" class="loss-reported-badge">
            {{ t('materialsView.lossReported', { qty: item.open_loss_qty }) }}
          </span>
        </div>
      </div>
    </template>

    <template v-if="showComboColumns" #item.material_type="{ item }">
      <span class="combo-type-badge" :class="item.material_type">
        <span class="combo-type-badge-emoji" aria-hidden="true">{{ comboBadgeEmoji({ materialType: item.material_type }) }}</span>
        {{
          item.material_type === 'physical_combo'
            ? t('components.materialDetail.typePhysicalShort')
            : t('components.materialDetail.typeVirtualShort')
        }}
      </span>
    </template>

    <template #item.category="{ item }">
      <span v-if="item.category" class="category-tag">
        <template v-if="item.category.parent_id && categoriesById[item.category.parent_id]">
          <span class="category-parent">{{ categoriesById[item.category.parent_id] }} →</span>
          <span class="category-child">{{ item.category.name }}</span>
        </template>
        <span v-else class="category-child">{{ item.category.name }}</span>
      </span>
      <span v-else class="no-category">-</span>
    </template>

    <template #item.total_stock="{ item }">
      <span class="stock-value" :class="getStockClass(item.total_stock)">
        {{ formatListStockQty(item.total_stock, item) }}
      </span>
      <span v-if="item.pack_size && item.pack_unit && !isMeterStockUnit(item.pack_unit)" class="pack-info">
        {{ Math.floor(item.total_stock / item.pack_size) }} {{ item.pack_unit }}
      </span>
    </template>

    <template v-if="showComboColumns" #item.combo_allocated="{ item }">
      <span v-if="item.combo_allocated > 0" class="stock-badge combo">{{ item.combo_allocated }}</span>
      <span v-else class="stock-zero">–</span>
    </template>

    <template v-if="showStockDetailColumns" #item.issued_out="{ item }">
      <span v-if="item.issued_out > 0" class="stock-badge issued">{{ item.issued_out }}</span>
      <span v-else class="stock-zero">–</span>
    </template>

    <template v-if="showStockDetailColumns" #item.repair_stock="{ item }">
      <span v-if="item.repair_stock > 0" class="stock-badge repair">{{ item.repair_stock }}</span>
      <span v-else class="stock-zero">–</span>
    </template>

    <template v-if="showStockDetailColumns" #item.available="{ item }">
      <span
        class="stock-badge available"
        :class="{ low: item.available < 3 && item.total_stock > 0, empty: item.available <= 0 && item.total_stock > 0 }"
      >
        {{ formatListStockQty(item.available, item) }}
      </span>
    </template>

    <template #item.actions="{ item }">
      <button
        type="button"
        class="action-btn"
        :title="t('materialsView.titleOpenDetails')"
        @click.stop="emit('open', item)"
      >
        <svg class="table-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
      </button>
    </template>

    <template #expanded-row="{ columns, item }">
      <tr v-if="isComboMaterial(item)" class="combo-components-row">
        <td :colspan="columns.length">
          <div class="combo-components-container">
            <div v-if="comboComponentsLoading.has(item.id)" class="combo-loading">
              <div class="spinner-sm"></div>
              {{ t('materialsView.comboComponentsLoading') }}
            </div>
            <div v-else-if="(comboComponentsById[item.id] || []).length === 0" class="combo-empty">
              {{ t('materialsView.comboComponentsEmpty') }}
            </div>
            <table v-else class="combo-sub-table">
              <thead>
                <tr>
                  <th>{{ t('materialsView.subColComponent') }}</th>
                  <th>{{ t('common.serialNumber') }}</th>
                  <th>{{ t('materialsView.subColQty') }}</th>
                  <th>{{ t('materialsView.subColAssignment') }}</th>
                  <th>{{ t('common.status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="comp in comboComponentsById[item.id]" :key="comp.id">
                  <td class="comp-name">
                    <span class="comp-link" @click="emit('open-component', comp.component_material.id)">
                      {{ comp.component_material.name }}
                    </span>
                  </td>
                  <td>
                    <span v-if="comp.component_batch?.serial_number" class="serial-code">
                      {{ comp.component_batch.serial_number }}
                    </span>
                    <span v-else class="no-serial">–</span>
                  </td>
                  <td>{{ comp.qty }}</td>
                  <td>
                    <span class="assignment-badge" :class="comp.assignment_mode === 'fixed' ? 'fix' : comp.assignment_mode">
                      {{ assignmentLabels[comp.assignment_mode] || comp.assignment_mode }}
                    </span>
                  </td>
                  <td>
                    <span v-if="comp.is_awaiting" class="status-dot awaiting" :title="t('materialsView.statusAwaitingTitle')"></span>
                    <span v-else-if="comp.is_assigned" class="status-dot assigned" :title="t('materialsView.statusAssignedTitle')"></span>
                    <span v-else class="status-dot linked" :title="t('materialsView.statusLinkedTitle')"></span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </td>
      </tr>
    </template>
  </v-data-table>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ComboComponent, Material } from '@/api/materials'
import { comboBadgeEmoji, isComboMaterial as isComboMaterialType } from '@/utils/comboDisplay'
import {
  canDisplayMeterStockAsPieces,
  formatMaterialDisplayName,
  formatStockQty,
  getMeterStockQtyParts,
  isMeterStockUnit,
} from '@/utils/materialStockUnit'
import '@/styles/ui/tables.css'
import '@/styles/materials-view.css'
import '@/styles/components/material-list-data-table.css'

defineOptions({ name: 'MaterialListDataTable' })

const props = defineProps<{
  items: Material[]
  categoriesById: Record<string, string>
  showComboColumns: boolean
  showComboExpandColumn: boolean
  showStockDetailColumns: boolean
  expandedIds: readonly string[]
  comboComponentsById: Record<string, ComboComponent[]>
  comboComponentsLoading: ReadonlySet<string>
  assignmentLabels: Record<string, string>
}>()

const emit = defineEmits<{
  open: [material: Material]
  'open-component': [materialId: string]
  'update:expandedIds': [ids: string[]]
}>()

const { t } = useI18n()

const headers = computed(() => {
  const cols: Array<{
    title: string
    key: string
    sortable?: boolean
    align?: 'center' | 'end' | 'start'
    width?: string
    minWidth?: string
  }> = [{ title: t('common.name'), key: 'name', sortable: true, minWidth: '260px' }]

  if (props.showComboColumns) {
    cols.push({ title: t('materialsView.colType'), key: 'material_type', sortable: false })
  }

  cols.push({ title: t('materialsView.colCategory'), key: 'category', sortable: false })
  cols.push({ title: t('materialsView.colTotal'), key: 'total_stock', sortable: true, align: 'center', width: '90px' })

  if (props.showComboColumns) {
    cols.push({ title: t('materialsView.colCombo'), key: 'combo_allocated', sortable: true, align: 'center', width: '80px' })
  }

  if (props.showStockDetailColumns) {
    cols.push({ title: t('materialsView.colIssuedOut'), key: 'issued_out', sortable: true, align: 'center', width: '80px' })
    cols.push({ title: t('materialsView.colRepair'), key: 'repair_stock', sortable: true, align: 'center', width: '80px' })
    cols.push({ title: t('materialsView.colAvailable'), key: 'available', sortable: true, align: 'center', width: '90px' })
  }

  cols.push({ title: '', key: 'actions', sortable: false, align: 'end', width: '72px' })
  return cols
})

function rowProps({ item }: { item: Material }) {
  return {
    onDblclick: () => emit('open', item),
  }
}

function isComboMaterial(material: Material) {
  return isComboMaterialType(material)
}

function isComboDraft(material: Material) {
  return isComboMaterial(material) && material.combo_status === 'draft'
}

function getStockClass(stock: number) {
  if (stock === 0) return 'empty'
  if (stock < 5) return 'low'
  return 'ok'
}

function getMaterialIconClass(material: Material) {
  if (material.is_container) return 'container'
  if (material.is_food) return 'food'
  if (material.is_consumable) return 'consumable'
  return ''
}

function formatPiecesAtLength(count: number, per: string, _total: string): string {
  return t('components.materialDetail.stockQtyPiecesAtLength', { count, per })
}

function formatListStockQty(qty: number, item: Material): string {
  if (canDisplayMeterStockAsPieces(item.pack_unit, item.size_length, item.name)) {
    const parts = getMeterStockQtyParts(qty, item.size_length, item.name)
    if (parts) {
      return formatPiecesAtLength(
        parts.count,
        String(parts.perM).replace(/\.?0+$/, ''),
        String(parts.totalM),
      )
    }
  }
  return formatStockQty(
    qty,
    item.pack_unit,
    item.pack_size,
    item.size_length,
    formatPiecesAtLength,
    item.name,
  )
}

</script>
