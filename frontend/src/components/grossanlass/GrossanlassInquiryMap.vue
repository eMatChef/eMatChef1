<template>
  <div class="ga-inquiry-map">
    <div class="ga-inquiry-map__stage">
      <div ref="mapContainer" class="ga-inquiry-map__canvas" :style="{ height }" />
      <div
        class="ga-inquiry-map__layers"
        @mousedown.stop
        @click.stop
      >
        <button
          type="button"
          :class="['ga-inquiry-map__layer-btn', { active: currentLayer === 'swisstopo' }]"
          :title="t('components.mapView.layerSwisstopoTitle')"
          @click.stop="setLayer('swisstopo')"
        >
          🇨🇭
        </button>
        <button
          type="button"
          :class="['ga-inquiry-map__layer-btn', { active: currentLayer === 'swissimage' }]"
          :title="t('components.mapView.layerSwissimageTitle')"
          @click.stop="setLayer('swissimage')"
        >
          📷
        </button>
        <button
          type="button"
          :class="['ga-inquiry-map__layer-btn', { active: currentLayer === 'osm' }]"
          :title="t('components.mapView.layerOsmTitle')"
          @click.stop="setLayer('osm')"
        >
          🌍
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

export type InquiryMapPin = {
  id: string
  label: string
  latitude: number
  longitude: number
  color: string
  meta?: string
}

type MapBaseLayer = 'swisstopo' | 'swissimage' | 'osm'

const WMTS_BASE = 'https://wmts.geo.admin.ch/1.0.0'
const SWISSTOPO_ATTRIBUTION = '&copy; <a href="https://www.swisstopo.admin.ch">swisstopo</a>'
const DEFAULT_CENTER: L.LatLngExpression = [46.8182, 8.2275]
const DEFAULT_ZOOM = 8

const props = withDefaults(
  defineProps<{
    pins: InquiryMapPin[]
    venue?: { latitude: number; longitude: number; label: string } | null
    radiusKm?: number | null
    height?: string
    selectedId?: string | null
    active?: boolean
  }>(),
  {
    venue: null,
    radiusKm: null,
    height: '420px',
    selectedId: null,
    active: true,
  },
)

const emit = defineEmits<{
  select: [id: string]
}>()

const { t } = useI18n()
const mapContainer = ref<HTMLDivElement>()
let map: L.Map | null = null
let markersLayer: L.LayerGroup | null = null
let radiusCircle: L.Circle | null = null
let venueMarker: L.Marker | null = null
let activeTileLayer: L.TileLayer | null = null
let unbindCtrlScroll: (() => void) | null = null
const currentLayer = ref<MapBaseLayer>('swisstopo')

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
}

function pinIcon(color: string, selected: boolean): L.DivIcon {
  const size = selected ? 18 : 14
  return L.divIcon({
    className: 'ga-inquiry-map__marker-wrap',
    html: `<span class="ga-inquiry-map__marker${selected ? ' is-selected' : ''}" style="background:${color};width:${size}px;height:${size}px"></span>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  })
}

function venueIcon(): L.DivIcon {
  return L.divIcon({
    className: 'ga-inquiry-map__marker-wrap',
    html: '<span class="ga-inquiry-map__venue"></span>',
    iconSize: [22, 22],
    iconAnchor: [11, 11],
  })
}

function render() {
  if (!map) return
  if (markersLayer) markersLayer.clearLayers()
  else markersLayer = L.layerGroup().addTo(map)

  if (venueMarker) {
    map.removeLayer(venueMarker)
    venueMarker = null
  }
  if (radiusCircle) {
    map.removeLayer(radiusCircle)
    radiusCircle = null
  }

  const venue = props.venue
  if (venue) {
    venueMarker = L.marker([venue.latitude, venue.longitude], { icon: venueIcon(), zIndexOffset: 800 })
    venueMarker.bindTooltip(venue.label, { direction: 'top', offset: [0, -10] })
    venueMarker.addTo(map)
    if (props.radiusKm && props.radiusKm > 0) {
      radiusCircle = L.circle([venue.latitude, venue.longitude], {
        radius: props.radiusKm * 1000,
        color: '#2563eb',
        weight: 1,
        fillColor: '#3b82f6',
        fillOpacity: 0.08,
      }).addTo(map)
    }
  }

  for (const pin of props.pins) {
    const selected = pin.id === props.selectedId
    const marker = L.marker([pin.latitude, pin.longitude], {
      icon: pinIcon(pin.color, selected),
      zIndexOffset: selected ? 600 : 0,
    })
    const tip = pin.meta ? `${pin.label}<br><span>${pin.meta}</span>` : pin.label
    marker.bindTooltip(tip, { direction: 'top', offset: [0, -8], sticky: true })
    marker.on('click', () => emit('select', pin.id))
    markersLayer.addLayer(marker)
  }

  const pts: L.LatLngExpression[] = props.pins.map((p) => [p.latitude, p.longitude])
  if (venue) pts.push([venue.latitude, venue.longitude])
  if (pts.length === 1) {
    map.setView(pts[0], 11, { animate: false })
  } else if (pts.length > 1) {
    map.fitBounds(L.latLngBounds(pts).pad(0.2), { animate: false, maxZoom: 12, padding: [24, 24] })
  } else {
    map.setView(DEFAULT_CENTER, DEFAULT_ZOOM, { animate: false })
  }
}

function setScrollZoom(on: boolean) {
  if (!map) return
  if (on) map.scrollWheelZoom.enable()
  else map.scrollWheelZoom.disable()
}

function isCtrlZoom(event: KeyboardEvent | WheelEvent): boolean {
  return event.ctrlKey || event.metaKey
}

function bindCtrlScrollZoom(instance: L.Map): () => void {
  instance.scrollWheelZoom.disable()
  const el = instance.getContainer()

  const onKeyDown = (event: KeyboardEvent) => {
    if (isCtrlZoom(event)) setScrollZoom(true)
  }
  const onKeyUp = (event: KeyboardEvent) => {
    if (!isCtrlZoom(event)) setScrollZoom(false)
  }
  const onWheel = (event: WheelEvent) => {
    if (isCtrlZoom(event)) {
      event.preventDefault()
      setScrollZoom(true)
      return
    }
    setScrollZoom(false)
  }
  const onBlur = () => setScrollZoom(false)

  window.addEventListener('keydown', onKeyDown)
  window.addEventListener('keyup', onKeyUp)
  window.addEventListener('blur', onBlur)
  el.addEventListener('wheel', onWheel, { capture: true, passive: false })

  return () => {
    window.removeEventListener('keydown', onKeyDown)
    window.removeEventListener('keyup', onKeyUp)
    window.removeEventListener('blur', onBlur)
    el.removeEventListener('wheel', onWheel, true)
  }
}

function refreshSize() {
  map?.invalidateSize({ pan: false })
}

function initMap() {
  if (!mapContainer.value || map) return
  map = L.map(mapContainer.value, {
    zoomControl: true,
    scrollWheelZoom: false,
  }).setView(DEFAULT_CENTER, DEFAULT_ZOOM)
  unbindCtrlScroll = bindCtrlScrollZoom(map)
  setLayer('swisstopo')
  render()
  setTimeout(refreshSize, 150)
  if (props.active) setTimeout(refreshSize, 350)
}

onMounted(() => {
  void nextTick(() => initMap())
})

onUnmounted(() => {
  unbindCtrlScroll?.()
  unbindCtrlScroll = null
  map?.remove()
  map = null
})

watch(
  () => props.active,
  (on) => {
    if (!on) {
      setScrollZoom(false)
      return
    }
    void nextTick(() => {
      refreshSize()
      setTimeout(refreshSize, 320)
    })
  },
)

watch(
  () => [props.pins, props.venue, props.radiusKm, props.selectedId] as const,
  () => {
    render()
  },
  { deep: true },
)
</script>

<style scoped>
.ga-inquiry-map { position: relative; }
.ga-inquiry-map__stage { position: relative; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
.ga-inquiry-map__canvas { width: 100%; min-height: 280px; background: #e2e8f0; }
.ga-inquiry-map__layers {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 500;
  display: flex;
  gap: 4px;
}
.ga-inquiry-map__layer-btn {
  width: 32px;
  height: 32px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
}
.ga-inquiry-map__layer-btn.active { outline: 2px solid #2563eb; }
</style>

<style>
.ga-inquiry-map__marker-wrap { background: none; border: 0; }
.ga-inquiry-map__marker {
  display: block;
  border-radius: 50%;
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.35);
}
.ga-inquiry-map__marker.is-selected { box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.45); }
.ga-inquiry-map__venue {
  display: block;
  width: 18px;
  height: 18px;
  border-radius: 4px;
  background: #1d4ed8;
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.4);
  transform: rotate(45deg);
}
</style>
