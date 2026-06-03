<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="640"
    :title="item ? t('supplierCatalog.modal.editTitle') : t('supplierCatalog.modal.createTitle')"
    scrollable
    persistent
  >
    <form id="supplier-catalog-item-form" @submit.prevent="submit">
      <ETextField
        v-model="form.name"
        :label="t('supplierCatalog.fields.name')"
        maxlength="255"
        hide-details="auto"
        class="mb-3"
      />

      <div class="field-row">
        <ETextField
          v-model="form.sku"
          :label="t('supplierCatalog.fields.sku')"
          maxlength="120"
          hide-details="auto"
          class="field-grow"
        />
        <ETextField
          v-model="form.manufacturer"
          :label="t('supplierCatalog.fields.manufacturer')"
          maxlength="120"
          hide-details="auto"
          class="field-grow"
        />
      </div>

      <div class="field-row">
        <ESelect
          v-model="form.tracking_type"
          :items="trackingTypeItems"
          :label="t('supplierCatalog.fields.trackingType')"
          hide-details="auto"
          class="field-grow"
        />
        <ETextField
          v-model="form.external_ref"
          :label="t('supplierCatalog.fields.externalRef')"
          maxlength="120"
          hide-details="auto"
          class="field-grow"
        />
      </div>

      <div class="field-row">
        <ETextField
          v-model="form.unit_price"
          type="number"
          :label="t('supplierCatalog.fields.unitPrice')"
          hide-details="auto"
          class="field-grow"
        />
        <ETextField
          v-model="form.currency"
          :label="t('supplierCatalog.fields.currency')"
          maxlength="3"
          hide-details="auto"
          class="field-narrow"
        />
        <ETextField
          v-model="form.pack_size"
          type="number"
          :label="t('supplierCatalog.fields.packSize')"
          hide-details="auto"
          class="field-narrow"
        />
      </div>

      <ETextField
        v-model="form.category_hint"
        :label="t('supplierCatalog.fields.categoryHint')"
        maxlength="255"
        hide-details="auto"
        class="mb-3"
      />

      <ETextarea
        v-model="form.description"
        :label="t('supplierCatalog.fields.description')"
        rows="3"
        maxlength="5000"
        hide-details="auto"
        class="mb-3"
      />

      <div class="field-row">
        <ESelect
          v-model="form.visibility"
          :items="visibilityItems"
          :label="t('supplierCatalog.fields.visibility')"
          hide-details="auto"
          class="field-grow"
        />
        <ESelect
          v-model="form.status"
          :items="statusItems"
          :label="t('supplierCatalog.fields.status')"
          hide-details="auto"
          class="field-grow"
        />
      </div>

      <p v-if="form.visibility === 'global'" class="hint">
        {{ t('supplierCatalog.globalReviewHint') }}
      </p>

      <ECheckbox
        v-model="form.is_active"
        :label="t('supplierCatalog.fields.isActive')"
        hide-details
        class="mb-2"
      />

      <v-alert v-if="error" type="error" variant="tonal" :text="error" />
    </form>

    <template #actions>
      <EButton variant="secondary" size="small" @click="close">{{ t('common.cancel') }}</EButton>
      <EButton
        variant="primary"
        size="small"
        type="submit"
        form="supplier-catalog-item-form"
        :disabled="saving"
        :loading="saving"
      >
        {{ saving ? t('common.saving') : t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  SupplierCatalogItem,
  SupplierCatalogItemPayload,
  SupplierCatalogStatus,
  SupplierCatalogTrackingType,
  SupplierCatalogVisibility,
} from '@/api/supplierCatalog'
import { EButton, ECheckbox, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'

const props = defineProps<{
  item: SupplierCatalogItem | null
  defaultManufacturer?: string | null
}>()

const emit = defineEmits<{
  close: []
  save: [payload: SupplierCatalogItemPayload]
}>()

const { t } = useI18n()
const dialogOpen = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const trackingTypeItems = computed(() => [
  { title: t('supplierCatalog.tracking.bulk'), value: 'bulk' as const },
  { title: t('supplierCatalog.tracking.serialized'), value: 'serialized' as const },
])

const visibilityItems = computed(() => [
  { title: t('supplierCatalog.visibility.private'), value: 'private' as const },
  { title: t('supplierCatalog.visibility.departments'), value: 'departments' as const },
  { title: t('supplierCatalog.visibility.global'), value: 'global' as const },
])

const statusItems = computed(() => [
  { title: t('supplierCatalog.status.draft'), value: 'draft' as const },
  { title: t('supplierCatalog.status.published'), value: 'published' as const },
  { title: t('supplierCatalog.status.pendingReview'), value: 'pending_review' as const },
])

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

watch(dialogOpen, (open) => {
  if (!open) emit('close')
})

function close() {
  dialogOpen.value = false
}

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
  flex: 0 1 120px;
}

.hint {
  margin: 0 0 12px;
  color: #6b7280;
  font-size: 13px;
}
</style>
