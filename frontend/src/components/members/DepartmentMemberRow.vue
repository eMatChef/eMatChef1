<template>
  <div class="department-member-row">
    <UserAvatarBadge
      :user="avatarUser"
      :size="avatarSize"
      :show-leader-star="showLeaderStar"
      :show-primary-home="showPrimaryHome"
      :dept-stage-role="deptStageRole"
      :show-tooltip="showTooltip"
    />
    <div class="department-member-row__meta">
      <strong class="department-member-row__name">{{ name }}</strong>
      <span v-if="subtitle" class="department-member-row__subtitle">{{ subtitle }}</span>
    </div>
    <DepartmentMemberActions
      v-if="showActions"
      class="department-member-row__actions"
      :can-manage="canManage"
      :tour-hover="tourHover"
      :onboarding="onboarding"
      @details="$emit('details')"
      @remove="$emit('remove')"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'
import type { UserAvatarFields } from '@/utils/userAvatar'
import DepartmentMemberActions from './DepartmentMemberActions.vue'

const props = withDefaults(
  defineProps<{
    name: string
    subtitle?: string
    avatar: UserAvatarFields
    avatarSize?: 'sm' | 'md' | 'lg'
    showLeaderStar?: boolean
    showPrimaryHome?: boolean
    deptStageRole?: string | null
    showTooltip?: boolean
    showActions?: boolean
    canManage?: boolean
    tourHover?: boolean
    onboarding?: string | null
  }>(),
  {
    subtitle: '',
    avatarSize: 'md',
    showLeaderStar: false,
    showPrimaryHome: false,
    deptStageRole: null,
    showTooltip: true,
    showActions: true,
    canManage: false,
    tourHover: false,
    onboarding: null,
  },
)

defineEmits<{
  details: []
  remove: []
}>()

const avatarUser = computed(() => props.avatar)
</script>

<style scoped>
.department-member-row {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 4px 8px;
  margin: 0 -8px;
  border-radius: 8px;
}

.department-member-row:hover {
  background: #f8fafc;
}

.department-member-row__meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.department-member-row__name {
  font-size: 14px;
  color: #1e293b;
}

.department-member-row__subtitle {
  color: #64748b;
  font-size: 0.8rem;
}

.department-member-row__actions {
  flex-shrink: 0;
  margin-left: auto;
}
</style>
