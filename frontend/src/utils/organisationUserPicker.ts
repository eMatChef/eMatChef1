import type { Organisation } from '@/api/organisations'

/** System-/J&S-Organisationen: nie in Dropdowns zur Auswahl durch User */
const HIDDEN_IDS = new Set(['GLOBALORG001', 'org_js000000'])

export function isOrganisationHiddenFromUserPickers(org: Organisation): boolean {
  if (HIDDEN_IDS.has(org.id)) {
    return true
  }
  const n = org.name.toLowerCase()
  if (n.includes('j&s') || n.includes('j+s')) {
    return true
  }
  if (n.includes('global system')) {
    return true
  }
  return false
}

export function filterOrganisationsForUserPickers(orgs: Organisation[]): Organisation[] {
  return orgs.filter(o => !isOrganisationHiddenFromUserPickers(o))
}
