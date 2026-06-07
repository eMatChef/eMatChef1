<template>
  <div v-if="visible" class="external-cleaning-panel">
    <div class="panel-header">
      <div class="modal-section-title">{{ t('workshop.externalCleaning.title') }}</div>
      <p class="panel-hint">{{ t('workshop.externalCleaning.hint') }}</p>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="inline"
      :message="t('workshop.externalCleaning.loading')"
    />

    <template v-else>
      <div v-if="!serviceKey" class="panel-empty">
        {{ t('workshop.externalCleaning.noService') }}
      </div>

      <template v-else>
        <div class="service-row">
          <span class="service-label">{{ t('workshop.externalCleaning.serviceLabel') }}</span>
          <span class="service-value">
            {{ serviceLabel }}
            <span v-if="servicePrice" class="service-price">CHF {{ servicePrice }}</span>
          </span>
        </div>

        <section v-if="hasTentLine" class="tent-line-section">
          <h4>{{ t('workshop.externalCleaning.tentLineTitle') }}</h4>
          <div class="tent-line-row">
            <span>{{ t('workshop.externalCleaning.tentLineLabel') }}</span>
            <span v-if="tentLinePrice" class="service-price">CHF {{ tentLinePrice }}</span>
          </div>
          <p v-if="estimatedTotal > 0" class="total-hint">
            {{ t('workshop.externalCleaning.estimatedTotal', { amount: formatChfAmount(estimatedTotal) }) }}
          </p>
        </section>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { WorkshopTicket } from '@/api/workshop'
import { listDepartmentSupplierRepairTemplates } from '@/api/supplierRepairTemplates'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { formatChfAmount, normalizeRepairChecklist } from '@/types/repairChecklist'
import {
  estimateExternalCleaningCost,
  getCleaningServiceKey,
  resolveCleaningServiceOption,
  supplierTemplateToCleaningSheetInput,
  TENT_CLEANING_ITEM_KEY,
} from '@/utils/workshopExternalCleaning'

const props = defineProps<{
  ticket: WorkshopTicket
  departmentId: string
}>()

const { t } = useI18n()

const isLoading = ref(false)
const serviceLabel = ref('')
const servicePrice = ref('')
const tentLinePrice = ref('')
const estimatedTotal = ref(0)

const visible = computed(() => props.ticket.strategy === 'external_cleaning')

const serviceKey = computed(() => getCleaningServiceKey(props.ticket.repair_checklist))

const hasTentLine = computed(() => {
  if (!props.ticket.material_item.repair_template_key) return false
  const items = (props.ticket.repair_checklist as { items?: Record<string, { quantity?: number }> } | null)?.items
  return (items?.[TENT_CLEANING_ITEM_KEY]?.quantity ?? 0) > 0
})

watch(
  () => [
    props.ticket.id,
    props.ticket.strategy,
    props.ticket.repair_checklist,
    props.ticket.assigned_to_supplier_company?.id,
  ],
  () => {
    void loadData()
  },
  { immediate: true, deep: true },
)

async function loadData() {
  serviceLabel.value = ''
  servicePrice.value = ''
  tentLinePrice.value = ''
  estimatedTotal.value = 0

  if (!visible.value || !props.departmentId) return

  const key = serviceKey.value
  const supplierId = props.ticket.assigned_to_supplier_company?.id
  if (!key || !supplierId) return

  isLoading.value = true
  try {
    const templates = await listDepartmentSupplierRepairTemplates(props.departmentId, supplierId)
    const service = resolveCleaningServiceOption(templates, key)
    if (!service) return

    serviceLabel.value = service.label
    servicePrice.value = service.unit_price_chf || ''

    const templateMatch = templates.find((tpl) => tpl.template_key === service.template_key)
    let sheetTemplate = null
    if (templateMatch && props.ticket.material_item.repair_template_key) {
      sheetTemplate = supplierTemplateToCleaningSheetInput(templateMatch)
      const checklist = normalizeRepairChecklist(props.ticket.repair_checklist, sheetTemplate)
      const unitPrice = sheetTemplate.prices_json[TENT_CLEANING_ITEM_KEY]?.unit_price_chf
      const qty = checklist.items[TENT_CLEANING_ITEM_KEY]?.quantity ?? 0
      if (qty > 0 && unitPrice) {
        tentLinePrice.value = (qty * Number(String(unitPrice).replace(',', '.'))).toFixed(2)
      }
      estimatedTotal.value = estimateExternalCleaningCost(service, sheetTemplate, checklist)
    } else {
      estimatedTotal.value = estimateExternalCleaningCost(service, null, null)
    }
  } catch (err) {
    console.error('Failed to load external cleaning panel:', err)
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
.external-cleaning-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.panel-hint {
  margin: 4px 0 0;
  font-size: 12px;
  color: #6b7280;
}

.panel-empty {
  font-size: 13px;
  color: #6b7280;
}

.service-row,
.tent-line-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 14px;
}

.service-label {
  color: #6b7280;
}

.service-price {
  margin-left: 8px;
  font-weight: 600;
  color: #111827;
}

.tent-line-section h4 {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
}

.total-hint {
  margin: 8px 0 0;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}
</style>
