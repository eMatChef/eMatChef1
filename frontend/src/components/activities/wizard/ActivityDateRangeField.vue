<template>
  <div class="activity-date-range-field-wrap">
    <VTextField
      :model-value="displayText"
      class="activity-date-range-field activity-v-date-input e-form-field"
      variant="outlined"
      density="compact"
      hide-details
      readonly
      prepend-icon=""
      prepend-inner-icon="mdi-calendar-range"
      :disabled="disabled"
      :focused="menuOpen"
      :placeholder="t('activities.dateRangePicker.placeholder')"
      @click:control="openMenu"
      @click:prepend-inner="openMenu"
    >
      <ActivityDatePickerMenuShell
        v-model:open="menuOpen"
        :presets="menuPresets"
        :show-presets="showPresetSidebar"
        :presets-aria-label="t('activities.dateRangePicker.presetsAria')"
        @select-preset="applyPreset"
      >
        <ActivityDateRangeDualPicker
          v-if="dualCalendar"
          :model-value="pickerRange"
          :min="minDate"
          :allowed-dates="allowedDates"
          :menu-open="menuOpen"
          @update:model-value="onRangeUpdate"
        >
          <template #day="{ item, props: dayBtnProps, paneMonth, paneYear, rangeInPaneMonthOnly }">
            <ActivityDatePickerDay
              :item="item"
              :btn-props="dayBtnProps"
              :range="displayRange"
              :range-anchor-count="rangeAnchorCount"
              :markers="markersForIsoKey(item.isoDate)"
              :department-closed-date-keys="departmentClosedDateKeys"
              :range-in-pane-month-only="rangeInPaneMonthOnly"
              :pane-month="paneMonth"
              :pane-year="paneYear"
              @hover="onDayHover"
            />
          </template>
        </ActivityDateRangeDualPicker>
        <div
          v-else
          class="activity-date-picker-pane-wrap"
          @wheel="onSingleWheel"
          @touchstart.passive="onSingleTouchStart"
          @touchend.passive="onSingleTouchEnd"
        >
          <VDatePicker
            v-bind="activityDatePickerCommonProps"
            :model-value="pickerRange"
            :month="singlePaneMonth"
            :year="singlePaneYear"
            multiple="range"
            :min="minDate"
            :allowed-dates="allowedDates"
            @update:model-value="onRangeUpdate"
            @update:month="onSingleMonthFromPicker"
            @update:year="onSingleYearFromPicker"
          >
            <template #controls="controls">
              <ActivityDatePickerControlsBar
                v-bind="controls"
                :prev-month="() => shiftSingleMonth(-1)"
                :next-month="() => shiftSingleMonth(1)"
              />
            </template>
            <template #day="{ item, props: dayBtnProps }">
              <ActivityDatePickerDay
                :item="item"
                :btn-props="dayBtnProps"
                :range="displayRange"
                :range-anchor-count="rangeAnchorCount"
                :markers="markersForIsoKey(item.isoDate)"
                :department-closed-date-keys="departmentClosedDateKeys"
                @hover="onDayHover"
              />
            </template>
          </VDatePicker>
        </div>
      </ActivityDatePickerMenuShell>
    </VTextField>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { VDatePicker, VTextField } from 'vuetify/components'
import { useSmAndUp } from '@/composables/useSmAndUp'
import { useActivityDatePickerEvents } from '@/composables/useActivityDatePickerEvents'
import { useActivityDatePickerPaneMonth } from '@/composables/useActivityDatePickerPaneMonth'
import { useActivityDatePresets } from '@/composables/useActivityDatePresets'
import { useActivityDateRangePicker } from '@/composables/useActivityDateRangePicker'
import { useToast } from '@/composables/useToast'
import { formatActivityDateRangeDe } from '@/utils/activityDateIso'
import { rangeContainsDepartmentClosedDate } from '@/utils/activityDatePickerModel'
import type { ActivityDatePresetItem } from '@/utils/activityDatePresets'
import { startOfToday } from '@/utils/swissMovableFeasts'
import ActivityDatePickerControlsBar from './ActivityDatePickerControlsBar.vue'
import ActivityDatePickerDay from './ActivityDatePickerDay.vue'
import ActivityDatePickerMenuShell from './ActivityDatePickerMenuShell.vue'
import ActivityDateRangeDualPicker from './ActivityDateRangeDualPicker.vue'
import { activityDatePickerCommonProps } from '@/utils/activityDatePickerCommonProps'

const props = withDefaults(
  defineProps<{
    modelValue: [Date, Date] | null
    showPresetSidebar?: boolean
    departmentId?: string | null
    disabled?: boolean
  }>(),
  { showPresetSidebar: true, departmentId: null, disabled: false },
)

const emit = defineEmits<{
  'update:modelValue': [value: [Date, Date] | null]
}>()

const { t } = useI18n()
const toast = useToast()
const smAndUp = useSmAndUp()
const dualCalendar = computed(() => smAndUp.value)
const menuOpen = ref(false)
const pickerRange = ref<Date[] | null>(null)

const minDate = computed(() => startOfToday())
const { allowedDates, departmentClosedDateKeys, calendarPeriods, markersForIsoKey } =
  useActivityDatePickerEvents(() => props.departmentId)
const menuPresets = useActivityDatePresets('range', calendarPeriods)

const { displayRange, rangeAnchorCount, onDayHover, onRangeUpdate } = useActivityDateRangePicker({
  pickerRange,
  menuOpen,
  onCommit: (range) => emit('update:modelValue', range),
  departmentClosedDateKeys,
})

const {
  month: singlePaneMonth,
  year: singlePaneYear,
  shiftMonth: shiftSingleMonth,
  onWheel: onSingleWheel,
  onTouchStart: onSingleTouchStart,
  onTouchEnd: onSingleTouchEnd,
  onMonthFromPicker: onSingleMonthFromPicker,
  onYearFromPicker: onSingleYearFromPicker,
} = useActivityDatePickerPaneMonth({
  menuOpen,
  anchorDate: () => pickerRange.value?.[0] ?? props.modelValue?.[0] ?? minDate.value,
})

watch(
  () => props.modelValue,
  (m) => {
    pickerRange.value = m ? [m[0], m[1]] : null
  },
  { immediate: true },
)

const displayText = computed(() => {
  if (!props.modelValue) return ''
  return formatActivityDateRangeDe(props.modelValue)
})

function openMenu() {
  if (props.disabled) return
  menuOpen.value = true
}

function applyPreset(preset: ActivityDatePresetItem) {
  const v = preset.value
  const range: [Date, Date] = v instanceof Date ? [v, v] : [v[0], v[1]]
  if (rangeContainsDepartmentClosedDate(range[0], range[1], departmentClosedDateKeys.value)) {
    toast.warning(t('activities.dateRangePicker.rangeBlockedByDepartmentBreak'))
    return
  }
  pickerRange.value = range
  emit('update:modelValue', range)
  menuOpen.value = false
}
</script>

<style scoped>
.activity-date-range-field-wrap {
  width: 100%;
}

.activity-date-range-field {
  width: 100%;
}
</style>
