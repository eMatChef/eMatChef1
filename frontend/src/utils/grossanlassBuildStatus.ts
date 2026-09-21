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

export function deriveBuildStatus(
  windowStart?: string | null,
  windowEnd?: string | null,
  today = todayYmd(),
): GaBuildStatus {
  const from = (windowStart || '').slice(0, 10)
  const to = (windowEnd || '').slice(0, 10)
  if (from && today < from) return 'planned'
  if (to && today > to) return 'done'
  if (from || to) return 'use'
  return 'planned'
}

export function resolveBuildStatus(
  group: { build_status?: string | null; window_start?: string | null; window_end?: string | null },
  today = todayYmd(),
): GaBuildStatus {
  if (isGaBuildStatus(group.build_status)) return group.build_status
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
