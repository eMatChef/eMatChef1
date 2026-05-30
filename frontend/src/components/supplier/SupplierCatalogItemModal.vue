<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <header class="modal-header">
        <h3>
          {{
            item
              ? t('supplierCatalog.modal.editTitle')
              : t('supplierCatalog.modal.createTitle')
          }}
        </h3>
        <button type="button" class="btn btn-secondary btn-sm" @click="emit('close')">
          {{ t('common.cancel') }}
        </button>
      </header>

      <form class="modal-body" @submit.prevent="submit">
        <label class="field">
          <span>{{ t('supplierCatalog.fields.name') }}</span>
          <input v-model.trim="form.name" type="text" required maxlength="255" />
        </label>

        <div class="field-row">
          <label class="field">
            <span>{{ t('supplierCatalog.fields.sku') }}</span>
            <input v-model.trim="form.sku" type="text" maxlength="120" />
          </label>
          <label class="field">
            <span>{{ t('supplierCatalog.fields.manufacturer') }}</span>
            <input v-model.trim="form.manufacturer" type="text" maxlength="120" />
          </label>
        </div>

        <div class="field-row">
          <label class="field">
            <span>{{ t('supplierCatalog.fields.trackingType') }}</span>
            <select v-model="form.tracking_type">
              <option value="bulk">{{ t('supplierCatalog.tracking.bulk') }}</option>
              <option value="serialized">{{ t('supplierCatalog.tracking.serialized') }}</option>
            </select>
          </label>
          <label class="field">
            <span>{{ t('supplierCatalog.fields.externalRef') }}</span>
            <input v-model.trim="form.external_ref" type="text" maxlength="120" />
          </label>
        </div>

        <div class="field-row">
          <label class="field">
            <span>{{ t('supplierCatalog.fields.unitPrice') }}</span>
            <input v-model.trim="form.unit_price" type="number" min="0" step="0.01" />
          </label>
          <label class="field field-narrow">
            <span>{{ t('supplierCatalog.fields.currency') }}</span>
            <input v-model.trim="form.currency" type="text" maxlength="3" />
          </label>
          <label class="field field-narrow">
            <span>{{ t('supplierCatalog.fields.packSize') }}</span>
            <input v-model.trim="form.pack_size" type="number" min="1" step="1" />
          </label>
        </div>

        <label class="field">
          <span>{{ t('supplierCatalog.fields.categoryHint') }}</span>
          <input v-model.trim="form.category_hint" type="text" maxlength="255" />
        </label>

        <label class="field">
          <span>{{ t('supplierCatalog.fields.description') }}</span>
          <textarea v-model.trim="form.description" rows="3" maxlength="5000" />
        </label>

        <div class="field-row">
          <label class="field">
            <span>{{ t('supplierCatalog.fields.visibility') }}</span>
            <select v-model="form.visibility">
              <option value="private">{{ t('supplierCatalog.visibility.private') }}</option>
              <option value="departments">{{ t('supplierCatalog.visibility.departments') }}</option>
              <option value="global">{{ t('supplierCatalog.visibility.global') }}</option>
            </select>
          </label>
          <label class="field">
            <span>{{ t('supplierCatalog.fields.status') }}</span>
            <select v-model="form.status">
              <option value="draft">{{ t('supplierCatalog.status.draft') }}</option>
              <option value="published">{{ t('supplierCatalog.status.published') }}</option>
              <option value="pending_review">{{ t('supplierCatalog.status.pendingReview') }}</option>
            </select>
          </label>
        </div>

        <p v-if="form.visibility === 'global'" class="hint">
          {{ t('supplierCatalog.globalReviewHint') }}
        </p>

        <label class="checkbox-field">
          <input v-model="form.is_active" type="checkbox" />
          <span>{{ t('supplierCatalog.fields.isActive') }}</span>
        </label>

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
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  SupplierCatalogItem,
  SupplierCatalogItemPayload,
  SupplierCatalogStatus,
  SupplierCatalogTrackingType,
  SupplierCatalogVisibility,
} from '@/api/supplierCatalog'

const props = defineProps<{
  item: SupplierCatalogItem | null
  defaultManufacturer?: string | null
}>()

const emit = defineEmits<{
  close: []
  save: [payload: SupplierCatalogItemPayload]
}>()

const { t } = useI18n()
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive({
  name: '',
  sku: '',
  manufacturer: '',
  tracking_type: 'bulk' as SupplierCatalogTrackingType,
  unit_price: '',
  currency: 'CHF',
  pack_size: '',
  category_hint: '',
  description: '',
  external_ref: '',
  visibility: 'private' as SupplierCatalogVisibility,
  status: 'draft' as SupplierCatalogStatus,
  is_active: true,
})

function resetForm() {
  form.name = props.item?.name || ''
  form.sku = props.item?.sku || ''
  form.manufacturer = props.item?.manufacturer || props.defaultManufacturer || ''
  form.tracking_type = props.item?.tracking_type || 'bulk'
  form.unit_price = props.item?.unit_price != null ? String(props.item.unit_price) : ''
  form.currency = props.item?.currency || 'CHF'
  form.pack_size = props.item?.pack_size != null ? String(props.item.pack_size) : ''
  form.category_hint = props.item?.category_hint || ''
  form.description = props.item?.description || ''
  form.external_ref = props.item?.external_ref || ''
  form.visibility = props.item?.visibility || 'private'
  form.status = props.item?.status || 'draft'
  form.is_active = props.item?.is_active ?? true
  error.value = null
}

watch(() => props.item, resetForm, { immediate: true })

function submit() {
  error.value = null
  if (!form.name.trim()) {
    error.value = t('supplierCatalog.errors.nameRequired')
    return
  }

  const payload: SupplierCatalogItemPayload = {
    name: form.name.trim(),
    sku: form.sku.trim() || null,
    manufacturer: form.manufacturer.trim() || null,
    tracking_type: form.tracking_type,
    unit_price: form.unit_price === '' ? null : Number(form.unit_price),
    currency: form.currency.trim() || 'CHF',
    pack_size: form.pack_size === '' ? null : Number(form.pack_size),
    category_hint: form.category_hint.trim() || null,
    description: form.description.trim() || null,
    external_ref: form.external_ref.trim() || null,
    visibility: form.visibility,
    status: form.status,
    is_active: form.is_active,
  }

  saving.value = true
  emit('save', payload)
  saving.value = false
}
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
  max-width: 640px;
  max-height: 90vh;
  overflow: auto;
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.15);
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
  flex: 0 1 120px;
}

.field input,
.field select,
.field textarea {
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-family: inherit;
}

.checkbox-field {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}

.hint {
  margin: 0;
  color: #6b7280;
  font-size: 13px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding-top: 8px;
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
