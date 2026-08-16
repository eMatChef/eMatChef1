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
    <nav class="sidebar-nav" data-onboarding="sidebar-nav">
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
        data-onboarding="nav-dashboard"
        :title="!showNavLabels ? t('sidebar.dashboard') : undefined"
      >
        <v-icon icon="mdi-view-grid" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.dashboard') }}</span>
      </router-link>

      <!-- Supplier-only: gleiche Top-Level-Icons wie die Abteilungs-App -->
      <template v-if="!isPendingAssignmentRoute && showMyCompanySection && authStore.isSupplierOnly">
        <router-link
          v-for="item in supplierNavItems"
          :key="item.id"
          :to="supplierLink(item.path)"
          class="nav-item"
          :class="{ active: item.active }"
          :title="!showNavLabels ? item.label : undefined"
        >
          <v-icon :icon="item.icon" class="nav-icon nav-icon--mdi" size="20" />
          <span class="nav-label" :class="{ visible: showNavLabels }">{{ item.label }}</span>
        </router-link>
      </template>

      <!-- Department + Supplier: «Meine Firma» mit Icons auch im Rail -->
      <div
        v-else-if="!isPendingAssignmentRoute && showMyCompanySection"
        class="nav-section"
      >
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
            v-for="item in mixedSupplierNavItems"
            :key="item.id"
            :to="supplierLink(item.path)"
            class="nav-item nav-item--sub"
            :class="{ active: item.active }"
            :title="!showNavLabels ? item.label : undefined"
          >
            <v-icon :icon="item.icon" class="nav-icon nav-icon--mdi" size="18" />
            <span class="nav-label nav-label--sub" :class="{ visible: showNavLabels }">
              {{ item.label }}
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
        data-onboarding="nav-activities"
      >
        <v-icon icon="mdi-calendar" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.activities') }}</span>
      </router-link>

      <!-- Grossanlass: Ressorts & Mitglieder (MW/DC — Name des Anlasses) -->
      <router-link
        v-if="!isPendingAssignmentRoute && isGrossanlassDept && showDeptContextSidebarLinks && !isUserRole"
        :to="getLink('/ressorts')"
        class="nav-item"
        :class="{ active: isDeptSectionNavActive('ressorts') }"
        :title="grossanlassRessortsNavTitle"
      >
        <v-icon icon="mdi-sitemap" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label nav-label--grossanlass" :class="{ visible: showNavLabels }">{{ grossanlassNavLabel }}</span>
      </router-link>

      <!-- Mein Ressort (Ressort-Mitglieder: Bauprojekte & Materialwünsche) -->
      <router-link
        v-if="!isPendingAssignmentRoute && isGrossanlassDept && showDeptContextSidebarLinks && isUserRole"
        :to="getLink('/mein-ressort')"
        class="nav-item"
        :class="{ active: isDeptSectionNavActive('mein-ressort') }"
        :title="t('sidebar.meinRessortHint')"
      >
        <v-icon icon="mdi-home-group" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.meinRessort') }}</span>
      </router-link>

      <!-- Planung: Runden → Material sammeln → Beschaffung -->
      <router-link
        v-if="!isPendingAssignmentRoute && isGrossanlassDept && showDeptContextSidebarLinks"
        :to="getLink('/planung')"
        class="nav-item"
        :class="{ active: isPlanungNavActive }"
        :title="t('sidebar.planungHint')"
      >
        <v-icon icon="mdi-calendar-clock" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.planung') }}</span>
      </router-link>

      <!-- Beschaffung (Grossanlass, MW/DC — Phase 2c Shell) -->
      <router-link
        v-if="!isPendingAssignmentRoute && isGrossanlassDept && showDeptContextSidebarLinks && showGrossanlassBeschaffungMenu"
        :to="getLink('/beschaffung')"
        class="nav-item"
        :class="{ active: isDeptSectionNavActive('beschaffung') }"
        :title="t('sidebar.beschaffungHint')"
      >
        <v-icon icon="mdi-cart-outline" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.beschaffung') }}</span>
      </router-link>

      <!-- Materialien -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showMaterialsMenu && hasDepartmentContext && !isGrossanlassDept"
        :to="getLink('/materials')"
        class="nav-item"
        :class="{ active: $route.path.includes('/materials') }"
        data-onboarding="nav-materials"
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
        data-onboarding="nav-contacts"
      >
        <v-icon icon="mdi-account-group" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.contacts') }}</span>
      </router-link>

      <!-- Aufgaben -->
      <router-link
        v-if="!isPendingAssignmentRoute && showDeptContextSidebarLinks"
        :to="getLink('/tasks')"
        class="nav-item"
        :class="{ active: isDeptSectionNavActive('tasks') }"
        data-onboarding="nav-tasks"
      >
        <v-icon icon="mdi-clipboard-list" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.tasks') }}</span>
      </router-link>

      <!-- Nachrichtenzentrale (unter Aufgaben) -->
      <router-link
        v-if="!isPendingAssignmentRoute && showDeptContextSidebarLinks"
        :to="getLink('/notifications')"
        class="nav-item"
        :class="{ active: isDeptSectionNavActive('notifications') }"
        data-onboarding="nav-notifications"
      >
        <v-icon icon="mdi-bell-outline" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.notifications') }}</span>
      </router-link>

      <!-- Horizontaler Balken (Divider) -->
      <div
        v-if="!isPendingAssignmentRoute && (showStandardDeptSidebarLinks || (isGrossanlassDept && showDeptContextSidebarLinks))"
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

      <!-- Lieferanten-Shop (MW/DC, nur wenn Katalog-Artikel vorhanden) -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showSupplierShopNav && hasDepartmentContext && !isGrossanlassDept"
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
        data-onboarding="nav-settings"
      >
        <v-icon icon="mdi-cog-outline" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.settings') }}</span>
      </router-link>

      <router-link
        v-if="!isPendingAssignmentRoute && showStandardDeptSidebarLinks"
        :to="helpNavLink"
        class="nav-item"
        :class="{ active: isHelpNavActive }"
      >
        <v-icon icon="mdi-help-circle-outline" class="nav-icon nav-icon--mdi" size="20" />
        <span class="nav-label" :class="{ visible: showNavLabels }">{{ t('sidebar.help') }}</span>
        <span
          v-if="helpOnboardingBadgeCount > 0 && showNavLabels"
          class="nav-badge"
        >
          {{ helpOnboardingBadgeCount }}
        </span>
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
import { isDepartmentBasicMemberRole, useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { canUseDepartmentOnboarding, canUseHelpEinrichtung } from '@/utils/onboardingGate'
import { countOpenChecklistItems } from '@/utils/onboardingChecklist'
import {
  isOnboardingDone,
  readOnboardingState,
} from '@/utils/departmentOnboarding'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import { isDevToolsEnvironment } from '@/utils/devEnvironmentBanner'
import { getSupplierShopAvailability } from '@/api/supplierShop'
const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const { isUserRole } = useDepartmentMemberRole()
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

type SupplierNavItem = {
  id: string
  path: string
  icon: string
  label: string
  active: boolean
}

const isSupplierDashboardActive = computed(
  () => isSupplierRoute.value && /\/dashboard\/?$/.test(route.path),
)
const isSupplierProfileActive = computed(
  () => isSupplierRoute.value && route.path.includes('/profile'),
)
const isSupplierTeamActive = computed(() => isSupplierRoute.value && route.path.includes('/team'))
const isSupplierCatalogActive = computed(() => isSupplierRoute.value && route.path.includes('/catalog'))
const isSupplierDeliveriesActive = computed(() => isSupplierRoute.value && route.path.includes('/deliveries'))
const isSupplierTemplatesActive = computed(
  () =>
    isSupplierRoute.value &&
    route.path.includes('/templates') &&
    !route.path.includes('/repair-templates'),
)
const isSupplierRepairsActive = computed(
  () => isSupplierRoute.value && /\/repairs(\/|$)/.test(route.path) && !route.path.includes('/repair-templates'),
)
const isSupplierRepairTemplatesActive = computed(
  () => isSupplierRoute.value && route.path.includes('/repair-templates'),
)

/** Lieferanten-Menü ohne Dashboard (das sitzt oben). */
const supplierNavItems = computed((): SupplierNavItem[] => {
  const items: SupplierNavItem[] = []
  if (showSupplierCatalogLink.value) {
    items.push({
      id: 'catalog',
      path: '/catalog',
      icon: 'mdi-storefront',
      label: t('sidebar.myCompanyCatalog'),
      active: isSupplierCatalogActive.value,
    })
  }
  if (showSupplierDeliveryLink.value) {
    items.push({
      id: 'deliveries',
      path: '/deliveries',
      icon: 'mdi-truck-delivery',
      label: t('sidebar.myCompanyDeliveries'),
      active: isSupplierDeliveriesActive.value,
    })
  }
  if (showSupplierTemplatesLink.value) {
    items.push({
      id: 'templates',
      path: '/templates',
      icon: 'mdi-file-document-multiple',
      label: t('sidebar.myCompanyTemplates'),
      active: isSupplierTemplatesActive.value,
    })
  }
  if (showSupplierRepairsLink.value) {
    items.push({
      id: 'repairs',
      path: '/repairs',
      icon: 'mdi-hammer-wrench',
      label: t('sidebar.myCompanyRepairs'),
      active: isSupplierRepairsActive.value,
    })
    items.push({
      id: 'repair-templates',
      path: '/repair-templates',
      icon: 'mdi-clipboard-text',
      label: t('sidebar.myCompanyRepairTemplates'),
      active: isSupplierRepairTemplatesActive.value,
    })
  }
  items.push({
    id: 'profile',
    path: '/profile',
    icon: 'mdi-card-account-details',
    label: t('sidebar.myCompanyProfile'),
    active: isSupplierProfileActive.value,
  })
  if (isCurrentSupplierAdmin.value) {
    items.push({
      id: 'team',
      path: '/team',
      icon: 'mdi-account-group',
      label: t('sidebar.myCompanyTeam'),
      active: isSupplierTeamActive.value,
    })
  }
  return items
})

/** Mit Department: Dashboard der Firma als erster Unterpunkt. */
const mixedSupplierNavItems = computed((): SupplierNavItem[] => [
  {
    id: 'dashboard',
    path: '/dashboard',
    icon: 'mdi-view-grid',
    label: t('sidebar.dashboard'),
    active: isSupplierDashboardActive.value,
  },
  ...supplierNavItems.value,
])

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

/** Home = Department-Dashboard; Supplier-only → Firmen-Dashboard; Superadmin → /dashboard */
const mainDashboardLink = computed(() => {
  if (isPendingAssignmentRoute.value) return '/pending-assignment'
  if (authStore.isSupplierOnly && supplierCompanyId.value) {
    return `/supplier/${supplierCompanyId.value}/dashboard`
  }
  if (isSuperAdmin.value) return '/dashboard'
  const id = departmentId.value || authStore.activeDepartmentId
  if (id) return `/${id}`
  if (isAdminDashboardRoute.value) return '/admin-dashboard/verwaltung'
  return '/pending-assignment'
})

const isMainDashboardNavActive = computed(() => {
  const p = route.path
  if (authStore.isSupplierOnly && isSupplierDashboardActive.value) return true
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

const grossanlassNavLabel = computed(() => {
  const id = departmentId.value
  const dept = authStore.departments.find((d) => d.department_id === id)
  return dept?.department?.name || t('grossanlass.label')
})

const grossanlassRessortsNavTitle = computed(() =>
  t('sidebar.grossanlassRessortsHint', { name: grossanlassNavLabel.value }),
)

const isPlanungNavActive = computed(() => {
  const path = route.path
  if (path.includes('/settings')) return false
  return path.includes('/planung')
})

/** Phase 1 Grossanlass: nur Dashboard, Konfiguration (+ Sandbox in Dev) — Ressorts/Planung, Aufgaben, Nachrichten */
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

/** Grossanlass-Beschaffung (Shell): nur MW/DC — kein Pfadi-/accounting-Modul */
const showGrossanlassBeschaffungMenu = computed(() => {
  if (isSuperAdmin.value || !isGrossanlassDept.value) return false
  const r = String(authStore.currentDepartmentRole || '').toLowerCase().trim()
  return r === 'mw' || r === 'dc'
})

/** Lieferanten-Shop: Materialwart / Departmentchef */
const showSupplierShopLink = computed(() => {
  if (isSuperAdmin.value) return false
  const r = String(authStore.currentDepartmentRole || '').toLowerCase().trim()
  return ['mw', 'dc', 'matwart', 'depchef'].includes(r)
})

const supplierShopHasArticles = ref(false)

const showSupplierShopNav = computed(
  () => showSupplierShopLink.value && supplierShopHasArticles.value,
)

async function refreshSupplierShopAvailability() {
  const depId = departmentId.value
  if (!depId || !showSupplierShopLink.value || isGrossanlassDept.value) {
    supplierShopHasArticles.value = false
    return
  }
  try {
    const result = await getSupplierShopAvailability(depId)
    supplierShopHasArticles.value = !!result.has_articles
  } catch {
    supplierShopHasArticles.value = false
  }
}

watch(
  [departmentId, showSupplierShopLink, isGrossanlassDept],
  () => {
    void refreshSupplierShopAvailability()
  },
  { immediate: true },
)

const helpNavLink = computed(() => {
  const depId = departmentId.value
  if (!depId) return getLink('/help/dokumentation')
  if (canUseDepartmentOnboarding(authStore, depId) && helpOnboardingBadgeCount.value > 0) {
    return getLink('/help/tours')
  }
  if (canUseHelpEinrichtung(authStore, depId)) {
    return getLink('/help/tours')
  }
  return getLink('/help/dokumentation')
})

const isHelpNavActive = computed(() => route.path.includes('/help'))

const helpOnboardingBadgeCount = computed(() => {
  const depId = departmentId.value
  if (!depId || !canUseDepartmentOnboarding(authStore, depId)) return 0
  const profId = authStore.profileId
  if (!profId) return 0
  if (isOnboardingDone(profId, depId)) return 0
  const state = readOnboardingState(profId, depId)
  return countOpenChecklistItems(state.completed, state.skipped || {})
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
