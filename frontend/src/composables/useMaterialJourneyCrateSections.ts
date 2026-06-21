import { type ComposerTranslation } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import {
  crateShellPeekSectionsForPackItem,
  peekSectionsForShellContainer,
  resolvePackContainerWarehouseBatchId,
} from '@/components/activities/packShellCrateHelpers'
import type { ComboComponent } from '@/api/materials'
import type { RackContentsItem } from '@/api/storageLocations'

export function materialJourneyPeekSectionTitles(t: ComposerTranslation) {
  return {
    fixed: t('activities.packList.containerSubsectionFixed'),
    extra: t('activities.packList.containerSubsectionExtra'),
    all: t('activities.packList.containerSubsectionAll'),
  }
}

export type MaterialJourneyCratePeekContext = {
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  containerWarehouseTemplateByContainerId: Record<string, Set<string>>
  containerWarehouseContentsByContainerId: Record<string, RackContentsItem[]>
  comboComponentsByMaterialId: Record<string, ComboComponent[]>
}

export function peekSectionsForJourneyContainer(
  container: ActivityPackContainer,
  ctx: MaterialJourneyCratePeekContext,
  shellPackItem: ActivityPackItem | null | undefined,
  t: ComposerTranslation,
  packItems: ActivityPackItem[] = [],
  packContainers: ActivityPackContainer[] = [],
): PackCrateShellPeekSection[] {
  const comboMaterialId =
    (shellPackItem?.materialItemId ?? '').trim() ||
    (container.container_material_item_id ?? '').trim()
  const combo = comboMaterialId ? ctx.comboComponentsByMaterialId[comboMaterialId] ?? [] : []

  const linkedBatchId =
    (shellPackItem?.linkedContainerBatchId ?? '').trim() ||
    resolvePackContainerWarehouseBatchId(container, packItems, packContainers)

  const shellForPeek: ActivityPackItem | null =
    shellPackItem ??
    (comboMaterialId || linkedBatchId
      ? ({
          id: '',
          materialItemId: comboMaterialId,
          linkedContainerBatchId: linkedBatchId || null,
          materialType: 'physical_combo',
        } as ActivityPackItem)
      : null)

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

export function countCratePeekLines(
  container: ActivityPackContainer,
  ctx: MaterialJourneyCratePeekContext,
  shellPackItem: ActivityPackItem | null | undefined,
  t: ComposerTranslation,
  packItems: ActivityPackItem[] = [],
  packContainers: ActivityPackContainer[] = [],
): number {
  return peekSectionsForJourneyContainer(
    container,
    ctx,
    shellPackItem,
    t,
    packItems,
    packContainers,
  ).reduce((sum, sec) => sum + sec.lines.length, 0)
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
    true,
  )
}
