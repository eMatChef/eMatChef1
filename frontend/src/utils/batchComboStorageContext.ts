import type { MaterialBatch } from '@/api/materials'
import type {
  MaterialStorageLocationRow,
  MaterialStorageLocationsResponse,
} from '@/api/materials'
import type { ContainerBatch } from '@/api/storageLocations'
import { formatStorageRowLabel } from '@/utils/compositionStockLocations'

export interface BatchComboStorageContext {
  parentMaterialId: string
  parentName: string
  containerBatchId: string | null
  containerDisplayName: string | null
  rackId: string | null
  slotId: string | null
  storageAddressName: string | null
  locationLabel: string
  storedInLinkedContainer: boolean
}

function pickComboLocationRow(
  locations: MaterialStorageLocationRow[] | undefined,
  linkedContainerBatchId: string | null,
): MaterialStorageLocationRow | null {
  const rows = (locations ?? []).filter((r) => (r.qty || 0) > 0)
  if (!rows.length) return null

  if (linkedContainerBatchId) {
    const inLinkedCrate = rows.find(
      (r) =>
        r.container_batch_id === linkedContainerBatchId &&
        (r.rack_id || r.location_label || r.rack_name),
    )
    if (inLinkedCrate) return inLinkedCrate
  }

  return (
    rows.find((r) => r.rack_id || r.location_label || r.rack_name) ?? rows[0]
  )
}

function resolveLinkedContainerDisplayName(
  linkedContainerBatchId: string | null,
  batch: MaterialBatch,
  containerBatches: ContainerBatch[],
): string | null {
  if (!linkedContainerBatchId) return null

  const fromList = containerBatches.find((c) => c.id === linkedContainerBatchId)
  if (fromList) {
    return (fromList.material_name || fromList.label || '').trim() || null
  }

  for (const alloc of batch.allocations ?? []) {
    if (alloc.container_batch_id !== linkedContainerBatchId) continue
    const cb = alloc.container_batch
    if (!cb) continue
    const name = (cb.material_name || cb.label || '').trim()
    if (name) return name
  }

  return null
}

function buildContext(
  parentMaterialId: string,
  parentName: string,
  linkedContainerBatchId: string | null,
  containerDisplayName: string | null,
  loc: MaterialStorageLocationRow | null,
  storedInLinkedContainer: boolean,
): BatchComboStorageContext | null {
  if (!parentMaterialId.trim()) return null

  const locationLabel = loc ? formatStorageRowLabel(loc) : ''
  return {
    parentMaterialId,
    parentName: parentName.trim() || '–',
    containerBatchId: linkedContainerBatchId,
    containerDisplayName,
    rackId: loc?.rack_id ?? null,
    slotId: loc?.slot_id ?? null,
    storageAddressName: (loc?.storage_address_name || '').trim() || null,
    locationLabel: locationLabel !== '–' ? locationLabel : '',
    storedInLinkedContainer,
  }
}

/** Lagerort & Kombi-Bezug für Chargen, die in einer physischen Kombi (Referenz-Sack) liegen. */
export function resolveBatchComboStorageContext(
  batch: MaterialBatch,
  storageLocations: MaterialStorageLocationsResponse | null,
  containerBatches: ContainerBatch[] = [],
): BatchComboStorageContext | null {
  const batchId = String(batch.id)

  for (const block of storageLocations?.via_physical_combo ?? []) {
    const compBatchId = (block.component_batch_id || '').trim()
    if (!compBatchId || compBatchId !== batchId) continue

    const linkedId = (block.parent_linked_container_batch_id || '').trim() || null
    const loc = pickComboLocationRow(block.locations, linkedId)
    const containerName = resolveLinkedContainerDisplayName(
      linkedId,
      batch,
      containerBatches,
    )

    return buildContext(
      block.parent_material_id,
      block.parent_name,
      linkedId,
      containerName,
      loc,
      !!linkedId,
    )
  }

  const containerAlloc = (batch.allocations ?? []).find(
    (a) => (a.qty || 0) > 0 && a.container_batch_id,
  )
  if (!containerAlloc?.container_batch_id) return null

  const cb = containerAlloc.container_batch
  const rackId =
    containerAlloc.rack_id || cb?.rack?.id || null
  const slotId =
    containerAlloc.slot_id || cb?.slot?.id || null
  const containerName =
    (cb?.material_name || cb?.label || '').trim() || null

  if (!rackId && !containerName) return null

  const loc: MaterialStorageLocationRow = {
    rack_id: rackId,
    slot_id: slotId,
    rack_name: cb?.rack?.name ?? containerAlloc.rack?.name ?? null,
    slot_name: cb?.slot?.name ?? containerAlloc.slot?.name ?? null,
    storage_address_name:
      cb?.storage_address_name ?? containerAlloc.storage_address_name ?? null,
    location_label: null,
    qty: containerAlloc.qty,
    batch_id: batchId,
    container_batch_id: containerAlloc.container_batch_id,
    container_caption: containerName,
    container_material_name: cb?.material_name ?? null,
  }

  return buildContext(
    '',
    '',
    containerAlloc.container_batch_id,
    containerName,
    loc,
    true,
  )
}
