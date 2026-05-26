/**
 * Auth-Secrets (JWT, Refresh) liegen nur in HttpOnly-Cookies (.ematchef.ch).
 * localStorage: nur nicht-sensitive UI-Präferenzen.
 */

/** Frühere Versionen — beim Start entfernen (Migration). */
const LEGACY_SECRET_KEYS = [
  'auth_token',
  'refresh_token',
  'user_id',
  'profile_id',
] as const

/** Beim Logout leeren (keine Secrets). */
const SESSION_PREFERENCE_KEYS = ['session_last_activity_at', 'active_department_id'] as const

/** Entfernt veraltete JWT/IDs aus localStorage (einmalig pro Origin). */
export function purgeLegacyAuthSecrets(): void {
  for (const key of LEGACY_SECRET_KEYS) {
    localStorage.removeItem(key)
  }
}

/** Session-UI-State nach Logout leeren. */
export function clearSessionPreferences(): void {
  for (const key of SESSION_PREFERENCE_KEYS) {
    localStorage.removeItem(key)
  }
}

/** Logout: Legacy-Secrets + Session-Präferenzen. */
export function clearAuthStorage(): void {
  purgeLegacyAuthSecrets()
  clearSessionPreferences()
}
