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
      class="activity-date-preset-list__item"
      :class="itemClass(preset)"
      :title="preset.label"
      rounded="lg"
      @click="emit('select', preset)"
    >
      <template #prepend>
        <VBadge
          dot
          inline
          :color="accentColor(preset)"
          class="activity-date-preset-list__dot"
        />
      </template>
    </VListItem>
  </VList>
</template>

<script setup lang="ts">
import { VBadge, VList, VListItem } from 'vuetify/components'
import type { ActivityDatePresetItem } from '@/utils/activityDatePresets'
import { activityDatePresetAccentColor } from '@/utils/activityDatePickerMarkers'

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

function accentColor(preset: ActivityDatePresetItem): string {
  return activityDatePresetAccentColor(preset)
}

function itemClass(preset: ActivityDatePresetItem): string {
  return preset.periodLabel
    ? `activity-date-preset-list__item--${preset.periodLabel}`
    : 'activity-date-preset-list__item--quick'
}
</script>
