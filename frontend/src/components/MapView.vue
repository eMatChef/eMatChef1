<template>
  <div class="map-wrapper">
    <div class="map-stage">
      <div class="map-container" ref="mapContainer">
      <div v-if="!hasCoordinates && !isLoading" :class="['no-coordinates', { editable: editable }]">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="#d1d5db"/>
          <circle cx="12" cy="9" r="2.5" fill="#9ca3af"/>
        </svg>
        <p>{{ editable ? t('components.mapView.hintSetLocation') : t('components.mapView.hintNoCoords') }}</p>
      </div>
      <div v-if="isLoading" class="loading-overlay">
        <div class="spinner"></div>
        <p>{{ t('components.mapView.searchingAddress') }}</p>
      </div>

      </div>

      <!-- Layer-Auswahl (ausserhalb des Leaflet-Containers, sonst Klick = Marker setzen) -->
      <div
        v-if="showLayerControl"
        class="layer-control"
        @mousedown.stop
        @click.stop
        @dblclick.stop
      >
        <button
          type="button"
          :class="['layer-btn', { active: currentLayer === 'swisstopo' }]"
          @click.stop="setLayer('swisstopo', { userInitiated: true })"
          :title="t('components.mapView.layerSwisstopoTitle')"
        >
          🇨🇭
        </button>
        <button
          type="button"
          :class="['layer-btn', { active: currentLayer === 'swissimage' }]"
          @click.stop="setLayer('swissimage', { userInitiated: true })"
          :title="t('components.mapView.layerSwissimageTitle')"
        >
          📷
        </button>
        <button
          type="button"
          :class="['layer-btn', { active: currentLayer === 'osm' }]"
          @click.stop="setLayer('osm', { userInitiated: true })"
          :title="t('components.mapView.layerOsmTitle')"
        >
          🌍
        </button>
      </div>
    </div>
    
    <!-- Koordinaten-Anzeige -->
    <div v-if="hasCoordinates && showCoordinates" class="coordinates-display">
      <div class="coord-row">
        <span class="coord-label">{{ isInSwitzerland ? t('components.mapView.labelLv95') : t('components.mapView.labelWgs84') }}</span>
        <span class="coord-value">
          {{ isInSwitzerland ? formatSwissCoords(latitude, longitude) : formatWGS84(latitude, longitude) }}
        </span>
      </div>
      <div v-if="isInSwitzerland" class="coord-row secondary">
        <span class="coord-label">{{ t('components.mapView.labelWgs84') }}</span>
        <span class="coord-value">{{ formatWGS84(latitude, longitude) }}</span>
      </div>
      <div v-if="foundAddress" class="coord-row address">
        <span class="coord-label">{{ t('components.mapView.labelAddress') }}</span>
        <span class="coord-value">{{ foundAddress }}</span>
      </div>
    </div>

    <div
      v-if="showExternalMapLinks && hasCoordinates && latitude != null && longitude != null"
      class="external-map-links"
    >
      <a
        :href="swisstopoMapLink"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-outline btn-sm"
      >
        {{ t('components.mapView.openSwisstopoMap') }}
      </a>
      <a
        :href="openStreetMapLink"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-outline btn-sm"
      >
        {{ t('components.mapView.openOpenStreetMap') }}
      </a>
      <a
        :href="googleMapsLink"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-outline btn-sm"
      >
        {{ t('components.mapView.openGoogleMaps') }}
      </a>
    </div>
    
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import L from 'leaflet'
import proj4 from 'proj4'
import 'proj4leaflet'
import { googleMapsCoordinatesUrl, swisstopoMapUrl, openStreetMapUrl, geoAdminZoomFromLv95LeafletZoom } from '@/utils/mapExternalLinks'
import 'leaflet/dist/leaflet.css'

void proj4

// Fix für Leaflet Marker Icons
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

// @ts-ignore
delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
})

interface Props {
  latitude?: number | null
  longitude?: number | null
  address?: string
  editable?: boolean
  height?: string
  zoom?: number
  showCoordinates?: boolean
  showLayerControl?: boolean
  preferSwissMap?: boolean
  /** Zoom/Pan/Scroll deaktivieren (z. B. Kontakt-Detailansicht). */
  interactive?: boolean
  /** Nur Mausrad-Zoom (de)aktivieren, ohne übrige Interaktivität anzutasten. */
  scrollWheelZoom?: boolean
  /** Links zu map.geo.admin.ch und Google Maps unter der Karte. */
  showExternalMapLinks?: boolean
  /**
   * Schweizer Projektion LV95 (EPSG:2056) verwenden – identisch zu
   * map.geo.admin.ch (gleiche Kachelgrundlage und Maßstabsstufen).
   * Achtung: Der weltweite OpenStreetMap-Layer ist dann nicht verfügbar.
   */
  useSwissProjection?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  latitude: null,
  longitude: null,
  address: '',
  editable: false,
  height: '300px',
  zoom: 17,
  showCoordinates: true,
  showLayerControl: true,
  preferSwissMap: true,
  interactive: true,
  scrollWheelZoom: true,
  showExternalMapLinks: false,
  useSwissProjection: false,
})

const { t, locale } = useI18n()

const emit = defineEmits<{
  'update:latitude': [value: number]
  'update:longitude': [value: number]
  'coordinates-changed': [lat: number, lng: number]
  'address-found': [address: string]
}>()

const mapContainer = ref<HTMLDivElement>()
let map: L.Map | null = null
let marker: L.Marker | null = null
let currentTileLayer: L.Layer | null = null
let tileLayerInstances: Partial<Record<MapBaseLayer, L.Layer>> | null = null

/** Aktive Karten-Projektion. OSM gibt es nur in Web-Mercator, swisstopo/swissimage je nach Prop. */
type Projection = 'lv95' | 'webmercator'
let activeProjection: Projection = 'webmercator'

const WMTS_BASE = 'https://wmts.geo.admin.ch/1.0.0'
const SWISSTOPO_ATTRIBUTION =
  '&copy; <a href="https://www.swisstopo.admin.ch">swisstopo</a>'
const MAP_MAX_ZOOM = 22

/** Kleinster sinnvoller Zoom für die Swisstopo-Landeskarte (EPSG:3857). */
const SWISSTOPO_MIN_ZOOM = 7

/* ---- Schweizer Projektion LV95 (EPSG:2056), identisch zu map.geo.admin.ch ---- */
const SWISS_LV95_PROJ4 =
  '+proj=somerc +lat_0=46.95240555555556 +lon_0=7.439583333333333 ' +
  '+k_0=1 +x_0=2600000 +y_0=1200000 +ellps=bessel ' +
  '+towgs84=674.374,15.056,405.346,0,0,0,0 +units=m +no_defs'
/** Offizielle Auflösungsstufen (m/px) des swisstopo-WMTS-Kachelgitters in LV95. */
const SWISS_LV95_RESOLUTIONS = [
  4000, 3750, 3500, 3250, 3000, 2750, 2500, 2250, 2000, 1750, 1500, 1250, 1000,
  750, 650, 500, 250, 100, 50, 20, 10, 5, 2.5, 2, 1.5, 1, 0.5, 0.25, 0.1,
]
const SWISS_LV95_ORIGIN: [number, number] = [2420000, 1350000]
const SWISS_LV95_MIN_ZOOM = 14
const SWISS_LV95_MAX_ZOOM = 27

let swissCrs: L.CRS | null = null
function getSwissCrs(): L.CRS {
  if (!swissCrs) {
    // @ts-ignore proj4leaflet erweitert L um L.Proj zur Laufzeit (kein Typ in @types/leaflet)
    swissCrs = new L.Proj.CRS('EPSG:2056', SWISS_LV95_PROJ4, {
      resolutions: SWISS_LV95_RESOLUTIONS,
      origin: SWISS_LV95_ORIGIN,
    })
  }
  return swissCrs as L.CRS
}

const hasCoordinates = ref(false)
const isLoading = ref(false)
type MapBaseLayer = 'swisstopo' | 'swissimage' | 'osm'

const foundAddress = ref<string | null>(null)
const currentLayer = ref<MapBaseLayer>('swisstopo')
/** Nutzer hat Layer per Karten-UI gewählt – kein automatisches Zurückschalten in setMarker. */
let userPickedLayer = false
/** Kurzzeitig Karten-Klicks ignorieren (z. B. nach Layer-Umschaltung). */
let suppressMapClick = false

type SetLayerOptions = { userInitiated?: boolean }

function coordsNearlyEqual(
  aLat: number,
  aLng: number,
  bLat: number,
  bLng: number,
  eps = 1e-7
): boolean {
  return Math.abs(aLat - bLat) < eps && Math.abs(aLng - bLng) < eps
}

// Schweiz-Zentrum als Fallback
const DEFAULT_CENTER: [number, number] = [46.8182, 8.2275]
const DEFAULT_ZOOM = 7
/** Kartenansicht ohne Koordinaten im Bearbeitungsmodus (regional, nicht ganze CH). */
const DEFAULT_ZOOM_EDITABLE = 11
/** Ab dieser Zoomstufe gilt die Karte als «nah genug» – darunter nach Treffer ranzoomen. */
const MIN_LOCATION_ZOOM = 14
const SEARCH_RESULT_ZOOM = 17
/** LV95: Detail-Zoom nach Adresssuche / Marker setzen (≈ geo.admin z 7.7). */
const SWISS_LV95_SEARCH_ZOOM = 21.7
/** LV95: Schwelle «nah genug» für automatisches Heranzoomen. */
const SWISS_LV95_MIN_LOCATION_ZOOM = 18

function searchResultZoom(): number {
  return props.useSwissProjection ? SWISS_LV95_SEARCH_ZOOM : SEARCH_RESULT_ZOOM
}

function minLocationZoom(): number {
  return props.useSwissProjection ? SWISS_LV95_MIN_LOCATION_ZOOM : MIN_LOCATION_ZOOM
}

// Schweiz Bounding Box (ungefähr)
const SWISS_BOUNDS = {
  minLat: 45.8,
  maxLat: 47.85,
  minLng: 5.9,
  maxLng: 10.55
}

function shouldFitSwissOverview(): boolean {
  return (
    props.editable &&
    props.useSwissProjection &&
    (props.latitude == null || props.longitude == null)
  )
}

function swissOverviewBounds(): L.LatLngBounds {
  return L.latLngBounds(
    [SWISS_BOUNDS.minLat, SWISS_BOUNDS.minLng],
    [SWISS_BOUNDS.maxLat, SWISS_BOUNDS.maxLng]
  )
}

/** Ganze Schweiz in den sichtbaren Kartenbereich einpassen (Adress-Modal ohne Koordinaten). */
function fitSwissOverviewIfNeeded() {
  if (!map || !shouldFitSwissOverview()) return
  map.fitBounds(swissOverviewBounds(), { padding: [12, 12], animate: false })
}

// Prüfen ob Koordinaten in der Schweiz liegen
const isInSwitzerland = computed(() => {
  const lat = props.latitude
  const lng = props.longitude
  if (!lat || !lng) return false
  return lat >= SWISS_BOUNDS.minLat && lat <= SWISS_BOUNDS.maxLat &&
         lng >= SWISS_BOUNDS.minLng && lng <= SWISS_BOUNDS.maxLng
})

const mapLang = computed(() => (String(locale.value).startsWith('en') ? 'en' : 'de'))

const swisstopoMapLink = computed(() => {
  if (props.latitude == null || props.longitude == null) return '#'
  const geoZoom = props.useSwissProjection
    ? geoAdminZoomFromLv95LeafletZoom(props.zoom)
    : 8
  return swisstopoMapUrl(props.latitude, props.longitude, {
    lang: mapLang.value,
    zoom: geoZoom,
  })
})

const googleMapsLink = computed(() => {
  if (props.latitude == null || props.longitude == null) return '#'
  return googleMapsCoordinatesUrl(props.latitude, props.longitude)
})

const openStreetMapLink = computed(() => {
  if (props.latitude == null || props.longitude == null) return '#'
  return openStreetMapUrl(props.latitude, props.longitude)
})

// WGS84 zu LV95 (Schweizer Koordinaten) Umrechnung
// Vereinfachte Näherungsformel (genau genug für Anzeige)
function wgs84ToLV95(lat: number, lng: number): { e: number, n: number } {
  // Hilfswerte
  const lat_aux = (lat * 3600 - 169028.66) / 10000
  const lng_aux = (lng * 3600 - 26782.5) / 10000
  
  // Ostkoordinate E
  const e = 2600072.37 +
    211455.93 * lng_aux -
    10938.51 * lng_aux * lat_aux -
    0.36 * lng_aux * lat_aux * lat_aux -
    44.54 * lng_aux * lng_aux * lng_aux
  
  // Nordkoordinate N
  const n = 1200147.07 +
    308807.95 * lat_aux +
    3745.25 * lng_aux * lng_aux +
    76.63 * lat_aux * lat_aux -
    194.56 * lng_aux * lng_aux * lat_aux +
    119.79 * lat_aux * lat_aux * lat_aux
  
  return { e: Math.round(e), n: Math.round(n) }
}

// Formatierung Schweizer Koordinaten (E / N – ohne Präfixe, mit Hochkomma-Trennung)
function formatSwissCoords(lat: number | null | undefined, lng: number | null | undefined): string {
  if (!lat || !lng) return '-'
  const { e, n } = wgs84ToLV95(lat, lng)
  return `${e.toLocaleString('de-CH')} / ${n.toLocaleString('de-CH')}`
}

// Formatierung WGS84
function formatWGS84(lat: number | null | undefined, lng: number | null | undefined): string {
  if (!lat || !lng) return '-'
  return `${lat.toFixed(6)}° N, ${lng.toFixed(6)}° E`
}

// Karten-Limits pro Modus (maxNativeZoom: echte Kacheln; höheres maxZoom: Leaflet skaliert letzte Kachel)
const tileLayers: Record<
  MapBaseLayer,
  { options: L.TileLayerOptions }
> = {
  swisstopo: {
    options: {
      attribution: SWISSTOPO_ATTRIBUTION,
      // Nahtlose Landeskarte (wie map.geo.admin.ch): wählt je nach Zoom
      // automatisch die passende Karte (u. a. 1:25'000). Native Kacheln bis
      // Zoom 18, darüber skaliert Leaflet.
      maxNativeZoom: 18,
      maxZoom: MAP_MAX_ZOOM,
      minZoom: SWISSTOPO_MIN_ZOOM,
    },
  },
  swissimage: {
    options: {
      attribution: SWISSTOPO_ATTRIBUTION,
      // Orthophoto bis ca. 0,5 m (Zoom 18); kein separates 1:25k/1:50k-WMTS
      maxNativeZoom: 18,
      maxZoom: 21,
      minZoom: 7,
    },
  },
  osm: {
    options: {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxNativeZoom: 19,
      maxZoom: 19,
      referrerPolicy: 'origin',
    },
  },
}

function wmtsUrl(
  layerId: string,
  format: 'jpeg' | 'png' = 'jpeg',
  grid: '2056' | '3857' = '3857'
): string {
  return `${WMTS_BASE}/${layerId}/default/current/${grid}/{z}/{x}/{y}.${format}`
}

function swissTileOptions(maxNativeZoom: number): L.TileLayerOptions {
  return {
    attribution: SWISSTOPO_ATTRIBUTION,
    minZoom: SWISS_LV95_MIN_ZOOM,
    maxZoom: SWISS_LV95_MAX_ZOOM,
    maxNativeZoom,
  }
}

/** Welche Projektion ein Basis-Layer benötigt (OSM nur Web-Mercator). */
function projectionForLayer(layer: MapBaseLayer): Projection {
  if (!props.useSwissProjection) return 'webmercator'
  return layer === 'osm' ? 'webmercator' : 'lv95'
}

function buildTileLayerInstances(projection: Projection): Partial<Record<MapBaseLayer, L.Layer>> {
  if (projection === 'lv95') {
    // LV95-Kachelgitter (wie map.geo.admin.ch). OSM existiert hier nicht.
    return {
      swisstopo: L.tileLayer(wmtsUrl('ch.swisstopo.pixelkarte-farbe', 'jpeg', '2056'), swissTileOptions(26)),
      swissimage: L.tileLayer(wmtsUrl('ch.swisstopo.swissimage', 'jpeg', '2056'), swissTileOptions(28)),
    }
  }
  return {
    swisstopo: L.tileLayer(wmtsUrl('ch.swisstopo.pixelkarte-farbe', 'jpeg', '3857'), tileLayers.swisstopo.options),
    swissimage: L.tileLayer(wmtsUrl('ch.swisstopo.swissimage', 'jpeg', '3857'), tileLayers.swissimage.options),
    osm: L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', tileLayers.osm.options),
  }
}

function applyMapZoomLimitsForLayer(layerName: MapBaseLayer) {
  if (!map) return
  if (activeProjection === 'lv95') {
    map.setMaxZoom(SWISS_LV95_MAX_ZOOM)
    map.setMinZoom(SWISS_LV95_MIN_ZOOM)
    return
  }
  const opts = tileLayers[layerName].options
  const layerMax = opts.maxZoom ?? MAP_MAX_ZOOM
  const layerMin = opts.minZoom ?? 1
  map.setMaxZoom(Math.min(MAP_MAX_ZOOM, layerMax))
  map.setMinZoom(layerMin)
}

/* ---- Auflösung (m/px am Boden) ↔ Zoom, projektionsübergreifend ---- */
const WEB_MERCATOR_RES0 = 156543.03392

function lv95Resolution(zoom: number): number {
  const res = SWISS_LV95_RESOLUTIONS
  const z = Math.max(0, Math.min(res.length - 1, zoom))
  const i = Math.min(res.length - 2, Math.floor(z))
  const f = z - i
  return res[i] * Math.pow(res[i + 1] / res[i], f)
}

function lv95ZoomForResolution(targetRes: number): number {
  const res = SWISS_LV95_RESOLUTIONS
  if (targetRes >= res[0]) return 0
  if (targetRes <= res[res.length - 1]) return res.length - 1
  for (let i = 0; i < res.length - 1; i++) {
    if (targetRes <= res[i] && targetRes >= res[i + 1]) {
      return i + Math.log(res[i] / targetRes) / Math.log(res[i] / res[i + 1])
    }
  }
  return res.length - 1
}

function groundResolution(projection: Projection, zoom: number, lat: number): number {
  if (projection === 'lv95') return lv95Resolution(zoom)
  return (WEB_MERCATOR_RES0 / Math.pow(2, zoom)) * Math.cos((lat * Math.PI) / 180)
}

function zoomForGroundResolution(projection: Projection, res: number, lat: number): number {
  if (projection === 'lv95') return lv95ZoomForResolution(res)
  return Math.log2((WEB_MERCATOR_RES0 * Math.cos((lat * Math.PI) / 180)) / res)
}

function pinnedMarkerLatLng(): L.LatLng | null {
  if (props.latitude != null && props.longitude != null) {
    return L.latLng(props.latitude, props.longitude)
  }
  return marker ? marker.getLatLng() : null
}

function setLayer(layerName: MapBaseLayer, options?: SetLayerOptions) {
  if (!map) return

  if (options?.userInitiated) {
    userPickedLayer = true
  }

  // Layer braucht andere Projektion → Karte in passender Projektion neu aufbauen.
  const targetProjection = projectionForLayer(layerName)
  if (targetProjection !== activeProjection) {
    rebuildMap(targetProjection, layerName)
    return
  }

  if (currentLayer.value === layerName && currentTileLayer) return
  applyActiveLayer(layerName)
}

/** Layer innerhalb der aktuellen Projektion umschalten (kein Neuaufbau). */
function applyActiveLayer(layerName: MapBaseLayer) {
  if (!map || !tileLayerInstances) return
  const targetLayer = tileLayerInstances[layerName]
  if (!targetLayer) return

  suppressMapClick = true

  const anchor = pinnedMarkerLatLng() ?? map.getCenter()
  const zoom = map.getZoom()

  currentLayer.value = layerName
  currentTileLayer = targetLayer

  for (const [name, layer] of Object.entries(tileLayerInstances) as [MapBaseLayer, L.Layer][]) {
    if (name === layerName) {
      if (!map.hasLayer(layer)) layer.addTo(map)
    } else if (map.hasLayer(layer)) {
      map.removeLayer(layer)
    }
  }

  applyMapZoomLimitsForLayer(layerName)

  let targetZoom = zoom
  if (targetZoom > map.getMaxZoom()) targetZoom = map.getMaxZoom()
  if (targetZoom < map.getMinZoom()) targetZoom = map.getMinZoom()

  if (marker) {
    marker.setLatLng(anchor)
  }

  if (targetZoom !== zoom) {
    map.setZoomAround(anchor, targetZoom)
  }

  requestAnimationFrame(() => {
    if (map) map.invalidateSize({ pan: false })
    suppressMapClick = false
  })
}

/** Karte in einer anderen Projektion neu aufbauen, Mittelpunkt und Maßstab beibehalten. */
function rebuildMap(projection: Projection, layerName: MapBaseLayer) {
  if (!map) return
  const center = pinnedMarkerLatLng() ?? map.getCenter()
  const res = groundResolution(activeProjection, map.getZoom(), center.lat)
  const newZoom = zoomForGroundResolution(projection, res, center.lat)

  map.off()
  map.remove()
  map = null
  marker = null
  currentTileLayer = null
  tileLayerInstances = null

  createMap(projection, center, newZoom, layerName)
}

/** Leaflet-Karte in der gewünschten Projektion erstellen und Layer/Marker setzen. */
function createMap(
  projection: Projection,
  center: L.LatLng,
  zoom: number,
  initialLayer: MapBaseLayer
) {
  if (!mapContainer.value) return

  activeProjection = projection
  const swiss = projection === 'lv95'

  map = L.map(mapContainer.value, {
    crs: swiss ? getSwissCrs() : L.CRS.EPSG3857,
    maxZoom: swiss ? SWISS_LV95_MAX_ZOOM : MAP_MAX_ZOOM,
    minZoom: swiss ? SWISS_LV95_MIN_ZOOM : undefined,
    // Stufenloser Zoom, um den exakten Maßstab von map.geo.admin.ch zu treffen.
    zoomSnap: swiss ? 0 : 1,
    zoomControl: props.interactive,
    scrollWheelZoom: props.interactive && props.scrollWheelZoom,
    touchZoom: props.interactive,
    doubleClickZoom: props.interactive,
    boxZoom: props.interactive,
    keyboard: props.interactive,
    dragging: props.interactive,
  }).setView(center, zoom)

  tileLayerInstances = buildTileLayerInstances(projection)
  currentTileLayer = null
  applyActiveLayer(initialLayer)
  applyMapInteractivity()

  if (props.latitude != null && props.longitude != null) {
    setMarker(props.latitude, props.longitude, undefined, { recenter: false, allowAutoLayer: false })
  }

  // Klick-Handler nur bei interaktiver, editierbarer Karte
  applyMapClickHandler()
  applyMarkerEditability()

  // Fix: invalidateSize nach dem Layout stabil ist, damit Tiles korrekt laden
  setTimeout(() => {
    if (map) {
      map.invalidateSize({ pan: false })
      fitSwissOverviewIfNeeded()
    }
  }, 200)
}

function initMap() {
  if (!mapContainer.value || map) return

  const lat = props.latitude ?? DEFAULT_CENTER[0]
  const lng = props.longitude ?? DEFAULT_CENTER[1]
  const zoom =
    props.latitude && props.longitude
      ? props.zoom
      : props.editable
        ? props.useSwissProjection
          ? props.zoom
          : DEFAULT_ZOOM_EDITABLE
        : DEFAULT_ZOOM

  hasCoordinates.value = props.latitude !== null && props.longitude !== null

  const useSwiss = props.preferSwissMap && (
    !props.latitude || !props.longitude || isInSwitzerland.value
  )
  const initialLayer: MapBaseLayer = useSwiss ? 'swisstopo' : 'osm'
  createMap(projectionForLayer(initialLayer), L.latLng(lat, lng), zoom, initialLayer)

  if (props.latitude && props.longitude) {
    reverseGeocode(props.latitude, props.longitude)
  }
}

// Kartengrösse neu berechnen (für Parent-Komponenten)
function invalidateSize() {
  if (map) {
    map.invalidateSize({ pan: false })
    fitSwissOverviewIfNeeded()
  }
}

type SetMarkerOptions = {
  /** Karte auf Marker zentrieren (Suche, Klick, neue Koordinaten). */
  recenter?: boolean
  /** Automatisch OSM/Swisstopo wählen (nicht nach manuellem Layer-Klick). */
  allowAutoLayer?: boolean
}

function setMarker(
  lat: number,
  lng: number,
  zoomLevel?: number,
  options: SetMarkerOptions = {}
) {
  if (!map) return

  const recenter = options.recenter ?? true
  const allowAutoLayer = options.allowAutoLayer ?? true

  if (marker) {
    marker.setLatLng([lat, lng])
    applyMarkerEditability()
  } else {
    marker = L.marker([lat, lng], {
      draggable: props.interactive && props.editable,
    }).addTo(map)
    applyMarkerEditability()
  }

  if (recenter) {
    const targetZoom =
      zoomLevel ?? (map.getZoom() < minLocationZoom() ? searchResultZoom() : map.getZoom())
    map.setView([lat, lng], targetZoom, { animate: false })
    setTimeout(() => {
      if (map) map.invalidateSize({ pan: false })
    }, 100)
  }

  if (allowAutoLayer && props.preferSwissMap && !userPickedLayer) {
    const inSwiss =
      lat >= SWISS_BOUNDS.minLat &&
      lat <= SWISS_BOUNDS.maxLat &&
      lng >= SWISS_BOUNDS.minLng &&
      lng <= SWISS_BOUNDS.maxLng
    if (inSwiss && currentLayer.value === 'osm') {
      setLayer('swisstopo')
    } else if (!inSwiss && currentLayer.value !== 'osm') {
      setLayer('osm')
    }
  }
}

// Reverse Geocoding - Adresse aus Koordinaten ermitteln
async function reverseGeocode(lat: number, lng: number) {
  foundAddress.value = null
  
  try {
    const inSwiss = lat >= SWISS_BOUNDS.minLat && lat <= SWISS_BOUNDS.maxLat &&
                    lng >= SWISS_BOUNDS.minLng && lng <= SWISS_BOUNDS.maxLng

    if (inSwiss) {
      // Swisstopo Search-API für Reverse Geocoding (zuverlässiger als Gebäuderegister)
      try {
        const response = await fetch(
          `https://api3.geo.admin.ch/rest/services/api/SearchServer?searchText=${lat},${lng}&type=locations&sr=4326&limit=1`
        )
        const data = await response.json()
        
        if (data.results && data.results.length > 0) {
          const label = data.results[0].attrs?.label?.replace(/<[^>]*>/g, '') || ''
          if (label) {
            foundAddress.value = label
          }
        }
      } catch (e) {
        // Swisstopo fehlgeschlagen, weiter zu Nominatim
      }
      
      // Fallback auf Nominatim
      if (!foundAddress.value) {
        await reverseGeocodeNominatim(lat, lng)
      }
    } else {
      await reverseGeocodeNominatim(lat, lng)
    }
    
    if (foundAddress.value) {
      emit('address-found', foundAddress.value)
    }
  } catch (error) {
    console.error('Reverse geocoding error:', error)
  }
}

async function reverseGeocodeNominatim(lat: number, lng: number) {
  try {
    const response = await fetch(
      `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
      { headers: { 'Accept-Language': 'de' } }
    )
    const data = await response.json()
    
    if (data.address) {
      const addr = data.address
      const parts = []
      if (addr.road) {
        parts.push(addr.road + (addr.house_number ? ' ' + addr.house_number : ''))
      }
      const place = addr.city || addr.town || addr.village || addr.municipality
      if (addr.postcode && place) {
        parts.push(`${addr.postcode} ${place}`)
      } else if (place) {
        parts.push(place)
      }
      if (addr.country && addr.country !== 'Schweiz' && addr.country !== 'Switzerland') {
        parts.push(addr.country)
      }
      foundAddress.value = parts.join(', ') || data.display_name || null
    }
  } catch (error) {
    console.error('Nominatim reverse geocoding error:', error)
  }
}

// Forward Geocoding - Koordinaten aus Adresse ermitteln
async function searchAddress() {
  if (!props.address) return
  
  isLoading.value = true
  
  try {
    // Zuerst Swisstopo API versuchen (besser für CH Adressen)
    const swissResponse = await fetch(
      `https://api3.geo.admin.ch/rest/services/api/SearchServer?searchText=${encodeURIComponent(props.address)}&type=locations&limit=1`
    )
    const swissData = await swissResponse.json()
    
    if (swissData.results && swissData.results.length > 0) {
      const result = swissData.results[0].attrs
      const lat = result.lat
      const lng = result.lon
      
      setMarker(lat, lng, searchResultZoom(), { recenter: true, allowAutoLayer: true })
      emit('update:latitude', lat)
      emit('update:longitude', lng)
      emit('coordinates-changed', lat, lng)
      foundAddress.value = result.label?.replace(/<[^>]*>/g, '') || props.address
      hasCoordinates.value = true
      return
    }
    
    // Fallback auf Nominatim
    const response = await fetch(
      `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(props.address)}&limit=1`,
      { headers: { 'Accept-Language': 'de' } }
    )
    const results = await response.json()
    
    if (results.length > 0) {
      const { lat, lon, display_name } = results[0]
      const latitude = parseFloat(lat)
      const longitude = parseFloat(lon)
      
      setMarker(latitude, longitude, searchResultZoom(), { recenter: true, allowAutoLayer: true })
      emit('update:latitude', latitude)
      emit('update:longitude', longitude)
      emit('coordinates-changed', latitude, longitude)
      foundAddress.value = display_name
      hasCoordinates.value = true
    }
  } catch (error) {
    console.error('Geocoding error:', error)
  } finally {
    isLoading.value = false
  }
}

// Watch für Koordinaten-Änderungen
watch([() => props.latitude, () => props.longitude], ([lat, lng], prev) => {
  if (lat == null || lng == null || !map) return
  const [prevLat, prevLng] = prev ?? [null, null]
  const moved =
    prevLat == null ||
    prevLng == null ||
    !coordsNearlyEqual(prevLat, prevLng, lat, lng)
  setMarker(lat, lng, undefined, { recenter: moved, allowAutoLayer: moved })
  hasCoordinates.value = true
})

// Watch für preferSwissMap-Änderungen (z.B. Länderwechsel im Formular)
watch(() => props.preferSwissMap, (useSwiss) => {
  if (!map) return
  userPickedLayer = false
  if (useSwiss && currentLayer.value === 'osm') {
    setLayer('swisstopo')
  } else if (!useSwiss && currentLayer.value !== 'osm') {
    setLayer('osm')
  }
})

watch(
  () => props.zoom,
  (z) => {
    if (!map) return
    if (props.latitude != null && props.longitude != null) {
      map.setView([props.latitude, props.longitude], z)
    } else if (shouldFitSwissOverview()) {
      fitSwissOverviewIfNeeded()
    } else if (props.editable) {
      map.setView(map.getCenter(), z)
    }
  }
)

function applyMapInteractivity() {
  if (!map) return
  const on = props.interactive
  if (on) {
    if (props.scrollWheelZoom) map.scrollWheelZoom.enable()
    else map.scrollWheelZoom.disable()
    map.touchZoom.enable()
    map.doubleClickZoom.enable()
    map.boxZoom.enable()
    map.keyboard.enable()
    map.dragging.enable()
  } else {
    map.scrollWheelZoom.disable()
    map.touchZoom.disable()
    map.doubleClickZoom.disable()
    map.boxZoom.disable()
    map.keyboard.disable()
    map.dragging.disable()
  }
  applyMapClickHandler()
  applyMarkerEditability()
}

async function onMapClickSetLocation(e: L.LeafletMouseEvent) {
  if (suppressMapClick) return
  const { lat, lng } = e.latlng
  setMarker(lat, lng, undefined, { recenter: true, allowAutoLayer: true })
  emit('update:latitude', lat)
  emit('update:longitude', lng)
  emit('coordinates-changed', lat, lng)
  hasCoordinates.value = true
  await reverseGeocode(lat, lng)
}

function applyMapClickHandler() {
  if (!map) return
  map.off('click', onMapClickSetLocation)
  if (props.interactive && props.editable) {
    map.on('click', onMapClickSetLocation)
  }
}

async function onMarkerDragEnd() {
  if (!marker) return
  const pos = marker.getLatLng()
  emit('update:latitude', pos.lat)
  emit('update:longitude', pos.lng)
  emit('coordinates-changed', pos.lat, pos.lng)
  await reverseGeocode(pos.lat, pos.lng)
}

function applyMarkerEditability() {
  if (!marker) return
  const canDrag = props.interactive && props.editable
  if (canDrag) {
    marker.dragging?.enable()
    marker.off('dragend', onMarkerDragEnd)
    marker.on('dragend', onMarkerDragEnd)
  } else {
    marker.dragging?.disable()
    marker.off('dragend', onMarkerDragEnd)
  }
}

watch([() => props.interactive, () => props.scrollWheelZoom, () => props.editable], () => {
  applyMapInteractivity()
})

onMounted(() => {
  nextTick(() => {
    initMap()
    // Zweites invalidateSize nach dem Browser-Repaint
    requestAnimationFrame(() => {
      if (map) {
        map.invalidateSize({ pan: false })
      }
    })
  })
})

onUnmounted(() => {
  if (map) {
    map.remove()
    map = null
  }
  tileLayerInstances = null
  currentTileLayer = null
})

// Expose für Parent-Komponente
defineExpose({
  searchAddress,
  setMarker,
  reverseGeocode,
  setLayer,
  invalidateSize
})
</script>

<style scoped>
.map-wrapper {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  height: auto;
  box-sizing: border-box;
  /* Leaflet-interne z-index-Stufen (Panes/Controls bis 1000) auf diese
     Komponente begrenzen, damit die Karte nicht über Overlays wie das
     Settings-Menü (z-index 20) gezeichnet wird. */
  isolation: isolate;
}

.map-stage {
  position: relative;
  width: 100%;
}

.map-container {
  width: 100%;
  height: v-bind(height);
  min-height: v-bind(height);
  border-radius: 8px;
  overflow: hidden;
  background: #f3f4f6;
  position: relative;
}

.no-coordinates {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #6b7280;
  z-index: 400;
  background: rgba(249, 250, 251, 0.9);
}

.no-coordinates.editable {
  pointer-events: none;
  background: rgba(249, 250, 251, 0.5);
}

.no-coordinates p {
  margin: 0;
  font-size: 14px;
}

.loading-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: rgba(255, 255, 255, 0.8);
  z-index: 500;
}

/* Koordinaten-Anzeige */
.coordinates-display {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 10px 12px;
  font-size: 13px;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}

.coord-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 4px 12px;
}

.coord-row + .coord-row {
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px solid #e5e7eb;
}

.coord-row.secondary {
  opacity: 0.7;
  font-size: 12px;
}

.coord-row.address {
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
}

.coord-label {
  color: #6b7280;
  font-weight: 500;
  white-space: nowrap;
}

.coord-value {
  color: #1f2937;
  font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
  font-size: 12px;
  min-width: 0;
  flex: 1 1 auto;
  overflow-wrap: anywhere;
  word-break: break-word;
  text-align: right;
  user-select: all;
  cursor: text;
}

.coord-row.address .coord-value {
  font-family: inherit;
  font-size: 13px;
  text-align: left;
  width: 100%;
}

/* Layer Control (über map-stage, nicht im Leaflet-Container) */
.layer-control {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 1000;
  pointer-events: auto;
  display: flex;
  gap: 4px;
  background: white;
  border-radius: 6px;
  padding: 4px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.layer-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 4px;
  background: transparent;
  cursor: pointer;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.layer-btn:hover {
  background: #f3f4f6;
}

.layer-btn.active {
  background: #dbeafe;
}

/* Leaflet Styles überschreiben */
:deep(.leaflet-container) {
  height: 100%;
  width: 100%;
  font-family: inherit;
}

:deep(.leaflet-control-attribution) {
  font-size: 10px;
  background: rgba(255, 255, 255, 0.8) !important;
}

:deep(.leaflet-control-attribution a) {
  color: #3b82f6;
}

.external-map-links {
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-top: 12px;
  min-width: 0;
  max-width: 100%;
  padding-bottom: 2px;
}

.external-map-links .btn {
  flex: 1 1 auto;
  min-width: 0;
  max-width: 100%;
  width: auto;
  justify-content: center;
  white-space: nowrap;
  text-align: center;
}

@media (max-width: 520px) {
  .external-map-links {
    flex-direction: column;
    align-items: stretch;
  }

  .external-map-links .btn {
    width: 100%;
    white-space: normal;
  }
}
</style>
