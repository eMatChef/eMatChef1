export type OnboardingTourId =
  | 'material-create'
  | 'activity-create'
  | 'issue-return'
  | 'categories'
  | 'invite-users'

export interface OnboardingTourStepDef {
  id: string
  target?: string
  titleKey: string
  bodyKey: string
}

export interface OnboardingTourDef {
  id: OnboardingTourId
  version: number
  routeName: string
  titleKey: string
  descriptionKey: string
  mdiIcon: string
  steps: OnboardingTourStepDef[]
}

export const ONBOARDING_TOUR_QUERY = 'onboardingTour'
export const ONBOARDING_TOUR_STEP_QUERY = 'onboardingTourStep'

export const ONBOARDING_TOURS: OnboardingTourDef[] = [
  {
    id: 'material-create',
    version: 1,
    routeName: 'Materials',
    titleKey: 'onboarding.tours.materialCreate.title',
    descriptionKey: 'onboarding.tours.materialCreate.description',
    mdiIcon: 'mdi-package-variant-plus',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="material-new"]',
        titleKey: 'onboarding.tours.materialCreate.step1Title',
        bodyKey: 'onboarding.tours.materialCreate.step1Body',
      },
      {
        id: '2',
        titleKey: 'onboarding.tours.materialCreate.step2Title',
        bodyKey: 'onboarding.tours.materialCreate.step2Body',
      },
    ],
  },
  {
    id: 'activity-create',
    version: 1,
    routeName: 'Activities',
    titleKey: 'onboarding.tours.activityCreate.title',
    descriptionKey: 'onboarding.tours.activityCreate.description',
    mdiIcon: 'mdi-calendar-plus',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="activity-new"]',
        titleKey: 'onboarding.tours.activityCreate.step1Title',
        bodyKey: 'onboarding.tours.activityCreate.step1Body',
      },
    ],
  },
  {
    id: 'issue-return',
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
    version: 1,
    routeName: 'SettingsCategories',
    titleKey: 'onboarding.tours.categories.title',
    descriptionKey: 'onboarding.tours.categories.description',
    mdiIcon: 'mdi-shape-outline',
    steps: [
      {
        id: '1',
        titleKey: 'onboarding.tours.categories.step1Title',
        bodyKey: 'onboarding.tours.categories.step1Body',
      },
    ],
  },
  {
    id: 'invite-users',
    version: 1,
    routeName: 'SettingsUsers',
    titleKey: 'onboarding.tours.inviteUsers.title',
    descriptionKey: 'onboarding.tours.inviteUsers.description',
    mdiIcon: 'mdi-account-plus-outline',
    steps: [
      {
        id: '1',
        titleKey: 'onboarding.tours.inviteUsers.step1Title',
        bodyKey: 'onboarding.tours.inviteUsers.step1Body',
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
