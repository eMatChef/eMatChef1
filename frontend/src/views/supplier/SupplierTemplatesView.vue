<template>
  <div class="supplier-page">
    <header class="supplier-page-header">
      <h1>{{ t('supplierTemplates.title') }}</h1>
      <p class="supplier-page-subtitle">{{ companyName }}</p>
      <p class="supplier-page-hint">{{ t('supplierTemplates.subtitle') }}</p>
    </header>

    <ELoadingState
      v-if="loading"
      variant="inline"
      :message="t('common.loading')"
    />
    <div v-else-if="loadError" class="supplier-page-error">
      <v-alert type="error" variant="tonal" :text="loadError" />
    </div>

    <template v-else>
      <div v-if="legacyHint?.has_global_templates" class="legacy-hint-banner">
        {{ t('supplierTemplates.legacyGlobalHint', { count: legacyHint.available_count }) }}
      </div>

      <div class="toolbar">
        <EButton variant="primary" @click="openCreate">
          {{ t('supplierTemplates.newTemplate') }}
        </EButton>
        <EButton variant="secondary" :disabled="loading" @click="loadTemplates">
          {{ t('supplierTemplates.refresh') }}
        </EButton>
      </div>

      <EEmptyState
        v-if="templates.length === 0"
        variant="create"
        :title="t('supplierTemplates.empty')"
      />

      <table v-else class="catalog-table">
        <thead>
          <tr>
            <th>{{ t('supplierTemplates.columns.name') }}</th>
            <th>{{ t('supplierTemplates.columns.type') }}</th>
            <th>{{ t('supplierTemplates.columns.components') }}</th>
            <th>{{ t('supplierTemplates.columns.price') }}</th>
            <th>{{ t('supplierTemplates.columns.status') }}</th>
            <th>{{ t('supplierTemplates.columns.active') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in templates" :key="item.id">
            <td>{{ item.name }}</td>
            <td>{{ materialTypeLabel(item.material_type) }}</td>
            <td>{{ item.component_count }}</td>
            <td>{{ formatPrice(item) }}</td>
            <td>{{ statusLabel(item) }}</td>
            <td>{{ item.is_active ? t('supplierTemplates.activeYes') : t('supplierTemplates.activeNo') }}</td>
            <td class="actions-cell">
              <EButton variant="secondary" size="small" @click="openEdit(item)">
                {{ t('common.edit') }}
              </EButton>
              <EButton variant="danger" size="small" @click="removeTemplate(item)">
                {{ t('common.delete') }}
              </EButton>
            </td>
          </tr>
        </tbody>
      </table>
    </template>

    <SupplierMaterialTemplateModal
      v-if="modalOpen"
      :template="editingTemplate"
      :default-manufacturer="defaultManufacturer"
      :loading-detail="loadingDetail"
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
import SupplierMaterialTemplateModal from '@/components/supplier/SupplierMaterialTemplateModal.vue'
import {
  createSupplierMaterialTemplate,
  deleteSupplierMaterialTemplate,
  getSupplierLegacyGlobalHint,
  getSupplierMaterialTemplate,
  listSupplierMaterialTemplates,
  updateSupplierMaterialTemplate,
  type SupplierLegacyGlobalHint,
  type SupplierMaterialTemplate,
  type SupplierMaterialTemplatePayload,
  type SupplierMaterialType,
} from '@/api/supplierMaterialTemplates'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'

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
const loadingDetail = ref(false)
const loadError = ref('')
const templates = ref<SupplierMaterialTemplate[]>([])
const legacyHint = ref<SupplierLegacyGlobalHint | null>(null)
const modalOpen = ref(false)
const editingTemplate = ref<SupplierMaterialTemplate | null>(null)

function materialTypeLabel(type: SupplierMaterialType): string {
  return type === 'virtual_combo'
    ? t('supplierTemplates.materialType.virtualCombo')
    : t('supplierTemplates.materialType.physicalCombo')
}

function formatPrice(item: SupplierMaterialTemplate): string {
  if (item.unit_price == null) return '—'
  return `${item.unit_price.toFixed(2)} ${item.currency}`
}

function statusLabel(item: SupplierMaterialTemplate): string {
  const key = `supplierTemplates.status.${item.status === 'pending_review' ? 'pendingReview' : item.status}` as const
  const label = t(key)
  if (item.visibility === 'global') {
    return `${label} (${t('supplierTemplates.visibility.global')})`
  }
  return label
}

async function loadTemplates() {
  loading.value = true
  loadError.value = ''
  try {
    const [items, hint] = await Promise.all([
      listSupplierMaterialTemplates(companyId.value),
      getSupplierLegacyGlobalHint(companyId.value).catch(() => null),
    ])
    templates.value = items
    legacyHint.value = hint
  } catch (err: any) {
    loadError.value = err?.response?.data?.error || t('supplierTemplates.errorLoad')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingTemplate.value = null
  modalOpen.value = true
}

async function openEdit(item: SupplierMaterialTemplate) {
  modalOpen.value = true
  loadingDetail.value = true
  editingTemplate.value = null
  try {
    editingTemplate.value = await getSupplierMaterialTemplate(companyId.value, item.id)
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierTemplates.errorLoad'))
    closeModal()
  } finally {
    loadingDetail.value = false
  }
}

function closeModal() {
  modalOpen.value = false
  editingTemplate.value = null
}

async function handleSave(payload: SupplierMaterialTemplatePayload) {
  try {
    if (editingTemplate.value) {
      await updateSupplierMaterialTemplate(companyId.value, editingTemplate.value.id, payload)
      toast.success(t('supplierTemplates.saveSuccess'))
    } else {
      await createSupplierMaterialTemplate(companyId.value, payload)
      toast.success(t('supplierTemplates.createSuccess'))
    }
    closeModal()
    await loadTemplates()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierTemplates.errorSave'))
  }
}

async function removeTemplate(item: SupplierMaterialTemplate) {
  const ok = await confirm.confirm({
    title: t('supplierTemplates.deleteTitle'),
    message: t('supplierTemplates.deleteMessage', { name: item.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  try {
    await deleteSupplierMaterialTemplate(companyId.value, item.id)
    toast.success(t('supplierTemplates.deleteSuccess'))
    await loadTemplates()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierTemplates.errorDelete'))
  }
}

watch(companyId, () => loadTemplates())

onMounted(() => {
  loadTemplates()
})
</script>

<style scoped>
.supplier-page {
  max-width: 1100px;
  padding: 24px;
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

.legacy-hint-banner {
  margin-top: 16px;
  padding: 12px 14px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  color: #1e40af;
  font-size: 0.95rem;
}

.supplier-page-error {
  margin-top: 16px;
}

.toolbar {
  display: flex;
  gap: 8px;
  margin: 20px 0 16px;
}

.catalog-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.95rem;
}

.catalog-table th,
.catalog-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
}

.catalog-table th {
  font-weight: 600;
  color: #374151;
  background: #f9fafb;
}

.actions-cell {
  white-space: nowrap;
  display: flex;
  gap: 6px;
}
</style>
