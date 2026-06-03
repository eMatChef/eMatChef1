<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="720"
    :title="delivery ? t('supplierDeliveries.modal.editTitle') : t('supplierDeliveries.modal.createTitle')"
    scrollable
    persistent
  >
    <form id="supplier-delivery-form" @submit.prevent="submit">
      <ETextField
        v-model="form.department_id"
        :label="t('supplierDeliveries.fields.departmentId')"
        :hint="t('supplierDeliveries.fields.departmentIdHint')"
        maxlength="12"
        hide-details="auto"
        class="mb-3"
      />

      <div class="field-row">
        <ETextField
          v-model="form.delivery_ref"
          :label="t('supplierDeliveries.fields.deliveryRef')"
          maxlength="120"
          hide-details="auto"
          class="field-grow"
        />
        <ETextField
          v-model="form.invoice_ref"
          :label="t('supplierDeliveries.fields.invoiceRef')"
          maxlength="120"
          hide-details="auto"
          class="field-grow"
        />
      </div>

      <ETextField
        v-model="form.delivered_at"
        type="date"
        :label="t('supplierDeliveries.fields.deliveredAt')"
        hide-details="auto"
        class="mb-3"
      />

      <ETextarea
        v-model="form.notes"
        :label="t('supplierDeliveries.fields.notes')"
        rows="2"
        hide-details="auto"
        class="mb-3"
      />

      <section class="lines-section">
        <div class="lines-header">
          <h4>{{ t('supplierDeliveries.linesTitle') }}</h4>
          <EButton variant="secondary" size="small" @click="addLine">
            {{ t('supplierDeliveries.addLine') }}
          </EButton>
        </div>

        <div v-for="(line, index) in form.lines" :key="index" class="line-card">
          <ESelect
            v-model="line.catalog_item_id"
            :items="catalogSelectItems"
            :label="t('supplierDeliveries.fields.catalogItem')"
            :placeholder="t('supplierDeliveries.selectCatalogItem')"
            hide-details="auto"
            class="mb-2"
            @update:model-value="onCatalogChange(line)"
          />
          <div class="field-row">
            <ETextField
              v-model.number="line.qty"
              type="number"
              :label="t('supplierDeliveries.fields.qty')"
              hide-details="auto"
              class="field-narrow"
            />
            <ETextField
              v-model="line.unit_price"
              type="number"
              :label="t('supplierDeliveries.fields.unitPrice')"
              hide-details="auto"
              class="field-narrow"
            />
          </div>
          <ETextarea
            v-if="lineTracking(line) === 'serialized'"
            v-model="line.serial_numbers_text"
            :label="t('supplierDeliveries.fields.serialNumbers')"
            :placeholder="t('supplierDeliveries.serialNumbersPlaceholder')"
            rows="3"
            hide-details="auto"
            class="mb-2"
          />
          <EButton variant="danger" size="small" class="line-remove" @click="removeLine(index)">
            {{ t('supplierDeliveries.removeLine') }}
          </EButton>
        </div>
      </section>

      <v-alert v-if="error" type="error" variant="tonal" :text="error" />
    </form>

    <template #actions>
      <EButton variant="secondary" size="small" @click="close">{{ t('common.cancel') }}</EButton>
      <EButton
        variant="primary"
        size="small"
        type="submit"
        form="supplier-delivery-form"
        :disabled="saving"
        :loading="saving"
      >
        {{ saving ? t('common.saving') : t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { listSupplierCatalogItems, type SupplierCatalogItem } from '@/api/supplierCatalog'
import type { SupplierDelivery, SupplierDeliveryPayload } from '@/api/supplierDeliveries'
import { EButton, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'

const props = defineProps<{
  companyId: string
  delivery: SupplierDelivery | null
}>()

const emit = defineEmits<{
  close: []
  save: [payload: SupplierDeliveryPayload]
}>()

const { t } = useI18n()
const dialogOpen = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const catalogItems = ref<SupplierCatalogItem[]>([])

const catalogSelectItems = computed(() =>
  catalogItems.value.map((item) => ({
    title: `${item.name}${item.sku ? ` (${item.sku})` : ''}`,
    value: item.id,
  })),
)

interface LineForm {
  catalog_item_id: string
  qty: number
  unit_price: string
  serial_numbers_text: string
}

const form = reactive({
  department_id: props.delivery?.department_id || '',
  delivery_ref: props.delivery?.delivery_ref || '',
  invoice_ref: props.delivery?.invoice_ref || '',
  delivered_at: props.delivery?.delivered_at ? props.delivery.delivered_at.slice(0, 10) : '',
  notes: props.delivery?.notes || '',
  lines: [] as LineForm[],
})

watch(dialogOpen, (open) => {
  if (!open) emit('close')
})

function close() {
  dialogOpen.value = false
}

function lineTracking(line: LineForm): string {
  const item = catalogItems.value.find((i) => i.id === line.catalog_item_id)
  return item?.tracking_type || 'bulk'
}

function onCatalogChange(line: LineForm) {
  const item = catalogItems.value.find((i) => i.id === line.catalog_item_id)
  if (item?.unit_price != null && line.unit_price === '') {
    line.unit_price = String(item.unit_price)
  }
}

function addLine() {
  form.lines.push({
    catalog_item_id: '',
    qty: 1,
    unit_price: '',
    serial_numbers_text: '',
  })
}

function removeLine(index: number) {
  form.lines.splice(index, 1)
}

function initLinesFromDelivery() {
  if (!props.delivery) {
    addLine()
    return
  }
  form.lines = props.delivery.lines.map((line) => ({
    catalog_item_id: line.catalog_item_id,
    qty: line.qty,
    unit_price: line.unit_price != null ? String(line.unit_price) : '',
    serial_numbers_text: (line.serial_numbers || []).join('\n'),
  }))
  if (form.lines.length === 0) addLine()
}

function submit() {
  error.value = null
  if (form.lines.length === 0) {
    error.value = t('supplierDeliveries.errors.linesRequired')
    return
  }

  const payload: SupplierDeliveryPayload = {
    department_id: form.department_id.trim(),
    delivery_ref: form.delivery_ref.trim() || null,
    invoice_ref: form.invoice_ref.trim() || null,
    delivered_at: form.delivered_at ? `${form.delivered_at}T12:00:00` : null,
    notes: form.notes.trim() || null,
    lines: form.lines.map((line, index) => ({
      catalog_item_id: line.catalog_item_id,
      qty: line.qty,
      unit_price: line.unit_price === '' ? null : Number(line.unit_price),
      serial_numbers: line.serial_numbers_text
        .split(/[\n,;]+/)
        .map((s) => s.trim())
        .filter(Boolean),
      sort_order: index,
    })),
  }

  saving.value = true
  emit('save', payload)
  saving.value = false
}

onMounted(async () => {
  initLinesFromDelivery()
  try {
    const res = await listSupplierCatalogItems(props.companyId)
    catalogItems.value = res.catalog_items.filter((i) => i.is_active)
  } catch {
    catalogItems.value = []
  }
})
</script>

<style scoped>
.field-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 12px;
}

.field-grow {
  flex: 1 1 180px;
}

.field-narrow {
  flex: 0 1 140px;
}

.lines-section h4 {
  margin: 0;
  font-size: 15px;
}

.lines-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}

.line-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 10px;
}

.line-remove {
  margin-top: 4px;
}
</style>
