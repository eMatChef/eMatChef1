import { getCategories } from '@/api/categories'
import { getJsMaterialDepartmentDefaults } from '@/api/departmentSettings'
import { getDepartmentMembers } from '@/api/departments'
import type { OnboardingTourId } from '@/config/onboardingTours'
import { filterUserSelectableCategories } from '@/utils/repairPartsCategory'
import {
  isOnboardingTourCompleted,
  markOnboardingTourCompleted,
} from '@/utils/onboardingTourProgress'

function hasDefaultCoachConfigured(js: {
  defaultCoachUserId?: string
  defaultCoachPersonNr: string
  defaultCoachFirstName: string
  defaultCoachLastName: string
  defaultCoachEmail: string
}): boolean {
  return Boolean(
    (js.defaultCoachUserId || '').trim() ||
      js.defaultCoachPersonNr.trim() ||
      js.defaultCoachEmail.trim() ||
      js.defaultCoachFirstName.trim() ||
      js.defaultCoachLastName.trim()
  )
}

/**
 * Markiert Settings-Touren als erledigt, wenn die Daten bereits im Department existieren
 * (ohne dass die Spotlight-Tour durchgeklickt werden muss).
 */
export async function syncOnboardingTourAutoCompletion(
  profileId: string,
  departmentId: string
): Promise<OnboardingTourId[]> {
  if (!profileId || !departmentId) return []

  const [categoriesResult, membersResult, coachResult] = await Promise.allSettled([
    getCategories(departmentId),
    getDepartmentMembers(departmentId),
    getJsMaterialDepartmentDefaults(departmentId),
  ])

  const marked: OnboardingTourId[] = []

  const markIfNeeded = (tourId: OnboardingTourId) => {
    if (isOnboardingTourCompleted(profileId, departmentId, tourId)) return
    markOnboardingTourCompleted(profileId, departmentId, tourId)
    marked.push(tourId)
  }

  if (categoriesResult.status === 'fulfilled') {
    const categories = filterUserSelectableCategories(categoriesResult.value)
    if (categories.length > 0) markIfNeeded('categories')
  }

  if (membersResult.status === 'fulfilled') {
    const members = membersResult.value
    if (members.length > 1) markIfNeeded('invite-users')
    if (members.some((m) => !!m.is_js_coach)) {
      markIfNeeded('default-coach')
    }
  }

  if (coachResult.status === 'fulfilled' && hasDefaultCoachConfigured(coachResult.value)) {
    markIfNeeded('default-coach')
  }

  return marked
}
