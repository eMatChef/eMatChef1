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
        <AutoSaveField
          v-model="form.name"
          :baseline="savedBaselines.name"
          :label="t('common.name')"
          span-class="form-group span-2 activity-compact-autosave-field"
          :placeholder="t('activities.draftOverview.namePlaceholder')"
          :save="saveName"
          @saved="onAutoFieldSaved"
        />
        <AutoSaveField
          v-if="showGroup"
          v-model="groupField"
          :baseline="savedBaselines.group_id"
          :label="groupFieldLabel"
          type="select"
          span-class="form-group span-2 activity-compact-autosave-field"
          :options="groupSelectOptions"
          :save="saveGroupId"
          @saved="onAutoFieldSaved"
        />
        <div v-if="showVenue" class="form-group span-2 activity-external-address-wrap activity-venue-field-wrap">
          <p class="field-hint text-muted activity-venue-field-hint">
            {{ t('activities.wizard.form.venueHint') }}
          </p>
          <AutoSaveField
            v-model="venueField"
            :baseline="savedBaselines.venue_address_id"
            :label="t('activities.wizard.form.venueLabel')"
            span-class="activity-compact-autosave-field activity-venue-autosave-field"
            :save="saveVenueAddressId"
            @saved="onAutoFieldSaved"
          >
            <template #default="{ inputId, onChange }">
              <DepartmentAddressAutocomplete
                ref="venueAddressAutocompleteRef"
                :input-id="inputId"
                :addresses="addresses"
                :selected-id="form.venue_address_id"
                primary-type="event"
                :placeholder="t('activities.wizard.form.addressSearchPlaceholder')"
                :add-button-title="t('activities.wizard.form.addVenueAddressTitle')"
                :empty-addresses-label="t('activities.wizard.form.noAddressesWithAdd')"
                inline-create-label-key="addresses.search.createEventVenueInline"
                @update:selected-id="(id) => onVenueAddressId(id, onChange)"
                @create="openAddVenueAddressModal"
              />
            </template>
          </AutoSaveField>
        </div>
        <AutoSaveField
          v-if="showCustomerAddress"
          v-model="addressField"
          :baseline="savedBaselines.address_id"
          :label="t('activities.draftOverview.customerTenantAddress')"
          type="select"
          span-class="form-group span-2 activity-compact-autosave-field"
          :options="addressSelectOptions"
          :save="saveAddressId"
          @saved="onAutoFieldSaved"
        />
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

      <div
        class="activity-zeitraum-autosave-wrap"
        :class="{ 'is-zeitraum-saving': zeitraumSaving, 'is-zeitraum-saved': zeitraumShowSaved }"
        @focusout="onZeitraumFocusOut"
      >
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
        <p v-if="zeitraumSaving" class="activity-zeitraum-autosave-status" role="status">
          {{ t('common.autoSaveField.saving') }}
        </p>
        <p v-else-if="zeitraumShowSaved" class="activity-zeitraum-autosave-status is-saved" role="status">
          {{ t('common.autoSaveField.saved') }}
        </p>
      </div>
    </div>

    <div class="section-card">
      <AutoSaveField
        v-model="form.notes"
        :baseline="savedBaselines.notes"
        :label="t('activities.detail.sectionNotes')"
        type="textarea"
        span-class="form-group span-2 activity-compact-autosave-field"
        :rows="4"
        :placeholder="t('activities.wizard.form.notesPlaceholder')"
        :save="saveNotes"
        @saved="onAutoFieldSaved"
      />
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

    <div v-if="hasManualUnsavedChanges" class="draft-form-actions">
      <button
        type="button"
        class="btn-primary"
        :disabled="saving || !hasManualUnsavedChanges"
        @click="onSaveInvites"
      >
        {{ saving ? t('activities.draftOverview.saveSaving') : t('common.save') }}
      </button>
      <button type="button" class="btn-outline" :disabled="saving || !hasManualUnsavedChanges" @click="resetInvites">
        {{ t('activities.draftOverview.reset') }}
      </button>
    </div>

    <AddressModal
      v-if="showVenueAddressModal"
      :department-id="departmentId"
      default-type="event"
      :default-name="venueAddressModalDefaultName"
      @close="closeVenueAddressModal"
      @saved="onVenueAddressModalSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
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
import AutoSaveField from '@/components/common/autoSave/AutoSaveField.vue'
import type { AutoSaveFieldValue, AutoSaveSelectOption } from '@/components/common/autoSave/types'
import { DepartmentAddressAutocomplete } from '@/components/addresses'
import AddressModal from '@/components/AddressModal.vue'
import { formatAddressOption } from '@/utils/departmentAddressSearch'

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
const showVenueAddressModal = ref(false)
const venueAddressModalDefaultName = ref('')
const venueAddressAutocompleteRef = ref<InstanceType<typeof DepartmentAddressAutocomplete> | null>(null)
const zeitraumSaving = ref(false)
const zeitraumShowSaved = ref(false)
let zeitraumBlurTimer: ReturnType<typeof setTimeout> | null = null
let zeitraumSavedTimer: ReturnType<typeof setTimeout> | null = null

const savedBaselines = reactive({
  name: '',
  group_id: '',
  venue_address_id: '',
  address_id: '',
  notes: '',
})

const zeitraumBaseline = ref({
  usage_start: null as string | null,
  usage_end: null as string | null,
  planning_start: null as string | null,
  planning_end: null as string | null,
})

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
  if (zeitraumBlurTimer) clearTimeout(zeitraumBlurTimer)
  if (zeitraumSavedTimer) clearTimeout(zeitraumSavedTimer)
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

function addressShort(a: Address): string {
  const line = a.full_address || a.street_line || a.name || a.id
  const typeSuffix = a.type_label ? ` · ${a.type_label}` : ''
  return `${line}${typeSuffix}`
}

const groupField = computed({
  get() {
    return form.value.group_id ?? ''
  },
  set(v: string) {
    form.value.group_id = v === '' ? null : v
  },
})

const venueField = computed({
  get() {
    return form.value.venue_address_id ?? ''
  },
  set(v: string) {
    form.value.venue_address_id = v === '' ? null : v
  },
})

const addressField = computed({
  get() {
    return form.value.address_id ?? ''
  },
  set(v: string) {
    form.value.address_id = v === '' ? null : v
  },
})

const groupFieldLabel = computed(() => {
  let label = t('common.group')
  if (groupRequired.value) label += ' *'
  else if (activityType.value === 'event') label += ` (${t('activities.wizard.form.groupOptional')})`
  return label
})

const groupSelectOptions = computed((): AutoSaveSelectOption[] => {
  const opts: AutoSaveSelectOption[] = []
  if (activityType.value === 'camp' || activityType.value === 'event') {
    opts.push({
      value: '',
      label: props.activity.department_name || t('activities.wizard.form.summaryEmpty'),
    })
  } else {
    opts.push({ value: '', label: t('activities.wizard.form.groupChoose') })
  }
  for (const g of flatGroups.value) {
    opts.push({ value: g.id, label: `${'↳ '.repeat(g._level)}${g.name}` })
  }
  return opts
})

const addressSelectOptions = computed((): AutoSaveSelectOption[] => {
  const opts: AutoSaveSelectOption[] = [{ value: '', label: t('activities.draftOverview.selectNone') }]
  for (const a of addresses.value) {
    opts.push({ value: a.id, label: addressShort(a) })
  }
  return opts
})

function onVenueAddressId(id: string | null, onChange: () => void) {
  form.value.venue_address_id = id
  onChange()
}

function openAddVenueAddressModal(presetName = '') {
  venueAddressModalDefaultName.value = presetName.trim()
  showVenueAddressModal.value = true
}

function closeVenueAddressModal() {
  showVenueAddressModal.value = false
  venueAddressModalDefaultName.value = ''
}

async function reloadAddresses() {
  try {
    const { addresses: list } = await getAddresses(props.departmentId)
    addresses.value = [...list].sort((a, b) =>
      formatAddressOption(a).localeCompare(formatAddressOption(b), 'de'),
    )
  } catch {
    addresses.value = []
  }
}

async function onVenueAddressModalSaved(addr?: Address) {
  closeVenueAddressModal()
  await reloadAddresses()
  if (addr?.id) {
    form.value.venue_address_id = addr.id
    await saveVenueAddressId(addr.id)
    onAutoFieldSaved()
  }
}

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

function syncSavedBaselinesFromActivity() {
  const a = props.activity
  savedBaselines.name = a.name ?? ''
  savedBaselines.group_id = a.group_id ?? ''
  savedBaselines.venue_address_id = a.venue_address_id ?? ''
  savedBaselines.address_id = a.address_id ?? ''
  savedBaselines.notes = a.notes ?? ''
}

function syncZeitraumBaselineFromActivity() {
  const a = props.activity
  zeitraumBaseline.value = {
    usage_start: normIso(a.usage_start),
    usage_end: normIso(a.usage_end),
    planning_start: normIso(a.planning_start),
    planning_end: normIso(a.planning_end),
  }
}

function syncZeitraumBaselineFromLocal() {
  const usage = buildUsageIsos()
  const planning = buildPlanningIsos()
  zeitraumBaseline.value = {
    usage_start: normIso(usage.usage_start),
    usage_end: normIso(usage.usage_end),
    planning_start: normIso(planning.planning_start),
    planning_end: normIso(planning.planning_end),
  }
}

function resetFromActivity() {
  const a = props.activity
  form.value = {
    name: a.name ?? '',
    group_id: a.group_id ?? null,
    venue_address_id: a.venue_address_id ?? null,
    address_id: a.address_id ?? null,
    notes: a.notes ?? '',
  }
  syncSavedBaselinesFromActivity()

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

  syncZeitraumBaselineFromActivity()
  invitedDraft.value = mapApiInvitesToDraft(a.invited_departments)
}

function resetInvites() {
  invitedDraft.value = mapApiInvitesToDraft(props.activity.invited_departments)
}

/** Live-Refresh von aussen: nur wenn lokal nichts Ungespeichertes (verhindert Überschreiben beim Tippen). */
watch(
  () => props.activity.updated_at,
  () => {
    if (shouldSkipActivitySync.value) return
    resetFromActivity()
  },
)

const hasManualUnsavedChanges = computed(() => {
  if (!showInviteDepartments.value) return false
  return (
    invitesSnapshot(invitedDraft.value) !==
    invitesSnapshot(props.activity.invited_departments ?? [])
  )
})

const hasPendingAutoSaveFields = computed(() => {
  const f = form.value
  return (
    f.name !== savedBaselines.name ||
    (f.group_id ?? '') !== savedBaselines.group_id ||
    (f.venue_address_id ?? '') !== savedBaselines.venue_address_id ||
    (f.address_id ?? '') !== savedBaselines.address_id ||
    (f.notes ?? '') !== savedBaselines.notes
  )
})

const zeitraumIsDirty = computed(() => {
  const usage = buildUsageIsos()
  const planning = buildPlanningIsos()
  const b = zeitraumBaseline.value
  return (
    normIso(usage.usage_start) !== b.usage_start ||
    normIso(usage.usage_end) !== b.usage_end ||
    normIso(planning.planning_start) !== b.planning_start ||
    normIso(planning.planning_end) !== b.planning_end
  )
})

const hasUnsavedChanges = computed(
  () => hasManualUnsavedChanges.value || zeitraumIsDirty.value || hasPendingAutoSaveFields.value,
)

const shouldSkipActivitySync = computed(
  () => hasUnsavedChanges.value || isSavingAny.value,
)

const isSavingAny = computed(() => saving.value || zeitraumSaving.value)

function isExternalDatetimePickerTarget(el: EventTarget | null): boolean {
  if (!(el instanceof HTMLElement)) return false
  return !!el.closest(
    '.activity-date-picker-menu, .activity-time-picker-menu, .v-date-picker, .v-time-picker, .v-picker',
  )
}

function onZeitraumFocusOut(event: FocusEvent) {
  if (isExternalDatetimePickerTarget(event.relatedTarget)) return
  if (zeitraumBlurTimer) clearTimeout(zeitraumBlurTimer)
  zeitraumBlurTimer = setTimeout(() => {
    zeitraumBlurTimer = null
    const active = document.activeElement
    if (active instanceof HTMLElement) {
      if (
        active.closest(
          '.activity-zeitraum-autosave-wrap, .activity-date-picker-menu, .activity-time-picker-menu, .v-date-picker, .v-time-picker, .v-picker',
        )
      )
        return
    }
    void saveZeitraumIfDirty()
  }, 150)
}

async function patchActivityFields(payload: PatchActivityPayload): Promise<void> {
  await patchActivity(props.activity.id, payload)
}

function onAutoFieldSaved() {
  emit('saved')
}

async function saveName(value: AutoSaveFieldValue) {
  const name = String(value ?? '')
  await patchActivityFields({ name })
  savedBaselines.name = name
}

async function saveGroupId(value: AutoSaveFieldValue) {
  const id = value === '' || value == null ? null : String(value)
  if (groupRequired.value && !id) {
    throw new Error(t('activities.wizard.form.groupChoose'))
  }
  await patchActivityFields({ group_id: id })
  savedBaselines.group_id = id ?? ''
  form.value.group_id = id
}

async function saveVenueAddressId(value: AutoSaveFieldValue) {
  const id = value === '' || value == null ? null : String(value)
  await patchActivityFields({ venue_address_id: id })
  savedBaselines.venue_address_id = id ?? ''
  form.value.venue_address_id = id
}

async function saveAddressId(value: AutoSaveFieldValue) {
  const id = value === '' || value == null ? null : String(value)
  await patchActivityFields({ address_id: id })
  savedBaselines.address_id = id ?? ''
  form.value.address_id = id
}

async function saveNotes(value: AutoSaveFieldValue) {
  const notes = value == null || value === '' ? null : String(value)
  await patchActivityFields({ notes })
  savedBaselines.notes = notes ?? ''
  form.value.notes = notes ?? ''
}

async function saveZeitraumIfDirty() {
  if (!zeitraumIsDirty.value) return
  const conflict = planningUsageConflictMessage.value
  if (conflict) {
    toast.error(conflict)
    return
  }

  const a = props.activity
  const usage = buildUsageIsos()
  const planning = buildPlanningIsos()
  const payload: PatchActivityPayload = {}
  if (normIso(usage.usage_start) !== normIso(a.usage_start ?? undefined)) {
    payload.usage_start = usage.usage_start ?? undefined
  }
  if (normIso(usage.usage_end) !== normIso(a.usage_end ?? undefined)) {
    payload.usage_end = usage.usage_end ?? undefined
  }
  if (normIso(planning.planning_start) !== normIso(a.planning_start ?? undefined)) {
    payload.planning_start = planning.planning_start ?? undefined
  }
  if (normIso(planning.planning_end) !== normIso(a.planning_end ?? undefined)) {
    payload.planning_end = planning.planning_end ?? undefined
  }
  if (Object.keys(payload).length === 0) return

  zeitraumSaving.value = true
  zeitraumShowSaved.value = false
  try {
    await patchActivityFields(payload)
    syncZeitraumBaselineFromLocal()
    zeitraumShowSaved.value = true
    if (zeitraumSavedTimer) clearTimeout(zeitraumSavedTimer)
    zeitraumSavedTimer = setTimeout(() => {
      zeitraumShowSaved.value = false
    }, 2000)
    emit('saved')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.draftOverview.toastSaveFailed'))
  } finally {
    zeitraumSaving.value = false
  }
}

async function onSaveInvites() {
  if (!hasManualUnsavedChanges.value) return
  saving.value = true
  try {
    await patchActivity(props.activity.id, {
      invited_departments: toInvitePayload(invitedDraft.value),
    })
    toast.success(
      isSubmittedActivityEdit.value
        ? t('activities.draftOverview.toastSavedSubmitted')
        : t('activities.draftOverview.toastSavedDraft'),
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
    addresses.value = [...addrRes.addresses].sort((a, b) =>
      formatAddressOption(a).localeCompare(formatAddressOption(b), 'de'),
    )
  } catch {
    toast.error(t('activities.draftOverview.toastLoadMetaFailed'))
  }
  resetFromActivity()
})

defineExpose({
  hasUnsavedChanges,
  isSaving: isSavingAny,
})
</script>

<style scoped>
.activity-draft-overview-form {
  display: flex;
  flex-direction: column;
  gap: 0;
  --activity-compact-field-width: min(100%, 28rem);
}

.activity-compact-autosave-field.autosave-field {
  width: var(--activity-compact-field-width);
  max-width: 28rem;
}

/* Zeitraum: volle Breite beibehalten */
.activity-zeitraum-autosave-wrap {
  width: 100%;
  max-width: none;
}

.activity-zeitraum-autosave-wrap :deep(.activity-outlined-fieldset) {
  width: 100%;
  max-width: 100%;
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
.activity-detail-datetime-host :deep(.activity-pill-bar .activity-v-date-input:focus-within) {
  background: color-mix(in srgb, AccentColor 14%, transparent);
  border-radius: 8px;
}

.activity-detail-datetime-host :deep(.activity-time-select .v-field--focused),
.activity-detail-datetime-host :deep(.activity-time-edit:focus) {
  border-color: AccentColor;
  box-shadow: 0 0 0 2px color-mix(in srgb, AccentColor 35%, transparent);
}

.activity-zeitraum-autosave-status {
  margin: 10px 0 0;
  font-size: 13px;
  line-height: 1.4;
  color: #6b7280;
}

.activity-zeitraum-autosave-status.is-saved {
  color: #059669;
}

.activity-venue-field-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0;
}

.activity-invite-departments-wrap .activity-address-select-row {
  width: var(--activity-compact-field-width);
  max-width: 28rem;
}

.activity-invite-departments-wrap .activity-address-autocomplete {
  flex: 1;
  min-width: 0;
}

.activity-venue-field-hint {
  margin: 0 0 8px;
  width: 100%;
  font-size: 13px;
  line-height: 1.45;
}

.activity-venue-autosave-field.autosave-field {
  margin-bottom: 6px;
}

.activity-venue-autosave-field :deep(.department-address-autocomplete) {
  width: 100%;
}

.activity-venue-autosave-field :deep(.department-address-autocomplete .form-input) {
  min-height: 48px;
  padding: 16px 12px 10px;
  border-radius: 8px;
}

.activity-venue-autosave-field :deep(.add-inline-btn) {
  width: 48px;
  height: 48px;
  min-height: 48px;
  flex-shrink: 0;
}
</style>
