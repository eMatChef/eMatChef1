import type { PackCrateCheckLineStatus } from '@/api/activityPackCrateCheck'
import {
  countedQtyForDisplay,
  type CrateCheckSnapshot,
} from '@/components/activities/packCrateCheckReality'
import {
  applyCountedQtyToReview,
  defaultLineReview,
  type ShellForwardCheckLine,
  type ShellForwardLineReview,
  type ShellForwardResolution,
} from '@/components/activities/packCrateForwardCheck'

export function buildHistoryReplenishByKeyFromSnapshot(
  snapshot: CrateCheckSnapshot | undefined,
): Record<string, boolean> {
  if (!snapshot) return {}
  const out: Record<string, boolean> = {}
  for (const a of snapshot.actions_applied ?? []) {
    const key = String(a.line_key ?? '').trim()
    const st = String(a.status ?? '').trim()
    if (key && (st === 'replenish' || st === 'replenish_after_loss')) {
      out[key] = true
    }
  }
  for (const h of snapshot.lines) {
    const key = String(h.line_key ?? '').trim()
    const rq = Math.max(0, Math.floor(Number(h.replenish_qty ?? 0) || 0))
    if (key && rq > 0) out[key] = true
  }
  return out
}

function historyResolutionFromStatus(
  status: string,
  isExtra: boolean,
): ShellForwardResolution {
  const st = status.trim()
  if (st === 'ok') return null
  if (st === 'replenish' || st === 'replenish_after_loss') return 'replenish'
  if (st === 'loss' || st === 'repair' || st === 'not_taken' || st === 'extra') {
    return st as ShellForwardResolution
  }
  if (isExtra && st === 'problem') return 'extra'
  return null
}

/** Vorausfüllung aus letztem Check — Verlust/Reparatur/nicht mitgegeben ohne vorausgewählte Aktion. */
export function buildPrefillLineReviewsFromSnapshot(
  checkLines: ShellForwardCheckLine[],
  snapshot: CrateCheckSnapshot | undefined,
  historyReplenishByKey: Record<string, boolean>,
): Record<string, ShellForwardLineReview> {
  const byKey = new Map<string, Record<string, unknown>>()
  if (snapshot) {
    for (const h of snapshot.lines) {
      const k = String(h.line_key ?? '').trim()
      if (k) byKey.set(k, h)
    }
  }

  const out: Record<string, ShellForwardLineReview> = {}
  for (const line of checkLines) {
    const h = byKey.get(line.key)
    if (!h) {
      out[line.key] = defaultLineReview(line.quantity)
      continue
    }

    const histStatus = String(h.status ?? 'ok').trim()
    const counted = countedQtyForDisplay(h)
    const soll = Math.max(0, Math.floor(Number(h.expected_qty ?? line.quantity) || 0))
    const missingQty = Math.max(0, Math.floor(Number(h.missing_qty ?? 0) || 0)) || null
    const replenishQty = Math.max(0, Math.floor(Number(h.replenish_qty ?? 0) || 0)) || null
    const note = String(h.note ?? '').trim()

    if (historyReplenishByKey[line.key]) {
      out[line.key] = applyCountedQtyToReview(
        {
          ...defaultLineReview(soll),
          countedQty: counted,
          status: 'ok',
          resolution: null,
          note,
        },
        soll,
        line.isExtra,
      )
      continue
    }

    const skipResolutionPrefill =
      histStatus === 'loss' || histStatus === 'repair' || histStatus === 'not_taken'

    let review = defaultLineReview(soll)
    review.countedQty = counted

    if (histStatus === 'ok' && counted === soll) {
      review = applyCountedQtyToReview({ ...review, status: 'ok', resolution: null }, soll, line.isExtra)
    } else if (histStatus === 'extra' || counted > soll) {
      review = applyCountedQtyToReview(
        { ...review, status: 'problem', resolution: 'extra', note },
        soll,
        true,
      )
    } else if (counted < soll || histStatus === 'loss' || histStatus === 'problem') {
      review = applyCountedQtyToReview(
        {
          ...review,
          status: 'problem',
          resolution: skipResolutionPrefill ? null : historyResolutionFromStatus(histStatus, line.isExtra),
          missingQty: missingQty ?? Math.max(0, soll - counted),
          note,
          doReplenishAfterLoss: histStatus === 'loss' && replenishQty != null && replenishQty > 0,
          replenishQty,
        },
        soll,
        line.isExtra,
      )
    } else {
      review = applyCountedQtyToReview({ ...review, note }, soll, line.isExtra)
    }

    out[line.key] = review
  }
  return out
}
