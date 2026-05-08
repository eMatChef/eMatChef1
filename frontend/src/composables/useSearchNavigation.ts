import { useRouter } from 'vue-router'
import { getMaterials } from '@/api/materials'
import { getWorkshopTickets } from '@/api/workshop'
import apiClient from '@/api/apiClient'

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

function buildSuggestionPath(
  base: string,
  type: SearchTargetType,
  id: string,
  searchTerm: string
): string {
  const q = searchTerm ? `q=${encodeURIComponent(searchTerm)}` : ''
  switch (type) {
    case 'material':
      return q ? `${base}/materials/${id}?${q}` : `${base}/materials/${id}`
    case 'activity':
      return q ? `${base}/activities/${id}?${q}` : `${base}/activities/${id}`
    case 'reparatur':
      return q ? `${base}/workshop?ticket=${id}&${q}` : `${base}/workshop?ticket=${id}`
    default:
      return `${base}/materials/${id}`
  }
}

/**
 * Lädt bis zu 4 Suchvorschläge (Material, Aktivität oder Workshop) die den Begriff enthalten.
 * Die path-URL enthält den Suchbegriff (?q=...) für Share/Bookmark.
 */
export async function fetchSearchSuggestions(
  raw: string,
  departmentId: string,
  defaultType: SearchTargetType = 'material'
): Promise<SearchSuggestion[]> {
  const parsed = parseSearchQuery(raw, defaultType)
  if (!parsed || parsed.term.length < 2 || !departmentId) return []

  const term = parsed.term
  const base = `/${departmentId}`

  try {
    switch (parsed.type) {
      case 'material': {
        const materials = await getMaterials(departmentId, { search: term })
        return materials.slice(0, MAX_SUGGESTIONS).map((m) => ({
          id: m.id,
          label: m.name,
          type: 'material' as const,
          path: buildSuggestionPath(base, 'material', m.id, term),
        }))
      }
      case 'activity': {
        const { data } = await apiClient.get<any[]>('/api/activities', {
          params: { department_id: departmentId, search: term },
        })
        const list = data || []
        return list.slice(0, MAX_SUGGESTIONS).map((a: any) => ({
          id: a.id,
          label: a.name || a.no || 'Aktivität',
          type: 'activity' as const,
          path: buildSuggestionPath(base, 'activity', a.id, term),
        }))
      }
      case 'reparatur': {
        const tickets = await getWorkshopTickets(departmentId, { search: term })
        return tickets.slice(0, MAX_SUGGESTIONS).map((t) => ({
          id: t.id,
          label: t.title,
          type: 'reparatur' as const,
          path: buildSuggestionPath(base, 'reparatur', t.id, term),
        }))
      }
      default:
        return []
    }
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

  function executeSearch(
    raw: string,
    departmentId: string,
    defaultType: SearchTargetType = 'material'
  ): boolean {
    const target = getSearchTarget(raw, departmentId, defaultType)
    if (!target) return false
    router.push({ path: target.path, query: target.query })
    return true
  }

  return {
    parseSearchQuery,
    getSearchTarget,
    executeSearch,
    fetchSearchSuggestions,
    SEARCH_PREFIXES,
  }
}
