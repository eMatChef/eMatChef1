<template>
  <div class="ga-standorte-page">
    <p class="ga-standorte-page__intro">{{ t('grossanlass.einstellungen.standorteIntro') }}</p>

    <ol v-if="!allCoreLocationsReady" class="ga-core-steps" :aria-label="t('grossanlass.einstellungen.coreStepsTitle')">
      <li class="ga-core-steps__item" :class="{ 'is-done': !!venueAddressId }">
        <span class="ga-core-steps__mark" aria-hidden="true">{{ venueAddressId ? '✓' : '1' }}</span>
        {{ t('grossanlass.einstellungen.coreStepVenue') }}
      </li>
      <li class="ga-core-steps__item" :class="{ 'is-done': coreReady.delivery }">
        <span class="ga-core-steps__mark" aria-hidden="true">{{ coreReady.delivery ? '✓' : '2' }}</span>
        {{ t('grossanlass.einstellungen.coreStepDelivery') }}
      </li>
      <li class="ga-core-steps__item" :class="{ 'is-done': coreReady.storage }">
        <span class="ga-core-steps__mark" aria-hidden="true">{{ coreReady.storage ? '✓' : '3' }}</span>
        {{ t('grossanlass.einstellungen.coreStepStorage') }}
      </li>
    </ol>
    <p v-if="!canCreateGaPlaces" class="ga-standorte-page__intro">
      {{ t('grossanlass.einstellungen.placesNeedCore') }}
    </p>

    <section v-if="departmentId" class="ga-places ga-places--map">
      <h3>{{ t('grossanlass.einstellungen.mapTitle') }}</h3>
      <p class="ga-standorte-page__intro">{{ t('grossanlass.einstellungen.mapUnifiedHint') }}</p>
      <ActivityVenueOverviewBlock
        v-if="venueAddressId"
        ref="mapRef"
        :venue-address-id="venueAddressId"
        :department-id="departmentId"
        :ga-department-id="departmentId"
        ga-map-mode="all"
        @updated="onMapUpdated"
        @venue-cleared="loadVenue"
        @core-ready="onCoreReady"
        @create-storage="openStorageModalFromMap"
        @edit-storage="openStorageModalFromMap"
      />
      <div v-else-if="canManage" class="venue-empty">
        <p class="venue-empty__hint">{{ t('grossanlass.einstellungen.mapNeedVenue') }}</p>
        <button
          type="button"
          class="venue-set-cta"
          data-onboarding="activity-venue-add"
          :disabled="venueSaving"
          @click="openAddVenueAddressModal()"
        >
          <span class="venue-set-cta-plus" aria-hidden="true">+</span>
          <span>{{ t('grossanlass.planung.stammdaten.setEventVenue') }}</span>
        </button>
        <DepartmentAddressAutocomplete
          class="venue-empty__search"
          input-id="ga-standorte-venue-search"
          :addresses="rentalAddresses"
          :selected-id="venueAddressId"
          primary-type="event"
          :placeholder="t('grossanlass.einstellungen.mapVenueSearchPlaceholder')"
          :add-button-title="t('activities.wizard.form.addVenueAddressTitle')"
          :edit-button-title="t('activities.wizard.form.editVenueAddressTitle')"
          :empty-addresses-label="t('activities.wizard.form.noAddressesWithAdd')"
          inline-create-label-key="addresses.search.createEventVenueInline"
          @update:selected-id="onVenueSelected"
          @create="openAddVenueAddressModal"
        />
      </div>
      <p v-else class="empty">{{ t('grossanlass.einstellungen.mapNeedVenueReadonly') }}</p>
    </section>

    <div v-if="departmentId" class="ga-standorte-page__panel">
      <h3>{{ t('grossanlass.einstellungen.lagerTitle') }}</h3>
      <p class="ga-standorte-page__intro">{{ t('grossanlass.einstellungen.lagerHint') }}</p>
      <DepartmentAddressKindPanel
        ref="storagePanelRefInner"
        :department-id="departmentId"
        address-kind="storage"
        :map-overlay="sitePlanOverlay"
        :initial-latitude="venueMapFocus?.latitude ?? null"
        :initial-longitude="venueMapFocus?.longitude ?? null"
        @changed="onStorageChanged"
      />
    </div>

    <section class="ga-places">
      <div class="ga-places__head">
        <h3>{{ t('grossanlass.einstellungen.placesTitle') }}</h3>
        <form v-if="canCreateGaPlaces" class="ga-places__add" @submit.prevent="addPlace">
          <input v-model="newPlaceName" type="text" :placeholder="t('grossanlass.einstellungen.placesName')">
          <select v-model="newPlaceKind" :aria-label="t('grossanlass.einstellungen.placesKind')">
            <option v-for="kind in createPlaceKinds" :key="kind" :value="kind">
              {{ t(`grossanlass.einstellungen.placesKind${kindLabelKey(kind)}`) }}
            </option>
          </select>
          <EButton variant="primary" size="small" type="submit" :loading="busy">
            {{ t('grossanlass.einstellungen.placesAdd') }}
          </EButton>
        </form>
      </div>
      <p class="ga-standorte-page__intro">{{ t('grossanlass.einstellungen.placesHint') }}</p>
      <ul>
        <li v-for="place in places" :key="place.id" class="ga-places__row">
          <button
            type="button"
            class="ga-places__star"
            :class="{ 'is-on': place.starred }"
            :title="t('grossanlass.einstellungen.placesStar')"
            :aria-label="t('grossanlass.einstellungen.placesStar')"
            @click="toggleStar(place)"
          >
            ★
          </button>
          <button type="button" class="ga-places__main" @click="editPlaceOnMap(place)">
            <strong>{{ place.name }}</strong>
            <span class="ga-places__kind">{{ t(`grossanlass.einstellungen.placesKind${kindLabelKey(placeKind(place))}`) }}</span>
            <span v-if="place.latitude == null" class="ga-places__missing">{{ t('grossanlass.einstellungen.placesNoCoords') }}</span>
          </button>
          <div class="ga-places__refs">
            <a :href="place.qr_url" target="_blank" rel="noopener">{{ place.public_code }}</a>
            <button
              v-if="place.group_id"
              type="button"
              class="ga-places__link"
              @click="openLinkedGroup(place)"
            >
              {{
                placeKind(place) === 'bauprojekt'
                  ? t('activities.venueLocations.openLinkedBauprojekt')
                  : t('activities.venueLocations.openLinkedBereich')
              }}
            </button>
          </div>
          <div class="ga-places__actions">
            <button
              type="button"
              class="ga-places__action"
              :aria-label="t('common.edit')"
              :title="t('common.edit')"
              @click="editPlaceOnMap(place)"
            >
              ✎
            </button>
            <button
              v-if="place.can_delete"
              type="button"
              class="ga-places__action ga-places__action--danger"
              :aria-label="t('common.delete')"
              :title="t('common.delete')"
              @click="deletePlace(place)"
            >
              ×
            </button>
          </div>
        </li>
      </ul>
      <p v-if="!places.length" class="empty">{{ t('grossanlass.einstellungen.placesEmpty') }}</p>
    </section>

    <v-dialog
      v-model="showVenueContactModal"
      class="contact-create-dialog"
      max-width="960"
      scrollable
      content-class="contact-create-dialog__content"
      :z-index="2400"
    >
      <v-card class="contact-create-dialog__card" rounded="lg">
        <v-card-text class="contact-create-dialog__body">
          <ContactDetailView
            v-if="showVenueContactModal"
            :key="venueContactModalKey"
            :mode="venueContactModalMode"
            as-modal
            :department-id="departmentId"
            :contact-id="venueContactModalId"
            default-type="event"
            :initial-name="venueContactInitialName"
            @close="closeVenueContactModal"
            @created="onVenueContactCreated"
            @updated="onVenueContactUpdated"
          />
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { EButton } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import ActivityVenueOverviewBlock from '@/components/activities/ActivityVenueOverviewBlock.vue'
import DepartmentAddressKindPanel from '@/components/settings/DepartmentAddressKindPanel.vue'
import ContactDetailView from '@/components/contacts/ContactDetailView.vue'
import { DepartmentAddressAutocomplete } from '@/components/addresses'
import { getAddresses, getAddress, type Address } from '@/api/addresses'
import { getGrossanlassPlanung, updateGrossanlassPlanung } from '@/api/grossanlassPlanung'
import {
  createGrossanlassPlace,
  deleteGrossanlassPlace,
  listGrossanlassPlaces,
  updateGrossanlassPlace,
  type GaPlace,
  type GaPlaceKind,
} from '@/api/grossanlassLogistics'
import { GA_PLACE_CREATE_KINDS, gaPlaceKind, gaPlaceKindLabelKey } from '@/utils/grossanlassGaMap'
import { formatAddressOption } from '@/utils/departmentAddressSearch'
import '@/styles/contacts-view.css'

defineOptions({ name: 'GrossanlassEinstellungenStandorte' })

const createPlaceKinds = GA_PLACE_CREATE_KINDS

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const mapRef = ref<InstanceType<typeof ActivityVenueOverviewBlock> | null>(null)
const storagePanelRefInner = ref<InstanceType<typeof DepartmentAddressKindPanel> | null>(null)
const places = ref<GaPlace[]>([])
const venueAddressId = ref<string | null>(null)
const canManage = ref(true)
const rentalAddresses = ref<Address[]>([])
const venueSaving = ref(false)
const venueMapFocus = ref<{ latitude: number; longitude: number } | null>(null)
const showVenueContactModal = ref(false)
const venueContactModalMode = ref<'view' | 'create'>('view')
const venueContactModalId = ref<string | null>(null)
const venueContactInitialName = ref('')
const newPlaceName = ref('')
const newPlaceKind = ref<GaPlaceKind>('bauprojekt')
const busy = ref(false)
const coreReady = reactive({ delivery: false, storage: false })
const canCreateGaPlaces = computed(
  () => !!venueAddressId.value && coreReady.delivery && coreReady.storage,
)

const sitePlanOverlay = computed(() => mapRef.value?.getSitePlanOverlay?.() ?? null)

const allCoreLocationsReady = computed(
  () => !!venueAddressId.value && coreReady.delivery && coreReady.storage,
)

function onCoreReady(payload: { delivery: boolean; storage: boolean }) {
  coreReady.delivery = payload.delivery
  coreReady.storage = payload.storage
}

async function onStorageChanged() {
  await onMapUpdated()
}

async function onMapUpdated() {
  await loadPlaces()
  await mapRef.value?.reloadStorage?.()
  await storagePanelRefInner.value?.reload?.()
}

function openStorageModalFromMap(address?: Address | null) {
  storagePanelRefInner.value?.openAddressModal?.(address ?? undefined)
}

const venueContactModalKey = computed(() =>
  venueContactModalMode.value === 'create'
    ? 'venue-create'
    : `venue-view-${venueContactModalId.value ?? 'none'}`,
)

function kindLabelKey(kind: GaPlaceKind) {
  return gaPlaceKindLabelKey(kind)
}

function placeKind(place: GaPlace): GaPlaceKind {
  return gaPlaceKind(place.kind)
}

function openLinkedGroup(place: GaPlace) {
  void mapRef.value?.openLinkedGroupByPlaceId?.(place.id)
}

async function loadPlaces() {
  if (!departmentId.value) return
  try {
    places.value = await listGrossanlassPlaces(departmentId.value)
  } catch {
    places.value = []
  }
}

async function loadVenueMapFocus() {
  const id = venueAddressId.value
  if (!id) {
    venueMapFocus.value = null
    return
  }
  const fromList = rentalAddresses.value.find((row) => row.id === id)
  if (fromList?.latitude != null && fromList?.longitude != null) {
    venueMapFocus.value = { latitude: fromList.latitude, longitude: fromList.longitude }
    return
  }
  try {
    const data = await getAddress(id)
    const lat = data.address.latitude
    const lng = data.address.longitude
    if (lat != null && lng != null) {
      venueMapFocus.value = { latitude: lat, longitude: lng }
    } else {
      venueMapFocus.value = null
    }
  } catch {
    venueMapFocus.value = null
  }
}

async function loadVenue() {
  if (!departmentId.value) return
  try {
    const pack = await getGrossanlassPlanung(departmentId.value)
    venueAddressId.value = pack.config.venue_address_id || null
    canManage.value = pack.can_manage !== false
  } catch {
    venueAddressId.value = null
    canManage.value = false
  }
  if (!venueAddressId.value) coreReady.delivery = false
  await loadVenueMapFocus()
}

async function loadRentalAddresses() {
  if (!departmentId.value) return
  try {
    const { addresses } = await getAddresses(departmentId.value)
    rentalAddresses.value = [...addresses].sort((a, b) =>
      formatAddressOption(a).localeCompare(formatAddressOption(b), 'de'),
    )
  } catch {
    rentalAddresses.value = []
  }
  coreReady.storage = rentalAddresses.value.some((row) => row.type === 'storage' && !row.deleted_at)
  await loadVenueMapFocus()
}

async function saveVenueAddress(id: string | null) {
  if (!departmentId.value || !id) return
  venueSaving.value = true
  try {
    const pack = await updateGrossanlassPlanung(departmentId.value, { venue_address_id: id })
    venueAddressId.value = pack.config.venue_address_id || null
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    venueSaving.value = false
  }
}

function onVenueSelected(id: string | null) {
  if (!id || id === venueAddressId.value) return
  void saveVenueAddress(id)
}

function closeVenueContactModal() {
  showVenueContactModal.value = false
  venueContactModalId.value = null
}

function openAddVenueAddressModal(presetName = '') {
  venueContactModalMode.value = 'create'
  venueContactModalId.value = null
  venueContactInitialName.value = String(presetName ?? '').trim()
  showVenueContactModal.value = true
}

async function onVenueContactCreated(addr: Address) {
  closeVenueContactModal()
  await loadRentalAddresses()
  if (addr?.id) await saveVenueAddress(addr.id)
}

async function onVenueContactUpdated() {
  await loadRentalAddresses()
}

async function addPlace() {
  const name = newPlaceName.value.trim()
  if (!name || !departmentId.value) return
  if (!canCreateGaPlaces.value) {
    toast.error(t('grossanlass.einstellungen.placesNeedCore'))
    return
  }
  busy.value = true
  try {
    const created = await createGrossanlassPlace(departmentId.value, {
      name,
      kind: newPlaceKind.value,
    })
    places.value = [...places.value, created].sort((a, b) => a.name.localeCompare(b.name))
    newPlaceName.value = ''
    await mapRef.value?.reloadGa?.()
    await nextTick()
    mapRef.value?.beginEditPlace?.(created.id)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.placesAddError'))
  } finally {
    busy.value = false
  }
}

async function toggleStar(place: GaPlace) {
  if (!departmentId.value) return
  const next = !place.starred
  try {
    const saved = await updateGrossanlassPlace(departmentId.value, place.id, { starred: next })
    places.value = places.value.map((row) => (row.id === saved.id ? saved : row))
    await mapRef.value?.reloadGa?.()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.placesStarError'))
  }
}

async function editPlaceOnMap(place: GaPlace) {
  await mapRef.value?.reloadGa?.()
  await nextTick()
  if (place.latitude == null || place.longitude == null) {
    mapRef.value?.beginEditPlace?.(place.id)
    return
  }
  mapRef.value?.openGaPlaceDialog?.(place.id)
}

async function deletePlace(place: GaPlace) {
  if (!departmentId.value || !place.can_delete) return
  const ok = await confirm.confirm({
    title: t('grossanlass.einstellungen.placesDeleteConfirmTitle'),
    message: t('grossanlass.einstellungen.placesDeleteConfirmMessage', { name: place.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  busy.value = true
  try {
    await deleteGrossanlassPlace(departmentId.value, place.id)
    await mapRef.value?.softReload?.()
    toast.success(t('grossanlass.einstellungen.placesDeleted'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.placesDeleteError'))
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  void loadPlaces()
  void loadVenue()
  void loadRentalAddresses()
})
</script>

<style scoped>
.ga-standorte-page {
  padding: 4px 0 24px;
}

.ga-standorte-page__intro {
  margin: 0 0 16px;
  color: #64748b;
  font-size: 0.9rem;
}

.ga-core-steps {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0 0 12px;
  padding: 0;
  list-style: none;
}

.ga-core-steps__item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  font-size: 0.85rem;
  color: #64748b;
  background: #fff;
}

.ga-core-steps__item.is-done {
  border-color: #86efac;
  color: #166534;
  background: #f0fdf4;
}

.ga-core-steps__mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  background: #e2e8f0;
  color: #334155;
}

.ga-core-steps__item.is-done .ga-core-steps__mark {
  background: #16a34a;
  color: #fff;
}

.ga-standorte-page__panel {
  margin-top: 20px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
.ga-standorte-page__panel h3 {
  margin: 0 0 8px;
  font-size: 1rem;
}

.ga-places {
  margin-top: 20px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
.ga-places--map { overflow: hidden; }
.ga-places__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
}
.ga-places__head h3,
.ga-places--map h3 { margin: 0 0 8px; font-size: 1rem; }
.ga-places__add { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.ga-places__add input,
.ga-places__add select {
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
}
.ga-places ul { list-style: none; margin: 12px 0 0; padding: 0; display: grid; gap: 8px; }
.ga-places__row {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto auto;
  gap: 12px;
  align-items: center;
}
.ga-places__main {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  border: 0;
  background: transparent;
  padding: 0;
  text-align: left;
  cursor: pointer;
}
.ga-places__missing {
  font-size: 0.75rem;
  color: #b45309;
}
.ga-places__refs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.ga-places__link {
  border: 0;
  background: transparent;
  padding: 0;
  color: #0f766e;
  cursor: pointer;
  font-size: 0.85rem;
  text-decoration: underline;
}
.ga-places__actions {
  display: flex;
  gap: 4px;
}
.ga-places__action {
  width: 28px;
  height: 28px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
  color: #475569;
}
.ga-places__action--danger {
  color: #b91c1c;
  border-color: #fecaca;
}
.ga-places__kind {
  font-size: 0.75rem;
  color: #475569;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 2px 8px;
}
.ga-places__star {
  border: 0;
  background: transparent;
  cursor: pointer;
  color: #cbd5e1;
  font-size: 1.1rem;
  line-height: 1;
  padding: 0;
}
.ga-places__star.is-on { color: #d97706; }
.empty { color: #94a3b8; font-size: 0.85rem; }
.venue-empty {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
  margin-top: 4px;
}
.venue-empty__hint {
  margin: 0;
  color: #64748b;
  font-size: 0.85rem;
}
.venue-empty__search {
  width: 100%;
  max-width: 520px;
}
.venue-set-cta {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
  color: #059669;
  font-size: 0.95rem;
  font-weight: 500;
}
.venue-set-cta:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.venue-set-cta:hover:not(:disabled) .venue-set-cta-plus,
.venue-set-cta:focus-visible:not(:disabled) .venue-set-cta-plus {
  border-color: #059669;
  background: #ecfdf5;
}
.venue-set-cta-plus {
  width: 42px;
  height: 42px;
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  line-height: 1;
  transition: border-color 0.2s ease, background 0.2s ease;
}
</style>
