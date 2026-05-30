<template>
  <div class="user-org-overview">
    <div class="page-header">
      <div>
        <h2 class="settings-title">{{ t('settings.userOrgOverview.title') }}</h2>
        <p class="settings-description">{{ t('settings.userOrgOverview.subtitle') }}</p>
      </div>
      <button class="btn btn-secondary" @click="load" :disabled="isLoading">
        {{ isLoading ? t('settings.userOrgOverview.loadingShort') : t('common.refresh') }}
      </button>
    </div>

    <div class="toolbar">
      <div class="view-toggle">
        <button
          type="button"
          class="toggle-btn"
          :class="{ active: viewMode === 'tree' }"
          @click="viewMode = 'tree'"
        >
          {{ t('settings.userOrgOverview.viewTree') }}
        </button>
        <button
          type="button"
          class="toggle-btn"
          :class="{ active: viewMode === 'kanban' }"
          @click="viewMode = 'kanban'"
        >
          {{ t('settings.userOrgOverview.viewKanban') }}
        </button>
      </div>
      <div class="search-box">
        <SearchFieldInput
          v-model="searchQuery"
          :label="t('settings.userOrgOverview.searchPlaceholder')"
        />
      </div>
      <label class="filter-checkbox">
        <input v-model="onlyWithAssignments" type="checkbox" />
        {{ t('settings.userOrgOverview.onlyAssigned') }}
      </label>
      <div v-if="viewMode === 'tree'" class="tree-actions">
        <button type="button" class="btn btn-secondary btn-sm" @click="expandMyBranch">
          {{ t('settings.userOrgOverview.expandMyBranch') }}
        </button>
        <button type="button" class="btn btn-secondary btn-sm" @click="collapseAll">
          {{ t('settings.userOrgOverview.collapseAll') }}
        </button>
        <button type="button" class="btn btn-secondary btn-sm" @click="expandAll">
          {{ t('settings.userOrgOverview.expandAll') }}
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="state-card">{{ t('settings.userOrgOverview.loading') }}</div>
    <div v-else-if="error" class="state-card state-error">
      <p>{{ error }}</p>
      <button class="btn btn-secondary" @click="load">{{ t('common.retry') }}</button>
    </div>

    <template v-else>
      <!-- Tree -->
      <div v-if="viewMode === 'tree'" class="overview-tree">
        <div
          v-for="org in orgTrees"
          :key="org.id"
          class="org-accordion"
          :class="{
            expanded: expandedOrgIds.includes(org.id),
            'is-my-branch': orgContainsBranchRoot(org.id),
          }"
        >
          <button type="button" class="org-accordion-header" @click="toggleOrg(org.id)">
            <span class="accordion-chevron" aria-hidden="true">{{ expandedOrgIds.includes(org.id) ? '▼' : '▶' }}</span>
            <span class="org-accordion-title">{{ org.name }}</span>
            <span v-if="orgContainsBranchRoot(org.id)" class="org-branch-badge">
              {{ t('settings.userOrgOverview.myBranch') }}
            </span>
            <span class="org-assignment-count">{{ countOrgAssignments(org) }}</span>
          </button>
          <div v-show="expandedOrgIds.includes(org.id)" class="org-accordion-panel">
            <div
              v-if="hasOrgWideScope(org.id)"
              class="org-wide-frame admin-scope-frame"
              :class="`scope-frame-${orgGlobalFrameLevel(org.id)}`"
            >
              <div class="scope-frame-banner">
                {{ t('settings.userOrgOverview.scopeFrameOrgWide', { name: org.name }) }}
              </div>
              <div class="org-wide-frame-body">
                <div class="org-global-strip">
                  <div class="section-label">{{ t('settings.userOrgOverview.globalRolesSection') }}</div>
                  <div class="org-global-cards">
                    <UserRoleGroupCard
                      v-for="group in orgGlobalGroups(org.id)"
                      :key="`org-${org.id}-${group.user.id}`"
                      :group="group"
                      :scope-label="orgGlobalScopeLabel(group)"
                      plain
                      :format-dept-role="formatDeptRole"
                      :format-global-role="formatGlobalRole"
                      @edit-user="(userId, kind) => openUserEdit(userId, kind)"
                    />
                  </div>
                </div>
                <DeptOverviewNode
                  v-for="node in org.children"
                  :key="node.id"
                  :node="node"
                  :scope-root-ids="scopeRootIdSet"
                  :branch-root-ids="branchRootIdSet"
                  :dept-name-by-id="deptNameById"
                  :org-name-by-id="orgNameById"
                  :format-dept-role="formatDeptRole"
                  :format-global-role="formatGlobalRole"
                  :inside-admin-scope="true"
                  @edit-user="openUserEdit"
                />
              </div>
            </div>
            <template v-else>
              <DeptOverviewNode
                v-for="node in org.children"
                :key="node.id"
                :node="node"
                :scope-root-ids="scopeRootIdSet"
                :branch-root-ids="branchRootIdSet"
                :dept-name-by-id="deptNameById"
                :org-name-by-id="orgNameById"
                :format-dept-role="formatDeptRole"
                :format-global-role="formatGlobalRole"
                @edit-user="openUserEdit"
              />
            </template>
          </div>
        </div>
        <div v-if="orgTrees.length === 0" class="state-card">{{ t('settings.userOrgOverview.empty') }}</div>
      </div>

      <!-- Kanban -->
      <div v-else class="kanban-board">
        <div
          v-for="col in kanbanColumns"
          :key="col.id"
          class="kanban-column"
        >
          <div class="kanban-column-header">
            <span class="column-title">{{ col.name }}</span>
            <span class="column-org">{{ col.orgName }}</span>
            <span class="column-count">{{ col.groups.length }}</span>
          </div>
          <div class="kanban-column-body">
            <div v-for="group in col.groups" :key="`${col.id}-${group.user.id}`" class="kanban-card-wrap">
              <UserRoleGroupCard
                :group="group"
                :scope-label="kanbanScopeLabel(group)"
                :format-dept-role="formatDeptRole"
                :format-global-role="formatGlobalRole"
                @edit-user="openUserEdit"
              />
              <div v-if="kanbanOtherLinks(group, col.id).length > 0" class="card-other">
                {{ t('settings.userOrgOverview.alsoAt') }}:
                {{ kanbanOtherLinks(group, col.id).join(', ') }}
              </div>
            </div>
            <div v-if="col.groups.length === 0" class="kanban-empty">{{ t('settings.userOrgOverview.kanbanEmpty') }}</div>
          </div>
        </div>
        <div v-if="kanbanColumns.length === 0" class="state-card">{{ t('settings.userOrgOverview.empty') }}</div>
      </div>

      <p class="legend">
        <span class="legend-item"><span class="badge badge-membership">U</span> {{ t('settings.userOrgOverview.legendMembership') }}</span>
        <span class="legend-item"><span class="badge badge-global-sub">Sub</span> {{ t('settings.userOrgOverview.legendGlobalSub') }}</span>
        <span class="legend-item"><span class="badge badge-global-org">Org</span> {{ t('settings.userOrgOverview.legendGlobalOrg') }}</span>
        <span class="legend-item"><span class="legend-frame legend-frame-org" /> {{ t('settings.userOrgOverview.legendFrameOrg') }}</span>
        <span class="legend-item"><span class="legend-frame legend-frame-sub" /> {{ t('settings.userOrgOverview.legendFrameSub') }}</span>
        <span class="legend-item legend-hint">{{ t('settings.userOrgOverview.legendDeptRolesToggle') }}</span>
        <span class="legend-item legend-hint">{{ t('settings.userOrgOverview.legendClickEdit') }}</span>
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import {
  getAdminOrgOverview,
  getOrganisationsForAdmin,
  type AdminOrgOverviewUser,
  type DepartmentRole,
} from '@/api/adminUsers'
import { getDepartments, type Department } from '@/api/departments'
import SearchFieldInput from '@/components/common/SearchFieldInput.vue'
import DeptOverviewNode, {
  type DeptAssignment,
  type DeptTreeNode,
} from '@/components/admin/DeptOverviewNode.vue'
import UserRoleGroupCard from '@/components/admin/UserRoleGroupCard.vue'
import {
  groupAssignments,
  scopeLabelForUser,
  type OverviewKind,
  type UserRoleGroup,
} from '@/utils/userRoleDisplay'
import { useAuthStore } from '@/stores/auth'
import { filterDepartmentsByAccessibleIds } from '@/utils/adminCapabilities'
import {
  filterDepartmentsForAdminScope,
  filterOrganisationsForAdminScope,
  isDepartmentHiddenFromAdminScope,
} from '@/utils/organisationUserPicker'

interface OrgTree {
  id: string
  name: string
  children: DeptTreeNode[]
}

interface KanbanEntry {
  user: AdminOrgOverviewUser
  kind: OverviewKind
  role: string
  isPrimary: boolean
  otherLinks: string[]
}

interface KanbanColumn {
  id: string
  name: string
  orgName: string
  groups: UserRoleGroup[]
  entries: KanbanEntry[]
}

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

const viewMode = ref<'tree' | 'kanban'>('tree')
const searchQuery = ref('')
const onlyWithAssignments = ref(true)
const isLoading = ref(false)
const error = ref<string | null>(null)
const users = ref<AdminOrgOverviewUser[]>([])
const departments = ref<Department[]>([])
const organisations = ref<Array<{ id: string; name: string }>>([])
const expandedOrgIds = ref<string[]>([])

const branchRootIds = computed(() => computeMyBranchRootIds())

const branchRootIdSet = computed(() => new Set(branchRootIds.value))

/** Department-Wurzeln mit globalem Verwaltungs-Geltungsbereich → Rahmen um Unterbaum */
const scopeRootIdSet = computed(() => {
  const ids = new Set<string>()
  for (const u of filteredUsers.value) {
    if (u.global_admin_role !== 'org' && u.global_admin_role !== 'sub') continue
    for (const id of u.department_root_ids || []) {
      if (departments.value.some((d) => d.id === id)) ids.add(id)
    }
  }
  return ids
})

const deptNameById = computed(() => new Map(departments.value.map((d) => [d.id, d.name])))
const orgNameById = computed(() => new Map(organisations.value.map((o) => [o.id, o.name])))

const filteredUsers = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return users.value.filter((u) => {
    if (
      onlyWithAssignments.value &&
      u.memberships.length === 0 &&
      u.department_root_ids.length === 0 &&
      (u.organisation_ids?.length ?? 0) === 0
    ) {
      return false
    }
    if (!q) return true
    return u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
  })
})

function formatDeptRole(role: string): string {
  const key = role as DepartmentRole
  if (['mw', 'dc', 'l1', 'l2', 'l3', 'u'].includes(key)) {
    return t(`settings.adminUsers.roles.${key}`)
  }
  return role
}

function formatGlobalRole(role: string): string {
  const key = role === 'org' || role === 'sub' ? role : 'none'
  return t(`settings.adminUsers.globalRoles.${key}`)
}

function buildOrgGlobalAssignments(orgId: string): DeptAssignment[] {
  const list: DeptAssignment[] = []
  for (const user of filteredUsers.value) {
    if (user.global_admin_role !== 'org' && user.global_admin_role !== 'sub') continue
    if ((user.department_root_ids?.length ?? 0) > 0) continue
    const orgIds = user.organisation_ids || []
    if (orgIds.length > 0 && !orgIds.includes(orgId)) continue
    list.push({
      user,
      kind: 'global_scope',
      role: String(user.global_admin_role),
      isPrimary: false,
    })
  }
  return list
}

function orgGlobalGroups(orgId: string): UserRoleGroup[] {
  return groupAssignments(buildOrgGlobalAssignments(orgId))
}

function hasOrgWideScope(orgId: string): boolean {
  return buildOrgGlobalAssignments(orgId).length > 0
}

function orgGlobalFrameLevel(orgId: string): 'org' | 'sub' {
  const assignments = buildOrgGlobalAssignments(orgId)
  if (assignments.some((a) => a.role === 'org')) return 'org'
  return 'sub'
}

function buildAssignmentsByDept(): Map<string, DeptAssignment[]> {
  const map = new Map<string, DeptAssignment[]>()
  const add = (deptId: string, assignment: DeptAssignment) => {
    const list = map.get(deptId) || []
    const exists = list.some(
      (a) => a.user.id === assignment.user.id && a.kind === assignment.kind && a.role === assignment.role
    )
    if (!exists) list.push(assignment)
    map.set(deptId, list)
  }

  for (const user of filteredUsers.value) {
    for (const m of user.memberships) {
      if (isDepartmentHiddenFromAdminScope({ id: m.department_id, name: m.department_name })) {
        continue
      }
      add(m.department_id, {
        user,
        kind: 'membership',
        role: m.role,
        isPrimary: m.is_primary,
      })
    }
    if (user.global_admin_role === 'org' || user.global_admin_role === 'sub') {
      for (const rootId of user.department_root_ids || []) {
        add(rootId, {
          user,
          kind: 'global_scope',
          role: String(user.global_admin_role),
          isPrimary: false,
        })
      }
    }
  }

  for (const [, list] of map) {
    list.sort((a, b) => a.user.name.localeCompare(b.user.name, 'de'))
  }
  return map
}

function buildDeptTree(orgId: string, parentId: string | null, byDept: Map<string, DeptAssignment[]>): DeptTreeNode[] {
  return departments.value
    .filter((d) => d.organisation_id === orgId && (d.parent_id || null) === parentId)
    .map((d) => ({
      id: d.id,
      name: d.name,
      organisationId: orgId,
      children: buildDeptTree(orgId, d.id, byDept),
      assignments: byDept.get(d.id) || [],
    }))
}

const orgTrees = computed((): OrgTree[] => {
  const byDept = buildAssignmentsByDept()
  return organisations.value
    .map((org) => ({
      id: org.id,
      name: org.name,
      children: buildDeptTree(org.id, null, byDept),
    }))
    .filter((org) => {
      if (!onlyWithAssignments.value) return true
      return org.children.length > 0 || buildOrgGlobalAssignments(org.id).length > 0
    })
})

function collectOtherLinks(user: AdminOrgOverviewUser, currentDeptId: string): string[] {
  const links: string[] = []
  for (const m of user.memberships) {
    if (m.department_id !== currentDeptId) {
      links.push(`${deptNameById.value.get(m.department_id) || m.department_name} (${formatDeptRole(m.role)})`)
    }
  }
  if (user.global_admin_role === 'org' || user.global_admin_role === 'sub') {
    for (const rootId of user.department_root_ids || []) {
      if (rootId !== currentDeptId) {
        links.push(`${deptNameById.value.get(rootId) || rootId} (${formatGlobalRole(String(user.global_admin_role))})`)
      }
    }
    const orgIds = user.organisation_ids || []
    if (orgIds.length > 0 && (user.department_root_ids?.length ?? 0) === 0) {
      for (const orgId of orgIds) {
        links.push(`${orgNameById.value.get(orgId) || orgId} (${formatGlobalRole(String(user.global_admin_role))})`)
      }
    }
  }
  return links
}

const kanbanColumns = computed((): KanbanColumn[] => {
  const byDept = buildAssignmentsByDept()
  const cols: KanbanColumn[] = []

  for (const dept of departments.value) {
    const assignments = byDept.get(dept.id) || []
    if (onlyWithAssignments.value && assignments.length === 0) continue

    const entries: KanbanEntry[] = assignments.map((a) => ({
      user: a.user,
      kind: a.kind,
      role: a.role,
      isPrimary: a.isPrimary,
      otherLinks: collectOtherLinks(a.user, dept.id),
    }))

    cols.push({
      id: dept.id,
      name: dept.name,
      orgName: orgNameById.value.get(dept.organisation_id) || '',
      groups: groupAssignments(entries),
      entries,
    })
  }

  return cols.sort((a, b) => a.orgName.localeCompare(b.orgName, 'de') || a.name.localeCompare(b.name, 'de'))
})

function computeSubtreeRoots(deptIds: string[]): string[] {
  const idSet = new Set(deptIds)
  const roots: string[] = []
  for (const id of deptIds) {
    const dept = departments.value.find((d) => d.id === id)
    if (!dept) continue
    let pid: string | null | undefined = dept.parent_id
    let underAccessibleParent = false
    while (pid) {
      if (idSet.has(pid)) {
        underAccessibleParent = true
        break
      }
      const parent = departments.value.find((d) => d.id === pid)
      pid = parent?.parent_id
    }
    if (!underAccessibleParent) roots.push(id)
  }
  return roots
}

function computeMyBranchRootIds(): string[] {
  const scopeRoots = authStore.adminCapabilities?.scope?.department_root_ids || []
  if (scopeRoots.length > 0) {
    return scopeRoots.filter((id) => departments.value.some((d) => d.id === id))
  }

  if (authStore.userRoles.includes('ROLE_SUPERADMIN')) {
    const primary = authStore.departments.find((d) => d.is_primary)
    if (primary) return [primary.department_id]
    if (authStore.activeDepartmentId) return [authStore.activeDepartmentId]
    return []
  }

  if (authStore.hasGlobalAdminAccess()) {
    const accessible = authStore.accessibleDepartmentIds
    if (accessible && accessible.length > 0) {
      return computeSubtreeRoots(accessible)
    }
    return []
  }

  const primary = authStore.departments.find((d) => d.is_primary)
  if (primary) return [primary.department_id]
  if (authStore.activeDepartmentId) return [authStore.activeDepartmentId]
  return []
}

function orgContainsBranchRoot(orgId: string): boolean {
  if (branchRootIds.value.length === 0) return false
  return branchRootIds.value.some((rootId) => {
    const dept = departments.value.find((d) => d.id === rootId)
    return dept?.organisation_id === orgId
  })
}

function countOrgAssignments(org: OrgTree): number {
  let n = buildOrgGlobalAssignments(org.id).length
  const walk = (nodes: DeptTreeNode[]) => {
    for (const node of nodes) {
      n += node.assignments.length
      walk(node.children)
    }
  }
  walk(org.children)
  return n
}

function applyDefaultExpansion() {
  const roots = branchRootIds.value
  const orgIds = new Set<string>()

  for (const rootId of roots) {
    const dept = departments.value.find((d) => d.id === rootId)
    if (dept) orgIds.add(dept.organisation_id)
  }

  if (roots.length === 0) {
    for (const org of orgTrees.value) {
      if (countOrgAssignments(org) > 0) orgIds.add(org.id)
    }
  }

  expandedOrgIds.value = [...orgIds]
}

function toggleOrg(id: string) {
  if (expandedOrgIds.value.includes(id)) {
    expandedOrgIds.value = expandedOrgIds.value.filter((x) => x !== id)
  } else {
    expandedOrgIds.value = [...expandedOrgIds.value, id]
  }
}

function expandMyBranch() {
  applyDefaultExpansion()
}

function collapseAll() {
  expandedOrgIds.value = []
}

function expandAll() {
  expandedOrgIds.value = orgTrees.value.map((o) => o.id)
}

const scopeLabels = computed(() => ({
  all: t('settings.userOrgOverview.scopeAllShort'),
  orgs: (names: string[]) => t('settings.userOrgOverview.scopeOrgsShort', { names: names.join(', ') }),
  roots: (names: string[]) => t('settings.userOrgOverview.scopeRootsShort', { names: names.join(', ') }),
  memberOnly: '',
}))

function orgGlobalScopeLabel(group: UserRoleGroup): string {
  return scopeLabelForUser(group.user, deptNameById.value, scopeLabels.value, orgNameById.value)
}

function kanbanScopeLabel(group: UserRoleGroup): string {
  return scopeLabelForUser(group.user, deptNameById.value, scopeLabels.value, orgNameById.value)
}

function kanbanOtherLinks(group: UserRoleGroup, colDeptId: string): string[] {
  return collectOtherLinks(group.user, colDeptId)
}

function openUserEdit(userId: string, kind: OverviewKind) {
  if (kind === 'global_scope') {
    void router.push({
      path: '/admin-dashboard/verwaltung/global-admin-roles',
      query: { edit: userId },
    })
    return
  }
  void router.push({
    path: '/admin-dashboard/verwaltung/users',
    query: { edit: userId },
  })
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const [orgs, depts, overviewUsers] = await Promise.all([
      getOrganisationsForAdmin(),
      getDepartments(),
      getAdminOrgOverview(),
    ])
    organisations.value = filterOrganisationsForAdminScope(orgs)
    const accessible = authStore.accessibleDepartmentIds
    const scopedDepts =
      accessible === null ? depts : filterDepartmentsByAccessibleIds(depts, accessible)
    departments.value = filterDepartmentsForAdminScope(scopedDepts)
    users.value = overviewUsers
    applyDefaultExpansion()
  } catch {
    error.value = t('settings.userOrgOverview.loadError')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.user-org-overview {
  padding: 0;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.settings-title {
  margin: 0 0 0.25rem;
  font-size: 1.5rem;
}

.settings-description {
  margin: 0;
  color: var(--color-text-muted, #64748b);
  font-size: 0.9rem;
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.view-toggle {
  display: flex;
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 8px;
  overflow: hidden;
}

.toggle-btn {
  padding: 0.4rem 0.85rem;
  border: none;
  background: #fff;
  cursor: pointer;
  font-size: 0.875rem;
}

.toggle-btn.active {
  background: var(--color-primary, #2563eb);
  color: #fff;
}

.search-wrapper {
  position: relative;
  flex: 1;
  min-width: 200px;
  max-width: 320px;
}

.search-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}

.search-input {
  width: 100%;
  padding: 0.45rem 0.75rem 0.45rem 2rem;
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 8px;
  font-size: 0.875rem;
}

.filter-checkbox {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.875rem;
  color: var(--color-text-muted, #64748b);
}

.state-card {
  padding: 2rem;
  text-align: center;
  background: #f8fafc;
  border-radius: 8px;
}

.state-error {
  color: #b91c1c;
}

.overview-tree {
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 8px;
  background: #fff;
  padding: 1rem;
  max-height: 70vh;
  overflow: auto;
}

.tree-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.btn-sm {
  padding: 0.35rem 0.65rem;
  font-size: 0.8rem;
}

.org-accordion {
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 8px;
  margin-bottom: 0.5rem;
  overflow: hidden;
  background: #fff;
}

.org-accordion.is-my-branch {
  border-color: #93c5fd;
  box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.08);
}

.org-accordion-header {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 0.85rem;
  border: none;
  background: #f8fafc;
  cursor: pointer;
  text-align: left;
  font: inherit;
}

.org-accordion.expanded .org-accordion-header {
  border-bottom: 1px solid var(--color-border, #e2e8f0);
}

.org-accordion.is-my-branch .org-accordion-header {
  background: #eff6ff;
}

.accordion-chevron {
  width: 1rem;
  font-size: 0.65rem;
  color: #64748b;
  flex-shrink: 0;
}

.org-accordion-title {
  font-weight: 700;
  font-size: 0.95rem;
  flex: 1;
}

.org-branch-badge {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
  background: #dbeafe;
  color: #1d4ed8;
}

.org-assignment-count {
  font-size: 0.75rem;
  color: #64748b;
  background: #fff;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
}

.org-accordion-panel {
  padding: 0.5rem 0.75rem 0.75rem;
}

.org-wide-frame {
  margin-bottom: 0.5rem;
  border-radius: 10px;
  overflow: hidden;
}

.org-wide-frame.scope-frame-org {
  border: 2px solid #f59e0b;
  box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.12);
}

.org-wide-frame.scope-frame-sub {
  border: 2px solid #8b5cf6;
  box-shadow: 0 0 0 1px rgba(139, 92, 246, 0.1);
}

.org-wide-frame .scope-frame-banner {
  padding: 0.45rem 0.75rem;
  font-size: 0.8rem;
  font-weight: 600;
}

.org-wide-frame.scope-frame-org .scope-frame-banner {
  background: linear-gradient(90deg, #fef3c7, #fffbeb);
  color: #92400e;
}

.org-wide-frame.scope-frame-sub .scope-frame-banner {
  background: linear-gradient(90deg, #ede9fe, #f5f3ff);
  color: #5b21b6;
}

.org-wide-frame-body {
  padding: 0.5rem 0.65rem 0.65rem;
  background: #fff;
}

.org-global-strip {
  margin-bottom: 0.65rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.org-global-strip .section-label {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #64748b;
  margin-bottom: 0.35rem;
}

.org-global-cards {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.kanban-board {
  display: flex;
  gap: 1rem;
  overflow-x: auto;
  padding-bottom: 0.5rem;
  max-height: 70vh;
  align-items: flex-start;
}

.kanban-column {
  flex: 0 0 260px;
  background: #f1f5f9;
  border-radius: 8px;
  max-height: 68vh;
  display: flex;
  flex-direction: column;
}

.kanban-column-header {
  padding: 0.75rem;
  border-bottom: 1px solid #e2e8f0;
}

.column-title {
  display: block;
  font-weight: 600;
  font-size: 0.9rem;
}

.column-org {
  display: block;
  font-size: 0.75rem;
  color: #64748b;
}

.column-count {
  display: inline-block;
  margin-top: 0.25rem;
  font-size: 0.75rem;
  background: #fff;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
}

.kanban-column-body {
  padding: 0.5rem;
  overflow-y: auto;
  flex: 1;
}

.kanban-card-wrap {
  margin-bottom: 0.5rem;
}

.kanban-card-wrap :deep(.user-role-group) {
  width: 100%;
}

.card-other {
  margin-top: 0.35rem;
  font-size: 0.7rem;
  color: #64748b;
  line-height: 1.3;
}

.kanban-empty {
  font-size: 0.75rem;
  color: #94a3b8;
  text-align: center;
  padding: 0.5rem;
}

.badge {
  font-size: 0.7rem;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  font-weight: 600;
}

.badge-membership {
  background: #e0f2fe;
  color: #0369a1;
}

.badge-global-sub {
  background: #ede9fe;
  color: #5b21b6;
}

.badge-global-org {
  background: #fef3c7;
  color: #92400e;
}

.badge-primary {
  background: #ffedd5;
  color: #c2410c;
}

.legend {
  margin-top: 1rem;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  font-size: 0.8rem;
  color: #64748b;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.legend-frame {
  display: inline-block;
  width: 1.25rem;
  height: 0.75rem;
  border-radius: 3px;
}

.legend-frame-org {
  border: 2px solid #f59e0b;
  background: #fffbeb;
}

.legend-frame-sub {
  border: 2px solid #8b5cf6;
  background: #f5f3ff;
}

.legend-hint {
  flex-basis: 100%;
}
</style>
