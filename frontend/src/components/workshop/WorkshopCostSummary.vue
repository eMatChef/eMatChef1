<template>
  <div class="workshop-cost-summary">
    <h4 class="cost-summary-title">{{ t('workshop.costSummary.title') }}</h4>
    <p class="cost-summary-hint">{{ t('workshop.costSummary.hint') }}</p>

    <ELoadingState
      v-if="templateLoading"
      variant="inline"
      :message="t('workshop.costSummary.loading')"
    />

    <template v-else>
      <div class="cost-block">
        <ECheckbox
          v-model="includeLabor"
          :label="t('workshop.costSummary.labor')"
          hide-details
          density="compact"
        />
        <div v-if="includeLabor" class="cost-block-fields">
          <ETextField
            v-model.number="laborHours"
            type="number"
            min="0"
            step="0.25"
            :label="t('workshop.costSummary.laborHours')"
            hide-details="auto"
          />
          <p class="cost-line-hint">
            {{ t('workshop.costSummary.laborCalc', {
              hours: laborHoursDisplay,
              rate: hourlyRateChf,
              amount: breakdown.labor_total_chf,
            }) }}
          </p>
        </div>
      </div>

      <div v-if="flatRateAvailable" class="cost-block">
        <ECheckbox
          v-model="includeFlatRate"
          :label="t('workshop.costSummary.flatRate')"
          hide-details
          density="compact"
        />
        <div v-if="includeFlatRate" class="cost-block-fields">
          <ETextField
            v-model="flatRateOverride"
            type="number"
            min="0"
            step="0.05"
            :label="t('workshop.costSummary.flatRateAmount')"
            hide-details="auto"
          />
        </div>
      </div>

      <div v-if="materialAvailable" class="cost-block">
        <ECheckbox
          v-model="includeMaterial"
          :label="t('workshop.costSummary.material')"
          hide-details
          density="compact"
        />
        <div v-if="includeMaterial" class="cost-block-fields">
          <p v-if="partsMaterialCost > 0" class="cost-line-hint">
            {{ t('workshop.costSummary.materialParts', { amount: breakdown.material_parts_chf }) }}
          </p>
          <template v-else-if="materialManualRequired">
            <p class="cost-line-hint">{{ t('workshop.costSummary.materialManualHint') }}</p>
            <ETextField
              v-model="materialManualOverride"
              type="number"
              min="0"
              step="0.05"
              :label="t('workshop.costSummary.materialManual')"
              hide-details="auto"
            />
          </template>
          <p v-if="sheetCosts.sheetPositionsCost > 0" class="cost-line-hint">
            {{ t('workshop.costSummary.materialSheet', { amount: breakdown.material_sheet_chf }) }}
          </p>
          <p v-if="breakdown.material_total_chf !== '0.00'" class="cost-line-total">
            {{ t('workshop.costSummary.materialTotal', { amount: breakdown.material_total_chf }) }}
          </p>
        </div>
      </div>

      <div class="cost-grand-total">
        <span>{{ t('workshop.costSummary.total') }}</span>
        <strong>CHF {{ breakdown.total_chf }}</strong>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { WorkshopTicket } from '@/api/workshop'
import { getDepartmentRepairTemplates } from '@/api/repairTemplates'
import {
  listDepartmentSupplierRepairTemplates,
  supplierTemplateToSheetInput,
} from '@/api/supplierRepairTemplates'
import { ECheckbox, ETextField } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  departmentTemplateToSheetInput,
  normalizeRepairChecklist,
  type RepairChecklist,
  type RepairSheetTemplateInput,
} from '@/types/repairChecklist'
import {
  buildCostBreakdown,
  resolveSheetMaterialCosts,
  suggestDefaultCostFlags,
  type WorkshopCostBreakdown,
} from '@/types/workshopCostSummary'

const props = defineProps<{
  ticket: WorkshopTicket
  departmentId: string
  hourlyRateChf: string
  partsMaterialCost: number
  /** Stückliste vorhanden (auch ohne hinterlegte EK-Preise) */
  hasRepairParts?: boolean
}>()

const emit = defineEmits<{
  'update:actualCost': [value: string]
  'update:costBreakdown': [value: WorkshopCostBreakdown]
}>()

const { t } = useI18n()

const templateLoading = ref(false)
const sheetTemplate = ref<RepairSheetTemplateInput | null>(null)
const repairChecklist = ref<RepairChecklist | null>(null)

const includeLabor = ref(false)
const includeFlatRate = ref(false)
const includeMaterial = ref(false)
const laborHours = ref(0)
const flatRateOverride = ref('')
const materialManualOverride = ref('')

const isExternalStrategy = computed(() =>
  ['external_repair', 'external_cleaning'].includes(props.ticket.strategy),
)

const sheetCosts = computed(() =>
  resolveSheetMaterialCosts(sheetTemplate.value, repairChecklist.value),
)

const flatRateAvailable = computed(
  () =>
    sheetCosts.value.suggestedFlatRate > 0
    || sheetCosts.value.wholeUnitFlatRate > 0
    || includeFlatRate.value,
)

const materialAvailable = computed(
  () =>
    props.partsMaterialCost > 0
    || props.hasRepairParts
    || sheetCosts.value.sheetPositionsCost > 0
    || includeMaterial.value,
)

const materialManualRequired = computed(
  () => props.hasRepairParts && props.partsMaterialCost <= 0,
)

const effectivePartsMaterialCost = computed(() => {
  if (props.partsMaterialCost > 0) return props.partsMaterialCost
  if (!includeMaterial.value) return 0
  const manual = Number.parseFloat(String(materialManualOverride.value).replace(',', '.'))
  return Number.isFinite(manual) && manual > 0 ? manual : 0
})

const laborHoursDisplay = computed(() => {
  const value = laborHours.value
  if (!Number.isFinite(value) || value <= 0) return '0'
  return Number.isInteger(value) ? String(value) : value.toFixed(2)
})

const breakdown = computed(() =>
  buildCostBreakdown({
    hourlyRateChf: props.hourlyRateChf,
    partsMaterialCost: effectivePartsMaterialCost.value,
    sheetTemplate: sheetTemplate.value,
    repairChecklist: repairChecklist.value,
    includeLabor: includeLabor.value,
    includeFlatRate: includeFlatRate.value,
    includeMaterial: includeMaterial.value,
    laborHours: laborHours.value,
    flatRateOverride: flatRateOverride.value,
  }),
)

watch(
  breakdown,
  (value) => {
    emit('update:actualCost', value.total_chf)
    emit('update:costBreakdown', value)
  },
  { immediate: true, deep: true },
)

watch(
  () => [props.ticket.id, props.ticket.strategy, props.ticket.repair_checklist, props.ticket.assigned_to_supplier_company?.id],
  () => {
    void loadTemplate()
  },
  { immediate: true, deep: true },
)

function applyDefaultFlags() {
  const defaults = suggestDefaultCostFlags(
    props.partsMaterialCost,
    sheetTemplate.value,
    repairChecklist.value,
    props.hasRepairParts,
  )
  includeLabor.value = defaults.includeLabor
  includeFlatRate.value = defaults.includeFlatRate
  includeMaterial.value = defaults.includeMaterial
  flatRateOverride.value = defaults.flatRateOverride
  if (!props.hasRepairParts || props.partsMaterialCost > 0) {
    materialManualOverride.value = ''
  }
}

async function loadTemplate() {
  sheetTemplate.value = null
  repairChecklist.value = null

  const templateKey = props.ticket.material_item.repair_template_key
  if (!templateKey || !props.departmentId) {
    applyDefaultFlags()
    return
  }

  templateLoading.value = true
  try {
    if (isExternalStrategy.value && props.ticket.assigned_to_supplier_company?.id) {
      const supplierTemplates = await listDepartmentSupplierRepairTemplates(
        props.departmentId,
        props.ticket.assigned_to_supplier_company.id,
      )
      const supplierMatch = supplierTemplates.find((tpl) => tpl.template_key === templateKey)
      if (supplierMatch) {
        sheetTemplate.value = supplierTemplateToSheetInput(supplierMatch)
        repairChecklist.value = normalizeRepairChecklist(
          props.ticket.repair_checklist,
          sheetTemplate.value,
        )
        applyDefaultFlags()
        return
      }
    }

    const templates = await getDepartmentRepairTemplates(props.departmentId)
    const match = templates.find((tpl) => tpl.template_key === templateKey)
    if (!match) {
      applyDefaultFlags()
      return
    }

    sheetTemplate.value = departmentTemplateToSheetInput(match)
    repairChecklist.value = normalizeRepairChecklist(
      props.ticket.repair_checklist,
      sheetTemplate.value,
    )
    applyDefaultFlags()
  } catch (err) {
    console.error('Failed to load repair sheet for cost summary:', err)
    applyDefaultFlags()
  } finally {
    templateLoading.value = false
  }
}
</script>

<style scoped>
.workshop-cost-summary {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 12px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
}

.cost-summary-title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
}

.cost-summary-hint {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
}

.cost-block {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cost-block-fields {
  margin-left: 28px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cost-line-hint,
.cost-line-total {
  margin: 0;
  font-size: 12px;
  color: #4b5563;
}

.cost-line-total {
  font-weight: 600;
}

.cost-grand-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 8px;
  border-top: 1px solid #e5e7eb;
  font-size: 14px;
}
</style>
