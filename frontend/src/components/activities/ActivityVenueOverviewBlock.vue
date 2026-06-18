<template>
  <div v-if="venueAddressId" class="activity-venue-overview span-2">
    <ActivityDualLocationMap
      ref="mapRef"
      :pins="pins"
      height="200px"
      :interactive="false"
    />
    <ul v-if="pins.length > 0" class="activity-venue-overview-links">
      <li v-for="pin in pins" :key="pin.id" class="activity-venue-overview-link-row">
        <span
          class="activity-venue-overview-link-dot"
          :class="`activity-venue-overview-link-dot--${pin.variant ?? 'venue'}`"
          aria-hidden="true"
        />
        <span class="activity-venue-overview-link-label">{{ pin.label }}</span>
        <span class="activity-venue-overview-link-actions">
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
    <p v-if="showJsHint" class="field-hint text-muted activity-venue-overview-js-hint">
      {{ t('activities.venueLocations.activityVenueHint') }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAddress } from '@/api/addresses'
import ActivityDualLocationMap, { type ActivityLocationPin } from '@/components/activities/ActivityDualLocationMap.vue'
import { googleMapsCoordinatesUrl, swisstopoMapUrl } from '@/utils/mapExternalLinks'

const props = defineProps<{
  venueAddressId: string | null
  showJsHint?: boolean
}>()

const { t, locale } = useI18n()
const mapRef = ref<InstanceType<typeof ActivityDualLocationMap> | null>(null)
const pins = ref<ActivityLocationPin[]>([])

function mapLinkLang(): string {
  return locale.value.split('-')[0] || 'de'
}

function googleMapsLinkFor(pin: ActivityLocationPin): string {
  return googleMapsCoordinatesUrl(pin.latitude, pin.longitude)
}

function swisstopoLinkFor(pin: ActivityLocationPin): string {
  return swisstopoMapUrl(pin.latitude, pin.longitude, { lang: mapLinkLang() })
}

async function loadPins() {
  const id = props.venueAddressId
  if (!id) {
    pins.value = []
    return
  }
  try {
    const data = await getAddress(id)
    const venue = data.address
    const next: ActivityLocationPin[] = []
    if (venue.latitude != null && venue.longitude != null) {
      next.push({
        id: 'venue',
        label: t('activities.wizard.form.venueLabel'),
        latitude: venue.latitude,
        longitude: venue.longitude,
        variant: 'venue',
      })
    }
    const delivery = (data.child_addresses ?? [])[0]
    if (
      delivery?.latitude != null &&
      delivery.longitude != null &&
      delivery.id !== venue.id
    ) {
      next.push({
        id: 'delivery',
        label: t('activities.venueLocations.deliveryPinLabel'),
        latitude: delivery.latitude,
        longitude: delivery.longitude,
        variant: 'delivery',
      })
    }
    pins.value = next
    mapRef.value?.invalidateSize()
  } catch {
    pins.value = []
  }
}

watch(() => props.venueAddressId, () => void loadPins(), { immediate: true })
</script>

<style scoped>
.activity-venue-overview {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.activity-venue-overview-links {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.activity-venue-overview-link-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
}

.activity-venue-overview-link-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.activity-venue-overview-link-dot--venue {
  background: #2563eb;
  box-shadow: 0 0 0 2px #fff, 0 0 0 3px #2563eb;
}

.activity-venue-overview-link-dot--delivery {
  background: #ea580c;
  box-shadow: 0 0 0 2px #fff, 0 0 0 3px #ea580c;
}

.activity-venue-overview-link-label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #334155;
}

.activity-venue-overview-link-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-left: auto;
}

.activity-venue-overview-js-hint {
  margin: 0;
}
</style>
