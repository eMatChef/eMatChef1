import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  buildMaterialJourneyTasks,
  type MaterialJourneyTaskBuildContext,
} from '@/components/activities/materialJourneyTaskList'
import {
  journeyStepToPackStage,
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
): MaterialJourneyTaskBuildContext {
  const packStage = journeyStepToPackStage(step, profile)
  const state = createMaterialJourneyPackContextState({
    packItems,
    packContainers,
    containerItemsByContainerId,
    packStage,
    profile,
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
    canMoveItem: () => true,
    canOpenSheet: true,
    formatCrateLineCount: (count) => String(count),
    shellPackItemForContainer: state.shellPackItemForContainer,
  }
}

/** Keine offenen Checklisten-Positionen in diesem Journey-Schritt (Touren nicht relevant). */
export function isJourneyStepWorkComplete(
  step: JourneyStep,
  profile: PackWorkflowProfile,
  packItems: ActivityPackItem[],
  packContainers: ActivityPackContainer[],
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>,
): boolean {
  if (packItems.length === 0) return false
  const ctx = buildTaskContextForStep(
    step,
    profile,
    packItems,
    packContainers,
    containerItemsByContainerId,
  )
  const tasks = buildMaterialJourneyTasks(packItems, ctx).filter((row) => row.isOpen || row.isDone)
  if (tasks.length === 0) return false
  return tasks.every((row) => !row.isOpen)
}
