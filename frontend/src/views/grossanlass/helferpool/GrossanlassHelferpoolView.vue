<template>
  <PageShell class="helferpool" :title="t('grossanlass.helferpool.title')" :subtitle="t('grossanlass.helferpool.subtitle')">
    <template #filters>
      <v-tabs v-model="tab" class="materials-view-tabs" color="primary" show-arrows>
        <v-tab value="helpers"><v-icon icon="mdi-account-group-outline" start size="18" />{{ t('grossanlass.helferpool.tab.helpers') }}</v-tab>
        <v-tab value="timeline"><v-icon icon="mdi-chart-gantt" start size="18" />{{ t('grossanlass.helferpool.tab.timeline') }}</v-tab>
        <v-tab value="skills"><v-icon icon="mdi-hammer-wrench" start size="18" />{{ t('grossanlass.helferpool.tab.skills') }}</v-tab>
      </v-tabs>
    </template>

    <div class="hp">
      <p class="hp__intro">
        {{ t('grossanlass.helferpool.intro') }}
        <v-chip size="x-small" variant="tonal" color="warning" class="ml-2">{{ t('grossanlass.helferpool.prototype') }}</v-chip>
      </p>

      <section class="hp__kpis">
        <button v-for="kpi in kpis" :key="kpi.key" type="button" class="kpi" :class="[`kpi--${kpi.key}`, { 'kpi--active': availabilityKpi === kpi.key }]" @click="toggleKpi(kpi.key)">
          <strong>{{ kpi.count }}</strong><span>{{ t(`grossanlass.helferpool.status.${kpi.key}`) }}</span>
        </button>
      </section>

      <!-- Bedarf aus Aufgabe -->
      <section v-if="needs.length" class="need">
        <header class="need__head">
          <div>
            <p class="need__label"><v-icon icon="mdi-clipboard-account-outline" size="16" /> {{ t('grossanlass.helferpool.need.title') }}</p>
            <ESelect v-model="needId" :items="needItems" :label="t('grossanlass.helferpool.need.task')" hide-details />
          </div>
          <v-chip v-if="activeNeed" size="small" variant="flat" :color="staffingOf(activeNeed).assigned >= staffingOf(activeNeed).need ? 'success' : 'warning'">
            {{ t('grossanlass.helferpool.need.staffed', staffingOf(activeNeed)) }}
          </v-chip>
        </header>
        <template v-if="activeNeed">
          <p class="need__req">
            <strong>{{ t('grossanlass.helferpool.need.requirement', { count: activeNeed.helperNeed?.count, skill: activeNeed.helperNeed?.skill }) }}</strong>
            · {{ windowLabel({ from: activeNeed.startsAt, to: activeNeed.endsAt }, locale) }}
            · {{ activeNeed.origin.join(' · ') }}
          </p>
          <div class="need__bulk">
            <EButton variant="primary" :disabled="!fits.length || missing <= 0" @click="assignFits">
              <v-icon icon="mdi-account-multiple-plus-outline" start size="18" />
              {{ t('grossanlass.helferpool.need.assignFits', { n: Math.min(fits.length, Math.max(missing, 0)) }) }}
            </EButton>
            <span class="need__hint">{{ t('grossanlass.helferpool.need.noOptimization') }}</span>
          </div>
          <ul class="matches">
            <li v-for="match in matches" :key="match.helper.id" class="match" :class="`match--${match.kind}`">
              <button type="button" class="match__who" @click="openHelper(match.helper.id)">
                <strong>{{ match.helper.name }}</strong>
                <small>{{ match.helper.org }}</small>
              </button>
              <span class="match__reason">
                <v-icon :icon="MATCH_ICON[match.kind]" size="16" />
                <template v-if="match.kind === 'busy'">{{ t('grossanlass.helferpool.need.busy', { task: match.conflict?.label }) }}</template>
                <template v-else>{{ t(`grossanlass.helferpool.need.match.${match.kind}`) }}</template>
              </span>
              <EButton v-if="match.kind === 'fit'" size="small" variant="primary" @click="assign(match.helper)">{{ t('grossanlass.helferpool.assign') }}</EButton>
              <EButton v-else-if="match.kind === 'assigned'" size="small" variant="secondary" @click="unassign(match.helper)">{{ t('grossanlass.helferpool.unassign') }}</EButton>
              <EButton v-else size="small" variant="secondary" disabled>{{ t('grossanlass.helferpool.assign') }}</EButton>
            </li>
          </ul>
        </template>
      </section>

      <!-- Filter -->
      <section class="hp__filters">
        <ESearchField v-model="filters.search" :label="t('grossanlass.helferpool.filter.search')" />
        <ESelect v-model="periodId" :items="periodItems" :label="t('grossanlass.helferpool.filter.period')" hide-details />
        <ESelect v-model="filters.ressort" :items="ressortItems" :label="t('grossanlass.helferpool.filter.ressort')" clearable hide-details />
        <ESelect v-model="filters.skill" :items="skillItems" :label="t('grossanlass.helferpool.filter.skill')" clearable hide-details />
        <ESelect v-model="filters.license" :items="licenseItems" :label="t('grossanlass.helferpool.filter.license')" clearable hide-details />
        <ESelect v-model="filters.availability" :items="availabilityItems" :label="t('grossanlass.helferpool.filter.availability')" hide-details />
        <ESelect v-model="filters.org" :items="orgItems" :label="t('grossanlass.helferpool.filter.org')" clearable hide-details />
      </section>

      <EEmptyState v-if="!visible.length" variant="generic" icon="mdi-account-search-outline" :title="t('grossanlass.helferpool.emptyTitle')" :description="t('grossanlass.helferpool.emptyText')" />

      <!-- Helfer -->
      <div v-if="tab === 'helpers'" class="cards">
        <article v-for="helper in visible" :key="helper.id" class="helper" :class="`helper--${statusOf(helper)}`">
          <header class="helper__head">
            <button type="button" class="helper__name" @click="openHelper(helper.id)">
              <strong>{{ helper.name }}</strong><small>{{ helper.org }}</small>
            </button>
            <v-chip size="x-small" variant="flat" :color="STATUS_COLOR[statusOf(helper)]">{{ t(`grossanlass.helferpool.status.${statusOf(helper)}`) }}</v-chip>
          </header>
          <div class="chips">
            <v-chip v-for="skill in helper.skills" :key="skill" size="x-small" variant="tonal" color="primary">{{ skill }}</v-chip>
          </div>
          <div class="chips">
            <v-chip v-for="license in helper.licenses" :key="license" size="x-small" variant="outlined" prepend-icon="mdi-card-account-details-outline">{{ license }}</v-chip>
            <span v-if="!helper.licenses.length" class="muted">{{ t('grossanlass.helferpool.noLicense') }}</span>
          </div>
          <p class="helper__line"><v-icon icon="mdi-calendar-clock-outline" size="14" /> {{ t('grossanlass.helferpool.hours', { free: freeHours(helper), total: totalHours(helper) }) }}</p>
          <ul class="helper__assign">
            <li v-for="entry in assignmentsOf(helper).slice(0, 2)" :key="`${entry.source}-${entry.id}`">
              <v-icon :icon="entry.source === 'tour' ? 'mdi-truck-fast-outline' : 'mdi-clipboard-check-outline'" size="14" /> {{ entry.label }}
              <small>{{ windowLabel({ from: entry.startsAt, to: entry.endsAt }, locale) }}</small>
            </li>
            <li v-if="!assignmentsOf(helper).length" class="muted">{{ t('grossanlass.helferpool.noAssignments') }}</li>
          </ul>
          <EButton size="small" variant="secondary" @click="openHelper(helper.id)">{{ t('grossanlass.helferpool.details') }}</EButton>
        </article>
      </div>

      <!-- Zeitplan -->
      <section v-else-if="tab === 'timeline'" class="timeline">
        <div class="timeline__scroll">
          <div class="timeline__row timeline__row--head">
            <span class="timeline__label" />
            <span class="timeline__track" :style="{ minWidth: `${dayCount * 96}px` }">
              <span v-for="(day, index) in dayList" :key="day.toISOString()" class="timeline__day" :style="{ left: `${(index / dayCount) * 100}%`, width: `${100 / dayCount}%` }">
                {{ day.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'numeric' }) }}
              </span>
            </span>
          </div>
          <div v-for="helper in visible" :key="helper.id" class="timeline__row">
            <button type="button" class="timeline__label" @click="openHelper(helper.id)">
              <strong>{{ helper.name }}</strong>
              <v-chip size="x-small" variant="flat" :color="STATUS_COLOR[statusOf(helper)]">{{ t(`grossanlass.helferpool.status.${statusOf(helper)}`) }}</v-chip>
            </button>
            <span class="timeline__track" :style="{ minWidth: `${dayCount * 96}px` }">
              <span v-for="window in helper.windows" :key="window.from.toISOString()" class="timeline__avail" :style="barStyle(window)" />
              <button
                v-for="entry in assignmentsOf(helper)"
                :key="`${entry.source}-${entry.id}`"
                type="button"
                class="timeline__block"
                :class="`timeline__block--${entry.kind}`"
                :style="barStyle({ from: entry.startsAt, to: entry.endsAt })"
                :title="`${entry.label} · ${windowLabel({ from: entry.startsAt, to: entry.endsAt }, locale)}`"
                @click="openHelper(helper.id)"
              >{{ entry.label }}</button>
            </span>
          </div>
        </div>
        <ul class="timeline__legend">
          <li><span class="sw sw--avail" /> {{ t('grossanlass.helferpool.legend.available') }}</li>
          <li><span class="sw sw--task" /> {{ t('grossanlass.helferpool.legend.assigned') }}</li>
        </ul>
      </section>

      <!-- Fähigkeiten -->
      <section v-else class="skills">
        <div class="skills__scroll">
          <table class="matrix">
            <thead>
              <tr>
                <th>{{ t('grossanlass.helferpool.tab.helpers') }}</th>
                <th v-for="skill in GA_SKILLS" :key="skill">
                  <button type="button" :class="{ 'is-active': filters.skill === skill }" @click="filters.skill = filters.skill === skill ? '' : skill">{{ skill }}</button>
                  <small>{{ matrix.find((row) => row.skill === skill)?.helpers.length }}</small>
                </th>
                <th v-for="license in GA_LICENSES" :key="license" class="matrix__license">
                  <button type="button" :class="{ 'is-active': filters.license === license }" @click="filters.license = filters.license === license ? '' : license">{{ license }}</button>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="helper in visible" :key="helper.id">
                <td><button type="button" class="matrix__name" @click="openHelper(helper.id)">{{ helper.name }}</button></td>
                <td v-for="skill in GA_SKILLS" :key="skill" class="matrix__cell">
                  <v-icon v-if="helper.skills.includes(skill)" icon="mdi-check-circle" color="primary" size="18" />
                </td>
                <td v-for="license in GA_LICENSES" :key="license" class="matrix__cell matrix__license">
                  <v-icon v-if="helper.licenses.includes(license)" icon="mdi-card-account-details" color="secondary" size="18" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <GrossanlassHelferDrawer v-model="drawerOpen" :helper="drawerHelper" :period="period" />
  </PageShell>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ESearchField, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import PageShell from '@/components/layout/PageShell.vue'
import { useToast } from '@/composables/useToast'
import GrossanlassHelferDrawer from './GrossanlassHelferDrawer.vue'
import {
  GA_ANLASS_PERIOD,
  GA_HELPERS,
  GA_LICENSES,
  GA_SKILLS,
  assignHelper,
  assignmentsOf,
  availableMs,
  busyMs,
  emptyHelperFilters,
  helperStatus,
  matchNeed,
  matchesHelper,
  needTasks,
  skillMatrix,
  staffing,
  statusCounts,
  unassignHelper,
  useGaHelferMock,
  type GaHelper,
  type GaHelperStatus,
  type GaMatchKind,
  type GaTimeWindow,
} from './gaHelferMock'
import { STATUS_COLOR, windowLabel } from './gaHelferUi'
import { anlassAt, type GaAufgabe } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import '@/styles/views/materials-view-tabs.css'

const { t, locale } = useI18n()
const toast = useToast()
const { tasks } = useGaHelferMock()

const tab = ref<'helpers' | 'timeline' | 'skills'>('helpers')
const filters = reactive(emptyHelperFilters())
const periodId = ref<'anlass' | 'day' | 'next14'>('anlass')
const availabilityKpi = ref<GaHelperStatus | ''>('')
const drawerOpen = ref(false)
const drawerHelperId = ref<string | null>(null)
const needId = ref<string>('')

const MATCH_ICON: Record<GaMatchKind, string> = { fit: 'mdi-check-circle', busy: 'mdi-alert-circle-outline', unavailable: 'mdi-clock-remove-outline', assigned: 'mdi-account-check' }

const period = computed<GaTimeWindow>(() => {
  if (periodId.value === 'day') return { from: anlassAt(4, 0), to: anlassAt(4, 23, 59) }
  if (periodId.value === 'next14') {
    const from = new Date()
    from.setHours(0, 0, 0, 0)
    const to = new Date(from)
    to.setDate(to.getDate() + 14)
    return { from, to }
  }
  return GA_ANLASS_PERIOD
})
const periodItems = computed(() => [
  { value: 'anlass', title: t('grossanlass.helferpool.period.anlass') },
  { value: 'day', title: t('grossanlass.helferpool.period.day') },
  { value: 'next14', title: t('grossanlass.helferpool.period.next14') },
])
const availabilityItems = computed(() => [
  { value: 'all', title: t('grossanlass.helferpool.filter.all') },
  { value: 'free', title: t('grossanlass.helferpool.filter.free') },
  { value: 'available', title: t('grossanlass.helferpool.filter.availableAny') },
])
const skillItems = [...GA_SKILLS]
const licenseItems = [...GA_LICENSES]
const orgItems = computed(() => [...new Set(GA_HELPERS.map((helper) => helper.org))].sort())
const ressortItems = computed(() => [...new Set(tasks.value.map((task) => task.ressort))].sort())

const counts = computed(() => statusCounts(period.value))
const kpis = computed(() => (['free', 'partial', 'busy', 'unavailable'] as const).map((key) => ({ key, count: counts.value[key] })))
function toggleKpi(key: GaHelperStatus) {
  availabilityKpi.value = availabilityKpi.value === key ? '' : key
}

function statusOf(helper: GaHelper): GaHelperStatus {
  void tasks.value
  return helperStatus(helper, period.value)
}
const visible = computed(() => {
  void tasks.value
  return GA_HELPERS.filter((helper) => matchesHelper(helper, filters, period.value) && (!availabilityKpi.value || helperStatus(helper, period.value) === availabilityKpi.value))
})
const matrix = computed(() => skillMatrix())
function totalHours(helper: GaHelper): number {
  return Math.round((availableMs(helper, period.value) / 3_600_000) * 10) / 10
}
function freeHours(helper: GaHelper): number {
  void tasks.value
  return Math.max(0, Math.round(((availableMs(helper, period.value) - busyMs(helper, period.value)) / 3_600_000) * 10) / 10)
}

// Bedarf
const needs = computed(() => needTasks())
const needItems = computed(() => needs.value.map((task) => ({ value: task.id, title: task.title })))
const activeNeed = computed<GaAufgabe | undefined>(() => needs.value.find((task) => task.id === needId.value))
const matches = computed(() => {
  void tasks.value
  return activeNeed.value ? matchNeed(activeNeed.value) : []
})
const fits = computed(() => matches.value.filter((match) => match.kind === 'fit'))
const missing = computed(() => {
  if (!activeNeed.value) return 0
  const { assigned, need } = staffing(activeNeed.value)
  return need - assigned
})
function staffingOf(task: GaAufgabe) {
  void tasks.value
  return staffing(task)
}
function assign(helper: GaHelper) {
  if (activeNeed.value && assignHelper(activeNeed.value, helper)) {
    toast.success(t('grossanlass.helferpool.assigned', { name: helper.name, task: activeNeed.value.title }))
  }
}
function unassign(helper: GaHelper) {
  if (activeNeed.value) unassignHelper(activeNeed.value, helper)
}
function assignFits() {
  const task = activeNeed.value
  if (!task) return
  let n = 0
  for (const match of fits.value.slice(0, Math.max(missing.value, 0))) {
    if (assignHelper(task, match.helper)) n += 1
  }
  if (n) toast.success(t('grossanlass.helferpool.assignedMany', { n }))
}

watch(needs, (list) => {
  if (!needId.value && list[0]) needId.value = list[0].id
}, { immediate: true })

// Detail
const drawerHelper = computed(() => GA_HELPERS.find((helper) => helper.id === drawerHelperId.value) ?? null)
function openHelper(id: string) {
  drawerHelperId.value = id
  drawerOpen.value = true
}

// Zeitplan
const dayList = computed(() => {
  const start = new Date(period.value.from)
  start.setHours(0, 0, 0, 0)
  const out: Date[] = []
  for (let cursor = new Date(start); cursor <= period.value.to; cursor.setDate(cursor.getDate() + 1)) out.push(new Date(cursor))
  return out
})
const dayCount = computed(() => Math.max(1, dayList.value.length))
function barStyle(window: GaTimeWindow): Record<string, string> {
  const start = dayList.value[0]!.getTime()
  const total = dayCount.value * 86_400_000
  const left = Math.max(0, (window.from.getTime() - start) / total) * 100
  const right = Math.min(1, (window.to.getTime() - start) / total) * 100
  return { left: `${left}%`, width: `${Math.max(0, right - left)}%`, display: right <= left ? 'none' : '' }
}
</script>

<style scoped>
.hp {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-bottom: 28px;
}
.hp__intro {
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
}
.hp__kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}
.kpi {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.kpi strong {
  font-size: 1.6rem;
  line-height: 1.1;
}
.kpi span {
  font-size: 0.8rem;
  color: #64748b;
}
.kpi--active {
  border-color: #059669;
  box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.18);
}
.kpi--free strong {
  color: #15803d;
}
.kpi--busy strong {
  color: #b91c1c;
}
.need {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border: 1px solid #a7f3d0;
  border-radius: 14px;
  background: #f0fdf4;
}
.need__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-end;
  gap: 10px;
}
.need__head > div {
  flex: 1 1 280px;
}
.need__label {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 6px;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #065f46;
}
.need__req {
  margin: 0;
  font-size: 0.9rem;
}
.need__bulk {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}
.need__hint {
  color: #64748b;
  font-size: 0.8rem;
}
.matches {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.match {
  display: grid;
  grid-template-columns: minmax(140px, 1fr) minmax(0, 2fr) auto;
  gap: 10px;
  align-items: center;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}
.match--fit {
  border-color: #86efac;
}
.match--assigned {
  background: #ecfdf5;
}
.match--busy {
  background: #fffbeb;
}
.match--unavailable {
  background: #f8fafc;
  color: #64748b;
}
.match__who {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  border: 0;
  background: none;
  text-align: left;
  cursor: pointer;
}
.match__who small {
  color: #64748b;
}
.match__reason {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.84rem;
}
.hp__filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 10px;
}
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}
.helper {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #94a3b8;
  border-radius: 12px;
  background: #fff;
}
.helper--free {
  border-left-color: #16a34a;
}
.helper--partial {
  border-left-color: #f59e0b;
}
.helper--busy {
  border-left-color: #dc2626;
}
.helper__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}
.helper__name {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  border: 0;
  background: none;
  text-align: left;
  cursor: pointer;
}
.helper__name small {
  color: #64748b;
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.muted {
  color: #94a3b8;
  font-size: 0.82rem;
}
.helper__line {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 0.84rem;
  color: #475569;
}
.helper__assign {
  display: grid;
  gap: 4px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 0.84rem;
}
.helper__assign small {
  display: block;
  margin-left: 18px;
  color: #64748b;
}
.timeline {
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.timeline__scroll {
  overflow-x: auto;
}
.timeline__row {
  display: grid;
  grid-template-columns: 150px minmax(0, 1fr);
  gap: 8px;
  align-items: center;
  min-height: 44px;
  border-bottom: 1px solid #f1f5f9;
}
.timeline__row--head {
  min-height: 32px;
  font-size: 0.74rem;
  color: #64748b;
}
.timeline__label {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  border: 0;
  background: none;
  text-align: left;
  cursor: pointer;
  position: sticky;
  left: 0;
  z-index: 1;
  background: #fff;
}
.timeline__track {
  position: relative;
  height: 36px;
}
.timeline__day {
  position: absolute;
  top: 0;
  text-align: center;
  border-left: 1px solid #e5e7eb;
  font-size: 0.74rem;
}
.timeline__avail {
  position: absolute;
  top: 6px;
  height: 24px;
  border-radius: 6px;
  background: #dcfce7;
  border: 1px solid #86efac;
}
.timeline__block {
  position: absolute;
  top: 8px;
  height: 20px;
  padding: 0 6px;
  overflow: hidden;
  border: 0;
  border-radius: 6px;
  background: #2563eb;
  color: #fff;
  font-size: 0.68rem;
  white-space: nowrap;
  cursor: pointer;
}
.timeline__block--logistik {
  background: #7c3aed;
}
.timeline__block--material {
  background: #0891b2;
}
.timeline__block--werkstatt {
  background: #b45309;
}
.timeline__legend {
  display: flex;
  gap: 16px;
  margin: 10px 0 0;
  padding: 0;
  list-style: none;
  font-size: 0.8rem;
  color: #475569;
}
.sw {
  display: inline-block;
  width: 22px;
  height: 12px;
  margin-right: 6px;
  border-radius: 3px;
  vertical-align: middle;
}
.sw--avail {
  background: #dcfce7;
  border: 1px solid #86efac;
}
.sw--task {
  background: #2563eb;
}
.skills {
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.skills__scroll {
  overflow-x: auto;
}
.matrix {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.84rem;
}
.matrix th,
.matrix td {
  padding: 6px 8px;
  border-bottom: 1px solid #f1f5f9;
  text-align: center;
}
.matrix th:first-child,
.matrix td:first-child {
  text-align: left;
  position: sticky;
  left: 0;
  background: #fff;
}
.matrix th button,
.matrix__name {
  border: 0;
  background: none;
  font-weight: 600;
  cursor: pointer;
}
.matrix th button.is-active {
  color: #059669;
  text-decoration: underline;
}
.matrix th small {
  display: block;
  color: #64748b;
  font-weight: 400;
}
.matrix__license {
  background: #f8fafc;
}
@media (max-width: 720px) {
  .hp__kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .match {
    grid-template-columns: 1fr;
  }
}
</style>
