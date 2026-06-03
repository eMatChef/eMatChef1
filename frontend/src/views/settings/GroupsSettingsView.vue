<template>
  <div class="groups-settings">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.groups.title') }}</h2>
        <p class="settings-description">
          {{ groupsSubtitle }}
        </p>
      </div>
      <EButton v-if="canFullyManageGroups" variant="primary" @click="openCreateModal()">
        <v-icon icon="mdi-plus" start size="20" />
        {{ t('settings.groups.newGroup') }}
      </EButton>
    </div>

    <!-- Stats Bar -->
    <div v-if="!isLoading && groups.length > 0" class="stats-bar">
      <div class="stat-item">
        <span class="stat-value">{{ groups.length }}</span>
        <span class="stat-label">{{ t('settings.groups.statGroups') }}</span>
      </div>
      <div class="stat-item">
        <span class="stat-value">{{ totalMembers }}</span>
        <span class="stat-label">{{ t('settings.groups.statMembers') }}</span>
      </div>
      <div class="stat-item">
        <span class="stat-value">{{ totalLeaders }}</span>
        <span class="stat-label">{{ t('settings.groups.statLeaders') }}</span>
      </div>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="list"
      :message="t('settings.groups.loading')"
    />

    <div v-else-if="error" class="groups-settings-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadGroups">{{ t('common.retry') }}</EButton>
    </div>

    <EEmptyState
      v-else-if="groups.length === 0"
      variant="create"
      :title="t('settings.groups.emptyTitle')"
      :description="t('settings.groups.emptyDescription')"
    >
      <template v-if="canFullyManageGroups" #actions>
        <EButton @click="openCreateModal()">{{ t('settings.groups.firstGroup') }}</EButton>
      </template>
    </EEmptyState>

    <!-- Groups Table -->
    <div v-else class="table-wrapper">
      <table class="groups-table">
        <thead>
          <tr>
            <th class="col-name">{{ t('common.group') }}</th>
            <th class="col-members">{{ t('settings.groups.colMembersAndLeaders') }}</th>
            <th v-if="showGroupManagementActions" class="col-actions"></th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="group in hierarchicalGroups" 
            :key="group.id"
            class="group-row"
            :class="{ 'is-child': group._level > 0 }"
          >
            <!-- Name (mit Einrückung) -->
            <td class="col-name">
              <div class="name-cell" :style="{ paddingLeft: (group._level * 24) + 'px' }">
                <span v-if="group._level > 0" class="indent-icon">↳</span>
                <div class="group-icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                  </svg>
                </div>
                <span class="group-name">{{ group.name }}</span>
              </div>
            </td>

            <!-- Mitglieder & Gruppenchefs (★ = Gruppenchef) -->
            <td class="col-members">
              <div class="user-avatar-badge-list">
                <template v-if="getGroupMembersForDisplay(group).length > 0">
                  <UserAvatarBadge
                    v-for="member in getGroupMembersForDisplay(group)"
                    :key="member.user_id"
                    :user="member"
                    :show-leader-star="member.is_leader"
                  />
                </template>
                <span v-else class="text-muted">–</span>
              </div>
            </td>

            <!-- Aktionen -->
            <td v-if="showGroupManagementActions" class="col-actions">
              <div class="action-buttons">
                <button
                  v-if="canManageMembersForGroup(group)"
                  class="action-btn"
                  :title="t('settings.groups.titleManageMembers')"
                  @click="openMembersModal(group)"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
                  </svg>
                </button>
                <button
                  v-if="canFullyManageGroups"
                  class="action-btn"
                  :title="t('common.edit')"
                  @click="openEditModal(group)"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
                <button
                  v-if="canFullyManageGroups"
                  class="action-btn action-btn-danger"
                  :title="t('common.delete')"
                  @click="handleDelete(group)"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <EDialog
      v-model="showGroupModal"
      :max-width="480"
      :title="editingGroup ? t('settings.groups.modalEditGroup') : t('settings.groups.modalNewGroup')"
    >
      <ETextField
        ref="groupNameInput"
        v-model="groupForm.name"
        :label="t('settings.groups.groupNameLabel')"
        :placeholder="t('settings.groups.groupNamePlaceholder')"
        hide-details="auto"
      />
      <ESelect
        v-model="groupForm.parent_id"
        class="mt-3"
        :items="parentGroupSelectItems"
        :label="t('settings.groups.parentGroupLabel')"
        hide-details
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeGroupModal">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!groupForm.name.trim() || isSaving"
          :loading="isSaving"
          @click="saveGroup"
        >
          {{ isSaving ? t('settings.groups.saving') : (editingGroup ? t('common.save') : t('common.create')) }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="showMembersModal"
      :max-width="720"
    >
      <template #title>
        <template v-if="selectedGroup">
          {{ t('settings.groups.membersHeading') }} <strong>{{ selectedGroup.name }}</strong>
        </template>
      </template>
      <template v-if="selectedGroup">
            <!-- Bestehendes Mitglieder-Tabelle -->
            <div v-if="selectedGroup.members.length > 0" class="members-section">
              <h4 class="section-title">{{ t('settings.groups.sectionCurrentMembers', { count: selectedGroup.members.length }) }}</h4>
              <table class="members-table">
                <thead>
                  <tr>
                    <th>{{ t('common.name') }}</th>
                    <th>{{ t('settings.groups.memberColEmail') }}</th>
                    <th>{{ t('common.role') }}</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="member in selectedGroup.members" :key="member.user_id">
                    <td class="member-name">
                      <span class="name-text">{{ member.name }}</span>
                      <span v-if="member.is_primary" class="primary-badge">{{ t('settings.groups.primaryGroupBadge') }}</span>
                    </td>
                    <td class="member-email">{{ member.email }}</td>
                    <td>
                      <select
                        v-if="canFullyManageGroups"
                        :value="member.role"
                        class="role-select"
                        @change="handleRoleChange(member, ($event.target as HTMLSelectElement).value)"
                      >
                        <option value="leader">{{ t('settings.groups.roleLeader') }}</option>
                        <option value="member">{{ t('settings.groups.roleMember') }}</option>
                      </select>
                      <span v-else class="role-readonly">
                        {{ member.is_leader ? t('settings.groups.roleLeader') : t('settings.groups.roleMember') }}
                      </span>
                    </td>
                    <td>
                      <button
                        v-if="canFullyManageGroups"
                        class="action-btn action-btn-danger"
                        :title="t('common.remove')"
                        @click="handleRemoveMember(member)"
                      >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="empty-members">
              <p>{{ t('settings.groups.emptyNoMembers') }}</p>
            </div>

            <!-- Neues Mitglied hinzufügen -->
            <div class="add-member-section">
              <h4 class="section-title">{{ t('settings.groups.addMemberHeading') }}</h4>
              
              <div v-if="isLoadingUsers" class="loading-inline">
                <div class="spinner-sm"></div>
                <span>{{ t('settings.groups.loadingUsers') }}</span>
              </div>

              <div v-else-if="unassignedUsers.length === 0" class="no-users-hint">
                <p>{{ t('settings.groups.allUsersAssigned') }}</p>
              </div>

              <div v-else class="add-member-form">
                <!-- Dropdown: User aus Organisation/Department auswählen -->
                <select
                  v-model="addMemberForm.user_id"
                  class="form-select user-select"
                >
                  <option value="">{{ t('settings.groups.selectUser') }}</option>
                  <option
                    v-for="user in unassignedUsers"
                    :key="user.user_id"
                    :value="user.user_id"
                  >
                    {{ user.name }} ({{ user.email }})
                  </option>
                </select>
                <select
                  v-if="canFullyManageGroups"
                  v-model="addMemberForm.role"
                  class="form-select role-select-sm"
                >
                  <option value="member">{{ t('settings.groups.roleMember') }}</option>
                  <option value="leader">{{ t('settings.groups.roleLeader') }}</option>
                </select>
                <EButton
                  variant="primary"
                  size="small"
                  :disabled="!addMemberForm.user_id"
                  @click="handleAddMember"
                >
                  {{ t('common.add') }}
                </EButton>
              </div>
            </div>
      </template>
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeMembersModal">{{ t('settings.groups.close') }}</EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useGroupManagementScope } from '@/composables/useGroupManagementScope'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, EDialog, ETextField, ESelect } from '@/components/form/base'
import {
  getGroups,
  createGroup,
  updateGroup,
  deleteGroup as apiDeleteGroup,
  addGroupMember,
  updateGroupMember,
  removeGroupMember,
  type Group,
  type GroupMember,
} from '@/api/groups'
import {
  getDepartmentMembers,
  type DepartmentMember
} from '@/api/departments'

const { t } = useI18n()
const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')

// === State ===
const groups = ref<Group[]>([])
const isLoading = ref(false)
const error = ref<string | null>(null)

// Group Modal
const showGroupModal = ref(false)
const editingGroup = ref<Group | null>(null)
const isSaving = ref(false)
const groupNameInput = ref<HTMLInputElement | null>(null)
const groupForm = ref({
  name: '',
  parent_id: null as string | null,
})

// Members Modal
const showMembersModal = ref(false)
const selectedGroup = ref<Group | null>(null)
const departmentMembers = ref<DepartmentMember[]>([])
const isLoadingUsers = ref(false)
const addMemberForm = ref({
  user_id: '',
  role: 'member',
})


// === Computed ===

const {
  canFullyManageGroups,
  canManageMembersForGroup,
  isGroupLeaderSomewhere,
  showGroupManagementActions,
} = useGroupManagementScope(groups)

const groupsSubtitle = computed(() => {
  if (canFullyManageGroups.value) return t('settings.groups.subtitle')
  if (isGroupLeaderSomewhere.value) return t('settings.groups.subtitleLeader')
  return t('settings.groups.subtitleReadOnly')
})

const totalMembers = computed(() => groups.value.reduce((sum, g) => sum + g.member_count, 0))
const totalLeaders = computed(() => groups.value.reduce((sum, g) => sum + g.leader_count, 0))

/**
 * Department-User die noch NICHT in der ausgewählten Gruppe sind
 */
const unassignedUsers = computed(() => {
  if (!selectedGroup.value) return []
  const assignedIds = new Set(selectedGroup.value.members.map(m => m.user_id))
  return departmentMembers.value.filter(u => !assignedIds.has(u.user_id))
})

/**
 * Gruppen hierarchisch sortiert (Flatten mit Level-Info)
 */
const hierarchicalGroups = computed(() => {
  const all = groups.value
  const rootGroups = all.filter(g => !g.parent_id)

  function flatten(nodes: Group[], level: number): (Group & { _level: number })[] {
    const result: (Group & { _level: number })[] = []
    for (const node of nodes) {
      result.push({ ...node, _level: level })
      const children = all.filter(g => g.parent_id === node.id)
      if (children.length > 0) {
        result.push(...flatten(children, level + 1))
      }
    }
    return result
  }

  return flatten(rootGroups, 0)
})

/**
 * Verfügbare Parent-Gruppen (für Dropdown, ohne sich selbst & eigene Kinder)
 */
const availableParents = computed(() => {
  if (!editingGroup.value) {
    return hierarchicalGroups.value
  }
  // Eigene ID und alle Kinder ausschließen
  const excludeIds = new Set<string>()
  excludeIds.add(editingGroup.value.id)
  
  function collectChildIds(parentId: string) {
    for (const g of groups.value) {
      if (g.parent_id === parentId) {
        excludeIds.add(g.id)
        collectChildIds(g.id)
      }
    }
  }
  collectChildIds(editingGroup.value.id)
  
  return hierarchicalGroups.value.filter(g => !excludeIds.has(g.id))
})

const parentGroupSelectItems = computed(() => [
  { title: t('settings.groups.parentNone'), value: null },
  ...availableParents.value.map((g) => ({
    title: `${'↳ '.repeat(g._level)}${g.name}`,
    value: g.id,
  })),
])

// === Helpers ===

function getGroupMembersForDisplay(group: Group): GroupMember[] {
  const leaders = group.members.filter((m) => m.is_leader)
  const members = group.members.filter((m) => !m.is_leader)
  return [...leaders, ...members]
}

// === Data Loading ===

async function loadGroups() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = null
  try {
    groups.value = await getGroups(departmentId.value)
  } catch (err: any) {
    error.value = err.response?.data?.error || t('settings.groups.errorLoadGroups')
  } finally {
    isLoading.value = false
  }
}

async function loadDepartmentMembers() {
  if (!departmentId.value) return
  isLoadingUsers.value = true
  try {
    departmentMembers.value = await getDepartmentMembers(departmentId.value)
  } catch (err: any) {
    console.error('Fehler beim Laden der User:', err)
  } finally {
    isLoadingUsers.value = false
  }
}

// === Group CRUD ===

function openCreateModal() {
  editingGroup.value = null
  groupForm.value = { name: '', parent_id: null }
  showGroupModal.value = true
  nextTick(() => groupNameInput.value?.focus())
}

function openEditModal(group: Group) {
  editingGroup.value = group
  groupForm.value = {
    name: group.name,
    parent_id: group.parent_id,
  }
  showGroupModal.value = true
  nextTick(() => groupNameInput.value?.focus())
}

function closeGroupModal() {
  showGroupModal.value = false
  editingGroup.value = null
}

async function saveGroup() {
  if (!groupForm.value.name.trim() || isSaving.value) return
  isSaving.value = true
  
  try {
    if (editingGroup.value) {
      // Update
      await updateGroup(editingGroup.value.id, {
        name: groupForm.value.name.trim(),
        parent_id: groupForm.value.parent_id,
      })
    } else {
      // Create
      await createGroup({
        name: groupForm.value.name.trim(),
        department_id: departmentId.value,
        parent_id: groupForm.value.parent_id,
      })
    }
    closeGroupModal()
    await loadGroups()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.groups.errorSave'))
  } finally {
    isSaving.value = false
  }
}

async function handleDelete(group: Group) {
  const ok = await confirm.confirm({
    title: t('settings.groups.deleteGroupTitle'),
    message: t('settings.groups.deleteGroupMessage', { name: group.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await apiDeleteGroup(group.id)
    await loadGroups()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.groups.errorDelete'))
  }
}

// === Members ===

function openMembersModal(group: Group) {
  selectedGroup.value = group
  showMembersModal.value = true
  addMemberForm.value = { user_id: '', role: 'member' }
  loadDepartmentMembers()
}

function closeMembersModal() {
  showMembersModal.value = false
  selectedGroup.value = null
}

async function handleAddMember() {
  if (!selectedGroup.value || !addMemberForm.value.user_id) return
  try {
    await addGroupMember(selectedGroup.value.id, {
      user_id: addMemberForm.value.user_id,
      role: canFullyManageGroups.value ? addMemberForm.value.role : 'member',
    })
    // Daten neu laden
    await loadGroups()
    // Aktualisiere selectedGroup mit den neuen Daten
    const updated = groups.value.find(g => g.id === selectedGroup.value?.id)
    if (updated) selectedGroup.value = updated
    // Reset Form
    addMemberForm.value = { user_id: '', role: 'member' }
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.groups.errorAddMember'))
  }
}

async function handleRoleChange(member: GroupMember, newRole: string) {
  if (!selectedGroup.value) return
  try {
    await updateGroupMember(selectedGroup.value.id, member.user_id, { role: newRole })
    await loadGroups()
    const updated = groups.value.find(g => g.id === selectedGroup.value?.id)
    if (updated) selectedGroup.value = updated
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.groups.errorRoleChange'))
  }
}

async function handleRemoveMember(member: GroupMember) {
  if (!selectedGroup.value) return
  const ok = await confirm.confirm({
    title: t('settings.groups.removeMemberTitle'),
    message: t('settings.groups.removeMemberMessage', { name: member.name }),
    confirmText: t('common.remove'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await removeGroupMember(selectedGroup.value.id, member.user_id)
    await loadGroups()
    const updated = groups.value.find(g => g.id === selectedGroup.value?.id)
    if (updated) selectedGroup.value = updated
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.groups.errorRemoveMember'))
  }
}

// === Lifecycle ===

watch(departmentId, () => loadGroups())

onMounted(() => {
  loadGroups()
})
</script>

<style scoped>
/* ========================================
   Layout & Header
   ======================================== */
.groups-settings-error {
  margin-top: 8px;
}

.groups-settings {
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
   Loading / Error / Empty
   ======================================== */
/* Loading/error/empty base uses shared ui/states.css */

.error-message {
  color: #dc2626;
  margin-bottom: 12px;
}

/* Empty-state title/text typography uses shared ui/states.css */

/* ========================================
   Groups Table
   ======================================== */
.table-wrapper {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: visible;
}

.groups-table {
  width: 100%;
  border-collapse: collapse;
}

.groups-table thead th {
  padding: 12px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.groups-table tbody td {
  padding: 14px 16px;
  font-size: 14px;
  color: #1e293b;
  border-bottom: 1px solid #f3f4f6;
}

.group-row {
  transition: background 0.15s;
}

.group-row:hover {
  background: #f9fafb;
}

.group-row.is-child {
  background: #fafbfc;
}

.group-row.is-child:hover {
  background: #f3f4f6;
}

/* Name Cell */
.name-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.indent-icon {
  color: #94a3b8;
  font-size: 14px;
  flex-shrink: 0;
}

.group-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #eef2ff;
  border-radius: 8px;
  color: var(--color-primary, #4f46e5);
  flex-shrink: 0;
}

.group-name {
  font-weight: 500;
}

/* Leaders */
.col-leaders {
  min-width: 200px;
}

.leaders-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

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

.col-members {
  min-width: 120px;
}

/* Actions */
.col-actions {
  width: 120px;
}

.action-buttons {
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.15s;
}

.group-row:hover .action-buttons {
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

.text-muted {
  color: #9ca3af;
  font-size: 13px;
}

.role-readonly {
  font-size: 13px;
  color: #475569;
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

.modal-lg {
  width: 100%;
  max-width: 700px;
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
/* Form group/input/select base uses shared ui/forms.css */

/* ========================================
   Members Modal Specifics
   ======================================== */
.members-section {
  margin-bottom: 24px;
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 12px;
}

.members-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 8px;
}

.members-table th {
  padding: 8px 12px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  border-bottom: 1px solid #e5e7eb;
}

.members-table td {
  padding: 10px 12px;
  font-size: 14px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}

.member-name {
  display: flex;
  align-items: center;
  gap: 8px;
}

.name-text {
  font-weight: 500;
}

.primary-badge {
  font-size: 11px;
  color: #d97706;
  background: #fef3c7;
  padding: 1px 6px;
  border-radius: 4px;
  white-space: nowrap;
}

.member-email {
  color: #64748b;
  font-size: 13px;
}

.role-select {
  padding: 6px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 13px;
  outline: none;
  background: white;
  cursor: pointer;
}

.role-select:focus {
  border-color: var(--color-primary, #4f46e5);
}

.empty-members {
  text-align: center;
  padding: 20px;
  color: #64748b;
  background: #f9fafb;
  border-radius: 8px;
  margin-bottom: 24px;
}

.add-member-section {
  border-top: 1px solid #e5e7eb;
  padding-top: 20px;
}

.add-member-form {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.add-member-form .form-select,
.add-member-form .user-select {
  flex: 1;
  min-width: 200px;
}

.role-select-sm {
  width: 160px !important;
  flex: none !important;
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
  flex: 1;
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
</style>
