<template>
  <ActivityOutlinedSection :title="title" :icon="icon" :id="id" :required="required">
    <slot />
  </ActivityOutlinedSection>
</template>

<script setup lang="ts">
import ActivityOutlinedSection from './ActivityOutlinedSection.vue'

defineProps<{
  title: string
  icon?: 'calendar' | 'truck' | null
  id?: string
  /** Pflichtfeld: Stern in der Legend */
  required?: boolean
}>()
</script>
