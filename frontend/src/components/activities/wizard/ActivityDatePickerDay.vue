<template>
  <VTooltip :text="dayTooltip ?? ''" :disabled="!dayTooltip" location="top" :open-delay="300">
    <template #activator="{ props: tipProps }">
      <span v-bind="tipProps" class="activity-date-picker-day-tooltip-target">
        <VBtn
          v-bind="mergedBtnProps"
          class="v-date-picker-month__day-btn activity-date-picker-day-btn"
          :class="dayClasses"
          :variant="buttonVariant"
          :color="buttonColor"
          :aria-current="item.isToday ? 'date' : undefined"
          :aria-label="dayTooltip"
          @mouseenter="onMouseEnter"
        >
          <span :class="{ 'activity-date-picker-day-btn--blocked-num': isDepartmentClosedDay }">{{
            item.localized
          }}</span>
          <div v-if="markers.length" class="v-date-picker-month__events">
            <VBadge
              v-for="(marker, idx) in markers"
              :key="`${marker.kind}-${idx}`"
              dot
              :color="marker.badgeColor"
            />
          </div>
        </VBtn>
      </span>
    </template>
  </VTooltip>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { VBadge, VBtn, VTooltip } from 'vuetify/components'
import { toIsoDateKey } from '@/utils/activityDateIso'
import type { ActivityDatePickerDayMarker } from '@/utils/activityDatePickerMarkers'

export type ActivityDatePickerDayItem = {
  date: unknown
  localized: string
  isToday?: boolean
  isSelected?: boolean
  isDisabled?: boolean
  isAdjacent?: boolean
  isoDate?: string
}

const props = withDefaults(
  defineProps<{
    item: ActivityDatePickerDayItem
    btnProps: Record<string, unknown>
    range?: Date[] | null
    rangeAnchorCount?: number
    markers?: ActivityDatePickerDayMarker[]
    departmentClosedDateKeys?: ReadonlySet<string> | null
    /** Doppelkalender: Range-Balken nur im angezeigten Monat des Panes */
    rangeInPaneMonthOnly?: boolean
    paneMonth?: number
    paneYear?: number
  }>(),
  {
    range: null,
    rangeAnchorCount: 0,
    markers: () => [],
    departmentClosedDateKeys: null,
    rangeInPaneMonthOnly: false,
    paneMonth: undefined,
    paneYear: undefined,
  },
)

const emit = defineEmits<{
  hover: [date: Date]
}>()

function startOfDay(d: Date): Date {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate())
}

function toDate(value: unknown): Date | null {
  if (value instanceof Date && Number.isFinite(value.getTime())) return value
  if (value == null) return null
  const d = new Date(String(value))
  return Number.isFinite(d.getTime()) ? d : null
}

function sameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  )
}

const rangeRole = computed((): 'start' | 'end' | 'middle' | 'start-end' | null => {
  const day = toDate(props.item.date)
  const range = props.range
  if (!day || !range || range.length < 1) return null
  const sorted = [...range].map(startOfDay).sort((a, b) => a.getTime() - b.getTime())
  const start = sorted[0]
  const end = sorted[sorted.length - 1]

  if (range.length === 1) {
    return sameDay(day, start) ? 'start' : null
  }

  const t = startOfDay(day).getTime()
  if (t < start.getTime() || t > end.getTime()) return null
  if (sameDay(day, start) && sameDay(day, end)) return 'start-end'
  if (sameDay(day, start)) return 'start'
  if (sameDay(day, end)) return 'end'
  return 'middle'
})

const isOutsideDisplayedPaneMonth = computed(() => {
  if (!props.rangeInPaneMonthOnly || props.paneMonth == null || props.paneYear == null) {
    return false
  }
  const day = toDate(props.item.date)
  if (!day) return false
  return day.getMonth() !== props.paneMonth || day.getFullYear() !== props.paneYear
})

/** Nachbar-Monatstage (z. B. Juli am Ende des Juni-Rasters): kein Range-Balken. */
const suppressRangeFill = computed(() => {
  if (!isOutsideDisplayedPaneMonth.value) return false
  // Einzelner Anker auf Nachbar-Tag: grüner Kreis behalten
  if (
    rangeRole.value === 'start' &&
    props.rangeAnchorCount === 1 &&
    (props.range?.length ?? 0) === 1
  ) {
    return false
  }
  return true
})

const visibleRangeRole = computed((): 'start' | 'end' | 'middle' | 'start-end' | null => {
  if (suppressRangeFill.value) return null
  return rangeRole.value
})

const visibleRangePreview = computed(
  () =>
    !suppressRangeFill.value &&
    props.rangeAnchorCount === 1 &&
    rangeRole.value != null &&
    rangeRole.value !== 'start' &&
    rangeRole.value !== 'start-end',
)

const isSelectedSingle = computed(() => {
  if (rangeRole.value) return false
  return !!props.item.isSelected
})

const dayIsoKey = computed((): string | null => {
  if (props.item.isoDate) return props.item.isoDate
  const d = toDate(props.item.date)
  return d ? toIsoDateKey(d) : null
})

const isDepartmentClosedDay = computed(() => {
  const k = dayIsoKey.value
  return k != null && (props.departmentClosedDateKeys?.has(k) ?? false)
})

const isDisabledDay = computed(() => !!props.item.isDisabled || isDepartmentClosedDay.value)

const isAdjacentFuture = computed(
  () => !!props.item.isAdjacent && !isDisabledDay.value,
)

const dayTooltip = computed(() => {
  if (!props.markers.length) return undefined
  return props.markers.map((m) => m.label).join('\n')
})

/** icon:false — sonst 24px-Kreis und verschobene Range/Events (Vuetify-Default) */
const mergedBtnProps = computed(() => ({
  ...props.btnProps,
  icon: false,
  disabled: isDisabledDay.value,
}))

const dayClasses = computed(() => ({
  'activity-date-picker-day-btn--today':
    props.item.isToday &&
    !props.item.isSelected &&
    !visibleRangeRole.value &&
    !isDisabledDay.value,
  'activity-date-picker-day-btn--disabled': isDisabledDay.value && !isDepartmentClosedDay.value,
  'activity-date-picker-day-btn--blocked': isDepartmentClosedDay.value,
  'activity-date-picker-day-btn--adjacent-future':
    isAdjacentFuture.value && !isSelectedSingle.value && !visibleRangeRole.value,
  'activity-date-picker-day-btn--selected': isSelectedSingle.value,
  'activity-date-picker-day-btn--range-start':
    visibleRangeRole.value === 'start' || visibleRangeRole.value === 'start-end',
  'activity-date-picker-day-btn--range-start-only':
    props.rangeAnchorCount === 1 &&
    (props.range?.length ?? 0) === 1 &&
    visibleRangeRole.value === 'start',
  'activity-date-picker-day-btn--range-end':
    visibleRangeRole.value === 'end' || visibleRangeRole.value === 'start-end',
  'activity-date-picker-day-btn--range-middle': visibleRangeRole.value === 'middle',
  'activity-date-picker-day-btn--range-preview': visibleRangePreview.value,
}))

const buttonVariant = computed(() => {
  if (
    visibleRangeRole.value === 'start' ||
    visibleRangeRole.value === 'end' ||
    visibleRangeRole.value === 'start-end'
  ) {
    return 'flat'
  }
  if (isSelectedSingle.value) return 'flat'
  if (props.item.isToday && !visibleRangeRole.value) return 'text'
  return 'text'
})

const buttonColor = computed(() => {
  if (
    visibleRangeRole.value === 'start' ||
    visibleRangeRole.value === 'start-end' ||
    isSelectedSingle.value
  ) {
    return 'primary'
  }
  if (visibleRangeRole.value === 'end') {
    return 'primary'
  }
  return undefined
})

function onMouseEnter() {
  if (isDisabledDay.value) return
  const d = toDate(props.item.date)
  if (d) emit('hover', d)
}
</script>

<style scoped>
.activity-date-picker-day-tooltip-target {
  display: inline-flex;
}
</style>
