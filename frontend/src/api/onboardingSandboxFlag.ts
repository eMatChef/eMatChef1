/** Module flag: API-Client hängt include_onboarding_sandbox an, solange eine Aktivitäten-Tour läuft. */
let onboardingSandboxIncludeActive = false

export function setOnboardingSandboxIncludeActive(active: boolean): void {
  onboardingSandboxIncludeActive = active
}

export function isOnboardingSandboxIncludeActive(): boolean {
  return onboardingSandboxIncludeActive
}
