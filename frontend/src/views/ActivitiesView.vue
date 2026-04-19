<template>
  <div class="activities-view">
    <ActivityCreateWizard
      v-model="showCreateActivityWizard"
      :department-id="departmentId"
      :resume-activity-id="resumeWizardActivityId"
      @created="onActivityCreateWizardCreated"
      @resume-consumed="resumeWizardActivityId = null"
    />

    <div v-if="activityRouteId && activityDetailGateLoading" class="loading-state activities-detail-gate">
      <div class="spinner"></div>
      <p>Aktivität wird geöffnet…</p>
          </div>

    <div v-else-if="activityRouteId && showActivityDetail" class="dept-page activities-detail-root">
      <ActivityDetailView :department-id="departmentId" :activity-id="activityRouteId" />
                </div>

    <!-- Übersicht -->
    <template v-else-if="!activityRouteId">
      <div class="activities-header page-header header-content">
        <div class="header-left">
          <h1>Aktivitäten</h1>
          <span class="subtitle">Events, Vermietungen & Ausleihen verwalten</span>
        </div>
        <div class="header-right">
          <button type="button" class="btn-primary" @click="openCreateActivityWizard">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
              <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span>Aktivität erstellen</span>
          </button>
        </div>
      </div>

      <div v-if="!isLoading && activities.length > 0" class="stats-bar">
        <div class="stat-item">
          <span class="stat-value">{{ activities.length }}</span>
          <span class="stat-label">Gesamt</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-draft">{{ activities.filter((a) => a.status === 'draft').length }}</span>
          <span class="stat-label">Entwürfe</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-submitted">{{ activities.filter((a) => a.status === 'submitted').length }}</span>
          <span class="stat-label">Eingereicht</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-approved">{{ activities.filter((a) => ['approved', 'packing', 'packed'].includes(a.status)).length }}</span>
          <span class="stat-label">In Bearbeitung</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-issued">{{ activities.filter((a) => a.status === 'issued').length }}</span>
          <span class="stat-label">Ausgegeben</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-completed">{{ activities.filter((a) => a.status === 'completed').length }}</span>
          <span class="stat-label">Abgeschlossen</span>
        </div>
      </div>

      <div class="filter-bar">
        <div class="filter-tabs">
          <button 
            v-for="tab in tabs" 
            :key="tab.key"
            class="filter-tab"
            :class="{ active: activeTab === tab.key }"
            type="button"
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
            <span v-if="tab.count !== undefined" class="tab-count">{{ tab.count }}</span>
          </button>
        </div>
        <div class="filter-actions">
          <div class="type-filter-chips">
            <button 
              v-for="tpl in typeFilterChips"
              :key="tpl.type"
              type="button"
              class="type-chip"
              :class="{ active: activeTypeFilter === tpl.type }"
              @click="activeTypeFilter = activeTypeFilter === tpl.type ? '' : tpl.type"
            >
              {{ tpl.label }}
            </button>
          </div>
          <div class="search-box">
            <GlobalSearchInput
              mode="inline"
              :department-id="departmentId"
              default-type="activity"
              v-model="searchQuery"
              placeholder="Suchen (material:, aktivität:, reparatur:)"
            />
          </div>
        </div>
      </div>

      <div v-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>Aktivitäten werden geladen...</p>
      </div>

      <div v-else class="activities-table-wrapper">
        <table class="activities-table">
          <thead>
            <tr>
              <th class="col-status"></th>
              <th class="col-name" @click="toggleSort('name')">
                Name
                <span v-if="sortField === 'name'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-type">Typ</th>
              <th class="col-customer">Gruppe / Kunde</th>
              <th class="col-period" @click="toggleSort('date')">
                Zeitraum
                <span v-if="sortField === 'date'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-items">Material</th>
              <th class="col-price" @click="toggleSort('price')">
                Preis
                <span v-if="sortField === 'price'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-progress">Status</th>
              <th class="col-issues">Meldungen</th>
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
                  <p>{{ searchQuery || activeTypeFilter ? 'Keine Treffer' : 'Noch keine Aktivitäten vorhanden' }}</p>
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
              @dblclick="openActivityDetail(activity)"
            >
              <td class="col-status">
                <span class="status-dot" :class="activity.status"></span>
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
                <span v-if="activity.groupName" class="customer-group">{{ activity.groupName }}</span>
                <span v-else class="text-muted">–</span>
              </td>
              <td class="col-period">
                <span v-if="activity.usageStart" class="period-display">
                  <span class="period-date">{{ formatDateShort(activity.usageStart) }}</span>
                  <span v-if="activity.usageEnd && !isSameDay(activity.usageStart, activity.usageEnd)" class="period-separator">–</span>
                  <span v-if="activity.usageEnd && !isSameDay(activity.usageStart, activity.usageEnd)" class="period-date">{{
                    formatDateShort(activity.usageEnd)
                  }}</span>
                  <span class="period-relative">{{ getRelativeDate(activity.usageStart) }}</span>
                </span>
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
                <span class="status-label" :class="activity.status">{{ getStatusLabel(activity.status) }}</span>
              </td>
              <td class="col-issues" @click.stop>
                <router-link
                  v-if="['issued', 'returned', 'completed'].includes(activity.status)"
                  class="activities-list-issues-link"
                  :to="`/${departmentId}/activities/${activity.id}?tab=issues`"
                >
                  Meldungen
                </router-link>
                <span v-else class="text-muted">–</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="filteredActivities.length > 0" class="table-footer">
          <span>{{ filteredActivities.length }} von {{ activities.length }} Aktivitäten</span>
        </div>
      </div>

                    </template>
                      </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'ActivitiesView' })
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/api/apiClient'
import { getActivity } from '@/api/activities'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import { ActivityCreateWizard, ActivityDetailView } from '@/components/activities'
import { usePageHeadStore } from '@/stores/pageHead'
import { syncDocumentHead } from '@/composables/usePageHead'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const pageHeadStore = usePageHeadStore()

const departmentId = computed(() => route.params.departmentId as string)
const activityRouteId = computed(() => (route.params.activityId as string | undefined) || undefined)

const activities = ref<Activity[]>([])
const isLoading = ref(false)
const activeTab = ref('upcoming')
const activeTypeFilter = ref('')
const searchQuery = ref('')
const sortField = ref('date')
const sortDir = ref<'asc' | 'desc'>('asc')
const selectedActivityId = ref<string | null>(null)
const showCreateActivityWizard = ref(false)
/** Entwurf aus Detail-Route: Wizard fortsetzen */
const resumeWizardActivityId = ref<string | null>(null)
/** Detail nur, wenn Erstell-Wizard abgeschlossen (oder nicht Stepper-Typ) */
const showActivityDetail = ref(false)
const activityDetailGateLoading = ref(false)
/** Verhindert Dashboard-Rücksprung nach erfolgreicher Anlage (Navigation zur Detailseite). */
const activityJustCreated = ref(false)

const STEPPER_ACTIVITY_TYPES = ['camp', 'event', 'external'] as const

interface Activity {
  id: string
  no?: string
  name: string
  departmentId?: string
  departmentName?: string
  type: 'activity' | 'camp' | 'event' | 'external'
  status: 'draft' | 'submitted' | 'approved' | 'packing' | 'packed' | 'issued' | 'returned' | 'completed' | 'cancelled'
  invitedDepartments?: Array<{ id?: string; name?: string; organisation_name?: string; status?: string }>
  groupName?: string
  usageStart?: string
  usageEnd?: string
  itemCount?: number
  totalPrice?: number
  createdAt: string
  updatedAt: string
}

const typeFilterChips = [
  { type: 'activity', label: 'Aktivität' },
  { type: 'camp', label: 'Lager' },
  { type: 'event', label: 'Event' },
  { type: 'external', label: 'Extern' },
]

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
    groupName: a.group_name as string | undefined,
    usageStart: a.usage_start as string | undefined,
    usageEnd: a.usage_end as string | undefined,
    itemCount: (a.item_count as number | undefined) ?? 0,
    totalPrice: a.total_price as number | undefined,
    createdAt: String(a.created_at ?? ''),
    updatedAt: String(a.updated_at ?? ''),
  }
}

async function loadActivities() {
  isLoading.value = true
  try {
    const response = await apiClient.get('/api/activities', {
      params: { department_id: departmentId.value },
    })
    activities.value = (response.data || []).map((a: Record<string, unknown>) => mapActivityListItem(a))
  } catch (err: unknown) {
    const e = err as { code?: string; response?: { data?: { error?: string } }; message?: string }
    const msg =
      e?.code === 'ECONNABORTED'
        ? 'Zeitüberschreitung – Backend antwortet nicht.'
        : e?.response?.data?.error || e?.message || 'Unbekannter Fehler'
    toast.error('Aktivitäten konnten nicht geladen werden: ' + msg)
  } finally {
    isLoading.value = false
  }
}

function isUpcomingActivity(a: Activity): boolean {
  if (!['draft', 'submitted', 'approved', 'packing', 'packed', 'issued', 'returned'].includes(a.status)) return false
  if (!a.usageEnd) return true
  const endDate = new Date(a.usageEnd)
  const todayStart = new Date()
  todayStart.setHours(0, 0, 0, 0)
  return endDate >= todayStart
}

function isPastActivity(a: Activity): boolean {
  if (a.status === 'cancelled') return false
  if (a.status === 'completed') return true
  if (!a.usageEnd) return false
  const endDate = new Date(a.usageEnd)
  const todayStart = new Date()
  todayStart.setHours(0, 0, 0, 0)
  return endDate < todayStart
}

const tabs = computed(() => [
  { key: 'upcoming', label: 'Anstehend', count: activities.value.filter(isUpcomingActivity).length },
  { key: 'past', label: 'Vergangen', count: activities.value.filter(isPastActivity).length },
  { key: 'all', label: 'Alle', count: activities.value.length },
  { key: 'cancelled', label: 'Storniert', count: activities.value.filter((a) => a.status === 'cancelled').length },
])

const filteredActivities = computed(() => {
  let result = activities.value

  if (activeTab.value === 'upcoming') {
    result = result.filter(isUpcomingActivity)
  } else if (activeTab.value === 'past') {
    result = result.filter(isPastActivity)
  } else if (activeTab.value === 'cancelled') {
    result = result.filter((a) => a.status === 'cancelled')
  }

  if (activeTypeFilter.value) {
    result = result.filter((a) => a.type === activeTypeFilter.value)
  }

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(
      (a) =>
      a.name.toLowerCase().includes(q) || 
      a.groupName?.toLowerCase().includes(q) ||
        a.no?.toLowerCase().includes(q),
    )
  }

  result = [...result].sort((a, b) => {
    let cmp = 0
    if (activeTab.value === 'past') {
      const da = new Date(a.usageEnd || a.usageStart || 0).getTime()
      const db = new Date(b.usageEnd || b.usageStart || 0).getTime()
      return db - da
    }
    if (sortField.value === 'name') {
      cmp = a.name.localeCompare(b.name, 'de')
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

function getTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    activity: 'Aktivität',
    camp: 'Lager',
    event: 'Event',
    external: 'Extern',
  }
  return labels[type] || type
}

function getStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    draft: 'Entwurf',
    submitted: 'Eingereicht',
    approved: 'Bestätigt',
    packing: 'Wird gepackt',
    packed: 'Gepackt',
    issued: 'Ausgegeben',
    returned: 'Retour',
    completed: 'Abgeschlossen',
    cancelled: 'Storniert',
    confirmed: 'Bestätigt',
    active: 'Aktiv',
  }
  return labels[status] || status
}

function getActivityShareHint(activity: Activity): string | null {
  if (activity.departmentId && activity.departmentId !== departmentId.value) {
    return `Geteilt von: ${activity.departmentName || 'anderem Department'}`
  }
  const accepted = (activity.invitedDepartments || []).filter((entry) => entry?.status === 'accepted')
  if (accepted.length > 0) {
    const names = accepted
      .map((entry) => entry?.name)
      .filter((name): name is string => !!name)
      .slice(0, 2)
    if (names.length === 0) return 'Geteilt mit anderen Departments'
    const suffix = accepted.length > 2 ? ` +${accepted.length - 2}` : ''
    return `Geteilt mit: ${names.join(', ')}${suffix}`
  }
  return null
}

function getActivityShareStatus(activity: Activity): string | null {
  const invited = activity.invitedDepartments || []
  if (activity.departmentId && activity.departmentId !== departmentId.value) {
    const ownInvite = invited.find((entry) => entry?.id === departmentId.value)
    if (!ownInvite) return null
    if (ownInvite.status === 'accepted') return 'Status: angenommen'
    if (ownInvite.status === 'rejected') return 'Status: abgelehnt'
    return 'Status: ausstehend'
  }
  if (invited.length === 0) return null
  const accepted = invited.filter((entry) => entry?.status === 'accepted').length
  const pending = invited.filter((entry) => entry?.status === 'pending').length
  const rejected = invited.filter((entry) => entry?.status === 'rejected').length
  const parts: string[] = []
  if (accepted > 0) parts.push(`${accepted} angenommen`)
  if (pending > 0) parts.push(`${pending} ausstehend`)
  if (rejected > 0) parts.push(`${rejected} abgelehnt`)
  return parts.length > 0 ? `Freigabe: ${parts.join(' · ')}` : null
}

function isSameDay(date1: string, date2: string): boolean {
  const d1 = new Date(date1)
  const d2 = new Date(date2)
  return d1.toDateString() === d2.toDateString()
}

function formatDateShort(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: 'short' })
}

function getRelativeDate(dateStr: string): string {
  const now = new Date()
  const d = new Date(dateStr)
  const diffMs = d.getTime() - now.getTime()
  const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24))
  if (diffDays === 0) return 'heute'
  if (diffDays === 1) return 'morgen'
  if (diffDays === -1) return 'gestern'
  if (diffDays > 0 && diffDays <= 7) return `in ${diffDays} Tagen`
  if (diffDays < 0 && diffDays >= -7) return `vor ${Math.abs(diffDays)} Tagen`
  if (diffDays > 7 && diffDays <= 30) return `in ${Math.ceil(diffDays / 7)} Wochen`
  if (diffDays < -7 && diffDays >= -30) return `vor ${Math.ceil(Math.abs(diffDays) / 7)} Wochen`
  return ''
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
  router.push(`/${departmentId.value}/activities/${activity.id}`)
}

function openCreateActivityWizard() {
  showCreateActivityWizard.value = true
}

function onActivityCreateWizardCreated(id: string) {
  activityJustCreated.value = true
  showCreateActivityWizard.value = false
  void loadActivities()
  if (route.query.from === 'dashboard') {
    const q = { ...route.query }
    delete q.from
    router.replace({ path: route.path, query: q })
  }
  if (id) {
    router.push(`/${departmentId.value}/activities/${id}`)
  }
}

watch(
  () => route.query.q,
  (q) => {
    if (route.path.includes('/activities')) {
      searchQuery.value = (q as string) ?? ''
    }
  },
  { immediate: true },
)

// ?new=1: Erstell-Wizard öffnen (z. B. vom Dashboard)
watch(
  () => route.query.new,
  (val) => {
    if (val === '1' && !showCreateActivityWizard.value) {
      openCreateActivityWizard()
      const q = { ...route.query }
      delete q.new
      router.replace({ path: route.path, query: q })
    }
  },
  { immediate: true },
)

// from=dashboard: Bei Schließen ohne Speichern zurück zum Dashboard
watch(showCreateActivityWizard, (isOpen) => {
  if (!isOpen) {
    resumeWizardActivityId.value = null
  }
  if (!isOpen && route.query.from === 'dashboard' && departmentId.value && !activityJustCreated.value) {
    router.replace(`/${departmentId.value}`)
  }
  if (!isOpen) activityJustCreated.value = false
})

watch(
  () => ({ aid: activityRouteId.value, dept: departmentId.value }),
  async ({ aid, dept }) => {
    showActivityDetail.value = false
    if (!aid || !dept) {
      activityDetailGateLoading.value = false
    return
  }
    activityDetailGateLoading.value = true
    try {
      const act = await getActivity(aid)
      const isStepper = STEPPER_ACTIVITY_TYPES.includes(act.type as (typeof STEPPER_ACTIVITY_TYPES)[number])
      const wizardIncomplete = isStepper && act.create_wizard_completed === false
      if (wizardIncomplete) {
        resumeWizardActivityId.value = aid
        showCreateActivityWizard.value = true
        await router.replace({ path: `/${dept}/activities`, query: { ...route.query } })
        showActivityDetail.value = false
      } else {
        showActivityDetail.value = true
      }
    } catch {
      showActivityDetail.value = true
  } finally {
      activityDetailGateLoading.value = false
    }
  },
  { immediate: true },
)

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
  outline: 2px solid rgba(99, 102, 241, 0.35);
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
