import { htmlToPlainText } from '@/utils/sanitizeHtml'

export type GeoSearchResult = {
  label: string
  lat: number
  lng: number
}

const SWISS_SEARCH_URL = 'https://api3.geo.admin.ch/rest/services/api/SearchServer'
const NOMINATIM_SEARCH_URL = 'https://nominatim.openstreetmap.org/search'

export function stripGeoSearchLabel(raw: string): string {
  return htmlToPlainText(raw, 'compact')
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

/** Schweizer Landeskoordinaten LV95 (E/N) → WGS84, swisstopo-Näherungsformeln (Genauigkeit ~1 m). */
function lv95ToWgs84(e: number, n: number): { lat: number; lng: number } {
  const y = (e - 2600000) / 1e6
  const x = (n - 1200000) / 1e6
  const lng = 2.6779094 + 4.728982 * y + 0.791484 * y * x + 0.1306 * y * x * x - 0.0436 * y * y * y
  const lat = 16.9023892 + 3.238272 * x - 0.270978 * y * y - 0.002528 * x * x - 0.0447 * y * y * x - 0.014 * x * x * x
  return { lat: (lat * 100) / 36, lng: (lng * 100) / 36 }
}

/** Koordinaten-Eingabe: «47.178, 8.047» (auch umgekehrt) oder LV95 «2 600 000 / 1 200 000». */
export function parseCoordinateQuery(raw: string): GeoSearchResult | null {
  const cleaned = raw.trim().replace(/['’\s]+(?=\d{3}\b)/g, '').replace(/[;/]/g, ' ')
  const nums = cleaned.match(/-?\d+(?:[.,]\d+)?/g)?.map((v) => parseFloat(v.replace(',', '.')))
  if (!nums || nums.length !== 2 || nums.some((v) => !Number.isFinite(v))) return null
  const [a, b] = nums
  const inLat = (v: number) => v >= 45.7 && v <= 47.9
  const inLng = (v: number) => v >= 5.8 && v <= 10.6
  if (inLat(a) && inLng(b)) return { label: `${a.toFixed(6)}, ${b.toFixed(6)}`, lat: a, lng: b }
  if (inLat(b) && inLng(a)) return { label: `${b.toFixed(6)}, ${a.toFixed(6)}`, lat: b, lng: a }
  const [e, n] = a > b ? [a, b] : [b, a]
  if (e >= 2400000 && e <= 2900000 && n >= 1000000 && n <= 1400000) {
    const { lat, lng } = lv95ToWgs84(e, n)
    return { label: `LV95 ${Math.round(e)} / ${Math.round(n)} (${lat.toFixed(6)}, ${lng.toFixed(6)})`, lat, lng }
  }
  return null
}

export async function searchGeoLocations(query: string, limit = 6): Promise<GeoSearchResult[]> {
  const q = query.trim()
  if (q.length < 2) return []
  const coordinate = parseCoordinateQuery(q)
  if (coordinate) return [coordinate]

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
