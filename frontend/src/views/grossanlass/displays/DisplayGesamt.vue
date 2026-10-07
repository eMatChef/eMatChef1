<template>
  <div class="all">
    <section class="all__map">
      <h3>{{ t('grossanlass.displays.gesamt.map') }}</h3>
      <GaSiteMap
        dark
        :label="t('grossanlass.displays.projekt.mapLabel')"
        :places="['Zentrallager', 'Lager A', 'Häberli Holz', 'Zeltbau AG', 'Festgelände Süd']"
        :projects="mapProjects"
        :trips="data.logistics.trips"
        :estimate-label="t('grossanlass.displays.logistik.estimated')"
        :gps-label="t('grossanlass.displays.logistik.gps')"
      />
    </section>

    <section class="all__now">
      <h3>{{ t('grossanlass.displays.gesamt.now') }}</h3>
      <ul>
        <li v-for="task in data.running" :key="task.id"><v-icon icon="mdi-play-circle" color="success" size="28" /><span><strong>{{ task.title }}</strong><small>{{ task.origin[task.origin.length - 1] }} · {{ task.people.join(', ') || '–' }} · {{ taskProgress(task) }} %</small></span></li>
        <li v-for="trip in underway" :key="trip.task.id"><v-icon icon="mdi-truck-fast" color="info" size="28" /><span><strong>{{ trip.from }} → {{ trip.to }}</strong><small>{{ trip.driver }} · {{ trip.vehicle }} · {{ trip.stepKey ? t(`grossanlass.aufgaben.route.steps.${trip.stepKey}`) : '' }}<template v-if="trip.eta"> · ETA {{ clock(trip.eta) }}</template></small></span></li>
        <li v-if="!data.running.length && !underway.length" class="muted">{{ t('grossanlass.displays.gesamt.nothingNow') }}</li>
      </ul>
    </section>

    <section class="all__next">
      <h3>{{ t('grossanlass.displays.gesamt.next') }}</h3>
      <ul>
        <li v-for="task in data.next" :key="task.id"><time>{{ stamp(task.startsAt) }}</time><span>{{ task.title }}<small>{{ task.origin[task.origin.length - 1] }}</small></span></li>
        <li v-for="res in nextResources" :key="res.id" class="res"><time>{{ stamp(res.planned?.from ?? res.earliest) }}</time><span>{{ res.label }}<small>{{ t(`grossanlass.auftraege.res.status.${res.status}`) }}</small></span></li>
      </ul>
    </section>

    <section class="all__kpis">
      <div class="k"><strong>{{ data.tasks.progress }}</strong><span>{{ t('grossanlass.displays.gesamt.tasksRunning') }}</span></div>
      <div class="k"><strong>{{ data.tasks.open }}</strong><span>{{ t('grossanlass.displays.gesamt.tasksOpen') }}</span></div>
      <div class="k k--amber"><strong>{{ data.material.kpis.missingItems }}</strong><span>{{ t('grossanlass.displays.gesamt.materialMissing') }}</span></div>
      <div class="k k--blue"><strong>{{ data.material.kpis.waitingTransport }}</strong><span>{{ t('grossanlass.displays.gesamt.materialWaiting') }}</span></div>
      <div class="k k--green"><strong>{{ data.logistics.kpis.underway }}</strong><span>{{ t('grossanlass.displays.gesamt.underway') }}</span></div>
      <div class="k k--red"><strong>{{ data.logistics.kpis.urgent }}</strong><span>{{ t('grossanlass.displays.gesamt.urgent') }}</span></div>
    </section>

    <section class="all__projects">
      <h3>{{ t('grossanlass.displays.gesamt.projects') }}</h3>
      <ul>
        <li v-for="board in data.boards" :key="board.order.id" :class="`p--${board.health}`">
          <span class="p__name">{{ board.order.title }}</span>
          <v-progress-linear :model-value="board.progress" height="16" rounded :color="board.health === 'critical' ? 'error' : board.health === 'attention' ? 'warning' : 'success'" />
          <b>{{ board.progress }} %</b>
        </li>
      </ul>
    </section>

    <section class="all__problems">
      <h3>{{ t('grossanlass.displays.gesamt.problems') }}</h3>
      <ul>
        <li v-for="entry in data.problems" :key="entry.project + entry.blocker.text"><v-icon icon="mdi-alert-octagon" color="error" size="26" /><span><strong>{{ entry.project }}</strong><small>{{ t(`grossanlass.displays.projekt.blocker.${entry.blocker.kind}`) }}: {{ entry.blocker.kind === 'deadline' ? t(`grossanlass.auftraege.times.issue.${entry.blocker.text}`) : entry.blocker.text }}</small></span></li>
        <li v-for="item in data.logistics.problems" :key="item.kind === 'need' ? item.need.id : item.tour.id"><v-icon icon="mdi-alert" color="warning" size="26" /><span><strong>{{ item.kind === 'need' ? item.need.title : item.tour.name }}</strong><small>{{ item.kind === 'need' ? t(`grossanlass.dispo.problem.${item.need.problem?.kind ?? 'other'}`) : t(`grossanlass.dispo.problem.${item.tour.problem?.kind ?? 'other'}`) }}</small></span></li>
        <li v-if="!data.problems.length && !data.logistics.problems.length" class="muted">{{ t('grossanlass.displays.gesamt.noProblems') }}</li>
      </ul>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import GaSiteMap from './GaSiteMap.vue'
import { taskProgress } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import { orderPlaceName, type overall } from '@/views/grossanlass/live/gaLiveModel'

const props = defineProps<{ data: ReturnType<typeof overall> }>()
const { t, locale } = useI18n()

const underway = computed(() => props.data.logistics.trips.filter((trip) => trip.underway))
const mapProjects = computed(() => props.data.boards.map((board) => ({ id: board.order.id, name: board.order.title, place: orderPlaceName(board.order), health: board.health })))
const nextResources = computed(() => props.data.logistics.resources.filter((res) => res.status !== 'planned' || (res.planned?.from ?? res.earliest) > new Date()).slice(0, 3))
const pad = (n: number) => String(n).padStart(2, '0')
const clock = (date: Date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`
function stamp(date: Date): string {
  return date.toLocaleString(locale.value, { weekday: 'short', day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.all {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 18px;
  min-height: 0;
}
section {
  padding: 18px 22px;
  border-radius: 18px;
  background: #111c33;
}
section h3 {
  margin: 0 0 10px;
  font-size: 1.15rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #94a3b8;
}
ul {
  display: grid;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
li {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: 1.35rem;
}
li small {
  display: block;
  font-size: 1rem;
  color: #94a3b8;
}
.muted {
  color: #64748b;
}
.all__map {
  grid-column: span 6;
  grid-row: span 2;
}
.all__map :deep(.site) {
  height: auto;
  aspect-ratio: 640 / 380;
}
.all__now {
  grid-column: span 6;
}
.all__next {
  grid-column: span 6;
}
.all__next li {
  display: grid;
  grid-template-columns: 160px minmax(0, 1fr);
}
.all__next time {
  color: #94a3b8;
  font-size: 1.1rem;
}
.all__kpis {
  grid-column: span 12;
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 14px;
  padding: 0;
  background: none;
}
.k {
  display: flex;
  flex-direction: column;
  padding: 16px 20px;
  border-radius: 16px;
  background: #111c33;
}
.k strong {
  font-size: 3.4rem;
  line-height: 1;
}
.k span {
  font-size: 1rem;
  color: #94a3b8;
}
.k--amber strong {
  color: #fbbf24;
}
.k--blue strong {
  color: #60a5fa;
}
.k--green strong {
  color: #4ade80;
}
.k--red strong {
  color: #f87171;
}
.all__projects {
  grid-column: span 6;
}
.all__projects li {
  display: grid;
  grid-template-columns: 200px minmax(0, 1fr) 70px;
  align-items: center;
}
.all__problems {
  grid-column: span 6;
}
</style>
