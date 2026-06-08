import type { Material, MaterialBatch, BatchStorageAllocation } from '@/api/materials'
import type { MaterialStorageLocationRow } from '@/api/materials'
import type { ContainerBatch } from '@/api/storageLocations'
import { getMaterials, getMaterialStorageLocations } from '@/api/materials'

export interface StockSourceLine {
  key: string
  label: string
  qtyAvailable: number
  kind: 'loose' | 'container'
  rackId: string | null
  slotId: string | null
  containerBatchId: string | null
}

export interface TakePreviewLine {
  label: string
  qty: number
}

export interface TakePreviewResult {
  lines: TakePreviewLine[]
  toLabel: string
  remaining: number
}

export interface StorageTargetSuggestion {
  id: string
  label: string
  hint: string
  targetMode: 'slot' | 'kiste'
  rackId: string | null
  slotId: string | null
  containerBatchId: string | null
  storageAddressId: string | null
}

export function resolveContainerDisplayName(row: MaterialStorageLocationRow): string | null {
  if (!row.container_batch_id && !row.container_caption) return null
  const materialName = (row.container_material_name || '').trim()
  if (materialName) return materialName
  return (row.container_caption || '').trim() || null
}

export function formatStorageRowLabel(row: MaterialStorageLocationRow): string {
  const addr = (row.storage_address_name || '').trim()
  const loc = (row.location_label || '').trim()
  const rack = (row.rack_name || '').trim()
  const slot = (row.slot_name || '').trim()
  const place = loc || (rack ? (slot ? `${rack} / ${slot}` : rack) : slot)
  const containerName = resolveContainerDisplayName(row)
  const parts: string[] = []
  if (addr) parts.push(addr)
  if (place) parts.push(place)
  if (containerName) parts.push(`Kiste «${containerName}»`)
  return parts.length ? parts.join(' · ') : '–'
}

function allocationLabel(
  alloc: BatchStorageAllocation,
  containerBatches: ContainerBatch[],
): { label: string; kind: 'loose' | 'container'; containerBatchId: string | null } {
  const cbId = alloc.container_batch_id || null
  if (cbId) {
    const fromAlloc = alloc.container_batch
    const fromList = containerBatches.find((c) => c.id === cbId)
    const materialName = (fromAlloc?.material_name || fromList?.material_name || '').trim()
    const containerName =
      materialName ||
      (fromAlloc?.label || fromList?.label || '').trim() ||
      (fromAlloc?.serial_number || fromList?.serial_number || '').trim() ||
      'Kiste'
    const rack = fromAlloc?.rack?.name || fromList?.rack?.name
    const slot = fromAlloc?.slot?.name || fromList?.slot?.name
    const addr = (fromAlloc?.storage_address_name || '').trim()
    const parts: string[] = []
    if (addr) parts.push(addr)
    if (rack) parts.push(slot ? `${rack} / ${slot}` : rack)
    else if (slot) parts.push(slot)
    parts.push(`Kiste «${containerName}»`)
    return { label: parts.join(' · '), kind: 'container', containerBatchId: cbId }
  }
  const addr = (alloc.storage_address_name || '').trim()
  const rack = (alloc.rack?.name || alloc.rack_name || '').trim()
  const slot = (alloc.slot?.name || alloc.slot_name || '').trim()
  const parts: string[] = []
  if (addr) parts.push(addr)
  if (rack) parts.push(slot ? `${rack} / ${slot}` : rack)
  else if (slot) parts.push(slot)
  return { label: parts.length ? parts.join(' · ') : 'Regal/Fach', kind: 'loose', containerBatchId: null }
}

/** Sammelt verfügbare Quellen in Backend-Reihenfolge (lose, dann andere Kisten). */
export function collectStockSources(
  material: Material,
  targetContainerBatchId: string | null,
  containerBatches: ContainerBatch[] = [],
): StockSourceLine[] {
  const looseMap = new Map<string, StockSourceLine>()
  const containerMap = new Map<string, StockSourceLine>()
  const batches = [...(material.batches || [])]
    .filter((b) => b.status === 'active' && (b.qty || 0) > 0)
    .sort((a, b) => String(a.acquired_on || '').localeCompare(String(b.acquired_on || '')) || String(a.id).localeCompare(String(b.id)))

  for (const batch of batches) {
    const allocs = batch.allocations
    if (allocs && allocs.length > 0) {
      for (const alloc of allocs) {
        if ((alloc.qty || 0) <= 0) continue
        const cbId = alloc.container_batch_id || null
        if (cbId && cbId === targetContainerBatchId) continue
        const meta = allocationLabel(alloc, containerBatches)
        const map = meta.kind === 'loose' ? looseMap : containerMap
        const key =
          meta.kind === 'loose'
            ? `loose:${alloc.rack_id || ''}:${alloc.slot_id || ''}`
            : `crate:${cbId}`
        const existing = map.get(key)
        if (existing) {
          existing.qtyAvailable += alloc.qty
        } else {
          map.set(key, {
            key,
            label: meta.label,
            qtyAvailable: alloc.qty,
            kind: meta.kind,
            rackId: meta.kind === 'loose' ? alloc.rack_id || null : null,
            slotId: meta.kind === 'loose' ? alloc.slot_id || null : null,
            containerBatchId: meta.containerBatchId,
          })
        }
      }
    } else if (batch.rack_id && !batch.allocations?.length) {
      const key = `loose:${batch.rack_id}:${batch.slot_id || ''}`
      const addr = (batch.storage_address_name || '').trim()
      const rack = (batch.rack?.name || '').trim()
      const slot = (batch.slot?.name || '').trim()
      const parts: string[] = []
      if (addr) parts.push(addr)
      if (rack) parts.push(slot ? `${rack} / ${slot}` : rack)
      const existing = looseMap.get(key)
      if (existing) {
        existing.qtyAvailable += batch.qty
      } else {
        looseMap.set(key, {
          key,
          label: parts.length ? parts.join(' · ') : 'Regal/Fach',
          qtyAvailable: batch.qty,
          kind: 'loose',
          rackId: batch.rack_id,
          slotId: batch.slot_id || null,
          containerBatchId: null,
        })
      }
    }
  }

  return [...looseMap.values(), ...containerMap.values()]
}

export function previewTakeForLinkedCrate(
  material: Material,
  targetContainerBatchId: string | null,
  targetContainerLabel: string,
  qtyNeeded: number,
  containerBatches: ContainerBatch[] = [],
): TakePreviewResult {
  const sources = collectStockSources(material, targetContainerBatchId, containerBatches)
  let remaining = Math.max(0, qtyNeeded)
  const lines: TakePreviewLine[] = []

  for (const src of sources) {
    if (remaining <= 0) break
    const take = Math.min(src.qtyAvailable, remaining)
    if (take <= 0) continue
    lines.push({ label: src.label, qty: take })
    remaining -= take
  }

  return {
    lines,
    toLabel: targetContainerLabel,
    remaining,
  }
}

function searchTokenFromName(name: string): string {
  const words = name
    .trim()
    .split(/\s+/)
    .map((w) => w.replace(/[^a-zA-ZäöüÄÖÜß0-9]/g, ''))
    .filter((w) => w.length >= 3)
  if (words.length === 0) return name.trim().slice(0, 20)
  words.sort((a, b) => b.length - a.length)
  return words[0]
}

function resolveContainerBatchId(
  row: MaterialStorageLocationRow,
  containerBatches: ContainerBatch[],
): string | null {
  const caption = (row.container_caption || '').trim()
  if (!caption) return null
  const hit = containerBatches.find(
    (c) =>
      (c.label || '').trim() === caption ||
      (c.serial_number || '').trim() === caption ||
      `${c.serial_number || ''} – ${c.label || ''}`.includes(caption),
  )
  return hit?.id || null
}

function rowToSuggestion(
  row: MaterialStorageLocationRow,
  containerBatches: ContainerBatch[],
  hintPrefix: string,
  racks: { id: string; storage_address_id?: string | null }[],
): StorageTargetSuggestion | null {
  const qty = row.qty || 0
  if (qty <= 0) return null
  const label = formatStorageRowLabel(row)
  const containerId = resolveContainerBatchId(row, containerBatches)
  if (containerId) {
    return {
      id: `crate:${containerId}`,
      label,
      hint: hintPrefix,
      targetMode: 'kiste',
      rackId: null,
      slotId: null,
      containerBatchId: containerId,
      storageAddressId: null,
    }
  }
  if (!row.rack_id) return null
  const rack = racks.find((r) => r.id === row.rack_id)
  return {
    id: `slot:${row.rack_id}:${row.slot_id || ''}`,
    label,
    hint: hintPrefix,
    targetMode: 'slot',
    rackId: row.rack_id,
    slotId: row.slot_id || null,
    containerBatchId: null,
    storageAddressId: rack?.storage_address_id || null,
  }
}

/** Lager-Vorschläge: eigene andere Plätze + ähnlich benannte Artikel. */
export async function loadStorageTargetSuggestions(options: {
  departmentId: string
  materialId: string
  materialName: string
  excludeContainerBatchId: string | null
  containerBatches: ContainerBatch[]
  racks: { id: string; storage_address_id?: string | null }[]
  maxSimilarMaterials?: number
}): Promise<StorageTargetSuggestion[]> {
  const {
    departmentId,
    materialId,
    materialName,
    excludeContainerBatchId,
    containerBatches,
    racks,
    maxSimilarMaterials = 5,
  } = options

  const seen = new Set<string>()
  const out: StorageTargetSuggestion[] = []

  const push = (s: StorageTargetSuggestion | null) => {
    if (!s || seen.has(s.id)) return
    if (s.containerBatchId && s.containerBatchId === excludeContainerBatchId) return
    seen.add(s.id)
    out.push(s)
  }

  try {
    const own = await getMaterialStorageLocations(materialId, departmentId)
    for (const row of own.direct || []) {
      if (excludeContainerBatchId && row.container_caption) {
        const cid = resolveContainerBatchId(row, containerBatches)
        if (cid === excludeContainerBatchId) continue
      }
      push(rowToSuggestion(row, containerBatches, 'Anderer Platz dieses Artikels', racks))
    }
  } catch {
    /* ignore */
  }

  const token = searchTokenFromName(materialName)
  if (token.length >= 2) {
    try {
      const hits = await getMaterials(departmentId, { search: token })
      const similar = hits
        .filter((m) => m.id !== materialId && (m.total_stock || 0) > 0)
        .slice(0, maxSimilarMaterials)
      for (const m of similar) {
        try {
          const locs = await getMaterialStorageLocations(m.id, departmentId)
          for (const row of locs.direct || []) {
            push(
              rowToSuggestion(
                row,
                containerBatches,
                `Gleichartig: «${m.name}»`,
                racks,
              ),
            )
          }
        } catch {
          /* ignore */
        }
      }
    } catch {
      /* ignore */
    }
  }

  return out.slice(0, 12)
}
