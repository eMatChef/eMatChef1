import type { ActivityHistoryEntryRow } from '@/api/activities'
import type { Composer } from 'vue-i18n'

type Translate = Composer['t']
type Te = Composer['te']

export const PACK_HISTORY_AGGREGATABLE_ACTIONS = new Set([
  'pack_move',
  'pack_moveback',
  'pack_container_bulk',
])

const AGGREGATION_WINDOW_MS = 2 * 60 * 1000

export type ActivityHistoryDisplayRow =
  | { kind: 'single'; entry: ActivityHistoryEntryRow }
  | { kind: 'aggregated'; entries: ActivityHistoryEntryRow[] }

function aggregationBucketKey(entry: ActivityHistoryEntryRow): string {
  const userId = entry.user?.id ?? '_anon'
  const step = String(entry.changes?.journey_step ?? entry.changes?.stage_to ?? '')
  return `${userId}|${entry.action}|${step}`
}

function canAggregateWith(group: ActivityHistoryEntryRow[], entry: ActivityHistoryEntryRow): boolean {
  if (group.length === 0) return true
  const first = group[0]
  if (aggregationBucketKey(first) !== aggregationBucketKey(entry)) return false
  const firstTime = new Date(first.created_at).getTime()
  const entryTime = new Date(entry.created_at).getTime()
  return Math.abs(entryTime - firstTime) <= AGGREGATION_WINDOW_MS
}

/** Chronologisch aufsteigend gruppieren, dann für Anzeige absteigend sortieren. */
export function aggregatePackHistoryEntries(
  entries: ActivityHistoryEntryRow[],
): ActivityHistoryDisplayRow[] {
  const chronological = [...entries].sort(
    (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
  )

  const rows: ActivityHistoryDisplayRow[] = []
  let currentGroup: ActivityHistoryEntryRow[] = []

  function flushGroup(): void {
    if (currentGroup.length === 0) return
    if (currentGroup.length === 1) {
      rows.push({ kind: 'single', entry: currentGroup[0] })
    } else {
      rows.push({ kind: 'aggregated', entries: [...currentGroup] })
    }
    currentGroup = []
  }

  for (const entry of chronological) {
    if (!PACK_HISTORY_AGGREGATABLE_ACTIONS.has(entry.action)) {
      flushGroup()
      rows.push({ kind: 'single', entry })
      continue
    }
    if (canAggregateWith(currentGroup, entry)) {
      currentGroup.push(entry)
    } else {
      flushGroup()
      currentGroup = [entry]
    }
  }
  flushGroup()

  return rows.sort((a, b) => {
    const timeA =
      a.kind === 'single'
        ? new Date(a.entry.created_at).getTime()
        : Math.max(...a.entries.map((e) => new Date(e.created_at).getTime()))
    const timeB =
      b.kind === 'single'
        ? new Date(b.entry.created_at).getTime()
        : Math.max(...b.entries.map((e) => new Date(e.created_at).getTime()))
    return timeB - timeA
  })
}

export function journeyStepHistoryLabel(
  step: string | undefined,
  t: Translate,
  te: Te,
): string {
  const s = (step ?? '').trim()
  if (!s) return ''
  const logisticsKey = `activities.materialJourney.step.${s}` as const
  if (s === 'issue' && te('activities.materialJourney.step.issueLogistics')) {
    return t('activities.materialJourney.step.issueLogistics')
  }
  if (te(logisticsKey)) return t(logisticsKey)
  const stageKey = `activities.history.packStage_${s}` as const
  if (te(stageKey)) return t(stageKey)
  return s
}

export function aggregatedPackHistoryTitle(
  entries: ActivityHistoryEntryRow[],
  t: Translate,
  te: Te,
): string {
  const first = entries[0]
  const step = journeyStepHistoryLabel(String(first.changes?.journey_step ?? ''), t, te)
  const count = entries.length
  if (step) {
    return t('activities.history.packAggregateTitle', { step, count })
  }
  return t('activities.history.packAggregateTitleNoStep', { count })
}

export function formatHistoryTimeRange(
  entries: ActivityHistoryEntryRow[],
  locale: string,
): string {
  const times = entries.map((e) => new Date(e.created_at).getTime())
  const min = Math.min(...times)
  const max = Math.max(...times)
  const fmt = (ms: number) =>
    new Date(ms).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })
  if (min === max) return fmt(min)
  return `${fmt(min)}–${fmt(max)}`
}

export function packMoveSummaryLine(
  entry: ActivityHistoryEntryRow,
  t: Translate,
  te: Te,
): string {
  const ch = entry.changes ?? {}
  if (entry.action === 'pack_container_bulk') {
    const label = String(ch.container_label ?? t('activities.history.packContainerFallback'))
    const mode = String(ch.mode ?? '')
    if (mode === 'return_all') {
      return t('activities.history.packContainerReturnAll', { label })
    }
    if (mode === 'unissue_all') {
      return t('activities.history.packContainerUnissueAll', { label })
    }
    const step = journeyStepHistoryLabel(String(ch.journey_step ?? ''), t, te)
    return t('activities.history.packContainerIssueAll', { label, step })
  }

  const name = String(ch.material_name ?? t('common.material'))
  const qty = Math.max(0, Number(ch.quantity ?? 0) || 0)
  const step = journeyStepHistoryLabel(String(ch.journey_step ?? ''), t, te)
  const source = String(ch.source ?? '').trim()

  if (entry.action === 'pack_moveback') {
    return t('activities.history.packMoveBackLine', { name, qty, step })
  }

  const sourceSuffix =
    source === 'scan'
      ? t('activities.history.packSourceScan')
      : source === 'bulk'
        ? t('activities.history.packSourceBulk')
        : ''

  const base = t('activities.history.packMoveLine', { name, qty, step })
  return sourceSuffix ? `${base} ${sourceSuffix}` : base
}
