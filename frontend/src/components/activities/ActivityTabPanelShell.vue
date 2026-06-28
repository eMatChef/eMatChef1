<script setup lang="ts">
import ELoadingState from '@/components/layout/ELoadingState.vue'

defineProps<{
  loading?: boolean
  refreshing?: boolean
  loadingMessage?: string
  loadingClass?: string
}>()
</script>

<template>
  <div
    class="section-card activity-tab-panel-card"
    :class="{ 'is-soft-loading': refreshing }"
  >
    <div v-if="refreshing" class="soft-refresh-bar" aria-hidden="true" />
    <ELoadingState
      v-if="loading"
      variant="inline"
      :class="loadingClass"
      :message="loadingMessage"
    />
    <slot v-else />
  </div>
</template>
