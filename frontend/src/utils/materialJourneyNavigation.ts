import type { ActivityDetail } from '@/api/activities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'
import {
  defaultJourneyStepForStatus,
  isValidJourneyStep,
  journeyStepsForProfile,
  type JourneyStep,
} from '@/components/activities/materialJourneySteps'

export type JourneyStepAccess = 'editable' | 'readonly_past' | 'readonly_future'

/** Aktuell bearbeitbarer Pipeline-Schritt (Backend pack_journey_step oder Status-Fallback). */
export function resolveActiveJourneyStep(
  activity: Pick<ActivityDetail, 'status' | 'pack_journey_step' | 'type'> | null | undefined,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
): JourneyStep {
  if (!activity) return 'pack'
  const stored = activity.pack_journey_step?.trim()
  if (stored && isValidJourneyStep(stored, profile)) {
    return clampJourneyStepToStatus(stored as JourneyStep, activity.status ?? 'packing', profile, canManageMaterials)
  }
  return defaultJourneyStepForStatus(activity.status ?? 'packing', profile, canManageMaterials)
}

/** Maximal erlaubter Schritt für den groben Aktivitäts-Status. */
export function maxJourneyStepForStatus(
  status: string,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
): JourneyStep {
  if (status === 'packing') return 'pack'
  if (status === 'packed') return 'issue'
  if (status === 'at_event') {
    return profile === 'logistics' ? 'return' : 'return'
  }
  if (status === 'returned' || status === 'completed') {
    return canManageMaterials ? 'store' : 'return'
  }
  return 'pack'
}

function clampJourneyStepToStatus(
  step: JourneyStep,
  status: string,
  profile: PackWorkflowProfile,
  canManageMaterials: boolean,
): JourneyStep {
  const steps = journeyStepsForProfile(profile)
  const stepIdx = steps.indexOf(step)
  const maxStep = maxJourneyStepForStatus(status, profile, canManageMaterials)
  const maxIdx = steps.indexOf(maxStep)
  if (stepIdx < 0 || maxIdx < 0 || stepIdx > maxIdx) return maxStep
  return step
}

export function journeyStepAccess(
  viewedStep: JourneyStep,
  activeStep: JourneyStep,
  profile: PackWorkflowProfile,
): JourneyStepAccess {
  const steps = journeyStepsForProfile(profile)
  const viewedIdx = steps.indexOf(viewedStep)
  const activeIdx = steps.indexOf(activeStep)
  if (viewedIdx < 0 || activeIdx < 0) return 'readonly_future'
  if (viewedIdx < activeIdx) return 'readonly_past'
  if (viewedIdx > activeIdx) return 'readonly_future'
  return 'editable'
}

export function nextJourneyStep(step: JourneyStep, profile: PackWorkflowProfile): JourneyStep | null {
  const steps = journeyStepsForProfile(profile)
  const idx = steps.indexOf(step)
  if (idx < 0 || idx >= steps.length - 1) return null
  return steps[idx + 1]!
}

/** Logistics: Schritte mit explizitem «Weiter»-Button nach Abschluss der Checkliste. */
export function journeyStepNeedsAdvanceConfirm(step: JourneyStep, profile: PackWorkflowProfile): boolean {
  if (profile !== 'logistics') return false
  return step === 'transport_out' || step === 'transport_back'
}

export function journeyStepIndex(step: JourneyStep, profile: PackWorkflowProfile): number {
  return journeyStepsForProfile(profile).indexOf(step)
}

/** Header-Status «Am Event»: erst ab freigeschaltetem Schritt «Am Anlass». */
export function allowsPackedToAtEventHandoff(
  activity: Pick<ActivityDetail, 'status' | 'pack_journey_step' | 'type'> | null | undefined,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
): boolean {
  if (!activity || activity.status !== 'packed') return false
  if (profile !== 'logistics') return true
  const active = resolveActiveJourneyStep(activity, profile, canManageMaterials)
  return journeyStepIndex(active, profile) >= journeyStepIndex('issue', profile)
}

/** Header-Status «Retour»: Logistics erst ab Schritt Retour. */
export function allowsAtEventToReturnedHandoff(
  activity: Pick<ActivityDetail, 'status' | 'pack_journey_step' | 'type'> | null | undefined,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
): boolean {
  if (!activity || activity.status !== 'at_event') return false
  if (profile !== 'logistics') return true
  const active = resolveActiveJourneyStep(activity, profile, canManageMaterials)
  return journeyStepIndex(active, profile) >= journeyStepIndex('return', profile)
}
