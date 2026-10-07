<template>
  <div class="proj">
    <div class="proj__map">
      <GaSiteMap
        dark
        :label="t('grossanlass.displays.projekt.mapLabel')"
        :places="placeNames"
        :projects="mapProjects"
        :trips="trips"
        :active-id="activeId"
        :estimate-label="t('grossanlass.displays.logistik.estimated')"
        :gps-label="t('grossanlass.displays.logistik.gps')"
      />
      <ul class="proj__chips">
        <li v-for="board in boards" :key="board.order.id">
          <button type="button" class="chip" :class="[`chip--${board.health}`, { 'chip--active': board.order.id === activeId }]" @click="$emit('select', board.order.id)">
            <span class="chip__dot" />{{ board.order.title }}
          </button>
        </li>
      </ul>
      <p class="proj__legend">
        <span class="lg lg--ok" /> {{ t('grossanlass.displays.projekt.health.ok') }}
        <span class="lg lg--attention" /> {{ t('grossanlass.displays.projekt.health.attention') }}
        <span class="lg lg--critical" /> {{ t('grossanlass.displays.projekt.health.critical') }}
      </p>
    </div>

    <article v-if="active" class="detail" :class="`detail--${active.health}`">
      <header>
        <div>
          <h2>{{ active.order.title }}</h2>
          <p>{{ active.order.ressort }} · {{ active.order.bereich }}<template v-if="active.order.build"> · {{ active.order.build.project }}</template></p>
        </div>
        <v-chip size="x-large" variant="flat" :color="HEALTH_CHIP[active.health]">{{ t(`grossanlass.displays.projekt.health.${active.health}`) }}</v-chip>
      </header>

      <div class="bar"><span>{{ t('grossanlass.displays.projekt.progress') }}</span><v-progress-linear :model-value="active.progress" height="22" rounded color="success" /><b>{{ active.progress }} %</b></div>
      <div class="bar"><span>{{ t('grossanlass.displays.projekt.material') }}</span><v-progress-linear :model-value="active.material ?? 0" height="22" rounded color="primary" /><b>{{ active.material === null ? '–' : `${active.material} %` }}</b></div>

      <dl class="facts">
        <div><dt>{{ t('grossanlass.displays.projekt.deadline') }}</dt><dd>{{ active.deadline ? stamp(active.deadline) : '–' }}</dd></div>
        <div><dt>{{ t('grossanlass.displays.projekt.currentTask') }}</dt><dd>{{ active.currentTask?.title ?? '–' }}</dd></div>
        <div><dt>{{ t('grossanlass.displays.projekt.helpers') }}</dt><dd>{{ t('grossanlass.auftraege.staffed', { assigned: active.helpers.assigned, need: active.helpers.need }) }}</dd></div>
        <div><dt>{{ t('grossanlass.displays.projekt.packs') }}</dt><dd>{{ active.packs.length ? active.packs.map((p) => `${p.code} (${t(`grossanlass.packen.paletteStatus.${p.status}`)})`).join(', ') : '–' }}</dd></div>
        <div><dt>{{ t('grossanlass.displays.projekt.transport') }}</dt><dd>{{ t('grossanlass.displays.projekt.transportLine', active.transport) }}</dd></div>
        <div>
          <dt>{{ t('grossanlass.displays.projekt.nextResources') }}</dt>
          <dd>
            <template v-if="active.nextResources.length"><span v-for="res in active.nextResources" :key="res.id" class="res">{{ res.label }} · {{ t(`grossanlass.auftraege.res.status.${res.status}`) }}</span></template>
            <template v-else>–</template>
          </dd>
        </div>
      </dl>

      <ul v-if="active.blockers.length" class="blockers">
        <li v-for="blocker in active.blockers" :key="blocker.kind + blocker.text">
          <v-icon icon="mdi-alert-octagon" size="30" />
          <span><strong>{{ t(`grossanlass.displays.projekt.blocker.${blocker.kind}`) }}</strong> {{ blockerText(blocker) }}</span>
        </li>
      </ul>
      <ul v-else-if="active.attention.length" class="attention">
        <li v-for="item in active.attention" :key="item"><v-icon icon="mdi-alert-circle-outline" size="26" /> {{ t(`grossanlass.displays.projekt.attention.${item}`) }}</li>
      </ul>
    </article>
    <p v-else class="empty">{{ t('grossanlass.displays.projekt.none') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import GaSiteMap from './GaSiteMap.vue'
import { orderPlaceName, type GaBlocker, type GaHealth, type GaProjectBoard, type GaTrip } from '@/views/grossanlass/live/gaLiveModel'

const props = defineProps<{ boards: GaProjectBoard[]; trips: GaTrip[]; activeId: string }>()
defineEmits<{ select: [id: string] }>()
const { t, locale } = useI18n()

const HEALTH_CHIP: Record<GaHealth, string> = { ok: 'success', attention: 'warning', critical: 'error' }
const active = computed(() => props.boards.find((board) => board.order.id === props.activeId) ?? props.boards[0] ?? null)
const mapProjects = computed(() => props.boards.map((board) => ({ id: board.order.id, name: board.order.title, place: orderPlaceName(board.order), health: board.health })))
const placeNames = computed(() => ['Zentrallager', 'Lager A', 'Häberli Holz', 'Zeltbau AG', 'Festgelände Süd'])

function stamp(date: Date): string {
  return date.toLocaleString(locale.value, { weekday: 'short', day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function blockerText(blocker: GaBlocker): string {
  return blocker.kind === 'deadline' ? t(`grossanlass.auftraege.times.issue.${blocker.text}`) : blocker.text
}
</script>

<style scoped>
.proj {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(0, 2.4fr);
  gap: 22px;
  min-height: 0;
}
.proj__map {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.proj__map :deep(.site) {
  height: auto;
  aspect-ratio: 640 / 380;
}
.proj__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 10px 20px;
  border: 2px solid transparent;
  border-radius: 999px;
  background: #111c33;
  color: #fff;
  font-size: 1.4rem;
  cursor: pointer;
}
.chip__dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #22c55e;
}
.chip--attention .chip__dot {
  background: #f59e0b;
}
.chip--critical .chip__dot {
  background: #ef4444;
}
.chip--active {
  border-color: #fff;
}
.proj__legend {
  display: flex;
  align-items: center;
  gap: 8px 18px;
  margin: 0;
  font-size: 1.15rem;
  color: #94a3b8;
}
.lg {
  display: inline-block;
  width: 16px;
  height: 16px;
  border-radius: 50%;
}
.lg--ok {
  background: #22c55e;
}
.lg--attention {
  background: #f59e0b;
}
.lg--critical {
  background: #ef4444;
}
.detail {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  background: #111c33;
  border-top: 8px solid #22c55e;
}
.detail--attention {
  border-top-color: #f59e0b;
}
.detail--critical {
  border-top-color: #ef4444;
}
.detail header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}
.detail h2 {
  margin: 0;
  font-size: 2.6rem;
}
.detail header p {
  margin: 4px 0 0;
  font-size: 1.2rem;
  color: #94a3b8;
}
.bar {
  display: grid;
  grid-template-columns: 150px minmax(0, 1fr) 80px;
  gap: 14px;
  align-items: center;
  font-size: 1.3rem;
}
.facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 22px;
  margin: 0;
}
.facts dt {
  font-size: 0.95rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #94a3b8;
}
.facts dd {
  margin: 2px 0 0;
  font-size: 1.45rem;
}
.res {
  display: block;
}
.blockers,
.attention {
  display: grid;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.blockers li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 14px;
  background: #7f1d1d;
  font-size: 1.35rem;
}
.attention li {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #fbbf24;
  font-size: 1.3rem;
}
.empty {
  color: #64748b;
  font-size: 1.5rem;
}
</style>
