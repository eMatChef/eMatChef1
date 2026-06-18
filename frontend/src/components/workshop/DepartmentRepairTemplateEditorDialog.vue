<template>
  <EDialog
    v-model="open"
    :title="template ? t('settings.workshopSettings.templates.editTitle', { name: template.name }) : ''"
    max-width="720"
    scrollable
  >
    <template v-if="template">
      <div class="editor-meta">
        <span class="template-key">{{ template.template_key }}</span>
        <ESwitch
          v-model="form.isActive"
          :label="t('settings.workshopSettings.templates.fields.templateActive')"
          hide-details
          density="compact"
        />
      </div>

      <ETextField
        v-model="form.flatRateChf"
        type="number"
        step="0.05"
        min="0"
        :label="t('settings.workshopSettings.templates.fields.flatRateChf')"
        :hint="t('settings.workshopSettings.templates.hints.flatRateChf')"
        hide-details="auto"
        class="flat-rate-field"
      />

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
              :label="t('settings.workshopSettings.templates.fields.positionActive')"
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
              :label="t('settings.workshopSettings.templates.fields.unitPriceChf')"
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
  updateDepartmentRepairTemplate,
  type DepartmentRepairTemplate,
  type RepairTemplatePricesJson,
  type RepairTemplateStructureSection,
} from '@/api/repairTemplates'
import { EButton, EDialog, ESwitch, ETextField } from '@/components/form/base'

const props = defineProps<{
  departmentId: string
}>()

const emit = defineEmits<{
  saved: [template: DepartmentRepairTemplate]
}>()

const open = defineModel<boolean>({ default: false })
const template = defineModel<DepartmentRepairTemplate | null>('template', { default: null })

const { t } = useI18n()
const toast = useToast()

const saving = ref(false)

const form = reactive({
  isActive: true,
  flatRateChf: '' as string,
  prices: {} as RepairTemplatePricesJson,
})

const sections = computed((): RepairTemplateStructureSection[] => {
  return template.value?.structure_json?.sections ?? []
})

watch(
  () => template.value,
  (value) => {
    if (!value) return
    form.isActive = value.is_active
    form.flatRateChf = value.flat_rate_chf ?? ''
    form.prices = buildEditablePrices(value)
  },
  { immediate: true }
)

function priceEntry(key: string) {
  if (!form.prices[key]) {
    form.prices[key] = { unit_price_chf: null, is_active: true }
  }
  return form.prices[key]
}

function buildEditablePrices(source: DepartmentRepairTemplate): RepairTemplatePricesJson {
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

function normalizePricesForSave(): RepairTemplatePricesJson {
  const result: RepairTemplatePricesJson = {}
  for (const [key, entry] of Object.entries(form.prices)) {
    const raw = entry.unit_price_chf
    result[key] = {
      is_active: entry.is_active,
      unit_price_chf:
        raw === null || raw === undefined || String(raw).trim() === ''
          ? null
          : String(raw),
    }
  }
  return result
}

async function save() {
  if (!template.value) return
  saving.value = true
  try {
    const updated = await updateDepartmentRepairTemplate(
      props.departmentId,
      template.value.template_key,
      {
        is_active: form.isActive,
        flat_rate_chf: form.flatRateChf.trim() === '' ? null : form.flatRateChf,
        prices_json: normalizePricesForSave(),
      }
    )
    emit('saved', updated)
    open.value = false
    toast.success(t('settings.workshopSettings.templates.toastSaved'))
  } catch (err: unknown) {
    console.error('Repair template save failed:', err)
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(message || t('settings.workshopSettings.templates.toastSaveError'))
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

.price-row-label span:first-child {
  font-size: 13px;
  color: #111827;
}

.item-key {
  font-size: 11px;
  color: #9ca3af;
  font-family: ui-monospace, monospace;
}

@media (max-width: 600px) {
  .price-row {
    grid-template-columns: 1fr;
  }
}
</style>
