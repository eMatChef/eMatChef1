<template>
  <div class="users-settings">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h2 class="settings-title">Benutzer</h2>
        <p class="settings-description">Benutzer verwalten und Rollen zuweisen</p>
      </div>
      <button class="btn btn-primary" @click="openAddModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="8.5" cy="7" r="4"/>
          <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
        </svg>
        Benutzer hinzufügen
      </button>
    </div>

    <!-- Stats Bar -->
    <div v-if="!isLoading && members.length > 0" class="stats-bar">
      <div class="stat-item">
        <span class="stat-value">{{ members.length }}</span>
        <span class="stat-label">Benutzer</span>
      </div>
      <div class="stat-item">
        <span class="stat-value">{{ leaderCount }}</span>
        <span class="stat-label">Leiter/Admins</span>
      </div>
      <div class="stat-item">
        <span class="stat-value">{{ memberCount }}</span>
        <span class="stat-label">Mitglieder</span>
      </div>
    </div>

    <!-- Search -->
    <div v-if="!isLoading && members.length > 3" class="search-bar">
      <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input 
        v-model="searchQuery" 
        type="text" 
        placeholder="Benutzer suchen (Name, Spitzname, Vorname, Nachname, E-Mail)..."
        class="search-input"
      />
    </div>

    <div v-if="canManagePendingInvites && !isLoading" class="pending-invites-card">
      <div class="pending-head">
        <h3>Eingeladene Mitglieder</h3>
        <span class="pending-count">{{ pendingInvites.length }}</span>
      </div>
      <p v-if="pendingInvitesError" class="pending-error">{{ pendingInvitesError }}</p>
      <p v-else-if="isLoadingPendingInvites" class="pending-muted">Einladungen werden geladen...</p>
      <ul v-else-if="pendingInvites.length > 0" class="pending-list">
        <li v-for="invite in pendingInvites" :key="invite.id" class="pending-item">
          <span>{{ invite.email }} ({{ getRoleLabel(invite.role) }})</span>
          <button class="btn btn-secondary btn-sm" @click="removePendingInviteItem(invite.id)">Loeschen</button>
        </li>
      </ul>
      <p v-else class="pending-muted">Keine offenen Einladungen vorhanden.</p>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Benutzer werden geladen...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <p class="error-message">{{ error }}</p>
      <button @click="loadMembers" class="btn btn-secondary">Erneut versuchen</button>
    </div>

    <!-- Empty State -->
    <div v-else-if="members.length === 0" class="empty-state">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="8.5" cy="7" r="4"/>
        <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
      </svg>
      <h3>Noch keine Benutzer</h3>
      <p>Fügen Sie Benutzer zu diesem Department hinzu.</p>
      <button class="btn btn-primary" @click="openAddModal()">
        Ersten Benutzer hinzufügen
      </button>
    </div>

    <!-- Users Table -->
    <div v-else class="table-wrapper">
      <table class="users-table">
        <thead>
          <tr>
            <th class="col-name" @click="toggleSort('name')">
              Name
              <span v-if="sortBy === 'name'" class="sort-indicator">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="col-email">E-Mail</th>
            <th class="col-role" @click="toggleSort('role')">
              Rolle
              <span v-if="sortBy === 'role'" class="sort-indicator">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="col-primary">Primär</th>
            <th class="col-actions"></th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="member in filteredMembers" 
            :key="member.user_id"
            class="user-row"
          >
            <!-- Name -->
            <td class="col-name">
              <div class="name-cell">
                <div class="user-avatar" :style="{ background: getAvatarColor(member.name) }">
                  {{ getInitials(member.name) }}
                </div>
                <div class="name-info">
                  <span class="user-name">{{ member.name }}</span>
                  <span v-if="member.state !== 'active'" class="state-badge inactive">{{ member.state }}</span>
                </div>
              </div>
            </td>

            <!-- Email -->
            <td class="col-email">
              <span class="email-text">{{ member.email }}</span>
            </td>

            <!-- Rolle -->
            <td class="col-role">
              <span 
                class="role-badge"
                :style="{ background: getRoleColor(member.role) + '18', color: getRoleColor(member.role) }"
              >
                <span class="role-short">{{ getRoleShort(member.role) }}</span>
                {{ getRoleLabel(member.role) }}
              </span>
            </td>

            <!-- Primär -->
            <td class="col-primary">
              <span v-if="member.is_primary" class="primary-star" title="Primäres Department">★</span>
              <span v-else class="text-muted">–</span>
            </td>

            <!-- Aktionen -->
            <td class="col-actions">
              <div class="action-buttons">
                <button 
                  class="action-btn" 
                  title="Rolle bearbeiten"
                  @click="openEditModal(member)"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
                <button 
                  class="action-btn action-btn-danger" 
                  title="Aus Department entfernen"
                  @click="handleRemove(member)"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <line x1="23" y1="11" x2="17" y2="11"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ======================================== -->
    <!-- MODAL: Benutzer hinzufügen               -->
    <!-- ======================================== -->
    <Teleport to="body">
      <div v-if="showAddModal" class="modal-overlay">
        <div class="modal-container modal-sm">
          <div class="modal-header">
            <h3>Benutzer hinzufügen</h3>
            <button class="close-btn" @click="closeAddModal">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>

          <div class="modal-body">
            <div v-if="isLoadingAvailable" class="loading-inline">
              <div class="spinner-sm"></div>
              <span>Lade verfügbare Benutzer...</span>
            </div>

            <div v-else-if="availableUsers.length === 0" class="no-users-hint">
              <p>Keine weiteren Benutzer verfügbar. Alle User sind bereits diesem Department zugewiesen.</p>
            </div>

            <template v-else>
              <div class="form-group">
                <label>Benutzer *</label>
                <div class="autocomplete-wrapper">
                  <div v-if="selectedAvailableUser" class="selected-user-chip">
                    <span>{{ selectedAvailableUser.name }} ({{ selectedAvailableUser.email }})</span>
                    <button class="chip-remove" @click="clearAvailableUser">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                      </svg>
                    </button>
                  </div>
                  <input 
                    v-else
                    v-model="userSearchQuery"
                    type="text"
                    class="form-input"
                    placeholder="Name oder E-Mail eingeben (mind. 3 Zeichen)..."
                    @focus="showUserDropdown = true"
                    @blur="handleUserSearchBlur"
                    ref="userSearchInput"
                  />
                  <div v-if="showUserDropdown && userSearchQuery.length >= 3 && filteredAvailableUsers.length > 0" class="autocomplete-dropdown">
                    <div 
                      v-for="user in filteredAvailableUsers" 
                      :key="user.id"
                      class="autocomplete-item"
                      @mousedown.prevent="selectAvailableUser(user)"
                    >
                      <span class="ac-name">{{ user.name }}</span>
                      <span class="ac-email">{{ user.email }}</span>
                    </div>
                  </div>
                  <div v-if="showUserDropdown && userSearchQuery.length >= 3 && filteredAvailableUsers.length === 0" class="autocomplete-dropdown">
                    <div class="autocomplete-empty">Kein Treffer</div>
                  </div>
                  <div v-if="showUserDropdown && userSearchQuery.length > 0 && userSearchQuery.length < 3" class="autocomplete-hint">
                    Noch {{ 3 - userSearchQuery.length }} Zeichen...
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label>Rolle</label>
                <select v-model="addForm.role" class="form-select">
                  <option v-for="(cfg, key) in assignableRoles" :key="key" :value="key">
                    {{ cfg.short }} – {{ cfg.label }}
                  </option>
                </select>
              </div>

              <div class="form-group">
                <label class="checkbox-label">
                  <input type="checkbox" v-model="addForm.is_primary" />
                  Primäres Department
                </label>
              </div>
            </template>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeAddModal">Abbrechen</button>
            <button 
              class="btn btn-primary" 
              :disabled="!addForm.user_id || isSaving"
              @click="handleAdd"
            >
              {{ isSaving ? 'Speichere...' : 'Hinzufügen' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ======================================== -->
    <!-- MODAL: Rolle bearbeiten                  -->
    <!-- ======================================== -->
    <Teleport to="body">
      <div v-if="showEditModal && editingMember" class="modal-overlay">
        <div class="modal-container modal-sm">
          <div class="modal-header">
            <h3>Rolle bearbeiten: {{ editingMember.name }}</h3>
            <button class="close-btn" @click="closeEditModal">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>

          <div class="modal-body">
            <div class="form-group">
              <label>Rolle</label>
              <select v-model="editForm.role" class="form-select">
                <option v-for="(cfg, key) in assignableRoles" :key="key" :value="key">
                  {{ cfg.short }} – {{ cfg.label }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="editForm.is_primary" />
                Primäres Department
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeEditModal">Abbrechen</button>
            <button 
              class="btn btn-primary" 
              :disabled="isSaving"
              @click="handleUpdate"
            >
              {{ isSaving ? 'Speichere...' : 'Speichern' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { deletePendingInvite, getPendingInvites, type PendingInvite } from '@/api/joinRequests'
import {
  getDepartmentMembers,
  addDepartmentMember,
  updateDepartmentMember,
  removeDepartmentMember,
  getAvailableUsersForDepartment,
  type DepartmentMember,
  type AvailableUser
} from '@/api/departments'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')

// === Department Rollen-Konfiguration ===

const DEPT_ROLES = {
  mw:  { label: 'Materialchef', short: 'MW', color: '#2563eb' },
  dc:  { label: 'Dep.chef', short: 'DC', color: '#0891b2' },
  l1:  { label: 'Leader 1', short: 'L1', color: '#10b981' },
  l2:  { label: 'Leader 2', short: 'L2', color: '#f59e0b' },
  l3:  { label: 'Leader 3', short: 'L3', color: '#ef4444' },
  u:   { label: 'Mitglied', short: 'U', color: '#6b7280' },
} as const

type DeptRoleKey = keyof typeof DEPT_ROLES

// Rollen-Hierarchie (Index = Rang, 0 = höchste)
const ROLE_HIERARCHY: DeptRoleKey[] = ['mw', 'dc', 'l1', 'l2', 'l3', 'u']

const hasGlobalAdminPrivilege = computed(() => {
  return authStore.userRoles.includes('ROLE_SUPERADMIN')
    || authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF')
    || authStore.userRoles.includes('ROLE_SUBORGCHEF')
})

const canManagePendingInvites = computed(() => {
  if (hasGlobalAdminPrivilege.value) return true
  const role = String(authStore.currentDepartmentRole || '').toLowerCase().trim()
  return ['mw', 'dc', 'sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(role)
})

// Nur Rollen die der aktuelle User vergeben darf (eigene Rolle + darunter)
const assignableRoles = computed(() => {
  // Globale Admin-Rollen dürfen alle Department-Rollen verwalten
  if (hasGlobalAdminPrivilege.value) {
    return ROLE_HIERARCHY.reduce((acc, roleKey) => {
      acc[roleKey] = DEPT_ROLES[roleKey]
      return acc
    }, {} as Partial<Record<DeptRoleKey, (typeof DEPT_ROLES)[DeptRoleKey]>>)
  }

  const myRole = (authStore.currentDepartmentRole || 'u').toLowerCase() as DeptRoleKey
  const myIndex = ROLE_HIERARCHY.indexOf(myRole)

  // Wenn Rolle nicht gefunden (z.B. 'user'), nur 'u' erlauben
  const startIndex = myIndex >= 0 ? myIndex : ROLE_HIERARCHY.length - 1

  const result: Partial<Record<DeptRoleKey, (typeof DEPT_ROLES)[DeptRoleKey]>> = {}
  for (let i = startIndex; i < ROLE_HIERARCHY.length; i++) {
    const key = ROLE_HIERARCHY[i]
    result[key] = DEPT_ROLES[key]
  }

  return result
})

function getRoleColor(role: string): string {
  return DEPT_ROLES[role as DeptRoleKey]?.color || '#6b7280'
}

function getRoleShort(role: string): string {
  return DEPT_ROLES[role as DeptRoleKey]?.short || role.toUpperCase()
}

function getRoleLabel(role: string): string {
  return DEPT_ROLES[role as DeptRoleKey]?.label || role
}

// === State ===
const members = ref<DepartmentMember[]>([])
const isLoading = ref(false)
const error = ref<string | null>(null)
const searchQuery = ref('')
const sortBy = ref<'name' | 'role'>('name')
const sortDir = ref<'asc' | 'desc'>('asc')
const pendingInvites = ref<PendingInvite[]>([])
const isLoadingPendingInvites = ref(false)
const pendingInvitesError = ref('')

// Add Modal
const showAddModal = ref(false)
const availableUsers = ref<AvailableUser[]>([])
const isLoadingAvailable = ref(false)
const isSaving = ref(false)
const addForm = ref({
  user_id: '',
  role: 'u',
  is_primary: false,
})

// Autocomplete
const userSearchQuery = ref('')
const showUserDropdown = ref(false)
const selectedAvailableUser = ref<AvailableUser | null>(null)
const userSearchInput = ref<HTMLInputElement | null>(null)
let availableSearchTimer: ReturnType<typeof setTimeout> | null = null

// Edit Modal
const showEditModal = ref(false)
const editingMember = ref<DepartmentMember | null>(null)
const editForm = ref({
  role: 'u',
  is_primary: false,
})

// === Computed ===

const leaderCount = computed(() => members.value.filter(m => !['u'].includes(m.role)).length)
const memberCount = computed(() => members.value.filter(m => m.role === 'u').length)

const filteredMembers = computed(() => {
  let result = [...members.value]

  // Suche
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(m => 
      m.name.toLowerCase().includes(q) ||
      (m.nickname || '').toLowerCase().includes(q) ||
      (m.first_name || '').toLowerCase().includes(q) ||
      (m.last_name || '').toLowerCase().includes(q) ||
      m.email.toLowerCase().includes(q)
    )
  }

  // Sortierung
  result.sort((a, b) => {
    let cmp = 0
    if (sortBy.value === 'name') {
      cmp = a.name.localeCompare(b.name)
    } else if (sortBy.value === 'role') {
      const roleOrder = Object.keys(DEPT_ROLES)
      cmp = roleOrder.indexOf(a.role) - roleOrder.indexOf(b.role)
    }
    return sortDir.value === 'asc' ? cmp : -cmp
  })

  return result
})

// === Helpers ===

function toggleSort(field: 'name' | 'role') {
  if (sortBy.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = field
    sortDir.value = 'asc'
  }
}

function getInitials(name: string): string {
  const parts = name.split(' ')
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  }
  return name.substring(0, 2).toUpperCase()
}

function getAvatarColor(name: string): string {
  const colors = ['#4f46e5', '#7c3aed', '#0891b2', '#059669', '#d97706', '#dc2626', '#db2777', '#2563eb']
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return colors[Math.abs(hash) % colors.length]
}

// === Data Loading ===

async function loadMembers() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = null
  try {
    members.value = await getDepartmentMembers(departmentId.value)
  } catch (err: any) {
    error.value = err.response?.data?.error || 'Fehler beim Laden der Benutzer'
  } finally {
    isLoading.value = false
  }
}

async function loadPendingInvites() {
  if (!departmentId.value || !canManagePendingInvites.value) {
    pendingInvites.value = []
    pendingInvitesError.value = ''
    return
  }
  isLoadingPendingInvites.value = true
  pendingInvitesError.value = ''
  try {
    pendingInvites.value = await getPendingInvites(departmentId.value)
  } catch (err: any) {
    pendingInvites.value = []
    pendingInvitesError.value = err.response?.data?.error || 'Pending Einladungen konnten nicht geladen werden.'
  } finally {
    isLoadingPendingInvites.value = false
  }
}

async function loadAvailableUsers(query?: string) {
  if (!departmentId.value) return
  isLoadingAvailable.value = true
  try {
    availableUsers.value = await getAvailableUsersForDepartment(departmentId.value, query)
  } catch (err: any) {
    console.error('Fehler beim Laden verfügbarer User:', err)
  } finally {
    isLoadingAvailable.value = false
  }
}

// Autocomplete computed
const filteredAvailableUsers = computed(() => {
  if (userSearchQuery.value.length < 3) return []
  const q = userSearchQuery.value.toLowerCase()
  return availableUsers.value.filter(u =>
    u.name.toLowerCase().includes(q) ||
    (u.nickname || '').toLowerCase().includes(q) ||
    (u.first_name || '').toLowerCase().includes(q) ||
    (u.last_name || '').toLowerCase().includes(q) ||
    u.email.toLowerCase().includes(q)
  ).slice(0, 8)
})

function selectAvailableUser(user: AvailableUser) {
  selectedAvailableUser.value = user
  addForm.value.user_id = user.id
  userSearchQuery.value = ''
  showUserDropdown.value = false
}

function clearAvailableUser() {
  selectedAvailableUser.value = null
  addForm.value.user_id = ''
  userSearchQuery.value = ''
  nextTick(() => userSearchInput.value?.focus())
}

function handleUserSearchBlur() {
  setTimeout(() => {
    showUserDropdown.value = false
  }, 200)
}

// === Add Member ===

function openAddModal() {
  // Default-Rolle = niedrigste erlaubte Rolle (letzter Eintrag in assignableRoles)
  const allowedKeys = Object.keys(assignableRoles.value) as DeptRoleKey[]
  const defaultRole = allowedKeys.length > 0 ? allowedKeys[allowedKeys.length - 1] : 'u'
  addForm.value = { user_id: '', role: defaultRole, is_primary: false }
  selectedAvailableUser.value = null
  userSearchQuery.value = ''
  showAddModal.value = true
  loadAvailableUsers()
}

function closeAddModal() {
  showAddModal.value = false
}

async function handleAdd() {
  if (!addForm.value.user_id || isSaving.value) return
  isSaving.value = true
  try {
    await addDepartmentMember(departmentId.value, {
      user_id: addForm.value.user_id,
      role: addForm.value.role,
      is_primary: addForm.value.is_primary,
    })
    closeAddModal()
    await loadMembers()
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Fehler beim Hinzufügen')
  } finally {
    isSaving.value = false
  }
}

// === Edit Member ===

function openEditModal(member: DepartmentMember) {
  editingMember.value = member
  editForm.value = {
    role: member.role,
    is_primary: member.is_primary,
  }
  showEditModal.value = true
}

function closeEditModal() {
  showEditModal.value = false
  editingMember.value = null
}

async function handleUpdate() {
  if (!editingMember.value || isSaving.value) return
  isSaving.value = true
  try {
    await updateDepartmentMember(departmentId.value, editingMember.value.user_id, {
      role: editForm.value.role,
      is_primary: editForm.value.is_primary,
    })
    closeEditModal()
    await loadMembers()
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Fehler beim Aktualisieren')
  } finally {
    isSaving.value = false
  }
}

// === Remove Member ===

async function handleRemove(member: DepartmentMember) {
  const ok = await confirm.confirm({
    title: 'Mitglied entfernen?',
    message: `${member.name} wirklich aus dem Department entfernen?`,
    confirmText: 'Entfernen',
    cancelText: 'Abbrechen',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await removeDepartmentMember(departmentId.value, member.user_id)
    await loadMembers()
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Fehler beim Entfernen')
  }
}

async function removePendingInviteItem(inviteId: string) {
  if (!departmentId.value) return
  const ok = await confirm.confirm({
    title: 'Pending Einladung loeschen?',
    message: 'Die Einladung wird entfernt und ist nicht mehr sichtbar.',
    confirmText: 'Loeschen',
    cancelText: 'Abbrechen',
    variant: 'danger',
  })
  if (!ok) return

  try {
    await deletePendingInvite(departmentId.value, inviteId)
    pendingInvites.value = pendingInvites.value.filter((entry) => entry.id !== inviteId)
    toast.success('Pending Einladung geloescht.')
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Pending Einladung konnte nicht geloescht werden.')
  }
}

// === Lifecycle ===

watch(departmentId, () => {
  loadMembers()
  loadPendingInvites()
})
watch(userSearchQuery, (value) => {
  if (!showAddModal.value || selectedAvailableUser.value) return
  if (availableSearchTimer) clearTimeout(availableSearchTimer)
  const q = value.trim()
  if (q.length > 0 && q.length < 2) return
  availableSearchTimer = setTimeout(() => {
    loadAvailableUsers(q || undefined)
  }, 220)
})

onMounted(() => {
  loadMembers()
  loadPendingInvites()
})

onUnmounted(() => {
  if (availableSearchTimer) clearTimeout(availableSearchTimer)
})
</script>

<style scoped>
/* ========================================
   Layout & Header
   ======================================== */
.users-settings {
  padding: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.settings-title {
  font-size: 24px;
  font-weight: 600;
  margin-bottom: 4px;
  color: #1e293b;
}

.settings-description {
  color: #64748b;
  font-size: 14px;
  margin: 0;
}

/* ========================================
   Buttons
   ======================================== */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

/* Buttons use shared ui/buttons.css */

/* ========================================
   Stats Bar
   ======================================== */
.stats-bar {
  display: flex;
  gap: 24px;
  padding: 14px 20px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  margin-bottom: 20px;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.stat-value {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
}

.stat-label {
  font-size: 13px;
  color: #64748b;
}

/* ========================================
   Search
   ======================================== */
.search-bar {
  position: relative;
  margin-bottom: 16px;
}

.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
}

/* Search input base uses shared ui/page-layout.css */

.pending-invites-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f9fafb;
  padding: 12px 14px;
  margin-bottom: 14px;
}

.pending-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 8px;
}

.pending-head h3 {
  margin: 0;
  font-size: 14px;
  color: #1e293b;
}

.pending-count {
  font-size: 12px;
  color: #475569;
  background: #e2e8f0;
  border-radius: 999px;
  padding: 2px 8px;
}

.pending-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pending-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  font-size: 13px;
  color: #334155;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 10px;
}

.pending-muted {
  margin: 0;
  color: #64748b;
  font-size: 13px;
}

.pending-error {
  margin: 0;
  color: #b91c1c;
  font-size: 13px;
}

/* Small button size uses shared ui/buttons.css */

/* ========================================
   Loading / Error / Empty
   ======================================== */
/* Loading/error/empty base uses shared ui/states.css */

.error-message {
  color: #dc2626;
  margin-bottom: 12px;
}

/* Empty-state title/text typography uses shared ui/states.css */

/* ========================================
   Users Table
   ======================================== */
.table-wrapper {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
}

.users-table thead th {
  padding: 12px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  cursor: default;
  user-select: none;
}

.users-table thead th[onClick] {
  cursor: pointer;
}

.users-table thead th:hover {
  color: #374151;
}

.sort-indicator {
  margin-left: 4px;
  font-size: 11px;
}

.users-table tbody td {
  padding: 12px 16px;
  font-size: 14px;
  color: #1e293b;
  border-bottom: 1px solid #f3f4f6;
}

.user-row {
  transition: background 0.15s;
}

.user-row:hover {
  background: #f9fafb;
}

/* Name Cell */
.name-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 13px;
  font-weight: 600;
  flex-shrink: 0;
}

.name-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-name {
  font-weight: 500;
}

.state-badge {
  font-size: 11px;
  padding: 1px 6px;
  border-radius: 4px;
}

.state-badge.inactive {
  background: #fee2e2;
  color: #dc2626;
}

/* Email */
.email-text {
  color: #64748b;
  font-size: 13px;
}

/* Role Badge */
.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
}

.role-short {
  font-weight: 700;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Primary */
.primary-star {
  color: #d97706;
  font-size: 18px;
}

.text-muted {
  color: #9ca3af;
}

/* Actions */
.col-actions {
  width: 90px;
}

.action-buttons {
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.15s;
}

.user-row:hover .action-buttons {
  opacity: 1;
}

.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  background: #f3f4f6;
  border-radius: 6px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s;
}

.action-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

.action-btn-danger:hover {
  background: #fee2e2;
  color: #dc2626;
}

/* ========================================
   Modal
   ======================================== */
/* Modal overlay base uses shared ui/modals.css */

.modal-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  max-height: 85vh;
}

.modal-sm {
  width: 100%;
  max-width: 480px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
}

.close-btn:hover {
  color: #374151;
  background: #f3f4f6;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
}

/* ========================================
   Form Elements
   ======================================== */
/* Form group/select base uses shared ui/forms.css */

.checkbox-label {
  display: flex !important;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--color-primary, #4f46e5);
}

.loading-inline {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #64748b;
  font-size: 13px;
  padding: 12px 0;
}

.no-users-hint {
  color: #64748b;
  font-size: 13px;
  padding: 12px;
  background: #f9fafb;
  border-radius: 8px;
}

/* ========================================
   Autocomplete
   ======================================== */
.autocomplete-wrapper {
  position: relative;
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

.ac-name {
  font-weight: 500;
  color: #1e293b;
  font-size: 14px;
}

.ac-email {
  color: #94a3b8;
  font-size: 12px;
  white-space: nowrap;
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

.selected-user-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 12px;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #4338ca;
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
  transition: all 0.15s;
}

.chip-remove:hover {
  background: #c7d2fe;
  color: #4338ca;
}

/* Form input base uses shared ui/forms.css */
</style>
