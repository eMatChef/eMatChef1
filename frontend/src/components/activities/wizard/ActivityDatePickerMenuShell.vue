<template>
  <VMenu
    v-model="open"
    activator="parent"
    :close-on-content-click="false"
    :open-on-click="false"
    location="bottom start"
    min-width="0"
    v-bind="menuProps"
  >
    <VCard class="activity-date-picker-menu__shell" rounded="lg" elevation="8">
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
  </VMenu>
</template>

<script setup lang="ts">
import { VCard, VDivider, VMenu } from 'vuetify/components'
import ActivityDatePresetList from './ActivityDatePresetList.vue'
import { activityDatePickerMenuProps } from '@/utils/activityDatePickerMenuProps'
import type { ActivityDatePresetItem } from '@/utils/activityDatePresets'

withDefaults(
  defineProps<{
    presets?: ActivityDatePresetItem[]
    showPresets?: boolean
    presetsAriaLabel?: string
    menuProps?: typeof activityDatePickerMenuProps
  }>(),
  {
    presets: () => [],
    showPresets: true,
    presetsAriaLabel: '',
    menuProps: () => activityDatePickerMenuProps,
  },
)

const emit = defineEmits<{
  'select-preset': [preset: ActivityDatePresetItem]
}>()

const open = defineModel<boolean>('open', { default: false })
</script>
