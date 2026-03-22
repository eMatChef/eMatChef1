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
        <span class="nav-label" :class="{ visible: isHovered }">Neu</span>
      </router-link>

      <!-- Dashboard -->
      <router-link
        v-if="!isPendingAssignmentRoute"
        :to="isAdminDashboardRoute ? '/admin-dashboard/dashboard' : getLink('/dashboard')"
        class="nav-item"
        :class="{ active: $route.path.endsWith('/dashboard') }"
      >
        <IconDashboard class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Dashboard</span>
      </router-link>

      <router-link
        v-if="!isPendingAssignmentRoute && showJobsMenu"
        :to="isAdminDashboardRoute ? '/admin-dashboard/jobs' : getLink('/jobs')"
        class="nav-item"
        :class="{ active: $route.path.includes('/jobs') }"
      >
        <IconJobs class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Jobs</span>
      </router-link>

      <router-link
        v-if="!isPendingAssignmentRoute && showSupportRequestsMenu"
        :to="isAdminDashboardRoute ? '/admin-dashboard/support-requests' : getLink('/support-requests')"
        class="nav-item"
        :class="{ active: $route.path.includes('/support-requests') }"
      >
        <IconSupport class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Supportanfragen</span>
      </router-link>

      <!-- Aktivitäten -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showActivitiesMenu"
        :to="getLink('/activities')"
        class="nav-item"
        :class="{ active: $route.path.includes('/activities') }"
      >
        <IconActivities class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Aktivitäten</span>
      </router-link>

      <!-- Materialien -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showMaterialsMenu"
        :to="getLink('/materials')"
        class="nav-item"
        :class="{ active: $route.path.includes('/materials') }"
      >
        <IconMaterials class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Materialien</span>
      </router-link>

      <!-- Kontakte -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute"
        :to="getLink('/contacts')"
        class="nav-item"
        :class="{ active: $route.path.includes('/contacts') }"
      >
        <IconContacts class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Kontakte</span>
      </router-link>

      <!-- Aufgaben -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute"
        :to="getLink('/tasks')"
        class="nav-item"
        :class="{ active: $route.path.includes('/tasks') }"
      >
        <IconTasks class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Aufgaben</span>
      </router-link>

      <!-- Horizontaler Balken (Divider) -->
      <div v-if="!isPendingAssignmentRoute" class="nav-divider"></div>

      <!-- Werkstatt -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute && showWorkshopMenu"
        :to="getLink('/workshop')"
        class="nav-item"
        :class="{ active: $route.path.includes('/workshop') }"
      >
        <IconWorkshop class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Werkstatt</span>
      </router-link>

      <!-- Statistik -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute"
        :to="getLink('/statistics')"
        class="nav-item"
        :class="{ active: $route.path.includes('/statistics') }"
      >
        <IconStatistics class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Statistik</span>
      </router-link>

      <router-link
        v-if="isAdminDashboardRoute"
        to="/admin-dashboard/settings/organisations"
        class="nav-item"
        :class="{ active: $route.path.includes('/admin-dashboard/settings') }"
      >
        <IconSettings class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Admin-Konfiguration</span>
      </router-link>

      <!-- Konfiguration -->
      <router-link
        v-if="!isPendingAssignmentRoute && !isAdminDashboardRoute"
        :to="getLink('/settings')"
        class="nav-item"
        :class="{ active: $route.path.includes('/settings') }"
      >
        <IconSettings class="nav-icon" />
        <span class="nav-label" :class="{ visible: isHovered }">Konfiguration</span>
      </router-link>
    </nav>
    
    <!-- Bottom Actions -->
    <div class="sidebar-footer">
      
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import IconDashboard from '@/components/icons/IconDashboard.vue'
import IconActivities from '@/components/icons/IconActivities.vue'
import IconMaterials from '@/components/icons/IconMaterials.vue'
import IconContacts from '@/components/icons/IconContacts.vue'
import IconTasks from '@/components/icons/IconTasks.vue'
import IconWorkshop from '@/components/icons/IconWorkshop.vue'
import IconStatistics from '@/components/icons/IconStatistics.vue'
import IconSettings from '@/components/icons/IconSettings.vue'
import IconJobs from '@/components/icons/IconJobs.vue'
import IconSupport from '@/components/icons/IconTasks.vue'

const route = useRoute()
const authStore = useAuthStore()
const isHovered = ref(false)

// Department-ID aus Route oder Store
const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})
const isPendingAssignmentRoute = computed(() => route.path === '/pending-assignment')
const isAdminDashboardRoute = computed(() => route.path.startsWith('/admin-dashboard'))
const homeLink = computed(() => {
  if (isPendingAssignmentRoute.value) return '/pending-assignment'
  if (isAdminDashboardRoute.value) return '/admin-dashboard/dashboard'
  if (!departmentId.value && isSuperAdmin.value) return '/admin-dashboard/dashboard'
  return getLink('/dashboard')
})
// SA/ORG/SUB kommen ausschließlich aus profile.roles, nicht aus Department-Membership
const isSuperAdmin = computed(() => authStore.userRoles.includes('ROLE_SUPERADMIN'))
const hasGlobalAdminAccess = computed(() =>
  authStore.userRoles.includes('ROLE_SUPERADMIN') ||
  authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
  authStore.userRoles.includes('ROLE_SUBORGCHEF') ||
  authStore.currentDepartmentRole === 'sa' ||
  authStore.currentDepartmentRole === 'org' ||
  authStore.currentDepartmentRole === 'sub'
)

const showJobsMenu = computed(() => hasGlobalAdminAccess.value)

const showSupportRequestsMenu = computed(() => hasGlobalAdminAccess.value)

const showActivitiesMenu = computed(() => !isSuperAdmin.value)
const showMaterialsMenu = computed(() => !isSuperAdmin.value)
const showWorkshopMenu = computed(() => !isSuperAdmin.value)

// Helper-Funktion für Links
function getLink(path: string): string {
  if (isAdminDashboardRoute.value) return `/admin-dashboard${path}`
  if (!departmentId.value && isSuperAdmin.value) return '/admin-dashboard/dashboard'
  if (!departmentId.value) return '/pending-assignment'
  return `/${departmentId.value}${path}`
}
</script>

<style scoped>
@import '@/styles/sidebar.css';
</style>
