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

/** map.geo.admin.ch – center in LV95 (E,N), siehe map.geo.admin.ch Hash-Routing. */
export function swisstopoMapUrl(lat: number, lng: number, lang = 'de'): string {
  const { e, n } = wgs84ToLV95(lat, lng)
  return `https://map.geo.admin.ch/#/map?lang=${encodeURIComponent(lang)}&center=${e},${n}`
}
