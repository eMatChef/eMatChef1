import type { LocationQueryRaw, RouteLocationNormalizedLoaded } from 'vue-router'
import { ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY } from '@/config/onboardingTours'
import { PROFILE_SECURITY_RETURN_PARAM } from '@/utils/oauthReturnParams'

/** Query parameter holding the page the user came from before opening the profile. */
export const PROFILE_FROM_PARAM = 'from'

/** Parameters the server or the tour engine sets; a return path never carries them. */
const STRIPPED_PARAMS = [PROFILE_SECURITY_RETURN_PARAM, 'oauth', 'provider', 'reason', ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY]

const ORIGIN_PROBE = 'https://return.invalid'
const MAX_LENGTH = 2000

/** Global profile routes; used to refuse return paths that would loop back into the profile. */
export function isProfilePath(path: string): boolean {
  return path === '/profile' || path.startsWith('/profile/')
}

function hasUnsafeChars(value: string): boolean {
  // eslint-disable-next-line no-control-regex
  return /[\u0000-\u001f\u007f\\]/.test(value)
}

/**
 * Validates a return path: internal absolute path only (no scheme, no protocol-relative `//`, no backslash,
 * no control characters, also not after one round of percent-decoding), never a profile or login route.
 * Server-reserved and tour parameters are removed. Returns the normalised path or null.
 */
export function sanitizeProfileFrom(raw: unknown): string | null {
  const value = Array.isArray(raw) ? raw[0] : raw
  if (typeof value !== 'string') return null
  const candidate = value.trim()
  if (!candidate || candidate.length > MAX_LENGTH) return null
  if (!candidate.startsWith('/') || candidate.startsWith('//') || hasUnsafeChars(candidate)) return null

  let decoded: string
  try {
    decoded = decodeURIComponent(candidate)
  } catch {
    return null
  }
  if (decoded.startsWith('//') || hasUnsafeChars(decoded)) return null

  let url: URL
  try {
    url = new URL(candidate, ORIGIN_PROBE)
  } catch {
    return null
  }
  if (url.origin !== ORIGIN_PROBE) return null
  if (isProfilePath(url.pathname) || ['/login', '/register', '/forgot-password', '/reset-password'].includes(url.pathname)) return null

  for (const key of STRIPPED_PARAMS) url.searchParams.delete(key)
  return `${url.pathname}${url.search}${url.hash}`
}

/** Return path from a route query, or null when absent or invalid. */
export function profileFromQuery(query: RouteLocationNormalizedLoaded['query']): string | null {
  return sanitizeProfileFrom(query[PROFILE_FROM_PARAM])
}

/** Where to go when there is no valid return path: the active department, else the role-based dashboard redirect. */
export function profileFallbackPath(activeDepartmentId: string | null | undefined): string {
  return activeDepartmentId ? `/${encodeURIComponent(activeDepartmentId)}` : '/dashboard'
}

/** Query for opening the profile from `route`: keeps `from` inside the profile, otherwise uses the current page. */
export function profileEntryQuery(route: Pick<RouteLocationNormalizedLoaded, 'fullPath' | 'path' | 'query'>): LocationQueryRaw {
  const from = isProfilePath(route.path) ? profileFromQuery(route.query) : sanitizeProfileFrom(route.fullPath)
  return from ? { [PROFILE_FROM_PARAM]: from } : {}
}

const REMEMBERED_FROM_KEY = 'emc-profile-from'

/**
 * The OAuth return URL drops the query, so the return path is parked in the session storage
 * while the user is at the provider. Storage may be unavailable; the profile then simply uses the fallback.
 */
export function rememberProfileFrom(raw: unknown): void {
  try {
    const from = sanitizeProfileFrom(raw)
    if (from) sessionStorage.setItem(REMEMBERED_FROM_KEY, from)
    else sessionStorage.removeItem(REMEMBERED_FROM_KEY)
  } catch {
    /* ignore */
  }
}

/** Reads and clears the parked return path. */
export function takeRememberedProfileFrom(): string | null {
  try {
    const value = sessionStorage.getItem(REMEMBERED_FROM_KEY)
    sessionStorage.removeItem(REMEMBERED_FROM_KEY)
    return sanitizeProfileFrom(value)
  } catch {
    return null
  }
}

/**
 * `from` to add when the profile is entered without one (menu, onboarding tour, any link): the page the user
 * came from. Nothing is added on a fresh load (no previous page) or when moving between profile routes.
 */
export function profileFromForEntry(
  to: { query: RouteLocationNormalizedLoaded['query'] },
  previous: { matched: readonly unknown[]; path: string; fullPath: string },
): string | null {
  if (to.query[PROFILE_FROM_PARAM] !== undefined) return null
  if (previous.matched.length === 0 || isProfilePath(previous.path)) return null
  return sanitizeProfileFrom(previous.fullPath)
}

/** Key of the page component in the app layout: profile tabs share one key so the profile page stays mounted. */
export function layoutViewKey(route: { path: string; meta: Record<string, unknown> }, clockRevision: number): string {
  return `${route.meta.globalProfile ? '/profile' : route.path}:${clockRevision}`
}
