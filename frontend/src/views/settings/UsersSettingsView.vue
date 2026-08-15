<template>
  <div class="users-settings" :class="{ 'users-settings--embedded': embedded }">
    <!-- Header (auf eigener Settings-Seite) -->
    <div v-if="!embedded" class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.departmentUsers.title') }}</h2>
        <p class="settings-description">{{ t('settings.departmentUsers.subtitle') }}</p>
      </div>
      <EButton variant="primary" data-onboarding="settings-user-add" @click="openAddModal()">
        <v-icon icon="mdi-account-plus" start size="20" />
        {{ t('settings.departmentUsers.addUser') }}
      </EButton>
    </div>
    <div v-else class="embedded-toolbar">
      <EButton variant="primary" size="small" data-onboarding="settings-user-add" @click="openAddModal()">
        <v-icon icon="mdi-account-plus" start size="18" />
        {{ t('settings.departmentUsers.addUser') }}
      </EButton>
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

    <div
      v-if="canEditRoleLabels && !isGrossanlassDept && !isLoading"
      class="role-labels-card"
    >
      <div class="role-labels-head">
        <div>
          <h3>{{ t('settings.departmentUsers.roleLabelsTitle') }}</h3>
          <p class="role-labels-hint">{{ t('settings.departmentUsers.roleLabelsHint') }}</p>
        </div>
        <EButton
          variant="primary"
          size="small"
          :disabled="isSavingRoleLabels || !roleLabelsDirty"
          :loading="isSavingRoleLabels"
          @click="saveRoleLabels"
        >
          {{ isSavingRoleLabels ? t('common.saving') : t('common.save') }}
        </EButton>
      </div>
      <div class="role-labels-grid">
        <ETextField
          v-model="roleLabelForm.l1"
          :label="t('settings.departmentUsers.roleLabels.l1')"
          :placeholder="t('settings.departmentUsers.roles.l1')"
          maxlength="60"
          hide-details
        />
        <ETextField
          v-model="roleLabelForm.l2"
          :label="t('settings.departmentUsers.roleLabels.l2')"
          :placeholder="t('settings.departmentUsers.roles.l2')"
          maxlength="60"
          hide-details
        />
        <ETextField
          v-model="roleLabelForm.l3"
          :label="t('settings.departmentUsers.roleLabels.l3')"
          :placeholder="t('settings.departmentUsers.roles.l3')"
          maxlength="60"
          hide-details
        />
      </div>
    </div>

    <!-- Search -->
    <div v-if="!isLoading && members.length > 3" class="search-bar">
      <div class="search-box">
        <ESearchField
          v-model="searchQuery"
          :label="t('settings.departmentUsers.searchPlaceholder')"
        />
      </div>
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
          <EButton
            variant="secondary"
            size="small"
            @click.stop="removePendingInviteItem(invite.id)"
          >
            {{ isInviteOpen(invite) ? t('common.delete') : t('settings.departmentUsers.inviteDismiss') }}
          </EButton>
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
            <EButton variant="primary" size="small" type="button" @click="decidePendingJoin(jr.id, 'approved')">
              {{ t('settings.departmentUsers.pendingJoinApprove') }}
            </EButton>
            <EButton variant="secondary" size="small" type="button" @click="decidePendingJoin(jr.id, 'rejected')">
              {{ t('settings.departmentUsers.pendingJoinReject') }}
            </EButton>
          </div>
        </li>
      </ul>
      <p v-else class="pending-muted">{{ t('settings.departmentUsers.pendingJoinRequestsNone') }}</p>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="table"
      :rows="6"
      :message="t('settings.departmentUsers.loading')"
    />

    <div v-else-if="error" class="users-settings-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadMembers">{{ t('common.retry') }}</EButton>
    </div>

    <EEmptyState
      v-else-if="members.length === 0"
      variant="create"
      :title="t('settings.departmentUsers.emptyTitle')"
      :description="t('settings.departmentUsers.emptyText')"
    >
      <template #actions>
        <EButton @click="openAddModal()">{{ t('settings.departmentUsers.emptyCta') }}</EButton>
      </template>
    </EEmptyState>

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

    <EDialog
      v-model="showAddModal"
      :max-width="640"
      :retain-focus="false"
      :title="t('settings.departmentUsers.modalAddTitle')"
      card-class="modal-add-user modal-add-user-wide"
    >
      <div class="add-user-modal-body">
        <p
          v-if="!isLoadingAvailable && availableUsers.length === 0 && userSearchTrimmed.length < 3"
          class="no-users-hint"
        >
          {{ t('settings.departmentUsers.modalNoAvailableUsers') }}
        </p>

        <EAutocomplete
          v-model="selectedAvailableUser"
          v-model:search="userSearchQuery"
          autocomplete="off"
          name="emc-add-user-search"
          :label="t('settings.departmentUsers.labelUserRequired')"
          :placeholder="t('settings.departmentUsers.userSearchPlaceholder')"
          :items="autocompleteUsers"
          item-title="name"
          item-value="id"
          return-object
          chips
          closable-chips
          :menu="userSearchMenuOpen"
          :loading="isLoadingAvailable"
          hide-details
          @update:menu="onUserSearchMenuUpdate"
        >
          <template #item="{ item: user, props: itemProps }">
            <AvailableUserAutocompleteItem
              :user="user"
              :item-props="itemProps"
              :search-query="userSearchTrimmed"
              :first-name-label="t('settings.departmentUsers.autocompleteFieldFirstName')"
              :email-label="t('settings.departmentUsers.autocompleteFieldEmail')"
              :department-label="t('settings.departmentUsers.autocompleteFieldDepartment')"
              :no-department-text="t('settings.departmentUsers.autocompleteNoDepartment')"
            />
          </template>
          <template #no-data>
            <div class="add-user-autocomplete-no-data">
              <template v-if="isLoadingAvailable">
                {{ t('settings.departmentUsers.modalLoadingAvailable') }}
              </template>
            </div>
          </template>
        </EAutocomplete>
        <p
          v-if="!selectedAvailableUser && userSearchTrimmed.length < 3"
          class="add-user-search-hint"
        >
          {{ t('settings.departmentUsers.autocompleteCharsHint', { n: Math.max(0, 3 - userSearchTrimmed.length) }) }}
        </p>

        <div v-if="showInviteByEmail" class="invite-by-email-box">
          <div class="invite-by-email-box__hero">
            <v-icon icon="mdi-account-search-outline" class="invite-by-email-box__icon" aria-hidden="true" />
            <div class="invite-by-email-box__hero-text">
              <p class="invite-by-email-lead">
                {{ t('settings.departmentUsers.inviteNoUserFoundForQuery', { query: userSearchTrimmed }) }}
              </p>
              <p class="invite-by-email-box__hint">{{ t('settings.departmentUsers.inviteByEmailHint') }}</p>
            </div>
          </div>
          <p class="invite-by-email-box__divider-label">{{ t('settings.departmentUsers.inviteByEmailDivider') }}</p>
          <ETextField
            v-model="inviteEmail"
            type="email"
            :label="t('settings.departmentUsers.inviteEmailLabel')"
            :placeholder="t('settings.departmentUsers.inviteEmailPlaceholder')"
            hide-details
          />
          <ESelect
            v-model="addForm.role"
            :label="t('common.role')"
            :items="editRoleSelectItems"
            hide-details
          />
        </div>

        <template v-if="selectedAvailableUser">
          <p class="groups-hint">{{ t('settings.departmentUsers.inviteExistingUserHint') }}</p>
          <ESelect
            v-model="addForm.role"
            :label="t('common.role')"
            :items="editRoleSelectItems"
            hide-details
          />
          <ECheckbox
            v-model="addForm.is_primary"
            :label="t('settings.departmentUsers.primaryDepartment')"
            hide-details
          />
          <div class="add-user-groups-block">
            <p class="add-user-groups-block__label">{{ addUserUnitLabels.label }}</p>
            <p class="groups-hint">{{ addUserUnitLabels.hint }}</p>
            <div v-if="isLoadingGroups" class="loading-inline">
              <div class="spinner-sm"></div>
              <span>{{ addUserUnitLabels.loading }}</span>
            </div>
            <p v-else-if="hierarchicalGroupsForAdd.length === 0" class="groups-empty">
              {{ addUserUnitLabels.empty }}
            </p>
            <div v-else class="group-picker">
              <div
                v-for="group in hierarchicalGroupsForAdd"
                :key="group.id"
                class="group-picker-item"
                :class="[
                  `group-picker-item--level-${group._level}`,
                  isGrossanlassBauprojekt(group) ? 'group-picker-item--bauprojekt' : '',
                ]"
              >
                <ECheckbox
                  v-if="!isGrossanlassDept"
                  :model-value="selectedGroupIds.includes(group.id)"
                  :label="group.name"
                  hide-details
                  @update:model-value="(checked: boolean | null) => setGroupSelected(group.id, !!checked)"
                />
                <v-checkbox
                  v-else
                  :model-value="selectedGroupIds.includes(group.id)"
                  hide-details
                  density="compact"
                  color="primary"
                  class="group-picker-checkbox"
                  @update:model-value="(checked: boolean | null) => setGroupSelected(group.id, !!checked)"
                >
                  <template #label>
                    <span class="group-picker-row">
                      <span class="group-picker-name">{{ group.name }}</span>
                      <span v-if="isGrossanlassBauprojekt(group)" class="kind-badge kind-badge--bauprojekt">
                        {{ t('grossanlass.planung.ressorts.kindBauprojekt') }}
                      </span>
                    </span>
                  </template>
                </v-checkbox>
              </div>
            </div>
          </div>
        </template>
      </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeAddModal">{{ t('common.cancel') }}</EButton>
        <EButton
          v-if="showInviteByEmail"
          variant="primary"
          size="small"
          :disabled="!isInviteEmailValid || isSendingInvite"
          :loading="isSendingInvite"
          @click="sendEmailInvite"
        >
          {{ isSendingInvite ? t('settings.departmentUsers.sendingInvite') : t('settings.departmentUsers.sendInvite') }}
        </EButton>
        <EButton
          v-else
          variant="primary"
          size="small"
          :disabled="!addForm.user_id || isSaving"
          :loading="isSaving"
          @click="handleAdd"
        >
          {{ isSaving ? t('settings.departmentUsers.sendingInvite') : t('settings.departmentUsers.sendInvite') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="showEditModal"
      :max-width="480"
      :title="editingMember ? t('settings.departmentUsers.modalEditTitle', { name: editingMember.name }) : ''"
    >
      <template v-if="editingMember">
        <ESelect
          v-model="editForm.role"
          :label="t('common.role')"
          :items="editRoleSelectItems"
          hide-details
        />
        <ECheckbox
          v-model="editForm.is_primary"
          :label="t('settings.departmentUsers.primaryDepartment')"
          hide-details
        />
      </template>
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeEditModal">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="isSaving"
          :loading="isSaving"
          @click="handleUpdate"
        >
          {{ isSaving ? t('settings.departmentUsers.saving') : t('common.save') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import EAutocomplete from '@/components/form/base/EAutocomplete.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import AvailableUserAutocompleteItem from '@/components/settings/AvailableUserAutocompleteItem.vue'
import { filterAvailableUsersByQuery } from '@/utils/availableUserSearch'
import { EButton, EDialog, ESearchField, ESelect, ECheckbox } from '@/components/form/base'
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
import {
  saveDepartmentRoleLabels,
  type DepartmentRoleLabels,
} from '@/api/departmentSettings'
import { useDepartmentRoleLabelsStore } from '@/stores/departmentRoleLabels'
import { getGroups, type Group } from '@/api/groups'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import {
  flattenGrossanlassGroupsWithLevel,
  grossanlassGroupSelectTitle,
  isBauprojektGroup,
  type GrossanlassGroupWithLevel,
} from '@/utils/grossanlassGroupHierarchy'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'

const props = withDefaults(
  defineProps<{
    /** Override when embedded (e.g. Mein Department accordion). */
    departmentId?: string
    /** Hide page title; compact toolbar only. */
    embedded?: boolean
  }>(),
  {
    departmentId: undefined,
    embedded: false,
  },
)

const emit = defineEmits<{
  changed: [memberCount: number]
}>()

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const roleLabelsStore = useDepartmentRoleLabelsStore()
const toast = useToast()
const confirm = useConfirm()
const departmentId = computed(
  () =>
    (props.departmentId || '').trim()
    || (route.params.departmentId as string)
    || authStore.activeDepartmentId
    || '',
)
const isGrossanlassDept = computed(() => authStore.isDepartmentGrossanlass(departmentId.value))

const roleLabelForm = ref<DepartmentRoleLabels>({ l1: '', l2: '', l3: '' })
const roleLabelSaved = ref<DepartmentRoleLabels>({ l1: '', l2: '', l3: '' })
const isSavingRoleLabels = ref(false)

const roleLabelsDirty = computed(() =>
  roleLabelForm.value.l1 !== roleLabelSaved.value.l1
  || roleLabelForm.value.l2 !== roleLabelSaved.value.l2
  || roleLabelForm.value.l3 !== roleLabelSaved.value.l3
)

async function loadRoleLabels() {
  if (!departmentId.value || isGrossanlassDept.value) return
  await roleLabelsStore.load(departmentId.value)
  const cached = roleLabelsStore.getCached(departmentId.value)
  roleLabelForm.value = { ...cached }
  roleLabelSaved.value = { ...cached }
}

async function saveRoleLabels() {
  if (!departmentId.value || !roleLabelsDirty.value) return
  isSavingRoleLabels.value = true
  try {
    await saveDepartmentRoleLabels(departmentId.value, roleLabelForm.value)
    const next = {
      l1: roleLabelForm.value.l1.trim(),
      l2: roleLabelForm.value.l2.trim(),
      l3: roleLabelForm.value.l3.trim(),
    }
    roleLabelForm.value = { ...next }
    roleLabelSaved.value = { ...next }
    roleLabelsStore.setLocal(departmentId.value, next)
    toast.success(t('settings.departmentUsers.toastRoleLabelsSaved'))
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.departmentUsers.errSaveRoleLabels'))
  } finally {
    isSavingRoleLabels.value = false
  }
}

const addUserUnitLabels = computed(() => {
  if (isGrossanlassDept.value) {
    return {
      label: t('settings.departmentUsers.labelRessorts'),
      hint: t('settings.departmentUsers.ressortsHint'),
      loading: t('settings.departmentUsers.loadingRessorts'),
      empty: t('settings.departmentUsers.noRessorts'),
    }
  }
  return {
    label: t('settings.departmentUsers.labelGroups'),
    hint: t('settings.departmentUsers.groupsHint'),
    loading: t('settings.departmentUsers.loadingGroups'),
    empty: t('settings.departmentUsers.noGroups'),
  }
})

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

const canEditRoleLabels = computed(() => canManagePendingInvites.value)

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

const editRoleSelectItems = computed(() =>
  Object.entries(assignableRoles.value).map(([key, cfg]) => ({
    title: `${cfg?.short ?? key} – ${getRoleLabel(key)}`,
    value: key,
  })),
)

function getRoleColor(role: string): string {
  return DEPT_ROLES[role as DeptRoleKey]?.color || '#6b7280'
}

function getRoleShort(role: string): string {
  return DEPT_ROLES[role as DeptRoleKey]?.short || role.toUpperCase()
}

function getRoleLabel(role: string): string {
  return roleLabelsStore.labelFor(role, departmentId.value, t)
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

const userSearchQuery = ref('')
const selectedAvailableUser = ref<AvailableUser | null>(null)
let availableSearchTimer: ReturnType<typeof setTimeout> | null = null

const departmentGroups = ref<(Group | GrossanlassGroup)[]>([])
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
  if (isGrossanlassDept.value) {
    return flattenGrossanlassGroupsWithLevel(departmentGroups.value as GrossanlassGroup[])
  }

  const all = departmentGroups.value as Group[]
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

function isGrossanlassBauprojekt(group: Group & { _level: number } | GrossanlassGroupWithLevel): boolean {
  return isGrossanlassDept.value && isBauprojektGroup(group as GrossanlassGroup)
}

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
    emit('changed', members.value.length)
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

const userSearchTrimmed = computed(() => (userSearchQuery.value ?? '').trim())

/** Trefferliste — API + UND über alle Suchwörter (Name, E-Mail, Abteilung, …) */
const autocompleteUsers = computed(() => {
  if (userSearchTrimmed.value.length < 3) return []
  return filterAvailableUsersByQuery(
    excludeExistingDepartmentMembers(availableUsers.value),
    userSearchTrimmed.value,
  ).slice(0, 12)
})

const showInviteByEmail = computed(() => {
  if (selectedAvailableUser.value) return false
  if (userSearchTrimmed.value.length < 3) return false
  if (isLoadingAvailable.value) return false
  return autocompleteUsers.value.length === 0
})

function shouldOpenUserSearchMenu(): boolean {
  if (selectedAvailableUser.value) return false
  if (userSearchTrimmed.value.length < 3) return false
  if (showInviteByEmail.value) return false
  return isLoadingAvailable.value || autocompleteUsers.value.length > 0
}

/** Kontrolliertes Menü — zu früh geöffnet + Browser-Autofill sonst Doppel-Dropdown */
const userSearchMenuOpen = ref(false)

function onUserSearchMenuUpdate(open: boolean) {
  if (!open) {
    userSearchMenuOpen.value = false
    return
  }
  // Klick/Fokus bei < 3 Zeichen: Menü zu (Hinweis steht unter dem Feld)
  userSearchMenuOpen.value = shouldOpenUserSearchMenu()
}

watch(
  [userSearchTrimmed, selectedAvailableUser, isLoadingAvailable, autocompleteUsers, showInviteByEmail],
  () => {
    userSearchMenuOpen.value = shouldOpenUserSearchMenu()
  },
)

const isInviteEmailValid = computed(() => isValidEmail(inviteEmail.value))

function isValidEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

function setGroupSelected(groupId: string, checked: boolean) {
  if (checked) {
    if (!selectedGroupIds.value.includes(groupId)) {
      selectedGroupIds.value = [...selectedGroupIds.value, groupId]
    }
  } else {
    selectedGroupIds.value = selectedGroupIds.value.filter((id) => id !== groupId)
  }
}

async function loadDepartmentGroups() {
  if (!departmentId.value) return
  isLoadingGroups.value = true
  try {
    departmentGroups.value = isGrossanlassDept.value
      ? await getGrossanlassGroups(departmentId.value)
      : await getGroups(departmentId.value)
  } catch {
    departmentGroups.value = []
  } finally {
    isLoadingGroups.value = false
  }
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
  loadRoleLabels()
})
watch(selectedAvailableUser, (user) => {
  addForm.value.user_id = user?.id ?? ''
  if (!user) {
    selectedGroupIds.value = []
  }
})

watch(userSearchQuery, (value) => {
  if (!showAddModal.value || selectedAvailableUser.value) return
  const trimmed = (value ?? '').trim()
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
  loadRoleLabels()
})

onUnmounted(() => {
  if (availableSearchTimer) clearTimeout(availableSearchTimer)
})
</script>

<style scoped>
/* ========================================
   Layout & Header
   ======================================== */
.users-settings-error {
  margin-top: 8px;
}

.users-settings {
  padding: 0;
}

.users-settings--embedded {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.embedded-toolbar {
  display: flex;
  justify-content: flex-end;
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

.role-labels-card {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  padding: 14px 16px;
  margin-bottom: 16px;
}

.role-labels-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.role-labels-head h3 {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
}

.role-labels-hint {
  margin: 0;
  font-size: 13px;
  line-height: 1.4;
  color: #64748b;
}

.role-labels-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

@media (max-width: 720px) {
  .role-labels-grid {
    grid-template-columns: 1fr;
  }

  .role-labels-head {
    flex-direction: column;
  }
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
  margin: 0;
  padding: 8px 12px;
  border-bottom: 1px solid #f1f5f9;
}

.group-picker-item--level-0 { padding-left: 12px; }
.group-picker-item--level-1 { padding-left: 32px; }
.group-picker-item--level-2 { padding-left: 52px; }
.group-picker-item--level-3 { padding-left: 72px; }
.group-picker-item--level-4 { padding-left: 92px; }

.group-picker-item:last-child {
  border-bottom: none;
}

.group-picker-checkbox {
  width: 100%;
}

.group-picker-row {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.group-picker-name {
  font-size: 14px;
  color: #374151;
}

.kind-badge {
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
  padding: 3px 8px;
  border-radius: 999px;
  white-space: nowrap;
}

.kind-badge--bauprojekt {
  background: #fef3c7;
  color: #b45309;
}

.invite-by-email-box {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 8px;
  padding: 16px;
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.invite-by-email-box__hero {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.invite-by-email-box__icon {
  flex-shrink: 0;
  margin-top: 2px;
  color: #94a3b8;
  opacity: 0.9;
}

.invite-by-email-box__hero-text {
  min-width: 0;
  flex: 1;
}

.invite-by-email-lead {
  margin: 0 0 6px;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.4;
  color: #1e293b;
}

.invite-by-email-box__hint {
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
  color: #64748b;
}

.invite-by-email-box__divider-label {
  margin: 0;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #94a3b8;
}

.add-user-modal-body {
  overflow: visible;
}

.add-user-autocomplete-no-data {
  padding: 10px 14px;
  font-size: 13px;
  color: #64748b;
  line-height: 1.4;
}

.add-user-search-hint {
  margin: 6px 0 0;
  font-size: 13px;
  color: #64748b;
  line-height: 1.35;
}

.add-user-groups-block__label {
  margin: 0 0 4px;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
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

/* Form input base uses shared ui/forms.css */
</style>
