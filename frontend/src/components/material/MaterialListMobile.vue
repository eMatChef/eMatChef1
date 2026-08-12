<template>
  <v-list v-if="items.length > 0" class="material-list-mobile" lines="three">
    <template v-for="item in items" :key="item.id">
      <v-list-item class="material-list-mobile__item" @click="emit('open', item)">
        <template #prepend>
          <div class="material-icon material-list-mobile__icon" :class="getMaterialIconClass(item)">
            <v-icon :icon="iconName(item)" size="20" />
          </div>
        </template>

        <v-list-item-title class="material-list-mobile__title">
          {{ formatMaterialDisplayName(item.name, item.pack_unit, item.pack_size, item.size_length) }}
          <span v-if="item.is_js_material" class="source-badge">J&amp;S</span>
          <span v-if="isComboDraft(item)" class="combo-draft-badge">{{ t('materialsView.comboDraftBadge') }}</span>
        </v-list-item-title>

        <v-list-item-subtitle class="material-list-mobile__meta">
          {{ categoryLabel(item) }}
        </v-list-item-subtitle>

        <v-list-item-subtitle class="material-list-mobile__stock">
          {{ t('materialsView.colTotal') }} {{ formatListStockQty(item.total_stock, item) }}
          <template v-if="showFoodColumns">
            ·
            <span :class="expiryToneClass(daysUntilExpiry(item.nearest_expiry_date))">
              {{ formatExpiryDate(item.nearest_expiry_date) }}
              <template v-if="daysUntilExpiry(item.nearest_expiry_date) !== null">
                ({{ daysUntilExpiry(item.nearest_expiry_date) }}d)
              </template>
            </span>
          </template>
          <template v-if="showStockDetailColumns">
            · {{ t('materialsView.colAvailable') }}
            <span
              class="stock-badge available material-list-mobile__stock-badge"
              :class="{
                low: item.available < 3 && item.total_stock > 0,
                empty: item.available <= 0 && item.total_stock > 0,
              }"
            >
              {{ formatListStockQty(item.available, item) }}
            </span>
          </template>
        </v-list-item-subtitle>

        <template #append>
          <div class="material-list-mobile__actions">
            <v-btn
              v-if="(showComboExpandColumn && isComboMaterial(item)) || (showFoodExpandColumn && item.is_food)"
              icon
              variant="text"
              size="small"
              density="compact"
              :aria-expanded="expandedIds.includes(item.id)"
              :aria-label="
                item.is_food && showFoodExpandColumn
                  ? t('materialsView.expandBatchesTitle')
                  : t('materialsView.expandComboTitle')
              "
              @click.stop="emit('toggle-expand', item.id)"
            >
              <v-icon
                :icon="expandedIds.includes(item.id) ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                size="20"
              />
            </v-btn>
            <v-btn
              icon
              variant="text"
              size="small"
              density="compact"
              :aria-label="t('materialsView.titleOpenDetails')"
              @click.stop="emit('open', item)"
            >
              <v-icon icon="mdi-eye-outline" size="18" />
            </v-btn>
          </div>
        </template>
      </v-list-item>

      <v-expand-transition>
        <div
          v-if="showComboExpandColumn && isComboMaterial(item) && expandedIds.includes(item.id)"
          class="material-list-mobile__combo-panel"
        >
          <div v-if="comboComponentsLoading.has(item.id)" class="material-list-mobile__loading">
            <div class="spinner-sm"></div>
            {{ t('materialsView.comboComponentsLoading') }}
          </div>
          <div v-else-if="(comboComponentsById[item.id] || []).length === 0" class="material-list-mobile__combo-empty">
            {{ t('materialsView.comboComponentsEmpty') }}
          </div>
          <template v-else>
            <p class="material-list-mobile__combo-heading">
              {{ t('materialsView.subColComponent') }}
            </p>
            <ul class="material-list-mobile__combo-items">
              <li v-for="comp in comboComponentsById[item.id]" :key="comp.id">
                <span class="comp-link" @click.stop="emit('open-component', comp.component_material.id)">
                  {{ comp.component_material.name }}
                </span>
                <span class="material-list-mobile__combo-meta">
                  ×{{ comp.qty }}
                  <template v-if="comp.component_batch?.serial_number">
                    · {{ comp.component_batch.serial_number }}
                  </template>
                </span>
              </li>
            </ul>
          </template>
        </div>
      </v-expand-transition>

      <v-expand-transition>
        <div
          v-if="showFoodExpandColumn && item.is_food && expandedIds.includes(item.id)"
          class="material-list-mobile__combo-panel"
        >
          <div v-if="foodBatchesLoading.has(item.id)" class="material-list-mobile__loading">
            <div class="spinner-sm"></div>
            {{ t('materialsView.foodBatchesLoading') }}
          </div>
          <div v-else-if="(foodBatchesById[item.id] || []).length === 0" class="material-list-mobile__combo-empty">
            {{ t('materialsView.foodBatchesEmpty') }}
          </div>
          <template v-else>
            <p class="material-list-mobile__combo-heading">
              {{ t('materialsView.subColBatch') }}
            </p>
            <ul class="material-list-mobile__combo-items">
              <li v-for="batch in foodBatchesById[item.id]" :key="batch.id">
                <span>
                  {{ batch.label || batch.serial_number || batch.id }}
                </span>
                <span class="material-list-mobile__combo-meta">
                  ×{{ batch.qty }}
                  ·
                  <span :class="expiryToneClass(daysUntilExpiry(batch.expiry_date))">
                    {{ formatExpiryDate(batch.expiry_date) }}
                  </span>
                </span>
              </li>
            </ul>
          </template>
        </div>
      </v-expand-transition>
    </template>
  </v-list>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ComboComponent, Material, MaterialBatch } from '@/api/materials'
import { isComboMaterial as isComboMaterialType } from '@/utils/comboDisplay'
import {
  daysUntilExpiry,
  expiryToneClass,
  formatExpiryDate,
} from '@/utils/materialExpiry'
import {
  canDisplayMeterStockAsPieces,
  formatMaterialDisplayName,
  formatStockQty,
  getMeterStockQtyParts,
} from '@/utils/materialStockUnit'
import '@/styles/materials-view.css'
import '@/styles/components/material-list-mobile.css'

defineOptions({ name: 'MaterialListMobile' })

const props = withDefaults(
  defineProps<{
    items: Material[]
    categoriesById: Record<string, string>
    showComboExpandColumn: boolean
    showFoodColumns?: boolean
    showFoodExpandColumn?: boolean
    showStockDetailColumns: boolean
    expandedIds: readonly string[]
    comboComponentsById: Record<string, ComboComponent[]>
    comboComponentsLoading: ReadonlySet<string>
    foodBatchesById?: Record<string, MaterialBatch[]>
    foodBatchesLoading?: ReadonlySet<string>
  }>(),
  {
    showFoodColumns: false,
    showFoodExpandColumn: false,
    foodBatchesById: () => ({}),
    foodBatchesLoading: () => new Set(),
  },
)

const emit = defineEmits<{
  open: [material: Material]
  'open-component': [materialId: string]
  'toggle-expand': [materialId: string]
}>()

const { t } = useI18n()

function categoryLabel(item: Material) {
  if (item.category?.parent_id && props.categoriesById[item.category.parent_id]) {
    return `${props.categoriesById[item.category.parent_id]} → ${item.category.name}`
  }
  if (item.category?.name) return item.category.name
  return '–'
}

function isComboMaterial(material: Material) {
  return isComboMaterialType(material)
}

function isComboDraft(material: Material) {
  return isComboMaterial(material) && material.combo_status === 'draft'
}

function getMaterialIconClass(material: Material) {
  if (material.is_container) return 'container'
  if (material.is_food) return 'food'
  if (material.is_consumable) return 'consumable'
  if (isComboMaterial(material)) return 'container'
  return ''
}

function iconName(material: Material) {
  if (material.is_container) return 'mdi-package-variant-closed'
  if (isComboMaterial(material)) return 'mdi-triangle-outline'
  if (material.is_food) return 'mdi-coffee'
  if (material.is_consumable) return 'mdi-minus-circle-outline'
  return 'mdi-cube-outline'
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
