<template>
  <div v-if="venueAddressId" class="activity-venue-overview span-2">
    <EventVenueDetailLocations
      v-if="venueAddress"
      ref="locationsRef"
      :event-address="venueAddress"
      :child-addresses="childAddresses"
      :read-only="readOnly"
      :allow-children="true"
      location-kind="event"
      :hide-title="hideTitle"
      @edit-child="openChildEditModal"
      @create-child="openChildCreateModal"
      @edit-venue-details="openVenueDetailsModal"
      @venue-updated="handleVenueUpdated"
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
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAddress, type Address } from '@/api/addresses'
import AddressModal from '@/components/AddressModal.vue'
import EventVenueDetailLocations from '@/components/contacts/EventVenueDetailLocations.vue'

const props = withDefaults(
  defineProps<{
    venueAddressId: string | null
    departmentId: string
    readOnly?: boolean
    showJsHint?: boolean
    /** Titel «Standorte» ausblenden (Feld heisst schon Eventstandort). */
    hideTitle?: boolean
  }>(),
  {
    readOnly: false,
    showJsHint: false,
    hideTitle: true,
  },
)

const emit = defineEmits<{
  updated: []
}>()

const { t } = useI18n()

const locationsRef = ref<InstanceType<typeof EventVenueDetailLocations> | null>(null)
const venueAddress = ref<Address | null>(null)
const childAddresses = ref<Address[]>([])

const showChildModal = ref(false)
const childModalAddress = ref<Address | null>(null)
const childModalIsVenueDetails = ref(false)
const childModalDefaultType = ref<string>('event_delivery')

const hasDeliveryChild = computed(() => childAddresses.value.some((a) => a.type === 'event_delivery'))

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

function openChildCreateModal() {
  childModalIsVenueDetails.value = false
  childModalAddress.value = null
  childModalDefaultType.value = hasDeliveryChild.value ? 'event_poi' : 'event_delivery'
  showChildModal.value = true
}

function openChildEditModal(address: Address) {
  childModalIsVenueDetails.value = false
  childModalAddress.value = address
  childModalDefaultType.value = address.type === 'event_poi' ? 'event_poi' : 'event_delivery'
  showChildModal.value = true
}

function openVenueDetailsModal() {
  if (!venueAddress.value) return
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

defineExpose({ reload: loadVenue })
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
</style>
