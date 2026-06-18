<template>
  <div class="repair-sheet-editor" :class="[`mode-${mode}`]">
    <div v-if="showSheetMeta" class="sheet-meta">
      <span class="template-name">{{ template.name }}</span>
      <span v-if="priceSource" class="price-source-badge">{{ priceSourceLabel }}</span>
    </div>

    <RepairSheetDiagram
      :diagram="template.diagram_json"
      :active-section-key="checklist.active_section_key"
      :selected-marker-ids="checklist.marker_ids"
      :readonly="isReadonly"
      @section-select="setActiveSection"
      @marker-toggle="toggleMarker"
    />

    <div v-if="showScopeChoice" class="scope-choice">
      <span class="scope-label">{{ t('workshop.repairSheet.scopeLabel') }}</span>
      <div class="scope-options">
        <button
          type="button"
          class="scope-btn"
          :class="{ active: checklist.scope === 'partial' }"
          :disabled="isReadonly"
          @click="setScope('partial')"
        >
          {{ t('workshop.repairSheet.scopePartial') }}
        </button>
        <button
          type="button"
          class="scope-btn"
          :class="{ active: checklist.scope === 'whole_unit' }"
          :disabled="isReadonly"
          @click="setScope('whole_unit')"
        >
          {{ t('workshop.repairSheet.scopeWholeUnit') }}
        </button>
      </div>
    </div>

    <div v-if="checklist.scope === 'partial'" class="sections">
      <details
        v-for="section in sections"
        :key="section.key"
        class="section-panel"
        :open="checklist.active_section_key === section.key"
      >
        <summary class="section-summary" @click.prevent="setActiveSection(section.key)">
          <span>{{ section.label }}</span>
          <span v-if="showPrices" class="section-subtotal">{{ formatChf(sectionSubtotal(section.key)) }}</span>
        </summary>
        <div class="section-body">
          <div
            v-for="item in visibleItems(section)"
            :key="item.key"
            class="position-row"
          >
            <div class="position-label">
              <span>{{ item.label }}</span>
              <span v-if="showPrices" class="position-unit-price">
                {{ unitPriceLabel(item.key) }}
              </span>
            </div>
            <div v-if="showQuantities" class="position-qty">
              <button
                type="button"
                class="qty-btn"
                :disabled="isReadonly || itemQuantity(item.key) <= 0"
                @click="changeQuantity(item.key, -1)"
              >
                −
              </button>
              <span class="qty-value">{{ itemQuantity(item.key) }}</span>
              <button
                type="button"
                class="qty-btn"
                :disabled="isReadonly"
                @click="changeQuantity(item.key, 1)"
              >
                +
              </button>
            </div>
            <div v-if="showPrices" class="position-total">
              {{ formatChf(lineTotal(item.key)) }}
            </div>
          </div>
        </div>
      </details>
    </div>

    <div v-else class="whole-unit-hint">
      {{ t('workshop.repairSheet.wholeUnitHint') }}
    </div>

    <ETextarea
      :model-value="checklist.notes"
      :label="t('workshop.repairSheet.notesLabel')"
      :placeholder="t('workshop.repairSheet.notesPlaceholder')"
      :readonly="isReadonly"
      rows="3"
      hide-details="auto"
      class="notes-field"
      @update:model-value="updateNotes"
    />

    <div v-if="showPrices" class="totals-card">
      <div v-if="checklist.scope === 'partial'" class="total-row">
        <span>{{ t('workshop.repairSheet.positionsSubtotal') }}</span>
        <strong>{{ formatChf(totals.positionsSubtotal) }}</strong>
      </div>
      <div v-if="totals.flatRate > 0" class="total-row">
        <span>{{ t('workshop.repairSheet.flatRate') }}</span>
        <strong>{{ formatChf(totals.flatRate) }}</strong>
      </div>
      <div class="total-row grand">
        <span>{{ t('workshop.repairSheet.grandTotal') }}</span>
        <strong>{{ formatChf(totals.grandTotal) }}</strong>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import RepairSheetDiagram from '@/components/workshop/RepairSheetDiagram.vue'
import { ETextarea } from '@/components/form/base'
import type {
  RepairChecklist,
  RepairSheetEditorMode,
  RepairSheetPriceSource,
  RepairSheetTemplateInput,
} from '@/types/repairChecklist'
import {
  calcLineTotal,
  calcRepairSheetTotal,
  createEmptyRepairChecklist,
  formatChfAmount,
  hydrateChecklistItems,
  normalizeRepairChecklist,
  parseUnitPrice,
} from '@/types/repairChecklist'
import type { RepairTemplateStructureSection } from '@/api/repairTemplates'

const props = withDefaults(
  defineProps<{
    modelValue?: RepairChecklist | Record<string, unknown> | null
    template: RepairSheetTemplateInput
    mode?: RepairSheetEditorMode
    priceSource?: RepairSheetPriceSource | null
  }>(),
  {
    modelValue: null,
    mode: 'edit',
    priceSource: null,
  }
)

const emit = defineEmits<{
  'update:modelValue': [value: RepairChecklist]
}>()

const { t } = useI18n()

const checklist = ref<RepairChecklist>(
  normalizeRepairChecklist(props.modelValue, props.template)
)

const isReportMode = computed(() => props.mode === 'report')
const isReadonly = computed(() => props.mode === 'readonly')
const showPrices = computed(() => !isReportMode.value)
const showQuantities = computed(() => !isReportMode.value)
const showSheetMeta = computed(() => isReportMode.value || !!props.priceSource)
const showScopeChoice = computed(() => props.template.structure_json.whole_unit_option === true)

const sections = computed(() => props.template.structure_json.sections ?? [])

const priceSourceLabel = computed(() => {
  if (!props.priceSource) return ''
  return props.priceSource === 'supplier'
    ? t('workshop.repairSheet.priceSourceSupplier')
    : t('workshop.repairSheet.priceSourceDepartment')
})

const totals = computed(() => calcRepairSheetTotal(checklist.value, props.template))

watch(
  () => props.modelValue,
  (value) => {
    checklist.value = normalizeRepairChecklist(value, props.template)
  },
  { deep: true }
)

watch(
  () => props.template,
  (template) => {
    checklist.value = hydrateChecklistItems(
      normalizeRepairChecklist(checklist.value, template),
      template
    )
  },
  { deep: true }
)

function emitChecklist() {
  emit('update:modelValue', structuredClone(checklist.value))
}

function formatChf(amount: number): string {
  return `${formatChfAmount(amount)} CHF`
}

function unitPriceLabel(itemKey: string): string {
  const entry = props.template.prices_json[itemKey]
  if (!entry?.is_active) return t('workshop.repairSheet.positionInactive')
  const price = parseUnitPrice(entry.unit_price_chf)
  if (price <= 0) return t('workshop.repairSheet.noUnitPrice')
  return `${formatChfAmount(price)} CHF / Stk.`
}

function visibleItems(section: RepairTemplateStructureSection) {
  return (section.items ?? []).filter((item) => {
    if (isReportMode.value) return true
    const priceEntry = props.template.prices_json[item.key]
    return priceEntry?.is_active !== false
  })
}

function itemQuantity(itemKey: string): number {
  return checklist.value.items[itemKey]?.quantity ?? 0
}

function lineTotal(itemKey: string): number {
  const qty = itemQuantity(itemKey)
  const unitPrice = props.template.prices_json[itemKey]?.unit_price_chf
  return calcLineTotal(qty, unitPrice)
}

function sectionSubtotal(sectionKey: string): number {
  const section = sections.value.find((s) => s.key === sectionKey)
  if (!section) return 0
  return visibleItems(section).reduce((sum, item) => sum + lineTotal(item.key), 0)
}

function setActiveSection(sectionKey: string) {
  checklist.value.active_section_key = sectionKey
  emitChecklist()
}

function toggleMarker(markerId: string, sectionKey: string) {
  if (isReadonly.value) return
  const ids = [...checklist.value.marker_ids]
  const index = ids.indexOf(markerId)
  if (index >= 0) {
    ids.splice(index, 1)
  } else {
    ids.push(markerId)
  }
  checklist.value.marker_ids = ids
  checklist.value.active_section_key = sectionKey
  emitChecklist()
}

function setScope(scope: RepairChecklist['scope']) {
  if (isReadonly.value) return
  checklist.value.scope = scope
  emitChecklist()
}

function updateNotes(value: string) {
  checklist.value.notes = value
  emitChecklist()
}

function changeQuantity(itemKey: string, delta: number) {
  if (isReadonly.value) return
  if (!checklist.value.items[itemKey]) {
    checklist.value.items[itemKey] = { quantity: 0 }
  }
  const next = Math.max(0, (checklist.value.items[itemKey].quantity ?? 0) + delta)
  checklist.value.items[itemKey].quantity = next
  emitChecklist()
}

if (!checklist.value.template_key) {
  checklist.value.template_key = props.template.template_key
}
</script>

<style scoped>
.repair-sheet-editor {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sheet-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.template-name {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.price-source-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 999px;
  background: #ede9fe;
  color: #6d28d9;
}

.scope-choice {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.scope-label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.scope-options {
  display: inline-flex;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  overflow: hidden;
}

.scope-btn {
  border: none;
  background: #fff;
  padding: 8px 14px;
  font-size: 13px;
  cursor: pointer;
  color: #374151;
}

.scope-btn.active {
  background: #2563eb;
  color: #fff;
}

.scope-btn:disabled {
  cursor: default;
  opacity: 0.85;
}

.sections {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.section-panel {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  overflow: hidden;
}

.section-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  color: #111827;
  list-style: none;
}

.section-summary::-webkit-details-marker {
  display: none;
}

.section-subtotal {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
}

.section-body {
  border-top: 1px solid #f3f4f6;
  padding: 8px 14px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.position-row {
  display: grid;
  grid-template-columns: 1fr auto 88px;
  gap: 12px;
  align-items: center;
}

.position-label {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.position-label span:first-child {
  font-size: 13px;
  color: #111827;
}

.position-unit-price {
  font-size: 11px;
  color: #6b7280;
}

.position-qty {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.qty-btn {
  width: 28px;
  height: 28px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
  font-size: 16px;
  line-height: 1;
}

.qty-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.qty-value {
  min-width: 20px;
  text-align: center;
  font-weight: 600;
  font-size: 14px;
}

.position-total {
  text-align: right;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.whole-unit-hint {
  padding: 12px 14px;
  border-radius: 10px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 13px;
}

.notes-field {
  margin-top: 4px;
}

.totals-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f9fafb;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  color: #4b5563;
}

.total-row.grand {
  padding-top: 8px;
  border-top: 1px solid #e5e7eb;
  font-size: 14px;
  color: #111827;
}

@media (max-width: 640px) {
  .position-row {
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .position-total {
    text-align: left;
  }
}
</style>
