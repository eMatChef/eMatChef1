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
  | 'material-food'
  | 'activity-create'
  | 'activity-camp-create'
  | 'activity-approve'
  | 'issue-return'
  | 'issue-handoff'
  | 'activity-store'
  | 'activity-close'
  | 'workshop-overview'
  | 'categories'
  | 'invite-users'
  | 'default-coach'
  | 'department-details'
  | 'org-overview'
  | 'suborg-overview'

export type OnboardingTourCategory = 'start' | 'material' | 'activities' | 'settings' | 'admin'

export type OnboardingTourStepMode = 'info' | 'click' | 'waitFor'

export interface OnboardingTourCompletionCta {
  labelKey: string
  /** stay = aktuelle Seite; helpTours = Touren-Hub */
  action: 'stay' | 'helpTours'
}

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
  /**
   * Zusätzlicher Klick-Trigger (z. B. «+» im waitFor-Schritt): bei Klick weiter / zu advanceToStepId.
   */
  advanceOnClick?: string
  /**
   * Wenn dieses Ziel im DOM erscheint → automatisch weiter (z. B. Modal nach «+»).
   */
  advanceWhenVisible?: string
  /** Ziel-Schritt-ID bei advanceOnClick / advanceWhenVisible (sonst next). */
  advanceToStepId?: string
  /** Ziel an den oberen Viewport-Rand scrollen (statt nearest). */
  scroll?: 'start' | 'nearest'
  /**
   * Spotlight darf fast die volle Viewport-Höhe nutzen (z. B. Lager-Übersicht mit Regalen/Fächern).
   * Sonst wird das Loch auf ~42% Höhe begrenzt.
   */
  tallSpotlight?: boolean
  /**
   * Spotlight auf (nahezu) die ganze Seite — kein Höhen-Deckel; Karte typisch mit cardPlacement bottom-right.
   */
  fullPageSpotlight?: boolean
  /**
   * Tour-Karte fest positionieren (z. B. bei sehr grossem Spotlight, damit sie nicht abgeschnitten wird).
   */
  cardPlacement?: 'auto' | 'bottom-right' | 'bottom-center' | 'right-middle' | 'bottom-left'
  /**
   * Vor «Weiter»: diese Elemente nacheinander klicken (z. B. Modal Abbrechen/Schliessen),
   * damit der nächste Schritt sichtbar wird.
   */
  dismissOnNext?: string[]
  /**
   * Nach dismissOnNext warten, bis dieses Ziel im DOM ist (z. B. nächster Wizard-Schritt).
   */
  waitVisibleOnNext?: string
  /**
   * Nach advanceOnClick: warten bis Selector weg ist, optional weitere dismiss-Klicks,
   * dann erst weiter (z. B. nach «Erstellen» Modal schliessen + Eventstandort-Dialog).
   */
  advanceOnClickWaitGone?: string
  advanceOnClickThenDismiss?: string[]
  /**
   * Beim Betreten des Schritts einmal klicken (z. B. Status-Filter «Eingereicht»).
   */
  clickOnEnter?: string
  /**
   * Beim Betreten Fokus auf dieses Input (z. B. Materialsuche).
   */
  focusOnEnter?: string
  /**
   * Text in focusOnEnter (oder target) tippen und Input-Event feuern — öffnet z. B. Material-Dropdown.
   */
  typeIntoOnEnter?: string
  titleKey: string
  bodyKey: string
  /** Optional: i18n-Key auf string[] — strukturierte Bullet-Punkte unter dem Body. */
  bodyItemsKey?: string
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
  /** Optionale Abschluss-Buttons (letzter Schritt). */
  completionCtas?: OnboardingTourCompletionCta[]
  /**
   * Optional-Tour: Durchklicken ohne Pflicht-Aktion möglich.
   * Letzter Schritt: «Tour erledigt» statt «Tour beenden» (markiert als erledigt).
   */
  browseComplete?: boolean
  /**
   * Tour ist empfohlen im Block (z. B. Freigabe bei Sandbox-Auto-Approve).
   * Wird im Hub als überspringbar gekennzeichnet; blockiert nachfolgende Touren nicht.
   */
  optional?: boolean
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
    version: 11,
    audience: 'mw',
    requiresCompletedTours: ['categories'],
    routeName: 'Materials',
    titleKey: 'onboarding.tours.materialCreate.title',
    descriptionKey: 'onboarding.tours.materialCreate.description',
    mdiIcon: 'mdi-package-variant-plus',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="nav-materials"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialCreate.step1Title',
        bodyKey: 'onboarding.tours.materialCreate.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="material-new"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialCreate.step2Title',
        bodyKey: 'onboarding.tours.materialCreate.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="material-creation-individual"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialCreate.step3Title',
        bodyKey: 'onboarding.tours.materialCreate.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="material-wizard-article-name"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialCreate.step4Title',
        bodyKey: 'onboarding.tours.materialCreate.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="material-wizard-type-toggles"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialCreate.step5Title',
        bodyKey: 'onboarding.tours.materialCreate.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="material-wizard-category"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialCreate.step6Title',
        bodyKey: 'onboarding.tours.materialCreate.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="material-wizard-tracking"]',
        mode: 'click',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialCreate.step7Title',
        bodyKey: 'onboarding.tours.materialCreate.step7Body',
      },
      {
        id: '8',
        target: '[data-onboarding="material-wizard-stock-unit"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialCreate.step8Title',
        bodyKey: 'onboarding.tours.materialCreate.step8Body',
      },
      {
        id: '9',
        target: '[data-onboarding="material-wizard-stock-qty"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialCreate.step9Title',
        bodyKey: 'onboarding.tours.materialCreate.step9Body',
      },
      {
        id: '10',
        target: '[data-onboarding="material-wizard-stock-purchase-date"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialCreate.step10Title',
        bodyKey: 'onboarding.tours.materialCreate.step10Body',
      },
      {
        id: '11',
        target: '[data-onboarding="material-wizard-stock-purchase-price"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialCreate.step11Title',
        bodyKey: 'onboarding.tours.materialCreate.step11Body',
      },
      {
        id: '12',
        target: '[data-onboarding="material-wizard-stock-codes"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialCreate.step12Title',
        bodyKey: 'onboarding.tours.materialCreate.step12Body',
      },
      {
        id: '13',
        target: '[data-onboarding="material-wizard-stock-split"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialCreate.step13Title',
        bodyKey: 'onboarding.tours.materialCreate.step13Body',
      },
      {
        id: '14',
        target: '[data-onboarding="material-wizard-stock-storage"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialCreate.step14Title',
        bodyKey: 'onboarding.tours.materialCreate.step14Body',
      },
      {
        id: '15',
        target: '[data-onboarding="material-wizard-details"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialCreate.step15Title',
        bodyKey: 'onboarding.tours.materialCreate.step15Body',
        bodyItemsKey: 'onboarding.tours.materialCreate.step15Items',
      },
      {
        id: '16',
        target: '[data-onboarding="material-wizard-rental"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialCreate.step16Title',
        bodyKey: 'onboarding.tours.materialCreate.step16Body',
      },
      {
        id: '17',
        target: '[data-onboarding="material-wizard-submit"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialCreate.step17Title',
        bodyKey: 'onboarding.tours.materialCreate.step17Body',
      },
    ],
  },
  {
    id: 'material-consumable',
    category: 'material',
    version: 9,
    audience: 'mw',
    requiresCompletedTours: ['material-create'],
    routeName: 'Materials',
    titleKey: 'onboarding.tours.materialConsumable.title',
    descriptionKey: 'onboarding.tours.materialConsumable.description',
    mdiIcon: 'mdi-candle',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="nav-materials"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialConsumable.step1Title',
        bodyKey: 'onboarding.tours.materialConsumable.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="material-new"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialConsumable.step2Title',
        bodyKey: 'onboarding.tours.materialConsumable.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="material-creation-individual"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialConsumable.step3Title',
        bodyKey: 'onboarding.tours.materialConsumable.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="material-wizard-article-name"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialConsumable.step4Title',
        bodyKey: 'onboarding.tours.materialConsumable.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="material-wizard-toggle-consumable"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialConsumable.step5Title',
        bodyKey: 'onboarding.tours.materialConsumable.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="material-wizard-category"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialConsumable.step6Title',
        bodyKey: 'onboarding.tours.materialConsumable.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="material-wizard-tracking"]',
        mode: 'click',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialConsumable.step7Title',
        bodyKey: 'onboarding.tours.materialConsumable.step7Body',
      },
      {
        id: '8',
        target: '[data-onboarding="material-wizard-stock-unit"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialConsumable.step8Title',
        bodyKey: 'onboarding.tours.materialConsumable.step8Body',
      },
      {
        id: '9',
        target: '[data-onboarding="material-wizard-stock-qty"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialConsumable.step9Title',
        bodyKey: 'onboarding.tours.materialConsumable.step9Body',
      },
      {
        id: '10',
        target: '[data-onboarding="material-wizard-consumable-pricing"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialConsumable.step10Title',
        bodyKey: 'onboarding.tours.materialConsumable.step10Body',
      },
      {
        id: '11',
        target: '[data-onboarding="material-wizard-submit"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialConsumable.step11Title',
        bodyKey: 'onboarding.tours.materialConsumable.step11Body',
      },
    ],
  },
  {
    id: 'material-food',
    category: 'material',
    version: 3,
    audience: 'mw',
    requiresCompletedTours: ['material-create'],
    routeName: 'Materials',
    titleKey: 'onboarding.tours.materialFood.title',
    descriptionKey: 'onboarding.tours.materialFood.description',
    mdiIcon: 'mdi-food-apple-outline',
    browseComplete: true,
    completionCtas: [
      {
        labelKey: 'onboarding.tours.materialFood.ctaStay',
        action: 'stay',
      },
      {
        labelKey: 'onboarding.tours.materialFood.ctaMoreTours',
        action: 'helpTours',
      },
    ],
    steps: [
      {
        id: '1',
        target: '[data-onboarding="nav-materials"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialFood.step1Title',
        bodyKey: 'onboarding.tours.materialFood.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="material-new"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialFood.step2Title',
        bodyKey: 'onboarding.tours.materialFood.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="material-creation-individual"]',
        mode: 'click',
        titleKey: 'onboarding.tours.materialFood.step3Title',
        bodyKey: 'onboarding.tours.materialFood.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="material-wizard-article-name"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialFood.step4Title',
        bodyKey: 'onboarding.tours.materialFood.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="material-wizard-toggle-food"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialFood.step5Title',
        bodyKey: 'onboarding.tours.materialFood.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="material-wizard-category"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialFood.step6Title',
        bodyKey: 'onboarding.tours.materialFood.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="material-wizard-food-lots"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialFood.step7Title',
        bodyKey: 'onboarding.tours.materialFood.step7Body',
      },
      {
        id: '8',
        target: '[data-onboarding="material-wizard-consumable-pricing"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.materialFood.step8Title',
        bodyKey: 'onboarding.tours.materialFood.step8Body',
      },
      {
        id: '9',
        target: '[data-onboarding="material-wizard-submit"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.materialFood.step9Title',
        bodyKey: 'onboarding.tours.materialFood.step9Body',
      },
    ],
  },
  {
    id: 'activity-create',
    category: 'activities',
    version: 14,
    audience: 'all',
    requiresCompletedTours: ['material-create', 'material-consumable'],
    routeName: 'Activities',
    titleKey: 'onboarding.tours.activityCreate.title',
    descriptionKey: 'onboarding.tours.activityCreate.description',
    mdiIcon: 'mdi-white-balance-sunny',
    browseComplete: true,
    completionCtas: [
      {
        labelKey: 'onboarding.tours.activityCreate.ctaStay',
        action: 'stay',
      },
      {
        labelKey: 'onboarding.tours.activityCreate.ctaMoreTours',
        action: 'helpTours',
      },
    ],
    steps: [
      {
        id: '1',
        target: '[data-onboarding="nav-activities"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCreate.step1Title',
        bodyKey: 'onboarding.tours.activityCreate.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="activity-new"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCreate.step2Title',
        bodyKey: 'onboarding.tours.activityCreate.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="activity-type-activity"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCreate.step3Title',
        bodyKey: 'onboarding.tours.activityCreate.step3Body',
      },
      {
        id: '4',
        target: '#activity-create-grunddaten',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCreate.step4Title',
        bodyKey: 'onboarding.tours.activityCreate.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="activity-create-zeitraum"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCreate.step5Title',
        bodyKey: 'onboarding.tours.activityCreate.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="activity-create-material"]',
        mode: 'waitFor',
        scroll: 'start',
        cardPlacement: 'bottom-right',
        tallSpotlight: true,
        focusOnEnter: '[data-onboarding="activity-create-material-search"]',
        typeIntoOnEnter: 'Onboarding',
        titleKey: 'onboarding.tours.activityCreate.step6Title',
        bodyKey: 'onboarding.tours.activityCreate.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="activity-wizard-submit"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCreate.step7Title',
        bodyKey: 'onboarding.tours.activityCreate.step7Body',
      },
      {
        id: '8',
        target: '[data-onboarding="activity-detail-period"]',
        mode: 'waitFor',
        scroll: 'start',
        cardPlacement: 'bottom-right',
        tallSpotlight: true,
        titleKey: 'onboarding.tours.activityCreate.step8Title',
        bodyKey: 'onboarding.tours.activityCreate.step8Body',
      },
      {
        id: '9',
        target: '[data-onboarding="activity-detail-root"]',
        mode: 'waitFor',
        scroll: 'start',
        cardPlacement: 'bottom-right',
        tallSpotlight: true,
        fullPageSpotlight: true,
        titleKey: 'onboarding.tours.activityCreate.step9Title',
        bodyKey: 'onboarding.tours.activityCreate.step9Body',
      },
    ],
  },
  {
    id: 'activity-camp-create',
    category: 'activities',
    version: 12,
    audience: 'all',
    requiresCampCreate: true,
    requiresCompletedTours: ['material-create', 'material-consumable'],
    routeName: 'Activities',
    titleKey: 'onboarding.tours.activityCampCreate.title',
    descriptionKey: 'onboarding.tours.activityCampCreate.description',
    mdiIcon: 'mdi-home-variant-outline',
    browseComplete: true,
    completionCtas: [
      {
        labelKey: 'onboarding.tours.activityCampCreate.ctaStay',
        action: 'stay',
      },
      {
        labelKey: 'onboarding.tours.activityCampCreate.ctaMoreTours',
        action: 'helpTours',
      },
    ],
    steps: [
      {
        id: '1',
        target: '[data-onboarding="nav-activities"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCampCreate.step1Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="activity-new"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCampCreate.step2Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="activity-type-camp"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCampCreate.step3Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="activity-camp-name-group"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step4Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="activity-camp-venue"]',
        mode: 'waitFor',
        advanceOnClick: '[data-onboarding="activity-venue-add"]',
        advanceWhenVisible: '[data-onboarding="activity-venue-create"]',
        advanceToStepId: '7',
        cardPlacement: 'right-middle',
        titleKey: 'onboarding.tours.activityCampCreate.step5Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="activity-venue-add"]',
        mode: 'click',
        advanceWhenVisible: '[data-onboarding="activity-venue-create"]',
        advanceToStepId: '7',
        titleKey: 'onboarding.tours.activityCampCreate.step6Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="activity-venue-create"]',
        mode: 'waitFor',
        cardPlacement: 'right-middle',
        tallSpotlight: true,
        fullPageSpotlight: true,
        advanceOnClick: '[data-onboarding="activity-venue-set-pin"]',
        titleKey: 'onboarding.tours.activityCampCreate.step7Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step7Body',
      },
      {
        id: '8',
        target: '[data-onboarding="activity-venue-delivery-add"]',
        mode: 'waitFor',
        advanceOnClick: '[data-onboarding="activity-venue-delivery-add"]',
        advanceWhenVisible: '[data-onboarding="activity-venue-delivery-modal"]',
        advanceToStepId: '9',
        dismissOnNext: ['[data-onboarding="activity-venue-delivery-add"]'],
        waitVisibleOnNext: '[data-onboarding="activity-venue-delivery-modal"]',
        titleKey: 'onboarding.tours.activityCampCreate.step8Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step8Body',
      },
      {
        id: '9',
        target: '[data-onboarding="activity-venue-delivery-modal"]',
        mode: 'waitFor',
        cardPlacement: 'right-middle',
        tallSpotlight: true,
        fullPageSpotlight: true,
        advanceOnClick: '[data-onboarding="activity-venue-delivery-submit"]',
        advanceOnClickWaitGone: '[data-onboarding="activity-venue-delivery-modal"]',
        advanceOnClickThenDismiss: ['[data-onboarding="activity-venue-create-close"]'],
        advanceToStepId: '10',
        dismissOnNext: [
          '[data-onboarding="activity-venue-delivery-cancel"]',
          '[data-onboarding="activity-venue-delivery-discard"]',
          '[data-onboarding="activity-venue-create-close"]',
        ],
        titleKey: 'onboarding.tours.activityCampCreate.step9Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step9Body',
      },
      {
        id: '10',
        target: '[data-onboarding="activity-camp-js-material"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step10Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step10Body',
      },
      {
        id: '11',
        target: '[data-onboarding="activity-camp-invite"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step11Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step11Body',
      },
      {
        id: '12',
        target: '[data-onboarding="activity-wizard-next"]',
        mode: 'waitFor',
        dismissOnNext: ['[data-onboarding="activity-wizard-next"]'],
        waitVisibleOnNext: '#activity-usage-block-s',
        titleKey: 'onboarding.tours.activityCampCreate.step12Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step12Body',
      },
      {
        id: '13',
        target: '#activity-usage-block-s',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step13Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step13Body',
      },
      {
        id: '14',
        target: '[data-onboarding="activity-create-planning"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step14Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step14Body',
      },
      {
        id: '15',
        target: '[data-onboarding="activity-camp-participants"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step15Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step15Body',
      },
      {
        id: '16',
        target: '[data-onboarding="activity-wizard-next"]',
        mode: 'waitFor',
        dismissOnNext: ['[data-onboarding="activity-wizard-next"]'],
        waitVisibleOnNext: '[data-onboarding="activity-create-material"]',
        titleKey: 'onboarding.tours.activityCampCreate.step16Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step16Body',
      },
      {
        id: '17',
        target: '[data-onboarding="activity-create-material"]',
        mode: 'waitFor',
        scroll: 'start',
        cardPlacement: 'bottom-right',
        tallSpotlight: true,
        focusOnEnter: '[data-onboarding="activity-create-material-search"]',
        typeIntoOnEnter: 'Onboarding',
        advanceOnClick: '[data-onboarding="activity-wizard-next"]',
        advanceWhenVisible: '[data-onboarding="activity-camp-overview"]',
        advanceToStepId: '18',
        dismissOnNext: ['[data-onboarding="activity-wizard-next"]'],
        waitVisibleOnNext: '[data-onboarding="activity-camp-overview"]',
        titleKey: 'onboarding.tours.activityCampCreate.step17Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step17Body',
      },
      {
        id: '18',
        target: '[data-onboarding="activity-camp-overview"]',
        mode: 'waitFor',
        cardPlacement: 'right-middle',
        tallSpotlight: true,
        titleKey: 'onboarding.tours.activityCampCreate.step18Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step18Body',
      },
      {
        id: '19',
        target: '[data-onboarding="activity-wizard-submit"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityCampCreate.step19Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step19Body',
      },
      {
        id: '20',
        target: '[data-onboarding="activity-detail-period"]',
        mode: 'waitFor',
        scroll: 'start',
        cardPlacement: 'bottom-right',
        tallSpotlight: true,
        titleKey: 'onboarding.tours.activityCampCreate.step20Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step20Body',
      },
      {
        id: '21',
        target: '[data-onboarding="activity-detail-overview-tab"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step21Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step21Body',
      },
      {
        id: '22',
        target: '[data-onboarding="activity-detail-material-tab"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step22Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step22Body',
      },
      {
        id: '23',
        target: '[data-onboarding="activity-detail-vehicles-tab"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step23Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step23Body',
      },
      {
        id: '24',
        target: '[data-onboarding="activity-detail-js-tab"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step24Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step24Body',
      },
      {
        id: '25',
        target: '[data-onboarding="activity-detail-submit"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step25Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step25Body',
      },
      {
        id: '26',
        target: '[data-onboarding="activity-detail-approve"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.activityCampCreate.step26Title',
        bodyKey: 'onboarding.tours.activityCampCreate.step26Body',
      },
    ],
  },
  {
    id: 'activity-approve',
    category: 'activities',
    version: 8,
    audience: 'leader',
    requiresCampCreate: true,
    requiresCompletedTours: ['activity-camp-create'],
    optional: true,
    routeName: 'Activities',
    titleKey: 'onboarding.tours.activityApprove.title',
    descriptionKey: 'onboarding.tours.activityApprove.description',
    mdiIcon: 'mdi-check-decagram-outline',
    steps: [
      {
        id: '1',
        target: '[data-onboarding="activities-submitted-filter"]',
        mode: 'waitFor',
        clickOnEnter: '[data-onboarding="activities-submitted-filter"]',
        titleKey: 'onboarding.tours.activityApprove.step1Title',
        bodyKey: 'onboarding.tours.activityApprove.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="activities-submitted-row"]',
        mode: 'waitFor',
        dismissOnNext: ['[data-onboarding="activities-submitted-row"]'],
        waitVisibleOnNext: '[data-onboarding="activity-detail-root"]',
        advanceOnClick: '[data-onboarding="activities-submitted-row"]',
        advanceWhenVisible: '[data-onboarding="activity-detail-root"]',
        advanceToStepId: '3',
        titleKey: 'onboarding.tours.activityApprove.step2Title',
        bodyKey: 'onboarding.tours.activityApprove.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="activity-detail-period"]',
        mode: 'waitFor',
        scroll: 'start',
        cardPlacement: 'bottom-center',
        tallSpotlight: true,
        titleKey: 'onboarding.tours.activityApprove.step3Title',
        bodyKey: 'onboarding.tours.activityApprove.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="activity-detail-material"]',
        mode: 'waitFor',
        clickOnEnter: '[data-onboarding="activity-detail-material-tab"]',
        scroll: 'start',
        cardPlacement: 'bottom-center',
        tallSpotlight: true,
        titleKey: 'onboarding.tours.activityApprove.step4Title',
        bodyKey: 'onboarding.tours.activityApprove.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="activity-detail-approve"]',
        mode: 'click',
        titleKey: 'onboarding.tours.activityApprove.step5Title',
        bodyKey: 'onboarding.tours.activityApprove.step5Body',
      },
    ],
  },
  {
    id: 'issue-return',
    category: 'activities',
    version: 8,
    audience: 'mw',
    requiresAnyCompletedTours: ['activity-create', 'activity-camp-create'],
    requiresApprovedActivityOrCamp: true,
    routeName: 'Activities',
    titleKey: 'onboarding.tours.issueReturn.title',
    descriptionKey: 'onboarding.tours.issueReturn.description',
    mdiIcon: 'mdi-package-variant-closed',
    browseComplete: true,
    completionCtas: [
      {
        labelKey: 'onboarding.tours.issueReturn.ctaStay',
        action: 'stay',
      },
      {
        labelKey: 'onboarding.tours.issueReturn.ctaMoreTours',
        action: 'helpTours',
      },
    ],
    steps: [
      {
        id: '1',
        target: '[data-onboarding="activities-packing-filter"]',
        mode: 'waitFor',
        clickOnEnter: '[data-onboarding="activities-packing-filter"]',
        titleKey: 'onboarding.tours.issueReturn.step1Title',
        bodyKey: 'onboarding.tours.issueReturn.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="activities-packing-row"]',
        mode: 'waitFor',
        dismissOnNext: ['[data-onboarding="activities-packing-row"]'],
        waitVisibleOnNext: '[data-onboarding="activity-detail-root"]',
        advanceOnClick: '[data-onboarding="activities-packing-row"]',
        advanceWhenVisible: '[data-onboarding="activity-detail-root"]',
        advanceToStepId: '3',
        titleKey: 'onboarding.tours.issueReturn.step2Title',
        bodyKey: 'onboarding.tours.issueReturn.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="activity-detail-period"]',
        mode: 'waitFor',
        scroll: 'start',
        cardPlacement: 'bottom-center',
        tallSpotlight: true,
        titleKey: 'onboarding.tours.issueReturn.step3Title',
        bodyKey: 'onboarding.tours.issueReturn.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="activity-detail-material"]',
        mode: 'waitFor',
        clickOnEnter: '[data-onboarding="activity-detail-material-tab"]',
        scroll: 'start',
        cardPlacement: 'bottom-center',
        tallSpotlight: true,
        titleKey: 'onboarding.tours.issueReturn.step4Title',
        bodyKey: 'onboarding.tours.issueReturn.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="activity-detail-accept-pack"]',
        mode: 'click',
        titleKey: 'onboarding.tours.issueReturn.step5Title',
        bodyKey: 'onboarding.tours.issueReturn.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="activity-pack-stepper"]',
        mode: 'waitFor',
        clickOnEnter: '[data-onboarding="activity-detail-packs-tab"]',
        scroll: 'start',
        cardPlacement: 'bottom-center',
        titleKey: 'onboarding.tours.issueReturn.step6Title',
        bodyKey: 'onboarding.tours.issueReturn.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="activity-pack-scan"]',
        mode: 'waitFor',
        clickOnEnter: '[data-onboarding="activity-detail-packs-tab"]',
        scroll: 'start',
        cardPlacement: 'bottom-center',
        tallSpotlight: true,
        titleKey: 'onboarding.tours.issueReturn.step7Title',
        bodyKey: 'onboarding.tours.issueReturn.step7Body',
      },
      {
        id: '8',
        target: '[data-onboarding="activity-pack-list"]',
        mode: 'waitFor',
        clickOnEnter: '[data-onboarding="activity-detail-packs-tab"]',
        scroll: 'start',
        cardPlacement: 'bottom-center',
        tallSpotlight: true,
        titleKey: 'onboarding.tours.issueReturn.step8Title',
        bodyKey: 'onboarding.tours.issueReturn.step8Body',
      },
      {
        id: '9',
        mode: 'info',
        titleKey: 'onboarding.tours.issueReturn.step9Title',
        bodyKey: 'onboarding.tours.issueReturn.step9Body',
      },
    ],
  },
  {
    id: 'issue-handoff',
    category: 'activities',
    version: 6,
    audience: 'mw',
    requiresCompletedTours: ['issue-return'],
    routeName: 'Activities',
    titleKey: 'onboarding.tours.issueHandoff.title',
    descriptionKey: 'onboarding.tours.issueHandoff.description',
    mdiIcon: 'mdi-swap-horizontal',
    browseComplete: true,
    completionCtas: [
      {
        labelKey: 'onboarding.tours.issueHandoff.ctaStay',
        action: 'stay',
      },
      {
        labelKey: 'onboarding.tours.issueHandoff.ctaMoreTours',
        action: 'helpTours',
      },
    ],
    steps: [
      {
        id: '1',
        target: '[data-onboarding="activities-packing-filter"]',
        mode: 'waitFor',
        clickOnEnter: '[data-onboarding="activities-packing-filter"]',
        titleKey: 'onboarding.tours.issueHandoff.step1Title',
        bodyKey: 'onboarding.tours.issueHandoff.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="activities-packing-row"]',
        mode: 'waitFor',
        dismissOnNext: ['[data-onboarding="activities-packing-row"]'],
        waitVisibleOnNext: '[data-onboarding="activity-detail-root"]',
        advanceOnClick: '[data-onboarding="activities-packing-row"]',
        advanceWhenVisible: '[data-onboarding="activity-detail-root"]',
        advanceToStepId: '3',
        titleKey: 'onboarding.tours.issueHandoff.step2Title',
        bodyKey: 'onboarding.tours.issueHandoff.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="activity-pack-step-issue"]',
        mode: 'click',
        clickOnEnter: '[data-onboarding="activity-detail-packs-tab"]',
        // «Weiter» ohne Klick: trotzdem Ausleihen öffnen
        dismissOnNext: ['[data-onboarding="activity-pack-step-issue"]'],
        scroll: 'start',
        cardPlacement: 'bottom-center',
        titleKey: 'onboarding.tours.issueHandoff.step3Title',
        bodyKey: 'onboarding.tours.issueHandoff.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="activity-pack-issue-actions"]',
        mode: 'waitFor',
        clickOnEnter: '[data-onboarding="activity-pack-step-issue"]',
        scroll: 'start',
        cardPlacement: 'bottom-center',
        tallSpotlight: true,
        titleKey: 'onboarding.tours.issueHandoff.step4Title',
        bodyKey: 'onboarding.tours.issueHandoff.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="activity-pack-step-return"]',
        mode: 'click',
        clickOnEnter: '[data-onboarding="activity-pack-step-return"]',
        dismissOnNext: ['[data-onboarding="activity-pack-step-return"]'],
        scroll: 'start',
        cardPlacement: 'bottom-center',
        titleKey: 'onboarding.tours.issueHandoff.step5Title',
        bodyKey: 'onboarding.tours.issueHandoff.step5Body',
      },
    ],
  },
  {
    id: 'activity-store',
    category: 'activities',
    version: 1,
    audience: 'mw',
    requiresCompletedTours: ['issue-handoff'],
    browseComplete: true,
    routeName: 'Activities',
    titleKey: 'onboarding.tours.activityStore.title',
    descriptionKey: 'onboarding.tours.activityStore.description',
    mdiIcon: 'mdi-warehouse',
    completionCtas: [
      {
        labelKey: 'onboarding.tours.activityStore.ctaStay',
        action: 'stay',
      },
      {
        labelKey: 'onboarding.tours.activityStore.ctaMoreTours',
        action: 'helpTours',
      },
    ],
    steps: [
      {
        id: '1',
        target: '[data-onboarding="activities-list-filters"]',
        mode: 'info',
        titleKey: 'onboarding.tours.activityStore.step1Title',
        bodyKey: 'onboarding.tours.activityStore.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="activities-packing-filter"]',
        mode: 'info',
        titleKey: 'onboarding.tours.activityStore.step2Title',
        bodyKey: 'onboarding.tours.activityStore.step2Body',
      },
      {
        id: '3',
        mode: 'info',
        titleKey: 'onboarding.tours.activityStore.step3Title',
        bodyKey: 'onboarding.tours.activityStore.step3Body',
      },
      {
        id: '4',
        mode: 'info',
        titleKey: 'onboarding.tours.activityStore.step4Title',
        bodyKey: 'onboarding.tours.activityStore.step4Body',
      },
      {
        id: '5',
        mode: 'info',
        titleKey: 'onboarding.tours.activityStore.step5Title',
        bodyKey: 'onboarding.tours.activityStore.step5Body',
      },
    ],
  },
  {
    id: 'activity-close',
    category: 'activities',
    version: 2,
    audience: 'dc',
    requiresCompletedTours: ['activity-store'],
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
    version: 8,
    audience: 'mw',
    routeName: 'SettingsCategories',
    titleKey: 'onboarding.tours.categoriesTour.title',
    descriptionKey: 'onboarding.tours.categoriesTour.description',
    mdiIcon: 'mdi-shape-outline',
    completionCtas: [
      {
        labelKey: 'onboarding.tours.categoriesTour.ctaCreate',
        action: 'stay',
      },
      {
        labelKey: 'onboarding.tours.categoriesTour.ctaMoreTours',
        action: 'helpTours',
      },
    ],
    steps: [
      {
        id: '1',
        target: '[data-onboarding="nav-settings"]',
        mode: 'click',
        titleKey: 'onboarding.tours.categoriesTour.step1Title',
        bodyKey: 'onboarding.tours.categoriesTour.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="settings-nav-categories"]',
        mode: 'click',
        titleKey: 'onboarding.tours.categoriesTour.step2Title',
        bodyKey: 'onboarding.tours.categoriesTour.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="settings-category-templates"]',
        mode: 'click',
        titleKey: 'onboarding.tours.categoriesTour.step3Title',
        bodyKey: 'onboarding.tours.categoriesTour.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="settings-category-templates-dialog"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.categoriesTour.step4Title',
        bodyKey: 'onboarding.tours.categoriesTour.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="settings-category-new"]',
        mode: 'click',
        titleKey: 'onboarding.tours.categoriesTour.step5Title',
        bodyKey: 'onboarding.tours.categoriesTour.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="category-modal-fields"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.categoriesTour.step6Title',
        bodyKey: 'onboarding.tours.categoriesTour.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="category-modal-actions"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.categoriesTour.step7Title',
        bodyKey: 'onboarding.tours.categoriesTour.step7Body',
      },
      {
        id: '8',
        target: '[data-onboarding="settings-category-actions"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.categoriesTour.step8Title',
        bodyKey: 'onboarding.tours.categoriesTour.step8Body',
      },
      {
        id: '9',
        mode: 'info',
        titleKey: 'onboarding.tours.categoriesTour.step9Title',
        bodyKey: 'onboarding.tours.categoriesTour.step9Body',
      },
    ],
  },
  {
    id: 'department-details',
    category: 'settings',
    version: 6,
    audience: 'mw',
    routeName: 'SettingsMyDepartment',
    titleKey: 'onboarding.tours.departmentDetails.title',
    descriptionKey: 'onboarding.tours.departmentDetails.description',
    mdiIcon: 'mdi-map-marker-radius-outline',
    completionCtas: [
      {
        labelKey: 'onboarding.tours.departmentDetails.ctaStay',
        action: 'stay',
      },
      {
        labelKey: 'onboarding.tours.departmentDetails.ctaMoreTours',
        action: 'helpTours',
      },
    ],
    steps: [
      {
        id: '1',
        target: '[data-onboarding="nav-settings"]',
        mode: 'click',
        titleKey: 'onboarding.tours.departmentDetails.step1Title',
        bodyKey: 'onboarding.tours.departmentDetails.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="settings-nav-my-department"]',
        mode: 'click',
        titleKey: 'onboarding.tours.departmentDetails.step2Title',
        bodyKey: 'onboarding.tours.departmentDetails.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="settings-dept-storage-accordion"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.departmentDetails.step3Title',
        bodyKey: 'onboarding.tours.departmentDetails.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="settings-dept-storage-panel"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.departmentDetails.step4Title',
        bodyKey: 'onboarding.tours.departmentDetails.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="settings-nav-storage"]',
        mode: 'click',
        titleKey: 'onboarding.tours.departmentDetails.step5Title',
        bodyKey: 'onboarding.tours.departmentDetails.step5Body',
      },
      {
        id: '6',
        routeName: 'SettingsStorage',
        target: '[data-onboarding="settings-storage-new-rack"]',
        mode: 'click',
        scroll: 'start',
        titleKey: 'onboarding.tours.departmentDetails.step6Title',
        bodyKey: 'onboarding.tours.departmentDetails.step6Body',
      },
      {
        id: '7',
        routeName: 'SettingsStorage',
        target: '[data-onboarding="settings-storage-rack-modal"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.departmentDetails.step7Title',
        bodyKey: 'onboarding.tours.departmentDetails.step7Body',
      },
      {
        id: '8',
        routeName: 'SettingsStorage',
        target: '[data-onboarding="settings-storage-overview"]',
        mode: 'waitFor',
        scroll: 'start',
        tallSpotlight: true,
        cardPlacement: 'bottom-right',
        titleKey: 'onboarding.tours.departmentDetails.step8Title',
        bodyKey: 'onboarding.tours.departmentDetails.step8Body',
      },
      {
        id: '9',
        routeName: 'SettingsMyDepartment',
        target: '[data-onboarding="settings-dept-billing-accordion"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.departmentDetails.step9Title',
        bodyKey: 'onboarding.tours.departmentDetails.step9Body',
      },
      {
        id: '10',
        routeName: 'SettingsMyDepartment',
        target: '[data-onboarding="settings-dept-billing-panel"]',
        mode: 'waitFor',
        scroll: 'start',
        titleKey: 'onboarding.tours.departmentDetails.step10Title',
        bodyKey: 'onboarding.tours.departmentDetails.step10Body',
      },
      {
        id: '11',
        routeName: 'SettingsMyDepartment',
        mode: 'info',
        titleKey: 'onboarding.tours.departmentDetails.step11Title',
        bodyKey: 'onboarding.tours.departmentDetails.step11Body',
      },
    ],
  },
  {
    id: 'invite-users',
    category: 'settings',
    version: 7,
    audience: 'mw',
    routeName: 'SettingsMyDepartment',
    titleKey: 'onboarding.tours.inviteUsers.title',
    descriptionKey: 'onboarding.tours.inviteUsers.description',
    mdiIcon: 'mdi-account-plus-outline',
    completionCtas: [
      {
        labelKey: 'onboarding.tours.inviteUsers.ctaStay',
        action: 'stay',
      },
      {
        labelKey: 'onboarding.tours.inviteUsers.ctaMoreTours',
        action: 'helpTours',
      },
    ],
    steps: [
      {
        id: '1',
        target: '[data-onboarding="nav-settings"]',
        mode: 'click',
        titleKey: 'onboarding.tours.inviteUsers.step1Title',
        bodyKey: 'onboarding.tours.inviteUsers.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="settings-nav-my-department"]',
        mode: 'click',
        titleKey: 'onboarding.tours.inviteUsers.step2Title',
        bodyKey: 'onboarding.tours.inviteUsers.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="settings-user-search"]',
        mode: 'info',
        scroll: 'start',
        titleKey: 'onboarding.tours.inviteUsers.step3Title',
        bodyKey: 'onboarding.tours.inviteUsers.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="settings-user-add"]',
        mode: 'click',
        titleKey: 'onboarding.tours.inviteUsers.step4Title',
        bodyKey: 'onboarding.tours.inviteUsers.step4Body',
      },
      {
        id: '5',
        target: '[data-onboarding="settings-user-add-search"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.inviteUsers.step5Title',
        bodyKey: 'onboarding.tours.inviteUsers.step5Body',
      },
      {
        id: '6',
        target: '[data-onboarding="settings-user-add-actions"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.inviteUsers.step6Title',
        bodyKey: 'onboarding.tours.inviteUsers.step6Body',
      },
      {
        id: '7',
        target: '[data-onboarding="settings-user-edit"]',
        mode: 'click',
        scroll: 'start',
        titleKey: 'onboarding.tours.inviteUsers.step7Title',
        bodyKey: 'onboarding.tours.inviteUsers.step7Body',
      },
      {
        id: '8',
        target: '[data-onboarding="settings-user-edit-profile"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.inviteUsers.step8Title',
        bodyKey: 'onboarding.tours.inviteUsers.step8Body',
      },
      {
        id: '9',
        target: '[data-onboarding="settings-user-edit-address"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.inviteUsers.step9Title',
        bodyKey: 'onboarding.tours.inviteUsers.step9Body',
      },
      {
        id: '10',
        mode: 'info',
        titleKey: 'onboarding.tours.inviteUsers.step10Title',
        bodyKey: 'onboarding.tours.inviteUsers.step10Body',
      },
    ],
  },
  {
    id: 'default-coach',
    category: 'settings',
    version: 2,
    audience: 'mw',
    routeName: 'SettingsMyDepartment',
    titleKey: 'onboarding.tours.defaultCoach.title',
    descriptionKey: 'onboarding.tours.defaultCoach.description',
    mdiIcon: 'mdi-account-tie-outline',
    completionCtas: [
      {
        labelKey: 'onboarding.tours.defaultCoach.ctaStay',
        action: 'stay',
      },
      {
        labelKey: 'onboarding.tours.defaultCoach.ctaMoreTours',
        action: 'helpTours',
      },
    ],
    steps: [
      {
        id: '1',
        target: '[data-onboarding="nav-settings"]',
        mode: 'click',
        titleKey: 'onboarding.tours.defaultCoach.step1Title',
        bodyKey: 'onboarding.tours.defaultCoach.step1Body',
      },
      {
        id: '2',
        target: '[data-onboarding="settings-nav-my-department"]',
        mode: 'click',
        titleKey: 'onboarding.tours.defaultCoach.step2Title',
        bodyKey: 'onboarding.tours.defaultCoach.step2Body',
      },
      {
        id: '3',
        target: '[data-onboarding="settings-user-edit"]',
        mode: 'click',
        scroll: 'start',
        titleKey: 'onboarding.tours.defaultCoach.step3Title',
        bodyKey: 'onboarding.tours.defaultCoach.step3Body',
      },
      {
        id: '4',
        target: '[data-onboarding="settings-user-edit-coach"]',
        mode: 'waitFor',
        titleKey: 'onboarding.tours.defaultCoach.step4Title',
        bodyKey: 'onboarding.tours.defaultCoach.step4Body',
      },
      {
        id: '5',
        mode: 'info',
        titleKey: 'onboarding.tours.defaultCoach.step5Title',
        bodyKey: 'onboarding.tours.defaultCoach.step5Body',
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

/** Status ab «freigegeben» / packbar — Packen/Ausgabe möglich. */
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

/**
 * Typ «Aktivität»: nach Einreichen (submitted) direkt packbar.
 * Camp/Event: erst ab freigegeben (approved).
 */
export function isActivityReadyForIssueTour(
  type: string | null | undefined,
  status: string | null | undefined
): boolean {
  const t = String(type || '').toLowerCase()
  if (t !== 'activity' && t !== 'camp') return false
  const s = status === 'issued' ? 'at_event' : String(status || '').toLowerCase()
  if (t === 'activity' && s === 'submitted') return true
  return ONBOARDING_ISSUE_READY_STATUSES.has(s)
}

/**
 * Liste in der Pack-Tour: Einträge, die «Annehmen & Packen» anbieten.
 * Camp/Event bevorzugt (approved); Aktivität auch (submitted oder approved).
 */
export function isAcceptPackTourListCandidate(
  type: string | null | undefined,
  status: string | null | undefined
): boolean {
  const t = String(type || '').toLowerCase()
  const s = String(status || '').toLowerCase()
  if (t === 'camp' || t === 'event') return s === 'approved'
  if (t === 'activity') return s === 'approved' || s === 'submitted'
  return false
}

/** Liste in der Handoff-Tour: gepackte / am Event (Ausgabe & Retour). */
export function isHandoffTourListCandidate(
  type: string | null | undefined,
  status: string | null | undefined
): boolean {
  const t = String(type || '').toLowerCase()
  const s = String(status || '').toLowerCase()
  if (t !== 'activity' && t !== 'camp' && t !== 'event') return false
  return s === 'packed' || s === 'transport_out' || s === 'at_event'
}

/** Sortier-Rang für Pack-Tour: Camp vor Event vor Aktivität. */
export function acceptPackTourTypeRank(type: string | null | undefined): number {
  const t = String(type || '').toLowerCase()
  if (t === 'camp') return 0
  if (t === 'event') return 1
  if (t === 'activity') return 2
  return 9
}

/** Nicht stornierte Aktivität vorhanden (für Auto-Complete der Anlege-Touren). */
export function isExistingActivityOfType(
  type: string | null | undefined,
  status: string | null | undefined,
  expectedType: 'activity' | 'camp'
): boolean {
  const t = String(type || '').toLowerCase()
  if (t !== expectedType) return false
  const s = String(status || '').toLowerCase()
  return s !== 'cancelled'
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
