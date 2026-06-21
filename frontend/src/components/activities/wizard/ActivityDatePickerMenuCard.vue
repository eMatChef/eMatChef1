<template>
  <div
    v-if="stacked"
    class="activity-date-picker-menu__shell activity-date-picker-menu__shell--stacked"
    :class="shellClass ? shellClass : undefined"
  >
    <div class="activity-date-picker-menu__body">
      <ActivityDatePresetList
        v-if="showPresets && presets.length"
        class="activity-date-picker-menu__presets activity-date-picker-menu__presets--stacked"
        :presets="presets"
        :aria-label="presetsAriaLabel"
        @select="emit('select-preset', $event)"
      />
      <VDivider v-if="showPresets && presets.length" class="activity-date-picker-menu__divider" />
      <div class="activity-date-picker-menu__picker">
        <slot />
      </div>
    </div>
  </div>

  <VCard
    v-else
    class="activity-date-picker-menu__shell"
    :class="shellClass ? shellClass : undefined"
    :rounded="rounded"
    :elevation="elevation"
  >
    <div class="activity-date-picker-menu__picker">
      <slot />
    </div>
    <template v-if="showPresets && presets.length">
      <VDivider class="activity-date-picker-menu__divider" vertical />
      <ActivityDatePresetList
        class="activity-date-picker-menu__presets"
        :presets="presets"
        :aria-label="presetsAriaLabel"
        @select="emit('select-preset', $event)"
      />
    </template>
  </VCard>
</template>

<script setup lang="ts">
import { VCard, VDivider } from 'vuetify/components'
import ActivityDatePresetList from './ActivityDatePresetList.vue'
import type { ActivityDatePresetItem } from '@/utils/activityDatePresets'

withDefaults(
  defineProps<{
    presets?: ActivityDatePresetItem[]
    showPresets?: boolean
    presetsAriaLabel?: string
    /** Mobile / Bottom Sheet: Presets oben, Kalender unten */
    stacked?: boolean
    rounded?: string | number | boolean
    elevation?: string | number
    shellClass?: string
  }>(),
  {
    presets: () => [],
    showPresets: true,
    presetsAriaLabel: '',
    stacked: false,
    rounded: 'lg',
    elevation: 8,
    shellClass: '',
  },
)

const emit = defineEmits<{
  'select-preset': [preset: ActivityDatePresetItem]
}>()
</script>
