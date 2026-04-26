<template>
  <div class="admin-users-settings">
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.adminUsers.title') }}</h2>
        <p class="settings-description">{{ t('settings.adminUsers.subtitle') }}</p>
      </div>
    </div>

    <div class="toolbar">
      <div class="search-wrapper">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <input
          v-model="searchQuery"
          class="search-input"
          type="text"
          :placeholder="t('settings.adminUsers.searchPlaceholder')"
        />
      </div>
      <button class="btn btn-secondary" @click="loadUsers" :disabled="isLoading">
        {{ isLoading ? t('settings.adminUsers.loadingShort') : t('settings.adminUsers.refresh') }}
      </button>
    </div>

    <div v-if="isLoading" class="state-card">{{ t('settings.adminUsers.loading') }}</div>
    <div v-else-if="error" class="state-card state-error">
      <p>{{ error }}</p>
      <button class="btn btn-secondary" @click="loadUsers">{{ t('common.retry') }}</button>
    </div>
    <div v-else-if="filteredUsers.length === 0" class="state-card">{{ t('settings.adminUsers.empty') }}</div>

    <div v-else class="table-wrapper">
      <table class="users-table">
        <thead>
          <tr>
            <th class="sortable" @click="toggleSort('name')">
              {{ t('settings.adminUsers.columns.name') }} <span v-if="sortBy === 'name'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="sortable" @click="toggleSort('email')">
              {{ t('settings.adminUsers.columns.email') }} <span v-if="sortBy === 'email'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="sortable" @click="toggleSort('created_at')">
              {{ t('settings.adminUsers.columns.createdAt') }} <span v-if="sortBy === 'created_at'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="sortable dept-col" @click="toggleSort('departments_count')">
              {{ t('settings.adminUsers.columns.departmentsCount') }} <span v-if="sortBy === 'departments_count'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="actions-col">{{ t('settings.adminUsers.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in filteredUsers" :key="user.id">
            <td>{{ user.name }}</td>
            <td>{{ user.email }}</td>
            <td>{{ formatDate(user.created_at) }}</td>
            <td class="dept-col">{{ user.departments_count }}</td>
            <td class="actions-col">
              <button class="icon-btn" :title="t('settings.adminUsers.edit')" @click="openEditModal(user.id)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="showEditModal && editForm" class="modal-overlay">
        <div class="modal-container">
          <div class="modal-header">
            <h3>{{ t('settings.adminUsers.editUserTitle', { name: editForm.display_name }) }}</h3>
            <button class="close-btn" @click="closeEditModal">×</button>
          </div>

          <div class="modal-body">
            <div class="form-grid">
              <div class="form-group">
                <label>{{ t('settings.adminUsers.fields.firstName') }}</label>
                <input v-model="editForm.first_name" class="form-input" type="text" />
              </div>
              <div class="form-group">
                <label>{{ t('settings.adminUsers.fields.lastName') }}</label>
                <input v-model="editForm.last_name" class="form-input" type="text" />
              </div>
              <div class="form-group">
                <label>{{ t('settings.adminUsers.fields.nickname') }}</label>
                <input v-model="editForm.nickname" class="form-input" type="text" />
              </div>
              <div class="form-group">
                <label>{{ t('settings.adminUsers.fields.email') }}</label>
                <input v-model="editForm.email" class="form-input" type="email" />
              </div>
              <div class="form-group">
                <label>{{ t('settings.adminUsers.fields.state') }}</label>
                <select v-model="editForm.state" class="form-select">
                  <option value="active">active</option>
                  <option value="inactive">inactive</option>
                  <option value="disabled">disabled</option>
                </select>
              </div>
            </div>

            <div class="membership-headline">
              <h4>{{ t('settings.adminUsers.membershipsTitle') }}</h4>
              <button class="btn btn-secondary btn-sm" @click="addMembershipRow">{{ t('settings.adminUsers.addDepartment') }}</button>
            </div>

            <div v-if="editForm.memberships.length === 0" class="inline-hint">
              {{ t('settings.adminUsers.noDepartment') }}
            </div>

            <div v-for="(membership, index) in editForm.memberships" :key="membership.local_id" class="membership-row">
              <select v-model="membership.department_id" class="form-select">
                <option value="" disabled>{{ t('settings.adminUsers.selectDepartment') }}</option>
                <option
                  v-for="department in departmentOptionsFor(index)"
                  :key="department.id"
                  :value="department.id"
                >
                  {{ department.name }}
                </option>
              </select>

              <select v-model="membership.role" class="form-select role-select">
                <option v-for="role in roleOptions" :key="role.value" :value="role.value">
                  {{ role.label }}
                </option>
              </select>

              <label class="primary-checkbox">
                <input
                  type="checkbox"
                  :checked="membership.is_primary"
                  @change="setPrimaryMembership(index)"
                />
                {{ t('settings.adminUsers.primary') }}
              </label>

              <button class="icon-btn icon-btn-danger" :title="t('settings.adminUsers.removeDepartment')" @click="removeMembershipRow(index)">
                ×
              </button>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeEditModal">{{ t('common.cancel') }}</button>
            <button class="btn btn-primary" @click="saveUser" :disabled="isSaving || !canSave">
              {{ isSaving ? t('settings.adminUsers.saving') : t('common.save') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getDepartments, type Department } from '@/api/departments'
import {
  getAdminUsers,
  getAdminUserDetail,
  updateAdminUser,
  type AdminUserListItem,
  type DepartmentRole,
} from '@/api/adminUsers'
import { useToast } from '@/composables/useToast'

type SortBy = 'created_at' | 'name' | 'email' | 'departments_count'
type SortDir = 'asc' | 'desc'

interface EditableMembership {
  local_id: string
  department_id: string
  role: DepartmentRole
  is_primary: boolean
}

interface EditForm {
  user_id: string
  display_name: string
  first_name: string
  last_name: string
  nickname: string
  email: string
  state: string
  memberships: EditableMembership[]
}

const toast = useToast()
const { t } = useI18n()

const users = ref<AdminUserListItem[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const error = ref<string | null>(null)
const searchQuery = ref('')
const sortBy = ref<SortBy>('created_at')
const sortDir = ref<SortDir>('desc')
const showEditModal = ref(false)
const editForm = ref<EditForm | null>(null)
const departments = ref<Department[]>([])

const roleOptions: Array<{ value: DepartmentRole; label: string }> = [
  { value: 'mw', label: t('settings.adminUsers.roles.mw') },
  { value: 'dc', label: t('settings.adminUsers.roles.dc') },
  { value: 'l1', label: t('settings.adminUsers.roles.l1') },
  { value: 'l2', label: t('settings.adminUsers.roles.l2') },
  { value: 'l3', label: t('settings.adminUsers.roles.l3') },
  { value: 'u', label: t('settings.adminUsers.roles.u') },
]

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return users.value

  return users.value.filter((user) => {
    return user.name.toLowerCase().includes(query) || user.email.toLowerCase().includes(query)
  })
})

const canSave = computed(() => {
  if (!editForm.value) return false
  if (!editForm.value.email.trim()) return false

  const departmentIds = editForm.value.memberships.map((membership) => membership.department_id).filter(Boolean)
  if (departmentIds.length !== editForm.value.memberships.length) return false

  return new Set(departmentIds).size === departmentIds.length
})

function formatDate(value: string): string {
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return value
  return parsed.toLocaleString('de-CH', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function toggleSort(field: SortBy) {
  if (sortBy.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = field
    sortDir.value = field === 'created_at' ? 'desc' : 'asc'
  }
  loadUsers()
}

async function loadUsers() {
  isLoading.value = true
  error.value = null
  try {
    users.value = await getAdminUsers({
      sortBy: sortBy.value,
      sortDir: sortDir.value,
    })
  } catch (err: any) {
    error.value = err.response?.data?.error || t('settings.adminUsers.loadError')
  } finally {
    isLoading.value = false
  }
}

async function loadDepartments() {
  try {
    departments.value = await getDepartments()
  } catch (err) {
    console.error(t('settings.adminUsers.departmentsLoadError'), err)
  }
}

async function openEditModal(userId: string) {
  try {
    const detail = await getAdminUserDetail(userId)
    editForm.value = {
      user_id: detail.id,
      display_name: detail.name,
      first_name: detail.first_name || '',
      last_name: detail.last_name || '',
      nickname: detail.nickname || '',
      email: detail.email,
      state: detail.state,
      memberships: detail.memberships.map((membership) => ({
        local_id: `${membership.department_id}-${Math.random().toString(36).slice(2, 8)}`,
        department_id: membership.department_id,
        role: membership.role,
        is_primary: membership.is_primary,
      })),
    }
    showEditModal.value = true
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.adminUsers.detailsLoadError'))
  }
}

function closeEditModal() {
  showEditModal.value = false
  editForm.value = null
}

function departmentOptionsFor(index: number): Department[] {
  if (!editForm.value) return departments.value
  const selectedByOthers = new Set(
    editForm.value.memberships
      .filter((_, i) => i !== index)
      .map((membership) => membership.department_id)
      .filter(Boolean)
  )
  return departments.value.filter((department) => !selectedByOthers.has(department.id))
}

function addMembershipRow() {
  if (!editForm.value) return
  editForm.value.memberships.push({
    local_id: `new-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    department_id: '',
    role: 'u',
    is_primary: editForm.value.memberships.length === 0,
  })
}

function removeMembershipRow(index: number) {
  if (!editForm.value) return
  const removedPrimary = editForm.value.memberships[index]?.is_primary
  editForm.value.memberships.splice(index, 1)
  if (removedPrimary && editForm.value.memberships.length > 0) {
    editForm.value.memberships[0].is_primary = true
  }
}

function setPrimaryMembership(index: number) {
  if (!editForm.value) return
  editForm.value.memberships.forEach((membership, i) => {
    membership.is_primary = i === index
  })
}

async function saveUser() {
  if (!editForm.value || !canSave.value || isSaving.value) return
  isSaving.value = true
  try {
    await updateAdminUser(editForm.value.user_id, {
      first_name: editForm.value.first_name.trim() || null,
      last_name: editForm.value.last_name.trim() || null,
      nickname: editForm.value.nickname.trim() || null,
      email: editForm.value.email.trim(),
      state: editForm.value.state,
      memberships: editForm.value.memberships.map((membership) => ({
        department_id: membership.department_id,
        role: membership.role,
        is_primary: membership.is_primary,
      })),
    })
    toast.success(t('settings.adminUsers.toastUpdated'))
    closeEditModal()
    await loadUsers()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.adminUsers.saveError'))
  } finally {
    isSaving.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadUsers(), loadDepartments()])
})
</script>

<style scoped>
.admin-users-settings {
  padding: 0;
}

.page-header {
  margin-bottom: 18px;
}

.settings-title {
  margin: 0;
  font-size: 24px;
  color: #1f2937;
}

.settings-description {
  margin-top: 6px;
  color: #6b7280;
  font-size: 14px;
}

.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.search-wrapper {
  position: relative;
  flex: 1;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
}

/* Search input base uses shared ui/page-layout.css */

.state-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 24px;
  text-align: center;
  color: #6b7280;
}

.state-error {
  color: #b91c1c;
}

.table-wrapper {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
  background: white;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
}

.users-table th,
.users-table td {
  padding: 12px 14px;
  border-bottom: 1px solid #f1f5f9;
  text-align: left;
}

.users-table th {
  background: #f8fafc;
  color: #64748b;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.users-table tr:last-child td {
  border-bottom: none;
}

.sortable {
  cursor: pointer;
  user-select: none;
}

.dept-col {
  width: 180px;
}

.actions-col {
  width: 90px;
}

.icon-btn {
  width: 30px;
  height: 30px;
  border-radius: 6px;
  border: none;
  background: #f1f5f9;
  color: #475569;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.icon-btn:hover {
  background: #e2e8f0;
}

.icon-btn-danger {
  color: #b91c1c;
}

/* Buttons use shared ui/buttons.css */

/* Modal overlay base uses shared ui/modals.css */

.modal-container {
  width: 100%;
  max-width: 840px;
  max-height: 90vh;
  overflow: hidden;
  background: white;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
}

.modal-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.close-btn {
  border: none;
  background: transparent;
  font-size: 20px;
  cursor: pointer;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

/* Form group/input/select base uses shared ui/forms.css */

.membership-headline {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}

.inline-hint {
  color: #6b7280;
  background: #f8fafc;
  border-radius: 8px;
  padding: 10px 12px;
}

.membership-row {
  display: grid;
  grid-template-columns: minmax(0, 1.8fr) minmax(0, 1fr) auto auto;
  gap: 8px;
  align-items: center;
  margin-bottom: 8px;
}

.role-select {
  min-width: 160px;
}

.primary-checkbox {
  display: inline-flex;
  gap: 6px;
  align-items: center;
  white-space: nowrap;
}

.modal-footer {
  padding: 14px 20px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
