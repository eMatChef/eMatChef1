import { type ComposerTranslation } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import {
  crateShellPeekSectionsForPackItem,
  peekSectionsForShellContainer,
} from '@/components/activities/packShellCrateHelpers'
import type { ComboComponent } from '@/api/materials'

export function materialJourneyPeekSectionTitles(t: ComposerTranslation) {
  return {
    fixed: t('activities.packList.containerSubsectionFixed'),
    extra: t('activities.packList.containerSubsectionExtra'),
    all: t('activities.packList.containerSubsectionAll'),
  }
}

export function peekSectionsForJourneyContainer(
  container: ActivityPackContainer,
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  shellPackItem: ActivityPackItem | null | undefined,
  t: ComposerTranslation,
): PackCrateShellPeekSection[] {
  return peekSectionsForShellContainer(
    container,
    containerItemsByContainerId,
    {},
    {},
    [],
    materialJourneyPeekSectionTitles(t),
    t('common.material'),
    shellPackItem?.id,
    {},
    false,
    shellPackItem ?? null,
  )
}

export function peekSectionsForJourneyCombo(
  packItem: ActivityPackItem,
  packContainers: ActivityPackContainer[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  comboComponentsByMaterialId: Record<string, ComboComponent[]>,
  t: ComposerTranslation,
): PackCrateShellPeekSection[] {
  return crateShellPeekSectionsForPackItem(
    packItem,
    packContainers,
    containerItemsByContainerId,
    {},
    {},
    comboComponentsByMaterialId,
    materialJourneyPeekSectionTitles(t),
    t('common.material'),
    {},
    true,
  )
}
