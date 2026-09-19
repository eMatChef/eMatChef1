<template>
  <div class="ga-standorte-page">
    <p class="ga-standorte-page__intro">{{ t('grossanlass.einstellungen.standorteIntro') }}</p>

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
        @updated="loadPlaces"
      />
      <p v-else class="empty">{{ t('grossanlass.einstellungen.mapNeedVenue') }}</p>
    </section>

    <section class="ga-places">
      <div class="ga-places__head">
        <h3>{{ t('grossanlass.einstellungen.placesTitle') }}</h3>
        <form class="ga-places__add" @submit.prevent="addPlace">
          <input v-model="newPlaceName" type="text" :placeholder="t('grossanlass.einstellungen.placesName')">
          <select v-model="newPlaceKind" :aria-label="t('grossanlass.einstellungen.placesKind')">
            <option v-for="kind in placeKinds" :key="kind" :value="kind">
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
        <li v-for="place in places" :key="place.id">
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
          <strong>{{ place.name }}</strong>
          <span class="ga-places__kind">{{ t(`grossanlass.einstellungen.placesKind${kindLabelKey(placeKind(place))}`) }}</span>
          <a :href="place.qr_url" target="_blank" rel="noopener">{{ place.public_code }}</a>
        </li>
      </ul>
      <p v-if="!places.length" class="empty">{{ t('grossanlass.einstellungen.placesEmpty') }}</p>
    </section>

    <div v-if="departmentId" class="ga-standorte-page__panel">
      <h3>{{ t('grossanlass.einstellungen.lagerTitle') }}</h3>
      <p class="ga-standorte-page__intro">{{ t('grossanlass.einstellungen.lagerHint') }}</p>
      <DepartmentAddressKindPanel :department-id="departmentId" address-kind="storage" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { EButton } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import ActivityVenueOverviewBlock from '@/components/activities/ActivityVenueOverviewBlock.vue'
import DepartmentAddressKindPanel from '@/components/settings/DepartmentAddressKindPanel.vue'
import { getGrossanlassPlanung } from '@/api/grossanlassPlanung'
import {
  createGrossanlassPlace,
  listGrossanlassPlaces,
  updateGrossanlassPlace,
  type GaPlace,
  type GaPlaceKind,
} from '@/api/grossanlassLogistics'
import { GA_PLACE_KINDS, gaPlaceKind } from '@/utils/grossanlassGaMap'

defineOptions({ name: 'GrossanlassEinstellungenStandorte' })

const placeKinds = GA_PLACE_KINDS

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const mapRef = ref<InstanceType<typeof ActivityVenueOverviewBlock> | null>(null)
const places = ref<GaPlace[]>([])
const venueAddressId = ref<string | null>(null)
const newPlaceName = ref('')
const newPlaceKind = ref<GaPlaceKind>('bauprojekt')
const busy = ref(false)

function kindLabelKey(kind: GaPlaceKind): 'Bauprojekt' | 'Unterlager' | 'Matplatz' | 'Anfahrt' | 'Poi' {
  if (kind === 'bauprojekt') return 'Bauprojekt'
  if (kind === 'unterlager') return 'Unterlager'
  if (kind === 'matplatz') return 'Matplatz'
  if (kind === 'anfahrt') return 'Anfahrt'
  return 'Poi'
}

function placeKind(place: GaPlace): GaPlaceKind {
  return gaPlaceKind(place.kind)
}

async function loadPlaces() {
  if (!departmentId.value) return
  try {
    places.value = await listGrossanlassPlaces(departmentId.value)
  } catch {
    places.value = []
  }
}

async function loadVenue() {
  if (!departmentId.value) return
  try {
    const pack = await getGrossanlassPlanung(departmentId.value)
    venueAddressId.value = pack.config.venue_address_id || null
  } catch {
    venueAddressId.value = null
  }
}

async function addPlace() {
  const name = newPlaceName.value.trim()
  if (!name || !departmentId.value) return
  busy.value = true
  try {
    const created = await createGrossanlassPlace(departmentId.value, {
      name,
      kind: newPlaceKind.value,
    })
    places.value = [...places.value, created].sort((a, b) => a.name.localeCompare(b.name))
    newPlaceName.value = ''
    await mapRef.value?.reloadGa?.()
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

onMounted(() => {
  void loadPlaces()
  void loadVenue()
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
.ga-places li { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
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
</style>
