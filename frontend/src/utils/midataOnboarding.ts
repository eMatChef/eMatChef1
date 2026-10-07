import { midataLinkStartUrl } from '@/api/auth'

/**
 * Startet den MiData-Link-Flow für ein verifiziertes Angebot oder einen Suchtreffer.
 * Die ID ist nur ein Verweis; der Server prüft Rolle und Struktur danach mit frischem MiData-Token.
 */
export function midataOnboardingStartUrl(kind: 'offer' | 'candidate', id: string): string {
  const query = new URLSearchParams({ [kind === 'offer' ? 'midata_onboarding' : 'midata_candidate']: id })
  return midataLinkStartUrl(`/pending-assignment?${query.toString()}`)
}

/** Query-Parameter, mit denen /pending-assignment auch für User mit Membership als MiData-Rückkehrseite dient. */
export const MIDATA_ONBOARDING_QUERY_KEYS = ['midata_onboarding', 'midata_candidate', 'midata_onboarding_result'] as const

export function isMiDataOnboardingLanding(query: Record<string, unknown>): boolean {
  return MIDATA_ONBOARDING_QUERY_KEYS.some((key) => typeof query[key] === 'string' && query[key] !== '')
}
