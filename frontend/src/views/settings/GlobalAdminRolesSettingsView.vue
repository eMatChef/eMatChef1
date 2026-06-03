<template>
  <div class="global-admin-roles-settings">
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.globalAdminRoles.title') }}</h2>
        <p class="settings-description">{{ t('settings.globalAdminRoles.subtitle') }}</p>
      </div>
      <EButton variant="secondary" :loading="isLoading" @click="loadUsers">
        {{ isLoading ? t('settings.globalAdminRoles.loadingShort') : t('common.refresh') }}
      </EButton>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="table"
      :rows="5"
      :message="t('settings.globalAdminRoles.loading')"
    />
    <div v-else-if="error" class="error-block">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadUsers">{{ t('common.retry') }}</EButton>
    </div>
    <EEmptyState
      v-else-if="adminUsers.length === 0"
      variant="generic"
      :title="t('settings.globalAdminRoles.empty')"
    />

    <div v-else class="table-wrapper">
      <table class="users-table">
        <thead>
          <tr>
            <th>{{ t('common.name') }}</th>
            <th>{{ t('settings.globalAdminRoles.columns.email') }}</th>
            <th>{{ t('common.role') }}</th>
            <th>{{ t('settings.globalAdminRoles.columns.scope') }}</th>
            <th class="actions-col">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in adminUsers" :key="user.id">
            <td>{{ user.name }}</td>
            <td>{{ user.email }}</td>
            <td>{{ formatGlobalRole(user.global_admin_role) }}</td>
            <td>{{ formatScopeSummary(user.id) }}</td>
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

    <p class="hint-all-users">
      {{ t('settings.globalAdminRoles.allUsersHint') }}
      <router-link to="/admin-dashboard/verwaltung/users">{{ t('settings.globalAdminRoles.allUsersLink') }}</router-link>
      ·
      <router-link to="/admin-dashboard/verwaltung/user-org-overview">{{ t('settings.globalAdminRoles.overviewLink') }}</router-link>
    </p>

    <EDialog
      v-model="showEditModal"
      :max-width="720"
      :title="editForm ? t('settings.globalAdminRoles.editTitle', { name: editForm.display_name }) : ''"
      scrollable
      persistent
    >
      <template v-if="editForm">
        <ESelect
          v-model="editForm.global_admin_role"
          :label="t('settings.globalAdminRoles.fields.globalRole')"
          :items="globalRoleSelectItems"
          hide-details="auto"
          class="mb-3"
          @update:model-value="onGlobalRoleChange"
        />

        <div v-if="editForm.global_admin_role !== 'none'" class="capabilities-block">
          <h4>{{ t('settings.adminUsers.capabilitiesTitle') }}</h4>
          <div v-for="group in capabilityGroups" :key="group.id" class="capability-group">
            <div class="capability-group-title">{{ t(group.labelKey) }}</div>
            <ECheckbox
              v-for="item in group.items"
              :key="item.key"
              :model-value="getCapability(item.key)"
              :label="t(item.labelKey)"
              hide-details
              @update:model-value="setCapability(item.key, $event)"
            />
          </div>

          <div class="scope-block">
            <h4>{{ t('settings.globalAdminRoles.scopeTitle') }}</h4>
            <p class="inline-hint">{{ t('settings.globalAdminRoles.scopeHint') }}</p>
            <div v-if="scopeTree.length === 0" class="inline-hint">
              {{ t('settings.globalAdminRoles.organisationScopeEmpty') }}
            </div>
            <div v-else class="scope-tree">
              <div v-for="org in scopeTree" :key="org.id" class="scope-org">
                <ECheckbox
                  :model-value="editForm.admin_capabilities.scope.organisation_ids.includes(org.id)"
                  :label="org.name"
                  hide-details
                  class="scope-org-header"
                  @update:model-value="toggleOrganisationScope(org.id, $event)"
                />
                <p v-if="org.flatNodes.length === 0" class="inline-hint scope-org-no-depts">
                  {{ t('settings.globalAdminRoles.orgNoDepartmentsYet') }}
                </p>
                <ECheckbox
                  v-for="node in org.flatNodes"
                  :key="node.id"
                  :model-value="editForm.admin_capabilities.scope.department_root_ids.includes(node.id)"
                  :label="node.name"
                  :disabled="isOrgFullyScoped(org.id)"
                  hide-details
                  class="scope-dept-node"
                  :class="{ 'scope-dept-node--org-selected': isOrgFullyScoped(org.id) }"
                  :style="{ marginLeft: `${28 + node.level * 16}px` }"
                  :title="isOrgFullyScoped(org.id) ? t('settings.globalAdminRoles.deptUnderOrgHint') : undefined"
                  @update:model-value="toggleDepartmentRoot(node.id, $event)"
                />
              </div>
            </div>
          </div>
        </div>
      </template>

      <template #actions>
        <EButton variant="secondary" @click="closeEditModal">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :loading="isSaving" :disabled="isSaving" @click="save">
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
import {
  getAdminUsers,
  getAdminUserDetail,
  updateAdminUser,
  getOrganisationsForAdmin,
} from '@/api/adminUsers'
import { getDepartments, type Department } from '@/api/departments'
import {
  filterDepartmentsForAdminScope,
  filterOrganisationsForAdminScope,
} from '@/utils/organisationUserPicker'
import { useToast } from '@/composables/useToast'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, ECheckbox, EDialog, ESelect } from '@/components/form/base'
import {
  ADMIN_CAPABILITY_GROUPS,
  cloneAdminCapabilities,
  defaultAdminCapabilities,
  formatAdminScopeSummary,
  getCapabilityValue,
  normalizeAdminCapabilities,
  setCapabilityValue,
  type AdminCapabilities,
  type GlobalAdminRole,
} from '@/utils/adminCapabilities'

interface DeptScopeNode {
  id: string
  name: string
  children: DeptScopeNode[]
}

interface FlatDeptNode {
  id: string
  name: string
  level: number
}

interface OrgScopeNode {
  id: string
  name: string
  flatNodes: FlatDeptNode[]
}

interface EditForm {
  user_id: string
  display_name: string
  global_admin_role: GlobalAdminRole
  admin_capabilities: AdminCapabilities
}

function flattenDeptNodes(nodes: DeptScopeNode[], level = 0): FlatDeptNode[] {
  const out: FlatDeptNode[] = []
  for (const node of nodes) {
    out.push({ id: node.id, name: node.name, level })
    out.push(...flattenDeptNodes(node.children, level + 1))
  }
  return out
}

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const capabilityGroups = ADMIN_CAPABILITY_GROUPS

const globalRoleSelectItems = computed(() => [
  { title: t('settings.adminUsers.globalRoles.none'), value: 'none' },
  { title: t('settings.adminUsers.globalRoles.org'), value: 'org' },
  { title: t('settings.adminUsers.globalRoles.sub'), value: 'sub' },
])

const users = ref<Awaited<ReturnType<typeof getAdminUsers>>>([])
const departments = ref<Department[]>([])
const organisations = ref<Array<{ id: string; name: string }>>([])
const scopeSummaryCache = ref<Record<string, string>>({})
const isLoading = ref(false)
const isSaving = ref(false)
const error = ref<string | null>(null)
const showEditModal = ref(false)
const editForm = ref<EditForm | null>(null)

const adminUsers = computed(() =>
  users.value.filter((u) => u.global_admin_role === 'org' || u.global_admin_role === 'sub')
)

const scopeTree = computed((): OrgScopeNode[] => {
  const depts = departments.value

  function buildDeptTree(orgId: string, parentId: string | null): DeptScopeNode[] {
    return depts
      .filter((d) => d.organisation_id === orgId && (d.parent_id || null) === parentId)
      .map((d) => ({
        id: d.id,
        name: d.name,
        children: buildDeptTree(orgId, d.id),
      }))
  }

  return organisations.value.map((org) => {
    const tree = buildDeptTree(org.id, null)
    return {
      id: org.id,
      name: org.name,
      flatNodes: flattenDeptNodes(tree),
    }
  })
})

const scopeSummaryLabels = computed(() => ({
  all: t('settings.globalAdminRoles.scopeAll'),
  orgs: (names: string[]) => t('settings.globalAdminRoles.scopeOrgsOnly', { names: names.join(', ') }),
  depts: (names: string[]) => t('settings.globalAdminRoles.scopeDeptsOnly', { names: names.join(', ') }),
  mixed: (orgNames: string[], deptNames: string[]) =>
    t('settings.globalAdminRoles.scopeOrgsAndDepts', {
      orgs: orgNames.join(', '),
      depts: deptNames.join(', '),
    }),
}))

function formatGlobalRole(role: string | undefined): string {
  const key = role === 'org' || role === 'sub' ? role : 'none'
  return t(`settings.adminUsers.globalRoles.${key}`)
}

function formatScopeSummary(userId: string): string {
  return scopeSummaryCache.value[userId] || '—'
}

async function loadScopeSummaries() {
  const depts = departments.value
  const nameById = new Map(depts.map((d) => [d.id, d.name]))
  const orgNameById = new Map(organisations.value.map((o) => [o.id, o.name]))
  for (const user of adminUsers.value) {
    try {
      const detail = await getAdminUserDetail(user.id)
      const scope = detail.admin_capabilities?.scope || { organisation_ids: [], department_root_ids: [] }
      scopeSummaryCache.value[user.id] = formatAdminScopeSummary(
        scope,
        orgNameById,
        nameById,
        scopeSummaryLabels.value
      )
    } catch {
      scopeSummaryCache.value[user.id] = '—'
    }
  }
}

async function loadUsers() {
  isLoading.value = true
  error.value = null
  try {
    const [userList, deptList, orgList] = await Promise.all([
      getAdminUsers({ sortBy: 'name', sortDir: 'asc' }),
      getDepartments(),
      getOrganisationsForAdmin(),
    ])
    users.value = userList
    organisations.value = filterOrganisationsForAdminScope(orgList)
    departments.value = filterDepartmentsForAdminScope(deptList)
    await loadScopeSummaries()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    error.value = e.response?.data?.error || t('settings.globalAdminRoles.loadError')
  } finally {
    isLoading.value = false
  }
}

function onGlobalRoleChange() {
  if (!editForm.value) return
  editForm.value.admin_capabilities = cloneAdminCapabilities(
    defaultAdminCapabilities(editForm.value.global_admin_role)
  )
}

function getCapability(dotKey: string): boolean {
  if (!editForm.value) return false
  return getCapabilityValue(editForm.value.admin_capabilities, dotKey)
}

function setCapability(dotKey: string, value: boolean | null) {
  if (!editForm.value) return
  editForm.value.admin_capabilities = setCapabilityValue(editForm.value.admin_capabilities, dotKey, !!value)
}

function deptIdsInOrganisation(orgId: string): Set<string> {
  return new Set(departments.value.filter((d) => d.organisation_id === orgId).map((d) => d.id))
}

function getDeptAncestorIds(deptId: string): string[] {
  const ancestors: string[] = []
  let parentId = departments.value.find((d) => d.id === deptId)?.parent_id
  while (parentId) {
    ancestors.push(parentId)
    parentId = departments.value.find((d) => d.id === parentId)?.parent_id
  }
  return ancestors
}

function getDeptDescendantIds(deptId: string): string[] {
  const result: string[] = []
  const stack = departments.value.filter((d) => d.parent_id === deptId).map((d) => d.id)
  while (stack.length > 0) {
    const id = stack.pop()!
    result.push(id)
    for (const child of departments.value) {
      if (child.parent_id === id) stack.push(child.id)
    }
  }
  return result
}

function isOrgFullyScoped(orgId: string): boolean {
  return editForm.value?.admin_capabilities.scope.organisation_ids.includes(orgId) ?? false
}

function toggleOrganisationScope(orgId: string, checked: boolean | null) {
  if (!editForm.value) return
  const caps = cloneAdminCapabilities(editForm.value.admin_capabilities)
  const orgIds = new Set(caps.scope.organisation_ids)
  const inOrg = deptIdsInOrganisation(orgId)

  if (checked) {
    orgIds.add(orgId)
    caps.scope.department_root_ids = caps.scope.department_root_ids.filter((id) => !inOrg.has(id))
  } else {
    orgIds.delete(orgId)
  }

  caps.scope.organisation_ids = Array.from(orgIds)
  editForm.value.admin_capabilities = caps
}

function toggleDepartmentRoot(deptId: string, checked: boolean | null) {
  if (!editForm.value) return
  const caps = cloneAdminCapabilities(editForm.value.admin_capabilities)
  const ids = new Set(caps.scope.department_root_ids)
  const dept = departments.value.find((d) => d.id === deptId)

  if (checked) {
    if (dept?.organisation_id) {
      caps.scope.organisation_ids = caps.scope.organisation_ids.filter((id) => id !== dept.organisation_id)
    }
    for (const anc of getDeptAncestorIds(deptId)) ids.delete(anc)
    for (const desc of getDeptDescendantIds(deptId)) ids.delete(desc)
    ids.add(deptId)
  } else {
    ids.delete(deptId)
  }

  caps.scope.department_root_ids = Array.from(ids)
  editForm.value.admin_capabilities = caps
}

async function openEditModal(userId: string) {
  try {
    const detail = await getAdminUserDetail(userId)
    const globalRole = (detail.global_admin_role === 'org' || detail.global_admin_role === 'sub'
      ? detail.global_admin_role
      : 'none') as GlobalAdminRole
    editForm.value = {
      user_id: detail.id,
      display_name: detail.name,
      global_admin_role: globalRole,
      admin_capabilities: cloneAdminCapabilities(
        normalizeAdminCapabilities(detail.admin_capabilities_stored ?? detail.admin_capabilities, globalRole)
      ),
    }
    showEditModal.value = true
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('settings.globalAdminRoles.detailsLoadError'))
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
  clearEditQuery()
}

async function tryOpenEditFromQuery() {
  const editId = route.query.edit
  if (typeof editId !== 'string' || !editId) return
  await openEditModal(editId)
}

async function save() {
  if (!editForm.value || isSaving.value) return
  isSaving.value = true
  try {
    const detail = await getAdminUserDetail(editForm.value.user_id)
    const capsPayload =
      editForm.value.global_admin_role !== 'none'
        ? cloneAdminCapabilities(editForm.value.admin_capabilities)
        : undefined

    await updateAdminUser(editForm.value.user_id, {
      first_name: detail.first_name,
      last_name: detail.last_name,
      nickname: detail.nickname,
      email: detail.email,
      state: detail.state,
      global_admin_role: editForm.value.global_admin_role,
      admin_capabilities: capsPayload,
      memberships: detail.memberships.map((m) => ({
        department_id: m.department_id,
        role: m.role,
        is_primary: m.is_primary,
      })),
    })
    scopeSummaryCache.value = {}
    toast.success(t('settings.globalAdminRoles.toastUpdated'))
    closeEditModal()
    await loadUsers()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('settings.globalAdminRoles.saveError'))
  } finally {
    isSaving.value = false
  }
}

onMounted(async () => {
  await loadUsers()
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
.global-admin-roles-settings {
  padding: 0;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
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
}

.actions-col {
  width: 90px;
}

.hint-all-users {
  margin-top: 14px;
  font-size: 13px;
  color: #64748b;
}

.capabilities-block {
  margin-top: 20px;
}

.capability-group {
  margin-bottom: 12px;
}

.capability-group-title {
  font-weight: 600;
  font-size: 13px;
  margin-bottom: 6px;
}

.scope-block {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
}

.inline-hint {
  color: #6b7280;
  font-size: 13px;
  margin-bottom: 10px;
}

.scope-org-header {
  font-weight: 600;
  font-size: 14px;
  margin-bottom: 6px;
  padding: 4px 0;
}

.scope-org-name {
  color: #1e293b;
}

.scope-org-no-depts {
  margin: 0 0 8px 28px;
  font-style: italic;
}

.scope-dept-node {
  margin-bottom: 4px;
}

.scope-dept-node--org-selected {
  opacity: 0.75;
}

.scope-tree {
  max-height: 280px;
  overflow-y: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  background: #f8fafc;
}

.scope-org {
  margin-bottom: 12px;
}
</style>
