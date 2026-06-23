import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { packMaterialDisplayName } from '@/components/activities/packMaterialDisplay'
import {
  isCrateShellPackItem,
  packShellContainerForPackItem,
} from '@/components/activities/packShellCrateHelpers'

export const IMPLICIT_SINGLE_TRIP_ID = '__implicit_single_trip__'

export type ImplicitTripLine = {
  id: string
  label: string
  kind: 'crate' | 'loose'
  pendingQty: number
}

/** Material mit quantity_transport_to > quantity_issued (noch nicht am Anlass). */
export function buildImplicitOutboundTripPendingLines(
  packItems: ActivityPackItem[],
  packContainers: ActivityPackContainer[],
): ImplicitTripLine[] {
  const lines: ImplicitTripLine[] = []
  const listedContainerIds = new Set<string>()

  for (const container of packContainers) {
    const shell = packItems.find((pi) => {
      const linked = packShellContainerForPackItem(pi, packContainers)
      return linked?.id === container.id
    })
    const pending = shell
      ? Math.max(0, (shell.quantityTransportTo ?? 0) - (shell.quantityIssued ?? 0))
      : 0
    if (pending < 1) continue
    lines.push({
      id: `crate-${container.id}`,
      label: container.label,
      kind: 'crate',
      pendingQty: pending,
    })
    listedContainerIds.add(container.id)
  }

  for (const pi of packItems) {
    if (isCrateShellPackItem(pi, packContainers)) {
      const linked = packShellContainerForPackItem(pi, packContainers)
      if (linked && listedContainerIds.has(linked.id)) continue
    }
    const pending = Math.max(0, (pi.quantityTransportTo ?? 0) - (pi.quantityIssued ?? 0))
    if (pending < 1) continue
    lines.push({
      id: `loose-${pi.id}`,
      label: packMaterialDisplayName(pi),
      kind: 'loose',
      pendingQty: pending,
    })
  }

  return lines
}

export function hasAnyOutboundTransport(packItems: ActivityPackItem[]): boolean {
  return packItems.some((pi) => (pi.quantityTransportTo ?? 0) > 0)
}
