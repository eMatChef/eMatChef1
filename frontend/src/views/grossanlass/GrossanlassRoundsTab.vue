<template>
  <div class="grossanlass-rounds">
    <div class="page-header">
      <div>
        <p class="tab-description">{{ roundsSubtitle }}</p>
      </div>
      <EButton v-if="canManage" variant="primary" @click="openCreateModal()">
        <v-icon icon="mdi-plus" start size="20" />
        {{ t('grossanlass.planung.rounds.addAction') }}
      </EButton>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="list"
      :message="t('grossanlass.planung.rounds.loading')"
    />

    <template v-else>
      <section
        v-for="group in formGroups"
        :key="group.purpose"
        class="wish-form-group"
      >
        <div class="wish-form-group__head">
          <h2 class="wish-form-group__title">
            <span class="purpose-badge" :class="'purpose-' + group.purpose">{{ group.title }}</span>
          </h2>
          <p class="wish-form-group__landing">{{ group.landing }}</p>
        </div>

        <v-alert
          v-if="error"
          type="error"
          variant="tonal"
          :text="error"
          class="mb-3"
        />
        <EButton
          v-if="error"
          variant="secondary"
          class="mb-3"
          @click="loadRounds"
        >
          {{ t('common.retry') }}
        </EButton>

        <EEmptyState
          v-if="!group.rows.length"
          compact
          variant="create"
          :heading-level="3"
          icon="mdi-form-select"
          :title="group.emptyTitle"
          :description="group.emptyDescription"
        >
          <template v-if="canManage" #actions>
            <EButton size="small" @click="openCreateModal(group.purpose)">
              {{ t('grossanlass.planung.rounds.addAction') }}
            </EButton>
          </template>
        </EEmptyState>

        <div v-else class="table-wrapper">
          <table class="rounds-table">
            <thead>
              <tr>
                <th class="col-name">{{ t('grossanlass.planung.rounds.colName') }}</th>
                <th class="col-status">{{ t('grossanlass.planung.rounds.colStatus') }}</th>
                <th class="col-window">{{ t('grossanlass.planung.rounds.colWindow') }}</th>
                <th class="col-auto">{{ t('grossanlass.planung.rounds.colAuto') }}</th>
                <th v-if="canManage" class="col-actions"></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in group.rows"
                :key="row.id"
                class="round-row"
                @click="openRow(row)"
              >
                <td class="col-name col-name--link">
                  <span class="round-name">{{ row.name }}</span>
                  <span class="round-type">{{ purposeLabel(row.purpose) }}</span>
                </td>
                <td class="col-status">
                  <span class="status-badge" :class="'status-' + row.status">
                    {{ statusLabel(row.status) }}
                  </span>
                </td>
                <td class="col-window">
                  <span class="window-text">{{ formatWindow(row.opens_at, row.closes_at) }}</span>
                </td>
                <td class="col-auto">
                  <v-icon
                    v-if="row.use_auto_schedule"
                    icon="mdi-clock-check-outline"
                    size="18"
                    color="primary"
                    :title="t('grossanlass.planung.rounds.autoEnabled')"
                  />
                  <span v-else class="text-muted">–</span>
                </td>
                <td v-if="canManage" class="col-actions" @click="stopRowClick">
                  <div v-if="row.live" class="action-buttons">
                    <button
                      v-if="canEditForm && row.live.status !== 'closed'"
                      class="action-btn"
                      :title="t('grossanlass.formBuilder.editFormAction')"
                      @click="openFormModal(row.live)"
                    >
                      <v-icon icon="mdi-form-select" size="16" />
                    </button>
                    <button
                      v-if="row.live.status !== 'closed'"
                      class="action-btn"
                      :title="t('common.edit')"
                      @click="openEditModal(row.live)"
                    >
                      <v-icon icon="mdi-pencil-outline" size="16" />
                    </button>
                    <button
                      v-if="row.live.status === 'scheduled'"
                      class="action-btn action-btn-primary"
                      :title="t('grossanlass.planung.rounds.openAction')"
                      @click="handleOpen(row.live)"
                    >
                      <v-icon icon="mdi-play-circle-outline" size="16" />
                    </button>
                    <button
                      v-if="row.live.status === 'open'"
                      class="action-btn action-btn-warning"
                      :title="t('grossanlass.planung.rounds.closeAction')"
                      @click="handleClose(row.live)"
                    >
                      <v-icon icon="mdi-stop-circle-outline" size="16" />
                    </button>
                    <button
                      v-if="row.live.status === 'closed'"
                      class="action-btn action-btn-primary"
                      :title="t('grossanlass.planung.rounds.reopenAction')"
                      @click="handleReopen(row.live)"
                    >
                      <v-icon icon="mdi-replay" size="16" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <EDialog
      v-model="showModal"
      :max-width="wizardStep === 2 ? 920 : 800"
      :title="modalTitle"
      scrollable
      :retain-focus="false"
      card-class="grossanlass-form-dialog-card"
    >
      <div v-if="showWizardSteps && !formOnlyModal" class="wizard-steps">
        <span class="wizard-step" :class="{ active: wizardStep === 1 }">{{ t('grossanlass.planung.rounds.wizardStepRound') }}</span>
        <v-icon icon="mdi-chevron-right" size="16" class="wizard-step-sep" />
        <span class="wizard-step" :class="{ active: wizardStep === 2 }">{{ t('grossanlass.planung.rounds.wizardStepForm') }}</span>
      </div>

      <template v-if="wizardStep === 1">
        <ESelect
          v-if="!editingRound"
          v-model="form.purpose"
          :items="purposeItems"
          item-title="title"
          item-value="value"
          :label="t('grossanlass.planung.wishForms.purposeLabel')"
          hide-details="auto"
          class="mb-3"
        />
        <p class="purpose-hint">{{ purposeHint }}</p>
        <v-alert
          v-if="form.purpose === 'material_wish'"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-3 grob-fein-alert"
        >
          {{ t('grossanlass.planung.wishForms.grobFeinHint') }}
        </v-alert>
        <ETextField
          v-model="form.name"
          :label="t('grossanlass.planung.rounds.nameLabel')"
          :placeholder="namePlaceholder"
          hide-details="auto"
          class="mb-3"
        />

        <div class="activity-datetime-host round-single-time mb-3">
          <ActivityOutlinedDatetimeSection
            :title="t('grossanlass.planung.rounds.opensAtLabel')"
            icon="calendar"
          >
            <ActivityDateTimeFields
              v-model:day="opensDay"
              v-model:time-from="opensAt"
              v-model:time-to="opensAtTimeToDummy"
              date-mode="single"
              layout="stacked"
              :department-id="departmentId"
              :show-presets="true"
              :show-markers="true"
              preset-mode="fixed-periods"
              :disabled="!canEditOpens"
              :times-locked="!canEditOpens"
              :label-from="t('activities.zeitraum.timeFrom')"
              :label-to="t('activities.zeitraum.timeTo')"
              :aria-label="t('grossanlass.planung.rounds.opensAtLabel')"
            />
          </ActivityOutlinedDatetimeSection>
        </div>

        <ECheckbox
          v-model="hasClosesAt"
          :label="t('grossanlass.planung.rounds.closesAtEnable')"
          :hint="t('grossanlass.planung.rounds.closesAtEnableHint')"
          hide-details="auto"
          class="mb-2"
          @update:model-value="onHasClosesAtToggle"
        />

        <div v-if="hasClosesAt" class="activity-datetime-host round-single-time mb-3">
          <ActivityOutlinedDatetimeSection
            :title="t('grossanlass.planung.rounds.closesAtLabel')"
            icon="calendar"
          >
            <ActivityDateTimeFields
              v-model:day="closesDay"
              v-model:time-from="closesAt"
              v-model:time-to="closesAtTimeToDummy"
              date-mode="single"
              layout="stacked"
              :department-id="departmentId"
              :show-presets="true"
              :show-markers="true"
              preset-mode="fixed-periods"
              :label-from="t('activities.zeitraum.timeFrom')"
              :label-to="t('activities.zeitraum.timeTo')"
              :aria-label="t('grossanlass.planung.rounds.closesAtLabel')"
            />
          </ActivityOutlinedDatetimeSection>
        </div>

        <ECheckbox
          v-model="form.useAutoSchedule"
          :label="t('grossanlass.planung.rounds.autoScheduleLabel')"
          :hint="t('grossanlass.planung.rounds.autoScheduleHint')"
          hide-details="auto"
          @update:model-value="onAutoScheduleToggle"
        />
      </template>

      <GrossanlassRoundFormBuilder
        v-else-if="wizardRoundId"
        ref="formBuilderRef"
        :department-id="departmentId"
        :round-id="wizardRoundId"
        embedded
        :show-actions="false"
        silent-save
      />

      <template #actions>
        <EButton variant="secondary" @click.stop="closeModal">{{ t('common.cancel') }}</EButton>
        <template v-if="wizardStep === 1">
          <EButton
            v-if="editingRound && showWizardSteps"
            variant="secondary"
            :loading="isSaving"
            @click.stop="saveRoundAndClose"
          >
            {{ t('common.save') }}
          </EButton>
          <EButton
            v-if="showWizardSteps"
            variant="primary"
            :loading="isSaving"
            @click.stop="goToFormStep"
          >
            {{ t('grossanlass.planung.rounds.wizardNext') }}
          </EButton>
          <EButton
            v-else
            variant="primary"
            :loading="isSaving"
            @click.stop="saveRoundAndClose"
          >
            {{ isSaving ? t('grossanlass.planung.rounds.saving') : t('common.save') }}
          </EButton>
        </template>
        <template v-else>
          <EButton v-if="!formOnlyModal" variant="secondary" @click.stop="wizardStep = 1">{{ t('grossanlass.planung.rounds.wizardBack') }}</EButton>
          <EButton variant="primary" :loading="isSavingForm" @click.stop="finishWizard">
            {{ formOnlyModal ? t('common.save') : t('grossanlass.planung.rounds.wizardFinish') }}
          </EButton>
        </template>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ECheckbox, EDialog, ESelect, ETextField } from '@/components/form/base'
import ActivityOutlinedDatetimeSection from '@/components/activities/wizard/ActivityOutlinedDatetimeSection.vue'
import ActivityDateTimeFields from '@/components/activities/wizard/ActivityDateTimeFields.vue'
import { combineDayAndTime, startOfLocalDay } from '@/utils/activityDateTimeParts'
import GrossanlassRoundFormBuilder from '@/components/grossanlass/GrossanlassRoundFormBuilder.vue'
import {
  closeGrossanlassPlanningRound,
  createGrossanlassPlanningRound,
  getGrossanlassPlanningRounds,
  openGrossanlassPlanningRound,
  reopenGrossanlassPlanningRound,
  updateGrossanlassPlanningRound,
  type GrossanlassFormPurpose,
  type GrossanlassPlanningRound,
  type GrossanlassRoundStatus,
} from '@/api/grossanlassRounds'

type WishFormRow = {
  id: string
  name: string
  purpose: GrossanlassFormPurpose
  status: GrossanlassRoundStatus
  opens_at: string | null
  closes_at: string | null
  use_auto_schedule: boolean
  live: GrossanlassPlanningRound | null
}

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const { isUserRole, isMaterialwart } = useDepartmentMemberRole()

const departmentId = computed(() => String(route.params.departmentId || ''))
const canManage = computed(() => !isUserRole.value)
const canEditForm = computed(() => isMaterialwart.value)

const rounds = ref<GrossanlassPlanningRound[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const error = ref('')
const showModal = ref(false)
const editingRound = ref<GrossanlassPlanningRound | null>(null)
const wizardStep = ref<1 | 2>(1)
const wizardRoundId = ref<string | null>(null)
const formBuilderRef = ref<InstanceType<typeof GrossanlassRoundFormBuilder> | null>(null)
const isSavingForm = ref(false)
const formOnlyModal = ref(false)

const form = ref({
  name: '',
  purpose: 'material_wish' as GrossanlassFormPurpose,
  useAutoSchedule: false,
})

const opensAt = ref<Date | null>(null)
const closesAt = ref<Date | null>(null)
const hasClosesAt = ref(false)
const opensAtTimeToDummy = ref<Date | null>(null)
const closesAtTimeToDummy = ref<Date | null>(null)

const canEditOpens = computed(
  () => !editingRound.value || editingRound.value.status === 'scheduled',
)

const purposeItems = computed(() => [
  { title: t('grossanlass.planung.wishForms.purposeMaterial'), value: 'material_wish' },
  { title: t('grossanlass.planung.wishForms.purposeCompany'), value: 'company_tip' },
  { title: t('grossanlass.planung.wishForms.purposeFree'), value: 'free' },
])

const purposeHint = computed(() => {
  switch (form.value.purpose) {
    case 'company_tip':
      return t('grossanlass.planung.wishForms.purposeHintCompany')
    case 'free':
      return t('grossanlass.planung.wishForms.purposeHintFree')
    default:
      return t('grossanlass.planung.wishForms.purposeHintMaterial')
  }
})

const namePlaceholder = computed(() => {
  switch (form.value.purpose) {
    case 'company_tip':
      return t('grossanlass.planung.wishForms.namePlaceholderCompany')
    case 'free':
      return t('grossanlass.planung.wishForms.namePlaceholderFree')
    default:
      return t('grossanlass.planung.wishForms.namePlaceholderMaterial')
  }
})

function purposeLabel(purpose: GrossanlassFormPurpose): string {
  switch (purpose) {
    case 'company_tip':
      return t('grossanlass.planung.wishForms.purposeCompany')
    case 'free':
      return t('grossanlass.planung.wishForms.purposeFree')
    default:
      return t('grossanlass.planung.wishForms.purposeMaterial')
  }
}

function purposeOf(round: GrossanlassPlanningRound): GrossanlassFormPurpose {
  return round.form_purpose || 'material_wish'
}

function toLiveRows(purpose: GrossanlassFormPurpose): WishFormRow[] {
  return rounds.value
    .filter((round) => purposeOf(round) === purpose)
    .map((round) => ({
      id: round.id,
      name: round.name,
      purpose: purposeOf(round),
      status: round.status,
      opens_at: round.opens_at,
      closes_at: round.closes_at,
      use_auto_schedule: round.use_auto_schedule,
      live: round,
    }))
}

const formGroups = computed(() => [
  {
    purpose: 'material_wish' as const,
    title: t('grossanlass.planung.wishForms.groupMaterialTitle'),
    landing: t('grossanlass.planung.wishForms.landingMaterial'),
    emptyTitle: t('grossanlass.planung.wishForms.emptyMaterialTitle'),
    emptyDescription: t('grossanlass.planung.wishForms.emptyMaterialDescription'),
    rows: toLiveRows('material_wish'),
  },
  {
    purpose: 'company_tip' as const,
    title: t('grossanlass.planung.wishForms.groupCompanyTitle'),
    landing: t('grossanlass.planung.wishForms.landingCompany'),
    emptyTitle: t('grossanlass.planung.wishForms.emptyCompanyTitle'),
    emptyDescription: t('grossanlass.planung.wishForms.emptyCompanyDescription'),
    rows: toLiveRows('company_tip'),
  },
  {
    purpose: 'free' as const,
    title: t('grossanlass.planung.wishForms.groupFreeTitle'),
    landing: t('grossanlass.planung.wishForms.landingFree'),
    emptyTitle: t('grossanlass.planung.wishForms.emptyFreeTitle'),
    emptyDescription: t('grossanlass.planung.wishForms.emptyFreeDescription'),
    rows: toLiveRows('free'),
  },
])

function defaultQuarterTime(day: Date, hour: number, minute: number): Date {
  return new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour, minute, 0, 0)
}

function defaultOpensAt(): Date {
  return defaultQuarterTime(new Date(), 9, 0)
}
function defaultClosesAt(after: Date | null): Date {
  const candidate = defaultQuarterTime(after ?? new Date(), 17, 0)
  if (after && candidate <= after) {
    const nextDay = new Date(after)
    nextDay.setDate(nextDay.getDate() + 1)
    return defaultQuarterTime(nextDay, 17, 0)
  }
  return candidate
}

function parseIsoDate(iso: string | null): Date | null {
  if (!iso) return null
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? null : d
}

const opensDay = computed({
  get: () => (opensAt.value ? startOfLocalDay(opensAt.value) : null),
  set: (d: Date | null) => {
    if (!d) {
      opensAt.value = null
      return
    }
    opensAt.value = combineDayAndTime(d, opensAt.value ?? defaultQuarterTime(d, 9, 0))
  },
})

const closesDay = computed({
  get: () => (closesAt.value ? startOfLocalDay(closesAt.value) : null),
  set: (d: Date | null) => {
    if (!d) {
      closesAt.value = null
      return
    }
    closesAt.value = combineDayAndTime(d, closesAt.value ?? defaultQuarterTime(d, 17, 0))
  },
})

const roundsSubtitle = computed(() =>
  canManage.value
    ? t('grossanlass.planung.rounds.subtitleMw')
    : t('grossanlass.planung.rounds.subtitleMember'),
)

const modalTitle = computed(() => {
  if (wizardStep.value === 2) {
    return t('grossanlass.formBuilder.title')
  }
  return editingRound.value
    ? t('grossanlass.planung.rounds.modalEdit')
    : t('grossanlass.planung.rounds.modalNew')
})

const showWizardSteps = computed(() => {
  if (editingRound.value?.status === 'closed') return false
  if (!canEditForm.value) return false
  return true
})

function statusLabel(status: GrossanlassRoundStatus): string {
  switch (status) {
    case 'open':
      return t('grossanlass.planung.rounds.statusOpen')
    case 'closed':
      return t('grossanlass.planung.rounds.statusClosed')
    default:
      return t('grossanlass.planung.rounds.statusScheduled')
  }
}

function formatDateTime(iso: string | null): string {
  if (!iso) return '–'
  try {
    return new Date(iso).toLocaleString('de-CH', { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return iso
  }
}

function formatWindow(opensAtIso: string | null, closesAtIso: string | null): string {
  const open = formatDateTime(opensAtIso)
  const close = formatDateTime(closesAtIso)
  if (open === '–' && close === '–') return t('grossanlass.planung.rounds.windowManual')
  return t('grossanlass.planung.rounds.windowRange', { open, close })
}

function onHasClosesAtToggle(checked: boolean | null) {
  if (checked && !closesAt.value) {
    closesAt.value = defaultClosesAt(opensAt.value)
  }
}

function onAutoScheduleToggle(checked: boolean | null) {
  if (!checked) return
  if (canEditOpens.value && !opensAt.value) {
    opensAt.value = defaultOpensAt()
  }
}

function resetForm(purpose: GrossanlassFormPurpose = 'material_wish') {
  form.value = {
    name: '',
    purpose,
    useAutoSchedule: false,
  }
  opensAt.value = defaultOpensAt()
  closesAt.value = null
  hasClosesAt.value = false
}

function openCreateModal(purpose: GrossanlassFormPurpose = 'material_wish') {
  editingRound.value = null
  wizardStep.value = 1
  wizardRoundId.value = null
  formOnlyModal.value = false
  resetForm(purpose)
  showModal.value = true
}

function openEditModal(round: GrossanlassPlanningRound) {
  editingRound.value = round
  wizardStep.value = 1
  wizardRoundId.value = round.id
  formOnlyModal.value = false
  form.value = {
    name: round.name,
    purpose: purposeOf(round),
    useAutoSchedule: round.use_auto_schedule,
  }
  const parsedOpens = parseIsoDate(round.opens_at) ?? parseIsoDate(round.opened_at)
  const isScheduled = round.status === 'scheduled'
  opensAt.value = parsedOpens ?? (isScheduled ? defaultOpensAt() : null)
  closesAt.value = parseIsoDate(round.closes_at)
  hasClosesAt.value = closesAt.value != null
  showModal.value = true
}

function openFormModal(round: GrossanlassPlanningRound) {
  editingRound.value = round
  wizardStep.value = 2
  wizardRoundId.value = round.id
  formOnlyModal.value = true
  showModal.value = true
}

async function closeModal() {
  await nextTick()
  showModal.value = false
}

function validateRoundFields(): boolean {
  const name = form.value.name.trim()
  if (!name) {
    toast.error(t('grossanlass.planung.rounds.nameRequired'))
    return false
  }
  if (form.value.useAutoSchedule && canEditOpens.value && !opensAt.value) {
    toast.error(t('grossanlass.planung.rounds.autoScheduleNeedsOpens'))
    return false
  }
  if (hasClosesAt.value && !closesAt.value) {
    toast.error(t('grossanlass.planung.rounds.closesAtRequired'))
    return false
  }
  if (opensAt.value && hasClosesAt.value && closesAt.value && closesAt.value < opensAt.value) {
    toast.error(t('grossanlass.planung.rounds.windowInvalid'))
    return false
  }
  return true
}

function buildRoundPayload() {
  return {
    name: form.value.name.trim(),
    form_purpose: form.value.purpose,
    opens_at: opensAt.value?.toISOString() ?? null,
    closes_at: hasClosesAt.value ? closesAt.value?.toISOString() ?? null : null,
    use_auto_schedule: form.value.useAutoSchedule,
  }
}

async function persistRoundStep(): Promise<GrossanlassPlanningRound | null> {
  if (!departmentId.value) return null
  if (!validateRoundFields()) return null

  isSaving.value = true
  try {
    const payload = buildRoundPayload()
    if (editingRound.value) {
      const updatePayload = { ...payload }
      if (editingRound.value.status === 'open') {
        delete (updatePayload as { opens_at?: string | null }).opens_at
      }
      const updated = await updateGrossanlassPlanningRound(departmentId.value, editingRound.value.id, updatePayload)
      editingRound.value = updated
      wizardRoundId.value = updated.id
      return updated
    }
    const created = await createGrossanlassPlanningRound(departmentId.value, payload)
    editingRound.value = created
    wizardRoundId.value = created.id
    return created
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.planung.rounds.errorSave'))
    return null
  } finally {
    isSaving.value = false
  }
}

async function saveRoundAndClose() {
  const wasCreate = !editingRound.value
  const saved = await persistRoundStep()
  if (!saved) return
  toast.success(wasCreate ? t('grossanlass.planung.rounds.created') : t('grossanlass.planung.rounds.saved'))
  closeModal()
  await loadRounds()
}

async function goToFormStep() {
  const wasCreate = !editingRound.value
  const saved = await persistRoundStep()
  if (!saved) return
  if (wasCreate) {
    toast.success(t('grossanlass.planung.rounds.created'))
  }
  wizardStep.value = 2
}

async function finishWizard() {
  isSavingForm.value = true
  try {
    if (formBuilderRef.value) {
      const ok = await formBuilderRef.value.flushAutoSave()
      if (!ok) return
    }
    closeModal()
    await loadRounds()
  } finally {
    isSavingForm.value = false
  }
}

async function loadRounds() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = ''
  try {
    rounds.value = await getGrossanlassPlanningRounds(departmentId.value)
  } catch (e: any) {
    error.value = e.response?.data?.error || t('grossanlass.planung.rounds.errorLoad')
    rounds.value = []
  } finally {
    isLoading.value = false
  }
}

async function handleOpen(round: GrossanlassPlanningRound) {
  if (!departmentId.value) return
  try {
    await openGrossanlassPlanningRound(departmentId.value, round.id)
    toast.success(t('grossanlass.planung.rounds.opened', { name: round.name }))
    await loadRounds()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.planung.rounds.errorOpen'))
  }
}

async function handleClose(round: GrossanlassPlanningRound) {
  if (!departmentId.value) return
  const ok = await confirm.confirm({
    title: t('grossanlass.planung.rounds.closeConfirmTitle'),
    message: t('grossanlass.planung.rounds.closeConfirmMessage', { name: round.name }),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await closeGrossanlassPlanningRound(departmentId.value, round.id)
    toast.success(t('grossanlass.planung.rounds.closed', { name: round.name }))
    await loadRounds()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.planung.rounds.errorClose'))
  }
}

async function handleReopen(round: GrossanlassPlanningRound) {
  if (!departmentId.value) return
  const ok = await confirm.confirm({
    title: t('grossanlass.planung.rounds.reopenConfirmTitle'),
    message: t('grossanlass.planung.rounds.reopenConfirmMessage', { name: round.name }),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await reopenGrossanlassPlanningRound(departmentId.value, round.id)
    toast.success(t('grossanlass.planung.rounds.reopened', { name: round.name }))
    await loadRounds()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.planung.rounds.errorReopen'))
  }
}

function openRow(row: WishFormRow) {
  if (row.live) {
    void router.push(`/${departmentId.value}/planung/runden/${row.live.id}`)
  }
}

function stopRowClick(event: Event) {
  event.stopPropagation()
}

onMounted(loadRounds)
</script>

<style scoped>
.grossanlass-rounds {
  padding: 8px 0 24px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}

.tab-description {
  margin: 0;
  color: #6b7280;
  font-size: 0.9rem;
  line-height: 1.45;
  max-width: 42rem;
}

.wish-form-group {
  margin-bottom: 28px;
}

.wish-form-group__head {
  margin-bottom: 10px;
}

.wish-form-group__title {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 6px;
  font-size: 1.05rem;
  font-weight: 700;
  color: #111827;
}

.wish-form-group__landing {
  margin: 0;
  color: #6b7280;
  font-size: 0.85rem;
  line-height: 1.45;
  max-width: 42rem;
}

.purpose-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.purpose-material_wish {
  background: #dbeafe;
  color: #1e40af;
}

.purpose-company_tip {
  background: #fef3c7;
  color: #92400e;
}

.purpose-free {
  background: #ede9fe;
  color: #5b21b6;
}

.purpose-hint {
  margin: 0 0 12px;
  color: #6b7280;
  font-size: 0.85rem;
  line-height: 1.45;
}

.preview-detail-meta {
  display: flex;
  gap: 8px;
  align-items: center;
  margin: 0 0 12px;
}

.preview-samples-title {
  margin: 16px 0 8px;
  font-size: 0.9rem;
  font-weight: 700;
}

.preview-samples {
  margin: 0;
  padding: 0;
  list-style: none;
}

.preview-samples li {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 0;
  border-bottom: 1px solid #f3f4f6;
  font-size: 0.88rem;
}

.preview-samples span {
  color: #6b7280;
  font-size: 0.8rem;
}

.table-wrapper {
  overflow-x: auto;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}

.rounds-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.rounds-table th,
.rounds-table td {
  padding: 12px 14px;
  text-align: left;
  border-bottom: 1px solid #f3f4f6;
}

.rounds-table th {
  background: #f9fafb;
  font-weight: 600;
  color: #374151;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.round-row {
  cursor: pointer;
}

.round-row:hover {
  background: #f9fafb;
}

.round-name {
  display: block;
  font-weight: 600;
  color: #111827;
}

.col-name--link .round-name {
  color: #2563eb;
}

.round-type {
  display: block;
  font-size: 0.78rem;
  color: #6b7280;
  margin-top: 2px;
}

.status-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-scheduled {
  background: #e0e7ff;
  color: #3730a3;
}

.status-open {
  background: #d1fae5;
  color: #065f46;
}

.status-closed {
  background: #f3f4f6;
  color: #4b5563;
}

.window-text {
  font-size: 0.85rem;
  color: #4b5563;
}

.text-muted {
  color: #9ca3af;
}

.action-buttons {
  display: flex;
  gap: 4px;
  justify-content: flex-end;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
  color: #4b5563;
}

.action-btn:hover {
  background: #f9fafb;
}

.action-btn-primary {
  color: #059669;
  border-color: #a7f3d0;
}

.action-btn-warning {
  color: #d97706;
  border-color: #fde68a;
}

.col-actions {
  width: 156px;
}

.col-auto {
  width: 64px;
  text-align: center;
}

.col-status {
  width: 120px;
}

.round-single-time {
  min-width: 0;
  max-width: 100%;
}

.round-single-time :deep(.activity-outlined-fieldset) {
  min-inline-size: 0;
  width: 100%;
  max-width: 100%;
}

.round-single-time :deep(.activity-datetime-mobile__times) {
  grid-template-columns: 1fr;
}

.round-single-time :deep(.activity-datetime-mobile__time-slot:last-child),
.round-single-time :deep(.activity-pill-cell--time:last-child) {
  display: none;
}

.grob-fein-alert :deep(.v-alert__content) {
  white-space: normal;
  overflow-wrap: break-word;
}

.wizard-steps {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 16px;
  font-size: 0.82rem;
}

.wizard-step {
  color: #9ca3af;
  font-weight: 500;
}

.wizard-step.active {
  color: #2563eb;
  font-weight: 600;
}

.wizard-step-sep {
  color: #d1d5db;
}
</style>

<style>
@import '@/styles/components/activity-datetime-field.css';
@import '@/styles/components/activity-datetime-layout.css';

.grossanlass-form-dialog-card .v-card-text.e-dialog__body {
  overflow-x: hidden;
  min-width: 0;
}

.grossanlass-form-dialog-card .grob-fein-alert .v-alert__content {
  white-space: normal;
  overflow-wrap: break-word;
}
</style>
