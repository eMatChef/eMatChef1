<template>
  <div class="ga-ok-lage">
    <ELoadingState
      v-if="isLoading"
      variant="page"
      :message="t('grossanlass.dashboard.loading')"
    />

    <v-alert v-else-if="error" type="error" variant="tonal" :text="error" class="mb-4" />

    <template v-else>
      <p class="ga-ok-lage__intro">{{ intro }}</p>

      <div class="ga-ok-lage__stats">
        <router-link :to="einsaetzeLink" class="stat-card stat-card--link">
          <span class="stat-card__value">{{ einsatzRows.length }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statEinsaetze') }}</span>
        </router-link>
        <router-link :to="bauLink" class="stat-card stat-card--link">
          <span class="stat-card__value">{{ bauRows.length }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statBauauftraege') }}</span>
        </router-link>
        <router-link :to="tripsLink" class="stat-card stat-card--link">
          <span class="stat-card__value">{{ tripRows.length }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statTrips') }}</span>
        </router-link>
        <router-link
          v-if="conflictCount > 0"
          :to="konflikteLink"
          class="stat-card stat-card--link stat-card--warn"
        >
          <span class="stat-card__value">{{ conflictCount }}</span>
          <span class="stat-card__label">{{ t('grossanlass.dashboard.statConflicts') }}</span>
        </router-link>
      </div>

      <section class="ga-ok-lage__section">
        <div class="section-header">
          <h2 class="section-title">
            <router-link :to="einsaetzeLink" class="section-title__link">
              {{ t('grossanlass.dashboard.lageEinsaetzeTitle') }}
            </router-link>
          </h2>
          <div class="section-header__actions">
            <router-link :to="createEinsatzLink" class="section-action">
              {{ t('grossanlass.dashboard.createEinsatz') }}
            </router-link>
            <router-link :to="einsaetzeLink" class="section-link">
              {{ t('grossanlass.dashboard.lageAll') }}
            </router-link>
          </div>
        </div>
        <ul v-if="einsatzPreview.length" class="lage-list">
          <li v-for="row in einsatzPreview" :key="row.id">
            <router-link :to="einsaetzeLink" class="lage-row">
              <strong>{{ row.object_name }}</strong>
              <span class="lage-row__meta">{{ rowMeta(row) }}</span>
              <span class="lage-row__when">{{ whenLabel(row) }}</span>
            </router-link>
          </li>
        </ul>
        <p v-else class="lage-empty">{{ t('grossanlass.dashboard.lageEinsaetzeEmpty') }}</p>
      </section>

      <section class="ga-ok-lage__section">
        <div class="section-header">
          <h2 class="section-title">
            <router-link :to="bauLink" class="section-title__link">
              {{ t('grossanlass.dashboard.lageBauTitle') }}
            </router-link>
          </h2>
          <router-link :to="bauLink" class="section-link">
            {{ t('grossanlass.dashboard.lageAll') }}
          </router-link>
        </div>
        <ul v-if="bauPreview.length" class="lage-list">
          <li v-for="row in bauPreview" :key="row.id">
            <router-link :to="bauLink" class="lage-row">
              <strong>{{ row.object_name }}</strong>
              <span class="lage-row__meta">{{ rowMeta(row) }}</span>
              <span class="lage-row__when">{{ whenLabel(row) }}</span>
            </router-link>
          </li>
        </ul>
        <p v-else class="lage-empty">{{ t('grossanlass.dashboard.lageBauEmpty') }}</p>
      </section>

      <section class="ga-ok-lage__section">
        <div class="section-header">
          <h2 class="section-title">
            <router-link :to="tripsLink" class="section-title__link">
              {{ t('grossanlass.dashboard.lageTripsTitle') }}
            </router-link>
          </h2>
          <router-link :to="tripsLink" class="section-link">
            {{ t('grossanlass.dashboard.lageAll') }}
          </router-link>
        </div>
        <ul v-if="tripPreview.length" class="lage-list">
          <li v-for="row in tripPreview" :key="row.id">
            <router-link :to="tripsLink" class="lage-row">
              <strong>{{ row.object_name }}</strong>
              <span class="lage-row__meta">{{ tripMeta(row) }}</span>
              <span class="lage-row__when">{{ whenLabel(row) }}</span>
            </router-link>
          </li>
        </ul>
        <p v-else class="lage-empty">{{ t('grossanlass.dashboard.lageTripsEmpty') }}</p>
      </section>

      <p v-if="conflictCount > 0" class="conflicts-link">
        <router-link :to="konflikteLink">
          {{ t('grossanlass.dashboard.conflictsLink', { count: conflictCount }) }}
        </router-link>
      </p>

      <section class="ga-ok-lage__section">
        <h2 class="section-title">{{ t('grossanlass.dashboard.quickLinksTitle') }}</h2>
        <div class="quick-links">
          <router-link v-if="showAnlassLinks" :to="ressortsLink" class="quick-link-card">
            <v-icon icon="mdi-sitemap" size="22" />
            <span>{{ t('grossanlass.dashboard.linkRessorts') }}</span>
          </router-link>
          <router-link :to="meinRessortLink" class="quick-link-card">
            <v-icon icon="mdi-home-group" size="22" />
            <span>{{ t('sidebar.meinRessort') }}</span>
          </router-link>
          <router-link v-if="showAnlassLinks" :to="planungLink" class="quick-link-card">
            <v-icon icon="mdi-calendar-clock" size="22" />
            <span>{{ t('sidebar.planung') }}</span>
          </router-link>
          <router-link :to="materialUebersichtLink" class="quick-link-card">
            <v-icon icon="mdi-truck-delivery-outline" size="22" />
            <span>{{ t('sidebar.materialUebersicht') }}</span>
          </router-link>
          <router-link v-if="showAnlassLinks" :to="kostenLink" class="quick-link-card">
            <v-icon icon="mdi-cash-multiple" size="22" />
            <span>{{ t('sidebar.kosten') }}</span>
          </router-link>
        </div>
      </section>

      <section v-if="showAnlassLinks && known && hasGuestDepartments" class="ga-ok-lage__section">
        <div class="section-header">
          <h2 class="section-title">{{ t('grossanlass.dashboard.previewParticipantsTitle') }}</h2>
          <router-link :to="teilnehmerLink" class="section-link">
            {{ t('grossanlass.dashboard.participantsAll') }}
          </router-link>
        </div>
        <p class="participants-lead">{{ t('grossanlass.dashboard.participantsLiveText') }}</p>
        <ul v-if="liveParticipants.length" class="participants-list">
          <li v-for="row in liveParticipants" :key="row.id">
            {{ row.name }}
            <span v-if="row.organisation_name" class="participants-list__org">{{ row.organisation_name }}</span>
            · {{ t(`grossanlass.planung.struktur.status.${row.status}`) }}
          </li>
        </ul>
        <p v-else class="participants-empty">{{ t('grossanlass.dashboard.previewParticipantsEmpty') }}</p>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import {
  getGrossanlassPlanung,
  type GrossanlassParticipant,
  type GrossanlassPlanungOverview,
} from '@/api/grossanlassPlanung'
import {
  getGrossanlassUebersicht,
  type GaUebersichtEinsatz,
  type GaUebersichtPayload,
} from '@/api/grossanlassUebersicht'
import { useGrossanlassGuestDepartments } from '@/composables/useGrossanlassGuestDepartments'
import { formatHelperWhenLabel } from '@/views/grossanlass/grossanlassHelperAssignment'
import { gaLageIsOpen, gaLageTaskKind, sortGaLageByStart } from '@/utils/grossanlassLage'

const LIST_LIMIT = 8

const props = withDefaults(defineProps<{
  departmentId: string
  variant?: 'ok' | 'bereich'
}>(), {
  variant: 'ok',
})

const { t, locale } = useI18n()
const authStore = useAuthStore()
const { hasGuestDepartments, known, setHasGuestDepartments } = useGrossanlassGuestDepartments(
  () => props.departmentId,
)

const isLoading = ref(true)
const error = ref('')
const groups = ref<GrossanlassGroup[]>([])
const uebersicht = ref<GaUebersichtPayload | null>(null)
const planung = ref<GrossanlassPlanungOverview | null>(null)
const showAnlassLinks = computed(() => props.variant !== 'bereich')
const intro = computed(() =>
  props.variant === 'bereich'
    ? t('grossanlass.dashboard.bereichIntro')
    : t('grossanlass.dashboard.okIntro'),
)

const einsaetzeLink = computed(() => `/${props.departmentId}/planung/belegung`)
const bauLink = computed(() => `/${props.departmentId}/planung/bauauftraege`)
const createEinsatzLink = computed(() => ({
  path: einsaetzeLink.value,
  query: { book: '1' },
}))
const tripsLink = computed(() => `/${props.departmentId}/planung/transporte`)
const konflikteLink = computed(() => `/${props.departmentId}/planung/konflikte`)
const ressortsLink = computed(() => `/${props.departmentId}/einstellungen/ressorts`)
const planungLink = computed(() => `/${props.departmentId}/planung`)
const materialUebersichtLink = computed(() => `/${props.departmentId}/material-uebersicht`)
const meinRessortLink = computed(() => `/${props.departmentId}/mein-ressort`)
const kostenLink = computed(() => `/${props.departmentId}/kosten`)
const teilnehmerLink = computed(() => `/${props.departmentId}/einstellungen/teilnehmer`)

const openRows = computed(() => {
  const rows = (uebersicht.value?.einsaetze ?? [])
    .filter((row) => gaLageIsOpen(row))
    .slice()
    .sort(sortGaLageByStart)
  const branch = branchIds.value
  if (!branch) return rows
  return rows.filter((row) => {
    if (row.group_id && branch.has(row.group_id)) return true
    const place = places.value.find((item) => item.id === row.destination_place_id)
    return !!(place?.group_id && branch.has(place.group_id))
  })
})

const places = computed(() => uebersicht.value?.places ?? [])

const branchIds = computed(() => {
  if (props.variant !== 'bereich') return null
  const userId = authStore.userId
  if (!userId) return new Set<string>()
  const leaderIds = groups.value
    .filter((group) => group.members?.some((member) => member.user_id === userId && member.is_leader))
    .map((group) => group.id)
  const ids = new Set<string>()
  const queue = [...leaderIds]
  while (queue.length > 0) {
    const id = queue.shift()!
    if (ids.has(id)) continue
    ids.add(id)
    for (const group of groups.value) {
      if (group.parent_id === id) queue.push(group.id)
    }
  }
  return ids
})

const einsatzRows = computed(() =>
  openRows.value.filter((row) => gaLageTaskKind(row, groups.value, places.value) === 'einsatz'),
)
const bauRows = computed(() =>
  openRows.value.filter((row) => gaLageTaskKind(row, groups.value, places.value) === 'bauauftrag'),
)
const tripRows = computed(() =>
  openRows.value.filter((row) => gaLageTaskKind(row, groups.value, places.value) === 'fahrauftrag'),
)

const einsatzPreview = computed(() => einsatzRows.value.slice(0, LIST_LIMIT))
const bauPreview = computed(() => bauRows.value.slice(0, LIST_LIMIT))
const tripPreview = computed(() => tripRows.value.slice(0, LIST_LIMIT))

const conflictCount = computed(() => {
  const conflicts = uebersicht.value?.conflicts ?? []
  if (props.variant !== 'bereich') return conflicts.length
  const ids = new Set(openRows.value.map((row) => row.id))
  return conflicts.filter((conflict) => conflict.einsatz_ids.some((id) => ids.has(id))).length
})
const liveParticipants = computed<GrossanlassParticipant[]>(() => planung.value?.participants ?? [])

function placeName(row: GaUebersichtEinsatz): string {
  const id = row.destination_place_id
  if (!id) return ''
  return uebersicht.value?.places?.find((place) => place.id === id)?.name || ''
}

function rowMeta(row: GaUebersichtEinsatz): string {
  const status = t(`grossanlass.materialUebersicht.status.${row.status}`)
  const parts = [row.ressort, placeName(row), status].filter(Boolean)
  return parts.join(' · ')
}

function tripMeta(row: GaUebersichtEinsatz): string {
  const status = t(`grossanlass.materialUebersicht.status.${row.status}`)
  const dest = placeName(row)
  const parts = [row.ressort, dest, status].filter(Boolean)
  return parts.join(' · ')
}

function whenLabel(row: GaUebersichtEinsatz): string {
  return formatHelperWhenLabel(row.from, row.to, locale.value)
}

async function load() {
  if (!props.departmentId) return
  isLoading.value = true
  error.value = ''
  try {
    const [groupList, overview, pack] = await Promise.all([
      getGrossanlassGroups(props.departmentId),
      getGrossanlassUebersicht(props.departmentId),
      props.variant === 'bereich'
        ? Promise.resolve(null)
        : getGrossanlassPlanung(props.departmentId).catch(() => null),
    ])
    groups.value = groupList
    uebersicht.value = overview
    planung.value = pack
    if (pack) {
      setHasGuestDepartments(pack.config.has_guest_departments === true)
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.dashboard.errorLoad')
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.ga-ok-lage {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.ga-ok-lage__intro {
  margin: 0;
  font-size: 0.95rem;
  color: var(--color-text-muted, #6b7280);
}

.ga-ok-lage__stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr));
  gap: 12px;
}

.stat-card {
  min-width: 0;
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-card__value {
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.2;
  color: var(--color-text, #111827);
  font-variant-numeric: tabular-nums;
}

.stat-card__label {
  font-size: 0.82rem;
  color: var(--color-text-muted, #6b7280);
}

.stat-card--link {
  text-decoration: none;
  color: inherit;
}

.stat-card--link:hover {
  border-color: var(--color-primary, #059669);
}

.stat-card--warn {
  border-color: #fecaca;
}

.stat-card--warn .stat-card__value {
  color: var(--color-error, #b91c1c);
}

.ga-ok-lage__section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.section-header__actions {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.section-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
}

.section-title__link {
  color: inherit;
  text-decoration: none;
}

.section-title__link:hover {
  color: var(--color-primary, #059669);
  text-decoration: underline;
}

.section-link,
.section-action {
  font-size: 0.875rem;
  color: var(--color-primary, #059669);
  text-decoration: none;
}

.section-action {
  font-weight: 600;
}

.section-link:hover,
.section-action:hover {
  text-decoration: underline;
}

.lage-list {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
}

.lage-list li + li {
  border-top: 1px solid var(--color-border, #e5e7eb);
}

.lage-row {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 16px;
  text-decoration: none;
  color: inherit;
}

.lage-row:hover {
  background: var(--color-surface-muted, #f9fafb);
}

.lage-row strong {
  font-size: 0.95rem;
}

.lage-row__meta,
.lage-row__when,
.lage-empty,
.participants-lead,
.participants-empty {
  margin: 0;
  font-size: 0.82rem;
  color: var(--color-text-muted, #6b7280);
}

.lage-empty {
  font-style: italic;
}

.conflicts-link {
  margin: 0;
  font-size: 0.85rem;
}

.conflicts-link a {
  color: var(--color-error, #b91c1c);
  font-weight: 600;
  text-decoration: none;
}

.conflicts-link a:hover {
  text-decoration: underline;
}

.quick-links {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 10px;
}

.quick-link-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 10px;
  text-decoration: none;
  color: inherit;
  font-weight: 500;
  font-size: 0.9rem;
  background: var(--color-surface, #fff);
}

.quick-link-card:hover {
  border-color: var(--color-primary, #059669);
  color: var(--color-primary, #059669);
}

.participants-list {
  margin: 0;
  padding-left: 18px;
  font-size: 0.9rem;
  color: #334155;
}

.participants-list__org {
  color: #64748b;
  font-size: 0.85rem;
}

.participants-list__org::before {
  content: ' · ';
}
</style>
