<template>
  <div class="map-wrapper">
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

      <!-- Layer-Auswahl -->
      <div v-if="interactive && showLayerControl" class="layer-control">
        <button
          type="button"
          :class="['layer-btn', { active: currentLayer === 'swisstopo' }]"
          @click="setLayer('swisstopo')"
          :title="t('components.mapView.layerSwisstopoTitle')"
        >
          🇨🇭
        </button>
        <button
          type="button"
          :class="['layer-btn', { active: currentLayer === 'swissimage' }]"
          @click="setLayer('swissimage')"
          :title="t('components.mapView.layerSwissimageTitle')"
        >
          📷
        </button>
        <button
          type="button"
          :class="['layer-btn', { active: currentLayer === 'osm' }]"
          @click="setLayer('osm')"
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
      v-if="!interactive && showExternalMapLinks && hasCoordinates && latitude != null && longitude != null"
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
import { googleMapsCoordinatesUrl, swisstopoMapUrl } from '@/utils/mapExternalLinks'
import 'leaflet/dist/leaflet.css'

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
  /** Links zu map.geo.admin.ch und Google Maps unter der Karte. */
  showExternalMapLinks?: boolean
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
  showExternalMapLinks: false,
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
let tileLayerInstances: Record<MapBaseLayer, L.Layer> | null = null

const WMTS_BASE = 'https://wmts.geo.admin.ch/1.0.0'
const SWISSTOPO_ATTRIBUTION =
  '&copy; <a href="https://www.swisstopo.admin.ch">swisstopo</a>'
const MAP_MAX_ZOOM = 22

/** Zoom-Stufen für gestaffelte Landeskarten (EPSG:3857, geo.admin.ch). */
const SWISSTOPO_SCALE_ZOOM = {
  overview: { min: 7, max: 13 },
  pk50: { min: 14, max: 16 },
  pk25: { min: 17, max: 18 },
  /** Landeskarte 1:10'000 – native Kacheln bis Zoom 19, darüber skaliert Leaflet. */
  lk10: { min: 19, max: MAP_MAX_ZOOM },
} as const

const hasCoordinates = ref(false)
const isLoading = ref(false)
type MapBaseLayer = 'swisstopo' | 'swissimage' | 'osm'

const foundAddress = ref<string | null>(null)
const currentLayer = ref<MapBaseLayer>('swisstopo')

// Schweiz-Zentrum als Fallback
const DEFAULT_CENTER: [number, number] = [46.8182, 8.2275]
const DEFAULT_ZOOM = 7
/** Kartenansicht ohne Koordinaten im Bearbeitungsmodus (regional, nicht ganze CH). */
const DEFAULT_ZOOM_EDITABLE = 11
/** Ab dieser Zoomstufe gilt die Karte als «nah genug» – darunter nach Treffer ranzoomen. */
const MIN_LOCATION_ZOOM = 14
const SEARCH_RESULT_ZOOM = 17

// Schweiz Bounding Box (ungefähr)
const SWISS_BOUNDS = {
  minLat: 45.8,
  maxLat: 47.85,
  minLng: 5.9,
  maxLng: 10.55
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
  return swisstopoMapUrl(props.latitude, props.longitude, mapLang.value)
})

const googleMapsLink = computed(() => {
  if (props.latitude == null || props.longitude == null) return '#'
  return googleMapsCoordinatesUrl(props.latitude, props.longitude)
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

// Formatierung Schweizer Koordinaten
function formatSwissCoords(lat: number | null | undefined, lng: number | null | undefined): string {
  if (!lat || !lng) return '-'
  const { e, n } = wgs84ToLV95(lat, lng)
  return `E ${e.toLocaleString('de-CH')} / N ${n.toLocaleString('de-CH')}`
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
      maxNativeZoom: 19,
      maxZoom: MAP_MAX_ZOOM,
      minZoom: SWISSTOPO_SCALE_ZOOM.overview.min,
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
      maxZoom: 20,
    },
  },
}

function wmtsUrl(layerId: string, format: 'jpeg' | 'png' = 'jpeg'): string {
  return `${WMTS_BASE}/${layerId}/default/current/3857/{z}/{x}/{y}.${format}`
}

function buildSwisstopoLayerGroup(): L.LayerGroup {
  const overview = L.tileLayer(wmtsUrl('ch.swisstopo.pixelkarte-farbe'), {
    attribution: SWISSTOPO_ATTRIBUTION,
    minZoom: SWISSTOPO_SCALE_ZOOM.overview.min,
    maxZoom: SWISSTOPO_SCALE_ZOOM.overview.max,
    maxNativeZoom: 18,
  })
  const pk50 = L.tileLayer(wmtsUrl('ch.swisstopo.pixelkarte-farbe-pk50.noscale'), {
    attribution: SWISSTOPO_ATTRIBUTION,
    minZoom: SWISSTOPO_SCALE_ZOOM.pk50.min,
    maxZoom: SWISSTOPO_SCALE_ZOOM.pk50.max,
    maxNativeZoom: SWISSTOPO_SCALE_ZOOM.pk50.max,
  })
  const pk25 = L.tileLayer(wmtsUrl('ch.swisstopo.pixelkarte-farbe-pk25.noscale'), {
    attribution: SWISSTOPO_ATTRIBUTION,
    minZoom: SWISSTOPO_SCALE_ZOOM.pk25.min,
    maxZoom: SWISSTOPO_SCALE_ZOOM.pk25.max,
    maxNativeZoom: SWISSTOPO_SCALE_ZOOM.pk25.max,
  })
  const lk10 = L.tileLayer(wmtsUrl('ch.swisstopo.landeskarte-farbe-10', 'png'), {
    attribution: SWISSTOPO_ATTRIBUTION,
    minZoom: SWISSTOPO_SCALE_ZOOM.lk10.min,
    maxZoom: SWISSTOPO_SCALE_ZOOM.lk10.max,
    maxNativeZoom: 19,
  })
  return L.layerGroup([overview, pk50, pk25, lk10])
}

function buildTileLayerInstances(): Record<MapBaseLayer, L.Layer> {
  return {
    swisstopo: buildSwisstopoLayerGroup(),
    swissimage: L.tileLayer(wmtsUrl('ch.swisstopo.swissimage'), tileLayers.swissimage.options),
    osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', tileLayers.osm.options),
  }
}

function applyMapZoomLimitsForLayer(layerName: MapBaseLayer) {
  if (!map) return
  const opts = tileLayers[layerName].options
  const layerMax = opts.maxZoom ?? MAP_MAX_ZOOM
  const layerMin = opts.minZoom ?? 1
  map.setMaxZoom(Math.min(MAP_MAX_ZOOM, layerMax))
  map.setMinZoom(layerMin)
}

function setLayer(layerName: MapBaseLayer) {
  if (!map || !tileLayerInstances) return
  if (currentLayer.value === layerName && currentTileLayer) return

  const center = map.getCenter()
  const zoom = map.getZoom()

  currentLayer.value = layerName
  currentTileLayer = tileLayerInstances[layerName]

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

  map.setView(center, targetZoom, { animate: false })

  if (marker) {
    marker.addTo(map)
  }
}

function initMap() {
  if (!mapContainer.value || map) return
  
  const lat = props.latitude ?? DEFAULT_CENTER[0]
  const lng = props.longitude ?? DEFAULT_CENTER[1]
  const zoom =
    props.latitude && props.longitude
      ? props.zoom
      : props.editable
        ? DEFAULT_ZOOM_EDITABLE
        : DEFAULT_ZOOM
  
  hasCoordinates.value = props.latitude !== null && props.longitude !== null
  
  map = L.map(mapContainer.value, {
    maxZoom: MAP_MAX_ZOOM,
    zoomControl: props.interactive,
    scrollWheelZoom: props.interactive,
    touchZoom: props.interactive,
    doubleClickZoom: props.interactive,
    boxZoom: props.interactive,
    keyboard: props.interactive,
    dragging: props.interactive,
  }).setView([lat, lng], zoom)

  tileLayerInstances = buildTileLayerInstances()

  // Initial Layer basierend auf Position oder Präferenz
  const useSwiss = props.preferSwissMap && (
    !props.latitude || !props.longitude || isInSwitzerland.value
  )
  setLayer(useSwiss ? 'swisstopo' : 'osm')
  applyMapInteractivity()
  
  // Marker setzen wenn Koordinaten vorhanden
  if (props.latitude && props.longitude) {
    setMarker(props.latitude, props.longitude)
    // Reverse Geocoding für initiale Position
    reverseGeocode(props.latitude, props.longitude)
  }
  
  // Klick-Handler nur bei interaktiver, editierbarer Karte
  if (props.interactive && props.editable) {
    map.on('click', async (e: L.LeafletMouseEvent) => {
      const { lat, lng } = e.latlng
      setMarker(lat, lng)
      emit('update:latitude', lat)
      emit('update:longitude', lng)
      emit('coordinates-changed', lat, lng)
      hasCoordinates.value = true
      
      // Reverse Geocoding
      await reverseGeocode(lat, lng)
    })
  }
  
  // Fix: invalidateSize nach dem Layout stabil ist, damit Tiles korrekt laden
  setTimeout(() => {
    if (map) {
      map.invalidateSize()
    }
  }, 200)
}

// Kartengrösse neu berechnen (für Parent-Komponenten)
function invalidateSize() {
  if (map) {
    map.invalidateSize()
  }
}

function setMarker(lat: number, lng: number, zoomLevel?: number) {
  if (!map) return
  
  if (marker) {
    marker.setLatLng([lat, lng])
  } else {
    marker = L.marker([lat, lng], {
      draggable: props.interactive && props.editable,
    }).addTo(map)
    
    if (props.interactive && props.editable) {
      marker.on('dragend', async () => {
        const pos = marker!.getLatLng()
        emit('update:latitude', pos.lat)
        emit('update:longitude', pos.lng)
        emit('coordinates-changed', pos.lat, pos.lng)
        await reverseGeocode(pos.lat, pos.lng)
      })
    }
  }

  const targetZoom =
    zoomLevel ?? (map.getZoom() < MIN_LOCATION_ZOOM ? SEARCH_RESULT_ZOOM : map.getZoom())
  map.setView([lat, lng], targetZoom)
  
  // Tiles neu laden falls nötig
  setTimeout(() => {
    if (map) map.invalidateSize()
  }, 100)
  
  // Layer wechseln wenn nötig (Luftbild nicht überschreiben; in CH nur von OSM auf Landeskarte)
  const inSwiss = lat >= SWISS_BOUNDS.minLat && lat <= SWISS_BOUNDS.maxLat &&
                  lng >= SWISS_BOUNDS.minLng && lng <= SWISS_BOUNDS.maxLng
  if (props.preferSwissMap) {
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
      
      setMarker(lat, lng, SEARCH_RESULT_ZOOM)
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
      
      setMarker(latitude, longitude, SEARCH_RESULT_ZOOM)
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
watch([() => props.latitude, () => props.longitude], ([lat, lng]) => {
  if (lat && lng && map) {
    setMarker(lat, lng)
    hasCoordinates.value = true
  }
})

// Watch für preferSwissMap-Änderungen (z.B. Länderwechsel im Formular)
watch(() => props.preferSwissMap, (useSwiss) => {
  if (!map) return
  if (useSwiss && currentLayer.value === 'osm') {
    setLayer('swisstopo')
  } else if (!useSwiss && currentLayer.value !== 'osm') {
    setLayer('osm')
  }
})

watch(
  () => props.zoom,
  (z) => {
    if (!map || props.latitude == null || props.longitude == null) return
    map.setView([props.latitude, props.longitude], z)
  }
)

function applyMapInteractivity() {
  if (!map) return
  const on = props.interactive
  if (on) {
    map.scrollWheelZoom.enable()
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
}

watch(() => props.interactive, () => {
  applyMapInteractivity()
})

onMounted(() => {
  nextTick(() => {
    initMap()
    // Zweites invalidateSize nach dem Browser-Repaint
    requestAnimationFrame(() => {
      if (map) {
        map.invalidateSize()
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
  height: 100%;
  box-sizing: border-box;
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
}

.coord-row.address .coord-value {
  font-family: inherit;
  font-size: 13px;
  text-align: left;
  width: 100%;
}

/* Layer Control */
.layer-control {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 500;
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
  flex-direction: column;
  align-items: stretch;
  gap: 10px;
  margin-top: 12px;
  min-width: 0;
  max-width: 100%;
  padding-bottom: 2px;
}

.external-map-links .btn {
  width: 100%;
  justify-content: center;
  white-space: normal;
  text-align: center;
}
</style>
