import type { PackStage } from '@/components/activities/packStageQuantities'
import type { PackWorkflowProfile } from '@/components/activities/packWorkflowProfile'

export type JourneyStep =
  | 'pack'
  | 'transport_out'
  | 'issue'
  | 'transport_back'
  | 'return'
  | 'store'

const JOURNEY_STEPS_QUICK: JourneyStep[] = ['pack', 'issue', 'return', 'store']

const JOURNEY_STEPS_LOGISTICS: JourneyStep[] = [
  'pack',
  'transport_out',
  'issue',
  'transport_back',
  'return',
  'store',
]

export function journeyStepsForProfile(profile: PackWorkflowProfile): JourneyStep[] {
  if (profile === 'logistics') return [...JOURNEY_STEPS_LOGISTICS]
  return [...JOURNEY_STEPS_QUICK]
}

export function isValidJourneyStep(step: string, profile: PackWorkflowProfile): step is JourneyStep {
  return journeyStepsForProfile(profile).includes(step as JourneyStep)
}

/** Mapping journey_step → PackStage (shared Rules-Layer). */
export function journeyStepToPackStage(step: JourneyStep, profile: PackWorkflowProfile): PackStage {
  if (profile === 'logistics') {
    switch (step) {
      case 'pack':
        return 'confirmed_packed'
      case 'transport_out':
        return 'packed_transport_to'
      case 'issue':
        return 'transport_to_at_event'
      case 'transport_back':
        return 'at_event_transport_back'
      case 'return':
        return 'transport_back_returned'
      case 'store':
        return 'returned_unpack'
      default:
        return 'confirmed_packed'
    }
  }

  switch (step) {
    case 'pack':
      return 'confirmed_packed'
    case 'issue':
      return 'packed_at_event'
    case 'return':
      return 'at_event_returned'
    case 'store':
      return 'returned_unpack'
    default:
      return 'confirmed_packed'
  }
}

/**
 * Pipeline-Stufe für Verbrauchsmaterial-Nachlieferung: aktiver Journey-Schritt
 * (oder Ableitung aus Aktivitäts-Status), z. B. at_event → packed_at_event.
 */
export function replenishmentPackStageForContext(
  activityStatus: string,
  profile: PackWorkflowProfile,
  options?: {
    journeyStep?: JourneyStep | null
    canManageMaterials?: boolean
  },
): PackStage {
  const step =
    options?.journeyStep ??
    defaultJourneyStepForStatus(activityStatus, profile, options?.canManageMaterials ?? false)
  return journeyStepToPackStage(step, profile)
}

/** Stepper-Schritt aus activity.status (1:1, siehe ADR-workflow-layers.md). */
export function defaultJourneyStepForStatus(
  status: string,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
): JourneyStep {
  if (status === 'packing') return 'pack'
  if (status === 'packed') {
    return profile === 'logistics' ? 'transport_out' : 'issue'
  }
  if (status === 'transport_out') return 'transport_out'
  if (status === 'at_event') return 'issue'
  if (status === 'transport_back') return 'transport_back'
  if (status === 'returned') return 'return'
  if (status === 'storing') return 'store'
  if (status === 'completed') {
    return canManageMaterials ? 'store' : 'return'
  }
  return 'pack'
}

/** Nächster Activity-Status nach «Weiter» vom aktuellen Journey-Schritt. */
export function activityStatusAfterJourneyStep(
  step: JourneyStep,
  profile: PackWorkflowProfile,
): string | null {
  if (profile === 'logistics') {
    switch (step) {
      case 'transport_out':
        return 'at_event'
      case 'issue':
        return 'transport_back'
      case 'transport_back':
        return 'returned'
      default:
        return null
    }
  }
  switch (step) {
    case 'issue':
      return 'returned'
    default:
      return null
  }
}

/** Phase 2+: lose Vorwärts-Moves; Phase 6: Retour + Einlagern; Phase 7: Logistics-Transport. */
export function isJourneyLooseMovesEnabledForStep(
  step: JourneyStep,
  profile: PackWorkflowProfile,
): boolean {
  if (step === 'pack') return true
  if (step === 'return' || step === 'store') return true
  if (profile === 'logistics') {
    return step === 'transport_out' || step === 'transport_back'
  }
  return step === 'issue'
}

/** Am Anlass (Logistics): Ankunft über Touren, keine Pipeline-Checkliste. */
export function isLogisticsTourArrivalStep(step: JourneyStep, profile: PackWorkflowProfile): boolean {
  return profile === 'logistics' && step === 'issue'
}

/** Vorwärts-Checkliste: Packen/Ausgabe/Transport (Kisten-Sheet, Scan «in Kiste»). */
export function isJourneyForwardChecklistStep(
  step: JourneyStep,
  profile?: PackWorkflowProfile,
): boolean {
  if (profile === 'logistics' && step === 'issue') return false
  return (
    step === 'pack' ||
    step === 'issue' ||
    step === 'transport_out' ||
    step === 'transport_back'
  )
}

export function isJourneyTransportOutStep(step: JourneyStep): boolean {
  return step === 'transport_out'
}

export function isJourneyTransportBackStep(step: JourneyStep): boolean {
  return step === 'transport_back'
}

export function isJourneyReturnStep(step: JourneyStep): boolean {
  return step === 'return'
}

export function isJourneyStoreStep(step: JourneyStep): boolean {
  return step === 'store'
}

/** Regal/Fach in Checkliste — nur Packen und Einlagern (ab «Gepackt» kein Lagerort mehr). */
export function materialJourneyShowsShelfLocation(step: JourneyStep): boolean {
  return step === 'pack' || step === 'store'
}

export function materialJourneyShowsMoveForwardQty(
  step: JourneyStep,
  profile: PackWorkflowProfile,
): boolean {
  return isJourneyLooseMovesEnabledForStep(step, profile) || step === 'store'
}

export function isJourneyStepAheadOfDefault(
  step: JourneyStep,
  defaultStep: JourneyStep,
  profile: PackWorkflowProfile,
): boolean {
  const steps = journeyStepsForProfile(profile)
  return steps.indexOf(step) > steps.indexOf(defaultStep)
}

/** URL-Schritt liegt hinter dem Status (z. B. pack bei Status packed) — auf Default weiterleiten. */
export function isJourneyStepBehindDefault(
  step: JourneyStep,
  defaultStep: JourneyStep,
  profile: PackWorkflowProfile,
): boolean {
  const steps = journeyStepsForProfile(profile)
  return steps.indexOf(step) < steps.indexOf(defaultStep)
}
