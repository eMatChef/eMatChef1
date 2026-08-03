import type { ActivityIssueReportRow } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  buildMaterialJourneyTasks,
  type MaterialJourneyTaskBuildContext,
} from '@/components/activities/materialJourneyTaskList'
import {
  journeyStepToPackStage,
  isLogisticsTourArrivalStep,
  type JourneyStep,
} from '@/components/activities/materialJourneySteps'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { createMaterialJourneyPackContextState } from '@/composables/materialJourneyPackContextState'

function buildTaskContextForStep(
  step: JourneyStep,
  profile: PackWorkflowProfile,
  packItems: ActivityPackItem[],
  packContainers: ActivityPackContainer[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  issues: ActivityIssueReportRow[] = [],
): MaterialJourneyTaskBuildContext {
  const packStage = journeyStepToPackStage(step, profile)
  const state = createMaterialJourneyPackContextState({
    packItems,
    packContainers,
    containerItemsByContainerId,
    packStage,
    profile,
    issues,
  })

  return {
    listCtx: state.packListCtx,
    containerCtx: state.packContainerCtx,
    stageLeftItems: state.stageLeftItems,
    packStage,
    packContainers,
    maxForwardQty: state.packIssueForwardMax,
    containerIssueableUnits: state.containerIssueableUnits,
    containerActionableUnits: state.containerActionableUnits,
    containerContentActionableUnits: state.containerContentActionableUnits,
    canMoveItem: () => true,
    canOpenSheet: true,
    formatCrateLineCount: (count) => String(count),
    shellPackItemForContainer: state.shellPackItemForContainer,
  }
}

/** Keine offenen Checklisten-Positionen in diesem Journey-Schritt. */
export function isJourneyStepWorkComplete(
  step: JourneyStep,
  profile: PackWorkflowProfile,
  packItems: ActivityPackItem[],
  packContainers: ActivityPackContainer[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
  issues: ActivityIssueReportRow[] = [],
): boolean {
  if (packItems.length === 0) return false

  if (isLogisticsTourArrivalStep(step, profile)) {
    return !hasPendingLogisticsArrival(packItems)
  }

  const ctx = buildTaskContextForStep(
    step,
    profile,
    packItems,
    packContainers,
    containerItemsByContainerId,
    issues,
  )
  const tasks = buildMaterialJourneyTasks(packItems, ctx).filter((row) => row.isOpen || row.isDone)
  // Keine offenen Zeilen = fertig (auch wenn Done-Spiegel leer ist, weil Mengen schon weiter sind).
  return !tasks.some((row) => row.isOpen)
}

/** Noch transportiert, aber noch nicht am Anlass (quantity_transport_to > quantity_issued). */
export function hasPendingLogisticsArrival(packItems: ActivityPackItem[]): boolean {
  return packItems.some(
    (pi) => (pi.quantityTransportTo ?? 0) > (pi.quantityIssued ?? 0),
  )
}
