import { snapDateToQuarterHour } from '@/utils/activityPlanningFromDefaults'

export function startOfLocalDay(d: Date): Date {
  const x = new Date(d.getTime())
  x.setHours(0, 0, 0, 0)
  return x
}

/** Kalendertag + Uhrzeit (Zeit aus zweitem Datum) */
export function combineDayAndTime(day: Date, time: Date): Date {
  const x = new Date(day.getTime())
  x.setHours(time.getHours(), time.getMinutes(), 0, 0)
  return snapDateToQuarterHour(x)
}
