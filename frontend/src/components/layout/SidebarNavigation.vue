<template>
  <aside class="sidebar" @mouseenter="isHovered = true" @mouseleave="isHovered = false">
    <!-- Logo -->
    <div class="sidebar-logo">
      <router-link :to="homeLink" class="logo-link">
        <EmcLogoMark size="sm" />
        <span class="logo-text" :class="{ visible: isHovered }">eMatChef</span>
      </router-link>
    </div>
    
    <!-- Navigation Items -->
    <nav class="sidebar-nav">
      <router-link
        v-if="isPendingAssignmentRoute"
        to="/pending-assignment"
        class="nav-item"
        :class="{ active: $route.path === '/pending-assignment' }"
      >
        <IconTasks class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.pendingNew') }}</span>
      </router-link>

      <!-- Home: Übersicht für alle (Department-Dashboard bzw. Admin-Übersicht) -->
      <router-link
        v-if="!isPendingAssignmentRoute"
        :to="mainDashboardLink"
        class="nav-item"
        :class="{ active: isMainDashboardNavActive }"
      >
        <IconDashboard class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.dashboard') }}</span>
      </router-link>

      <!-- Meine Firma (Supplier-Portal) -->
      <div v-if="!isPendingAssignmentRoute && showMyCompanySection" class="nav-section">
        <button
          type="button"
          class="nav-item nav-item--toggle"
          :class="{ active: isSupplierRoute }"
          :title="!isHovered ? t('sidebar.myCompany') : undefined"
          @click="toggleMyCompany"
        >
          <IconPackage class="nav-icon" />
          <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.myCompany') }}</span>
          <IconChevronDown
            v-if="isHovered"
            class="nav-chevron"
            :class="{ 'nav-chevron--collapsed': !myCompanyExpanded }"
          />
        </button>
        <template v-if="myCompanyExpanded">
          <router-link
            :to="supplierLink('/profile')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierProfileActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: isHovered }">
              {{ t('sidebar.myCompanyProfile') }}
            </span>
          </router-link>
          <router-link
            v-if="showSupplierCatalogLink"
            :to="supplierLink('/catalog')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierCatalogActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: isHovered }">
              {{ t('sidebar.myCompanyCatalog') }}
            </span>
          </router-link>
          <router-link
            v-if="showSupplierDeliveryLink"
            :to="supplierLink('/deliveries')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierDeliveriesActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: isHovered }">
              {{ t('sidebar.myCompanyDeliveries') }}
            </span>
          </router-link>
          <router-link
            v-if="isCurrentSupplierAdmin"
            :to="supplierLink('/team')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierTeamActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: isHovered }">
              {{ t('sidebar.myCompanyTeam') }}
            </span>
          </router-link>
        </template>
      </div>

      <!-- Verwaltung der Webseite: Superadmin / Organisationschef / Suborgchef -->
      <router-link
        v-if="!isPendingAssignmentRoute && hasGlobalAdminAccess"
        :to="verwaltungEntryLink"
        class="nav-item"
        :class="{ active: isVerwaltungNavActive }"
      >
        <IconSettings class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.siteAdmin') }}</span>
      </router-link>

      <router-link
        v-if="!isPendingAssignmentRoute && canEditPublicWebsite"
        to="/site-inhalt"
        class="nav-item"
        :class="{ active: $route.path.startsWith('/site-inhalt') }"
      >
        <IconSettings class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.website') }}</span>
      </router-link>

      <div
        v-if="!isPendingAssignmentRoute && hasGlobalAdminAccess && showDeptContextSidebarLinks"
        class="nav-divider"
      />

      <!-- Aktivitäten -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showActivitiesMenu && hasDepartmentContext"
        :to="getLink('/activities')"
        class="nav-item"
        :class="{ active: $route.path.includes('/activities') }"
      >
        <IconActivities class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.activities') }}</span>
      </router-link>

      <!-- Materialien -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showMaterialsMenu && hasDepartmentContext"
        :to="getLink('/materials')"
        class="nav-item"
        :class="{ active: $route.path.includes('/materials') }"
      >
        <IconMaterials class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.materials') }}</span>
      </router-link>

      <!-- Buchhaltung (nur Materialchef / Departmentchef) -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showAccountingMenu && hasDepartmentContext"
        :to="getLink('/accounting')"
        class="nav-item"
        :class="{ active: isAccountingNavActive }"
      >
        <IconAccounting class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.accounting') }}</span>
      </router-link>

      <!-- Kontakte -->
      <router-link
        v-if="!isPendingAssignmentRoute && showDeptContextSidebarLinks"
        :to="getLink('/contacts')"
        class="nav-item"
        :class="{ active: $route.path.includes('/contacts') }"
      >
        <IconContacts class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.contacts') }}</span>
      </router-link>

      <!-- Aufgaben -->
      <router-link
        v-if="!isPendingAssignmentRoute && showDeptContextSidebarLinks"
        :to="getLink('/tasks')"
        class="nav-item"
        :class="{ active: $route.path.includes('/tasks') }"
      >
        <IconTasks class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.tasks') }}</span>
      </router-link>

      <!-- Nachrichtenzentrale (unter Aufgaben) -->
      <router-link
        v-if="!isPendingAssignmentRoute && showDeptContextSidebarLinks"
        :to="getLink('/notifications')"
        class="nav-item"
        :class="{ active: $route.path.includes('/notifications') }"
      >
        <IconBell class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.notifications') }}</span>
      </router-link>

      <!-- Horizontaler Balken (Divider) -->
      <div
        v-if="!isPendingAssignmentRoute && showDeptContextSidebarLinks"
        class="nav-divider"
      />

      <!-- Werkstatt -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showWorkshopMenu && hasDepartmentContext"
        :to="getLink('/workshop')"
        class="nav-item"
        :class="{ active: $route.path.includes('/workshop') }"
      >
        <IconWorkshop class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.workshop') }}</span>
      </router-link>

      <!-- Statistik -->
      <router-link
        v-if="!isPendingAssignmentRoute && showDeptContextSidebarLinks && showStatisticsMenu"
        :to="getLink('/statistics')"
        class="nav-item"
        :class="{ active: $route.path.includes('/statistics') }"
      >
        <IconStatistics class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.statistics') }}</span>
      </router-link>

      <!-- Konfiguration -->
      <router-link
        v-if="!isPendingAssignmentRoute && showDeptContextSidebarLinks"
        :to="getLink('/settings')"
        class="nav-item"
        :class="{ active: $route.path.includes('/settings') }"
      >
        <IconSettings class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">{{ t('sidebar.settings') }}</span>
      </router-link>
    </nav>
    
    <!-- Bottom Actions -->
    <div class="sidebar-footer">
      
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { isDepartmentBasicMemberRole } from '@/composables/useDepartmentMemberRole'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import {
  IconDashboard,
  IconActivities,
  IconMaterials,
  IconAccounting,
  IconContacts,
  IconTasks,
  IconBell,
  IconWorkshop,
  IconStatistics,
  IconSettings,
  IconPackage,
  IconChevronDown,
} from '@/components/icons'

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const isHovered = ref(false)
const myCompanyExpanded = ref(false)

// Department-ID aus Route oder Store
const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const isSupplierRoute = computed(() => route.path.startsWith('/supplier/'))
const supplierCompanyId = computed(
  () =>
    (route.params.companyId as string) ||
    authStore.activeSupplierCompanyId ||
    authStore.activeSupplierCompanies[0]?.id ||
    ''
)
const showMyCompanySection = computed(() => authStore.hasSupplierAccess)
const hasDepartmentContext = computed(
  () => !authStore.isSupplierOnly && !!(departmentId.value || authStore.departments.length)
)
const isCurrentSupplierAdmin = computed(() => {
  const id = supplierCompanyId.value
  const company = authStore.activeSupplierCompanies.find((c) => c.id === id)
  return company?.role === 'admin'
})

const showSupplierCatalogLink = computed(() => {
  const id = supplierCompanyId.value
  const company = authStore.activeSupplierCompanies.find((c) => c.id === id)
  return company?.capabilities?.includes('catalog') ?? false
})

const showSupplierDeliveryLink = computed(() => {
  const id = supplierCompanyId.value
  const company = authStore.activeSupplierCompanies.find((c) => c.id === id)
  return company?.capabilities?.includes('delivery') ?? false
})

watch(
  isSupplierRoute,
  (active) => {
    if (active) myCompanyExpanded.value = true
  },
  { immediate: true }
)

function toggleMyCompany() {
  myCompanyExpanded.value = !myCompanyExpanded.value
}

function supplierLink(subpath: string): string {
  const id = supplierCompanyId.value
  if (!id) return '/pending-assignment'
  return `/supplier/${id}${subpath}`
}

const isSupplierProfileActive = computed(
  () => isSupplierRoute.value && route.path.includes('/profile')
)
const isSupplierTeamActive = computed(() => isSupplierRoute.value && route.path.includes('/team'))
const isSupplierCatalogActive = computed(() => isSupplierRoute.value && route.path.includes('/catalog'))
const isSupplierDeliveriesActive = computed(() => isSupplierRoute.value && route.path.includes('/deliveries'))

const isPendingAssignmentRoute = computed(() => route.path === '/pending-assignment')
const isAdminDashboardRoute = computed(() => route.path.startsWith('/admin-dashboard'))
/** Unter /admin-dashboard ist die zweite Sidebar (Verwaltung) aktiv — Standard-Links würden sonst ausgeblendet. */
const showAppNavInAdminShell = computed(() => !isAdminDashboardRoute.value || isSuperAdmin.value)
/** Kontakte/Aufgaben/Statistik/Konfiguration sind Abteilungs-App — Superadmin braucht sie nicht (nur Verwaltung/Dashboard). */
const showDeptContextSidebarLinks = computed(
  () => showAppNavInAdminShell.value && !isSuperAdmin.value && hasDepartmentContext.value
)
/** Einstieg Verwaltung: Unterbereich (nicht die Übersicht — die ist unter „Dashboard“) */
const verwaltungEntryLink = computed(() => {
  if (isAdminDashboardRoute.value) return '/admin-dashboard/verwaltung'
  if (!departmentId.value && isSuperAdmin.value) return '/admin-dashboard/verwaltung'
  if (!departmentId.value) return '/pending-assignment'
  return `/${departmentId.value}/verwaltung`
})

const isVerwaltungNavActive = computed(() => {
  const p = route.path
  if (p === '/admin-dashboard' || p.startsWith('/admin-dashboard/')) return true
  if (!p.includes('/verwaltung')) return false
  if (p.endsWith('/verwaltung/dashboard')) return false
  return true
})

/** Home = Department-Dashboard; Supplier-only → Profil; Superadmin ohne Department → /dashboard */
const mainDashboardLink = computed(() => {
  if (isPendingAssignmentRoute.value) return '/pending-assignment'
  if (authStore.isSupplierOnly && supplierCompanyId.value) {
    return `/supplier/${supplierCompanyId.value}/profile`
  }
  const id = departmentId.value || authStore.activeDepartmentId
  if (id) return `/${id}`
  if (isSuperAdmin.value) return '/dashboard'
  if (isAdminDashboardRoute.value) return '/admin-dashboard/verwaltung'
  return '/pending-assignment'
})

const isMainDashboardNavActive = computed(() => {
  const p = route.path
  if (authStore.isSupplierOnly && isSupplierProfileActive.value) return true
  const id = departmentId.value || authStore.activeDepartmentId
  if (id && (p === `/${id}` || p === `/${id}/` || p === `/${id}/dashboard`)) return true
  if (p === '/dashboard') return true
  return false
})

const homeLink = computed(() => {
  if (isPendingAssignmentRoute.value) return '/pending-assignment'
  return mainDashboardLink.value
})
// SA/ORG/SUB kommen ausschließlich aus profile.roles, nicht aus Department-Membership
const isSuperAdmin = computed(() => authStore.userRoles.includes('ROLE_SUPERADMIN'))
const canEditPublicWebsite = computed(
  () =>
    authStore.userRoles.includes('ROLE_SUPERADMIN') ||
    authStore.userRoles.includes('ROLE_WEBADMIN')
)
const hasGlobalAdminAccess = computed(() =>
  authStore.userRoles.includes('ROLE_SUPERADMIN') ||
  authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
  authStore.userRoles.includes('ROLE_SUBORGCHEF') ||
  authStore.currentDepartmentRole === 'sa' ||
  authStore.currentDepartmentRole === 'org' ||
  authStore.currentDepartmentRole === 'sub'
)

const showActivitiesMenu = computed(() => !isSuperAdmin.value)
const showMaterialsMenu = computed(() => !isSuperAdmin.value)
/** Werkstatt: nicht für Basissicht (u, l1–l3) — nur MW/DC */
const showWorkshopMenu = computed(() => {
  if (isSuperAdmin.value) return false
  return !isDepartmentBasicMemberRole(authStore.currentDepartmentRole)
})

/** Statistik: wie Werkstatt — nicht für Basissicht */
const showStatisticsMenu = computed(() => !isDepartmentBasicMemberRole(authStore.currentDepartmentRole))

/** Buchhaltung: nur Materialchef (mw) oder Departmentchef (dc) im aktuellen Department */
const showAccountingMenu = computed(() => {
  if (isSuperAdmin.value) return false
  const r = String(authStore.currentDepartmentRole || '').toLowerCase().trim()
  return r === 'mw' || r === 'dc'
})

/** Sidebar: Buchhaltung aktiv bei allen Unterpfaden /accounting/… */
const isAccountingNavActive = computed(() => route.path.includes('/accounting'))

// Mit Department-Kontext immer /{id}/… — auch wenn die Route gerade /admin-dashboard ist (Store/Primär-Dept)
function getLink(path: string): string {
  let id = (route.params.departmentId as string) || authStore.activeDepartmentId || ''
  if (!id && authStore.departments?.length) {
    const d = authStore.departments.find((x) => x.is_primary) || authStore.departments[0]
    id = d.department_id
  }
  if (id) {
    if (path === '/dashboard') return `/${id}`
    if (
      hasGlobalAdminAccess.value &&
      (path === '/jobs' || path === '/support-requests')
    ) {
      return `/${id}/verwaltung${path}`
    }
    return `/${id}${path}`
  }
  if (isAdminDashboardRoute.value) {
    if (path === '/dashboard' && isSuperAdmin.value) return '/dashboard'
    if (path === '/dashboard') return '/admin-dashboard/verwaltung'
    return `/admin-dashboard/verwaltung${path}`
  }
  if (isSuperAdmin.value) return '/dashboard'
  return '/pending-assignment'
}
</script>

<style scoped>
@import '@/styles/sidebar.css';
</style>
