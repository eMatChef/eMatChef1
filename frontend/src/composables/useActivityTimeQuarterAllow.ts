import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { snapDateToQuarterHour } from '@/utils/activityPlanningFromDefaults'
import {
  isInstantInsideClosedUsage,
  nearestAllowedQuarterOnDayOutsideUsage,
} from '@/utils/activityPlanningUsageConstraint'
import { ACTIVITY_QUARTER_MINUTES, isActivityQuarterMinute } from '@/utils/activityTimeQuarter'
import { startOfLocalDay } from '@/utils/activityDateTimeParts'

export function useActivityTimeQuarterAllow(
  modelValue: MaybeRefOrGetter<Date | null>,
  blockedUsageRange: MaybeRefOrGetter<{ start: Date; end: Date } | null | undefined>,
) {
  function dayAnchor(): Date | null {
    const d = toValue(modelValue)
    if (!d || !Number.isFinite(d.getTime())) return null
    return startOfLocalDay(d)
  }

  function instantOnDay(day: Date, hour: number, minute: number): Date {
    const x = new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour, minute, 0, 0)
    return snapDateToQuarterHour(x)
  }

  function isMinuteAllowed(hour: number, minute: number): boolean {
    if (!isActivityQuarterMinute(minute)) return false
    const r = toValue(blockedUsageRange)
    const day = dayAnchor()
    const mv = toValue(modelValue)
    if (!r || !day || !mv) return true
    return !isInstantInsideClosedUsage(instantOnDay(day, hour, minute), r.start, r.end)
  }

  const allowedHours = computed(() => (hour: number) =>
    ACTIVITY_QUARTER_MINUTES.some((m) => isMinuteAllowed(hour, m)),
  )

  const allowedMinutes = computed(() => (hour: number, minute: number) => isMinuteAllowed(hour, minute))

  function applyTimeToModel(hour: number, minute: number): Date | null {
    const base = toValue(modelValue)
    if (!base) return null
    let out = new Date(base.getTime())
    out.setHours(hour, minute, 0, 0)
    out = snapDateToQuarterHour(out)
    const r = toValue(blockedUsageRange)
    if (r && isInstantInsideClosedUsage(out, r.start, r.end)) {
      const fixed = nearestAllowedQuarterOnDayOutsideUsage(out, r.start, r.end)
      if (!fixed) return null
      out = fixed
    }
    return out
  }

  return { allowedHours, allowedMinutes, applyTimeToModel }
}
