<template>
  <div class="activities-view">
    <ActivityCreateWizard
      v-model="showCreateActivityWizard"
      :department-id="departmentId"
      @created="onActivityCreateWizardCreated"
      @draft-saved="onActivityDraftSaved"
    />

    <div v-if="activityRouteId" class="dept-page activities-detail-root">
      <ActivityDetailView :department-id="departmentId" :activity-id="activityRouteId" />
    </div>

    <!-- Übersicht -->
    <template v-else>
      <div class="activities-header page-header header-content">
        <div class="header-left">
          <h1>{{ t('activities.title') }}</h1>
          <span class="subtitle">{{ t('activities.subtitle') }}</span>
        </div>
        <div class="header-right">
          <button type="button" class="btn-primary" @click="openCreateActivityWizard">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
              <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span>{{ t('activities.create') }}</span>
          </button>
        </div>
      </div>

      <div v-if="!isLoading" class="stats-bar">
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
          :class="{ 'stat-item-active': activeTab === 'all' && statusFilter === 'submitted' }"
          @click="applyStatFilter('submitted')"
        >
          <span class="stat-value stat-submitted">{{ activities.filter((a) => a.status === 'submitted').length }}</span>
          <span class="stat-label">{{ t('activities.stats.submitted') }}</span>
        </button>
        <button
          type="button"
          class="stat-item stat-item-btn"
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

      <div class="filter-bar">
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
        <div v-if="activeTab === 'all'" class="filter-actions filter-actions-all">
          <div class="search-box">
            <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <circle cx="11" cy="11" r="8" />
              <path d="m21 21-4.35-4.35" />
            </svg>
            <input
              v-model="searchQuery"
              type="search"
              class="search-input form-input"
              :placeholder="t('activities.searchListPlaceholder')"
            />
          </div>
        </div>
      </div>

      <div v-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>{{ t('activities.loadingList') }}</p>
      </div>

      <div v-else class="activities-table-wrapper">
        <table class="activities-table">
          <thead>
            <tr>
              <th class="col-status"></th>
              <th class="col-name" @click="toggleSort('name')">
                {{ t('activities.table.name') }}
                <span v-if="sortField === 'name'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-type">
                <div class="th-filter-wrap">
                  <span>{{ t('activities.table.type') }}</span>
                  <select
                    v-if="activeTab === 'all'"
                    v-model="activeTypeFilter"
                    class="col-filter-select"
                    @click.stop
                  >
                    <option value="">{{ t('activities.filters.allTypes') }}</option>
                    <option v-for="tpl in typeFilterOptions" :key="tpl.type" :value="tpl.type">{{ tpl.label }}</option>
                  </select>
                </div>
              </th>
              <th class="col-customer">{{ t('activities.table.group') }}</th>
              <th class="col-period" @click="toggleSort('date')">
                {{ t('activities.table.period') }}
                <span v-if="sortField === 'date'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-items">{{ t('activities.table.material') }}</th>
              <th class="col-price" @click="toggleSort('price')">
                {{ t('activities.table.price') }}
                <span v-if="sortField === 'price'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-progress">
                <div class="th-filter-wrap">
                  <span>{{ t('activities.table.status') }}</span>
                  <select
                    v-if="activeTab === 'all'"
                    v-model="statusFilter"
                    class="col-filter-select"
                    @click.stop
                  >
                    <option value="">{{ t('activities.filters.allStatuses') }}</option>
                    <option v-for="opt in statusFilterOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                </div>
              </th>
              <th class="col-issues">{{ t('activities.table.issues') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredActivities.length === 0">
              <td colspan="9" class="empty-state">
                <div class="empty-content">
                  <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                  </svg>
                  <p>{{ hasActiveListFilters ? t('activities.empty.noMatch') : t('activities.empty.noneYet') }}</p>
                </div>
              </td>
            </tr>
            <tr 
              v-for="activity in filteredActivities" 
              :key="activity.id"
              class="activity-row"
              :class="{
                'row-draft': activity.status === 'draft',
                'row-selected': selectedActivityId === activity.id,
              }"
              @click="selectedActivityId = activity.id"
              @dblclick.prevent="openActivityDetail(activity)"
            >
              <td class="col-status">
                <span class="status-dot" :class="activityStatusClass(activity.status)"></span>
              </td>
              <td class="col-name">
                <div class="activity-name">{{ activity.name }}</div>
                <div v-if="activity.no" class="activity-no">{{ activity.no }}</div>
                <div v-if="getActivityShareHint(activity)" class="activity-share-hint">{{ getActivityShareHint(activity) }}</div>
                <div v-if="getActivityShareStatus(activity)" class="activity-share-status">{{ getActivityShareStatus(activity) }}</div>
              </td>
              <td class="col-type">
                <span class="type-badge" :class="activity.type">{{ getTypeLabel(activity.type) }}</span>
              </td>
              <td class="col-customer">
                <span v-if="activity.type === 'external'" class="text-muted">–</span>
                <div v-else-if="getActivityGroupPathLines(activity).length" class="activity-group-path">
                  <span
                    v-for="(line, lineIdx) in getActivityGroupPathLines(activity)"
                    :key="lineIdx"
                    class="activity-group-path-line"
                    :style="{ paddingLeft: `${line.level * 12}px` }"
                  >{{ line.label }}</span>
                </div>
                <span v-else class="text-muted">–</span>
              </td>
              <td class="col-period">
                <span v-if="activity.usageStart" class="period-compact">{{
                  formatPeriodCompact(activity.usageStart, activity.usageEnd)
                }}</span>
                <span v-else class="text-muted">–</span>
              </td>
              <td class="col-items">
                <span v-if="activity.itemCount" class="items-badge">{{ activity.itemCount }}</span>
                <span v-else class="text-muted">0</span>
              </td>
              <td class="col-price">
                <span v-if="activity.totalPrice" class="price-display">CHF {{ activity.totalPrice.toFixed(2) }}</span>
                <span v-else class="text-muted">–</span>
              </td>
              <td class="col-progress">
                <span class="status-label" :class="activityStatusClass(activity.status)">{{ getStatusLabel(activity.status) }}</span>
              </td>
              <td class="col-issues" @click.stop>
                <router-link
                  v-if="['at_event', 'returned', 'completed'].includes(activity.status)"
                  class="activities-list-issues-link"
                  :to="`/${departmentId}/activities/${activity.id}?tab=issues`"
                >
                  {{ t('activities.table.issues') }}
                </router-link>
                <span v-else class="text-muted">–</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="filteredActivities.length > 0" class="table-footer">
          <span>{{ t('activities.table.footer', { shown: filteredActivities.length, total: activities.length }) }}</span>
        </div>
      </div>

                    </template>
                      </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'ActivitiesView' })
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/apiClient'
import { getActivity } from '@/api/activities'
import { getGroups, type Group } from '@/api/groups'
import { buildActivityGroupPathLines, type GroupPathLine } from '@/utils/groupHierarchy'
import { ActivityCreateWizard, ActivityDetailView } from '@/components/activities'
import { usePageHeadStore } from '@/stores/pageHead'
import { syncDocumentHead } from '@/composables/usePageHead'
import { useToast } from '@/composables/useToast'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import { useDepartmentLiveRefresh } from '@/composables/useDepartmentLiveRefresh'
import { useListSearchQueryRoute } from '@/composables/useListSearchQueryRoute'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'

const route = useRoute()
const router = useRouter()
const { t, te, locale } = useI18n()
const toast = useToast()
const headerNotificationsStore = useHeaderNotificationsStore()
const pageHeadStore = usePageHeadStore()

const departmentId = computed(() => route.params.departmentId as string)
const activityRouteId = computed(() => (route.params.activityId as string | undefined) || undefined)

const activities = ref<Activity[]>([])
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

interface Activity {
  id: string
  no?: string
  name: string
  departmentId?: string
  departmentName?: string
  type: 'activity' | 'camp' | 'event' | 'external'
  status: 'draft' | 'submitted' | 'approved' | 'packing' | 'packed' | 'at_event' | 'returned' | 'completed' | 'cancelled'
  invitedDepartments?: Array<{ id?: string; name?: string; organisation_name?: string; status?: string }>
  groupId?: string | null
  groupName?: string
  usageStart?: string
  usageEnd?: string
  itemCount?: number
  totalPrice?: number
  createdAt: string
  updatedAt: string
}

const ACTIVITY_FILTER_TYPES = ['activity', 'camp', 'event', 'external'] as const

const typeFilterOptions = computed(() =>
  ACTIVITY_FILTER_TYPES.map((type) => ({
    type,
    label: t(`activities.types.${type}`),
  })),
)

const statusFilterOptions = computed(() => [
  { value: 'draft' as const, label: t('activities.stats.drafts') },
  { value: 'submitted' as const, label: t('activities.stats.submitted') },
  { value: 'in_progress' as const, label: t('activities.stats.inProgress') },
  { value: 'at_event' as const, label: t('activities.stats.issued') },
  { value: 'completed' as const, label: t('activities.stats.completed') },
  { value: 'cancelled' as const, label: t('activities.tabs.cancelled') },
])

const hasActiveListFilters = computed(
  () =>
    !!searchQuery.value ||
    !!activeTypeFilter.value ||
    (activeTab.value === 'all' && !!statusFilter.value),
)

function nameSortLocale(): string {
  const raw = String(locale.value)
  if (raw.startsWith('de')) return 'de'
  if (raw.startsWith('fr')) return 'fr'
  if (raw.startsWith('it')) return 'it'
  return 'en'
}

function mapActivityListItem(a: Record<string, unknown>): Activity {
  const no = a.no as number | string | undefined
  return {
    id: String(a.id),
    no: no != null ? `#${String(no).padStart(3, '0')}` : undefined,
    name: String(a.name ?? ''),
    departmentId: a.department_id as string | undefined,
    departmentName: a.department_name as string | undefined,
    type: a.type as Activity['type'],
    status: a.status as Activity['status'],
    invitedDepartments: Array.isArray(a.invited_departments) ? (a.invited_departments as Activity['invitedDepartments']) : [],
    groupId: (a.group_id as string | null | undefined) ?? null,
    groupName: a.group_name as string | undefined,
    usageStart: a.usage_start as string | undefined,
    usageEnd: a.usage_end as string | undefined,
    itemCount: (a.item_count as number | undefined) ?? 0,
    totalPrice: a.total_price as number | undefined,
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

function isOpenActivity(a: Activity): boolean {
  return a.status !== 'completed' && a.status !== 'cancelled'
}

function isUpcomingActivity(a: Activity): boolean {
  if (!isOpenActivity(a)) return false
  if (!a.usageEnd) return true
  const endDate = new Date(a.usageEnd)
  const todayStart = new Date()
  todayStart.setHours(0, 0, 0, 0)
  return endDate >= todayStart
}

function matchesStatusFilter(a: Activity, filter: StatusFilter): boolean {
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
  }
}

function applyStatFilter(filter: StatusFilter) {
  activeTab.value = 'all'
  statusFilter.value = filter
}

const filteredActivities = computed(() => {
  let result = activities.value

  if (activeTab.value === 'open') {
    result = result.filter(isOpenActivity)
  } else if (activeTab.value === 'upcoming') {
    result = result.filter(isUpcomingActivity)
  }

  if (activeTab.value === 'all' && statusFilter.value) {
    result = result.filter((a) => matchesStatusFilter(a, statusFilter.value))
  }

  if (activeTab.value === 'all' && activeTypeFilter.value) {
    result = result.filter((a) => a.type === activeTypeFilter.value)
  }

  if (activeTab.value === 'all' && searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter((a) => activityMatchesSearch(a, q))
  }

  result = [...result].sort((a, b) => {
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

  return result
})

function getActivityGroupPathLines(activity: Activity): GroupPathLine[] {
  if (activity.type === 'external') return []
  return buildActivityGroupPathLines(
    activity.groupId,
    activity.departmentName || '',
    departmentGroups.value,
    activity.groupName,
  )
}

function activityMatchesSearch(activity: Activity, q: string): boolean {
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

function getActivityShareHint(activity: Activity): string | null {
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

function getActivityShareStatus(activity: Activity): string | null {
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

function isSameDay(date1: string, date2: string): boolean {
  const d1 = new Date(date1)
  const d2 = new Date(date2)
  return d1.toDateString() === d2.toDateString()
}

function pad2(n: number): string {
  return String(n).padStart(2, '0')
}

function formatDayMonthYear(d: Date): string {
  return `${pad2(d.getDate())}.${pad2(d.getMonth() + 1)}.${String(d.getFullYear()).slice(-2)}`
}

/** z. B. 14.03.26 oder 14.–18.03.26 */
function formatPeriodCompact(startStr?: string, endStr?: string): string {
  if (!startStr) return ''
  const start = new Date(startStr)
  if (!endStr || isSameDay(startStr, endStr)) return formatDayMonthYear(start)
  const end = new Date(endStr)
  const d1 = pad2(start.getDate())
  const m1 = pad2(start.getMonth() + 1)
  const y1 = String(start.getFullYear()).slice(-2)
  const d2 = pad2(end.getDate())
  const m2 = pad2(end.getMonth() + 1)
  const y2 = String(end.getFullYear()).slice(-2)
  const sameMonth = start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()
  if (sameMonth && y1 === y2) return `${d1}.–${d2}.${m1}.${y1}`
  if (y1 === y2) return `${d1}.${m1}.–${d2}.${m2}.${y2}`
  return `${d1}.${m1}.${y1}–${d2}.${m2}.${y2}`
}

function toggleSort(field: string) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'asc'
  }
}

function openActivityDetail(activity: Activity) {
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
  void router.push(`/${departmentId.value}/activities/${id}`)
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
    await router.push(`/${departmentId.value}/activities/${id}`)
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

.text-muted {
  color: #6b7280;
}

.row-selected {
  outline: 2px solid var(--color-primary-ring);
}

.col-issues {
  width: 100px;
  text-align: right;
  white-space: nowrap;
}

.activities-list-issues-link {
  font-size: 13px;
  font-weight: 600;
  color: #b45309;
  text-decoration: none;
}

.activities-list-issues-link:hover {
  text-decoration: underline;
}

</style>
