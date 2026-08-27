<template>
  <div class="ga-live-page">
    <p class="intro">{{ t('grossanlass.planung.activities.intro') }}</p>
    <ELoadingState v-if="loading" variant="list" :message="t('common.loading')" />
    <p v-else-if="error" class="warn">{{ error }}</p>
    <template v-else>
      <section class="card">
        <h3>{{ t('grossanlass.planung.activities.guestTitle') }}</h3>
        <p class="hint">{{ t('grossanlass.planung.activities.guestLead') }}</p>
        <div class="modus-grid">
          <button
            type="button"
            class="modus-card"
            :class="{ 'is-active': guestType === 'camp' }"
            :disabled="!pack?.can_manage || saving"
            @click="setGuestType('camp')"
          >
            <strong>{{ t('grossanlass.planung.activities.guestCamp') }}</strong>
            <span>{{ t('grossanlass.planung.activities.guestCampHelp') }}</span>
          </button>
          <button
            type="button"
            class="modus-card"
            :class="{ 'is-active': guestType === 'event' }"
            :disabled="!pack?.can_manage || saving"
            @click="setGuestType('event')"
          >
            <strong>{{ t('grossanlass.planung.activities.guestEvent') }}</strong>
            <span>{{ t('grossanlass.planung.activities.guestEventHelp') }}</span>
          </button>
        </div>
      </section>

      <section class="card">
        <h3>{{ t('grossanlass.planung.activities.internalTitle') }}</h3>
        <p class="hint">{{ t('grossanlass.planung.activities.internalHelp') }}</p>
        <p class="actions">
          <router-link :to="`/${departmentId}/einstellungen/ressorts`">
            {{ t('grossanlass.planung.struktur.openRessorts') }}
          </router-link>
        </p>
        <p v-if="periodLabel" class="meta">{{ t('grossanlass.planung.activities.anchorPeriod', { period: periodLabel }) }}</p>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  getGrossanlassPlanung,
  updateGrossanlassPlanung,
  type GrossanlassGuestActivityType,
  type GrossanlassPlanungOverview,
} from '@/api/grossanlassPlanung'

defineOptions({ name: 'GrossanlassPlanungActivities' })

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)
const pack = ref<GrossanlassPlanungOverview | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')

const guestType = computed<GrossanlassGuestActivityType>(
  () => pack.value?.config.guest_activity_type === 'event' ? 'event' : 'camp',
)

const periodLabel = computed(() => {
  const start = pack.value?.config.planned_event_start
  if (!start) return ''
  const from = new Date(start).toLocaleDateString('de-CH')
  const end = pack.value?.config.planned_event_end
  if (!end) return from
  return `${from} – ${new Date(end).toLocaleDateString('de-CH')}`
})

async function load() {
  if (!departmentId.value) return
  loading.value = true
  error.value = ''
  try {
    pack.value = await getGrossanlassPlanung(departmentId.value)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.beschaffung.anfragen.loadError')
  } finally {
    loading.value = false
  }
}

async function setGuestType(type: GrossanlassGuestActivityType) {
  if (!departmentId.value || guestType.value === type) return
  saving.value = true
  try {
    pack.value = await updateGrossanlassPlanung(departmentId.value, { guest_activity_type: type })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.ga-live-page { padding: 4px 0 24px; }
.intro, .hint { margin: 0 0 10px; color: #64748b; font-size: 0.9rem; }
.warn { color: #9a3412; }
.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 14px;
  max-width: 720px;
}
.card h3 { margin: 0 0 10px; font-size: 0.95rem; }
.modus-grid { display: grid; gap: 10px; }
@media (min-width: 640px) {
  .modus-grid { grid-template-columns: 1fr 1fr; }
}
.modus-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  text-align: left;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: #f8fafc;
  cursor: pointer;
  color: #334155;
}
.modus-card span { font-size: 0.8rem; line-height: 1.35; color: #64748b; }
.modus-card:disabled { cursor: default; }
.modus-card.is-active {
  border-color: #86efac;
  background: #ecfdf5;
}
.modus-card.is-active strong { color: #166534; }
.actions { margin: 0; }
.actions a { color: #166534; font-size: 0.9rem; }
.meta { margin: 10px 0 0; font-size: 0.82rem; color: #64748b; }
</style>
