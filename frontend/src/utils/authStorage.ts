/** Alle localStorage-Keys der User-Session (JWT + Hilfs-IDs + Auto-Logout). */
const AUTH_STORAGE_KEYS = [
  'auth_token',
  'refresh_token',
  'user_id',
  'profile_id',
  'session_last_activity_at',
  'active_department_id',
] as const

/** Entfernt gespeicherte Auth-Daten — z. B. nach Logout oder abgelaufener Session. */
export function clearAuthStorage(): void {
  for (const key of AUTH_STORAGE_KEYS) {
    localStorage.removeItem(key)
  }
}
