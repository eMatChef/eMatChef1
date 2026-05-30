<template>
  <div class="supplier-page">
    <header class="supplier-page-header">
      <h1>{{ t('supplierCatalog.title') }}</h1>
      <p class="supplier-page-subtitle">{{ companyName }}</p>
      <p class="supplier-page-hint">{{ t('supplierCatalog.subtitle') }}</p>
    </header>

    <div v-if="loading" class="supplier-page-state">{{ t('common.loading') }}</div>
    <div v-else-if="loadError" class="supplier-page-state supplier-page-state--error">{{ loadError }}</div>

    <template v-else>
      <div class="toolbar">
        <button type="button" class="btn btn-primary" @click="openCreate">
          {{ t('supplierCatalog.newItem') }}
        </button>
        <button type="button" class="btn btn-secondary" :disabled="loading" @click="loadItems">
          {{ t('supplierCatalog.refresh') }}
        </button>
      </div>

      <p v-if="items.length === 0" class="supplier-page-state">{{ t('supplierCatalog.empty') }}</p>

      <table v-else class="catalog-table">
        <thead>
          <tr>
            <th>{{ t('supplierCatalog.columns.name') }}</th>
            <th>{{ t('supplierCatalog.columns.sku') }}</th>
            <th>{{ t('supplierCatalog.columns.tracking') }}</th>
            <th>{{ t('supplierCatalog.columns.price') }}</th>
            <th>{{ t('supplierCatalog.columns.status') }}</th>
            <th>{{ t('supplierCatalog.columns.active') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>{{ item.name }}</td>
            <td>{{ item.sku || '—' }}</td>
            <td>{{ trackingLabel(item.tracking_type) }}</td>
            <td>{{ formatPrice(item) }}</td>
            <td>{{ statusLabel(item) }}</td>
            <td>{{ item.is_active ? t('supplierCatalog.activeYes') : t('supplierCatalog.activeNo') }}</td>
            <td class="actions-cell">
              <button type="button" class="btn btn-secondary btn-sm" @click="openEdit(item)">
                {{ t('common.edit') }}
              </button>
              <button type="button" class="btn btn-danger btn-sm" @click="removeItem(item)">
                {{ t('common.delete') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </template>

    <SupplierCatalogItemModal
      v-if="modalOpen"
      :item="editingItem"
      :default-manufacturer="defaultManufacturer"
      @close="closeModal"
      @save="handleSave"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import SupplierCatalogItemModal from '@/components/supplier/SupplierCatalogItemModal.vue'
import {
  createSupplierCatalogItem,
  deleteSupplierCatalogItem,
  listSupplierCatalogItems,
  updateSupplierCatalogItem,
  type SupplierCatalogItem,
  type SupplierCatalogItemPayload,
  type SupplierCatalogTrackingType,
} from '@/api/supplierCatalog'

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const companyId = computed(() => route.params.companyId as string)
const companyName = computed(() => {
  const company = authStore.activeSupplierCompanies.find((c) => c.id === companyId.value)
  return company?.name || authStore.activeSupplierCompanyName
})
const defaultManufacturer = computed(() => {
  const company = authStore.activeSupplierCompanies.find((c) => c.id === companyId.value)
  return company?.name || null
})

const loading = ref(true)
const loadError = ref('')
const items = ref<SupplierCatalogItem[]>([])
const modalOpen = ref(false)
const editingItem = ref<SupplierCatalogItem | null>(null)

function trackingLabel(type: SupplierCatalogTrackingType): string {
  return type === 'serialized'
    ? t('supplierCatalog.tracking.serialized')
    : t('supplierCatalog.tracking.bulk')
}

function formatPrice(item: SupplierCatalogItem): string {
  if (item.unit_price == null) return '—'
  return `${item.unit_price.toFixed(2)} ${item.currency}`
}

function statusLabel(item: SupplierCatalogItem): string {
  const key = `supplierCatalog.status.${item.status === 'pending_review' ? 'pendingReview' : item.status}` as const
  const label = t(key)
  if (item.visibility !== 'private' && item.visibility !== 'departments') {
    return `${label} (${t(`supplierCatalog.visibility.${item.visibility}`)})`
  }
  return label
}

async function loadItems() {
  loading.value = true
  loadError.value = ''
  try {
    const result = await listSupplierCatalogItems(companyId.value)
    items.value = result.catalog_items
  } catch (err: any) {
    loadError.value = err?.response?.data?.error || t('supplierCatalog.errorLoad')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingItem.value = null
  modalOpen.value = true
}

function openEdit(item: SupplierCatalogItem) {
  editingItem.value = item
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  editingItem.value = null
}

async function handleSave(payload: SupplierCatalogItemPayload) {
  try {
    if (editingItem.value) {
      await updateSupplierCatalogItem(companyId.value, editingItem.value.id, payload)
      toast.success(t('supplierCatalog.saveSuccess'))
    } else {
      await createSupplierCatalogItem(companyId.value, payload)
      toast.success(t('supplierCatalog.createSuccess'))
    }
    closeModal()
    await loadItems()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierCatalog.errorSave'))
  }
}

async function removeItem(item: SupplierCatalogItem) {
  const ok = await confirm.confirm({
    title: t('supplierCatalog.deleteTitle'),
    message: t('supplierCatalog.deleteMessage', { name: item.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  try {
    await deleteSupplierCatalogItem(companyId.value, item.id)
    toast.success(t('supplierCatalog.deleteSuccess'))
    await loadItems()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierCatalog.errorDelete'))
  }
}

watch(companyId, () => loadItems())

onMounted(() => {
  loadItems()
})
</script>

<style scoped>
.supplier-page {
  max-width: 1100px;
}

.supplier-page-header h1 {
  margin: 0;
  font-size: 1.75rem;
}

.supplier-page-subtitle {
  margin: 6px 0 0;
  color: #374151;
  font-weight: 600;
}

.supplier-page-hint {
  margin: 8px 0 0;
  color: #6b7280;
}

.supplier-page-state {
  margin-top: 16px;
  color: #6b7280;
}

.supplier-page-state--error {
  color: #b91c1c;
}

.toolbar {
  display: flex;
  gap: 8px;
  margin: 20px 0 12px;
}

.catalog-table {
  width: 100%;
  border-collapse: collapse;
}

.catalog-table th,
.catalog-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  font-size: 14px;
}

.catalog-table th {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280;
}

.actions-cell {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.btn {
  border: none;
  border-radius: 8px;
  padding: 8px 12px;
  cursor: pointer;
  font-size: 0.875rem;
}

.btn-sm {
  padding: 6px 10px;
  font-size: 12px;
}

.btn-primary {
  background: #2563eb;
  color: #fff;
}

.btn-secondary {
  background: #e5e7eb;
  color: #111827;
}

.btn-danger {
  background: #dc2626;
  color: #fff;
}
</style>
