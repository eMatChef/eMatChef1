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
            <th>{{ t('supplierRepairs.columns.phase') }}</th>
            <th>{{ t('supplierRepairs.columns.updated') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ticket in tickets" :key="ticket.id" :class="{ 'row-awaiting-quote': ticket.phase === 'awaiting_quote' }">
            <td>{{ ticket.title }}</td>
            <td>{{ ticket.department.name }}</td>
            <td>{{ ticket.material_item.name }}</td>
            <td>{{ ticket.phase_label || supplierPhaseLabel(ticket.phase) }}</td>
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
      :max-width="sheetTemplate ? 900 : 560"
      :title="selectedTicket?.title || ''"
      scrollable
    >
      <template v-if="selectedTicket">
        <p><strong>{{ t('supplierRepairs.columns.department') }}:</strong> {{ selectedTicket.department.name }}</p>
        <p><strong>{{ t('supplierRepairs.columns.material') }}:</strong> {{ selectedTicket.material_item.name }}</p>
        <p v-if="selectedTicket.phase_label">
          <strong>{{ t('supplierRepairs.columns.phase') }}:</strong> {{ selectedTicket.phase_label }}
        </p>
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
          <PhotoGallery :photos="selectedTicket.photos" :format-date="formatDate" />
        </section>

        <section v-if="isCleaningTicket && cleaningServiceLabel" class="sheet-section">
          <h4>{{ t('supplierRepairs.cleaningServiceTitle') }}</h4>
          <p class="cleaning-service-line">
            {{ cleaningServiceLabel }}
            <span v-if="cleaningServicePrice"> — CHF {{ cleaningServicePrice }}</span>
          </p>
        </section>

        <section v-if="sheetLoading" class="sheet-section">
          <ELoadingState variant="inline" :message="t('supplierRepairs.loadingSheet')" />
        </section>
        <section v-else-if="sheetTemplate && sheetChecklist && showRepairSheet" class="sheet-section">
          <h4>{{ isCleaningTicket ? t('supplierRepairs.cleaningSheetTitle') : t('supplierRepairs.repairSheetTitle') }}</h4>
          <RepairSheetEditor
            :model-value="sheetChecklist"
            :template="sheetTemplate"
            mode="readonly"
            price-source="supplier"
          />
          <p v-if="sheetGrandTotal > 0" class="sheet-total-hint">
            {{ t('supplierRepairs.sheetTotalHint', { amount: formatChfAmount(sheetGrandTotal) }) }}
          </p>
        </section>

        <PhotoUpload
          :upload-fn="uploadRepairPhoto"
          :disabled="acting"
          :label="t('supplierRepairs.uploadPhoto')"
          @uploaded="onPhotoUploaded"
          @error="onUploadError"
        />

        <div v-if="canSubmitQuote" class="quote-block">
          <h4>{{ t('supplierRepairs.quoteTitle') }}</h4>
          <p class="quote-hint">{{ t('supplierRepairs.quoteHint') }}</p>
          <ETextField
            v-model="formEstimatedCost"
            type="number"
            :label="t('supplierRepairs.estimatedCost')"
            :hint="quoteCostHint"
            hide-details="auto"
          />
          <EButton
            variant="primary"
            size="small"
            class="quote-submit-btn"
            :disabled="acting || !formEstimatedCost"
            :loading="acting"
            @click="submitQuote"
          >
            {{ t('supplierRepairs.submitQuote') }}
          </EButton>
        </div>

        <ETextField
          v-else
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
  submitSupplierRepairQuote,
  transitionSupplierRepair,
  updateSupplierRepair,
  uploadSupplierRepairPhoto,
  type SupplierRepairStatus,
  type SupplierRepairTicket,
} from '@/api/supplierRepairs'
import { getSupplierRepairTemplates, supplierTemplateToSheetInput } from '@/api/supplierRepairTemplates'
import {
  estimateExternalCleaningCost,
  getCleaningServiceKey,
  resolveCleaningServiceOption,
  supplierTemplateToCleaningSheetInput,
} from '@/utils/workshopExternalCleaning'
import { normalizeMediaPhotos } from '@/api/media'
import PhotoGallery from '@/components/media/PhotoGallery.vue'
import PhotoUpload from '@/components/media/PhotoUpload.vue'
import RepairSheetEditor from '@/components/workshop/RepairSheetEditor.vue'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  calcRepairSheetTotal,
  formatChfAmount,
  normalizeRepairChecklist,
  type RepairChecklist,
  type RepairSheetTemplateInput,
} from '@/types/repairChecklist'

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
  { title: t('workshop.phase.awaiting_quote'), value: 'waiting_parts' },
  { title: t('workshop.phase.in_progress'), value: 'in_progress' },
  { title: t('workshop.phase.completed'), value: 'completed' },
])

const issueReportPhotos = computed(() => {
  const report = selectedTicket.value?.issue_report
  if (!report) return []
  return normalizeMediaPhotos(report.photos, report.photo_url)
})

const canSubmitQuote = computed(() => {
  const ticket = selectedTicket.value
  if (!ticket) return false
  return ticket.phase === 'awaiting_quote' && ticket.allowed_transitions.includes('waiting_parts')
})

const sheetGrandTotal = computed(() => {
  if (!sheetTemplate.value || !sheetChecklist.value) return 0
  return calcRepairSheetTotal(sheetChecklist.value, sheetTemplate.value).grandTotal
})

const quoteCostHint = computed(() => {
  if (sheetGrandTotal.value <= 0) return undefined
  return t('supplierRepairs.quoteFromSheet', { amount: formatChfAmount(sheetGrandTotal.value) })
})

const loading = ref(true)
const loadError = ref('')
const tickets = ref<SupplierRepairTicket[]>([])
const statusFilter = ref('')
const detailOpen = ref(false)
const selectedTicket = ref<SupplierRepairTicket | null>(null)
const formEstimatedCost = ref('')
const acting = ref(false)
const sheetLoading = ref(false)
const sheetTemplate = ref<RepairSheetTemplateInput | null>(null)
const sheetChecklist = ref<RepairChecklist | null>(null)
const cleaningServiceLabel = ref('')
const cleaningServicePrice = ref('')

const isCleaningTicket = computed(() => selectedTicket.value?.strategy === 'external_cleaning')

const showRepairSheet = computed(() => {
  if (!isCleaningTicket.value) return true
  return !!selectedTicket.value?.material_item.repair_template_key
})

function formatDate(value: string): string {
  try {
    return new Date(value).toLocaleString()
  } catch {
    return value
  }
}

function supplierPhaseLabel(phase: string | null | undefined): string {
  if (!phase) return '—'
  const key = `workshop.phase.${phase}`
  return t(key)
}

function statusLabel(status: SupplierRepairStatus): string {
  const phaseKeyMap: Record<SupplierRepairStatus, string> = {
    open: 'workshop.phase.triage',
    in_progress: 'workshop.phase.in_progress',
    waiting_parts: 'workshop.phase.awaiting_quote',
    completed: 'workshop.phase.completed',
    cancelled: 'workshop.phase.cancelled',
  }
  const key = phaseKeyMap[status]
  if (key) return t(key)
  return status
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

async function loadRepairSheet(ticket: SupplierRepairTicket) {
  sheetTemplate.value = null
  sheetChecklist.value = null
  cleaningServiceLabel.value = ''
  cleaningServicePrice.value = ''

  sheetLoading.value = true
  try {
    const templates = await getSupplierRepairTemplates(companyId.value)

    if (ticket.strategy === 'external_cleaning') {
      const serviceKey = getCleaningServiceKey(ticket.repair_checklist)
      const service = resolveCleaningServiceOption(templates, serviceKey)
      if (service) {
        cleaningServiceLabel.value = service.label
        cleaningServicePrice.value = service.unit_price_chf || ''
      }

      const templateKey = ticket.material_item.repair_template_key
      if (!templateKey || !ticket.repair_checklist) return

      const match = templates.find((tpl) => tpl.template_key === templateKey && tpl.is_active)
      if (!match) return

      sheetTemplate.value = supplierTemplateToCleaningSheetInput(match)
      sheetChecklist.value = normalizeRepairChecklist(ticket.repair_checklist, sheetTemplate.value)
      return
    }

    const templateKey = ticket.material_item.repair_template_key
    if (!templateKey || !ticket.repair_checklist) return

    const match = templates.find((tpl) => tpl.template_key === templateKey && tpl.is_active)
    if (!match) return

    sheetTemplate.value = supplierTemplateToSheetInput(match)
    sheetChecklist.value = normalizeRepairChecklist(ticket.repair_checklist, sheetTemplate.value)
  } catch (err) {
    console.error('Failed to load repair sheet for supplier ticket:', err)
  } finally {
    sheetLoading.value = false
  }
}

async function openTicket(ticketId: string) {
  try {
    const ticket = await getSupplierRepair(companyId.value, ticketId)
    selectedTicket.value = ticket
    formEstimatedCost.value = ticket.estimated_cost || ''
    detailOpen.value = true
    await loadRepairSheet(ticket)
    if (!formEstimatedCost.value) {
      if (ticket.strategy === 'external_cleaning') {
        const templates = await getSupplierRepairTemplates(companyId.value)
        const service = resolveCleaningServiceOption(
          templates,
          getCleaningServiceKey(ticket.repair_checklist),
        )
        const total = estimateExternalCleaningCost(
          service,
          sheetTemplate.value,
          sheetChecklist.value,
        )
        if (total > 0) {
          formEstimatedCost.value = formatChfAmount(total)
        }
      } else if (sheetTemplate.value && sheetChecklist.value) {
        const total = calcRepairSheetTotal(sheetChecklist.value, sheetTemplate.value).grandTotal
        if (total > 0) {
          formEstimatedCost.value = formatChfAmount(total)
        }
      }
    }
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierRepairs.errorLoad'))
  }
}

function closeDetail() {
  detailOpen.value = false
  selectedTicket.value = null
  sheetTemplate.value = null
  sheetChecklist.value = null
  cleaningServiceLabel.value = ''
  cleaningServicePrice.value = ''
}

watch(detailOpen, (open) => {
  if (!open) {
    selectedTicket.value = null
    sheetTemplate.value = null
    sheetChecklist.value = null
    cleaningServiceLabel.value = ''
    cleaningServicePrice.value = ''
  }
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

async function submitQuote() {
  if (!selectedTicket.value || !formEstimatedCost.value) return
  acting.value = true
  try {
    selectedTicket.value = await submitSupplierRepairQuote(
      companyId.value,
      selectedTicket.value.id,
      formEstimatedCost.value,
    )
    formEstimatedCost.value = selectedTicket.value.estimated_cost || ''
    toast.success(t('supplierRepairs.quoteSubmitted'))
    await loadTickets()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('supplierRepairs.quoteSubmitError'))
  } finally {
    acting.value = false
  }
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

.row-awaiting-quote {
  background: #fffbeb;
}

.photos-section h4,
.sheet-section h4,
.quote-block h4 {
  margin: 0 0 8px;
  font-size: 14px;
}

.sheet-section,
.quote-block {
  margin-top: 16px;
}

.sheet-total-hint,
.quote-hint {
  margin: 8px 0 0;
  font-size: 13px;
  color: #6b7280;
}

.quote-block {
  padding: 14px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
}

.quote-submit-btn {
  margin-top: 12px;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 16px;
}
</style>
