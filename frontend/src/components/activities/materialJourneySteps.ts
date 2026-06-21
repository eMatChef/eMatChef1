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

/** Default journey step when :step route param is missing (§3.2). */
export function defaultJourneyStepForStatus(
  status: string,
  profile: PackWorkflowProfile,
  canManageMaterials = false,
  options?: { transportOutAcknowledged?: boolean },
): JourneyStep {
  if (status === 'packing') return 'pack'
  if (status === 'packed') {
    if (profile === 'logistics') {
      return options?.transportOutAcknowledged ? 'issue' : 'transport_out'
    }
    return 'issue'
  }
  if (status === 'at_event') {
    return profile === 'logistics' ? 'transport_back' : 'return'
  }
  if (status === 'returned') {
    return canManageMaterials ? 'store' : 'return'
  }
  if (status === 'completed') {
    return canManageMaterials ? 'store' : 'return'
  }
  return 'pack'
}

/** Phase 2+: lose Vorwärts-Moves; Phase 6: Retour + Einlagern; Phase 7: Logistics-Transport. */
export function isJourneyLooseMovesEnabledForStep(
  step: JourneyStep,
  profile: PackWorkflowProfile,
): boolean {
  if (step === 'pack') return true
  if (step === 'return' || step === 'store') return true
  if (profile === 'logistics') {
    return step === 'transport_out' || step === 'issue' || step === 'transport_back'
  }
  return step === 'issue'
}

/** Vorwärts-Checkliste: Packen/Ausgabe/Transport (Kisten-Sheet, Scan «in Kiste»). */
export function isJourneyForwardChecklistStep(step: JourneyStep): boolean {
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

/** Regal/Fach in Checkliste — nur Packen, Retour und Einlagern (§ Journey UX). */
export function materialJourneyShowsShelfLocation(step: JourneyStep): boolean {
  return step === 'pack' || step === 'return' || step === 'store'
}

/** Packkiste: «Lose mitnehmen» / «In andere Kiste» — ab Transport hin bis Retour. */
export function materialJourneyShowsCrateTransitActions(step: JourneyStep): boolean {
  return (
    step === 'transport_out' ||
    step === 'issue' ||
    step === 'transport_back' ||
    step === 'return'
  )
}

/** Erledigt-Tab: Buchung zurücknehmen mit Mengenkontrolle. */
export function materialJourneyShowsMoveBack(step: JourneyStep): boolean {
  return step === 'transport_out'
}

/** Offen-Tab: Packkiste per → nur beim Packen (Transport: Inhalt einzeln / Kiste bleibt). */
export function materialJourneyShowsCrateMoveForwardQty(step: JourneyStep): boolean {
  return step === 'pack'
}

/** Offen-Tab: Menge eingeben + Pfeil statt «Tippen = alles». */
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
