<template>
  <div class="grossanlass-ressorts">
    <div class="page-header">
      <div>
        <p class="tab-description">{{ ressortsSubtitle }}</p>
      </div>
      <EButton v-if="canCreateRoot()" variant="primary" @click="openCreateModal()">
        <v-icon icon="mdi-plus" start size="20" />
        {{ t('grossanlass.planung.ressorts.addAction') }}
      </EButton>
    </div>

    <div v-if="!isLoading && groups.length > 0" class="stats-bar">
      <div class="stat-item">
        <span class="stat-value">{{ rootCount }}</span>
        <span class="stat-label">{{ t('grossanlass.planung.ressorts.statRessorts') }}</span>
      </div>
      <div class="stat-item">
        <span class="stat-value">{{ groups.length }}</span>
        <span class="stat-label">{{ t('grossanlass.planung.ressorts.statNodes') }}</span>
      </div>
      <div class="stat-item">
        <span class="stat-value">{{ totalMembers }}</span>
        <span class="stat-label">{{ t('grossanlass.planung.ressorts.statMembers') }}</span>
      </div>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="list"
      :message="t('grossanlass.planung.ressorts.loading')"
    />

    <div v-else-if="error" class="ressorts-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadGroups">{{ t('common.retry') }}</EButton>
    </div>

    <EEmptyState
      v-else-if="groups.length === 0"
      variant="create"
      icon="mdi-sitemap"
      :title="t('grossanlass.planung.ressorts.emptyTitle')"
      :description="t('grossanlass.planung.ressorts.emptyDescription')"
    >
      <template v-if="canCreateRoot()" #actions>
        <EButton @click="openCreateModal()">{{ t('grossanlass.planung.ressorts.addAction') }}</EButton>
      </template>
    </EEmptyState>

    <v-expansion-panels
      v-else
      v-model="openRessortPanels"
      multiple
      class="ga-ressort-accordions"
    >
      <v-expansion-panel value="ressorts">
        <v-expansion-panel-title>
          {{ t('grossanlass.planung.ressorts.panelRessorts') }}
          <span class="panel-count">{{ groups.length }}</span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
    <p v-if="canFullyManage && !logisticsGroupId" class="cost-hint">
      {{ t('grossanlass.planung.ressorts.costSetHint') }}
    </p>
    <div class="table-wrapper">
      <table class="groups-table">
        <thead>
          <tr>
            <th class="col-name">{{ t('grossanlass.planung.ressorts.colName') }}</th>
            <th class="col-members">{{ t('grossanlass.planung.ressorts.colMembers') }}</th>
            <th v-if="showManagementActions" class="col-actions"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="group in hierarchicalGroups"
            :key="group.id"
            class="group-row"
            :class="{ 'is-child': group._level > 0 }"
          >
            <td class="col-name">
              <div class="name-cell" :style="{ paddingLeft: group._level * 24 + 'px' }">
                <span v-if="group._level > 0" class="indent-icon">↳</span>
                <div class="group-icon" :class="'node-' + group.node_type">
                  <v-icon :icon="nodeIcon(group.node_type)" size="18" />
                </div>
                <div class="name-stack">
                  <span class="group-name">{{ group.name }}</span>
                  <span class="kind-row">
                    <span class="kind-badge">{{ kindLabel(group) }}</span>
                    <button
                      v-if="isLogisticsNode(group) && canFullyManage"
                      type="button"
                      class="cost-flag is-editable"
                      :title="t('grossanlass.planung.ressorts.costFlagHint')"
                      :disabled="isSavingLogistics"
                      @click="clearLogisticsNode"
                    >
                      {{ t('grossanlass.planung.ressorts.costFlag') }}
                    </button>
                    <span
                      v-else-if="isLogisticsNode(group)"
                      class="cost-flag"
                    >
                      {{ t('grossanlass.planung.ressorts.costFlag') }}
                    </span>
                    <button
                      v-else-if="canSetLogisticsNode(group)"
                      type="button"
                      class="cost-set-btn"
                      :disabled="isSavingLogistics"
                      :title="t('grossanlass.planung.ressorts.costSet')"
                      @click="setLogisticsNode(group)"
                    >
                      {{ t('grossanlass.planung.ressorts.costSet') }}
                    </button>
                  </span>
                </div>
              </div>
            </td>
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
            <td v-if="showManagementActions" class="col-actions">
              <div class="action-buttons">
                <button
                  v-if="canCreateChild(group) && group.level < 10"
                  class="action-btn"
                  :title="t('grossanlass.planung.ressorts.addChild')"
                  @click="openCreateModal(group.id)"
                >
                  <v-icon icon="mdi-plus" size="16" />
                </button>
                <button
                  v-if="canManageMembersForGroup(group)"
                  class="action-btn"
                  :title="t('grossanlass.planung.ressorts.manageMembers')"
                  @click="openMembersModal(group)"
                >
                  <v-icon icon="mdi-account-plus-outline" size="16" />
                </button>
                <button
                  v-if="canEditGroup()"
                  class="action-btn"
                  :title="t('common.edit')"
                  @click="openEditModal(group)"
                >
                  <v-icon icon="mdi-pencil-outline" size="16" />
                </button>
                <button
                  v-if="canDeleteGroup()"
                  class="action-btn action-btn-danger"
                  :title="t('common.delete')"
                  @click="handleDelete(group)"
                >
                  <v-icon icon="mdi-delete-outline" size="16" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
      <v-expansion-panel value="members">
        <v-expansion-panel-title>
          {{ t('grossanlass.planung.ressorts.panelMembers') }}
          <span class="panel-count">{{ uniqueMembers.length }}</span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <ul v-if="uniqueMembers.length" class="member-overview">
            <li v-for="row in uniqueMembers" :key="row.member.user_id" class="member-overview__row">
              <UserAvatarBadge :user="row.member" :show-leader-star="row.member.is_leader" />
              <div class="member-overview__meta">
                <strong>{{ row.member.name }}</strong>
                <span>{{ row.groups.join(' · ') }}</span>
              </div>
            </li>
          </ul>
          <p v-else class="text-muted">{{ t('grossanlass.planung.ressorts.emptyMembersPanel') }}</p>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>

    <EDialog
      v-model="showGroupModal"
      :max-width="480"
      :title="groupModalTitle"
    >
      <ETextField
        ref="groupNameInput"
        v-model="groupForm.name"
        :label="groupNameLabel"
        :placeholder="groupNamePlaceholder"
        hide-details="auto"
      />
      <ESelect
        v-if="showChildKindSelect"
        v-model="groupForm.kind"
        :items="childKindSelectItems"
        :label="t('grossanlass.planung.ressorts.childKindLabel')"
        hide-details
      />
      <ESelect
        v-if="canEditGroup()"
        v-model="groupForm.parent_id"
        :items="parentGroupSelectItems"
        :label="t('grossanlass.planung.ressorts.parentLabel')"
        :disabled="!!fixedParentId && !editingGroup"
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
          {{ isSaving ? t('grossanlass.planung.ressorts.saving') : (editingGroup ? t('common.save') : t('common.create')) }}
        </EButton>
      </template>
    </EDialog>

    <EDialog v-model="showMembersModal" :max-width="720">
      <template #title>
        <template v-if="selectedGroup">
          {{ t('grossanlass.planung.ressorts.membersHeading') }}
          <strong>{{ selectedGroup.name }}</strong>
        </template>
      </template>
      <template v-if="selectedGroup">
        <div v-if="selectedGroup.members.length > 0" class="members-section">
          <h4 class="section-title">
            {{ t('grossanlass.planung.ressorts.sectionCurrentMembers', { count: selectedGroup.members.length }) }}
          </h4>
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
                </td>
                <td class="member-email">{{ member.email }}</td>
                <td>
                  <select
                    v-if="canFullyManage"
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
                    v-if="canManageMembersForGroup(selectedGroup)"
                    class="action-btn action-btn-danger"
                    :title="t('common.remove')"
                    @click="handleRemoveMember(member)"
                  >
                    <v-icon icon="mdi-close" size="14" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="empty-members">
          <p>{{ t('grossanlass.planung.ressorts.emptyNoMembers') }}</p>
        </div>

        <div v-if="canManageMembersForGroup(selectedGroup)" class="add-helper-section">
          <h4 class="section-title">{{ t('grossanlass.planung.ressorts.helperHeading') }}</h4>
          <GrossanlassHelperInviteForm
            :department-id="departmentId"
            :groups="groups"
            :fixed-group-id="selectedGroup.id"
            @created="onHelperCreated"
          />
        </div>

        <div v-if="canManageMembersForGroup(selectedGroup)" class="add-member-section">
          <h4 class="section-title">{{ t('grossanlass.planung.ressorts.addMemberHeading') }}</h4>
          <div v-if="isLoadingUsers" class="loading-inline">
            <div class="spinner-sm"></div>
            <span>{{ t('settings.groups.loadingUsers') }}</span>
          </div>
          <div v-else-if="unassignedUsers.length === 0" class="no-users-hint">
            <p>{{ t('settings.groups.allUsersAssigned') }}</p>
          </div>
          <div v-else class="add-member-form">
            <select v-model="addMemberForm.user_id" class="form-select user-select">
              <option value="">{{ t('settings.groups.selectUser') }}</option>
              <option v-for="user in unassignedUsers" :key="user.user_id" :value="user.user_id">
                {{ user.name }} ({{ user.email }})
              </option>
            </select>
            <select
              v-if="canFullyManage"
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
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'
import GrossanlassHelperInviteForm from '@/components/grossanlass/GrossanlassHelperInviteForm.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, EDialog, ETextField, ESelect } from '@/components/form/base'
import {
  getGrossanlassGroups,
  createGrossanlassGroup,
  updateGrossanlassGroup,
  deleteGrossanlassGroup,
  addGrossanlassGroupMember,
  updateGrossanlassGroupMember,
  removeGrossanlassGroupMember,
  type GrossanlassGroup,
  type GrossanlassGroupKind,
  type GrossanlassNodeType,
} from '@/api/grossanlassGroups'
import { getDepartmentMembers, type DepartmentMember } from '@/api/departments'
import type { GroupMember } from '@/api/groups'
import {
  flattenGrossanlassGroupsWithLevel,
  grossanlassGroupSelectTitle,
} from '@/utils/grossanlassGroupHierarchy'
import { getGrossanlassPlanung, updateGrossanlassPlanung } from '@/api/grossanlassPlanung'

const { t } = useI18n()
const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')

const groups = ref<GrossanlassGroup[]>([])
const logisticsGroupId = ref<string | null>(null)
const isSavingLogistics = ref(false)
const isLoading = ref(false)
const error = ref<string | null>(null)
const openRessortPanels = ref<string[]>(['ressorts'])

const showGroupModal = ref(false)
const editingGroup = ref<GrossanlassGroup | null>(null)
const fixedParentId = ref<string | null>(null)
const isSaving = ref(false)
const groupNameInput = ref<{ focus?: () => void } | null>(null)
const groupForm = ref({
  name: '',
  parent_id: null as string | null,
  kind: 'ressort' as GrossanlassGroupKind,
})

const showMembersModal = ref(false)
const selectedGroup = ref<GrossanlassGroup | null>(null)
const departmentMembers = ref<DepartmentMember[]>([])
const isLoadingUsers = ref(false)
const addMemberForm = ref({ user_id: '', role: 'member' })

const {
  canFullyManage,
  canCreateRoot,
  canCreateChild,
  canEditGroup,
  canDeleteGroup,
  canManageMembersForGroup,
  isRessortMemberSomewhere,
  showManagementActions,
} = useGrossanlassRessortScope(groups)

const ressortsSubtitle = computed(() => {
  if (canFullyManage.value) return t('grossanlass.planung.ressorts.subtitleMw')
  if (isRessortMemberSomewhere.value) return t('grossanlass.planung.ressorts.subtitleMember')
  return t('grossanlass.planung.ressorts.subtitleReadOnly')
})

const rootCount = computed(() => groups.value.filter((g) => !g.parent_id).length)
const totalMembers = computed(() => groups.value.reduce((sum, g) => sum + g.member_count, 0))

const hierarchicalGroups = computed(() => flattenGrossanlassGroupsWithLevel(groups.value))

const uniqueMembers = computed(() => {
  const map = new Map<string, { member: (typeof groups.value)[number]['members'][number]; groups: string[] }>()
  for (const group of groups.value) {
    for (const member of group.members ?? []) {
      const row = map.get(member.user_id)
      if (row) {
        if (!row.groups.includes(group.name)) row.groups.push(group.name)
      } else {
        map.set(member.user_id, { member, groups: [group.name] })
      }
    }
  }
  return [...map.values()].sort((a, b) => a.member.name.localeCompare(b.member.name, 'de'))
})

const availableParents = computed(() => {
  if (!editingGroup.value) {
    return hierarchicalGroups.value
  }
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
  return hierarchicalGroups.value.filter((g) => !excludeIds.has(g.id))
})

const parentGroupSelectItems = computed(() => [
  { title: t('grossanlass.planung.ressorts.parentNone'), value: null },
  ...availableParents.value.map((g) => ({
    title: grossanlassGroupSelectTitle(g, t('grossanlass.planung.ressorts.kindBauprojekt')),
    value: g.id,
  })),
])

const groupModalTitle = computed(() => {
  if (editingGroup.value) return t('grossanlass.planung.ressorts.modalEdit')
  if (fixedParentId.value || groupForm.value.parent_id) {
    return groupForm.value.kind === 'ressort'
      ? t('grossanlass.planung.ressorts.modalNewUnterressort')
      : t('grossanlass.planung.ressorts.modalNewBauprojekt')
  }
  return t('grossanlass.planung.ressorts.modalNewRessort')
})

const showChildKindSelect = computed(() => {
  if (editingGroup.value) {
    return !!editingGroup.value.parent_id && canEditGroup()
  }
  return !!(fixedParentId.value || groupForm.value.parent_id)
})

const childKindSelectItems = computed(() => [
  {
    title: t('grossanlass.planung.ressorts.kindUnterressort'),
    value: 'ressort' as GrossanlassGroupKind,
  },
  {
    title: t('grossanlass.planung.ressorts.kindBauprojekt'),
    value: 'teilbereich' as GrossanlassGroupKind,
  },
])

const groupNameLabel = computed(() => {
  if (!fixedParentId.value && !groupForm.value.parent_id && !editingGroup.value?.parent_id) {
    return t('grossanlass.planung.ressorts.nameLabelRessort')
  }
  return groupForm.value.kind === 'ressort' || editingGroup.value?.kind === 'ressort'
    ? t('grossanlass.planung.ressorts.nameLabelUnterressort')
    : t('grossanlass.planung.ressorts.nameLabelBauprojekt')
})

const groupNamePlaceholder = computed(() => {
  if (!fixedParentId.value && !groupForm.value.parent_id && !editingGroup.value?.parent_id) {
    return t('grossanlass.planung.ressorts.namePlaceholderRessort')
  }
  return groupForm.value.kind === 'ressort' || editingGroup.value?.kind === 'ressort'
    ? t('grossanlass.planung.ressorts.namePlaceholderUnterressort')
    : t('grossanlass.planung.ressorts.namePlaceholderBauprojekt')
})

const unassignedUsers = computed(() => {
  if (!selectedGroup.value) return []
  const assignedIds = new Set(selectedGroup.value.members.map((m) => m.user_id))
  return departmentMembers.value.filter((u) => !assignedIds.has(u.user_id))
})

function nodeIcon(nodeType: GrossanlassNodeType): string {
  if (nodeType === 'bauprojekt') return 'mdi-hammer-wrench'
  if (nodeType === 'unterressort') return 'mdi-source-branch'
  return 'mdi-sitemap'
}

function kindLabel(group: GrossanlassGroup): string {
  if (group.node_type === 'bauprojekt') {
    return t('grossanlass.planung.ressorts.kindBauprojekt')
  }
  if (group.node_type === 'unterressort') {
    return t('grossanlass.planung.ressorts.kindUnterressort')
  }
  return t('grossanlass.planung.ressorts.kindRessort')
}

function isCostEligible(group: GrossanlassGroup): boolean {
  return group.node_type !== 'bauprojekt' && group.kind !== 'teilbereich'
}

function isLogisticsNode(group: GrossanlassGroup): boolean {
  return logisticsGroupId.value === group.id
}

function canSetLogisticsNode(group: GrossanlassGroup): boolean {
  return canFullyManage.value && !logisticsGroupId.value && isCostEligible(group)
}

function getGroupMembersForDisplay(group: GrossanlassGroup): GroupMember[] {
  const leaders = group.members.filter((m) => m.is_leader)
  const members = group.members.filter((m) => !m.is_leader)
  return [...leaders, ...members]
}

async function loadGroups() {
  if (!departmentId.value) return
  isLoading.value = true
  error.value = null
  try {
    const [groupList, planung] = await Promise.all([
      getGrossanlassGroups(departmentId.value),
      getGrossanlassPlanung(departmentId.value),
    ])
    groups.value = groupList
    logisticsGroupId.value = planung.config.logistics_group_id || null
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    error.value = e.response?.data?.error || t('grossanlass.planung.ressorts.errorLoad')
  } finally {
    isLoading.value = false
  }
}

async function setLogisticsNode(group: GrossanlassGroup) {
  if (!departmentId.value || isSavingLogistics.value) return
  isSavingLogistics.value = true
  try {
    const next = await updateGrossanlassPlanung(departmentId.value, {
      logistics_group_id: group.id,
    })
    logisticsGroupId.value = next.config.logistics_group_id || group.id
    toast.success(t('grossanlass.planung.ressorts.costSetToast', { name: group.name }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    isSavingLogistics.value = false
  }
}

async function clearLogisticsNode() {
  if (!canFullyManage.value || !departmentId.value || isSavingLogistics.value) return
  const ok = await confirm.confirm({
    title: t('grossanlass.planung.ressorts.costClearTitle'),
    message: t('grossanlass.planung.ressorts.costClearMessage'),
    confirmText: t('grossanlass.planung.ressorts.costClearConfirm'),
    cancelText: t('common.cancel'),
  })
  if (!ok) return
  isSavingLogistics.value = true
  try {
    await updateGrossanlassPlanung(departmentId.value, { logistics_group_id: null })
    logisticsGroupId.value = null
    toast.success(t('grossanlass.planung.ressorts.costClearToast'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    isSavingLogistics.value = false
  }
}

async function loadDepartmentMembers() {
  if (!departmentId.value) return
  isLoadingUsers.value = true
  try {
    departmentMembers.value = await getDepartmentMembers(departmentId.value)
  } catch {
    // ignore
  } finally {
    isLoadingUsers.value = false
  }
}

function openCreateModal(parentId: string | null = null) {
  editingGroup.value = null
  fixedParentId.value = parentId
  groupForm.value = { name: '', parent_id: parentId, kind: 'ressort' }
  showGroupModal.value = true
  nextTick(() => groupNameInput.value?.focus?.())
}

function openEditModal(group: GrossanlassGroup) {
  editingGroup.value = group
  fixedParentId.value = null
  groupForm.value = {
    name: group.name,
    parent_id: group.parent_id,
    kind: group.kind,
  }
  showGroupModal.value = true
  nextTick(() => groupNameInput.value?.focus?.())
}

function closeGroupModal() {
  showGroupModal.value = false
  editingGroup.value = null
  fixedParentId.value = null
}

async function saveGroup() {
  if (!groupForm.value.name.trim() || isSaving.value || !departmentId.value) return
  isSaving.value = true
  try {
    if (editingGroup.value) {
      await updateGrossanlassGroup(departmentId.value, editingGroup.value.id, {
        name: groupForm.value.name.trim(),
        parent_id: groupForm.value.parent_id,
        kind: editingGroup.value.parent_id ? groupForm.value.kind : undefined,
      })
    } else {
      await createGrossanlassGroup(departmentId.value, {
        name: groupForm.value.name.trim(),
        parent_id: groupForm.value.parent_id,
        kind: groupForm.value.parent_id ? groupForm.value.kind : undefined,
      })
    }
    closeGroupModal()
    await loadGroups()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    isSaving.value = false
  }
}

async function handleDelete(group: GrossanlassGroup) {
  const ok = await confirm.confirm({
    title: t('grossanlass.planung.ressorts.deleteTitle'),
    message: t('grossanlass.planung.ressorts.deleteMessage', { name: group.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok || !departmentId.value) return
  try {
    await deleteGrossanlassGroup(departmentId.value, group.id)
    await loadGroups()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorDelete'))
  }
}

function openMembersModal(group: GrossanlassGroup) {
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
  if (!selectedGroup.value || !addMemberForm.value.user_id || !departmentId.value) return
  try {
    await addGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, {
      user_id: addMemberForm.value.user_id,
      role: canFullyManage.value ? addMemberForm.value.role : 'member',
    })
    await loadGroups()
    const updated = groups.value.find((g) => g.id === selectedGroup.value?.id)
    if (updated) selectedGroup.value = updated
    addMemberForm.value = { user_id: '', role: 'member' }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorAddMember'))
  }
}

async function handleRoleChange(member: GroupMember, newRole: string) {
  if (!selectedGroup.value || !departmentId.value) return
  try {
    await updateGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, member.user_id, {
      role: newRole,
    })
    await loadGroups()
    const updated = groups.value.find((g) => g.id === selectedGroup.value?.id)
    if (updated) selectedGroup.value = updated
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('settings.groups.errorRoleChange'))
  }
}

async function onHelperCreated() {
  await loadGroups()
  const updated = groups.value.find((g) => g.id === selectedGroup.value?.id)
  if (updated) selectedGroup.value = updated
  await loadDepartmentMembers()
}

async function handleRemoveMember(member: GroupMember) {
  if (!selectedGroup.value || !departmentId.value) return
  const ok = await confirm.confirm({
    title: t('settings.groups.removeMemberTitle'),
    message: t('settings.groups.removeMemberMessage', { name: member.name }),
    confirmText: t('common.remove'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await removeGrossanlassGroupMember(departmentId.value, selectedGroup.value.id, member.user_id)
    await loadGroups()
    const updated = groups.value.find((g) => g.id === selectedGroup.value?.id)
    if (updated) selectedGroup.value = updated
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('grossanlass.planung.ressorts.errorRemoveMember'))
  }
}

watch(departmentId, () => loadGroups())
onMounted(() => loadGroups())
</script>

<style scoped>
.grossanlass-ressorts {
  padding: 8px 0 24px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  gap: 16px;
}

.tab-description {
  color: #64748b;
  font-size: 14px;
  margin: 0;
}

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

.ressorts-error {
  margin-top: 8px;
}

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

.group-row:hover {
  background: #f9fafb;
}

.group-row.is-child {
  background: #fafbfc;
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.indent-icon {
  color: #94a3b8;
  font-size: 14px;
}

.group-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  flex-shrink: 0;
}

.group-icon.node-ressort {
  background: #eef2ff;
  color: var(--color-primary, #4f46e5);
}

.group-icon.node-unterressort {
  background: #e0f2fe;
  color: #0369a1;
}

.group-icon.node-bauprojekt {
  background: #fef3c7;
  color: #b45309;
}

.name-stack {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.group-name {
  font-weight: 500;
}

.kind-badge {
  font-size: 11px;
  color: #64748b;
}

.kind-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.cost-hint {
  margin: 0 0 12px;
  color: #64748b;
  font-size: 0.85rem;
}

.cost-flag,
.cost-set-btn {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.2;
  padding: 2px 8px;
}

.cost-flag {
  border: 0;
  background: #ecfdf5;
  color: #166534;
}

.cost-flag.is-editable {
  cursor: pointer;
}

.cost-flag:disabled {
  cursor: default;
}

.cost-set-btn {
  border: 1px solid #86efac;
  background: #fff;
  color: #166534;
  cursor: pointer;
}

.cost-set-btn:hover:not(:disabled) {
  background: #ecfdf5;
}

.cost-set-btn:disabled {
  opacity: 0.6;
  cursor: default;
}

.col-actions {
  width: 160px;
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

.members-section,
.add-member-section,
.add-helper-section {
  margin-bottom: 20px;
}

.add-helper-section {
  padding-bottom: 16px;
  border-bottom: 1px solid #e2e8f0;
}

.section-title {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  margin: 0 0 10px;
}

.members-table {
  width: 100%;
  border-collapse: collapse;
}

.members-table th,
.members-table td {
  padding: 8px 10px;
  text-align: left;
  font-size: 13px;
  border-bottom: 1px solid #f1f5f9;
}

.add-member-form {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.form-select {
  padding: 8px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
}

.user-select {
  min-width: 240px;
}

.role-select,
.role-select-sm {
  min-width: 120px;
}

.loading-inline {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #64748b;
  font-size: 13px;
}

.spinner-sm {
  width: 16px;
  height: 16px;
  border: 2px solid #e2e8f0;
  border-top-color: var(--color-primary, #4f46e5);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.empty-members,
.no-users-hint {
  color: #64748b;
  font-size: 13px;
}

.role-readonly {
  font-size: 13px;
  color: #475569;
}
.ga-ressort-accordions { margin-top: 4px; }
.ga-ressort-accordions :deep(.v-expansion-panel) {
  border: 1px solid #e5e7eb;
  border-radius: 10px !important;
  margin-bottom: 8px;
}
.panel-count { margin-left: 8px; color: #64748b; font-size: 0.85rem; }
.member-overview { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.member-overview__row { display: flex; align-items: center; gap: 10px; }
.member-overview__meta { display: flex; flex-direction: column; gap: 2px; }
.member-overview__meta span { color: #64748b; font-size: 0.8rem; }
</style>
