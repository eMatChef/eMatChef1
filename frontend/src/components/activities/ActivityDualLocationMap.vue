<template>
  <div class="activity-dual-location-map">
    <div ref="mapContainer" class="activity-dual-location-map__canvas" :style="{ height }" />
    <ul v-if="pins.length > 0" class="activity-dual-location-map__legend">
      <li v-for="pin in pins" :key="pin.id" class="activity-dual-location-map__legend-item">
        <span
          class="activity-dual-location-map__legend-dot"
          :class="`activity-dual-location-map__legend-dot--${pin.variant ?? 'venue'}`"
          aria-hidden="true"
        />
        <span>{{ pin.label }}</span>
      </li>
    </ul>
    <p v-else class="activity-dual-location-map__empty field-hint text-muted">
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
  variant?: 'venue' | 'delivery'
}

const props = withDefaults(
  defineProps<{
    pins: ActivityLocationPin[]
    height?: string
    /** Pan/zoom erlauben (Pins bleiben immer fix). */
    interactive?: boolean
  }>(),
  {
    height: '220px',
    interactive: true,
  },
)

const { t } = useI18n()
const mapContainer = ref<HTMLDivElement>()
let map: L.Map | null = null
let markersLayer: L.LayerGroup | null = null

const SWISS_BOUNDS = L.latLngBounds([45.8, 5.9], [47.85, 10.55])
const DEFAULT_CENTER: L.LatLngExpression = [46.8182, 8.2275]
const DEFAULT_ZOOM = 7

function pinIcon(variant: ActivityLocationPin['variant']): L.DivIcon {
  const kind = variant ?? 'venue'
  return L.divIcon({
    className: 'activity-dual-location-map__marker-wrap',
    html: `<span class="activity-dual-location-map__marker activity-dual-location-map__marker--${kind}"></span>`,
    iconSize: [20, 20],
    iconAnchor: [10, 10],
  })
}

function renderMarkers() {
  if (!map) return
  if (markersLayer) {
    markersLayer.clearLayers()
  } else {
    markersLayer = L.layerGroup().addTo(map)
  }

  const pinsToRender = props.pins
  for (const pin of pinsToRender) {
    const marker = L.marker([pin.latitude, pin.longitude], {
      icon: pinIcon(pin.variant),
      draggable: false,
      interactive: false,
    })
    marker.bindTooltip(pin.label, {
      permanent: true,
      direction: 'top',
      offset: [0, -8],
      className: 'activity-dual-location-map__tooltip',
    })
    markersLayer.addLayer(marker)
  }

  if (pinsToRender.length === 1) {
    map.setView([pinsToRender[0].latitude, pinsToRender[0].longitude], 15, { animate: false })
  } else if (pinsToRender.length > 1) {
    const bounds = L.latLngBounds(pinsToRender.map((p) => [p.latitude, p.longitude] as L.LatLngExpression))
    map.fitBounds(bounds.pad(0.35), { animate: false })
  } else {
    map.fitBounds(SWISS_BOUNDS, { animate: false })
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

  renderMarkers()

  setTimeout(() => {
    map?.invalidateSize({ pan: false })
    renderMarkers()
  }, 150)
}

function destroyMap() {
  if (map) {
    map.remove()
    map = null
    markersLayer = null
  }
}

function invalidateSize() {
  if (!map) return
  map.invalidateSize({ pan: false })
  renderMarkers()
}

defineExpose({ invalidateSize })

watch(
  () => props.pins,
  () => {
    if (map) renderMarkers()
  },
  { deep: true },
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

.activity-dual-location-map__legend {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 16px;
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
  font-size: 0.8125rem;
  color: #475569;
}

.activity-dual-location-map__legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.activity-dual-location-map__legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.activity-dual-location-map__legend-dot--venue {
  background: #2563eb;
  box-shadow: 0 0 0 2px #fff, 0 0 0 3px #2563eb;
}

.activity-dual-location-map__legend-dot--delivery {
  background: #ea580c;
  box-shadow: 0 0 0 2px #fff, 0 0 0 3px #ea580c;
}

.activity-dual-location-map__empty {
  margin: 8px 0 0;
}
</style>

<style>
.activity-dual-location-map__marker-wrap {
  background: transparent;
  border: none;
}

.activity-dual-location-map__marker {
  display: block;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.35);
}

.activity-dual-location-map__marker--venue {
  background: #2563eb;
}

.activity-dual-location-map__marker--delivery {
  background: #ea580c;
}

.activity-dual-location-map__tooltip {
  background: rgba(15, 23, 42, 0.92);
  border: none;
  border-radius: 4px;
  color: #fff;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 2px 6px;
  box-shadow: none;
}

.activity-dual-location-map__tooltip::before {
  border-top-color: rgba(15, 23, 42, 0.92);
}
</style>
