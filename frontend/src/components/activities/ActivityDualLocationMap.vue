<template>
  <div class="activity-dual-location-map">
    <div ref="mapContainer" class="activity-dual-location-map__canvas" :style="{ height }" />
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

const props = withDefaults(
  defineProps<{
    pins: ActivityLocationPin[]
    height?: string
    /** Pan/zoom erlauben. */
    interactive?: boolean
    /** Pin-ID die verschoben werden darf (z. B. «venue»). */
    editablePinId?: string | null
  }>(),
  {
    height: '220px',
    interactive: true,
    editablePinId: null,
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
const markerById = new Map<string, L.Marker>()

const SWISS_BOUNDS = L.latLngBounds([45.8, 5.9], [47.85, 10.55])
const DEFAULT_CENTER: L.LatLngExpression = [46.8182, 8.2275]
const DEFAULT_ZOOM = 7

const VARIANT_COLORS: Record<NonNullable<ActivityLocationPin['variant']>, string> = {
  venue: '#2563eb',
  delivery: '#ea580c',
  poi: '#16a34a',
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

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
  }).addTo(map)

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
    markerById.clear()
  }
}

function invalidateSize() {
  if (!map) return
  map.invalidateSize({ pan: false })
  renderMarkers({ fit: !props.editablePinId })
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

onMounted(() => {
  void nextTick(() => initMap())
})

onUnmounted(() => {
  destroyMap()
})
</script>

<style scoped>
.activity-dual-location-map__canvas {
  width: 100%;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
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
