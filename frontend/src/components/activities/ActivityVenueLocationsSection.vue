<template>
  <div class="activity-venue-locations span-2">
    <div
      v-if="showOverviewMap"
      class="activity-venue-locations-overview"
    >
      <ActivityDualLocationMap
        ref="overviewMapRef"
        :pins="overviewPins"
        height="240px"
        :interactive="false"
      />
      <ul v-if="overviewPins.length > 0" class="activity-venue-locations-overview-links">
        <li
          v-for="pin in overviewPins"
          :key="pin.id"
          class="activity-venue-locations-overview-link-row"
        >
          <span
            class="activity-venue-locations-overview-link-dot"
            :class="`activity-venue-locations-overview-link-dot--${pin.variant ?? 'venue'}`"
            aria-hidden="true"
          />
          <span class="activity-venue-locations-overview-link-label">{{ pin.label }}</span>
          <span class="activity-venue-locations-overview-link-actions">
            <a
              :href="googleMapsLinkFor(pin)"
              target="_blank"
              rel="noopener noreferrer"
              class="btn btn-outline btn-sm"
            >
              {{ t('components.mapView.openGoogleMaps') }}
            </a>
            <a
              :href="swisstopoLinkFor(pin)"
              target="_blank"
              rel="noopener noreferrer"
              class="btn btn-outline btn-sm"
            >
              {{ t('components.mapView.openSwisstopoMap') }}
            </a>
          </span>
        </li>
      </ul>
      <p class="field-hint text-muted activity-venue-locations-overview-hint">
        {{ t('activities.venueLocations.overviewHint') }}
      </p>
    </div>

    <div class="activity-venue-accordion">
      <button
        type="button"
        class="activity-venue-accordion-toggle"
        :aria-expanded="venueExpanded"
        @click="toggleVenue"
      >
        <span class="activity-venue-accordion-chevron" aria-hidden="true">{{ venueExpanded ? '▾' : '▸' }}</span>
        <span>{{ t('activities.venueLocations.accordionVenue') }}</span>
        <span v-if="venueSummary" class="activity-venue-accordion-summary">{{ venueSummary }}</span>
      </button>
      <div v-show="venueExpanded" class="activity-venue-accordion-body">
        <p class="field-hint text-muted">
          {{ venueHint }}
        </p>
        <AutoSaveField
          v-model="venueField"
          :baseline="venueBaseline"
          :label="t('activities.wizard.form.venueLabel')"
          span-class="activity-compact-autosave-field activity-venue-autosave-field"
          :save="saveVenueAddressId"
          @saved="emit('saved')"
        >
          <template #default="{ inputId, onChange }">
            <DepartmentAddressAutocomplete
              :input-id="inputId"
              :addresses="addresses"
              :selected-id="venueAddressId"
              primary-type="event"
              :placeholder="t('activities.wizard.form.addressSearchPlaceholder')"
              :add-button-title="t('activities.wizard.form.addVenueAddressTitle')"
              :empty-addresses-label="t('activities.wizard.form.noAddressesWithAdd')"
              inline-create-label-key="addresses.search.createEventVenueInline"
              @update:selected-id="(id) => onVenueAddressId(id, onChange)"
              @create="(name) => emit('create-venue-address', name)"
            />
          </template>
        </AutoSaveField>
        <div v-if="venueAddress?.has_coordinates" class="activity-venue-accordion-map">
          <MapView
            ref="venueMapRef"
            :latitude="venueAddress.latitude"
            :longitude="venueAddress.longitude"
            :address="venueAddress.full_address"
            :editable="false"
            :interactive="true"
            :show-coordinates="false"
            :show-layer-control="false"
            :prefer-swiss-map="true"
            height="280px"
          />
        </div>
        <p v-else-if="venueAddress" class="field-hint text-muted">
          {{ t('activities.venueLocations.noCoordsForAddress') }}
        </p>
      </div>
    </div>

    <div class="activity-venue-accordion">
      <button
        type="button"
        class="activity-venue-accordion-toggle"
        :aria-expanded="deliveryExpanded"
        @click="toggleDelivery"
      >
        <span class="activity-venue-accordion-chevron" aria-hidden="true">{{ deliveryExpanded ? '▾' : '▸' }}</span>
        <span>{{ t('activities.venueLocations.accordionDelivery') }}</span>
        <span v-if="deliverySummary" class="activity-venue-accordion-summary">{{ deliverySummary }}</span>
      </button>
      <div v-show="deliveryExpanded" class="activity-venue-accordion-body">
        <p class="field-hint text-muted">
          {{ wantsJsMaterial ? t('activities.jsMaterial.deliveryAddressHint') : t('activities.venueLocations.deliveryOptionalHint') }}
        </p>
        <AutoSaveField
          v-model="deliveryField"
          :baseline="deliveryBaseline"
          :label="wantsJsMaterial ? t('activities.jsMaterial.deliveryAddressLabel') : t('activities.venueLocations.deliveryAddressOptionalLabel')"
          span-class="activity-compact-autosave-field activity-venue-autosave-field"
          :save="saveJsDeliveryAddressId"
          @saved="emit('saved')"
        >
          <template #default="{ inputId, onChange }">
            <DepartmentAddressAutocomplete
              :input-id="inputId"
              :addresses="addresses"
              :selected-id="deliveryAddressId"
              primary-type="event"
              :placeholder="t('activities.wizard.form.addressSearchPlaceholder')"
              :add-button-title="t('activities.wizard.form.addVenueAddressTitle')"
              :empty-addresses-label="t('activities.wizard.form.noAddressesWithAdd')"
              inline-create-label-key="addresses.search.createEventVenueInline"
              @update:selected-id="(id) => onDeliveryAddressId(id, onChange)"
              @create="(name) => emit('create-delivery-address', name)"
            />
          </template>
        </AutoSaveField>
        <div v-if="deliveryAddress?.has_coordinates" class="activity-venue-accordion-map">
          <MapView
            ref="deliveryMapRef"
            :latitude="deliveryAddress.latitude"
            :longitude="deliveryAddress.longitude"
            :address="deliveryAddress.full_address"
            :editable="false"
            :interactive="true"
            :show-coordinates="false"
            :show-layer-control="false"
            :prefer-swiss-map="true"
            height="280px"
          />
        </div>
        <p v-else-if="deliveryAddress" class="field-hint text-muted">
          {{ t('activities.venueLocations.noCoordsForAddress') }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Address } from '@/api/addresses'
import AutoSaveField from '@/components/common/autoSave/AutoSaveField.vue'
import type { AutoSaveFieldValue } from '@/components/common/autoSave/types'
import { DepartmentAddressAutocomplete } from '@/components/addresses'
import MapView from '@/components/MapView.vue'
import ActivityDualLocationMap, { type ActivityLocationPin } from '@/components/activities/ActivityDualLocationMap.vue'
import { googleMapsCoordinatesUrl, swisstopoMapUrl } from '@/utils/mapExternalLinks'

const props = defineProps<{
  addresses: Address[]
  venueAddressId: string | null
  deliveryAddressId: string | null
  venueBaseline: string
  deliveryBaseline: string
  wantsJsMaterial: boolean
  isSharedActivity: boolean
  saveVenueAddressId: (value: AutoSaveFieldValue) => Promise<void>
  saveJsDeliveryAddressId: (value: AutoSaveFieldValue) => Promise<void>
}>()

const emit = defineEmits<{
  saved: []
  'update:venueAddressId': [id: string | null]
  'update:deliveryAddressId': [id: string | null]
  'create-venue-address': [presetName: string]
  'create-delivery-address': [presetName: string]
}>()

const { t, locale } = useI18n()

const venueExpanded = ref(false)
const deliveryExpanded = ref(false)
const overviewMapRef = ref<InstanceType<typeof ActivityDualLocationMap> | null>(null)
const venueMapRef = ref<InstanceType<typeof MapView> | null>(null)
const deliveryMapRef = ref<InstanceType<typeof MapView> | null>(null)

const showOverviewMap = computed(() => !venueExpanded.value && !deliveryExpanded.value)

const venueField = computed({
  get: () => props.venueAddressId ?? '',
  set(v: string) {
    emit('update:venueAddressId', v === '' ? null : v)
  },
})

const deliveryField = computed({
  get: () => props.deliveryAddressId ?? '',
  set(v: string) {
    emit('update:deliveryAddressId', v === '' ? null : v)
  },
})

const venueHint = computed(() =>
  props.isSharedActivity
    ? t('activities.sharedBasics.venueHintShared')
    : t('activities.wizard.form.venueHint'),
)

function addressById(id: string | null | undefined): Address | undefined {
  if (!id) return undefined
  return props.addresses.find((row) => row.id === id)
}

const venueAddress = computed(() => addressById(props.venueAddressId))
const deliveryAddress = computed(() => addressById(props.deliveryAddressId))

function addressSummary(addr: Address | undefined): string {
  if (!addr) return ''
  return addr.full_address || addr.street_line || addr.name || ''
}

const venueSummary = computed(() => addressSummary(venueAddress.value))
const deliverySummary = computed(() => addressSummary(deliveryAddress.value))

const overviewPins = computed((): ActivityLocationPin[] => {
  const pins: ActivityLocationPin[] = []
  const venue = venueAddress.value
  if (venue?.latitude != null && venue.longitude != null) {
    pins.push({
      id: 'venue',
      label: t('activities.wizard.form.venueLabel'),
      latitude: venue.latitude,
      longitude: venue.longitude,
      variant: 'venue',
    })
  }
  const delivery = deliveryAddress.value
  if (
    delivery?.latitude != null &&
    delivery.longitude != null &&
    delivery.id !== venue?.id
  ) {
    pins.push({
      id: 'delivery',
      label: props.wantsJsMaterial
        ? t('activities.venueLocations.deliveryPinLabel')
        : t('activities.venueLocations.accordionDelivery'),
      latitude: delivery.latitude,
      longitude: delivery.longitude,
      variant: 'delivery',
    })
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

function onVenueAddressId(id: string | null, onChange: () => void) {
  emit('update:venueAddressId', id)
  onChange()
}

function onDeliveryAddressId(id: string | null, onChange: () => void) {
  emit('update:deliveryAddressId', id)
  onChange()
}

function toggleVenue() {
  venueExpanded.value = !venueExpanded.value
  if (venueExpanded.value) deliveryExpanded.value = false
  void refreshMapsAfterToggle()
}

function toggleDelivery() {
  deliveryExpanded.value = !deliveryExpanded.value
  if (deliveryExpanded.value) venueExpanded.value = false
  void refreshMapsAfterToggle()
}

async function refreshMapsAfterToggle() {
  await nextTick()
  if (showOverviewMap.value) {
    overviewMapRef.value?.invalidateSize()
  } else if (venueExpanded.value) {
    venueMapRef.value?.invalidateSize()
  } else if (deliveryExpanded.value) {
    deliveryMapRef.value?.invalidateSize()
  }
}

watch(showOverviewMap, (visible) => {
  if (visible) void nextTick(() => overviewMapRef.value?.invalidateSize())
})
</script>

<style scoped>
.activity-venue-locations {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.activity-venue-locations-overview-hint {
  margin: 8px 0 0;
}

.activity-venue-locations-overview-links {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 12px 0 0;
  padding: 0;
  list-style: none;
}

.activity-venue-locations-overview-link-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
}

.activity-venue-locations-overview-link-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.activity-venue-locations-overview-link-dot--venue {
  background: #2563eb;
  box-shadow: 0 0 0 2px #fff, 0 0 0 3px #2563eb;
}

.activity-venue-locations-overview-link-dot--delivery {
  background: #ea580c;
  box-shadow: 0 0 0 2px #fff, 0 0 0 3px #ea580c;
}

.activity-venue-locations-overview-link-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #334155;
  min-width: 6rem;
}

.activity-venue-locations-overview-link-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-left: auto;
}

@media (max-width: 520px) {
  .activity-venue-locations-overview-link-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .activity-venue-locations-overview-link-actions {
    margin-left: 0;
    width: 100%;
  }

  .activity-venue-locations-overview-link-actions .btn {
    flex: 1 1 auto;
    justify-content: center;
  }
}

.activity-venue-accordion {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 14px;
  background: #fff;
}

.activity-venue-accordion--disabled {
  opacity: 0.85;
}

.activity-venue-accordion-toggle {
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

.activity-venue-accordion-toggle:disabled {
  cursor: default;
  color: #64748b;
}

.activity-venue-accordion-chevron {
  width: 1rem;
  flex-shrink: 0;
}

.activity-venue-accordion-summary {
  margin-left: auto;
  font-size: 0.75rem;
  font-weight: 500;
  color: #64748b;
  max-width: 55%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.activity-venue-accordion-body {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.activity-venue-accordion-map {
  border-radius: 8px;
  overflow: hidden;
}

.activity-venue-accordion-disabled-hint {
  margin: 8px 0 0;
}

.activity-venue-autosave-field :deep(.department-address-autocomplete) {
  width: 100%;
}

.activity-venue-autosave-field :deep(.department-address-autocomplete .form-input) {
  min-height: 48px;
  padding: 16px 12px 10px;
  border-radius: 8px;
}

.activity-venue-autosave-field :deep(.add-inline-btn) {
  width: 48px;
  height: 48px;
  min-height: 48px;
  flex-shrink: 0;
}
</style>
