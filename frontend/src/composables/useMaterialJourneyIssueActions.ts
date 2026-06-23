import { computed, type Ref } from 'vue'
import type { ActivityDetail } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { isConsumablePackItem } from '@/utils/packItemConsumable'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import {
  isPackForwardToEventStage,
  isPackReturnStage,
  type PackStage,
} from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import type { PackWorkflowListContext } from '@/components/activities/packWorkflowRules'
import { packIssuesVisibleForStage } from '@/components/activities/packWorkflowRules'
import type { MaterialJourneyAccordionLine } from '@/components/activities/materialJourneyAccordionLines'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
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

  function hasIssuedAtEventQty(pi: ActivityPackItem): boolean {
    if (isPackForwardToEventStage(options.packStage.value)) {
      const loose = Math.max(
        0,
        (pi.quantityIssued ?? 0) - options.packListCtx.value.qtyInContainersForItem(pi),
      )
      return loose > 0 || options.issuedQtyInContainersForMaterial(pi.materialItemId) > 0
    }
    if (isPackReturnStage(options.packStage.value)) {
      return Math.max(0, (pi.quantityIssued ?? 0) - (pi.quantityReturned ?? 0)) > 0
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

  function showIssueForRow(row: MaterialJourneyTaskRow): boolean {
    if (!row.packItem) return false
    if (row.kind === 'loose' || row.kind === 'combo') return showIssueForPackItem(row.packItem)
    return false
  }

  function showIssueForAccordionLine(
    line: MaterialJourneyAccordionLine,
    row: MaterialJourneyTaskRow,
  ): boolean {
    if (!line.materialItemId) return false
    if (!showIssueActions.value && !showConsumptionActions.value) return false

    const shellIssued = crateShellIssuedAtEvent(row, options.shellPackItemForContainer)
    const issued = issuedQtyForAccordionLineAtEvent(line, row, { crateShellIssued: shellIssued })
    if (issued <= 0) return false

    const pi = options.packItems.value.find((p) => p.materialItemId === line.materialItemId)
    if (!pi) return false

    if (isConsumable(pi)) {
      if (!showConsumptionActions.value) return false
      if (shellIssued && row.kind === 'crate') return true
      return showConsumableConsumptionForPackItem(pi)
    }

    if (!showIssueActions.value) return false
    /** Kisteninhalt am Anlass — wie Legacy showPackIssueForContainerLine. */
    if (shellIssued && row.kind === 'crate') return true
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
