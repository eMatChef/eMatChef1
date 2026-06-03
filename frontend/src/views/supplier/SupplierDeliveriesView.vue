<template>
  <div class="supplier-page">
    <header class="supplier-page-header">
      <h1>{{ t('supplierDeliveries.title') }}</h1>
      <p class="supplier-page-subtitle">{{ companyName }}</p>
      <p class="supplier-page-hint">{{ t('supplierDeliveries.subtitle') }}</p>
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
      <div class="toolbar">
        <EButton variant="primary" @click="openCreate">
          {{ t('supplierDeliveries.newDelivery') }}
        </EButton>
        <EButton variant="secondary" @click="loadDeliveries">
          {{ t('supplierDeliveries.refresh') }}
        </EButton>
      </div>

      <EEmptyState
        v-if="deliveries.length === 0"
        variant="create"
        :title="t('supplierDeliveries.empty')"
      />

      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ t('supplierDeliveries.columns.ref') }}</th>
            <th>{{ t('supplierDeliveries.columns.department') }}</th>
            <th>{{ t('supplierDeliveries.columns.deliveredAt') }}</th>
            <th>{{ t('supplierDeliveries.columns.lines') }}</th>
            <th>{{ t('supplierDeliveries.columns.status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="delivery in deliveries" :key="delivery.id">
            <td>{{ delivery.delivery_ref || '—' }}</td>
            <td>{{ delivery.department_name }}</td>
            <td>{{ formatDate(delivery.delivered_at) }}</td>
            <td>{{ delivery.lines.length }}</td>
            <td><span class="status-badge" :class="`status-${delivery.status}`">{{ statusLabel(delivery.status) }}</span></td>
            <td class="actions-cell">
              <EButton
                v-if="delivery.status === 'draft'"
                variant="secondary"
                size="small"
                @click="openEdit(delivery)"
              >
                {{ t('common.edit') }}
              </EButton>
              <EButton
                v-if="delivery.status === 'draft'"
                variant="primary"
                size="small"
                @click="submitDelivery(delivery)"
              >
                {{ t('supplierDeliveries.submit') }}
              </EButton>
              <EButton
                v-if="delivery.status === 'draft'"
                variant="danger"
                size="small"
                @click="removeDelivery(delivery)"
              >
                {{ t('common.delete') }}
              </EButton>
              <EButton
                v-if="delivery.status === 'submitted'"
                variant="secondary"
                size="small"
                @click="cancelDelivery(delivery)"
              >
                {{ t('supplierDeliveries.cancel') }}
              </EButton>
            </td>
          </tr>
        </tbody>
      </table>
    </template>

    <SupplierDeliveryEditModal
      v-if="modalOpen"
      :company-id="companyId"
      :delivery="editingDelivery"
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
import SupplierDeliveryEditModal from '@/components/supplier/SupplierDeliveryEditModal.vue'
import {
  cancelSupplierDelivery,
  createSupplierDelivery,
  deleteSupplierDelivery,
  listSupplierDeliveries,
  submitSupplierDelivery,
  updateSupplierDelivery,
  type SupplierDelivery,
  type SupplierDeliveryPayload,
  type SupplierDeliveryStatus,
} from '@/api/supplierDeliveries'
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

const loading = ref(true)
const loadError = ref('')
const deliveries = ref<SupplierDelivery[]>([])
const modalOpen = ref(false)
const editingDelivery = ref<SupplierDelivery | null>(null)

function statusLabel(status: SupplierDeliveryStatus): string {
  const key = `supplierDeliveries.status.${status}` as const
  return t(key)
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  return value.slice(0, 10)
}

async function loadDeliveries() {
  loading.value = true
  loadError.value = ''
  try {
    const res = await listSupplierDeliveries(companyId.value)
    deliveries.value = res.deliveries
  } catch (err: any) {
    loadError.value = err?.response?.data?.error || t('supplierDeliveries.errorLoad')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingDelivery.value = null
  modalOpen.value = true
}

function openEdit(delivery: SupplierDelivery) {
  editingDelivery.value = delivery
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  editingDelivery.value = null
}

async function handleSave(payload: SupplierDeliveryPayload) {
  try {
    if (editingDelivery.value) {
      await updateSupplierDelivery(companyId.value, editingDelivery.value.id, payload)
      toast.success(t('supplierDeliveries.saveSuccess'))
    } else {
      await createSupplierDelivery(companyId.value, payload)
      toast.success(t('supplierDeliveries.createSuccess'))
    }
    closeModal()
    await loadDeliveries()
  } catch (err: any) {
    const msg = err?.response?.data?.error || t('supplierDeliveries.errorSave')
    const details = err?.response?.data?.errors
    toast.error(Array.isArray(details) ? `${msg}: ${details.join('; ')}` : msg)
  }
}

async function submitDelivery(delivery: SupplierDelivery) {
  const ok = await confirm.confirm({
    title: t('supplierDeliveries.submitTitle'),
    message: t('supplierDeliveries.submitMessage'),
    confirmText: t('supplierDeliveries.submit'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return

  try {
    const res = await submitSupplierDelivery(companyId.value, delivery.id)
    toast.success(t('supplierDeliveries.submitSuccess'))
    if (res.warnings?.length) {
      toast.info(res.warnings.join('; '))
    }
    await loadDeliveries()
  } catch (err: any) {
    const details = err?.response?.data?.errors
    toast.error(
      Array.isArray(details)
        ? details.join('; ')
        : err?.response?.data?.error || t('supplierDeliveries.errorSubmit'),
    )
  }
}

async function cancelDelivery(delivery: SupplierDelivery) {
  const ok = await confirm.confirm({
    title: t('supplierDeliveries.cancelTitle'),
    message: t('supplierDeliveries.cancelMessage'),
    confirmText: t('supplierDeliveries.cancel'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await cancelSupplierDelivery(companyId.value, delivery.id)
    toast.success(t('supplierDeliveries.cancelSuccess'))
    await loadDeliveries()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierDeliveries.errorCancel'))
  }
}

async function removeDelivery(delivery: SupplierDelivery) {
  const ok = await confirm.confirm({
    title: t('supplierDeliveries.deleteTitle'),
    message: t('supplierDeliveries.deleteMessage'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteSupplierDelivery(companyId.value, delivery.id)
    toast.success(t('supplierDeliveries.deleteSuccess'))
    await loadDeliveries()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierDeliveries.errorDelete'))
  }
}

watch(companyId, () => loadDeliveries())
onMounted(() => loadDeliveries())
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

.supplier-page-error {
  margin-top: 16px;
}

.toolbar {
  display: flex;
  gap: 8px;
  margin: 20px 0 12px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.data-table th,
.data-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
}

.data-table th {
  font-size: 12px;
  text-transform: uppercase;
  color: #6b7280;
}

.actions-cell {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-end;
}

.status-badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  background: #e5e7eb;
}

.status-submitted {
  background: #dbeafe;
  color: #1d4ed8;
}

.status-imported {
  background: #d1fae5;
  color: #065f46;
}

.status-cancelled {
  background: #fee2e2;
  color: #b91c1c;
}
</style>
