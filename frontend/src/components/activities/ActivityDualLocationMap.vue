<template>
  <div class="activity-dual-location-map">
    <div class="activity-dual-location-map__stage">
      <div ref="mapContainer" class="activity-dual-location-map__canvas" :style="{ height }" />
      <div
        v-if="showLocationSearch"
        class="activity-dual-location-map__search"
        @mousedown.stop
        @click.stop
        @dblclick.stop
      >
        <input
          v-model="locationSearchQuery"
          type="search"
          class="activity-dual-location-map__search-input"
          :placeholder="t('components.mapView.locationSearchPlaceholder')"
          autocomplete="off"
          :aria-label="t('components.mapView.locationSearchPlaceholder')"
          @input="onLocationSearchInput"
          @focus="onLocationSearchFocus"
          @blur="hideLocationSearchDelayed"
          @keydown.escape.prevent="clearLocationSearch"
        />
        <div
          v-if="showLocationSearchResults && (locationSearchResults.length || locationSearchLoading || locationSearchQuery.trim().length >= 2)"
          class="activity-dual-location-map__search-dropdown"
          @mousedown.prevent
        >
          <p v-if="locationSearchLoading" class="activity-dual-location-map__search-hint">
            {{ t('components.mapView.searchingAddress') }}
          </p>
          <p
            v-else-if="!locationSearchResults.length && locationSearchQuery.trim().length >= 2"
            class="activity-dual-location-map__search-hint"
          >
            {{ t('components.mapView.locationSearchEmpty') }}
          </p>
          <button
            v-for="(result, index) in locationSearchResults"
            :key="`${result.lat}:${result.lng}:${index}`"
            type="button"
            class="activity-dual-location-map__search-item"
            @click="selectLocationSearchResult(result)"
          >
            {{ result.label }}
          </button>
        </div>
      </div>
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
        <button
          v-if="overlay?.url"
          type="button"
          :class="['activity-dual-location-map__layer-btn', { active: planVisible }]"
          :title="t('components.mapView.layerPlanTitle')"
          @click.stop="planVisible = !planVisible"
        >
          🗺️
        </button>
      </div>
    </div>
    <p
      v-if="pins.length === 0 && !editablePinId && !hasVisiblePolygons && !polygonDrawMode"
      class="activity-dual-location-map__empty field-hint text-muted"
    >
      {{ t('activities.venueLocations.noMapCoords') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { searchGeoLocations, type GeoSearchResult } from '@/utils/geoSearch'
import {
  boundsFromCornerDragWithAspect,
  GA_MAP_DETAIL_MIN_ZOOM,
  gaMapOverlayOpacity,
  overlayImageAspectRatio,
} from '@/utils/grossanlassGaMap'
import type { GaPolygonPoint } from '@/api/grossanlassLogistics'

export interface ActivityLocationPin {
  id: string
  label: string
  latitude: number
  longitude: number
  variant?: 'venue' | 'delivery' | 'poi'
  /** Hex-Farbe für freie POIs; überschreibt Variantenfarbe wenn gesetzt. */
  color?: string | null
  /** Nur sichtbar, wenn die Karte nah genug ist (unmarkierte GA-Orte). */
  detailOnly?: boolean
}

export type ActivityMapPolygon = {
  id: string
  label: string
  color: string
  points: GaPolygonPoint[]
  starred?: boolean
  detailOnly?: boolean
}

type MapBaseLayer = 'swisstopo' | 'swissimage' | 'osm'

export type ActivityMapOverlay = {
  url: string
  north: number
  south: number
  east: number
  west: number
  imageWidth?: number
  imageHeight?: number
  /** 0–1, wie deckend der Plan über der Basiskarte liegt. */
  opacity?: number
}

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
    /** Ortschaft/Ort auf der Karte suchen (geo.admin.ch / Nominatim). */
    showLocationSearch?: boolean
    /** Optionaler Geländeplan über dem Ausschnitt. */
    overlay?: ActivityMapOverlay | null
    /** Ecken verschieben / Plan auf der Karte platzieren. */
    overlayEditable?: boolean
    /** Mausrad-Zoom. Aus = Seite scrollt, Karte bleibt auf allen Pins. */
    scrollWheelZoom?: boolean
    /** Mausrad-Zoom nur mit Strg/Cmd (Seite scrollt sonst weiter). */
    scrollWheelZoomRequireCtrl?: boolean
    polygons?: ActivityMapPolygon[]
    editablePolygonId?: string | null
    polygonDrawMode?: boolean
  }>(),
  {
    height: '220px',
    interactive: true,
    editablePinId: null,
    preferSwissMap: true,
    showLayerControl: true,
    showLocationSearch: false,
    overlay: null,
    overlayEditable: false,
    scrollWheelZoom: true,
    scrollWheelZoomRequireCtrl: false,
    polygons: () => [],
    editablePolygonId: null,
    polygonDrawMode: false,
  },
)

const emit = defineEmits<{
  'pin-moved': [payload: { id: string; latitude: number; longitude: number }]
  'map-click': [payload: { latitude: number; longitude: number }]
  'polygon-change': [payload: { id: string; points: GaPolygonPoint[] }]
  'overlay-bounds-change': [bounds: ActivityMapOverlay]
}>()

const { t } = useI18n()
const planVisible = defineModel<boolean>('planVisible', { default: true })
const mapContainer = ref<HTMLDivElement>()
let map: L.Map | null = null
let markersLayer: L.FeatureGroup | null = null
let activeTileLayer: L.TileLayer | null = null
let overlayLayer: L.ImageOverlay | null = null
let overlayOutline: L.Rectangle | null = null
let overlayHandleLayer: L.FeatureGroup | null = null
const overlayHandleMarkers = new Map<string, L.Marker>()
let overlayEditBounds: ActivityMapOverlay | null = null
let draggingOverlayHandleId: string | null = null
let overlayDragActive = false
const markerById = new Map<string, L.Marker>()
let polygonsLayer: L.FeatureGroup | null = null
const polygonById = new Map<string, L.Polygon>()
const vertexMarkers: L.Marker[] = []
let currentZoom = GA_MAP_DETAIL_MIN_ZOOM
const currentLayer = ref<MapBaseLayer>('swisstopo')
const locationSearchQuery = ref('')
const locationSearchResults = ref<GeoSearchResult[]>([])
const showLocationSearchResults = ref(false)
const locationSearchLoading = ref(false)
let locationSearchTimeout: ReturnType<typeof setTimeout> | null = null
let hideLocationSearchTimeout: ReturnType<typeof setTimeout> | null = null
let unbindCtrlScroll: (() => void) | null = null

const SWISS_BOUNDS = L.latLngBounds([45.8, 5.9], [47.85, 10.55])
const DEFAULT_CENTER: L.LatLngExpression = [46.8182, 8.2275]
const DEFAULT_ZOOM = 7

const VARIANT_COLORS: Record<NonNullable<ActivityLocationPin['variant']>, string> = {
  venue: '#2563eb',
  delivery: '#ea580c',
  poi: '#16a34a',
}

const hasVisiblePolygons = computed(() => props.polygons.some((row) => row.points.length >= 3))

function showsDetailLayer(): boolean {
  return currentZoom >= GA_MAP_DETAIL_MIN_ZOOM
}

function isDetailFeatureVisible(detailOnly: boolean | undefined, id: string): boolean {
  if (!detailOnly) return true
  if (id === props.editablePinId || id === props.editablePolygonId) return true
  return showsDetailLayer()
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
  applyOverlay()
  bringOverlayEditUiToFront()
  if (!props.overlayEditable) {
    bringLeafletLayerToFront(polygonsLayer)
    bringLeafletLayerToFront(markersLayer)
  }
}

function overlayLatLngBounds(overlay: Pick<ActivityMapOverlay, 'north' | 'south' | 'east' | 'west'>): L.LatLngBounds {
  return L.latLngBounds(
    [overlay.south, overlay.west],
    [overlay.north, overlay.east],
  )
}

function normalizeOverlayBounds(bounds: ActivityMapOverlay): ActivityMapOverlay | null {
  const north = Math.max(bounds.north, bounds.south + 0.00005)
  const south = Math.min(bounds.south, bounds.north - 0.00005)
  const east = Math.max(bounds.east, bounds.west + 0.00005)
  const west = Math.min(bounds.west, bounds.east - 0.00005)
  if (!(north > south) || !(east > west)) return null
  return { ...bounds, north, south, east, west }
}

function overlayCenter(bounds: Pick<ActivityMapOverlay, 'north' | 'south' | 'east' | 'west'>) {
  return {
    lat: (bounds.north + bounds.south) / 2,
    lng: (bounds.east + bounds.west) / 2,
  }
}

function syncOverlayEditBoundsFromProps() {
  if (!props.overlay?.url) {
    overlayEditBounds = null
    return
  }
  overlayEditBounds = normalizeOverlayBounds({
    url: props.overlay.url,
    north: props.overlay.north,
    south: props.overlay.south,
    east: props.overlay.east,
    west: props.overlay.west,
  })
}

function emitOverlayBoundsChange() {
  if (!overlayEditBounds) return
  emit('overlay-bounds-change', { ...overlayEditBounds })
}

function clearOverlayEditUi() {
  overlayHandleMarkers.forEach((marker) => {
    marker.off('dragstart', onOverlayHandleDragStart)
    marker.off('drag', onOverlayHandleDrag)
    marker.off('dragend', onOverlayHandleDragEnd)
  })
  overlayHandleMarkers.clear()
  if (overlayHandleLayer) {
    overlayHandleLayer.clearLayers()
  }
  overlayOutline = null
  draggingOverlayHandleId = null
  overlayDragActive = false
}

function bringLeafletLayerToFront(layer: L.Layer | null) {
  if (!layer || typeof (layer as { bringToFront?: () => void }).bringToFront !== 'function') {
    return
  }
  ;(layer as L.FeatureGroup).bringToFront()
}

function bringOverlayEditUiToFront() {
  bringLeafletLayerToFront(overlayHandleLayer)
}

function ensureOverlayEditPane() {
  if (!map) return
  if (!map.getPane('overlayEditPane')) {
    map.createPane('overlayEditPane')
    const pane = map.getPane('overlayEditPane')
    if (pane) {
      pane.style.zIndex = '800'
      pane.style.pointerEvents = 'auto'
    }
  }
}

function overlayHandleIcon(kind: 'corner' | 'move'): L.DivIcon {
  const size = kind === 'move' ? 34 : 28
  const color = kind === 'move' ? '#059669' : '#2563eb'
  const radius = kind === 'move' ? '8px' : '50%'
  return L.divIcon({
    className: 'leaflet-div-icon activity-dual-location-map__overlay-handle-wrap',
    html: `<span class="activity-dual-location-map__overlay-handle activity-dual-location-map__overlay-handle--${kind}" style="width:${size}px;height:${size}px;background:${color};border-radius:${radius}"></span>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  })
}

function findOverlayHandleId(marker: L.Marker): string | null {
  for (const [id, value] of overlayHandleMarkers.entries()) {
    if (value === marker) return id
  }
  return null
}

function boundsFromHandleDrag(
  id: string,
  pos: L.LatLng,
  current: ActivityMapOverlay,
): ActivityMapOverlay {
  const aspect = overlayImageAspectRatio(current.imageWidth ?? 0, current.imageHeight ?? 0)
  if (aspect && (id === 'nw' || id === 'ne' || id === 'se' || id === 'sw')) {
    const next = boundsFromCornerDragWithAspect(
      id,
      pos.lat,
      pos.lng,
      current,
      current.imageWidth ?? 0,
      current.imageHeight ?? 0,
    )
    if (next) return { ...current, ...next }
  }

  const next = { ...current }
  if (id === 'move') {
    const halfLat = (current.north - current.south) / 2
    const halfLng = (current.east - current.west) / 2
    next.north = pos.lat + halfLat
    next.south = pos.lat - halfLat
    next.east = pos.lng + halfLng
    next.west = pos.lng - halfLng
    return next
  }
  if (id === 'nw') {
    next.north = pos.lat
    next.west = pos.lng
  } else if (id === 'ne') {
    next.north = pos.lat
    next.east = pos.lng
  } else if (id === 'se') {
    next.south = pos.lat
    next.east = pos.lng
  } else if (id === 'sw') {
    next.south = pos.lat
    next.west = pos.lng
  }
  return next
}

function refreshOverlayVisuals() {
  if (!map || !overlayEditBounds) return
  const bounds = overlayLatLngBounds(overlayEditBounds)
  if (overlayLayer) {
    overlayLayer.setBounds(bounds)
  }
  if (overlayOutline) {
    overlayOutline.setBounds(bounds)
  }
}

function upsertOverlayHandle(id: string, lat: number, lng: number, kind: 'corner' | 'move') {
  if (!map) return
  ensureOverlayEditPane()
  if (!overlayHandleLayer) {
    overlayHandleLayer = L.featureGroup().addTo(map)
  }
  let marker = overlayHandleMarkers.get(id)
  if (!marker) {
    marker = L.marker([lat, lng], {
      icon: overlayHandleIcon(kind),
      draggable: true,
      interactive: true,
      autoPan: false,
      zIndexOffset: 5000,
      pane: 'overlayEditPane',
    })
    marker.on('dragstart', onOverlayHandleDragStart)
    marker.on('drag', onOverlayHandleDrag)
    marker.on('dragend', onOverlayHandleDragEnd)
    marker.on('mousedown', L.DomEvent.stopPropagation)
    marker.on('touchstart', L.DomEvent.stopPropagation)
    overlayHandleLayer.addLayer(marker)
    overlayHandleMarkers.set(id, marker)
  } else if (draggingOverlayHandleId !== id) {
    marker.setLatLng([lat, lng])
  }
}

function layoutOverlayHandles(skipId: string | null = null) {
  if (!map || !props.overlayEditable || !overlayEditBounds) {
    clearOverlayEditUi()
    return
  }
  const bounds = overlayEditBounds
  upsertOverlayHandle('nw', bounds.north, bounds.west, 'corner')
  upsertOverlayHandle('ne', bounds.north, bounds.east, 'corner')
  upsertOverlayHandle('se', bounds.south, bounds.east, 'corner')
  upsertOverlayHandle('sw', bounds.south, bounds.west, 'corner')
  const center = overlayCenter(bounds)
  upsertOverlayHandle('move', center.lat, center.lng, 'move')

  if (!overlayOutline) {
    overlayOutline = L.rectangle(overlayLatLngBounds(bounds), {
      color: '#2563eb',
      weight: 3,
      dashArray: '6 4',
      fillOpacity: 0,
      interactive: false,
      pane: 'overlayEditPane',
    })
    overlayHandleLayer?.addLayer(overlayOutline)
  } else if (skipId == null) {
    overlayOutline.setBounds(overlayLatLngBounds(bounds))
  }
  bringOverlayEditUiToFront()
}

function updateOverlayEditUi() {
  if (!map || !props.overlayEditable) {
    clearOverlayEditUi()
    return
  }
  if (!overlayEditBounds && props.overlay) {
    syncOverlayEditBoundsFromProps()
  }
  if (!overlayEditBounds) {
    clearOverlayEditUi()
    return
  }
  ensureOverlayEditPane()
  if (!overlayHandleLayer) {
    overlayHandleLayer = L.featureGroup().addTo(map)
  }
  layoutOverlayHandles()
}

function onOverlayHandleDragStart(event: L.LeafletEvent) {
  overlayDragActive = true
  draggingOverlayHandleId = findOverlayHandleId(event.target as L.Marker)
  map?.dragging.disable()
}

function onOverlayHandleDrag(event: L.LeafletEvent) {
  if (!overlayEditBounds || !draggingOverlayHandleId) return
  const pos = (event.target as L.Marker).getLatLng()
  const normalized = normalizeOverlayBounds(
    boundsFromHandleDrag(draggingOverlayHandleId, pos, overlayEditBounds),
  )
  if (!normalized) return
  overlayEditBounds = { ...normalized, url: overlayEditBounds.url }
  refreshOverlayVisuals()
  layoutOverlayHandles(draggingOverlayHandleId)
}

function onOverlayHandleDragEnd() {
  overlayDragActive = false
  draggingOverlayHandleId = null
  if (map && props.interactive) {
    map.dragging.enable()
  }
  layoutOverlayHandles()
  emitOverlayBoundsChange()
}

function resolveOverlayOpacity(): number {
  return gaMapOverlayOpacity(props.overlay?.opacity, props.overlayEditable)
}

function applyOverlay() {
  if (!map) return
  const overlay = props.overlayEditable && overlayEditBounds ? overlayEditBounds : props.overlay
  if (!overlay?.url || !planVisible.value) {
    if (overlayLayer) {
      map.removeLayer(overlayLayer)
      overlayLayer = null
    }
    if (!overlayDragActive) {
      updateOverlayEditUi()
    }
    return
  }
  const bounds = overlayLatLngBounds(overlay)
  const opacity = resolveOverlayOpacity()
  if (overlayLayer) {
    overlayLayer.setBounds(bounds)
    overlayLayer.setOpacity(opacity)
  } else {
    overlayLayer = L.imageOverlay(overlay.url, bounds, {
      opacity,
      interactive: false,
    })
    overlayLayer.addTo(map)
  }
  if (!overlayDragActive) {
    updateOverlayEditUi()
  }
  if (!props.overlayEditable) {
    bringLeafletLayerToFront(polygonsLayer)
    bringLeafletLayerToFront(markersLayer)
  }
  bringOverlayEditUiToFront()
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

function visiblePins(): ActivityLocationPin[] {
  return props.pins.filter((pin) => isDetailFeatureVisible(pin.detailOnly, pin.id))
}

function visiblePolygons(): ActivityMapPolygon[] {
  return props.polygons.filter((row) => {
    if (row.id === props.editablePolygonId) return true
    if (row.points.length < 3) return false
    return isDetailFeatureVisible(row.detailOnly, row.id)
  })
}

function fitToCurrentPins(pinsToRender: ActivityLocationPin[]) {
  if (!map) return
  const latLngs: L.LatLngExpression[] = pinsToRender.map((p) => [p.latitude, p.longitude])
  for (const row of visiblePolygons()) {
    for (const point of row.points) {
      latLngs.push([point.lat, point.lng])
    }
  }
  if (latLngs.length === 1) {
    map.setView(latLngs[0], 15, { animate: false })
  } else if (latLngs.length > 1) {
    const bounds = L.latLngBounds(latLngs)
    map.fitBounds(bounds.pad(0.35), { animate: false, maxZoom: 16, padding: [28, 28] })
  } else if (props.overlay?.url && planVisible.value) {
    map.fitBounds(overlayLatLngBounds(props.overlay).pad(0.08), { animate: false, maxZoom: 17 })
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
    markersLayer = L.featureGroup().addTo(map)
  }
  markerById.clear()

  const pinsToRender = visiblePins()
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

function vertexIcon(color: string): L.DivIcon {
  return L.divIcon({
    className: 'activity-dual-location-map__vertex-wrap',
    html: `<span class="activity-dual-location-map__vertex" style="background:${color}"></span>`,
    iconSize: [14, 14],
    iconAnchor: [7, 7],
  })
}

function clearVertexMarkers() {
  for (const marker of vertexMarkers) {
    marker.remove()
  }
  vertexMarkers.length = 0
}

function emitPolygonPoints(id: string, latlngs: L.LatLng[]) {
  emit('polygon-change', {
    id,
    points: latlngs.map((point) => ({ lat: point.lat, lng: point.lng })),
  })
}

function bindVertexMarkers(row: ActivityMapPolygon, latlngs: L.LatLng[]) {
  if (!map || row.id !== props.editablePolygonId || !props.interactive) return
  latlngs.forEach((point, index) => {
    const marker = L.marker(point, {
      icon: vertexIcon(row.color),
      draggable: true,
      zIndexOffset: 400,
    })
    marker.on('drag', () => {
      const polygon = polygonById.get(row.id)
      if (!polygon) return
      const next = [...latlngs]
      next[index] = marker.getLatLng()
      polygon.setLatLngs(next)
    })
    marker.on('dragend', () => {
      const polygon = polygonById.get(row.id)
      const next = [...latlngs]
      next[index] = marker.getLatLng()
      if (polygon) polygon.setLatLngs(next)
      emitPolygonPoints(row.id, next)
    })
    marker.on('click', L.DomEvent.stop)
    marker.on('dblclick', (event) => {
      L.DomEvent.stop(event)
      const next = latlngs.filter((_, pointIndex) => pointIndex !== index)
      const polygon = polygonById.get(row.id)
      if (polygon) polygon.setLatLngs(next)
      emitPolygonPoints(row.id, next)
    })
    marker.addTo(map as L.Map)
    vertexMarkers.push(marker)
  })
}

function renderPolygons() {
  if (!map) return
  if (polygonsLayer) {
    polygonsLayer.clearLayers()
  } else {
    polygonsLayer = L.featureGroup().addTo(map)
  }
  polygonById.clear()
  clearVertexMarkers()

  for (const row of visiblePolygons()) {
    const latlngs = row.points.map((point) => L.latLng(point.lat, point.lng))
    if (latlngs.length === 0) continue
    const polygon = L.polygon(latlngs, {
      color: row.color,
      weight: row.starred || row.id === props.editablePolygonId ? 2.5 : 1.5,
      fillColor: row.color,
      fillOpacity: row.starred ? 0.28 : 0.16,
      interactive: props.interactive,
    })
    polygon.bindTooltip(row.label, {
      sticky: true,
      className: 'activity-dual-location-map__tooltip',
    })
    polygonsLayer.addLayer(polygon)
    polygonById.set(row.id, polygon)
    bindVertexMarkers(row, latlngs)
  }
}

function onMapClick(e: L.LeafletMouseEvent) {
  if (!props.interactive) return
  const { lat, lng } = e.latlng
  if (props.polygonDrawMode && props.editablePolygonId) {
    const current = props.polygons.find((row) => row.id === props.editablePolygonId)
    const points = [...(current?.points ?? []), { lat, lng }].slice(0, 32)
    emit('polygon-change', { id: props.editablePolygonId, points })
    return
  }
  if (!props.editablePinId) return
  const editableId = props.editablePinId
  const existing = markerById.get(editableId)
  if (existing) {
    existing.setLatLng([lat, lng])
    emit('pin-moved', { id: editableId, latitude: lat, longitude: lng })
    return
  }
  emit('map-click', { latitude: lat, longitude: lng })
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

function teardownCtrlScrollZoom() {
  unbindCtrlScroll?.()
  unbindCtrlScroll = null
}

function setupCtrlScrollZoom() {
  teardownCtrlScrollZoom()
  if (!map || !(props.interactive || props.overlayEditable) || !props.scrollWheelZoom || !props.scrollWheelZoomRequireCtrl) {
    return
  }
  unbindCtrlScroll = bindCtrlScrollZoom(map)
}

function applyMapInteractivity() {
  if (!map) return
  const on = props.interactive || props.overlayEditable
  if (on) {
    if (props.scrollWheelZoomRequireCtrl) {
      map.scrollWheelZoom.disable()
      setupCtrlScrollZoom()
    } else if (props.scrollWheelZoom) {
      teardownCtrlScrollZoom()
      map.scrollWheelZoom.enable()
    } else {
      teardownCtrlScrollZoom()
      map.scrollWheelZoom.disable()
    }
  } else {
    teardownCtrlScrollZoom()
    map.scrollWheelZoom.disable()
  }
  if (on) {
    map.touchZoom.enable()
    map.doubleClickZoom.enable()
    map.boxZoom.enable()
    map.keyboard.enable()
    if (!overlayDragActive) {
      map.dragging.enable()
    }
    if (map.zoomControl) map.zoomControl.addTo(map)
  } else {
    map.touchZoom.disable()
    map.doubleClickZoom.disable()
    map.boxZoom.disable()
    map.keyboard.disable()
    map.dragging.disable()
  }
  map.off('click', onMapClick)
  map.off('zoomend', onZoomEnd)
  map.on('zoomend', onZoomEnd)
  if (props.interactive && (props.editablePinId || props.polygonDrawMode)) {
    map.on('click', onMapClick)
  }
  if (props.polygonDrawMode) {
    map.doubleClickZoom.disable()
  } else if (props.interactive || props.overlayEditable) {
    map.doubleClickZoom.enable()
  }
}

function onZoomEnd() {
  if (!map) return
  currentZoom = map.getZoom()
  renderMarkers({ fit: false })
  renderPolygons()
}

function initMap() {
  if (!mapContainer.value || map) return

  map = L.map(mapContainer.value, {
    zoomControl: props.interactive || props.overlayEditable,
    scrollWheelZoom:
      (props.interactive || props.overlayEditable)
      && props.scrollWheelZoom
      && !props.scrollWheelZoomRequireCtrl,
    touchZoom: props.interactive || props.overlayEditable,
    doubleClickZoom: props.interactive || props.overlayEditable,
    boxZoom: props.interactive || props.overlayEditable,
    keyboard: props.interactive || props.overlayEditable,
    dragging: props.interactive || props.overlayEditable,
  }).setView(DEFAULT_CENTER, DEFAULT_ZOOM)

  setLayer(props.preferSwissMap ? 'swisstopo' : 'osm')
  applyMapInteractivity()
  currentZoom = map.getZoom()
  renderMarkers({ fit: true })
  renderPolygons()

  setTimeout(() => {
    map?.invalidateSize({ pan: false })
    currentZoom = map?.getZoom() ?? currentZoom
    renderMarkers({ fit: true })
    renderPolygons()
  }, 150)
}

function destroyMap() {
  teardownCtrlScrollZoom()
  if (map) {
    map.off('click', onMapClick)
    map.off('zoomend', onZoomEnd)
    map.remove()
    map = null
    markersLayer = null
    polygonsLayer = null
    activeTileLayer = null
    overlayLayer = null
    overlayHandleLayer = null
    overlayOutline = null
    overlayHandleMarkers.clear()
    overlayEditBounds = null
    markerById.clear()
    polygonById.clear()
    clearVertexMarkers()
  }
}

function isEditingGeometry(): boolean {
  return Boolean(props.polygonDrawMode || props.editablePolygonId || props.editablePinId)
}

function invalidateSize() {
  if (!map) return
  map.invalidateSize({ pan: false })
  // Zeichnen/Verschieben: Viewport lassen. Ohne Pins: Schweiz-Übersicht behalten.
  const shouldFit = !isEditingGeometry() && props.pins.length === 0
  renderMarkers({ fit: shouldFit })
  renderPolygons()
}

function fitToPins() {
  if (!map) return
  fitToCurrentPins(props.pins)
}

function panTo(lat: number, lng: number, zoom = 14) {
  if (!map) return
  map.setView([lat, lng], zoom, { animate: true })
}

function onLocationSearchFocus() {
  showLocationSearchResults.value = true
  const query = locationSearchQuery.value.trim()
  if (query.length >= 2 && !locationSearchResults.value.length && !locationSearchLoading.value) {
    void performLocationSearch(query)
  }
}

function onLocationSearchInput() {
  if (locationSearchTimeout) clearTimeout(locationSearchTimeout)
  const query = locationSearchQuery.value.trim()
  if (query.length < 2) {
    locationSearchResults.value = []
    showLocationSearchResults.value = false
    locationSearchLoading.value = false
    return
  }
  showLocationSearchResults.value = true
  locationSearchLoading.value = true
  locationSearchTimeout = setTimeout(() => {
    void performLocationSearch(query)
  }, 400)
}

async function performLocationSearch(query: string) {
  locationSearchLoading.value = true
  try {
    locationSearchResults.value = await searchGeoLocations(query, 6)
    showLocationSearchResults.value = true
  } catch {
    locationSearchResults.value = []
  } finally {
    locationSearchLoading.value = false
  }
}

function selectLocationSearchResult(result: GeoSearchResult) {
  locationSearchQuery.value = result.label
  locationSearchResults.value = []
  showLocationSearchResults.value = false
  panTo(result.lat, result.lng, 14)
}

function clearLocationSearch() {
  locationSearchQuery.value = ''
  locationSearchResults.value = []
  showLocationSearchResults.value = false
}

function hideLocationSearchDelayed() {
  if (hideLocationSearchTimeout) clearTimeout(hideLocationSearchTimeout)
  hideLocationSearchTimeout = setTimeout(() => {
    showLocationSearchResults.value = false
  }, 180)
}

function getBounds(): { north: number; south: number; east: number; west: number } | null {
  if (!map) return null
  const bounds = map.getBounds()
  return {
    north: bounds.getNorth(),
    south: bounds.getSouth(),
    east: bounds.getEast(),
    west: bounds.getWest(),
  }
}

function getOverlayBounds(): ActivityMapOverlay | null {
  if (props.overlayEditable && overlayEditBounds) {
    return { ...overlayEditBounds }
  }
  if (props.overlay?.url) {
    return { ...props.overlay }
  }
  return null
}

function fitOverlayBounds(bounds: Pick<ActivityMapOverlay, 'north' | 'south' | 'east' | 'west'>) {
  if (!map) return
  map.fitBounds(overlayLatLngBounds(bounds).pad(0.12), { animate: false, maxZoom: 18, padding: [24, 24] })
}

function refreshOverlayEditUi() {
  if (!props.overlayEditable) return
  syncOverlayEditBoundsFromProps()
  applyOverlay()
}

defineExpose({
  invalidateSize,
  fitToPins,
  getBounds,
  getOverlayBounds,
  fitOverlayBounds,
  refreshOverlayEditUi,
  panTo,
  setPlanVisible(visible: boolean) {
    planVisible.value = visible
  },
  togglePlanVisible() {
    planVisible.value = !planVisible.value
  },
})

watch(
  () => props.pins.map((p) => `${p.id}:${p.latitude}:${p.longitude}:${p.color ?? ''}:${p.detailOnly ? 1 : 0}`).join('|'),
  () => {
    if (map) {
      nextTick(() => {
        // Beim Zeichnen/Verschieben nicht neu zoomen — nur Marker aktualisieren
        renderMarkers({ fit: !isEditingGeometry() })
      })
    }
  },
)

watch(
  () =>
    props.polygons
      .map((row) => `${row.id}:${row.color}:${row.detailOnly ? 1 : 0}:${row.points.map((p) => `${p.lat},${p.lng}`).join(';')}`)
      .join('|'),
  () => {
    if (map) {
      nextTick(() => renderPolygons())
    }
  },
)

watch(
  () =>
    [
      props.interactive,
      props.editablePinId,
      props.editablePolygonId,
      props.polygonDrawMode,
      props.overlayEditable,
      props.scrollWheelZoom,
      props.scrollWheelZoomRequireCtrl,
    ] as const,
  () => {
    applyMapInteractivity()
    renderMarkers({ fit: false })
    renderPolygons()
  },
)

watch(
  () => props.preferSwissMap,
  (useSwiss) => {
    if (!map) return
    setLayer(useSwiss ? 'swisstopo' : 'osm')
  },
)

watch(
  () => props.overlayEditable,
  (editable) => {
    if (!editable) {
      overlayEditBounds = null
      clearOverlayEditUi()
    } else {
      syncOverlayEditBoundsFromProps()
    }
    applyOverlay()
  },
)

watch(
  () => [props.overlay?.url, props.overlay?.north, props.overlay?.south, props.overlay?.east, props.overlay?.west, props.overlay?.opacity, planVisible.value, props.overlayEditable] as const,
  () => {
    if (overlayDragActive) return
    if (props.overlayEditable) {
      syncOverlayEditBoundsFromProps()
    }
    applyOverlay()
  },
)

onMounted(() => {
  void nextTick(() => initMap())
})

onUnmounted(() => {
  if (locationSearchTimeout) clearTimeout(locationSearchTimeout)
  if (hideLocationSearchTimeout) clearTimeout(hideLocationSearchTimeout)
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

.activity-dual-location-map__search {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 1000;
  width: min(280px, calc(100% - 160px));
  pointer-events: auto;
}

.activity-dual-location-map__search-input {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  font-size: 0.85rem;
  color: #334155;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
}

.activity-dual-location-map__search-input:focus {
  outline: none;
  border-color: #059669;
  box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.15);
}

.activity-dual-location-map__search-dropdown {
  margin-top: 4px;
  max-height: 220px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
}

.activity-dual-location-map__search-hint {
  margin: 0;
  padding: 10px 12px;
  font-size: 0.8rem;
  color: #64748b;
}

.activity-dual-location-map__search-item {
  display: block;
  width: 100%;
  padding: 8px 12px;
  border: 0;
  border-bottom: 1px solid #f1f5f9;
  background: #fff;
  text-align: left;
  font-size: 0.82rem;
  color: #334155;
  cursor: pointer;
}

.activity-dual-location-map__search-item:last-child {
  border-bottom: 0;
}

.activity-dual-location-map__search-item:hover,
.activity-dual-location-map__search-item:focus-visible {
  background: #ecfdf5;
  color: #166534;
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

:deep(.activity-dual-location-map__vertex-wrap) {
  background: transparent;
  border: none;
}

:deep(.activity-dual-location-map__vertex) {
  display: block;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.4);
  cursor: pointer;
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

:deep(.activity-dual-location-map__overlay-handle-wrap) {
  background: transparent !important;
  border: none !important;
}

:deep(.activity-dual-location-map__overlay-handle) {
  display: block;
  box-sizing: border-box;
  border: 3px solid #fff;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.45);
  cursor: grab;
  pointer-events: auto;
}

:deep(.activity-dual-location-map__overlay-handle:active) {
  cursor: grabbing;
}

:deep(.activity-dual-location-map__overlay-handle--move) {
  border-radius: 8px;
}
</style>
