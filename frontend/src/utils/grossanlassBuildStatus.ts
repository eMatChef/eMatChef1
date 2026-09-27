import { ref, watch, type Ref } from 'vue'
import {
  listDepartmentCalendarPeriods,
  type DepartmentCalendarPeriod,
} from '@/api/calendarPeriods'

export const GA_BUILD_STATUSES = [
  'planned',
  'build',
  'use',
  'teardown',
  'done',
  'aborted',
] as const

export type GaBuildStatus = (typeof GA_BUILD_STATUSES)[number]

export function todayYmd(now = new Date()): string {
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function isGaBuildStatus(value: unknown): value is GaBuildStatus {
  return typeof value === 'string' && (GA_BUILD_STATUSES as readonly string[]).includes(value)
}

const buildStatusPeriods = ref<DepartmentCalendarPeriod[]>([])
let loadedDepartmentId = ''

export function watchGrossanlassBuildPeriods(
  departmentId: Ref<string | null | undefined>,
): void {
  watch(departmentId, (id) => {
    void loadBuildStatusPeriods(id)
  }, { immediate: true })
}

export async function loadBuildStatusPeriods(departmentId?: string | null): Promise<void> {
  const id = departmentId || ''
  if (!id || id === loadedDepartmentId) return
  loadedDepartmentId = id
  const year = new Date().getFullYear()
  try {
    const rows = await listDepartmentCalendarPeriods(id, [year - 1, year, year + 1, year + 2])
    buildStatusPeriods.value = rows.filter((row) =>
      row.label === 'aufbau' || row.label === 'abbau' || row.label === 'grossanlass',
    )
  } catch {
    loadedDepartmentId = ''
    buildStatusPeriods.value = []
  }
}

export function deriveBuildStatus(
  windowStart?: string | null,
  windowEnd?: string | null,
  today = todayYmd(),
  periods: DepartmentCalendarPeriod[] = buildStatusPeriods.value,
): GaBuildStatus {
  const from = (windowStart || '').slice(0, 10)
  const to = (windowEnd || '').slice(0, 10)
  if (!from && !to) return 'planned'
  if (from && today < from) return 'planned'
  if (to && today > to) return 'done'

  const active = periods.filter((period) => {
    const start = (period.start_date || '').slice(0, 10)
    const end = (period.end_date || period.start_date || '').slice(0, 10)
    if (!start || today < start || today > end) return false
    if (from && end < from) return false
    if (to && start > to) return false
    return true
  })
  if (active.some((period) => period.label === 'abbau')) return 'teardown'
  if (active.some((period) => period.label === 'aufbau')) return 'build'
  return 'use'
}

const REPORT_ORDER: GaBuildStatus[] = ['aborted', 'planned', 'build', 'use', 'teardown', 'done']

export function childReportedStatuses(
  rootId: string,
  groups: Array<{ id: string; parent_id?: string | null; node_type?: string | null; build_status?: string | null }>,
): GaBuildStatus[] {
  const byParent = new Map<string, typeof groups>()
  for (const group of groups) {
    const parentId = group.parent_id || ''
    const list = byParent.get(parentId) ?? []
    list.push(group)
    byParent.set(parentId, list)
  }
  const reports: GaBuildStatus[] = []
  const walk = (id: string) => {
    for (const child of byParent.get(id) ?? []) {
      if (child.node_type === 'bauprojekt' && isGaBuildStatus(child.build_status)) {
        reports.push(child.build_status)
      } else {
        walk(child.id)
      }
    }
  }
  walk(rootId)
  return reports
}

export function resolveBuildStatus(
  group: {
    node_type?: string | null
    build_status?: string | null
    window_start?: string | null
    window_end?: string | null
  },
  today = todayYmd(),
  childReports: Array<string | null | undefined> = [],
): GaBuildStatus {
  const reports: GaBuildStatus[] = []
  if (group.node_type === 'bauprojekt' && isGaBuildStatus(group.build_status)) {
    reports.push(group.build_status)
  }
  for (const value of childReports) {
    if (isGaBuildStatus(value)) reports.push(value)
  }
  if (reports.length === 0) {
    return deriveBuildStatus(group.window_start, group.window_end, today)
  }
  for (const status of REPORT_ORDER) {
    if (reports.includes(status)) return status
  }
  return deriveBuildStatus(group.window_start, group.window_end, today)
}

export function gaBuildStatusI18nKey(status: GaBuildStatus): string {
  return `grossanlass.planung.ressorts.buildStatus.${status}`
}

export function gaBuildStatusSelectItems(
  t: (key: string) => string,
): Array<{ title: string; value: string }> {
  return [
    { title: t('grossanlass.planung.ressorts.buildStatus.auto'), value: '' },
    ...GA_BUILD_STATUSES.map((status) => ({
      title: t(gaBuildStatusI18nKey(status)),
      value: status,
    })),
  ]
}

export function gaBuildStatusAutoSaveOptions(
  t: (key: string) => string,
): Array<{ value: string; label: string }> {
  return gaBuildStatusSelectItems(t).map((item) => ({
    value: item.value,
    label: item.title,
  }))
}

export function showsGaBuildStatus(group: { node_type?: string | null }): boolean {
  return group.node_type === 'unterressort' || group.node_type === 'bauprojekt'
}
