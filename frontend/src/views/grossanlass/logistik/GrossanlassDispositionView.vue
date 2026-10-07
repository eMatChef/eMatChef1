<template>
  <div class="dispo">
    <p class="dispo__intro">
      {{ t('grossanlass.dispo.intro') }}
      <v-chip size="x-small" variant="tonal" color="warning" class="ml-2">{{ t('grossanlass.dispo.prototype') }}</v-chip>
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

    <details v-if="planningNeeds.length" class="dispo__planning">
      <summary>
        <v-icon icon="mdi-source-branch" size="16" />
        {{ t('grossanlass.dispo.planning.title', { n: planningNeeds.length }) }}
      </summary>
      <p class="dispo__planning-hint">{{ t('grossanlass.dispo.planning.hint') }}</p>
      <ul class="dispo__planning-list">
        <li v-for="need in planningNeeds" :key="need.id">
          <strong>{{ need.qty > 1 ? `${need.qty}× ` : '' }}{{ need.label }}</strong>
          <span>{{ orderTitle(need.orderId) }}</span>
          <span>{{ t('grossanlass.dispo.planning.window', { when: need.planned ? when(need.planned.from, need.planned.to) : when(need.earliest, need.latest) }) }}</span>
          <v-chip size="x-small" variant="flat" :color="need.status === 'planned' ? 'success' : need.status === 'proposal' ? 'info' : 'warning'">
            {{ t(`grossanlass.auftraege.res.status.${need.status}`) }}
          </v-chip>
        </li>
      </ul>
    </details>

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

    <!-- Liste -->
    <template v-if="mode === 'list'">
      <EEmptyState
        v-if="!visible.length"
        variant="generic"
        icon="mdi-truck-fast-outline"
        :title="t('grossanlass.dispo.emptyTitle')"
        :description="t('grossanlass.dispo.emptyText')"
      />
      <div class="dispo__list">
        <article v-for="need in visible" :key="need.id" class="need" :class="[`need--${need.priority}`, { 'need--problem': hasProblem(need) }]">
          <header class="need__head">
            <span class="need__source"><v-icon :icon="SOURCE_ICON[need.source]" size="16" /> {{ t(`grossanlass.dispo.source.${need.source}`) }}</span>
            <span class="need__chips">
              <v-chip size="x-small" variant="flat" :color="PRIORITY_COLOR[need.priority]">{{ t(`grossanlass.dispo.priority.${need.priority}`) }}</v-chip>
              <v-chip size="x-small" variant="flat" :color="need.ready ? 'success' : 'grey'">
                {{ need.ready ? t('grossanlass.dispo.ready') : t('grossanlass.dispo.waiting') }}
              </v-chip>
              <v-chip size="x-small" variant="flat" :color="STATUS_COLOR[need.status]">{{ t(`grossanlass.dispo.status.${need.status}`) }}</v-chip>
            </span>
          </header>
          <h4 class="need__title">{{ need.from }} <v-icon icon="mdi-arrow-right" size="16" /> {{ need.to }}</h4>
          <p class="need__meta"><v-icon icon="mdi-clock-outline" size="14" /> {{ dayName(need.dayOffset) }} · {{ need.timingLabel }}
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
          <p v-if="tourOf(need)" class="need__tour">
            <v-icon icon="mdi-source-branch" size="14" />
            {{ tourOf(need)!.name }} · {{ driverName(tourOf(need)!.driverId) }} · {{ vehicleName(tourOf(need)!.vehicleId) }}
          </p>
          <footer class="need__actions">
            <EButton variant="primary" size="small" @click="openNeed(need.id)">
              {{ need.status === 'open' ? t('grossanlass.dispo.dispose') : t('grossanlass.dispo.open') }}
            </EButton>
            <EButton v-if="tourOf(need)" variant="secondary" size="small" @click="openTour(tourOf(need)!.id)">
              {{ t('grossanlass.dispo.openTour') }}
            </EButton>
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
      <p class="timeline__note">{{ t('grossanlass.dispo.timelineNote') }}</p>
    </section>

    <!-- Karte (Mock) -->
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

    <GrossanlassDispoBedarfDialog v-model="needOpen" :need-id="needId" @open-tour="openTour" />
    <GrossanlassDispoTourDialog v-model="tourOpen" :tour-id="tourId" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassDispoBedarfDialog from './GrossanlassDispoBedarfDialog.vue'
import GrossanlassDispoTourDialog from './GrossanlassDispoTourDialog.vue'
import {
  dispoCounts,
  driverName,
  hasProblem,
  matchesView,
  sortNeeds,
  tourById,
  useGaDispoMock,
  vehicleName,
  type GaDispoNeed,
  type GaDispoView,
} from './gaDispoMock'
import { placePos } from '@/views/grossanlass/live/gaSitePlaces'
import { orderById } from '@/views/grossanlass/auftraege/gaAuftraegeMock'
import { GA_LOGISTICS_RESOURCE_TYPES, useGaRessourcenMock } from '@/views/grossanlass/ressourcen/gaRessourcenMock'
import { PRIORITY_COLOR, SOURCE_ICON, STATUS_COLOR, hourLabel } from './gaDispoUi'

const { t, locale } = useI18n()
const { needs, tours } = useGaDispoMock()

const { needs: resourceNeeds } = useGaRessourcenMock()
/** Dieselben Anforderungen wie in der Planung (Aufträge/Bauaufträge), nur die Logistik-relevanten. */
const planningNeeds = computed(() => resourceNeeds.value.filter((need) => GA_LOGISTICS_RESOURCE_TYPES.includes(need.type)))
function orderTitle(orderId: string): string {
  return orderById(orderId)?.title ?? ''
}
function when(from: Date, to: Date): string {
  const opts: Intl.DateTimeFormatOptions = { weekday: 'short', day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' }
  return `${from.toLocaleString(locale.value, opts)} – ${to.toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit' })}`
}

const flowSteps = ['need', 'dispo', 'tour', 'qr', 'underway', 'target', 'unload'] as const
const termKeys = ['need', 'dispo', 'tour', 'order'] as const
const viewOptions: GaDispoView[] = ['open', 'planned', 'underway', 'done', 'problems', 'scope']

const view = ref<GaDispoView>('open')
const mode = ref<'list' | 'timeline' | 'map'>('list')

const counts = computed(() => dispoCounts())
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
  return needs.value.filter((need) => matchesView(need, option)).length
}
const visible = computed(() => sortNeeds(needs.value.filter((need) => matchesView(need, view.value))))
function tourOf(need: GaDispoNeed) {
  return tourById(need.tourId)
}
function dayName(offset: number): string {
  const date = new Date()
  date.setDate(date.getDate() + offset)
  if (offset === 0) return t('grossanlass.dispo.today')
  if (offset === 1) return t('grossanlass.dispo.tomorrow')
  return date.toLocaleDateString(locale.value, { weekday: 'short', day: 'numeric', month: 'short' })
}

// Dialoge
const needOpen = ref(false)
const needId = ref<string | null>(null)
const tourOpen = ref(false)
const tourId = ref<string | null>(null)
function openNeed(id: string) {
  needId.value = id
  needOpen.value = true
}
function openTour(id: string) {
  tourId.value = id
  tourOpen.value = true
}

// Zeitplan (heute, 06–20 Uhr)
const START = 6
const END = 20
const hours = Array.from({ length: (END - START) / 2 + 1 }, (_, index) => START + index * 2)
function pct(hour: number): number {
  return Math.min(100, Math.max(0, ((hour - START) / (END - START)) * 100))
}
type Block = { id: string; label: string; start: number; end: number; tone: string; onClick: () => void }
const timelineRows = computed(() => {
  const rows = tours.value
    .filter((tour) => tour.status !== 'done')
    .map((tour) => ({
      id: tour.id,
      label: `${tour.name} · ${driverName(tour.driverId)}`,
      sub: `${vehicleName(tour.vehicleId)}${tour.trailer ? ` + ${tour.trailer}` : ''}`,
      blocks: [{
        id: tour.id,
        label: `${hourLabel(tour.startHour)}–${hourLabel(tour.endHour)}${tour.delayMin ? ` (+${tour.delayMin})` : ''}`,
        start: tour.startHour,
        end: tour.endHour,
        tone: tour.problem ? 'problem' : tour.status,
        onClick: () => openTour(tour.id),
      } as Block],
    }))
  const open = needs.value.filter((need) => need.status === 'open' && need.dayOffset === 0)
  rows.push({
    id: 'open',
    label: t('grossanlass.dispo.notDisposed'),
    sub: t('grossanlass.dispo.todayOnly'),
    blocks: open.map((need) => ({
      id: need.id,
      label: need.to,
      start: need.hour - 1,
      end: need.hour,
      tone: need.priority === 'urgent' ? 'urgent' : 'open',
      onClick: () => openNeed(need.id),
    })),
  })
  return rows
})

// Karte (Mock)
function pos(place: string) {
  return placePos(place)
}
const mapLines = computed(() => {
  const lines: Array<{ id: string; points: string; color: string; dashed: boolean }> = []
  for (const tour of tours.value.filter((row) => row.status !== 'done')) {
    lines.push({
      id: tour.id,
      points: tour.stops.map((item) => `${pos(item.place).x},${pos(item.place).y}`).join(' '),
      color: tour.problem ? '#dc2626' : '#059669',
      dashed: false,
    })
  }
  for (const need of needs.value.filter((row) => row.status === 'open')) {
    lines.push({
      id: need.id,
      points: `${pos(need.from).x},${pos(need.from).y} ${pos(need.to).x},${pos(need.to).y}`,
      color: need.priority === 'urgent' ? '#f59e0b' : '#94a3b8',
      dashed: true,
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
