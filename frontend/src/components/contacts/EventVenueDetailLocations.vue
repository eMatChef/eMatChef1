<template>
  <div class="event-venue-detail-locations">
    <div
      v-if="showOverviewMap"
      class="event-venue-detail-locations-overview"
    >
      <ActivityDualLocationMap
        ref="overviewMapRef"
        :pins="overviewPins"
        height="260px"
        :interactive="false"
      />
      <ul v-if="overviewPins.length > 0" class="event-venue-detail-locations-overview-links">
        <li
          v-for="pin in overviewPins"
          :key="pin.id"
          class="event-venue-detail-locations-overview-link-row"
        >
          <span
            class="event-venue-detail-locations-overview-link-dot"
            :class="`event-venue-detail-locations-overview-link-dot--${pin.variant ?? 'venue'}`"
            aria-hidden="true"
          />
          <span>{{ pin.label }}</span>
          <span class="event-venue-detail-locations-overview-link-actions">
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
      <p class="field-hint text-muted">{{ t('activities.venueLocations.overviewHint') }}</p>
    </div>

    <div class="event-venue-detail-accordion">
      <button
        type="button"
        class="event-venue-detail-accordion-toggle"
        :aria-expanded="venueExpanded"
        @click="toggleVenue"
      >
        <span class="event-venue-detail-accordion-chevron" aria-hidden="true">{{ venueExpanded ? '▾' : '▸' }}</span>
        <span>{{ t('activities.venueLocations.accordionVenue') }}</span>
        <span v-if="venueSummary" class="event-venue-detail-accordion-summary">{{ venueSummary }}</span>
      </button>
      <div v-show="venueExpanded" class="event-venue-detail-accordion-body">
        <p class="field-hint text-muted">{{ venueSummary || '—' }}</p>
        <div v-if="eventAddress.has_coordinates" class="event-venue-detail-accordion-map">
          <MapView
            ref="venueMapRef"
            :latitude="eventAddress.latitude"
            :longitude="eventAddress.longitude"
            :address="eventAddress.full_address"
            :editable="false"
            :interactive="true"
            :show-coordinates="false"
            :show-layer-control="false"
            :prefer-swiss-map="true"
            height="280px"
          />
        </div>
        <EButton v-if="!readOnly" size="small" variant="secondary" @click="emit('edit-venue')">
          {{ t('common.edit') }}
        </EButton>
      </div>
    </div>

    <div class="event-venue-detail-accordion">
      <button
        type="button"
        class="event-venue-detail-accordion-toggle"
        :aria-expanded="deliveryExpanded"
        @click="toggleDelivery"
      >
        <span class="event-venue-detail-accordion-chevron" aria-hidden="true">{{ deliveryExpanded ? '▾' : '▸' }}</span>
        <span>{{ t('activities.venueLocations.accordionDelivery') }}</span>
        <span v-if="deliverySummary" class="event-venue-detail-accordion-summary">{{ deliverySummary }}</span>
      </button>
      <div v-show="deliveryExpanded" class="event-venue-detail-accordion-body">
        <p class="field-hint text-muted">{{ t('activities.venueLocations.deliveryManageHint') }}</p>
        <template v-if="deliveryAddress">
          <p>{{ deliverySummary }}</p>
          <div v-if="deliveryAddress.has_coordinates" class="event-venue-detail-accordion-map">
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
          <EButton v-if="!readOnly" size="small" variant="secondary" @click="emit('edit-delivery')">
            {{ t('activities.venueLocations.deliveryEditButton') }}
          </EButton>
        </template>
        <EButton
          v-else-if="!readOnly"
          size="small"
          variant="primary"
          @click="emit('create-delivery')"
        >
          {{ t('activities.venueLocations.deliveryCreateButton') }}
        </EButton>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Address } from '@/api/addresses'
import { EButton } from '@/components/form/base'
import MapView from '@/components/MapView.vue'
import ActivityDualLocationMap, { type ActivityLocationPin } from '@/components/activities/ActivityDualLocationMap.vue'
import { googleMapsCoordinatesUrl, swisstopoMapUrl } from '@/utils/mapExternalLinks'

const props = defineProps<{
  eventAddress: Address
  deliveryAddress: Address | null
  readOnly?: boolean
}>()

const emit = defineEmits<{
  'edit-venue': []
  'edit-delivery': []
  'create-delivery': []
}>()

const { t, locale } = useI18n()

const venueExpanded = ref(false)
const deliveryExpanded = ref(false)
const overviewMapRef = ref<InstanceType<typeof ActivityDualLocationMap> | null>(null)
const venueMapRef = ref<InstanceType<typeof MapView> | null>(null)
const deliveryMapRef = ref<InstanceType<typeof MapView> | null>(null)

const showOverviewMap = computed(() => !venueExpanded.value && !deliveryExpanded.value)

function addressSummary(addr: Address | null | undefined): string {
  if (!addr) return ''
  return addr.full_address || addr.street_line || addr.name || ''
}

const venueSummary = computed(() => addressSummary(props.eventAddress))
const deliverySummary = computed(() => addressSummary(props.deliveryAddress))

const overviewPins = computed((): ActivityLocationPin[] => {
  const pins: ActivityLocationPin[] = []
  const venue = props.eventAddress
  if (venue.latitude != null && venue.longitude != null) {
    pins.push({
      id: 'venue',
      label: t('activities.wizard.form.venueLabel'),
      latitude: venue.latitude,
      longitude: venue.longitude,
      variant: 'venue',
    })
  }
  const delivery = props.deliveryAddress
  if (
    delivery?.latitude != null &&
    delivery.longitude != null &&
    delivery.id !== venue.id
  ) {
    pins.push({
      id: 'delivery',
      label: t('activities.venueLocations.deliveryPinLabel'),
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
.event-venue-detail-locations {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.event-venue-detail-locations-overview-links {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 12px 0 0;
  padding: 0;
  list-style: none;
  font-size: 0.8125rem;
}

.event-venue-detail-locations-overview-link-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.event-venue-detail-locations-overview-link-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.event-venue-detail-locations-overview-link-dot--venue {
  background: #2563eb;
}

.event-venue-detail-locations-overview-link-dot--delivery {
  background: #ea580c;
}

.event-venue-detail-locations-overview-link-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-left: auto;
}

.event-venue-detail-accordion {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 14px;
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

.event-venue-detail-accordion-map {
  border-radius: 8px;
  overflow: hidden;
}
</style>
