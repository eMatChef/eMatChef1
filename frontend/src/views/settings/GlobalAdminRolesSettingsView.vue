<template>
  <div class="global-admin-roles-settings">
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.globalAdminRoles.title') }}</h2>
        <p class="settings-description">{{ t('settings.globalAdminRoles.subtitle') }}</p>
      </div>
      <button class="btn btn-secondary" @click="loadUsers" :disabled="isLoading">
        {{ isLoading ? t('settings.globalAdminRoles.loadingShort') : t('common.refresh') }}
      </button>
    </div>

    <div v-if="isLoading" class="state-card">{{ t('settings.globalAdminRoles.loading') }}</div>
    <div v-else-if="error" class="state-card state-error">
      <p>{{ error }}</p>
      <button class="btn btn-secondary" @click="loadUsers">{{ t('common.retry') }}</button>
    </div>
    <div v-else-if="adminUsers.length === 0" class="state-card">{{ t('settings.globalAdminRoles.empty') }}</div>

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
              <button class="icon-btn" :title="t('common.edit')" @click="openEditModal(user.id)">
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

    <p class="hint-all-users">
      {{ t('settings.globalAdminRoles.allUsersHint') }}
      <router-link to="/admin-dashboard/verwaltung/users">{{ t('settings.globalAdminRoles.allUsersLink') }}</router-link>
      ·
      <router-link to="/admin-dashboard/verwaltung/user-org-overview">{{ t('settings.globalAdminRoles.overviewLink') }}</router-link>
    </p>

    <Teleport to="body">
      <div v-if="showEditModal && editForm" class="modal-overlay">
        <div class="modal-container">
          <div class="modal-header">
            <h3>{{ t('settings.globalAdminRoles.editTitle', { name: editForm.display_name }) }}</h3>
            <button class="close-btn" @click="closeEditModal">×</button>
          </div>

          <div class="modal-body">
            <div class="form-group">
              <label>{{ t('settings.globalAdminRoles.fields.globalRole') }}</label>
              <select v-model="editForm.global_admin_role" class="form-select" @change="onGlobalRoleChange">
                <option value="none">{{ t('settings.adminUsers.globalRoles.none') }}</option>
                <option value="org">{{ t('settings.adminUsers.globalRoles.org') }}</option>
                <option value="sub">{{ t('settings.adminUsers.globalRoles.sub') }}</option>
              </select>
            </div>

            <div v-if="editForm.global_admin_role !== 'none'" class="capabilities-block">
              <h4>{{ t('settings.adminUsers.capabilitiesTitle') }}</h4>
              <div v-for="group in capabilityGroups" :key="group.id" class="capability-group">
                <div class="capability-group-title">{{ t(group.labelKey) }}</div>
                <label v-for="item in group.items" :key="item.key" class="capability-checkbox">
                  <input
                    type="checkbox"
                    :checked="getCapability(item.key)"
                    @change="setCapability(item.key, ($event.target as HTMLInputElement).checked)"
                  />
                  {{ t(item.labelKey) }}
                </label>
              </div>

              <div class="scope-block">
                <h4>{{ t('settings.globalAdminRoles.scopeTitle') }}</h4>
                <p class="inline-hint">{{ t('settings.globalAdminRoles.scopeHint') }}</p>
                <div v-if="scopeTree.length === 0" class="inline-hint">
                  {{ t('settings.globalAdminRoles.organisationScopeEmpty') }}
                </div>
                <div v-else class="scope-tree">
                  <div v-for="org in scopeTree" :key="org.id" class="scope-org">
                    <label class="capability-checkbox scope-org-header">
                      <input
                        type="checkbox"
                        :checked="editForm.admin_capabilities.scope.organisation_ids.includes(org.id)"
                        @click.stop
                        @change="toggleOrganisationScope(org.id, ($event.target as HTMLInputElement).checked)"
                      />
                      <span class="scope-org-name">{{ org.name }}</span>
                    </label>
                    <p v-if="org.flatNodes.length === 0" class="inline-hint scope-org-no-depts">
                      {{ t('settings.globalAdminRoles.orgNoDepartmentsYet') }}
                    </p>
                    <label
                      v-for="node in org.flatNodes"
                      :key="node.id"
                      class="capability-checkbox scope-dept-node"
                      :class="{ 'scope-dept-node--org-selected': isOrgFullyScoped(org.id) }"
                      :style="{ marginLeft: `${28 + node.level * 16}px` }"
                      :title="isOrgFullyScoped(org.id) ? t('settings.globalAdminRoles.deptUnderOrgHint') : undefined"
                    >
                      <input
                        type="checkbox"
                        :checked="editForm.admin_capabilities.scope.department_root_ids.includes(node.id)"
                        @click.stop
                        @change="toggleDepartmentRoot(node.id, ($event.target as HTMLInputElement).checked)"
                      />
                      {{ node.name }}
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeEditModal">{{ t('common.cancel') }}</button>
            <button class="btn btn-primary" @click="save" :disabled="isSaving">
              {{ isSaving ? t('settings.adminUsers.saving') : t('common.save') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
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

function setCapability(dotKey: string, value: boolean) {
  if (!editForm.value) return
  editForm.value.admin_capabilities = setCapabilityValue(editForm.value.admin_capabilities, dotKey, value)
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

function toggleOrganisationScope(orgId: string, checked: boolean) {
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

function toggleDepartmentRoot(deptId: string, checked: boolean) {
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
  cursor: pointer;
}

.hint-all-users {
  margin-top: 14px;
  font-size: 13px;
  color: #64748b;
}

.modal-container {
  width: 100%;
  max-width: 720px;
  max-height: 90vh;
  overflow: hidden;
  background: white;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
}

.modal-header,
.modal-footer {
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-footer {
  border-bottom: none;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
}

.close-btn {
  border: none;
  background: transparent;
  font-size: 20px;
  cursor: pointer;
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

.capability-checkbox {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-bottom: 4px;
  font-size: 14px;
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
