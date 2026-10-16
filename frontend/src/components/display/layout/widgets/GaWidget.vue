<template>
  <div class="ga-w">
    <ScaledBox class="ga-w__box">
      <h2 v-if="title" class="ga-w__title">{{ title }}</h2>
      <div class="ga-w__body">
        <DisplayMaterial v-if="family === 'material'" :board="materialData" :view="materialView" />
        <DisplayLogistik v-else-if="family === 'logistik'" :board="logistikData" :view="logistikView" />
        <DisplayProjekt v-else-if="family === 'projekt'" passive :boards="boards" :trips="tripList" :active-id="activeProject" />
        <GaSiteMap
          v-else-if="family === 'map'"
          dark
          :label="t('grossanlass.displays.projekt.mapLabel')"
          :places="SITE_PLACES"
          :projects="mapProjects"
          :trips="overallData.logistics.trips"
          :estimate-label="t('grossanlass.displays.logistik.estimated')"
          :gps-label="t('grossanlass.displays.logistik.gps')"
        />
        <DisplayFeed v-else-if="family === 'feed'" :events="feed" />
        <ul v-else-if="family === 'tasks'" class="ga-w__list">
          <li v-for="task in openTasks" :key="task.id">
            <strong>{{ task.title }}</strong>
            <small>{{ task.origin.join(' › ') }}<template v-if="task.deadlineLabel"> · {{ task.deadlineLabel }}</template></small>
          </li>
          <li v-if="!openTasks.length" class="ga-w__empty">{{ t('display.layout.ga.noTasks') }}</li>
        </ul>
        <ul v-else-if="family === 'helpers'" class="ga-w__list">
          <li v-for="(line, i) in helperLines" :key="i"><strong>{{ line }}</strong></li>
          <li v-if="!helperLines.length" class="ga-w__empty">{{ t('display.layout.ga.noHelperInfo') }}</li>
        </ul>
        <ul v-else-if="family === 'warnings'" class="ga-w__list">
          <li v-for="(warning, i) in warnings" :key="i" :class="`ga-w__warn ga-w__warn--${warning.level}`">
            <strong>{{ warning.title }}</strong>
            <small v-if="warning.detail">{{ warning.detail }}</small>
          </li>
          <li v-if="!warnings.length" class="ga-w__empty">{{ t('display.layout.ga.noWarnings') }}</li>
        </ul>
      </div>
      <span v-if="isDemoData" class="ga-w__demo">{{ t('display.layout.demoData') }}</span>
    </ScaledBox>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import DisplayFeed from '@/views/grossanlass/displays/DisplayFeed.vue'
import DisplayLogistik from '@/views/grossanlass/displays/DisplayLogistik.vue'
import DisplayMaterial from '@/views/grossanlass/displays/DisplayMaterial.vue'
import DisplayProjekt from '@/views/grossanlass/displays/DisplayProjekt.vue'
import GaSiteMap from '@/views/grossanlass/displays/GaSiteMap.vue'
import { useGaAufgabenMock } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import { recentEvents, useGaLive, type GaLiveArea, type GaLiveEvent } from '@/views/grossanlass/live/gaLiveEvents'
import {
  logistikBoard,
  materialBoard,
  matchesTaskFilter,
  orderPlaceName,
  overall,
  projectBoards,
  trips as tripsOf,
} from '@/views/grossanlass/live/gaLiveModel'
import ScaledBox from './ScaledBox.vue'

/**
 * Renderer für alle GA-Fachwidgets. Datenquelle: die bestehenden GA-Demo-/Seed-Daten (`ga_demo`) – keine erfundenen
 * Live-Daten und keine eigene Geschäftslogik. Die Widgets sind rein passiv (keine Bedienelemente).
 */
const props = defineProps<{ widget: string; config: Record<string, unknown> }>()

const { t } = useI18n()
const { events } = useGaLive()
const { tasks } = useGaAufgabenMock()

const SITE_PLACES = ['Zentrallager', 'Lager A', 'Häberli Holz', 'Zeltbau AG', 'Festgelände Süd']

const FAMILY: Record<string, string> = {
  ga_material_overview: 'material',
  ga_pack_progress: 'material',
  ga_missing_material: 'material',
  ga_goods_receipt: 'material',
  ga_transport_overview: 'logistik',
  ga_dispatch_tours: 'logistik',
  ga_projects: 'projekt',
  ga_tasks: 'tasks',
  ga_helpers: 'helpers',
  ga_site_map: 'map',
  ga_activity_feed: 'feed',
  ga_warnings: 'warnings',
}
const family = computed(() => (props.widget === 'ga_transport_overview' && props.config.view === 'material' ? 'material' : FAMILY[props.widget] ?? ''))
const isDemoData = computed(() => props.widget !== 'ga_helpers')

const title = computed(() => String(props.config.title ?? ''))
const filter = computed(() => ({
  ressort: String(props.config.filter_ressort ?? ''),
  bereich: String(props.config.filter_bereich ?? ''),
  project: String(props.config.filter_project ?? ''),
}))
const helperLines = computed(() => (Array.isArray(props.config.lines) ? props.config.lines.map(String) : []))
const maxItems = computed(() => Number(props.config.max_items) || 8)

const MATERIAL_VIEW: Record<string, string> = {
  ga_material_overview: 'overview',
  ga_pack_progress: 'pack',
  ga_missing_material: 'missing',
  ga_goods_receipt: 'incoming',
}
// Das Transport-Widget zeigt je nach Auswahl die Material-Transportsicht oder die Disposition.
const materialView = computed(() => (props.widget === 'ga_transport_overview' ? 'transport' : MATERIAL_VIEW[props.widget] ?? 'overview'))
const logistikView = computed(() => String(props.config.view ?? 'overview'))

// Zeit nur für die ETA-Simulation der Demo-Daten.
const now = ref(new Date())
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  timer = setInterval(() => {
    now.value = new Date()
  }, 1000)
})
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
  if (rotateTimer) clearInterval(rotateTimer)
})

const materialData = computed(() => {
  void now.value
  void events.value.length
  return materialBoard(filter.value)
})
const logistikData = computed(() => {
  void now.value
  void tasks.value
  return logistikBoard(filter.value)
})
const overallData = computed(() => {
  void now.value
  void tasks.value
  return overall(filter.value)
})
const boards = computed(() => {
  void now.value
  void tasks.value
  return projectBoards(filter.value)
})
const tripList = computed(() => {
  void now.value
  return tripsOf(filter.value).filter((trip) => matchesTaskFilter(trip.task, filter.value))
})
const mapProjects = computed(() => overallData.value.boards.map((board) => ({ id: board.order.id, name: board.order.title, place: orderPlaceName(board.order), health: board.health })))

// Projektrotation (rein automatisch)
const activeProject = ref('')
let rotateTimer: ReturnType<typeof setInterval> | undefined
function restartRotation() {
  if (rotateTimer) clearInterval(rotateTimer)
  rotateTimer = undefined
  if (family.value !== 'projekt' || props.config.rotate === false) return
  const seconds = Math.max(5, Number(props.config.rotate_sec) || 15)
  rotateTimer = setInterval(() => {
    const list = boards.value
    const index = list.findIndex((board) => board.order.id === activeProject.value)
    activeProject.value = list[(index + 1) % Math.max(1, list.length)]?.order.id ?? ''
  }, seconds * 1000)
}
watch(boards, (list) => {
  if (!list.some((board) => board.order.id === activeProject.value)) activeProject.value = list[0]?.order.id ?? ''
}, { immediate: true })
watch(() => [props.config.rotate, props.config.rotate_sec, family.value], restartRotation, { immediate: true })

const FEED_AREAS: Record<string, GaLiveArea[]> = {
  material: ['material', 'problem'],
  logistics: ['logistics', 'problem'],
  task: ['task'],
  sale: ['sale'],
  problem: ['problem'],
}
const feed = computed<GaLiveEvent[]>(() => {
  const areas = FEED_AREAS[String(props.config.area ?? 'all')]
  return recentEvents(maxItems.value, areas ? (event: GaLiveEvent) => areas.includes(event.area) : undefined)
})

const openTasks = computed(() => {
  void now.value
  return tasks.value.filter((task) => task.status !== 'done' && matchesTaskFilter(task, filter.value)).slice(0, maxItems.value)
})

type Warning = { level: 'warning' | 'critical'; title: string; detail: string }
const warnings = computed<Warning[]>(() => {
  const rows: Warning[] = []
  for (const board of boards.value) {
    if (board.health === 'ok') continue
    for (const blocker of board.blockers) {
      rows.push({ level: board.health === 'critical' ? 'critical' : 'warning', title: board.order.title, detail: blocker.kind === 'deadline' ? t(`grossanlass.auftraege.times.issue.${blocker.text}`) : blocker.text })
    }
  }
  for (const event of events.value) {
    if (event.severity === 'warning' || event.severity === 'error') {
      rows.push({ level: event.severity === 'error' ? 'critical' : 'warning', title: event.text, detail: event.detail })
    }
  }
  return rows.slice(0, maxItems.value)
})
</script>

<style scoped>
.ga-w {
  width: 100%;
  height: 100%;
  overflow: hidden;
  border-radius: 0.6em;
}
.ga-w__box :deep(.sb__inner) {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ga-w__title {
  margin: 0;
  font-size: 22px;
}
.ga-w__body {
  flex: 1 1 auto;
  min-height: 0;
  overflow: hidden;
}
.ga-w__demo {
  position: absolute;
  right: 8px;
  bottom: 6px;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(180, 83, 9, 0.85);
  color: #fff;
  font-size: 12px;
}
.ga-w__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 14px;
  list-style: none;
  border-radius: 14px;
  background: #111c33;
  font-size: 20px;
}
.ga-w__list li {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.ga-w__list small {
  color: #94a3b8;
  font-size: 15px;
}
.ga-w__empty {
  color: #94a3b8;
}
.ga-w__warn--warning strong {
  color: #fbbf24;
}
.ga-w__warn--critical strong {
  color: #f87171;
}
</style>
