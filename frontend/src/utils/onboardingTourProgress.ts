import type { OnboardingTourId } from '@/config/onboardingTours'
import { getOnboardingTour } from '@/config/onboardingTours'

function buildTourProgressKey(profileId: string, departmentId: string): string {
  return `onboarding_tours_${profileId}_${departmentId}`
}

type TourProgressMap = Partial<Record<OnboardingTourId, number>>

function readTourProgress(profileId: string, departmentId: string): TourProgressMap {
  const raw = localStorage.getItem(buildTourProgressKey(profileId, departmentId))
  if (!raw) return {}
  try {
    return JSON.parse(raw) as TourProgressMap
  } catch {
    return {}
  }
}

function writeTourProgress(profileId: string, departmentId: string, map: TourProgressMap): void {
  localStorage.setItem(buildTourProgressKey(profileId, departmentId), JSON.stringify(map))
}

export function isOnboardingTourCompleted(
  profileId: string,
  departmentId: string,
  tourId: OnboardingTourId
): boolean {
  const tour = getOnboardingTour(tourId)
  if (!tour) return false
  const storedVersion = readTourProgress(profileId, departmentId)[tourId]
  return storedVersion !== undefined && storedVersion >= tour.version
}

export function markOnboardingTourCompleted(
  profileId: string,
  departmentId: string,
  tourId: OnboardingTourId
): void {
  const tour = getOnboardingTour(tourId)
  if (!tour) return
  const map = readTourProgress(profileId, departmentId)
  map[tourId] = tour.version
  writeTourProgress(profileId, departmentId, map)
}

export function getCompletedOnboardingTourIds(
  profileId: string,
  departmentId: string
): OnboardingTourId[] {
  const map = readTourProgress(profileId, departmentId)
  return (Object.keys(map) as OnboardingTourId[]).filter((tourId) =>
    isOnboardingTourCompleted(profileId, departmentId, tourId)
  )
}
