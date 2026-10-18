<template>
  <svg :viewBox="`0 0 640 380`" class="site" role="img" :aria-label="label">
    <rect width="640" height="380" :fill="dark ? '#16213a' : '#e8eef0'" />
    <path d="M0 300 Q160 250 320 290 T640 260 V380 H0 Z" :fill="dark ? '#1b3a3a' : '#d6e4dc'" />
    <g v-for="route in routes" :key="route.id">
      <polyline :points="route.points" fill="none" :stroke="route.color" stroke-width="3" :stroke-dasharray="route.dashed ? '6 6' : ''" opacity="0.9" />
    </g>
    <g v-for="place in places" :key="place.name">
      <circle :cx="place.x" :cy="place.y" r="6" :fill="dark ? '#94a3b8' : '#64748b'" />
      <text :x="place.x + 10" :y="place.y + 4" class="site__text site__text--place" :fill="dark ? '#cbd5e1' : '#334155'">{{ place.name }}</text>
    </g>
    <g v-for="project in projects" :key="project.id" :class="{ 'site__project--active': project.id === activeId }">
      <circle v-if="project.health === 'critical'" :cx="project.x" :cy="project.y" r="22" fill="none" :stroke="HEALTH_COLOR.critical" stroke-width="3" class="site__pulse" />
      <circle :cx="project.x" :cy="project.y" :r="project.id === activeId ? 17 : 13" :fill="HEALTH_COLOR[project.health]" stroke="#fff" stroke-width="3" />
      <text :x="project.x" :y="project.y - 22" text-anchor="middle" class="site__text site__text--project" :fill="dark ? '#fff' : '#0f172a'">{{ project.name }}</text>
    </g>
    <g v-for="vehicle in vehicles" :key="vehicle.id">
      <rect :x="vehicle.x - 11" :y="vehicle.y - 9" width="22" height="18" rx="4" :fill="vehicle.mode === 'gps' ? '#7c3aed' : '#2563eb'" stroke="#fff" stroke-width="2" />
      <text :x="vehicle.x" :y="vehicle.y + 26" text-anchor="middle" class="site__text site__text--vehicle" :fill="dark ? '#e2e8f0' : '#1e293b'">{{ vehicle.label }}</text>
      <text v-if="vehicle.note" :x="vehicle.x" :y="vehicle.y + 40" text-anchor="middle" class="site__text site__text--note" :fill="dark ? '#fbbf24' : '#b45309'">{{ vehicle.note }}</text>
    </g>
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { placePos } from '@/views/grossanlass/live/gaSitePlaces'
import type { GaHealth, GaTrip } from '@/views/grossanlass/live/gaLiveModel'

const props = defineProps<{
  label: string
  places?: string[]
  projects?: Array<{ id: string; name: string; place: string; health: GaHealth }>
  trips?: GaTrip[]
  activeId?: string
  dark?: boolean
  estimateLabel?: string
  gpsLabel?: string
}>()

const HEALTH_COLOR: Record<GaHealth, string> = { ok: '#16a34a', attention: '#f59e0b', critical: '#dc2626' }

const places = computed(() => (props.places ?? []).map((name) => ({ name, ...placePos(name) })))
const projects = computed(() => (props.projects ?? []).map((project) => ({ ...project, ...placePos(project.place) })))

const routes = computed(() =>
  (props.trips ?? []).filter((trip) => !trip.done).map((trip) => {
    const a = placePos(trip.from)
    const b = placePos(trip.to)
    return {
      id: trip.task.id,
      points: `${a.x},${a.y} ${b.x},${b.y}`,
      color: trip.underway ? '#38bdf8' : '#94a3b8',
      dashed: !trip.underway,
    }
  }),
)

/** Position auf der Route: ETA = geschätzt entlang der Strecke, ohne Tracking am Start, GPS = Platzhalter am Start. */
const vehicles = computed(() =>
  (props.trips ?? []).filter((trip) => !trip.done && trip.stepIndex >= 0).map((trip) => {
    const a = placePos(trip.from)
    const b = placePos(trip.to)
    const arrived = trip.stepKey === 'arrived' || trip.stepKey === 'unloaded'
    const t = arrived ? 1 : trip.mode === 'eta' && trip.progress !== null ? trip.progress : 0
    const note = trip.mode === 'eta' && trip.underway ? (props.estimateLabel ?? '') : trip.mode === 'gps' ? (props.gpsLabel ?? '') : ''
    return {
      id: trip.task.id,
      x: a.x + (b.x - a.x) * t,
      y: a.y + (b.y - a.y) * t,
      label: `${trip.vehicle}`,
      mode: trip.mode,
      note,
    }
  }),
)
</script>

<style scoped>
.site {
  width: 100%;
  height: 100%;
  border-radius: 14px;
}
.site__text {
  font-size: 12px;
  font-weight: 600;
}
.site__text--place {
  font-size: 11px;
  font-weight: 500;
}
.site__text--project {
  font-size: 14px;
  font-weight: 700;
}
.site__text--vehicle {
  font-size: 11px;
}
.site__text--note {
  font-size: 10px;
  font-weight: 500;
}
.site__pulse {
  animation: pulse 1.4s ease-out infinite;
  transform-origin: center;
  transform-box: fill-box;
}
@keyframes pulse {
  0% {
    opacity: 0.9;
    transform: scale(0.8);
  }
  100% {
    opacity: 0;
    transform: scale(1.6);
  }
}
</style>
