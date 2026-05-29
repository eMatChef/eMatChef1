<template>
  <div class="users-settings">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.departmentUsers.title') }}</h2>
        <p class="settings-description">{{ t('settings.departmentUsers.subtitle') }}</p>
      </div>
      <button class="btn btn-primary" @click="openAddModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="8.5" cy="7" r="4"/>
          <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
        </svg>
        {{ t('settings.departmentUsers.addUser') }}
      </button>
    </div>

    <!-- Stats Bar -->
    <div v-if="!isLoading && members.length > 0" class="stats-bar">
      <div class="stat-item">
        <span class="stat-value">{{ members.length }}</span>
        <span class="stat-label">{{ t('settings.departmentUsers.statsUsers') }}</span>
      </div>
      <div class="stat-item">
        <span class="stat-value">{{ leaderCount }}</span>
        <span class="stat-label">{{ t('settings.departmentUsers.statsLeaders') }}</span>
      </div>
      <div class="stat-item">
        <span class="stat-value">{{ memberCount }}</span>
        <span class="stat-label">{{ t('settings.departmentUsers.statsMembers') }}</span>
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
        :placeholder="t('settings.departmentUsers.searchPlaceholder')"
        class="search-input"
      />
    </div>

    <div v-if="canManagePendingInvites && !isLoading" class="pending-invites-card">
      <div class="pending-head">
        <h3>{{ t('settings.departmentUsers.pendingInvitesTitle') }}</h3>
        <span v-if="openPendingInviteCount > 0" class="pending-count">{{ openPendingInviteCount }}</span>
      </div>
      <p v-if="pendingInvitesError" class="pending-error">{{ pendingInvitesError }}</p>
      <p v-else-if="isLoadingPendingInvites" class="pending-muted">{{ t('settings.departmentUsers.pendingLoading') }}</p>
      <ul v-else-if="pendingInvites.length > 0" class="pending-list">
        <li v-for="invite in pendingInvites" :key="invite.id" class="pending-item">
          <div class="pending-item-main">
            <span class="pending-email">{{ invite.email }}</span>
            <span v-if="invite.user_name" class="pending-user-name">{{ invite.user_name }}</span>
            <span
              class="invite-status-badge"
              :class="{
                'invite-status-badge--accepted': isInviteAccepted(invite),
                'invite-status-badge--pending': isInviteOpen(invite),
                'invite-status-badge--declined': isInviteDeclined(invite),
              }"
            >
              {{
                isInviteAccepted(invite)
                  ? t('settings.departmentUsers.inviteStatusAccepted')
                  : isInviteDeclined(invite)
                    ? t('settings.departmentUsers.inviteStatusDeclined')
                    : t('settings.departmentUsers.inviteStatusPending')
              }}
            </span>
            <span v-if="invite.user_registered && isInviteOpen(invite)" class="invite-registered-hint">
              {{ t('settings.departmentUsers.inviteUserRegistered') }}
            </span>
            <span class="pending-role">{{ getRoleLabel(invite.role) }}</span>
            <span v-if="isInviteAccepted(invite) && invite.accepted_at" class="invite-accepted-at">
              {{ t('settings.departmentUsers.inviteAcceptedAt', { date: formatInviteDate(invite.accepted_at) }) }}
            </span>
          </div>
          <button
            v-if="isInviteOpen(invite)"
            class="btn btn-secondary btn-sm"
            @click.stop="removePendingInviteItem(invite.id)"
          >
            {{ t('common.delete') }}
          </button>
          <button
            v-else
            class="btn btn-secondary btn-sm"
            @click.stop="removePendingInviteItem(invite.id)"
          >
            {{ t('settings.departmentUsers.inviteDismiss') }}
          </button>
        </li>
      </ul>
      <p v-else class="pending-muted">{{ t('settings.departmentUsers.pendingNone') }}</p>
    </div>

    <div v-if="canManagePendingInvites && !isLoading" class="pending-invites-card">
      <div class="pending-head">
        <h3>{{ t('settings.departmentUsers.pendingJoinRequestsTitle') }}</h3>
        <span v-if="pendingJoinRequests.length > 0" class="pending-count">{{ pendingJoinRequests.length }}</span>
      </div>
      <p v-if="pendingJoinRequestsError" class="pending-error">{{ pendingJoinRequestsError }}</p>
      <p v-else-if="isLoadingPendingJoinRequests" class="pending-muted">{{ t('settings.departmentUsers.pendingJoinRequestsLoading') }}</p>
      <ul v-else-if="pendingJoinRequests.length > 0" class="pending-list">
        <li v-for="jr in pendingJoinRequests" :key="jr.id" class="pending-item">
          <div class="pending-item-main">
            <span class="pending-email">{{ jr.name }}</span>
            <span v-if="jr.email" class="pending-user-name">{{ jr.email }}</span>
            <p v-if="jr.message" class="pending-join-message">{{ t('settings.departmentUsers.pendingJoinMessage', { text: jr.message }) }}</p>
          </div>
          <div class="pending-join-actions">
            <button class="btn btn-primary btn-sm" type="button" @click="decidePendingJoin(jr.id, 'approved')">
              {{ t('settings.departmentUsers.pendingJoinApprove') }}
            </button>
            <button class="btn btn-secondary btn-sm" type="button" @click="decidePendingJoin(jr.id, 'rejected')">
              {{ t('settings.departmentUsers.pendingJoinReject') }}
            </button>
          </div>
        </li>
      </ul>
      <p v-else class="pending-muted">{{ t('settings.departmentUsers.pendingJoinRequestsNone') }}</p>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>{{ t('settings.departmentUsers.loading') }}</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <p class="error-message">{{ error }}</p>
      <button @click="loadMembers" class="btn btn-secondary">{{ t('common.retry') }}</button>
    </div>

    <!-- Empty State -->
    <div v-else-if="members.length === 0" class="empty-state">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="8.5" cy="7" r="4"/>
        <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
      </svg>
      <h3>{{ t('settings.departmentUsers.emptyTitle') }}</h3>
      <p>{{ t('settings.departmentUsers.emptyText') }}</p>
      <button class="btn btn-primary" @click="openAddModal()">
        {{ t('settings.departmentUsers.emptyCta') }}
      </button>
    </div>

    <!-- Users Table -->
    <div v-else class="table-wrapper">
      <table class="users-table">
        <thead>
          <tr>
            <th class="col-name" @click="toggleSort('name')">
              {{ t('common.name') }}
              <span v-if="sortBy === 'name'" class="sort-indicator">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="col-email">{{ t('settings.departmentUsers.colEmail') }}</th>
            <th class="col-role" @click="toggleSort('role')">
              {{ t('common.role') }}
              <span v-if="sortBy === 'role'" class="sort-indicator">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
            </th>
            <th class="col-primary">{{ t('settings.departmentUsers.colPrimary') }}</th>
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
                <UserAvatarBadge :user="member" size="md" :show-tooltip="false" />
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
              <span v-if="member.is_primary" class="primary-star" :title="t('settings.departmentUsers.primaryStarTitle')">★</span>
              <span v-else class="text-muted">–</span>
            </td>

            <!-- Aktionen -->
            <td class="col-actions">
              <div class="action-buttons">
                <button 
                  class="action-btn" 
                  :title="t('settings.departmentUsers.titleEditRole')"
                  @click="openEditModal(member)"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
                <button 
                  v-if="!isCurrentUser(member)"
                  class="action-btn action-btn-danger" 
                  :title="t('settings.departmentUsers.titleRemoveFromDept')"
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
        <div class="modal-container modal-sm modal-add-user modal-add-user-wide">
          <div class="modal-header">
            <h3>{{ t('settings.departmentUsers.modalAddTitle') }}</h3>
            <button class="close-btn" @click="closeAddModal">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>

          <div class="modal-body add-user-modal-body">
            <p
              v-if="!isLoadingAvailable && availableUsers.length === 0 && userSearchQuery.trim().length < 3"
              class="no-users-hint"
            >
              {{ t('settings.departmentUsers.modalNoAvailableUsers') }}
            </p>

            <div class="form-group form-group-user-search">
              <label>{{ t('settings.departmentUsers.labelUserRequired') }}</label>
              <div
                class="autocomplete-wrapper"
                :class="{ 'is-dropdown-open': showUserDropdown && !selectedAvailableUser }"
              >
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
                    :placeholder="t('settings.departmentUsers.userSearchPlaceholder')"
                    @focus="showUserDropdown = true"
                    @blur="handleUserSearchBlur"
                    ref="userSearchInput"
                  />
                  <div v-if="isLoadingAvailable && userSearchQuery.trim().length >= 3" class="autocomplete-hint loading-hint">
                    <div class="spinner-sm"></div>
                    <span>{{ t('settings.departmentUsers.modalLoadingAvailable') }}</span>
                  </div>
                  <div v-if="showUserDropdown && userSearchQuery.length >= 3 && !isLoadingAvailable && filteredAvailableUsers.length > 0" class="autocomplete-dropdown">
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
                  <div v-if="showUserDropdown && userSearchQuery.length >= 3 && !isLoadingAvailable && filteredAvailableUsers.length === 0" class="autocomplete-dropdown">
                    <div class="autocomplete-empty">{{ t('settings.departmentUsers.autocompleteEmpty') }}</div>
                  </div>
                  <div v-if="showUserDropdown && userSearchQuery.length > 0 && userSearchQuery.length < 3" class="autocomplete-hint">
                    {{ t('settings.departmentUsers.autocompleteCharsHint', { n: Math.max(0, 3 - userSearchQuery.length) }) }}
                  </div>
              </div>
            </div>

            <div v-if="showInviteByEmail" class="invite-by-email-box">
              <p class="invite-by-email-lead">{{ t('settings.departmentUsers.inviteNoUserFound') }}</p>
              <p class="groups-hint">{{ t('settings.departmentUsers.inviteByEmailHint') }}</p>
              <div class="form-group">
                <label>{{ t('settings.departmentUsers.inviteEmailLabel') }}</label>
                <input
                  v-model="inviteEmail"
                  type="email"
                  class="form-input"
                  :placeholder="t('settings.departmentUsers.inviteEmailPlaceholder')"
                />
              </div>
              <div class="form-group form-group-role">
                <label>{{ t('common.role') }}</label>
                <select v-model="addForm.role" class="form-select">
                  <option v-for="(cfg, key) in assignableRoles" :key="key" :value="key">
                    {{ cfg.short }} – {{ getRoleLabel(String(key)) }}
                  </option>
                </select>
              </div>
            </div>

            <template v-if="selectedAvailableUser">
              <p class="groups-hint">{{ t('settings.departmentUsers.inviteExistingUserHint') }}</p>
              <div class="form-group form-group-role">
                <label>{{ t('common.role') }}</label>
                <select v-model="addForm.role" class="form-select">
                  <option v-for="(cfg, key) in assignableRoles" :key="key" :value="key">
                    {{ cfg.short }} – {{ getRoleLabel(String(key)) }}
                  </option>
                </select>
              </div>

              <div class="form-group">
                <label class="checkbox-label">
                  <input type="checkbox" v-model="addForm.is_primary" />
                  {{ t('settings.departmentUsers.primaryDepartment') }}
                </label>
              </div>

              <div class="form-group form-group-groups">
                <label>{{ t('settings.departmentUsers.labelGroups') }}</label>
                <p class="groups-hint">{{ t('settings.departmentUsers.groupsHint') }}</p>
                <div v-if="isLoadingGroups" class="loading-inline">
                  <div class="spinner-sm"></div>
                  <span>{{ t('settings.departmentUsers.loadingGroups') }}</span>
                </div>
                <p v-else-if="hierarchicalGroupsForAdd.length === 0" class="groups-empty">
                  {{ t('settings.departmentUsers.noGroups') }}
                </p>
                <div v-else class="group-picker">
                  <label
                    v-for="group in hierarchicalGroupsForAdd"
                    :key="group.id"
                    class="group-picker-item"
                    :style="{ paddingLeft: (12 + group._level * 20) + 'px' }"
                  >
                    <input
                      type="checkbox"
                      :value="group.id"
                      :checked="selectedGroupIds.includes(group.id)"
                      @change="toggleGroupSelection(group.id)"
                    />
                    <span>{{ group.name }}</span>
                  </label>
                </div>
              </div>
            </template>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeAddModal">{{ t('common.cancel') }}</button>
            <button
              v-if="showInviteByEmail"
              class="btn btn-primary"
              :disabled="!isInviteEmailValid || isSendingInvite"
              @click="sendEmailInvite"
            >
              {{ isSendingInvite ? t('settings.departmentUsers.sendingInvite') : t('settings.departmentUsers.sendInvite') }}
            </button>
            <button
              v-else
              class="btn btn-primary"
              :disabled="!addForm.user_id || isSaving"
              @click="handleAdd"
            >
              {{ isSaving ? t('settings.departmentUsers.sendingInvite') : t('settings.departmentUsers.sendInvite') }}
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
            <h3>{{ t('settings.departmentUsers.modalEditTitle', { name: editingMember.name }) }}</h3>
            <button class="close-btn" @click="closeEditModal">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>

          <div class="modal-body">
            <div class="form-group">
              <label>{{ t('common.role') }}</label>
              <select v-model="editForm.role" class="form-select">
                <option v-for="(cfg, key) in assignableRoles" :key="key" :value="key">
                  {{ cfg.short }} – {{ getRoleLabel(String(key)) }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="editForm.is_primary" />
                {{ t('settings.departmentUsers.primaryDepartment') }}
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeEditModal">{{ t('common.cancel') }}</button>
            <button 
              class="btn btn-primary" 
              :disabled="isSaving"
              @click="handleUpdate"
            >
              {{ isSaving ? t('settings.departmentUsers.saving') : t('common.save') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  createPendingInvite,
  decideJoinRequest,
  deletePendingInvite,
  getPendingInvites,
  getPendingJoinRequests,
  type PendingInvite,
  type PendingJoinRequest,
} from '@/api/joinRequests'
import {
  getDepartmentMembers,
  updateDepartmentMember,
  removeDepartmentMember,
  getAvailableUsersForDepartment,
  type DepartmentMember,
  type AvailableUser
} from '@/api/departments'
import { getGroups, type Group } from '@/api/groups'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')

// === Department Rollen-Konfiguration ===

const DEPT_ROLES = {
  mw: { short: 'MW', color: '#2563eb' },
  dc: { short: 'DC', color: '#0891b2' },
  l1: { short: 'L1', color: '#10b981' },
  l2: { short: 'L2', color: '#f59e0b' },
  l3: { short: 'L3', color: '#ef4444' },
  u: { short: 'U', color: '#6b7280' },
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
  const key = role as DeptRoleKey
  if (key in DEPT_ROLES) return t(`settings.departmentUsers.roles.${key}`)
  return role
}

function isCurrentUser(member: DepartmentMember): boolean {
  const uid = authStore.userId
  return uid !== null && member.user_id === uid
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
const pendingJoinRequests = ref<PendingJoinRequest[]>([])
const isLoadingPendingJoinRequests = ref(false)
const pendingJoinRequestsError = ref('')

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

const departmentGroups = ref<Group[]>([])
const isLoadingGroups = ref(false)
const selectedGroupIds = ref<string[]>([])
const inviteEmail = ref('')
const isSendingInvite = ref(false)

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

const openPendingInviteCount = computed(() =>
  pendingInvites.value.filter((inv) => isInviteOpen(inv)).length
)

function isInviteAccepted(invite: PendingInvite): boolean {
  return invite.status === 'accepted'
}

function isInviteDeclined(invite: PendingInvite): boolean {
  return invite.status === 'declined'
}

function isInviteOpen(invite: PendingInvite): boolean {
  return (invite.status ?? 'pending') === 'pending'
}

const hierarchicalGroupsForAdd = computed(() => {
  const all = departmentGroups.value
  const rootGroups = all.filter((g) => !g.parent_id)

  function flatten(nodes: Group[], level: number): (Group & { _level: number })[] {
    const result: (Group & { _level: number })[] = []
    for (const node of nodes) {
      result.push({ ...node, _level: level })
      const children = all.filter((g) => g.parent_id === node.id)
      if (children.length > 0) {
        result.push(...flatten(children, level + 1))
      }
    }
    return result
  }

  return flatten(rootGroups, 0)
})

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

// === Data Loading ===

async function loadMembers() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = null
  try {
    members.value = await getDepartmentMembers(departmentId.value)
  } catch (err: any) {
    error.value = err.response?.data?.error || t('settings.departmentUsers.errLoadMembers')
  } finally {
    isLoading.value = false
  }
}

function formatInviteDate(iso: string): string {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString(undefined, {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return iso
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
    await loadMembers()
  } catch (err: any) {
    pendingInvites.value = []
    pendingInvitesError.value = err.response?.data?.error || t('settings.departmentUsers.errLoadPendingInvites')
  } finally {
    isLoadingPendingInvites.value = false
  }
}

async function loadPendingJoinRequests() {
  if (!departmentId.value || !canManagePendingInvites.value) {
    pendingJoinRequests.value = []
    pendingJoinRequestsError.value = ''
    return
  }
  isLoadingPendingJoinRequests.value = true
  pendingJoinRequestsError.value = ''
  try {
    pendingJoinRequests.value = await getPendingJoinRequests(departmentId.value)
  } catch (err: any) {
    pendingJoinRequests.value = []
    pendingJoinRequestsError.value = err.response?.data?.error || t('settings.departmentUsers.errLoadPendingInvites')
  } finally {
    isLoadingPendingJoinRequests.value = false
  }
}

async function decidePendingJoin(id: string, status: 'approved' | 'rejected') {
  try {
    await decideJoinRequest(id, status)
    toast.success(status === 'approved' ? t('settings.departmentUsers.pendingJoinApprove') : t('settings.departmentUsers.pendingJoinReject'))
    await Promise.all([loadPendingJoinRequests(), loadMembers()])
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.departmentUsers.errLoadPendingInvites'))
  }
}

const existingMemberUserIds = computed(() => new Set(members.value.map((m) => m.user_id)))

function excludeExistingDepartmentMembers(users: AvailableUser[]): AvailableUser[] {
  const ids = existingMemberUserIds.value
  if (ids.size === 0) return users
  return users.filter((u) => !ids.has(u.id))
}

async function loadAvailableUsers(query?: string) {
  if (!departmentId.value) return
  isLoadingAvailable.value = true
  try {
    const results = await getAvailableUsersForDepartment(departmentId.value, query)
    availableUsers.value = excludeExistingDepartmentMembers(results)
  } catch (err: any) {
    console.error(t('settings.departmentUsers.logErrorLoadAvailable'), err)
  } finally {
    isLoadingAvailable.value = false
  }
}

// Autocomplete computed
const filteredAvailableUsers = computed(() => {
  if (userSearchQuery.value.length < 3) return []
  const q = userSearchQuery.value.toLowerCase()
  return excludeExistingDepartmentMembers(availableUsers.value).filter(u =>
    u.name.toLowerCase().includes(q) ||
    (u.nickname || '').toLowerCase().includes(q) ||
    (u.first_name || '').toLowerCase().includes(q) ||
    (u.last_name || '').toLowerCase().includes(q) ||
    u.email.toLowerCase().includes(q)
  ).slice(0, 8)
})

const showInviteByEmail = computed(() => {
  if (selectedAvailableUser.value) return false
  if (userSearchQuery.value.trim().length < 3) return false
  if (isLoadingAvailable.value) return false
  return filteredAvailableUsers.value.length === 0
})

const isInviteEmailValid = computed(() => isValidEmail(inviteEmail.value))

function isValidEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

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
  selectedGroupIds.value = []
  nextTick(() => userSearchInput.value?.focus())
}

function toggleGroupSelection(groupId: string) {
  const idx = selectedGroupIds.value.indexOf(groupId)
  if (idx >= 0) {
    selectedGroupIds.value = selectedGroupIds.value.filter((id) => id !== groupId)
  } else {
    selectedGroupIds.value = [...selectedGroupIds.value, groupId]
  }
}

async function loadDepartmentGroups() {
  if (!departmentId.value) return
  isLoadingGroups.value = true
  try {
    departmentGroups.value = await getGroups(departmentId.value)
  } catch {
    departmentGroups.value = []
  } finally {
    isLoadingGroups.value = false
  }
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
  inviteEmail.value = ''
  selectedGroupIds.value = []
  showAddModal.value = true
  loadAvailableUsers()
  loadDepartmentGroups()
}

function closeAddModal() {
  showAddModal.value = false
}

async function sendDepartmentInvite(email: string) {
  if (!departmentId.value || isSendingInvite.value) return
  isSendingInvite.value = true
  const userName = selectedAvailableUser.value?.name || email
  try {
    await createPendingInvite({
      departmentId: departmentId.value,
      email,
      userId: selectedAvailableUser.value?.id,
      role: addForm.value.role,
      groupIds: [...selectedGroupIds.value],
      isPrimary: addForm.value.is_primary,
    })
    await loadPendingInvites()
    toast.success(t('settings.departmentUsers.toastInviteSent', { name: userName }))
    closeAddModal()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.departmentUsers.errSendInvite'))
  } finally {
    isSendingInvite.value = false
  }
}

async function sendEmailInvite() {
  const email = inviteEmail.value.trim().toLowerCase()
  if (!isValidEmail(email)) return
  await sendDepartmentInvite(email)
}

async function handleAdd() {
  if (!selectedAvailableUser.value?.email || isSaving.value) return
  isSaving.value = true
  try {
    await sendDepartmentInvite(selectedAvailableUser.value.email.trim().toLowerCase())
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
    toast.error(err.response?.data?.error || t('settings.departmentUsers.errUpdateMember'))
  } finally {
    isSaving.value = false
  }
}

// === Remove Member ===

async function handleRemove(member: DepartmentMember) {
  if (isCurrentUser(member)) {
    toast.error(t('settings.departmentUsers.errCannotRemoveSelf'))
    return
  }
  const ok = await confirm.confirm({
    title: t('settings.departmentUsers.confirmRemoveTitle'),
    message: t('settings.departmentUsers.confirmRemoveMessage', { name: member.name }),
    confirmText: t('common.remove'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await removeDepartmentMember(departmentId.value, member.user_id)
    await loadMembers()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.departmentUsers.errRemoveMember'))
  }
}

async function removePendingInviteItem(inviteId: string) {
  if (!departmentId.value) return
  const ok = await confirm.confirm({
    title: t('settings.departmentUsers.confirmDeleteInviteTitle'),
    message: t('settings.departmentUsers.confirmDeleteInviteMessage'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  try {
    await deletePendingInvite(departmentId.value, inviteId)
    pendingInvites.value = pendingInvites.value.filter((entry) => entry.id !== inviteId)
    toast.success(t('settings.departmentUsers.toastInviteDeleted'))
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.departmentUsers.errDeleteInvite'))
  }
}

// === Lifecycle ===

watch(departmentId, () => {
  loadMembers()
  loadPendingInvites()
  loadPendingJoinRequests()
})
watch(userSearchQuery, (value) => {
  if (!showAddModal.value || selectedAvailableUser.value) return
  const trimmed = value.trim()
  if (isValidEmail(trimmed)) {
    inviteEmail.value = trimmed.toLowerCase()
  }
  if (availableSearchTimer) clearTimeout(availableSearchTimer)
  if (trimmed.length < 3) return
  availableSearchTimer = setTimeout(() => {
    loadAvailableUsers(trimmed)
  }, 300)
})

onMounted(() => {
  loadMembers()
  loadPendingInvites()
  loadPendingJoinRequests()
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

.pending-item-main {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  min-width: 0;
}

.pending-email {
  font-weight: 500;
  color: #1e293b;
  word-break: break-all;
}

.invite-status-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  border-radius: 999px;
  padding: 2px 8px;
}

.invite-status-badge--pending {
  color: #1d4ed8;
  background: #dbeafe;
}

.invite-status-badge--accepted {
  color: #166534;
  background: #dcfce7;
}

.invite-status-badge--declined {
  color: #991b1b;
  background: #fee2e2;
}

.pending-user-name {
  font-size: 12px;
  color: #64748b;
}

.invite-registered-hint {
  font-size: 11px;
  color: #64748b;
  font-style: italic;
}

.invite-accepted-at {
  font-size: 11px;
  color: #166534;
}

.pending-role {
  font-size: 12px;
  color: #64748b;
}

.invite-notifications {
  margin-bottom: 14px;
  padding-bottom: 12px;
  border-bottom: 1px solid #e5e7eb;
}

.invite-notifications-title {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
}

.notification-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.notification-item {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 10px;
  cursor: pointer;
}

.notification-item.unread {
  border-color: #93c5fd;
  background: #eff6ff;
}

.notification-text {
  margin: 0 0 4px;
  font-size: 13px;
  color: #334155;
}

.notification-time {
  font-size: 11px;
  color: #94a3b8;
}

.pending-join-message {
  margin: 6px 0 0;
  font-size: 13px;
  color: #4b5563;
}

.pending-join-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  flex-shrink: 0;
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

.modal-add-user {
  overflow: visible;
}

.modal-add-user-wide {
  max-width: 520px;
}

.groups-hint {
  margin: 0 0 10px;
  font-size: 12px;
  color: #64748b;
}

.groups-empty {
  margin: 0;
  font-size: 13px;
  color: #94a3b8;
}

.group-picker {
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f9fafb;
}

.group-picker-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  cursor: pointer;
  font-size: 14px;
  color: #1e293b;
  border-bottom: 1px solid #f1f5f9;
}

.group-picker-item:last-child {
  border-bottom: none;
}

.group-picker-item:hover {
  background: #f0f4ff;
}

.group-picker-item input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--color-primary, #4f46e5);
  flex-shrink: 0;
}

.invite-by-email-box {
  margin-top: 4px;
  padding: 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.invite-by-email-lead {
  margin: 0 0 6px;
  font-size: 14px;
  font-weight: 500;
  color: #1e293b;
}

.add-user-modal-body {
  overflow: visible;
}

.form-group-user-search {
  position: relative;
  z-index: 20;
}

.form-group-user-search .autocomplete-wrapper.is-dropdown-open {
  z-index: 30;
}

.form-group-role {
  position: relative;
  z-index: 1;
}

.modal-footer {
  position: relative;
  z-index: 1;
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
  top: calc(100% + 2px);
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 12px 28px -6px rgba(0, 0, 0, 0.18);
  z-index: 200;
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

.loading-hint {
  display: flex;
  position: absolute;
  top: calc(100% + 2px);
  left: 0;
  right: 0;
  z-index: 200;
  align-items: center;
  gap: 8px;
}

.autocomplete-hint {
  position: absolute;
  top: calc(100% + 2px);
  z-index: 200;
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
