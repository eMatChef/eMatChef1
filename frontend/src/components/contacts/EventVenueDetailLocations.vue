<template>
  <div ref="rootRef" class="event-venue-detail-locations">
    <div v-if="!hideTitle" class="event-venue-detail-locations-header">
      <h2 class="event-venue-detail-locations-title">
        {{ t('activities.venueLocations.sectionTitle') }}
      </h2>
      <div v-if="!readOnly" class="event-venue-detail-locations-actions">
        <EButton
          v-if="editingVenue && !createPinMode"
          variant="primary"
          size="small"
          :loading="isSavingVenue"
          :disabled="!canAcceptVenue"
          @click="acceptVenueDraft"
        >
          {{ acceptVenueLabel }}
        </EButton>
        <button
          v-else-if="!createPinMode && !extraEditablePinId && !extraFocusPinId"
          type="button"
          class="event-venue-map-edit-btn"
          :aria-label="t('common.edit')"
          @click="startVenueEdit"
        >
          <v-icon icon="mdi-pencil-outline" size="16" />
        </button>
      </div>
    </div>
    <div v-else-if="!readOnly && (editingVenue || (!extraEditablePinId && !extraFocusPinId))" class="event-venue-detail-locations-header event-venue-detail-locations-header--actions-only">
      <div class="event-venue-detail-locations-actions">
        <EButton
          v-if="editingVenue && !createPinMode"
          variant="primary"
          size="small"
          :loading="isSavingVenue"
          :disabled="!canAcceptVenue"
          @click="acceptVenueDraft"
        >
          {{ acceptVenueLabel }}
        </EButton>
        <button
          v-else-if="!createPinMode && !extraEditablePinId && !extraFocusPinId"
          type="button"
          class="event-venue-map-edit-btn"
          :aria-label="t('common.edit')"
          @click="startVenueEdit"
        >
          <v-icon icon="mdi-pencil-outline" size="16" />
        </button>
      </div>
    </div>

    <p v-if="editingVenue" class="event-venue-edit-hint">
      {{ createPinMode ? t('activities.venueLocations.createPinHint') : t('contacts.detail.mapEditHint') }}
    </p>

    <div class="event-venue-detail-map-layout">
      <div class="event-venue-detail-accordion-list">
      <template v-for="item in sidebarListItems" :key="item.key">
      <div
        v-if="item.type === 'site'"
        class="event-venue-detail-accordion"
        :data-site-id="item.site.id"
        :class="{ 'is-highlighted': highlightPulseId === item.site.id }"
      >
        <div class="event-venue-detail-accordion-row">
          <button
            type="button"
            class="event-venue-detail-accordion-toggle"
            :aria-expanded="expandedId === item.site.id"
            @click="toggleSite(item.site.id)"
          >
            <span class="event-venue-detail-accordion-chevron" aria-hidden="true">
              {{ expandedId === item.site.id ? '▾' : '▸' }}
            </span>
            <span
              class="event-venue-detail-accordion-dot"
              :style="{ background: item.site.color }"
              aria-hidden="true"
            />
            <span class="event-venue-detail-accordion-label">{{ item.site.label }}</span>
            <span v-if="item.site.summary" class="event-venue-detail-accordion-summary">{{ item.site.summary }}</span>
          </button>
          <button
            v-if="showSiteStar(item.site)"
            type="button"
            class="event-venue-accordion-star-btn"
            :class="{ 'is-on': item.site.starred }"
            :aria-label="t('activities.venueLocations.starToggle')"
            :title="t('activities.venueLocations.starToggle')"
            @click.stop.prevent="emit('toggle-extra-star', item.site.id)"
          >
            <v-icon :icon="item.site.starred ? 'mdi-star' : 'mdi-star-outline'" size="16" />
          </button>
          <button
            v-if="showSitePencil(item.site)"
            type="button"
            class="event-venue-accordion-edit-btn"
            :aria-label="t('common.edit')"
            @click.stop.prevent="item.site.onEdit()"
          >
            <v-icon icon="mdi-pencil-outline" size="16" />
          </button>
          <button
            v-if="showSiteTrash(item.site)"
            type="button"
            class="event-venue-accordion-edit-btn event-venue-accordion-delete-btn"
            :aria-label="t('activities.venueLocations.deleteSite')"
            :title="t('activities.venueLocations.deleteSite')"
            @click.stop.prevent="item.site.onDelete?.()"
          >
            <v-icon icon="mdi-delete-outline" size="16" />
          </button>
        </div>
        <div v-show="expandedId === item.site.id" class="event-venue-detail-accordion-body">
          <template v-if="createPinMode && item.site.id === 'venue'">
            <ETextField
              v-model="createName"
              :label="t('activities.venueLocations.createNameLabel')"
              :placeholder="t('activities.venueLocations.createNamePlaceholder')"
              :hint="t('activities.venueLocations.createNameHint')"
              :persistent-hint="true"
              hide-details="auto"
              @update:model-value="onCreateNameInput"
            />
            <p class="field-hint text-muted">
              {{
                draftLat != null
                  ? t('activities.venueLocations.createPinPlacedHint')
                  : t('activities.venueLocations.createPinHint')
              }}
            </p>
            <div class="event-venue-detail-accordion-actions">
              <EButton
                variant="primary"
                size="small"
                data-onboarding="activity-venue-set-pin"
                :loading="isSavingVenue"
                :disabled="!canAcceptVenue"
                @click="acceptVenueDraft"
              >
                {{ acceptVenueLabel }}
              </EButton>
            </div>
          </template>
          <template v-else-if="isAreaDrawSite(item.site)">
            <p class="field-hint text-muted">
              {{ extraPlaceHintText || t('grossanlass.planung.ressorts.areaMapHint') }}
            </p>
            <p v-if="areaPointCount(item.site) >= 3" class="field-hint text-muted">
              {{ t('grossanlass.einstellungen.placesPolygonReady', { count: areaPointCount(item.site) }) }}
            </p>
            <p class="field-hint text-muted">{{ t('grossanlass.planung.ressorts.areaSaveHint') }}</p>
            <div class="event-venue-detail-accordion-actions">
              <EButton
                variant="secondary"
                size="small"
                type="button"
                :disabled="areaPointCount(item.site) === 0"
                @click.stop.prevent="emit('undo-polygon')"
              >
                {{ t('grossanlass.planung.ressorts.areaUndoPoint') }}
              </EButton>
              <EButton
                variant="secondary"
                size="small"
                type="button"
                :disabled="areaPointCount(item.site) === 0"
                @click.stop.prevent="emit('redraw-polygon')"
              >
                {{ t('grossanlass.planung.ressorts.areaRedraw') }}
              </EButton>
              <EButton
                variant="primary"
                size="small"
                type="button"
                @click.stop.prevent="emit('save-area')"
              >
                {{ t('common.save') }}
              </EButton>
            </div>
          </template>
          <template v-else>
            <p class="field-hint text-muted">
              {{
                isExtraEditingSite(item.site)
                  ? extraPlaceHintText || item.site.hint || item.site.summary || '—'
                  : item.site.hint || item.site.summary || '—'
              }}
            </p>
            <p v-if="item.site.address && !item.site.pin" class="field-hint text-muted">
              {{ t('activities.venueLocations.noCoordsForAddress') }}
            </p>
            <p v-else-if="item.site.extra && !item.site.pin && !isAreaSite(item.site)" class="field-hint text-muted">
              {{ t('activities.venueLocations.extraNoCoords') }}
            </p>
            <div class="event-venue-detail-accordion-actions">
              <EButton
                v-if="canEditSite(item.site) && item.site.extra && !item.site.pin && !isAreaSite(item.site)"
                variant="secondary"
                size="small"
                @click.stop.prevent="item.site.onEdit()"
              >
                {{ t('grossanlass.einstellungen.placesSetLocation') }}
              </EButton>
              <button
                v-if="canEditSite(item.site)"
                type="button"
                class="event-venue-accordion-edit-btn"
                :aria-label="t('common.edit')"
                @click.stop.prevent="item.site.onEdit()"
              >
                <v-icon icon="mdi-pencil-outline" size="16" />
              </button>
              <a
                v-if="item.site.qrUrl"
                :href="item.site.qrUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn-outline btn-sm"
              >
                {{ t('activities.venueLocations.extraQr') }}
              </a>
              <template v-if="item.site.pin">
                <a
                  :href="googleMapsLinkFor(item.site.pin)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn btn-outline btn-sm"
                >
                  {{ t('components.mapView.openGoogleMaps') }}
                </a>
                <a
                  :href="swisstopoLinkFor(item.site.pin)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn btn-outline btn-sm"
                >
                  {{ t('components.mapView.openSwisstopoMap') }}
                </a>
              </template>
            </div>
          </template>
        </div>
      </div>

      <button
        v-else-if="!readOnly && !createPinMode"
        type="button"
        class="event-venue-detail-add-row"
        :data-onboarding="item.onboarding"
        :class="{ 'is-highlighted': highlightPulseId === item.key }"
        @click="item.onClick()"
      >
        <span class="event-venue-detail-add-plus" aria-hidden="true">+</span>
        <span>{{ item.label }}</span>
      </button>
      </template>

      <template v-if="!coreLocationMode">
      <button
        v-if="!readOnly && allowChildren && !createPinMode && (allowPoiChildren || !deliveryAddress)"
        type="button"
        class="event-venue-detail-add-row"
        data-onboarding="activity-venue-delivery-add"
        :class="{ 'is-highlighted': highlightPulseId === 'add-delivery' }"
        @click="emit('create-child')"
      >
        <span class="event-venue-detail-add-plus" aria-hidden="true">+</span>
        <span>{{ addChildButtonLabel }}</span>
      </button>
      <button
        v-if="!readOnly && allowExtraSites && allowCreateExtra && !createPinMode"
        type="button"
        class="event-venue-detail-add-row"
        @click="startExtraPlace"
      >
        <span class="event-venue-detail-add-plus" aria-hidden="true">+</span>
        <span>{{ extraAddLabel }}</span>
      </button>
      <p
        v-else-if="!readOnly && allowExtraSites && extraCreateLockedHint && !createPinMode"
        class="field-hint text-muted event-venue-detail-extra-locked"
      >
        {{ extraCreateLockedHint }}
      </p>
      </template>
      </div>

      <div class="event-venue-detail-map-panel">
        <ActivityDualLocationMap
          ref="overviewMapRef"
          v-model:plan-visible="planVisible"
          :pins="displayPins"
          :polygons="displayPolygons"
          height="var(--ev-map-height, 360px)"
          :interactive="mapInteractive"
          :editable-pin-id="polygonDrawMode ? null : mapEditablePinId"
          :editable-polygon-id="polygonDrawMode ? extraEditablePinId : null"
          :polygon-draw-mode="polygonDrawMode"
          :prefer-swiss-map="true"
          :show-layer-control="true"
          :overlay="overlay"
          :overlay-editable="overlayEditable"
          :scroll-wheel-zoom="scrollWheelZoom"
          :scroll-wheel-zoom-require-ctrl="scrollWheelZoomRequireCtrl"
          :show-location-search="mapShowLocationSearch"
          @pin-moved="onMapPinMoved"
          @map-click="onVenueMapClick"
          @polygon-change="onPolygonChange"
          @overlay-bounds-change="(bounds) => emit('overlay-bounds-change', bounds)"
        />
        <p v-if="scrollWheelZoomRequireCtrl" class="field-hint text-muted event-venue-map-zoom-hint">
          {{ t('grossanlass.beschaffung.anfragen.mapZoomHint') }}
        </p>
      </div>
    </div>

    <p v-if="!createPinMode && (allowChildren || allowExtraSites) && !lockCoreAdds" class="field-hint text-muted">{{ overviewHintText }}</p>
    <p v-else-if="!createPinMode && !allowChildren" class="field-hint text-muted">
      {{ t('activities.venueLocations.venueMapEditHint') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Address } from '@/api/addresses'
import { updateAddress } from '@/api/addresses'
import { EButton, ETextField } from '@/components/form/base'
import ActivityDualLocationMap, {
  type ActivityLocationPin,
  type ActivityMapOverlay,
  type ActivityMapPolygon,
} from '@/components/activities/ActivityDualLocationMap.vue'
import type { GaPolygonPoint } from '@/api/grossanlassLogistics'
import { googleMapsCoordinatesUrl, swisstopoMapUrl } from '@/utils/mapExternalLinks'
import { useToast } from '@/composables/useToast'

const VENUE_COLOR = '#2563eb'
const DELIVERY_COLOR = '#ea580c'
const STORAGE_COLOR = '#7c3aed'
const POI_FALLBACK_COLOR = '#16a34a'

export type VenueExtraSite = {
  id: string
  label: string
  summary?: string
  hint?: string
  color: string
  pin: ActivityLocationPin | null
  polygon?: GaPolygonPoint[] | null
  qrUrl?: string | null
  starred?: boolean
  detailOnly?: boolean
  canDelete?: boolean
}

type AccordionSite = {
  id: string
  label: string
  summary: string
  hint?: string
  color: string
  address: Address | null
  pin: ActivityLocationPin | null
  extra?: boolean
  starred?: boolean
  qrUrl?: string | null
  polygon?: GaPolygonPoint[] | null
  canDelete?: boolean
  onEdit: () => void
  onDelete?: () => void
}

const props = withDefaults(
  defineProps<{
    eventAddress?: Address | null
    childAddresses?: Address[]
    readOnly?: boolean
    /** Erfassen: Karte + Accordion-Bezeichnung, Speichern emittiert pin-accepted. */
    createPinMode?: boolean
    /** Kind-Adressen (Zustellpunkt / Event-Punkt) — nur Eventstandort. */
    allowChildren?: boolean
    /** Label-Kontext für den Haupt-Pin. */
    locationKind?: 'event' | 'meeting'
    /** Titel «Standorte» ausblenden (z.B. unter Eventstandort-Feld). */
    hideTitle?: boolean
    /** Zustellpunkt / «Lieferort erfassen» hervorheben (Blinken + Scroll). */
    highlightDelivery?: boolean
    /** Bezeichnung von oben (Kontaktformular) — vorausfüllen / synchron halten. */
    suggestedName?: string
    extraSites?: VenueExtraSite[]
    allowExtraSites?: boolean
    allowCreateExtra?: boolean
    allowStarExtra?: boolean
    /** Nach dem Zustellpunkt weitere Address-POIs — beim Grossanlass aus, dort sind Extra-Punkte GA-Orte. */
    allowPoiChildren?: boolean
    extraEditablePinId?: string | null
    extraFocusPinId?: string | null
    extraPlaceHint?: string
    addExtraButtonLabel?: string
    overviewHint?: string
    extraCreateLockedHint?: string
    /** Grossanlass: feste Reihenfolge Eventstandort → Zustellpunkt → Lagerstandort → GA-Orte. */
    coreLocationMode?: boolean
    /** Keine +-Zeilen für Kernstandorte/GA (Stammdaten-Übersicht). */
    lockCoreAdds?: boolean
    hasStorageLocation?: boolean
    storageSummary?: string
    storageLatitude?: number | null
    storageLongitude?: number | null
    /** Ortschaft-Suche auf der Karte (Standard: nur im Erfassungsmodus). */
    showLocationSearch?: boolean
    overlay?: ActivityMapOverlay | null
    overlayEditable?: boolean
    /** Mausrad-Zoom — auf Stammdaten Strg+Scroll, damit die Seite sonst scrollt. */
    scrollWheelZoom?: boolean
    scrollWheelZoomRequireCtrl?: boolean
  }>(),
  {
    eventAddress: null,
    childAddresses: () => [],
    readOnly: false,
    createPinMode: false,
    allowChildren: true,
    locationKind: 'event',
    hideTitle: false,
    highlightDelivery: false,
    suggestedName: '',
    extraSites: () => [],
    allowExtraSites: false,
    allowCreateExtra: true,
    allowStarExtra: false,
    allowPoiChildren: true,
    extraEditablePinId: null,
    extraFocusPinId: null,
    extraPlaceHint: '',
    addExtraButtonLabel: '',
    overviewHint: '',
    extraCreateLockedHint: '',
    coreLocationMode: false,
    lockCoreAdds: false,
    hasStorageLocation: false,
    storageSummary: '',
    storageLatitude: null,
    storageLongitude: null,
    overlay: null,
    overlayEditable: false,
    scrollWheelZoom: true,
    scrollWheelZoomRequireCtrl: false,
  },
)

const emit = defineEmits<{
  'edit-child': [address: Address]
  'create-child': []
  'edit-venue-details': []
  'venue-updated': [address: Address]
  'pin-accepted': [payload: { latitude: number; longitude: number; name: string }]
  'update:suggestedName': [name: string]
  'edit-extra': [id: string]
  'delete-extra': [id: string]
  'create-extra': []
  'create-storage': []
  'edit-storage': []
  'delete-storage': []
  'delete-venue': []
  'delete-child': [address: Address]
  'toggle-extra-star': [id: string]
  'extra-pin-moved': [payload: { id: string; latitude: number; longitude: number }]
  'extra-map-click': [payload: { id: string; latitude: number; longitude: number }]
  'polygon-change': [payload: { id: string; points: GaPolygonPoint[] }]
  'overlay-bounds-change': [bounds: ActivityMapOverlay]
  'undo-polygon': []
  'redraw-polygon': []
  'save-area': []
}>()

const { t, locale } = useI18n()
const toast = useToast()

const rootRef = ref<HTMLElement | null>(null)
const expandedId = ref<string | null>(null)
const highlightPulseId = ref<string | null>(null)
let highlightClearTimer: ReturnType<typeof setTimeout> | null = null
const overviewMapRef = ref<InstanceType<typeof ActivityDualLocationMap> | null>(null)
const planVisible = defineModel<boolean>('planVisible', { default: true })
let mapLayoutObserver: ResizeObserver | null = null

const editingVenue = ref(false)
const isSavingVenue = ref(false)
const draftLat = ref<number | null>(null)
const draftLng = ref<number | null>(null)
const baselineLat = ref<number | null>(null)
const baselineLng = ref<number | null>(null)
const createName = ref('')
/** Letzter von oben übernommener Wert — damit manuelle Edits nicht überschrieben werden. */
const lastSyncedSuggested = ref('')

function applySuggestedName(raw: string | undefined, force = false) {
  if (!props.createPinMode) return
  const next = (raw ?? '').trim()
  if (!force && createName.value.trim() && createName.value !== lastSyncedSuggested.value) {
    return
  }
  createName.value = next
  lastSyncedSuggested.value = next
}

function onCreateNameInput(value: string | number | null) {
  const next = String(value ?? '')
  createName.value = next
  lastSyncedSuggested.value = next.trim()
  emit('update:suggestedName', next.trim())
}

const deliveryAddress = computed(
  () => props.childAddresses.find((a) => a.type === 'event_delivery') ?? null,
)

const poiAddresses = computed(() => props.childAddresses.filter((a) => a.type === 'event_poi'))

const venueSiteLabel = computed(() =>
  props.locationKind === 'meeting'
    ? t('settings.addressForm.types.meeting')
    : t('activities.wizard.form.venueLabel'),
)

const acceptVenueLabel = computed(() => {
  if (!props.createPinMode) return t('contacts.detail.acceptLocation')
  return props.locationKind === 'meeting'
    ? t('activities.venueLocations.setMeetingPoint')
    : t('activities.venueLocations.setEventVenue')
})

const addChildButtonLabel = computed(() =>
  deliveryAddress.value
    ? t('activities.venueLocations.addExtraAddressButton')
    : t('activities.venueLocations.addDeliveryAddressButton'),
)

const extraAddLabel = computed(
  () => props.addExtraButtonLabel || t('activities.venueLocations.addExtraGaPlaceButton'),
)

const extraPlaceHintText = computed(
  () => props.extraPlaceHint || t('activities.venueLocations.extraPlaceHint'),
)

const overviewHintText = computed(
  () => props.overviewHint || t('activities.venueLocations.overviewHint'),
)

const polygonDrawMode = computed(() => {
  if (!props.extraEditablePinId || props.readOnly) return false
  const site = props.extraSites.find((row) => row.id === props.extraEditablePinId)
  return site?.polygon != null
})

const mapInteractive = computed(
  () =>
    props.lockCoreAdds
    || editingVenue.value
    || (!!props.extraEditablePinId && !props.readOnly)
    || (!!props.overlayEditable && !props.readOnly)
    || (props.allowExtraSites && !props.readOnly)
    || polygonDrawMode.value,
)

const mapEditablePinId = computed(() => {
  if (editingVenue.value) return 'venue'
  return props.extraEditablePinId
})

const canAcceptVenue = computed(() => {
  if (draftLat.value == null || draftLng.value == null) return false
  if (props.createPinMode && !createName.value.trim()) return false
  return true
})

function addressSummary(addr: Address | null | undefined): string {
  if (!addr) return ''
  if (addr.full_address?.trim()) return addr.full_address.trim()
  const street = (addr.street_line || [addr.street, addr.street_number].filter(Boolean).join(' ')).trim()
  const place = [addr.postal_code, addr.city].filter(Boolean).join(' ').trim()
  if (street && place) return `${street}, ${place}`
  if (street || place) return street || place
  return addr.name || addr.company || ''
}

function formatCoords(lat: number, lng: number): string {
  return `${lat.toFixed(5)}° N, ${lng.toFixed(5)}° E`
}

function pinFromAddress(
  id: string,
  label: string,
  addr: Address | null | undefined,
  variant: ActivityLocationPin['variant'],
  color?: string | null,
): ActivityLocationPin | null {
  if (addr?.latitude == null || addr.longitude == null) return null
  return {
    id,
    label,
    latitude: addr.latitude,
    longitude: addr.longitude,
    variant,
    color: color ?? null,
  }
}

const venuePinBase = computed(() =>
  pinFromAddress(
    'venue',
    venueSiteLabel.value,
    props.eventAddress,
    'venue',
  ),
)

const storagePinBase = computed((): ActivityLocationPin | null => {
  const lat = props.storageLatitude
  const lng = props.storageLongitude
  if (lat == null || lng == null) return null
  return {
    id: 'storage',
    label: t('grossanlass.einstellungen.coreStepStorage'),
    latitude: lat,
    longitude: lng,
    variant: 'poi',
    color: STORAGE_COLOR,
  }
})

const mapShowLocationSearch = computed(
  () => props.showLocationSearch ?? props.createPinMode,
)

const accordionSites = computed((): AccordionSite[] => {
  // Erfassen: Accordion schon sichtbar, auch ohne gespeicherte Adresse
  if (props.createPinMode && !props.eventAddress) {
    const name = createName.value.trim()
    let summary = t('activities.venueLocations.siteMissing')
    if (draftLat.value != null && draftLng.value != null) {
      summary = formatCoords(draftLat.value, draftLng.value)
    } else if (name) {
      summary = name
    }
    return [
      {
        id: 'venue',
        label: venueSiteLabel.value,
        summary,
        hint: t('activities.venueLocations.createPinHint'),
        color: VENUE_COLOR,
        address: null,
        pin: null,
        onEdit: () => emit('edit-venue-details'),
      },
    ]
  }

  if (!props.eventAddress) return []
  const sites: AccordionSite[] = [
    {
      id: 'venue',
      label: venueSiteLabel.value,
      summary: addressSummary(props.eventAddress) || t('activities.venueLocations.venueMapOnlyHint'),
      hint: t('activities.venueLocations.venueMapEditHint'),
      color: VENUE_COLOR,
      address: props.eventAddress,
        pin: venuePinBase.value,
        // Stift → Eventstandort/Treffpunkt bearbeiten (nicht Kind anlegen)
        canDelete: props.coreLocationMode && !props.readOnly && !props.lockCoreAdds,
        onEdit: () => emit('edit-venue-details'),
        onDelete: () => emit('delete-venue'),
      },
    ]

  if (props.allowChildren) {
    const delivery = deliveryAddress.value
    if (delivery) {
      sites.push({
        id: delivery.id,
        label: t('activities.venueLocations.accordionDelivery'),
        summary: addressSummary(delivery),
        hint: t('activities.venueLocations.deliveryManageHint'),
        color: DELIVERY_COLOR,
        address: delivery,
        pin: pinFromAddress(
          delivery.id,
          t('activities.venueLocations.deliveryPinLabel'),
          delivery,
          'delivery',
        ),
        canDelete: !props.readOnly && !props.lockCoreAdds,
        onEdit: () => emit('edit-child', delivery),
        onDelete: () => emit('delete-child', delivery),
      })
    }

    for (const poi of poiAddresses.value) {
      const color = poi.pin_color || POI_FALLBACK_COLOR
      const label = poi.name || t('activities.venueLocations.poiFallbackLabel')
      sites.push({
        id: poi.id,
        label,
        summary: addressSummary(poi),
        color,
        address: poi,
        pin: pinFromAddress(poi.id, label, poi, 'poi', color),
        canDelete: !props.readOnly && !props.lockCoreAdds,
        onEdit: () => emit('edit-child', poi),
        onDelete: () => emit('delete-child', poi),
      })
    }
  }

  for (const extra of props.extraSites) {
    const pin = extra.pin
    const polygon = extra.polygon ?? null
    const pointCount = polygon?.length ?? 0
    sites.push({
      id: extra.id,
      label: extra.label,
      summary:
        polygon != null && pointCount > 0
          ? t('grossanlass.einstellungen.placesPolygonReady', { count: pointCount })
          : extra.summary || (pin ? formatCoords(pin.latitude, pin.longitude) : t('activities.venueLocations.siteMissing')),
      hint: extra.hint,
      color: extra.color,
      address: null,
      pin,
      extra: true,
      starred: extra.starred === true,
      qrUrl: extra.qrUrl ?? null,
      polygon,
      canDelete: extra.canDelete === true,
      onEdit: () => emit('edit-extra', extra.id),
      onDelete: () => emit('delete-extra', extra.id),
    })
  }

  return sites
})

type SidebarAddItem = {
  type: 'add'
  key: string
  label: string
  onboarding?: string
  onClick: () => void
}

type SidebarSiteItem = {
  type: 'site'
  key: string
  site: AccordionSite
}

type SidebarItem = SidebarAddItem | SidebarSiteItem

const sidebarListItems = computed((): SidebarItem[] => {
  if (!props.coreLocationMode) {
    return accordionSites.value.map((site) => ({ type: 'site', key: site.id, site }))
  }

  const items: SidebarItem[] = []
  const sites = accordionSites.value
  const venue = sites.find((site) => site.id === 'venue')
  if (venue) items.push({ type: 'site', key: venue.id, site: venue })

  const delivery = deliveryAddress.value
  if (delivery) {
    const deliverySite = sites.find((site) => site.id === delivery.id)
    if (deliverySite) items.push({ type: 'site', key: deliverySite.id, site: deliverySite })
  } else if (!props.readOnly && props.allowChildren && !props.lockCoreAdds) {
    items.push({
      type: 'add',
      key: 'add-delivery',
      label: t('activities.venueLocations.addCoreDeliveryButton'),
      onboarding: 'activity-venue-delivery-add',
      onClick: () => emit('create-child'),
    })
  }

  if (props.hasStorageLocation) {
    items.push({
      type: 'site',
      key: 'storage',
      site: {
        id: 'storage',
        label: t('grossanlass.einstellungen.coreStepStorage'),
        summary: props.storageSummary || t('activities.venueLocations.siteMissing'),
        hint: t('grossanlass.einstellungen.lagerHint'),
        color: STORAGE_COLOR,
        address: null,
        pin: storagePinBase.value,
        canDelete: !props.readOnly && !props.lockCoreAdds,
        onEdit: () => emit('edit-storage'),
        onDelete: () => emit('delete-storage'),
      },
    })
  } else if (!props.readOnly && !props.lockCoreAdds) {
    items.push({
      type: 'add',
      key: 'add-storage',
      label: t('settings.storage.addStorageLocation'),
      onClick: () => emit('create-storage'),
    })
  }

  for (const site of sites.filter((row) => row.extra)) {
    items.push({ type: 'site', key: site.id, site })
  }

  if (!props.readOnly && props.allowExtraSites && props.allowCreateExtra && !props.createPinMode) {
    items.push({
      type: 'add',
      key: 'add-ga',
      label: extraAddLabel.value,
      onClick: () => startExtraPlace(),
    })
  }

  return items
})

const childPins = computed((): ActivityLocationPin[] =>
  accordionSites.value
    .filter((s) => s.id !== 'venue')
    .map((s) => s.pin)
    .filter((p): p is ActivityLocationPin => p != null),
)

const displayPins = computed((): ActivityLocationPin[] => {
  const pins = [...childPins.value]
  if (storagePinBase.value) {
    pins.push(storagePinBase.value)
  }
  if (editingVenue.value && draftLat.value != null && draftLng.value != null) {
    pins.unshift({
      id: 'venue',
      // Fixe Typ-Bezeichnung auf der Karte (Eventstandort / Treffpunkt) — Freitext nur im Accordion
      label: venueSiteLabel.value,
      latitude: draftLat.value,
      longitude: draftLng.value,
      variant: 'venue',
    })
  } else if (venuePinBase.value) {
    pins.unshift(venuePinBase.value)
  }
  return pins.map((pin) => {
    const extra = props.extraSites.find((row) => row.id === pin.id)
    if (!extra) return pin
    return { ...pin, detailOnly: extra.detailOnly === true }
  })
})

const displayPolygons = computed((): ActivityMapPolygon[] =>
  props.extraSites
    .filter((row) => row.polygon != null)
    .map((row) => ({
      id: row.id,
      label: row.label,
      color: row.color,
      points: row.polygon ?? [],
      starred: row.starred === true,
      detailOnly: row.detailOnly === true,
    })),
)

function mapLinkLang(): string {
  return locale.value.split('-')[0] || 'de'
}

function googleMapsLinkFor(pin: ActivityLocationPin): string {
  return googleMapsCoordinatesUrl(pin.latitude, pin.longitude)
}

function swisstopoLinkFor(pin: ActivityLocationPin): string {
  return swisstopoMapUrl(pin.latitude, pin.longitude, { lang: mapLinkLang() })
}

function toggleSite(id: string) {
  expandedId.value = expandedId.value === id ? null : id
  void refreshMaps()
}

function focusedExtraId(): string | null {
  return props.extraEditablePinId || props.extraFocusPinId || null
}

function isFocusedExtraSite(site: AccordionSite): boolean {
  const focusId = focusedExtraId()
  return !focusId || site.id === focusId
}

function canEditSite(site: AccordionSite): boolean {
  if (props.readOnly || props.createPinMode) return false
  if (props.lockCoreAdds && site.extra) return false
  if (!isFocusedExtraSite(site)) return false
  return true
}

function showSiteStar(site: AccordionSite): boolean {
  return Boolean(
    !props.readOnly
    && !props.createPinMode
    && site.extra
    && props.allowStarExtra
    && isFocusedExtraSite(site),
  )
}

function showSitePencil(site: AccordionSite): boolean {
  if (!canEditSite(site)) return false
  if (isAreaDrawSite(site) || isExtraEditingSite(site)) return false
  return true
}

function showSiteTrash(site: AccordionSite): boolean {
  if (props.readOnly || props.createPinMode || props.lockCoreAdds) return false
  if (!site.canDelete || !site.onDelete) return false
  if (props.extraEditablePinId || props.extraFocusPinId) return false
  return true
}

function isAreaSite(site: AccordionSite): boolean {
  return site.polygon != null
}

function isAreaDrawSite(site: AccordionSite): boolean {
  return Boolean(
    !props.readOnly
    && props.extraEditablePinId
    && site.id === props.extraEditablePinId
    && isAreaSite(site),
  )
}

function isExtraEditingSite(site: AccordionSite): boolean {
  return Boolean(
    site.extra
    && !props.readOnly
    && props.extraEditablePinId
    && site.id === props.extraEditablePinId,
  )
}

function areaPointCount(site: AccordionSite): number {
  return site.polygon?.length ?? 0
}

function expandSite(id: string) {
  expandedId.value = id
  void nextTick(() => {
    rootRef.value
      ?.querySelector(`[data-site-id="${id}"]`)
      ?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
  })
}

function openEditableExtraSite(id: string) {
  expandSite(id)
}

async function refreshMaps() {
  await nextTick()
  overviewMapRef.value?.invalidateSize()
}

function startExtraPlace() {
  editingVenue.value = false
  emit('create-extra')
}

function startVenueEdit() {
  if (props.readOnly) return
  // Beim Erfassen: kein Auto-Pin → ganze Schweiz sichtbar; Pin per Klick setzen
  draftLat.value = props.eventAddress?.latitude ?? null
  draftLng.value = props.eventAddress?.longitude ?? null
  baselineLat.value = props.eventAddress?.latitude ?? null
  baselineLng.value = props.eventAddress?.longitude ?? null
  editingVenue.value = true
  expandedId.value = 'venue'
  void refreshMaps()
  if (props.createPinMode) {
    void nextTick(() => overviewMapRef.value?.fitToPins())
  }
}

function onMapPinMoved(payload: { id: string; latitude: number; longitude: number }) {
  if (payload.id === 'venue') {
    draftLat.value = payload.latitude
    draftLng.value = payload.longitude
    return
  }
  emit('extra-pin-moved', payload)
}

function onVenueMapClick(payload: { latitude: number; longitude: number }) {
  if (editingVenue.value) {
    draftLat.value = payload.latitude
    draftLng.value = payload.longitude
    expandedId.value = 'venue'
    return
  }
  if (polygonDrawMode.value) return
  if (props.extraEditablePinId) {
    emit('extra-map-click', {
      id: props.extraEditablePinId,
      latitude: payload.latitude,
      longitude: payload.longitude,
    })
  }
}

function onPolygonChange(payload: { id: string; points: GaPolygonPoint[] }) {
  emit('polygon-change', payload)
}

async function acceptVenueDraft() {
  if (draftLat.value == null || draftLng.value == null) {
    if (!props.createPinMode) editingVenue.value = false
    return
  }

  if (props.createPinMode) {
    const name = createName.value.trim()
    if (!name) {
      toast.error(t('activities.venueLocations.createNameRequired'))
      expandedId.value = 'venue'
      return
    }
    isSavingVenue.value = true
    try {
      emit('pin-accepted', {
        latitude: draftLat.value,
        longitude: draftLng.value,
        name,
      })
    } finally {
      isSavingVenue.value = false
    }
    return
  }

  if (!props.eventAddress?.id) {
    editingVenue.value = false
    return
  }

  const same =
    baselineLat.value != null
    && baselineLng.value != null
    && Math.abs(draftLat.value - baselineLat.value) < 1e-7
    && Math.abs(draftLng.value - baselineLng.value) < 1e-7
  if (same) {
    editingVenue.value = false
    return
  }
  isSavingVenue.value = true
  try {
    const { address } = await updateAddress(props.eventAddress.id, {
      latitude: draftLat.value,
      longitude: draftLng.value,
    })
    baselineLat.value = address.latitude
    baselineLng.value = address.longitude
    editingVenue.value = false
    emit('venue-updated', address)
    await refreshMaps()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('contacts.detail.saveError'))
  } finally {
    isSavingVenue.value = false
  }
}

function onOutsidePointerDown(event: Event) {
  if (!editingVenue.value || props.createPinMode) return
  const el = rootRef.value
  const target = event.target as Node | null
  if (!el || !target || el.contains(target)) return
  void acceptVenueDraft()
}

watch(editingVenue, (active) => {
  if (active) {
    nextTick(() => {
      document.addEventListener('pointerdown', onOutsidePointerDown, true)
    })
  } else {
    document.removeEventListener('pointerdown', onOutsidePointerDown, true)
  }
})

watch(expandedId, () => {
  void refreshMaps()
})

watch(
  displayPins,
  () => {
    if (polygonDrawMode.value) return
    void refreshMaps()
  },
  { deep: true },
)

watch(
  () => props.suggestedName,
  (name) => {
    applySuggestedName(name)
  },
)

watch(
  () => props.createPinMode,
  (active, wasActive) => {
    if (active) {
      lastSyncedSuggested.value = ''
      applySuggestedName(props.suggestedName, true)
      startVenueEdit()
      return
    }
    if (wasActive) {
      editingVenue.value = false
      expandedId.value = 'venue'
      void refreshMaps()
    }
  },
)

onMounted(() => {
  if (props.createPinMode) {
    applySuggestedName(props.suggestedName, true)
    startVenueEdit()
  }
  if (typeof ResizeObserver !== 'undefined' && rootRef.value) {
    mapLayoutObserver = new ResizeObserver(() => {
      overviewMapRef.value?.invalidateSize()
    })
    mapLayoutObserver.observe(rootRef.value)
  }
})

onBeforeUnmount(() => {
  mapLayoutObserver?.disconnect()
  mapLayoutObserver = null
  document.removeEventListener('pointerdown', onOutsidePointerDown, true)
  if (highlightClearTimer) {
    clearTimeout(highlightClearTimer)
    highlightClearTimer = null
  }
})

watch(
  () => props.highlightDelivery,
  (on) => {
    if (on) void focusDeliveryHighlight()
  },
)

watch(
  () => [props.extraEditablePinId, props.extraSites.map((site) => site.id).join('|')] as const,
  ([id]) => {
    if (!id) return
    if (!props.extraSites.some((site) => site.id === id)) return
    openEditableExtraSite(id)
  },
  { immediate: true },
)

async function focusDeliveryHighlight() {
  if (highlightClearTimer) {
    clearTimeout(highlightClearTimer)
    highlightClearTimer = null
  }
  await nextTick()
  const delivery = deliveryAddress.value
  if (delivery) {
    expandedId.value = delivery.id
    highlightPulseId.value = delivery.id
  } else if (props.allowChildren) {
    highlightPulseId.value = 'add-delivery'
  } else {
    expandedId.value = 'venue'
    highlightPulseId.value = 'venue'
  }
  await nextTick()
  const root = rootRef.value
  const target =
    highlightPulseId.value === 'add-delivery'
      ? root?.querySelector('.event-venue-detail-add-row.is-highlighted')
        ?? root?.querySelector('[data-onboarding="activity-venue-delivery-add"]')
      : root?.querySelector('.event-venue-detail-accordion.is-highlighted')
  target?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  highlightClearTimer = setTimeout(() => {
    highlightPulseId.value = null
    highlightClearTimer = null
  }, 3200)
}

function getBounds(): { north: number; south: number; east: number; west: number } | null {
  return overviewMapRef.value?.getBounds() ?? null
}

function getOverlayBounds(): ActivityMapOverlay | null {
  return overviewMapRef.value?.getOverlayBounds() ?? null
}

function fitOverlayBounds(bounds: Pick<ActivityMapOverlay, 'north' | 'south' | 'east' | 'west'>) {
  overviewMapRef.value?.fitOverlayBounds(bounds)
}

function refreshOverlayEditUi() {
  overviewMapRef.value?.refreshOverlayEditUi()
}

defineExpose({
  refreshMaps,
  startVenueEdit,
  expandSite,
  focusDeliveryHighlight,
  getBounds,
  getOverlayBounds,
  fitOverlayBounds,
  refreshOverlayEditUi,
  togglePlanVisible() {
    overviewMapRef.value?.togglePlanVisible()
  },
  setPlanVisible(visible: boolean) {
    overviewMapRef.value?.setPlanVisible(visible)
  },
})
</script>

<style scoped>
.event-venue-detail-locations {
  --ev-map-height: 360px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.event-venue-detail-map-layout {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.event-venue-detail-map-panel {
  min-width: 0;
}

.event-venue-map-zoom-hint {
  margin: 6px 0 0;
  font-size: 0.8rem;
}

.event-venue-detail-map-panel :deep(.activity-dual-location-map__stage),
.event-venue-detail-map-panel :deep(.activity-dual-location-map__canvas) {
  height: var(--ev-map-height, 360px);
  min-height: var(--ev-map-height, 360px);
}

@media (min-width: 768px) {
  .event-venue-detail-locations {
    --ev-map-height: 520px;
  }

  .event-venue-detail-map-layout {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) minmax(0, 1.65fr);
    gap: 16px;
    align-items: stretch;
  }

  .event-venue-detail-accordion-list {
    max-height: var(--ev-map-height, 520px);
    overflow-y: auto;
    padding-right: 2px;
  }
}

.event-venue-detail-locations-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.event-venue-detail-locations-header--actions-only {
  justify-content: flex-end;
}

.event-venue-detail-locations-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 650;
  color: #0f172a;
}

.event-venue-detail-locations-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.event-venue-map-edit-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  color: #6b7280;
  cursor: pointer;
}

.event-venue-map-edit-btn:hover {
  background: #f3f4f6;
  color: #111827;
}

.event-venue-edit-hint {
  margin: 0;
  font-size: 13px;
  color: #6b7280;
}

.event-venue-detail-accordion-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.event-venue-detail-accordion {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 12px;
  background: #fff;
}

.event-venue-detail-accordion-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.event-venue-detail-accordion-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
  padding: 0;
  border: none;
  background: none;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #0f172a;
  cursor: pointer;
  text-align: left;
}

.event-venue-accordion-edit-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  padding: 0;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  color: #6b7280;
  cursor: pointer;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.event-venue-accordion-edit-btn:hover {
  background: #f3f4f6;
  color: #111827;
  border-color: #d1d5db;
}

.event-venue-accordion-delete-btn:hover {
  background: #fef2f2;
  color: #dc2626;
  border-color: #fecaca;
}

.event-venue-accordion-star-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  padding: 0;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
  color: #94a3b8;
  cursor: pointer;
}

.event-venue-accordion-star-btn:hover,
.event-venue-accordion-star-btn.is-on {
  color: #d97706;
  border-color: #fbbf24;
  background: #fffbeb;
}

.event-venue-detail-accordion-chevron {
  width: 1rem;
  flex-shrink: 0;
  color: #64748b;
}

.event-venue-detail-accordion-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
  box-shadow: 0 0 0 2px #fff, 0 0 0 3px currentColor;
}

.event-venue-detail-accordion-label {
  flex-shrink: 0;
}

.event-venue-detail-accordion-summary {
  margin-left: auto;
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
  max-width: 45%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.event-venue-detail-accordion-body {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.event-venue-detail-accordion-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.event-venue-detail-add-row {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 12px 14px;
  border: 2px dashed #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #059669;
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  text-align: left;
  transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
}

.event-venue-detail-add-row:hover {
  border-color: #059669;
  background: #ecfdf5;
  color: #047857;
}

.event-venue-detail-add-plus {
  font-size: 1.25rem;
  line-height: 1;
}

.event-venue-detail-accordion.is-highlighted,
.event-venue-detail-add-row.is-highlighted {
  animation: event-venue-highlight-pulse 0.85s ease-in-out 3;
  border-radius: 8px;
}

@keyframes event-venue-highlight-pulse {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(234, 88, 12, 0);
    background-color: transparent;
  }
  50% {
    box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.55);
    background-color: rgba(255, 237, 213, 0.65);
  }
}
</style>
