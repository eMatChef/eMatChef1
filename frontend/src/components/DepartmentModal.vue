<template>
  <div v-if="isOpen" class="modal-overlay">
    <div class="modal-dialog department-modal-dialog">
      <div class="modal-header">
        <h2>{{ isEdit ? 'Department bearbeiten' : 'Neues Department hinzufügen' }}</h2>
        <button @click="close" class="modal-close">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path
              d="M15 5L5 15M5 5L15 15"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
            />
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <!-- Department Name -->
          <div class="form-group">
            <label for="department-name" class="form-label">Name *</label>
            <input
              id="department-name"
              v-model="formData.name"
              type="text"
              class="form-input"
              placeholder="Department Name"
              required
            />
          </div>

          <!-- Organisation Auswahl -->
          <div class="form-group">
            <label for="organisation" class="form-label">Organisation *</label>
            <select
              id="organisation"
              v-model="formData.organisationId"
              class="form-select"
              required
              @change="onOrganisationChange"
            >
              <option value="" disabled hidden>&nbsp;</option>
              <option
                v-for="org in organisations"
                :key="org.id"
                :value="org.id"
              >
                {{ org.name }}
              </option>
            </select>
          </div>

          <!-- Parent Department Auswahl (optional, nur wenn Organisation gewählt) -->
          <div v-if="formData.organisationId" class="form-group">
            <label class="form-label">Übergeordnetes Department (optional)</label>
            <div class="tree-select-container">
              <div class="tree-select-header">
                <span>Wählen Sie ein übergeordnetes Department aus:</span>
                <button
                  type="button"
                  @click="formData.parentId = null"
                  class="btn-clear-parent"
                  :class="{ active: formData.parentId === null }"
                >
                  Kein Parent (Haupt-Department)
                </button>
              </div>
              <div class="tree-select-content">
                <div
                  v-for="dept in availableParentDepartmentsTree"
                  :key="dept.id"
                  class="tree-select-item"
                  :class="{ 
                    selected: formData.parentId === dept.id,
                    disabled: isEdit && dept.id === props.department?.id
                  }"
                  :style="{ paddingLeft: `${dept.level * 20 + 12}px` }"
                  @click="selectParentDepartment(dept)"
                >
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="folder-icon">
                    <path
                      d="M2 4C2 3.44772 2.44772 3 3 3H6.58579C6.851 3 7.10536 3.10536 7.29289 3.29289L8.70711 4.70711C8.89464 4.89464 9.149 5 9.41421 5H13C13.5523 5 14 5.44772 14 6V12C14 12.5523 13.5523 13 13 13H3C2.44772 13 2 12.5523 2 12V4Z"
                      fill="currentColor"
                    />
                  </svg>
                  <span>{{ dept.name }}</span>
                </div>
              </div>
            </div>
            <p class="form-hint">
              Lassen Sie leer für ein Haupt-Department. Sie können auch ein Unter-Department als Parent wählen.
            </p>
          </div>

          <!-- User-Verwaltung (nur im Edit-Modus) -->
          <div v-if="isEdit && props.department?.id" class="form-group user-management-section">
            <label class="form-label">User in diesem Department</label>

            <div v-if="isMembersLoading" class="user-management-hint">Lade Benutzer...</div>

            <template v-else>
              <div v-if="members.length === 0" class="user-management-hint">
                Noch keine Benutzer im Department.
              </div>
              <div v-else class="members-table-wrap">
                <table class="members-table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>E-Mail</th>
                      <th>Rolle</th>
                      <th>Primär</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="member in members" :key="member.user_id">
                      <td>{{ formatMemberName(member) }}</td>
                      <td>{{ member.email }}</td>
                      <td>
                        <select v-model="member.role" class="form-select small-select">
                          <option v-for="role in roleOptions" :key="role.value" :value="role.value">
                            {{ role.label }}
                          </option>
                        </select>
                      </td>
                      <td>
                        <input type="checkbox" v-model="member.is_primary" />
                      </td>
                      <td class="member-actions">
                        <button
                          type="button"
                          class="btn-inline"
                          :disabled="memberActionLoading"
                          @click="saveMember(member)"
                        >
                          Speichern
                        </button>
                        <button
                          v-if="!isCurrentUser(member)"
                          type="button"
                          class="btn-inline btn-inline-danger"
                          :disabled="memberActionLoading"
                          @click="deleteMember(member)"
                        >
                          Entfernen
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="add-member-box">
                <div class="add-member-title">Benutzer hinzufügen</div>
                <div class="add-member-search-row">
                  <div class="autocomplete-wrapper add-member-search">
                    <div v-if="selectedAvailableUser" class="selected-user-chip">
                      <span>{{ formatAvailableUserName(selectedAvailableUser) }} ({{ selectedAvailableUser.email }})</span>
                      <button type="button" class="chip-remove" @click="clearSelectedAvailableUser">×</button>
                    </div>
                    <div v-else>
                      <input
                        v-model="newMemberSearchQuery"
                        type="text"
                        class="form-input"
                        placeholder="Benutzer suchen (Name, Vorname, Spitzname, E-Mail)..."
                        @focus="showAvailableDropdown = true"
                        @blur="handleAvailableBlur"
                      />
                      <div
                        v-if="showAvailableDropdown && newMemberSearchQuery.trim().length >= 2 && filteredAvailableUsers.length > 0"
                        class="autocomplete-dropdown"
                      >
                        <div
                          v-for="user in filteredAvailableUsers"
                          :key="user.id"
                          class="autocomplete-item"
                          @mousedown.prevent="selectAvailableUser(user)"
                        >
                          <span class="ac-name">{{ formatAvailableUserName(user) }}</span>
                          <span class="ac-email">{{ user.email }}</span>
                        </div>
                      </div>
                      <div
                        v-else-if="showAvailableDropdown && newMemberSearchQuery.trim().length >= 2 && filteredAvailableUsers.length === 0"
                        class="autocomplete-dropdown"
                      >
                        <div class="autocomplete-empty">Kein Treffer</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="add-member-controls-row">
                  <select v-model="newMemberRole" class="form-select small-select">
                    <option v-for="role in roleOptions" :key="role.value" :value="role.value">
                      {{ role.value.toUpperCase() }}
                    </option>
                  </select>
                  <label class="checkbox-inline">
                    <input type="checkbox" v-model="newMemberPrimary" />
                    Primär
                  </label>
                  <button
                    type="button"
                    class="btn-inline"
                    :disabled="!newMemberUserId || memberActionLoading"
                    @click="addMember"
                  >
                    Hinzufügen
                  </button>
                </div>
              </div>
            </template>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="error-message">
            {{ error }}
          </div>

          <!-- Buttons -->
          <div class="modal-footer">
            <button type="button" @click="close" class="btn-secondary">
              Abbrechen
            </button>
            <button type="submit" class="btn-primary" :disabled="isSubmitting">
              {{ isSubmitting ? 'Wird gespeichert...' : (isEdit ? 'Speichern' : 'Hinzufügen') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import {
  createDepartment,
  updateDepartment,
  getDepartments,
  getDepartmentMembers,
  getAvailableUsersForDepartment,
  updateDepartmentMember,
  removeDepartmentMember,
  addDepartmentMember,
  type Department,
  type DepartmentMember,
  type AvailableUser
} from '@/api/departments'
import { getOrganisations, type Organisation } from '@/api/organisations'
import {
  filterOrganisationsForUserPickers,
  memberOrganisationIdsFromUserDepartments,
  prepareOrganisationsForOrgSubAdminList,
  sortOrganisationsMembersFirst
} from '@/utils/organisationUserPicker'

interface Props {
  isOpen: boolean
  department?: Department | null
  preselectedOrganisationId?: string | null
  preselectedParentId?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  department: null,
  preselectedOrganisationId: null,
  preselectedParentId: null
})

const emit = defineEmits<{
  'close': []
  'saved': []
}>()

const toast = useToast()
const authStore = useAuthStore()
const isSuperAdmin = computed(() =>
  (authStore.userRoles || []).includes('ROLE_SUPERADMIN')
)
const memberOrganisationIds = computed(() =>
  memberOrganisationIdsFromUserDepartments(authStore.departments)
)
const isEdit = computed(() => !!props.department)
const isSubmitting = ref(false)
const error = ref<string | null>(null)
const organisations = ref<Organisation[]>([])
const allDepartments = ref<Department[]>([])
const members = ref<DepartmentMember[]>([])
const availableUsers = ref<AvailableUser[]>([])
const isMembersLoading = ref(false)
const memberActionLoading = ref(false)
const newMemberUserId = ref('')
const newMemberRole = ref<'mw' | 'dc' | 'l1' | 'l2' | 'l3' | 'u'>('u')
const newMemberPrimary = ref(false)
const newMemberSearchQuery = ref('')
const showAvailableDropdown = ref(false)
const selectedAvailableUser = ref<AvailableUser | null>(null)

const roleOptions: Array<{ value: 'mw' | 'dc' | 'l1' | 'l2' | 'l3' | 'u'; label: string }> = [
  { value: 'mw', label: 'MW - Materialchef' },
  { value: 'dc', label: 'DC - Dep.chef' },
  { value: 'l1', label: 'L1 - Leader 1' },
  { value: 'l2', label: 'L2 - Leader 2' },
  { value: 'l3', label: 'L3 - Leader 3' },
  { value: 'u', label: 'U - Mitglied' }
]

const filteredAvailableUsers = computed(() => {
  const query = newMemberSearchQuery.value.trim().toLowerCase()
  if (query.length < 2) return []
  return availableUsers.value
    .filter((user) => {
      return (
        user.name.toLowerCase().includes(query) ||
        (user.first_name || '').toLowerCase().includes(query) ||
        (user.last_name || '').toLowerCase().includes(query) ||
        (user.nickname || '').toLowerCase().includes(query) ||
        user.email.toLowerCase().includes(query)
      )
    })
    .slice(0, 10)
})

function joinNonEmpty(values: Array<string | null | undefined>, separator: string): string {
  return values.map((v) => (v || '').trim()).filter(Boolean).join(separator)
}

function formatAvailableUserName(user: AvailableUser): string {
  const legalName = joinNonEmpty([user.first_name, user.last_name], ' ')
  const nickname = (user.nickname || '').trim()
  if (legalName && nickname) return `${legalName} (${nickname})`
  if (legalName) return legalName
  if (nickname) return nickname
  return user.name
}

function formatMemberName(member: DepartmentMember): string {
  const legalName = joinNonEmpty([member.first_name, member.last_name], ' ')
  const nickname = (member.nickname || '').trim()
  if (legalName && nickname) return `${legalName} (${nickname})`
  if (legalName) return legalName
  if (nickname) return nickname
  return member.name
}

function isCurrentUser(member: DepartmentMember): boolean {
  const uid = authStore.userId
  return uid !== null && member.user_id === uid
}

const formData = ref({
  name: '',
  organisationId: '',
  parentId: null as string | null
})

// Verfügbare Parent-Departments als Tree-Struktur
const availableParentDepartmentsTree = computed(() => {
  if (!formData.value.organisationId) {
    return []
  }
  
  const currentDeptId = isEdit.value ? props.department?.id : null
  
  // Funktion um zu prüfen ob ein Department ein Nachkomme des aktuellen Departments ist
  const isDescendant = (deptId: string): boolean => {
    if (!currentDeptId) return false
    const dept = allDepartments.value.find(d => d.id === deptId)
    if (!dept || !dept.parent_id) return false
    if (dept.parent_id === currentDeptId) return true
    return isDescendant(dept.parent_id)
  }
  
  // Filtere verfügbare Departments
  const available = allDepartments.value.filter(dept => 
    dept.organisation_id === formData.value.organisationId &&
    dept.id !== currentDeptId &&
    !isDescendant(dept.id)
  )
  
  // Erstelle hierarchische Tree-Struktur
  const mainDepts = available.filter(d => !d.parent_id)
  const subDepts = available.filter(d => d.parent_id)
  
  interface TreeDept {
    id: string
    name: string
    level: number
  }
  
  function buildTree(parentId: string | null, level: number): TreeDept[] {
    const children = available.filter(d => d.parent_id === parentId)
    const result: TreeDept[] = []
    
    children.forEach(dept => {
      result.push({
        id: dept.id,
        name: dept.name,
        level
      })
      // Rekursiv Unter-Departments hinzufügen
      result.push(...buildTree(dept.id, level + 1))
    })
    
    return result
  }
  
  // Baue Tree auf, beginnend mit Haupt-Departments
  const tree: TreeDept[] = []
  mainDepts.forEach(dept => {
    tree.push({
      id: dept.id,
      name: dept.name,
      level: 0
    })
    tree.push(...buildTree(dept.id, 1))
  })
  
  return tree
})

function selectParentDepartment(dept: { id: string }) {
  if (isEdit.value && dept.id === props.department?.id) {
    return // Kann nicht sein eigener Parent sein
  }
  formData.value.parentId = dept.id
}

// Watch für Department-Änderungen (Edit-Modus) und vorausgewählte Organisation/Parent
watch(
  () => [props.department, props.preselectedOrganisationId, props.preselectedParentId],
  (tuple) => {
    const dept = tuple[0] as Department | null | undefined
    const preselOrgId = tuple[1] as string | null | undefined
    const preselParentId = tuple[2] as string | null | undefined
    if (dept) {
      formData.value = {
        name: dept.name,
        organisationId: dept.organisation_id,
        parentId: dept.parent_id || null
      }
    } else {
      formData.value = {
        name: '',
        organisationId: preselOrgId || '',
        parentId: preselParentId || null
      }
    }
  },
  { immediate: true }
)

function onOrganisationChange() {
  // Parent zurücksetzen wenn Organisation geändert wird
  formData.value.parentId = null
}

// Watch für Modal-Öffnung
watch(() => props.isOpen, async (open) => {
  if (open) {
    error.value = null
    // Organisationen und Departments laden
    try {
      const [rawOrgs, depts] = await Promise.all([getOrganisations(), getDepartments()])
      allDepartments.value = depts
      const picked = filterOrganisationsForUserPickers(rawOrgs)
      let list = prepareOrganisationsForOrgSubAdminList(picked, {
        isSuperAdmin: isSuperAdmin.value,
        memberOrganisationIds: memberOrganisationIds.value
      })
      const editOrgId = props.department?.organisation_id
      if (editOrgId && !list.some((o) => o.id === editOrgId)) {
        const missing =
          picked.find((o) => o.id === editOrgId) || rawOrgs.find((o) => o.id === editOrgId)
        if (missing) {
          list = sortOrganisationsMembersFirst(
            [missing, ...list.filter((o) => o.id !== missing.id)],
            memberOrganisationIds.value
          )
        }
      }
      organisations.value = list
    } catch (err: any) {
      error.value = 'Fehler beim Laden der Daten'
    }

    if (isEdit.value && props.department?.id) {
      await loadMembersData(props.department.id)
    }
  } else {
    members.value = []
    availableUsers.value = []
    newMemberUserId.value = ''
    newMemberRole.value = 'u'
    newMemberPrimary.value = false
    newMemberSearchQuery.value = ''
    selectedAvailableUser.value = null
    showAvailableDropdown.value = false
  }
})

watch(() => props.department?.id, async (departmentId) => {
  if (!props.isOpen || !isEdit.value || !departmentId) return
  await loadMembersData(departmentId)
})

async function loadMembersData(departmentId: string) {
  isMembersLoading.value = true
  try {
    const [membersRes, availableRes] = await Promise.all([
      getDepartmentMembers(departmentId),
      getAvailableUsersForDepartment(departmentId)
    ])
    members.value = membersRes
    availableUsers.value = availableRes
    if (newMemberUserId.value) {
      const selected = availableRes.find((u) => u.id === newMemberUserId.value) || null
      selectedAvailableUser.value = selected
      if (!selected) {
        newMemberUserId.value = ''
      }
    }
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Fehler beim Laden der Department-Benutzer')
  } finally {
    isMembersLoading.value = false
  }
}

async function saveMember(member: DepartmentMember) {
  if (!props.department?.id || memberActionLoading.value) return
  memberActionLoading.value = true
  try {
    await updateDepartmentMember(props.department.id, member.user_id, {
      role: member.role,
      is_primary: member.is_primary
    })
    toast.success('Benutzer aktualisiert')
    await loadMembersData(props.department.id)
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Fehler beim Aktualisieren')
  } finally {
    memberActionLoading.value = false
  }
}

async function deleteMember(member: DepartmentMember) {
  if (!props.department?.id || memberActionLoading.value) return
  if (isCurrentUser(member)) {
    toast.error('Du kannst dich hier nicht selbst aus dem Department entfernen.')
    return
  }
  if (!window.confirm(`${member.name} aus dem Department entfernen?`)) return
  memberActionLoading.value = true
  try {
    await removeDepartmentMember(props.department.id, member.user_id)
    toast.success('Benutzer entfernt')
    await loadMembersData(props.department.id)
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Fehler beim Entfernen')
  } finally {
    memberActionLoading.value = false
  }
}

async function addMember() {
  if (!props.department?.id || !newMemberUserId.value || memberActionLoading.value) return
  memberActionLoading.value = true
  try {
    await addDepartmentMember(props.department.id, {
      user_id: newMemberUserId.value,
      role: newMemberRole.value,
      is_primary: newMemberPrimary.value
    })
    toast.success('Benutzer hinzugefügt')
    newMemberUserId.value = ''
    newMemberRole.value = 'u'
    newMemberPrimary.value = false
    newMemberSearchQuery.value = ''
    selectedAvailableUser.value = null
    showAvailableDropdown.value = false
    await loadMembersData(props.department.id)
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Fehler beim Hinzufügen')
  } finally {
    memberActionLoading.value = false
  }
}

function selectAvailableUser(user: AvailableUser) {
  selectedAvailableUser.value = user
  newMemberUserId.value = user.id
  newMemberSearchQuery.value = ''
  showAvailableDropdown.value = false
}

function clearSelectedAvailableUser() {
  selectedAvailableUser.value = null
  newMemberUserId.value = ''
  newMemberSearchQuery.value = ''
}

function handleAvailableBlur() {
  setTimeout(() => {
    showAvailableDropdown.value = false
  }, 200)
}

async function handleSubmit() {
  if (!formData.value.name || !formData.value.organisationId) {
    error.value = 'Bitte füllen Sie alle Felder aus'
    return
  }

  try {
    isSubmitting.value = true
    error.value = null

    if (isEdit.value && props.department) {
      await updateDepartment(props.department.id, {
        name: formData.value.name,
        organisation_id: formData.value.organisationId,
        parent_id: formData.value.parentId || null
      })
    } else {
      await createDepartment({
        name: formData.value.name,
        organisation_id: formData.value.organisationId,
        parent_id: formData.value.parentId || null
      })
    }

    emit('saved')
    close()
  } catch (err: any) {
    const msg = err.response?.data?.error || 'Fehler beim Speichern des Departments'
    error.value = msg
    toast.error(msg)
  } finally {
    isSubmitting.value = false
  }
}

function close() {
  emit('close')
  formData.value = { name: '', organisationId: '', parentId: null }
  error.value = null
  members.value = []
  availableUsers.value = []
  newMemberUserId.value = ''
  newMemberRole.value = 'u'
  newMemberPrimary.value = false
  newMemberSearchQuery.value = ''
  selectedAvailableUser.value = null
  showAvailableDropdown.value = false
}
</script>

<style scoped>
/* Modal overlay/dialog/header/body/footer base uses shared ui/modals.css */
.department-modal-dialog {
  width: min(1180px, calc(100vw - 48px));
  max-height: calc(100vh - 48px);
  padding: 0;
  overflow: hidden;
}

.modal-header h2 {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

/* Form group/input/select base uses shared ui/forms.css */

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 8px;
}

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

.tree-select-item.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.tree-select-item.disabled:hover {
  background: transparent;
}

.tree-select-item .folder-icon {
  color: #6b7280;
  flex-shrink: 0;
}

.tree-select-item.selected .folder-icon {
  color: #3b82f6;
}

.user-management-section {
  border-top: 1px solid #e5e7eb;
  margin-top: 20px;
  padding-top: 16px;
}

.user-management-hint {
  padding: 10px 12px;
  background: #f9fafb;
  border-radius: 6px;
  color: #6b7280;
  font-size: 13px;
}

.members-table-wrap {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 12px;
}

.members-table {
  width: 100%;
  border-collapse: collapse;
}

.members-table th,
.members-table td {
  border-bottom: 1px solid #f1f5f9;
  padding: 8px 10px;
  font-size: 13px;
  text-align: left;
}

.members-table th {
  background: #f8fafc;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  font-size: 11px;
}

.members-table tr:last-child td {
  border-bottom: none;
}

.member-actions {
  display: flex;
  gap: 6px;
}

.btn-inline {
  border: 1px solid #d1d5db;
  background: white;
  color: #374151;
  border-radius: 6px;
  padding: 5px 8px;
  font-size: 12px;
  cursor: pointer;
}

.btn-inline:hover:not(:disabled) {
  background: #f3f4f6;
}

.btn-inline:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn-inline-danger {
  color: #b91c1c;
  border-color: #fecaca;
  background: #fff7f7;
}

.small-select {
  min-width: 120px;
}

.add-member-box {
  border: 1px dashed #d1d5db;
  border-radius: 8px;
  padding: 10px;
  background: #fcfcfd;
}

.add-member-title {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  margin-bottom: 8px;
}

.add-member-search-row {
  margin-bottom: 10px;
}

.add-member-controls-row {
  display: grid;
  grid-template-columns: 140px auto auto;
  gap: 8px;
  align-items: center;
  justify-content: end;
}

.add-member-search {
  width: 100%;
}

.checkbox-inline {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #374151;
}

.autocomplete-wrapper {
  position: relative;
}

.autocomplete-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 10px 24px -8px rgba(0, 0, 0, 0.2);
  z-index: 80;
  max-height: 220px;
  overflow-y: auto;
}

.autocomplete-item {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: center;
  padding: 9px 10px;
  cursor: pointer;
}

.autocomplete-item:hover {
  background: #f3f4f6;
}

.ac-name {
  color: #1f2937;
  font-size: 13px;
  font-weight: 500;
}

.ac-email {
  color: #6b7280;
  font-size: 12px;
  white-space: nowrap;
}

.autocomplete-empty {
  padding: 10px;
  color: #6b7280;
  text-align: center;
  font-size: 13px;
}

.selected-user-chip {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border: 1px solid #c7d2fe;
  background: #eef2ff;
  color: #3730a3;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 13px;
}

.chip-remove {
  border: none;
  background: transparent;
  color: #4338ca;
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
}

@media (max-width: 900px) {
  .add-member-controls-row {
    grid-template-columns: 1fr 1fr;
    justify-content: stretch;
  }
}
</style>
