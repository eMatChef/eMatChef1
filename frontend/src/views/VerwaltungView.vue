<template>
  <div class="verwaltung-view">
    <div class="verwaltung-container">
      <div
        class="verwaltung-menu-rail"
        :class="{ 'verwaltung-menu-rail--expanded': menuExpanded }"
      >
        <aside
          class="verwaltung-menu"
          :class="{ 'verwaltung-menu--collapsed': !menuExpanded }"
          @mouseenter="openMenu"
          @mouseleave="closeMenu"
        >
        <div class="verwaltung-menu-header">
          <h2 class="verwaltung-menu-title" :class="{ 'verwaltung-menu-title--collapsed': !menuExpanded }">
            {{ t('verwaltung.menuTitle') }}
          </h2>
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
            <span class="verwaltung-nav-item__icon-wrap" aria-hidden="true">
              <v-icon :icon="item.mdiIcon" class="nav-icon nav-icon--mdi" size="20" />
            </span>
            <span class="nav-label" :class="{ 'nav-label--collapsed': !menuExpanded }">{{ item.label }}</span>
          </router-link>
        </nav>
        </aside>
      </div>

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
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useHoverSubnav } from '@/composables/useHoverSubnav'
import { useAuthStore } from '@/stores/auth'
const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const { expanded: menuExpanded, open: openMenu, close: closeMenu, onNavClick: onMenuNavClick } =
  useHoverSubnav()

const isAdminDashboardRoute = computed(() => route.path.startsWith('/admin-dashboard'))

const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')

const isSuperAdminUser = computed(() => authStore.userRoles.includes('ROLE_SUPERADMIN'))

const canEditGlobalTemplates = computed(() =>
  authStore.userRoles.includes('ROLE_SUPERADMIN') ||
  authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
  authStore.userRoles.includes('ROLE_SUBORGCHEF')
)

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
  mdiIcon: string
  /** z. B. Superadmin im Department: globaler Benutzer-Bereich unter /admin-dashboard/… */
  to?: string
}

const visibleMenuItems = computed((): MenuItem[] => {
  const start: MenuItem[] = []
  if (canManageGlobalAddresses.value) {
    start.push({ id: 'global-addresses', label: t('verwaltung.nav.globalAddresses'), mdiIcon: 'mdi-earth' })
  }
  if (isSuperAdminUser.value) {
    start.push({
      id: 'supplier-global-review',
      label: t('verwaltung.nav.supplierGlobalReview'),
      mdiIcon: 'mdi-clipboard-check-outline',
      to: '/admin-dashboard/verwaltung/supplier-global-review',
    })
    start.push({
      id: 'js-leihkatalog',
      label: t('verwaltung.nav.jsLeihkatalog'),
      mdiIcon: 'mdi-tent',
      to: '/admin-dashboard/verwaltung/js-leihkatalog',
    })
  }
  const jobsItem: MenuItem = { id: 'jobs', label: t('verwaltung.nav.systemJobs'), mdiIcon: 'mdi-briefcase-outline' }
  const mid: MenuItem[] = canAssignSupport.value
    ? [{ id: 'support-requests', label: t('verwaltung.nav.supportRequests'), mdiIcon: 'mdi-lifebuoy' }]
    : []
  const core: MenuItem[] = [
    ...start,
    ...(canViewSystemJobs.value ? [jobsItem] : []),
    ...mid,
  ]

  if (isAdminDashboardRoute.value) {
    const sa: MenuItem[] = []
    if (canViewOrganisations.value) {
      sa.push({ id: 'organisations', label: t('verwaltung.nav.organisations'), mdiIcon: 'mdi-domain' })
    }
    if (canViewDepartments.value) {
      sa.push({ id: 'departments', label: t('verwaltung.nav.departments'), mdiIcon: 'mdi-office-building-outline' })
    }
    if (canManageGlobalUsers.value) {
      sa.push({ id: 'users', label: t('verwaltung.nav.users'), mdiIcon: 'mdi-account-group' })
    }
    if (isSuperAdminUser.value) {
      sa.push({
        id: 'global-admin-roles',
        label: t('verwaltung.nav.globalAdminRoles'),
        mdiIcon: 'mdi-shield-account',
      })
      sa.push({
        id: 'user-org-overview',
        label: t('verwaltung.nav.userOrgOverview'),
        mdiIcon: 'mdi-account-search',
        to: '/admin-dashboard/verwaltung/user-org-overview',
      })
    }
    const integrations: MenuItem = { id: 'integrations', label: t('verwaltung.nav.integrations'), mdiIcon: 'mdi-api' }
    const securityMonitoring: MenuItem = { id: 'security-monitoring', label: t('verwaltung.nav.securityMonitoring'), mdiIcon: 'mdi-shield-alert-outline' }
    const mail: MenuItem = { id: 'mail', label: t('verwaltung.nav.mail'), mdiIcon: 'mdi-email-outline' }
    const perm: MenuItem = { id: 'permissions', label: t('verwaltung.nav.permissions'), mdiIcon: 'mdi-lock-outline' }
    const materialTemplates: MenuItem = {
      id: 'templates',
      label: t('verwaltung.nav.materialTemplates'),
      mdiIcon: 'mdi-file-document-multiple-outline',
    }
    return [
      ...core,
      ...sa,
      ...(canEditGlobalTemplates.value ? [materialTemplates] : []),
      ...(canManageIntegrations.value ? [integrations] : []),
      ...(canViewSecurityMonitoring.value ? [securityMonitoring] : []),
      ...(canManageMail.value ? [mail] : []),
      perm,
    ]
  }

  const saUsersGlobal: MenuItem = {
    id: 'users-global',
    label: t('verwaltung.nav.users'),
    mdiIcon: 'mdi-account-group',
    to: '/admin-dashboard/verwaltung/users'
  }

  if (!canManageOrganisations.value) {
    return canManageGlobalUsers.value ? [...core, saUsersGlobal] : core
  }

  const orgItems: MenuItem[] = []
  if (canViewOrganisations.value) {
    orgItems.push({ id: 'organisations', label: t('verwaltung.nav.organisations'), mdiIcon: 'mdi-domain' })
  }
  if (canViewDepartments.value) {
    orgItems.push({ id: 'departments', label: t('verwaltung.nav.allDepartments'), mdiIcon: 'mdi-office-building-outline' })
  }
  if (canViewSecurityMonitoring.value) {
    orgItems.push({ id: 'security-monitoring', label: t('verwaltung.nav.securityMonitoring'), mdiIcon: 'mdi-shield-alert-outline' })
  }
  orgItems.push({ id: 'permissions', label: t('verwaltung.nav.permissions'), mdiIcon: 'mdi-lock-outline' })

  const items = [...core, ...orgItems]
  if (canManageMail.value) {
    items.push({ id: 'mail', label: t('verwaltung.nav.mail'), mdiIcon: 'mdi-email-outline' })
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
  overflow-x: hidden;
}

.verwaltung-container:has(.verwaltung-menu-rail--expanded) {
  overflow-x: visible;
}

/* Feste schmale Spalte — Hover klappt Panel nach rechts über den Inhalt */
.verwaltung-menu-rail {
  flex: 0 0 56px;
  width: 56px;
  max-width: 56px;
  position: relative;
  align-self: flex-start;
  z-index: 20;
  overflow-x: hidden;
}

.verwaltung-menu-rail--expanded {
  overflow: visible;
}

.verwaltung-menu {
  width: 56px;
  flex-shrink: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 16px 0 20px;
  height: fit-content;
  overflow: hidden;
}

.verwaltung-menu-rail--expanded .verwaltung-menu {
  width: 260px;
  position: absolute;
  left: 0;
  top: 0;
  z-index: 32;
  box-shadow:
    0 4px 6px rgba(15, 23, 42, 0.06),
    0 16px 40px rgba(15, 23, 42, 0.14);
  overflow-x: hidden;
  overflow-y: visible;
}

.verwaltung-menu-header {
  margin-bottom: 8px;
  padding: 0 12px;
  height: 36px;
  display: flex;
  align-items: flex-end;
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
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  opacity: 1;
  transition: opacity 0.15s ease;
}

.verwaltung-menu-title--collapsed {
  opacity: 0;
  pointer-events: none;
}

.verwaltung-nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.verwaltung-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 4px 12px 4px 8px;
  color: #64748b;
  text-decoration: none;
  transition: background-color 0.2s, color 0.2s, border-color 0.2s;
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
  padding: 4px 8px;
}

.verwaltung-nav-item__icon-wrap {
  flex: 0 0 40px;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.verwaltung-nav-item .nav-icon {
  width: 20px;
  height: 20px;
  margin: 0;
  flex-shrink: 0;
}

.verwaltung-nav-item .nav-icon--mdi {
  color: currentColor;
}

.verwaltung-nav-item .nav-label {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 14px;
  line-height: 1.25;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  opacity: 1;
  transition: opacity 0.15s ease;
}

.nav-label--collapsed {
  flex: 0 0 0;
  width: 0;
  opacity: 0;
  overflow: hidden;
  pointer-events: none;
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
