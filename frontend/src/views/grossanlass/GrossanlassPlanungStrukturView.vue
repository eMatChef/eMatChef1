<template>
  <div class="ga-live-page">
    <p class="intro">{{ t('grossanlass.planung.struktur.intro') }}</p>
    <ELoadingState v-if="loading" variant="list" :message="t('common.loading')" />
    <p v-else-if="error" class="warn">{{ error }}</p>
    <template v-else>
      <section class="card">
        <h3>{{ t('grossanlass.planung.struktur.modusTitle') }}</h3>
        <p class="hint">{{ t('grossanlass.planung.struktur.modusLead') }}</p>
        <div class="modus-grid">
          <button
            v-for="modus in modi"
            :key="modus"
            type="button"
            class="modus-card"
            :class="{ 'is-active': pack?.config.struktur_modus === modus }"
            :disabled="!pack?.can_manage || saving"
            @click="setModus(modus)"
          >
            <strong>{{ t(`grossanlass.planung.struktur.modus${cap(modus)}`) }}</strong>
            <span>{{ t(`grossanlass.planung.struktur.modusHelp${cap(modus)}`) }}</span>
          </button>
        </div>
        <p class="example">{{ t(`grossanlass.planung.struktur.modusExample${cap(pack?.config.struktur_modus || 'offen')}`) }}</p>
      </section>

      <section class="card">
        <div class="head">
          <h3>{{ t('grossanlass.planung.tabRessorts') }}</h3>
          <router-link :to="`/${departmentId}/einstellungen/ressorts`">
            {{ t('grossanlass.planung.struktur.openRessorts') }}
          </router-link>
        </div>
        <p class="hint">{{ t('grossanlass.planung.struktur.ressortsHint') }}</p>
        <ul v-if="pack?.ressorts.length" class="plain-list">
          <li v-for="row in pack.ressorts" :key="row.id">
            <strong>{{ row.name }}</strong>
            <span class="meta">{{ t('grossanlass.planung.struktur.memberCount', { count: row.member_count }) }}</span>
          </li>
        </ul>
        <p v-else class="hint">{{ t('grossanlass.planung.ressorts.emptyTitle') }}</p>
      </section>

      <section class="card">
        <div class="head">
          <h3>{{ t('grossanlass.planung.struktur.unterlagerTitle') }}</h3>
          <router-link :to="`/${departmentId}/einstellungen/standorte`">
            {{ t('grossanlass.planung.stammdaten.openStandorte') }}
          </router-link>
        </div>
        <p class="hint">{{ t('grossanlass.planung.struktur.unterlagerHint') }}</p>
        <ul v-if="pack?.storage_locations.length" class="plain-list">
          <li v-for="row in pack.storage_locations" :key="row.id">
            <strong>{{ row.name }}</strong>
            <span v-if="row.is_primary" class="tag">{{ t('grossanlass.planung.struktur.primarySite') }}</span>
          </li>
        </ul>
        <p v-else class="hint">{{ t('grossanlass.planung.struktur.unterlagerEmpty') }}</p>
      </section>

      <section class="card">
        <h3>{{ t('grossanlass.planung.struktur.participantsTitle') }}</h3>
        <p class="hint">{{ t('grossanlass.planung.struktur.participantsLater') }}</p>
        <div v-if="pack?.can_manage" class="search">
          <label class="sr-only" for="ga-guest-search">{{ t('grossanlass.planung.struktur.searchLabel') }}</label>
          <input
            id="ga-guest-search"
            v-model="searchQuery"
            type="search"
            class="search-input"
            :placeholder="t('grossanlass.planung.struktur.searchPlaceholder')"
            autocomplete="off"
            @input="onSearchInput"
          >
          <ul v-if="hits.length" class="hits">
            <li v-for="hit in hits" :key="hit.id">
              <button type="button" :disabled="saving" @click="addGuest(hit.id)">
                <strong>{{ hit.name }}</strong>
                <span class="meta">{{ hit.organisation_name }}</span>
              </button>
            </li>
          </ul>
          <p v-else-if="searchQuery.trim().length >= 2 && !searching" class="hint">
            {{ t('grossanlass.planung.struktur.searchEmpty') }}
          </p>
        </div>
        <ul v-if="pack?.participants?.length" class="plain-list">
          <li v-for="row in pack.participants" :key="row.id">
            <div class="guest">
              <strong>{{ row.name }}</strong>
              <span class="meta">{{ row.organisation_name }}</span>
            </div>
            <span class="tag">{{ t(`grossanlass.planung.struktur.status.${row.status}`) }}</span>
            <button
              v-if="pack.can_manage && row.status !== 'accepted'"
              type="button"
              class="remove"
              :disabled="saving"
              @click="removeGuest(row.id)"
            >
              {{ t('common.remove') }}
            </button>
          </li>
        </ul>
        <p v-else class="hint">{{ t('grossanlass.planung.struktur.participantsEmpty') }}</p>
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
  addGrossanlassParticipant,
  getGrossanlassPlanung,
  removeGrossanlassParticipant,
  searchGrossanlassGuests,
  updateGrossanlassPlanung,
  type GrossanlassGuestSearchHit,
  type GrossanlassPlanungOverview,
  type GrossanlassStrukturModus,
} from '@/api/grossanlassPlanung'

defineOptions({ name: 'GrossanlassPlanungStruktur' })

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)
const modi: GrossanlassStrukturModus[] = ['offen', 'verschachtelt', 'parallel']
const pack = ref<GrossanlassPlanungOverview | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const searchQuery = ref('')
const hits = ref<GrossanlassGuestSearchHit[]>([])
const searching = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | null = null

function cap(modus: string): string {
  return modus.charAt(0).toUpperCase() + modus.slice(1)
}

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

function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer)
  const q = searchQuery.value.trim()
  if (q.length < 2) {
    hits.value = []
    return
  }
  searchTimer = setTimeout(() => {
    void runSearch(q)
  }, 280)
}

async function runSearch(q: string) {
  if (!departmentId.value) return
  searching.value = true
  try {
    hits.value = await searchGrossanlassGuests(departmentId.value, q)
  } catch {
    hits.value = []
  } finally {
    searching.value = false
  }
}

async function addGuest(guestId: string) {
  if (!departmentId.value) return
  saving.value = true
  try {
    pack.value = await addGrossanlassParticipant(departmentId.value, guestId)
    searchQuery.value = ''
    hits.value = []
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

async function removeGuest(participantId: string) {
  if (!departmentId.value) return
  saving.value = true
  try {
    pack.value = await removeGrossanlassParticipant(departmentId.value, participantId)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

async function setModus(modus: GrossanlassStrukturModus) {
  if (!departmentId.value || pack.value?.config.struktur_modus === modus) return
  saving.value = true
  try {
    pack.value = await updateGrossanlassPlanung(departmentId.value, { struktur_modus: modus })
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
}
.card h3 { margin: 0 0 10px; font-size: 0.95rem; }
.head { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px; }
.head h3 { margin: 0; }
.head a { font-size: 0.85rem; color: #166534; }
.modus-grid { display: grid; gap: 10px; }
@media (min-width: 720px) {
  .modus-grid { grid-template-columns: 1fr 1fr 1fr; }
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
.modus-card strong { font-size: 0.95rem; }
.modus-card span { font-size: 0.8rem; line-height: 1.35; color: #64748b; }
.modus-card:disabled { cursor: default; }
.modus-card.is-active {
  border-color: #86efac;
  background: #ecfdf5;
}
.modus-card.is-active strong { color: #166534; }
.example {
  margin: 12px 0 0;
  font-size: 0.82rem;
  color: #334155;
  background: #f8fafc;
  border-radius: 8px;
  padding: 10px 12px;
}
.plain-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.plain-list li { display: flex; gap: 8px; align-items: center; font-size: 0.9rem; flex-wrap: wrap; }
.guest { display: flex; flex-direction: column; gap: 2px; flex: 1; }
.search { margin: 10px 0 12px; }
.search-input {
  width: 100%;
  max-width: 420px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 0.9rem;
}
.hits { list-style: none; margin: 8px 0 0; padding: 0; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; max-width: 420px; }
.hits button {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 8px 10px;
  background: #fff;
  border: 0;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  text-align: left;
}
.hits button:hover { background: #f8fafc; }
.remove {
  margin-left: auto;
  border: 0;
  background: transparent;
  color: #9a3412;
  cursor: pointer;
  font-size: 0.8rem;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}
.meta { color: #64748b; font-size: 0.8rem; }
.tag {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  background: #ecfdf5;
  color: #166534;
}
</style>
