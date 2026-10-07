<template>
  <div class="mat">
    <!-- Übersicht -->
    <template v-if="view === 'overview'">
      <div class="kpis">
        <div class="kpi"><strong>{{ board.kpis.packOpen }}</strong><span>{{ t('grossanlass.displays.material.kpi.packOpen') }}</span></div>
        <div class="kpi kpi--red"><strong>{{ board.kpis.blocked }}</strong><span>{{ t('grossanlass.displays.material.kpi.blocked') }}</span></div>
        <div class="kpi kpi--green"><strong>{{ board.kpis.packsReady }}</strong><span>{{ t('grossanlass.displays.material.kpi.packsReady') }}</span></div>
        <div class="kpi kpi--blue"><strong>{{ board.kpis.waitingTransport }}</strong><span>{{ t('grossanlass.displays.material.kpi.waitingTransport') }}</span></div>
        <div class="kpi kpi--amber"><strong>{{ board.kpis.missingItems }}</strong><span>{{ t('grossanlass.displays.material.kpi.missingItems') }}</span></div>
      </div>
      <ul class="rows">
        <li v-for="entry in board.projects" :key="entry.project.id" class="row" :class="`row--${entry.status}`">
          <div class="row__name">
            <strong>{{ entry.project.name }}</strong>
            <small>{{ entry.project.ressort }} · {{ entry.project.bereich }}</small>
          </div>
          <div class="row__bar"><v-progress-linear :model-value="entry.progress" height="18" rounded :color="BAR[entry.status]" /><span>{{ entry.progress }} %</span></div>
          <div class="row__facts">
            <span v-if="entry.missing" class="f f--red">{{ t('grossanlass.displays.material.missingN', { n: entry.missing }) }}</span>
            <span v-if="deadline(entry.deadline)" class="f">{{ t('grossanlass.displays.deadline', { when: deadline(entry.deadline) }) }}</span>
          </div>
          <v-chip size="large" variant="flat" :color="BAR[entry.status]">{{ t(`grossanlass.packen.status.${entry.status}`) }}</v-chip>
        </li>
      </ul>
    </template>

    <!-- Packen pro Auftrag -->
    <template v-else-if="view === 'pack'">
      <div class="legend">
        <span class="dot dot--red" /> {{ t('grossanlass.displays.material.state.missing') }}
        <span class="dot dot--amber" /> {{ t('grossanlass.displays.material.state.available') }}
        <span class="dot dot--blue" /> {{ t('grossanlass.displays.material.state.packed') }}
        <span class="dot dot--green" /> {{ t('grossanlass.displays.material.state.onSite') }}
      </div>
      <div class="cards">
        <article v-for="entry in board.projects" :key="entry.project.id" class="card">
          <header>
            <h3>{{ entry.project.name }}</h3>
            <v-chip size="large" variant="flat" :color="BAR[entry.status]">{{ t(`grossanlass.packen.status.${entry.status}`) }}</v-chip>
          </header>
          <div class="row__bar"><v-progress-linear :model-value="entry.progress" height="20" rounded :color="BAR[entry.status]" /><span>{{ entry.progress }} %</span></div>
          <p class="card__meta">
            {{ t('grossanlass.displays.material.sums', { needed: entry.needed, available: entry.available, packed: entry.packed, missing: entry.missing }) }}
            <template v-if="deadline(entry.deadline)"> · {{ t('grossanlass.displays.deadline', { when: deadline(entry.deadline) }) }}</template>
          </p>
          <ul class="lines">
            <li v-for="state in entry.lines" :key="state.line.id">
              <span class="lines__name">{{ state.line.label }} <small>{{ state.line.needed }}</small></span>
              <span class="n n--red" :class="{ 'n--zero': !state.missing }">{{ state.missing }}</span>
              <span class="n n--amber" :class="{ 'n--zero': !state.availableNotPacked }">{{ state.availableNotPacked }}</span>
              <span class="n n--blue" :class="{ 'n--zero': !state.packedWaiting }">{{ state.packedWaiting }}</span>
              <span class="n n--green" :class="{ 'n--zero': !state.onSite }">{{ state.onSite }}</span>
            </li>
          </ul>
          <p v-if="entry.palettes.length" class="card__packs">
            <v-icon icon="mdi-package-variant-closed" size="22" />
            <span v-for="palette in entry.palettes" :key="palette.id" class="pill" :class="`pill--${palette.status}`">{{ palette.code }} · {{ t(`grossanlass.packen.paletteStatus.${palette.status}`) }}</span>
          </p>
        </article>
      </div>
    </template>

    <!-- Fehlmaterial -->
    <template v-else-if="view === 'missing'">
      <p v-if="!board.missing.length" class="empty">{{ t('grossanlass.displays.material.nothingMissing') }}</p>
      <ul class="rows">
        <li v-for="entry in board.missing" :key="entry.state.line.id" class="miss">
          <div class="row__name">
            <strong>{{ entry.state.missing }}× {{ entry.state.line.label }}</strong>
            <small>{{ entry.project.name }}</small>
          </div>
          <v-chip size="large" variant="flat" :color="CAUSE_COLOR[entry.state.supply?.cause ?? 'unknown']">
            {{ t(`grossanlass.displays.material.cause.${entry.state.supply?.cause ?? 'unknown'}`) }}
          </v-chip>
          <div class="miss__when">
            <strong v-if="entry.state.supply?.eta">{{ stamp(entry.state.supply.eta) }}</strong>
            <small>{{ entry.state.supply?.note }}</small>
          </div>
        </li>
      </ul>
    </template>

    <!-- Bereit / Transport -->
    <template v-else-if="view === 'transport'">
      <div class="cols">
        <section>
          <h3>{{ t('grossanlass.displays.material.waitingTransport') }} ({{ board.waitingTransport.length }})</h3>
          <ul class="rows">
            <li v-for="palette in board.waitingTransport" :key="palette.id" class="miss">
              <div class="row__name"><strong>{{ palette.code }} · {{ palette.title }}</strong><small>{{ palette.projectName }} → {{ palette.target }}</small></div>
              <v-chip size="large" variant="flat" :color="palette.status === 'sent' ? 'primary' : 'success'">{{ t(`grossanlass.packen.paletteStatus.${palette.status}`) }}</v-chip>
            </li>
            <li v-if="!board.waitingTransport.length" class="empty">{{ t('grossanlass.displays.material.nothingWaiting') }}</li>
          </ul>
        </section>
        <section>
          <h3>{{ t('grossanlass.displays.material.onSite') }}</h3>
          <ul class="rows">
            <li v-for="palette in delivered" :key="palette.id" class="miss">
              <div class="row__name"><strong>{{ palette.code }} · {{ palette.title }}</strong><small>{{ palette.target }}</small></div>
              <v-chip size="large" variant="flat" color="success">{{ t('grossanlass.displays.material.state.onSite') }}</v-chip>
            </li>
            <li v-if="!delivered.length" class="empty">{{ t('grossanlass.displays.material.nothingOnSite') }}</li>
          </ul>
        </section>
      </div>
    </template>

    <!-- Wareneingang -->
    <template v-else>
      <p class="hint">{{ t('grossanlass.displays.material.incomingHint') }}</p>
      <ul class="rows">
        <li v-for="entry in incoming" :key="entry.state.line.id" class="miss">
          <div class="row__name"><strong>{{ entry.state.missing }}× {{ entry.state.line.label }}</strong><small>{{ entry.project.name }}</small></div>
          <v-chip size="large" variant="flat" :color="CAUSE_COLOR[entry.state.supply!.cause]">{{ t(`grossanlass.displays.material.cause.${entry.state.supply!.cause}`) }}</v-chip>
          <div class="miss__when"><strong>{{ entry.state.supply!.eta ? stamp(entry.state.supply!.eta!) : '–' }}</strong><small>{{ entry.state.supply!.note }}</small></div>
        </li>
        <li v-if="!incoming.length" class="empty">{{ t('grossanlass.displays.material.nothingIncoming') }}</li>
      </ul>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { GaPackStatus, GaSupplyCause } from '@/views/grossanlass/packen/gaPackenMock'
import type { materialBoard } from '@/views/grossanlass/live/gaLiveModel'

const props = defineProps<{ board: ReturnType<typeof materialBoard>; view: string }>()
const { t, locale } = useI18n()

const BAR: Record<GaPackStatus, string> = { packable: 'success', partial: 'warning', waiting: 'error', done: 'primary' }
const CAUSE_COLOR: Record<GaSupplyCause, string> = { ordered: 'info', pickupPlanned: 'primary', notProcured: 'error', partial: 'warning', late: 'error', unknown: 'grey' }

const delivered = computed(() => props.board.projects.flatMap((entry) => entry.palettes).filter((row) => row.status === 'delivered'))
const incoming = computed(() =>
  props.board.incoming.slice().sort((a, b) => (a.state.supply?.eta?.getTime() ?? 0) - (b.state.supply?.eta?.getTime() ?? 0)),
)
function stamp(date: Date): string {
  return date.toLocaleString(locale.value, { weekday: 'short', day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function deadline(date: Date | null): string {
  return date ? stamp(date) : ''
}
</script>

<style scoped>
.mat {
  display: flex;
  flex-direction: column;
  gap: 20px;
  min-height: 0;
}
.kpis {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px;
}
.kpi {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 20px 24px;
  border-radius: 18px;
  background: #111c33;
}
.kpi strong {
  font-size: 4.2rem;
  line-height: 1;
}
.kpi span {
  font-size: 1.2rem;
  color: #94a3b8;
}
.kpi--red strong {
  color: #f87171;
}
.kpi--green strong {
  color: #4ade80;
}
.kpi--blue strong {
  color: #60a5fa;
}
.kpi--amber strong {
  color: #fbbf24;
}
.rows {
  display: grid;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.row,
.miss {
  display: grid;
  grid-template-columns: minmax(220px, 2fr) minmax(220px, 3fr) auto auto;
  gap: 20px;
  align-items: center;
  padding: 16px 22px;
  border-radius: 16px;
  background: #111c33;
  font-size: 1.5rem;
}
.miss {
  grid-template-columns: minmax(240px, 2fr) auto minmax(200px, 1.5fr);
}
.row--waiting {
  box-shadow: inset 6px 0 0 #ef4444;
}
.row__name {
  display: flex;
  flex-direction: column;
}
.row__name small,
.miss__when small {
  font-size: 1.05rem;
  color: #94a3b8;
}
.miss__when {
  display: flex;
  flex-direction: column;
}
.row__bar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 90px;
  gap: 12px;
  align-items: center;
  font-size: 1.4rem;
}
.row__facts {
  display: flex;
  flex-direction: column;
  font-size: 1.1rem;
}
.f--red {
  color: #f87171;
  font-weight: 700;
}
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(520px, 1fr));
  gap: 16px;
}
.card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 20px 22px;
  border-radius: 18px;
  background: #111c33;
}
.card header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.card h3 {
  margin: 0;
  font-size: 1.8rem;
}
.card__meta {
  margin: 0;
  color: #94a3b8;
  font-size: 1.1rem;
}
.lines {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.lines li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) repeat(4, 70px);
  gap: 8px;
  align-items: center;
  font-size: 1.25rem;
}
.lines__name small {
  color: #94a3b8;
}
.n {
  padding: 4px 0;
  border-radius: 10px;
  text-align: center;
  font-size: 1.4rem;
  font-weight: 700;
}
.n--red {
  background: #7f1d1d;
  color: #fecaca;
}
.n--amber {
  background: #78350f;
  color: #fde68a;
}
.n--blue {
  background: #1e3a8a;
  color: #bfdbfe;
}
.n--green {
  background: #14532d;
  color: #bbf7d0;
}
.n--zero {
  opacity: 0.25;
}
.legend {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 22px;
  font-size: 1.2rem;
  color: #cbd5e1;
}
.dot {
  display: inline-block;
  width: 18px;
  height: 18px;
  border-radius: 5px;
}
.dot--red {
  background: #ef4444;
}
.dot--amber {
  background: #f59e0b;
}
.dot--blue {
  background: #3b82f6;
}
.dot--green {
  background: #22c55e;
}
.card__packs {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0;
}
.pill {
  padding: 4px 12px;
  border-radius: 999px;
  background: #1e293b;
  font-size: 1.05rem;
}
.pill--ready,
.pill--delivered {
  background: #14532d;
}
.pill--sent {
  background: #1e3a8a;
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
.empty,
.hint {
  margin: 0;
  color: #64748b;
  font-size: 1.4rem;
}
</style>
