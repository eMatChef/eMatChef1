import type { PackCrateCheckLineStatus } from '@/api/activityPackCrateCheck'
import {
  buildLineOverlaysFromCrateCheck,
  countedQtyForDisplay,
  type CrateCheckSnapshot,
} from '@/components/activities/packCrateCheckReality'
import {
  applyCountedQtyToReview,
  applyGroupAutoResolution,
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

/** Gruppe/Leiter: Vorausfüllung aus letztem Check + «noch korrekt?»-Hinweis. */
export function buildGroupPrefillLineReviewsFromSnapshot(
  checkLines: ShellForwardCheckLine[],
  snapshot: CrateCheckSnapshot | undefined,
): { reviews: Record<string, ShellForwardLineReview>; replenishByKey: Record<string, boolean> } {
  const replenishByKey = buildHistoryReplenishByKeyFromSnapshot(snapshot)
  const raw = buildPrefillLineReviewsFromSnapshot(checkLines, snapshot, replenishByKey)
  const reviews: Record<string, ShellForwardLineReview> = {}
  for (const line of checkLines) {
    let review = raw[line.key] ?? defaultLineReview(line.quantity)
    if (review.status === null && review.countedQty !== line.quantity) {
      review = applyCountedQtyToReview(review, line.quantity, line.isExtra)
    }
    review = applyGroupAutoResolution(review, line.quantity)
    reviews[line.key] = review
  }
  return { reviews, replenishByKey }
}

export function formatGroupCrateCheckPrefillHint(
  snapshot: CrateCheckSnapshot | undefined,
  t: (key: string, params?: Record<string, unknown>) => string,
): string | null {
  if (!snapshot) return null
  const overlays = buildLineOverlaysFromCrateCheck(snapshot)
  const gaps = overlays.filter((o) => o.countedQty < o.sollQty)
  if (gaps.length === 0) return null
  if (gaps.length === 1) {
    const o = gaps[0]!
    return t('activities.packList.shellForwardGroupPrefillHintOne', {
      name: o.materialName,
      counted: o.countedQty,
      soll: o.sollQty,
      missing: o.sollQty - o.countedQty,
    })
  }
  const lines = gaps
    .map((o) =>
      t('activities.packList.shellForwardGroupPrefillLineShort', {
        name: o.materialName,
        counted: o.countedQty,
        soll: o.sollQty,
        missing: o.sollQty - o.countedQty,
      }),
    )
    .join('; ')
  return t('activities.packList.shellForwardGroupPrefillHintMany', { lines })
}
