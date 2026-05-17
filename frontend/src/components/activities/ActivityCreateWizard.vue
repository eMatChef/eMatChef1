<template>
  <Teleport to="body">
    <div v-if="showDialog" class="material-wizard-overlay activity-create-wizard-host">
      <div class="material-wizard-modal">
        <div class="material-wizard-header">
          <div class="material-wizard-header-title">
            <h2>{{ t('activities.wizard.createTitle') }}</h2>
          </div>
          <button type="button" class="close-btn" :title="t('activities.wizard.closeTitle')" @click="handleClose">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        <div class="material-wizard-body">
          <div class="material-wizard-content">
            <div ref="wizardFormRef" class="material-wizard-form">
              <ActivityTypeChips
                v-if="!isRestrictedGroupMember"
                :selected="selectedActivityType"
                @select="onSelectActivityType"
              />

              <ActivityCreateWizardForm
                v-if="selectedActivityType"
                :department-id="departmentId"
                :department-name="wizardDepartmentName"
                :layout-mode="layoutMode"
                :wizard-step-index="wizardStepIndex"
                :step-keys="stepKeys"
                :current-step-key="currentStepKey"
                :current-step-progress-label="currentStepProgressLabel"
                :step-titles="stepTitles"
                :form-name="formName"
                :usage-start-at="usageStartAt"
                :usage-end-at="usageEndAt"
                :planning-start-at="planningStartAt"
                :planning-end-at="planningEndAt"
                :selected-activity-type="selectedActivityType"
                :activity-defaults="activityDefaults"
                :planning-synced="planningSynced"
                :groups="groupsForWizard"
                :selected-group-id="selectedGroupId"
                :customer-address-id="customerAddressId"
                :venue-address-id="venueAddressId"
                :material-lines="materialLines"
                :draft-activity-id="draftActivityId"
                :invited-departments="invitedDepartments"
                :activity-notes="activityNotes"
                @update:selected-group-id="onSelectedGroupId"
                @update:customer-address-id="onCustomerAddressId"
                @update:venue-address-id="onVenueAddressId"
                @update:material-lines="onMaterialLines"
                @update:invited-departments="onInvitedDepartments"
                @update:activity-notes="onActivityNotes"
                @update:form-name="onUpdateFormName"
                @update:usage-start-at="onUpdateUsageStartAt"
                @update:usage-end-at="onUpdateUsageEndAt"
                @update:planning-start-at="onUpdatePlanningStartAt"
                @update:planning-end-at="onUpdatePlanningEndAt"
                @resync-planning="resyncPlanningFromUsage"
              />
            </div>
          </div>

          <ActivityPreviewSidebar
            :preview-title="previewTitle"
            :preview-usage-line="previewUsageLine"
            :preview-planning-line="previewPlanningLine"
            :selected-activity-type="selectedActivityType"
            :preview-group-line="previewGroupLine"
            :preview-venue-line="previewVenueLine"
            :preview-mieter-line="previewMieterLine"
            :preview-material-line="previewMaterialLine"
            :preview-invited-line="previewInvitedLine"
          />
        </div>

        <ActivityWizardFooter
          :submit-error="submitError"
          :missing-steps="missingSteps"
          :layout-mode="layoutMode"
          :selected-activity-type="selectedActivityType"
          :wizard-step-index="wizardStepIndex"
          :is-last-step="isLastStep"
          :can-advance-from-current-step="canAdvanceFromCurrentStep"
          :can-submit="canSubmit"
          :is-submitting="isSubmitting"
          :is-saving-draft="isSavingDraft"
          :show-submit-button="showSubmitButton"
          :submit-button-title="submitButtonTitle"
          :submit-button-label="submitButtonLabel"
          :show-draft-status="showDraftFooterStatus"
          :last-saved-at="lastDraftSavedAt"
          @close="handleClose"
          @prev="prevStep"
          @weiter="onWeiter"
          @submit="handleSubmit"
          @jump-missing="onJumpToMissing"
        />
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import '@/styles/material-wizard.css'
import '@/styles/activity-type-chips.css'
import '@/styles/activity-create-wizard.css'
import {
  createActivity,
  getActivity,
  getActivityItems,
  patchActivity,
  patchActivityStatus,
  syncActivityItems,
} from '@/api/activities'
import { getAddresses, type Address } from '@/api/addresses'
import { FALLBACK_ACTIVITY_DEFAULTS, getActivityDefaults } from '@/api/departmentSettings'
import { resolveActivityGroupPickerLabel } from '@/utils/groupHierarchy'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import {
  useActivityCreateWizard,
  type ActivityCreateType,
  type ActivityMaterialLine,
  type ActivityMissingStepKey,
  type InvitedDepartmentDraft,
} from '@/composables/useActivityCreateWizard'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import { useActivityGroupMemberScope } from '@/composables/useActivityGroupMemberScope'
import {
  ActivityCreateWizardForm,
  ActivityPreviewSidebar,
  ActivityTypeChips,
  ActivityWizardFooter,
} from './wizard'

const props = defineProps<{
  modelValue: boolean
  departmentId: string
  /** Wenn gesetzt: Entwurf von der API laden (Wizard fortsetzen) */
  resumeActivityId?: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  created: [id: string]
  'resume-consumed': []
}>()

const { t } = useI18n()
const toast = useToast()
const authStore = useAuthStore()
const headerNotificationsStore = useHeaderNotificationsStore()
const {
  isRestrictedGroupMember,
  loadGroupsForDepartment,
  setGroups: setScopeGroups,
  groups: scopeGroups,
  wizardGroupsForUser,
} = useActivityGroupMemberScope()

const showDialog = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
})

const wizardFormRef = ref<HTMLElement | null>(null)
const isSubmitting = ref(false)
const isSavingDraft = ref(false)
const submitError = ref('')
/** Nach erfolgreichem „Weiter“ (Draft) oder finalem Speichern */
const lastDraftSavedAt = ref<Date | null>(null)
/** Für Vorschau: Adressstamm (Gruppe kommt aus groupsForWizard) */
const previewAddresses = ref<Address[]>([])

const {
  selectedActivityType,
  layoutMode,
  wizardStepIndex,
  stepKeys,
  currentStepKey,
  currentStepProgressLabel,
  formName,
  usageStartAt,
  usageEndAt,
  planningStartAt,
  planningEndAt,
  activityDefaults,
  planningSynced,
  groupsForWizard,
  selectedGroupId,
  customerAddressId,
  venueAddressId,
  materialLines,
  invitedDepartments,
  activityNotes,
  setWizardGroups,
  missingSteps,
  canSubmit,
  canAdvanceFromCurrentStep,
  isLastStep,
  setActivityDefaults,
  notifyUsageChanged,
  notifyPlanningTouched,
  resyncPlanningFromUsage,
  selectActivityType,
  resetWizard,
  attemptNext,
  prevStep,
  jumpToMissingStep,
  buildCreatePayload,
  stepTitles,
  draftActivityId,
  saveDraftStep,
  applyInvitedDepartmentsApiResponse,
  hydrateFromActivityDetail,
  shouldAutoSubmitAfterWizard,
} = useActivityCreateWizard()

function onSelectActivityType(t: ActivityCreateType) {
  const didChangeType = selectActivityType(t)
  if (didChangeType) {
    submitError.value = ''
    void nextTick(() => {
      wizardFormRef.value?.scrollTo({ top: 0, behavior: 'smooth' })
    })
  }
}

function onUpdateFormName(v: string) {
  formName.value = v
}
const wizardDepartmentName = computed(() => {
  const row = authStore.departments.find((d) => d.department_id === props.departmentId)
  return row?.department?.name?.trim() || ''
})

function onSelectedGroupId(v: string | null) {
  selectedGroupId.value = v
}
function onCustomerAddressId(v: string | null) {
  customerAddressId.value = v
}
function onVenueAddressId(v: string | null) {
  venueAddressId.value = v
}
function onMaterialLines(v: ActivityMaterialLine[]) {
  materialLines.value = v
}
function onInvitedDepartments(v: InvitedDepartmentDraft[]) {
  invitedDepartments.value = v
}
function onActivityNotes(v: string) {
  activityNotes.value = v
}
function onUpdateUsageStartAt(v: Date | null) {
  usageStartAt.value = v
  notifyUsageChanged()
}
function onUpdateUsageEndAt(v: Date | null) {
  usageEndAt.value = v
  notifyUsageChanged()
}
function onUpdatePlanningStartAt(v: Date | null) {
  planningStartAt.value = v
  notifyPlanningTouched()
}
function onUpdatePlanningEndAt(v: Date | null) {
  planningEndAt.value = v
  notifyPlanningTouched()
}

const showSubmitButton = computed(() => {
  if (!selectedActivityType.value) return false
  if (layoutMode.value === 'single') return true
  return isLastStep.value
})

const submitButtonTitle = computed(() => {
  if (!canSubmit.value && missingSteps.value.length === 0 && showSubmitButton.value) {
    return t('activities.wizard.submitTitleWhenBlocked')
  }
  if (missingSteps.value.length > 0) {
    return missingSteps.value.map((k) => t(`activities.wizard.missing.${k}`)).join(', ')
  }
  return ''
})

const showDraftFooterStatus = computed(
  () =>
    layoutMode.value === 'stepper' &&
    !!selectedActivityType.value &&
    (!!draftActivityId.value || lastDraftSavedAt.value !== null),
)

const submitButtonLabel = computed(() => {
  switch (selectedActivityType.value) {
    case 'activity':
      return t('activities.wizard.submitActivity')
    case 'camp':
      return t('activities.wizard.submitCamp')
    case 'event':
      return t('activities.wizard.submitEvent')
    case 'external':
      return t('activities.wizard.submitExternal')
    default:
      return t('activities.wizard.defaultSubmit')
  }
})

const previewTitle = computed(() => {
  const n = formName.value.trim()
  if (n) return n
  if (selectedActivityType.value) return t('activities.wizard.previewUnnamed')
  return t('activities.wizard.previewPickType')
})

function formatDtLine(a: Date | null, b: Date | null): string {
  if (!a && !b) return '–'
  const opts: Intl.DateTimeFormatOptions = {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }
  const fa = a ? a.toLocaleString('de-CH', opts) : '–'
  const fb = b ? b.toLocaleString('de-CH', opts) : '–'
  return `${fa} – ${fb}`
}

const previewUsageLine = computed(() => formatDtLine(usageStartAt.value, usageEndAt.value))
const previewPlanningLine = computed(() => formatDtLine(planningStartAt.value, planningEndAt.value))

function findPreviewAddress(id: string | null | undefined): Address | undefined {
  if (!id) return undefined
  return previewAddresses.value.find((a) => a.id === id)
}

/** Kurzbezeichnung Eventstandort (Name/Firma, ggf. Ort) */
function formatVenuePreviewLine(a: Address): string {
  const head = [a.name, a.company].filter((x) => x && String(x).trim()).join(' · ')
  const city = a.city_line?.trim() || [a.postal_code, a.city].filter(Boolean).join(' ')
  if (head && city) return `${head}, ${city}`
  return head || city || a.full_address?.trim() || '–'
}

/**
 * Mieter: im Adressstamm meist ein Feld „name“ (z. B. Vorname Nachname).
 * Zusätzlich Firma, falls gesetzt.
 */
function formatMieterPreviewLine(a: Address): string {
  const n = a.name?.trim()
  const c = a.company?.trim()
  if (n && c) return `${n} (${c})`
  if (n) return n
  if (c) return c
  return a.full_address?.trim() || '–'
}

async function loadPreviewAddresses() {
  if (!props.departmentId) {
    previewAddresses.value = []
    return
  }
  try {
    const { addresses } = await getAddresses(props.departmentId)
    previewAddresses.value = addresses
  } catch {
    previewAddresses.value = []
  }
}

const previewGroupLine = computed(() => {
  const typ = selectedActivityType.value
  if (!typ || typ === 'external') return null
  if (typ === 'activity' && (!selectedGroupId.value || groupsForWizard.value.length === 0)) return null
  const label = resolveActivityGroupPickerLabel(
    selectedGroupId.value,
    wizardDepartmentName.value,
    groupsForWizard.value,
  )
  return label === '–' ? null : label
})

const previewVenueLine = computed(() => {
  const t = selectedActivityType.value
  if (!t || !['camp', 'event', 'external'].includes(t)) return null
  if (!venueAddressId.value) return null
  const a = findPreviewAddress(venueAddressId.value)
  if (!a) return null
  return formatVenuePreviewLine(a)
})

const previewMieterLine = computed(() => {
  if (selectedActivityType.value !== 'external') return null
  if (!customerAddressId.value) return null
  const a = findPreviewAddress(customerAddressId.value)
  if (!a) return null
  return formatMieterPreviewLine(a)
})

const previewMaterialLine = computed(() => {
  const lines = materialLines.value
  if (!lines.length) return null
  if (lines.length > 2) return `${lines.length} Positionen`
  return lines.map((l) => `${l.material_name} ×${l.quantity}`).join(', ')
})

const previewInvitedLine = computed(() => {
  const inv = invitedDepartments.value
  if (!inv.length) return null
  if (inv.length > 2) return `${inv.length} Abteilungen eingeladen`
  return inv.map((d) => d.name || d.id).join(', ')
})

function handleClose() {
  submitError.value = ''
  showDialog.value = false
}

function onJumpToMissing(key: ActivityMissingStepKey) {
  jumpToMissingStep(key)
  if (key === 'choose_type' && wizardFormRef.value) {
    wizardFormRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
  if (key === 'enter_name') {
    document.getElementById('activity-create-grunddaten')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
  if (key === 'check_date_range') {
    document.getElementById('activity-create-zeitraum')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
  if (key === 'choose_group') {
    const g =
      document.getElementById('activity-create-group') ?? document.getElementById('activity-create-group-stepper')
    g?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
  if (key === 'choose_venue' || key === 'choose_tenant_address') {
    document.getElementById('activity-create-grunddaten')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
  if (key === 'complete_date_ranges' || key === 'pickup_outside_usage') {
    document.getElementById('activity-create-zeitraum')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
  if (key === 'choose_material') {
    document.getElementById('activity-create-material')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
}

async function onWeiter() {
  submitError.value = ''
  if (!canAdvanceFromCurrentStep.value) {
    toast.error(t('activities.wizard.toastFillRequired'))
    return
  }
  if (layoutMode.value === 'stepper' && props.departmentId) {
    isSavingDraft.value = true
    try {
      const result = await saveDraftStep(props.departmentId)
      if (!result.ok) {
        toast.error(result.message)
        return
      }
      lastDraftSavedAt.value = new Date()
      if (invitedDepartments.value.length > 0) {
        headerNotificationsStore.requestRefresh()
      }
    } finally {
      isSavingDraft.value = false
    }
  }
  attemptNext()
}

async function handleSubmit() {
  if (!canSubmit.value || !props.departmentId) return
  submitError.value = ''
  isSubmitting.value = true
  try {
    const payload = buildCreatePayload(props.departmentId, {
      wizardCreateCompleted: true,
    })
    let id = ''
    if (draftActivityId.value) {
      const { department_id: _omit, ...patchBody } = payload
      const updated = await patchActivity(draftActivityId.value, patchBody)
      applyInvitedDepartmentsApiResponse(updated)
      id = draftActivityId.value
    } else {
      const created = await createActivity(payload)
      applyInvitedDepartmentsApiResponse(created)
      id = created?.id ? String(created.id) : ''
    }
    let materialSyncFailed = false
    if (id && materialLines.value.length > 0) {
      try {
        await syncActivityItems(id, {
          items: materialLines.value.map((l) => ({
            material_item_id: l.material_item_id,
            quantity: l.quantity,
            priority: 'normal',
          })),
        })
      } catch {
        materialSyncFailed = true
      }
    }
    const wantsAutoSubmit = shouldAutoSubmitAfterWizard()
    let autoSubmitFailed = false
    let autoSubmitError = ''
    if (id && !materialSyncFailed && wantsAutoSubmit) {
      try {
        await patchActivityStatus(id, { status: 'submitted' })
      } catch (err: unknown) {
        autoSubmitFailed = true
        const e = err as { response?: { data?: { error?: string } }; message?: string }
        autoSubmitError = e?.response?.data?.error || e?.message || ''
      }
    }
    if (materialSyncFailed) {
      toast.error(t('activities.wizard.toastActivityCreatedMaterialFailed'))
    } else if (autoSubmitFailed) {
      toast.error(autoSubmitError || t('activities.wizard.toastAutoSubmitFailed'))
    } else if (wantsAutoSubmit) {
      toast.success(t('activities.wizard.toastSubmitted'))
    } else {
      toast.success(t('activities.wizard.toastDraftSaved'))
    }
    lastDraftSavedAt.value = new Date()
    headerNotificationsStore.requestRefresh()
    emit('created', id)
    showDialog.value = false
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    const msg = e?.response?.data?.error || e?.message || t('activities.wizard.toastCreateFailed')
    submitError.value = msg
    toast.error(msg)
  } finally {
    isSubmitting.value = false
  }
}

watch(
  () => props.modelValue,
  async (open) => {
    if (!open) {
      resetWizard()
      lastDraftSavedAt.value = null
      previewAddresses.value = []
      submitError.value = ''
      return
    }
    if (!props.departmentId) return
    try {
      const d = await getActivityDefaults(props.departmentId)
      setActivityDefaults(d)
    } catch {
      toast.error(t('activities.wizard.toastDefaultsLoadFailed'))
      setActivityDefaults(FALLBACK_ACTIVITY_DEFAULTS)
    }
    try {
      await loadGroupsForDepartment(props.departmentId)
      const wizardGroups = wizardGroupsForUser(scopeGroups.value)
      if (isRestrictedGroupMember.value && !props.resumeActivityId && !selectedActivityType.value) {
        onSelectActivityType('activity')
      }
      setWizardGroups(wizardGroups)
    } catch {
      setWizardGroups([])
      setScopeGroups([])
    }
    void loadPreviewAddresses()
    if (props.resumeActivityId) {
      try {
        const [detail, items] = await Promise.all([
          getActivity(props.resumeActivityId),
          getActivityItems(props.resumeActivityId),
        ])
        await hydrateFromActivityDetail(detail, items)
        emit('resume-consumed')
      } catch {
        toast.error(t('components.activityCreateWizard.toastDraftLoadFailed'))
      }
    }
  },
)

watch(
  () => [customerAddressId.value, venueAddressId.value, props.modelValue] as const,
  () => {
    if (!props.modelValue || !props.departmentId) return
    const ids = [customerAddressId.value, venueAddressId.value].filter(Boolean) as string[]
    if (ids.length === 0) return
    const missing = ids.some((id) => !previewAddresses.value.some((a) => a.id === id))
    if (missing) void loadPreviewAddresses()
  },
)

</script>
