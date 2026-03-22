<template>
  <div class="dashboard">
    <!-- Header -->
    <header class="dashboard-header">
      <div class="header-content">
        <h1>Dashboard</h1>
        <p class="welcome-text">
          Willkommen, {{ dashboardDisplayName }} – {{ formatDate(new Date()) }}
        </p>
      </div>
    </header>

    <!-- Offene Join-Requests (über Schnellaktionen, nur mit Department + Daten) -->
    <section
      v-if="departmentId && !isLoading && showJoinRequestsWidget && (hasOpenJoinRequests || hasSupportAdminRole)"
      class="dashboard-section join-requests-above-actions"
    >
      <h2 class="section-title">
        <router-link :to="getLink('/support-requests')" class="section-title-link">
          Offene Join-Requests
        </router-link>
      </h2>
      <div class="stat-cards">
        <router-link :to="getLink('/support-requests')" class="stat-card submitted join-request-stat-link">
          <span class="stat-value">{{ totalOpenJoinRequests }}</span>
          <span class="stat-label">Offen gesamt</span>
        </router-link>
        <div v-if="showAdminJoinRequestsWidget" class="stat-card draft">
          <span class="stat-value">{{ pendingAdminJoinRequests.length }}</span>
          <span class="stat-label">Admin-Anfragen</span>
        </div>
      </div>
      <router-link :to="getLink('/support-requests')" class="section-link">Zu Supportanfragen →</router-link>
    </section>

    <!-- Schnellaktionen (nur mit Department) -->
    <div v-if="departmentId" class="quick-actions">
      <router-link
        v-if="showNewActivity"
        :to="{ path: getLink('/activities'), query: { new: '1', from: 'dashboard' } }"
        class="quick-action-btn primary"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
          <line x1="12" y1="5" x2="12" y2="19"/>
          <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        <span>Neue Aktivität</span>
      </router-link>
      <router-link
        v-if="showMaterialCreate"
        :to="{ path: getLink('/materials'), query: { new: '1', from: 'dashboard' } }"
        class="quick-action-btn primary"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
          <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
        <span>Material erstellen</span>
      </router-link>
      <button
        class="quick-action-btn"
        @click="showDamageWizard = true"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
          <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
        </svg>
        <span>Schaden melden</span>
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="dashboard-loading">
      <div class="spinner"></div>
      <p>Dashboard wird geladen...</p>
    </div>

    <!-- Content -->
    <div v-else class="dashboard-content">
      <!-- Superadmin: globale Verwaltungs-Shortcuts -->
      <section v-if="isSuperAdmin" class="dashboard-section admin-global-shortcuts">
        <h2 class="section-title">Organisation & Abteilungen</h2>
        <div class="config-links">
          <router-link to="/admin-dashboard/verwaltung/organisations" class="config-card">
            <span class="config-label">Organisationen</span>
            <span class="config-desc">Organisationen verwalten</span>
          </router-link>
          <router-link to="/admin-dashboard/verwaltung/departments" class="config-card">
            <span class="config-label">Abteilungen</span>
            <span class="config-desc">Departments verwalten</span>
          </router-link>
        </div>
      </section>

      <!-- Nur ohne Department: globale Admin-Support-Zahlen -->
      <section v-if="!departmentId && hasSupportAdminRole" class="dashboard-section">
        <h2 class="section-title">
          <router-link to="/admin-dashboard/verwaltung/support-requests" class="section-title-link">
            Supportanfragen
          </router-link>
        </h2>
        <div class="stat-cards">
          <router-link
            to="/admin-dashboard/verwaltung/support-requests"
            class="stat-card submitted join-request-stat-link"
          >
            <span class="stat-value">{{ globalAdminPendingCount }}</span>
            <span class="stat-label">Offene Admin-Anfragen</span>
          </router-link>
        </div>
        <router-link to="/admin-dashboard/verwaltung/support-requests" class="section-link">Zu Supportanfragen →</router-link>
      </section>

      <template v-if="departmentId">
      <!-- User / L1-L3: Aktive Aktivitäten -->
      <section v-if="showActiveActivities" class="dashboard-section">
        <h2 class="section-title">Meine aktiven Aktivitäten</h2>
        <div v-if="activeActivities.length === 0" class="widget-empty">
          <p>Keine aktiven Aktivitäten</p>
          <router-link :to="getLink('/activities')" class="btn-link">Aktivitäten anzeigen</router-link>
        </div>
        <div v-else class="activity-list">
          <router-link
            v-for="a in activeActivities.slice(0, 5)"
            :key="a.id"
            :to="getLink(`/activities/${a.id}`)"
            class="activity-card"
          >
            <span class="status-dot" :class="a.status"></span>
            <div class="activity-info">
              <span class="activity-name">{{ a.name }}</span>
              <span class="activity-meta">{{ formatDateShort(a.usage_start) }} · {{ getStatusLabel(a.status) }}</span>
            </div>
            <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </router-link>
        </div>
        <router-link v-if="activeActivities.length > 0" :to="getLink('/activities')" class="section-link">
          Alle Aktivitäten anzeigen →
        </router-link>
      </section>

      <!-- L1-L3: Entwürfe -->
      <section v-if="showDraftsWidget && draftCount > 0" class="dashboard-section">
        <h2 class="section-title">Entwürfe zur Prüfung</h2>
        <div class="stat-cards">
          <div class="stat-card draft">
            <span class="stat-value">{{ draftCount }}</span>
            <span class="stat-label">Entwürfe</span>
          </div>
          <div class="stat-card submitted">
            <span class="stat-value">{{ submittedCount }}</span>
            <span class="stat-label">Eingereicht</span>
          </div>
        </div>
        <router-link :to="getLink('/activities')" class="section-link">Aktivitäten prüfen →</router-link>
      </section>

      <!-- DC / MW: Übersicht -->
      <section v-if="showOverviewWidget" class="dashboard-section">
        <h2 class="section-title">Aktivitäten-Übersicht</h2>
        <div class="stat-cards overview">
          <div class="stat-card draft">
            <span class="stat-value">{{ draftCount }}</span>
            <span class="stat-label">Entwürfe</span>
          </div>
          <div class="stat-card submitted">
            <span class="stat-value">{{ submittedCount }}</span>
            <span class="stat-label">Eingereicht</span>
          </div>
          <div class="stat-card approved">
            <span class="stat-value">{{ inProgressCount }}</span>
            <span class="stat-label">In Bearbeitung</span>
          </div>
          <div class="stat-card issued">
            <span class="stat-value">{{ issuedCount }}</span>
            <span class="stat-label">Ausgegeben</span>
          </div>
        </div>
      </section>

      <!-- DC / MW: Werkstatt -->
      <section v-if="showWorkshopWidget && workshopStats" class="dashboard-section">
        <h2 class="section-title">Werkstatt</h2>
        <div class="stat-cards workshop">
          <div class="stat-card open">
            <span class="stat-value">{{ workshopStats.status_counts?.open || 0 }}</span>
            <span class="stat-label">Offen</span>
          </div>
          <div class="stat-card in-progress">
            <span class="stat-value">{{ workshopStats.status_counts?.in_progress || 0 }}</span>
            <span class="stat-label">In Arbeit</span>
          </div>
          <div class="stat-card waiting">
            <span class="stat-value">{{ workshopStats.status_counts?.waiting_parts || 0 }}</span>
            <span class="stat-label">Wartet auf Teile</span>
          </div>
          <router-link
            class="stat-card warning"
            :to="{ path: getLink('/workshop'), query: { qf: 'waiting_quote' } }"
            style="text-decoration:none; color:inherit;"
            title="Offerten offen in Werkstatt anzeigen"
          >
            <span class="stat-value">{{ workshopStats.pending_cost_tasks?.waiting_quote || 0 }}</span>
            <span class="stat-label">Offerten offen</span>
          </router-link>
          <router-link
            class="stat-card warning"
            :to="{ path: getLink('/workshop'), query: { qf: 'missing_estimated_cost' } }"
            style="text-decoration:none; color:inherit;"
            title="Tickets ohne Preisschätzung anzeigen"
          >
            <span class="stat-value">{{ workshopStats.pending_cost_tasks?.missing_estimated_cost || 0 }}</span>
            <span class="stat-label">Preis fehlt</span>
          </router-link>
        </div>
        <router-link :to="getLink('/workshop')" class="section-link">Zur Werkstatt →</router-link>
      </section>

      <!-- MW: Pack-Queue heute -->
      <section v-if="showPackQueueWidget && todayActivities.length > 0" class="dashboard-section">
        <h2 class="section-title">Heute relevant</h2>
        <div class="activity-list">
          <router-link
            v-for="a in todayActivities.slice(0, 5)"
            :key="a.id"
            :to="getLink(`/activities/${a.id}`)"
            class="activity-card"
          >
            <span class="status-dot" :class="a.status"></span>
            <div class="activity-info">
              <span class="activity-name">{{ a.name }}</span>
              <span class="activity-meta">{{ getPlanningLabel(a) }}</span>
            </div>
            <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </router-link>
        </div>
        <router-link :to="getLink('/activities')" class="section-link">Alle Aktivitäten →</router-link>
      </section>

      <!-- Kommende Termine (alle Rollen) -->
      <section v-if="upcomingActivities.length > 0" class="dashboard-section">
        <h2 class="section-title">Kommende Termine</h2>
        <div class="activity-list compact">
          <router-link
            v-for="a in upcomingActivities.slice(0, 5)"
            :key="a.id"
            :to="getLink(`/activities/${a.id}`)"
            class="activity-card compact"
          >
            <span class="status-dot" :class="a.status"></span>
            <div class="activity-info">
              <span class="activity-name">{{ a.name }}</span>
              <span class="activity-meta">{{ formatDateShort(a.usage_start) }} {{ getRelativeDate(a.usage_start) }}</span>
            </div>
            <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </router-link>
        </div>
      </section>
      </template>
    </div>

    <!-- Schaden-melden-Wizard -->
    <DamageReportWizard
      v-if="departmentId"
      :is-open="showDamageWizard"
      :department-id="departmentId"
      @close="showDamageWizard = false"
      @success="onDamageReportSuccess"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getDashboardData, type DashboardActivity } from '@/api/dashboard'
import { getPendingAdminJoinRequests } from '@/api/joinRequests'
import DamageReportWizard from '@/components/DamageReportWizard.vue'

const route = useRoute()
const authStore = useAuthStore()
const dashboardDisplayName = computed(() => {
  const nickname = authStore.profile?.nickname?.trim()
  if (nickname) return nickname
  return authStore.userDisplayName
})

/**
 * Superadmin ohne Department-Mitgliedschaft: kein operatives Department-Dashboard
 * (keine Schnellaktionen, Aktivitäten, Werkstatt — nur globale Verwaltungsblöcke).
 */
const departmentId = computed(() => {
  const hasNoDeptMembership =
    authStore.userRoles.includes('ROLE_SUPERADMIN') &&
    (authStore.departments?.length ?? 0) === 0
  if (hasNoDeptMembership) return ''
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})
const showDamageWizard = ref(false)

// === State ===
const isLoading = ref(true)
const dashboardData = ref<Awaited<ReturnType<typeof getDashboardData>> | null>(null)
const globalAdminPendingCount = ref(0)

// === Role helpers ===
// Department-Rollen: nur mw, dc, l1, l2, l3, u (sa/org/sub kommen aus profile.roles)
const role = computed(() => (authStore.currentDepartmentRole || 'u').toLowerCase())
const isSuperAdmin = computed(() => authStore.userRoles.includes('ROLE_SUPERADMIN'))
const USER_ROLES = ['u', 'user']
const LEADER_ROLES = ['l1', 'l2', 'l3']
const MW_ROLES = ['mw']
const DC_ROLES = ['dc']
const MW_DASHBOARD_ROLES = ['mw']
const hasSupportAdminRole = computed(() =>
  authStore.userRoles.includes('ROLE_SUPERADMIN') ||
  authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
  authStore.userRoles.includes('ROLE_SUBORGCHEF')
)

const showNewActivity = computed(
  () =>
    USER_ROLES.includes(role.value) ||
    LEADER_ROLES.includes(role.value) ||
    MW_DASHBOARD_ROLES.includes(role.value)
)
const showMaterialCreate = computed(() => MW_DASHBOARD_ROLES.includes(role.value))
const showActiveActivities = computed(() => USER_ROLES.includes(role.value) || LEADER_ROLES.includes(role.value))
const showDraftsWidget = computed(() => LEADER_ROLES.includes(role.value))
const showOverviewWidget = computed(() => DC_ROLES.includes(role.value) || MW_DASHBOARD_ROLES.includes(role.value))
const showWorkshopWidget = computed(() => DC_ROLES.includes(role.value) || MW_DASHBOARD_ROLES.includes(role.value))
const showPackQueueWidget = computed(() => MW_DASHBOARD_ROLES.includes(role.value))
const showJoinRequestsWidget = computed(
  () => DC_ROLES.includes(role.value) || MW_ROLES.includes(role.value) || hasSupportAdminRole.value
)
const showAdminJoinRequestsWidget = computed(() => hasSupportAdminRole.value)

// === Data ===
const activitiesUpcoming = computed(() => dashboardData.value?.activitiesUpcoming || [])
const dashboardActivities = computed(() => dashboardData.value?.activities || [])
const workshopStats = computed(() => dashboardData.value?.workshopStats || null)
const pendingJoinRequests = computed(() => dashboardData.value?.pendingJoinRequests || [])
const pendingAdminJoinRequests = computed(() => dashboardData.value?.pendingAdminJoinRequests || [])
const totalOpenJoinRequests = computed(() => pendingJoinRequests.value.length + pendingAdminJoinRequests.value.length)
const hasOpenJoinRequests = computed(() => pendingJoinRequests.value.length > 0 || pendingAdminJoinRequests.value.length > 0)

const activeActivities = computed(() => {
  const statuses = ['draft', 'submitted', 'approved', 'packing', 'packed', 'issued', 'returned']
  return activitiesUpcoming.value.filter(a => statuses.includes(a.status))
})

const draftCount = computed(() => {
  return dashboardActivities.value.filter(a => a.status === 'draft').length
})
const submittedCount = computed(() => {
  return dashboardActivities.value.filter(a => a.status === 'submitted').length
})
const inProgressCount = computed(() => {
  return dashboardActivities.value.filter(a => ['approved', 'packing', 'packed'].includes(a.status)).length
})
const issuedCount = computed(() => {
  return dashboardActivities.value.filter(a => a.status === 'issued').length
})

const todayActivities = computed(() => {
  const today = new Date().toISOString().split('T')[0]
  return activitiesUpcoming.value.filter(a => {
    const start = a.planning_start || a.usage_start
    if (!start) return false
    const dateStr = start.split('T')[0]
    return dateStr === today
  })
})

const upcomingActivities = computed(() => {
  const statuses = ['submitted', 'approved', 'packing', 'packed', 'issued']
  const now = new Date()
  const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
  return activitiesUpcoming.value
    .filter(a => {
      if (!statuses.includes(a.status)) return false
      const start = a.usage_start || a.planning_start
      if (!start) return false
      const startDate = new Date(start)
      return startDate.getTime() >= todayStart
    })
    .sort((a, b) => {
      const aDate = a.usage_start || a.planning_start || ''
      const bDate = b.usage_start || b.planning_start || ''
      return aDate.localeCompare(bDate)
    })
})

// === Helpers ===
function getLink(path: string): string {
  const id = departmentId.value
  if (!id) {
    if (path === '/support-requests' || path === '/jobs') {
      return `/admin-dashboard/verwaltung${path}`
    }
    return '#'
  }
  if (hasSupportAdminRole.value && (path === '/jobs' || path === '/support-requests')) {
    return `/${id}/verwaltung${path}`
  }
  return `/${id}${path}`
}

function formatDate(d: Date): string {
  return d.toLocaleDateString('de-CH', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
}

function formatDateShort(iso?: string | null): string {
  if (!iso) return '–'
  return new Date(iso).toLocaleDateString('de-CH', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

function getRelativeDate(iso?: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  const diff = Math.ceil((d.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
  if (diff < 0) return '(vergangen)'
  if (diff === 0) return '(heute)'
  if (diff === 1) return '(morgen)'
  if (diff <= 7) return `(in ${diff} Tagen)`
  return ''
}

function getPlanningLabel(a: DashboardActivity): string {
  const start = a.planning_start || a.usage_start
  if (!start) return getStatusLabel(a.status)
  const d = new Date(start)
  const today = new Date()
  const isToday = d.toDateString() === today.toDateString()
  const timeStr = d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' })
  return isToday ? `Heute ${timeStr}` : formatDateShort(start) + ' ' + timeStr
}

function onDamageReportSuccess() {
  load()
}

function getStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    draft: 'Entwurf',
    submitted: 'Eingereicht',
    approved: 'Genehmigt',
    packing: 'Packen',
    packed: 'Gepackt',
    issued: 'Ausgegeben',
    returned: 'Zurück',
    completed: 'Abgeschlossen',
    cancelled: 'Storniert'
  }
  return labels[status] || status
}

// === Load ===
async function load() {
  const id = departmentId.value
  if (!id) {
    dashboardData.value = null
    if (hasSupportAdminRole.value) {
      isLoading.value = true
      try {
        const g = await getPendingAdminJoinRequests('')
        globalAdminPendingCount.value = g.length
      } catch (err) {
        console.error('Globale Admin-Anfragen laden fehlgeschlagen:', err)
      } finally {
        isLoading.value = false
      }
    } else {
      isLoading.value = false
    }
    return
  }
  isLoading.value = true
  try {
    dashboardData.value = await getDashboardData(id)
  } catch (err) {
    console.error('Dashboard laden fehlgeschlagen:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => load())
watch(departmentId, () => load())
</script>

<style scoped>
.dashboard {
  padding: 24px;
  max-width: 1200px;
}

.dashboard-header {
  margin-bottom: 24px;
}

.join-requests-above-actions {
  margin-bottom: 24px;
}

.dashboard-header h1 {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.welcome-text {
  color: #6b7280;
  font-size: 0.95rem;
  margin: 0;
}

.quick-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 32px;
}

.quick-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.95rem;
  text-decoration: none;
  color: #374151;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  transition: all 0.2s ease;
  cursor: pointer;
  font-family: inherit;
}

button.quick-action-btn {
  appearance: none;
}

.quick-action-btn:hover {
  background: #e5e7eb;
  border-color: #d1d5db;
  color: #1f2937;
}

.quick-action-btn.primary {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border-color: transparent;
}

.quick-action-btn.primary:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  color: white;
}

.dashboard-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px;
  color: #6b7280;
}

.dashboard-content {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.dashboard-section {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.section-title {
  font-size: 1rem;
  font-weight: 600;
  color: #374151;
  margin: 0 0 16px 0;
}

.section-title-link {
  color: inherit;
  text-decoration: none;
}

.section-title-link:hover {
  text-decoration: underline;
}

.activity-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.activity-list.compact .activity-card {
  padding: 10px 14px;
}

.activity-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: #f9fafb;
  border-radius: 8px;
  text-decoration: none;
  color: inherit;
  border: 1px solid transparent;
  transition: all 0.2s ease;
}

.activity-card:hover {
  background: #f3f4f6;
  border-color: #e5e7eb;
}

.status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.status-dot.draft { background: #9ca3af; }
.status-dot.submitted { background: #3b82f6; }
.status-dot.approved { background: #8b5cf6; }
.status-dot.packing { background: #f59e0b; }
.status-dot.packed { background: #f59e0b; }
.status-dot.issued { background: #10b981; }
.status-dot.returned { background: #06b6d4; }
.status-dot.completed { background: #6b7280; }

.activity-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.activity-name {
  font-weight: 500;
  color: #1f2937;
}

.activity-meta {
  font-size: 0.8rem;
  color: #6b7280;
}

.arrow {
  flex-shrink: 0;
  color: #9ca3af;
}

.widget-empty {
  padding: 24px;
  text-align: center;
  color: #6b7280;
}

.widget-empty .btn-link {
  display: inline-block;
  margin-top: 8px;
  color: #10b981;
  font-weight: 500;
  text-decoration: none;
}

.widget-empty .btn-link:hover {
  text-decoration: underline;
}

.stat-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.stat-cards.overview,
.stat-cards.workshop {
  margin-bottom: 12px;
}

.stat-card {
  padding: 16px 20px;
  border-radius: 10px;
  min-width: 100px;
  text-align: center;
}

.join-request-stat-link {
  text-decoration: none;
  color: inherit;
  border: 1px solid transparent;
}

.join-request-stat-link:hover {
  border-color: #93c5fd;
}

.stat-card .stat-value {
  display: block;
  font-size: 1.5rem;
  font-weight: 700;
  color: #1f2937;
}

.stat-card .stat-label {
  font-size: 0.8rem;
  color: #6b7280;
}

.stat-card.draft { background: #f3f4f6; }
.stat-card.submitted { background: #dbeafe; }
.stat-card.submitted .stat-value { color: #2563eb; }
.stat-card.approved { background: #ede9fe; }
.stat-card.approved .stat-value { color: #7c3aed; }
.stat-card.issued { background: #d1fae5; }
.stat-card.issued .stat-value { color: #059669; }
.stat-card.open { background: #fef3c7; }
.stat-card.open .stat-value { color: #d97706; }
.stat-card.in-progress { background: #dbeafe; }
.stat-card.in-progress .stat-value { color: #2563eb; }
.stat-card.waiting { background: #fce7f3; }
.stat-card.waiting .stat-value { color: #be185d; }
.stat-card.warning { background: #fef3c7; }
.stat-card.warning .stat-value { color: #b45309; }

.section-link {
  display: inline-block;
  margin-top: 8px;
  font-size: 0.9rem;
  color: #10b981;
  font-weight: 500;
  text-decoration: none;
}

.section-link:hover {
  text-decoration: underline;
}

.config-links {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.config-card {
  display: flex;
  flex-direction: column;
  padding: 16px 20px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: #fff;
  min-width: 180px;
  text-decoration: none;
  color: inherit;
  transition: all 0.2s;
}

.config-card:hover {
  border-color: #c7d2fe;
  background: #f8fafc;
}

.config-label {
  font-weight: 600;
  color: #1f2937;
}

.config-desc {
  font-size: 13px;
  color: #6b7280;
  margin-top: 4px;
}

.admin-global-shortcuts {
  margin-bottom: 0;
}

</style>
