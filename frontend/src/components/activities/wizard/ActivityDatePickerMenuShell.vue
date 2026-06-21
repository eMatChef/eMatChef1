<template>
  <VMenu
    v-if="useDropdown"
    v-model="open"
    :activator="menuActivator"
    :close-on-content-click="false"
    :open-on-click="false"
    location="bottom start"
    min-width="0"
    v-bind="menuProps"
    @click:outside="closeOnOutside"
  >
    <ActivityDatePickerMenuCard
      :presets="presets"
      :show-presets="showPresets"
      :presets-aria-label="presetsAriaLabel"
      :stacked="false"
      :shell-class="shellHostClass"
      @select-preset="emit('select-preset', $event)"
    >
      <slot />
    </ActivityDatePickerMenuCard>
  </VMenu>

  <VBottomSheet
    v-else
    v-model="open"
    class="activity-date-picker-bottom-sheet"
    content-class="activity-date-picker-bottom-sheet__content"
    :scrim="false"
    :retain-focus="false"
    :z-index="ACTIVITY_PICKER_MENU_Z_INDEX"
    @click:outside="closeOnOutside"
  >
    <ActivityDatePickerMenuCard
      :presets="presets"
      :show-presets="showPresets"
      :presets-aria-label="presetsAriaLabel"
      stacked
      rounded="t-lg"
      :elevation="0"
      shell-class="activity-date-picker-menu__shell--sheet"
      @select-preset="emit('select-preset', $event)"
    >
      <slot />
    </ActivityDatePickerMenuCard>
  </VBottomSheet>
</template>

<script setup lang="ts">
import { computed, toRef, type ComponentPublicInstance } from 'vue'
import { VBottomSheet, VMenu } from 'vuetify/components'
import { useActivityPickerOutsideClose } from '@/composables/useActivityPickerOutsideClose'
import { useSmAndUp } from '@/composables/useSmAndUp'
import {
  ACTIVITY_PICKER_MENU_Z_INDEX,
} from '@/composables/useActivityDateMenuProps'
import ActivityDatePickerMenuCard from './ActivityDatePickerMenuCard.vue'
import { activityDatePickerMenuProps } from '@/utils/activityDatePickerMenuProps'
import type { ActivityDatePresetItem } from '@/utils/activityDatePresets'

const props = withDefaults(
  defineProps<{
    presets?: ActivityDatePresetItem[]
    showPresets?: boolean
    presetsAriaLabel?: string
    menuProps?: typeof activityDatePickerMenuProps
    activator?: ComponentPublicInstance | HTMLElement | null
  }>(),
  {
    presets: () => [],
    showPresets: true,
    presetsAriaLabel: '',
    menuProps: () => activityDatePickerMenuProps,
    activator: null,
  },
)

const emit = defineEmits<{
  'select-preset': [preset: ActivityDatePresetItem]
}>()

const open = defineModel<boolean>('open', { default: false })
const smAndUp = useSmAndUp()
const useDropdown = computed(() => smAndUp.value)
const menuActivator = computed(() => props.activator ?? undefined)
const shellHostClass = computed(() =>
  smAndUp.value ? 'activity-date-picker-menu__shell--sm-and-up' : 'activity-date-picker-menu__shell--sm-down',
)

const { closeOnOutside } = useActivityPickerOutsideClose({
  open,
  activator: toRef(props, 'activator'),
})
</script>
