/** WGS84 → LV95 (EPSG:2056), Näherung für map.geo.admin.ch. */
function wgs84ToLV95(lat: number, lng: number): { e: number; n: number } {
  const latAux = (lat * 3600 - 169028.66) / 10000
  const lngAux = (lng * 3600 - 26782.5) / 10000

  const e = Math.round(
    2600072.37 +
      211455.93 * lngAux -
      10938.51 * lngAux * latAux -
      0.36 * lngAux * latAux * latAux -
      44.54 * lngAux * lngAux * lngAux
  )

  const n = Math.round(
    1200147.07 +
      308807.95 * latAux +
      3745.25 * lngAux * lngAux +
      76.63 * latAux * latAux -
      194.56 * lngAux * lngAux * latAux +
      119.79 * latAux * latAux * latAux
  )

  return { e, n }
}

/** Google Maps mit exakten Koordinaten (WGS84). */
export function googleMapsCoordinatesUrl(lat: number, lng: number): string {
  return `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`
}

/** Google Maps mit Adresssuche. */
export function googleMapsAddressUrl(address: string): string {
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`
}

/** map.geo.admin.ch – Zoomstufe (0–13, oft mit Dezimalstellen). */
export type SwisstopoMapUrlOptions = {
  lang?: string
  /** Zoom der map.geo.admin.ch-Karte (z-Parameter). Default: 8. */
  zoom?: number
  /** Marker am Standort anzeigen. Default: true. */
  showMarker?: boolean
}

/**
 * Deep-Link zu map.geo.admin.ch (Viewer ab 2024: Query-Parameter auf der Root-URL).
 * center = LV95 (E,N), bgLayer = Landeskarte farbig wie in der App.
 */
export function swisstopoMapUrl(
  lat: number,
  lng: number,
  options: SwisstopoMapUrlOptions = {}
): string {
  const { lang = 'de', zoom = 8, showMarker = true } = options
  const { e, n } = wgs84ToLV95(lat, lng)
  const params = new URLSearchParams({
    lang,
    center: `${e},${n}`,
    z: String(Math.round(zoom * 100) / 100),
    bgLayer: 'ch.swisstopo.pixelkarte-farbe',
  })
  if (showMarker) {
    params.set('crosshair', `marker,${e},${n}`)
  }
  return `https://map.geo.admin.ch/?${params.toString()}`
}

/**
 * map.geo.admin.ch z-Parameter aus Leaflet-LV95-Zoom (EPSG:2056, ab Zoom 14).
 * Entspricht dem Maßstab in unserer LV95-Karte (z ≈ leafletZoom − 14).
 */
export function geoAdminZoomFromLv95LeafletZoom(leafletZoom: number): number {
  return Math.round((leafletZoom - 14) * 100) / 100
}

/** OpenStreetMap mit Marker (WGS84). */
export function openStreetMapUrl(lat: number, lng: number, zoom = 17): string {
  return `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=${zoom}/${lat}/${lng}`
}
