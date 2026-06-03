<template>
  <div class="admin-users-settings">
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.adminUsers.title') }}</h2>
        <p class="settings-description">{{ t('settings.adminUsers.subtitle') }}</p>
      </div>
    </div>

    <div class="toolbar">
      <div class="search-box">
        <ESearchField
          v-model="searchQuery"
          :label="t('settings.adminUsers.searchPlaceholder')"
        />
      </div>
      <EButton variant="secondary" :loading="isLoading" @click="loadUsers">
        {{ isLoading ? t('settings.adminUsers.loadingShort') : t('common.refresh') }}
      </EButton>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="table"
      :rows="6"
      :message="t('settings.adminUsers.loading')"
    />
    <div v-else-if="error" class="error-block">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadUsers">{{ t('common.retry') }}</EButton>
    </div>
    <EEmptyState
      v-else-if="filteredUsers.length === 0"
      variant="search"
      :title="t('settings.adminUsers.empty')"
    />

    <div v-else class="table-wrapper">
      <table class="users-table">
        <thead>
          <tr>
            <th class="sortable" @click="toggleSort('name')">
              {{ t('common.name') }} <span v-if="sortBy === 'name'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="sortable" @click="toggleSort('email')">
              {{ t('settings.adminUsers.columns.email') }} <span v-if="sortBy === 'email'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th>{{ t('settings.adminUsers.columns.globalRole') }}</th>
            <th class="sortable" @click="toggleSort('created_at')">
              {{ t('settings.adminUsers.columns.createdAt') }} <span v-if="sortBy === 'created_at'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="sortable dept-col" @click="toggleSort('departments_count')">
              {{ t('settings.adminUsers.columns.departmentsCount') }} <span v-if="sortBy === 'departments_count'">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="actions-col">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in filteredUsers" :key="user.id">
            <td>{{ user.name }}</td>
            <td>{{ user.email }}</td>
            <td>{{ formatGlobalRole(user.global_admin_role) }}</td>
            <td>{{ formatDate(user.created_at) }}</td>
            <td class="dept-col">{{ user.departments_count }}</td>
            <td class="actions-col">
              <EButton
                variant="text"
                size="small"
                :title="t('common.edit')"
                @click="openEditModal(user.id)"
              >
                <v-icon icon="mdi-pencil-outline" size="18" />
              </EButton>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EDialog
      v-model="showEditModal"
      :max-width="920"
      :title="editForm ? t('settings.adminUsers.editUserTitle', { name: editForm.display_name }) : ''"
      scrollable
      persistent
    >
      <template v-if="editForm">
        <div class="form-grid">
          <ETextField
            v-model="editForm.first_name"
            :label="t('settings.adminUsers.fields.firstName')"
            hide-details="auto"
          />
          <ETextField
            v-model="editForm.last_name"
            :label="t('settings.adminUsers.fields.lastName')"
            hide-details="auto"
          />
          <ETextField
            v-model="editForm.nickname"
            :label="t('settings.adminUsers.fields.nickname')"
            hide-details="auto"
          />
          <ETextField
            v-model="editForm.email"
            :label="t('settings.adminUsers.fields.email')"
            type="email"
            hide-details="auto"
          />
          <ESelect
            v-model="editForm.state"
            :label="t('common.status')"
            :items="stateSelectItems"
            hide-details="auto"
          />
        </div>

        <p v-if="isSuperAdminEditor" class="inline-hint admin-users-hint">
          {{ t('settings.adminUsers.globalRolesMovedHint') }}
          <router-link to="/admin-dashboard/verwaltung/global-admin-roles">
            {{ t('settings.adminUsers.globalRolesMovedLink') }}
          </router-link>
        </p>

        <div class="membership-headline">
          <h4>{{ t('settings.adminUsers.membershipsTitle') }}</h4>
          <EButton variant="secondary" size="small" @click="addMembershipRow">
            {{ t('settings.adminUsers.addDepartment') }}
          </EButton>
        </div>

        <div v-if="editForm.memberships.length === 0" class="inline-hint">
          {{ t('settings.adminUsers.noDepartment') }}
        </div>

        <div v-for="(membership, index) in editForm.memberships" :key="membership.local_id" class="membership-row">
          <DepartmentMembershipPicker
            v-model="membership.department_id"
            :departments="manageableDepartments"
            :organisation-name-by-id="organisationNameById"
            :excluded-department-ids="excludedDepartmentIdsFor(index)"
            :auto-focus="membershipFocusId === membership.local_id"
          />

          <ESelect
            v-model="membership.role"
            :items="roleSelectItems"
            hide-details="auto"
            class="role-select"
          />

          <ECheckbox
            :model-value="membership.is_primary"
            :label="t('settings.adminUsers.primary')"
            hide-details
            @update:model-value="(v) => v && setPrimaryMembership(index)"
          />

          <EButton
            variant="text"
            size="small"
            :title="t('settings.adminUsers.removeDepartment')"
            @click="removeMembershipRow(index)"
          >
            <v-icon icon="mdi-close" size="18" color="error" />
          </EButton>
        </div>
      </template>

      <template #actions>
        <EButton variant="secondary" @click="closeEditModal">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          :loading="isSaving"
          :disabled="isSaving || !canSave"
          @click="saveUser"
        >
          {{ isSaving ? t('settings.adminUsers.saving') : t('common.save') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { getDepartments, type Department } from '@/api/departments'
import {
  filterDepartmentsForAdminScope,
  filterOrganisationsForAdminScope,
} from '@/utils/organisationUserPicker'
import {
  getAdminUsers,
  getAdminUserDetail,
  updateAdminUser,
  getOrganisationsForAdmin,
  type AdminUserListItem,
  type DepartmentRole,
} from '@/api/adminUsers'
import DepartmentMembershipPicker from '@/components/admin/DepartmentMembershipPicker.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, ECheckbox, EDialog, ESearchField, ESelect, ETextField } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { filterDepartmentsByAccessibleIds } from '@/utils/adminCapabilities'

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
const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const isSuperAdminEditor = computed(() => authStore.userRoles.includes('ROLE_SUPERADMIN'))

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
const organisations = ref<Array<{ id: string; name: string }>>([])
const membershipFocusId = ref<string | null>(null)

const organisationNameById = computed(
  () => new Map(organisations.value.map((o) => [o.id, o.name]))
)

/** Departments in deinem Verwaltungsbereich (Org/Suborg-Scope oder Superadmin = alle). */
const manageableDepartments = computed(() => {
  const accessible = authStore.accessibleDepartmentIds
  if (accessible === null) return departments.value
  return filterDepartmentsByAccessibleIds(departments.value, accessible)
})

const roleOptions: Array<{ value: DepartmentRole; label: string }> = [
  { value: 'mw', label: t('settings.adminUsers.roles.mw') },
  { value: 'dc', label: t('settings.adminUsers.roles.dc') },
  { value: 'l1', label: t('settings.adminUsers.roles.l1') },
  { value: 'l2', label: t('settings.adminUsers.roles.l2') },
  { value: 'l3', label: t('settings.adminUsers.roles.l3') },
  { value: 'u', label: t('settings.adminUsers.roles.u') },
]

const roleSelectItems = computed(() =>
  roleOptions.map((role) => ({ title: role.label, value: role.value }))
)

const stateSelectItems = [
  { title: 'active', value: 'active' },
  { title: 'inactive', value: 'inactive' },
  { title: 'disabled', value: 'disabled' },
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

function formatGlobalRole(role: string | undefined): string {
  const key = role === 'org' || role === 'sub' ? role : 'none'
  return t(`settings.adminUsers.globalRoles.${key}`)
}

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
    const [depts, orgs] = await Promise.all([getDepartments(), getOrganisationsForAdmin()])
    departments.value = filterDepartmentsForAdminScope(depts)
    organisations.value = filterOrganisationsForAdminScope(orgs)
  } catch (err) {
    console.error(t('settings.adminUsers.departmentsLoadError'), err)
  }
}

function excludedDepartmentIdsFor(index: number): string[] {
  if (!editForm.value) return []
  return editForm.value.memberships
    .filter((_, i) => i !== index)
    .map((m) => m.department_id)
    .filter(Boolean)
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

function clearEditQuery() {
  if (!route.query.edit) return
  const { edit: _edit, ...rest } = route.query
  void router.replace({ query: rest })
}

function closeEditModal() {
  showEditModal.value = false
  editForm.value = null
  membershipFocusId.value = null
  clearEditQuery()
}

async function tryOpenEditFromQuery() {
  const editId = route.query.edit
  if (typeof editId !== 'string' || !editId) return
  await openEditModal(editId)
}

function addMembershipRow() {
  if (!editForm.value) return
  const localId = `new-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
  editForm.value.memberships.push({
    local_id: localId,
    department_id: '',
    role: 'u',
    is_primary: editForm.value.memberships.length === 0,
  })
  membershipFocusId.value = localId
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
  await tryOpenEditFromQuery()
})

watch(
  () => route.query.edit,
  (editId) => {
    if (typeof editId === 'string' && editId) void openEditModal(editId)
  }
)
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

.error-block {
  padding: 8px 0;
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

.admin-users-hint {
  margin-bottom: 16px;
  padding: 10px 12px;
  background: #f0f9ff;
  border-radius: 8px;
  border: 1px solid #bae6fd;
}
</style>
