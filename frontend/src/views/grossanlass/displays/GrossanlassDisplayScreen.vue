<template>
  <div ref="rootEl" class="tv" :class="{ 'tv--fullscreen': isFullscreen }">
    <header class="tv__head">
      <div class="tv__title">
        <EmcLogoMark size="sm" />
        <div>
          <h1>{{ config?.name ?? t('grossanlass.displays.unknown') }}</h1>
          <p>{{ config?.location }} · {{ t(`grossanlass.displays.type.${config?.type ?? 'gesamt'}`) }}<template v-if="filterLabel"> · {{ filterLabel }}</template></p>
        </div>
      </div>
      <nav v-if="tabs.length > 1" class="tv__tabs" :aria-label="t('grossanlass.displays.views')">
        <button v-for="tab in tabs" :key="tab" type="button" class="tv__tab" :class="{ 'tv__tab--on': tab === view }" @click="select(tab)">
          {{ t(`grossanlass.displays.view.${tab}`) }}
        </button>
      </nav>
      <div class="tv__meta">
        <v-chip size="small" variant="flat" color="warning">{{ t('grossanlass.displays.demo') }}</v-chip>
        <label v-if="config" class="tv__rotate">
          <input v-model="rotating" type="checkbox"> {{ t('grossanlass.displays.rotation', { sec: config.rotateSec }) }}
        </label>
        <time class="tv__clock">{{ clock }}</time>
        <button type="button" class="tv__btn" @click="toggleFullscreen">{{ isFullscreen ? t('grossanlass.displays.exitFullscreen') : t('grossanlass.displays.fullscreen') }}</button>
        <button v-if="showAdmin" type="button" class="tv__btn" @click="goAdmin">{{ t('grossanlass.displays.toAdmin') }}</button>
      </div>
    </header>

    <transition name="banner">
      <div v-if="banner" class="tv__banner" :class="`tv__banner--${banner.severity}`" role="status">
        <strong>{{ banner.actor }}</strong>
        <span>{{ banner.text }}</span>
        <small v-if="banner.detail">{{ banner.detail }}</small>
      </div>
    </transition>

    <p v-if="!config" class="tv__missing">{{ t('grossanlass.displays.missing') }}</p>

    <main v-else class="tv__body" :class="{ 'tv__body--wide': config.type === 'projekt' }">
      <section class="tv__main">
        <DisplayMaterial v-if="config.type === 'material'" :board="materialData" :view="view" />
        <DisplayLogistik v-else-if="config.type === 'logistik'" :board="logistikData" :view="view" />
        <DisplayProjekt v-else-if="config.type === 'projekt'" :boards="boards" :trips="trips" :active-id="activeProject" @select="activeProject = $event" />
        <template v-else>
          <DisplayGesamt v-if="view === 'dashboard'" :data="overallData" />
          <div v-else class="tv__mapfull">
            <GaSiteMap
              dark
              :label="t('grossanlass.displays.projekt.mapLabel')"
              :places="['Zentrallager', 'Lager A', 'Häberli Holz', 'Zeltbau AG', 'Festgelände Süd']"
              :projects="overallData.boards.map((board) => ({ id: board.order.id, name: board.order.title, place: orderPlaceName(board.order), health: board.health }))"
              :trips="overallData.logistics.trips"
              :estimate-label="t('grossanlass.displays.logistik.estimated')"
              :gps-label="t('grossanlass.displays.logistik.gps')"
            />
          </div>
        </template>
      </section>
      <DisplayFeed v-if="config.type !== 'projekt'" class="tv__feed" :events="feed" />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import { useAuthStore } from '@/stores/auth'
import { isDevToolsEnvironment } from '@/utils/devEnvironmentBanner'
import { displayById, recentEvents, useGaLive, type GaLiveEvent } from '@/views/grossanlass/live/gaLiveEvents'
import { logistikBoard, materialBoard, orderPlaceName, overall, projectBoards, trips as tripsOf, matchesTaskFilter } from '@/views/grossanlass/live/gaLiveModel'
import DisplayFeed from './DisplayFeed.vue'
import DisplayGesamt from './DisplayGesamt.vue'
import DisplayLogistik from './DisplayLogistik.vue'
import DisplayMaterial from './DisplayMaterial.vue'
import DisplayProjekt from './DisplayProjekt.vue'
import GaSiteMap from './GaSiteMap.vue'
import { useGaAufgabenMock } from '@/views/grossanlass/aufgaben/gaAufgabenMock'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { events, displays } = useGaLive()
const { tasks } = useGaAufgabenMock()

const config = computed(() => {
  void displays.value
  return displayById(String(route.params.screenId || ''))
})
const filter = computed(() => config.value?.filter ?? { ressort: '', bereich: '', project: '' })
const filterLabel = computed(() => [filter.value.ressort, filter.value.bereich, filter.value.project].filter(Boolean).join(' › '))

// Zeit (nur Anzeige und ETA-Simulation, kein Netzwerk)
const now = ref(new Date())
let timer: ReturnType<typeof setInterval> | undefined
const clock = computed(() => now.value.toLocaleTimeString(locale.value, { hour: '2-digit', minute: '2-digit', second: '2-digit' }))

const TABS: Record<string, string[]> = {
  material: ['overview', 'pack', 'missing', 'transport', 'incoming'],
  logistik: ['overview', 'transports', 'tours', 'resources'],
  projekt: [],
  gesamt: ['dashboard', 'map'],
}
const tabs = computed(() => TABS[config.value?.type ?? 'gesamt'] ?? [])
const view = ref('')
const rotating = ref(false)
const activeProject = ref('')

function resetView() {
  view.value = tabs.value[0] ?? ''
  rotating.value = !!config.value?.rotate
  activeProject.value = ''
}
watch(config, resetView, { immediate: true })
function select(tab: string) {
  view.value = tab
}

// Daten (immer aus dem gemeinsamen Demo-State, hängen an «now» für die ETA-Simulation)
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
const trips = computed(() => {
  void now.value
  return tripsOf(filter.value).filter((trip) => matchesTaskFilter(trip.task, filter.value))
})
watch(boards, (list) => {
  if (!activeProject.value && list[0]) activeProject.value = list[0].order.id
}, { immediate: true })

// Live-Feed (Bereichsfilter nach Typ) und prominente neue Ereignisse
const feed = computed<GaLiveEvent[]>(() => {
  const type = config.value?.type
  const match = type === 'material' ? (event: GaLiveEvent) => event.area === 'material' || event.area === 'problem'
    : type === 'logistik' ? (event: GaLiveEvent) => event.area === 'logistics' || event.area === 'problem'
      : undefined
  return recentEvents(8, match)
})
const banner = ref<GaLiveEvent | null>(null)
let bannerTimer: ReturnType<typeof setTimeout> | undefined
watch(
  () => events.value[0]?.id,
  (id, previous) => {
    if (!previous || !id) return
    const event = events.value[0]!
    banner.value = event
    if (bannerTimer) clearTimeout(bannerTimer)
    bannerTimer = setTimeout(() => {
      banner.value = null
    }, 8000)
  },
)

// Rotation (nur simuliert)
let rotateTimer: ReturnType<typeof setInterval> | undefined
function restartRotation() {
  if (rotateTimer) clearInterval(rotateTimer)
  if (!rotating.value || !config.value) return
  rotateTimer = setInterval(() => {
    if (config.value?.type === 'projekt') {
      const list = boards.value
      const index = list.findIndex((board) => board.order.id === activeProject.value)
      activeProject.value = list[(index + 1) % Math.max(1, list.length)]?.order.id ?? ''
      return
    }
    const list = tabs.value
    const index = list.indexOf(view.value)
    view.value = list[(index + 1) % Math.max(1, list.length)] ?? ''
  }, Math.max(3, config.value.rotateSec) * 1000)
}
watch([rotating, config], restartRotation)

// Vollbild
const rootEl = ref<HTMLElement | null>(null)
const isFullscreen = ref(false)
function syncFullscreen() {
  isFullscreen.value = !!document.fullscreenElement
}
function toggleFullscreen() {
  if (document.fullscreenElement) void document.exitFullscreen()
  else void rootEl.value?.requestFullscreen?.()
}

const showAdmin = computed(() => isDevToolsEnvironment())
function goAdmin() {
  const dept = authStore.activeDepartmentId
  void router.push(dept ? `/${dept}/displays` : '/login')
}

onMounted(() => {
  timer = setInterval(() => {
    now.value = new Date()
  }, 1000)
  document.addEventListener('fullscreenchange', syncFullscreen)
  restartRotation()
})
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
  if (rotateTimer) clearInterval(rotateTimer)
  if (bannerTimer) clearTimeout(bannerTimer)
  document.removeEventListener('fullscreenchange', syncFullscreen)
})
</script>

<style scoped>
.tv {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-height: 100dvh;
  height: 100dvh;
  padding: 22px 28px;
  overflow: auto;
  background: #0b1220;
  color: #f8fafc;
  font-family: inherit;
}
.tv__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 14px 28px;
}
.tv__title {
  display: flex;
  align-items: center;
  gap: 16px;
}
.tv__title h1 {
  margin: 0;
  font-size: 2.6rem;
  line-height: 1.1;
}
.tv__title p {
  margin: 2px 0 0;
  font-size: 1.15rem;
  color: #94a3b8;
}
.tv__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.tv__tab {
  padding: 10px 22px;
  border: 0;
  border-radius: 999px;
  background: #111c33;
  color: #cbd5e1;
  font-size: 1.3rem;
  cursor: pointer;
}
.tv__tab--on {
  background: #f8fafc;
  color: #0b1220;
  font-weight: 700;
}
.tv__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 18px;
}
.tv__rotate {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #94a3b8;
  font-size: 1rem;
}
.tv__clock {
  font-size: 2.2rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
.tv__btn {
  padding: 8px 16px;
  border: 1px solid #334155;
  border-radius: 10px;
  background: transparent;
  color: #cbd5e1;
  font-size: 1rem;
  cursor: pointer;
}
.tv__banner {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px 16px;
  padding: 18px 26px;
  border-radius: 18px;
  background: #1d4ed8;
  font-size: 2rem;
}
.tv__banner--success {
  background: #15803d;
}
.tv__banner--warning {
  background: #b45309;
}
.tv__banner--error {
  background: #b91c1c;
}
.tv__banner small {
  font-size: 1.2rem;
  opacity: 0.9;
}
.banner-enter-active,
.banner-leave-active {
  transition: all 0.4s ease;
}
.banner-enter-from,
.banner-leave-to {
  opacity: 0;
  transform: translateY(-16px);
}
.tv__missing {
  font-size: 2rem;
  color: #94a3b8;
}
.tv__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 420px;
  gap: 22px;
  flex: 1 1 auto;
  min-height: 0;
}
.tv__body--wide {
  grid-template-columns: minmax(0, 1fr);
}
.tv__main {
  min-width: 0;
}
.tv__mapfull :deep(.site) {
  height: auto;
  aspect-ratio: 640 / 380;
}
@media (max-width: 1100px) {
  .tv__body {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
