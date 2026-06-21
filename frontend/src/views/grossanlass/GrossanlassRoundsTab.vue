<template>
  <div class="grossanlass-rounds">
    <div class="page-header">
      <div>
        <p class="tab-description">{{ roundsSubtitle }}</p>
      </div>
      <EButton v-if="canManage" variant="primary" @click="openCreateModal">
        <v-icon icon="mdi-plus" start size="20" />
        {{ t('grossanlass.planung.rounds.addAction') }}
      </EButton>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="list"
      :message="t('grossanlass.planung.rounds.loading')"
    />

    <div v-else-if="error" class="rounds-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadRounds">{{ t('common.retry') }}</EButton>
    </div>

    <EEmptyState
      v-else-if="rounds.length === 0"
      variant="create"
      icon="mdi-calendar-clock"
      :title="t('grossanlass.planung.rounds.emptyTitle')"
      :description="t('grossanlass.planung.rounds.emptyDescription')"
    >
      <template v-if="canManage" #actions>
        <EButton @click="openCreateModal">{{ t('grossanlass.planung.rounds.addAction') }}</EButton>
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
          <tr v-for="round in rounds" :key="round.id" class="round-row" @click="openRound(round)">
            <td class="col-name col-name--link">
              <span class="round-name">{{ round.name }}</span>
              <span class="round-type">{{ t('grossanlass.planung.rounds.typeRessortWuensche') }}</span>
            </td>
            <td class="col-status">
              <span class="status-badge" :class="'status-' + round.status">
                {{ statusLabel(round.status) }}
              </span>
            </td>
            <td class="col-window">
              <span class="window-text">{{ formatWindow(round) }}</span>
            </td>
            <td class="col-auto">
              <v-icon
                v-if="round.use_auto_schedule"
                icon="mdi-clock-check-outline"
                size="18"
                color="primary"
                :title="t('grossanlass.planung.rounds.autoEnabled')"
              />
              <span v-else class="text-muted">–</span>
            </td>
            <td v-if="canManage" class="col-actions" @click="stopRowClick">
              <div class="action-buttons">
                <button
                  v-if="round.status !== 'closed'"
                  class="action-btn"
                  :title="t('common.edit')"
                  @click="openEditModal(round)"
                >
                  <v-icon icon="mdi-pencil-outline" size="16" />
                </button>
                <button
                  v-if="round.status === 'scheduled'"
                  class="action-btn action-btn-primary"
                  :title="t('grossanlass.planung.rounds.openAction')"
                  @click="handleOpen(round)"
                >
                  <v-icon icon="mdi-play-circle-outline" size="16" />
                </button>
                <button
                  v-if="round.status === 'open'"
                  class="action-btn action-btn-warning"
                  :title="t('grossanlass.planung.rounds.closeAction')"
                  @click="handleClose(round)"
                >
                  <v-icon icon="mdi-stop-circle-outline" size="16" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EDialog v-model="showModal" :max-width="720" :title="modalTitle">
      <ETextField
        v-model="form.name"
        :label="t('grossanlass.planung.rounds.nameLabel')"
        :placeholder="t('grossanlass.planung.rounds.namePlaceholder')"
        hide-details="auto"
        class="mb-3"
      />

      <div
        v-if="!editingRound || editingRound.status === 'scheduled'"
        class="activity-datetime-host round-single-time mb-3"
      >
        <ActivityOutlinedDatetimeSection
          :title="t('grossanlass.planung.rounds.opensAtLabel')"
          icon="calendar"
        >
          <ActivityDateTimeFields
            v-model:day="opensDay"
            v-model:time-from="opensAt"
            v-model:time-to="opensAtTimeToDummy"
            date-mode="single"
            :department-id="departmentId"
            :show-presets="true"
            :show-markers="true"
            preset-mode="fixed-periods"
            :label-from="t('activities.zeitraum.timeFrom')"
            :label-to="t('activities.zeitraum.timeTo')"
            :aria-label="t('grossanlass.planung.rounds.opensAtLabel')"
          />
        </ActivityOutlinedDatetimeSection>
      </div>

      <div class="activity-datetime-host round-single-time mb-3">
        <ActivityOutlinedDatetimeSection
          :title="t('grossanlass.planung.rounds.closesAtLabel')"
          icon="calendar"
        >
          <ActivityDateTimeFields
            v-model:day="closesDay"
            v-model:time-from="closesAt"
            v-model:time-to="closesAtTimeToDummy"
            date-mode="single"
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
      />
      <template #actions>
        <EButton variant="secondary" @click="showModal = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :loading="isSaving" @click="saveRound">
          {{ isSaving ? t('grossanlass.planung.rounds.saving') : (editingRound ? t('common.save') : t('common.create')) }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ECheckbox, EDialog, ETextField } from '@/components/form/base'
import ActivityOutlinedDatetimeSection from '@/components/activities/wizard/ActivityOutlinedDatetimeSection.vue'
import ActivityDateTimeFields from '@/components/activities/wizard/ActivityDateTimeFields.vue'
import { combineDayAndTime, startOfLocalDay } from '@/utils/activityDateTimeParts'
import {
  closeGrossanlassPlanningRound,
  createGrossanlassPlanningRound,
  getGrossanlassPlanningRounds,
  openGrossanlassPlanningRound,
  updateGrossanlassPlanningRound,
  type GrossanlassPlanningRound,
  type GrossanlassRoundStatus,
} from '@/api/grossanlassRounds'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const { isUserRole } = useDepartmentMemberRole()

const departmentId = computed(() => String(route.params.departmentId || ''))
const canManage = computed(() => !isUserRole.value)

const rounds = ref<GrossanlassPlanningRound[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const error = ref('')
const showModal = ref(false)
const editingRound = ref<GrossanlassPlanningRound | null>(null)

const form = ref({
  name: '',
  useAutoSchedule: false,
})

const opensAt = ref<Date | null>(null)
const closesAt = ref<Date | null>(null)
const opensAtTimeToDummy = ref<Date | null>(null)
const closesAtTimeToDummy = ref<Date | null>(null)

function defaultQuarterTime(day: Date, hour: number, minute: number): Date {
  return new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour, minute, 0, 0)
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

const modalTitle = computed(() =>
  editingRound.value
    ? t('grossanlass.planung.rounds.modalEdit')
    : t('grossanlass.planung.rounds.modalNew'),
)

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

function formatWindow(round: GrossanlassPlanningRound): string {
  const open = formatDateTime(round.opens_at)
  const close = formatDateTime(round.closes_at)
  if (open === '–' && close === '–') return t('grossanlass.planung.rounds.windowManual')
  return t('grossanlass.planung.rounds.windowRange', { open, close })
}

function resetForm() {
  form.value = {
    name: '',
    useAutoSchedule: false,
  }
  opensAt.value = null
  closesAt.value = null
}

function openCreateModal() {
  editingRound.value = null
  resetForm()
  showModal.value = true
}

function openEditModal(round: GrossanlassPlanningRound) {
  editingRound.value = round
  form.value = {
    name: round.name,
    useAutoSchedule: round.use_auto_schedule,
  }
  opensAt.value = parseIsoDate(round.opens_at)
  closesAt.value = parseIsoDate(round.closes_at)
  showModal.value = true
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

async function saveRound() {
  if (!departmentId.value) return
  const name = form.value.name.trim()
  if (!name) {
    toast.error(t('grossanlass.planung.rounds.nameRequired'))
    return
  }

  isSaving.value = true
  try {
    const payload = {
      name,
      opens_at: opensAt.value?.toISOString() ?? null,
      closes_at: closesAt.value?.toISOString() ?? null,
      use_auto_schedule: form.value.useAutoSchedule,
    }

    if (editingRound.value) {
      const updatePayload = { ...payload }
      if (editingRound.value.status === 'open') {
        delete (updatePayload as { opens_at?: string | null }).opens_at
      }
      await updateGrossanlassPlanningRound(departmentId.value, editingRound.value.id, updatePayload)
      toast.success(t('grossanlass.planung.rounds.saved'))
    } else {
      await createGrossanlassPlanningRound(departmentId.value, payload)
      toast.success(t('grossanlass.planung.rounds.created'))
    }
    showModal.value = false
    await loadRounds()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.planung.rounds.errorSave'))
  } finally {
    isSaving.value = false
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
  try {
    await closeGrossanlassPlanningRound(departmentId.value, round.id)
    toast.success(t('grossanlass.planung.rounds.closed', { name: round.name }))
    await loadRounds()
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.planung.rounds.errorClose'))
  }
}

function openRound(round: GrossanlassPlanningRound) {
  void router.push(`/${departmentId.value}/planung/runden/${round.id}`)
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
  width: 120px;
}

.col-auto {
  width: 64px;
  text-align: center;
}

.col-status {
  width: 120px;
}

.round-single-time :deep(.activity-datetime-mobile__time-slot:last-child),
.round-single-time :deep(.activity-pill-cell--time:last-child) {
  display: none;
}
</style>

<style>
@import '@/styles/components/activity-datetime-field.css';
@import '@/styles/components/activity-datetime-layout.css';
</style>
