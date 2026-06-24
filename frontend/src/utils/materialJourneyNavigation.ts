import type { ActivityDetail } from '@/api/activities'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  defaultJourneyStepForStatus,
  journeyStepToPackStage,
  journeyStepsForProfile,
  type JourneyStep,
} from '@/components/activities/materialJourneySteps'
import { packIssuesVisibleForStage } from '@/components/activities/packWorkflowRules'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import { isJourneyStepWorkComplete } from '@/utils/materialJourneyStepWorkStatus'

export type JourneyStepAccess = 'editable' | 'readonly_past' | 'readonly_future'

export type JourneyStepMaterialContext = {
  packItems: ActivityPackItem[]
  packContainers?: ActivityPackContainer[]
  containerItemsByContainerId?: Record<string, ActivityPackContainerItem[]>
}

/** Aktueller Stepper-Schritt = abgeleitet aus activity.status (ADR-workflow-layers). */
export function resolveActiveJourneyStep(
  activity: Pick<ActivityDetail, 'status' | 'type'> | null | undefined,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
): JourneyStep {
  if (!activity) return 'pack'
  return defaultJourneyStepForStatus(activity.status ?? 'packing', profile, canManageMaterials)
}

/**
 * Quick/External: ab «Am Anlass» ist Retour der aktive Checkpoint —
 * Übergabe an MW erst per Handoff-Button, auch wenn nicht alles ausgegeben wurde.
 */
export function resolveEffectiveActiveJourneyStep(
  activity: Pick<ActivityDetail, 'status' | 'type'> | null | undefined,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
): JourneyStep {
  const base = resolveActiveJourneyStep(activity, profile, canManageMaterials)
  if (profile !== 'logistics' && activity?.status === 'at_event') {
    return 'return'
  }
  return base
}

export function journeyStepAccess(
  viewedStep: JourneyStep,
  activeStep: JourneyStep,
  profile: PackWorkflowProfile,
  material?: JourneyStepMaterialContext,
): JourneyStepAccess {
  const steps = journeyStepsForProfile(profile)
  const viewedIdx = steps.indexOf(viewedStep)
  const activeIdx = steps.indexOf(activeStep)
  if (viewedIdx < 0 || activeIdx < 0) return 'readonly_future'
  if (viewedIdx > activeIdx) return 'readonly_future'
  if (viewedIdx < activeIdx) {
    if (material?.packItems.length && !isJourneyStepWorkComplete(
      viewedStep,
      profile,
      material.packItems,
      material.packContainers ?? [],
      material.containerItemsByContainerId ?? {},
    )) {
      return 'editable'
    }
    return 'readonly_past'
  }
  return 'editable'
}

/** Vergangene Journey-Schritte mit noch offenen Checklisten-Positionen. */
export function journeyStepsWithOpenWork(
  activeStep: JourneyStep,
  profile: PackWorkflowProfile,
  material: JourneyStepMaterialContext,
): JourneyStep[] {
  if (!material.packItems.length) return []
  const steps = journeyStepsForProfile(profile)
  const activeIdx = steps.indexOf(activeStep)
  if (activeIdx <= 0) return []

  const open: JourneyStep[] = []
  for (let i = 0; i < activeIdx; i++) {
    const step = steps[i]!
    if (!isJourneyStepWorkComplete(
      step,
      profile,
      material.packItems,
      material.packContainers ?? [],
      material.containerItemsByContainerId ?? {},
    )) {
      open.push(step)
    }
  }
  return open
}

export function nextJourneyStep(step: JourneyStep, profile: PackWorkflowProfile): JourneyStep | null {
  const steps = journeyStepsForProfile(profile)
  const idx = steps.indexOf(step)
  if (idx < 0 || idx >= steps.length - 1) return null
  return steps[idx + 1]!
}

/** Logistics: Schritte mit explizitem «Weiter»-Button nach Abschluss der Checkliste / Ankunft. */
export function journeyStepNeedsAdvanceConfirm(step: JourneyStep, profile: PackWorkflowProfile): boolean {
  if (profile !== 'logistics') return false
  return step === 'transport_out' || step === 'issue' || step === 'transport_back'
}

export function journeyStepIndex(step: JourneyStep, profile: PackWorkflowProfile): number {
  return journeyStepsForProfile(profile).indexOf(step)
}

/** Verbrauch / Reparatur / Verlust — ab Journey-Schritt «Am Anlass». */
export function activityAllowsIssueReports(
  activity: Pick<ActivityDetail, 'status' | 'type'> | null | undefined,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
): boolean {
  if (!activity) return false
  const status = activity.status ?? ''
  if (status === 'completed' || status === 'cancelled') return false
  if (['at_event', 'transport_back', 'returned', 'storing'].includes(status)) return true
  const activeStep = resolveActiveJourneyStep(activity, profile, canManageMaterials)
  const stage = journeyStepToPackStage(activeStep, profile)
  return packIssuesVisibleForStage(stage)
}

export function activityAllowsDamageReport(
  activity: Pick<ActivityDetail, 'status' | 'type' | 'can_report_issues'> | null | undefined,
  profile: PackWorkflowProfile,
  canReportDamageAsMaterialStaff: boolean,
  canManageMaterials = false,
): boolean {
  if (!activity || activity.status === 'completed') return false
  if (activity.can_report_issues === false) {
    return activityAllowsIssueReports(activity, profile, canManageMaterials)
  }
  const s = activity.status ?? ''
  if (s === 'at_event' || s === 'transport_back') return true
  if (s === 'returned' || s === 'storing') return canReportDamageAsMaterialStaff
  return activityAllowsIssueReports(activity, profile, canManageMaterials)
}

export function activityAllowsConsumptionBooking(
  activity: Pick<ActivityDetail, 'status' | 'type' | 'can_report_issues'> | null | undefined,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
): boolean {
  if (!activity || activity.status === 'completed') return false
  if (activity.can_report_issues === false) {
    return activityAllowsIssueReports(activity, profile, canManageMaterials)
  }
  const s = activity.status ?? ''
  if (['at_event', 'transport_back', 'returned', 'storing'].includes(s)) return true
  return activityAllowsIssueReports(activity, profile, canManageMaterials)
}

/** Quick: packed → at_event; Logistics: transport_out → at_event. */
export function allowsPackedToAtEventHandoff(
  activity: Pick<ActivityDetail, 'status' | 'type'> | null | undefined,
  profile: PackWorkflowProfile,
): boolean {
  if (!activity) return false
  const s = activity.status ?? ''
  if (profile !== 'logistics') return s === 'packed'
  return s === 'transport_out'
}

/** Quick: at_event → returned; Logistics: transport_back → returned. */
export function allowsAtEventToReturnedHandoff(
  activity: Pick<ActivityDetail, 'status' | 'type'> | null | undefined,
  profile: PackWorkflowProfile,
): boolean {
  if (!activity) return false
  const s = activity.status ?? ''
  if (profile !== 'logistics') return s === 'at_event'
  return s === 'transport_back'
}
