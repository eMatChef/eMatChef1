<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <header class="modal-header">
        <h3>
          {{ delivery ? t('supplierDeliveries.modal.editTitle') : t('supplierDeliveries.modal.createTitle') }}
        </h3>
        <button type="button" class="btn btn-secondary btn-sm" @click="emit('close')">
          {{ t('common.cancel') }}
        </button>
      </header>

      <form class="modal-body" @submit.prevent="submit">
        <label class="field">
          <span>{{ t('supplierDeliveries.fields.departmentId') }}</span>
          <input v-model.trim="form.department_id" type="text" required maxlength="12" />
          <span class="hint">{{ t('supplierDeliveries.fields.departmentIdHint') }}</span>
        </label>

        <div class="field-row">
          <label class="field">
            <span>{{ t('supplierDeliveries.fields.deliveryRef') }}</span>
            <input v-model.trim="form.delivery_ref" type="text" maxlength="120" />
          </label>
          <label class="field">
            <span>{{ t('supplierDeliveries.fields.invoiceRef') }}</span>
            <input v-model.trim="form.invoice_ref" type="text" maxlength="120" />
          </label>
        </div>

        <label class="field">
          <span>{{ t('supplierDeliveries.fields.deliveredAt') }}</span>
          <input v-model="form.delivered_at" type="date" />
        </label>

        <label class="field">
          <span>{{ t('supplierDeliveries.fields.notes') }}</span>
          <textarea v-model.trim="form.notes" rows="2" />
        </label>

        <section class="lines-section">
          <div class="lines-header">
            <h4>{{ t('supplierDeliveries.linesTitle') }}</h4>
            <button type="button" class="btn btn-secondary btn-sm" @click="addLine">
              {{ t('supplierDeliveries.addLine') }}
            </button>
          </div>

          <div v-for="(line, index) in form.lines" :key="index" class="line-card">
            <label class="field">
              <span>{{ t('supplierDeliveries.fields.catalogItem') }}</span>
              <select v-model="line.catalog_item_id" required @change="onCatalogChange(line)">
                <option value="" disabled>{{ t('supplierDeliveries.selectCatalogItem') }}</option>
                <option v-for="item in catalogItems" :key="item.id" :value="item.id">
                  {{ item.name }}{{ item.sku ? ` (${item.sku})` : '' }}
                </option>
              </select>
            </label>
            <div class="field-row">
              <label class="field field-narrow">
                <span>{{ t('supplierDeliveries.fields.qty') }}</span>
                <input v-model.number="line.qty" type="number" min="1" step="1" required />
              </label>
              <label class="field field-narrow">
                <span>{{ t('supplierDeliveries.fields.unitPrice') }}</span>
                <input v-model.trim="line.unit_price" type="number" min="0" step="0.01" />
              </label>
            </div>
            <label v-if="lineTracking(line) === 'serialized'" class="field">
              <span>{{ t('supplierDeliveries.fields.serialNumbers') }}</span>
              <textarea
                v-model="line.serial_numbers_text"
                rows="3"
                :placeholder="t('supplierDeliveries.serialNumbersPlaceholder')"
              />
            </label>
            <button type="button" class="btn btn-danger btn-sm line-remove" @click="removeLine(index)">
              {{ t('supplierDeliveries.removeLine') }}
            </button>
          </div>
        </section>

        <p v-if="error" class="error">{{ error }}</p>

        <footer class="modal-footer">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? t('common.saving') : t('common.save') }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { listSupplierCatalogItems, type SupplierCatalogItem } from '@/api/supplierCatalog'
import type { SupplierDelivery, SupplierDeliveryPayload } from '@/api/supplierDeliveries'

const props = defineProps<{
  companyId: string
  delivery: SupplierDelivery | null
}>()

const emit = defineEmits<{
  close: []
  save: [payload: SupplierDeliveryPayload]
}>()

const { t } = useI18n()
const saving = ref(false)
const error = ref<string | null>(null)
const catalogItems = ref<SupplierCatalogItem[]>([])

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
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 720px;
  max-height: 90vh;
  overflow: auto;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
}

.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.field-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
  flex: 1 1 180px;
}

.field-narrow {
  flex: 0 1 140px;
}

.field input,
.field select,
.field textarea {
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-family: inherit;
}

.hint {
  color: #6b7280;
  font-size: 12px;
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
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.line-remove {
  align-self: flex-end;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
}

.error {
  color: #b91c1c;
  font-size: 14px;
}

.btn-sm {
  padding: 6px 10px;
  font-size: 12px;
}
</style>
