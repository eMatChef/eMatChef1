<template>
  <div class="dept-node-body">
    <div class="dept-row-header">
      <span class="dept-name">{{ node.name }}</span>
    </div>

    <div v-if="globalGroups.length > 0" class="scope-global-users">
      <div class="section-label">{{ t('settings.userOrgOverview.globalRolesSection') }}</div>
      <div class="user-cards">
        <UserRoleGroupCard
          v-for="group in globalGroups"
          :key="`g-${node.id}-${group.user.id}`"
          :group="group"
          :scope-label="hideScopeOnCards ? '' : scopeLabelForGroup(group)"
          plain
          :format-dept-role="formatDeptRole"
          :format-global-role="formatGlobalRole"
          @edit-user="(userId, kind) => $emit('edit-user', userId, kind)"
        />
      </div>
    </div>

    <div v-if="membershipGroups.length > 0" class="dept-roles-section">
      <button type="button" class="dept-roles-toggle" @click="deptRolesOpen = !deptRolesOpen">
        <span class="toggle-chevron">{{ deptRolesOpen ? '▼' : '▶' }}</span>
        {{ t('settings.userOrgOverview.deptRolesToggle', { n: membershipGroups.length }) }}
      </button>
      <div v-show="deptRolesOpen" class="dept-roles-panel">
        <UserRoleGroupCard
          v-for="group in membershipGroups"
          :key="`m-${node.id}-${group.user.id}`"
          :group="group"
          scope-label=""
          plain
          :format-dept-role="formatDeptRole"
          :format-global-role="formatGlobalRole"
          @edit-user="(userId, kind) => $emit('edit-user', userId, kind)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import UserRoleGroupCard from '@/components/admin/UserRoleGroupCard.vue'
import { scopeLabelForUser, type OverviewKind, type UserRoleGroup } from '@/utils/userRoleDisplay'
import type { DeptTreeNode } from '@/components/admin/DeptOverviewNode.vue'

const props = defineProps<{
  node: DeptTreeNode
  globalGroups: UserRoleGroup[]
  membershipGroups: UserRoleGroup[]
  deptNameById: Map<string, string>
  orgNameById?: Map<string, string>
  hideScopeOnCards: boolean
  formatDeptRole: (role: string) => string
  formatGlobalRole: (role: string) => string
}>()

defineEmits<{
  'edit-user': [userId: string, kind: OverviewKind]
}>()

const { t } = useI18n()
const deptRolesOpen = ref(false)

const scopeLabels = computed(() => ({
  all: t('settings.userOrgOverview.scopeAllShort'),
  orgs: (names: string[]) => t('settings.userOrgOverview.scopeOrgsShort', { names: names.join(', ') }),
  roots: (names: string[]) => t('settings.userOrgOverview.scopeRootsShort', { names: names.join(', ') }),
  memberOnly: '',
}))

function scopeLabelForGroup(group: UserRoleGroup): string {
  return scopeLabelForUser(
    group.user,
    props.deptNameById,
    scopeLabels.value,
    props.orgNameById || new Map()
  )
}
</script>

<style scoped>
.dept-row-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0;
}

.dept-name {
  font-weight: 600;
  font-size: 0.9rem;
}

.section-label {
  font-size: 0.68rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #64748b;
  margin: 0.35rem 0 0.25rem;
}

.scope-global-users {
  margin-bottom: 0.35rem;
}

.user-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.dept-roles-section {
  margin: 0.35rem 0 0.15rem;
}

.dept-roles-toggle {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  width: 100%;
  padding: 0.4rem 0.55rem;
  border: 1px dashed #cbd5e1;
  border-radius: 6px;
  background: #f8fafc;
  cursor: pointer;
  font: inherit;
  font-size: 0.8rem;
  font-weight: 500;
  color: #475569;
  text-align: left;
}

.dept-roles-toggle:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
}

.toggle-chevron {
  font-size: 0.65rem;
  color: #64748b;
}

.dept-roles-panel {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.4rem;
  padding: 0.35rem 0 0.35rem 0.5rem;
  border-left: 2px solid #e2e8f0;
}
</style>
