<template>
  <div class="activity-detail-user-line">
    <label class="activity-detail-user-line__label">{{ label }}</label>
    <div v-if="user" class="activity-detail-user-line__value">
      <UserAvatarBadge :user="avatarUser" size="sm" :show-tooltip="false" />
      <span class="activity-detail-user-line__name">{{ displayName }}</span>
      <span v-if="whenLabel" class="activity-detail-user-line__when text-muted">{{ whenLabel }}</span>
    </div>
    <p v-else class="activity-readonly-value text-muted">{{ emptyLabel }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { UserAvatarBadge } from '@/components/user'
import type { ActivityUserSummary } from '@/api/activities'
import { formatUserNicknameFirstNameLastName } from '@/utils/userAvatar'

const props = defineProps<{
  label: string
  user: ActivityUserSummary | null | undefined
  at?: string | null
  emptyLabel: string
  formatWhen: (iso: string) => string
}>()

const avatarUser = computed(() => ({
  name: props.user?.display_name ?? undefined,
  first_name: props.user?.first_name ?? undefined,
  last_name: props.user?.last_name ?? undefined,
  nickname: props.user?.nickname ?? undefined,
  avatar_initials: props.user?.avatar_initials ?? undefined,
  background_color: props.user?.background_color ?? undefined,
  text_color: props.user?.text_color ?? undefined,
}))

const displayName = computed(() => {
  const formatted = formatUserNicknameFirstNameLastName(avatarUser.value).trim()
  if (formatted) return formatted
  return props.user?.display_name?.trim() || '–'
})

const whenLabel = computed(() => {
  const iso = props.at?.trim()
  if (!iso) return null
  return props.formatWhen(iso)
})
</script>

<style scoped>
.activity-detail-user-line__label {
  display: block;
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
}

.activity-detail-user-line__value {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-height: 32px;
}

.activity-detail-user-line__name {
  font-weight: 600;
  color: #0f172a;
}

.activity-detail-user-line__when::before {
  content: '·';
  margin-right: 8px;
  color: #94a3b8;
}
</style>
