import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'

export type PackContainerItemSection = {
  subsectionKey: string
  title: string
  lines: ActivityPackContainerItem[]
}

export function packShellContainerForPackItem(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
): ActivityPackContainer | undefined {
  const mid = pi.materialItemId
  const linkBatch = (pi.linkedContainerBatchId ?? '').trim()
  for (const c of packContainers) {
    if (c.container_material_item_id === mid) return c
    if (linkBatch && c.container_batch_id === linkBatch) return c
  }
  return undefined
}

export function isCrateShellPackItem(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
): boolean {
  if (pi.materialType !== 'physical_combo') return false
  if ((pi.linkedContainerLabel ?? '').trim() !== '') return true
  if ((pi.linkedContainerBatchId ?? '').trim() !== '') return true
  return packShellContainerForPackItem(pi, packContainers) != null
}

export function packContainerItemSections(
  containerId: string,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  warehouseFixedMids: Set<string> | undefined,
  titles: { fixed: string; extra: string; all: string },
): PackContainerItemSection[] {
  const rows = containerItemsByContainerId[containerId] ?? []
  if (rows.length === 0) return []
  if (!warehouseFixedMids || warehouseFixedMids.size === 0) {
    return [{ subsectionKey: 'all', title: titles.all, lines: rows }]
  }
  const fixed: ActivityPackContainerItem[] = []
  const extra: ActivityPackContainerItem[] = []
  for (const ci of rows) {
    const mid = (ci.material_item_id ?? '').trim()
    if (mid && warehouseFixedMids.has(mid)) fixed.push(ci)
    else extra.push(ci)
  }
  const out: PackContainerItemSection[] = []
  if (fixed.length > 0) {
    out.push({ subsectionKey: 'fixed', title: titles.fixed, lines: fixed })
  }
  if (extra.length > 0) {
    out.push({ subsectionKey: 'extra', title: titles.extra, lines: extra })
  }
  if (out.length === 0) {
    return [{ subsectionKey: 'all', title: titles.all, lines: rows }]
  }
  return out
}

export function crateShellForwardPeekSections(
  pi: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  warehouseFixedMids: Set<string> | undefined,
  titles: { fixed: string; extra: string; all: string },
  materialFallback: string,
): PackCrateShellPeekSection[] {
  const c = packShellContainerForPackItem(pi, packContainers)
  if (!c) return []
  return packContainerItemSections(c.id, containerItemsByContainerId, warehouseFixedMids, titles).map(
    (sec) => ({
      subsectionKey: sec.subsectionKey,
      title: sec.title,
      lines: sec.lines.map((ci) => ({
        id: ci.id,
        materialName:
          (ci.material_name && String(ci.material_name).trim()) || materialFallback,
        quantity: ci.quantity_packed ?? 0,
        materialItemId: ci.material_item_id ?? null,
      })),
    }),
  )
}
