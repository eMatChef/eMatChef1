import apiClient from '@/api/apiClient'
import type { OnboardingTourId } from '@/config/onboardingTours'

export type OnboardingSandboxEnsureResult = {
  activityId: string | null
  campId: string | null
  venueId: string | null
  materialIds: {
    blache: string | null
    packpapier: string | null
    statikseil: string | null
  }
  vehicleId: string | null
  statuses: {
    activity: string | null
    camp: string | null
  }
}

let lastSandboxResult: OnboardingSandboxEnsureResult | null = null

export {
  isOnboardingSandboxIncludeActive,
  setOnboardingSandboxIncludeActive,
} from '@/api/onboardingSandboxFlag'

export function getLastOnboardingSandboxResult(): OnboardingSandboxEnsureResult | null {
  return lastSandboxResult
}

export async function ensureOnboardingSandbox(
  departmentId: string,
  forTourId?: OnboardingTourId | string | null,
  opts?: { reset?: boolean }
): Promise<OnboardingSandboxEnsureResult> {
  const body: { forTourId?: string; reset?: boolean } = {}
  if (forTourId) body.forTourId = String(forTourId)
  if (opts?.reset) body.reset = true
  const { data } = await apiClient.post<OnboardingSandboxEnsureResult>(
    `/api/departments/${departmentId}/onboarding-sandbox`,
    body
  )
  lastSandboxResult = data
  return data
}

/** Welche Sandbox-ID die Tour bevorzugt öffnet. */
export function preferredSandboxActivityId(
  tourId: string,
  result: OnboardingSandboxEnsureResult | null
): string | null {
  if (!result) return null
  switch (tourId) {
    case 'activity-create':
      // Create-Tour: User legt neu an — kein Pre-Create
      return null
    case 'activity-camp-create':
      return null
    case 'activity-approve':
    case 'issue-return':
    case 'issue-handoff':
    case 'activity-store':
    case 'activity-close':
    case 'workshop-overview':
      return result.campId ?? result.activityId
    default:
      return result.campId ?? result.activityId
  }
}

export const ONBOARDING_DEMO_ACTIVITY_NAME = 'demo_activity'
export const ONBOARDING_DEMO_CAMP_NAME = 'demo_camp'
