<template>
  <EDialog
    v-model="open"
    :title="template ? t('supplierRepairTemplates.editTitle', { name: template.name }) : ''"
    max-width="760"
    scrollable
  >
    <template v-if="template">
      <div class="editor-meta">
        <span class="template-key">{{ template.template_key }}</span>
        <ESwitch
          v-model="form.isActive"
          :label="t('supplierRepairTemplates.fields.templateActive')"
          hide-details
          density="compact"
        />
      </div>

      <ETextField
        v-model="form.flatRateChf"
        type="number"
        step="0.05"
        min="0"
        :label="t('supplierRepairTemplates.fields.flatRateChf')"
        hide-details="auto"
        class="flat-rate-field"
      />

      <div class="services-section">
        <div class="section-head">
          <h4 class="section-title">{{ t('supplierRepairTemplates.servicesTitle') }}</h4>
          <EButton variant="secondary" size="x-small" @click="addService">
            {{ t('supplierRepairTemplates.addService') }}
          </EButton>
        </div>
        <p class="section-hint">{{ t('supplierRepairTemplates.servicesHint') }}</p>
        <div v-if="form.services.length === 0" class="services-empty">
          {{ t('supplierRepairTemplates.servicesEmpty') }}
        </div>
        <div v-for="(service, index) in form.services" :key="service.key || index" class="service-row">
          <ETextField
            v-model="service.label"
            :label="t('supplierRepairTemplates.fields.serviceLabel')"
            hide-details="auto"
          />
          <ESelect
            v-model="service.type"
            :items="serviceTypeItems"
            :label="t('supplierRepairTemplates.fields.serviceType')"
            hide-details="auto"
          />
          <ETextField
            v-model="service.unit_price_chf"
            type="number"
            step="0.05"
            min="0"
            :label="t('supplierRepairTemplates.fields.servicePrice')"
            hide-details="auto"
          />
          <ESwitch
            v-model="service.is_active"
            :label="t('supplierRepairTemplates.fields.serviceActive')"
            hide-details
            density="compact"
          />
          <button type="button" class="service-remove" @click="removeService(index)">×</button>
        </div>
      </div>

      <div v-for="section in sections" :key="section.key" class="price-section">
        <h4 class="section-title">{{ section.label }}</h4>
        <div class="price-rows">
          <div v-for="item in section.items" :key="item.key" class="price-row">
            <div class="price-row-label">
              <span>{{ item.label }}</span>
              <span class="item-key">{{ item.key }}</span>
            </div>
            <ESwitch
              v-model="priceEntry(item.key).is_active"
              :label="t('supplierRepairTemplates.fields.positionActive')"
              hide-details
              density="compact"
              class="price-row-switch"
            />
            <ETextField
              v-model="priceEntry(item.key).unit_price_chf"
              type="number"
              step="0.05"
              min="0"
              :disabled="!priceEntry(item.key).is_active"
              :label="t('supplierRepairTemplates.fields.unitPriceChf')"
              hide-details="auto"
              class="price-row-input"
            />
          </div>
        </div>
      </div>
    </template>

    <template #actions>
      <EButton variant="secondary" :disabled="saving" @click="open = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton variant="primary" :loading="saving" @click="save">
        {{ saving ? t('common.saving') : t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import {
  updateSupplierRepairTemplate,
  type SupplierRepairServiceEntry,
  type SupplierRepairTemplate,
} from '@/api/supplierRepairTemplates'
import type { RepairTemplatePricesJson, RepairTemplateStructureSection } from '@/api/repairTemplates'
import { EButton, EDialog, ESelect, ESwitch, ETextField } from '@/components/form/base'

const props = defineProps<{
  companyId: string
}>()

const emit = defineEmits<{
  saved: [template: SupplierRepairTemplate]
}>()

const open = defineModel<boolean>({ default: false })
const template = defineModel<SupplierRepairTemplate | null>('template', { default: null })

const { t } = useI18n()
const toast = useToast()

const saving = ref(false)

const form = reactive({
  isActive: true,
  flatRateChf: '' as string,
  prices: {} as RepairTemplatePricesJson,
  services: [] as SupplierRepairServiceEntry[],
})

const sections = computed((): RepairTemplateStructureSection[] => {
  return template.value?.structure_json?.sections ?? []
})

const serviceTypeItems = computed(() => [
  { value: 'cleaning', title: t('supplierRepairTemplates.serviceType.cleaning') },
  { value: 'repair', title: t('supplierRepairTemplates.serviceType.repair') },
])

watch(
  () => template.value,
  (value) => {
    if (!value) return
    form.isActive = value.is_active
    form.flatRateChf = value.flat_rate_chf ?? ''
    form.prices = buildEditablePrices(value)
    form.services = [...(value.services_json?.services ?? [])]
  },
  { immediate: true },
)

function priceEntry(key: string) {
  if (!form.prices[key]) {
    form.prices[key] = { unit_price_chf: null, is_active: true }
  }
  return form.prices[key]
}

function buildEditablePrices(source: SupplierRepairTemplate): RepairTemplatePricesJson {
  const prices: RepairTemplatePricesJson = { ...source.prices_json }
  for (const section of source.structure_json?.sections ?? []) {
    for (const item of section.items ?? []) {
      if (!prices[item.key]) {
        prices[item.key] = { unit_price_chf: null, is_active: true }
      }
    }
  }
  return prices
}

function slugify(label: string): string {
  return label
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 40)
}

function addService() {
  const index = form.services.length + 1
  form.services.push({
    key: `service_${index}`,
    label: '',
    type: 'cleaning',
    unit_price_chf: null,
    is_active: true,
  })
}

function removeService(index: number) {
  form.services.splice(index, 1)
}

function normalizePricesForSave(): RepairTemplatePricesJson {
  const result: RepairTemplatePricesJson = {}
  for (const [key, entry] of Object.entries(form.prices)) {
    const raw = entry.unit_price_chf
    result[key] = {
      is_active: entry.is_active,
      unit_price_chf:
        raw === null || raw === undefined || String(raw).trim() === '' ? null : String(raw),
    }
  }
  return result
}

function normalizeServicesForSave(): SupplierRepairServiceEntry[] {
  const usedKeys = new Set<string>()
  return form.services
    .map((service, index) => {
      const label = service.label.trim()
      if (!label) return null
      let key = service.key?.trim() || slugify(label) || `service_${index + 1}`
      while (usedKeys.has(key)) {
        key = `${key}_${index + 1}`
      }
      usedKeys.add(key)
      const raw = service.unit_price_chf
      return {
        key,
        label,
        type: service.type === 'repair' ? 'repair' : 'cleaning',
        is_active: service.is_active,
        unit_price_chf:
          raw === null || raw === undefined || String(raw).trim() === '' ? null : String(raw),
      }
    })
    .filter((entry): entry is SupplierRepairServiceEntry => entry !== null)
}

async function save() {
  if (!template.value) return
  saving.value = true
  try {
    const updated = await updateSupplierRepairTemplate(props.companyId, template.value.template_key, {
      is_active: form.isActive,
      flat_rate_chf: form.flatRateChf.trim() === '' ? null : form.flatRateChf,
      prices_json: normalizePricesForSave(),
      services_json: { services: normalizeServicesForSave() },
    })
    emit('saved', updated)
    open.value = false
    toast.success(t('supplierRepairTemplates.toastSaved'))
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(message || t('supplierRepairTemplates.toastSaveError'))
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.editor-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.template-key {
  font-size: 12px;
  color: #6b7280;
  font-family: ui-monospace, monospace;
}

.flat-rate-field {
  max-width: 280px;
  margin-bottom: 20px;
}

.services-section {
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.section-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.section-hint {
  margin: 6px 0 12px;
  font-size: 12px;
  color: #6b7280;
}

.services-empty {
  font-size: 13px;
  color: #9ca3af;
  margin-bottom: 8px;
}

.service-row {
  display: grid;
  grid-template-columns: 1fr 140px 120px auto auto;
  gap: 10px;
  align-items: end;
  margin-bottom: 10px;
}

.service-remove {
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 20px;
  cursor: pointer;
  padding: 4px 8px;
}

.price-section {
  margin-bottom: 20px;
}

.section-title {
  margin: 0 0 10px;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  padding-bottom: 6px;
  border-bottom: 1px solid #e5e7eb;
}

.price-rows {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.price-row {
  display: grid;
  grid-template-columns: 1fr auto 140px;
  gap: 12px;
  align-items: center;
}

.price-row-label {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.item-key {
  font-size: 11px;
  color: #9ca3af;
  font-family: ui-monospace, monospace;
}

@media (max-width: 700px) {
  .service-row,
  .price-row {
    grid-template-columns: 1fr;
  }
}
</style>
