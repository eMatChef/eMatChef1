import type { ComposerTranslation } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
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

function linesFromPeekSections(
  sections: {
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
    for (const line of sec.lines) {
      out.push({
        id: line.id,
        name: line.materialName,
        quantity: line.quantity,
        materialItemId: line.materialItemId ?? null,
        issuedQty: line.quantity,
      })
    }
  }
  return out
}

function mergeAccordionLines(
  ...groups: MaterialJourneyAccordionLine[][]
): MaterialJourneyAccordionLine[] {
  const map = new Map<string, MaterialJourneyAccordionLine>()
  for (const group of groups) {
    for (const line of group) {
      const key = line.id || line.name
      const existing = map.get(key)
      if (!existing || line.quantity > existing.quantity) {
        map.set(key, line)
      }
    }
  }
  return [...map.values()].sort((a, b) =>
    a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }),
  )
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
  if (row.kind === 'crate' && row.container) {
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

    return mergeAccordionLines(booked, peekLines)
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
