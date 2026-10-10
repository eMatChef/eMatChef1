import type { NavigationGuardNext, RouteLocationNormalized, RouteLocationRaw } from 'vue-router'

/** Hinweis des Servers nach dem Verknüpfen einer Login-Identität (Ergebnis liegt serverseitig, siehe Backend LinkResultStore). */
export const PROFILE_SECURITY_RETURN_PARAM = 'profile_security'

/**
 * Router-Redirects (z. B. /dashboard → /{departmentId}, / → Abteilung, Pending-Seite verlassen) verwerfen die
 * Query. Damit der Rückweg aus dem Verknüpfen trotzdem im Profil ankommt, wird der Hinweis bei jedem Redirect
 * dieser Navigation mitgenommen. Alle anderen Parameter bleiben wie bisher unberührt.
 */
export function carryOAuthReturnParams(to: RouteLocationNormalized, next: NavigationGuardNext): NavigationGuardNext {
  if (to.query[PROFILE_SECURITY_RETURN_PARAM] !== '1') return next

  return ((target?: unknown) => {
    if (typeof target === 'string') {
      const url = new URL(target, 'https://local.invalid')
      url.searchParams.set(PROFILE_SECURITY_RETURN_PARAM, '1')
      return next(`${url.pathname}${url.search}${url.hash}`)
    }
    if (target && typeof target === 'object' && !(target instanceof Error)) {
      const location = target as Exclude<RouteLocationRaw, string>
      return next({ ...location, query: { ...(location.query ?? {}), [PROFILE_SECURITY_RETURN_PARAM]: '1' } })
    }
    return next(target as never)
  }) as NavigationGuardNext
}
