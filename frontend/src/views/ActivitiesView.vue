<template>
  <div class="activities-view">
    <ActivityCreateWizard
      v-model="showCreateActivityWizard"
      :department-id="departmentId"
      @created="onActivityCreateWizardCreated"
      @draft-saved="onActivityDraftSaved"
    />

    <div v-if="isPackJourneyRoute" class="dept-page activities-detail-root activities-pack-journey-root">
      <router-view />
    </div>
    <div
      v-else-if="activityRouteId"
      class="dept-page activities-detail-root"
      data-onboarding="activity-detail-root"
    >
      <ActivityDetailView :department-id="departmentId" :activity-id="activityRouteId" />
    </div>

    <!-- Übersicht -->
    <PageShell v-else class="activities-view activities-view--list" :class="listViewDisplayClasses">
      <template #title>{{ t('activities.title') }}</template>
      <template #subtitle>{{ t('activities.subtitle') }}</template>
      <template #actions>
        <EButton variant="primary" data-onboarding="activity-new" @click="openCreateActivityWizard">
          <v-icon icon="mdi-plus" start size="20" />
          {{ t('activities.create') }}
        </EButton>
      </template>

      <template #filters>
      <div v-if="!isLoading && smAndUp" class="stats-bar">
        <button
          type="button"
          class="stat-item stat-item-btn"
          :class="{ 'stat-item-active': activeTab === 'all' && !statusFilter && !activeTypeFilter }"
          @click="applyStatFilter('')"
        >
          <span class="stat-value">{{ activities.length }}</span>
          <span class="stat-label">{{ t('activities.stats.total') }}</span>
        </button>
        <button
          type="button"
          class="stat-item stat-item-btn"
          :class="{ 'stat-item-active': activeTab === 'all' && statusFilter === 'draft' }"
          @click="applyStatFilter('draft')"
        >
          <span class="stat-value stat-draft">{{ activities.filter((a) => a.status === 'draft').length }}</span>
          <span class="stat-label">{{ t('activities.stats.drafts') }}</span>
        </button>
        <button
          type="button"
          class="stat-item stat-item-btn"
          data-onboarding="activities-submitted-filter"
          :class="{ 'stat-item-active': activeTab === 'all' && statusFilter === 'submitted' }"
          @click="applyStatFilter('submitted')"
        >
          <span class="stat-value stat-submitted">{{ activities.filter((a) => a.status === 'submitted').length }}</span>
          <span class="stat-label">{{ t('activities.stats.submitted') }}</span>
        </button>
        <button
          type="button"
          class="stat-item stat-item-btn"
          data-onboarding="activities-packing-filter"
          :class="{ 'stat-item-active': activeTab === 'all' && statusFilter === 'in_progress' }"
          @click="applyStatFilter('in_progress')"
        >
          <span class="stat-value stat-approved">{{ activities.filter((a) => ['approved', 'packing', 'packed'].includes(a.status)).length }}</span>
          <span class="stat-label">{{ t('activities.stats.inProgress') }}</span>
        </button>
        <button
          type="button"
          class="stat-item stat-item-btn"
          :class="{ 'stat-item-active': activeTab === 'all' && statusFilter === 'at_event' }"
          @click="applyStatFilter('at_event')"
        >
          <span class="stat-value stat-issued">{{ activities.filter((a) => ['at_event', 'returned'].includes(a.status)).length }}</span>
          <span class="stat-label">{{ t('activities.stats.issued') }}</span>
        </button>
        <button
          type="button"
          class="stat-item stat-item-btn"
          :class="{ 'stat-item-active': activeTab === 'all' && statusFilter === 'completed' }"
          @click="applyStatFilter('completed')"
        >
          <span class="stat-value stat-completed">{{ activities.filter((a) => a.status === 'completed').length }}</span>
          <span class="stat-label">{{ t('activities.stats.completed') }}</span>
        </button>
      </div>

      <div
        class="activities-filter-stack"
        :class="{
          'activities-filter-stack--expanded': mobileFiltersExpanded,
          'activities-filter-stack--pinned': mobileFiltersPinned,
        }"
        @focusin="onMobileFilterToolsFocusIn"
        @focusout="onMobileFilterToolsFocusOut"
      >
        <div class="filter-bar" data-onboarding="activities-list-filters">
          <div class="filter-tabs">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              class="filter-tab"
              :class="{ active: activeTab === tab.key }"
              type="button"
              @click="onListTabChange(tab.key)"
            >
              {{ tab.label }}
              <span v-if="tab.count !== undefined" class="tab-count">{{ tab.count }}</span>
            </button>
          </div>
          <div
            class="filter-actions filter-actions-all activities-filter-stack__search"
            :class="{ 'filter-actions-all--reserved': activeTab !== 'all' }"
            :aria-hidden="activeTab !== 'all'"
          >
            <div class="search-box">
              <ESearchField
                v-model="searchQuery"
                :label="t('activities.searchListPlaceholder')"
                :disabled="activeTab !== 'all'"
              />
            </div>
          </div>
        </div>

        <div v-if="activeTab === 'all'" class="activities-list-filters">
          <ESelect
            v-model="activeTypeFilter"
            :items="typeSelectItems"
            :label="t('activities.table.type')"
            hide-details
            class="activities-list-filters__select"
          />
          <ESelect
            v-model="statusFilter"
            :items="statusSelectItems"
            :label="t('common.status')"
            hide-details
            class="activities-list-filters__select"
          />
        </div>
      </div>
      </template>

      <ELoadingState
        v-if="isLoading"
        variant="table"
        :rows="8"
        :message="t('activities.loadingList')"
      />

      <EEmptyState
        v-else-if="filteredActivities.length === 0"
        :title="hasActiveListFilters ? t('activities.empty.noMatch') : t('activities.empty.noneYet')"
      />

      <div v-else class="activity-list-panel" :class="listPanelDisplayClasses">
        <EResponsiveDataList>
          <template #table>
            <ActivityListDataTable
              :items="filteredActivities"
              :department-id="departmentId"
              :selected-id="selectedActivityId"
              :sort-field="sortField"
              :sort-dir="sortDir"
              :type-label="getTypeLabel"
              :status-label="getStatusLabel"
              :period-label="(a) => formatPeriodCompact(a.usageStart, a.usageEnd)"
              :group-path-lines="getActivityGroupPathLines"
              :share-hint="getActivityShareHint"
              :share-status="getActivityShareStatus"
              :open-on-row-click="statusFilter === 'submitted' || isPackOrHandoffTourActive"
              :mark-first-submitted-for-tour="isApproveTourActive"
              :mark-first-packing-for-tour="isPackOrHandoffTourActive"
              @open="openActivityDetail"
              @select="selectedActivityId = $event"
              @sort="onTableSort"
            />
          </template>
          <template #mobile>
            <ActivityListMobile
              :items="filteredActivities"
              :type-label="getTypeLabel"
              :status-label="getStatusLabel"
              :period-label="(a) => formatPeriodCompact(a.usageStart, a.usageEnd)"
              :group-path-lines="getActivityGroupPathLines"
              :mark-first-submitted-for-tour="isApproveTourActive"
              :mark-first-packing-for-tour="isPackOrHandoffTourActive"
              @open="openActivityDetail"
            />
          </template>
        </EResponsiveDataList>
        <div class="table-footer">
          <span>{{ t('activities.table.footer', { shown: filteredActivities.length, total: activities.length }) }}</span>
        </div>
      </div>
    </PageShell>
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'ActivitiesView' })
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDisplayHostClasses } from '@/composables/useDisplayHostClasses'
import { useSmAndUp } from '@/composables/useSmAndUp'
import apiClient from '@/api/apiClient'
import { getActivity } from '@/api/activities'
import { getGroups, type Group } from '@/api/groups'
import { buildActivityGroupPathLines, type GroupPathLine } from '@/utils/groupHierarchy'
import PageShell from '@/components/layout/PageShell.vue'
import EResponsiveDataList from '@/components/layout/EResponsiveDataList.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EButton from '@/components/form/base/EButton.vue'
import ESearchField from '@/components/form/base/ESearchField.vue'
import ESelect from '@/components/form/base/ESelect.vue'
import {
  ActivityCreateWizard,
  ActivityDetailView,
  ActivityListDataTable,
  ActivityListMobile,
  type ActivityListItem,
} from '@/components/activities'
import { formatPeriodCompact } from '@/utils/formatPeriod'
import { syncDocumentHead } from '@/composables/usePageHead'
import { usePageHeadStore } from '@/stores/pageHead'
import { useToast } from '@/composables/useToast'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { useDepartmentLiveRefresh } from '@/composables/useDepartmentLiveRefresh'
import { useListSearchQueryRoute } from '@/composables/useListSearchQueryRoute'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'
import {
  ONBOARDING_TOUR_QUERY,
  ONBOARDING_TOUR_STEP_QUERY,
  acceptPackTourTypeRank,
  isAcceptPackTourListCandidate,
  isHandoffTourListCandidate,
} from '@/config/onboardingTours'

const route = useRoute()
const router = useRouter()
const smAndUp = useSmAndUp()
const listPanelDisplayClasses = useDisplayHostClasses('activity-list-panel')
const listViewDisplayClasses = useDisplayHostClasses('activities-view--list')
const { t, te, locale } = useI18n()
const toast = useToast()
const headerNotificationsStore = useHeaderNotificationsStore()
const detailTabsStore = useDetailTabsStore()
const pageHeadStore = usePageHeadStore()

const isApproveTourActive = computed(
  () => route.query[ONBOARDING_TOUR_QUERY] === 'activity-approve',
)
const isPackTourActive = computed(
  () => route.query[ONBOARDING_TOUR_QUERY] === 'issue-return',
)
const isHandoffTourActive = computed(
  () => route.query[ONBOARDING_TOUR_QUERY] === 'issue-handoff',
)
const isPackOrHandoffTourActive = computed(
  () => isPackTourActive.value || isHandoffTourActive.value,
)
const departmentId = computed(() => route.params.departmentId as string)
const isPackJourneyRoute = computed(() => route.name === 'ActivityPackJourney')
const activityRouteId = computed(() => {
  if (isPackJourneyRoute.value) return undefined
  return (route.params.activityId as string | undefined) || undefined
})

const activities = ref<ActivityListItem[]>([])
const departmentGroups = ref<Group[]>([])
const isLoading = ref(false)
type ListTab = 'open' | 'upcoming' | 'all'
type StatusFilter = '' | 'draft' | 'submitted' | 'in_progress' | 'at_event' | 'completed' | 'cancelled'

const activeTab = ref<ListTab>('open')
const activeTypeFilter = ref('')
const statusFilter = ref<StatusFilter>('')
const searchQuery = ref('')
const sortField = ref('date')
const sortDir = ref<'asc' | 'desc'>('asc')
const selectedActivityId = ref<string | null>(null)
const showCreateActivityWizard = ref(false)
/** Verhindert Dashboard-Rücksprung nach erfolgreicher Anlage (Navigation zur Detailseite). */
const activityJustCreated = ref(false)

let lastDetailOpenId = ''
let lastDetailOpenAt = 0

const ACTIVITY_FILTER_TYPES = ['activity', 'camp', 'event', 'external'] as const

const typeFilterOptions = computed(() =>
  ACTIVITY_FILTER_TYPES.map((type) => ({
    type,
    label: t(`activities.types.${type}`),
  })),
)

const typeSelectItems = computed(() => [
  { title: t('activities.filters.allTypes'), value: '' },
  ...typeFilterOptions.value.map((o) => ({ title: o.label, value: o.type })),
])

const statusSelectItems = computed(() => [
  { title: t('activities.filters.allStatuses'), value: '' },
  { value: 'draft' as const, title: t('activities.stats.drafts') },
  { value: 'submitted' as const, title: t('activities.stats.submitted') },
  { value: 'in_progress' as const, title: t('activities.stats.inProgress') },
  { value: 'at_event' as const, title: t('activities.stats.issued') },
  { value: 'completed' as const, title: t('activities.stats.completed') },
  { value: 'cancelled' as const, title: t('activities.tabs.cancelled') },
])

const hasActiveListFilters = computed(
  () =>
    !!searchQuery.value ||
    !!activeTypeFilter.value ||
    (activeTab.value === 'all' && !!statusFilter.value),
)

/** Smartphone: Filterzeile bleibt gross, solange Werte gesetzt sind */
const mobileFiltersPinned = computed(() => hasActiveListFilters.value)

const mobileFiltersExpanded = ref(false)

function isMobileFilterOverlayActive(): boolean {
  return !!document.querySelector('.v-overlay--active')
}

function onMobileFilterToolsFocusIn() {
  if (smAndUp.value) return
  mobileFiltersExpanded.value = true
}

function onMobileFilterToolsFocusOut(event: FocusEvent) {
  if (smAndUp.value) return
  const tools = event.currentTarget as HTMLElement | null
  window.setTimeout(() => {
    if (mobileFiltersPinned.value) return
    const active = document.activeElement
    if (tools?.contains(active)) return
    if (isMobileFilterOverlayActive()) return
    mobileFiltersExpanded.value = false
  }, 120)
}

function nameSortLocale(): string {
  const raw = String(locale.value)
  if (raw.startsWith('de')) return 'de'
  if (raw.startsWith('fr')) return 'fr'
  if (raw.startsWith('it')) return 'it'
  return 'en'
}

function mapActivityListItem(a: Record<string, unknown>): ActivityListItem {
  const no = a.no as number | string | undefined
  return {
    id: String(a.id),
    no: no != null ? `#${String(no).padStart(3, '0')}` : undefined,
    name: String(a.name ?? ''),
    departmentId: a.department_id as string | undefined,
    departmentName: a.department_name as string | undefined,
    type: a.type as ActivityListItem['type'],
    status: a.status as ActivityListItem['status'],
    invitedDepartments: Array.isArray(a.invited_departments)
      ? (a.invited_departments as ActivityListItem['invitedDepartments'])
      : [],
    groupId: (a.group_id as string | null | undefined) ?? null,
    groupName: a.group_name as string | undefined,
    usageStart: a.usage_start as string | undefined,
    usageEnd: a.usage_end as string | undefined,
    itemCount: (a.item_count as number | undefined) ?? 0,
    totalPrice: a.total_price as number | undefined,
    wantsJsMaterial: a.wants_js_material === true,
    jsListPhase:
      a.js_list_phase === 'draft' || a.js_list_phase === 'coach' || a.js_list_phase === 'return'
        ? a.js_list_phase
        : null,
    onboardingSandbox: a.onboarding_sandbox === true,
    createdAt: String(a.created_at ?? ''),
    updatedAt: String(a.updated_at ?? ''),
  }
}

async function loadDepartmentGroups() {
  if (!departmentId.value) {
    departmentGroups.value = []
    return
  }
  try {
    departmentGroups.value = await getGroups(departmentId.value)
  } catch {
    departmentGroups.value = []
  }
}

function upsertActivityInList(source: Record<string, unknown>) {
  const mapped = mapActivityListItem(source)
  const idx = activities.value.findIndex((a) => a.id === mapped.id)
  if (idx >= 0) {
    const next = [...activities.value]
    next[idx] = mapped
    activities.value = next
  } else {
    activities.value = [mapped, ...activities.value]
  }
}

async function loadActivities(opts?: { silent?: boolean }) {
  if (!opts?.silent) isLoading.value = true
  try {
    const [, response] = await Promise.all([
      loadDepartmentGroups(),
      apiClient.get('/api/activities', {
        params: { department_id: departmentId.value },
      }),
    ])
    activities.value = (response.data || []).map((a: Record<string, unknown>) => mapActivityListItem(a))
  } catch (err: unknown) {
    if (!opts?.silent) {
      const e = err as { code?: string; response?: { data?: { error?: string } }; message?: string }
      const msg =
        e?.code === 'ECONNABORTED'
          ? t('activities.errors.timeout')
          : e?.response?.data?.error || e?.message || t('activities.errors.unknown')
      toast.error(t('activities.errors.loadFailed', { msg }))
    }
  } finally {
    isLoading.value = false
  }
}

/** Alle User im Tab: neue/geänderte Aktivitäten ohne F5 (keep-alive inkl. Detail). */
useDepartmentLiveRefresh({
  departmentId,
  reload: loadActivities,
  isBusy: () =>
    showCreateActivityWizard.value || (isLoading.value && activities.value.length === 0),
})

/** Tour start/stop: Liste neu laden (Sandbox-Include via API-Interceptor). */
watch(
  () => route.query[ONBOARDING_TOUR_QUERY],
  () => {
    if (departmentId.value) void loadActivities({ silent: true })
  },
)

function isOpenActivity(a: ActivityListItem): boolean {
  return a.status !== 'completed' && a.status !== 'cancelled'
}

function isUpcomingActivity(a: ActivityListItem): boolean {
  if (!isOpenActivity(a)) return false
  if (!a.usageEnd) return true
  const endDate = new Date(a.usageEnd)
  const todayStart = new Date()
  todayStart.setHours(0, 0, 0, 0)
  return endDate >= todayStart
}

function matchesStatusFilter(a: ActivityListItem, filter: StatusFilter): boolean {
  if (!filter) return true
  if (filter === 'draft') return a.status === 'draft'
  if (filter === 'submitted') return a.status === 'submitted'
  if (filter === 'in_progress') return ['approved', 'packing', 'packed'].includes(a.status)
  if (filter === 'at_event') return ['at_event', 'returned'].includes(a.status)
  if (filter === 'completed') return a.status === 'completed'
  if (filter === 'cancelled') return a.status === 'cancelled'
  return true
}

const tabs = computed(() => [
  { key: 'open' as const, label: t('activities.tabs.open'), count: activities.value.filter(isOpenActivity).length },
  { key: 'upcoming' as const, label: t('activities.tabs.upcoming'), count: activities.value.filter(isUpcomingActivity).length },
  { key: 'all' as const, label: t('activities.tabs.all'), count: activities.value.length },
])

const { clearSearchFromRoute, stripQueryFromDetailRoute } = useListSearchQueryRoute({
  searchQuery,
  route,
  router,
  pathIncludes: '/activities',
  isListView: () => !activityRouteId.value,
  isSearchActive: () => activeTab.value === 'all',
})

function onListTabChange(tab: ListTab) {
  activeTab.value = tab
  if (tab !== 'all') {
    statusFilter.value = ''
    activeTypeFilter.value = ''
    clearSearchFromRoute()
    mobileFiltersExpanded.value = false
  }
}

function applyStatFilter(filter: StatusFilter) {
  activeTab.value = 'all'
  statusFilter.value = filter
}

watch(
  isApproveTourActive,
  (on) => {
    if (!on || activityRouteId.value) return
    activeTab.value = 'all'
    statusFilter.value = 'submitted'
    activeTypeFilter.value = ''
  },
  { immediate: true },
)

watch(
  isPackTourActive,
  (on) => {
    if (!on || activityRouteId.value) return
    activeTab.value = 'all'
    statusFilter.value = 'in_progress'
    activeTypeFilter.value = ''
  },
  { immediate: true },
)

watch(
  isHandoffTourActive,
  (on) => {
    if (!on || activityRouteId.value) return
    activeTab.value = 'all'
    statusFilter.value = 'in_progress'
    activeTypeFilter.value = ''
  },
  { immediate: true },
)

const filteredActivities = computed(() => {
  let result = activities.value

  if (activeTab.value === 'open') {
    result = result.filter(isOpenActivity)
  } else if (activeTab.value === 'upcoming') {
    result = result.filter(isUpcomingActivity)
  }

  // Pack-Tour: annehmbare Lager/Events + Aktivitäten (Statusfilter «In Bearbeitung» nur UI)
  if (isPackTourActive.value) {
    result = result.filter((a) => isAcceptPackTourListCandidate(a.type, a.status))
  } else if (isHandoffTourActive.value) {
    result = result.filter((a) => isHandoffTourListCandidate(a.type, a.status))
  } else if (activeTab.value === 'all' && statusFilter.value) {
    result = result.filter((a) => matchesStatusFilter(a, statusFilter.value))
  }

  if (activeTab.value === 'all' && activeTypeFilter.value) {
    result = result.filter((a) => a.type === activeTypeFilter.value)
  }

  // Freigabe-Tour: nur Lager/Event (Typ «Aktivität» braucht keine Leiter-Freigabe)
  if (isApproveTourActive.value) {
    result = result.filter((a) => a.type === 'camp' || a.type === 'event')
  }

  if (activeTab.value === 'all' && searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter((a) => activityMatchesSearch(a, q))
  }

  result = [...result].sort((a, b) => {
    if (isPackOrHandoffTourActive.value || isApproveTourActive.value) {
      const sandboxCmp = Number(!!b.onboardingSandbox) - Number(!!a.onboardingSandbox)
      if (sandboxCmp !== 0) return sandboxCmp
    }
    if (isPackTourActive.value || isHandoffTourActive.value) {
      const typeCmp = acceptPackTourTypeRank(a.type) - acceptPackTourTypeRank(b.type)
      if (typeCmp !== 0) return typeCmp
    }
    let cmp = 0
    if (activeTab.value === 'all' && statusFilter.value === 'completed') {
      const da = new Date(a.usageEnd || a.usageStart || 0).getTime()
      const db = new Date(b.usageEnd || b.usageStart || 0).getTime()
      return db - da
    }
    if (sortField.value === 'name') {
      cmp = a.name.localeCompare(b.name, nameSortLocale())
    } else if (sortField.value === 'date') {
      const da = a.usageStart ? new Date(a.usageStart).getTime() : 0
      const db = b.usageStart ? new Date(b.usageStart).getTime() : 0
      cmp = da - db
    } else if (sortField.value === 'price') {
      cmp = (a.totalPrice || 0) - (b.totalPrice || 0)
    }
    return sortDir.value === 'desc' ? -cmp : cmp
  })

  return result.map((a) => ({
    ...a,
    period: a.usageStart || '',
    price: a.totalPrice ?? 0,
  }))
})

function getActivityGroupPathLines(activity: ActivityListItem): GroupPathLine[] {
  if (activity.type === 'external') return []
  return buildActivityGroupPathLines(
    activity.groupId,
    activity.departmentName || '',
    departmentGroups.value,
    activity.groupName,
  )
}

function activityMatchesSearch(activity: ActivityListItem, q: string): boolean {
  if (activity.name.toLowerCase().includes(q)) return true
  if (activity.no?.toLowerCase().includes(q)) return true
  return getActivityGroupPathLines(activity).some((line) => line.label.toLowerCase().includes(q))
}

function getTypeLabel(type: string): string {
  const key = `activities.types.${type}`
  return te(key) ? t(key) : type
}

function getStatusLabel(status: string): string {
  const key = `activities.status.${activityStatusI18nKey(status)}`
  return te(key) ? t(key) : status
}

function getActivityShareHint(activity: ActivityListItem): string | null {
  if (activity.departmentId && activity.departmentId !== departmentId.value) {
    return `${t('activities.share.fromPrefix')} ${activity.departmentName || t('activities.share.otherDeptFallback')}`
  }
  const accepted = (activity.invitedDepartments || []).filter((entry) => entry?.status === 'accepted')
  if (accepted.length > 0) {
    const names = accepted
      .map((entry) => entry?.name)
      .filter((name): name is string => !!name)
      .slice(0, 2)
    if (names.length === 0) return t('activities.share.withOthers')
    const suffix = accepted.length > 2 ? t('activities.share.moreSuffix', { n: accepted.length - 2 }) : ''
    return `${t('activities.share.withPrefix')} ${names.join(', ')}${suffix}`
  }
  return null
}

function getActivityShareStatus(activity: ActivityListItem): string | null {
  const invited = activity.invitedDepartments || []
  if (activity.departmentId && activity.departmentId !== departmentId.value) {
    const ownInvite = invited.find((entry) => entry?.id === departmentId.value)
    if (!ownInvite) return null
    if (ownInvite.status === 'accepted') return t('activities.share.statusAccepted')
    if (ownInvite.status === 'rejected') return t('activities.share.statusRejected')
    return t('activities.share.statusPending')
  }
  if (invited.length === 0) return null
  const accepted = invited.filter((entry) => entry?.status === 'accepted').length
  const pending = invited.filter((entry) => entry?.status === 'pending').length
  const rejected = invited.filter((entry) => entry?.status === 'rejected').length
  const parts: string[] = []
  if (accepted > 0) parts.push(t('activities.share.partAccepted', { n: accepted }))
  if (pending > 0) parts.push(t('activities.share.partPending', { n: pending }))
  if (rejected > 0) parts.push(t('activities.share.partRejected', { n: rejected }))
  return parts.length > 0 ? `${t('activities.share.releasePrefix')} ${parts.join(' · ')}` : null
}

function onTableSort(payload: { field: string; order: 'asc' | 'desc' }) {
  sortField.value = payload.field
  sortDir.value = payload.order
}

function openActivityDetail(activity: ActivityListItem) {
  const id = activity.id?.trim()
  if (!id || !departmentId.value) return

  const now = Date.now()
  if (id === lastDetailOpenId && now - lastDetailOpenAt < 600) return
  lastDetailOpenId = id
  lastDetailOpenAt = now

  if (showCreateActivityWizard.value) {
    showCreateActivityWizard.value = false
  }

  if (route.params.activityId === id) return
  const query: Record<string, string | string[]> = {}
  const tour = route.query[ONBOARDING_TOUR_QUERY]
  const step = route.query[ONBOARDING_TOUR_STEP_QUERY]
  if (typeof tour === 'string' && tour) query[ONBOARDING_TOUR_QUERY] = tour
  if (typeof step === 'string' && step) query[ONBOARDING_TOUR_STEP_QUERY] = step
  void router.push({
    path: `/${departmentId.value}/activities/${id}`,
    query,
  })
}

function openCreateActivityWizard() {
  if (activityRouteId.value) {
    void router.push({ path: `/${departmentId.value}/activities`, query: { new: '1' } })
    return
  }
  showCreateActivityWizard.value = true
}

async function onActivityDraftSaved(id: string) {
  if (id) {
    try {
      const detail = await getActivity(id)
      upsertActivityInList(detail as unknown as Record<string, unknown>)
    } catch {
      /* Liste wird unten still nachgeladen */
    }
  }
  void loadActivities({ silent: true })
}

async function onActivityCreateWizardCreated(id: string) {
  activityJustCreated.value = true
  showCreateActivityWizard.value = false
  if (id) {
    try {
      const detail = await getActivity(id)
      upsertActivityInList(detail as unknown as Record<string, unknown>)
    } catch {
      /* Liste wird unten nachgeladen */
    }
  }
  void loadActivities()
  headerNotificationsStore.requestRefresh()
  if (route.query.from === 'dashboard') {
    const q = { ...route.query }
    delete q.from
    router.replace({ path: route.path, query: q })
  }
  if (id) {
    // Tour-Query behalten + nach Einreichen auf Detail-Schritt (Details anschauen)
    const query = { ...route.query }
    delete query.from
    delete query.new
    if (query[ONBOARDING_TOUR_QUERY] === 'activity-create') {
      query[ONBOARDING_TOUR_STEP_QUERY] = '8'
    }
    if (query[ONBOARDING_TOUR_QUERY] === 'activity-camp-create') {
      query[ONBOARDING_TOUR_STEP_QUERY] = '21'
    }
    await router.push({
      path: `/${departmentId.value}/activities/${id}`,
      query,
    })
  }
}

watch(activityRouteId, (id, prevId) => {
  if (id) {
    showCreateActivityWizard.value = false
    stripQueryFromDetailRoute()
    if (activityJustCreated.value) {
      activityJustCreated.value = false
    }
    return
  }
  if (prevId) {
    void loadActivities()
  }
})

watch(
  [activityRouteId, departmentId],
  ([id, deptId]) => {
    if (!id || !deptId) return
    const act = activities.value.find((a) => a.id === id)
    const label =
      act?.name?.trim() ||
      act?.no?.trim() ||
      t('activities.fallbackTabLabel', { id })
    detailTabsStore.addOrUpdateTab({
      id,
      type: 'activity',
      label,
      departmentId: deptId,
      path: `/${deptId}/activities/${id}`,
    })
  },
  { immediate: true }
)

// ?new=1: Erstell-Wizard nur auf der Listen-Route (nie in der Detailansicht)
watch(
  () => route.query.new,
  (val) => {
    if (val !== '1') return
    if (activityRouteId.value) {
      void router.replace({
        path: `/${departmentId.value}/activities`,
        query: { new: '1' },
      })
      return
    }
    if (!showCreateActivityWizard.value) {
      showCreateActivityWizard.value = true
    }
    const q = { ...route.query }
    delete q.new
    void router.replace({ path: route.path, query: q })
  },
  { immediate: true },
)

// from=dashboard: Bei Schließen ohne Speichern zurück zum Dashboard
watch(showCreateActivityWizard, (isOpen) => {
  if (!isOpen && route.query.from === 'dashboard' && departmentId.value && !activityJustCreated.value) {
    router.replace(`/${departmentId.value}`)
  }
})

watch(
  () => activityRouteId.value,
  (id) => {
    if (!id) {
      pageHeadStore.clearDynamic()
      syncDocumentHead(route)
    }
  },
  { immediate: true },
)

watch(
  () => ({ dept: departmentId.value, act: activityRouteId.value }),
  ({ dept, act }) => {
    if (!dept || act) return
    loadActivities()
  },
  { immediate: true },
)
</script>

<style scoped>
@import '@/styles/views/activities/index.css';

.activities-detail-root {
  padding: 0;
  max-width: none;
}

.activities-pack-journey-root {
  width: 100%;
}
</style>
