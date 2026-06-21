import { type ComposerTranslation } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import {
  crateShellPeekSectionsForPackItem,
  isNonActionableContainerLine,
  peekSectionsForShellContainer,
  peekSectionsFromComboComponents,
  resolvePackContainerWarehouseBatchId,
} from '@/components/activities/packShellCrateHelpers'
import type { ComboComponent } from '@/api/materials'
import type { RackContentsItem } from '@/api/storageLocations'

export function materialJourneyPeekSectionTitles(t: ComposerTranslation) {
  return {
    all: t('activities.packList.shellForwardSectionAll'),
    fixed: t('activities.packList.shellForwardSectionFixed'),
    extra: t('activities.packList.shellForwardSectionExtra'),
  }
}

export type MaterialJourneyCratePeekContext = {
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  containerWarehouseTemplateByContainerId: Record<string, Set<string>>
  containerWarehouseContentsByContainerId: Record<string, RackContentsItem[]>
  comboComponentsByMaterialId: Record<string, ComboComponent[]>
  comboMaterialIdByContainerId?: Record<string, string>
}

export function comboComponentsForJourneyContainer(
  container: ActivityPackContainer,
  shellPackItem: ActivityPackItem | null | undefined,
  ctx: Pick<
    MaterialJourneyCratePeekContext,
    'comboComponentsByMaterialId' | 'comboMaterialIdByContainerId'
  >,
): ComboComponent[] {
  const candidates: string[] = []
  const resolved = (ctx.comboMaterialIdByContainerId?.[container.id] ?? '').trim()
  if (resolved && (ctx.comboComponentsByMaterialId[resolved] ?? []).length > 0) {
    candidates.push(resolved)
  }
  const shellMid = (shellPackItem?.materialItemId ?? '').trim()
  if (
    shellMid &&
    !candidates.includes(shellMid) &&
    (shellPackItem?.materialType === 'physical_combo' || (ctx.comboComponentsByMaterialId[shellMid] ?? []).length > 0)
  ) {
    candidates.push(shellMid)
  }
  const containerMid = (container.container_material_item_id ?? '').trim()
  if (containerMid && !candidates.includes(containerMid) && (ctx.comboComponentsByMaterialId[containerMid] ?? []).length > 0) {
    candidates.push(containerMid)
  }
  for (const mid of candidates) {
    const combo = ctx.comboComponentsByMaterialId[mid]
    if (combo?.length) return combo
  }
  return []
}

export function peekSectionsForJourneyContainer(
  container: ActivityPackContainer,
  ctx: MaterialJourneyCratePeekContext,
  shellPackItem: ActivityPackItem | null | undefined,
  t: ComposerTranslation,
  packItems: ActivityPackItem[] = [],
  packContainers: ActivityPackContainer[] = [],
): PackCrateShellPeekSection[] {
  const combo = comboComponentsForJourneyContainer(container, shellPackItem, ctx)

  const comboMaterialId =
    (ctx.comboMaterialIdByContainerId?.[container.id] ?? '').trim() ||
    (shellPackItem?.materialType === 'physical_combo' ? (shellPackItem.materialItemId ?? '').trim() : '') ||
    ''

  // Journey-Peek: linkedContainerBatchId nie setzen — sonst filtert linkedContainerComponentMaterialIds
  // die Stückliste fälschlich weg (0 Positionen bei Phys.-Kombi-Kisten).
  const shellForPeek: ActivityPackItem | null =
    combo.length > 0 || shellPackItem
      ? ({
          id: shellPackItem?.id ?? '',
          materialItemId: comboMaterialId || (shellPackItem?.materialItemId ?? '').trim(),
          linkedContainerBatchId: null,
          materialType: shellPackItem?.materialType ?? 'physical_combo',
        } as ActivityPackItem)
      : null

  return peekSectionsForShellContainer(
    container,
    ctx.containerItemsByContainerId,
    ctx.containerWarehouseTemplateByContainerId,
    ctx.containerWarehouseContentsByContainerId,
    combo,
    materialJourneyPeekSectionTitles(t),
    t('common.material'),
    shellPackItem?.id,
    {},
    false,
    shellForPeek,
  )
}

function countPeekSectionLines(sections: PackCrateShellPeekSection[]): number {
  return sections.reduce((sum, sec) => sum + sec.lines.length, 0)
}

export function countCratePeekLines(
  container: ActivityPackContainer,
  ctx: MaterialJourneyCratePeekContext,
  shellPackItem: ActivityPackItem | null | undefined,
  t: ComposerTranslation,
  packItems: ActivityPackItem[] = [],
  packContainers: ActivityPackContainer[] = [],
): number {
  const combo = comboComponentsForJourneyContainer(container, shellPackItem, ctx)
  if (combo.length > 0) {
    const comboSections = peekSectionsFromComboComponents(
      combo,
      materialJourneyPeekSectionTitles(t),
      t('common.material'),
    )
    const comboCount = countPeekSectionLines(comboSections)
    if (comboCount > 0) return comboCount
  }

  const sections = peekSectionsForJourneyContainer(
    container,
    ctx,
    shellPackItem,
    t,
    packItems,
    packContainers,
  )
  const peekCount = countPeekSectionLines(sections)
  if (peekCount > 0) return peekCount

  const batchId = resolvePackContainerWarehouseBatchId(container, packItems, packContainers)
  if (batchId && ctx.containerWarehouseTemplateByContainerId[container.id]?.size) {
    return ctx.containerWarehouseTemplateByContainerId[container.id]!.size
  }

  if (combo.length > 0) return combo.length

  return (ctx.containerItemsByContainerId[container.id] ?? []).filter(
    (ci) => !isNonActionableContainerLine(ci),
  ).length
}

export function peekSectionsForJourneyCombo(
  packItem: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  ctx: MaterialJourneyCratePeekContext,
  t: ComposerTranslation,
): PackCrateShellPeekSection[] {
  return crateShellPeekSectionsForPackItem(
    packItem,
    packContainers,
    ctx.containerItemsByContainerId,
    ctx.containerWarehouseTemplateByContainerId,
    ctx.containerWarehouseContentsByContainerId,
    ctx.comboComponentsByMaterialId,
    materialJourneyPeekSectionTitles(t),
    t('common.material'),
    {},
    false,
  )
}
