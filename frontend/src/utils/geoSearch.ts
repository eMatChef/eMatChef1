export type GeoSearchResult = {
  label: string
  lat: number
  lng: number
}

const SWISS_SEARCH_URL = 'https://api3.geo.admin.ch/rest/services/api/SearchServer'
const NOMINATIM_SEARCH_URL = 'https://nominatim.openstreetmap.org/search'

export function stripGeoSearchLabel(raw: string): string {
  return raw.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim()
}

function parseSwisstopoResults(data: unknown, limit: number): GeoSearchResult[] {
  const results = (data as { results?: Array<{ attrs?: Record<string, unknown> }> })?.results
  if (!Array.isArray(results)) return []

  const out: GeoSearchResult[] = []
  for (const row of results) {
    const attrs = row?.attrs
    if (!attrs) continue
    const lat = Number(attrs.lat)
    const lng = Number(attrs.lon)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue
    const label = stripGeoSearchLabel(String(attrs.label ?? attrs.detail ?? ''))
    if (!label) continue
    out.push({ label, lat, lng })
    if (out.length >= limit) break
  }
  return out
}

function parseNominatimResults(data: unknown, limit: number): GeoSearchResult[] {
  if (!Array.isArray(data)) return []
  const out: GeoSearchResult[] = []
  for (const item of data) {
    const lat = parseFloat(String(item?.lat ?? ''))
    const lng = parseFloat(String(item?.lon ?? ''))
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue
    const label = String(item?.display_name ?? '').trim()
    if (!label) continue
    out.push({ label, lat, lng })
    if (out.length >= limit) break
  }
  return out
}

export async function searchGeoLocations(query: string, limit = 6): Promise<GeoSearchResult[]> {
  const q = query.trim()
  if (q.length < 2) return []

  try {
    const swissResponse = await fetch(
      `${SWISS_SEARCH_URL}?searchText=${encodeURIComponent(q)}&type=locations&limit=${limit}`,
    )
    if (swissResponse.ok) {
      const swissData = await swissResponse.json()
      const swissResults = parseSwisstopoResults(swissData, limit)
      if (swissResults.length > 0) return swissResults
    }
  } catch {
    /* fallback below */
  }

  try {
    const response = await fetch(
      `${NOMINATIM_SEARCH_URL}?format=json&q=${encodeURIComponent(q)}&limit=${limit}&addressdetails=1`,
      { headers: { 'Accept-Language': 'de' } },
    )
    if (!response.ok) return []
    const data = await response.json()
    return parseNominatimResults(data, limit)
  } catch {
    return []
  }
}
