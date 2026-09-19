<template>
  <div v-if="venueAddressId" class="activity-venue-overview span-2">
    <div v-if="gaDepartmentId && gaMapMode === 'all' && !readOnly" class="ga-overlay-bar">
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
        v-if="gaMap?.image_url && !overlay"
        variant="secondary"
        size="small"
        type="button"
        :loading="overlayBusy"
        @click="rememberOverlayBounds"
      >
        {{ t('grossanlass.einstellungen.mapFitBounds') }}
      </EButton>
      <p class="ga-overlay-bar__hint">{{ overlayHint }}</p>
    </div>

    <EventVenueDetailLocations
      v-if="venueAddress"
      ref="locationsRef"
      :event-address="venueAddress"
      :child-addresses="childAddresses"
      :read-only="readOnly"
      :allow-children="true"
      :allow-extra-sites="Boolean(gaDepartmentId)"
      :allow-create-extra="allowCreateExtra"
      :allow-star-extra="gaMapMode === 'all'"
      :allow-poi-children="!gaDepartmentId"
      :extra-sites="extraSites"
      :extra-editable-pin-id="extraEditablePinId"
      :overlay="overlay"
      :overview-hint="gaOverviewHint"
      :add-extra-button-label="extraAddLabel"
      location-kind="event"
      :hide-title="hideTitle"
      @edit-child="openChildEditModal"
      @create-child="openChildCreateModal"
      @edit-venue-details="openVenueDetailsModal"
      @venue-updated="handleVenueUpdated"
      @create-extra="startPlacingGa"
      @edit-extra="openGaPlaceDialog"
      @toggle-extra-star="toggleGaStar"
      @extra-pin-moved="onExtraPinMoved"
      @extra-map-click="onExtraMapClick"
    />

    <p v-if="showJsHint" class="field-hint text-muted activity-venue-overview-js-hint">
      {{ t('activities.venueLocations.activityVenueHint') }}
    </p>

    <AddressModal
      v-if="showChildModal"
      :department-id="departmentId"
      :address="childModalAddress"
      :default-type="childModalDefaultType"
      :parent-id="childModalParentId"
      :default-name="childModalDefaultName"
      :allowed-types="childModalAllowedTypes"
      :initial-latitude="childModalMapFocus?.latitude ?? null"
      :initial-longitude="childModalMapFocus?.longitude ?? null"
      @close="closeChildModal"
      @saved="handleChildSaved"
    />

    <EDialog v-model="gaDialogOpen" :title="t('grossanlass.einstellungen.mapPinTitle')" :max-width="420">
      <label class="ga-place-field">
        <span>{{ t('grossanlass.einstellungen.placesName') }}</span>
        <input v-model="gaDraftName" type="text">
      </label>
      <label class="ga-place-field">
        <span>{{ t('grossanlass.einstellungen.placesKind') }}</span>
        <select v-model="gaDraftKind">
          <option v-for="kind in GA_PLACE_KINDS" :key="kind" :value="kind">
            {{ t(`grossanlass.einstellungen.placesKind${kindLabelKey(kind)}`) }}
          </option>
        </select>
      </label>
      <label v-if="gaMapMode === 'all'" class="ga-place-field ga-place-field--star">
        <input v-model="gaDraftStarred" type="checkbox">
        <span>{{ t('grossanlass.einstellungen.placesStar') }}</span>
      </label>
      <p v-if="gaDraftLat != null" class="ga-place-field__hint">
        {{ gaDraftLat.toFixed(5) }}° N, {{ gaDraftLng?.toFixed(5) }}° E
      </p>
      <p v-else class="ga-place-field__hint">{{ t('activities.venueLocations.extraPlaceHint') }}</p>
      <template #actions>
        <EButton variant="secondary" @click="closeGaDialog">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :loading="gaBusy" :disabled="!gaDraftName.trim()" @click="saveGaPlace">
          {{ t('grossanlass.einstellungen.mapPinSave') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAddress, type Address } from '@/api/addresses'
import {
  createGrossanlassMap,
  createGrossanlassPlace,
  listGrossanlassMaps,
  listGrossanlassPlaces,
  updateGrossanlassMap,
  updateGrossanlassPlace,
  uploadGrossanlassMapBackground,
  type GaMap,
  type GaPlace,
  type GaPlaceKind,
} from '@/api/grossanlassLogistics'
import AddressModal from '@/components/AddressModal.vue'
import EventVenueDetailLocations, {
  type VenueExtraSite,
} from '@/components/contacts/EventVenueDetailLocations.vue'
import { EButton, EDialog } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import {
  DRAFT_GA_PIN_ID,
  GA_PLACE_KINDS,
  gaMapOverlayBounds,
  gaPlaceColor,
  gaPlaceKind,
} from '@/utils/grossanlassGaMap'

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
  }>(),
  {
    readOnly: false,
    showJsHint: false,
    hideTitle: true,
    gaDepartmentId: null,
    gaMapMode: 'all',
  },
)

const emit = defineEmits<{
  updated: []
}>()

const { t } = useI18n()
const toast = useToast()

const locationsRef = ref<InstanceType<typeof EventVenueDetailLocations> | null>(null)
const overlayFileEl = ref<HTMLInputElement | null>(null)
const venueAddress = ref<Address | null>(null)
const childAddresses = ref<Address[]>([])
const gaPlaces = ref<GaPlace[]>([])
const gaMap = ref<GaMap | null>(null)

const showChildModal = ref(false)
const childModalAddress = ref<Address | null>(null)
const childModalIsVenueDetails = ref(false)
const childModalDefaultType = ref<string>('event_delivery')

const extraEditablePinId = ref<string | null>(null)
const gaDialogOpen = ref(false)
const gaBusy = ref(false)
const overlayBusy = ref(false)
const gaEditingId = ref<string | null>(null)
const gaDraftName = ref('')
const gaDraftKind = ref<GaPlaceKind>('bauprojekt')
const gaDraftStarred = ref(false)
const gaDraftLat = ref<number | null>(null)
const gaDraftLng = ref<number | null>(null)

const hasDeliveryChild = computed(() => childAddresses.value.some((a) => a.type === 'event_delivery'))

const overlay = computed(() => {
  const bounds = gaMapOverlayBounds(gaMap.value)
  if (!bounds || !gaMap.value?.image_url) return null
  return { url: gaMap.value.image_url, ...bounds }
})

const overlayHint = computed(() => {
  if (!gaMap.value?.image_url) return t('grossanlass.einstellungen.mapOverlayHint')
  if (!overlay.value) return t('grossanlass.einstellungen.mapFitBoundsHint')
  return t('grossanlass.einstellungen.mapOverlayOnHint')
})

const allowCreateExtra = computed(() => {
  if (!props.gaDepartmentId || props.readOnly) return false
  return true
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

const extraSites = computed((): VenueExtraSite[] => {
  const source =
    props.gaMapMode === 'starred'
      ? gaPlaces.value.filter((place) => place.starred)
      : gaPlaces.value
  const rows: VenueExtraSite[] = source.map((place) => {
    const kind = gaPlaceKind(place.kind)
    const color = gaPlaceColor(kind)
    const lat = place.latitude
    const lng = place.longitude
    return {
      id: place.id,
      label: place.name,
      summary: t(`grossanlass.einstellungen.placesKind${kindLabelKey(kind)}`),
      hint: t('activities.venueLocations.extraGaHint'),
      color,
      qrUrl: place.qr_url,
      starred: place.starred === true,
      pin:
        lat != null && lng != null
          ? {
              id: place.id,
              label: place.name,
              latitude: lat,
              longitude: lng,
              variant: 'poi',
              color,
            }
          : null,
    }
  })
  if (
    extraEditablePinId.value === DRAFT_GA_PIN_ID &&
    gaDraftLat.value != null &&
    gaDraftLng.value != null
  ) {
    const color = gaPlaceColor(gaDraftKind.value)
    const label = gaDraftName.value.trim() || t('grossanlass.einstellungen.mapPinTitle')
    rows.push({
      id: DRAFT_GA_PIN_ID,
      label,
      color,
      starred: gaDraftStarred.value,
      pin: {
        id: DRAFT_GA_PIN_ID,
        label,
        latitude: gaDraftLat.value,
        longitude: gaDraftLng.value,
        variant: 'poi',
        color,
      },
    })
  }
  return rows
})

const childModalAllowedTypes = computed(() => {
  if (childModalIsVenueDetails.value) {
    return [venueAddress.value?.type === 'meeting' ? 'meeting' : 'event']
  }
  if (childModalAddress.value) return [childModalAddress.value.type]
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
  const base = venueAddress.value?.name || venueAddress.value?.company || ''
  return base ? `${base} – Zustellung` : ''
})

function kindLabelKey(kind: GaPlaceKind): 'Bauprojekt' | 'Unterlager' | 'Matplatz' | 'Anfahrt' | 'Poi' {
  if (kind === 'bauprojekt') return 'Bauprojekt'
  if (kind === 'unterlager') return 'Unterlager'
  if (kind === 'matplatz') return 'Matplatz'
  if (kind === 'anfahrt') return 'Anfahrt'
  return 'Poi'
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

async function loadGa() {
  const departmentId = props.gaDepartmentId
  if (!departmentId) {
    gaPlaces.value = []
    gaMap.value = null
    return
  }
  try {
    const [places, maps] = await Promise.all([
      listGrossanlassPlaces(departmentId),
      listGrossanlassMaps(departmentId),
    ])
    gaPlaces.value = places
    gaMap.value = maps[0] ?? null
  } catch {
    gaPlaces.value = []
    gaMap.value = null
  }
}

function startPlacingGa() {
  extraEditablePinId.value = DRAFT_GA_PIN_ID
  gaEditingId.value = null
  gaDraftName.value = ''
  gaDraftKind.value = props.gaMapMode === 'starred' ? 'anfahrt' : 'bauprojekt'
  gaDraftStarred.value = props.gaMapMode === 'starred'
  gaDraftLat.value = null
  gaDraftLng.value = null
  gaDialogOpen.value = false
}

function openGaPlaceDialog(id: string) {
  if (id === DRAFT_GA_PIN_ID) {
    extraEditablePinId.value = DRAFT_GA_PIN_ID
    gaDialogOpen.value = true
    return
  }
  const place = gaPlaces.value.find((row) => row.id === id)
  if (!place) return
  extraEditablePinId.value = id
  gaEditingId.value = id
  gaDraftName.value = place.name
  gaDraftKind.value = gaPlaceKind(place.kind)
  gaDraftStarred.value = place.starred === true
  gaDraftLat.value = place.latitude ?? null
  gaDraftLng.value = place.longitude ?? null
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
        kind: gaDraftKind.value,
        latitude: gaDraftLat.value,
        longitude: gaDraftLng.value,
        starred,
      })
      gaPlaces.value = gaPlaces.value.map((row) => (row.id === saved.id ? saved : row))
    } else {
      const created = await createGrossanlassPlace(departmentId, {
        name,
        kind: gaDraftKind.value,
        latitude: gaDraftLat.value,
        longitude: gaDraftLng.value,
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
      currentMapBounds(),
    )
    await loadGa()
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
  const bounds = currentMapBounds()
  if (!departmentId || !current || !bounds) {
    toast.error(t('grossanlass.einstellungen.mapFitBoundsError'))
    return
  }
  overlayBusy.value = true
  try {
    gaMap.value = await updateGrossanlassMap(departmentId, current.id, bounds)
    await loadGa()
    toast.success(t('grossanlass.einstellungen.mapFitted'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.mapFitBoundsError'))
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

watch(() => props.venueAddressId, () => void loadVenue(), { immediate: true })
watch(() => props.gaDepartmentId, () => void loadGa(), { immediate: true })

defineExpose({ reload: loadVenue, reloadGa: loadGa })
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
</style>
