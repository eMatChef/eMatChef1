<template>
  <PageShell class="helferpool" :title="t('grossanlass.helferpool.title')" :subtitle="t('grossanlass.helferpool.subtitle')">
    <template #filters>
      <v-tabs v-model="tab" class="materials-view-tabs" color="primary" show-arrows>
        <v-tab v-if="canManage" value="helpers"><v-icon icon="mdi-account-group-outline" start size="18" />{{ t('grossanlass.helferpool.tab.helpers') }}</v-tab>
        <v-tab v-if="canManage" value="timeline"><v-icon icon="mdi-chart-gantt" start size="18" />{{ t('grossanlass.helferpool.tab.timeline') }}</v-tab>
        <v-tab v-if="canManage" value="skills"><v-icon icon="mdi-hammer-wrench" start size="18" />{{ t('grossanlass.helferpool.tab.skills') }}</v-tab>
        <v-tab value="needs"><v-icon icon="mdi-clipboard-account-outline" start size="18" />{{ t('grossanlass.helferpool.tab.needs') }}</v-tab>
      </v-tabs>
    </template>

    <div class="hp">
      <p class="hp__intro">{{ canManage ? t('grossanlass.helferpool.intro') : t('grossanlass.helferpool.introReadonly') }}</p>
      <p v-if="loadError" class="hp__error" role="alert">{{ loadError }}</p>

      <section class="hp__kpis">
        <div class="kpi"><strong>{{ overview?.totals.required_total ?? 0 }}</strong><span>{{ t('grossanlass.helferpool.kpi.required') }}</span></div>
        <div class="kpi" :class="{ 'kpi--bad': (overview?.totals.open_needs ?? 0) > 0 }"><strong>{{ overview?.totals.open_needs ?? 0 }}</strong><span>{{ t('grossanlass.helferpool.kpi.open') }}</span></div>
        <div class="kpi"><strong>{{ percent(overview?.totals.fill_ratio ?? null) }}</strong><span>{{ t('grossanlass.helferpool.kpi.fill') }}</span></div>
        <div v-if="canManage" class="kpi"><strong>{{ activeHelperCount }}</strong><span>{{ t('grossanlass.helferpool.kpi.helpers') }}</span></div>
        <button v-if="canManage" type="button" class="kpi kpi--link" :class="{ 'kpi--bad': realConflicts.length > 0 }" @click="conflictsOpen = !conflictsOpen">
          <strong>{{ realConflicts.length }}</strong><span>{{ t('grossanlass.helferpool.kpi.conflicts') }}</span>
        </button>
      </section>

      <section v-if="canManage && conflictsOpen" class="conflicts" role="region" :aria-label="t('grossanlass.helferpool.kpi.conflicts')">
        <p v-if="!conflicts.length" class="muted">{{ t('grossanlass.helferpool.conflictItem.none') }}</p>
        <ul>
          <li v-for="(item, index) in conflicts" :key="index" :class="{ 'is-excused': item.excused }">
            <v-icon :icon="item.excused ? 'mdi-check-circle-outline' : 'mdi-alert-outline'" size="16" />
            {{ conflictText(item) }}
          </li>
        </ul>
      </section>

      <!-- Helfer -->
      <template v-if="tab === 'helpers' && canManage">
        <section class="hp__filters">
          <ESearchField v-model="search" :label="t('grossanlass.helferpool.filter.search')" />
          <ESelect v-model="helperStatus" :items="statusItems" :label="t('grossanlass.helferpool.filter.status')" hide-details />
          <EButton variant="primary" @click="openCreate"><v-icon icon="mdi-account-plus-outline" start size="18" />{{ t('grossanlass.helferpool.add') }}</EButton>
        </section>
        <EEmptyState v-if="!visibleHelpers.length" variant="generic" icon="mdi-account-search-outline" :title="t('grossanlass.helferpool.emptyTitle')" :description="t('grossanlass.helferpool.emptyText')" />
        <div class="cards">
          <article v-for="helper in visibleHelpers" :key="helper.id" class="helper" :class="{ 'helper--archived': helper.status === 'archived' }">
            <header class="helper__head">
              <button type="button" class="helper__name" @click="openDrawer(helper.id)"><strong>{{ fullName(helper) }}</strong></button>
              <v-chip v-if="helper.status === 'archived'" size="x-small" variant="flat" color="grey">{{ t('grossanlass.helferpool.helperStatus.archived') }}</v-chip>
            </header>
            <p v-if="helper.email" class="helper__line"><v-icon icon="mdi-email-outline" size="14" /> {{ helper.email }}</p>
            <p v-if="helper.phone" class="helper__line"><v-icon icon="mdi-phone-outline" size="14" /> {{ helper.phone }}</p>
            <p class="helper__line">
              <v-icon icon="mdi-clipboard-check-outline" size="14" />
              {{ t('grossanlass.helferpool.assignmentCount', { n: scheduleOf(helper.id)?.assignments.length ?? 0 }) }}
            </p>
            <v-chip v-if="helper.status === 'active' && !(scheduleOf(helper.id)?.availability.length)" size="x-small" variant="tonal" color="warning">{{ t('grossanlass.helferpool.noAvailabilityInRange') }}</v-chip>
            <EButton size="small" variant="secondary" @click="openDrawer(helper.id)">{{ t('grossanlass.helferpool.details') }}</EButton>
          </article>
        </div>
      </template>

      <!-- Zeitplan -->
      <GrossanlassStaffingTimeline
        v-else-if="tab === 'timeline' && canManage"
        v-model:start="scheduleStart"
        v-model:days="scheduleDays"
        :helpers="schedule"
        @open="openDrawer"
      />

      <!-- Fähigkeiten: Funktionswünsche des Personalbedarfs -->
      <section v-else-if="tab === 'skills' && canManage" class="skills">
        <p class="muted">{{ t('grossanlass.helferpool.skillsNote') }}</p>
        <EEmptyState v-if="!functions.length" variant="generic" icon="mdi-hammer-wrench" :title="t('grossanlass.helferpool.needs.emptyTitle')" :description="t('grossanlass.helferpool.needs.emptyText')" />
        <table v-else class="matrix">
          <thead>
            <tr>
              <th>{{ t('grossanlass.helferpool.skillsFunction') }}</th>
              <th>{{ t('grossanlass.helferpool.skillsRequired') }}</th>
              <th>{{ t('grossanlass.helferpool.skillsAssigned') }}</th>
              <th>{{ t('grossanlass.helferpool.kpi.fill') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in functions" :key="row.label">
              <td>{{ row.label || t('grossanlass.helferpool.skillsNoFunction') }}</td>
              <td>{{ row.required }}</td>
              <td>{{ row.assigned }}</td>
              <td>{{ percent(row.fill) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Personalbedarf -->
      <GrossanlassStaffingNeedsPanel
        v-else
        :needs="overview?.needs ?? []"
        :groups="overview?.groups ?? []"
        :group-names="groupNames"
        :can-manage="canManage"
        :details="details"
        @assign="openAssign"
        @edit="openEditAssignment"
        @cancel="cancelAssignment"
        @load-detail="loadDetail"
      />
    </div>

    <GrossanlassHelferDrawer v-model="drawerOpen" :department-id="departmentId" :helper-id="drawerHelperId" :schedule="scheduleOf(drawerHelperId)" @edit="openEdit" @changed="reload" />
    <GrossanlassHelferFormDialog v-model="formOpen" :department-id="departmentId" :helper="formHelper" @saved="onHelperSaved" />
    <GrossanlassStaffingAssignDialog
      v-model="assignOpen"
      :department-id="departmentId"
      :need="assignNeed"
      :helpers="helpers"
      :assignment="assignExisting"
      @done="onAssigned"
    />
  </PageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { getGrossanlassGroups } from '@/api/grossanlassGroups'
import {
  cancelGaAssignment,
  getGaStaffingConflicts,
  getGaStaffingNeedDetail,
  getGaStaffingOverview,
  getGaStaffingSchedule,
  listGaHelpers,
  type GaHelper,
  type GaNeedAssignment,
  type GaScheduleHelper,
  type GaStaffingAssignResult,
  type GaStaffingConflictItem,
  type GaStaffingNeedDetail,
  type GaStaffingNeedRow,
  type GaStaffingOverview,
} from '@/api/grossanlassStaffing'
import { EButton, ESearchField, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import PageShell from '@/components/layout/PageShell.vue'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { apiErrorMessage } from '@/utils/apiErrorMessage'
import { gaCanManageStaffing } from '@/utils/grossanlassAccess'
import GrossanlassHelferDrawer from './GrossanlassHelferDrawer.vue'
import GrossanlassHelferFormDialog from './GrossanlassHelferFormDialog.vue'
import GrossanlassStaffingAssignDialog from './GrossanlassStaffingAssignDialog.vue'
import GrossanlassStaffingNeedsPanel from './GrossanlassStaffingNeedsPanel.vue'
import GrossanlassStaffingTimeline from './GrossanlassStaffingTimeline.vue'
import { defaultScheduleStart, fullName, functionRows, percent, scheduleRange, windowLabel } from './gaHelferUi'
import '@/styles/views/materials-view-tabs.css'

const { t, locale } = useI18n()
const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()

const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')
const loadError = ref('')
const overview = ref<GaStaffingOverview | null>(null)
/** Verwaltung und Zuteilung: GA-HV/GA-SUBHV sowie GA-MW/GA-OK laut GA-Einstellung; alle anderen sehen nur den Personalbedarf (ohne Namen). */
const canManage = computed(() => overview.value?.can_manage ?? gaCanManageStaffing(authStore.currentDepartmentRole))

const tab = ref<'helpers' | 'timeline' | 'skills' | 'needs'>(canManage.value ? 'helpers' : 'needs')
const helpers = ref<GaHelper[]>([])
const schedule = ref<GaScheduleHelper[]>([])
const conflicts = ref<GaStaffingConflictItem[]>([])
const details = ref<Record<string, GaStaffingNeedDetail>>({})
const groupNames = ref<Record<string, string>>({})

const search = ref('')
const helperStatus = ref<'active' | 'archived'>('active')
const conflictsOpen = ref(false)
const scheduleStart = ref('')
const scheduleDays = ref(7)

const drawerOpen = ref(false)
const drawerHelperId = ref<string | null>(null)
const formOpen = ref(false)
const formHelper = ref<GaHelper | null>(null)
const assignOpen = ref(false)
const assignNeed = ref<GaStaffingNeedRow | null>(null)
const assignExisting = ref<{ id: string; helper_id: string; starts_at: string; ends_at: string; version: number } | null>(null)

const statusItems = computed(() => [
  { value: 'active', title: t('grossanlass.helferpool.helperStatus.active') },
  { value: 'archived', title: t('grossanlass.helferpool.helperStatus.archived') },
])
const activeHelperCount = computed(() => helpers.value.filter((h) => h.status === 'active').length)
const realConflicts = computed(() => conflicts.value.filter((c) => !c.excused))
const functions = computed(() => functionRows(overview.value?.needs ?? []))
const visibleHelpers = computed(() => {
  const q = search.value.trim().toLowerCase()
  return helpers.value.filter((h) => !q || `${fullName(h)} ${h.email ?? ''}`.toLowerCase().includes(q))
})

function scheduleOf(helperId: string | null): GaScheduleHelper | null {
  return helperId ? schedule.value.find((s) => s.helper_id === helperId) ?? null : null
}

function helperName(id: string | undefined): string {
  const helper = helpers.value.find((h) => h.id === id) ?? null
  return helper ? fullName(helper) : (schedule.value.find((s) => s.helper_id === id)?.name ?? '?')
}

function conflictText(item: GaStaffingConflictItem): string {
  const when = item.starts_at && item.ends_at ? windowLabel(item.starts_at, item.ends_at, locale.value) : ''
  return t(`grossanlass.helferpool.conflictItem.${item.kind}`, { name: helperName(item.helper_id), when, required: item.required ?? 0, peak: item.peak_assigned ?? 0 })
}

async function loadOverview() {
  overview.value = await getGaStaffingOverview(departmentId.value)
}

async function loadGroups() {
  const groups = await getGrossanlassGroups(departmentId.value)
  groupNames.value = Object.fromEntries(groups.map((g) => [g.id, g.name]))
}

async function loadHelpers() {
  helpers.value = await listGaHelpers(departmentId.value, { status: helperStatus.value })
}

async function loadSchedule() {
  const range = scheduleRange(scheduleStart.value, scheduleDays.value)
  schedule.value = await getGaStaffingSchedule(departmentId.value, range.from, range.to)
}

async function reload() {
  loadError.value = ''
  details.value = {}
  try {
    await Promise.all([
      loadOverview(),
      ...(canManage.value ? [loadHelpers(), loadSchedule(), getGaStaffingConflicts(departmentId.value).then((c) => { conflicts.value = c })] : []),
    ])
  } catch (err) {
    loadError.value = apiErrorMessage(err, t('grossanlass.helferpool.error.load'))
  }
}

onMounted(async () => {
  try {
    await Promise.all([loadOverview(), loadGroups()])
    scheduleStart.value = defaultScheduleStart(overview.value?.needs ?? [])
    if (!canManage.value) tab.value = 'needs'
  } catch (err) {
    loadError.value = apiErrorMessage(err, t('grossanlass.helferpool.error.load'))
  }
  if (canManage.value) await reload()
})

watch([scheduleStart, scheduleDays], async () => {
  if (!canManage.value || !scheduleStart.value) return
  try {
    await loadSchedule()
  } catch (err) {
    loadError.value = apiErrorMessage(err, t('grossanlass.helferpool.error.load'))
  }
})
watch(helperStatus, async () => {
  try {
    await loadHelpers()
  } catch (err) {
    loadError.value = apiErrorMessage(err, t('grossanlass.helferpool.error.load'))
  }
})

function openDrawer(id: string) {
  drawerHelperId.value = id
  drawerOpen.value = true
}
function openCreate() {
  formHelper.value = null
  formOpen.value = true
}
function openEdit(helper: GaHelper) {
  formHelper.value = helper
  formOpen.value = true
}
async function onHelperSaved() {
  toast.success(t('grossanlass.helferpool.saved'))
  await reload()
}

function openAssign(need: GaStaffingNeedRow) {
  assignNeed.value = need
  assignExisting.value = null
  assignOpen.value = true
}
function openEditAssignment(need: GaStaffingNeedRow, entry: GaNeedAssignment) {
  assignNeed.value = need
  assignExisting.value = { id: entry.id, helper_id: entry.helper_id, starts_at: entry.starts_at, ends_at: entry.ends_at, version: entry.version }
  assignOpen.value = true
}
async function onAssigned(result: GaStaffingAssignResult) {
  toast.success(t('grossanlass.helferpool.assigned'))
  for (const warning of result.warnings) toast.warning(t(`grossanlass.helferpool.warning.${warning.kind}`))
  const needId = result.assignment.need_id
  await reload()
  await loadDetail(needId)
}
async function loadDetail(needId: string) {
  try {
    details.value = { ...details.value, [needId]: await getGaStaffingNeedDetail(departmentId.value, needId) }
  } catch (err) {
    toast.error(apiErrorMessage(err, t('grossanlass.helferpool.error.load')))
  }
}
async function cancelAssignment(assignmentId: string) {
  const needId = Object.values(details.value).find((d) => d.assignments.some((a) => a.id === assignmentId))?.id
  try {
    await cancelGaAssignment(departmentId.value, assignmentId)
    toast.success(t('grossanlass.helferpool.unassigned'))
    await reload()
    if (needId) await loadDetail(needId)
  } catch (err) {
    toast.error(apiErrorMessage(err, t('grossanlass.helferpool.error.assign')))
  }
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
.hp__error {
  margin: 0;
  color: #b91c1c;
}
.hp__kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
}
.kpi strong {
  font-size: 1.6rem;
  line-height: 1.1;
}
.kpi span {
  font-size: 0.8rem;
  color: #64748b;
}
.kpi--link {
  cursor: pointer;
}
.kpi--bad strong {
  color: #b91c1c;
}
.conflicts {
  padding: 12px 14px;
  border: 1px solid #fecaca;
  border-radius: 12px;
  background: #fef2f2;
}
.conflicts ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 6px;
  font-size: 0.9rem;
  color: #7f1d1d;
}
.conflicts .is-excused {
  color: #64748b;
}
.hp__filters {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
}
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 12px;
}
.helper {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
}
.helper--archived {
  opacity: 0.7;
}
.helper__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  width: 100%;
}
.helper__name {
  border: 0;
  background: none;
  padding: 0;
  cursor: pointer;
  text-align: left;
}
.helper__line {
  margin: 0;
  color: #475569;
  font-size: 0.88rem;
}
.matrix {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}
.matrix th,
.matrix td {
  padding: 10px 14px;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.9rem;
}
.matrix th {
  background: #f8fafc;
  color: #64748b;
  font-weight: 600;
}
.muted {
  margin: 0;
  color: #94a3b8;
  font-size: 0.86rem;
}
@media (max-width: 700px) {
  .hp__filters {
    grid-template-columns: 1fr;
  }
}
</style>
