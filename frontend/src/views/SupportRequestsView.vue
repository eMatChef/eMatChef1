<template>
  <div class="support-requests-page">
    <div class="header">
      <h1>{{ t('supportRequests.title') }}</h1>
      <p>{{ t('supportRequests.subtitle') }}</p>
    </div>

    <div class="actions">
      <button class="btn btn-sm support-tab-btn" :class="{ active: activeTab === 'pending' }" @click="activeTab = 'pending'">
        {{ t('supportRequests.tabOpen') }}
      </button>
      <button class="btn btn-sm support-tab-btn" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">
        {{ t('supportRequests.tabHistory') }}
      </button>
      <button class="btn btn-sm" :disabled="loading" @click="loadRequests">
        {{ loading ? t('supportRequests.loading') : t('supportRequests.refresh') }}
      </button>
    </div>
    <p v-if="activeTab === 'history'" class="tab-hint">
      {{ t('supportRequests.tabHistoryHint') }}
    </p>

    <div v-if="error" class="error">{{ error }}</div>

    <div v-if="!loading && requests.length === 0" class="empty">
      {{ activeTab === 'pending' ? t('supportRequests.emptyPending') : t('supportRequests.emptyHistory') }}
    </div>

    <div v-else class="list">
      <div v-for="req in requests" :key="`${req.request_kind || 'admin'}-${req.id}`" class="card">
        <div class="title-row">
          <span v-if="req.request_kind === 'department_join'" class="request-kind-badge request-kind-badge--join">
            {{ t('supportRequests.kindDepartmentJoin') }}
          </span>
          <span v-else class="request-kind-badge request-kind-badge--admin">
            {{ t('supportRequests.kindAdminRequest') }}
          </span>
          <div class="title">{{ req.requested_department_name }}</div>
        </div>
        <div class="meta">
          <span>{{ req.name }}</span>
          <span v-if="req.email"> · {{ req.email }}</span>
        </div>
        <div v-if="req.request_kind === 'department_join' && req.organisation_name" class="meta">
          {{ t('supportRequests.organisation', { name: req.organisation_name }) }}
        </div>
        <div v-if="req.request_kind === 'department_join' && req.target_department_name" class="meta">
          {{ t('supportRequests.joinTargetDept', { name: req.target_department_name }) }}
        </div>
        <div v-if="req.requested_organisation_id" class="meta">
          {{ t('supportRequests.organisation', { name: organisations.find(o => o.id === req.requested_organisation_id)?.name || req.requested_organisation_id }) }}
        </div>
        <div v-if="req.requested_affiliation" class="meta">
          {{ t('supportRequests.affiliation', { value: req.requested_affiliation }) }}
        </div>
        <div v-if="req.requested_parent_department_name" class="meta">
          {{ t('supportRequests.parentDepartment', { name: req.requested_parent_department_name }) }}
        </div>
        <div v-if="req.message" class="message">{{ req.message }}</div>
        <div class="meta">{{ t('supportRequests.requestedAt', { date: formatDate(req.created_at) }) }}</div>
        <div v-if="activeTab === 'history'" class="meta">
          {{ t('pendingAssignment.colStatus') }}:
          {{
            req.status === 'assigned'
              ? t('supportRequests.statusAssigned')
              : req.status === 'rejected'
                ? t('supportRequests.statusRejected')
                : t('supportRequests.statusOpen')
          }}
          <span v-if="req.assigned_department_name">{{ t('supportRequests.target', { name: req.assigned_department_name }) }}</span>
          <span v-if="req.reviewed_by_name">{{ t('supportRequests.reviewedBy', { name: req.reviewed_by_name }) }}</span>
          <span v-if="req.updated_at"> · {{ formatDate(req.updated_at) }}</span>
        </div>
        <div v-if="activeTab === 'pending'" class="row-actions">
          <template v-if="req.request_kind === 'department_join'">
            <button
              class="btn btn-success btn-sm"
              :disabled="loading"
              @click="decideDepartmentJoin(req.id, 'approved')"
            >
              {{ t('supportRequests.approveJoin') }}
            </button>
            <button class="btn btn-danger btn-sm" :disabled="loading" @click="decideDepartmentJoin(req.id, 'rejected')">
              {{ t('supportRequests.reject') }}
            </button>
          </template>
          <template v-else>
            <template v-if="canAssignSupportRequests">
              <button
                class="btn btn-success btn-sm"
                :disabled="loading"
                @click="openAssignModal(req)"
              >
                {{ t('supportRequests.assign') }}
              </button>
            </template>
            <button class="btn btn-danger btn-sm" :disabled="loading" @click="decideAdmin(req.id)">
              {{ t('supportRequests.reject') }}
            </button>
          </template>
        </div>
      </div>
    </div>

    <div v-if="assignModalOpen" class="modal-overlay">
      <div class="modal-dialog support-modal-dialog">
        <div class="modal-header">
          <h2>{{ t('supportRequests.modalTitle') }}</h2>
          <button type="button" class="modal-close" @click="closeAssignModal">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <div class="modal-body">
          <div v-if="selectedRequest" class="assign-request-details">
            <div class="assign-request-details-row">
              <span class="assign-request-details-label">{{ t('supportRequests.userLabel') }}</span>
              <span>{{ selectedRequest.name || '–' }}</span>
            </div>
            <div class="assign-request-details-row">
              <span class="assign-request-details-label">{{ t('supportRequests.emailLabel') }}</span>
              <a v-if="selectedRequest.email" :href="`mailto:${selectedRequest.email}`">{{ selectedRequest.email }}</a>
              <span v-else>–</span>
            </div>
            <div class="assign-request-details-row">
              <span class="assign-request-details-label">{{ t('supportRequests.searchedLabel') }}</span>
              <span>{{ selectedRequest.requested_department_name || '–' }}</span>
            </div>
            <p
              v-if="selectedRequest.requested_department_name === unknownDepartmentName"
              class="assign-request-details-hint"
            >
              {{ t('supportRequests.unknownDeptHint') }}
            </p>
            <div v-if="selectedRequestOrganisationName" class="assign-request-details-row">
              <span class="assign-request-details-label">{{ t('supportRequests.organisationLabel') }}</span>
              <span>{{ selectedRequestOrganisationName }}</span>
            </div>
            <div v-if="selectedRequest.requested_parent_department_name" class="assign-request-details-row">
              <span class="assign-request-details-label">{{ t('supportRequests.parentDeptLabel') }}</span>
              <span>{{ selectedRequest.requested_parent_department_name }}</span>
            </div>
            <div v-if="selectedRequest.requested_affiliation" class="assign-request-details-row">
              <span class="assign-request-details-label">{{ t('supportRequests.affiliationLabel') }}</span>
              <span>{{ selectedRequest.requested_affiliation }}</span>
            </div>
            <div v-if="selectedRequest.message" class="assign-request-details-row assign-request-details-row--block">
              <span class="assign-request-details-label">{{ t('supportRequests.messageLabel') }}</span>
              <span class="assign-request-details-message">{{ selectedRequest.message }}</span>
            </div>
            <div class="assign-request-details-row">
              <span class="assign-request-details-label">{{ t('supportRequests.requestedAtLabel') }}</span>
              <span>{{ formatDate(selectedRequest.created_at) }}</span>
            </div>
          </div>

          <!-- Toggle: Bestehendes Department ODER Neues erstellen -->
          <div class="form-group">
            <div class="assign-mode-toggle">
            <button
              type="button"
              class="toggle-option"
              :class="{ active: !createDepartmentMode }"
              @click="switchToSearchMode"
            >
              {{ t('supportRequests.modeSearchDept') }}
            </button>
            <button
              type="button"
              class="toggle-option"
              :class="{ active: createDepartmentMode }"
              @click="switchToCreateMode"
            >
              {{ t('supportRequests.modeNewDept') }}
            </button>
          </div>
          </div>

          <div v-if="!createDepartmentMode" class="form-group">
            <label class="form-label">{{ t('supportRequests.selectExistingDept') }}</label>
            <p
              v-if="selectedRequest?.requested_department_name && selectedRequest.requested_department_name !== unknownDepartmentName"
              class="form-hint assign-dept-hint"
            >
              {{ t('supportRequests.hintSortedNear', { name: selectedRequest.requested_department_name }) }}<template v-if="selectedRequest.requested_organisation_id">{{ t('supportRequests.hintSortedNearOrgOnly') }}</template>
            </p>
          <div class="autocomplete-wrapper">
            <div v-if="selectedDepartment" class="selected-chip">
              <span>{{ selectedDepartment.name }}</span>
              <span v-if="selectedDepartment.parent_id" class="chip-meta">{{ t('supportRequests.subDepartment') }}</span>
              <button class="chip-remove" type="button" @click="clearSelectedDepartment">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
              </button>
            </div>
            <input
              v-else
              v-model="departmentSearchQuery"
              type="text"
              class="form-input"
              :placeholder="t('supportRequests.deptSearchPlaceholder')"
              autocomplete="off"
              @focus="onDepartmentSearchFocus"
              @input="onDepartmentSearchInput"
              @blur="handleDepartmentBlur"
              ref="departmentSearchInput"
            />
            <Teleport to="body">
              <div
                v-if="showDepartmentDropdown && !selectedDepartment"
                class="autocomplete-dropdown autocomplete-dropdown--teleported"
                :style="departmentDropdownStyle"
              >
                <div
                  v-for="d in filteredAssignableDepartments"
                  :key="d.id"
                  class="autocomplete-item"
                  :class="{ 'is-child': d._level > 0 }"
                  :style="{ paddingLeft: `${12 + d._level * 14}px` }"
                  @mousedown.prevent="selectDepartment(d)"
                >
                  <span class="ac-name">{{ d.name }}</span>
                  <span v-if="d.parent_id" class="ac-meta">› {{ getParentPath(d) }}</span>
                </div>
                <div v-if="filteredAssignableDepartments.length === 0" class="autocomplete-empty">
                  {{ t('supportRequests.noResults') }}
                </div>
              </div>
            </Teleport>
          </div>
          <div v-if="selectedDepartment" class="form-group">
            <label class="form-label">{{ t('supportRequests.roleInDept') }}</label>
            <select v-model="assignRole" class="form-select">
              <option v-for="r in assignRoles" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
            <p class="form-hint">{{ t('supportRequests.roleHintExisting') }}</p>
          </div>
          <div v-if="assignError" class="error-message">{{ assignError }}</div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" @click="closeAssignModal">{{ t('supportRequests.cancel') }}</button>
            <button
              type="button"
              class="btn-primary"
              :disabled="assignLoading || !selectedDepartment || !selectedAssignmentDepartmentId || !selectedRequest"
              @click="assignSelectedDepartment"
            >
              {{ assignLoading ? t('supportRequests.assigning') : t('supportRequests.assignConfirm') }}
            </button>
          </div>
          </div>

          <div v-else class="form-group">
          <label class="form-label">{{ t('supportRequests.createNewDept') }}</label>
          <div class="create-form">
            <input
              v-model="newDepartmentName"
              class="form-input"
              :placeholder="t('supportRequests.newDeptNamePlaceholder')"
            />
            <select v-model="newDepartmentOrganisationId" class="form-select" @change="newDepartmentParentId = ''">
              <option value="" disabled hidden>&nbsp;</option>
              <option v-for="org in organisations" :key="org.id" :value="org.id">{{ org.name }}</option>
            </select>
            <div v-if="newDepartmentOrganisationId" class="form-group">
              <label class="form-label">{{ t('supportRequests.parentDeptOptional') }}</label>
              <div class="tree-select-container">
                <div class="tree-select-header">
                  <span>{{ t('supportRequests.parentDeptPrompt') }}</span>
                  <button
                    type="button"
                    class="btn-clear-parent"
                    :class="{ active: !newDepartmentParentId }"
                    @click="newDepartmentParentId = ''"
                  >
                    {{ t('supportRequests.noParentMain') }}
                  </button>
                </div>
                <div class="tree-select-content">
                  <div
                    v-for="dept in availableParentDepartmentsTree"
                    :key="dept.id"
                    class="tree-select-item"
                    :class="{ selected: newDepartmentParentId === dept.id }"
                    :style="{ paddingLeft: `${dept.level * 20 + 12}px` }"
                    @click="newDepartmentParentId = dept.id"
                  >
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="folder-icon">
                      <path d="M2 4C2 3.44772 2.44772 3 3 3H6.58579C6.851 3 7.10536 3.10536 7.29289 3.29289L8.70711 4.70711C8.89464 4.89464 9.149 5 9.41421 5H13C13.5523 5 14 5.44772 14 6V12C14 12.5523 13.5523 13 13 13H3C2.44772 13 2 12.5523 2 12V4Z" fill="currentColor"/>
                    </svg>
                    <span>{{ dept.name }}</span>
                  </div>
                </div>
                <p class="form-hint">{{ t('supportRequests.parentDeptFooterHint') }}</p>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">{{ t('supportRequests.roleInDept') }}</label>
              <select v-model="assignRole" class="form-select">
                <option v-for="r in assignRoles" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
              <p class="form-hint">{{ t('supportRequests.roleHintNew') }}</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeAssignModal">{{ t('supportRequests.cancel') }}</button>
              <button
                type="button"
                class="btn-primary"
                :disabled="loading || !newDepartmentName.trim() || !newDepartmentOrganisationId || !selectedRequest"
                @click="createAndAssignDepartment"
              >
                {{ t('supportRequests.createAndAssign') }}
              </button>
            </div>
          </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { flattenDepartmentsWithLevel } from '@/utils/departmentHierarchy'
import { levenshtein } from '@/utils/stringSimilarity'
import { useRoute } from 'vue-router'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { createDepartment, departmentHasManager, getDepartments, type Department } from '@/api/departments'
import { getOrganisations, type Organisation } from '@/api/organisations'
import {
  filterDepartmentsForAdminScope,
  filterOrganisationsForUserPickers,
} from '@/utils/organisationUserPicker'
import {
  assignAdminJoinRequest,
  decideAdminJoinRequest,
  decideJoinRequest,
  getAdminJoinRequestHistory,
  getPendingAdminJoinRequests,
  type PendingAdminJoinRequest
} from '@/api/joinRequests'

const route = useRoute()
const { t, locale } = useI18n()
const toast = useToast()
const authStore = useAuthStore()

/** Backend liefert diesen Platzhalter für fehlende Abteilung (Sprache API). */
const unknownDepartmentName = 'Unbekannte Abteilung'
const DEPARTMENT_DROPDOWN_Z_INDEX = 2500
const DEPARTMENT_DROPDOWN_MAX_HEIGHT = 240
// Im Admin-Bereich (/admin-dashboard) keine departmentId – API akzeptiert '' für globale Admins
const isAdminRoute = computed(() => route.path.startsWith('/admin-dashboard'))
const departmentId = computed(() =>
  isAdminRoute.value ? '' : String(route.params.departmentId || authStore.activeDepartmentId || '')
)
const loading = ref(false)
const error = ref<string | null>(null)
const requests = ref<PendingAdminJoinRequest[]>([])
const activeTab = ref<'pending' | 'history'>('pending')
const assignableDepartments = ref<Department[]>([])
const organisations = ref<Organisation[]>([])
/** Zuordnen: SA / OrgChef / SubOrgChef oder Materialchef im gewählten Department (wie Backend). */
const canAssignSupportRequests = computed(() => {
  if (authStore.canAdmin('support_requests.assign')) return true
  return (authStore.currentDepartmentRole || '').toLowerCase() === 'mw' && !!departmentId.value
})
const assignModalOpen = ref(false)
const selectedRequest = ref<PendingAdminJoinRequest | null>(null)
const selectedAssignmentDepartmentId = ref('')
const selectedDepartment = ref<Department | null>(null)
const departmentSearchQuery = ref('')
const showDepartmentDropdown = ref(false)
const departmentSearchInput = ref<HTMLInputElement | null>(null)
const departmentDropdownStyle = ref<Record<string, string>>({})
let departmentDropdownListenersBound = false
const assignLoading = ref(false)
const assignError = ref<string | null>(null)
const createDepartmentMode = ref(false)
const newDepartmentName = ref('')
const newDepartmentOrganisationId = ref('')
const newDepartmentParentId = ref('')
const assignRole = ref<'u' | 'l1' | 'l2' | 'l3' | 'dc' | 'mw'>('u')

const assignRoles = computed(() => [
  { value: 'u', label: t('supportRequests.roles.u') },
  { value: 'l3', label: t('supportRequests.roles.l3') },
  { value: 'l2', label: t('supportRequests.roles.l2') },
  { value: 'l1', label: t('supportRequests.roles.l1') },
  { value: 'dc', label: t('supportRequests.roles.dc') },
  { value: 'mw', label: t('supportRequests.roles.mw') }
])

const selectedRequestOrganisationName = computed(() => {
  const orgId = selectedRequest.value?.requested_organisation_id
  if (!orgId) return ''
  return organisations.value.find((o) => o.id === orgId)?.name || orgId
})

// Verfügbare Parent-Departments als Tree (nur aus gewählter Organisation)
const availableParentDepartmentsTree = computed(() => {
  const orgId = newDepartmentOrganisationId.value
  if (!orgId) return []
  return flattenDepartmentsWithLevel(
    assignableDepartments.value.filter((d) => d.organisation_id === orgId),
    locale.value,
  ).map((d) => ({ id: d.id, name: d.name, level: d._level }))
})

/** Departments derselben Organisation wie die Anfrage (falls gesetzt), sonst alle. */
const departmentPoolForAssign = computed(() => {
  const orgId = selectedRequest.value?.requested_organisation_id
  if (!orgId) return assignableDepartments.value
  return assignableDepartments.value.filter((d) => d.organisation_id === orgId)
})

const filteredAssignableDepartments = computed(() => {
  const q = departmentSearchQuery.value.trim()
  let list = departmentPoolForAssign.value

  if (q) {
    const ql = q.toLowerCase()
    list = list.filter((d) => {
      const dn = d.name.toLowerCase()
      if (dn.includes(ql)) return true
      const path = getParentPath(d).toLowerCase()
      if (path.includes(ql)) return true
      const maxLen = Math.max(dn.length, ql.length)
      if (maxLen === 0) return false
      return levenshtein(dn, ql) <= Math.min(4, Math.ceil(maxLen / 3))
    })
  }

  return flattenDepartmentsWithLevel(list, locale.value).slice(0, q ? 40 : 80)
})

function getParentPath(d: Department): string {
  if (!d.parent_id) return ''
  const parent = assignableDepartments.value.find((x) => x.id === d.parent_id)
  return parent ? `${getParentPath(parent)}${getParentPath(parent) ? ' › ' : ''}${parent.name}` : ''
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString(locale.value)
}

async function loadRequests() {
  // Im Admin-Bereich: departmentId kann leer sein, API akzeptiert das für globale Admins
  loading.value = true
  error.value = null
  try {
    requests.value = activeTab.value === 'pending'
      ? await getPendingAdminJoinRequests(departmentId.value)
      : await getAdminJoinRequestHistory(departmentId.value)
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('supportRequests.errors.loadFailed')
  } finally {
    loading.value = false
  }
}

async function loadAssignableDepartments() {
  try {
    assignableDepartments.value = filterDepartmentsForAdminScope(await getDepartments())
  } catch (err) {
    console.error('Departments konnten nicht geladen werden:', err)
    assignableDepartments.value = []
  }
}

async function loadOrganisations() {
  try {
    organisations.value = filterOrganisationsForUserPickers(await getOrganisations())
  } catch (err) {
    console.error('Organisationen konnten nicht geladen werden:', err)
    organisations.value = []
  }
}

async function decideAdmin(id: string) {
  loading.value = true
  error.value = null
  try {
    await decideAdminJoinRequest(departmentId.value, id, 'rejected')
    await loadRequests()
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('supportRequests.errors.decideFailed')
  } finally {
    loading.value = false
  }
}

async function decideDepartmentJoin(id: string, status: 'approved' | 'rejected') {
  loading.value = true
  error.value = null
  try {
    await decideJoinRequest(id, status)
    toast.success(
      status === 'approved'
        ? t('supportRequests.toastJoinApproved')
        : t('supportRequests.toastJoinRejected'),
    )
    await loadRequests()
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('supportRequests.errors.decideFailed')
    toast.error(error.value)
  } finally {
    loading.value = false
  }
}

function isAdminSupportRequest(req: PendingAdminJoinRequest): boolean {
  return req.request_kind !== 'department_join'
}

async function openAssignModal(req: PendingAdminJoinRequest) {
  if (!isAdminSupportRequest(req)) return
  selectedRequest.value = req
  selectedAssignmentDepartmentId.value = ''
  selectedDepartment.value = null
  departmentSearchQuery.value = ''
  showDepartmentDropdown.value = false
  assignError.value = null
  createDepartmentMode.value = false
  newDepartmentOrganisationId.value = ''
  newDepartmentParentId.value = ''
  newDepartmentName.value = req.requested_department_name === unknownDepartmentName
    ? ''
    : req.requested_department_name
  assignModalOpen.value = true
  assignRole.value = 'u'
  error.value = null
  await loadAssignableDepartments()
}

function closeAssignModal() {
  showDepartmentDropdown.value = false
  unbindDepartmentDropdownListeners()
  assignModalOpen.value = false
  selectedRequest.value = null
  selectedDepartment.value = null
  assignError.value = null
  assignRole.value = 'u'
}

async function selectDepartment(d: Department) {
  selectedDepartment.value = d
  selectedAssignmentDepartmentId.value = d.id
  departmentSearchQuery.value = ''
  showDepartmentDropdown.value = false
  unbindDepartmentDropdownListeners()
  try {
    const hasManager = await departmentHasManager(d.id)
    assignRole.value = hasManager ? 'u' : 'mw'
  } catch {
    assignRole.value = 'u'
  }
}

function clearSelectedDepartment() {
  selectedDepartment.value = null
  selectedAssignmentDepartmentId.value = ''
  departmentSearchQuery.value = ''
}

function syncDepartmentDropdownPosition() {
  const el = departmentSearchInput.value
  if (!el) return

  const rect = el.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const width = Math.min(Math.max(rect.width, 280), vw - 16)
  const left = Math.max(8, Math.min(rect.left, vw - width - 8))
  const spaceBelow = vh - rect.bottom - 8
  const spaceAbove = rect.top - 8
  const openBelow = spaceBelow >= 120 || spaceBelow >= spaceAbove

  if (openBelow) {
    departmentDropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      left: `${left}px`,
      width: `${width}px`,
      maxHeight: `${Math.min(DEPARTMENT_DROPDOWN_MAX_HEIGHT, Math.max(spaceBelow - 4, 80))}px`,
      zIndex: String(DEPARTMENT_DROPDOWN_Z_INDEX),
    }
    return
  }

  departmentDropdownStyle.value = {
    position: 'fixed',
    left: `${left}px`,
    width: `${width}px`,
    bottom: `${vh - rect.top + 4}px`,
    maxHeight: `${Math.min(DEPARTMENT_DROPDOWN_MAX_HEIGHT, Math.max(spaceAbove - 4, 80))}px`,
    zIndex: String(DEPARTMENT_DROPDOWN_Z_INDEX),
  }
}

function onDepartmentDropdownPositionChange() {
  if (showDepartmentDropdown.value) syncDepartmentDropdownPosition()
}

function bindDepartmentDropdownListeners() {
  if (departmentDropdownListenersBound) return
  departmentDropdownListenersBound = true
  window.addEventListener('resize', onDepartmentDropdownPositionChange)
  window.addEventListener('scroll', onDepartmentDropdownPositionChange, true)
}

function unbindDepartmentDropdownListeners() {
  if (!departmentDropdownListenersBound) return
  departmentDropdownListenersBound = false
  window.removeEventListener('resize', onDepartmentDropdownPositionChange)
  window.removeEventListener('scroll', onDepartmentDropdownPositionChange, true)
}

async function onDepartmentSearchFocus() {
  showDepartmentDropdown.value = true
  await nextTick()
  syncDepartmentDropdownPosition()
  bindDepartmentDropdownListeners()
}

function onDepartmentSearchInput() {
  showDepartmentDropdown.value = true
  void nextTick().then(() => {
    syncDepartmentDropdownPosition()
    bindDepartmentDropdownListeners()
  })
}

function handleDepartmentBlur() {
  setTimeout(() => {
    showDepartmentDropdown.value = false
    unbindDepartmentDropdownListeners()
  }, 200)
}

function switchToSearchMode() {
  createDepartmentMode.value = false
  clearSelectedDepartment()
  newDepartmentName.value = ''
  newDepartmentOrganisationId.value = ''
  newDepartmentParentId.value = ''
}

function switchToCreateMode() {
  createDepartmentMode.value = true
  clearSelectedDepartment()
  selectedAssignmentDepartmentId.value = ''
  assignRole.value = 'mw'
}

async function assignToDepartment(id: string, targetDepartmentId: string, role?: string) {
  if (!targetDepartmentId) return
  assignLoading.value = true
  assignError.value = null
  try {
    const res = await assignAdminJoinRequest(
      departmentId.value || '',
      id,
      targetDepartmentId,
      role || assignRole.value
    )
    toast.success(t('supportRequests.toastAssigned', { role: res.assigned_role }))
    if (res.role_forced_to_mw_warning) {
      toast.warning(res.role_forced_to_mw_warning)
    }
    closeAssignModal()
    await loadRequests()
  } catch (err: any) {
    const msg = err?.response?.data?.error || t('supportRequests.errors.assignFailed')
    assignError.value = msg
    toast.error(msg)
  } finally {
    assignLoading.value = false
  }
}

async function assignSelectedDepartment() {
  if (!selectedRequest.value || !selectedAssignmentDepartmentId.value) return
  await assignToDepartment(selectedRequest.value.id, selectedAssignmentDepartmentId.value, assignRole.value)
}

async function createAndAssignDepartment() {
  if (!selectedRequest.value) return
  const name = newDepartmentName.value.trim()
  const organisationId = newDepartmentOrganisationId.value
  if (!name || !organisationId) return

  loading.value = true
  error.value = null
  try {
    const created = await createDepartment({
      name,
      organisation_id: organisationId,
      parent_id: newDepartmentParentId.value || null
    })
    await loadAssignableDepartments()
    await assignToDepartment(selectedRequest.value.id, created.id, assignRole.value)
  } catch (err: any) {
    const msg = err?.response?.data?.error || t('supportRequests.errors.createDeptFailed')
    error.value = msg
    toast.error(msg)
  } finally {
    loading.value = false
  }
}

watch(showDepartmentDropdown, async (open) => {
  if (!open) {
    unbindDepartmentDropdownListeners()
    return
  }
  await nextTick()
  syncDepartmentDropdownPosition()
  bindDepartmentDropdownListeners()
})

onMounted(loadRequests)
onMounted(loadOrganisations)
onUnmounted(unbindDepartmentDropdownListeners)
watch(departmentId, loadRequests)
watch(activeTab, loadRequests)
</script>

<style scoped>
.support-requests-page { padding: 24px; max-width: 980px; }
.header h1 { margin: 0 0 6px; }
.header p { margin: 0; color: #6b7280; }
.actions { margin: 16px 0; display: flex; gap: 8px; }
.tab-hint {
  margin: -8px 0 16px;
  padding: 10px 12px;
  font-size: 13px;
  color: #475569;
  line-height: 1.45;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}
.tab-hint code {
  font-size: 12px;
  background: #e2e8f0;
  padding: 1px 5px;
  border-radius: 4px;
}
/* Buttons use shared ui/buttons.css */
.support-tab-btn.active { background: #ecfdf5; border-color: #10b981; color: #047857; }
.error { color: #b91c1c; margin-bottom: 12px; }
.empty { padding: 16px; border: 1px dashed #d1d5db; border-radius: 8px; color: #6b7280; }
.list { display: flex; flex-direction: column; gap: 10px; }
.card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; background: #fff; }
.title-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.title-row .title {
  margin: 0;
}

.request-kind-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.request-kind-badge--join {
  background: #dbeafe;
  color: #1d4ed8;
}

.request-kind-badge--admin {
  background: #fef3c7;
  color: #92400e;
}

.title { font-weight: 700; color: #111827; }
.meta { font-size: 13px; color: #6b7280; margin-top: 3px; }
.message { margin-top: 8px; color: #374151; }
.row-actions { margin-top: 10px; display: flex; gap: 8px; }
/* Modal overlay/header/body/footer base uses shared ui/modals.css */
.support-modal-dialog {
  width: min(560px, calc(100vw - 48px));
  max-height: calc(100vh - 48px);
  padding: 0;
  overflow: hidden;
}

.assign-request-details {
  margin-bottom: 16px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
  font-size: 14px;
  line-height: 1.45;
}

.assign-request-details-row {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 8px;
  margin-bottom: 6px;
}

.assign-request-details-row:last-child {
  margin-bottom: 0;
}

.assign-request-details-row--block {
  flex-direction: column;
  gap: 2px;
}

.assign-request-details-label {
  flex: 0 0 auto;
  min-width: 9.5rem;
  font-weight: 600;
  color: #374151;
}

.assign-request-details-hint {
  margin: 0 0 8px;
  padding-left: 0;
  font-size: 13px;
  color: #6b7280;
}

.assign-request-details-message {
  white-space: pre-wrap;
  color: #374151;
}

.assign-request-details a {
  color: #2563eb;
  word-break: break-all;
}

.modal-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
}
/* Form group base uses shared ui/forms.css */
.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 8px;
}
/* Form input/select base uses shared ui/forms.css */
.form-hint {
  font-size: 12px;
  color: #6b7280;
  margin-top: 4px;
  margin-bottom: 0;
}
.error-message {
  background: #fee2e2;
  color: #dc2626;
  padding: 12px;
  border-radius: 6px;
  font-size: 14px;
  margin-bottom: 20px;
}
.tree-select-container {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
  overflow: hidden;
}
.tree-select-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: white;
  border-bottom: 1px solid #e5e7eb;
  font-size: 14px;
  color: #374151;
}
.btn-clear-parent {
  padding: 6px 12px;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 12px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-clear-parent:hover {
  background: #e5e7eb;
  color: #374151;
}
.btn-clear-parent.active {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}
.tree-select-content {
  max-height: 300px;
  overflow-y: auto;
  padding: 8px;
}
.tree-select-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  margin: 2px 0;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
  color: #374151;
}
.tree-select-item:hover {
  background: #e5e7eb;
}
.tree-select-item.selected {
  background: #dbeafe;
  color: #1e40af;
  font-weight: 500;
}
.tree-select-item .folder-icon {
  color: #6b7280;
  flex-shrink: 0;
}
.tree-select-item.selected .folder-icon {
  color: #3b82f6;
}

.assign-mode-toggle {
  display: flex;
  background: #f1f5f9;
  border-radius: 10px;
  padding: 4px;
  gap: 0;
}

.toggle-option {
  flex: 1;
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  background: transparent;
  cursor: pointer;
  transition: all 0.2s;
}

.toggle-option:hover {
  color: #374151;
}

.toggle-option.active {
  background: white;
  color: #1e40af;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
.create-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.create-form .form-group {
  margin-bottom: 0;
}

/* Autocomplete */
.autocomplete-wrapper {
  position: relative;
  margin-bottom: 8px;
}

.selected-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #4338ca;
}

.chip-meta {
  font-size: 12px;
  color: #6366f1;
  font-weight: 400;
}

.chip-remove {
  display: flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  color: #6366f1;
  cursor: pointer;
  padding: 2px;
  border-radius: 4px;
  margin-left: auto;
}

.chip-remove:hover {
  background: #c7d2fe;
  color: #4338ca;
}

.autocomplete-dropdown--teleported {
  overflow-y: auto;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}

.autocomplete-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  cursor: pointer;
  transition: background 0.1s;
  gap: 12px;
}

.autocomplete-item:hover {
  background: #f0f4ff;
}

.autocomplete-item.is-child {
  font-size: 13px;
}

.ac-name {
  font-weight: 500;
  color: #1e293b;
  font-size: 14px;
}

.ac-meta {
  color: #94a3b8;
  font-size: 12px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 200px;
}

.autocomplete-empty {
  padding: 12px 14px;
  color: #94a3b8;
  font-size: 13px;
  text-align: center;
}

.autocomplete-hint {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  padding: 6px 14px;
  color: #94a3b8;
  font-size: 12px;
  background: white;
  border: 1px solid #e5e7eb;
  border-top: none;
  border-radius: 0 0 8px 8px;
}

.assign-dept-hint {
  margin-top: 0;
  margin-bottom: 8px;
}

</style>
