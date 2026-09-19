<template>
  <div class="dept-node" :class="{ 'is-branch-root': isBranchRoot && !insideAdminScope }">
    <div
      v-if="isOrgScopeZoneRoot"
      class="admin-scope-frame org-admin-scope-frame"
      :class="`scope-frame-${orgScopeFrameLevel}`"
    >
      <div class="scope-frame-banner org-scope-banner">
        <span class="org-scope-banner-text">
          {{ t('settings.userOrgOverview.scopeFrameOrgWide', { name: node.name }) }}
        </span>
        <div v-if="orgWideAdminGroups.length > 0" class="scope-banner-avatars">
          <button
            v-for="group in orgWideAdminGroups"
            :key="`org-scope-avatar-${group.user.id}`"
            type="button"
            class="scope-banner-avatar-btn"
            :title="group.user.name"
            @click="emit('edit-user', group.user.id, 'global_scope')"
          >
            <UserAvatarBadge :user="toOverviewAvatarFields(group.user)" size="sm" />
          </button>
        </div>
      </div>
      <div class="scope-frame-body">
        <DeptNodeBody
          :node="node"
          :global-groups="[]"
          :membership-groups="membershipGroups"
          :dept-name-by-id="deptNameById"
          :org-name-by-id="orgNameById"
          :format-dept-role="formatDeptRole"
          :format-global-role="formatGlobalRole"
          :hide-scope-on-cards="true"
          @edit-user="(userId, kind) => emit('edit-user', userId, kind)"
        />
        <div v-if="node.children.length > 0" class="dept-children">
          <DeptOverviewNode
            v-for="child in node.children"
            :key="child.id"
            :node="child"
            :scope-root-ids="scopeRootIds"
            :org-admin-scope-root-ids="orgAdminScopeRootIds"
            :org-wide-admin-groups="orgWideAdminGroups"
            :inside-admin-scope="true"
            :branch-root-ids="branchRootIds"
            :dept-name-by-id="deptNameById"
            :org-name-by-id="orgNameById"
            :format-dept-role="formatDeptRole"
            :format-global-role="formatGlobalRole"
            @edit-user="(userId, kind) => emit('edit-user', userId, kind)"
          />
        </div>
      </div>
    </div>

    <div
      v-else-if="isScopeZoneRoot"
      class="admin-scope-frame"
      :class="`scope-frame-${scopeFrameLevel}`"
    >
      <div class="scope-frame-banner org-scope-banner">
        <span class="org-scope-banner-text">{{ scopeFrameTitle }}</span>
        <div v-if="globalGroups.length > 0" class="scope-banner-avatars">
          <button
            v-for="group in globalGroups"
            :key="`dept-scope-avatar-${group.user.id}`"
            type="button"
            class="scope-banner-avatar-btn"
            :title="group.user.name"
            @click="emit('edit-user', group.user.id, 'global_scope')"
          >
            <UserAvatarBadge :user="toOverviewAvatarFields(group.user)" size="sm" />
          </button>
        </div>
      </div>
      <div class="scope-frame-body">
        <DeptNodeBody
          :node="node"
          :global-groups="globalGroups"
          :membership-groups="membershipGroups"
          :dept-name-by-id="deptNameById"
          :org-name-by-id="orgNameById"
          :format-dept-role="formatDeptRole"
          :format-global-role="formatGlobalRole"
          :hide-scope-on-cards="true"
          @edit-user="(userId, kind) => emit('edit-user', userId, kind)"
        />
        <div v-if="node.children.length > 0" class="dept-children">
          <DeptOverviewNode
            v-for="child in node.children"
            :key="child.id"
            :node="child"
            :scope-root-ids="scopeRootIds"
            :org-admin-scope-root-ids="orgAdminScopeRootIds"
            :org-wide-admin-groups="orgWideAdminGroups"
            :inside-admin-scope="true"
            :branch-root-ids="branchRootIds"
            :dept-name-by-id="deptNameById"
            :org-name-by-id="orgNameById"
            :format-dept-role="formatDeptRole"
            :format-global-role="formatGlobalRole"
            @edit-user="(userId, kind) => emit('edit-user', userId, kind)"
          />
        </div>
      </div>
    </div>

    <template v-else>
      <DeptNodeBody
        :node="node"
        :global-groups="globalGroups"
        :membership-groups="membershipGroups"
        :dept-name-by-id="deptNameById"
        :org-name-by-id="orgNameById"
        :format-dept-role="formatDeptRole"
        :format-global-role="formatGlobalRole"
        :hide-scope-on-cards="insideAdminScope"
        @edit-user="(userId, kind) => emit('edit-user', userId, kind)"
      />
      <div v-if="node.children.length > 0" class="dept-children" :class="{ 'in-scope': insideAdminScope }">
        <DeptOverviewNode
          v-for="child in node.children"
          :key="child.id"
          :node="child"
          :scope-root-ids="scopeRootIds"
          :org-admin-scope-root-ids="orgAdminScopeRootIds"
          :org-wide-admin-groups="orgWideAdminGroups"
          :inside-admin-scope="insideAdminScope"
          :branch-root-ids="branchRootIds"
          :dept-name-by-id="deptNameById"
          :org-name-by-id="orgNameById"
          :format-dept-role="formatDeptRole"
          :format-global-role="formatGlobalRole"
          @edit-user="(userId, kind) => emit('edit-user', userId, kind)"
        />
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DeptNodeBody from '@/components/admin/DeptNodeBody.vue'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'
import { groupAssignments, type OverviewKind, type UserRoleGroup } from '@/utils/userRoleDisplay'
import { orgWideFrameLevel, toOverviewAvatarFields } from '@/utils/userOrgScope'
import type { AdminOrgOverviewUser } from '@/api/adminUsers'

export type { OverviewKind } from '@/utils/userRoleDisplay'

export interface DeptAssignment {
  user: AdminOrgOverviewUser
  kind: OverviewKind
  role: string
  isPrimary: boolean
}

export interface DeptTreeNode {
  id: string
  name: string
  organisationId: string
  children: DeptTreeNode[]
  assignments: DeptAssignment[]
}

const props = defineProps<{
  node: DeptTreeNode
  scopeRootIds: Set<string>
  orgAdminScopeRootIds: Set<string>
  orgWideAdminGroups: UserRoleGroup[]
  insideAdminScope?: boolean
  branchRootIds: Set<string>
  deptNameById: Map<string, string>
  orgNameById: Map<string, string>
  formatDeptRole: (role: string) => string
  formatGlobalRole: (role: string) => string
}>()

const emit = defineEmits<{
  'edit-user': [userId: string, kind: OverviewKind]
}>()

const { t } = useI18n()

const insideAdminScope = computed(() => props.insideAdminScope === true)
const isScopeZoneRoot = computed(() => props.scopeRootIds.has(props.node.id))
const isOrgScopeZoneRoot = computed(
  () => !isScopeZoneRoot.value && props.orgAdminScopeRootIds.has(props.node.id),
)
const isBranchRoot = computed(() => props.branchRootIds.has(props.node.id))
const orgScopeFrameLevel = computed(() => orgWideFrameLevel(props.orgWideAdminGroups.map((g) => g.user)))

const globalAssignments = computed(() =>
  props.node.assignments.filter((a) => a.kind === 'global_scope')
)
const membershipAssignments = computed(() =>
  props.node.assignments.filter((a) => a.kind === 'membership')
)

const globalGroups = computed(() => groupAssignments(globalAssignments.value))
const membershipGroups = computed(() => groupAssignments(membershipAssignments.value))

const scopeFrameLevel = computed((): 'org' | 'sub' => {
  if (globalAssignments.value.some((a) => a.role === 'org')) return 'org'
  if (globalAssignments.value.some((a) => a.role === 'sub')) return 'sub'
  for (const u of collectScopeUsersAtRoot()) {
    if (u.global_admin_role === 'org') return 'org'
  }
  return 'sub'
})

function collectScopeUsersAtRoot(): AdminOrgOverviewUser[] {
  const seen = new Set<string>()
  const users: AdminOrgOverviewUser[] = []
  for (const a of props.node.assignments) {
    if (seen.has(a.user.id)) continue
    const roots = a.user.department_root_ids || []
    if (roots.length > 0 && roots.includes(props.node.id)) {
      users.push(a.user)
      seen.add(a.user.id)
    }
  }
  return users
}

const scopeFrameTitle = computed(() => {
  const hasAllScope = collectScopeUsersAtRoot().some((u) => {
    if (u.global_admin_role !== 'org' && u.global_admin_role !== 'sub') return false
    return (u.department_root_ids?.length ?? 0) === 0 && (u.organisation_ids?.length ?? 0) === 0
  })
  if (hasAllScope) {
    return t('settings.userOrgOverview.scopeFrameAll', { name: props.node.name })
  }
  return t('settings.userOrgOverview.scopeFrameSubtree', { name: props.node.name })
})
</script>

<script lang="ts">
import { defineComponent } from 'vue'
export default defineComponent({ name: 'DeptOverviewNode' })
</script>

<style scoped>
.dept-node {
  margin-left: 0;
}

.dept-children {
  margin-left: 1.25rem;
  border-left: 2px solid #e2e8f0;
  padding-left: 0.75rem;
  margin-top: 0.35rem;
}

.dept-children.in-scope {
  border-left-color: #c4b5fd;
}

.admin-scope-frame {
  margin: 0.5rem 0 0.75rem;
  border-radius: 10px;
  overflow: hidden;
}

.admin-scope-frame.scope-frame-org {
  border: 2px solid #f59e0b;
  box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.12);
}

.admin-scope-frame.scope-frame-sub {
  border: 2px solid #8b5cf6;
  box-shadow: 0 0 0 1px rgba(139, 92, 246, 0.1);
}

.scope-frame-banner {
  padding: 0.45rem 0.75rem;
  font-size: 0.78rem;
  font-weight: 600;
}

.scope-frame-org .scope-frame-banner {
  background: linear-gradient(to right, #fffbeb, #fef3c7);
  color: #92400e;
}

.scope-frame-sub .scope-frame-banner {
  background: linear-gradient(to right, #f5f3ff, #ede9fe);
  color: #5b21b6;
}

.scope-frame-body {
  padding: 0.35rem 0.65rem 0.65rem;
  background: #fff;
}

.org-scope-banner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.org-scope-banner-text {
  flex: 1;
  min-width: 0;
}

.scope-banner-avatars {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.scope-banner-avatar-btn {
  border: none;
  background: transparent;
  padding: 0;
  cursor: pointer;
  border-radius: 6px;
  line-height: 0;
}

.scope-banner-avatar-btn:hover {
  outline: 2px solid rgba(37, 99, 235, 0.35);
  outline-offset: 1px;
}
</style>
