<template>
  <div class="step-section" :data-step="stepId">
    <div class="step-header step-header--clickable" @click="$emit('toggle')">
      <span class="step-title">{{ title }}</span>
      <span v-if="badge" class="step-badge" :class="badgeClass">{{ badge }}</span>
      <span class="step-chevron" :class="{ open: isOpen }">▾</span>
    </div>
    <div v-show="isOpen" class="step-content">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  stepId: string
  title: string
  badge?: string
  badgeClass?: string
  isOpen: boolean
}>()
defineEmits<{
  toggle: []
}>()
</script>
