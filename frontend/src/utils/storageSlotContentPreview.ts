import type { StorageSlotContent } from '@/api/storageLocations'

export type SlotContentLike = Pick<
  StorageSlotContent,
  'material_name' | 'qty' | 'container_batch_id' | 'container_label'
>

/** Nur Materialmengen, z. B. für Kisten-Inhalt ohne weiteren Kontext */
export function summarizeMaterialsForPreview(
  items: Array<{ material_name: string; qty: number }>,
  maxItems = 5
): string {
  if (!items.length) return 'Leer'
  const merged = new Map<string, number>()
  for (const it of items) {
    const name = (it.material_name || 'Material').trim() || 'Material'
    merged.set(name, (merged.get(name) || 0) + Number(it.qty || 0))
  }
  const entries = Array.from(merged.entries())
  const shown = entries.slice(0, maxItems).map(([n, q]) => `${n} (${q})`)
  const extra = entries.length > maxItems ? ` +${entries.length - maxItems} weitere` : ''
  return shown.join(', ') + extra
}

function labelForContainerRow(
  cid: string,
  sample: SlotContentLike | undefined,
  resolveContainerLabel?: (containerBatchId: string) => string
): string {
  const fromRow = (sample?.container_label || '').trim()
  if (fromRow) return fromRow
  if (resolveContainerLabel) {
    const r = (resolveContainerLabel(cid) || '').trim()
    if (r) return r
  }
  return 'Kiste'
}

/**
 * Nur für das **Fach**-Dropdown (Gestell/Fach): hier ist keine Kiste wählbar — nur Materialien,
 * nicht „Kiste XY · …“.
 */
export function formatFachSelectPreviewLine(contents: SlotContentLike[]): string {
  if (!contents.length) return 'Leer'
  const items = contents.map((c) => ({
    material_name: (c.material_name || 'Material').trim() || 'Material',
    qty: Number(c.qty || 0),
  }))
  return summarizeMaterialsForPreview(items)
}

/**
 * Eine Zeile pro Fach: mit Kiste zuerst Label, dann Inhalt; ohne Kiste nur die gelagerten Sachen.
 * (Nur für Gestell-Übersicht / Kontext, in dem Kisten-Information Sinn ergibt.)
 * Mehrere Kisten im Fach: mit " | " getrennt.
 */
export function formatSlotStoragePreviewLine(
  contents: SlotContentLike[],
  resolveContainerLabel?: (containerBatchId: string) => string
): string {
  if (!contents.length) return 'Leer'

  const byContainer = new Map<string, SlotContentLike[]>()
  const direct: Array<{ material_name: string; qty: number }> = []

  for (const c of contents) {
    const cid = (c.container_batch_id || '').trim()
    const row = {
      material_name: (c.material_name || 'Material').trim() || 'Material',
      qty: Number(c.qty || 0),
    }
    if (!cid) {
      direct.push(row)
    } else {
      if (!byContainer.has(cid)) byContainer.set(cid, [])
      byContainer.get(cid)!.push(c)
    }
  }

  const parts: string[] = []

  for (const [cid, rows] of byContainer) {
    const sample = rows[0]
    const label = labelForContainerRow(cid, sample, resolveContainerLabel)
    const materialRows = rows.map((c) => ({
      material_name: (c.material_name || 'Material').trim() || 'Material',
      qty: Number(c.qty || 0),
    }))
    const inner = summarizeMaterialsForPreview(materialRows)
    if (inner === 'Leer') {
      parts.push(label)
    } else {
      parts.push(`${label} · ${inner}`)
    }
  }

  if (direct.length) {
    parts.push(summarizeMaterialsForPreview(direct))
  }

  return parts.join(' | ') || 'Leer'
}

/**
 * Gestell-Übersicht: pro belegtem Fach eine Zeile „Fachname · …“ (Kiste+Inhalt oder nur Material).
 */
export function formatRackSlotsDirectPreview(
  slots: Array<{ name: string; contents: SlotContentLike[] }>,
  resolveContainerLabel?: (containerBatchId: string) => string
): string {
  const lines: string[] = []
  for (const slot of slots) {
    const inner = formatSlotStoragePreviewLine(slot.contents || [], resolveContainerLabel)
    if (inner === 'Leer') continue
    const slotName = (slot.name || '').trim() || 'Fach'
    lines.push(`${slotName} · ${inner}`)
  }
  return lines.join('\n')
}
