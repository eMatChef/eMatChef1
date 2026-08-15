/**
 * Spotlight-Tour-Definitionen.
 * Bei UI-Änderungen an Targets (`data-onboarding`) → Touren + Version mitprüfen.
 * Siehe docs/onboarding/README.md § Wartung / Checkliste bei UI-Änderungen.
 */
import {
  isDepartmentBasicMemberRole,
  isDepartmentDcRole,
  isDepartmentLeaderRole,
  isDepartmentMwOrDcRole,
} from '@/composables/useDepartmentMemberRole'

export type OnboardingTourId =
  | 'profile-overview'
  | 'material-create'
  | 'material-consumable'
  | 'activity-create'
  | 'activity-camp-create'
  | 'activity-approve'
  | 'issue-return'
  | 'issue-handoff'
  | 'activity-close'
  | 'workshop-overview'
  | 'categories'
  | 'invite-users'
  | 'default-coach'
  | 'org-overview'
  | 'suborg-overview'

export type OnboardingTourCategory = 'start' | 'material' | 'activities' | 'settings' | 'admin'

export type OnboardingTourStepMode = 'info' | 'click' | 'waitFor'

/**
 * Wer darf die Tour im Hub sehen?
 * Höhere Rollen sehen immer auch die Touren darunter.
 * - all / member: Basis
 * - leader: L1–L3 / Gruppenchef ★ — und darüber MW/DC
 * - mw: Materialwart + Depchef
 * - dc: nur Depchef (und Superadmin)
 * - org / suborg: wie bisher
 */
export type OnboardingTourAudience = 'mw' | 'dc' | 'leader' | 'member' | 'all' | 'org' | 'suborg'

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
  /** Default `mw`. */
  audience?: OnboardingTourAudience
  /** Tour nur anzeigen wenn Camp/Event anlegen erlaubt (z. B. activity-camp-create). */
  requiresCampCreate?: boolean
  /**
   * Tour erst starten, wenn diese Touren abgeschlossen sind.
   * Prereqs, die für die Rolle ohnehin unsichtbar sind, werden ignoriert.
   */
  requiresCompletedTours?: OnboardingTourId[]
  /**
   * Mindestens eine dieser Touren muss erledigt sein (OR).
   * Unsichtbare Touren für die Rolle zählen nicht als Pflicht.
   */
  requiresAnyCompletedTours?: OnboardingTourId[]
  /**
   * Packen: mind. eine Aktivität vom Typ activity oder camp
   * im Status freigegeben (approved) oder weiter im Pack-Flow.
   */
  requiresApprovedActivityOrCamp?: boolean
  steps: OnboardingTourStepDef[]
}

export type OnboardingTourFilterOptions = {
  canCreateCamp?: boolean
  /** User ist Gruppenchef (★) in mind. einer Gruppe dieses Departments. */
  isGroupLeader?: boolean
}

export const ONBOARDING_TOUR_QUERY = 'onboardingTour'
export const ONBOARDING_TOUR_STEP_QUERY = 'onboardingTourStep'

export const ONBOARDING_TOUR_CATEGORY_ORDER: OnboardingTourCategory[] = [
  'start',
  'settings',
  'material',
  'activities',
  'admin',
]

export const ONBOARDING_TOUR_CATEGORY_LABEL_KEYS: Record<OnboardingTourCategory, string> = {
  start: 'onboarding.tours.categories.start',
  material: 'onboarding.tours.categories.material',
  activities: 'onboarding.tours.categories.activities',
  settings: 'onboarding.tours.categories.settings',
  admin: 'onboarding.tours.categories.admin',
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
    version: 5,
    audience: 'mw',
    requiresCompletedTours: ['categories'],
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
        target: '[data-onboarding="material-wizard-article-name"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialCreate.step3Title',
        bodyKey: 'onboarding.tours.materialCreate.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="material-wizard-type-toggles"]',
        mode: 'info',
        titleKey: 'onboarding.tours.materialCreate.step4Title',
        bodyKey: 'onboarding.tours.materialCreate.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="material-wizard-category"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialCreate.step5Title',
        bodyKey: 'onboarding.tours.materialCreate.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="material-wizard-tracking"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialCreate.step6Title',
        bodyKey: 'onboarding.tours.materialCreate.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="material-wizard-stock"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialCreate.step7Title',
        bodyKey: 'onboarding.tours.materialCreate.step7Body',
      },
      {
        id: '8',
        target: '[data-onboarding="material-wizard-details"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialCreate.step8Title',
        bodyKey: 'onboarding.tours.materialCreate.step8Body',
      },
      {
        id: '9',
        target: '[data-onboarding="material-wizard-submit"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialCreate.step9Title',
        bodyKey: 'onboarding.tours.materialCreate.step9Body',
      },
    ],
  },
  {
    id: 'material-consumable',
    category: 'material',
    version: 2,
    audience: 'mw',
    requiresCompletedTours: ['material-create'],
    routeName: 'Materials',
    titleKey: 'onboarding.tours.materialConsumable.title',
    descriptionKey: 'onboarding.tours.materialConsumable.description',
    mdiIcon: 'mdi-candle',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="material-new"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialConsumable.step1Title',
        bodyKey: 'onboarding.tours.materialConsumable.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="material-creation-individual"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialConsumable.step2Title',
        bodyKey: 'onboarding.tours.materialConsumable.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="material-wizard-type-toggles"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialConsumable.step3Title',
        bodyKey: 'onboarding.tours.materialConsumable.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="material-wizard-details"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialConsumable.step4Title',
        bodyKey: 'onboarding.tours.materialConsumable.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="nav-activities"]',
        mode: 'info',
        titleKey: 'onboarding.tours.materialConsumable.step5Title',
        bodyKey: 'onboarding.tours.materialConsumable.step5Body',
      },
      {
        id: '6',
        mode: 'info',
        titleKey: 'onboarding.tours.materialConsumable.step6Title',
        bodyKey: 'onboarding.tours.materialConsumable.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="nav-accounting"]',
        mode: 'info',
        titleKey: 'onboarding.tours.materialConsumable.step7Title',
        bodyKey: 'onboarding.tours.materialConsumable.step7Body',
      },
    ],
  },
  {
    id: 'activity-create',
    category: 'activities',
    version: 7,
    audience: 'all',
    requiresCompletedTours: ['material-create', 'material-consumable'],
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
        target: '[data-onboarding="activity-create-zeitraum"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCreate.step4Title',
        bodyKey: 'onboarding.tours.activityCreate.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="activity-create-material"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCreate.step5Title',
        bodyKey: 'onboarding.tours.activityCreate.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="activity-wizard-submit"]',
        mode: 'info',
        titleKey: 'onboarding.tours.activityCreate.step6Title',
        bodyKey: 'onboarding.tours.activityCreate.step6Body',
      },
    ],
  },
  {
    id: 'activity-camp-create',
    category: 'activities',
    version: 3,
    audience: 'all',
    requiresCampCreate: true,
    requiresCompletedTours: ['material-create', 'material-consumable'],
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
    id: 'activity-approve',
    category: 'activities',
    version: 2,
    audience: 'leader',
    requiresCampCreate: true,
    requiresCompletedTours: ['activity-camp-create'],
    routeName: 'Activities',
    titleKey: 'onboarding.tours.activityApprove.title',
    descriptionKey: 'onboarding.tours.activityApprove.description',
    mdiIcon: 'mdi-check-decagram-outline',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="activities-list-filters"]',
        mode: 'info',
        titleKey: 'onboarding.tours.activityApprove.step1Title',
        bodyKey: 'onboarding.tours.activityApprove.step1Body',
      },
      {
        id: '2',
        mode: 'info',
        titleKey: 'onboarding.tours.activityApprove.step2Title',
        bodyKey: 'onboarding.tours.activityApprove.step2Body',
      },
      {
        id: '3',
        mode: 'info',
        titleKey: 'onboarding.tours.activityApprove.step3Title',
        bodyKey: 'onboarding.tours.activityApprove.step3Body',
      },
    ],
  },
  {
    id: 'issue-return',
    category: 'activities',
    version: 4,
    audience: 'mw',
    requiresAnyCompletedTours: ['activity-create', 'activity-camp-create'],
    requiresApprovedActivityOrCamp: true,
    routeName: 'Activities',
    titleKey: 'onboarding.tours.issueReturn.title',
    descriptionKey: 'onboarding.tours.issueReturn.description',
    mdiIcon: 'mdi-package-variant-closed',
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
        mode: 'info',
        titleKey: 'onboarding.tours.issueReturn.step3Title',
        bodyKey: 'onboarding.tours.issueReturn.step3Body',
      },
      {
        id: '4',
        mode: 'info',
        titleKey: 'onboarding.tours.issueReturn.step4Title',
        bodyKey: 'onboarding.tours.issueReturn.step4Body',
      },
    ],
  },
  {
    id: 'issue-handoff',
    category: 'activities',
    version: 1,
    audience: 'mw',
    requiresCompletedTours: ['issue-return'],
    routeName: 'Activities',
    titleKey: 'onboarding.tours.issueHandoff.title',
    descriptionKey: 'onboarding.tours.issueHandoff.description',
    mdiIcon: 'mdi-swap-horizontal',
    steps: [
      {
        id: '1',
        mode: 'info',
        titleKey: 'onboarding.tours.issueHandoff.step1Title',
        bodyKey: 'onboarding.tours.issueHandoff.step1Body',
      },
      {
        id: '2',
        mode: 'info',
        titleKey: 'onboarding.tours.issueHandoff.step2Title',
        bodyKey: 'onboarding.tours.issueHandoff.step2Body',
      },
      {
        id: '3',
        mode: 'info',
        titleKey: 'onboarding.tours.issueHandoff.step3Title',
        bodyKey: 'onboarding.tours.issueHandoff.step3Body',
      },
      {
        id: '4',
        mode: 'info',
        titleKey: 'onboarding.tours.issueHandoff.step4Title',
        bodyKey: 'onboarding.tours.issueHandoff.step4Body',
      },
    ],
  },
  {
    id: 'activity-close',
    category: 'activities',
    version: 1,
    audience: 'dc',
    requiresCompletedTours: ['issue-handoff'],
    routeName: 'Activities',
    titleKey: 'onboarding.tours.activityClose.title',
    descriptionKey: 'onboarding.tours.activityClose.description',
    mdiIcon: 'mdi-archive-check-outline',
    steps: [
      {
        id: '1',
        mode: 'info',
        titleKey: 'onboarding.tours.activityClose.step1Title',
        bodyKey: 'onboarding.tours.activityClose.step1Body',
      },
      {
        id: '2',
        mode: 'info',
        titleKey: 'onboarding.tours.activityClose.step2Title',
        bodyKey: 'onboarding.tours.activityClose.step2Body',
      },
      {
        id: '3',
        mode: 'info',
        titleKey: 'onboarding.tours.activityClose.step3Title',
        bodyKey: 'onboarding.tours.activityClose.step3Body',
      },
    ],
  },
  {
    id: 'workshop-overview',
    category: 'activities',
    version: 2,
    audience: 'mw',
    requiresCompletedTours: ['issue-handoff'],
    routeName: 'Workshop',
    titleKey: 'onboarding.tours.workshopOverview.title',
    descriptionKey: 'onboarding.tours.workshopOverview.description',
    mdiIcon: 'mdi-hammer-wrench',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="nav-workshop"]',
        mode: 'info',
        titleKey: 'onboarding.tours.workshopOverview.step1Title',
        bodyKey: 'onboarding.tours.workshopOverview.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="workshop-list"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.workshopOverview.step2Title',
        bodyKey: 'onboarding.tours.workshopOverview.step2Body',
      },
      {
        id: '3',
        mode: 'info',
        titleKey: 'onboarding.tours.workshopOverview.step3Title',
        bodyKey: 'onboarding.tours.workshopOverview.step3Body',
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
  {
    id: 'org-overview',
    category: 'admin',
    version: 1,
    audience: 'org',
    routeName: 'DepartmentVerwaltungOrganisations',
    titleKey: 'onboarding.tours.orgOverview.title',
    descriptionKey: 'onboarding.tours.orgOverview.description',
    mdiIcon: 'mdi-office-building-outline',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="admin-orgs"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.orgOverview.step1Title',
        bodyKey: 'onboarding.tours.orgOverview.step1Body',
      },
      {
        id: '2',
        mode: 'info',
        titleKey: 'onboarding.tours.orgOverview.step2Title',
        bodyKey: 'onboarding.tours.orgOverview.step2Body',
      },
      {
        id: '3',
        mode: 'info',
        titleKey: 'onboarding.tours.orgOverview.step3Title',
        bodyKey: 'onboarding.tours.orgOverview.step3Body',
      },
    ],
  },
  {
    id: 'suborg-overview',
    category: 'admin',
    version: 1,
    audience: 'suborg',
    routeName: 'DepartmentVerwaltungOrganisations',
    titleKey: 'onboarding.tours.suborgOverview.title',
    descriptionKey: 'onboarding.tours.suborgOverview.description',
    mdiIcon: 'mdi-sitemap-outline',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="admin-orgs"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.suborgOverview.step1Title',
        bodyKey: 'onboarding.tours.suborgOverview.step1Body',
      },
      {
        id: '2',
        mode: 'info',
        titleKey: 'onboarding.tours.suborgOverview.step2Title',
        bodyKey: 'onboarding.tours.suborgOverview.step2Body',
      },
      {
        id: '3',
        mode: 'info',
        titleKey: 'onboarding.tours.suborgOverview.step3Title',
        bodyKey: 'onboarding.tours.suborgOverview.step3Body',
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
  options: OnboardingTourFilterOptions = {}
): boolean {
  const audience = tour.audience ?? 'mw'
  const normalized = String(role || '').toLowerCase().trim()
  const isMw = isDepartmentMwOrDcRole(role)
  const isMember = isDepartmentBasicMemberRole(role)
  const isLeaderRole = isDepartmentLeaderRole(role) || !!options.isGroupLeader
  const isDc = isDepartmentDcRole(role)
  const isOrg = ['org', 'organisationschef'].includes(normalized)
  const isSuborg = ['sub', 'suborgchef'].includes(normalized)
  const isSuperadmin = ['sa', 'superadmin'].includes(normalized)

  if (audience === 'org') return isOrg || isSuperadmin
  if (audience === 'suborg') return isSuborg || isOrg || isSuperadmin

  let allowed = false
  if (audience === 'dc') {
    allowed = isDc || isSuperadmin
  } else if (audience === 'leader') {
    // L1–L3 / ★ — MW/DC sehen sie ebenfalls (höhere Rolle)
    allowed = isLeaderRole || isMw || isSuperadmin
  } else if (audience === 'mw') {
    allowed = isMw || isSuperadmin
  } else if (audience === 'member') {
    allowed = isMember || isMw || isSuperadmin
  } else if (audience === 'all') {
    allowed = isMw || isMember || isSuperadmin
  }

  if (!allowed) return false
  // Auch für leader/dc: Camp-Touren nur mit Camp-Recht (MW/SA immer)
  if (tour.requiresCampCreate && !isMw && !isSuperadmin && !options.canCreateCamp) return false
  return true
}

/**
 * Fehlende Vorgänger-Touren (nur solche, die die Rolle überhaupt sehen kann).
 * `requiresCompletedTours` = alle nötig; `requiresAnyCompletedTours` = mindestens eine.
 */
export function getMissingTourPrerequisites(
  tour: OnboardingTourDef,
  role: string,
  completedTourIds: ReadonlySet<OnboardingTourId>,
  options: OnboardingTourFilterOptions = {}
): OnboardingTourId[] {
  const missingAll = (tour.requiresCompletedTours ?? []).filter((prereqId) => {
    const prereq = getOnboardingTour(prereqId)
    if (!prereq) return false
    if (!isTourVisibleForRole(prereq, role, options)) return false
    return !completedTourIds.has(prereqId)
  })

  const anyRequired = (tour.requiresAnyCompletedTours ?? []).filter((prereqId) => {
    const prereq = getOnboardingTour(prereqId)
    if (!prereq) return false
    return isTourVisibleForRole(prereq, role, options)
  })

  const missingAny: OnboardingTourId[] = []
  if (anyRequired.length > 0) {
    const anyDone = anyRequired.some((id) => completedTourIds.has(id))
    if (!anyDone) {
      missingAny.push(anyRequired[0]!)
    }
  }

  return [...missingAll, ...missingAny]
}

/** Status ab «freigegeben» — Packen/Ausgabe möglich. */
export const ONBOARDING_ISSUE_READY_STATUSES = new Set([
  'approved',
  'packing',
  'packed',
  'transport_out',
  'at_event',
  'transport_back',
  'returned',
  'storing',
  'completed',
])

export function isActivityReadyForIssueTour(
  type: string | null | undefined,
  status: string | null | undefined
): boolean {
  const t = String(type || '').toLowerCase()
  if (t !== 'activity' && t !== 'camp') return false
  const s = status === 'issued' ? 'at_event' : String(status || '').toLowerCase()
  return ONBOARDING_ISSUE_READY_STATUSES.has(s)
}

export function filterOnboardingToursForRole(
  role: string,
  options: OnboardingTourFilterOptions = {}
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
    admin: [],
  }
  for (const tour of tours) {
    grouped[tour.category].push(tour)
  }
  return grouped
}
