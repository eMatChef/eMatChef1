<template>
  <div class="dispo">
    <p class="dispo__intro">
      {{ t('grossanlass.dispo.intro') }}
      <v-chip v-if="!canManage" size="x-small" variant="tonal" color="info" class="ml-2">{{ t('grossanlass.dispo.readOnly') }}</v-chip>
    </p>

    <ol class="dispo__flow" :aria-label="t('grossanlass.dispo.flow.label')">
      <li v-for="(step, index) in flowSteps" :key="step">
        <span class="dispo__flow-dot">{{ index + 1 }}</span>{{ t(`grossanlass.dispo.flow.${step}`) }}
      </li>
    </ol>

    <details class="dispo__terms">
      <summary>{{ t('grossanlass.dispo.terms.title') }}</summary>
      <dl>
        <div v-for="term in termKeys" :key="term">
          <dt>{{ t(`grossanlass.dispo.terms.${term}`) }}</dt>
          <dd>{{ t(`grossanlass.dispo.terms.${term}Text`) }}</dd>
        </div>
      </dl>
    </details>

    <section class="dispo__kpis" :aria-label="t('grossanlass.dispo.kpi.label')">
      <div v-for="kpi in kpis" :key="kpi.key" class="kpi" :class="`kpi--${kpi.key}`">
        <strong>{{ kpi.value }}</strong>
        <span>{{ t(`grossanlass.dispo.kpi.${kpi.key}`) }}</span>
      </div>
    </section>

    <details v-if="canManage && suggestions.length" class="dispo__planning" data-testid="dispo-suggestions">
      <summary>
        <v-icon icon="mdi-lightbulb-on-outline" size="16" />
        {{ t('grossanlass.dispo.suggestionsTitle', { n: suggestions.length }) }}
      </summary>
      <p class="dispo__planning-hint">{{ t('grossanlass.dispo.suggestionsHint') }}</p>
      <ul class="dispo__planning-list">
        <li v-for="item in suggestions" :key="item.id" :data-testid="`dispo-suggestion-${item.reason}`">
          <strong>{{ t(`grossanlass.dispo.suggestReason.${item.reason}`) }}</strong>
          <span>{{ suggestionTitles(item.need_ids) }}</span>
          <span>{{ item.starts_at.slice(11, 16) }}–{{ item.ends_at.slice(11, 16) }}</span>
          <EButton size="small" variant="secondary" :disabled="busy" @click="takeSuggestion(item.need_ids)">{{ t('grossanlass.dispo.suggestTake') }}</EButton>
        </li>
      </ul>
    </details>

    <details v-if="openRequests.length" class="dispo__planning" open data-testid="dispo-requests">
      <summary>
        <v-icon icon="mdi-message-alert-outline" size="16" />
        {{ t('grossanlass.dispo.requests.panelTitle', { n: openRequests.length }) }}
      </summary>
      <p class="dispo__planning-hint">{{ t('grossanlass.dispo.requests.planStillValid') }}</p>
      <ul class="dispo__planning-list">
        <li v-for="item in openRequests" :key="item.id" :data-testid="`dispo-open-request-${item.id}`">
          <strong>{{ item.title }}</strong>
          <span>{{ item.requested_by }}{{ item.note ? ` · ${item.note}` : '' }}</span>
          <EButton size="small" variant="secondary" @click="openNeed(item.need_id)">{{ t('grossanlass.dispo.requests.decide') }}</EButton>
        </li>
      </ul>
    </details>

    <v-alert v-if="actionError" type="warning" variant="tonal" density="compact" closable data-testid="dispo-action-error" @click:close="actionError = ''">{{ actionError }}</v-alert>

    <section v-if="canManage && selected.length" class="dispo__selection" data-testid="dispo-selection">
      <span>{{ t('grossanlass.dispo.selected', { n: selected.length }) }}</span>
      <EButton size="small" variant="primary" :disabled="busy" data-testid="dispo-selection-create" @click="createFromSelection">{{ t('grossanlass.dispo.tourFromSelection') }}</EButton>
      <EButton size="small" variant="secondary" @click="selected = []">{{ t('common.cancel') }}</EButton>
    </section>

    <section class="dispo__bar">
      <div class="dispo__filters" role="group" :aria-label="t('grossanlass.dispo.filter.label')">
        <EButton
          v-for="option in viewOptions"
          :key="option"
          size="small"
          :variant="view === option ? 'primary' : 'secondary'"
          @click="view = option"
        >
          {{ t(`grossanlass.dispo.view.${option}`) }}
          <span class="dispo__count">{{ countOf(option) }}</span>
        </EButton>
      </div>
      <v-btn-toggle v-model="mode" mandatory density="compact" color="primary" variant="outlined">
        <v-btn value="list" size="small" prepend-icon="mdi-format-list-bulleted">{{ t('grossanlass.dispo.mode.list') }}</v-btn>
        <v-btn value="timeline" size="small" prepend-icon="mdi-chart-gantt">{{ t('grossanlass.dispo.mode.timeline') }}</v-btn>
        <v-btn value="map" size="small" prepend-icon="mdi-map-outline">{{ t('grossanlass.dispo.mode.map') }}</v-btn>
      </v-btn-toggle>
    </section>

    <v-alert v-if="failed" type="error" variant="tonal" density="compact" data-testid="dispo-error">
      {{ t('grossanlass.dispo.loadFailed') }}
      <template #append>
        <EButton size="small" variant="secondary" @click="reload">{{ t('common.retry') }}</EButton>
      </template>
    </v-alert>

    <!-- Liste -->
    <template v-if="mode === 'list'">
      <EEmptyState
        v-if="!(view === 'tours' ? tours.length : visible.length) && !loading"
        variant="generic"
        icon="mdi-truck-fast-outline"
        :title="t('grossanlass.dispo.emptyTitle')"
        :description="t('grossanlass.dispo.emptyText')"
      />
      <div v-if="view === 'tours'" class="dispo__list" data-testid="dispo-tours">
        <article v-for="row in tours" :key="row.id" class="need" :class="{ 'need--problem': row.conflicts.length }" :data-testid="`dispo-tour-${row.id}`">
          <header class="need__head">
            <span class="need__source"><v-icon icon="mdi-source-branch" size="16" /> {{ row.name }}</span>
            <span class="need__chips">
              <v-chip size="x-small" variant="flat" :color="TOUR_STATUS_COLOR[row.status]">{{ t(`grossanlass.dispo.tourStatus.${row.status}`) }}</v-chip>
              <v-chip v-if="row.conflicts.length" size="x-small" variant="flat" color="error">{{ t('grossanlass.dispo.conflictsCount', { n: row.conflicts.length }) }}</v-chip>
            </span>
          </header>
          <p class="need__meta"><v-icon icon="mdi-steering" size="14" /> {{ driverName(row.chauffeurId) || t('grossanlass.dispo.noDriver') }} · <v-icon icon="mdi-truck-outline" size="14" /> {{ vehicleName(row.vehicleId) || t('grossanlass.dispo.noVehicle') }}</p>
          <p v-if="row.startsAt" class="need__meta"><v-icon icon="mdi-clock-outline" size="14" /> {{ dayName(row.startsAt) }} · {{ clockOf(row.startsAt) }}–{{ row.endsAt ? clockOf(row.endsAt) : '' }}</p>
          <p class="need__meta"><v-icon icon="mdi-package-variant-closed" size="14" /> {{ tourNeedTitles(row.needIds) }}</p>
          <footer class="need__actions">
            <EButton variant="primary" size="small" @click="openTour(row.id)">{{ t('grossanlass.dispo.openTour') }}</EButton>
          </footer>
        </article>
      </div>
      <div v-else class="dispo__list">
        <article v-for="need in visible" :key="need.id" class="need" :class="[`need--${need.priority}`, { 'need--problem': !!need.problem }]">
          <header class="need__head">
            <span class="need__source">
              <input v-if="canManage && !need.tourId && (need.status === 'open' || need.status === 'planned')" v-model="selected" type="checkbox" :value="need.id" :aria-label="t('grossanlass.dispo.select')" :data-testid="`dispo-select-${need.id}`" />
              <v-icon :icon="SOURCE_ICON[need.source]" size="16" /> {{ t(`grossanlass.dispo.source.${need.source}`) }}</span>
            <span class="need__chips">
              <v-chip size="x-small" variant="flat" :color="PRIORITY_COLOR[need.priority]">{{ t(`grossanlass.dispo.priority.${need.priority}`) }}</v-chip>
              <v-chip size="x-small" variant="flat" :color="need.ready ? 'success' : 'grey'">
                {{ need.ready ? t('grossanlass.dispo.ready') : t('grossanlass.dispo.waiting') }}
              </v-chip>
              <v-chip size="x-small" variant="flat" :color="STATUS_COLOR[need.status]">{{ t(`grossanlass.dispo.status.${need.status}`) }}</v-chip>
            </span>
          </header>
          <h4 class="need__title">{{ need.from }} <v-icon icon="mdi-arrow-right" size="16" /> {{ need.to }}</h4>
          <p class="need__meta"><v-icon icon="mdi-clock-outline" size="14" /> {{ dayName(need) }} · {{ need.timingLabel }}
            <span class="need__kind">{{ t(`grossanlass.dispo.timingKind.${need.timingKind}`) }}</span></p>
          <p class="need__meta"><v-icon icon="mdi-package-variant-closed" size="14" /> {{ need.cargo }}</p>
          <p class="need__meta"><v-icon icon="mdi-flag-outline" size="14" /> {{ need.target }}</p>
          <div v-if="need.requirements.length" class="need__reqs">
            <v-chip v-for="req in need.requirements" :key="req" size="x-small" variant="outlined">{{ req }}</v-chip>
          </div>
          <p v-if="need.readyNote && !need.ready" class="need__warn"><v-icon icon="mdi-timer-sand" size="14" /> {{ need.readyNote }}</p>
          <p v-if="need.problem" class="need__problem">
            <v-icon icon="mdi-alert-circle-outline" size="14" /> {{ t(`grossanlass.dispo.problem.${need.problem.kind}`) }}
          </p>
          <p v-if="tourOf(need)" class="need__tour" data-testid="dispo-need-tour-line">
            <v-icon icon="mdi-source-branch" size="14" />
            {{ tourOf(need)!.name }} · {{ driverName(tourOf(need)!.driverId) }} · {{ vehicleName(tourOf(need)!.vehicleId) }}
          </p>
          <p v-else-if="need.chauffeurId" class="need__tour">
            <v-icon icon="mdi-steering" size="14" /> {{ driverName(need.chauffeurId) || t('grossanlass.dispo.driver') }}
          </p>
          <footer class="need__actions">
            <EButton variant="primary" size="small" @click="openNeed(need.id)">
              {{ canManage && !need.tourId && (need.status === 'open' || need.status === 'planned') ? t('grossanlass.dispo.dispose') : t('grossanlass.dispo.open') }}
            </EButton>
            <EButton v-if="tourOf(need)" variant="secondary" size="small" @click="openTour(tourOf(need)!.id)">{{ t('grossanlass.dispo.openTour') }}</EButton>
          </footer>
        </article>
      </div>
    </template>

    <!-- Zeitplan -->
    <section v-else-if="mode === 'timeline'" class="timeline">
      <div class="timeline__axis">
        <span class="timeline__label" />
        <span class="timeline__scale">
          <span v-for="h in hours" :key="h" :style="{ left: pct(h) + '%' }">{{ String(h).padStart(2, '0') }}</span>
        </span>
      </div>
      <div v-for="row in timelineRows" :key="row.id" class="timeline__row">
        <span class="timeline__label">
          <strong>{{ row.label }}</strong>
          <small>{{ row.sub }}</small>
        </span>
        <span class="timeline__track">
          <button
            v-for="block in row.blocks"
            :key="block.id"
            type="button"
            class="timeline__block"
            :class="`timeline__block--${block.tone}`"
            :style="{ left: pct(block.start) + '%', width: Math.max(4, pct(block.end) - pct(block.start)) + '%' }"
            @click="block.onClick()"
          >
            {{ block.label }}
          </button>
        </span>
      </div>
      <p class="timeline__note">{{ t('grossanlass.dispo.timelineDay', { day: dayName(timelineDay) }) }}</p>
    </section>

    <!-- Karte (Schema) -->
    <section v-else class="map">
      <svg viewBox="0 0 640 380" class="map__svg" role="img" :aria-label="t('grossanlass.dispo.mapPlaceholder')">
        <rect width="640" height="380" fill="#e8eef0" />
        <path d="M0 300 Q160 250 320 290 T640 260 V380 H0 Z" fill="#d6e4dc" />
        <g v-for="line in mapLines" :key="line.id">
          <polyline :points="line.points" fill="none" :stroke="line.color" stroke-width="3" :stroke-dasharray="line.dashed ? '6 6' : ''" />
        </g>
        <g v-for="place in mapPlaces" :key="place.name">
          <circle :cx="place.x" :cy="place.y" r="8" fill="#0f766e" stroke="#fff" stroke-width="2" />
          <text :x="place.x + 12" :y="place.y + 4" class="map__text">{{ place.name }}</text>
        </g>
      </svg>
      <p class="map__note">{{ t('grossanlass.dispo.mapPlaceholder') }}</p>
      <ul class="map__legend">
        <li><span class="swatch swatch--tour" /> {{ t('grossanlass.dispo.map.tour') }}</li>
        <li><span class="swatch swatch--need" /> {{ t('grossanlass.dispo.map.need') }}</li>
      </ul>
    </section>

    <GrossanlassDispoBedarfDialog v-model="needOpen" :need-id="needId" @open-tour="openTourFromNeed" />
    <GrossanlassDispoTourDialog v-model="tourOpen" :tour-id="tourId" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassDispoBedarfDialog from './GrossanlassDispoBedarfDialog.vue'
import GrossanlassDispoTourDialog from './GrossanlassDispoTourDialog.vue'
import type { GaDispoView as GaDispoNeedView } from './gaDispoMock'

type GaDispoView = GaDispoNeedView | 'tours'
import {
  dispatchActions,
  liveCounts,
  liveDriverName,
  liveMatchesView,
  liveSortNeeds,
  liveVehicleName,
  loadDispatch,
  useGaDispo,
  type GaDispoLiveNeed,
} from './useGaDispo'
import { placePos } from '@/views/grossanlass/live/gaSitePlaces'
import { PRIORITY_COLOR, SOURCE_ICON, STATUS_COLOR, TOUR_STATUS_COLOR, hourLabel } from './gaDispoUi'

const { t, locale } = useI18n()
const route = useRoute()
const { needs, tours, now, loading, failed, suggestions, canManage, changeRequests } = useGaDispo()
const openRequests = computed(() => (canManage.value ? changeRequests.value.filter((item) => item.status === 'open') : []))
const driverName = liveDriverName
const vehicleName = liveVehicleName
const departmentId = computed(() => String(route.params.departmentId || ''))

/** Deep-Link aus der Planung: `?need=<Fahrauftrag>` öffnet den Bedarf, `?tour=<Tour>` die Tour. */
async function reload() {
  if (!departmentId.value) return
  await loadDispatch(departmentId.value)
  const needQuery = typeof route.query.need === 'string' ? route.query.need : ''
  const tourQuery = typeof route.query.tour === 'string' ? route.query.tour : ''
  if (tourQuery && tours.value.some((row) => row.id === tourQuery)) openTour(tourQuery)
  else if (needQuery && needs.value.some((row) => row.id === needQuery)) openNeed(needQuery)
}
onMounted(reload)

// Disponieren (nur Logistikverantwortliche): Auswahl und Vorschläge legen höchstens einen Tourentwurf an
const selected = ref<string[]>([])
const busy = ref(false)
const actionError = ref('')
async function createDraft(needIds: string[]) {
  busy.value = true
  actionError.value = ''
  const result = await dispatchActions.createTour(departmentId.value, { need_ids: needIds })
  busy.value = false
  if (!result.ok) {
    actionError.value = result.error
    return
  }
  selected.value = []
  view.value = 'tours'
}
function createFromSelection() {
  return createDraft(selected.value)
}
function takeSuggestion(ids: string[]) {
  return createDraft(ids)
}
function suggestionTitles(ids: string[]): string {
  return ids.map((id) => needs.value.find((need) => need.id === id)?.title ?? id).join(' · ')
}
function tourNeedTitles(ids: string[]): string {
  return suggestionTitles(ids)
}
function clockOf(date: Date): string {
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}
watch(departmentId, reload)

const flowSteps = ['need', 'dispo', 'tour', 'qr', 'underway', 'target', 'unload'] as const
const termKeys = ['need', 'dispo', 'tour', 'order'] as const
const viewOptions: GaDispoView[] = ['open', 'planned', 'underway', 'done', 'problems', 'scope', 'tours']

const view = ref<GaDispoView>('open')
const mode = ref<'list' | 'timeline' | 'map'>('list')

const counts = computed(() => liveCounts())
const kpis = computed(() => [
  { key: 'open', value: counts.value.open },
  { key: 'ready', value: counts.value.ready },
  { key: 'urgent', value: counts.value.urgent },
  { key: 'planned', value: counts.value.planned },
  { key: 'underway', value: counts.value.underway },
  { key: 'driversFree', value: counts.value.driversFree },
  { key: 'vehiclesFree', value: counts.value.vehiclesFree },
])

function countOf(option: GaDispoView): number {
  if (option === 'tours') return tours.value.length
  return needs.value.filter((need) => liveMatchesView(need, option)).length
}
const visible = computed(() => (view.value === 'tours' ? [] : liveSortNeeds(needs.value.filter((need) => liveMatchesView(need, view.value as GaDispoNeedView)))))
function tourOf(need: GaDispoLiveNeed) {
  return tours.value.find((tour) => tour.id === need.tourId)
}
function dayName(target: GaDispoLiveNeed | Date): string {
  const date = target instanceof Date ? target : target.startsAt
  const offset = Math.round((new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()
    - new Date(now.value.getFullYear(), now.value.getMonth(), now.value.getDate()).getTime()) / 86_400_000)
  if (offset === 0) return t('grossanlass.dispo.today')
  if (offset === 1) return t('grossanlass.dispo.tomorrow')
  return date.toLocaleDateString(locale.value, { weekday: 'short', day: 'numeric', month: 'short' })
}

// Dialoge
const needOpen = ref(false)
const needId = ref<string | null>(null)
const tourOpen = ref(false)
const tourId = ref<string | null>(null)
function openTour(id: string) {
  tourId.value = id
  tourOpen.value = true
}
function openTourFromNeed(id: string) {
  needOpen.value = false
  openTour(id)
}
function openNeed(id: string) {
  needId.value = id
  needOpen.value = true
}

// Zeitplan (heute, 06–20 Uhr)
const START = 6
const END = 20
const hours = Array.from({ length: (END - START) / 2 + 1 }, (_, index) => START + index * 2)
function pct(hour: number): number {
  return Math.min(100, Math.max(0, ((hour - START) / (END - START)) * 100))
}
function dayKey(date: Date): number {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()
}
/** Heute, sonst der nächste Tag mit offenen oder laufenden Bedarfen. */
const timelineDay = computed(() => {
  const active = needs.value.filter((need) => need.status !== 'done')
  const today = dayKey(now.value)
  if (!active.length || active.some((need) => dayKey(need.startsAt) === today)) return now.value
  const upcoming = active.filter((need) => dayKey(need.startsAt) >= today).sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime())
  return (upcoming[0] ?? active[0]!).startsAt
})
const hourOf = (date: Date) => date.getHours() + date.getMinutes() / 60
const timelineRows = computed(() => {
  const day = dayKey(timelineDay.value)
  const tourRows = tours.value
    .filter((tour) => tour.status !== 'done' && tour.startsAt && tour.endsAt && dayKey(tour.startsAt) === day)
    .map((tour) => ({
      id: tour.id,
      label: `${tour.name} · ${driverName(tour.chauffeurId) || t('grossanlass.dispo.noDriver')}`,
      sub: vehicleName(tour.vehicleId) || t('grossanlass.dispo.noVehicle'),
      blocks: [{
        id: tour.id,
        label: `${clockOf(tour.startsAt!)}–${clockOf(tour.endsAt!)}`,
        start: hourOf(tour.startsAt!),
        end: hourOf(tour.endsAt!),
        tone: tour.conflicts.length ? 'problem' : tour.status === 'draft' ? 'open' : tour.status,
        onClick: () => openTour(tour.id),
      }],
    }))
  const needRows = liveSortNeeds(needs.value.filter((need) => need.status !== 'done' && !need.tourId && dayKey(need.startsAt) === day))
    .map((need) => ({
      id: need.id,
      label: need.title,
      sub: need.chauffeurId ? driverName(need.chauffeurId) : t('grossanlass.dispo.notDisposed'),
      blocks: [{
        id: need.id,
        label: `${hourLabel(need.hour)}–${hourLabel(hourOf(need.endsAt))}`,
        start: need.hour,
        end: hourOf(need.endsAt),
        tone: need.status === 'open' && need.priority === 'urgent' ? 'urgent' : need.status,
        onClick: () => openNeed(need.id),
      }],
    }))
  return [...tourRows, ...needRows]
})

// Karte (Schema)
function pos(place: string) {
  return placePos(place)
}
const mapLines = computed(() => {
  const lines: Array<{ id: string; points: string; color: string; dashed: boolean }> = []
  for (const tour of tours.value.filter((row) => row.status !== 'done')) {
    lines.push({
      id: tour.id,
      points: tour.stops.map((item) => `${pos(item.label).x},${pos(item.label).y}`).join(' '),
      color: tour.conflicts.length ? '#dc2626' : '#059669',
      dashed: false,
    })
  }
  for (const need of needs.value.filter((row) => row.status !== 'done' && !row.tourId)) {
    lines.push({
      id: need.id,
      points: `${pos(need.from).x},${pos(need.from).y} ${pos(need.to).x},${pos(need.to).y}`,
      color: need.status !== 'open' ? '#059669' : need.priority === 'urgent' ? '#f59e0b' : '#94a3b8',
      dashed: need.status === 'open',
    })
  }
  return lines
})
const mapPlaces = computed(() => {
  const names = new Set<string>()
  needs.value.forEach((need) => {
    names.add(need.from)
    names.add(need.to)
  })
  tours.value.forEach((tour) => tour.stops.forEach((item) => item.label && names.add(item.label)))
  return [...names].map((name) => ({ name, ...pos(name) }))
})
</script>

<style scoped>
.dispo {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 4px 0 28px;
}
.dispo__intro {
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
}
.dispo__flow {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 14px;
  margin: 0;
  padding: 10px 12px;
  list-style: none;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  font-size: 0.82rem;
}
.dispo__flow li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.dispo__flow li:not(:last-child)::after {
  content: '→';
  margin-left: 8px;
  color: #94a3b8;
}
.dispo__flow-dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #ecfdf5;
  color: #065f46;
  font-size: 0.7rem;
  font-weight: 700;
}
.dispo__terms {
  font-size: 0.86rem;
}
.dispo__terms summary {
  cursor: pointer;
  color: #475569;
  font-weight: 600;
}
.dispo__terms dl {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 10px;
  margin: 10px 0 0;
}
.dispo__terms dt {
  font-weight: 700;
}
.dispo__terms dd {
  margin: 0;
  color: #64748b;
}
.dispo__planning {
  padding: 10px 14px;
  border: 1px solid #bae6fd;
  border-radius: 12px;
  background: #f0f9ff;
  font-size: 0.88rem;
}
.dispo__planning summary {
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  font-weight: 600;
}
.dispo__planning-hint {
  margin: 8px 0;
  color: #64748b;
}
.dispo__selection {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  padding: 10px 14px;
  border: 1px solid #a7f3d0;
  border-radius: 12px;
  background: #ecfdf5;
  font-size: 0.88rem;
}
.dispo__planning-list {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.dispo__planning-list li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 12px;
  padding: 6px 0;
  border-bottom: 1px solid #e0f2fe;
}
.dispo__kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 10px;
}
.kpi {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.kpi strong {
  font-size: 1.6rem;
  line-height: 1.1;
}
.kpi span {
  font-size: 0.78rem;
  color: #64748b;
}
.kpi--urgent strong {
  color: #b91c1c;
}
.kpi--ready strong {
  color: #15803d;
}
.dispo__bar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}
.dispo__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.dispo__count {
  margin-left: 6px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.1);
  font-size: 0.72rem;
}
.dispo__list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 12px;
}
.need {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #94a3b8;
  border-radius: 12px;
  background: #fff;
}
.need--urgent {
  border-left-color: #dc2626;
}
.need--normal {
  border-left-color: #059669;
}
.need--problem {
  background: #fef2f2;
}
.need__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 6px;
}
.need__source {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.need__chips {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 4px;
}
.need__title {
  margin: 0;
  font-size: 1rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.need__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 0.84rem;
  color: #334155;
}
.need__kind {
  padding: 0 6px;
  border-radius: 6px;
  background: #f1f5f9;
  font-size: 0.72rem;
}
.need__reqs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.need__warn {
  margin: 0;
  font-size: 0.82rem;
  color: #b45309;
}
.need__problem {
  margin: 0;
  font-size: 0.82rem;
  color: #b91c1c;
  font-weight: 600;
}
.need__tour {
  margin: 0;
  font-size: 0.82rem;
  color: #0f766e;
  font-weight: 600;
}
.need__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}
.timeline {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  overflow-x: auto;
}
.timeline__axis,
.timeline__row {
  display: grid;
  grid-template-columns: 180px minmax(520px, 1fr);
  gap: 10px;
  align-items: center;
}
.timeline__label {
  display: flex;
  flex-direction: column;
  font-size: 0.84rem;
}
.timeline__label small {
  color: #64748b;
}
.timeline__scale,
.timeline__track {
  position: relative;
  height: 36px;
}
.timeline__scale span {
  position: absolute;
  top: 0;
  font-size: 0.72rem;
  color: #64748b;
  transform: translateX(-50%);
}
.timeline__track {
  background: repeating-linear-gradient(90deg, #f1f5f9 0, #f1f5f9 1px, transparent 1px, transparent 14.28%);
  border-radius: 8px;
}
.timeline__block {
  position: absolute;
  top: 4px;
  height: 28px;
  padding: 0 8px;
  overflow: hidden;
  border: 0;
  border-radius: 8px;
  color: #fff;
  font-size: 0.72rem;
  white-space: nowrap;
  cursor: pointer;
}
.timeline__block--confirmed,
.timeline__block--planned {
  background: #2563eb;
}
.timeline__block--underway {
  background: #059669;
}
.timeline__block--problem,
.timeline__block--urgent {
  background: #dc2626;
}
.timeline__block--open {
  background: #f59e0b;
}
.timeline__note {
  margin: 0;
  font-size: 0.78rem;
  color: #64748b;
}
.map {
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.map__svg {
  width: 100%;
  height: auto;
  border-radius: 10px;
}
.map__text {
  font-size: 12px;
  fill: #0f172a;
}
.map__note {
  margin: 8px 0 0;
  color: #64748b;
  font-size: 0.8rem;
}
.map__legend {
  display: flex;
  gap: 16px;
  margin: 8px 0 0;
  padding: 0;
  list-style: none;
  font-size: 0.8rem;
}
.swatch {
  display: inline-block;
  width: 24px;
  height: 0;
  margin-right: 6px;
  vertical-align: middle;
}
.swatch--tour {
  border-top: 3px solid #059669;
}
.swatch--need {
  border-top: 3px dashed #f59e0b;
}
@media (max-width: 720px) {
  .dispo__list {
    grid-template-columns: 1fr;
  }
}
</style>
