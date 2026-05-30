import type { ActivityHistoryEntryRow } from '@/api/activities'
import type { Composer } from 'vue-i18n'

type Translate = Composer['t']
type Te = Composer['te']

function statusLabel(t: Translate, te: Te, status: string): string {
  const key = `activities.status.${status}` as const
  return te(key) ? t(key) : status
}

export function historyActionLabel(t: Translate, action: string): string {
  const key = `activities.history.action_${action}`
  const tr = t(key)
  return tr === key ? action : tr
}

export function historyEntryTitle(
  e: ActivityHistoryEntryRow,
  t: Translate,
  te: Te,
): string {
  if (e.action === 'pack_crate_check') {
    return t('activities.history.crateCheckForwardTitle')
  }
  return historyActionLabel(t, e.action)
}

export function historyStatusChange(
  e: ActivityHistoryEntryRow,
  t: Translate,
  te: Te,
): { old: string; new: string } | null {
  if (e.action !== 'status_changed') return null
  const st = e.changes?.status
  if (!st || typeof st !== 'object' || !('old' in st) || !('new' in st)) return null
  const oldS = String((st as { old?: unknown }).old ?? '')
  const newS = String((st as { new?: unknown }).new ?? '')
  if (!oldS && !newS) return null
  return {
    old: statusLabel(t, te, oldS),
    new: statusLabel(t, te, newS),
  }
}

export function historyEntrySummaryLines(
  e: ActivityHistoryEntryRow,
  t: Translate,
  te: Te,
): string[] {
  if (e.action === 'pack_crate_check') {
    const lines: string[] = []
    const result = String(e.changes?.result ?? '').trim()
    if (result === 'ok') {
      lines.push(t('activities.history.crateCheckResultOk'))
    } else if (result === 'incomplete') {
      lines.push(t('activities.history.crateCheckResultIncomplete'))
    }
    const raw = e.changes?.lines
    if (Array.isArray(raw)) {
      for (const ln of raw as Array<Record<string, unknown>>) {
        lines.push(crateCheckLineText(ln, t, te))
      }
    }
    return lines
  }

  if (e.action === 'status_changed' && historyStatusChange(e, t, te)) {
    return []
  }

  if (e.action === 'material_changed' || e.action === 'material_items_changed') {
    return materialItemsChangedLines(e.changes, t)
  }

  if (e.action === 'pack_reset_cancel') {
    const prev = String(e.changes?.previous_status ?? '').trim()
    if (prev) {
      return [t('activities.history.packResetCancelPreviousStatus', { status: statusLabel(t, te, prev) })]
    }
    return []
  }

  const comment = e.changes?.comment
  if (typeof comment === 'string' && comment.trim()) {
    return [comment.trim()]
  }

  return []
}

type MaterialHistoryRow = {
  material_name?: string
  quantity?: number
  old?: number
  new?: number
}

function materialRowName(row: MaterialHistoryRow, t: Translate): string {
  const n = String(row.material_name ?? '').trim()
  return n || t('common.material')
}

function materialItemsChangedLines(
  changes: ActivityHistoryEntryRow['changes'],
  t: Translate,
): string[] {
  const lines: string[] = []
  const ch = changes ?? {}
  const added = Array.isArray(ch.added) ? (ch.added as MaterialHistoryRow[]) : []
  const removed = Array.isArray(ch.removed) ? (ch.removed as MaterialHistoryRow[]) : []
  const qtyChanged = Array.isArray(ch.quantity_changed)
    ? (ch.quantity_changed as MaterialHistoryRow[])
    : []

  for (const row of added) {
    lines.push(
      t('activities.history.materialAdded', {
        name: materialRowName(row, t),
        n: Math.max(0, Number(row.quantity ?? 0) || 0),
      }),
    )
  }
  for (const row of removed) {
    lines.push(
      t('activities.history.materialRemoved', {
        name: materialRowName(row, t),
        n: Math.max(0, Number(row.quantity ?? 0) || 0),
      }),
    )
  }
  for (const row of qtyChanged) {
    lines.push(
      t('activities.history.materialQuantityChanged', {
        name: materialRowName(row, t),
        old: Math.max(0, Number(row.old ?? 0) || 0),
        new: Math.max(0, Number(row.new ?? 0) || 0),
      }),
    )
  }
  return lines
}

function crateCheckLineText(ln: Record<string, unknown>, t: Translate, te: Te): string {
  const name = String(ln.material_name ?? t('common.material'))
  const status = String(ln.status ?? 'ok')
  const note = typeof ln.note === 'string' ? ln.note.trim() : ''
  const missingQty = Math.max(0, Number(ln.missing_qty ?? 0) || 0)
  const replenishQty = Math.max(0, Number(ln.replenish_qty ?? 0) || 0)
  const qty =
    status === 'loss' || status === 'repair' || status === 'not_taken'
      ? missingQty || 1
      : status === 'extra' || status === 'return_surplus'
        ? replenishQty || missingQty || 1
        : replenishQty || missingQty || 0

  const statusKey = `activities.history.crateLineStatus_${status}` as const
  const statusPart = te(statusKey)
    ? t(statusKey, { n: qty || 1 })
    : status

  const counted = ln.counted_qty != null ? Number(ln.counted_qty) : null
  const expected = ln.expected_qty != null ? Number(ln.expected_qty) : null
  const parts = [`${name}: ${statusPart}`]
  if (note) {
    parts.push(note)
  } else if (counted != null && expected != null) {
    parts.push(t('activities.history.lineIstSoll', { ist: counted, soll: expected }))
  }
  return parts.join(' — ')
}
