import { clearAuthStorage } from '@/utils/authStorage'

/** Muss mit {@link CrossSubdomainAuthCookies::LOGOUT_MARKER_COOKIE} übereinstimmen. */
export const LOGOUT_MARKER_COOKIE = 'emat_logged_out'
const LOGOUT_MARKER_SEEN_KEY = 'emat_logged_out_seen'

function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const match = document.cookie.match(new RegExp(`(?:^|; )${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}=([^;]*)`))
  return match ? decodeURIComponent(match[1]) : null
}

export function getCrossSubdomainLogoutMarker(): string | null {
  const value = readCookie(LOGOUT_MARKER_COOKIE)
  return value && value.trim() !== '' ? value.trim() : null
}

export function markCrossSubdomainLogoutSeen(marker: string): void {
  localStorage.setItem(LOGOUT_MARKER_SEEN_KEY, marker)
}

/** Nach Logout-Response: Marker aus Set-Cookie übernehmen, damit diese Origin nicht erneut reagiert. */
export function markCrossSubdomainLogoutSeenFromCookie(): void {
  const marker = getCrossSubdomainLogoutMarker()
  if (marker) {
    markCrossSubdomainLogoutSeen(marker)
  }
}

/**
 * Logout auf einer anderen Subdomain (devices/app/qr): localStorage hier leeren.
 * @returns true wenn lokale Session-Daten entfernt wurden
 */
export function applyCrossSubdomainLogoutSync(): boolean {
  const marker = getCrossSubdomainLogoutMarker()
  if (!marker) return false

  const seen = localStorage.getItem(LOGOUT_MARKER_SEEN_KEY)
  if (seen === marker) return false

  clearAuthStorage()
  markCrossSubdomainLogoutSeen(marker)
  return true
}
