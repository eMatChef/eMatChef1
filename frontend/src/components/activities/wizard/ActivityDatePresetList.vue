<template>
  <VList
    v-if="presets.length"
    class="activity-date-preset-list"
    density="compact"
    nav
    role="group"
    :aria-label="ariaLabel"
  >
    <VListItem
      v-for="(preset, idx) in presets"
      :key="idx"
      :title="preset.label"
      rounded="lg"
      @click="emit('select', preset)"
    />
  </VList>
</template>

<script setup lang="ts">
import { VList, VListItem } from 'vuetify/components'
import type { ActivityDatePresetItem } from '@/utils/activityDatePresets'

withDefaults(
  defineProps<{
    presets: ActivityDatePresetItem[]
    ariaLabel?: string
  }>(),
  { presets: () => [], ariaLabel: '' },
)

const emit = defineEmits<{
  select: [preset: ActivityDatePresetItem]
}>()
</script>
