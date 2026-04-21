<template>
  <div class="support-requests-page">
    <div class="header">
      <h1>Supportanfragen</h1>
      <p>Offene Anfragen von neuen Benutzern ohne gefundene Abteilung.</p>
    </div>

    <div class="actions">
      <button class="btn btn-sm support-tab-btn" :class="{ active: activeTab === 'pending' }" @click="activeTab = 'pending'">
        Offen
      </button>
      <button class="btn btn-sm support-tab-btn" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">
        History
      </button>
      <button class="btn btn-sm" :disabled="loading" @click="loadRequests">
        {{ loading ? 'Lade...' : 'Aktualisieren' }}
      </button>
    </div>
    <p v-if="activeTab === 'history'" class="tab-hint">
      Hier erscheinen nur <strong>abgeschlossene</strong> Anfragen (Status <code>assigned</code> oder <code>rejected</code> in der
      Datenbank). Noch offene Einträge (<code>pending</code>) stehen unter „Offen“.
    </p>

    <div v-if="error" class="error">{{ error }}</div>

    <div v-if="!loading && requests.length === 0" class="empty">
      {{ activeTab === 'pending' ? 'Keine offenen Supportanfragen.' : 'Keine bearbeiteten Supportanfragen.' }}
    </div>

    <div v-else class="list">
      <div v-for="req in requests" :key="req.id" class="card">
        <div class="title">{{ req.requested_department_name }}</div>
        <div class="meta">
          <span>{{ req.name }}</span>
          <span v-if="req.email"> · {{ req.email }}</span>
        </div>
        <div v-if="req.requested_organisation_id" class="meta">
          Organisation: {{ organisations.find(o => o.id === req.requested_organisation_id)?.name || req.requested_organisation_id }}
        </div>
        <div v-if="req.requested_affiliation" class="meta">
          Zugehörigkeit: {{ req.requested_affiliation }}
        </div>
        <div v-if="req.requested_parent_department_name" class="meta">
          Übergeordnete Abteilung: {{ req.requested_parent_department_name }}
        </div>
        <div v-if="req.message" class="message">{{ req.message }}</div>
        <div class="meta">Anfrage am {{ formatDate(req.created_at) }}</div>
        <div v-if="activeTab === 'history'" class="meta">
          Status:
          {{
            req.status === 'assigned'
              ? 'An Department zugeordnet'
              : req.status === 'rejected'
                ? 'Abgelehnt'
                : 'Offen'
          }}
          <span v-if="req.assigned_department_name"> · Ziel: {{ req.assigned_department_name }}</span>
          <span v-if="req.reviewed_by_name"> · durch {{ req.reviewed_by_name }}</span>
          <span v-if="req.updated_at"> · {{ formatDate(req.updated_at) }}</span>
        </div>
        <div v-if="activeTab === 'pending'" class="row-actions">
          <template v-if="canAssignSupportRequests">
            <button
              class="btn btn-success btn-sm"
              :disabled="loading"
              @click="openAssignModal(req)"
            >
              Zuordnen...
            </button>
          </template>
          <button class="btn btn-danger btn-sm" :disabled="loading" @click="decide(req.id, 'rejected')">Ablehnen</button>
        </div>
      </div>
    </div>

    <div v-if="assignModalOpen" class="modal-overlay">
      <div class="modal-dialog support-modal-dialog">
        <div class="modal-header">
          <h2>Supportanfrage zuordnen</h2>
          <button type="button" class="modal-close" @click="closeAssignModal">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <div class="meta"><strong>User:</strong> {{ selectedRequest?.name || '-' }}</div>
            <div class="meta"><strong>Gesucht:</strong> {{ selectedRequest?.requested_department_name || '-' }}</div>
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
              Department suchen
            </button>
            <button
              type="button"
              class="toggle-option"
              :class="{ active: createDepartmentMode }"
              @click="switchToCreateMode"
            >
              Neues Department
            </button>
          </div>
          </div>

          <div v-if="!createDepartmentMode" class="form-group">
            <label class="form-label">Bestehendes Department auswählen</label>
            <p
              v-if="selectedRequest?.requested_department_name && selectedRequest.requested_department_name !== 'Unbekannte Abteilung'"
              class="form-hint assign-dept-hint"
            >
              Vorschlagsliste sortiert nach Nähe zu „{{ selectedRequest.requested_department_name }}“
              <template v-if="selectedRequest.requested_organisation_id"> (nur Departments dieser Organisation)</template>.
            </p>
          <div class="autocomplete-wrapper">
            <div v-if="selectedDepartment" class="selected-chip">
              <span>{{ selectedDepartment.name }}</span>
              <span v-if="selectedDepartment.parent_id" class="chip-meta"> (Unterabteilung)</span>
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
              placeholder="Department suchen oder auswählen..."
              @focus="showDepartmentDropdown = true"
              @input="showDepartmentDropdown = true"
              @blur="handleDepartmentBlur"
              ref="departmentSearchInput"
            />
            <div v-if="showDepartmentDropdown" class="autocomplete-dropdown">
              <div
                v-for="d in filteredAssignableDepartments"
                :key="d.id"
                class="autocomplete-item"
                :class="{ 'is-child': d.parent_id }"
                :style="d.parent_id ? { paddingLeft: `${(getDepartmentLevel(d) * 12) + 12}px` } : {}"
                @mousedown.prevent="selectDepartment(d)"
              >
                <span class="ac-name">{{ d.name }}</span>
                <span v-if="d.parent_id" class="ac-meta">› {{ getParentPath(d) }}</span>
              </div>
              <div v-if="filteredAssignableDepartments.length === 0" class="autocomplete-empty">
                Kein Treffer
              </div>
            </div>
          </div>
          <div v-if="selectedDepartment" class="form-group">
            <label class="form-label">Rolle im Department</label>
            <select v-model="assignRole" class="form-select">
              <option v-for="r in ASSIGN_ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
            <p class="form-hint">Standard: Benutzer. Wenn kein mw/dc im Department existiert, wird automatisch mw zugewiesen.</p>
          </div>
          <div v-if="assignError" class="error-message">{{ assignError }}</div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" @click="closeAssignModal">Abbrechen</button>
            <button
              type="button"
              class="btn-primary"
              :disabled="assignLoading || !selectedDepartment || !selectedAssignmentDepartmentId || !selectedRequest"
              @click="assignSelectedDepartment"
            >
              {{ assignLoading ? 'Wird zugeordnet...' : 'Zuordnen' }}
            </button>
          </div>
          </div>

          <div v-else class="form-group">
          <label class="form-label">Neues Department erstellen</label>
          <div class="create-form">
            <input
              v-model="newDepartmentName"
              class="form-input"
              placeholder="Name neues Department"
            />
            <select v-model="newDepartmentOrganisationId" class="form-select" @change="newDepartmentParentId = ''">
              <option value="" disabled hidden>&nbsp;</option>
              <option v-for="org in organisations" :key="org.id" :value="org.id">{{ org.name }}</option>
            </select>
            <div v-if="newDepartmentOrganisationId" class="form-group">
              <label class="form-label">Übergeordnetes Department (optional)</label>
              <div class="tree-select-container">
                <div class="tree-select-header">
                  <span>Wählen Sie ein übergeordnetes Department aus:</span>
                  <button
                    type="button"
                    class="btn-clear-parent"
                    :class="{ active: !newDepartmentParentId }"
                    @click="newDepartmentParentId = ''"
                  >
                    Kein Parent (Haupt-Department)
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
                <p class="form-hint">Lassen Sie leer für ein Haupt-Department.</p>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Rolle im Department</label>
              <select v-model="assignRole" class="form-select">
                <option v-for="r in ASSIGN_ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
              <p class="form-hint">Standard: Benutzer. Bei neuem Department ohne mw/dc wird automatisch mw zugewiesen.</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="closeAssignModal">Abbrechen</button>
              <button
                type="button"
                class="btn-primary"
                :disabled="loading || !newDepartmentName.trim() || !newDepartmentOrganisationId || !selectedRequest"
                @click="createAndAssignDepartment"
              >
                Department erstellen + zuordnen
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
import { computed, onMounted, ref, watch } from 'vue'
import { departmentNameMatchScore, levenshtein } from '@/utils/stringSimilarity'
import { useRoute } from 'vue-router'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { createDepartment, departmentHasManager, getDepartments, type Department } from '@/api/departments'
import { getOrganisations, type Organisation } from '@/api/organisations'
import { filterOrganisationsForUserPickers } from '@/utils/organisationUserPicker'
import {
  assignAdminJoinRequest,
  decideAdminJoinRequest,
  getAdminJoinRequestHistory,
  getPendingAdminJoinRequests,
  type PendingAdminJoinRequest
} from '@/api/joinRequests'

const route = useRoute()
const toast = useToast()
const authStore = useAuthStore()
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
  if (authStore.userRoles.includes('ROLE_SUPERADMIN')) return true
  if (
    authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
    authStore.userRoles.includes('ROLE_SUBORGCHEF')
  ) {
    return true
  }
  return (authStore.currentDepartmentRole || '').toLowerCase() === 'mw' && !!departmentId.value
})
const assignModalOpen = ref(false)
const selectedRequest = ref<PendingAdminJoinRequest | null>(null)
const selectedAssignmentDepartmentId = ref('')
const selectedDepartment = ref<Department | null>(null)
const departmentSearchQuery = ref('')
const showDepartmentDropdown = ref(false)
const departmentSearchInput = ref<HTMLInputElement | null>(null)
const assignLoading = ref(false)
const assignError = ref<string | null>(null)
const createDepartmentMode = ref(false)
const newDepartmentName = ref('')
const newDepartmentOrganisationId = ref('')
const newDepartmentParentId = ref('')
const assignRole = ref<'u' | 'l1' | 'l2' | 'l3' | 'dc' | 'mw'>('u')

const ASSIGN_ROLES: { value: string; label: string }[] = [
  { value: 'u', label: 'Benutzer (u)' },
  { value: 'l3', label: 'Leiter Stufe 3 (l3)' },
  { value: 'l2', label: 'Leiter Stufe 2 (l2)' },
  { value: 'l1', label: 'Leiter Stufe 1 (l1)' },
  { value: 'dc', label: 'Departmentchef (dc)' },
  { value: 'mw', label: 'Materialchef (mw)' }
]

// Verfügbare Parent-Departments als Tree (nur aus gewählter Organisation)
const availableParentDepartmentsTree = computed(() => {
  const orgId = newDepartmentOrganisationId.value
  if (!orgId) return []
  const depts = assignableDepartments.value.filter((d) => d.organisation_id === orgId)
  interface TreeDept { id: string; name: string; level: number }
  function buildTree(parentId: string | null, level: number): TreeDept[] {
    const children = depts.filter((d) => (d.parent_id ?? null) === parentId)
    const result: TreeDept[] = []
    children.forEach((dept) => {
      result.push({ id: dept.id, name: dept.name, level })
      result.push(...buildTree(dept.id, level + 1))
    })
    return result
  }
  const main = depts.filter((d) => !d.parent_id)
  const tree: TreeDept[] = []
  main.forEach((d) => {
    tree.push({ id: d.id, name: d.name, level: 0 })
    tree.push(...buildTree(d.id, 1))
  })
  return tree
})

/** Departments derselben Organisation wie die Anfrage (falls gesetzt), sonst alle. */
const departmentPoolForAssign = computed(() => {
  const orgId = selectedRequest.value?.requested_organisation_id
  if (!orgId) return assignableDepartments.value
  return assignableDepartments.value.filter((d) => d.organisation_id === orgId)
})

const filteredAssignableDepartments = computed(() => {
  const rawReq = selectedRequest.value?.requested_department_name || ''
  const requestedNeedle = rawReq === 'Unbekannte Abteilung' ? '' : rawReq.trim()
  const q = departmentSearchQuery.value.trim()
  const sortNeedle = q || requestedNeedle
  let list = departmentPoolForAssign.value

  if (q) {
    const ql = q.toLowerCase()
    list = list.filter((d) => {
      const dn = d.name.toLowerCase()
      if (dn.includes(ql)) return true
      const maxLen = Math.max(dn.length, ql.length)
      if (maxLen === 0) return false
      return levenshtein(dn, ql) <= Math.min(4, Math.ceil(maxLen / 3))
    })
  }

  if (sortNeedle) {
    return [...list]
      .sort(
        (a, b) =>
          departmentNameMatchScore(b.name, sortNeedle) -
          departmentNameMatchScore(a.name, sortNeedle)
      )
      .slice(0, 30)
  }

  return [...list].sort((a, b) => a.name.localeCompare(b.name, 'de-CH')).slice(0, 25)
})

function getDepartmentLevel(d: Department): number {
  if (!d.parent_id) return 0
  const parent = assignableDepartments.value.find((x) => x.id === d.parent_id)
  return parent ? 1 + getDepartmentLevel(parent) : 0
}

function getParentPath(d: Department): string {
  if (!d.parent_id) return ''
  const parent = assignableDepartments.value.find((x) => x.id === d.parent_id)
  return parent ? `${getParentPath(parent)}${getParentPath(parent) ? ' › ' : ''}${parent.name}` : ''
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('de-CH')
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
    error.value = err?.response?.data?.error || 'Supportanfragen konnten nicht geladen werden'
  } finally {
    loading.value = false
  }
}

async function loadAssignableDepartments() {
  try {
    assignableDepartments.value = await getDepartments()
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

async function decide(id: string, status: 'rejected') {
  loading.value = true
  error.value = null
  try {
    await decideAdminJoinRequest(departmentId.value, id, status)
    await loadRequests()
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Anfrage konnte nicht bearbeitet werden'
  } finally {
    loading.value = false
  }
}

async function openAssignModal(req: PendingAdminJoinRequest) {
  selectedRequest.value = req
  selectedAssignmentDepartmentId.value = ''
  selectedDepartment.value = null
  departmentSearchQuery.value = ''
  showDepartmentDropdown.value = false
  assignError.value = null
  createDepartmentMode.value = false
  newDepartmentOrganisationId.value = ''
  newDepartmentParentId.value = ''
  newDepartmentName.value = req.requested_department_name === 'Unbekannte Abteilung'
    ? ''
    : req.requested_department_name
  assignModalOpen.value = true
  assignRole.value = 'u'
  error.value = null
  await loadAssignableDepartments()
}

function closeAssignModal() {
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

function handleDepartmentBlur() {
  setTimeout(() => { showDepartmentDropdown.value = false }, 200)
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
    toast.success(`Benutzer wurde dem Department zugeordnet (Rolle: ${res.assigned_role}).`)
    if (res.role_forced_to_mw_warning) {
      toast.warning(res.role_forced_to_mw_warning)
    }
    closeAssignModal()
    await loadRequests()
  } catch (err: any) {
    const msg = err?.response?.data?.error || 'Zuordnung konnte nicht gespeichert werden'
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
    const msg = err?.response?.data?.error || 'Department konnte nicht erstellt werden'
    error.value = msg
    toast.error(msg)
  } finally {
    loading.value = false
  }
}

onMounted(loadRequests)
onMounted(loadOrganisations)
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
.title { font-weight: 700; color: #111827; }
.meta { font-size: 13px; color: #6b7280; margin-top: 3px; }
.message { margin-top: 8px; color: #374151; }
.row-actions { margin-top: 10px; display: flex; gap: 8px; }
/* Modal overlay/header/body/footer base uses shared ui/modals.css */
.support-modal-dialog {
  width: min(500px, calc(100vw - 48px));
  max-height: calc(100vh - 48px);
  padding: 0;
  overflow: hidden;
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

.autocomplete-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-top: none;
  border-radius: 0 0 8px 8px;
  box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.15);
  z-index: 50;
  max-height: 240px;
  overflow-y: auto;
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
