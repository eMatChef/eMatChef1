<template>
  <div class="activity-dual-location-map">
    <div class="activity-dual-location-map__stage">
      <div ref="mapContainer" class="activity-dual-location-map__canvas" :style="{ height }" />
      <div
        v-if="showLayerControl"
        class="activity-dual-location-map__layers"
        @mousedown.stop
        @click.stop
        @dblclick.stop
      >
        <button
          type="button"
          :class="['activity-dual-location-map__layer-btn', { active: currentLayer === 'swisstopo' }]"
          :title="t('components.mapView.layerSwisstopoTitle')"
          @click.stop="setLayer('swisstopo')"
        >
          🇨🇭
        </button>
        <button
          type="button"
          :class="['activity-dual-location-map__layer-btn', { active: currentLayer === 'swissimage' }]"
          :title="t('components.mapView.layerSwissimageTitle')"
          @click.stop="setLayer('swissimage')"
        >
          📷
        </button>
        <button
          type="button"
          :class="['activity-dual-location-map__layer-btn', { active: currentLayer === 'osm' }]"
          :title="t('components.mapView.layerOsmTitle')"
          @click.stop="setLayer('osm')"
        >
          🌍
        </button>
      </div>
    </div>
    <p v-if="pins.length === 0 && !editablePinId" class="activity-dual-location-map__empty field-hint text-muted">
      {{ t('activities.venueLocations.noMapCoords') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

export interface ActivityLocationPin {
  id: string
  label: string
  latitude: number
  longitude: number
  variant?: 'venue' | 'delivery' | 'poi'
  /** Hex-Farbe für freie POIs; überschreibt Variantenfarbe wenn gesetzt. */
  color?: string | null
}

type MapBaseLayer = 'swisstopo' | 'swissimage' | 'osm'

const WMTS_BASE = 'https://wmts.geo.admin.ch/1.0.0'
const SWISSTOPO_ATTRIBUTION =
  '&copy; <a href="https://www.swisstopo.admin.ch">swisstopo</a>'

const props = withDefaults(
  defineProps<{
    pins: ActivityLocationPin[]
    height?: string
    /** Pan/zoom erlauben. */
    interactive?: boolean
    /** Pin-ID die verschoben werden darf (z. B. «venue»). */
    editablePinId?: string | null
    /** Swisstopo als Standard-Layer (wie MapView). */
    preferSwissMap?: boolean
    /** Layer-Umschalter (Landeskarte / Luftbild / OSM). */
    showLayerControl?: boolean
  }>(),
  {
    height: '220px',
    interactive: true,
    editablePinId: null,
    preferSwissMap: true,
    showLayerControl: true,
  },
)

const emit = defineEmits<{
  'pin-moved': [payload: { id: string; latitude: number; longitude: number }]
  'map-click': [payload: { latitude: number; longitude: number }]
}>()

const { t } = useI18n()
const mapContainer = ref<HTMLDivElement>()
let map: L.Map | null = null
let markersLayer: L.LayerGroup | null = null
let activeTileLayer: L.TileLayer | null = null
const markerById = new Map<string, L.Marker>()
const currentLayer = ref<MapBaseLayer>('swisstopo')

const SWISS_BOUNDS = L.latLngBounds([45.8, 5.9], [47.85, 10.55])
const DEFAULT_CENTER: L.LatLngExpression = [46.8182, 8.2275]
const DEFAULT_ZOOM = 7

const VARIANT_COLORS: Record<NonNullable<ActivityLocationPin['variant']>, string> = {
  venue: '#2563eb',
  delivery: '#ea580c',
  poi: '#16a34a',
}

function wmtsUrl(layerId: string, format: 'jpeg' | 'png' = 'jpeg'): string {
  return `${WMTS_BASE}/${layerId}/default/current/3857/{z}/{x}/{y}.${format}`
}

function createTileLayer(layer: MapBaseLayer): L.TileLayer {
  if (layer === 'swissimage') {
    return L.tileLayer(wmtsUrl('ch.swisstopo.swissimage', 'jpeg'), {
      attribution: SWISSTOPO_ATTRIBUTION,
      maxNativeZoom: 18,
      maxZoom: 21,
      minZoom: 7,
    })
  }
  if (layer === 'swisstopo') {
    return L.tileLayer(wmtsUrl('ch.swisstopo.pixelkarte-farbe', 'jpeg'), {
      attribution: SWISSTOPO_ATTRIBUTION,
      maxNativeZoom: 18,
      maxZoom: 20,
      minZoom: 7,
    })
  }
  return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxNativeZoom: 19,
    maxZoom: 19,
    referrerPolicy: 'origin',
  })
}

function setLayer(layer: MapBaseLayer) {
  if (!map) return
  currentLayer.value = layer
  if (activeTileLayer) {
    map.removeLayer(activeTileLayer)
    activeTileLayer = null
  }
  activeTileLayer = createTileLayer(layer)
  activeTileLayer.addTo(map)
  // Marker über den Tiles halten (FeatureGroup-API; LayerGroup-Typen ohne bringToFront)
  if (markersLayer) (markersLayer as L.FeatureGroup).bringToFront()
}

function resolvePinColor(pin: ActivityLocationPin): string {
  if (pin.color && /^#[0-9A-Fa-f]{6}$/.test(pin.color)) return pin.color
  return VARIANT_COLORS[pin.variant ?? 'venue']
}

function pinIcon(pin: ActivityLocationPin): L.DivIcon {
  const color = resolvePinColor(pin)
  const kind = pin.variant ?? 'venue'
  return L.divIcon({
    className: 'activity-dual-location-map__marker-wrap',
    html: `<span class="activity-dual-location-map__marker activity-dual-location-map__marker--${kind}" style="background:${color}"></span>`,
    iconSize: [20, 20],
    iconAnchor: [10, 10],
  })
}

function fitToCurrentPins(pinsToRender: ActivityLocationPin[]) {
  if (!map) return
  if (pinsToRender.length === 1) {
    map.setView([pinsToRender[0].latitude, pinsToRender[0].longitude], 15, { animate: false })
  } else if (pinsToRender.length > 1) {
    const bounds = L.latLngBounds(pinsToRender.map((p) => [p.latitude, p.longitude] as L.LatLngExpression))
    map.fitBounds(bounds.pad(0.35), { animate: false, maxZoom: 16, padding: [28, 28] })
  } else {
    map.fitBounds(SWISS_BOUNDS, { animate: false })
  }
}

function renderMarkers(opts: { fit?: boolean } = {}) {
  if (!map) return
  const shouldFit = opts.fit ?? true
  if (markersLayer) {
    markersLayer.clearLayers()
  } else {
    markersLayer = L.layerGroup().addTo(map)
  }
  markerById.clear()

  const pinsToRender = props.pins
  for (const pin of pinsToRender) {
    const canDrag = props.interactive && props.editablePinId === pin.id
    const marker = L.marker([pin.latitude, pin.longitude], {
      icon: pinIcon(pin),
      draggable: canDrag,
      interactive: canDrag || props.interactive,
    })
    marker.bindTooltip(pin.label, {
      permanent: true,
      direction: 'top',
      offset: [0, -8],
      className: 'activity-dual-location-map__tooltip',
    })
    if (canDrag) {
      marker.on('dragend', () => {
        const pos = marker.getLatLng()
        emit('pin-moved', { id: pin.id, latitude: pos.lat, longitude: pos.lng })
      })
    }
    markersLayer.addLayer(marker)
    markerById.set(pin.id, marker)
  }

  if (shouldFit) {
    fitToCurrentPins(pinsToRender)
  }
}

function onMapClick(e: L.LeafletMouseEvent) {
  if (!props.interactive || !props.editablePinId) return
  const { lat, lng } = e.latlng
  const editableId = props.editablePinId
  const existing = markerById.get(editableId)
  if (existing) {
    existing.setLatLng([lat, lng])
    emit('pin-moved', { id: editableId, latitude: lat, longitude: lng })
    return
  }
  emit('map-click', { latitude: lat, longitude: lng })
}

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
    if (map.zoomControl) map.zoomControl.addTo(map)
  } else {
    map.scrollWheelZoom.disable()
    map.touchZoom.disable()
    map.doubleClickZoom.disable()
    map.boxZoom.disable()
    map.keyboard.disable()
    map.dragging.disable()
  }
  map.off('click', onMapClick)
  if (on && props.editablePinId) {
    map.on('click', onMapClick)
  }
}

function initMap() {
  if (!mapContainer.value || map) return

  map = L.map(mapContainer.value, {
    zoomControl: props.interactive,
    scrollWheelZoom: props.interactive,
    touchZoom: props.interactive,
    doubleClickZoom: props.interactive,
    boxZoom: props.interactive,
    keyboard: props.interactive,
    dragging: props.interactive,
  }).setView(DEFAULT_CENTER, DEFAULT_ZOOM)

  setLayer(props.preferSwissMap ? 'swisstopo' : 'osm')
  applyMapInteractivity()
  renderMarkers({ fit: true })

  setTimeout(() => {
    map?.invalidateSize({ pan: false })
    renderMarkers({ fit: true })
  }, 150)
}

function destroyMap() {
  if (map) {
    map.off('click', onMapClick)
    map.remove()
    map = null
    markersLayer = null
    activeTileLayer = null
    markerById.clear()
  }
}

function invalidateSize() {
  if (!map) return
  map.invalidateSize({ pan: false })
  // Ohne Pins: Schweiz-Übersicht behalten (z. B. Eventstandort erfassen)
  const shouldFit = !props.editablePinId || props.pins.length === 0
  renderMarkers({ fit: shouldFit })
}

function fitToPins() {
  if (!map) return
  fitToCurrentPins(props.pins)
}

defineExpose({ invalidateSize, fitToPins })

watch(
  () => props.pins.map((p) => `${p.id}:${p.latitude}:${p.longitude}:${p.color ?? ''}`).join('|'),
  () => {
    if (map) {
      nextTick(() => {
        // Beim Verschieben nicht neu zoomen — nur Marker aktualisieren
        renderMarkers({ fit: !props.editablePinId })
      })
    }
  },
)

watch(
  () => [props.interactive, props.editablePinId] as const,
  () => {
    applyMapInteractivity()
    renderMarkers({ fit: false })
  },
)

watch(
  () => props.preferSwissMap,
  (useSwiss) => {
    if (!map) return
    setLayer(useSwiss ? 'swisstopo' : 'osm')
  },
)

onMounted(() => {
  void nextTick(() => initMap())
})

onUnmounted(() => {
  destroyMap()
})
</script>

<style scoped>
.activity-dual-location-map__stage {
  position: relative;
}

.activity-dual-location-map__canvas {
  width: 100%;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
}

.activity-dual-location-map__layers {
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

.activity-dual-location-map__layer-btn {
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

.activity-dual-location-map__layer-btn:hover {
  background: #f3f4f6;
}

.activity-dual-location-map__layer-btn.active {
  background: #dbeafe;
}

.activity-dual-location-map__empty {
  margin: 8px 0 0;
}

:deep(.activity-dual-location-map__marker-wrap) {
  background: transparent;
  border: none;
}

:deep(.activity-dual-location-map__marker) {
  display: block;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.35);
}

:deep(.activity-dual-location-map__tooltip) {
  background: #0f172a;
  color: #fff;
  border: none;
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 11px;
  font-weight: 600;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.25);
}

:deep(.activity-dual-location-map__tooltip::before) {
  border-top-color: #0f172a;
}
</style>
