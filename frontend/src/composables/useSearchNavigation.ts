import { useRouter } from 'vue-router'
import { getMaterials } from '@/api/materials'
import { getWorkshopTickets } from '@/api/workshop'
import apiClient from '@/api/apiClient'
import { useAuthStore } from '@/stores/auth'
import { isDepartmentBasicMemberRole } from '@/composables/useDepartmentMemberRole'

/** Suchtypen mit Prefix für zentrale Suche (Header, Material, Aktivitäten, Workshop) */
export const SEARCH_PREFIXES = {
  material: 'material:',
  aktivitaet: 'aktivität:',
  aktivitaetAlt: 'aktivitaet:',
  reparatur: 'reparatur:',
} as const

export type SearchTargetType = 'material' | 'activity' | 'reparatur'

export interface ParsedSearch {
  type: SearchTargetType
  term: string
  raw: string
}

/** true wenn material:, aktivität: oder reparatur: gesetzt ist */
export function hasExplicitSearchPrefix(raw: string): boolean {
  const trimmed = raw.trim()
  return (
    /^material:/i.test(trimmed) ||
    /^aktivität:/i.test(trimmed) ||
    /^aktivitaet:/i.test(trimmed) ||
    /^reparatur:/i.test(trimmed)
  )
}

/**
 * Parst eine Suchanfrage mit optionalem Prefix.
 * Ohne Prefix → defaultType (Standard: Material)
 */
export function parseSearchQuery(
  raw: string,
  defaultType: SearchTargetType = 'material'
): ParsedSearch | null {
  const trimmed = raw.trim()
  if (!trimmed) return null

  const materialMatch = trimmed.match(/^material:(.+)$/i)
  const aktivitaetMatch =
    trimmed.match(/^aktivität:(.+)$/i) || trimmed.match(/^aktivitaet:(.+)$/i)
  const reparaturMatch = trimmed.match(/^reparatur:(.+)$/i)

  if (materialMatch) {
    return {
      type: 'material',
      term: materialMatch[1].trim(),
      raw: trimmed,
    }
  }
  if (aktivitaetMatch) {
    return {
      type: 'activity',
      term: aktivitaetMatch[1].trim(),
      raw: trimmed,
    }
  }
  if (reparaturMatch) {
    return {
      type: 'reparatur',
      term: reparaturMatch[1].trim(),
      raw: trimmed,
    }
  }
  return {
    type: defaultType,
    term: trimmed,
    raw: trimmed,
  }
}

/** Suchtypen gemäss Navigation/Rolle (Header: alle erlaubten Bereiche). */
export function getSearchEnabledTypes(auth: {
  userRoles: string[]
  currentDepartmentRole: string | null | undefined
}): SearchTargetType[] {
  const types: SearchTargetType[] = ['material']
  if (auth.userRoles.includes('ROLE_SUPERADMIN')) {
    return types
  }
  types.push('activity')
  if (!isDepartmentBasicMemberRole(auth.currentDepartmentRole)) {
    types.push('reparatur')
  }
  return types
}

export interface SearchTarget {
  path: string
  query: { q?: string }
}

/**
 * Ermittelt Ziel-Pfad und Query für eine Suchanfrage.
 * defaultType: Standard-Suchtyp ohne Prefix (z.B. 'material' in Material-View)
 */
export function getSearchTarget(
  raw: string,
  departmentId: string,
  defaultType: SearchTargetType = 'material'
): SearchTarget | null {
  if (!departmentId) return null
  const parsed = parseSearchQuery(raw, defaultType)
  if (!parsed) return null

  const base = `/${departmentId}`
  const q = parsed.term || undefined

  switch (parsed.type) {
    case 'material':
      return { path: `${base}/materials`, query: q ? { q } : {} }
    case 'activity':
      return { path: `${base}/activities`, query: q ? { q } : {} }
    case 'reparatur':
      return { path: `${base}/workshop`, query: q ? { q } : {} }
    default:
      return { path: `${base}/materials`, query: q ? { q } : {} }
  }
}

export interface SearchSuggestion {
  id: string
  label: string
  type: SearchTargetType
  path: string
}

const MAX_SUGGESTIONS = 4
const MAX_SUGGESTIONS_PER_TYPE = 3
const MAX_SUGGESTIONS_COMBINED = 8
export const GLOBAL_SEARCH_PAGE_LIMIT = 30

/** Ziel der zentralen Suchergebnisseite (Header Enter / «Alle Ergebnisse»). */
export function getGlobalSearchPageTarget(
  departmentId: string,
  term: string,
  typeFilter?: SearchTargetType
): SearchTarget {
  const query: Record<string, string> = {}
  const trimmed = term.trim()
  if (trimmed) query.q = trimmed
  if (typeFilter) query.type = typeFilter
  return { path: `/${departmentId}/search`, query }
}

function buildSuggestionPath(base: string, type: SearchTargetType, id: string): string {
  switch (type) {
    case 'material':
      return `${base}/materials/${id}`
    case 'activity':
      return `${base}/activities/${id}`
    case 'reparatur':
      return `${base}/workshop?ticket=${id}`
    default:
      return `${base}/materials/${id}`
  }
}

async function fetchMaterialSuggestions(
  term: string,
  departmentId: string,
  base: string,
  limit: number
): Promise<SearchSuggestion[]> {
  const materials = await getMaterials(departmentId, { search: term })
  return materials.slice(0, limit).map((m) => ({
    id: m.id,
    label: m.name,
    type: 'material' as const,
    path: buildSuggestionPath(base, 'material', m.id),
  }))
}

async function fetchActivitySuggestions(
  term: string,
  departmentId: string,
  base: string,
  limit: number
): Promise<SearchSuggestion[]> {
  const { data } = await apiClient.get<any[]>('/api/activities', {
    params: { department_id: departmentId, search: term },
  })
  const list = data || []
  return list.slice(0, limit).map((a: any) => ({
    id: a.id,
    label: a.name || a.no || 'Aktivität',
    type: 'activity' as const,
    path: buildSuggestionPath(base, 'activity', a.id),
  }))
}

async function fetchReparaturSuggestions(
  term: string,
  departmentId: string,
  base: string,
  limit: number
): Promise<SearchSuggestion[]> {
  const tickets = await getWorkshopTickets(departmentId, { search: term })
  return tickets.slice(0, limit).map((t) => ({
    id: t.id,
    label: t.title,
    type: 'reparatur' as const,
    path: buildSuggestionPath(base, 'reparatur', t.id),
  }))
}

async function fetchSuggestionsForType(
  type: SearchTargetType,
  term: string,
  departmentId: string,
  base: string,
  limit: number
): Promise<SearchSuggestion[]> {
  switch (type) {
    case 'material':
      return fetchMaterialSuggestions(term, departmentId, base, limit)
    case 'activity':
      return fetchActivitySuggestions(term, departmentId, base, limit)
    case 'reparatur':
      return fetchReparaturSuggestions(term, departmentId, base, limit)
    default:
      return []
  }
}

async function fetchCombinedSearchSuggestions(
  term: string,
  departmentId: string,
  types: SearchTargetType[],
  limitPerType = MAX_SUGGESTIONS_PER_TYPE
): Promise<SearchSuggestion[]> {
  const base = `/${departmentId}`
  const chunks = await Promise.all(
    types.map(async (type) => {
      try {
        return await fetchSuggestionsForType(type, term, departmentId, base, limitPerType)
      } catch {
        return []
      }
    })
  )
  return chunks.flat().slice(0, MAX_SUGGESTIONS_COMBINED)
}

/** Vollständige Suchergebnisse für die zentrale Suchseite (gruppiert nach Typ). */
export async function fetchGlobalSearchResults(
  term: string,
  departmentId: string,
  types: SearchTargetType[],
  limitPerType = GLOBAL_SEARCH_PAGE_LIMIT
): Promise<{ type: SearchTargetType; items: SearchSuggestion[] }[]> {
  const trimmed = term.trim()
  if (trimmed.length < 2 || !departmentId || types.length === 0) return []

  const base = `/${departmentId}`
  const chunks = await Promise.all(
    types.map(async (type) => {
      try {
        const items = await fetchSuggestionsForType(type, trimmed, departmentId, base, limitPerType)
        return { type, items }
      } catch {
        return { type, items: [] as SearchSuggestion[] }
      }
    })
  )
  return chunks
}

/**
 * Lädt Suchvorschläge (Material, Aktivität, Workshop).
 * Mit searchAllTypes und ohne Prefix: alle erlaubten Typen parallel.
 */
export async function fetchSearchSuggestions(
  raw: string,
  departmentId: string,
  defaultType: SearchTargetType = 'material',
  options?: {
    searchAllTypes?: boolean
    enabledTypes?: SearchTargetType[]
  }
): Promise<SearchSuggestion[]> {
  const parsed = parseSearchQuery(raw, defaultType)
  if (!parsed || parsed.term.length < 2 || !departmentId) return []

  const term = parsed.term
  const base = `/${departmentId}`
  const useAllTypes =
    options?.searchAllTypes &&
    !hasExplicitSearchPrefix(raw) &&
    (options.enabledTypes?.length ?? 0) > 1

  try {
    if (useAllTypes && options?.enabledTypes) {
      return fetchCombinedSearchSuggestions(term, departmentId, options.enabledTypes)
    }

    const limit = MAX_SUGGESTIONS
    return fetchSuggestionsForType(parsed.type, term, departmentId, base, limit)
  } catch {
    return []
  }
}

/**
 * Composable für zentrale Such-Navigation.
 * Wiederverwendbar in Header, Material-View, Aktivitäten-View etc.
 */
export function useSearchNavigation() {
  const router = useRouter()
  const authStore = useAuthStore()

  function executeSearch(
    raw: string,
    departmentId: string,
    defaultType: SearchTargetType = 'material',
    options?: { searchAllTypes?: boolean }
  ): boolean {
    const parsed = parseSearchQuery(raw, defaultType)
    if (!parsed || !departmentId) return false

    let target: SearchTarget | null
    if (options?.searchAllTypes) {
      target = getGlobalSearchPageTarget(
        departmentId,
        parsed.term,
        hasExplicitSearchPrefix(raw) ? parsed.type : undefined
      )
    } else {
      target = getSearchTarget(raw, departmentId, defaultType)
    }
    if (!target) return false
    router.push({ path: target.path, query: target.query })
    return true
  }

  const enabledSearchTypes = () => getSearchEnabledTypes(authStore)

  return {
    parseSearchQuery,
    getSearchTarget,
    getGlobalSearchPageTarget,
    executeSearch,
    fetchSearchSuggestions,
    fetchGlobalSearchResults,
    getSearchEnabledTypes: enabledSearchTypes,
    hasExplicitSearchPrefix,
    SEARCH_PREFIXES,
  }
}
