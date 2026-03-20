<template>
  <div class="settings-view">
    <div class="settings-container">
      <!-- Linkes Menü -->
      <aside class="settings-menu">
        <nav class="settings-nav">
          <router-link
            v-for="item in visibleMenuItems"
            :key="item.id"
            :to="getSettingsLink(`/${item.id}`)"
            class="settings-nav-item"
            :class="{ active: $route.path.includes(`/settings/${item.id}`) }"
          >
            <component :is="item.icon" class="nav-icon" />
            <span class="nav-label">{{ item.label }}</span>
          </router-link>
        </nav>
      </aside>

      <!-- Rechter Content-Bereich -->
      <main class="settings-content">
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
import { ref, computed, markRaw } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import IconSettings from '@/components/icons/IconSettings.vue'
import IconContacts from '@/components/icons/IconContacts.vue'
import IconEmployees from '@/components/icons/IconEmployees.vue'
import IconDashboard from '@/components/icons/IconDashboard.vue'
import IconActivities from '@/components/icons/IconActivities.vue'
import IconMaterials from '@/components/icons/IconMaterials.vue'

const route = useRoute()
const authStore = useAuthStore()
const isAdminDashboardRoute = computed(() => route.path.startsWith('/admin-dashboard'))

// Department-ID aus Route oder Store
const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

// Helper-Funktion für Links
function getSettingsLink(path: string): string {
  if (isAdminDashboardRoute.value) return `/admin-dashboard/settings${path}`
  if (!departmentId.value) return '#'
  return `/${departmentId.value}/settings${path}`
}

/**
 * Prüft ob der User Zugriff auf Organisations-/Department-Verwaltung hat.
 * Erlaubt für: superadmin (sa), organisationschef (org), suborgchef (sub)
 */
const canManageOrganisations = computed(() => {
  // Prüfe currentDepartmentRole (Abkürzung aus membership)
  const role = authStore.currentDepartmentRole
  if (role) {
    const normalizedRole = String(role).toLowerCase().trim()
    // Erlaubte Rollen: sa, org, sub (superadmin, organisationschef, suborgchef)
    if (['sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(normalizedRole)) {
      return true
    }
  }
  
  // Fallback: Prüfe Symfony-Rollen
  const userRoles = authStore.userRoles || []
  if (userRoles.includes('ROLE_SUPERADMIN') || 
      userRoles.includes('ROLE_ORGANISATIONSCHEF') || 
      userRoles.includes('ROLE_SUBORGCHEF')) {
    return true
  }
  
  return false
})

// Components mit markRaw markieren, um Vue-Warnungen zu vermeiden
const allMenuItems = ref([
  {
    id: 'general',
    label: 'Allgemein',
    icon: markRaw(IconSettings),
    requiresOrgAccess: false
  },
  {
    id: 'categories',
    label: 'Kategorien',
    icon: markRaw(IconDashboard),
    requiresOrgAccess: false
  },
  {
    id: 'my-department',
    label: 'Mein Department',
    icon: markRaw(IconDashboard),
    requiresOrgAccess: false // Für alle Rollen sichtbar
  },
  {
    id: 'organisations',
    label: 'Organisationen',
    icon: markRaw(IconDashboard),
    requiresOrgAccess: true // Nur für sa, org, sub
  },
  {
    id: 'departments',
    label: 'Alle Departments',
    icon: markRaw(IconDashboard),
    requiresOrgAccess: true // Nur für sa, org, sub
  },
  {
    id: 'users',
    label: 'Benutzer',
    icon: markRaw(IconEmployees),
    requiresOrgAccess: false
  },
  {
    id: 'groups',
    label: 'Gruppen',
    icon: markRaw(IconContacts),
    requiresOrgAccess: false
  },
  {
    id: 'permissions',
    label: 'Berechtigungen',
    icon: markRaw(IconSettings),
    requiresOrgAccess: true // Nur für sa, org, sub
  },
  {
    id: 'activities',
    label: 'Aktivitäten',
    icon: markRaw(IconActivities),
    requiresOrgAccess: false
  },
  {
    id: 'storage',
    label: 'Regale & Fächer',
    icon: markRaw(IconMaterials),
    requiresOrgAccess: false
  },
  {
    id: 'templates',
    label: 'Vorlagen',
    icon: markRaw(IconSettings),
    requiresOrgAccess: false
  },
  {
    id: 'mail-templates',
    label: 'Mailvorlagen',
    icon: markRaw(IconSettings),
    requiresOrgAccess: true
  }
])

// Gefilterte Menüpunkte basierend auf Berechtigungen
const visibleMenuItems = computed(() => {
  if (isAdminDashboardRoute.value) {
    return allMenuItems.value.filter(item => ['organisations', 'departments', 'users', 'mail-templates'].includes(item.id))
  }

  return allMenuItems.value.filter(item => {
    if (item.requiresOrgAccess) {
      return canManageOrganisations.value
    }
    return true
  })
})
</script>

<style scoped>
.settings-view {
  padding: 24px;
  height: 100%;
}

.settings-container {
  display: flex;
  gap: 24px;
  height: 100%;
  max-width: 1400px;
  margin: 0 auto;
}

.settings-menu {
  width: 240px;
  flex-shrink: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 16px 0;
  height: fit-content;
}

.settings-nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.settings-nav-item {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  color: #64748b;
  text-decoration: none;
  transition: all 0.2s;
  border-left: 3px solid transparent;
}

.settings-nav-item:hover {
  background-color: #f1f5f9;
  color: #334155;
}

.settings-nav-item.active {
  background-color: #eff6ff;
  color: #2563eb;
  border-left-color: #2563eb;
  font-weight: 500;
}

.settings-nav-item .nav-icon {
  width: 20px;
  height: 20px;
  margin-right: 12px;
  flex-shrink: 0;
}

.settings-nav-item .nav-label {
  font-size: 14px;
}

.settings-content {
  flex: 1;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 24px;
  min-height: 600px;
}

/* Fade Transition */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
