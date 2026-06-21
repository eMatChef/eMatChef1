<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="1080"
    :title="t('grossanlass.wizard.title')"
    scrollable
    persistent
    :retain-focus="false"
    card-class="grossanlass-wizard-card"
  >
    <form id="grossanlass-wizard-form" class="grossanlass-wizard-body" @submit.prevent="handleSubmit">
      <ETextField
        id="grossanlass-name"
        v-model="formData.name"
        :label="t('grossanlass.wizard.nameLabel')"
        :placeholder="t('grossanlass.wizard.namePlaceholder')"
        hide-details="auto"
        class="mb-1"
        required
      />
      <p v-if="conflictingDepartmentName" class="field-error mb-3">
        {{ t('grossanlass.wizard.nameDuplicate', { name: conflictingDepartmentName }) }}
      </p>
      <div v-else class="mb-3" />

      <div class="activity-datetime-host grossanlass-wizard-period mb-3">
        <ActivityOutlinedDatetimeSection
          :title="t('grossanlass.wizard.eventPeriodLabel')"
          icon="calendar"
          :required="true"
        >
          <ActivityDateTimeFields
            v-model:range="eventDateRange"
            v-model:time-from="eventTimeFrom"
            v-model:time-to="eventTimeTo"
            date-mode="range"
            :department-id="contextDepartmentId"
            :show-presets="true"
            :show-markers="true"
            preset-mode="fixed-periods"
            :label-from="t('activities.zeitraum.timeStart')"
            :label-to="t('activities.zeitraum.timeEnd')"
            :aria-label="t('grossanlass.wizard.eventPeriodLabel')"
          />
        </ActivityOutlinedDatetimeSection>
      </div>

      <ESelect
        id="grossanlass-organisation"
        v-model="formData.organisationId"
        :items="organisationItems"
        :label="t('grossanlass.wizard.organisationLabel')"
        hide-details="auto"
        class="mb-3"
        @update:model-value="onOrganisationChange"
      />

      <div v-if="formData.organisationId" class="form-group mb-3">
        <label class="form-label">{{ t('grossanlass.wizard.parentLabel') }}</label>
        <div class="tree-select-container">
          <div class="tree-select-header">
            <span>{{ t('grossanlass.wizard.parentPrompt') }}</span>
            <button
              type="button"
              class="btn-clear-parent"
              :class="{ active: formData.parentId === null }"
              @click="formData.parentId = null"
            >
              {{ t('grossanlass.wizard.noParent') }}
            </button>
          </div>
          <div class="tree-select-content">
            <div
              v-for="dept in availableParentDepartmentsTree"
              :key="dept.id"
              class="tree-select-item"
              :class="{ selected: formData.parentId === dept.id }"
              :style="{ paddingLeft: `${dept.level * 20 + 12}px` }"
              @click="formData.parentId = dept.id"
            >
              <v-icon icon="mdi-folder-outline" size="14" class="folder-icon" />
              <span>{{ dept.name }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="form-group mb-3">
        <label class="form-label">{{ t('grossanlass.wizard.chiefMwLabel') }}</label>
        <p class="form-hint mb-2">{{ t('grossanlass.wizard.chiefMwHint') }}</p>
        <div class="autocomplete-wrapper">
          <div v-if="selectedChiefMw" class="selected-user-chip">
            <span>{{ formatUserName(selectedChiefMw) }} ({{ selectedChiefMw.email }})</span>
            <button type="button" class="chip-remove" @click="clearChiefMw">×</button>
          </div>
          <div v-else>
            <input
              ref="chiefMwInputRef"
              v-model="chiefMwSearchQuery"
              type="text"
              class="form-input"
              :placeholder="t('grossanlass.wizard.userSearchPlaceholder')"
              autocomplete="off"
              @focus="onChiefMwFocus"
              @blur="handleChiefMwBlur"
              @input="onChiefMwSearchInput"
            />
            <Teleport to="body">
              <div
                v-if="chiefMwDropdownVisible"
                class="autocomplete-dropdown autocomplete-dropdown--teleported"
                :style="chiefMwDropdownStyle"
              >
                <div v-if="isSearchingChiefMw" class="autocomplete-empty">{{ t('common.loading') }}</div>
                <template v-else-if="chiefMwSearchResults.length > 0">
                  <div
                    v-for="user in chiefMwSearchResults"
                    :key="user.id"
                    class="autocomplete-item"
                    @mousedown.prevent="selectChiefMw(user)"
                  >
                    <span class="ac-name">{{ formatUserName(user) }}</span>
                    <span class="ac-email">{{ user.email }}</span>
                    <span v-if="user.departments_label" class="ac-depts">{{ user.departments_label }}</span>
                  </div>
                </template>
                <div v-else class="autocomplete-empty">{{ t('grossanlass.wizard.noUserSearchResults') }}</div>
              </div>
            </Teleport>
          </div>
        </div>
        <v-alert
          v-if="!selectedChiefMw && showChiefMwWarning"
          type="warning"
          variant="tonal"
          density="compact"
          class="mt-2"
          :text="t('grossanlass.wizard.chiefMwWarning')"
        />
      </div>

      <v-alert v-if="error" type="error" variant="tonal" class="mt-2" :text="error" />
    </form>

    <template #actions>
      <EButton variant="secondary" size="small" @click="close">{{ t('common.cancel') }}</EButton>
      <EButton
        variant="primary"
        size="small"
        type="submit"
        form="grossanlass-wizard-form"
        :loading="isSubmitting"
        :disabled="isSubmitting || Boolean(conflictingDepartmentName)"
      >
        {{ t('grossanlass.wizard.create') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { ref, watch, computed, nextTick, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import ActivityDateTimeFields from '@/components/activities/wizard/ActivityDateTimeFields.vue'
import ActivityOutlinedDatetimeSection from '@/components/activities/wizard/ActivityOutlinedDatetimeSection.vue'
import { getActivityDefaults } from '@/api/departmentSettings'
import {
  createGrossanlassDepartment,
  getDepartments,
  getGrossanlassAvailableUsers,
  type AvailableUser,
  type Department,
} from '@/api/departments'
import { getOrganisations, type Organisation } from '@/api/organisations'
import {
  filterOrganisationsForUserPickers,
  memberOrganisationIdsFromUserDepartments,
  prepareOrganisationsForOrgSubAdminList,
  sortOrganisationsMembersFirst,
} from '@/utils/organisationUserPicker'
import { combineDayAndTime, startOfLocalDay } from '@/utils/activityDateTimeParts'
import { defaultUsageWindowFromDepartmentDefaults } from '@/utils/activityPlanningFromDefaults'
import { departmentNamesConflict } from '@/utils/departmentNameMatcher'
import '@/styles/components/activity-datetime-field.css'
import '@/styles/components/activity-datetime-layout.css'

interface Props {
  isOpen: boolean
  preselectedOrganisationId?: string | null
  preselectedParentId?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  preselectedOrganisationId: null,
  preselectedParentId: null,
})

const emit = defineEmits<{
  close: []
  created: [departmentId: string]
}>()

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const contextDepartmentId = computed(
  () => (route.params.departmentId as string | undefined) ?? authStore.activeDepartmentId ?? null,
)

const plannedEventStartAt = ref<Date | null>(null)
const plannedEventEndAt = ref<Date | null>(null)

const eventDateRange = computed({
  get: (): [Date, Date] | null => {
    if (!plannedEventStartAt.value || !plannedEventEndAt.value) return null
    return [startOfLocalDay(plannedEventStartAt.value), startOfLocalDay(plannedEventEndAt.value)]
  },
  set: (v: [Date, Date] | null) => {
    if (!v || v.length < 2) return
    const [dStart, dEnd] = v
    const tStart = plannedEventStartAt.value ?? dStart
    const tEnd = plannedEventEndAt.value ?? dEnd
    plannedEventStartAt.value = combineDayAndTime(dStart, tStart)
    plannedEventEndAt.value = combineDayAndTime(dEnd, tEnd)
  },
})

const eventTimeFrom = computed({
  get: () => plannedEventStartAt.value ?? null,
  set: (v: Date | null) => {
    if (!v || !plannedEventStartAt.value) return
    plannedEventStartAt.value = combineDayAndTime(startOfLocalDay(plannedEventStartAt.value), v)
  },
})

const eventTimeTo = computed({
  get: () => plannedEventEndAt.value ?? null,
  set: (v: Date | null) => {
    if (!v || !plannedEventEndAt.value) return
    plannedEventEndAt.value = combineDayAndTime(startOfLocalDay(plannedEventEndAt.value), v)
  },
})

const isSuperAdmin = computed(() => (authStore.userRoles || []).includes('ROLE_SUPERADMIN'))
const memberOrganisationIds = computed(() => memberOrganisationIdsFromUserDepartments(authStore.departments))

const dialogOpen = computed({
  get: () => props.isOpen,
  set: (value: boolean) => {
    if (!value) close()
  },
})

const isSubmitting = ref(false)
const error = ref<string | null>(null)
const organisations = ref<Organisation[]>([])
const allDepartments = ref<Department[]>([])
const availableUsers = ref<AvailableUser[]>([])
const chiefMwSearchQuery = ref('')
const showChiefMwDropdown = ref(false)
const chiefMwInputRef = ref<HTMLInputElement | null>(null)
const chiefMwDropdownStyle = ref<Record<string, string>>({})
const selectedChiefMw = ref<AvailableUser | null>(null)
const showChiefMwWarning = ref(false)
const isSearchingChiefMw = ref(false)
let chiefMwSearchTimer: ReturnType<typeof setTimeout> | null = null
let chiefMwPositionListenersBound = false

const CHIEF_MW_DROPDOWN_Z_INDEX = 2700
const CHIEF_MW_DROPDOWN_MAX_HEIGHT = 280

const chiefMwSearchResults = computed(() => availableUsers.value)

const chiefMwQueryTrimmed = computed(() => chiefMwSearchQuery.value.trim())

const chiefMwDropdownVisible = computed(
  () =>
    showChiefMwDropdown.value &&
    !selectedChiefMw.value &&
    chiefMwQueryTrimmed.value.length >= 2,
)

watch(chiefMwDropdownVisible, async (open) => {
  if (!open) {
    unbindChiefMwPositionListeners()
    return
  }
  await nextTick()
  syncChiefMwDropdownPosition()
  bindChiefMwPositionListeners()
})

watch([chiefMwSearchResults, isSearchingChiefMw], () => {
  if (chiefMwDropdownVisible.value) {
    void nextTick().then(syncChiefMwDropdownPosition)
  }
})

onUnmounted(() => {
  unbindChiefMwPositionListeners()
  if (chiefMwSearchTimer) clearTimeout(chiefMwSearchTimer)
})

const formData = ref({
  name: '',
  organisationId: '',
  parentId: null as string | null,
})

const organisationItems = computed(() =>
  organisations.value.map((org) => ({ title: org.name, value: org.id })),
)

function findConflictingDepartmentName(name: string, organisationId: string): string | null {
  const trimmed = name.trim()
  if (!trimmed || !organisationId) return null
  const hit = allDepartments.value.find(
    (dept) =>
      dept.organisation_id === organisationId && departmentNamesConflict(trimmed, dept.name),
  )
  return hit?.name ?? null
}

const conflictingDepartmentName = computed(() =>
  findConflictingDepartmentName(formData.value.name, formData.value.organisationId),
)

const availableParentDepartmentsTree = computed(() => {
  if (!formData.value.organisationId) return []

  const available = allDepartments.value.filter(
    (dept) => dept.organisation_id === formData.value.organisationId,
  )

  interface TreeDept {
    id: string
    name: string
    level: number
  }

  function buildTree(parentId: string | null, level: number): TreeDept[] {
    const children = available.filter((d) => d.parent_id === parentId)
    const result: TreeDept[] = []
    children.forEach((dept) => {
      result.push({ id: dept.id, name: dept.name, level })
      result.push(...buildTree(dept.id, level + 1))
    })
    return result
  }

  return buildTree(null, 0)
})

function joinNonEmpty(values: Array<string | null | undefined>, separator: string): string {
  return values.map((v) => (v || '').trim()).filter(Boolean).join(separator)
}

function formatUserName(user: AvailableUser): string {
  const legalName = joinNonEmpty([user.first_name, user.last_name], ' ')
  const nickname = (user.nickname || '').trim()
  if (legalName && nickname) return `${legalName} (${nickname})`
  if (legalName) return legalName
  if (nickname) return nickname
  return user.name
}

function onOrganisationChange() {
  formData.value.parentId = null
  selectedChiefMw.value = null
  chiefMwSearchQuery.value = ''
  availableUsers.value = []
  if (chiefMwSearchTimer) {
    clearTimeout(chiefMwSearchTimer)
    chiefMwSearchTimer = null
  }
}

function syncChiefMwDropdownPosition() {
  const el = chiefMwInputRef.value
  if (!el) return

  const rect = el.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const width = Math.min(Math.max(rect.width, 320), vw - 16)
  const left = Math.max(8, Math.min(rect.left, vw - width - 8))
  const spaceBelow = vh - rect.bottom - 8
  const spaceAbove = rect.top - 8
  const openBelow = spaceBelow >= 120 || spaceBelow >= spaceAbove

  if (openBelow) {
    chiefMwDropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      left: `${left}px`,
      width: `${width}px`,
      maxHeight: `${Math.min(CHIEF_MW_DROPDOWN_MAX_HEIGHT, Math.max(spaceBelow - 4, 80))}px`,
      zIndex: String(CHIEF_MW_DROPDOWN_Z_INDEX),
    }
    return
  }

  chiefMwDropdownStyle.value = {
    position: 'fixed',
    left: `${left}px`,
    width: `${width}px`,
    bottom: `${vh - rect.top + 4}px`,
    maxHeight: `${Math.min(CHIEF_MW_DROPDOWN_MAX_HEIGHT, Math.max(spaceAbove - 4, 80))}px`,
    zIndex: String(CHIEF_MW_DROPDOWN_Z_INDEX),
  }
}

function onChiefMwPositionChange() {
  if (chiefMwDropdownVisible.value) syncChiefMwDropdownPosition()
}

function bindChiefMwPositionListeners() {
  if (chiefMwPositionListenersBound) return
  chiefMwPositionListenersBound = true
  window.addEventListener('resize', onChiefMwPositionChange)
  window.addEventListener('scroll', onChiefMwPositionChange, true)
}

function unbindChiefMwPositionListeners() {
  if (!chiefMwPositionListenersBound) return
  chiefMwPositionListenersBound = false
  window.removeEventListener('resize', onChiefMwPositionChange)
  window.removeEventListener('scroll', onChiefMwPositionChange, true)
}

function onChiefMwFocus() {
  showChiefMwDropdown.value = true
  void nextTick().then(syncChiefMwDropdownPosition)
}

function onChiefMwSearchInput() {
  if (chiefMwSearchTimer) clearTimeout(chiefMwSearchTimer)
  const query = chiefMwSearchQuery.value.trim()
  if (query.length < 2) {
    availableUsers.value = []
    isSearchingChiefMw.value = false
    return
  }

  isSearchingChiefMw.value = true
  chiefMwSearchTimer = setTimeout(() => {
    void searchChiefMwUsers(query)
  }, 250)
}

async function searchChiefMwUsers(query: string) {
  try {
    availableUsers.value = await getGrossanlassAvailableUsers(
      query,
      formData.value.organisationId || null,
    )
  } catch {
    availableUsers.value = []
  } finally {
    isSearchingChiefMw.value = false
  }
}

function selectChiefMw(user: AvailableUser) {
  selectedChiefMw.value = user
  chiefMwSearchQuery.value = ''
  showChiefMwDropdown.value = false
  showChiefMwWarning.value = false
  unbindChiefMwPositionListeners()
}

function clearChiefMw() {
  selectedChiefMw.value = null
}

function handleChiefMwBlur() {
  window.setTimeout(() => {
    showChiefMwDropdown.value = false
  }, 200)
}

async function seedDefaultPeriod() {
  const deptId = contextDepartmentId.value
  if (!deptId) return
  try {
    const defaults = await getActivityDefaults(deptId)
    const { usageStart, usageEnd } = defaultUsageWindowFromDepartmentDefaults(defaults)
    plannedEventStartAt.value = usageStart
    plannedEventEndAt.value = usageEnd
  } catch {
    plannedEventStartAt.value = null
    plannedEventEndAt.value = null
  }
}

function resetForm() {
  formData.value = {
    name: '',
    organisationId: props.preselectedOrganisationId || '',
    parentId: props.preselectedParentId || null,
  }
  plannedEventStartAt.value = null
  plannedEventEndAt.value = null
  selectedChiefMw.value = null
  chiefMwSearchQuery.value = ''
  showChiefMwWarning.value = false
  error.value = null
}

watch(
  () => [props.preselectedOrganisationId, props.preselectedParentId],
  () => {
    if (props.isOpen) {
      formData.value.organisationId = props.preselectedOrganisationId || formData.value.organisationId
      formData.value.parentId = props.preselectedParentId ?? formData.value.parentId
    }
  },
)

watch(
  () => props.isOpen,
  async (open) => {
    if (!open) return
    error.value = null
    resetForm()
    try {
      const [rawOrgs, depts] = await Promise.all([getOrganisations(), getDepartments()])
      allDepartments.value = depts
      const picked = filterOrganisationsForUserPickers(rawOrgs)
      let list = prepareOrganisationsForOrgSubAdminList(picked, {
        isSuperAdmin: isSuperAdmin.value,
        memberOrganisationIds: memberOrganisationIds.value,
      })
      list = sortOrganisationsMembersFirst(list, memberOrganisationIds.value)
      organisations.value = list
      if (!formData.value.organisationId && list.length === 1) {
        formData.value.organisationId = list[0].id
      }
      await Promise.all([seedDefaultPeriod()])
    } catch {
      error.value = t('grossanlass.wizard.loadError')
    }
  },
)

function close() {
  emit('close')
}

async function handleSubmit() {
  error.value = null
  showChiefMwWarning.value = false

  if (!formData.value.name.trim()) {
    error.value = t('grossanlass.wizard.nameRequired')
    return
  }
  if (conflictingDepartmentName.value) {
    error.value = t('grossanlass.wizard.nameDuplicate', { name: conflictingDepartmentName.value })
    return
  }
  if (!formData.value.organisationId) {
    error.value = t('grossanlass.wizard.organisationRequired')
    return
  }
  if (!plannedEventStartAt.value) {
    error.value = t('grossanlass.wizard.eventStartRequired')
    return
  }
  if (
    plannedEventEndAt.value &&
    plannedEventEndAt.value.getTime() < plannedEventStartAt.value.getTime()
  ) {
    error.value = t('grossanlass.wizard.eventEndBeforeStart')
    return
  }
  if (!selectedChiefMw.value) {
    showChiefMwWarning.value = true
  }

  isSubmitting.value = true
  try {
    const created = await createGrossanlassDepartment({
      name: formData.value.name.trim(),
      organisation_id: formData.value.organisationId,
      parent_id: formData.value.parentId || null,
      planned_event_start: plannedEventStartAt.value.toISOString(),
      planned_event_end: plannedEventEndAt.value?.toISOString() || null,
      chief_mw_user_id: selectedChiefMw.value?.id || null,
    })

    await authStore.loadDepartments()
    await authStore.setActiveDepartment(created.id)
    emit('created', created.id)
    close()
    toast.success(t('grossanlass.wizard.createdSuccess', { name: created.name }))
    await router.push(`/${created.id}/dashboard`)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    error.value = e.response?.data?.error || t('grossanlass.wizard.createError')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
.grossanlass-wizard-body {
  padding: 4px 0;
}

.field-error {
  margin: 0;
  font-size: 13px;
  color: #b91c1c;
}

.grossanlass-wizard-period {
  width: 100%;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

.form-hint {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

.tree-select-container {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.tree-select-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  font-size: 13px;
}

.btn-clear-parent {
  border: none;
  background: transparent;
  color: #4f46e5;
  cursor: pointer;
  font-size: 13px;
  padding: 2px 6px;
  border-radius: 4px;
}

.btn-clear-parent.active {
  background: #eef2ff;
}

.tree-select-content {
  max-height: 160px;
  overflow-y: auto;
}

.tree-select-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  cursor: pointer;
  font-size: 14px;
}

.tree-select-item:hover {
  background: #f3f4f6;
}

.tree-select-item.selected {
  background: #eef2ff;
  color: #4338ca;
}

.autocomplete-wrapper {
  position: relative;
}

.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
}

.selected-user-chip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: #eef2ff;
  border-radius: 8px;
  font-size: 14px;
}

.chip-remove {
  border: none;
  background: transparent;
  font-size: 18px;
  cursor: pointer;
  color: #6b7280;
}

.autocomplete-dropdown--teleported {
  overflow-y: auto;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.14);
}

.autocomplete-item {
  padding: 10px 12px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.autocomplete-item:hover {
  background: #f3f4f6;
}

.ac-name {
  font-size: 14px;
  font-weight: 500;
}

.ac-email {
  font-size: 12px;
  color: #6b7280;
}

.ac-depts {
  font-size: 11px;
  color: #9ca3af;
}

.autocomplete-empty {
  padding: 10px 12px;
  font-size: 13px;
  color: #6b7280;
}
</style>
