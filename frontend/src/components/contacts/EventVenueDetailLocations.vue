<template>
  <div ref="rootRef" class="event-venue-detail-locations">
    <div class="event-venue-detail-locations-header">
      <h2 class="event-venue-detail-locations-title">
        {{ t('activities.venueLocations.sectionTitle') }}
      </h2>
      <div v-if="!readOnly" class="event-venue-detail-locations-actions">
        <EButton
          v-if="editingVenue"
          variant="primary"
          size="small"
          :loading="isSavingVenue"
          :disabled="draftLat == null || draftLng == null"
          @click="acceptVenueDraft"
        >
          {{ t('contacts.detail.acceptLocation') }}
        </EButton>
        <button
          v-else
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
      {{ t('contacts.detail.mapEditHint') }}
    </p>

    <ActivityDualLocationMap
      ref="overviewMapRef"
      :pins="displayPins"
      height="280px"
      :interactive="editingVenue"
      :editable-pin-id="editingVenue ? 'venue' : null"
      @pin-moved="onVenuePinMoved"
      @map-click="onVenueMapClick"
    />

    <div class="event-venue-detail-accordion-list">
      <div
        v-for="site in accordionSites"
        :key="site.id"
        class="event-venue-detail-accordion"
      >
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
        <div v-show="expandedId === site.id" class="event-venue-detail-accordion-body">
          <p class="field-hint text-muted">{{ site.hint || site.summary || '—' }}</p>
          <p v-if="site.address && !site.pin" class="field-hint text-muted">
            {{ t('activities.venueLocations.noCoordsForAddress') }}
          </p>
          <div class="event-venue-detail-accordion-actions">
            <EButton
              v-if="!readOnly && site.id === 'venue'"
              size="small"
              variant="secondary"
              @click="startVenueEdit"
            >
              {{ t('common.edit') }}
            </EButton>
            <EButton
              v-else-if="!readOnly"
              size="small"
              variant="secondary"
              @click="site.onEdit()"
            >
              {{ t('common.edit') }}
            </EButton>
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
        </div>
      </div>

      <button
        v-if="!readOnly"
        type="button"
        class="event-venue-detail-add-row"
        @click="emit('create-child')"
      >
        <span class="event-venue-detail-add-plus" aria-hidden="true">+</span>
        <span>{{ t('activities.venueLocations.addAddressButton') }}</span>
      </button>
    </div>

    <p class="field-hint text-muted">{{ t('activities.venueLocations.overviewHint') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Address } from '@/api/addresses'
import { updateAddress } from '@/api/addresses'
import { EButton } from '@/components/form/base'
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

const props = defineProps<{
  eventAddress: Address
  childAddresses: Address[]
  readOnly?: boolean
}>()

const emit = defineEmits<{
  'edit-child': [address: Address]
  'create-child': []
  'venue-updated': [address: Address]
}>()

const { t, locale } = useI18n()
const toast = useToast()

const rootRef = ref<HTMLElement | null>(null)
const expandedId = ref<string | null>(null)
const overviewMapRef = ref<InstanceType<typeof ActivityDualLocationMap> | null>(null)

const editingVenue = ref(false)
const isSavingVenue = ref(false)
const draftLat = ref<number | null>(null)
const draftLng = ref<number | null>(null)
const baselineLat = ref<number | null>(null)
const baselineLng = ref<number | null>(null)

const deliveryAddress = computed(
  () => props.childAddresses.find((a) => a.type === 'event_delivery') ?? null,
)

const poiAddresses = computed(() => props.childAddresses.filter((a) => a.type === 'event_poi'))

function addressSummary(addr: Address | null | undefined): string {
  if (!addr) return ''
  return addr.full_address || addr.street_line || addr.name || ''
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
    t('activities.wizard.form.venueLabel'),
    props.eventAddress,
    'venue',
  ),
)

const accordionSites = computed((): AccordionSite[] => {
  const sites: AccordionSite[] = [
    {
      id: 'venue',
      label: t('activities.wizard.form.venueLabel'),
      summary: addressSummary(props.eventAddress) || t('activities.venueLocations.venueMapOnlyHint'),
      hint: t('activities.venueLocations.venueMapEditHint'),
      color: VENUE_COLOR,
      address: props.eventAddress,
      pin: venuePinBase.value,
      onEdit: () => startVenueEdit(),
    },
  ]

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
      label: t('activities.wizard.form.venueLabel'),
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
  draftLat.value = props.eventAddress.latitude
  draftLng.value = props.eventAddress.longitude
  baselineLat.value = props.eventAddress.latitude
  baselineLng.value = props.eventAddress.longitude
  editingVenue.value = true
  expandedId.value = 'venue'
  void refreshMaps()
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
}

async function acceptVenueDraft() {
  if (draftLat.value == null || draftLng.value == null) {
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
  if (!editingVenue.value) return
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

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onOutsidePointerDown, true)
})

defineExpose({ refreshMaps, startVenueEdit })
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

.event-venue-detail-accordion-toggle {
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
  max-width: 55%;
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
</style>
