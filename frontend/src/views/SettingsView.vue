<template>
  <div class="settings-view">
    <div class="settings-container">
      <aside
        class="settings-menu"
        :class="{ 'settings-menu--collapsed': !menuExpanded }"
        @mouseenter="openMenu"
        @mouseleave="closeMenu"
      >
        <div class="settings-menu-header">
          <h2 v-show="menuExpanded" class="settings-menu-title">{{ t('settings.menuTitle') }}</h2>
        </div>
        <nav class="settings-nav">
          <router-link
            v-for="item in visibleMenuItems"
            :key="item.id"
            :to="getSettingsLink(`/${item.id}`)"
            class="settings-nav-item"
            :class="{ active: isSettingsItemActive(item.id) }"
            :title="!menuExpanded ? item.label : undefined"
            @click="onMenuNavClick"
          >
            <component :is="item.icon" class="nav-icon" />
            <span v-show="menuExpanded" class="nav-label">{{ item.label }}</span>
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
import { computed, markRaw } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useHoverSubnav } from '@/composables/useHoverSubnav'
import { IconSettings, IconContacts, IconEmployees, IconDashboard, IconActivities, IconMaterials, IconDisplay } from '@/components/icons'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const { isUserRole, canManageMaterials } = useDepartmentMemberRole()
const { expanded: menuExpanded, open: openMenu, close: closeMenu, onNavClick: onMenuNavClick } =
  useHoverSubnav()

// Department-ID aus Route oder Store
const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

// Helper: /…/settings/<segment>
function getSettingsLink(path: string): string {
  if (!departmentId.value) return '#'
  return `/${departmentId.value}/settings${path}`
}

function isSettingsItemActive(itemId: string): boolean {
  const base = departmentId.value ? `/${departmentId.value}/settings`.replace(/\/$/, '') : ''
  const p = (route.path || '').replace(/\/$/, '') || '/'
  if (itemId === 'my-department') {
    return p === base || p === `${base}/my-department`
  }
  if (itemId === 'zeit') {
    return p === `${base}/zeit`
  }
  if (itemId === 'my-department/join-code') {
    return p === `${base}/my-department/join-code`
  }
  if (itemId === 'my-department/display-screens') {
    return p === `${base}/my-department/display-screens`
  }
  if (itemId === 'my-department/storage-locations') {
    return p === `${base}/my-department/storage-locations`
  }
  if (itemId === 'my-department/billing-address') {
    return p === `${base}/my-department/billing-address`
  }
  if (itemId === 'my-department/public-material-page') {
    return p === `${base}/my-department/public-material-page`
  }
  return p === `${base}/${itemId}` || p.startsWith(`${base}/${itemId}/`)
}

// Nav-Labels: nur in de.json (Crowdin); andere Locales nutzen i18n-Fallback auf Deutsch.
const allMenuItems = computed(() => [
  { id: 'my-department', label: t('settings.nav.myDepartment'), icon: markRaw(IconDashboard) },
  { id: 'users', label: t('settings.nav.users'), icon: markRaw(IconEmployees) },
  { id: 'groups', label: t('settings.nav.groups'), icon: markRaw(IconContacts) },
  { id: 'categories', label: t('settings.nav.categories'), icon: markRaw(IconDashboard) },
  { id: 'storage', label: t('settings.nav.storage'), icon: markRaw(IconMaterials) },
  { id: 'my-department/join-code', label: t('settings.nav.joinCode'), icon: markRaw(IconEmployees) },
  { id: 'my-department/display-screens', label: t('settings.nav.displayScreens'), icon: markRaw(IconDisplay) },
  { id: 'activities', label: t('settings.nav.activities'), icon: markRaw(IconActivities) },
  { id: 'my-department/storage-locations', label: t('settings.nav.storageLocations'), icon: markRaw(IconMaterials) },
  { id: 'my-department/billing-address', label: t('settings.nav.billingAddress'), icon: markRaw(IconContacts) },
  {
    id: 'my-department/public-material-page',
    label: t('settings.nav.publicMaterialPage'),
    icon: markRaw(IconMaterials)
  },
  { id: 'templates', label: t('settings.nav.templates'), icon: markRaw(IconSettings) },
  {
    id: 'material-import',
    label: t('settings.nav.materialImport'),
    icon: markRaw(IconMaterials),
    requiresMaterialManage: true,
  },
  {
    id: 'supplier-deliveries',
    label: t('settings.nav.supplierDeliveries'),
    icon: markRaw(IconMaterials),
    requiresMaterialManage: true,
  },
  { id: 'zeit', label: t('settings.nav.timeLocation'), icon: markRaw(IconSettings) },
  { id: 'addons', label: t('settings.nav.addons'), icon: markRaw(IconActivities) }
])

const USER_ALLOWED_MENU_IDS = new Set(['my-department', 'groups'])

const visibleMenuItems = computed(() => {
  let items = isUserRole.value
    ? allMenuItems.value.filter((item) => USER_ALLOWED_MENU_IDS.has(item.id))
    : allMenuItems.value
  if (!canManageMaterials.value) {
    items = items.filter((item) => !(item as { requiresMaterialManage?: boolean }).requiresMaterialManage)
  }
  return items
})

</script>

<style scoped>
.settings-view {
  padding: 24px;
  height: 100%;
  max-width: 100%;
  overflow-x: hidden;
}

.settings-container {
  display: flex;
  gap: 24px;
  height: 100%;
  max-width: 1400px;
  margin: 0 auto;
  min-width: 0;
  position: relative;
  /* Platzhalter für das eingeklappte Menü (56px) + gap (24px),
     damit das aufgeklappte Menü den Inhalt überlappt statt verschiebt */
  padding-left: 80px;
}

.settings-menu {
  position: absolute;
  top: 0;
  left: 0;
  z-index: 20;
  width: 260px;
  flex-shrink: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 16px 0 20px;
  height: fit-content;
  transition: width 0.2s ease, box-shadow 0.2s ease;
}

.settings-menu--collapsed {
  width: 56px;
  padding-top: 12px;
}

.settings-menu:not(.settings-menu--collapsed) {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
}

.settings-menu-header {
  margin-bottom: 8px;
  padding: 0 12px;
  min-height: 1.5rem;
}

.settings-menu--collapsed .settings-menu-header {
  padding: 0 8px;
}

.settings-menu-title {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
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

.settings-menu--collapsed .settings-nav-item {
  justify-content: center;
  padding: 12px 8px;
  border-left-width: 3px;
}

.settings-menu--collapsed .settings-nav-item .nav-icon {
  margin-right: 0;
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
  min-width: 0;
  overflow-x: hidden;
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
