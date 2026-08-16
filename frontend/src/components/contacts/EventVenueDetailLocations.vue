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
          v-else-if="!createPinMode"
          type="button"
          class="event-venue-map-edit-btn"
          :aria-label="t('common.edit')"
          @click="startVenueEdit"
        >
          <v-icon icon="mdi-pencil-outline" size="16" />
        </button>
      </div>
    </div>
    <div v-else-if="!readOnly" class="event-venue-detail-locations-header event-venue-detail-locations-header--actions-only">
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
          v-else-if="!createPinMode"
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

    <ActivityDualLocationMap
      ref="overviewMapRef"
      :pins="displayPins"
      height="280px"
      :interactive="editingVenue"
      :editable-pin-id="editingVenue ? 'venue' : null"
      :prefer-swiss-map="true"
      :show-layer-control="true"
      @pin-moved="onVenuePinMoved"
      @map-click="onVenueMapClick"
    />

    <div class="event-venue-detail-accordion-list">
      <div
        v-for="site in accordionSites"
        :key="site.id"
        class="event-venue-detail-accordion"
        :class="{ 'is-highlighted': highlightPulseId === site.id }"
      >
        <div class="event-venue-detail-accordion-row">
          <button
            type="button"
            class="event-venue-detail-accordion-toggle"
            :aria-expanded="expandedId === site.id"
            @click="toggleSite(site.id)"
          >
            <span class="event-venue-detail-accordion-chevron" aria-hidden="true">
              {{ expandedId === site.id ? '▾' : '▸' }}
            </span>
            <span
              class="event-venue-detail-accordion-dot"
              :style="{ background: site.color }"
              aria-hidden="true"
            />
            <span class="event-venue-detail-accordion-label">{{ site.label }}</span>
            <span v-if="site.summary" class="event-venue-detail-accordion-summary">{{ site.summary }}</span>
          </button>
          <button
            v-if="!readOnly && !createPinMode && expandedId !== site.id"
            type="button"
            class="event-venue-accordion-edit-btn"
            :aria-label="t('common.edit')"
            @click.stop.prevent="site.onEdit()"
          >
            <v-icon icon="mdi-pencil-outline" size="16" />
          </button>
        </div>
        <div v-show="expandedId === site.id" class="event-venue-detail-accordion-body">
          <template v-if="createPinMode && site.id === 'venue'">
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
          <template v-else>
            <p class="field-hint text-muted">{{ site.hint || site.summary || '—' }}</p>
            <p v-if="site.address && !site.pin" class="field-hint text-muted">
              {{ t('activities.venueLocations.noCoordsForAddress') }}
            </p>
            <div class="event-venue-detail-accordion-actions">
              <button
                v-if="!readOnly"
                type="button"
                class="event-venue-accordion-edit-btn"
                :aria-label="t('common.edit')"
                @click.stop.prevent="site.onEdit()"
              >
                <v-icon icon="mdi-pencil-outline" size="16" />
              </button>
              <template v-if="site.pin">
                <a
                  :href="googleMapsLinkFor(site.pin)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn btn-outline btn-sm"
                >
                  {{ t('components.mapView.openGoogleMaps') }}
                </a>
                <a
                  :href="swisstopoLinkFor(site.pin)"
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
        v-if="!readOnly && allowChildren && !createPinMode"
        type="button"
        class="event-venue-detail-add-row"
        data-onboarding="activity-venue-delivery-add"
        :class="{ 'is-highlighted': highlightPulseId === 'add-delivery' }"
        @click="emit('create-child')"
      >
        <span class="event-venue-detail-add-plus" aria-hidden="true">+</span>
        <span>{{ addChildButtonLabel }}</span>
      </button>
    </div>

    <p v-if="!createPinMode && allowChildren" class="field-hint text-muted">{{ t('activities.venueLocations.overviewHint') }}</p>
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
import ActivityDualLocationMap, { type ActivityLocationPin } from '@/components/activities/ActivityDualLocationMap.vue'
import { googleMapsCoordinatesUrl, swisstopoMapUrl } from '@/utils/mapExternalLinks'
import { useToast } from '@/composables/useToast'

const VENUE_COLOR = '#2563eb'
const DELIVERY_COLOR = '#ea580c'
const POI_FALLBACK_COLOR = '#16a34a'

type AccordionSite = {
  id: string
  label: string
  summary: string
  hint?: string
  color: string
  address: Address | null
  pin: ActivityLocationPin | null
  onEdit: () => void
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
  },
)

const emit = defineEmits<{
  'edit-child': [address: Address]
  'create-child': []
  'edit-venue-details': []
  'venue-updated': [address: Address]
  'pin-accepted': [payload: { latitude: number; longitude: number; name: string }]
  'update:suggestedName': [name: string]
}>()

const { t, locale } = useI18n()
const toast = useToast()

const rootRef = ref<HTMLElement | null>(null)
const expandedId = ref<string | null>(null)
const highlightPulseId = ref<string | null>(null)
let highlightClearTimer: ReturnType<typeof setTimeout> | null = null
const overviewMapRef = ref<InstanceType<typeof ActivityDualLocationMap> | null>(null)

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
      onEdit: () => emit('edit-venue-details'),
    },
  ]

  if (!props.allowChildren) return sites

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
      onEdit: () => emit('edit-child', delivery),
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
      onEdit: () => emit('edit-child', poi),
    })
  }

  return sites
})

const childPins = computed((): ActivityLocationPin[] =>
  accordionSites.value
    .filter((s) => s.id !== 'venue')
    .map((s) => s.pin)
    .filter((p): p is ActivityLocationPin => p != null),
)

const displayPins = computed((): ActivityLocationPin[] => {
  const pins = [...childPins.value]
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
  return pins
})

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

async function refreshMaps() {
  await nextTick()
  overviewMapRef.value?.invalidateSize()
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

function onVenuePinMoved(payload: { id: string; latitude: number; longitude: number }) {
  if (payload.id !== 'venue') return
  draftLat.value = payload.latitude
  draftLng.value = payload.longitude
}

function onVenueMapClick(payload: { latitude: number; longitude: number }) {
  if (!editingVenue.value) return
  draftLat.value = payload.latitude
  draftLng.value = payload.longitude
  expandedId.value = 'venue'
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
})

onBeforeUnmount(() => {
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
      ? root?.querySelector('.event-venue-detail-add-row')
      : root?.querySelector('.event-venue-detail-accordion.is-highlighted')
  target?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  highlightClearTimer = setTimeout(() => {
    highlightPulseId.value = null
    highlightClearTimer = null
  }, 3200)
}

defineExpose({ refreshMaps, startVenueEdit, focusDeliveryHighlight })
</script>

<style scoped>
.event-venue-detail-locations {
  display: flex;
  flex-direction: column;
  gap: 12px;
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
