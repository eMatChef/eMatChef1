<template>
  <span class="public-user-chip">
    <span class="public-user-avatar" :style="avatarStyle">
      {{ safeInitials }}
    </span>
    <span class="public-user-name">{{ safeName }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  displayName?: string | null
  initials?: string | null
  backgroundColor?: string | null
  textColor?: string | null
}>()

const safeName = computed(() => {
  const value = String(props.displayName || '').trim()
  return value || 'User'
})

const safeInitials = computed(() => {
  const value = String(props.initials || '').trim()
  return value || '??'
})

const avatarStyle = computed(() => ({
  backgroundColor: props.backgroundColor || '#ec4899',
  color: props.textColor || '#ffffff',
}))
</script>

<style scoped>
.public-user-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  max-width: min(220px, 42vw);
}

.public-user-avatar {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.public-user-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

@media (max-width: 380px) {
  .public-user-name {
    display: none;
  }
}
</style>
