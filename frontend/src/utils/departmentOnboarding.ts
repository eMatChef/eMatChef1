export interface DepartmentOnboardingCompleted {
  departmentAddress: boolean
  storageAddress: boolean
  settingsInitialized: boolean
  inviteUsers: boolean
  createGroup: boolean
  assignRoles: boolean
  categoriesConfigured: boolean
  storageConfigured: boolean
  materialCaptured: boolean
  miniIssueReturn: boolean
}

export type DepartmentOnboardingSkipped = Partial<Record<keyof DepartmentOnboardingCompleted, boolean>>

export interface DepartmentOnboardingState {
  currentStep: number
  completed: DepartmentOnboardingCompleted
  /** Vom Nutzer als «nicht nötig» markiert (nur empfohlene/optionale Schritte). */
  skipped?: DepartmentOnboardingSkipped
  updatedAt: string
}

export const ONBOARDING_TOTAL_STEPS = 10

export function buildOnboardingDoneKey(profileId: string, departmentId: string): string {
  return `onboarding_done_${profileId}_${departmentId}`
}

export function buildOnboardingPausedKey(profileId: string, departmentId: string): string {
  return `onboarding_paused_${profileId}_${departmentId}`
}

export function buildOnboardingStateKey(profileId: string, departmentId: string): string {
  return `onboarding_state_${profileId}_${departmentId}`
}

export function createDefaultOnboardingState(): DepartmentOnboardingState {
  return {
    currentStep: 1,
    completed: {
      departmentAddress: false,
      storageAddress: false,
      settingsInitialized: false,
      inviteUsers: false,
      createGroup: false,
      assignRoles: false,
      categoriesConfigured: false,
      storageConfigured: false,
      materialCaptured: false,
      miniIssueReturn: false,
    },
    updatedAt: new Date().toISOString(),
  }
}

export function readOnboardingState(profileId: string, departmentId: string): DepartmentOnboardingState {
  const key = buildOnboardingStateKey(profileId, departmentId)
  const raw = localStorage.getItem(key)
  if (!raw) {
    return createDefaultOnboardingState()
  }

  try {
    const parsed = JSON.parse(raw) as Partial<DepartmentOnboardingState>
    const parsedCompleted = (parsed.completed || {}) as Partial<DepartmentOnboardingState['completed']>
    // Migration: In der alten 7-Schritte-Version bedeutete miniIssueReturn faktisch "Material erfasst".
    // Wir uebertragen das in das neue Feld materialCaptured und starten miniIssueReturn standardmaessig wieder offen.
    const migratedMiniAsMaterial = parsedCompleted.miniIssueReturn === true
    return {
      ...createDefaultOnboardingState(),
      ...parsed,
      completed: {
        ...createDefaultOnboardingState().completed,
        ...parsedCompleted,
        materialCaptured: parsedCompleted.materialCaptured ?? migratedMiniAsMaterial,
        miniIssueReturn: parsedCompleted.materialCaptured === undefined && migratedMiniAsMaterial
          ? false
          : (parsedCompleted.miniIssueReturn ?? false),
      },
      skipped: {
        ...(parsed.skipped || {}),
      },
      currentStep: Math.min(Math.max(Number(parsed.currentStep || 1), 1), ONBOARDING_TOTAL_STEPS),
      updatedAt: parsed.updatedAt || new Date().toISOString(),
    }
  } catch {
    return createDefaultOnboardingState()
  }
}

export function writeOnboardingState(profileId: string, departmentId: string, state: DepartmentOnboardingState): void {
  const key = buildOnboardingStateKey(profileId, departmentId)
  localStorage.setItem(
    key,
    JSON.stringify({
      ...state,
      updatedAt: new Date().toISOString(),
    })
  )
}

export function isOnboardingDone(profileId: string, departmentId: string): boolean {
  return localStorage.getItem(buildOnboardingDoneKey(profileId, departmentId)) === '1'
}

export function markOnboardingDone(profileId: string, departmentId: string): void {
  localStorage.setItem(buildOnboardingDoneKey(profileId, departmentId), '1')
}

export function isOnboardingPaused(profileId: string, departmentId: string): boolean {
  return localStorage.getItem(buildOnboardingPausedKey(profileId, departmentId)) === '1'
}

export function markOnboardingPaused(profileId: string, departmentId: string): void {
  localStorage.setItem(buildOnboardingPausedKey(profileId, departmentId), '1')
}

export function clearOnboardingPaused(profileId: string, departmentId: string): void {
  localStorage.removeItem(buildOnboardingPausedKey(profileId, departmentId))
}
