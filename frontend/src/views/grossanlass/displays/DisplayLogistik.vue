<template>
  <div class="log">
    <template v-if="view === 'overview'">
      <div class="kpis">
        <div class="kpi"><strong>{{ board.kpis.open }}</strong><span>{{ t('grossanlass.displays.logistik.kpi.open') }}</span></div>
        <div class="kpi kpi--blue"><strong>{{ board.kpis.planned }}</strong><span>{{ t('grossanlass.displays.logistik.kpi.planned') }}</span></div>
        <div class="kpi kpi--green"><strong>{{ board.kpis.underway }}</strong><span>{{ t('grossanlass.displays.logistik.kpi.underway') }}</span></div>
        <div class="kpi kpi--red"><strong>{{ board.kpis.urgent }}</strong><span>{{ t('grossanlass.displays.logistik.kpi.urgent') }}</span></div>
        <div class="kpi kpi--amber"><strong>{{ board.kpis.problems }}</strong><span>{{ t('grossanlass.displays.logistik.kpi.problems') }}</span></div>
        <div class="kpi"><strong>{{ board.kpis.driversFree }}</strong><span>{{ t('grossanlass.displays.logistik.kpi.driversFree') }}</span></div>
        <div class="kpi"><strong>{{ board.kpis.vehiclesFree }}</strong><span>{{ t('grossanlass.displays.logistik.kpi.vehiclesFree') }}</span></div>
      </div>
      <div class="cols">
        <section>
          <h3>{{ t('grossanlass.displays.logistik.trips') }}</h3>
          <ul class="rows">
            <li v-for="trip in activeTrips" :key="trip.task.id" class="trip" :class="{ 'trip--underway': trip.underway }">
              <div class="trip__main">
                <strong>{{ trip.from }} → {{ trip.to }}</strong>
                <small>{{ trip.driver }} · {{ trip.vehicle }}</small>
              </div>
              <div class="trip__state">
                <v-chip size="large" variant="flat" :color="trip.underway ? 'success' : 'info'">{{ trip.stepKey ? t(`grossanlass.aufgaben.route.steps.${trip.stepKey}`) : t('grossanlass.displays.logistik.notStarted') }}</v-chip>
                <small v-if="trip.eta">{{ t('grossanlass.displays.logistik.eta', { when: clock(trip.eta) }) }}<template v-if="trip.mode === 'eta' && trip.underway"> · {{ t('grossanlass.displays.logistik.estimated') }}</template></small>
                <small v-if="trip.mode === 'gps'" class="gps">{{ t('grossanlass.displays.logistik.gps') }}</small>
              </div>
            </li>
            <li v-if="!activeTrips.length" class="empty">{{ t('grossanlass.displays.logistik.noTrips') }}</li>
          </ul>
        </section>
        <section>
          <h3>{{ t('grossanlass.displays.logistik.urgentTitle') }}</h3>
          <ul class="rows">
            <li v-for="need in board.urgent" :key="need.id" class="trip trip--urgent">
              <div class="trip__main"><strong>{{ need.from }} → {{ need.to }}</strong><small>{{ need.cargo }}</small></div>
              <div class="trip__state"><v-chip size="large" variant="flat" color="error">{{ need.timingLabel }}</v-chip><small>{{ t(`grossanlass.dispo.status.${need.status}`) }}</small></div>
            </li>
            <li v-if="!board.urgent.length" class="empty">{{ t('grossanlass.displays.logistik.noUrgent') }}</li>
          </ul>
        </section>
      </div>
    </template>

    <template v-else-if="view === 'transports'">
      <div class="cols">
        <section>
          <h3>{{ t('grossanlass.displays.logistik.openNeeds') }} ({{ board.openNeeds.length }})</h3>
          <ul class="rows">
            <li v-for="need in board.openNeeds" :key="need.id" class="trip" :class="{ 'trip--urgent': need.priority === 'urgent' }">
              <div class="trip__main"><strong>{{ need.from }} → {{ need.to }}</strong><small>{{ need.cargo }}</small></div>
              <div class="trip__state"><v-chip size="large" variant="flat" :color="need.ready ? 'success' : 'grey'">{{ need.ready ? t('grossanlass.dispo.ready') : t('grossanlass.dispo.waiting') }}</v-chip><small>{{ need.timingLabel }}</small></div>
            </li>
          </ul>
        </section>
        <section>
          <h3>{{ t('grossanlass.displays.logistik.plannedNeeds') }} ({{ board.plannedNeeds.length }})</h3>
          <ul class="rows">
            <li v-for="need in board.plannedNeeds" :key="need.id" class="trip">
              <div class="trip__main"><strong>{{ need.from }} → {{ need.to }}</strong><small>{{ need.cargo }}</small></div>
              <div class="trip__state"><small>{{ need.timingLabel }}</small></div>
            </li>
          </ul>
        </section>
      </div>
    </template>

    <template v-else-if="view === 'tours'">
      <ul class="rows">
        <li v-for="entry in board.tours" :key="entry.tour.id" class="tour" :class="{ 'tour--problem': entry.tour.problem }">
          <div class="tour__head">
            <strong>{{ entry.tour.name }} · {{ entry.driver }} · {{ entry.vehicle }}<template v-if="entry.tour.trailer"> + {{ entry.tour.trailer }}</template></strong>
            <v-chip size="large" variant="flat" :color="entry.tour.status === 'underway' ? 'success' : 'info'">{{ t(`grossanlass.dispo.tourStatus.${entry.tour.status}`) }}</v-chip>
            <v-chip v-if="entry.tour.delayMin" size="large" variant="flat" color="warning">{{ t('grossanlass.dispo.delay', { min: entry.tour.delayMin }) }}</v-chip>
            <v-chip v-if="entry.tour.problem?.kind === 'vehicleDefect'" size="large" variant="flat" color="error">{{ t('grossanlass.dispo.problem.vehicleDefect') }}</v-chip>
          </div>
          <ol class="stops">
            <li v-for="stop in entry.tour.stops" :key="stop.id" :class="{ 'stop--done': stop.done, 'stop--next': entry.nextStop?.id === stop.id }">{{ stop.place }}<small>{{ stop.label }}</small></li>
          </ol>
        </li>
        <li v-if="!board.tours.length" class="empty">{{ t('grossanlass.displays.logistik.noTours') }}</li>
      </ul>
    </template>

    <template v-else>
      <p class="hint">{{ t('grossanlass.displays.logistik.resourcesHint') }}</p>
      <ul class="rows">
        <li v-for="need in board.resources" :key="need.id" class="trip">
          <div class="trip__main"><strong>{{ need.qty > 1 ? `${need.qty}× ` : '' }}{{ need.label }}</strong><small>{{ need.note }}</small></div>
          <div class="trip__state">
            <v-chip size="large" variant="flat" :color="need.status === 'planned' ? 'success' : need.status === 'proposal' ? 'info' : 'warning'">{{ t(`grossanlass.auftraege.res.status.${need.status}`) }}</v-chip>
            <small>{{ when(need.planned?.from ?? need.earliest, need.planned?.to ?? need.latest) }}</small>
          </div>
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { logistikBoard } from '@/views/grossanlass/live/gaLiveModel'

const props = defineProps<{ board: ReturnType<typeof logistikBoard>; view: string }>()
const { t, locale } = useI18n()

const activeTrips = computed(() => props.board.trips.filter((trip) => !trip.done))
const pad = (n: number) => String(n).padStart(2, '0')
const clock = (date: Date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`
function when(from: Date, to: Date): string {
  const day = from.toLocaleDateString(locale.value, { weekday: 'short', day: 'numeric', month: 'numeric' })
  return `${day} ${clock(from)}–${clock(to)}`
}
</script>

<style scoped>
.log {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.kpis {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 14px;
}
.kpi {
  display: flex;
  flex-direction: column;
  padding: 18px 20px;
  border-radius: 18px;
  background: #111c33;
}
.kpi strong {
  font-size: 3.6rem;
  line-height: 1;
}
.kpi span {
  font-size: 1.05rem;
  color: #94a3b8;
}
.kpi--blue strong {
  color: #60a5fa;
}
.kpi--green strong {
  color: #4ade80;
}
.kpi--red strong {
  color: #f87171;
}
.kpi--amber strong {
  color: #fbbf24;
}
.cols {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 24px;
}
.cols h3 {
  margin: 0 0 12px;
  font-size: 1.5rem;
  color: #cbd5e1;
}
.rows {
  display: grid;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.trip {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 16px;
  align-items: center;
  padding: 16px 22px;
  border-radius: 16px;
  background: #111c33;
  font-size: 1.5rem;
}
.trip--underway {
  box-shadow: inset 6px 0 0 #22c55e;
}
.trip--urgent {
  box-shadow: inset 6px 0 0 #ef4444;
}
.trip__main,
.trip__state {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.trip__state {
  align-items: flex-end;
}
.trip small {
  font-size: 1.05rem;
  color: #94a3b8;
}
.gps {
  color: #c4b5fd !important;
}
.tour {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 16px 22px;
  border-radius: 16px;
  background: #111c33;
  font-size: 1.4rem;
}
.tour--problem {
  box-shadow: inset 6px 0 0 #ef4444;
}
.tour__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}
.stops {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.stops li {
  display: flex;
  flex-direction: column;
  padding: 8px 14px;
  border-radius: 12px;
  background: #1e293b;
  font-size: 1.15rem;
}
.stops small {
  color: #94a3b8;
  font-size: 0.9rem;
}
.stop--done {
  opacity: 0.45;
}
.stop--next {
  background: #14532d !important;
}
.empty,
.hint {
  margin: 0;
  color: #64748b;
  font-size: 1.4rem;
}
</style>
