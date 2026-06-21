export type OnboardingTourId =
  | 'material-create'
  | 'activity-create'
  | 'activity-camp-create'
  | 'issue-return'
  | 'categories'
  | 'invite-users'
  | 'default-coach'

export type OnboardingTourCategory = 'material' | 'activities' | 'settings'

export type OnboardingTourStepMode = 'info' | 'click' | 'waitFor'

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
  steps: OnboardingTourStepDef[]
}

export const ONBOARDING_TOUR_QUERY = 'onboardingTour'
export const ONBOARDING_TOUR_STEP_QUERY = 'onboardingTourStep'

export const ONBOARDING_TOUR_CATEGORY_ORDER: OnboardingTourCategory[] = [
  'material',
  'activities',
  'settings',
]

export const ONBOARDING_TOUR_CATEGORY_LABEL_KEYS: Record<OnboardingTourCategory, string> = {
  material: 'onboarding.tours.categories.material',
  activities: 'onboarding.tours.categories.activities',
  settings: 'onboarding.tours.categories.settings',
}

export const ONBOARDING_TOURS: OnboardingTourDef[] = [
  {
    id: 'material-create',
    category: 'material',
    version: 2,
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
    version: 2,
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
    ],
  },
  {
    id: 'activity-camp-create',
    category: 'activities',
    version: 1,
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
        titleKey: 'onboarding.tours.activityCampCreate.step4Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step4Body',
      },
    ],
  },
  {
    id: 'issue-return',
    category: 'activities',
    version: 1,
    routeName: 'Activities',
    titleKey: 'onboarding.tours.issueReturn.title',
    descriptionKey: 'onboarding.tours.issueReturn.description',
    mdiIcon: 'mdi-swap-horizontal',
    steps: [
      {
        id: '1',
        titleKey: 'onboarding.tours.issueReturn.step1Title',
        bodyKey: 'onboarding.tours.issueReturn.step1Body',
      },
    ],
  },
  {
    id: 'categories',
    category: 'settings',
    version: 2,
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

export function groupOnboardingToursByCategory(): Record<
  OnboardingTourCategory,
  OnboardingTourDef[]
> {
  const grouped: Record<OnboardingTourCategory, OnboardingTourDef[]> = {
    material: [],
    activities: [],
    settings: [],
  }
  for (const tour of ONBOARDING_TOURS) {
    grouped[tour.category].push(tour)
  }
  return grouped
}
