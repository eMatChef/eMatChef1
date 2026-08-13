/**
 * Spotlight-Tour-Definitionen.
 * Bei UI-Änderungen an Targets (`data-onboarding`) → Touren + Version mitprüfen.
 * Siehe docs/onboarding/README.md § Wartung / Checkliste bei UI-Änderungen.
 */
import {
  isDepartmentBasicMemberRole,
  isDepartmentMwOrDcRole,
} from '@/composables/useDepartmentMemberRole'

export type OnboardingTourId =
  | 'profile-overview'
  | 'material-create'
  | 'activity-create'
  | 'activity-camp-create'
  | 'issue-return'
  | 'categories'
  | 'invite-users'
  | 'default-coach'

export type OnboardingTourCategory = 'start' | 'material' | 'activities' | 'settings'

export type OnboardingTourStepMode = 'info' | 'click' | 'waitFor'

/** Wer darf die Tour im Hub sehen? */
export type OnboardingTourAudience = 'mw' | 'member' | 'all'

export interface OnboardingTourStepDef {
  id: string
  target?: string
  /** Optional route override when entering this step (named route). */
  routeName?: string
  mode?: OnboardingTourStepMode
  titleKey: string
  bodyKey: string
}

export interface OnboardingTourDef {
  id: OnboardingTourId
  category: OnboardingTourCategory
  version: number
  routeName: string
  titleKey: string
  descriptionKey: string
  mdiIcon: string
  /** Tour-Karte zeigt den aktuellen User-Avatar statt MDI-Icon. */
  useUserAvatar?: boolean
  /** Default `mw` — nur Materialwart/Depchef. `member` = User/L1–L3 (+ MW). `all` = beides. */
  audience?: OnboardingTourAudience
  /** Tour nur anzeigen wenn Camp/Event anlegen erlaubt (z. B. activity-camp-create). */
  requiresCampCreate?: boolean
  steps: OnboardingTourStepDef[]
}

export const ONBOARDING_TOUR_QUERY = 'onboardingTour'
export const ONBOARDING_TOUR_STEP_QUERY = 'onboardingTourStep'

export const ONBOARDING_TOUR_CATEGORY_ORDER: OnboardingTourCategory[] = [
  'start',
  'activities',
  'material',
  'settings',
]

export const ONBOARDING_TOUR_CATEGORY_LABEL_KEYS: Record<OnboardingTourCategory, string> = {
  start: 'onboarding.tours.categories.start',
  material: 'onboarding.tours.categories.material',
  activities: 'onboarding.tours.categories.activities',
  settings: 'onboarding.tours.categories.settings',
}

export const ONBOARDING_TOURS: OnboardingTourDef[] = [
  {
    id: 'profile-overview',
    category: 'start',
    version: 3,
    audience: 'all',
    useUserAvatar: true,
    routeName: 'Dashboard',
    titleKey: 'onboarding.tours.profileOverview.title',
    descriptionKey: 'onboarding.tours.profileOverview.description',
    mdiIcon: 'mdi-account-circle-outline',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="sidebar-nav"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step1Title',
        bodyKey: 'onboarding.tours.profileOverview.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="nav-dashboard"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step2Title',
        bodyKey: 'onboarding.tours.profileOverview.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="nav-activities"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step3Title',
        bodyKey: 'onboarding.tours.profileOverview.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="nav-materials"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step4Title',
        bodyKey: 'onboarding.tours.profileOverview.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="nav-contacts"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step5Title',
        bodyKey: 'onboarding.tours.profileOverview.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="nav-tasks"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step6Title',
        bodyKey: 'onboarding.tours.profileOverview.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="nav-notifications"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step7Title',
        bodyKey: 'onboarding.tours.profileOverview.step7Body',
      },
      {
        id: '8',
        target: '[data-onboarding="nav-settings"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step8Title',
        bodyKey: 'onboarding.tours.profileOverview.step8Body',
      },
      {
        id: '9',
        target: '[data-onboarding="header-search"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step9Title',
        bodyKey: 'onboarding.tours.profileOverview.step9Body',
      },
      {
        id: '10',
        target: '[data-onboarding="header-user-menu"]',
        mode: 'click',
        titleKey: 'onboarding.tours.profileOverview.step10Title',
        bodyKey: 'onboarding.tours.profileOverview.step10Body',
      },
      {
        id: '11',
        target: '[data-onboarding="header-dept-switch"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.profileOverview.step11Title',
        bodyKey: 'onboarding.tours.profileOverview.step11Body',
      },
      {
        id: '12',
        target: '[data-onboarding="header-edit-profile"]',
        mode: 'click',
        titleKey: 'onboarding.tours.profileOverview.step12Title',
        bodyKey: 'onboarding.tours.profileOverview.step12Body',
      },
      {
        id: '13',
        target: '[data-onboarding="profile-identity"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.profileOverview.step13Title',
        bodyKey: 'onboarding.tours.profileOverview.step13Body',
      },
      {
        id: '14',
        target: '[data-onboarding="profile-personal"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step14Title',
        bodyKey: 'onboarding.tours.profileOverview.step14Body',
      },
      {
        id: '15',
        target: '[data-onboarding="profile-password"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step15Title',
        bodyKey: 'onboarding.tours.profileOverview.step15Body',
      },
      {
        id: '16',
        target: '[data-onboarding="profile-address"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step16Title',
        bodyKey: 'onboarding.tours.profileOverview.step16Body',
      },
      {
        id: '17',
        target: '[data-onboarding="profile-colors"]',
        mode: 'info',
        titleKey: 'onboarding.tours.profileOverview.step17Title',
        bodyKey: 'onboarding.tours.profileOverview.step17Body',
      },
      {
        id: '18',
        target: '[data-onboarding="profile-save"]',
        mode: 'click',
        titleKey: 'onboarding.tours.profileOverview.step18Title',
        bodyKey: 'onboarding.tours.profileOverview.step18Body',
      },
    ],
  },
  {
    id: 'material-create',
    category: 'material',
    version: 2,
    audience: 'mw',
    routeName: 'Materials',
    titleKey: 'onboarding.tours.materialCreate.title',
    descriptionKey: 'onboarding.tours.materialCreate.description',
    mdiIcon: 'mdi-package-variant-plus',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="material-new"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialCreate.step1Title',
        bodyKey: 'onboarding.tours.materialCreate.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="material-creation-individual"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialCreate.step2Title',
        bodyKey: 'onboarding.tours.materialCreate.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="material-wizard-general"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialCreate.step3Title',
        bodyKey: 'onboarding.tours.materialCreate.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="material-wizard-category"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialCreate.step4Title',
        bodyKey: 'onboarding.tours.materialCreate.step4Body',
      },
    ],
  },
  {
    id: 'activity-create',
    category: 'activities',
    version: 3,
    audience: 'all',
    routeName: 'Activities',
    titleKey: 'onboarding.tours.activityCreate.title',
    descriptionKey: 'onboarding.tours.activityCreate.description',
    mdiIcon: 'mdi-white-balance-sunny',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="activity-new"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCreate.step1Title',
        bodyKey: 'onboarding.tours.activityCreate.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="activity-type-activity"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCreate.step2Title',
        bodyKey: 'onboarding.tours.activityCreate.step2Body',
      },
      {
        id: '3',
        target: '#activity-create-grunddaten',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCreate.step3Title',
        bodyKey: 'onboarding.tours.activityCreate.step3Body',
      },
      {
        id: '4',
        target: '#activity-create-zeitraum',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCreate.step4Title',
        bodyKey: 'onboarding.tours.activityCreate.step4Body',
      },
    ],
  },
  {
    id: 'activity-camp-create',
    category: 'activities',
    version: 2,
    audience: 'all',
    requiresCampCreate: true,
    routeName: 'Activities',
    titleKey: 'onboarding.tours.activityCampCreate.title',
    descriptionKey: 'onboarding.tours.activityCampCreate.description',
    mdiIcon: 'mdi-home-variant-outline',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="activity-new"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCampCreate.step1Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="activity-type-camp"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCampCreate.step2Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step2Body',
      },
      {
        id: '3',
        target: '#activity-create-grunddaten',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step3Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="activity-camp-js-material"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step4Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="activity-wizard-next"]',
        mode: 'info',
        titleKey: 'onboarding.tours.activityCampCreate.step5Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step5Body',
      },
    ],
  },
  {
    id: 'issue-return',
    category: 'activities',
    version: 2,
    audience: 'mw',
    routeName: 'Activities',
    titleKey: 'onboarding.tours.issueReturn.title',
    descriptionKey: 'onboarding.tours.issueReturn.description',
    mdiIcon: 'mdi-swap-horizontal',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="activities-list-filters"]',
        mode: 'info',
        titleKey: 'onboarding.tours.issueReturn.step1Title',
        bodyKey: 'onboarding.tours.issueReturn.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="activities-packing-filter"]',
        mode: 'info',
        titleKey: 'onboarding.tours.issueReturn.step2Title',
        bodyKey: 'onboarding.tours.issueReturn.step2Body',
      },
      {
        id: '3',
        titleKey: 'onboarding.tours.issueReturn.step3Title',
        bodyKey: 'onboarding.tours.issueReturn.step3Body',
      },
      {
        id: '4',
        titleKey: 'onboarding.tours.issueReturn.step4Title',
        bodyKey: 'onboarding.tours.issueReturn.step4Body',
      },
    ],
  },
  {
    id: 'categories',
    category: 'settings',
    version: 2,
    audience: 'mw',
    routeName: 'SettingsCategories',
    titleKey: 'onboarding.tours.categoriesTour.title',
    descriptionKey: 'onboarding.tours.categoriesTour.description',
    mdiIcon: 'mdi-shape-outline',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="settings-category-new"]',
        mode: 'info',
        titleKey: 'onboarding.tours.categoriesTour.step1Title',
        bodyKey: 'onboarding.tours.categoriesTour.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="settings-category-list"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.categoriesTour.step2Title',
        bodyKey: 'onboarding.tours.categoriesTour.step2Body',
      },
    ],
  },
  {
    id: 'invite-users',
    category: 'settings',
    version: 2,
    audience: 'mw',
    routeName: 'SettingsUsers',
    titleKey: 'onboarding.tours.inviteUsers.title',
    descriptionKey: 'onboarding.tours.inviteUsers.description',
    mdiIcon: 'mdi-account-plus-outline',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="settings-user-add"]',
        mode: 'info',
        titleKey: 'onboarding.tours.inviteUsers.step1Title',
        bodyKey: 'onboarding.tours.inviteUsers.step1Body',
      },
    ],
  },
  {
    id: 'default-coach',
    category: 'settings',
    version: 1,
    audience: 'mw',
    routeName: 'SettingsActivities',
    titleKey: 'onboarding.tours.defaultCoach.title',
    descriptionKey: 'onboarding.tours.defaultCoach.description',
    mdiIcon: 'mdi-account-tie-outline',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="settings-js-coach"]',
        mode: 'info',
        titleKey: 'onboarding.tours.defaultCoach.step1Title',
        bodyKey: 'onboarding.tours.defaultCoach.step1Body',
      },
      {
        id: '2',
        target: '#js-default-coach-person-nr',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.defaultCoach.step2Title',
        bodyKey: 'onboarding.tours.defaultCoach.step2Body',
      },
    ],
  },
]

export function getOnboardingTour(id: string): OnboardingTourDef | undefined {
  return ONBOARDING_TOURS.find((tour) => tour.id === id)
}

export function getOnboardingTourStepIndex(tour: OnboardingTourDef, stepId: string): number {
  const index = tour.steps.findIndex((step) => step.id === stepId)
  return index >= 0 ? index : 0
}

export function getRouteNameForTourStep(
  tour: OnboardingTourDef,
  stepIndex: number
): string {
  const step = tour.steps[stepIndex]
  return step?.routeName ?? tour.routeName
}

export function isTourVisibleForRole(
  tour: OnboardingTourDef,
  role: string,
  options: { canCreateCamp?: boolean } = {}
): boolean {
  const audience = tour.audience ?? 'mw'
  const isMw = isDepartmentMwOrDcRole(role)
  const isMember = isDepartmentBasicMemberRole(role)

  if (audience === 'mw' && !isMw) return false
  if (audience === 'member' && !isMember && !isMw) return false
  if (audience === 'all' && !isMw && !isMember) return false

  if (tour.requiresCampCreate && !isMw && !options.canCreateCamp) return false
  return true
}

export function filterOnboardingToursForRole(
  role: string,
  options: { canCreateCamp?: boolean } = {}
): OnboardingTourDef[] {
  return ONBOARDING_TOURS.filter((tour) => isTourVisibleForRole(tour, role, options))
}

export function groupOnboardingToursByCategory(
  tours: OnboardingTourDef[] = ONBOARDING_TOURS
): Record<OnboardingTourCategory, OnboardingTourDef[]> {
  const grouped: Record<OnboardingTourCategory, OnboardingTourDef[]> = {
    start: [],
    material: [],
    activities: [],
    settings: [],
  }
  for (const tour of tours) {
    grouped[tour.category].push(tour)
  }
  return grouped
}
