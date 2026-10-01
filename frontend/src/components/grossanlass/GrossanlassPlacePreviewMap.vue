<template>
  <div class="ga-place-map">
    <div class="ga-place-map__stage">
      <div ref="mapContainer" class="ga-place-map__canvas" :style="{ height }" />
      <div
        class="ga-place-map__layers"
        @mousedown.stop
        @click.stop
      >
        <button
          type="button"
          :class="['ga-place-map__layer-btn', { active: currentLayer === 'swisstopo' }]"
          :title="t('components.mapView.layerSwisstopoTitle')"
          @click.stop="setLayer('swisstopo')"
        >
          🇨🇭
        </button>
        <button
          type="button"
          :class="['ga-place-map__layer-btn', { active: currentLayer === 'swissimage' }]"
          :title="t('components.mapView.layerSwissimageTitle')"
          @click.stop="setLayer('swissimage')"
        >
          📷
        </button>
        <button
          type="button"
          :class="['ga-place-map__layer-btn', { active: currentLayer === 'osm' }]"
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
import { gaMapOverlayOpacity } from '@/utils/grossanlassGaMap'

type MapBaseLayer = 'swisstopo' | 'swissimage' | 'osm'

export type PlacePreviewOverlay = {
  url: string
  north: number
  south: number
  east: number
  west: number
  opacity?: number | null
}

const WMTS_BASE = 'https://wmts.geo.admin.ch/1.0.0'
const SWISSTOPO_ATTRIBUTION = '&copy; <a href="https://www.swisstopo.admin.ch">swisstopo</a>'
const DEFAULT_CENTER: L.LatLngExpression = [46.8182, 8.2275]
const DEFAULT_ZOOM = 8

const props = withDefaults(
  defineProps<{
    latitude?: number | null
    longitude?: number | null
    label?: string
    overlay?: PlacePreviewOverlay | null
    height?: string
    active?: boolean
    editable?: boolean
  }>(),
  {
    latitude: null,
    longitude: null,
    label: '',
    overlay: null,
    height: '280px',
    active: true,
    editable: false,
  },
)

const emit = defineEmits<{
  pick: [lat: number, lng: number]
}>()

const { t } = useI18n()
const mapContainer = ref<HTMLDivElement>()
let map: L.Map | null = null
let pinMarker: L.Marker | null = null
let overlayLayer: L.ImageOverlay | null = null
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

function pinIcon(): L.DivIcon {
  return L.divIcon({
    className: 'ga-place-map__marker-wrap',
    html: '<span class="ga-place-map__marker"></span>',
    iconSize: [18, 18],
    iconAnchor: [9, 9],
  })
}

function overlayBounds(overlay: PlacePreviewOverlay): L.LatLngBounds {
  return L.latLngBounds(
    [overlay.south, overlay.west],
    [overlay.north, overlay.east],
  )
}

function applyOverlay() {
  if (!map) return
  const overlay = props.overlay
  if (!overlay?.url) {
    if (overlayLayer) {
      map.removeLayer(overlayLayer)
      overlayLayer = null
    }
    return
  }
  const bounds = overlayBounds(overlay)
  const opacity = gaMapOverlayOpacity(overlay.opacity, false)
  if (overlayLayer) {
    overlayLayer.setUrl(overlay.url)
    overlayLayer.setBounds(bounds)
    overlayLayer.setOpacity(opacity)
  } else {
    overlayLayer = L.imageOverlay(overlay.url, bounds, {
      opacity,
      interactive: false,
    })
    overlayLayer.addTo(map)
  }
}

function hasPin(): boolean {
  return Number.isFinite(props.latitude) && Number.isFinite(props.longitude)
}

function render(opts: { fit?: boolean } = {}) {
  if (!map) return
  applyOverlay()

  if (pinMarker) {
    map.removeLayer(pinMarker)
    pinMarker = null
  }

  if (hasPin()) {
    pinMarker = L.marker([props.latitude as number, props.longitude as number], {
      icon: pinIcon(),
      zIndexOffset: 600,
      draggable: props.editable,
      autoPan: true,
    })
    if (props.label) {
      pinMarker.bindTooltip(props.label, { direction: 'top', offset: [0, -8] })
    }
    pinMarker.on('dragend', () => {
      const point = pinMarker?.getLatLng()
      if (!point || !props.editable) return
      emit('pick', point.lat, point.lng)
    })
    pinMarker.addTo(map)
  }

  if (!opts.fit) return
  if (hasPin()) {
    map.setView([props.latitude as number, props.longitude as number], 16, { animate: false })
  } else if (props.overlay?.url) {
    map.fitBounds(overlayBounds(props.overlay).pad(0.08), { animate: false, maxZoom: 17 })
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
  instance.dragging.disable()
  const el = instance.getContainer()

  const onKeyDown = (event: KeyboardEvent) => {
    if (!isCtrlZoom(event)) return
    setScrollZoom(true)
    instance.dragging.enable()
  }
  const onKeyUp = (event: KeyboardEvent) => {
    if (isCtrlZoom(event)) return
    setScrollZoom(false)
    instance.dragging.disable()
  }
  const onWheel = (event: WheelEvent) => {
    if (isCtrlZoom(event)) {
      event.preventDefault()
      setScrollZoom(true)
      instance.dragging.enable()
      return
    }
    setScrollZoom(false)
    instance.dragging.disable()
  }
  const onBlur = () => {
    setScrollZoom(false)
    instance.dragging.disable()
  }

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

function onMapClick(event: L.LeafletMouseEvent) {
  if (!props.editable) return
  emit('pick', event.latlng.lat, event.latlng.lng)
}

function initMap() {
  if (!mapContainer.value || map) return
  map = L.map(mapContainer.value, {
    zoomControl: true,
    scrollWheelZoom: false,
  }).setView(DEFAULT_CENTER, DEFAULT_ZOOM)
  unbindCtrlScroll = bindCtrlScrollZoom(map)
  map.on('click', onMapClick)
  setLayer('swisstopo')
  render({ fit: true })
  setTimeout(refreshSize, 150)
  if (props.active) setTimeout(refreshSize, 350)
}

onMounted(() => {
  void nextTick(() => initMap())
})

onUnmounted(() => {
  unbindCtrlScroll?.()
  unbindCtrlScroll = null
  map?.off('click', onMapClick)
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
      render({ fit: true })
      setTimeout(refreshSize, 320)
    })
  },
)

watch(
  () => [props.latitude, props.longitude, props.label, props.overlay, props.editable] as const,
  () => {
    const hadPin = !!pinMarker
    render({ fit: false })
    if (!hadPin && hasPin() && map) {
      map.setView([props.latitude as number, props.longitude as number], Math.max(map.getZoom(), 15), {
        animate: false,
      })
    }
  },
  { deep: true },
)

defineExpose({ refreshSize })
</script>

<style scoped>
.ga-place-map { position: relative; }
.ga-place-map__stage { position: relative; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
.ga-place-map__canvas { width: 100%; min-height: 220px; background: #e2e8f0; }
.ga-place-map__layers {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 500;
  display: flex;
  gap: 4px;
}
.ga-place-map__layer-btn {
  width: 32px;
  height: 32px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
}
.ga-place-map__layer-btn.active { outline: 2px solid #0f766e; }
</style>

<style>
.ga-place-map__marker-wrap { background: none; border: 0; }
.ga-place-map__marker {
  display: block;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #d97706;
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.35);
  cursor: grab;
}
</style>
