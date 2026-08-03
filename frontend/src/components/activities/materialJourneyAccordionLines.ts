import type { ComposerTranslation } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import { isMaterialJourneyCrateKind } from '@/components/activities/materialJourneyTaskList'
import {
  peekSectionsForJourneyCombo,
  peekSectionsForJourneyContainer,
} from '@/composables/useMaterialJourneyCrateSections'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'

export type MaterialJourneyAccordionLine = {
  id: string
  name: string
  quantity: number
  materialItemId?: string | null
  /** Am Anlass: quantity_issued der Zeile (Melden / Bestand). */
  issuedQty?: number
  /** Noch in der Kiste gepackt — verschiebbar per «In andere Packkiste». */
  maxReassignQty?: number
  /** Position aus activity_pack_container_item — «Lose mitnehmen» / «In andere Packkiste». */
  actionable?: boolean
  /** Nur Lager-Vorlage — nicht auf der Packliste gebucht. */
  isWarehouseTemplate?: boolean
}

function accordionQtyForContainerLine(item: ActivityPackContainerItem): number {
  const packed = item.quantity_packed ?? 0
  const transportTo = item.quantity_transport_to ?? 0
  const issued = item.quantity_issued ?? 0
  const transportBack = item.quantity_transport_back ?? 0
  const returned = item.quantity_returned ?? 0
  return Math.max(packed, transportTo, issued, transportBack, returned)
}

function linesFromContainerItems(
  items: ActivityPackContainerItem[],
): MaterialJourneyAccordionLine[] {
  return items
    .filter((item) => accordionQtyForContainerLine(item) > 0)
    .map((item) => ({
      id: item.id,
      name: (item.material_name ?? '').trim() || '—',
      quantity: accordionQtyForContainerLine(item),
      materialItemId: item.material_item_id,
      issuedQty: item.quantity_issued ?? 0,
      maxReassignQty: item.quantity_packed ?? 0,
      actionable: (item.quantity_packed ?? 0) > 0,
    }))
}

export function materialJourneyAccordionLinesForContainerId(
  containerId: string,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
): MaterialJourneyAccordionLine[] {
  return linesFromContainerItems(containerItemsByContainerId[containerId] ?? [])
}

function linesFromPeekSections(
  sections: {
    subsectionKey?: string
    lines: {
      id: string
      materialName: string
      quantity: number
      materialItemId?: string | null
    }[]
  }[],
): MaterialJourneyAccordionLine[] {
  const out: MaterialJourneyAccordionLine[] = []
  for (const sec of sections) {
    const isWarehouseTemplate = sec.subsectionKey === 'fixed'
    for (const line of sec.lines) {
      out.push({
        id: `peek-${sec.subsectionKey ?? 'all'}-${line.id}`,
        name: line.materialName,
        quantity: line.quantity,
        materialItemId: line.materialItemId ?? null,
        issuedQty: isWarehouseTemplate ? 0 : line.quantity,
        actionable: false,
        isWarehouseTemplate,
      })
    }
  }
  return out
}

export function materialJourneyAccordionLinesForRow(
  row: MaterialJourneyTaskRow,
  ctx: {
    containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
    cratePeekMaps: MaterialJourneyCratePeekMaps
    packItems: ActivityPackItem[]
    packContainers: ActivityPackContainer[]
    shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
    t: ComposerTranslation
  },
): MaterialJourneyAccordionLine[] {
  if (isMaterialJourneyCrateKind(row.kind) && row.container) {
    const containerItems = ctx.containerItemsByContainerId[row.container.id] ?? []
    const booked = linesFromContainerItems(containerItems)

    const shell = ctx.shellPackItemForContainer(row.container.id)
    const peekCtx = {
      containerItemsByContainerId: ctx.containerItemsByContainerId,
      ...ctx.cratePeekMaps,
    }
    const sections = peekSectionsForJourneyContainer(
      row.container,
      peekCtx,
      shell,
      ctx.t,
      ctx.packItems,
      ctx.packContainers,
    )
    const peekLines = linesFromPeekSections(sections)
    const bookedMids = new Set(
      booked.map((line) => (line.materialItemId ?? '').trim()).filter(Boolean),
    )

    const extraFromPeek = peekLines.filter((line) => {
      if (line.isWarehouseTemplate) return false
      const mid = (line.materialItemId ?? '').trim()
      return mid === '' || !bookedMids.has(mid)
    })
    const templateOnly = peekLines.filter(
      (line) =>
        line.isWarehouseTemplate &&
        (!(line.materialItemId ?? '').trim() || !bookedMids.has((line.materialItemId ?? '').trim())),
    )

    return [...booked, ...extraFromPeek, ...templateOnly].sort((a, b) =>
      a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }),
    )
  }

  if (row.kind === 'combo' && row.packItem) {
    const peekCtx = {
      containerItemsByContainerId: ctx.containerItemsByContainerId,
      ...ctx.cratePeekMaps,
    }
    const sections = peekSectionsForJourneyCombo(
      row.packItem,
      ctx.packContainers,
      peekCtx,
      ctx.t,
    )
    return linesFromPeekSections(sections)
  }

  return []
}
