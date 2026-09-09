<template>
  <div
    class="department-member-actions"
    :class="{ 'department-member-actions--tour-hover': tourHover }"
    :data-onboarding="onboarding || undefined"
  >
    <EButton
      v-if="canManage"
      variant="secondary"
      size="small"
      class="member-details-btn"
      :title="t('settings.departmentUsers.titleEditMember')"
      @click="$emit('details')"
    >
      {{ t('settings.departmentUsers.memberDetails') }}
    </EButton>
    <button
      v-if="canManage"
      type="button"
      class="action-btn action-btn-danger"
      :title="t('settings.departmentUsers.titleRemoveFromDept')"
      @click="$emit('remove')"
    >
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
        <circle cx="8.5" cy="7" r="4" />
        <line x1="23" y1="11" x2="17" y2="11" />
      </svg>
    </button>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'

withDefaults(
  defineProps<{
    canManage: boolean
    tourHover?: boolean
    onboarding?: string | null
  }>(),
  { tourHover: false, onboarding: null },
)

defineEmits<{
  details: []
  remove: []
}>()

const { t } = useI18n()
</script>

<style scoped>
.department-member-actions {
  display: flex;
  gap: 6px;
  align-items: center;
  justify-content: flex-end;
  min-width: 140px;
  box-sizing: border-box;
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
</style>
