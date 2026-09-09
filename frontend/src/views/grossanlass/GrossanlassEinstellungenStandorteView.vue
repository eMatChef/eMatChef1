<template>
  <div class="ga-standorte-page">
    <p class="ga-standorte-page__intro">{{ t('grossanlass.einstellungen.standorteIntro') }}</p>
    <div v-if="departmentId" class="ga-standorte-page__panel">
      <DepartmentAddressKindPanel :department-id="departmentId" address-kind="storage" />
    </div>

    <section class="ga-places">
      <div class="ga-places__head">
        <h3>{{ t('grossanlass.einstellungen.placesTitle') }}</h3>
        <form class="ga-places__add" @submit.prevent="addPlace">
          <input v-model="newPlaceName" type="text" :placeholder="t('grossanlass.einstellungen.placesName')">
          <EButton variant="primary" size="small" type="submit" :loading="busy">
            {{ t('grossanlass.einstellungen.placesAdd') }}
          </EButton>
        </form>
      </div>
      <p class="ga-standorte-page__intro">{{ t('grossanlass.einstellungen.placesHint') }}</p>
      <ul>
        <li v-for="place in places" :key="place.id">
          <strong>{{ place.name }}</strong>
          <a :href="place.qr_url" target="_blank" rel="noopener">{{ place.public_code }}</a>
        </li>
      </ul>
      <p v-if="!places.length" class="empty">{{ t('grossanlass.einstellungen.placesEmpty') }}</p>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { EButton } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import DepartmentAddressKindPanel from '@/components/settings/DepartmentAddressKindPanel.vue'
import { createGrossanlassPlace, listGrossanlassPlaces, type GaPlace } from '@/api/grossanlassLogistics'

defineOptions({ name: 'GrossanlassEinstellungenStandorte' })

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const places = ref<GaPlace[]>([])
const newPlaceName = ref('')
const busy = ref(false)

async function loadPlaces() {
  if (!departmentId.value) return
  try {
    places.value = await listGrossanlassPlaces(departmentId.value)
  } catch {
    places.value = []
  }
}

async function addPlace() {
  const name = newPlaceName.value.trim()
  if (!name || !departmentId.value) return
  busy.value = true
  try {
    const created = await createGrossanlassPlace(departmentId.value, { name })
    places.value = [...places.value, created].sort((a, b) => a.name.localeCompare(b.name))
    newPlaceName.value = ''
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.placesAddError'))
  } finally {
    busy.value = false
  }
}

onMounted(loadPlaces)
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
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}

.ga-places {
  margin-top: 20px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
.ga-places__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
}
.ga-places__head h3 { margin: 0; font-size: 1rem; }
.ga-places__add { display: flex; gap: 8px; }
.ga-places__add input {
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
}
.ga-places ul { list-style: none; margin: 12px 0 0; padding: 0; display: grid; gap: 8px; }
.ga-places li { display: flex; gap: 12px; align-items: center; }
.empty { color: #94a3b8; font-size: 0.85rem; }
</style>
