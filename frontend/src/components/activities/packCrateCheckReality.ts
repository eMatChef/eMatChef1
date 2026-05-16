import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { PackCrateShellPeekLine, PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'

export const CRATE_CHECK_DISPLAY_LINE_PREFIX = 'crate-check-'

export type CrateCheckLineOverlay = {
  lineKey: string
  materialItemId: string | null
  materialName: string
  countedQty: number
  sollQty: number
  status: string
  replenishQty: number
  subsectionKey: string
  note: string
}

export type CrateCheckSnapshot = {
  lines: Array<Record<string, unknown>>
  actions_applied: Array<Record<string, unknown>>
  created_at: string
}

export function isCrateCheckDisplayLine(ci: { id: string }): boolean {
  return ci.id.startsWith(CRATE_CHECK_DISPLAY_LINE_PREFIX)
}

export function parseCountedQtyFromHistoryLine(h: Record<string, unknown>): number | null {
  const raw = h.counted_qty
  if (raw != null && Number.isFinite(Number(raw))) {
    return Math.max(0, Math.floor(Number(raw)))
  }
  const note = String(h.note ?? '')
  const m = note.match(/Ist\s+(\d+)\s*\/\s*Soll\s+(\d+)/i)
  if (m) return Math.max(0, parseInt(m[1]!, 10) || 0)
  if (String(h.status ?? '').trim() === 'ok') {
    const eq = h.expected_qty
    if (eq != null && Number.isFinite(Number(eq))) return Math.max(0, Math.floor(Number(eq)))
  }
  return null
}

export function countedQtyForDisplay(h: Record<string, unknown>): number {
  const counted = parseCountedQtyFromHistoryLine(h)
  if (counted != null) return counted
  return Math.max(0, Math.floor(Number(h.expected_qty ?? 0) || 0))
}

function subsectionKeyFromLineKey(lineKey: string): string {
  const i = lineKey.indexOf(':')
  if (i <= 0) return 'all'
  return lineKey.slice(0, i)
}

function effectiveSubsectionKey(h: Record<string, unknown>): string {
  const status = String(h.status ?? '').trim()
  if (status === 'extra') return 'extra'
  return subsectionKeyFromLineKey(String(h.line_key ?? ''))
}

export function indexLatestCrateCheckByPackItemId(
  history: Array<{ action: string; created_at: string; changes?: Record<string, unknown> }>,
): Record<string, CrateCheckSnapshot> {
  const sorted = [...history]
    .filter((e) => e.action === 'pack_crate_check')
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
  const out: Record<string, CrateCheckSnapshot> = {}
  for (const e of sorted) {
    const pid = e.changes?.pack_item_id
    if (typeof pid !== 'string' || pid === '' || out[pid]) continue
    const lines = e.changes?.lines
    if (!Array.isArray(lines) || lines.length === 0) continue
    const actions = e.changes?.actions_applied
    out[pid] = {
      lines: lines as Array<Record<string, unknown>>,
      actions_applied: Array.isArray(actions) ? (actions as Array<Record<string, unknown>>) : [],
      created_at: e.created_at,
    }
  }
  return out
}

/** @deprecated use indexLatestCrateCheckByPackItemId */
export function indexLatestCrateCheckLinesByPackItemId(
  history: Array<{ action: string; created_at: string; changes?: Record<string, unknown> }>,
): Record<string, Array<Record<string, unknown>>> {
  const snaps = indexLatestCrateCheckByPackItemId(history)
  const out: Record<string, Array<Record<string, unknown>>> = {}
  for (const [pid, snap] of Object.entries(snaps)) {
    out[pid] = snap.lines
  }
  return out
}

export function buildLineOverlaysFromCrateCheck(snapshot: CrateCheckSnapshot): CrateCheckLineOverlay[] {
  const actions = snapshot.actions_applied ?? []
  const overlays: CrateCheckLineOverlay[] = []

  for (const h of snapshot.lines) {
    const lineKey = String(h.line_key ?? '').trim()
    if (!lineKey) continue
    const status = String(h.status ?? 'ok').trim()
    const sollQty = Math.max(0, Math.floor(Number(h.expected_qty ?? 0) || 0))
    const countedQty = countedQtyForDisplay(h)
    let replenishQty = Math.max(0, Math.floor(Number(h.replenish_qty ?? 0) || 0))
    const actionForLine = actions.filter((a) => String(a.line_key ?? '') === lineKey)
    for (const a of actionForLine) {
      const st = String(a.status ?? '').trim()
      if (st === 'replenish' || st === 'replenish_after_loss') {
        replenishQty = Math.max(
          replenishQty,
          Math.floor(Number(a.qty_moved ?? a.replenish_qty ?? 0) || 0),
        )
      }
    }
    overlays.push({
      lineKey,
      materialItemId: String(h.material_item_id ?? '').trim() || null,
      materialName: String(h.material_name ?? '').trim() || 'Material',
      countedQty,
      sollQty,
      status,
      replenishQty,
      subsectionKey: effectiveSubsectionKey(h),
      note: String(h.note ?? '').trim(),
    })
  }
  return overlays
}

export function displayQtyInCrateAfterCheck(overlay: CrateCheckLineOverlay): number {
  if (overlay.replenishQty > 0 && (overlay.status === 'loss' || overlay.status === 'replenish')) {
    return overlay.countedQty + overlay.replenishQty
  }
  return overlay.countedQty
}

export function overlayToPeekLine(overlay: CrateCheckLineOverlay): PackCrateShellPeekLine {
  const id = overlay.lineKey.includes(':') ? overlay.lineKey.slice(overlay.lineKey.indexOf(':') + 1) : overlay.lineKey
  let checkStatus = overlay.status
  if (overlay.replenishQty > 0 && overlay.status === 'loss') {
    checkStatus = 'replenish_after_loss'
  }
  return {
    id,
    materialName: overlay.materialName,
    quantity: displayQtyInCrateAfterCheck(overlay),
    materialItemId: overlay.materialItemId,
    checkStatus,
    sollQty: overlay.sollQty,
    countedQty: overlay.countedQty,
    replenishQty: overlay.replenishQty,
  }
}

export function peekSectionsFromCheckOverlays(
  overlays: CrateCheckLineOverlay[],
  titles: { all: string; fixed: string; extra: string },
): PackCrateShellPeekSection[] {
  const buckets = new Map<string, PackCrateShellPeekLine[]>()
  for (const o of overlays) {
    const list = buckets.get(o.subsectionKey) ?? []
    list.push(overlayToPeekLine(o))
    buckets.set(o.subsectionKey, list)
  }
  if (buckets.size === 0) return []
  const titleFor = (k: string) =>
    k === 'fixed' ? titles.fixed : k === 'extra' ? titles.extra : titles.all
  const rank = (k: string) => (k === 'fixed' ? 0 : k === 'extra' ? 1 : 2)
  return [...buckets.entries()]
    .sort((a, b) => rank(a[0]) - rank(b[0]))
    .map(([subsectionKey, lines]) => ({
      subsectionKey,
      title: titleFor(subsectionKey),
      lines: lines.sort((a, b) => a.materialName.localeCompare(b.materialName)),
    }))
}

/** @deprecated Prefer overlay on template sections */
export function peekSectionsFromCrateCheckHistory(
  histLines: Array<Record<string, unknown>>,
  titles: { all: string; fixed: string; extra: string },
): PackCrateShellPeekSection[] {
  return peekSectionsFromCheckOverlays(
    buildLineOverlaysFromCrateCheck({ lines: histLines, actions_applied: [], created_at: '' }),
    titles,
  )
}

export function overlayPeekSections(
  templateSections: PackCrateShellPeekSection[],
  overlays: CrateCheckLineOverlay[],
): PackCrateShellPeekSection[] {
  const byMid = new Map<string, CrateCheckLineOverlay>()
  const byLineKey = new Map<string, CrateCheckLineOverlay>()
  for (const o of overlays) {
    if (o.materialItemId) byMid.set(o.materialItemId, o)
    byLineKey.set(o.lineKey, o)
  }
  const usedMids = new Set<string>()

  const mapLine = (line: PackCrateShellPeekLine, secKey: string): PackCrateShellPeekLine => {
    const mid = (line.materialItemId ?? '').trim()
    const key = `${secKey}:${line.id}`
    const o = (mid && byMid.get(mid)) || byLineKey.get(key)
    if (!o) return line
    if (mid) usedMids.add(mid)
    return overlayToPeekLine(o)
  }

  const out: PackCrateShellPeekSection[] = templateSections.map((sec) => ({
    ...sec,
    lines: sec.lines.map((line) => mapLine(line, sec.subsectionKey)),
  }))

  appendMissingPeekOverlays(out, overlays, usedMids, 'fixed')
  appendMissingPeekOverlays(out, overlays, usedMids, 'extra')

  return out
}

function appendMissingPeekOverlays(
  out: PackCrateShellPeekSection[],
  overlays: CrateCheckLineOverlay[],
  usedMids: Set<string>,
  subsectionKey: string,
): void {
  const missing = overlays.filter(
    (o) =>
      o.subsectionKey === subsectionKey &&
      o.materialItemId &&
      !usedMids.has(o.materialItemId),
  )
  if (missing.length === 0) return
  for (const o of missing) usedMids.add(o.materialItemId!)
  const peekLines = missing.map(overlayToPeekLine)
  const sec = out.find((s) => s.subsectionKey === subsectionKey)
  if (sec) {
    sec.lines = [...sec.lines, ...peekLines]
  } else {
    out.push({ subsectionKey, title: subsectionKey, lines: peekLines })
  }
}

export function overlayContainerSections(
  templateSections: Array<{ subsectionKey: string; title: string; lines: ActivityPackContainerItem[] }>,
  c: ActivityPackContainer,
  overlays: CrateCheckLineOverlay[],
): Array<{ subsectionKey: string; title: string; lines: ActivityPackContainerItem[] }> {
  const byMid = new Map<string, CrateCheckLineOverlay>()
  for (const o of overlays) {
    if (o.materialItemId) byMid.set(o.materialItemId, o)
  }
  const usedMids = new Set<string>()

  const mapCi = (ci: ActivityPackContainerItem): ActivityPackContainerItem => {
    const mid = (ci.material_item_id ?? '').trim()
    const o = mid ? byMid.get(mid) : undefined
    if (!o || isCrateCheckDisplayLine(ci)) return ci
    usedMids.add(mid)
    const peek = overlayToPeekLine(o)
    return {
      ...ci,
      quantity_packed: peek.quantity ?? ci.quantity_packed,
      material_name: peek.materialName || ci.material_name,
      notes: peek.checkStatus && peek.checkStatus !== 'ok' ? peek.checkStatus : ci.notes,
    }
  }

  const out = templateSections.map((sec) => ({
    ...sec,
    lines: sec.lines.map(mapCi),
  }))

  appendMissingContainerOverlays(out, c, overlays, usedMids, 'fixed')
  appendMissingContainerOverlays(out, c, overlays, usedMids, 'extra')

  return out
}

function overlayToContainerItem(
  c: ActivityPackContainer,
  o: CrateCheckLineOverlay,
): ActivityPackContainerItem {
  return {
    id: `${CRATE_CHECK_DISPLAY_LINE_PREFIX}${o.lineKey}`,
    pack_container_id: c.id,
    material_item_id: o.materialItemId ?? '',
    material_batch_id: null,
    quantity_packed: displayQtyInCrateAfterCheck(o),
    quantity_issued: 0,
    quantity_returned: 0,
    condition_out: 'ok',
    notes: o.status,
    material_name: o.materialName,
  }
}

function appendMissingContainerOverlays(
  out: Array<{ subsectionKey: string; title: string; lines: ActivityPackContainerItem[] }>,
  c: ActivityPackContainer,
  overlays: CrateCheckLineOverlay[],
  usedMids: Set<string>,
  subsectionKey: string,
): void {
  const missing = overlays.filter(
    (o) =>
      o.subsectionKey === subsectionKey &&
      o.materialItemId &&
      !usedMids.has(o.materialItemId),
  )
  if (missing.length === 0) return
  const lines = missing.map((o) => overlayToContainerItem(c, o))
  const sec = out.find((s) => s.subsectionKey === subsectionKey)
  if (sec) {
    sec.lines = [...sec.lines, ...lines]
  } else {
    out.push({ subsectionKey, title: subsectionKey, lines })
  }
}

/** Nur Kistencheck — wenn noch keine Pack-Zeilen / Lager-Vorschau im Behälter */
export function containerSectionsFromCheckOverlaysOnly(
  c: ActivityPackContainer,
  overlays: CrateCheckLineOverlay[],
  titles: { all: string; fixed: string; extra: string },
): Array<{ subsectionKey: string; title: string; lines: ActivityPackContainerItem[] }> {
  const rank = (k: string) => (k === 'fixed' ? 0 : k === 'extra' ? 1 : 2)
  const buckets = new Map<string, ActivityPackContainerItem[]>()
  for (const o of overlays) {
    const list = buckets.get(o.subsectionKey) ?? []
    list.push(overlayToContainerItem(c, o))
    buckets.set(o.subsectionKey, list)
  }
  const titleFor = (k: string) =>
    k === 'fixed' ? titles.fixed : k === 'extra' ? titles.extra : titles.all
  return [...buckets.entries()]
    .sort((a, b) => rank(a[0]) - rank(b[0]))
    .map(([subsectionKey, lines]) => ({
      subsectionKey,
      title: titleFor(subsectionKey),
      lines,
    }))
}

export function summarizeCrateCheckActions(overlays: CrateCheckLineOverlay[]): {
  hasReplenish: boolean
  replenishLines: CrateCheckLineOverlay[]
  extraLines: CrateCheckLineOverlay[]
} {
  const replenishLines = overlays.filter((o) => o.replenishQty > 0)
  const extraLines = overlays.filter((o) => o.status === 'extra')
  return {
    hasReplenish: replenishLines.length > 0,
    replenishLines,
    extraLines,
  }
}

/** @deprecated */
export function containerSectionsFromCrateCheckHistory(
  c: ActivityPackContainer,
  histLines: Array<Record<string, unknown>>,
  titles: { all: string; fixed: string; extra: string },
): Array<{ subsectionKey: string; title: string; lines: ActivityPackContainerItem[] }> {
  const peek = peekSectionsFromCrateCheckHistory(histLines, titles)
  return peek.map((sec) => ({
    subsectionKey: sec.subsectionKey,
    title: sec.title,
    lines: sec.lines.map((line) => ({
      id: `${CRATE_CHECK_DISPLAY_LINE_PREFIX}${sec.subsectionKey}-${line.id}`,
      pack_container_id: c.id,
      material_item_id: line.materialItemId ?? '',
      material_batch_id: null,
      quantity_packed: line.quantity,
      quantity_issued: 0,
      quantity_returned: 0,
      condition_out: 'ok',
      notes: line.checkStatus && line.checkStatus !== 'ok' ? line.checkStatus : null,
      material_name: line.materialName,
    })),
  }))
}
