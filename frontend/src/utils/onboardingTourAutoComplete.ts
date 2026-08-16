import { getCategories } from '@/api/categories'
import { getJsMaterialDepartmentDefaults } from '@/api/departmentSettings'
import { getDepartmentMembers } from '@/api/departments'
import { getMaterials } from '@/api/materials'
import apiClient from '@/api/apiClient'
import {
  isExistingActivityOfType,
  type OnboardingTourId,
} from '@/config/onboardingTours'
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
 * Markiert Touren als erledigt, wenn die Daten bereits im Department existieren
 * (ohne dass die Spotlight-Tour durchgeklickt werden muss).
 */
export async function syncOnboardingTourAutoCompletion(
  profileId: string,
  departmentId: string
): Promise<OnboardingTourId[]> {
  if (!profileId || !departmentId) return []

  const [categoriesResult, membersResult, coachResult, materialsResult, activitiesResult] =
    await Promise.allSettled([
      getCategories(departmentId),
      getDepartmentMembers(departmentId),
      getJsMaterialDepartmentDefaults(departmentId),
      getMaterials(departmentId, { material_source: 'all', include_global_js: false }),
      apiClient.get<Array<{ type?: string; status?: string }>>('/api/activities', {
        params: { department_id: departmentId },
      }),
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

  // Esswaren-Tour ist optional: bereits vorhandene Esswaren = Tour erledigt
  if (materialsResult.status === 'fulfilled' && materialsResult.value.some((m) => m.is_food)) {
    markIfNeeded('material-food')
  }

  // Anlege-Touren: vorhandene Aktivität/Camp freigibt Folge-Touren (Packen & Co.)
  if (activitiesResult.status === 'fulfilled') {
    const activities = activitiesResult.value.data || []
    if (activities.some((a) => isExistingActivityOfType(a.type, a.status, 'activity'))) {
      markIfNeeded('activity-create')
    }
    if (activities.some((a) => isExistingActivityOfType(a.type, a.status, 'camp'))) {
      markIfNeeded('activity-camp-create')
    }
  }

  return marked
}
