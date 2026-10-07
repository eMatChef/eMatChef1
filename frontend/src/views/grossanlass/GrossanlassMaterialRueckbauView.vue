<template>
  <div class="rueckbau">
    <p class="rueckbau__intro">
      {{ t('grossanlass.rueckbau.intro') }}
      <v-chip size="x-small" variant="tonal" color="warning" class="ml-2">{{ t('grossanlass.rueckbau.prototype') }}</v-chip>
    </p>

    <ol class="rueckbau__cycle" :aria-label="t('grossanlass.rueckbau.cycle.label')">
      <li v-for="(step, index) in cycleSteps" :key="step">
        <span class="rueckbau__dot">{{ index + 1 }}</span>{{ t(`grossanlass.rueckbau.cycle.${step}`) }}
      </li>
    </ol>

    <section class="rueckbau__kpis">
      <div class="kpi"><strong>{{ totals.openPositions }}</strong><span>{{ t('grossanlass.rueckbau.kpi.open') }}</span></div>
      <div class="kpi kpi--ok"><strong>{{ totals.donePositions }}</strong><span>{{ t('grossanlass.rueckbau.kpi.done') }}</span></div>
      <div class="kpi kpi--warn"><strong>{{ totals.workshop }}</strong><span>{{ t('grossanlass.rueckbau.kpi.workshop') }}</span></div>
      <div class="kpi"><strong>{{ totals.returnFirms }}</strong><span>{{ t('grossanlass.rueckbau.kpi.returns') }}</span></div>
      <div class="kpi kpi--wide">
        <v-progress-linear :model-value="totals.percent" height="10" rounded color="primary" />
        <span>{{ t('grossanlass.rueckbau.kpi.progress', { percent: totals.percent }) }}</span>
      </div>
    </section>

    <section class="rueckbau__bar">
      <div class="rueckbau__filters" role="group" :aria-label="t('grossanlass.rueckbau.filter.label')">
        <EButton
          v-for="option in filterOptions"
          :key="option"
          size="small"
          :variant="filter === option ? 'primary' : 'secondary'"
          @click="filter = option"
        >
          {{ t(`grossanlass.rueckbau.filter.${option}`) }}
          <span class="rueckbau__count">{{ counts[option] }}</span>
        </EButton>
      </div>
      <ESelect v-model="project" :items="projectItems" :label="t('grossanlass.rueckbau.project')" clearable hide-details class="rueckbau__project" />
    </section>

    <EEmptyState
      v-if="!visibleProjects.length"
      variant="generic"
      icon="mdi-package-variant-closed-remove"
      :title="t('grossanlass.rueckbau.emptyTitle')"
      :description="t('grossanlass.rueckbau.emptyText')"
    />

    <section v-for="name in visibleProjects" :key="name" class="project">
      <header class="project__head">
        <h3>{{ t('grossanlass.rueckbau.projectTitle', { name }) }}</h3>
        <span class="project__progress">
          {{ projectProgress(items, name).doneRows }}/{{ projectProgress(items, name).positions }}
          · {{ projectProgress(items, name).percent }} %
        </span>
      </header>
      <v-progress-linear :model-value="projectProgress(items, name).percent" height="6" rounded color="primary" />

      <ul class="rows">
        <li
          v-for="row in rowsOf(name)"
          :key="row.id"
          class="row"
          :class="`row--${itemStatus(row)}`"
        >
          <span class="row__state" :aria-label="t(`grossanlass.rueckbau.state.${itemStatus(row)}`)">
            <v-icon :icon="STATE_ICON[itemStatus(row)]" size="22" />
          </span>
          <div class="row__main">
            <strong>{{ row.name }}</strong>
            <span class="row__sub">
              {{ row.ressort }} · {{ row.origin }}
            </span>
            <span class="row__chips">
              <v-chip size="x-small" variant="tonal" :color="CONDITION_COLOR[row.condition]">
                {{ t(`grossanlass.rueckbau.condition.${row.condition}`) }}
              </v-chip>
              <v-chip v-if="row.plannedFate" size="x-small" variant="outlined" :prepend-icon="FATE_ICON[row.plannedFate]">
                {{ t('grossanlass.rueckbau.planned') }}: {{ t(`grossanlass.rueckbau.fate.${row.plannedFate}`) }}
              </v-chip>
              <v-chip
                v-if="row.chosenFate"
                size="x-small"
                variant="flat"
                :color="FATE_COLOR[row.chosenFate]"
                :prepend-icon="FATE_ICON[row.chosenFate]"
              >
                {{ t('grossanlass.rueckbau.chosen') }}: {{ t(`grossanlass.rueckbau.fate.${row.chosenFate}`) }}
              </v-chip>
              <v-chip v-else-if="effectiveFate(row) === 'workshop'" size="x-small" variant="flat" color="error" prepend-icon="mdi-wrench">
                {{ t('grossanlass.rueckbau.fate.workshop') }}
              </v-chip>
              <v-icon v-if="deviatesFromPlan(row)" icon="mdi-alert-outline" size="16" color="warning" :title="t('grossanlass.rueckbau.deviates')" />
              <v-chip v-if="row.transportRequested" size="x-small" variant="tonal" color="primary" prepend-icon="mdi-truck-fast-outline">
                {{ t('grossanlass.rueckbau.transportSent') }}
              </v-chip>
            </span>
            <span v-if="row.plannedLabel" class="row__source">{{ row.plannedLabel }}</span>
            <span v-if="row.detail" class="row__detail">{{ row.detail }}</span>
          </div>
          <div class="row__qty">
            <strong>{{ row.qtyDone }}/{{ row.qty }}</strong>
            <v-progress-linear :model-value="Math.round((row.qtyDone / row.qty) * 100)" height="6" rounded :color="isDone(row) ? 'success' : 'primary'" />
            <small v-if="!isDone(row)">{{ t('grossanlass.rueckbau.openQty', { n: openQty(row), total: row.qty }) }}</small>
          </div>
          <div class="row__actions">
            <EButton v-if="!isDone(row)" variant="primary" size="small" @click="openDecide(row.id)">
              {{ t('grossanlass.rueckbau.chooseFate') }}
            </EButton>
            <EButton v-if="!isDone(row) && row.plannedFate" variant="secondary" size="small" @click="asPlanned(row.id)">
              {{ t('grossanlass.rueckbau.decide.asPlanned') }}
            </EButton>
          </div>
        </li>
      </ul>
    </section>

    <GrossanlassVerkaufPanel v-if="filter === 'sale'" />
    <template v-if="filter === 'return'">
      <GrossanlassRueckgabePanel />
      <section class="legacy">
        <h3 class="legacy__title">{{ t('grossanlass.rueckbau.legacyTitle') }}</h3>
        <GrossanlassMaterialRetourView />
      </section>
    </template>
    <GrossanlassEntsorgungPanel v-if="filter === 'dispose'" />

    <GrossanlassRueckbauDecideDialog v-model="decideOpen" :item-id="decideId" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useToast } from '@/composables/useToast'
import GrossanlassMaterialRetourView from '@/views/grossanlass/GrossanlassMaterialRetourView.vue'
import GrossanlassEntsorgungPanel from '@/views/grossanlass/rueckbau/GrossanlassEntsorgungPanel.vue'
import GrossanlassRueckbauDecideDialog from '@/views/grossanlass/rueckbau/GrossanlassRueckbauDecideDialog.vue'
import GrossanlassRueckgabePanel from '@/views/grossanlass/rueckbau/GrossanlassRueckgabePanel.vue'
import GrossanlassVerkaufPanel from '@/views/grossanlass/rueckbau/GrossanlassVerkaufPanel.vue'
import {
  applyPlanned,
  deviatesFromPlan,
  effectiveFate,
  fateCounts,
  isDone,
  itemStatus,
  matchesFateFilter,
  openQty,
  projectProgress,
  projects,
  returnFirms,
  useGaRueckbauMock,
  type GaFateFilter,
} from '@/views/grossanlass/rueckbau/gaRueckbauMock'
import { CONDITION_COLOR, FATE_COLOR, FATE_ICON } from '@/views/grossanlass/rueckbau/gaRueckbauUi'

const { t } = useI18n()
const toast = useToast()
const { items } = useGaRueckbauMock()

const cycleSteps = ['procurement', 'planned', 'use', 'teardown', 'fate', 'dispo', 'closed'] as const
const filterOptions: GaFateFilter[] = ['all', 'lager', 'reuse', 'sale', 'return', 'dispose']
const STATE_ICON = { done: 'mdi-check-circle', open: 'mdi-circle-outline', warning: 'mdi-alert' } as const

const filter = ref<GaFateFilter>('all')
const project = ref<string | null>(null)

const counts = computed(() => fateCounts(items.value))
const projectItems = computed(() => projects(items.value))
const filtered = computed(() =>
  items.value.filter((row) => matchesFateFilter(row, filter.value) && (!project.value || row.project === project.value)),
)
const visibleProjects = computed(() => projects(filtered.value))
function rowsOf(name: string) {
  return filtered.value.filter((row) => row.project === name)
}

const totals = computed(() => {
  const all = items.value
  const total = all.reduce((sum, row) => sum + row.qty, 0)
  const done = all.reduce((sum, row) => sum + row.qtyDone, 0)
  return {
    openPositions: all.filter((row) => !isDone(row)).length,
    donePositions: all.filter(isDone).length,
    workshop: all.filter((row) => !isDone(row) && effectiveFate(row) === 'workshop').length,
    returnFirms: returnFirms().length,
    percent: total ? Math.round((done / total) * 100) : 0,
  }
})

const decideOpen = ref(false)
const decideId = ref<string | null>(null)
function openDecide(id: string) {
  decideId.value = id
  decideOpen.value = true
}
function asPlanned(id: string) {
  if (applyPlanned(id)) toast.success(t('grossanlass.rueckbau.decide.savedPlanned'))
}
</script>

<style scoped>
.rueckbau {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 4px 0 28px;
}
.rueckbau__intro {
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
}
.rueckbau__cycle {
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
.rueckbau__cycle li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.rueckbau__cycle li:not(:last-child)::after {
  content: '→';
  margin-left: 8px;
  color: #94a3b8;
}
.rueckbau__dot {
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
.rueckbau__kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr)) minmax(220px, 2fr);
  gap: 10px;
}
.kpi {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  justify-content: center;
}
.kpi strong {
  font-size: 1.6rem;
  line-height: 1.1;
}
.kpi span {
  font-size: 0.78rem;
  color: #64748b;
}
.kpi--ok strong {
  color: #15803d;
}
.kpi--warn strong {
  color: #b91c1c;
}
.rueckbau__bar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
}
.rueckbau__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.rueckbau__count {
  margin-left: 6px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.1);
  font-size: 0.72rem;
}
.rueckbau__project {
  max-width: 260px;
  min-width: 200px;
}
.project {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
}
.project__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
}
.project__head h3 {
  margin: 0;
  font-size: 1.05rem;
}
.project__progress {
  color: #475569;
  font-size: 0.84rem;
}
.rows {
  display: grid;
  gap: 4px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.row {
  display: grid;
  grid-template-columns: 28px minmax(0, 3fr) minmax(110px, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 10px 6px;
  border-bottom: 1px solid #f1f5f9;
}
.row--done .row__state {
  color: #16a34a;
}
.row--open .row__state {
  color: #94a3b8;
}
.row--warning .row__state {
  color: #dc2626;
}
.row--warning {
  background: #fef2f2;
  border-radius: 10px;
}
.row__main {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.row__sub,
.row__source,
.row__detail {
  font-size: 0.8rem;
  color: #64748b;
}
.row__chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}
.row__qty {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.row__qty small {
  color: #b45309;
}
.row__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-end;
}
.legacy {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.legacy__title {
  margin: 0;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
@media (max-width: 860px) {
  .rueckbau__kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .kpi--wide {
    grid-column: 1 / -1;
  }
  .row {
    grid-template-columns: 28px minmax(0, 1fr);
  }
  .row__qty,
  .row__actions {
    grid-column: 2;
    justify-content: flex-start;
  }
}
</style>
