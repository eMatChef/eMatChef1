<script setup lang="ts">
defineProps<{
  title: string
}>()
</script>

<template>
  <div class="section-card activity-tab-header-card">
    <div class="activity-tab-header-row">
      <h2 class="section-title">{{ title }}</h2>
      <div v-if="$slots.actions" class="activity-tab-header-actions">
        <slot name="actions" />
      </div>
    </div>
    <div v-if="$slots.default" class="activity-tab-header-body">
      <slot />
    </div>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/detail-workflow.css';
</style>
