<template>
  <v-navigation-drawer
    :key="mdAndUp ? 'nav-desktop' : 'nav-mobile'"
    v-model="drawerOpen"
    class="emc-sidebar-drawer sidebar"
    color="#26353b"
    disable-resize-watcher
    :permanent="mdAndUp"
    :temporary="!mdAndUp"
    :rail="mdAndUp"
    expand-on-hover
    width="240"
    rail-width="64"
    @mouseenter="isHovered = true"
    @mouseleave="isHovered = false"
  >
    <!-- Logo -->
    <div class="sidebar-logo">
      <router-link :to="homeLink" class="logo-link">
        <EmcLogoMark size="sm" />
        <span class="logo-text" :class="{ visible: showNavLabels }">eMatChef</span>
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
        <v-icon icon="mdi-clipboard-list" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.pendingNew') }}</span>
      </router-link>

      <!-- Home: Übersicht für alle (Department-Dashboard bzw. Admin-Übersicht) -->
      <router-link
        v-if="!isPendingAssignmentRoute"
        :to="mainDashboardLink"
        class="nav-item"
        :class="{ active: isMainDashboardNavActive }"
      >
        <v-icon icon="mdi-view-grid" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.dashboard') }}</span>
      </router-link>

      <!-- Meine Firma (Supplier-Portal) -->
      <div v-if="!isPendingAssignmentRoute && showMyCompanySection" class="nav-section">
        <button
          type="button"
          class="nav-item nav-item--toggle"
          :class="{ active: isSupplierRoute }"
          :title="!showNavLabels ? t('sidebar.myCompany') : undefined"
          @click="toggleMyCompany"
        >
          <v-icon icon="mdi-package-variant" class="nav-icon nav-icon--mdi" size="20" />
          <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.myCompany') }}</span>
          <v-icon
            icon="mdi-chevron-down"
            class="nav-chevron nav-chevron--mdi"
            :class="{
              'nav-chevron--collapsed': !myCompanyExpanded,
              'nav-chevron--rail-hidden': !showNavLabels,
            }"
            size="14"
          />
        </button>
        <template v-if="myCompanyExpanded">
          <router-link
            :to="supplierLink('/profile')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierProfileActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: showNavLabels }">
              {{ t('sidebar.myCompanyProfile') }}
            </span>
          </router-link>
          <router-link
            v-if="showSupplierCatalogLink"
            :to="supplierLink('/catalog')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierCatalogActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: showNavLabels }">
              {{ t('sidebar.myCompanyCatalog') }}
            </span>
          </router-link>
          <router-link
            v-if="showSupplierDeliveryLink"
            :to="supplierLink('/deliveries')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierDeliveriesActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: showNavLabels }">
              {{ t('sidebar.myCompanyDeliveries') }}
            </span>
          </router-link>
          <router-link
            v-if="showSupplierTemplatesLink"
            :to="supplierLink('/templates')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierTemplatesActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: showNavLabels }">
              {{ t('sidebar.myCompanyTemplates') }}
            </span>
          </router-link>
          <router-link
            v-if="showSupplierRepairsLink"
            :to="supplierLink('/repairs')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierRepairsActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: showNavLabels }">
              {{ t('sidebar.myCompanyRepairs') }}
            </span>
          </router-link>
          <router-link
            v-if="showSupplierRepairsLink"
            :to="supplierLink('/repair-templates')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierRepairTemplatesActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: showNavLabels }">
              {{ t('sidebar.myCompanyRepairTemplates') }}
            </span>
          </router-link>
          <router-link
            v-if="isCurrentSupplierAdmin"
            :to="supplierLink('/team')"
            class="nav-item nav-item--sub"
            :class="{ active: isSupplierTeamActive }"
          >
            <span class="nav-label nav-label--sub" :class="{ visible: showNavLabels }">
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
        <v-icon icon="mdi-shield-account" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.siteAdmin') }}</span>
      </router-link>

      <router-link
        v-if="!isPendingAssignmentRoute && canEditPublicWebsite"
        to="/site-inhalt"
        class="nav-item"
        :class="{ active: $route.path.startsWith('/site-inhalt') }"
      >
        <v-icon icon="mdi-web" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.website') }}</span>
      </router-link>

      <div
        v-if="!isPendingAssignmentRoute && hasGlobalAdminAccess && showStandardDeptSidebarLinks"
        class="nav-divider"
      />

      <!-- Aktivitäten -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showActivitiesMenu && hasDepartmentContext && !isGrossanlassDept"
        :to="getLink('/activities')"
        class="nav-item"
        :class="{ active: isDeptSectionNavActive('activities') }"
      >
        <v-icon icon="mdi-calendar" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.activities') }}</span>
      </router-link>

      <!-- Materialien -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showMaterialsMenu && hasDepartmentContext && !isGrossanlassDept"
        :to="getLink('/materials')"
        class="nav-item"
        :class="{ active: $route.path.includes('/materials') }"
      >
        <v-icon icon="mdi-package-variant" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.materials') }}</span>
      </router-link>

      <!-- Buchhaltung (nur Materialchef / Departmentchef) -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showAccountingMenu && hasDepartmentContext && !isGrossanlassDept"
        :to="getLink('/accounting')"
        class="nav-item"
        :class="{ active: isAccountingNavActive }"
      >
        <v-icon icon="mdi-cash" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.accounting') }}</span>
      </router-link>

      <!-- Kontakte -->
      <router-link
        v-if="!isPendingAssignmentRoute && showStandardDeptSidebarLinks"
        :to="getLink('/contacts')"
        class="nav-item"
        :class="{ active: $route.path.includes('/contacts') }"
      >
        <v-icon icon="mdi-account-group" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.contacts') }}</span>
      </router-link>

      <!-- Aufgaben -->
      <router-link
        v-if="!isPendingAssignmentRoute && showStandardDeptSidebarLinks"
        :to="getLink('/tasks')"
        class="nav-item"
        :class="{ active: isDeptSectionNavActive('tasks') }"
      >
        <v-icon icon="mdi-clipboard-list" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.tasks') }}</span>
      </router-link>

      <!-- Nachrichtenzentrale (unter Aufgaben) -->
      <router-link
        v-if="!isPendingAssignmentRoute && showStandardDeptSidebarLinks"
        :to="getLink('/notifications')"
        class="nav-item"
        :class="{ active: isDeptSectionNavActive('notifications') }"
      >
        <v-icon icon="mdi-bell-outline" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.notifications') }}</span>
      </router-link>

      <!-- Horizontaler Balken (Divider) -->
      <div
        v-if="!isPendingAssignmentRoute && showStandardDeptSidebarLinks"
        class="nav-divider"
      />

      <!-- Werkstatt -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showWorkshopMenu && hasDepartmentContext && !isGrossanlassDept"
        :to="getLink('/workshop')"
        class="nav-item"
        :class="{ active: isDeptSectionNavActive('workshop') }"
      >
        <v-icon icon="mdi-wrench" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.workshop') }}</span>
      </router-link>

      <!-- Statistik -->
      <router-link
        v-if="!isPendingAssignmentRoute && showStandardDeptSidebarLinks && showStatisticsMenu"
        :to="getLink('/statistics')"
        class="nav-item"
        :class="{ active: $route.path.includes('/statistics') }"
      >
        <v-icon icon="mdi-chart-bar" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.statistics') }}</span>
      </router-link>

      <!-- Lieferanten-Shop (MW/DC, vor Konfiguration) -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showSupplierShopLink && hasDepartmentContext && !isGrossanlassDept"
        :to="getLink('/supplier-shop')"
        class="nav-item"
        :class="{ active: $route.path.includes('/supplier-shop') }"
      >
        <v-icon icon="mdi-store" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.supplierShop') }}</span>
      </router-link>

      <router-link
        v-if="!isPendingAssignmentRoute && showDevSandboxLink"
        :to="getLink('/dev/ui-playground')"
        class="nav-item"
        :class="{ active: isDevPlaygroundNavActive }"
      >
        <v-icon icon="mdi-flask-outline" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.devUiPlayground') }}</span>
      </router-link>

      <router-link
        v-if="!isPendingAssignmentRoute && showDeptContextSidebarLinks"
        :to="getLink('/settings')"
        class="nav-item"
        :class="{ active: $route.path.includes('/settings') }"
      >
        <v-icon icon="mdi-cog-outline" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.settings') }}</span>
      </router-link>

      <router-link
        v-if="!isPendingAssignmentRoute && showStandardDeptSidebarLinks"
        :to="getLink('/help/overview')"
        class="nav-item"
        :class="{ active: isHelpOverviewNavActive }"
      >
        <v-icon icon="mdi-help-circle-outline" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.help') }}</span>
      </router-link>

    </nav>
    
    <!-- Bottom Actions -->
    <div class="sidebar-footer">
      
    </div>
  </v-navigation-drawer>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/stores/auth'
import { isDepartmentBasicMemberRole } from '@/composables/useDepartmentMemberRole'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import { isDevToolsEnvironment } from '@/utils/devEnvironmentBanner'
const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const { mdAndUp } = useDisplay()
const drawerOpen = defineModel<boolean>({ default: false })
const isHovered = ref(false)

const showNavLabels = computed(() => !mdAndUp.value || isHovered.value)

/** Desktop: Rail immer sichtbar (drawerOpen=true). Mobile: zu bei Resize, sonst bleibt translateX(-width) hängen. */
watch(
  mdAndUp,
  (desktop) => {
    drawerOpen.value = desktop
  },
  { immediate: true },
)

watch(
  () => route.fullPath,
  () => {
    if (!mdAndUp.value) {
      drawerOpen.value = false
    }
  },
)
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

const showSupplierTemplatesLink = computed(() => {
  const id = supplierCompanyId.value
  const company = authStore.activeSupplierCompanies.find((c) => c.id === id)
  return company?.capabilities?.includes('templates') ?? false
})

const showSupplierRepairsLink = computed(() => {
  const id = supplierCompanyId.value
  const company = authStore.activeSupplierCompanies.find((c) => c.id === id)
  return company?.capabilities?.includes('repairs') ?? false
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
const isSupplierTemplatesActive = computed(() => isSupplierRoute.value && route.path.includes('/templates'))
const isSupplierRepairsActive = computed(
  () => isSupplierRoute.value && /\/repairs(\/|$)/.test(route.path) && !route.path.includes('/repair-templates'),
)
const isSupplierRepairTemplatesActive = computed(
  () => isSupplierRoute.value && route.path.includes('/repair-templates'),
)

const isPendingAssignmentRoute = computed(() => route.path === '/pending-assignment')
const isAdminDashboardRoute = computed(() => route.path.startsWith('/admin-dashboard'))
/** Unter /admin-dashboard ist die zweite Sidebar (Verwaltung) aktiv — Standard-Links würden sonst ausgeblendet. */
const showAppNavInAdminShell = computed(() => !isAdminDashboardRoute.value || isSuperAdmin.value)
/** Kontakte/Aufgaben/Statistik/Konfiguration sind Abteilungs-App — Superadmin braucht sie nicht (nur Verwaltung/Dashboard). */
const showDeptContextSidebarLinks = computed(
  () => showAppNavInAdminShell.value && !isSuperAdmin.value && hasDepartmentContext.value
)
const showDevSandboxLink = computed(
  () =>
    isDevToolsEnvironment() &&
    showAppNavInAdminShell.value &&
    !isPendingAssignmentRoute.value &&
    !isSupplierRoute.value
)
const isDevPlaygroundNavActive = computed(() => {
  const p = route.path
  return p.includes('/dev/ui-playground') || /\/[^/]+\/sandbox\/?$/.test(p)
})
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

/** Home = Department-Dashboard; Supplier-only → Profil; Superadmin → /dashboard */
const mainDashboardLink = computed(() => {
  if (isPendingAssignmentRoute.value) return '/pending-assignment'
  if (authStore.isSupplierOnly && supplierCompanyId.value) {
    return `/supplier/${supplierCompanyId.value}/profile`
  }
  if (isSuperAdmin.value) return '/dashboard'
  const id = departmentId.value || authStore.activeDepartmentId
  if (id) return `/${id}`
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

const isGrossanlassDept = computed(() => authStore.isDepartmentGrossanlass(departmentId.value))

/** Phase 1 Grossanlass: nur Dashboard, Konfiguration (+ Sandbox in Dev) — keine Standard-Dept-Module */
const showStandardDeptSidebarLinks = computed(
  () => showDeptContextSidebarLinks.value && !isGrossanlassDept.value,
)
const showMaterialsMenu = computed(() => !isSuperAdmin.value)
/** Werkstatt: nicht für Basissicht (u, l1–l3) — nur MW/DC */
const showWorkshopMenu = computed(() => {
  if (isSuperAdmin.value) return false
  return !isDepartmentBasicMemberRole(authStore.currentDepartmentRole)
})

/** Statistik: wie Werkstatt — nicht für Basissicht */
const showStatisticsMenu = computed(() => !isDepartmentBasicMemberRole(authStore.currentDepartmentRole))

/** Buchhaltung: MW/DC voller Zugriff; L1–L3 und Gruppenchefs nur Gruppenkosten */
const showAccountingMenu = computed(() => {
  if (isSuperAdmin.value) return false
  const r = String(authStore.currentDepartmentRole || '').toLowerCase().trim()
  if (r === 'mw' || r === 'dc') return true
  if (['l1', 'l2', 'l3'].includes(r)) return true
  return false
})

/** Lieferanten-Shop: Materialwart / Departmentchef */
const showSupplierShopLink = computed(() => {
  if (isSuperAdmin.value) return false
  const r = String(authStore.currentDepartmentRole || '').toLowerCase().trim()
  return ['mw', 'dc', 'matwart', 'depchef'].includes(r)
})

/** Sidebar: Buchhaltung aktiv bei allen Unterpfaden /accounting/… */
const isAccountingNavActive = computed(() => route.path.includes('/accounting'))

/** Hauptnav-Abschnitt aktiv — nicht wenn gleicher Name unter /settings/… (z. B. settings/workshop). */
function isDeptSectionNavActive(section: string): boolean {
  const path = route.path
  if (path.includes('/settings')) return false
  return path.includes(`/${section}`)
}

// Mit Department-Kontext immer /{id}/… — auch wenn die Route gerade /admin-dashboard ist (Store/Primär-Dept)
const isHelpOverviewNavActive = computed(() => route.path.includes('/help'))

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
