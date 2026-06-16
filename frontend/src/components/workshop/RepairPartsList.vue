<template>
  <div v-if="visible" class="repair-parts-list">
    <div class="panel-header">
      <div>
        <div class="modal-section-title">{{ t('workshop.repairPartsList.title') }}</div>
        <p class="panel-hint">{{ t('workshop.repairPartsList.subtitle') }}</p>
      </div>
      <EButton
        v-if="!isReadonly"
        variant="primary"
        size="small"
        :loading="isSaving"
        :disabled="!hasChanges || isSaving"
        @click="savePartsList"
      >
        {{ isSaving ? t('common.saving') : t('common.save') }}
      </EButton>
    </div>

    <ELoadingState
      v-if="isLoadingSettings"
      variant="inline"
      :message="t('workshop.repairPartsList.loading')"
    />

    <template v-else>
      <div v-if="lines.length === 0 && isReadonly" class="parts-empty">
        {{ t('workshop.repairPartsList.empty') }}
      </div>

      <div v-else-if="isReadonly" class="parts-table-wrap">
        <table class="parts-table parts-table--readonly">
          <thead>
            <tr>
              <th>{{ t('workshop.repairPartsList.colMaterial') }}</th>
              <th>{{ t('workshop.repairPartsList.colQuantity') }}</th>
              <th>{{ t('workshop.repairPartsList.colSource') }}</th>
              <th>{{ t('workshop.repairPartsList.colStatus') }}</th>
              <th>{{ t('workshop.repairPartsList.colUnitCost') }}</th>
              <th>{{ t('workshop.repairPartsList.colLineTotal') }}</th>
              <th>{{ t('workshop.repairPartsList.colStock') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in lines" :key="line.id">
              <td><span class="material-name">{{ line.material_name || line.material_item_id }}</span></td>
              <td>{{ formatRepairPartQuantity(line) }}</td>
              <td>{{ sourceLabel(line.source) }}</td>
              <td>
                <template v-if="line.source === 'rest'">
                  {{ restStockLabel(line) }}
                </template>
                <template v-else>{{ statusLabel(line.status) }}</template>
              </td>
              <td>{{ formatUnitCost(line.unit_cost) }}</td>
              <td>{{ formatLineTotal(line) }}</td>
              <td>
                <span
                  v-if="line.source === 'stock' && line.material_item_id"
                  class="stock-badge"
                  :class="stockBadgeClass(line)"
                >
                  {{ stockLabel(line) }}
                </span>
                <span v-else class="stock-na">{{ t('workshop.repairPartsList.stockNa') }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="parts-lines">
        <div v-for="(line, index) in lines" :key="line.id" class="parts-line">
          <div class="parts-line-top">
            <div class="parts-line-material">
              <label class="parts-field-label">{{ t('workshop.repairPartsList.colMaterial') }}</label>
              <template v-if="line.material_item_id">
                <div class="parts-selected-material">
                  <span class="material-name">{{ line.material_name || line.material_item_id }}</span>
                  <button
                    type="button"
                    class="parts-change-material-btn"
                    @click="clearMaterialSelection(index)"
                  >
                    {{ t('workshop.repairPartsList.changeMaterial') }}
                  </button>
                </div>
              </template>
              <MaterialLookupInput
                v-else
                :model-value="materialQueries[index] || ''"
                :fetcher="materialFetcher"
                :placeholder="t('workshop.repairPartsList.searchMaterial')"
                :min-chars="1"
                  :max-suggestions="12"
                :teleport-dropdown="true"
                :dropdown-min-width="320"
                :get-result-label="(item) => item.name"
                :get-result-secondary="materialResultSecondary"
                @select="(item) => onMaterialSelected(index, item)"
                @update:model-value="(value) => onMaterialQueryChange(index, value)"
              >
                <template #results="{ results, query, isLoading, selectResult }">
                  <div v-if="isLoading" class="mat-dropdown-loading">{{ t('components.materialLookup.loading') }}</div>
                  <div v-else-if="results.length === 0" class="mat-dropdown-empty-wrap">
                    <p class="mat-dropdown-empty">{{ t('workshop.repairPartsList.searchEmpty') }}</p>
                    <button
                      v-if="sparePartsCategoryId && query.trim()"
                      type="button"
                      class="mat-dropdown-create-btn"
                      @mousedown.prevent="openQuickCreate(index, query)"
                    >
                      {{ t('workshop.repairPartsList.createMaterial', { name: query.trim() }) }}
                    </button>
                  </div>
                  <div v-else class="mat-dropdown-list">
                    <button
                      v-for="(item, resultIndex) in results"
                      :key="String(item.id || resultIndex)"
                      type="button"
                      class="mat-dropdown-item material-lookup-item"
                      @mousedown.prevent="selectResult(item)"
                    >
                      <span class="mat-dropdown-name">{{ item.name }}</span>
                      <span class="mat-dropdown-meta">{{ materialResultSecondary(item) }}</span>
                    </button>
                    <button
                      v-if="sparePartsCategoryId && query.trim()"
                      type="button"
                      class="mat-dropdown-create-btn mat-dropdown-create-btn--inline"
                      @mousedown.prevent="openQuickCreate(index, query)"
                    >
                      {{ t('workshop.repairPartsList.createMaterial', { name: query.trim() }) }}
                    </button>
                  </div>
                </template>
              </MaterialLookupInput>
            </div>
            <button
              v-if="line.status !== 'consumed'"
              type="button"
              class="parts-remove-btn"
              :title="t('common.delete')"
              @click="removeLine(index)"
            >
              <v-icon icon="mdi-close" size="18" />
            </button>
          </div>

          <div
            class="parts-line-grid"
            :class="{ 'parts-line-grid--rest': line.source === 'rest' }"
          >
            <div class="parts-field parts-field--source">
              <label class="parts-field-label">{{ t('workshop.repairPartsList.colSource') }}</label>
              <ESelect
                :model-value="line.source"
                :items="sourceItems"
                hide-details="auto"
                density="compact"
                @update:model-value="(value) => onSourceChange(line, value)"
              />
            </div>

            <template v-if="line.source === 'rest'">
              <div class="parts-field parts-field--available">
                <label class="parts-field-label">{{ availableQtyLabel(line) }}</label>
                <input
                  v-model.number="line.available_qty"
                  type="number"
                  min="0"
                  step="any"
                  class="form-input"
                  :placeholder="t('workshop.repairPartsList.availableQtyPlaceholder')"
                />
              </div>
              <div class="parts-field parts-field--qty">
                <label class="parts-field-label">{{ consumptionLabel(line) }}</label>
                <input
                  v-model.number="line.quantity"
                  type="number"
                  min="0.01"
                  step="any"
                  class="form-input"
                />
              </div>
              <div class="parts-field parts-field--cost">
                <label class="parts-field-label">{{ unitCostLabel(line) }}</label>
                <input
                  v-if="line.material_item_id"
                  v-model="line.unit_cost"
                  type="number"
                  min="0"
                  step="0.05"
                  class="form-input"
                  :placeholder="t('workshop.repairPartsList.unitCostPlaceholder')"
                />
                <span v-else class="parts-field-readonly">{{ t('workshop.repairPartsList.stockNa') }}</span>
              </div>
              <div class="parts-field parts-field--total">
                <label class="parts-field-label">{{ t('workshop.repairPartsList.colLineTotal') }}</label>
                <span class="line-total">{{ formatLineTotal(line) }}</span>
              </div>
              <div class="parts-field parts-field--rest-stock">
                <label class="parts-field-label">{{ t('workshop.repairPartsList.colRestRemaining') }}</label>
                <span class="stock-badge" :class="restStockBadgeClass(line)">
                  {{ restStockLabel(line) }}
                </span>
              </div>
              <p class="parts-rest-hint">
                {{ t('workshop.repairPartsList.hintRestSource') }}
                {{ t('workshop.repairPartsList.hintRestSourcePrice') }}
              </p>
            </template>

            <template v-else>
              <div class="parts-field parts-field--qty">
                <label class="parts-field-label">{{ consumptionLabel(line) }}</label>
                <input
                  v-model.number="line.quantity"
                  type="number"
                  min="0.01"
                  step="any"
                  class="form-input"
                />
              </div>
              <div class="parts-field parts-field--status">
                <label class="parts-field-label">{{ t('workshop.repairPartsList.colStatus') }}</label>
                <ESelect
                  v-if="line.source !== 'purchase'"
                  v-model="line.status"
                  :items="statusItems"
                  hide-details="auto"
                  density="compact"
                />
                <span v-else class="parts-field-readonly">{{ statusLabel(line.status) }}</span>
              </div>
              <div class="parts-field parts-field--cost">
                <label class="parts-field-label">{{ t('workshop.repairPartsList.colUnitCost') }}</label>
                <input
                  v-if="line.material_item_id"
                  v-model="line.unit_cost"
                  type="number"
                  min="0"
                  step="0.05"
                  class="form-input"
                  :placeholder="t('workshop.repairPartsList.unitCostPlaceholder')"
                />
                <span v-else class="parts-field-readonly">{{ t('workshop.repairPartsList.stockNa') }}</span>
              </div>
              <div class="parts-field parts-field--total">
                <label class="parts-field-label">{{ t('workshop.repairPartsList.colLineTotal') }}</label>
                <span class="line-total">{{ formatLineTotal(line) }}</span>
              </div>
              <div class="parts-field parts-field--stock">
                <label class="parts-field-label">{{ t('workshop.repairPartsList.colStock') }}</label>
                <span
                  v-if="line.source === 'stock' && line.material_item_id"
                  class="stock-badge"
                  :class="stockBadgeClass(line)"
                >
                  {{ stockLabel(line) }}
                </span>
                <span v-else class="stock-na">{{ t('workshop.repairPartsList.stockNa') }}</span>
              </div>
            </template>
          </div>

          <div
            v-if="line.source === 'purchase' && ['planned', 'ordered'].includes(line.status)"
            class="parts-line-purchase"
          >
            <EButton
              v-if="line.status === 'planned'"
              variant="secondary"
              size="x-small"
              @click="openPurchaseDialog(line, 'order')"
            >
              {{ t('workshop.repairPartsList.actionOrder') }}
            </EButton>
            <EButton
              v-else-if="line.status === 'ordered'"
              variant="primary"
              size="x-small"
              @click="openPurchaseDialog(line, 'receive')"
            >
              {{ t('workshop.repairPartsList.actionReceive') }}
            </EButton>
          </div>
        </div>
      </div>

      <div class="parts-footer">
        <EButton
          v-if="!isReadonly"
          variant="secondary"
          size="small"
          class="parts-add-btn"
          @click="addLine"
        >
          <v-icon icon="mdi-plus" start size="18" />
          {{ t('workshop.repairPartsList.addLine') }}
        </EButton>
        <p v-if="lines.length > 0" class="parts-total">
          {{ t('workshop.repairPartsList.materialTotal', { amount: formatMaterialTotal() }) }}
        </p>
      </div>
    </template>

    <RepairPartQuickCreateDialog
      v-model="quickCreateOpen"
      :department-id="departmentId"
      :category-id="sparePartsCategoryId"
      :initial-name="quickCreateName"
      @created="onQuickCreateMaterial"
    />

    <PurchaseLineDialog
      v-model="purchaseDialogOpen"
      :ticket-id="ticket.id"
      :department-id="departmentId"
      :line="purchaseDialogLine"
      :mode="purchaseDialogMode"
      @updated="onPurchaseUpdated"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { updateWorkshopTicket, type WorkshopTicket } from '@/api/workshop'
import { getMaterial, getMaterials, type Material } from '@/api/materials'
import { resolveRepairPartUnitCost } from '@/utils/repairPartUnitCost'
import { formatRepairPartQuantity } from '@/utils/workshopPartsCompletion'
import { getWorkshopSettings } from '@/api/departmentSettings'
import { ticketUsesPartsList } from '@/composables/useWorkshopTriageOptions'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import PurchaseLineDialog from '@/components/workshop/PurchaseLineDialog.vue'
import RepairPartQuickCreateDialog, {
  type RepairPartQuickCreateResult,
} from '@/components/workshop/RepairPartQuickCreateDialog.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ESelect } from '@/components/form/base'
import {
  createRepairPartLine,
  normalizeRepairPartsList,
  repairPartsListToPayload,
  REPAIR_PART_SOURCES,
  REPAIR_PART_STATUSES,
  type RepairPartLine,
  type RepairPartSource,
  type RepairPartStatus,
} from '@/types/repairPartsList'

const props = defineProps<{
  ticket: WorkshopTicket
  departmentId: string
}>()

const emit = defineEmits<{
  updated: [ticket: WorkshopTicket]
}>()

const { t } = useI18n()
const toast = useToast()

const isLoadingSettings = ref(false)
const isSaving = ref(false)
const sparePartsCategoryId = ref('')
const lines = ref<RepairPartLine[]>([])
const savedLinesJson = ref('[]')
const materialQueries = ref<string[]>([])
const stockByMaterialId = ref<Record<string, number>>({})
const purchaseDialogOpen = ref(false)
const purchaseDialogLine = ref<RepairPartLine | null>(null)
const purchaseDialogMode = ref<'order' | 'receive'>('order')
const quickCreateOpen = ref(false)
const quickCreateName = ref('')
const quickCreateLineIndex = ref<number | null>(null)

const visible = computed(() => ticketUsesPartsList(props.ticket))

const isReadonly = computed(
  () => props.ticket.status === 'completed' || props.ticket.status === 'cancelled',
)

const hasChanges = computed(() => JSON.stringify(lines.value) !== savedLinesJson.value)

const AUTO_SAVE_DELAY_MS = 500
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null

function scheduleAutoSavePartsList() {
  if (isReadonly.value) return
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
  autoSaveTimer = setTimeout(() => {
    autoSaveTimer = null
    void autoSavePartsList()
  }, AUTO_SAVE_DELAY_MS)
}

watch(hasChanges, (dirty) => {
  if (dirty) scheduleAutoSavePartsList()
})

onBeforeUnmount(() => {
  if (autoSaveTimer) clearTimeout(autoSaveTimer)
})

const sourceItems = computed(() =>
  REPAIR_PART_SOURCES.map((value) => ({
    value,
    title: t(`workshop.repairPartsList.source.${value}`),
  })),
)

const statusItems = computed(() =>
  REPAIR_PART_STATUSES.map((value) => ({
    value,
    title: t(`workshop.repairPartsList.status.${value}`),
  })),
)

watch(
  () => [props.ticket.id, props.ticket.strategy, props.departmentId],
  () => {
    void init()
  },
  { immediate: true },
)

watch(
  () => props.ticket.parts_used,
  () => {
    if (!visible.value || hasChanges.value) return
    syncLinesFromTicket()
    void refreshStockCache()
  },
  { deep: true },
)

function syncLinesFromTicket() {
  lines.value = normalizeRepairPartsList(props.ticket.parts_used)
  savedLinesJson.value = JSON.stringify(lines.value)
  materialQueries.value = lines.value.map((line) => line.material_name || '')
}

async function init() {
  if (!visible.value || !props.departmentId) return

  isLoadingSettings.value = true
  try {
    const settings = await getWorkshopSettings(props.departmentId)
    sparePartsCategoryId.value = settings.sparePartsCategoryId
    syncLinesFromTicket()
    await refreshStockCache()
  } catch (err) {
    console.error('Failed to load repair parts list:', err)
  } finally {
    isLoadingSettings.value = false
  }
}

async function materialFetcher(query: string) {
  if (!props.departmentId || !query.trim()) return []

  const materials = await getMaterials(props.departmentId, { search: query })
  const spareId = sparePartsCategoryId.value

  return [...materials]
    .sort((a, b) => {
      const aSpare = a.category?.id === spareId ? 0 : 1
      const bSpare = b.category?.id === spareId ? 0 : 1
      if (aSpare !== bSpare) return aSpare - bSpare

      const aStock = Number(a.available ?? a.total_stock ?? 0)
      const bStock = Number(b.available ?? b.total_stock ?? 0)
      if (bStock !== aStock) return bStock - aStock

      return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' })
    })
    .slice(0, 12)
}

function materialResultSecondary(item: Record<string, unknown>) {
  const material = item as unknown as Material
  const available = Number(material.available ?? material.total_stock ?? 0)
  const categoryName = material.category?.name
  const isSparePart =
    !sparePartsCategoryId.value || material.category?.id === sparePartsCategoryId.value

  if (available <= 0) {
    return categoryName && !isSparePart
      ? t('workshop.repairPartsList.searchResultNoStockCategory', { category: categoryName })
      : t('workshop.repairPartsList.searchResultNoStock')
  }

  const stockText = t('workshop.repairPartsList.stockAvailable', { count: available })
  if (!isSparePart && categoryName) {
    return t('workshop.repairPartsList.searchResultOtherCategory', {
      stock: stockText,
      category: categoryName,
    })
  }
  return stockText
}

function onMaterialQueryChange(index: number, value: string) {
  materialQueries.value[index] = value
}

function openQuickCreate(index: number, query: string) {
  quickCreateLineIndex.value = index
  quickCreateName.value = query.trim()
  quickCreateOpen.value = true
}

async function onQuickCreateMaterial(result: RepairPartQuickCreateResult) {
  const index = quickCreateLineIndex.value
  if (index === null) return
  await onMaterialSelected(index, result.material)

  const line = lines.value[index]
  if (!line) return

  line.source = 'rest'
  line.status = 'planned'
  line.quantity_unit = result.packUnit === 'Stk' ? 'Stk' : result.packUnit

  if (result.availableQty != null && result.availableQty > 0) {
    line.available_qty = result.availableQty
    if (!line.quantity || line.quantity <= 0) {
      line.quantity = 1
    }
  }

  await autoSavePartsList()
}

function resolveQuantityUnit(material: Material): string {
  const raw = (material.pack_unit || '').trim()
  if (!raw) return 'Stk'
  const lower = raw.toLowerCase()
  if (['m', 'meter', 'metre'].includes(lower)) return 'm'
  if (['m2', 'm²', 'qm'].includes(lower)) return 'm²'
  return raw
}

function unitLabel(line: RepairPartLine): string {
  return line.quantity_unit || 'Stk'
}

function availableQtyLabel(line: RepairPartLine): string {
  return t('workshop.repairPartsList.colAvailableQty', { unit: unitLabel(line) })
}

function consumptionLabel(line: RepairPartLine): string {
  return t('workshop.repairPartsList.colConsumption', { unit: unitLabel(line) })
}

function unitCostLabel(line: RepairPartLine): string {
  const unit = unitLabel(line)
  if (unit === 'Stk') return t('workshop.repairPartsList.colUnitCost')
  return t('workshop.repairPartsList.colUnitCostPerUnit', { unit })
}

function restRemaining(line: RepairPartLine): number | null {
  if (line.available_qty == null || !Number.isFinite(Number(line.available_qty))) return null
  return Math.max(0, Number(line.available_qty) - line.quantity)
}

function restStockLabel(line: RepairPartLine): string {
  const remaining = restRemaining(line)
  if (remaining === null) return t('workshop.repairPartsList.restStockOpen')
  const unit = unitLabel(line)
  return t('workshop.repairPartsList.restStockRemaining', { amount: formatQty(remaining), unit })
}

function restStockBadgeClass(line: RepairPartLine): string {
  const remaining = restRemaining(line)
  if (remaining === null) return 'stock-unknown'
  if (remaining >= 0 && (line.available_qty == null || Number(line.available_qty) >= line.quantity)) {
    return 'stock-ok'
  }
  return 'stock-low'
}

function formatQty(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(2)
}

async function onMaterialSelected(index: number, item: Material | Record<string, unknown>) {
  let material = item as Material
  const line = lines.value[index]
  if (!line) return

  if (material.id) {
    try {
      material = await getMaterial(material.id)
    } catch {
      // Suche liefert oft weniger Felder — mit Teil-Daten weiter
    }
  }

  line.material_item_id = material.id
  line.material_name = material.name
  line.unit_cost = resolveRepairPartUnitCost(material)
  line.quantity_unit = resolveQuantityUnit(material)
  materialQueries.value[index] = material.name
  stockByMaterialId.value[material.id] = Number(material.available ?? material.total_stock ?? 0)

  const isSparePart =
    !sparePartsCategoryId.value || material.category?.id === sparePartsCategoryId.value
  const hasStock = stockByMaterialId.value[material.id] > 0
  if (!isSparePart && hasStock && line.source === 'rest') {
    line.source = 'stock'
  }

  await autoSavePartsList()
}

async function refreshStockCache() {
  const ids = [...new Set(lines.value.map((line) => line.material_item_id).filter(Boolean))]
  if (ids.length === 0) return

  try {
    const materials = await Promise.all(
      ids.map((id) => getMaterial(id).catch(() => null)),
    )
    const next: Record<string, number> = { ...stockByMaterialId.value }
    for (const material of materials) {
      if (!material) continue
      next[material.id] = Number(material.available ?? material.total_stock ?? 0)
    }
    stockByMaterialId.value = next
  } catch {
    // Bestand optional — Anzeige bleibt leer
  }
}

function addLine() {
  lines.value.push(createRepairPartLine())
  materialQueries.value.push('')
}

function removeLine(index: number) {
  lines.value.splice(index, 1)
  materialQueries.value.splice(index, 1)
  scheduleAutoSavePartsList()
}

function clearMaterialSelection(index: number) {
  const line = lines.value[index]
  if (!line) return
  line.material_item_id = ''
  line.material_name = null
  line.unit_cost = null
  line.quantity_unit = null
  line.available_qty = null
  materialQueries.value[index] = ''
}

function stockForLine(line: RepairPartLine): number | null {
  if (!line.material_item_id) return null
  const stock = stockByMaterialId.value[line.material_item_id]
  return typeof stock === 'number' ? stock : null
}

function stockBadgeClass(line: RepairPartLine): string {
  const stock = stockForLine(line)
  if (stock === null) return 'stock-unknown'
  return stock >= line.quantity ? 'stock-ok' : 'stock-low'
}

function stockLabel(line: RepairPartLine): string {
  const stock = stockForLine(line)
  if (stock === null) return t('workshop.repairPartsList.stockUnknown')
  return t('workshop.repairPartsList.stockOf', { available: stock, needed: line.quantity })
}

function formatUnitCost(value: string | null): string {
  if (!value) return '—'
  const num = Number(value)
  if (!Number.isFinite(num)) return '—'
  return t('workshop.repairPartsList.unitCostChf', { amount: num.toFixed(2) })
}

function lineTotalValue(line: RepairPartLine): number {
  const unit = Number(line.unit_cost ?? 0)
  if (!Number.isFinite(unit)) return 0
  return unit * line.quantity
}

function formatLineTotal(line: RepairPartLine): string {
  const total = lineTotalValue(line)
  if (total <= 0) return '—'
  return t('workshop.repairPartsList.unitCostChf', { amount: total.toFixed(2) })
}

function formatMaterialTotal(): string {
  const total = lines.value.reduce((sum, line) => sum + lineTotalValue(line), 0)
  return total.toFixed(2)
}

function sourceLabel(source: RepairPartSource): string {
  return t(`workshop.repairPartsList.source.${source}`)
}

function statusLabel(status: RepairPartStatus): string {
  return t(`workshop.repairPartsList.status.${status}`)
}

function openPurchaseDialog(line: RepairPartLine, mode: 'order' | 'receive') {
  purchaseDialogLine.value = line
  purchaseDialogMode.value = mode
  purchaseDialogOpen.value = true
}

function onPurchaseUpdated(ticket: WorkshopTicket) {
  lines.value = normalizeRepairPartsList(ticket.parts_used)
  savedLinesJson.value = JSON.stringify(lines.value)
  materialQueries.value = lines.value.map((line) => line.material_name || '')
  emit('updated', ticket)
}

function onSourceChange(line: RepairPartLine, value: RepairPartSource) {
  line.source = value
  if (value === 'rest') {
    line.status = 'planned'
    if (line.available_qty == null) {
      line.available_qty = line.quantity > 0 ? line.quantity : null
    }
    return
  }

  line.available_qty = null

  if (value === 'purchase' && line.status === 'consumed') {
    line.status = 'planned'
  } else if (value === 'purchase' && line.status !== 'ordered' && line.status !== 'received') {
    line.status = 'planned'
  } else if (value === 'stock' && (line.status === 'ordered' || line.status === 'received')) {
    line.status = 'planned'
  }

  scheduleAutoSavePartsList()
}

async function savePartsList(options?: { silent?: boolean }): Promise<boolean> {
  if (!props.ticket.id || isReadonly.value) return true

  const payload = repairPartsListToPayload(lines.value).filter((line) => line.material_item_id)
  if (payload.length === 0 && !hasChanges.value) return true

  isSaving.value = true
  try {
    const updated = await updateWorkshopTicket(props.ticket.id, {
      parts_used: payload,
    })
    if (Array.isArray(updated.parts_used)) {
      lines.value = normalizeRepairPartsList(updated.parts_used)
      savedLinesJson.value = JSON.stringify(lines.value)
      materialQueries.value = lines.value.map((line) => line.material_name || '')
    } else {
      savedLinesJson.value = JSON.stringify(lines.value)
    }
    const ticketForEmit = Array.isArray(updated.parts_used)
      ? updated
      : { ...updated, parts_used: payload }
    emit('updated', ticketForEmit)
    if (!options?.silent) {
      toast.success(t('workshop.repairPartsList.toastSaved'))
    }
    return true
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(message || t('workshop.repairPartsList.toastSaveError'))
    return false
  } finally {
    isSaving.value = false
  }
}

async function autoSavePartsList(): Promise<void> {
  if (!hasChanges.value || isReadonly.value) return
  if (isSaving.value) {
    scheduleAutoSavePartsList()
    return
  }
  const hasMaterial = lines.value.some((line) => line.material_item_id)
  if (!hasMaterial) return
  await savePartsList({ silent: true })
  if (hasChanges.value) scheduleAutoSavePartsList()
}

async function saveIfDirty(): Promise<boolean> {
  if (!hasChanges.value || isReadonly.value) return true
  return savePartsList({ silent: true })
}

defineExpose({ saveIfDirty })
</script>

<style scoped>
.repair-parts-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.panel-hint {
  margin: 4px 0 0;
  font-size: 12px;
  color: #6b7280;
}

.parts-empty {
  font-size: 13px;
  color: #6b7280;
}

.parts-lines {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.parts-line {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  padding: 12px;
}

.parts-line-top {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.parts-line-material {
  flex: 1;
  min-width: 0;
}

.parts-line-material :deep(.material-lookup) {
  width: 100%;
}

.parts-line-material :deep(.material-lookup-input) {
  min-height: 38px;
}

.parts-selected-material {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  min-height: 38px;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #f9fafb;
}

.parts-change-material-btn {
  flex-shrink: 0;
  border: none;
  background: transparent;
  color: #2563eb;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.parts-change-material-btn:hover {
  text-decoration: underline;
}

.parts-line-grid {
  display: grid;
  grid-template-columns: minmax(120px, 1fr) 64px minmax(100px, 1fr) 76px 76px minmax(88px, 1fr);
  gap: 8px;
  margin-top: 10px;
  align-items: end;
}

.parts-line-grid--rest {
  grid-template-columns: minmax(130px, 1.1fr) 88px 88px 76px 76px minmax(100px, 1fr);
}

.parts-rest-hint {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 12px;
  color: #92400e;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  padding: 8px 10px;
  line-height: 1.45;
}

.parts-field-label {
  display: block;
  margin-bottom: 4px;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.parts-field-readonly {
  display: flex;
  align-items: center;
  min-height: 36px;
  font-size: 13px;
  color: #6b7280;
}

.parts-field .form-input {
  width: 100%;
  padding: 7px 8px;
  font-size: 13px;
}

.parts-field--total .line-total {
  display: flex;
  align-items: center;
  min-height: 36px;
}

.parts-line-purchase {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed #e5e7eb;
}

.parts-table-wrap {
  overflow-x: auto;
}

.parts-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  table-layout: fixed;
}

.parts-table th,
.parts-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  vertical-align: middle;
}

.parts-table th {
  font-weight: 600;
  color: #374151;
  background: #f9fafb;
  font-size: 12px;
}

.parts-table--readonly th:nth-child(1) { width: 34%; }
.parts-table--readonly th:nth-child(2) { width: 8%; }
.parts-table--readonly th:nth-child(3) { width: 12%; }
.parts-table--readonly th:nth-child(4) { width: 12%; }
.parts-table--readonly th:nth-child(5) { width: 11%; }
.parts-table--readonly th:nth-child(6) { width: 11%; }
.parts-table--readonly th:nth-child(7) { width: 12%; }

.material-name {
  font-weight: 500;
  word-break: break-word;
}

.line-total {
  font-weight: 600;
  color: #1f2937;
  font-size: 13px;
}

.stock-badge {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
}

.stock-badge.stock-ok {
  background: #dcfce7;
  color: #166534;
}

.stock-badge.stock-low {
  background: #fee2e2;
  color: #b91c1c;
}

.stock-badge.stock-unknown {
  background: #f3f4f6;
  color: #6b7280;
}

.stock-na {
  color: #9ca3af;
  font-size: 12px;
}

.parts-remove-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
}

.parts-remove-btn:hover {
  background: #fee2e2;
  color: #b91c1c;
}

.parts-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.parts-total {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.mat-dropdown-list {
  display: flex;
  flex-direction: column;
}

.mat-dropdown-loading,
.mat-dropdown-empty {
  padding: 12px 14px;
  color: #6b7280;
  font-size: 13px;
}

.mat-dropdown-item {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  border: none;
  background: transparent;
  text-align: left;
  font-size: 14px;
  color: #111827;
  cursor: pointer;
}

.mat-dropdown-item:hover {
  background: #f0fdf4;
}

.mat-dropdown-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 500;
}

.mat-dropdown-meta {
  flex-shrink: 0;
  font-size: 12px;
  color: #6b7280;
  text-align: right;
  max-width: 48%;
  line-height: 1.3;
}

.mat-dropdown-empty-wrap {
  padding: 8px;
}

.mat-dropdown-create-btn {
  display: block;
  width: 100%;
  margin-top: 6px;
  padding: 8px 10px;
  border: 1px dashed #93c5fd;
  border-radius: 8px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 12px;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}

.mat-dropdown-create-btn--inline {
  margin-top: 4px;
}

.mat-dropdown-create-btn:hover {
  background: #dbeafe;
}

@media (max-width: 720px) {
  .parts-line-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .parts-field--qty,
  .parts-field--cost,
  .parts-field--total {
    grid-column: span 1;
  }

  .parts-field--source,
  .parts-field--status,
  .parts-field--stock {
    grid-column: span 1;
  }
}
</style>
