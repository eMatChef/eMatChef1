import { computed, type Ref } from 'vue'
import type { ActivityDetail } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { isConsumablePackItem } from '@/utils/packItemConsumable'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import { isJourneyStoreStep } from '@/components/activities/materialJourneySteps'
import {
  isPackForwardToEventStage,
  isPackReturnStage,
  isPackUnpackStage,
  type PackStage,
} from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import type { PackWorkflowListContext } from '@/components/activities/packWorkflowRules'
import { packIssuesVisibleForStage } from '@/components/activities/packWorkflowRules'
import type { MaterialJourneyAccordionLine } from '@/components/activities/materialJourneyAccordionLines'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import { isMaterialJourneyCrateKind } from '@/components/activities/materialJourneyTaskList'
import { issuedQtyForAccordionLineAtEvent, crateShellIssuedAtEvent } from '@/utils/materialJourneyAtEventInventoryQty'

export function useMaterialJourneyIssueActions(options: {
  activity: Ref<ActivityDetail | null>
  packItems: Ref<ActivityPackItem[]>
  packContainers: Ref<ActivityPackContainer[]>
  containerItemsByContainerId: Ref<Record<string, ActivityPackContainerItem[]>>
  journeyStep: Ref<JourneyStep>
  packStage: Ref<PackStage>
  profile: Ref<PackWorkflowProfile>
  packListCtx: Ref<PackWorkflowListContext>
  canReportIssues: Ref<boolean>
  canReportConsumption: Ref<boolean>
  consumableMaterialItemIds?: Ref<ReadonlySet<string>>
  shellPackItemForContainer?: (containerId: string) => ActivityPackItem | undefined
  issuedQtyInContainersForMaterial: (materialItemId: string) => number
  containerLineRemainingStore?: (ci: ActivityPackContainerItem) => number
  containerShellPendingStoreQty?: (containerId: string) => number
}) {
  const showIssueActions = computed(
    () =>
      packIssuesVisibleForStage(options.packStage.value) &&
      options.canReportIssues.value !== false,
  )

  const showConsumptionActions = computed(
    () =>
      packIssuesVisibleForStage(options.packStage.value) &&
      options.canReportConsumption.value !== false,
  )

  function isStorePhase(): boolean {
    return (
      isPackUnpackStage(options.packStage.value) ||
      isJourneyStoreStep(options.journeyStep.value)
    )
  }

  function packItemPendingStoreForIssue(pi: ActivityPackItem): boolean {
    return options.packListCtx.value.pendingStoreLooseQtyForPackItem(pi) > 0
  }

  function containerLinePendingStoreForIssue(ci: ActivityPackContainerItem): boolean {
    return (options.containerLineRemainingStore?.(ci) ?? 0) > 0
  }

  function looseIssuedAtEvent(pi: ActivityPackItem): number {
    return Math.max(
      0,
      (pi.quantityIssued ?? 0) - options.packListCtx.value.qtyInContainersForItem(pi),
    )
  }

  function hasIssuedAtEventQty(pi: ActivityPackItem): boolean {
    if (isPackForwardToEventStage(options.packStage.value)) {
      return looseIssuedAtEvent(pi) > 0
    }
    if (isPackReturnStage(options.packStage.value)) {
      return Math.max(0, (pi.quantityIssued ?? 0) - (pi.quantityReturned ?? 0)) > 0
    }
    if (isStorePhase()) {
      if (isConsumable(pi)) return false
      return packItemPendingStoreForIssue(pi)
    }
    return (pi.quantityIssued ?? 0) > 0
  }

  function isConsumable(pi: ActivityPackItem): boolean {
    return isConsumablePackItem(pi, options.consumableMaterialItemIds?.value)
  }

  function showConsumableConsumptionForPackItem(pi: ActivityPackItem): boolean {
    if (!isConsumable(pi) || !showConsumptionActions.value) return false
    return hasIssuedAtEventQty(pi)
  }

  function showIssueForPackItem(pi: ActivityPackItem): boolean {
    if (!showIssueActions.value && !showConsumptionActions.value) return false
    if (isConsumable(pi)) return showConsumableConsumptionForPackItem(pi)
    if (!showIssueActions.value) return false
    return hasIssuedAtEventQty(pi)
  }

  function showIssueForShellUnpack(containerId: string): boolean {
    const shell = options.shellPackItemForContainer?.(containerId)
    if (!shell) return false
    if (isConsumable(shell)) return showConsumableConsumptionForPackItem(shell)
    if (!showIssueActions.value) return false
    return (options.containerShellPendingStoreQty?.(containerId) ?? 0) > 0
  }

  function showIssueForRow(row: MaterialJourneyTaskRow): boolean {
    if (isStorePhase()) {
      if (!row.isOpen) return false
      if (isMaterialJourneyCrateKind(row.kind) && row.container) {
        return showIssueForShellUnpack(row.container.id)
      }
      if (!row.packItem) return false
      if (row.kind !== 'loose' && row.kind !== 'combo') return false
      return showIssueForPackItem(row.packItem)
    }
    if (!row.packItem) return false
    if (row.kind !== 'loose' && row.kind !== 'combo') return false
    return showIssueForPackItem(row.packItem)
  }

  function containerLineForAccordion(
    row: MaterialJourneyTaskRow,
    line: MaterialJourneyAccordionLine,
  ): ActivityPackContainerItem | undefined {
    const containerId = row.container?.id
    if (!containerId || !line.materialItemId) return undefined
    return (options.containerItemsByContainerId.value[containerId] ?? []).find(
      (ci) => ci.id === line.id || ci.material_item_id === line.materialItemId,
    )
  }

  function showIssueForAccordionLine(
    line: MaterialJourneyAccordionLine,
    row: MaterialJourneyTaskRow,
  ): boolean {
    if (!line.materialItemId) return false
    if (!showIssueActions.value && !showConsumptionActions.value) return false

    const pi = options.packItems.value.find((p) => p.materialItemId === line.materialItemId)
    if (!pi) return false

    if (isStorePhase()) {
      if (isConsumable(pi)) return false
      if (!showIssueActions.value) return false
      const ci = containerLineForAccordion(row, line)
      if (!ci) return false
      return containerLinePendingStoreForIssue(ci)
    }

    const shellIssued = crateShellIssuedAtEvent(row, options.shellPackItemForContainer)
    const issued = issuedQtyForAccordionLineAtEvent(line, row, { crateShellIssued: shellIssued })
    if (issued <= 0) return false

    if (isConsumable(pi)) {
      if (!showConsumptionActions.value) return false
      if (shellIssued && isMaterialJourneyCrateKind(row.kind)) return true
      return showConsumableConsumptionForPackItem(pi)
    }

    if (!showIssueActions.value) return false
    /** Kisteninhalt am Anlass — wie Legacy showPackIssueForContainerLine. */
    if (shellIssued && isMaterialJourneyCrateKind(row.kind)) return true
    if (isPackReturnStage(options.packStage.value)) {
      const ci = containerLineForAccordion(row, line)
      if (ci) {
        return Math.max(0, (ci.quantity_issued ?? 0) - (ci.quantity_returned ?? 0)) > 0
      }
    }
    return hasIssuedAtEventQty(pi)
  }

  function isConsumableForMaterialId(materialItemId: string | null | undefined): boolean {
    if (!materialItemId) return false
    if (options.consumableMaterialItemIds?.value?.has(materialItemId)) return true
    const pi = options.packItems.value.find((p) => p.materialItemId === materialItemId)
    if (!pi) return false
    return isConsumable(pi)
  }

  return {
    showIssueActions,
    showConsumptionActions,
    showIssueForPackItem,
    showIssueForRow,
    showIssueForAccordionLine,
    showConsumableConsumptionForPackItem,
    isConsumableForMaterialId,
    isConsumablePackItem: isConsumable,
  }
}
