<template>
  <div class="activity-date-field-wrap">
    <VTextField
      :model-value="displayText"
      class="activity-date-field activity-v-date-input e-form-field"
      variant="outlined"
      density="compact"
      hide-details
      readonly
      prepend-icon=""
      prepend-inner-icon="mdi-calendar"
      :disabled="disabled"
      :focused="menuOpen"
      @click:control="openMenu"
      @click:prepend-inner="openMenu"
    >
      <ActivityDatePickerMenuShell
        v-model:open="menuOpen"
        :presets="menuPresets"
        :show-presets="showPresets"
        :presets-aria-label="t('activities.dateRangePicker.presetsAria')"
        @select-preset="applyPreset"
      >
        <div
          class="activity-date-picker-pane-wrap"
          @wheel="onWheel"
          @touchstart.passive="onTouchStart"
          @touchend.passive="onTouchEnd"
        >
          <VDatePicker
            v-bind="activityDatePickerCommonProps"
            :model-value="modelValue"
            :month="paneMonth"
            :year="paneYear"
            :min="minDate"
            :allowed-dates="allowedDates"
            @update:model-value="onPickerUpdate"
            @update:month="onMonthFromPicker"
            @update:year="onYearFromPicker"
          >
            <template #controls="controls">
              <ActivityDatePickerControlsBar
                v-bind="controls"
                :prev-month="() => shiftMonth(-1)"
                :next-month="() => shiftMonth(1)"
              />
            </template>
            <template #day="{ item, props: dayBtnProps }">
              <ActivityDatePickerDay
                :item="item"
                :btn-props="dayBtnProps"
                :markers="markersForIsoKey(item.isoDate)"
                :department-closed-date-keys="departmentClosedDateKeys"
              />
            </template>
          </VDatePicker>
        </div>
      </ActivityDatePickerMenuShell>
    </VTextField>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { VDatePicker, VTextField } from 'vuetify/components'
import { useActivityDatePickerEvents } from '@/composables/useActivityDatePickerEvents'
import { useActivityDatePickerPaneMonth } from '@/composables/useActivityDatePickerPaneMonth'
import { useActivityDatePresets } from '@/composables/useActivityDatePresets'
import { formatActivityDateDe } from '@/utils/activityDateIso'
import type { ActivityDatePresetItem } from '@/utils/activityDatePresets'
import { startOfToday } from '@/utils/swissMovableFeasts'
import ActivityDatePickerControlsBar from './ActivityDatePickerControlsBar.vue'
import ActivityDatePickerDay from './ActivityDatePickerDay.vue'
import ActivityDatePickerMenuShell from './ActivityDatePickerMenuShell.vue'
import { activityDatePickerCommonProps } from '@/utils/activityDatePickerCommonProps'

const props = withDefaults(
  defineProps<{
    modelValue: Date | null
    departmentId?: string | null
    disabled?: boolean
    showPresets?: boolean
  }>(),
  { departmentId: null, disabled: false, showPresets: true },
)

const emit = defineEmits<{
  'update:modelValue': [value: Date | null]
}>()

const { t } = useI18n()
const menuOpen = ref(false)
const minDate = computed(() => startOfToday())

const {
  month: paneMonth,
  year: paneYear,
  shiftMonth,
  onWheel,
  onTouchStart,
  onTouchEnd,
  onMonthFromPicker,
  onYearFromPicker,
} = useActivityDatePickerPaneMonth({
  menuOpen,
  anchorDate: () => props.modelValue ?? minDate.value,
})

const { allowedDates, departmentClosedDateKeys, calendarPeriods, markersForIsoKey } =
  useActivityDatePickerEvents(() => props.departmentId)
const menuPresets = useActivityDatePresets('single', calendarPeriods)

const displayText = computed(() => formatActivityDateDe(props.modelValue))

function openMenu() {
  if (props.disabled) return
  menuOpen.value = true
}

function onPickerUpdate(value: Date | Date[] | null) {
  if (value instanceof Date) {
    emit('update:modelValue', value)
    menuOpen.value = false
  } else if (Array.isArray(value) && value[0]) {
    emit('update:modelValue', value[0])
    menuOpen.value = false
  }
}

function applyPreset(preset: ActivityDatePresetItem) {
  const d = preset.value instanceof Date ? preset.value : preset.value[0]
  emit('update:modelValue', d)
  menuOpen.value = false
}
</script>

<style scoped>
.activity-date-field-wrap {
  width: 100%;
}

.activity-date-field {
  width: 100%;
}
</style>
