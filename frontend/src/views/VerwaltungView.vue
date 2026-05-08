<template>
  <div class="verwaltung-view">
    <div class="verwaltung-container">
      <aside class="verwaltung-menu">
        <h2 class="verwaltung-menu-title">{{ t('verwaltung.menuTitle') }}</h2>
        <nav class="verwaltung-nav">
          <router-link
            v-for="item in visibleMenuItems"
            :key="item.id"
            :to="resolveItemTo(item)"
            class="verwaltung-nav-item"
            :class="{ active: isActiveItem(item) }"
          >
            <component :is="item.icon" class="nav-icon" />
            <span class="nav-label">{{ item.label }}</span>
          </router-link>
        </nav>
      </aside>

      <main class="verwaltung-content">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, markRaw } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import IconSettings from '@/components/icons/IconSettings.vue'
import IconDashboard from '@/components/icons/IconDashboard.vue'
import IconJobs from '@/components/icons/IconJobs.vue'
import IconTasks from '@/components/icons/IconTasks.vue'
import IconEmployees from '@/components/icons/IconEmployees.vue'
import IconContacts from '@/components/icons/IconContacts.vue'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()

const isAdminDashboardRoute = computed(() => route.path.startsWith('/admin-dashboard'))

const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')

const isSuperAdminUser = computed(() => authStore.userRoles.includes('ROLE_SUPERADMIN'))

const canManageOrganisations = computed(() => {
  const role = authStore.currentDepartmentRole
  if (role) {
    const r = String(role).toLowerCase().trim()
    if (['sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(r)) return true
  }
  const userRoles = authStore.userRoles || []
  return (
    userRoles.includes('ROLE_SUPERADMIN') ||
    userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
    userRoles.includes('ROLE_SUBORGCHEF')
  )
})

function getVerwaltungLink(suffix: string): string {
  if (isAdminDashboardRoute.value) return `/admin-dashboard/verwaltung${suffix}`
  if (!departmentId.value) return '#'
  return `/${departmentId.value}/verwaltung${suffix}`
}

function resolveItemTo(item: MenuItem): string {
  if (item.to) return item.to
  if (item.id === 'global-addresses') return getVerwaltungLink('')
  if (item.id === 'mail') return getVerwaltungLink('/mail/versand')
  if (item.id === 'security-monitoring') return getVerwaltungLink('/security-monitoring')
  return getVerwaltungLink(`/${item.id}`)
}

function isActiveItem(item: MenuItem): boolean {
  const target = resolveItemTo(item)
  const p = route.path.replace(/\/$/, '') || '/'
  const t = target.replace(/\/$/, '') || '/'
  // Root „Globale Adressen“ = …/verwaltung ohne weiteres Segment — kein startsWith, sonst wäre z. B. …/users immer mit aktiv
  if (item.id === 'global-addresses') {
    return p === t
  }
  if (item.id === 'mail') {
    const root = getVerwaltungLink('/mail').replace(/\/$/, '') || '/'
    return p === root || p.startsWith(`${root}/`)
  }
  return p === t || p.startsWith(`${t}/`)
}

type MenuItem = {
  id: string
  label: string
  icon: typeof IconDashboard
  /** z. B. Superadmin im Department: globaler Benutzer-Bereich unter /admin-dashboard/… */
  to?: string
}

const visibleMenuItems = computed((): MenuItem[] => {
  const start: MenuItem[] = [{ id: 'global-addresses', label: t('verwaltung.nav.globalAddresses'), icon: markRaw(IconContacts) }]
  const jobsItem: MenuItem = { id: 'jobs', label: t('verwaltung.nav.systemJobs'), icon: markRaw(IconJobs) }
  const mid: MenuItem[] = [{ id: 'support-requests', label: t('verwaltung.nav.supportRequests'), icon: markRaw(IconTasks) }]
  const core: MenuItem[] = isSuperAdminUser.value ? [...start, jobsItem, ...mid] : [...start, ...mid]

  if (isAdminDashboardRoute.value) {
    const sa: MenuItem[] = [
      { id: 'organisations', label: t('verwaltung.nav.organisations'), icon: markRaw(IconDashboard) },
      { id: 'departments', label: t('verwaltung.nav.departments'), icon: markRaw(IconDashboard) },
      { id: 'users', label: t('verwaltung.nav.users'), icon: markRaw(IconEmployees) }
    ]
    const integrations: MenuItem = { id: 'integrations', label: t('verwaltung.nav.integrations'), icon: markRaw(IconSettings) }
    const securityMonitoring: MenuItem = { id: 'security-monitoring', label: t('verwaltung.nav.securityMonitoring'), icon: markRaw(IconSettings) }
    const mail: MenuItem = { id: 'mail', label: t('verwaltung.nav.mail'), icon: markRaw(IconSettings) }
    const perm: MenuItem = { id: 'permissions', label: t('verwaltung.nav.permissions'), icon: markRaw(IconSettings) }
    return [
      ...core,
      ...(isSuperAdminUser.value ? sa : []),
      ...(isSuperAdminUser.value ? [integrations] : []),
      ...(canManageOrganisations.value ? [securityMonitoring] : []),
      ...(isSuperAdminUser.value ? [mail] : []),
      perm,
    ]
  }

  const saUsersGlobal: MenuItem = {
    id: 'users-global',
    label: t('verwaltung.nav.users'),
    icon: markRaw(IconEmployees),
    to: '/admin-dashboard/verwaltung/users'
  }

  if (!canManageOrganisations.value) {
    return isSuperAdminUser.value ? [...core, saUsersGlobal] : core
  }

  const orgItems: MenuItem[] = [
    { id: 'organisations', label: t('verwaltung.nav.organisations'), icon: markRaw(IconDashboard) },
    { id: 'departments', label: t('verwaltung.nav.allDepartments'), icon: markRaw(IconDashboard) },
    { id: 'security-monitoring', label: t('verwaltung.nav.securityMonitoring'), icon: markRaw(IconSettings) },
    { id: 'permissions', label: t('verwaltung.nav.permissions'), icon: markRaw(IconSettings) }
  ]
  const mailItem: MenuItem = { id: 'mail', label: t('verwaltung.nav.mail'), icon: markRaw(IconSettings) }
  return isSuperAdminUser.value ? [...core, ...orgItems, mailItem, saUsersGlobal] : [...core, ...orgItems]
})
</script>

<style scoped>
.verwaltung-view {
  padding: 24px;
  height: 100%;
}

.verwaltung-container {
  display: flex;
  gap: 24px;
  height: 100%;
  max-width: 1400px;
  margin: 0 auto;
}

.verwaltung-menu {
  width: 260px;
  flex-shrink: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 16px 0 20px;
  height: fit-content;
}

.verwaltung-menu-title {
  margin: 0 20px 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
}

.verwaltung-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.verwaltung-nav-item {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  color: #64748b;
  text-decoration: none;
  transition: all 0.2s;
  border-left: 3px solid transparent;
}

.verwaltung-nav-item:hover {
  background-color: #f1f5f9;
  color: #334155;
}

.verwaltung-nav-item.active {
  background-color: #eff6ff;
  color: #2563eb;
  border-left-color: #2563eb;
  font-weight: 500;
}

.verwaltung-nav-item .nav-icon {
  width: 20px;
  height: 20px;
  margin-right: 12px;
  flex-shrink: 0;
}

.verwaltung-nav-item .nav-label {
  font-size: 14px;
}

.verwaltung-content {
  flex: 1;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 24px;
  min-height: 600px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
