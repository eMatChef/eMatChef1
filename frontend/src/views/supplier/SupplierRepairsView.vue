<template>
  <div class="supplier-page">
    <header class="supplier-page-header">
      <h1>{{ t('supplierRepairs.title') }}</h1>
      <p class="supplier-page-subtitle">{{ companyName }}</p>
      <p class="supplier-page-hint">{{ t('supplierRepairs.subtitle') }}</p>
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
        <ESelect
          v-model="statusFilter"
          :items="statusFilterItems"
          :label="t('supplierRepairs.filterStatus')"
          hide-details
          class="status-filter"
          @update:model-value="loadTickets"
        />
        <EButton variant="secondary" @click="loadTickets">
          {{ t('supplierRepairs.refresh') }}
        </EButton>
      </div>

      <EEmptyState
        v-if="tickets.length === 0"
        :title="t('supplierRepairs.empty')"
      />

      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ t('supplierRepairs.columns.title') }}</th>
            <th>{{ t('supplierRepairs.columns.department') }}</th>
            <th>{{ t('supplierRepairs.columns.material') }}</th>
            <th>{{ t('supplierRepairs.columns.status') }}</th>
            <th>{{ t('supplierRepairs.columns.updated') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ticket in tickets" :key="ticket.id">
            <td>{{ ticket.title }}</td>
            <td>{{ ticket.department.name }}</td>
            <td>{{ ticket.material_item.name }}</td>
            <td>{{ ticket.status_label }}</td>
            <td>{{ formatDate(ticket.updated_at) }}</td>
            <td>
              <EButton variant="secondary" size="small" @click="openTicket(ticket.id)">
                {{ t('supplierRepairs.openDetail') }}
              </EButton>
            </td>
          </tr>
        </tbody>
      </table>
    </template>

    <EDialog
      v-model="detailOpen"
      :max-width="560"
      :title="selectedTicket?.title || ''"
      scrollable
    >
      <template v-if="selectedTicket">
        <p><strong>{{ t('supplierRepairs.columns.department') }}:</strong> {{ selectedTicket.department.name }}</p>
        <p><strong>{{ t('supplierRepairs.columns.material') }}:</strong> {{ selectedTicket.material_item.name }}</p>
        <p v-if="selectedTicket.material_item.serial_number">
          <strong>{{ t('supplierRepairs.serial') }}:</strong> {{ selectedTicket.material_item.serial_number }}
        </p>
        <p v-if="selectedTicket.description">{{ selectedTicket.description }}</p>
        <p v-if="selectedTicket.issue_report?.description" class="issue-desc">
          {{ selectedTicket.issue_report.description }}
        </p>
        <section v-if="issueReportPhotos.length" class="photos-section">
          <h4>{{ t('supplierRepairs.issuePhotosTitle') }}</h4>
          <PhotoGallery :photos="issueReportPhotos" :show-meta="false" />
        </section>

        <section v-if="selectedTicket.photos?.length" class="photos-section">
          <h4>{{ t('supplierRepairs.photosTitle') }}</h4>
          <PhotoGallery
            :photos="selectedTicket.photos"
            :format-date="formatDate"
          />
        </section>

        <PhotoUpload
          :upload-fn="uploadRepairPhoto"
          :disabled="acting"
          :label="t('supplierRepairs.uploadPhoto')"
          @uploaded="onPhotoUploaded"
          @error="onUploadError"
        />

        <ETextField
          v-model="formEstimatedCost"
          :label="t('supplierRepairs.estimatedCost')"
          hide-details="auto"
        />

        <div v-if="selectedTicket.allowed_transitions.length" class="actions">
          <EButton
            v-for="nextStatus in selectedTicket.allowed_transitions"
            :key="nextStatus"
            variant="primary"
            size="small"
            :disabled="acting"
            :loading="acting"
            @click="transitionTo(nextStatus)"
          >
            → {{ statusLabel(nextStatus) }}
          </EButton>
        </div>
      </template>

      <template #actions>
        <EButton variant="secondary" size="small" @click="closeDetail">
          {{ t('common.close') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import {
  getSupplierRepair,
  listSupplierRepairs,
  transitionSupplierRepair,
  updateSupplierRepair,
  uploadSupplierRepairPhoto,
  type SupplierRepairStatus,
  type SupplierRepairTicket,
} from '@/api/supplierRepairs'
import { normalizeMediaPhotos } from '@/api/media'
import PhotoGallery from '@/components/media/PhotoGallery.vue'
import PhotoUpload from '@/components/media/PhotoUpload.vue'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const toast = useToast()

const companyId = computed(() => route.params.companyId as string)
const companyName = computed(() => {
  const company = authStore.activeSupplierCompanies.find((c) => c.id === companyId.value)
  return company?.name || authStore.activeSupplierCompanyName
})

const statusFilterItems = computed(() => [
  { title: t('supplierRepairs.allStatuses'), value: '' },
  { title: t('supplierRepairs.status.open'), value: 'open' },
  { title: t('supplierRepairs.status.inProgress'), value: 'in_progress' },
  { title: t('supplierRepairs.status.waitingParts'), value: 'waiting_parts' },
  { title: t('supplierRepairs.status.completed'), value: 'completed' },
])

const issueReportPhotos = computed(() => {
  const report = selectedTicket.value?.issue_report
  if (!report) return []
  return normalizeMediaPhotos(report.photos, report.photo_url)
})

const loading = ref(true)
const loadError = ref('')
const tickets = ref<SupplierRepairTicket[]>([])
const statusFilter = ref('')
const detailOpen = ref(false)
const selectedTicket = ref<SupplierRepairTicket | null>(null)
const formEstimatedCost = ref('')
const acting = ref(false)

function formatDate(value: string): string {
  try {
    return new Date(value).toLocaleString()
  } catch {
    return value
  }
}

function statusLabel(status: SupplierRepairStatus): string {
  const key = `supplierRepairs.status.${status === 'in_progress' ? 'inProgress' : status === 'waiting_parts' ? 'waitingParts' : status}` as const
  return t(key)
}

async function loadTickets() {
  loading.value = true
  loadError.value = ''
  try {
    tickets.value = await listSupplierRepairs(
      companyId.value,
      (statusFilter.value || undefined) as SupplierRepairStatus | undefined,
    )
  } catch (err: any) {
    loadError.value = err?.response?.data?.error || t('supplierRepairs.errorLoad')
  } finally {
    loading.value = false
  }
}

async function openTicket(ticketId: string) {
  try {
    selectedTicket.value = await getSupplierRepair(companyId.value, ticketId)
    formEstimatedCost.value = selectedTicket.value.estimated_cost || ''
    detailOpen.value = true
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierRepairs.errorLoad'))
  }
}

function closeDetail() {
  detailOpen.value = false
  selectedTicket.value = null
}

watch(detailOpen, (open) => {
  if (!open) selectedTicket.value = null
})

async function uploadRepairPhoto(file: File) {
  if (!selectedTicket.value) {
    throw new Error(t('supplierRepairs.errorLoad'))
  }
  return uploadSupplierRepairPhoto(companyId.value, selectedTicket.value.id, file)
}

async function onPhotoUploaded(ticket: unknown) {
  selectedTicket.value = ticket as SupplierRepairTicket
  toast.success(t('media.uploadSuccess'))
  await loadTickets()
}

function onUploadError(message: string) {
  toast.error(message || t('media.uploadError'))
}

async function transitionTo(nextStatus: SupplierRepairStatus) {
  if (!selectedTicket.value) return
  acting.value = true
  try {
    if (formEstimatedCost.value && formEstimatedCost.value !== selectedTicket.value.estimated_cost) {
      await updateSupplierRepair(companyId.value, selectedTicket.value.id, {
        estimated_cost: formEstimatedCost.value,
      })
    }
    const payload: Parameters<typeof transitionSupplierRepair>[2] = { status: nextStatus }
    if (nextStatus === 'waiting_parts' && formEstimatedCost.value) {
      payload.estimated_cost = formEstimatedCost.value
    }
    selectedTicket.value = await transitionSupplierRepair(
      companyId.value,
      selectedTicket.value.id,
      payload,
    )
    formEstimatedCost.value = selectedTicket.value.estimated_cost || ''
    toast.success(t('supplierRepairs.transitionSuccess'))
    await loadTickets()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierRepairs.transitionError'))
  } finally {
    acting.value = false
  }
}

watch(companyId, () => loadTickets())
onMounted(() => loadTickets())
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
  gap: 12px;
  align-items: flex-end;
  margin: 20px 0 16px;
}

.status-filter {
  flex: 1 1 220px;
  max-width: 280px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
}

.photos-section h4 {
  margin: 0;
  font-size: 14px;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}
</style>
