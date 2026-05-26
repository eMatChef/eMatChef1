<template>
  <div class="verwaltung-view">
    <div class="verwaltung-container">
      <aside
        class="verwaltung-menu"
        :class="{ 'verwaltung-menu--collapsed': !menuExpanded }"
        @mouseenter="openMenu"
        @mouseleave="closeMenu"
      >
        <div class="verwaltung-menu-header">
          <h2 v-show="menuExpanded" class="verwaltung-menu-title">{{ t('verwaltung.menuTitle') }}</h2>
        </div>
        <nav class="verwaltung-nav">
          <router-link
            v-for="item in visibleMenuItems"
            :key="item.id"
            :to="resolveItemTo(item)"
            class="verwaltung-nav-item"
            :class="{ active: isActiveItem(item) }"
            :title="!menuExpanded ? item.label : undefined"
            @click="onMenuNavClick"
          >
            <component :is="item.icon" class="nav-icon" />
            <span v-show="menuExpanded" class="nav-label">{{ item.label }}</span>
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
import { useHoverSubnav } from '@/composables/useHoverSubnav'
import { useAuthStore } from '@/stores/auth'
import { IconSettings, IconDashboard, IconJobs, IconTasks, IconEmployees, IconContacts } from '@/components/icons'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const { expanded: menuExpanded, open: openMenu, close: closeMenu, onNavClick: onMenuNavClick } =
  useHoverSubnav()

const isAdminDashboardRoute = computed(() => route.path.startsWith('/admin-dashboard'))

const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')

const isSuperAdminUser = computed(() => authStore.userRoles.includes('ROLE_SUPERADMIN'))

const canManageOrganisations = computed(() => authStore.hasGlobalAdminAccess())

const canViewOrganisations = computed(() => authStore.canAdmin('organisations.view'))
const canViewDepartments = computed(() => authStore.canAdmin('departments.view'))
const canAssignSupport = computed(() => authStore.canAdmin('support_requests.assign'))
const canManageGlobalUsers = computed(() => authStore.canAdmin('users.global_manage'))
const canViewSecurityMonitoring = computed(() => authStore.canAdmin('security_monitoring.view'))
const canViewSystemJobs = computed(() => authStore.canAdmin('system_jobs.view'))
const canManageGlobalAddresses = computed(() => authStore.canAdmin('global_addresses.manage'))
const canManageMail = computed(() => authStore.canAdmin('mail.settings'))
const canManageIntegrations = computed(() => authStore.canAdmin('integrations.manage'))

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
  const start: MenuItem[] = canManageGlobalAddresses.value
    ? [{ id: 'global-addresses', label: t('verwaltung.nav.globalAddresses'), icon: markRaw(IconContacts) }]
    : []
  const jobsItem: MenuItem = { id: 'jobs', label: t('verwaltung.nav.systemJobs'), icon: markRaw(IconJobs) }
  const mid: MenuItem[] = canAssignSupport.value
    ? [{ id: 'support-requests', label: t('verwaltung.nav.supportRequests'), icon: markRaw(IconTasks) }]
    : []
  const core: MenuItem[] = [
    ...start,
    ...(canViewSystemJobs.value ? [jobsItem] : []),
    ...mid,
  ]

  if (isAdminDashboardRoute.value) {
    const sa: MenuItem[] = []
    if (canViewOrganisations.value) {
      sa.push({ id: 'organisations', label: t('verwaltung.nav.organisations'), icon: markRaw(IconDashboard) })
    }
    if (canViewDepartments.value) {
      sa.push({ id: 'departments', label: t('verwaltung.nav.departments'), icon: markRaw(IconDashboard) })
    }
    if (canManageGlobalUsers.value) {
      sa.push({ id: 'users', label: t('verwaltung.nav.users'), icon: markRaw(IconEmployees) })
    }
    if (isSuperAdminUser.value) {
      sa.push({
        id: 'global-admin-roles',
        label: t('verwaltung.nav.globalAdminRoles'),
        icon: markRaw(IconEmployees),
      })
      sa.push({
        id: 'user-org-overview',
        label: t('verwaltung.nav.userOrgOverview'),
        icon: markRaw(IconEmployees),
        to: '/admin-dashboard/verwaltung/user-org-overview',
      })
    }
    const integrations: MenuItem = { id: 'integrations', label: t('verwaltung.nav.integrations'), icon: markRaw(IconSettings) }
    const securityMonitoring: MenuItem = { id: 'security-monitoring', label: t('verwaltung.nav.securityMonitoring'), icon: markRaw(IconSettings) }
    const mail: MenuItem = { id: 'mail', label: t('verwaltung.nav.mail'), icon: markRaw(IconSettings) }
    const perm: MenuItem = { id: 'permissions', label: t('verwaltung.nav.permissions'), icon: markRaw(IconSettings) }
    return [
      ...core,
      ...sa,
      ...(canManageIntegrations.value ? [integrations] : []),
      ...(canViewSecurityMonitoring.value ? [securityMonitoring] : []),
      ...(canManageMail.value ? [mail] : []),
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
    return canManageGlobalUsers.value ? [...core, saUsersGlobal] : core
  }

  const orgItems: MenuItem[] = []
  if (canViewOrganisations.value) {
    orgItems.push({ id: 'organisations', label: t('verwaltung.nav.organisations'), icon: markRaw(IconDashboard) })
  }
  if (canViewDepartments.value) {
    orgItems.push({ id: 'departments', label: t('verwaltung.nav.allDepartments'), icon: markRaw(IconDashboard) })
  }
  if (canViewSecurityMonitoring.value) {
    orgItems.push({ id: 'security-monitoring', label: t('verwaltung.nav.securityMonitoring'), icon: markRaw(IconSettings) })
  }
  orgItems.push({ id: 'permissions', label: t('verwaltung.nav.permissions'), icon: markRaw(IconSettings) })

  const items = [...core, ...orgItems]
  if (canManageMail.value) {
    items.push({ id: 'mail', label: t('verwaltung.nav.mail'), icon: markRaw(IconSettings) })
  }
  if (canManageGlobalUsers.value) {
    items.push(saUsersGlobal)
  }
  return items
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
  max-width: none;
  margin: 0;
}

.verwaltung-menu {
  width: 260px;
  flex-shrink: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 16px 0 20px;
  height: fit-content;
  transition: width 0.2s ease;
}

.verwaltung-menu--collapsed {
  width: 56px;
  padding-top: 12px;
}

.verwaltung-menu-header {
  margin-bottom: 8px;
  padding: 0 12px;
  min-height: 1.5rem;
}

.verwaltung-menu--collapsed .verwaltung-menu-header {
  padding: 0 8px;
}

.verwaltung-menu-title {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  flex: 1;
  min-width: 0;
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

.verwaltung-menu--collapsed .verwaltung-nav-item {
  justify-content: center;
  padding: 12px 8px;
  border-left-width: 3px;
}

.verwaltung-menu--collapsed .verwaltung-nav-item .nav-icon {
  margin-right: 0;
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
