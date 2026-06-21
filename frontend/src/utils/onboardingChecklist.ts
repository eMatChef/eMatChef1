import apiClient from '@/api/apiClient'
import { getAddresses } from '@/api/addresses'
import { getCategories } from '@/api/categories'
import { filterUserSelectableCategories } from '@/utils/repairPartsCategory'
import { getDepartmentSettings } from '@/api/departmentSettings'
import { getDepartmentMembers } from '@/api/departments'
import { getGroups } from '@/api/groups'
import { getMaterials } from '@/api/materials'
import { getStorageOverview } from '@/api/storageLocations'
import {
  ONBOARDING_TOTAL_STEPS,
  readOnboardingState,
  writeOnboardingState,
  type DepartmentOnboardingCompleted,
  type DepartmentOnboardingSkipped,
  type DepartmentOnboardingState,
} from '@/utils/departmentOnboarding'

export type OnboardingChecklistKey = keyof DepartmentOnboardingCompleted

export type OnboardingChecklistTier = 'required' | 'recommended' | 'optional'

export interface OnboardingChecklistItemDef {
  key: OnboardingChecklistKey
  labelKey: string
  descriptionKey: string
  wizardStep: number
  /** Vue-Router-Name unter /:departmentId/… */
  routeName: string
  routeQuery?: Record<string, string>
  tier: OnboardingChecklistTier
  /** Immer als erledigt zählen — Link bleibt zum Anpassen in den Einstellungen. */
  alwaysResolved?: boolean
}

export const ONBOARDING_CHECKLIST_ITEMS: OnboardingChecklistItemDef[] = [
  {
    key: 'departmentAddress',
    labelKey: 'onboarding.hub.items.departmentAddress.label',
    descriptionKey: 'onboarding.hub.items.departmentAddress.description',
    wizardStep: 1,
    routeName: 'SettingsMyDepartment',
    tier: 'required',
  },
  {
    key: 'settingsInitialized',
    labelKey: 'onboarding.hub.items.settingsInitialized.label',
    descriptionKey: 'onboarding.hub.items.settingsInitialized.description',
    wizardStep: 2,
    routeName: 'SettingsZeit',
    tier: 'recommended',
    alwaysResolved: true,
  },
  {
    key: 'createGroup',
    labelKey: 'onboarding.hub.items.createGroup.label',
    descriptionKey: 'onboarding.hub.items.createGroup.description',
    wizardStep: 3,
    routeName: 'SettingsGroups',
    tier: 'recommended',
  },
  {
    key: 'inviteUsers',
    labelKey: 'onboarding.hub.items.inviteUsers.label',
    descriptionKey: 'onboarding.hub.items.inviteUsers.description',
    wizardStep: 4,
    routeName: 'SettingsUsers',
    tier: 'recommended',
  },
  {
    key: 'assignRoles',
    labelKey: 'onboarding.hub.items.assignRoles.label',
    descriptionKey: 'onboarding.hub.items.assignRoles.description',
    wizardStep: 5,
    routeName: 'SettingsGroups',
    tier: 'recommended',
  },
  {
    key: 'categoriesConfigured',
    labelKey: 'onboarding.hub.items.categoriesConfigured.label',
    descriptionKey: 'onboarding.hub.items.categoriesConfigured.description',
    wizardStep: 6,
    routeName: 'SettingsCategories',
    tier: 'recommended',
  },
  {
    key: 'storageAddress',
    labelKey: 'onboarding.hub.items.storageAddress.label',
    descriptionKey: 'onboarding.hub.items.storageAddress.description',
    wizardStep: 7,
    routeName: 'SettingsMyDepartmentStorageLocations',
    tier: 'recommended',
  },
  {
    key: 'storageConfigured',
    labelKey: 'onboarding.hub.items.storageConfigured.label',
    descriptionKey: 'onboarding.hub.items.storageConfigured.description',
    wizardStep: 8,
    routeName: 'SettingsStorage',
    tier: 'recommended',
  },
  {
    key: 'materialCaptured',
    labelKey: 'onboarding.hub.items.materialCaptured.label',
    descriptionKey: 'onboarding.hub.items.materialCaptured.description',
    wizardStep: 9,
    routeName: 'Materials',
    tier: 'optional',
  },
  {
    key: 'miniIssueReturn',
    labelKey: 'onboarding.hub.items.miniIssueReturn.label',
    descriptionKey: 'onboarding.hub.items.miniIssueReturn.description',
    wizardStep: 10,
    routeName: 'Activities',
    tier: 'optional',
  },
]

export function resolveChecklistItemRoute(
  departmentId: string,
  item: Pick<OnboardingChecklistItemDef, 'routeName' | 'routeQuery'>
) {
  return {
    name: item.routeName,
    params: { departmentId },
    ...(item.routeQuery ? { query: item.routeQuery } : {}),
  }
}

export function isAlwaysResolvedChecklistItem(key: OnboardingChecklistKey): boolean {
  return ONBOARDING_CHECKLIST_ITEMS.some((item) => item.key === key && item.alwaysResolved === true)
}

export function canSkipChecklistItem(item: OnboardingChecklistItemDef): boolean {
  return item.tier !== 'required' && item.alwaysResolved !== true
}

export function isChecklistItemResolved(
  key: OnboardingChecklistKey,
  completed: DepartmentOnboardingCompleted,
  skipped: DepartmentOnboardingSkipped = {}
): boolean {
  if (isAlwaysResolvedChecklistItem(key)) return true
  return completed[key] === true || skipped[key] === true
}

export function isChecklistItemDone(
  key: OnboardingChecklistKey,
  completed: DepartmentOnboardingCompleted
): boolean {
  if (isAlwaysResolvedChecklistItem(key)) return true
  return completed[key] === true
}

export function isChecklistItemSkipped(
  key: OnboardingChecklistKey,
  skipped: DepartmentOnboardingSkipped = {}
): boolean {
  if (isAlwaysResolvedChecklistItem(key)) return false
  return skipped[key] === true
}

/** Offene Pflicht- und Empfehlungspunkte (für Sidebar-Badge). Optionale Schritte zählen nicht. */
export function countOpenChecklistItems(
  completed: DepartmentOnboardingCompleted,
  skipped: DepartmentOnboardingSkipped = {}
): number {
  return ONBOARDING_CHECKLIST_ITEMS.filter(
    (item) => item.tier !== 'optional' && !isChecklistItemResolved(item.key, completed, skipped)
  ).length
}

export function countResolvedChecklistItems(
  completed: DepartmentOnboardingCompleted,
  skipped: DepartmentOnboardingSkipped = {}
): number {
  return ONBOARDING_CHECKLIST_ITEMS.filter((item) =>
    isChecklistItemResolved(item.key, completed, skipped)
  ).length
}

/** @deprecated use countResolvedChecklistItems with skipped */
export function countDoneChecklistItems(completed: DepartmentOnboardingCompleted): number {
  return ONBOARDING_CHECKLIST_ITEMS.filter((item) => completed[item.key]).length
}

export function evaluateAddressCompletion(addresses: Array<{ type: string }>) {
  return {
    departmentAddress: isDepartmentVereinDataComplete(addresses),
    storageAddress: hasStorageAddress(addresses),
  }
}

export function evaluateSettingsInitialized(
  raw: Record<string, string>,
  vereinDataComplete: boolean
): boolean {
  return isSettingsInitialized(raw, vereinDataComplete)
}

function hasBillingOrGeneralAddress(addresses: Array<{ type: string }>): boolean {
  return addresses.some((a) => a.type === 'billing' || a.type === 'general')
}

function hasStorageAddress(addresses: Array<{ type: string }>): boolean {
  return addresses.some((a) => a.type === 'storage')
}

function isDepartmentVereinDataComplete(addresses: Array<{ type: string }>): boolean {
  return hasBillingOrGeneralAddress(addresses) && hasStorageAddress(addresses)
}

function isSettingsInitialized(raw: Record<string, string>, vereinDataComplete: boolean): boolean {
  if (String(raw['onboarding.phase1_settings_done'] || '0') === '1') return true
  // Sinnvolle Defaults sind bereits gesetzt — wenn Vereinsdaten stehen, reicht das.
  return vereinDataComplete
}

function groupsHaveMembers(groups: Array<{ member_count?: number; members?: unknown[] }>): boolean {
  return groups.some((group) => (group.member_count ?? group.members?.length ?? 0) > 0)
}

function applyAlwaysResolvedItems(state: DepartmentOnboardingState): void {
  for (const item of ONBOARDING_CHECKLIST_ITEMS) {
    if (!item.alwaysResolved) continue
    state.completed[item.key] = true
    if (state.skipped?.[item.key]) {
      const nextSkipped = { ...state.skipped }
      delete nextSkipped[item.key]
      state.skipped = Object.keys(nextSkipped).length > 0 ? nextSkipped : undefined
    }
  }
}

interface ActivityListRow {
  item_count?: number
}

export async function refreshOnboardingCompletionStatus(
  profileId: string,
  departmentId: string
): Promise<DepartmentOnboardingState> {
  const state = readOnboardingState(profileId, departmentId)
  const skipped = state.skipped || {}

  const [
    addressesResult,
    settingsResult,
    groupsResult,
    categoriesResult,
    storageResult,
    materialsResult,
    membersResult,
    activitiesResult,
  ] = await Promise.allSettled([
    getAddresses(departmentId),
    getDepartmentSettings(departmentId),
    getGroups(departmentId),
    getCategories(departmentId),
    getStorageOverview(departmentId),
    getMaterials(departmentId, { material_source: 'all', include_global_js: false }),
    getDepartmentMembers(departmentId),
    apiClient.get<ActivityListRow[]>('/api/activities', { params: { department_id: departmentId } }),
  ])

  if (addressesResult.status === 'fulfilled') {
    const addresses = addressesResult.value.addresses || []
    const vereinComplete = isDepartmentVereinDataComplete(addresses)
    state.completed.departmentAddress = vereinComplete
    state.completed.storageAddress = hasStorageAddress(addresses)
  }

  if (settingsResult.status === 'fulfilled') {
    const vereinComplete =
      addressesResult.status === 'fulfilled'
        ? isDepartmentVereinDataComplete(addressesResult.value.addresses || [])
        : state.completed.departmentAddress
    state.completed.settingsInitialized = isSettingsInitialized(settingsResult.value, vereinComplete)
  }

  if (groupsResult.status === 'fulfilled') {
    const groups = groupsResult.value
    state.completed.createGroup = groups.length > 0
    if (!skipped.assignRoles) {
      state.completed.assignRoles = groupsHaveMembers(groups)
    }
  }

  if (categoriesResult.status === 'fulfilled') {
    const categories = filterUserSelectableCategories(categoriesResult.value)
    state.completed.categoriesConfigured = categories.length > 0
  }

  if (storageResult.status === 'fulfilled') {
    state.completed.storageConfigured = (storageResult.value.racks?.length ?? 0) >= 1
  }

  if (materialsResult.status === 'fulfilled') {
    state.completed.materialCaptured = materialsResult.value.length >= 1
  }

  if (membersResult.status === 'fulfilled' && !skipped.inviteUsers) {
    state.completed.inviteUsers = membersResult.value.length > 1
  }

  if (activitiesResult.status === 'fulfilled' && !skipped.miniIssueReturn) {
    const activities = activitiesResult.value.data || []
    state.completed.miniIssueReturn = activities.some((activity) => (activity.item_count ?? 0) > 0)
  }

  applyAlwaysResolvedItems(state)

  const resolvedCount = countResolvedChecklistItems(state.completed, state.skipped || {})
  state.currentStep = Math.min(
    Math.max(state.currentStep, Math.max(1, resolvedCount)),
    ONBOARDING_TOTAL_STEPS
  )
  writeOnboardingState(profileId, departmentId, state)
  return state
}

export function skipChecklistItem(
  profileId: string,
  departmentId: string,
  key: OnboardingChecklistKey
): DepartmentOnboardingState {
  const item = ONBOARDING_CHECKLIST_ITEMS.find((entry) => entry.key === key)
  if (!item || !canSkipChecklistItem(item)) {
    return readOnboardingState(profileId, departmentId)
  }

  const state = readOnboardingState(profileId, departmentId)
  state.skipped = {
    ...(state.skipped || {}),
    [key]: true,
  }
  writeOnboardingState(profileId, departmentId, state)
  return state
}
