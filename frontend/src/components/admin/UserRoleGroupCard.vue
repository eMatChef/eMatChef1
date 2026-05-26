<template>
  <div class="user-role-group" :class="plain ? 'plain' : `frame-${group.frameLevel}`">
    <div class="group-top">
      <button
        type="button"
        class="group-name"
        :title="nameEditTitle"
        @click="$emit('edit-user', group.user.id, preferredKind)"
      >
        {{ group.user.name }}
      </button>
      <span v-if="group.roles.some((r) => r.isPrimary)" class="chip-primary">
        {{ t('settings.userOrgOverview.primary') }}
      </span>
    </div>

    <div v-if="scopeLabel" class="group-scope">{{ scopeLabel }}</div>

    <div class="role-badges">
      <button
        v-for="(r, idx) in group.roles"
        :key="`${group.user.id}-${r.kind}-${r.role}-${idx}`"
        type="button"
        class="role-badge-btn"
        :class="badgeClassForRole(r.kind, r.role)"
        :title="roleEditTitle(r.kind)"
        @click="$emit('edit-user', group.user.id, r.kind)"
      >
        {{ roleLabel(r) }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  badgeClassForRole,
  preferredEditKind,
  type OverviewKind,
  type RoleBadge,
  type UserRoleGroup,
} from '@/utils/userRoleDisplay'

const props = withDefaults(
  defineProps<{
    group: UserRoleGroup
    scopeLabel: string
    formatDeptRole: (role: string) => string
    formatGlobalRole: (role: string) => string
    plain?: boolean
  }>(),
  { plain: false }
)

defineEmits<{
  'edit-user': [userId: string, kind: OverviewKind]
}>()

const { t } = useI18n()

const preferredKind = computed(() => preferredEditKind(props.group))

const nameEditTitle = computed(() =>
  preferredKind.value === 'global_scope'
    ? t('settings.userOrgOverview.editGlobalRole')
    : t('settings.userOrgOverview.editMembership')
)

function roleLabel(r: RoleBadge): string {
  return r.kind === 'global_scope' ? props.formatGlobalRole(r.role) : props.formatDeptRole(r.role)
}

function roleEditTitle(kind: OverviewKind): string {
  return kind === 'global_scope'
    ? t('settings.userOrgOverview.editGlobalRole')
    : t('settings.userOrgOverview.editMembership')
}
</script>

<style scoped>
.user-role-group {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  padding: 0.45rem 0.55rem;
  border-radius: 8px;
  background: #fff;
  min-width: 10rem;
  max-width: 100%;
}

.user-role-group.frame-org {
  border: 2px solid #f59e0b;
  box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.15);
  background: linear-gradient(to bottom, #fffbeb, #fff);
}

.user-role-group.frame-sub {
  border: 2px solid #8b5cf6;
  box-shadow: 0 0 0 1px rgba(139, 92, 246, 0.12);
  background: linear-gradient(to bottom, #f5f3ff, #fff);
}

.user-role-group.frame-dept {
  border: 1px solid #7dd3fc;
  background: #f8fafc;
}

.user-role-group.plain {
  border: 1px solid #e2e8f0;
  background: #fff;
  box-shadow: none;
  min-width: 8rem;
}

.group-top {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.group-name {
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  color: #0f172a;
  text-align: left;
}

.group-name:hover {
  color: #2563eb;
  text-decoration: underline;
}

.group-name:focus-visible {
  outline: 2px solid #2563eb;
  outline-offset: 2px;
  border-radius: 2px;
}

.group-scope {
  font-size: 0.68rem;
  line-height: 1.35;
  color: #64748b;
}

.role-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.role-badge-btn {
  border: none;
  font-size: 0.7rem;
  padding: 0.12rem 0.4rem;
  border-radius: 4px;
  font-weight: 600;
  cursor: pointer;
  transition: filter 0.15s, transform 0.1s;
}

.role-badge-btn:hover {
  filter: brightness(0.95);
  transform: translateY(-1px);
}

.role-badge-btn.badge-membership {
  background: #e0f2fe;
  color: #0369a1;
}

.role-badge-btn.badge-global-sub {
  background: #ede9fe;
  color: #5b21b6;
}

.role-badge-btn.badge-global-org {
  background: #fef3c7;
  color: #92400e;
}

.chip-primary {
  font-size: 0.65rem;
  color: #b45309;
  font-weight: 600;
}
</style>
