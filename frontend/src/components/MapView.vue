<template>
  <div class="map-wrapper">
    <div class="map-container" ref="mapContainer">
      <div v-if="!hasCoordinates && !isLoading" :class="['no-coordinates', { editable: editable }]">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="#d1d5db"/>
          <circle cx="12" cy="9" r="2.5" fill="#9ca3af"/>
        </svg>
        <p>{{ editable ? 'Klicken Sie auf die Karte, um einen Standort zu setzen' : 'Keine Koordinaten verfügbar' }}</p>
      </div>
      <div v-if="isLoading" class="loading-overlay">
        <div class="spinner"></div>
        <p>Suche Adresse...</p>
      </div>

      <!-- Layer-Auswahl -->
      <div v-if="showLayerControl" class="layer-control">
        <button 
          :class="['layer-btn', { active: currentLayer === 'swisstopo' }]" 
          @click="setLayer('swisstopo')"
          title="Swisstopo (CH)"
        >
          🇨🇭
        </button>
        <button 
          :class="['layer-btn', { active: currentLayer === 'osm' }]" 
          @click="setLayer('osm')"
          title="OpenStreetMap"
        >
          🌍
        </button>
      </div>
    </div>
    
    <!-- Koordinaten-Anzeige -->
    <div v-if="hasCoordinates && showCoordinates" class="coordinates-display">
      <div class="coord-row">
        <span class="coord-label">{{ isInSwitzerland ? 'LV95 (CH)' : 'WGS84' }}</span>
        <span class="coord-value">
          {{ isInSwitzerland ? formatSwissCoords(latitude, longitude) : formatWGS84(latitude, longitude) }}
        </span>
      </div>
      <div v-if="isInSwitzerland" class="coord-row secondary">
        <span class="coord-label">WGS84</span>
        <span class="coord-value">{{ formatWGS84(latitude, longitude) }}</span>
      </div>
      <div v-if="foundAddress" class="coord-row address">
        <span class="coord-label">Adresse</span>
        <span class="coord-value">{{ foundAddress }}</span>
      </div>
    </div>
    
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import L from 'leaflet'
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
}

const props = withDefaults(defineProps<Props>(), {
  latitude: null,
  longitude: null,
  address: '',
  editable: false,
  height: '300px',
  zoom: 15,
  showCoordinates: true,
  showLayerControl: true,
  preferSwissMap: true
})

const emit = defineEmits<{
  'update:latitude': [value: number]
  'update:longitude': [value: number]
  'coordinates-changed': [lat: number, lng: number]
  'address-found': [address: string]
}>()

const mapContainer = ref<HTMLDivElement>()
let map: L.Map | null = null
let marker: L.Marker | null = null
let currentTileLayer: L.TileLayer | null = null

const hasCoordinates = ref(false)
const isLoading = ref(false)
const foundAddress = ref<string | null>(null)
const currentLayer = ref<'swisstopo' | 'osm'>('swisstopo')

// Schweiz-Zentrum als Fallback
const DEFAULT_CENTER: [number, number] = [46.8182, 8.2275]
const DEFAULT_ZOOM = 7
const SEARCH_RESULT_ZOOM = 16

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

// Tile-Layer definieren
const tileLayers = {
  swisstopo: {
    url: 'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-farbe/default/current/3857/{z}/{x}/{y}.jpeg',
    options: {
      attribution: '&copy; <a href="https://www.swisstopo.admin.ch">swisstopo</a>',
      maxZoom: 18,
      minZoom: 7
    }
  },
  osm: {
    url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    options: {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19
    }
  }
}

function setLayer(layerName: 'swisstopo' | 'osm') {
  if (!map) return
  
  currentLayer.value = layerName
  
  if (currentTileLayer) {
    map.removeLayer(currentTileLayer)
  }
  
  const layer = tileLayers[layerName]
  currentTileLayer = L.tileLayer(layer.url, layer.options).addTo(map)
}

function initMap() {
  if (!mapContainer.value || map) return
  
  const lat = props.latitude ?? DEFAULT_CENTER[0]
  const lng = props.longitude ?? DEFAULT_CENTER[1]
  const zoom = props.latitude && props.longitude ? props.zoom : DEFAULT_ZOOM
  
  hasCoordinates.value = props.latitude !== null && props.longitude !== null
  
  map = L.map(mapContainer.value).setView([lat, lng], zoom)
  
  // Initial Layer basierend auf Position oder Präferenz
  const useSwiss = props.preferSwissMap && (
    !props.latitude || !props.longitude || isInSwitzerland.value
  )
  setLayer(useSwiss ? 'swisstopo' : 'osm')
  
  // Marker setzen wenn Koordinaten vorhanden
  if (props.latitude && props.longitude) {
    setMarker(props.latitude, props.longitude)
    // Reverse Geocoding für initiale Position
    reverseGeocode(props.latitude, props.longitude)
  }
  
  // Klick-Handler für editierbare Karte
  if (props.editable) {
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

function setMarker(lat: number, lng: number) {
  if (!map) return
  
  if (marker) {
    marker.setLatLng([lat, lng])
  } else {
    marker = L.marker([lat, lng], {
      draggable: props.editable
    }).addTo(map)
    
    if (props.editable) {
      marker.on('dragend', async () => {
        const pos = marker!.getLatLng()
        emit('update:latitude', pos.lat)
        emit('update:longitude', pos.lng)
        emit('coordinates-changed', pos.lat, pos.lng)
        await reverseGeocode(pos.lat, pos.lng)
      })
    }
  }
  
  map.setView([lat, lng], map.getZoom())
  
  // Tiles neu laden falls nötig
  setTimeout(() => {
    if (map) map.invalidateSize()
  }, 100)
  
  // Layer wechseln wenn nötig
  const inSwiss = lat >= SWISS_BOUNDS.minLat && lat <= SWISS_BOUNDS.maxLat &&
                  lng >= SWISS_BOUNDS.minLng && lng <= SWISS_BOUNDS.maxLng
  if (props.preferSwissMap) {
    if (inSwiss && currentLayer.value !== 'swisstopo') {
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
      
      setMarker(lat, lng)
      if (map) {
        map.setView([lat, lng], Math.max(map.getZoom(), SEARCH_RESULT_ZOOM))
      }
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
      
      setMarker(latitude, longitude)
      if (map) {
        map.setView([latitude, longitude], Math.max(map.getZoom(), SEARCH_RESULT_ZOOM))
      }
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
  if (useSwiss && currentLayer.value !== 'swisstopo') {
    setLayer('swisstopo')
  } else if (!useSwiss && currentLayer.value !== 'osm') {
    setLayer('osm')
  }
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
  height: 100%;
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
}

.coord-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
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
}

.coord-row.address .coord-value {
  font-family: inherit;
  font-size: 13px;
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
</style>
