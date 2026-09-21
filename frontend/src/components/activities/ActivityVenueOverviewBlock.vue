<template>
  <div v-if="venueAddressId" class="activity-venue-overview span-2">
    <EventVenueDetailLocations
      v-if="venueAddress"
      ref="locationsRef"
      v-model:plan-visible="planVisible"
      :event-address="venueAddress"
      :child-addresses="childAddresses"
      :read-only="readOnly"
      :allow-children="true"
      :allow-extra-sites="Boolean(gaDepartmentId)"
      :allow-create-extra="allowCreateExtra"
      :allow-star-extra="gaMapMode === 'all'"
      :allow-poi-children="!gaDepartmentId"
      :extra-create-locked-hint="extraCreateLockedHint"
      :core-location-mode="Boolean(gaDepartmentId)"
      :lock-core-adds="gaMapMode === 'starred'"
      :has-storage-location="hasStorageLocation"
      :storage-summary="primaryStorageSummary"
      :storage-latitude="primaryStorageAddress?.latitude ?? null"
      :storage-longitude="primaryStorageAddress?.longitude ?? null"
      :show-location-search="false"
      :extra-sites="extraSites"
      :extra-editable-pin-id="extraEditablePinId"
      :extra-focus-pin-id="gaEditingId"
      :extra-place-hint="gaPlaceHint"
      :overlay="displayOverlay"
      :overlay-editable="overlayEditMode"
      :scroll-wheel-zoom="scrollWheelZoom"
      :scroll-wheel-zoom-require-ctrl="scrollWheelZoomRequireCtrl"
      :overview-hint="gaOverviewHint"
      :add-extra-button-label="extraAddLabel"
      location-kind="event"
      :hide-title="hideTitle"
      @edit-child="openChildEditModal"
      @create-child="openChildCreateModal"
      @create-storage="emit('create-storage')"
      @edit-storage="emit('edit-storage', primaryStorageAddress)"
      @edit-venue-details="openVenueDetailsModal"
      @venue-updated="handleVenueUpdated"
      @create-extra="startPlacingGa"
      @edit-extra="onEditExtra"
      @delete-extra="deleteGaPlaceById"
      @delete-child="deleteChildLocation"
      @delete-storage="deleteStorageLocation"
      @delete-venue="unlinkVenueLocation"
      @toggle-extra-star="toggleGaStar"
      @extra-pin-moved="onExtraPinMoved"
      @extra-map-click="onExtraMapClick"
      @polygon-change="onPolygonChange"
      @overlay-bounds-change="draftOverlayBounds = $event"
      @undo-polygon="undoInlinePolygonPoint"
      @redraw-polygon="resetInlinePolygon"
      @save-area="emit('save-area')"
      @open-linked-group="openLinkedGroupByPlaceId"
    />

    <div
      v-if="showSitePlanControls"
      class="ga-overlay-accordion"
      :class="{ 'ga-overlay-accordion--below-map': hasSitePlanImage }"
    >
      <button
        type="button"
        class="ga-overlay-accordion__toggle"
        :aria-expanded="overlayPanelExpanded"
        @click="overlayPanelExpanded = !overlayPanelExpanded"
      >
        <span class="ga-overlay-accordion__chevron" aria-hidden="true">
          {{ overlayPanelExpanded ? '▾' : '▸' }}
        </span>
        <span class="ga-overlay-accordion__label">{{ t('grossanlass.einstellungen.mapOverlayAccordionTitle') }}</span>
        <span class="ga-overlay-accordion__summary">{{ overlayAccordionSummary }}</span>
      </button>
      <div v-show="overlayPanelExpanded" class="ga-overlay-bar ga-overlay-accordion__body">
        <input
          ref="overlayFileEl"
          type="file"
          accept="image/*"
          class="ga-overlay-bar__file"
          :disabled="overlayBusy"
          @change="onOverlayFile"
        >
        <EButton
          variant="secondary"
          size="small"
          type="button"
          :loading="overlayBusy"
          @click="overlayFileEl?.click()"
        >
          {{ gaMap?.image_url ? t('grossanlass.einstellungen.mapReplace') : t('grossanlass.einstellungen.mapUpload') }}
        </EButton>
        <EButton
          v-if="gaMap?.image_url && (overlay || overlayEditMode)"
          variant="secondary"
          size="small"
          type="button"
          :class="{ 'ga-overlay-bar__btn-active': overlayEditMode }"
          @click="planVisible = !planVisible"
        >
          {{ planVisible ? t('grossanlass.einstellungen.mapHideOverlay') : t('grossanlass.einstellungen.mapShowOverlay') }}
        </EButton>
        <EButton
          v-if="gaMap?.image_url"
          variant="secondary"
          size="small"
          type="button"
          :loading="overlayBusy"
          :class="{ 'ga-overlay-bar__btn-active': overlayEditMode }"
          @click="onOverlayBoundsAction"
        >
          {{ overlayBoundsButtonLabel }}
        </EButton>
        <EButton
          v-if="gaMap?.image_url"
          variant="secondary"
          size="small"
          type="button"
          :loading="overlayBusy"
          @click="removeOverlayBackground"
        >
          {{ t('grossanlass.einstellungen.mapDelete') }}
        </EButton>
        <label v-if="gaMap?.image_url" class="ga-overlay-bar__opacity">
          <span class="ga-overlay-bar__opacity-label">
            {{ t('grossanlass.einstellungen.mapOverlayOpacity', { percent: overlayOpacityPercent }) }}
          </span>
          <input
            v-model.number="overlayOpacityPercent"
            type="range"
            min="30"
            max="100"
            step="1"
            class="ga-overlay-bar__opacity-range"
            :disabled="overlayBusy"
            :aria-label="t('grossanlass.einstellungen.mapOverlayOpacity', { percent: overlayOpacityPercent })"
            @input="queueSaveOverlayOpacity"
          >
          <span class="ga-overlay-bar__opacity-sub">{{ overlayOpacityTransparencyHint }}</span>
        </label>
        <p class="ga-overlay-bar__hint">{{ overlayHint }}</p>
      </div>
    </div>

    <p v-if="showJsHint" class="field-hint text-muted activity-venue-overview-js-hint">
      {{ t('activities.venueLocations.activityVenueHint') }}
    </p>

    <AddressModal
      v-if="showChildModal"
      :key="`${childModalDefaultType}-${childModalAddress?.id ?? 'new'}`"
      :department-id="departmentId"
      :address="childModalAddress"
      :default-type="childModalDefaultType"
      :parent-id="childModalParentId"
      :default-name="childModalDefaultName"
      :allowed-types="childModalAllowedTypes"
      :initial-latitude="childModalMapFocus?.latitude ?? null"
      :initial-longitude="childModalMapFocus?.longitude ?? null"
      :map-overlay="displayOverlay"
      @close="closeChildModal"
      @saved="handleChildSaved"
    />

    <EDialog v-if="!inlinePlaceDraft" v-model="gaDialogOpen" :title="t('grossanlass.einstellungen.mapPinTitle')" :max-width="420">
      <label class="ga-place-field">
        <span>{{ t('grossanlass.einstellungen.placesName') }}</span>
        <input v-model="gaDraftName" type="text">
      </label>
      <label class="ga-place-field">
        <span>{{ t('grossanlass.einstellungen.placesKind') }}</span>
        <select v-model="gaDraftKind" :disabled="!!gaEditingId && !gaCanChangeKind">
          <option v-for="kind in draftKindOptions" :key="kind" :value="kind">
            {{ t(`grossanlass.einstellungen.placesKind${kindLabelKey(kind)}`) }}
          </option>
        </select>
      </label>
      <label v-if="gaMapMode === 'all'" class="ga-place-field ga-place-field--star">
        <input v-model="gaDraftStarred" type="checkbox">
        <span>{{ t('grossanlass.einstellungen.placesStar') }}</span>
      </label>
      <p v-if="gaDraftKind === 'area' && gaDraftPolygon.length >= 3" class="ga-place-field__hint">
        {{ t('grossanlass.einstellungen.placesPolygonReady', { count: gaDraftPolygon.length }) }}
      </p>
      <p v-else-if="gaDraftKind === 'area'" class="ga-place-field__hint">
        {{ t('activities.venueLocations.extraAreaHint') }}
      </p>
      <p v-else-if="gaDraftLat != null" class="ga-place-field__hint">
        {{ gaDraftLat.toFixed(5) }}° N, {{ gaDraftLng?.toFixed(5) }}° E
      </p>
      <p v-else class="ga-place-field__hint">{{ t('activities.venueLocations.extraPlaceHint') }}</p>
      <p v-if="gaEditingId && !gaCanDelete && gaEditingPlace?.kind === 'area'" class="ga-place-field__hint">
        {{ t('grossanlass.einstellungen.placesLinkedArea') }}
      </p>
      <p v-else-if="gaEditingId && !gaCanDelete && gaEditingPlace?.group_id" class="ga-place-field__hint">
        {{ t('grossanlass.einstellungen.placesLinkedBauprojekt') }}
      </p>
      <template #actions>
        <div class="ga-place-dialog-actions">
          <div v-if="gaEditingId" class="ga-place-dialog-actions__left">
            <EButton
              v-if="gaDraftLat != null || gaEditingPlace?.latitude != null"
              variant="secondary"
              size="small"
              :loading="gaBusy"
              @click="resetGaPlacePosition"
            >
              {{ t('grossanlass.einstellungen.placesResetLocation') }}
            </EButton>
            <EButton
              v-if="gaCanDelete"
              variant="secondary"
              size="small"
              :loading="gaBusy"
              class="ga-place-dialog-actions__danger"
              @click="deleteGaPlace"
            >
              {{ t('common.delete') }}
            </EButton>
          </div>
          <div class="ga-place-dialog-actions__right">
            <EButton variant="secondary" @click="closeGaDialog">{{ t('common.cancel') }}</EButton>
            <EButton variant="primary" :loading="gaBusy" :disabled="!gaDraftName.trim()" @click="saveGaPlace">
              {{ t('grossanlass.einstellungen.mapPinSave') }}
            </EButton>
          </div>
        </div>
      </template>
    </EDialog>

    <EDialog
      v-model="linkedBereichOpen"
      :title="linkedBereichTitle"
      :max-width="720"
      :z-index="2600"
      scrollable
    >
      <ETextField
        v-model="linkedBereichForm.name"
        :label="linkedBereichNameLabel"
        hide-details="auto"
      />
      <ETextarea
        v-model="linkedBereichForm.description"
        :label="t('grossanlass.planung.ressorts.descriptionHeading')"
        :placeholder="t('grossanlass.planung.ressorts.descriptionPlaceholder')"
        rows="4"
        hide-details="auto"
      />
      <ESwitch
        v-model="linkedBereichForm.include_on_map"
        :label="t('grossanlass.planung.ressorts.includeOnMap')"
        :hint="t('grossanlass.planung.ressorts.includeOnMapHint')"
        persistent-hint
        hide-details="auto"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="linkedBereichOpen = false">
          {{ t('common.cancel') }}
        </EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!linkedBereichForm.name.trim() || linkedBereichSaving"
          :loading="linkedBereichSaving"
          @click="saveLinkedBereich"
        >
          {{ t('common.save') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="linkedProjectOpen"
      :title="linkedProjectTitle"
      :max-width="920"
      :z-index="2600"
      :retain-focus="false"
      scrollable
    >
      <GrossanlassBauprojektPanel
        v-if="linkedProjectGroup && props.gaDepartmentId"
        :department-id="props.gaDepartmentId"
        :group-id="linkedProjectGroup.id"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="linkedProjectOpen = false">
          {{ t('settings.groups.close') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { deleteAddress, getAddress, getAddresses, type Address } from '@/api/addresses'
import { updateGrossanlassPlanung } from '@/api/grossanlassPlanung'
import {
  createGrossanlassMap,
  createGrossanlassPlace,
  deleteGrossanlassMapBackground,
  deleteGrossanlassPlace,
  listGrossanlassMaps,
  listGrossanlassPlaces,
  updateGrossanlassMap,
  updateGrossanlassPlace,
  uploadGrossanlassMapBackground,
  type GaMap,
  type GaMapBounds,
  type GaPlace,
  type GaPlaceKind,
  type GaPolygonPoint,
} from '@/api/grossanlassLogistics'
import AddressModal from '@/components/AddressModal.vue'
import EventVenueDetailLocations, {
  type VenueExtraSite,
} from '@/components/contacts/EventVenueDetailLocations.vue'
import {
  getGrossanlassGroups,
  updateGrossanlassGroup,
  type GrossanlassGroup,
} from '@/api/grossanlassGroups'
import GrossanlassBauprojektPanel from '@/components/grossanlass/GrossanlassBauprojektPanel.vue'
import { EButton, EDialog, ESwitch, ETextField, ETextarea } from '@/components/form/base'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import {
  boundsAroundPoint,
  boundsWithAspectRatio,
  fitBoundsToAspectRatio,
  DRAFT_GA_PIN_ID,
  GA_MAP_OVERLAY_OPACITY_DEFAULT,
  gaMapOverlayBounds,
  gaPlaceColor,
  gaPlaceCreateKinds,
  gaPlaceKind,
  gaPlaceKindLabelKey,
  normalizeGaPolygon,
} from '@/utils/grossanlassGaMap'
import type { ActivityMapOverlay } from '@/components/activities/ActivityDualLocationMap.vue'
import { formatAddressSelectionLabel } from '@/utils/departmentAddressSearch'

const props = withDefaults(
  defineProps<{
    venueAddressId: string | null
    departmentId: string
    readOnly?: boolean
    showJsHint?: boolean
    /** Titel «Standorte» ausblenden (Feld heisst schon Eventstandort). */
    hideTitle?: boolean
    /** Grossanlass: GA-Orte und Geländeplan auf derselben Karte. */
    gaDepartmentId?: string | null
    /** starred = nur wichtige Orte (Stammdaten); all = alle inkl. Bauprojekt (Tab Standorte). */
    gaMapMode?: 'starred' | 'all'
    /** Entwurf auf der Karte ohne GA-Dialog (z. B. Bauprojekt-Modal). */
    inlinePlaceDraft?: boolean
    /** Name für Inline-Entwurf (z. B. aus Formularfeld). */
    draftPlaceName?: string
    /** Inline-Entwurf als Bereichs-Polygon statt Pin. */
    draftPlaceKind?: GaPlaceKind
  }>(),
  {
    readOnly: false,
    showJsHint: false,
    hideTitle: true,
    gaDepartmentId: null,
    gaMapMode: 'all',
    inlinePlaceDraft: false,
    draftPlaceName: '',
    draftPlaceKind: 'bauprojekt',
  },
)

const emit = defineEmits<{
  updated: []
  'core-ready': [payload: { delivery: boolean; storage: boolean }]
  'create-storage': []
  'edit-storage': [address: Address | null]
  'save-area': []
  'venue-cleared': []
}>()

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const locationsRef = ref<InstanceType<typeof EventVenueDetailLocations> | null>(null)
const overlayFileEl = ref<HTMLInputElement | null>(null)
const venueAddress = ref<Address | null>(null)
const childAddresses = ref<Address[]>([])
const gaPlaces = ref<GaPlace[]>([])
const gaGroups = ref<GrossanlassGroup[]>([])
const gaMap = ref<GaMap | null>(null)
const linkedBereichOpen = ref(false)
const linkedBereichSaving = ref(false)
const linkedBereichGroup = ref<GrossanlassGroup | null>(null)
const linkedBereichForm = ref({ name: '', description: '', include_on_map: false })
const linkedProjectOpen = ref(false)
const linkedProjectGroup = ref<GrossanlassGroup | null>(null)
const hasStorageLocation = ref(false)
const primaryStorageAddress = ref<Address | null>(null)
const primaryStorageSummary = ref('')

const showChildModal = ref(false)
const childModalAddress = ref<Address | null>(null)
const childModalIsVenueDetails = ref(false)
const childModalDefaultType = ref<string>('event_delivery')

const extraEditablePinId = ref<string | null>(null)
const gaDialogOpen = ref(false)
const gaBusy = ref(false)
const overlayBusy = ref(false)
const planVisible = ref(true)
const overlayEditMode = ref(false)
const draftOverlayBounds = ref<ActivityMapOverlay | null>(null)
const overlayPanelExpanded = ref(false)
const overlayOpacityPercent = ref(Math.round(GA_MAP_OVERLAY_OPACITY_DEFAULT * 100))
let overlayOpacityTimer: ReturnType<typeof setTimeout> | null = null
const gaEditingId = ref<string | null>(null)
const gaDraftName = ref('')
const gaDraftKind = ref<GaPlaceKind>('bauprojekt')
const gaDraftStarred = ref(false)
const gaDraftLat = ref<number | null>(null)
const gaDraftLng = ref<number | null>(null)
const gaDraftPolygon = ref<GaPolygonPoint[]>([])

const gaEditingPlace = computed(() => {
  if (!gaEditingId.value) return null
  return gaPlaces.value.find((row) => row.id === gaEditingId.value) ?? null
})

const gaCanChangeKind = computed(() => {
  if (!gaEditingId.value) return true
  return gaEditingPlace.value?.can_change_kind !== false
})

const draftKindOptions = computed(() => gaPlaceCreateKinds(gaDraftKind.value))

const gaCanDelete = computed(() => gaEditingPlace.value?.can_delete === true)

const hasDeliveryChild = computed(() => childAddresses.value.some((a) => a.type === 'event_delivery'))

function overlayOpacityValue(): number {
  return overlayOpacityPercent.value / 100
}

const overlayOpacityTransparencyHint = computed(() =>
  t('grossanlass.einstellungen.mapOverlayOpacityHint', {
    sichtbar: 100 - overlayOpacityPercent.value,
  }),
)

const overlay = computed(() => {
  const bounds = gaMapOverlayBounds(gaMap.value)
  if (!bounds || !gaMap.value?.image_url) return null
  return {
    url: gaMap.value.image_url,
    ...bounds,
    imageWidth: gaMap.value.image_width,
    imageHeight: gaMap.value.image_height,
    opacity: overlayOpacityValue(),
  }
})

function overlayDimensions() {
  return {
    imageWidth: gaMap.value?.image_width ?? 0,
    imageHeight: gaMap.value?.image_height ?? 0,
  }
}

function withOverlayMeta(bounds: GaMapBounds, url: string): ActivityMapOverlay {
  const dims = overlayDimensions()
  return { url, ...bounds, ...dims, opacity: overlayOpacityValue() }
}

/** Geländeplan nur unter Standorte, nicht auf der Stammdaten-Übersicht. */
const showSitePlan = computed(() => Boolean(props.gaDepartmentId) && props.gaMapMode === 'all')
const showSitePlanControls = computed(() => showSitePlan.value && !props.readOnly)
const hasSitePlanImage = computed(() => Boolean(gaMap.value?.image_url))
const scrollWheelZoom = computed(() => {
  if (props.gaMapMode === 'starred') return true
  if (props.readOnly) return false
  return showSitePlan.value
})
const scrollWheelZoomRequireCtrl = computed(() => props.gaMapMode === 'starred')

const displayOverlay = computed((): ActivityMapOverlay | null => {
  if (!showSitePlan.value) return null
  const url = gaMap.value?.image_url
  if (!url) return null
  const dims = overlayDimensions()
  const opacity = overlayOpacityValue()
  if (overlayEditMode.value && draftOverlayBounds.value) {
    return { ...draftOverlayBounds.value, url, ...dims, opacity }
  }
  return overlay.value
})

const overlayBoundsButtonLabel = computed(() => {
  if (overlayEditMode.value) return t('grossanlass.einstellungen.mapSaveBounds')
  if (!overlay.value) return t('grossanlass.einstellungen.mapFitBounds')
  return t('grossanlass.einstellungen.mapAdjustBounds')
})

const overlayHint = computed(() => {
  if (!gaMap.value?.image_url) return t('grossanlass.einstellungen.mapOverlayHint')
  if (overlayEditMode.value) return t('grossanlass.einstellungen.mapOverlayPlaceHint')
  if (!overlay.value) return t('grossanlass.einstellungen.mapFitBoundsHint')
  if (!planVisible.value) return t('grossanlass.einstellungen.mapOverlayHiddenHint')
  return t('grossanlass.einstellungen.mapOverlayAdjustHint')
})

const overlayAccordionSummary = computed(() => {
  if (!gaMap.value?.image_url) return t('grossanlass.einstellungen.mapOverlayAccordionEmpty')
  if (overlayEditMode.value) return t('grossanlass.einstellungen.mapOverlayAccordionEditing')
  if (!planVisible.value) return t('grossanlass.einstellungen.mapOverlayAccordionHidden')
  return t('grossanlass.einstellungen.mapOverlayAccordionActive', {
    percent: overlayOpacityPercent.value,
  })
})

const allowCreateExtra = computed(() => {
  if (!props.gaDepartmentId || props.readOnly) return false
  if (props.gaMapMode === 'starred') return false
  return hasDeliveryChild.value && hasStorageLocation.value
})

const extraCreateLockedHint = computed(() => {
  if (!props.gaDepartmentId || props.readOnly || allowCreateExtra.value) return ''
  const missing: string[] = []
  if (!hasDeliveryChild.value) missing.push(t('grossanlass.einstellungen.coreStepDelivery'))
  if (!hasStorageLocation.value) missing.push(t('grossanlass.einstellungen.coreStepStorage'))
  return t('activities.venueLocations.gaNeedCoreLocations', { missing: missing.join(', ') })
})

const extraAddLabel = computed(() =>
  props.gaMapMode === 'starred'
    ? t('activities.venueLocations.addStarredGaPlaceButton')
    : t('activities.venueLocations.addExtraGaPlaceButton'),
)

const gaOverviewHint = computed(() => {
  if (!props.gaDepartmentId) return ''
  return props.gaMapMode === 'starred'
    ? t('activities.venueLocations.gaStarredHint')
    : t('activities.venueLocations.gaOverviewHint')
})

function groupForPlace(place: GaPlace | null | undefined): GrossanlassGroup | null {
  if (!place?.group_id) return null
  return gaGroups.value.find((row) => row.id === place.group_id) ?? null
}

function groupLinkKindForPlace(place: GaPlace | null | undefined): 'bereich' | 'bauprojekt' | null {
  if (!place?.group_id) return null
  const group = groupForPlace(place)
  if (group) return group.node_type === 'bauprojekt' ? 'bauprojekt' : 'bereich'
  return gaPlaceKind(place.kind) === 'bauprojekt' ? 'bauprojekt' : 'bereich'
}

const extraSites = computed((): VenueExtraSite[] => {
  const source =
    props.gaMapMode === 'starred'
      ? gaPlaces.value.filter((place) => place.starred)
      : gaPlaces.value
  const rows: VenueExtraSite[] = source.map((place) => {
    const kind = gaPlaceKind(place.kind)
    const color = gaPlaceColor(kind)
    const isEditing = extraEditablePinId.value === place.id
    const polygon = isEditing ? gaDraftPolygon.value : normalizeGaPolygon(place.polygon)
    const isArea = kind === 'area'
    const lat = isEditing && !isArea ? gaDraftLat.value : place.latitude
    const lng = isEditing && !isArea ? gaDraftLng.value : place.longitude
    return {
      id: place.id,
      label: place.name,
      summary: t(`grossanlass.einstellungen.placesKind${kindLabelKey(kind)}`),
      hint: isArea
        ? t('activities.venueLocations.extraAreaHint')
        : t('activities.venueLocations.extraGaHint'),
      color,
      qrUrl: place.qr_url,
      starred: place.starred === true,
      detailOnly: place.starred !== true,
      canDelete: place.can_delete === true,
      groupLinkKind: groupLinkKindForPlace(place),
      polygon: isArea ? polygon : null,
      pin:
        !isArea && lat != null && lng != null
          ? {
              id: place.id,
              label: place.name,
              latitude: lat,
              longitude: lng,
              variant: 'poi',
              color,
              detailOnly: place.starred !== true,
            }
          : null,
    }
  })
  if (extraEditablePinId.value === DRAFT_GA_PIN_ID) {
    const color = gaPlaceColor(gaDraftKind.value)
    const label = gaDraftName.value.trim() || t('grossanlass.einstellungen.mapPinTitle')
    const isArea = gaDraftKind.value === 'area'
    rows.push({
      id: DRAFT_GA_PIN_ID,
      label,
      color,
      summary: t(`grossanlass.einstellungen.placesKind${kindLabelKey(gaDraftKind.value)}`),
      hint: isArea
        ? t('activities.venueLocations.extraAreaHint')
        : t('activities.venueLocations.extraGaHint'),
      starred: gaDraftStarred.value,
      detailOnly: false,
      polygon: isArea ? gaDraftPolygon.value : null,
      pin:
        !isArea && gaDraftLat.value != null && gaDraftLng.value != null
          ? {
              id: DRAFT_GA_PIN_ID,
              label,
              latitude: gaDraftLat.value,
              longitude: gaDraftLng.value,
              variant: 'poi',
              color,
            }
          : null,
    })
  }
  return rows
})

const gaPlaceHint = computed(() => {
  if (gaDraftKind.value === 'area' || gaEditingPlace.value?.kind === 'area') {
    return t('activities.venueLocations.extraAreaHint')
  }
  return ''
})

const childModalAllowedTypes = computed(() => {
  if (childModalIsVenueDetails.value) {
    return [venueAddress.value?.type === 'meeting' ? 'meeting' : 'event']
  }
  if (childModalAddress.value) return [childModalAddress.value.type]
  if (props.gaDepartmentId) return ['event_delivery']
  return hasDeliveryChild.value ? ['event_poi'] : ['event_delivery']
})

const childModalParentId = computed(() => {
  if (childModalIsVenueDetails.value) return null
  return venueAddress.value?.id ?? null
})

const childModalMapFocus = computed(() => {
  if (childModalIsVenueDetails.value) return null
  const lat = venueAddress.value?.latitude
  const lng = venueAddress.value?.longitude
  if (lat == null || lng == null) return null
  return { latitude: lat, longitude: lng }
})

const childModalDefaultName = computed(() => {
  if (childModalIsVenueDetails.value) return ''
  if (childModalDefaultType.value === 'event_poi') return ''
  if (childModalDefaultType.value !== 'event_delivery') return ''
  const base = venueAddress.value?.name || venueAddress.value?.company || ''
  return base ? `${base} – Zustellung` : ''
})

function kindLabelKey(kind: GaPlaceKind): 'Bauprojekt' | 'Unterlager' | 'Matplatz' | 'Anfahrt' | 'Poi' | 'Area' {
  return gaPlaceKindLabelKey(kind)
}

const linkedBereichTitle = computed(() =>
  linkedBereichGroup.value
    ? t('grossanlass.meinRessort.editTitle', { name: linkedBereichGroup.value.name })
    : t('activities.venueLocations.openLinkedBereich'),
)

const linkedProjectTitle = computed(() =>
  linkedProjectGroup.value
    ? t('grossanlass.planung.ressorts.projectTitle', { name: linkedProjectGroup.value.name })
    : t('activities.venueLocations.openLinkedBauprojekt'),
)

const linkedBereichNameLabel = computed(() =>
  linkedBereichGroup.value && !linkedBereichGroup.value.parent_id
    ? t('grossanlass.planung.ressorts.nameLabelRessort')
    : t('grossanlass.planung.ressorts.nameLabelUnterressort'),
)

function fillLinkedBereichForm(group: GrossanlassGroup) {
  linkedBereichForm.value = {
    name: group.name,
    description: group.description || '',
    include_on_map: group.include_on_map === true || group.place?.kind === 'area',
  }
}

async function ensureGaGroups(): Promise<GrossanlassGroup[]> {
  const departmentId = props.gaDepartmentId
  if (!departmentId) return []
  if (gaGroups.value.length) return gaGroups.value
  try {
    gaGroups.value = await getGrossanlassGroups(departmentId)
  } catch {
    gaGroups.value = []
  }
  return gaGroups.value
}

async function openLinkedGroupByPlaceId(placeId: string) {
  const place = gaPlaces.value.find((row) => row.id === placeId)
  if (!place?.group_id) return
  await ensureGaGroups()
  const group = groupForPlace(place)
  const kind = groupLinkKindForPlace(place)
  if (kind === 'bauprojekt') {
    linkedProjectGroup.value = group ?? ({ id: place.group_id, name: place.name } as GrossanlassGroup)
    linkedProjectOpen.value = true
    return
  }
  if (!group) {
    toast.error(t('activities.venueLocations.linkedGroupMissing'))
    return
  }
  linkedBereichGroup.value = group
  fillLinkedBereichForm(group)
  linkedBereichOpen.value = true
}

async function saveLinkedBereich() {
  const departmentId = props.gaDepartmentId
  const group = linkedBereichGroup.value
  if (!departmentId || !group || !linkedBereichForm.value.name.trim() || linkedBereichSaving.value) {
    return
  }
  linkedBereichSaving.value = true
  try {
    const saved = await updateGrossanlassGroup(departmentId, group.id, {
      name: linkedBereichForm.value.name.trim(),
      description: linkedBereichForm.value.description.trim() || null,
      include_on_map: linkedBereichForm.value.include_on_map,
    })
    gaGroups.value = gaGroups.value.map((row) => (row.id === saved.id ? { ...row, ...saved } : row))
    linkedBereichGroup.value = gaGroups.value.find((row) => row.id === saved.id) ?? saved
    fillLinkedBereichForm(linkedBereichGroup.value)
    toast.success(t('grossanlass.meinRessort.ressortUpdated'))
    await loadGa()
    emit('updated')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    linkedBereichSaving.value = false
  }
}

async function loadVenue() {
  const id = props.venueAddressId
  if (!id) {
    venueAddress.value = null
    childAddresses.value = []
    return
  }
  try {
    const data = await getAddress(id)
    venueAddress.value = data.address
    childAddresses.value = data.child_addresses ?? []
    await nextTick()
    locationsRef.value?.refreshMaps()
  } catch {
    venueAddress.value = null
    childAddresses.value = []
  }
}

async function loadStorage() {
  const departmentId = props.gaDepartmentId || props.departmentId
  if (!props.gaDepartmentId || !departmentId) {
    hasStorageLocation.value = false
    primaryStorageAddress.value = null
    primaryStorageSummary.value = ''
    return
  }
  try {
    const { addresses } = await getAddresses(departmentId, 'storage')
    const active = addresses.filter((row) => !row.deleted_at)
    hasStorageLocation.value = active.length > 0
    const primary = active.find((row) => row.is_primary) ?? active[0] ?? null
    primaryStorageAddress.value = primary
    primaryStorageSummary.value = primary ? formatAddressSelectionLabel(primary) : ''
  } catch {
    hasStorageLocation.value = false
    primaryStorageAddress.value = null
    primaryStorageSummary.value = ''
  }
}

async function loadGa() {
  const departmentId = props.gaDepartmentId
  if (!departmentId) {
    gaPlaces.value = []
    gaGroups.value = []
    gaMap.value = null
    return
  }
  try {
    const [places, maps, groups] = await Promise.all([
      listGrossanlassPlaces(departmentId),
      listGrossanlassMaps(departmentId),
      getGrossanlassGroups(departmentId).catch(() => [] as GrossanlassGroup[]),
    ])
    gaPlaces.value = places
    gaGroups.value = groups
    gaMap.value = maps[0] ?? null
  } catch {
    gaPlaces.value = []
    gaGroups.value = []
    gaMap.value = null
  }
}

async function softReloadMapUi() {
  await Promise.all([loadGa(), loadVenue(), loadStorage()])
  await nextTick()
  locationsRef.value?.refreshMaps()
  emit('updated')
}

function startPlacingGa() {
  if (!allowCreateExtra.value) return
  extraEditablePinId.value = DRAFT_GA_PIN_ID
  gaEditingId.value = null
  gaDraftName.value = ''
  gaDraftKind.value = props.gaMapMode === 'starred' ? 'anfahrt' : 'bauprojekt'
  gaDraftStarred.value = props.gaMapMode === 'starred'
  gaDraftLat.value = null
  gaDraftLng.value = null
  gaDraftPolygon.value = []
  gaDialogOpen.value = true
  const lat = venueAddress.value?.latitude
  const lng = venueAddress.value?.longitude
  if (lat != null && lng != null) {
    void nextTick(() => locationsRef.value?.fitOverlayBounds?.(boundsAroundPoint(lat, lng, 0.004)))
  }
}

function openGaPlaceDialog(id: string) {
  if (id === DRAFT_GA_PIN_ID) {
    extraEditablePinId.value = DRAFT_GA_PIN_ID
    gaDialogOpen.value = true
    return
  }
  const place = gaPlaces.value.find((row) => row.id === id)
  if (!place) return
  if (place.latitude == null && place.longitude == null && !props.inlinePlaceDraft) {
    beginEditPlace(id)
    return
  }
  extraEditablePinId.value = id
  gaEditingId.value = id
  gaDraftName.value = place.name
  gaDraftKind.value = gaPlaceKind(place.kind)
  gaDraftStarred.value = place.starred === true
  gaDraftLat.value = place.latitude ?? null
  gaDraftLng.value = place.longitude ?? null
  gaDraftPolygon.value = normalizeGaPolygon(place.polygon)
  gaDialogOpen.value = true
}

function closeGaDialog() {
  gaDialogOpen.value = false
  extraEditablePinId.value = null
  gaEditingId.value = null
}

async function saveGaPlace() {
  const name = gaDraftName.value.trim()
  const departmentId = props.gaDepartmentId
  if (!name || !departmentId) return
  const starred = props.gaMapMode === 'starred' ? true : gaDraftStarred.value
  gaBusy.value = true
  try {
    if (gaEditingId.value) {
      const saved = await updateGrossanlassPlace(departmentId, gaEditingId.value, {
        name,
        kind: gaCanChangeKind.value ? gaDraftKind.value : undefined,
        latitude: gaDraftKind.value === 'area' ? undefined : gaDraftLat.value,
        longitude: gaDraftKind.value === 'area' ? undefined : gaDraftLng.value,
        polygon: gaDraftKind.value === 'area' ? gaDraftPolygon.value : undefined,
        starred,
      })
      gaPlaces.value = gaPlaces.value.map((row) => (row.id === saved.id ? saved : row))
    } else {
      const created = await createGrossanlassPlace(departmentId, {
        name,
        kind: gaDraftKind.value,
        latitude: gaDraftKind.value === 'area' ? undefined : gaDraftLat.value,
        longitude: gaDraftKind.value === 'area' ? undefined : gaDraftLng.value,
        polygon: gaDraftKind.value === 'area' ? gaDraftPolygon.value : undefined,
        starred,
      })
      gaPlaces.value = [...gaPlaces.value, created].sort((a, b) => a.name.localeCompare(b.name, 'de'))
    }
    closeGaDialog()
    emit('updated')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.placesAddError'))
  } finally {
    gaBusy.value = false
  }
}

async function resetGaPlacePosition() {
  const id = gaEditingId.value
  const departmentId = props.gaDepartmentId
  if (!id || !departmentId) return
  gaBusy.value = true
  try {
    const saved = await updateGrossanlassPlace(departmentId, id, {
      latitude: null,
      longitude: null,
      map_x: null,
      map_y: null,
    })
    gaDraftLat.value = null
    gaDraftLng.value = null
    gaPlaces.value = gaPlaces.value.map((row) => (row.id === saved.id ? saved : row))
    extraEditablePinId.value = id
    gaDialogOpen.value = false
    toast.success(t('grossanlass.einstellungen.placesResetLocationDone'))
    emit('updated')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.mapMoveError'))
  } finally {
    gaBusy.value = false
  }
}

async function deleteGaPlace() {
  return deleteGaPlaceById(gaEditingId.value)
}

async function deleteGaPlaceById(id: string | null) {
  const departmentId = props.gaDepartmentId
  const place = gaPlaces.value.find((row) => row.id === id) ?? (gaEditingId.value === id ? gaEditingPlace.value : null)
  if (!id || !departmentId || !place?.can_delete) return
  const ok = await confirm.confirm({
    title: t('grossanlass.einstellungen.placesDeleteConfirmTitle'),
    message: t('grossanlass.einstellungen.placesDeleteConfirmMessage', { name: place.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  gaBusy.value = true
  try {
    await deleteGrossanlassPlace(departmentId, id)
    if (gaEditingId.value === id) closeGaDialog()
    await softReloadMapUi()
    toast.success(t('grossanlass.einstellungen.placesDeleted'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.placesDeleteError'))
  } finally {
    gaBusy.value = false
  }
}

async function deleteChildLocation(address: Address) {
  const isDelivery = address.type === 'event_delivery'
  const ok = await confirm.confirm({
    title: t(
      isDelivery
        ? 'activities.venueLocations.deleteDeliveryTitle'
        : 'activities.venueLocations.deletePoiTitle',
    ),
    message: t(
      isDelivery
        ? 'activities.venueLocations.deleteDeliveryMessage'
        : 'activities.venueLocations.deletePoiMessage',
      { name: address.name || address.full_address || address.city || '—' },
    ),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteAddress(address.id)
    await loadVenue()
    emit('updated')
    toast.success(t('activities.venueLocations.deleteSiteDone'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('activities.venueLocations.deleteSiteError'))
  }
}

async function deleteStorageLocation() {
  const address = primaryStorageAddress.value
  if (!address) return
  const ok = await confirm.confirm({
    title: t('activities.venueLocations.deleteStorageTitle'),
    message: t('activities.venueLocations.deleteStorageMessage', {
      name: primaryStorageSummary.value || address.name || '—',
    }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteAddress(address.id)
    await loadStorage()
    emit('updated')
    toast.success(t('activities.venueLocations.deleteSiteDone'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('activities.venueLocations.deleteSiteError'))
  }
}

async function unlinkVenueLocation() {
  const departmentId = props.gaDepartmentId
  const name = venueAddress.value?.name || venueAddress.value?.company || '—'
  if (!departmentId) return
  const ok = await confirm.confirm({
    title: t('activities.venueLocations.deleteVenueTitle'),
    message: t('activities.venueLocations.deleteVenueMessage', { name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await updateGrossanlassPlanung(departmentId, { venue_address_id: null })
    venueAddress.value = null
    childAddresses.value = []
    emit('updated')
    emit('venue-cleared')
    toast.success(t('activities.venueLocations.deleteSiteDone'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('activities.venueLocations.deleteSiteError'))
  }
}

async function toggleGaStar(id: string) {
  if (id === DRAFT_GA_PIN_ID) return
  const departmentId = props.gaDepartmentId
  const place = gaPlaces.value.find((row) => row.id === id)
  if (!departmentId || !place) return
  const next = !place.starred
  try {
    const saved = await updateGrossanlassPlace(departmentId, id, { starred: next })
    gaPlaces.value = gaPlaces.value.map((row) => (row.id === saved.id ? saved : row))
    emit('updated')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.placesStarError'))
  }
}

async function savePlacePosition(id: string, latitude: number, longitude: number) {
  const departmentId = props.gaDepartmentId
  if (!departmentId || id === DRAFT_GA_PIN_ID) {
    gaDraftLat.value = latitude
    gaDraftLng.value = longitude
    return
  }
  try {
    const saved = await updateGrossanlassPlace(departmentId, id, { latitude, longitude })
    gaPlaces.value = gaPlaces.value.map((row) => (row.id === saved.id ? saved : row))
    if (gaEditingId.value === id) {
      gaDraftLat.value = latitude
      gaDraftLng.value = longitude
    }
  } catch {
    toast.error(t('grossanlass.einstellungen.mapMoveError'))
  }
}

function onExtraPinMoved(payload: { id: string; latitude: number; longitude: number }) {
  void savePlacePosition(payload.id, payload.latitude, payload.longitude)
}

function onExtraMapClick(payload: { id: string; latitude: number; longitude: number }) {
  if (payload.id === DRAFT_GA_PIN_ID) {
    gaDraftLat.value = payload.latitude
    gaDraftLng.value = payload.longitude
    if (props.inlinePlaceDraft) return
    if (!gaDialogOpen.value) {
      gaDraftName.value = gaDraftName.value || ''
      gaDraftKind.value = 'bauprojekt'
      gaEditingId.value = null
      gaDialogOpen.value = true
    }
    return
  }
  void savePlacePosition(payload.id, payload.latitude, payload.longitude)
}

function onPolygonChange(payload: { id: string; points: GaPolygonPoint[] }) {
  gaDraftPolygon.value = payload.points
  if (payload.id === DRAFT_GA_PIN_ID || props.inlinePlaceDraft) return
  const departmentId = props.gaDepartmentId
  if (!departmentId || payload.points.length < 3) return
  void updateGrossanlassPlace(departmentId, payload.id, { polygon: payload.points })
    .then((saved) => {
      gaPlaces.value = gaPlaces.value.map((row) => (row.id === saved.id ? saved : row))
    })
    .catch(() => {
      toast.error(t('grossanlass.einstellungen.mapMoveError'))
    })
}

function beginInlineDraft(name = '', kind: GaPlaceKind = 'bauprojekt') {
  extraEditablePinId.value = DRAFT_GA_PIN_ID
  gaEditingId.value = null
  gaDraftName.value = name.trim()
  gaDraftKind.value = kind
  gaDraftStarred.value = props.gaMapMode === 'starred'
  gaDraftLat.value = null
  gaDraftLng.value = null
  gaDraftPolygon.value = []
  gaDialogOpen.value = false
  const lat = venueAddress.value?.latitude
  const lng = venueAddress.value?.longitude
  if (lat != null && lng != null) {
    void nextTick(() => locationsRef.value?.fitOverlayBounds?.(boundsAroundPoint(lat, lng, 0.004)))
  }
}

function beginEditPlace(placeId: string) {
  const place = gaPlaces.value.find((row) => row.id === placeId)
  if (!place) return
  extraEditablePinId.value = placeId
  gaEditingId.value = placeId
  gaDraftName.value = place.name
  gaDraftKind.value = gaPlaceKind(place.kind)
  gaDraftStarred.value = place.starred === true
  gaDraftLat.value = place.latitude ?? null
  gaDraftLng.value = place.longitude ?? null
  gaDraftPolygon.value = normalizeGaPolygon(place.polygon)
  gaDialogOpen.value = false
  const lat = place.latitude ?? venueAddress.value?.latitude
  const lng = place.longitude ?? venueAddress.value?.longitude
  if (lat != null && lng != null) {
    void nextTick(() => locationsRef.value?.fitOverlayBounds?.(boundsAroundPoint(lat, lng, 0.004)))
  }
}

function onEditExtra(id: string) {
  if (props.inlinePlaceDraft) {
    beginEditPlace(id)
    return
  }
  openGaPlaceDialog(id)
}

function finishInlineDraw(placeId?: string | null) {
  extraEditablePinId.value = null
  gaDialogOpen.value = false
  gaDraftLat.value = null
  gaDraftLng.value = null
  if (!placeId) {
    gaEditingId.value = null
    gaDraftPolygon.value = []
    return
  }
  gaEditingId.value = placeId
  const place = gaPlaces.value.find((row) => row.id === placeId)
  if (place) {
    gaDraftName.value = place.name
    gaDraftKind.value = gaPlaceKind(place.kind)
    gaDraftStarred.value = place.starred === true
    gaDraftPolygon.value = normalizeGaPolygon(place.polygon)
  }
  void nextTick(() => locationsRef.value?.expandSite?.(placeId))
}

function getInlineDraftCoords(): { latitude: number | null; longitude: number | null } {
  return { latitude: gaDraftLat.value, longitude: gaDraftLng.value }
}

function getInlineDraftPolygon(): GaPolygonPoint[] {
  return [...gaDraftPolygon.value]
}

function resetInlinePolygon() {
  gaDraftPolygon.value = []
}

function undoInlinePolygonPoint() {
  gaDraftPolygon.value = gaDraftPolygon.value.slice(0, -1)
}

function clearInlineDraft() {
  extraEditablePinId.value = null
  gaEditingId.value = null
  gaDraftLat.value = null
  gaDraftLng.value = null
  gaDraftPolygon.value = []
  gaDialogOpen.value = false
}

function currentMapBounds() {
  return locationsRef.value?.getBounds() ?? null
}

async function ensureGaMap(): Promise<GaMap | null> {
  const departmentId = props.gaDepartmentId
  if (!departmentId) return null
  if (gaMap.value) return gaMap.value
  const created = await createGrossanlassMap(departmentId, {
    name: t('grossanlass.einstellungen.mapDefaultName'),
  })
  gaMap.value = created
  return created
}

function defaultOverlayBounds(): GaMapBounds | null {
  const lat = venueAddress.value?.latitude
  const lng = venueAddress.value?.longitude
  const w = gaMap.value?.image_width ?? 0
  const h = gaMap.value?.image_height ?? 0
  if (lat != null && lng != null) {
    const withAspect = boundsWithAspectRatio(lat, lng, w, h)
    if (withAspect) return withAspect
    return boundsAroundPoint(lat, lng)
  }
  const viewport = currentMapBounds()
  if (!viewport) return null
  if (w > 0 && h > 0) {
    const centerLat = (viewport.north + viewport.south) / 2
    const centerLng = (viewport.east + viewport.west) / 2
    const latSpan = viewport.north - viewport.south
    const withAspect = boundsWithAspectRatio(centerLat, centerLng, w, h, latSpan)
    if (withAspect) return withAspect
  }
  return viewport
}

function startOverlayEdit() {
  const url = gaMap.value?.image_url
  if (!url) return
  const saved = gaMapOverlayBounds(gaMap.value)
  let nextBounds = saved ?? defaultOverlayBounds()
  if (!nextBounds) return
  const w = gaMap.value?.image_width ?? 0
  const h = gaMap.value?.image_height ?? 0
  if (w > 0 && h > 0) {
    const corrected = fitBoundsToAspectRatio(nextBounds, w, h)
    if (corrected) nextBounds = corrected
  }
  planVisible.value = true
  draftOverlayBounds.value = withOverlayMeta(nextBounds, url)
  overlayEditMode.value = true
  overlayPanelExpanded.value = true
  void nextTick(() => {
    locationsRef.value?.refreshMaps()
    locationsRef.value?.fitOverlayBounds(nextBounds)
    locationsRef.value?.refreshOverlayEditUi()
  })
}

function onOverlayBoundsAction() {
  if (overlayEditMode.value) {
    void rememberOverlayBounds()
    return
  }
  startOverlayEdit()
}

async function onOverlayFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  const departmentId = props.gaDepartmentId
  if (!file || !departmentId) return
  overlayBusy.value = true
  try {
    const current = await ensureGaMap()
    if (!current) return
    gaMap.value = await uploadGrossanlassMapBackground(
      departmentId,
      current.id,
      file,
    )
    planVisible.value = true
    await loadGa()
    startOverlayEdit()
    toast.success(t('grossanlass.einstellungen.mapUploaded'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.mapUploadError'))
  } finally {
    overlayBusy.value = false
  }
}

async function rememberOverlayBounds() {
  const departmentId = props.gaDepartmentId
  const current = gaMap.value
  const bounds = locationsRef.value?.getOverlayBounds()
  if (!departmentId || !current || !bounds) {
    toast.error(t('grossanlass.einstellungen.mapFitBoundsError'))
    return
  }
  overlayBusy.value = true
  try {
    gaMap.value = await updateGrossanlassMap(departmentId, current.id, bounds)
    overlayEditMode.value = false
    draftOverlayBounds.value = null
    await loadGa()
    toast.success(t('grossanlass.einstellungen.mapFitted'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.mapFitBoundsError'))
  } finally {
    overlayBusy.value = false
  }
}

function queueSaveOverlayOpacity() {
  if (overlayOpacityTimer) clearTimeout(overlayOpacityTimer)
  overlayOpacityTimer = setTimeout(() => void saveOverlayOpacity(), 350)
}

async function saveOverlayOpacity() {
  const departmentId = props.gaDepartmentId
  const current = gaMap.value
  if (!departmentId || !current?.id) return
  const next = overlayOpacityPercent.value / 100
  const saved = current.overlay_opacity ?? GA_MAP_OVERLAY_OPACITY_DEFAULT
  if (Math.abs(saved - next) < 0.005) return
  overlayBusy.value = true
  try {
    gaMap.value = await updateGrossanlassMap(departmentId, current.id, { overlay_opacity: next })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.mapOverlayOpacityError'))
    overlayOpacityPercent.value = Math.round(saved * 100)
  } finally {
    overlayBusy.value = false
  }
}

async function removeOverlayBackground() {
  const departmentId = props.gaDepartmentId
  const current = gaMap.value
  if (!departmentId || !current?.image_url) return
  const ok = await confirm.confirm({
    title: t('grossanlass.einstellungen.mapDeleteConfirmTitle'),
    message: t('grossanlass.einstellungen.mapDeleteConfirmMessage'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  overlayBusy.value = true
  try {
    gaMap.value = await deleteGrossanlassMapBackground(departmentId, current.id)
    overlayEditMode.value = false
    draftOverlayBounds.value = null
    planVisible.value = true
    overlayPanelExpanded.value = true
    await softReloadMapUi()
    toast.success(t('grossanlass.einstellungen.mapDeleted'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.mapDeleteError'))
  } finally {
    overlayBusy.value = false
  }
}

function openChildCreateModal() {
  extraEditablePinId.value = null
  childModalIsVenueDetails.value = false
  childModalAddress.value = null
  childModalDefaultType.value = hasDeliveryChild.value ? 'event_poi' : 'event_delivery'
  showChildModal.value = true
}

function openChildEditModal(address: Address) {
  extraEditablePinId.value = null
  childModalIsVenueDetails.value = false
  childModalAddress.value = address
  childModalDefaultType.value = address.type === 'event_poi' ? 'event_poi' : 'event_delivery'
  showChildModal.value = true
}

function openVenueDetailsModal() {
  if (!venueAddress.value) return
  extraEditablePinId.value = null
  childModalIsVenueDetails.value = true
  childModalAddress.value = venueAddress.value
  childModalDefaultType.value = venueAddress.value.type === 'meeting' ? 'meeting' : 'event'
  showChildModal.value = true
}

function closeChildModal() {
  showChildModal.value = false
  childModalAddress.value = null
  childModalIsVenueDetails.value = false
}

async function handleVenueUpdated(address: Address) {
  venueAddress.value = address
  emit('updated')
  await nextTick()
  locationsRef.value?.refreshMaps()
}

async function handleChildSaved(saved?: Address) {
  const wasVenueDetails = childModalIsVenueDetails.value
  closeChildModal()
  if (wasVenueDetails && saved) {
    venueAddress.value = saved
  }
  await loadVenue()
  emit('updated')
}

watch(
  () => gaMap.value?.overlay_opacity,
  (value) => {
    overlayOpacityPercent.value = Math.round((value ?? GA_MAP_OVERLAY_OPACITY_DEFAULT) * 100)
  },
  { immediate: true },
)

watch(
  () => gaMap.value?.image_url,
  (url, previous) => {
    if (overlayEditMode.value) return
    if (url) {
      overlayPanelExpanded.value = false
      return
    }
    overlayPanelExpanded.value = true
    if (previous) return
  },
  { immediate: true },
)

watch(overlayEditMode, (active) => {
  if (active) {
    overlayPanelExpanded.value = true
    return
  }
  if (hasSitePlanImage.value) overlayPanelExpanded.value = false
})

watch(() => props.venueAddressId, () => void loadVenue(), { immediate: true })
watch(
  () => props.gaDepartmentId,
  () => {
    void loadGa()
    void loadStorage()
  },
  { immediate: true },
)
watch(
  [hasDeliveryChild, hasStorageLocation],
  () => {
    emit('core-ready', {
      delivery: hasDeliveryChild.value,
      storage: hasStorageLocation.value,
    })
  },
  { immediate: true },
)
watch(
  () => props.draftPlaceName,
  (name) => {
    if (!props.inlinePlaceDraft || extraEditablePinId.value !== DRAFT_GA_PIN_ID) return
    gaDraftName.value = String(name ?? '').trim()
  },
)

defineExpose({
  reload: loadVenue,
  reloadGa: loadGa,
  reloadStorage: loadStorage,
  softReload: softReloadMapUi,
  beginInlineDraft,
  beginEditPlace,
  finishInlineDraw,
  getInlineDraftCoords,
  getInlineDraftPolygon,
  resetInlinePolygon,
  undoInlinePolygonPoint,
  clearInlineDraft,
  refreshMaps: () => locationsRef.value?.refreshMaps(),
  openGaPlaceDialog,
  openLinkedGroupByPlaceId,
  canCreateGaPlaces: allowCreateExtra,
  hasDelivery: hasDeliveryChild,
  hasStorage: hasStorageLocation,
  getSitePlanOverlay: () => displayOverlay.value,
})
</script>

<style scoped>
.activity-venue-overview {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
}

.activity-venue-overview-js-hint {
  margin: 0;
}

.ga-overlay-accordion {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px 12px;
  background: #fff;
}

.ga-overlay-accordion--below-map {
  margin-top: 4px;
}

.ga-overlay-accordion__toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 0;
  border: none;
  background: none;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #0f172a;
  cursor: pointer;
  text-align: left;
}

.ga-overlay-accordion__chevron {
  flex-shrink: 0;
  width: 1rem;
  color: #64748b;
}

.ga-overlay-accordion__label {
  flex-shrink: 0;
}

.ga-overlay-accordion__summary {
  margin-left: auto;
  font-size: 0.8rem;
  font-weight: 400;
  color: #64748b;
  text-align: right;
}

.ga-overlay-accordion__body {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;
}

.ga-overlay-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
}

.ga-overlay-bar__file {
  display: none;
}

.ga-overlay-bar__hint {
  margin: 0;
  flex: 1 1 220px;
  color: #64748b;
  font-size: 0.8rem;
}

.ga-overlay-bar__btn-active {
  border-color: #059669 !important;
  background: #ecfdf5 !important;
  color: #047857 !important;
}

.ga-overlay-bar__opacity {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1 1 220px;
  min-width: 180px;
  max-width: 320px;
}

.ga-overlay-bar__opacity-label {
  font-size: 0.8rem;
  color: #475569;
}

.ga-overlay-bar__opacity-range {
  width: 100%;
}

.ga-overlay-bar__opacity-sub {
  font-size: 0.75rem;
  color: #64748b;
}

.ga-place-field {
  display: grid;
  gap: 4px;
  margin-bottom: 12px;
  font-size: 0.85rem;
}

.ga-place-field input,
.ga-place-field select {
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
}

.ga-place-field--star {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ga-place-field--star input {
  width: auto;
}

.ga-place-field__hint {
  margin: 0 0 8px;
  color: #64748b;
  font-size: 0.8rem;
}

.ga-place-dialog-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
}

.ga-place-dialog-actions__left,
.ga-place-dialog-actions__right {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.ga-place-dialog-actions__danger {
  color: #b91c1c !important;
  border-color: #fecaca !important;
}
</style>
