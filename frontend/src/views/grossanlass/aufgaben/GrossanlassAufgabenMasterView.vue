<template>
  <div class="ga-aufgaben">
    <p class="ga-aufgaben__intro">
      {{ t('grossanlass.aufgaben.intro') }}
    </p>

    <section class="ga-aufgaben__kpis" :aria-label="t('grossanlass.aufgaben.overview')">
      <button
        v-for="kpi in kpis"
        :key="kpi.key"
        type="button"
        class="ga-kpi"
        :class="[`ga-kpi--${kpi.key}`, { 'ga-kpi--active': filters.status === kpi.key }]"
        @click="toggleStatus(kpi.key)"
      >
        <strong>{{ kpi.count }}</strong>
        <span>{{ t(`grossanlass.aufgaben.status.${kpi.key}`) }}</span>
      </button>
    </section>

    <section class="ga-aufgaben__filters">
      <div>
        <EButton size="small" variant="primary" :disabled="!projects.length" @click="openCreate">
          <v-icon icon="mdi-plus" start size="16" />
          {{ t('grossanlass.aufgaben.create.open') }}
        </EButton>
      </div>
      <ESwitch v-model="showArchived" :label="t('grossanlass.aufgaben.archive.showArchived')" hide-details density="compact" data-testid="show-archived" />
      <div class="ga-aufgaben__selects">
        <ESearchField v-model="filters.search" :label="t('grossanlass.aufgaben.filter.search')" />
        <ESelect v-model="filters.ressort" :items="ressortItems" :label="t('grossanlass.aufgaben.filter.ressort')" clearable hide-details />
        <ESelect v-model="filters.bereich" :items="bereichItems" :label="t('grossanlass.aufgaben.filter.bereich')" clearable hide-details />
        <ESelect v-model="filters.person" :items="personItems" :label="t('grossanlass.aufgaben.filter.person')" clearable hide-details />
        <ESelect v-model="filters.period" :items="periodItems" :label="t('grossanlass.aufgaben.filter.period')" hide-details />
        <ESelect v-model="filters.status" :items="statusItems" :label="t('grossanlass.aufgaben.filter.status')" hide-details />
      </div>
    </section>

    <ELoadingState v-if="loading && !tasks.length" />
    <EEmptyState
      v-else-if="!visible.length"
      variant="generic"
      icon="mdi-clipboard-check-outline"
      :title="t('grossanlass.aufgaben.emptyTitle')"
      :description="t('grossanlass.aufgaben.emptyText')"
    />

    <section v-for="group in groups" :key="group.bucket" class="ga-aufgaben__group">
      <h3 class="ga-aufgaben__group-title">
        {{ t(`grossanlass.aufgaben.bucket.${group.bucket}`) }}
        <span>{{ group.items.length }}</span>
      </h3>
      <div class="ga-aufgaben__list">
        <article
          v-for="task in group.items"
          :key="task.id"
          class="ga-card"
          :class="`ga-card--${statusKey(task, now)}`"
        >
          <header class="ga-card__head">
            <span class="ga-card__kind">
              <v-icon :icon="KIND_ICON[task.kind]" size="16" />
              {{ t(`grossanlass.aufgaben.kind.${task.kind}`) }}
            </span>
            <v-chip v-if="task.rework" size="x-small" variant="tonal" color="info" prepend-icon="mdi-wrench-outline" class="mr-1">
              {{ t('grossanlass.aufgaben.rework.chip') }}
            </v-chip>
            <v-chip v-if="task.waiting" size="x-small" variant="flat" color="warning" prepend-icon="mdi-timer-sand" class="mr-1">
              {{ t('grossanlass.aufgaben.deps.chip') }}
            </v-chip>
            <v-chip v-if="task.blocked" size="x-small" variant="flat" color="error" prepend-icon="mdi-alert-octagon-outline" class="mr-1">
              {{ t('grossanlass.aufgaben.blocked.chip') }}
            </v-chip>
            <v-chip size="x-small" variant="flat" :color="STATUS_COLOR[statusKey(task, now)]">
              {{ t(`grossanlass.aufgaben.status.${statusKey(task, now)}`) }}
            </v-chip>
          </header>
          <p class="ga-card__origin">{{ task.origin.join(' · ') }}</p>
          <h4 class="ga-card__title">{{ task.title }}</h4>
          <p class="ga-card__meta">
            <v-icon icon="mdi-clock-outline" size="14" />
            {{ dayLabel(task.startsAt, locale) }} · {{ whenLabel(task, locale) }}
            <template v-if="task.deadlineLabel"> · {{ task.deadlineLabel }}</template>
          </p>
          <p class="ga-card__meta">
            <v-icon icon="mdi-account-multiple-outline" size="14" />
            <template v-if="task.people.length">{{ task.people.join(' · ') }}</template>
            <strong v-else class="ga-card__unassigned">{{ t('grossanlass.aufgaben.unassigned') }}</strong>
          </p>
          <div v-if="task.kind !== 'logistik' || task.status !== 'open'" class="ga-card__progress">
            <v-progress-linear :model-value="taskProgress(task)" height="8" rounded color="primary" />
            <span>{{ taskProgress(task) }} %</span>
          </div>
          <footer class="ga-card__actions">
            <EButton v-if="!task.people.length" size="small" variant="secondary" @click="openTask(task)">
              {{ t('grossanlass.aufgaben.assign') }}
            </EButton>
            <EButton size="small" variant="secondary" @click="openTask(task)">
              {{ task.kind === 'logistik' ? t('grossanlass.aufgaben.openFahrauftrag') : t('grossanlass.aufgaben.open') }}
            </EButton>
          </footer>
        </article>
      </div>
    </section>

    <EDialog v-model="detailOpen" :title="selected?.title ?? ''" max-width="720" :retain-focus="false">
      <GrossanlassAufgabeDetail
        v-if="selected"
        :task="selected"
        can-assign
        :assignees="assignees"
        :error="actionError"
        @status="(status) => setStatus(selected!, status)"
        @step-add="(label) => addStep(selected!, label)"
        @step-done="(id, done) => setStepDone(selected!, id, done)"
        @step-move="(id, delta) => moveStep(selected!, id, delta)"
        @step-remove="(id) => removeStep(selected!, id)"
        @block="(reason) => block(selected!, reason)"
        @unblock="unblock(selected!)"
        @report="(note) => reportDone(selected!, note)"
        @accept="accept(selected!)"
        @give-back="(reason) => giveBack(selected!, reason)"
        :dependency-candidates="dependencyCandidates"
        @dependency-add="(id, mode) => addDependency(selected!, id, mode)"
        @dependency-mode="(dep, mode) => changeDependencyMode(selected!, dep, mode)"
        @dependency-remove="(dep) => removeDependency(selected!, dep)"
        @dependency-release="(dep, reason) => releaseDependency(selected!, dep, reason)"
        @assign="(userId) => setAssignee(selected!, userId)"
      />
      <template #actions>
        <EButton variant="secondary" @click="detailOpen = false">{{ t('common.close') }}</EButton>
      </template>
    </EDialog>

    <EDialog v-model="createOpen" :title="t('grossanlass.aufgaben.create.title')" max-width="520" :retain-focus="false">
      <div class="ga-create">
        <ESelect v-model="draft.groupId" :items="projects" :label="t('grossanlass.aufgaben.create.project')" hide-details />
        <ETextField v-model="draft.title" :label="t('grossanlass.aufgaben.create.name')" hide-details />
        <ETextField v-model="draft.startsAt" type="datetime-local" :label="t('grossanlass.aufgaben.create.start')" hide-details />
        <ETextField v-model="draft.minutes" type="number" min="0" :label="t('grossanlass.aufgaben.create.minutes')" hide-details />
        <ESelect
          v-model="draft.assigneeId"
          :items="draftAssignees"
          :label="t('grossanlass.aufgaben.detail.assign')"
          clearable
          hide-details
        />
        <p v-if="createError" class="ga-create__error">{{ createError }}</p>
      </div>
      <template #actions>
        <EButton variant="secondary" @click="createOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :disabled="!draft.groupId || !draft.title.trim()" @click="submitCreate">
          {{ t('common.save') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog, ESearchField, ESelect, ESwitch, ETextField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassAufgabeDetail from './GrossanlassAufgabeDetail.vue'
import {
  bucketOf,
  emptyAufgabeFilters,
  matchesFilters,
  statusCounts,
  taskProgress,
  type GaAufgabe,
  type GaAufgabeBucket,
  type GaAufgabeStatusFilter,
} from './gaAufgabenMock'
import { useGaAufgaben, assigneesFor } from './useGaAufgaben'
import { KIND_ICON, STATUS_COLOR, dayLabel, statusKey, useGaNow, whenLabel } from './gaAufgabenUi'

const { t, locale } = useI18n()
const route = useRoute()
const { tasks, projects, groups: orgGroups, loading, load, actionError, addStep, setStepDone, moveStep, removeStep, block, unblock, reportDone, accept, giveBack, addDependency, changeDependencyMode, removeDependency, releaseDependency, setStatus, setAssignee, create } = useGaAufgaben(
  () => String(route.params.departmentId || ''),
  () => showArchived.value,
)
onMounted(load)
const now = useGaNow()

/** Archivierte Bauaufträge sind standardmässig ausgeblendet. */
const showArchived = ref(false)
watch(showArchived, () => load())
const filters = reactive(emptyAufgabeFilters())
const selectedId = ref<string | null>(null)
const detailOpen = ref(false)
const selected = computed(() => tasks.value.find((task) => task.id === selectedId.value) ?? null)
/** Mögliche Vorgänger: alle anderen sichtbaren Aufgaben (auch aus anderen Ressorts), noch nicht verknüpft. */
const dependencyCandidates = computed(() => {
  const current = selected.value
  if (!current) return []
  const linked = new Set((current.dependencies ?? []).filter((dep) => dep.kind === 'task' && dep.predecessor_id).map((dep) => dep.predecessor_id))
  return tasks.value
    .filter((task) => task.id !== current.id && !linked.has(task.id))
    .map((task) => ({ value: task.id, title: `${task.origin.join(' › ')} · ${task.title}` }))
    .sort((a, b) => a.title.localeCompare(b.title, 'de'))
})

/** Kennzahlen gelten für die gewählten Filter ausser Status. */
const scoped = computed(() => tasks.value.filter((task) => matchesFilters(task, filters, { ignoreStatus: true, now: now.value })))
const counts = computed(() => statusCounts(scoped.value, now.value))
const kpis = computed(() => [
  { key: 'open' as const, count: counts.value.open },
  { key: 'progress' as const, count: counts.value.progress },
  { key: 'overdue' as const, count: counts.value.overdue },
  { key: 'review' as const, count: counts.value.review },
  { key: 'done' as const, count: counts.value.done },
])

const visible = computed(() =>
  tasks.value
    .filter((task) => matchesFilters(task, filters, { now: now.value }))
    .slice()
    .sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime()),
)

const BUCKET_ORDER: GaAufgabeBucket[] = ['overdue', 'review', 'today', 'tomorrow', 'later', 'done']
const groups = computed(() =>
  BUCKET_ORDER.map((bucket) => ({
    bucket,
    items: visible.value.filter((task) => bucketOf(task, now.value) === bucket),
  })).filter((group) => group.items.length > 0),
)

function uniqueSorted(values: string[]): string[] {
  return [...new Set(values.filter(Boolean))].sort((a, b) => a.localeCompare(b, 'de'))
}
const ressortItems = computed(() => uniqueSorted(tasks.value.map((task) => task.ressort)))
const bereichItems = computed(() =>
  uniqueSorted(
    tasks.value.filter((task) => !filters.ressort || task.ressort === filters.ressort).map((task) => task.bereich),
  ),
)
const personItems = computed(() => uniqueSorted(tasks.value.flatMap((task) => task.people)))
const periodItems = computed(() => [
  { title: t('grossanlass.aufgaben.period.all'), value: 'all' },
  { title: t('grossanlass.aufgaben.period.today'), value: 'today' },
  { title: t('grossanlass.aufgaben.period.week'), value: 'week' },
])
const statusItems = computed(() => [
  { title: t('grossanlass.aufgaben.status.all'), value: 'all' },
  { title: t('grossanlass.aufgaben.status.open'), value: 'open' },
  { title: t('grossanlass.aufgaben.status.progress'), value: 'progress' },
  { title: t('grossanlass.aufgaben.status.review'), value: 'review' },
  { title: t('grossanlass.aufgaben.status.overdue'), value: 'overdue' },
  { title: t('grossanlass.aufgaben.status.done'), value: 'done' },
])

function toggleStatus(key: GaAufgabeStatusFilter) {
  filters.status = filters.status === key ? 'all' : key
}

const assignees = computed(() => (selected.value?.groupId ? assigneesFor(selected.value.groupId, orgGroups.value) : []))

const createOpen = ref(false)
const createError = ref('')
const draft = reactive({ groupId: '', title: '', startsAt: '', minutes: '60', assigneeId: null as string | null })
const draftAssignees = computed(() => (draft.groupId ? assigneesFor(draft.groupId, orgGroups.value) : []))

function openCreate() {
  Object.assign(draft, { groupId: '', title: '', startsAt: '', minutes: '60', assigneeId: null })
  createError.value = ''
  createOpen.value = true
}

async function submitCreate() {
  createError.value = ''
  try {
    await create({
      group_id: draft.groupId,
      title: draft.title.trim(),
      starts_at: draft.startsAt || null,
      duration_minutes: draft.minutes === '' ? null : Number(draft.minutes),
      assignee_user_id: draft.assigneeId,
    })
    createOpen.value = false
  } catch {
    createError.value = t('grossanlass.aufgaben.create.error')
  }
}

function openTask(task: GaAufgabe) {
  selectedId.value = task.id
  detailOpen.value = true
}
</script>

<style scoped>
.ga-aufgaben {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 4px 0 24px;
}
.ga-aufgaben__intro {
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
}
.ga-aufgaben__kpis {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px;
}
.ga-kpi {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.ga-kpi strong {
  font-size: 1.6rem;
  line-height: 1.1;
}
.ga-kpi span {
  font-size: 0.8rem;
  color: #64748b;
}
.ga-kpi--active {
  border-color: #059669;
  box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.18);
}
.ga-kpi--overdue strong {
  color: #b91c1c;
}
.ga-aufgaben__filters {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ga-aufgaben__kinds {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.ga-aufgaben__selects {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 10px;
}
.ga-aufgaben__group-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 10px;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.ga-aufgaben__group-title span {
  padding: 1px 8px;
  border-radius: 999px;
  background: #e2e8f0;
  font-size: 0.72rem;
}
.ga-aufgaben__list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 12px;
}
.ga-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-left-width: 4px;
  border-radius: 12px;
  background: #fff;
}
.ga-card--open {
  border-left-color: #f59e0b;
}
.ga-card--progress {
  border-left-color: #059669;
}
.ga-card--overdue {
  border-left-color: #dc2626;
}
.ga-card--done {
  border-left-color: #16a34a;
  opacity: 0.85;
}
.ga-card__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.ga-card__kind {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.ga-card__origin {
  margin: 0;
  font-size: 0.8rem;
  color: #64748b;
}
.ga-card__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}
.ga-card__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 0.84rem;
  color: #334155;
}
.ga-card__unassigned {
  color: #b45309;
}
.ga-card__progress {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.8rem;
}
.ga-card__progress :deep(.v-progress-linear) {
  flex: 1 1 auto;
}
.ga-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}
@media (max-width: 720px) {
  .ga-aufgaben__kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .ga-aufgaben__list {
    grid-template-columns: 1fr;
  }
}
.ga-create {
  display: grid;
  gap: 14px;
}
.ga-create__error {
  margin: 0;
  color: #b91c1c;
}
</style>
