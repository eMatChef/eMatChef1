<template>
  <PageShell v-if="isGrossanlassDept" class="grossanlass-dashboard">
    <template #title>
      <span class="grossanlass-dashboard__title">
        {{ grossanlassDeptName }}
        <span v-if="grossanlassStatus === 'draft'" class="status-label draft">
          {{ t('grossanlass.dashboard.draftBadge') }}
        </span>
      </span>
    </template>
    <template v-if="grossanlassPeriodLabel" #subtitle>
      <span class="period-label-row">
        <v-icon icon="mdi-calendar-range" size="18" class="grossanlass-dashboard__period-icon" />
        <span class="period-label">{{ grossanlassPeriodLabel }}</span>
      </span>
    </template>

    <GrossanlassDashboardPanel v-if="departmentId" :key="departmentId" :department-id="departmentId" />
  </PageShell>

  <div v-else class="dashboard">
    <!-- Header -->
    <header class="dashboard-header">
      <div class="header-content">
        <h1>{{ t('dashboard.title') }}</h1>
        <p class="welcome-text">
          {{ t('dashboard.welcome', { name: dashboardDisplayName, date: formatDate(new Date()) }) }}
        </p>
      </div>
    </header>

    <!-- Schnellaktionen (nur mit Department) -->
    <div v-if="departmentId" class="quick-actions">
      <router-link
        v-if="showMaterialCreate"
        :to="{ path: getLink('/materials'), query: { new: '1', from: 'dashboard' } }"
        class="quick-action-btn primary"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
          <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
        <span>{{ t('dashboard.createMaterial') }}</span>
      </router-link>
      <router-link
        v-if="showCreateActivity"
        :to="{ path: getLink('/activities'), query: { new: '1', from: 'dashboard' } }"
        class="quick-action-btn primary"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24" aria-hidden="true">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
        <span>{{ t('dashboard.createActivity') }}</span>
      </router-link>
      <EButton
        variant="secondary"
        class="quick-action-btn"
        @click="showDamageWizard = true"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
          <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
        </svg>
        {{ t('dashboard.reportDamage') }}
      </EButton>
    </div>

    <!-- Loading -->
    <ELoadingState
      v-if="isLoading"
      variant="page"
      :message="t('dashboard.loading')"
    />

    <!-- Content -->
    <div v-else class="dashboard-content">
      <!-- Superadmin: globale Verwaltungs-Shortcuts -->
      <section v-if="isSuperAdmin" class="dashboard-section admin-global-shortcuts">
        <h2 class="section-title">{{ t('dashboard.orgAndDepartments') }}</h2>
        <div class="config-links">
          <router-link to="/admin-dashboard/verwaltung/organisations" class="config-card">
            <span class="config-label">{{ t('dashboard.organisations') }}</span>
            <span class="config-desc">{{ t('dashboard.organisationsDesc') }}</span>
          </router-link>
          <router-link to="/admin-dashboard/verwaltung/departments" class="config-card">
            <span class="config-label">{{ t('dashboard.departments') }}</span>
            <span class="config-desc">{{ t('dashboard.departmentsDesc') }}</span>
          </router-link>
        </div>
      </section>

      <!-- Nur ohne Department: globale Admin-Support-Zahlen -->
      <section v-if="!departmentId && hasSupportAdminRole" class="dashboard-section">
        <h2 class="section-title">
          <router-link to="/admin-dashboard/verwaltung/support-requests" class="section-title-link">
            {{ t('dashboard.supportRequests') }}
          </router-link>
        </h2>
        <div class="stat-cards">
          <router-link
            to="/admin-dashboard/verwaltung/support-requests"
            class="stat-card submitted join-request-stat-link"
          >
            <span class="stat-value">{{ globalAdminPendingCount }}</span>
            <span class="stat-label">{{ t('dashboard.openAdminRequests') }}</span>
          </router-link>
        </div>
        <router-link to="/admin-dashboard/verwaltung/support-requests" class="section-link">{{ t('dashboard.toSupportRequests') }}</router-link>
      </section>

      <template v-if="departmentId">
      <!-- User / L1-L3: Aktive Aktivitäten -->
      <section v-if="showActiveActivities" class="dashboard-section">
        <h2 class="section-title">{{ t('dashboard.myActiveActivities') }}</h2>
        <div v-if="activeActivities.length === 0" class="widget-empty">
          <p>{{ t('dashboard.noActiveActivities') }}</p>
          <router-link :to="getLink('/activities')" class="btn-link">{{ t('dashboard.showActivities') }}</router-link>
        </div>
        <div v-else class="activity-list">
          <router-link
            v-for="a in activeActivities.slice(0, 5)"
            :key="a.id"
            :to="getLink(`/activities/${a.id}`)"
            class="activity-card"
          >
            <span class="status-dot" :class="activityStatusClass(a.status)"></span>
            <div class="activity-info">
              <span class="activity-name">{{ a.name }}</span>
              <span class="activity-meta">
                {{ formatDateShort(a.usage_start) }}
                <template v-if="a.group_name"> · {{ a.group_name }}</template>
                · {{ getStatusLabel(a.status) }}
              </span>
            </div>
            <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </router-link>
        </div>
        <router-link v-if="activeActivities.length > 0" :to="getLink('/activities')" class="section-link">
          {{ t('dashboard.showAllActivities') }}
        </router-link>
      </section>

      <!-- L1-L3: Entwürfe -->
      <section v-if="showDraftsWidget && draftCount > 0" class="dashboard-section">
        <h2 class="section-title">{{ t('dashboard.draftsForReview') }}</h2>
        <div class="stat-cards">
          <div class="stat-card draft">
            <span class="stat-value">{{ draftCount }}</span>
            <span class="stat-label">{{ t('dashboard.drafts') }}</span>
          </div>
          <div class="stat-card submitted">
            <span class="stat-value">{{ submittedCount }}</span>
            <span class="stat-label">{{ t('dashboard.submitted') }}</span>
          </div>
        </div>
        <router-link :to="getLink('/activities')" class="section-link">{{ t('dashboard.reviewActivities') }}</router-link>
      </section>

      <!-- DC / MW: Übersicht -->
      <section v-if="showOverviewWidget" class="dashboard-section">
        <h2 class="section-title">{{ t('dashboard.activitiesOverview') }}</h2>
        <div class="stat-cards overview">
          <div class="stat-card draft">
            <span class="stat-value">{{ draftCount }}</span>
            <span class="stat-label">{{ t('dashboard.drafts') }}</span>
          </div>
          <div class="stat-card submitted">
            <span class="stat-value">{{ submittedCount }}</span>
            <span class="stat-label">{{ t('dashboard.submitted') }}</span>
          </div>
          <div class="stat-card approved">
            <span class="stat-value">{{ inProgressCount }}</span>
            <span class="stat-label">{{ t('dashboard.inProgress') }}</span>
          </div>
          <div class="stat-card issued">
            <span class="stat-value">{{ issuedCount }}</span>
            <span class="stat-label">{{ t('dashboard.issued') }}</span>
          </div>
        </div>
      </section>

      <!-- DC / MW: Werkstatt -->
      <section v-if="showWorkshopWidget && workshopStats" class="dashboard-section">
        <h2 class="section-title">{{ t('dashboard.workshop') }}</h2>
        <div class="stat-cards workshop">
          <div class="stat-card open">
            <span class="stat-value">{{ workshopStats.phase_counts?.triage || 0 }}</span>
            <span class="stat-label">{{ t('workshop.phase.triage') }}</span>
          </div>
          <div class="stat-card in-progress">
            <span class="stat-value">{{ workshopStats.phase_counts?.in_progress || 0 }}</span>
            <span class="stat-label">{{ t('workshop.phase.in_progress') }}</span>
          </div>
          <div class="stat-card waiting">
            <span class="stat-value">{{ workshopStats.phase_counts?.awaiting_quote || 0 }}</span>
            <span class="stat-label">{{ t('workshop.phase.awaiting_quote') }}</span>
          </div>
          <router-link
            class="stat-card warning"
            :to="{ path: getLink('/workshop'), query: { qf: 'waiting_quote' } }"
            style="text-decoration:none; color:inherit;"
            :title="t('dashboard.tooltipQuotesOpen')"
          >
            <span class="stat-value">{{ workshopStats.pending_cost_tasks?.waiting_quote || 0 }}</span>
            <span class="stat-label">{{ t('dashboard.quotesOpen') }}</span>
          </router-link>
          <router-link
            class="stat-card warning"
            :to="{ path: getLink('/workshop'), query: { qf: 'missing_estimated_cost' } }"
            style="text-decoration:none; color:inherit;"
            :title="t('dashboard.tooltipMissingPrice')"
          >
            <span class="stat-value">{{ workshopStats.pending_cost_tasks?.missing_estimated_cost || 0 }}</span>
            <span class="stat-label">{{ t('dashboard.priceMissing') }}</span>
          </router-link>
        </div>
        <router-link :to="getLink('/workshop')" class="section-link">{{ t('dashboard.toWorkshop') }}</router-link>
      </section>

      <!-- Infoscreen (MW / DC) -->
      <section v-if="showDisplayLink" class="dashboard-section">
        <h2 class="section-title">{{ t('display.title') }}</h2>
        <p class="display-dashboard-hint">{{ t('display.subtitle') }}</p>
        <router-link :to="getLink('/settings/my-department/display-screens')" class="section-link">
          {{ t('dashboard.toDisplay') }}
        </router-link>
      </section>

      <!-- Offene Beitrittsanfragen (MW/DC) -->
      <section
        v-if="canManageDepartmentJoinRequests && !hasSupportAdminRole"
        class="dashboard-section"
      >
        <h2 class="section-title">
          <router-link :to="getLink('/settings/users')" class="section-title-link">
            {{ t('dashboard.openDepartmentJoinRequests') }}
          </router-link>
        </h2>
        <div class="stat-cards">
          <router-link :to="getLink('/settings/users')" class="stat-card submitted join-request-stat-link">
            <span class="stat-value">{{ pendingJoinRequests.length }}</span>
            <span class="stat-label">{{ t('dashboard.openDepartmentJoinRequestsCount') }}</span>
          </router-link>
        </div>
        <router-link :to="getLink('/settings/users')" class="section-link">{{ t('dashboard.toDepartmentUsers') }}</router-link>
      </section>

      <!-- Offene Support-/Admin-Anfragen (SA/OrgChef/SubOrgChef) -->
      <section v-if="hasSupportAdminRole" class="dashboard-section">
        <h2 class="section-title">
          <router-link :to="getLink('/support-requests')" class="section-title-link">
            {{ t('dashboard.openJoinRequests') }}
          </router-link>
        </h2>
        <div class="stat-cards">
          <router-link :to="getLink('/support-requests')" class="stat-card submitted join-request-stat-link">
            <span class="stat-value">{{ totalOpenJoinRequests }}</span>
            <span class="stat-label">{{ t('dashboard.openTotal') }}</span>
          </router-link>
          <div v-if="showAdminJoinRequestsWidget" class="stat-card draft">
            <span class="stat-value">{{ pendingAdminJoinRequests.length }}</span>
            <span class="stat-label">{{ t('dashboard.adminRequests') }}</span>
          </div>
        </div>
        <router-link :to="getLink('/support-requests')" class="section-link">{{ t('dashboard.toSupportRequests') }}</router-link>
      </section>

      <!-- MW: Pack-Queue heute -->
      <section v-if="showPackQueueWidget && todayActivities.length > 0" class="dashboard-section">
        <h2 class="section-title">{{ t('dashboard.relevantToday') }}</h2>
        <div class="activity-list">
          <router-link
            v-for="a in todayActivities.slice(0, 5)"
            :key="a.id"
            :to="getLink(`/activities/${a.id}`)"
            class="activity-card"
          >
            <span class="status-dot" :class="activityStatusClass(a.status)"></span>
            <div class="activity-info">
              <span class="activity-name">{{ a.name }}</span>
              <span class="activity-meta">{{ getPlanningLabel(a) }}</span>
            </div>
            <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <polyline points="9 18 15 12 9 6"/>
            </svg>
          </router-link>
        </div>
        <router-link :to="getLink('/activities')" class="section-link">{{ t('dashboard.allActivitiesArrow') }}</router-link>
      </section>

      <!-- Kommende Termine (alle Rollen) -->
      <section v-if="upcomingActivities.length > 0" class="dashboard-section">
        <h2 class="section-title">{{ t('dashboard.upcomingDates') }}</h2>
        <div class="activity-list compact">
          <router-link
            v-for="a in upcomingActivities.slice(0, 5)"
            :key="a.id"
            :to="getLink(`/activities/${a.id}`)"
            class="activity-card compact"
          >
            <span class="status-dot" :class="activityStatusClass(a.status)"></span>
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
import { ref, computed, onMounted, onActivated, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import { getDashboardData, type DashboardActivity } from '@/api/dashboard'
import { getPendingAdminJoinRequests } from '@/api/joinRequests'
import DamageReportWizard from '@/components/DamageReportWizard.vue'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'
import { useDepartmentLiveRefresh } from '@/composables/useDepartmentLiveRefresh'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import PageShell from '@/components/layout/PageShell.vue'
import GrossanlassDashboardPanel from '@/components/grossanlass/GrossanlassDashboardPanel.vue'
import { EButton } from '@/components/form/base'
import { formatPeriodCompact } from '@/utils/formatPeriod'

const route = useRoute()
const { t, locale } = useI18n()
const authStore = useAuthStore()
const headerNotificationsStore = useHeaderNotificationsStore()

/** BCP-47-Tag für `Intl` passend zur UI-Sprache (CH-Kontext). */
const intlLocale = computed(() => {
  const loc = String(locale.value)
  const map: Record<string, string> = {
    de: 'de-CH',
    'de-pfadi': 'de-CH',
    'de-cevi': 'de-CH',
    en: 'en-GB',
    fr: 'fr-CH',
    it: 'it-CH',
    'ch-rm': 'rm-CH'
  }
  return map[loc] ?? 'de-CH'
})
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

const activeMembership = computed(() => {
  if (!departmentId.value) return null
  return authStore.departments.find((d) => d.department_id === departmentId.value) ?? null
})

const isGrossanlassDept = computed(() => Boolean(activeMembership.value?.department?.is_grossanlass))

const grossanlassDeptName = computed(
  () => activeMembership.value?.department?.name || t('dashboard.title'),
)

const grossanlassStatus = computed(
  () => activeMembership.value?.department?.grossanlass_config?.status || 'draft',
)
const showDamageWizard = ref(false)

// === State ===
const isLoading = ref(true)
const dashboardData = ref<Awaited<ReturnType<typeof getDashboardData>> | null>(null)
const globalAdminPendingCount = ref(0)

// === Role helpers ===
const role = computed(() => (authStore.currentDepartmentRole || 'u').toLowerCase())
const isSuperAdmin = computed(() => authStore.userRoles.includes('ROLE_SUPERADMIN'))
const { isUserRole } = useDepartmentMemberRole()
const DC_ROLES = ['dc']
const MW_DASHBOARD_ROLES = ['mw']
const hasSupportAdminRole = computed(() =>
  authStore.userRoles.includes('ROLE_SUPERADMIN') ||
  authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
  authStore.userRoles.includes('ROLE_SUBORGCHEF')
)

const showMaterialCreate = computed(() => MW_DASHBOARD_ROLES.includes(role.value))
const showCreateActivity = computed(() =>
  isUserRole.value ||
  DC_ROLES.includes(role.value) ||
  MW_DASHBOARD_ROLES.includes(role.value)
)
const showActiveActivities = computed(() => isUserRole.value)
const showDraftsWidget = computed(() => false)
const showOverviewWidget = computed(() => DC_ROLES.includes(role.value) || MW_DASHBOARD_ROLES.includes(role.value))
const showWorkshopWidget = computed(() => DC_ROLES.includes(role.value) || MW_DASHBOARD_ROLES.includes(role.value))
const showDisplayLink = computed(() => DC_ROLES.includes(role.value) || MW_DASHBOARD_ROLES.includes(role.value))
const showPackQueueWidget = computed(() => MW_DASHBOARD_ROLES.includes(role.value))
/** Join-Requests nur für globale Profil-Rollen SA/OrgChef/SubOrgChef — nicht für reine Abteilungsrollen (mw/dc/…). */
const showAdminJoinRequestsWidget = computed(() => hasSupportAdminRole.value)
const canManageDepartmentJoinRequests = computed(() => ['mw', 'dc'].includes(role.value))

// === Data ===
const activitiesUpcoming = computed(() => dashboardData.value?.activitiesUpcoming || [])
const dashboardActivities = computed(() => dashboardData.value?.activities || [])
const workshopStats = computed(() => dashboardData.value?.workshopStats || null)
const pendingJoinRequests = computed(() => dashboardData.value?.pendingJoinRequests || [])
const pendingAdminJoinRequests = computed(() => dashboardData.value?.pendingAdminJoinRequests || [])
const totalOpenJoinRequests = computed(() => pendingJoinRequests.value.length + pendingAdminJoinRequests.value.length)
const hasOpenJoinRequests = computed(() => pendingJoinRequests.value.length > 0 || pendingAdminJoinRequests.value.length > 0)

const activeActivities = computed(() => {
  const statuses = ['draft', 'submitted', 'approved', 'packing', 'packed', 'at_event', 'returned']
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
  return dashboardActivities.value.filter(a => a.status === 'at_event').length
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
  const statuses = ['submitted', 'approved', 'packing', 'packed', 'at_event']
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
  return d.toLocaleDateString(intlLocale.value, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
}

function formatDateShort(iso?: string | null): string {
  if (!iso) return t('dashboard.datePlaceholder')
  return new Date(iso).toLocaleDateString(intlLocale.value, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

const grossanlassPeriodLabel = computed(() => {
  const cfg = activeMembership.value?.department?.grossanlass_config
  if (!cfg?.planned_event_start) return ''
  return formatPeriodCompact(cfg.planned_event_start, cfg.planned_event_end)
})

function getRelativeDate(iso?: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  const now = new Date()
  const diff = Math.ceil((d.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
  if (diff < 0) return t('dashboard.relative.past')
  if (diff === 0) return t('dashboard.relative.today')
  if (diff === 1) return t('dashboard.relative.tomorrow')
  if (diff <= 7) return t('dashboard.relative.inDays', { count: diff })
  return ''
}

function getPlanningLabel(a: DashboardActivity): string {
  const start = a.planning_start || a.usage_start
  if (!start) return getStatusLabel(a.status)
  const d = new Date(start)
  const today = new Date()
  const isToday = d.toDateString() === today.toDateString()
  const timeStr = d.toLocaleTimeString(intlLocale.value, { hour: '2-digit', minute: '2-digit' })
  return isToday
    ? t('dashboard.todayWithTime', { time: timeStr })
    : formatDateShort(start) + ' ' + timeStr
}

function onDamageReportSuccess() {
  load()
}

function getStatusLabel(status: string): string {
  const key = `dashboard.status.${activityStatusI18nKey(status)}`
  const translated = t(key)
  return translated === key ? status : translated
}

// === Load ===
async function load(opts?: { silent?: boolean }) {
  const id = departmentId.value
  if (isGrossanlassDept.value) {
    dashboardData.value = null
    isLoading.value = false
    return
  }
  if (!id) {
    dashboardData.value = null
    if (hasSupportAdminRole.value) {
      if (!opts?.silent) isLoading.value = true
      try {
        const g = await getPendingAdminJoinRequests('')
        globalAdminPendingCount.value = g.length
      } catch (err) {
        console.error(t('dashboard.errors.loadGlobalAdmin'), err)
      } finally {
        isLoading.value = false
      }
    } else {
      isLoading.value = false
    }
    return
  }
  if (!opts?.silent) isLoading.value = true
  try {
    dashboardData.value = await getDashboardData(id, {
      includeDepartmentJoinRequests: canManageDepartmentJoinRequests.value || hasSupportAdminRole.value,
      includeAdminJoinRequests: hasSupportAdminRole.value,
    })
  } catch (err) {
    console.error(t('dashboard.errors.load'), err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => load())
onActivated(() => {
  if (departmentId.value) void load({ silent: true })
})
watch(departmentId, () => load())

/** Nach Aktivitäts-Anlage (Wizard) oder Rückkehr von Aktivitäten — ohne F5. */
watch(
  () => headerNotificationsStore.refreshNonce,
  () => {
    if (departmentId.value) void load({ silent: true })
  },
)

watch(
  () => route.name,
  (name, prevName) => {
    if (name !== 'Dashboard' || !departmentId.value) return
    if (prevName && prevName !== 'Dashboard') void load({ silent: true })
  },
)

/** Andere User: Dashboard-Widgets alle 30s (sichtbarer Tab). */
useDepartmentLiveRefresh({
  departmentId,
  enabled: () => route.name === 'Dashboard' && !isGrossanlassDept.value,
  reload: load,
  isBusy: () => isLoading.value && !dashboardData.value,
})
</script>

<style scoped>
.dashboard {
  padding: 24px;
  max-width: 1200px;
}

.dashboard-header {
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

@media (max-width: 599px) {
  .dashboard-header .header-content {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }

  .welcome-text {
    width: 100%;
  }
}

.grossanlass-dashboard__title {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.grossanlass-dashboard__period-icon {
  color: var(--color-primary, #059669);
  opacity: 0.9;
}

.grossanlass-dashboard__welcome-card {
  margin-bottom: 0;
}

.grossanlass-dashboard__welcome {
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.55;
  max-width: 42rem;
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

.display-dashboard-hint {
  margin: 0 0 4px;
  font-size: 0.9rem;
  color: #6b7280;
  max-width: 42rem;
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
