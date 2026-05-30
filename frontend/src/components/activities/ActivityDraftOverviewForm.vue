<template>
  <div class="activity-draft-overview-form activity-create-wizard-host activity-detail-datetime-host">
    <div class="section-card">
      <h2 class="section-title">{{ t('activities.draftOverview.sectionBasics') }}</h2>
      <div class="form-grid">
        <div class="form-group">
          <label>{{ t('activities.detail.labelDepartment') }}</label>
          <p class="activity-readonly-inline">{{ activity.department_name ?? t('activities.wizard.form.summaryEmpty') }}</p>
        </div>
        <div v-if="activity.total_price != null" class="form-group">
          <label>{{ t('activities.draftOverview.totalPriceCurrent') }}</label>
          <p class="activity-readonly-inline">CHF {{ Number(activity.total_price).toFixed(2) }}</p>
        </div>
        <div class="form-group span-2">
          <label for="draft-activity-name">{{ t('common.name') }}</label>
          <input
            id="draft-activity-name"
            v-model="form.name"
            type="text"
            class="form-input"
            autocomplete="off"
            :placeholder="t('activities.draftOverview.namePlaceholder')"
          />
        </div>
        <div v-if="showGroup" class="form-group span-2">
          <label for="draft-activity-group">
            {{ t('common.group') }}
            <span v-if="groupRequired" class="req">*</span>
            <span v-else-if="activityType === 'event'" class="text-muted">{{ t('activities.wizard.form.groupOptional') }}</span>
          </label>
          <select id="draft-activity-group" v-model="groupField" class="form-input activity-group-select">
            <option v-if="activityType === 'camp' || activityType === 'event'" value="">
              {{ activity.department_name || t('activities.wizard.form.summaryEmpty') }}
            </option>
            <option v-else value="" disabled>{{ t('activities.wizard.form.groupChoose') }}</option>
            <option v-for="g in flatGroups" :key="g.id" :value="g.id">
              {{ '↳ '.repeat(g._level) }}{{ g.name }}
            </option>
          </select>
        </div>
        <div v-if="showVenue" class="form-group span-2">
          <label for="draft-venue-address">{{ t('activities.wizard.form.venueLabel') }}</label>
          <select id="draft-venue-address" v-model="form.venue_address_id" class="form-input">
            <option :value="null">{{ t('activities.draftOverview.selectNone') }}</option>
            <option v-for="a in addresses" :key="a.id" :value="a.id">
              {{ addressShort(a) }}
            </option>
          </select>
        </div>
        <div v-if="showCustomerAddress" class="form-group span-2">
          <label for="draft-customer-address">{{ t('activities.draftOverview.customerTenantAddress') }}</label>
          <select id="draft-customer-address" v-model="form.address_id" class="form-input">
            <option :value="null">{{ t('activities.draftOverview.selectNone') }}</option>
            <option v-for="a in addresses" :key="a.id" :value="a.id">
              {{ addressShort(a) }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <div class="section-card">
      <h2 class="section-title">{{ t('activities.detail.sectionPeriod') }}</h2>
      <p class="field-hint text-muted draft-time-hint">
        {{ t('activities.draftOverview.periodHint', { materialTab: t('common.material') }) }}
      </p>
      <p v-if="usageDatesLocked" class="field-hint activity-draft-usage-locked-hint" role="status">
        <strong>{{ t('activities.wizard.form.datesLockedTitle') }}</strong> {{ t('activities.draftOverview.datesLockedBodyDraft') }}
      </p>
      <p v-if="planningUsageConflictMessage" class="activity-planning-usage-warn" role="alert">
        {{ planningUsageConflictMessage }}
      </p>

      <ActivityZeitraumDatetimeFields
        v-model:usage-day="usageDay"
        v-model:usage-range="usageRange"
        v-model:usage-time-from="usageTimeFrom"
        v-model:usage-time-to="usageTimeTo"
        v-model:mat-range="matRange"
        v-model:mat-start-time="matStartTime"
        v-model:mat-end-time="matEndTime"
        :activity-type="activityTypeForZeitraum"
        :department-id="departmentId"
        teleport-to="body"
        :show-date-range-preset-sidebar="showDateRangePresetSidebar"
        :usage-dates-locked="usageDatesLocked"
        :material-times-blocked-usage="materialTimesBlockedUsage"
        usage-block-id="draft-usage-block"
        planning-block-id="draft-planning-block"
      />
    </div>

    <div class="section-card">
      <h2 class="section-title">{{ t('activities.detail.sectionNotes') }}</h2>
      <div class="form-group">
        <label for="draft-notes" class="sr-only">{{ t('activities.detail.sectionNotes') }}</label>
        <textarea
          id="draft-notes"
          v-model="form.notes"
          class="form-input"
          rows="4"
          :placeholder="t('activities.wizard.form.notesPlaceholder')"
        />
      </div>
    </div>

    <div v-if="showInviteDepartments" class="section-card">
      <h2 class="section-title">{{ t('activities.wizard.form.inviteDepartmentsLabel') }}</h2>
      <p class="field-hint text-muted">
        {{ t('activities.wizard.form.inviteDepartmentsHint') }}
      </p>
      <div class="form-group activity-invite-departments-wrap">
        <label for="draft-activity-invite-dept-search">{{ t('activities.draftOverview.inviteSearchLabel') }}</label>
        <div class="activity-address-select-row">
          <div class="autocomplete-wrapper activity-address-autocomplete">
            <input
              id="draft-activity-invite-dept-search"
              v-model="inviteDeptSearch"
              type="text"
              class="form-input"
              :placeholder="t('activities.wizard.form.inviteDeptPlaceholder')"
              autocomplete="off"
              @focus="showInviteDeptDropdown = true"
              @blur="hideInviteDeptDropdownDelayed"
            />
            <div
              v-if="showInviteDeptDropdown && inviteDeptResults.length > 0"
              class="autocomplete-dropdown activity-address-autocomplete-dropdown"
            >
              <div
                v-for="d in inviteDeptResults"
                :key="d.id"
                class="autocomplete-item activity-address-ac-item"
                @mousedown.prevent="addInvitedDepartment(d)"
              >
                <div class="activity-address-ac-main">
                  <span class="item-name">{{ d.name }}</span>
                </div>
                <span class="item-city">{{ d.organisation_name }}</span>
              </div>
            </div>
            <div
              v-else-if="
                showInviteDeptDropdown &&
                inviteDeptSearchTrimmed.length >= 2 &&
                !inviteDeptLoading &&
                inviteDeptResults.length === 0
              "
              class="autocomplete-dropdown activity-address-autocomplete-dropdown"
            >
              <div class="autocomplete-item autocomplete-empty">
                <span class="item-name">{{ t('activities.empty.noMatch') }}</span>
              </div>
            </div>
            <div
              v-else-if="showInviteDeptDropdown && inviteDeptLoading"
              class="autocomplete-dropdown activity-address-autocomplete-dropdown"
            >
              <div class="autocomplete-item autocomplete-empty">
                <span class="item-name">{{ t('activities.wizard.form.inviteSearching') }}</span>
              </div>
            </div>
          </div>
        </div>
        <p v-if="inviteDeptSearchTrimmed.length > 0 && inviteDeptSearchTrimmed.length < 2" class="field-hint text-muted invite-dept-min-hint">
          {{ t('activities.wizard.form.inviteMinChars') }}
        </p>
        <ul v-if="invitedDraft.length > 0" class="activity-invited-dept-chips" :aria-label="t('activities.wizard.form.invitedDepartmentsAria')">
          <li v-for="row in invitedDraft" :key="row.id" class="activity-invited-dept-chip">
            <span class="activity-invited-dept-chip-label">{{ row.name }}</span>
            <span v-if="row.organisation_name" class="activity-invited-dept-chip-org">{{ row.organisation_name }}</span>
            <span class="invite-status-chip" :class="inviteStatusClass(row.status)">{{ inviteStatusLabel(row.status) }}</span>
            <button
              type="button"
              class="activity-invited-dept-chip-remove"
              :title="t('activities.wizard.form.removeInviteTitle', { name: row.name })"
              @click="removeInvitedDepartment(row.id)"
            >
              ×
            </button>
          </li>
        </ul>
      </div>
    </div>

    <div class="draft-form-actions">
      <button
        type="button"
        class="btn-primary"
        :disabled="saving || !hasChanges || !isValid"
        @click="onSave"
      >
        {{ saving ? t('activities.draftOverview.saveSaving') : t('common.save') }}
      </button>
      <button type="button" class="btn-outline" :disabled="saving || !hasChanges" @click="resetFromActivity">
        {{ t('activities.draftOverview.reset') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import '@/styles/activity-create-wizard.css'
import {
  patchActivity,
  type ActivityApiType,
  type ActivityDetail,
  type InvitedDepartmentPayloadRow,
  type PatchActivityPayload,
} from '@/api/activities'
import { searchJoinableDepartments, type DepartmentSearchResult } from '@/api/joinRequests'
import type { InvitedDepartmentDraft } from '@/composables/useActivityCreateWizard'
import { getAddresses, type Address } from '@/api/addresses'
import { getGroups, type Group } from '@/api/groups'
import { useToast } from '@/composables/useToast'
import { combineDayAndTime, startOfLocalDay } from '@/utils/activityDateTimeParts'
import { getPlanningUsageViolation } from '@/utils/activityPlanningUsageConstraint'
import { flattenGroupsWithLevel } from '@/utils/groupHierarchy'
import ActivityZeitraumDatetimeFields from '@/components/activities/shared/ActivityZeitraumDatetimeFields.vue'

const props = withDefaults(
  defineProps<{
    activity: ActivityDetail
    departmentId: string
    /** Wie Erstell-Wizard: Nutzung sperren, sobald Materialpositionen existieren */
    usageDatesLocked?: boolean
  }>(),
  {
    usageDatesLocked: false,
  },
)

const emit = defineEmits<{
  saved: []
}>()

const toast = useToast()
const { t } = useI18n()
const groups = ref<Group[]>([])
const addresses = ref<Address[]>([])
const saving = ref(false)

/** Lager / Event / extern: wie Erstell-Wizard */
const showInviteDepartments = computed(() =>
  ['camp', 'event', 'external'].includes(activityType.value),
)

const isSubmittedActivityEdit = computed(() => props.activity.status !== 'draft')

const invitedDraft = ref<InvitedDepartmentDraft[]>([])

const inviteDeptSearch = ref('')
const inviteDeptRawResults = ref<DepartmentSearchResult[]>([])
const inviteDeptLoading = ref(false)
const showInviteDeptDropdown = ref(false)
let inviteDeptSearchTimer: ReturnType<typeof setTimeout> | null = null

const inviteDeptSearchTrimmed = computed(() => inviteDeptSearch.value.trim())

const inviteDeptResults = computed(() => {
  const own = props.departmentId
  const taken = new Set(invitedDraft.value.map((d) => d.id))
  return inviteDeptRawResults.value.filter((d) => d.id !== own && !taken.has(d.id))
})

watch(inviteDeptSearch, (value) => {
  if (inviteDeptSearchTimer) clearTimeout(inviteDeptSearchTimer)
  const q = value.trim()
  if (q.length < 2) {
    inviteDeptRawResults.value = []
    inviteDeptLoading.value = false
    return
  }
  inviteDeptSearchTimer = setTimeout(async () => {
    inviteDeptLoading.value = true
    try {
      inviteDeptRawResults.value = await searchJoinableDepartments(q)
    } catch {
      inviteDeptRawResults.value = []
    } finally {
      inviteDeptLoading.value = false
    }
  }, 250)
})

onUnmounted(() => {
  if (inviteDeptSearchTimer) clearTimeout(inviteDeptSearchTimer)
})

function hideInviteDeptDropdownDelayed() {
  window.setTimeout(() => {
    showInviteDeptDropdown.value = false
  }, 200)
}

function inviteStatusLabel(status?: string): string {
  if (status === 'accepted') return t('activities.detail.inviteAccepted')
  if (status === 'rejected') return t('activities.detail.inviteRejected')
  return t('activities.detail.invitePending')
}

function inviteStatusClass(status?: string): string {
  if (status === 'accepted') return 'accepted'
  if (status === 'rejected') return 'rejected'
  return 'pending'
}

function mapApiInvitesToDraft(rows: ActivityDetail['invited_departments'] | undefined): InvitedDepartmentDraft[] {
  if (!rows?.length) return []
  return rows.map((r) => {
    const st = typeof r.status === 'string' ? r.status : 'pending'
    const status: InvitedDepartmentDraft['status'] =
      st === 'accepted' || st === 'rejected' ? st : 'pending'
    return {
      id: r.id,
      name: (typeof r.name === 'string' && r.name.trim() ? r.name : r.id).trim() || r.id,
      organisation_name: typeof r.organisation_name === 'string' ? r.organisation_name : '',
      group_id: r.group_id ?? null,
      group_name: r.group_name ?? null,
      status,
    }
  })
}

function invitesSnapshot(rows: { id: string; name?: string; organisation_name?: string; group_id?: string | null }[]): string {
  return JSON.stringify(
    [...rows]
      .map((r) => ({
        id: r.id,
        name: (r.name || '').trim(),
        organisation_name: (r.organisation_name || '').trim(),
        group_id: r.group_id ?? null,
      }))
      .sort((a, b) => a.id.localeCompare(b.id)),
  )
}

function addInvitedDepartment(d: DepartmentSearchResult) {
  if (d.id === props.departmentId) return
  if (invitedDraft.value.some((x) => x.id === d.id)) return
  invitedDraft.value = [
    ...invitedDraft.value,
    {
      id: d.id,
      name: d.name,
      organisation_name: d.organisation_name || '',
      group_id: d.group_id ?? null,
      group_name: d.group_name ?? null,
      status: 'pending',
    },
  ]
  inviteDeptSearch.value = ''
  inviteDeptRawResults.value = []
  showInviteDeptDropdown.value = false
}

function removeInvitedDepartment(id: string) {
  invitedDraft.value = invitedDraft.value.filter((x) => x.id !== id)
}

function toInvitePayload(rows: InvitedDepartmentDraft[]): InvitedDepartmentPayloadRow[] {
  return rows.map((r) => ({
    id: r.id,
    name: r.name.trim() || r.id,
    organisation_name: r.organisation_name?.trim() || undefined,
    group_id: r.group_id ?? undefined,
  }))
}

const form = ref({
  name: '',
  group_id: null as string | null,
  venue_address_id: null as string | null,
  address_id: null as string | null,
  notes: '' as string | null,
})

/** Typ „Aktivität“: ein Kalendertag für Nutzung */
const usageDay = ref<Date | null>(null)
/** Lager/Event/Extern: Nutzungsbereich */
const usageRange = ref<[Date, Date] | null>(null)
const usageTimeFrom = ref<Date | null>(null)
const usageTimeTo = ref<Date | null>(null)

const matRange = ref<[Date, Date] | null>(null)
const matStartTime = ref<Date | null>(null)
const matEndTime = ref<Date | null>(null)

const flatGroups = computed(() => flattenGroupsWithLevel(groups.value))

const activityType = computed(() => (props.activity.type || 'activity') as string)

const activityTypeForZeitraum = computed((): ActivityApiType => (props.activity.type || 'activity') as ActivityApiType)

const isActivityType = computed(() => activityType.value === 'activity')

const showGroup = computed(() => {
  const typ = activityType.value
  if (typ === 'camp' || typ === 'event') return true
  return typ === 'activity' && groups.value.length > 0
})

const groupRequired = computed(() => activityType.value === 'activity' && groups.value.length > 0)

const showVenue = computed(() => ['camp', 'event', 'external'].includes(activityType.value))

const showCustomerAddress = computed(() => activityType.value === 'external')

const showDateRangePresetSidebar = computed(
  () => activityType.value !== 'activity' && activityType.value !== 'external',
)

const groupField = computed({
  get() {
    return form.value.group_id ?? ''
  },
  set(v: string) {
    form.value.group_id = v === '' ? null : v
  },
})

/** Ohne Uhrzeit: ActivityTimeField bleibt leer/fehlerhaft — Vorbelegung, sobald ein Datum gewählt ist */
watch(usageDay, (day) => {
  if (!day || !isActivityType.value) return
  if (!usageTimeFrom.value) usageTimeFrom.value = combineDayAndTime(day, new Date(2000, 0, 1, 9, 0, 0, 0))
  if (!usageTimeTo.value) usageTimeTo.value = combineDayAndTime(day, new Date(2000, 0, 1, 17, 0, 0, 0))
})

watch(usageRange, (range) => {
  if (!range?.[0] || !range[1] || isActivityType.value) return
  if (!usageTimeFrom.value) usageTimeFrom.value = combineDayAndTime(range[0], new Date(2000, 0, 1, 9, 0, 0, 0))
  if (!usageTimeTo.value) usageTimeTo.value = combineDayAndTime(range[1], new Date(2000, 0, 1, 17, 0, 0, 0))
})

watch(matRange, (range) => {
  if (!range?.[0] || !range[1]) return
  if (!matStartTime.value) matStartTime.value = combineDayAndTime(range[0], new Date(2000, 0, 1, 8, 0, 0, 0))
  if (!matEndTime.value) matEndTime.value = combineDayAndTime(range[1], new Date(2000, 0, 1, 18, 0, 0, 0))
})

function parseIso(iso?: string | null): Date | null {
  if (!iso) return null
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? null : d
}

function normIso(s: string | null | undefined): string | null {
  if (!s) return null
  const d = new Date(s)
  return Number.isNaN(d.getTime()) ? null : d.toISOString()
}

function buildUsageIsos(): { usage_start: string | null; usage_end: string | null } {
  if (isActivityType.value) {
    if (!usageDay.value || !usageTimeFrom.value || !usageTimeTo.value) {
      return { usage_start: null, usage_end: null }
    }
    return {
      usage_start: combineDayAndTime(usageDay.value, usageTimeFrom.value).toISOString(),
      usage_end: combineDayAndTime(usageDay.value, usageTimeTo.value).toISOString(),
    }
  }
  if (!usageRange.value?.[0] || !usageRange.value[1] || !usageTimeFrom.value || !usageTimeTo.value) {
    return { usage_start: null, usage_end: null }
  }
  return {
    usage_start: combineDayAndTime(usageRange.value[0], usageTimeFrom.value).toISOString(),
    usage_end: combineDayAndTime(usageRange.value[1], usageTimeTo.value).toISOString(),
  }
}

function buildPlanningIsos(): { planning_start: string | null; planning_end: string | null } {
  if (!matRange.value?.[0] || !matRange.value[1] || !matStartTime.value || !matEndTime.value) {
    return { planning_start: null, planning_end: null }
  }
  return {
    planning_start: combineDayAndTime(matRange.value[0], matStartTime.value).toISOString(),
    planning_end: combineDayAndTime(matRange.value[1], matEndTime.value).toISOString(),
  }
}

const planningUsageConflictMessage = computed(() => {
  const u = buildUsageIsos()
  const p = buildPlanningIsos()
  if (!u.usage_start || !u.usage_end || !p.planning_start || !p.planning_end) return null
  const us = new Date(u.usage_start)
  const ue = new Date(u.usage_end)
  if (ue.getTime() < us.getTime()) return null
  const ps = new Date(p.planning_start)
  const pe = new Date(p.planning_end)
  const v = getPlanningUsageViolation(ps, pe, us, ue)
  if (v.pickup && v.return) return t('activities.wizard.form.planningViolationBoth')
  if (v.pickup) return t('activities.wizard.form.planningViolationPickup')
  if (v.return) return t('activities.wizard.form.planningViolationReturn')
  return null
})

const materialTimesBlockedUsage = computed((): { start: Date; end: Date } | null => {
  const u = buildUsageIsos()
  if (!u.usage_start || !u.usage_end) return null
  const us = new Date(u.usage_start)
  const ue = new Date(u.usage_end)
  if (ue.getTime() < us.getTime()) return null
  return { start: us, end: ue }
})

function resetFromActivity() {
  const a = props.activity
  form.value = {
    name: a.name ?? '',
    group_id: a.group_id ?? null,
    venue_address_id: a.venue_address_id ?? null,
    address_id: a.address_id ?? null,
    notes: a.notes ?? '',
  }

  const us = parseIso(a.usage_start)
  const ue = parseIso(a.usage_end)
  const ps = parseIso(a.planning_start)
  const pe = parseIso(a.planning_end)

  if (isActivityType.value) {
    usageDay.value = us ? startOfLocalDay(us) : null
    usageRange.value = null
    usageTimeFrom.value = us
    usageTimeTo.value = ue
  } else {
    usageDay.value = null
    usageRange.value = us && ue ? [startOfLocalDay(us), startOfLocalDay(ue)] : null
    usageTimeFrom.value = us
    usageTimeTo.value = ue
  }

  matRange.value = ps && pe ? [startOfLocalDay(ps), startOfLocalDay(pe)] : null
  matStartTime.value = ps
  matEndTime.value = pe

  invitedDraft.value = mapApiInvitesToDraft(a.invited_departments)
}

/** Live-Refresh von aussen: nur wenn lokal nichts Ungespeichertes (verhindert Überschreiben beim Tippen). */
watch(
  () => props.activity.updated_at,
  () => {
    if (hasChanges.value || saving.value) return
    resetFromActivity()
  },
)

const hasChanges = computed(() => {
  const a = props.activity
  const f = form.value
  const u = buildUsageIsos()
  const p = buildPlanningIsos()
  return (
    f.name !== (a.name ?? '') ||
    (f.group_id ?? null) !== (a.group_id ?? null) ||
    (f.venue_address_id ?? null) !== (a.venue_address_id ?? null) ||
    (f.address_id ?? null) !== (a.address_id ?? null) ||
    normIso(u.usage_start) !== normIso(a.usage_start ?? undefined) ||
    normIso(u.usage_end) !== normIso(a.usage_end ?? undefined) ||
    normIso(p.planning_start) !== normIso(a.planning_start ?? undefined) ||
    normIso(p.planning_end) !== normIso(a.planning_end ?? undefined) ||
    (f.notes ?? '') !== (a.notes ?? '') ||
    (showInviteDepartments.value &&
      invitesSnapshot(invitedDraft.value) !== invitesSnapshot(a.invited_departments ?? []))
  )
})

const isValid = computed(() => {
  if (showGroup.value && groupRequired.value && !form.value.group_id) return false
  if (planningUsageConflictMessage.value) return false
  return true
})

function addressShort(a: Address): string {
  const line = a.full_address || a.street_line || a.name || a.id
  const typeSuffix = a.type_label ? ` · ${a.type_label}` : ''
  return `${line}${typeSuffix}`
}

function buildPayload(): PatchActivityPayload {
  const a = props.activity
  const f = form.value
  const p: PatchActivityPayload = {}
  if (f.name !== (a.name ?? '')) p.name = f.name
  if ((f.group_id ?? null) !== (a.group_id ?? null)) p.group_id = f.group_id
  if ((f.venue_address_id ?? null) !== (a.venue_address_id ?? null)) p.venue_address_id = f.venue_address_id
  if ((f.address_id ?? null) !== (a.address_id ?? null)) p.address_id = f.address_id
  if ((f.notes ?? '') !== (a.notes ?? '')) p.notes = f.notes || null

  const u = buildUsageIsos()
  const pl = buildPlanningIsos()
  if (normIso(u.usage_start) !== normIso(a.usage_start ?? undefined)) p.usage_start = u.usage_start ?? undefined
  if (normIso(u.usage_end) !== normIso(a.usage_end ?? undefined)) p.usage_end = u.usage_end ?? undefined
  if (normIso(pl.planning_start) !== normIso(a.planning_start ?? undefined)) {
    p.planning_start = pl.planning_start ?? undefined
  }
  if (normIso(pl.planning_end) !== normIso(a.planning_end ?? undefined)) {
    p.planning_end = pl.planning_end ?? undefined
  }
  if (
    showInviteDepartments.value &&
    invitesSnapshot(invitedDraft.value) !== invitesSnapshot(a.invited_departments ?? [])
  ) {
    p.invited_departments = toInvitePayload(invitedDraft.value)
  }
  return p
}

async function onSave() {
  const conflict = planningUsageConflictMessage.value
  if (conflict) {
    toast.error(conflict)
    return
  }
  const payload = buildPayload()
  if (Object.keys(payload).length === 0) return
  saving.value = true
  try {
    await patchActivity(props.activity.id, payload)
    toast.success(
      isSubmittedActivityEdit.value ? t('activities.draftOverview.toastSavedSubmitted') : t('activities.draftOverview.toastSavedDraft'),
    )
    emit('saved')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.draftOverview.toastSaveFailed'))
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const [g, addrRes] = await Promise.all([getGroups(props.departmentId), getAddresses(props.departmentId)])
    groups.value = g
    addresses.value = addrRes.addresses
  } catch {
    toast.error(t('activities.draftOverview.toastLoadMetaFailed'))
  }
  resetFromActivity()
})

defineExpose({
  hasUnsavedChanges: hasChanges,
  isSaving: saving,
})
</script>

<style scoped>
.activity-draft-overview-form {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.activity-detail-datetime-host {
  /* System-Akzent statt fester Markenfarbe (Fokus, wie vom OS vorgegeben) */
  --emc-brand-accent: AccentColor;
}

.draft-time-hint {
  margin: 0 0 16px;
  font-size: 13px;
  line-height: 1.45;
}

.activity-draft-usage-locked-hint {
  margin: 0 0 14px;
  font-size: 13px;
  line-height: 1.45;
  color: #374151;
}

.activity-planning-usage-warn {
  margin: 0 0 12px;
  font-size: 13px;
  line-height: 1.45;
  color: #b91c1c;
  font-weight: 500;
}

.draft-form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 8px;
  padding-top: 8px;
}

.req {
  color: #b91c1c;
}

.text-muted {
  color: #6b7280;
  font-weight: 400;
}

.activity-readonly-inline {
  margin: 0;
  font-size: 15px;
  color: #111827;
  line-height: 1.5;
}

.activity-invite-list {
  list-style: none;
  margin: 0 0 12px;
  padding: 0;
}

.activity-invite-row {
  padding: 6px 0;
  border-bottom: 1px solid #f3f4f6;
  font-size: 14px;
}

.activity-invite-name {
  font-weight: 500;
}

.invite-status-chip {
  margin-left: 4px;
  font-size: 11px;
  font-weight: 600;
  flex-shrink: 0;
}

.invite-status-chip.accepted {
  color: #059669;
}

.invite-status-chip.rejected {
  color: #b91c1c;
}

.invite-status-chip.pending {
  color: #6b7280;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}

/* Fokus-Hintergrund in der Pill-Leiste an Systemfarbe anbinden */
.activity-detail-datetime-host :deep(.activity-pill-bar .activity-date-field .dp__input_wrap:focus-within),
.activity-detail-datetime-host :deep(.activity-pill-bar .activity-date-range-field .dp__input_wrap:focus-within) {
  background: color-mix(in srgb, AccentColor 14%, transparent);
}

.activity-detail-datetime-host :deep(.activity-time-part:focus) {
  border-color: AccentColor;
  box-shadow: 0 0 0 2px color-mix(in srgb, AccentColor 35%, transparent);
}
</style>
