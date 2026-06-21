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
        @touchstart.passive="onLeftTouchStart"
        @touchend.passive="onLeftTouchEnd"
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
        @touchstart.passive="onRightTouchStart"
        @touchend.passive="onRightTouchEnd"
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

/** Linkes/rechtes Pane aus gewähltem Zeitraum (Start- und Endmonat). */
function dualPaneAnchorFromRange(
  range: Date[] | null | undefined,
  min?: Date,
): { leftMonth: number; leftYear: number; rightMonth: number; rightYear: number } {
  const start = range?.[0]
  const end = range && range.length >= 2 ? range[1] : start
  const fallback = min ?? new Date()

  if (!start || !Number.isFinite(start.getTime())) {
    const lm = fallback.getMonth()
    const ly = fallback.getFullYear()
    const next = shiftMonthYear(lm, ly, 1)
    return { leftMonth: lm, leftYear: ly, rightMonth: next.month, rightYear: next.year }
  }

  const lm = start.getMonth()
  const ly = start.getFullYear()

  if (end && Number.isFinite(end.getTime())) {
    const rm = end.getMonth()
    const ry = end.getFullYear()
    const leftKey = ly * 12 + lm
    const endKey = ry * 12 + rm

    if (leftKey === endKey) {
      const next = shiftMonthYear(lm, ly, 1)
      return { leftMonth: lm, leftYear: ly, rightMonth: next.month, rightYear: next.year }
    }

    if (leftKey < endKey) {
      return { leftMonth: lm, leftYear: ly, rightMonth: rm, rightYear: ry }
    }
  }

  const next = shiftMonthYear(lm, ly, 1)
  return { leftMonth: lm, leftYear: ly, rightMonth: next.month, rightYear: next.year }
}

const initialAnchor = dualPaneAnchorFromRange(props.modelValue, props.min)
const leftMonth = ref(initialAnchor.leftMonth)
const leftYear = ref(initialAnchor.leftYear)
const rightMonth = ref(initialAnchor.rightMonth)
const rightYear = ref(initialAnchor.rightYear)

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
  const next = dualPaneAnchorFromRange(props.modelValue, props.min)
  leftMonth.value = next.leftMonth
  leftYear.value = next.leftYear
  rightMonth.value = next.rightMonth
  rightYear.value = next.rightYear
}

watch(
  () => props.menuOpen,
  (open) => {
    if (open) anchorFromSelection()
  },
  { immediate: true },
)

/** Nach Datums-Klick: VDatePicker zieht Monat mit — Auswahl neu verankern solange Menü offen. */
watch(
  () => props.modelValue,
  () => {
    nextTick(() => {
      if (props.menuOpen) anchorFromSelection()
      else syncRightFromLeft()
    })
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

const { onWheel: onLeftWheel, onTouchStart: onLeftTouchStart, onTouchEnd: onLeftTouchEnd } =
  useActivityDatePickerWheelMonth({
  month: leftMonth,
  year: leftYear,
  onAfterChange: syncRightFromLeft,
})

const { onWheel: onRightWheel, onTouchStart: onRightTouchStart, onTouchEnd: onRightTouchEnd } =
  useActivityDatePickerWheelMonth({
  month: rightMonth,
  year: rightYear,
  onAfterChange: syncLeftFromRight,
})
</script>
