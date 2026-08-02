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

/** Gruppe / User ohne MW-Recht — wie PACK_STAGE_KEYS_QUICK_MEMBER in der klassischen Packliste. */
const JOURNEY_STEPS_QUICK_MEMBER: JourneyStep[] = ['issue', 'return']

const JOURNEY_STEPS_LOGISTICS: JourneyStep[] = [
  'pack',
  'transport_out',
  'issue',
  'transport_back',
  'return',
  'store',
]

/** Gruppe Camp/Event — Transport + Am Anlass + Retour, ohne Packen/Einlagern. */
const JOURNEY_STEPS_LOGISTICS_MEMBER: JourneyStep[] = [
  'transport_out',
  'issue',
  'transport_back',
  'return',
]

export function journeyStepsForProfile(profile: PackWorkflowProfile): JourneyStep[] {
  if (profile === 'logistics') return [...JOURNEY_STEPS_LOGISTICS]
  return [...JOURNEY_STEPS_QUICK]
}

/** Stepper-Sicht: MW volle Pipeline, Gruppe nur Ausgabe/Retour (± Transport). */
export function journeyStepsForViewer(
  profile: PackWorkflowProfile,
  canManageMaterials: boolean,
): JourneyStep[] {
  if (canManageMaterials) return journeyStepsForProfile(profile)
  if (profile === 'logistics') return [...JOURNEY_STEPS_LOGISTICS_MEMBER]
  return [...JOURNEY_STEPS_QUICK_MEMBER]
}

export function isValidJourneyStep(step: string, profile: PackWorkflowProfile): step is JourneyStep {
  return journeyStepsForProfile(profile).includes(step as JourneyStep)
}

export function isValidJourneyStepForViewer(
  step: string,
  profile: PackWorkflowProfile,
  canManageMaterials: boolean,
): step is JourneyStep {
  return journeyStepsForViewer(profile, canManageMaterials).includes(step as JourneyStep)
}

/** MW-only Steps (pack/store) auf den nächsten Gruppen-Schritt mappen. */
export function clampJourneyStepForViewer(
  step: JourneyStep,
  profile: PackWorkflowProfile,
  canManageMaterials: boolean,
): JourneyStep {
  if (canManageMaterials) return step
  const viewerSteps = journeyStepsForViewer(profile, canManageMaterials)
  if (viewerSteps.includes(step)) return step
  const pipeline = journeyStepsForProfile(profile)
  const idx = pipeline.indexOf(step)
  if (idx < 0) return viewerSteps[0] ?? step
  for (let i = idx; i >= 0; i--) {
    const candidate = pipeline[i]!
    if (viewerSteps.includes(candidate)) return candidate
  }
  return viewerSteps[0] ?? step
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

/** Logistics: Kistencheck-Bein «return» auf Transport zurück und Retour. */
export function isJourneyLogisticsReturnCrateCheckStep(
  step: JourneyStep,
  profile: PackWorkflowProfile,
): boolean {
  return profile === 'logistics' && (step === 'transport_back' || step === 'return')
}

/** Journey-Schritt mit Retour-Pipeline (Modal / returnAll), inkl. Logistics Transport→Retour. */
export function isJourneyReturnPipelineStep(
  step: JourneyStep,
  profile: PackWorkflowProfile,
): boolean {
  return isJourneyReturnStep(step)
}

export function isJourneyStoreStep(step: JourneyStep): boolean {
  return step === 'store'
}

/** Regal/Fach in Checkliste und Scan — nur Packen und Einlagern (dazwischen: Haufen / mitgenommen). */
export function materialJourneyShowsShelfLocation(step: JourneyStep): boolean {
  return step === 'pack' || step === 'store'
}

/** Regal-QR und Regal-Textsuche — dieselbe Regel wie {@link materialJourneyShowsShelfLocation}. */
export const materialJourneyAllowsShelfSearch = materialJourneyShowsShelfLocation

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

/** Quick/External Ausgabe: Material bereits mitgenommen (teilweise oder vollständig). */
export function isQuickIssueOnTheWay(
  profile: PackWorkflowProfile,
  step: JourneyStep,
  activityStatus: string,
  issueDoneCount: number,
): boolean {
  if (profile === 'logistics' || step !== 'issue') return false
  if (activityStatus === 'at_event') return true
  return issueDoneCount > 0
}

/** i18n-Key für Stepper, Badge und Kopfzeile (ohne t()). */
export function materialJourneyStepI18nKey(
  step: JourneyStep,
  profile: PackWorkflowProfile,
  options?: { activityStatus?: string; issueDoneCount?: number },
): string {
  if (step === 'issue' && profile === 'logistics') {
    return 'activities.materialJourney.step.issueLogistics'
  }
  if (
    isQuickIssueOnTheWay(
      profile,
      step,
      options?.activityStatus ?? '',
      options?.issueDoneCount ?? 0,
    )
  ) {
    return 'activities.materialJourney.step.issueOnTheWay'
  }
  return `activities.materialJourney.step.${step}`
}
