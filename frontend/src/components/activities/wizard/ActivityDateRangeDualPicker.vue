<template>
  <div class="activity-date-range-dual-picker">
    <ActivityDateRangeDualPickerHeader
      :left-month-text="leftMonthText"
      :right-month-text="rightMonthText"
      :year-text="yearText"
      @prev="shiftLeft(-1)"
      @next="shiftLeft(1)"
      @open-left-month="openLeftMonths()"
      @open-right-month="openRightMonths()"
      @open-year="openLeftYears()"
    />

    <div class="activity-date-range-dual-picker__panes">
      <div
        class="activity-date-range-dual-picker__pane-wrap"
        @wheel="onLeftWheel"
      >
        <VDatePicker
          v-bind="activityDatePickerCommonProps"
          :model-value="modelValue"
          :month="leftMonth"
          :year="leftYear"
          multiple="range"
          :min="min"
          :allowed-dates="allowedDates"
          class="activity-date-range-dual-picker__pane"
          @update:model-value="emit('update:modelValue', $event)"
          @update:month="onLeftMonth"
          @update:year="onLeftYear"
        >
          <template #controls="controls">
            <span class="d-none" aria-hidden="true">{{ bindLeftControls(controls) }}</span>
          </template>
          <template #day="slotProps">
            <slot
              name="day"
              v-bind="{
                ...slotProps,
                paneMonth: leftMonth,
                paneYear: leftYear,
                rangeInPaneMonthOnly: true,
              }"
            />
          </template>
        </VDatePicker>
      </div>
      <div
        class="activity-date-range-dual-picker__pane-wrap"
        @wheel="onRightWheel"
      >
        <VDatePicker
          v-bind="activityDatePickerCommonProps"
          :model-value="modelValue"
          :month="rightMonth"
          :year="rightYear"
          multiple="range"
          :min="min"
          :allowed-dates="allowedDates"
          class="activity-date-range-dual-picker__pane"
          @update:model-value="emit('update:modelValue', $event)"
          @update:month="onRightMonth"
          @update:year="onRightYear"
        >
          <template #controls="controls">
            <span class="d-none" aria-hidden="true">{{ bindRightControls(controls) }}</span>
          </template>
          <template #day="slotProps">
            <slot
              name="day"
              v-bind="{
                ...slotProps,
                paneMonth: rightMonth,
                paneYear: rightYear,
                rangeInPaneMonthOnly: true,
              }"
            />
          </template>
        </VDatePicker>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { VDatePicker } from 'vuetify/components'
import { activityDatePickerCommonProps } from '@/utils/activityDatePickerCommonProps'
import {
  formatActivityPickerMonthLabel,
  formatActivityPickerYearLabel,
} from '@/utils/activityDatePickerMonthLabel'
import {
  shiftMonthYear,
  useActivityDatePickerWheelMonth,
} from '@/composables/useActivityDatePickerWheelMonth'
import ActivityDateRangeDualPickerHeader from './ActivityDateRangeDualPickerHeader.vue'

type PickerControls = {
  openMonths: () => void
  openYears: () => void
}

const props = defineProps<{
  modelValue: Date[] | null
  min?: Date
  allowedDates?: (date: unknown) => boolean
  /** Menü geöffnet — Anker-Monat neu setzen */
  menuOpen?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Date | Date[] | null]
}>()

const leftMonth = ref(new Date().getMonth())
const leftYear = ref(new Date().getFullYear())
const rightMonth = ref((new Date().getMonth() + 1) % 12)
const rightYear = ref(
  new Date().getMonth() === 11 ? new Date().getFullYear() + 1 : new Date().getFullYear(),
)

const leftControls = ref<PickerControls | null>(null)
const rightControls = ref<PickerControls | null>(null)
const pendingLeftMonthPick = ref(false)
const pendingLeftYearPick = ref(false)
const pendingRightMonthPick = ref(false)
const pendingRightYearPick = ref(false)

const leftMonthText = computed(() => formatActivityPickerMonthLabel(leftMonth.value, leftYear.value))
const rightMonthText = computed(() =>
  formatActivityPickerMonthLabel(rightMonth.value, rightYear.value),
)
const yearText = computed(() => formatActivityPickerYearLabel(leftYear.value, rightYear.value))

function bindLeftControls(controls: PickerControls) {
  leftControls.value = controls
  return ''
}

function bindRightControls(controls: PickerControls) {
  rightControls.value = controls
  return ''
}

function syncRightFromLeft() {
  const next = shiftMonthYear(leftMonth.value, leftYear.value, 1)
  rightMonth.value = next.month
  rightYear.value = next.year
}

function syncLeftFromRight() {
  const prev = shiftMonthYear(rightMonth.value, rightYear.value, -1)
  leftMonth.value = prev.month
  leftYear.value = prev.year
}

function anchorFromSelection() {
  const picked = props.modelValue?.[0]
  const base = picked && Number.isFinite(picked.getTime()) ? picked : props.min ?? new Date()
  leftMonth.value = base.getMonth()
  leftYear.value = base.getFullYear()
  syncRightFromLeft()
}

watch(
  () => props.menuOpen,
  (open) => {
    if (open) anchorFromSelection()
  },
)

/** Nach Datums-Klick: VDatePicker zieht Monat mit — rechts wieder auf Folgemonat setzen. */
watch(
  () => props.modelValue,
  () => {
    nextTick(() => syncRightFromLeft())
  },
  { deep: true },
)

function openLeftMonths() {
  pendingLeftMonthPick.value = true
  leftControls.value?.openMonths()
}

function openRightMonths() {
  pendingRightMonthPick.value = true
  rightControls.value?.openMonths()
}

function openLeftYears() {
  pendingLeftYearPick.value = true
  leftControls.value?.openYears()
}

function onLeftMonth(m: number) {
  if (pendingLeftMonthPick.value) {
    pendingLeftMonthPick.value = false
    leftMonth.value = m
    syncRightFromLeft()
    return
  }
  syncRightFromLeft()
}

function onLeftYear(y: number) {
  if (pendingLeftYearPick.value) {
    pendingLeftYearPick.value = false
    leftYear.value = y
    syncRightFromLeft()
    return
  }
  syncRightFromLeft()
}

function onRightMonth(_m: number) {
  if (pendingRightMonthPick.value) {
    pendingRightMonthPick.value = false
    rightMonth.value = _m
    syncLeftFromRight()
    return
  }
  syncRightFromLeft()
}

function onRightYear(y: number) {
  if (pendingRightYearPick.value) {
    pendingRightYearPick.value = false
    rightYear.value = y
    syncLeftFromRight()
    return
  }
  syncRightFromLeft()
}

function shiftLeft(delta: number) {
  const next = shiftMonthYear(leftMonth.value, leftYear.value, delta)
  leftMonth.value = next.month
  leftYear.value = next.year
  syncRightFromLeft()
}

const { onWheel: onLeftWheel } = useActivityDatePickerWheelMonth({
  month: leftMonth,
  year: leftYear,
  onAfterChange: syncRightFromLeft,
})

const { onWheel: onRightWheel } = useActivityDatePickerWheelMonth({
  month: rightMonth,
  year: rightYear,
  onAfterChange: syncLeftFromRight,
})
</script>
