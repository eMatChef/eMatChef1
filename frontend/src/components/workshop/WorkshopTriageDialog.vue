<template>
  <EDialog
    v-model="open"
    :title="t('workshop.triage.title')"
    max-width="860"
    scrollable
    persistent
  >
    <div v-if="ticket" class="triage-dialog">
      <div class="context-card">
        <div class="context-header">
          <h3>{{ ticket.title }}</h3>
          <div class="context-badges">
            <span class="type-badge" :class="ticket.type">{{ ticket.type_label }}</span>
            <span v-if="isExternalRental" class="external-badge">
              {{ t('workshop.triage.externalRental') }}
            </span>
          </div>
        </div>

        <div class="context-material">
          <v-icon icon="mdi-package-variant" size="18" />
          <div>
            <div class="context-material-name">{{ ticket.material_item.name }}</div>
            <div class="context-material-meta">
              <span v-if="ticket.material_item.category">{{ ticket.material_item.category.name }}</span>
              <span v-if="ticket.material_batch?.serial_number">
                {{ ticket.material_batch.serial_number }}
              </span>
            </div>
          </div>
        </div>

        <div v-if="ticket.activity" class="context-row">
          <span class="context-label">{{ t('workshop.originActivity') }}</span>
          <span>{{ ticket.activity.name }}</span>
        </div>

        <div v-if="ticket.issue_report" class="context-row">
          <span class="context-label">{{ t('workshop.originDamageReport') }}</span>
          <span>
            {{ ticket.issue_report.type_label }}
            <template v-if="ticket.issue_report.reported_by">
              · {{ ticket.issue_report.reported_by.name }}
            </template>
          </span>
        </div>

        <p v-if="ticket.description" class="context-description">{{ ticket.description }}</p>

        <PhotoGallery
          v-if="contextPhotos.length"
          :photos="contextPhotos"
          :show-meta="false"
        />
      </div>

      <div v-if="sheetTemplate" class="triage-sheet">
        <div class="triage-sheet-title">{{ t('workshop.triage.repairSheet') }}</div>
        <RepairSheetEditor
          v-model="sheetChecklist"
          :template="sheetTemplate"
          mode="readonly"
          price-source="department"
        />
      </div>

      <ELoadingState
        v-else-if="isLoadingTemplate"
        variant="inline"
        :message="t('workshop.triage.loadingTemplate')"
      />

      <div v-if="step === 'main'" class="triage-priority-block">
        <ESelect
          v-model="selectedPriority"
          :items="prioritySelectItems"
          :label="t('workshop.triage.priorityLabel')"
          :hint="t('workshop.triage.priorityHint')"
          hide-details="auto"
        />
      </div>

      <div v-if="step === 'main'" class="triage-actions">
        <p class="triage-question">{{ t('workshop.triage.question') }}</p>
        <div class="action-grid">
          <EButton
            v-for="action in triageOptions"
            :key="action.id"
            :variant="action.variant"
            class="triage-action-btn"
            :class="{ prominent: action.prominent }"
            :loading="pendingActionId === action.id"
            @click="onActionClick(action)"
          >
            <v-icon :icon="action.mdiIcon" start size="18" />
            {{ t(`workshop.triage.actions.${action.id}`) }}
          </EButton>
        </div>
        <p v-if="actionError" class="triage-error">{{ actionError }}</p>
      </div>

      <div v-else-if="step === 'supplier'" class="supplier-step">
        <p class="supplier-step-title">{{ t('workshop.triage.supplierRequired') }}</p>
        <ESelect
          v-model="selectedSupplierId"
          :items="supplierSelectItems"
          :label="t('workshop.selectSupplierCompany')"
          hide-details="auto"
        />
        <p v-if="actionError" class="triage-error">{{ actionError }}</p>
        <div class="supplier-step-actions">
          <EButton variant="secondary" :disabled="isSubmitting" @click="step = 'main'">
            {{ t('common.back') }}
          </EButton>
          <EButton
            variant="primary"
            :loading="isSubmitting"
            :disabled="!selectedSupplierId"
            @click="onSupplierStepConfirm"
          >
            {{ isCleaningAction ? t('workshop.triage.nextCleaningService') : t('workshop.triage.confirmSupplier') }}
          </EButton>
        </div>
      </div>

      <div v-else class="supplier-step">
        <p class="supplier-step-title">{{ t('workshop.triage.cleaningServiceRequired') }}</p>
        <ELoadingState
          v-if="isLoadingCleaningServices"
          variant="inline"
          :message="t('workshop.triage.loadingCleaningServices')"
        />
        <template v-else>
          <ESelect
            v-model="selectedCleaningServiceKey"
            :items="cleaningServiceSelectItems"
            :label="t('workshop.triage.cleaningServiceLabel')"
            hide-details="auto"
          />
          <p v-if="selectedCleaningServiceKey && ticket?.material_item.repair_template_key" class="cleaning-tent-hint">
            {{ t('workshop.triage.cleaningTentHint') }}
          </p>
          <p v-if="!cleaningServiceSelectItems.length" class="triage-error">
            {{ t('workshop.triage.noCleaningServices') }}
          </p>
        </template>
        <p v-if="actionError" class="triage-error">{{ actionError }}</p>
        <div class="supplier-step-actions">
          <EButton variant="secondary" :disabled="isSubmitting" @click="step = 'supplier'">
            {{ t('common.back') }}
          </EButton>
          <EButton
            variant="primary"
            :loading="isSubmitting"
            :disabled="!selectedCleaningServiceKey"
            @click="confirmCleaningServiceStep"
          >
            {{ t('workshop.triage.confirmCleaningService') }}
          </EButton>
        </div>
      </div>
    </div>

    <template #actions>
      <EButton variant="text" :disabled="isSubmitting" @click="open = false">
        {{ t('common.close') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  triageWorkshopTicket,
  transitionWorkshopTicket,
  updateWorkshopTicket,
  type TicketPriority,
  type WorkshopTicket,
} from '@/api/workshop'
import { getDepartmentRepairTemplates } from '@/api/repairTemplates'
import { listSupplierRepairCompanies } from '@/api/supplierShop'
import { listDepartmentSupplierRepairTemplates } from '@/api/supplierRepairTemplates'
import { collectCleaningServices } from '@/utils/workshopExternalCleaning'
import RepairSheetEditor from '@/components/workshop/RepairSheetEditor.vue'
import PhotoGallery from '@/components/media/PhotoGallery.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog, ESelect } from '@/components/form/base'
import {
  getWorkshopTriageOptions,
  ticketHasRepairSheet,
  type TriageActionOption,
} from '@/composables/useWorkshopTriageOptions'
import {
  createEmptyRepairChecklist,
  departmentTemplateToSheetInput,
  normalizeRepairChecklist,
  type RepairChecklist,
  type RepairSheetTemplateInput,
} from '@/types/repairChecklist'
import { normalizeMediaPhotos } from '@/api/media'

const props = defineProps<{
  departmentId: string
}>()

const emit = defineEmits<{
  triaged: [ticket: WorkshopTicket]
  writeoff: [ticket: WorkshopTicket]
  'resolve-ok': [ticket: WorkshopTicket]
}>()

const open = defineModel<boolean>({ default: false })
const ticket = defineModel<WorkshopTicket | null>('ticket', { default: null })

const { t } = useI18n()

const step = ref<'main' | 'supplier' | 'cleaning_service'>('main')
const selectedPriority = ref<TicketPriority>('normal')
const pendingActionId = ref<string | null>(null)
const pendingAction = ref<TriageActionOption | null>(null)
const selectedSupplierId = ref('')
const selectedCleaningServiceKey = ref('')
const cleaningServiceOptions = ref<ReturnType<typeof collectCleaningServices>>([])
const isLoadingCleaningServices = ref(false)
const repairCompanies = ref<Array<{ id: string; name: string }>>([])
const isSubmitting = ref(false)
const actionError = ref('')
const isLoadingTemplate = ref(false)
const sheetTemplate = ref<RepairSheetTemplateInput | null>(null)
const sheetChecklist = ref<RepairChecklist>(createEmptyRepairChecklist())

const triageOptions = computed(() =>
  ticket.value ? getWorkshopTriageOptions(ticket.value) : []
)

const prioritySelectItems = computed(() => [
  { title: t('workshop.priority.urgent'), value: 'urgent' },
  { title: t('workshop.priority.high'), value: 'high' },
  { title: t('workshop.priority.normal'), value: 'normal' },
  { title: t('workshop.priority.low'), value: 'low' },
])

const isExternalRental = computed(
  () => ticket.value?.activity?.type === 'external'
)

const contextPhotos = computed(() => {
  if (!ticket.value) return []
  const issuePhotos = normalizeMediaPhotos(ticket.value.issue_report?.photos)
  const ticketPhotos = normalizeMediaPhotos(ticket.value.photos)
  return [...issuePhotos, ...ticketPhotos]
})

const supplierSelectItems = computed(() =>
  repairCompanies.value.map((c) => ({ title: c.name, value: c.id }))
)

const isCleaningAction = computed(() => pendingAction.value?.id === 'external_cleaning')

const cleaningServiceSelectItems = computed(() =>
  cleaningServiceOptions.value.map((service) => ({
    title: service.unit_price_chf
      ? `${service.label} (CHF ${service.unit_price_chf})`
      : service.label,
    value: service.key,
  })),
)

watch(open, (isOpen) => {
  if (isOpen) {
    resetState()
    void loadSupportingData()
  }
})

watch(
  () => ticket.value?.id,
  () => {
    if (open.value) {
      resetState()
      void loadSupportingData()
    }
  }
)

function resetState() {
  step.value = 'main'
  pendingActionId.value = null
  pendingAction.value = null
  selectedSupplierId.value = ''
  selectedCleaningServiceKey.value = ''
  cleaningServiceOptions.value = []
  actionError.value = ''
  sheetTemplate.value = null
  sheetChecklist.value = createEmptyRepairChecklist(ticket.value?.material_item.repair_template_key ?? undefined)
  selectedPriority.value = ticket.value?.priority ?? 'normal'
}

async function loadSupportingData() {
  if (!ticket.value || !props.departmentId) return

  try {
    repairCompanies.value = await listSupplierRepairCompanies(props.departmentId)
  } catch {
    repairCompanies.value = []
  }

  if (!ticketHasRepairSheet(ticket.value)) return

  const templateKey = ticket.value.material_item.repair_template_key
  if (!templateKey) return

  isLoadingTemplate.value = true
  try {
    const templates = await getDepartmentRepairTemplates(props.departmentId)
    const match = templates.find((tpl) => tpl.template_key === templateKey)
    if (match) {
      sheetTemplate.value = departmentTemplateToSheetInput(match)
      sheetChecklist.value = normalizeRepairChecklist(ticket.value.repair_checklist, sheetTemplate.value)
    }
  } catch (err) {
    console.error('Failed to load repair template for triage:', err)
  } finally {
    isLoadingTemplate.value = false
  }
}

async function onActionClick(action: TriageActionOption) {
  if (!ticket.value) return
  actionError.value = ''

  if (action.id === 'resolve_ok') {
    await resolveOk()
    return
  }

  if (action.id === 'writeoff') {
    await submitTriage(action, undefined)
    if (!actionError.value) {
      emit('writeoff', ticket.value)
      open.value = false
    }
    return
  }

  if (action.requiresSupplier) {
    pendingAction.value = action
    pendingActionId.value = action.id
    step.value = 'supplier'
    return
  }

  await submitTriage(action, undefined)
}

async function onSupplierStepConfirm() {
  if (!pendingAction.value || !selectedSupplierId.value) return
  if (pendingAction.value.id === 'external_cleaning') {
    await loadCleaningServices()
    if (!cleaningServiceOptions.value.length) return
    step.value = 'cleaning_service'
    return
  }
  await submitTriage(pendingAction.value, selectedSupplierId.value)
}

async function loadCleaningServices() {
  if (!props.departmentId || !selectedSupplierId.value) return
  isLoadingCleaningServices.value = true
  actionError.value = ''
  try {
    const templates = await listDepartmentSupplierRepairTemplates(
      props.departmentId,
      selectedSupplierId.value,
    )
    cleaningServiceOptions.value = collectCleaningServices(templates)
    if (!cleaningServiceOptions.value.length) {
      actionError.value = t('workshop.triage.noCleaningServices')
    } else if (!selectedCleaningServiceKey.value) {
      selectedCleaningServiceKey.value = cleaningServiceOptions.value[0].key
    }
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    actionError.value = message || t('workshop.triage.error')
    cleaningServiceOptions.value = []
  } finally {
    isLoadingCleaningServices.value = false
  }
}

async function confirmCleaningServiceStep() {
  if (!pendingAction.value || !selectedSupplierId.value || !selectedCleaningServiceKey.value) return
  await submitTriage(
    pendingAction.value,
    selectedSupplierId.value,
    selectedCleaningServiceKey.value,
  )
}

async function submitTriage(
  action: TriageActionOption,
  supplierId?: string,
  cleaningServiceKey?: string,
) {
  if (!ticket.value || !action.strategy) return

  isSubmitting.value = true
  pendingActionId.value = action.id
  actionError.value = ''

  try {
    const updated = await triageWorkshopTicket(ticket.value.id, {
      strategy: action.strategy,
      assigned_to_supplier_company_id: supplierId || undefined,
      cleaning_service_key:
        action.strategy === 'external_cleaning' ? cleaningServiceKey : undefined,
      priority: selectedPriority.value,
    })
    ticket.value = updated
    emit('triaged', updated)
    open.value = false
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    actionError.value = message || t('workshop.triage.error')
  } finally {
    isSubmitting.value = false
    pendingActionId.value = null
  }
}

async function resolveOk() {
  if (!ticket.value) return
  isSubmitting.value = true
  pendingActionId.value = 'resolve_ok'
  actionError.value = ''

  try {
    if (selectedPriority.value !== ticket.value.priority) {
      await updateWorkshopTicket(ticket.value.id, { priority: selectedPriority.value })
    }
    if (ticket.value.status === 'open') {
      await transitionWorkshopTicket(ticket.value.id, { status: 'in_progress' })
    }
    const updated = await transitionWorkshopTicket(ticket.value.id, {
      status: 'completed',
      resolution_action: 'ok',
    })
    ticket.value = updated
    emit('resolve-ok', updated)
    open.value = false
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    actionError.value = message || t('workshop.triage.error')
  } finally {
    isSubmitting.value = false
    pendingActionId.value = null
  }
}
</script>

<style scoped>
.triage-dialog {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.context-card {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  background: #f9fafb;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.context-header h3 {
  margin: 0 0 8px;
  font-size: 18px;
  color: #111827;
}

.context-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.external-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 999px;
  background: #fef3c7;
  color: #b45309;
}

.context-material {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.context-material-name {
  font-weight: 600;
  font-size: 14px;
  color: #111827;
}

.context-material-meta {
  font-size: 12px;
  color: #6b7280;
  display: flex;
  gap: 8px;
  margin-top: 2px;
}

.context-row {
  display: flex;
  gap: 8px;
  font-size: 13px;
}

.context-label {
  color: #6b7280;
  min-width: 120px;
}

.context-description {
  margin: 0;
  font-size: 13px;
  color: #374151;
  white-space: pre-wrap;
}

.triage-sheet-title {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
}

.triage-priority-block {
  padding: 14px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}

.triage-question {
  margin: 0 0 12px;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
}

.action-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}

.triage-action-btn {
  justify-content: flex-start;
}

.triage-action-btn.prominent {
  grid-column: 1 / -1;
}

.triage-error {
  margin: 10px 0 0;
  color: #b91c1c;
  font-size: 13px;
}

.supplier-step-title {
  margin: 0 0 12px;
  font-size: 14px;
  color: #374151;
}

.supplier-step-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 16px;
}

.cleaning-tent-hint {
  margin: 8px 0 0;
  font-size: 12px;
  color: #6b7280;
}
</style>
