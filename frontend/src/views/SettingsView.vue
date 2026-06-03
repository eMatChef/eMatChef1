<template>
  <div class="settings-view" :class="{ 'settings-view--desktop': mdAndUp }">
    <!-- Mobile: Hamburger + schwebendes Menü -->
    <header v-if="!mdAndUp" class="settings-view__mobile-bar">
      <v-menu
        v-model="mobileMenuOpen"
        location="bottom start"
        :close-on-content-click="true"
        offset="8"
        scroll-strategy="reposition"
        content-class="settings-mobile-menu-overlay"
      >
        <template #activator="{ props: menuProps }">
          <v-btn
            v-bind="menuProps"
            icon
            variant="text"
            size="small"
            class="settings-view__menu-btn"
            :aria-label="t('layout.header.menuAria')"
            :aria-expanded="mobileMenuOpen"
          >
            <v-icon icon="mdi-menu" size="24" />
          </v-btn>
        </template>
        <v-card class="settings-mobile-menu-card" elevation="12" rounded="lg" :style="mobileMenuCardStyle">
          <SettingsSubnavList
            :items="visibleMenuItems"
            :get-link="navLinkForItem"
            :is-active="isSettingsItemActive"
            list-density="default"
            @navigate="mobileMenuOpen = false"
          />
        </v-card>
      </v-menu>
      <span class="settings-view__mobile-title">{{ t('settings.menuTitle') }}</span>
    </header>

    <!-- Tablet/Desktop: 64px-Spalte im Layout; Hover/Pin → Panel klappt nach rechts auf -->
    <aside
      v-if="mdAndUp"
      class="settings-subnav-rail"
      :class="{ 'settings-subnav-rail--expanded': desktopNavExpanded }"
      @mouseenter="desktopNavHovered = true"
      @mouseleave="onDesktopNavLeave"
    >
      <div class="settings-subnav-rail__panel" @click="onDesktopRailBackgroundClick">
        <SettingsSubnavList
          :items="visibleMenuItems"
          :get-link="navLinkForItem"
          :is-active="isSettingsItemActive"
          :show-title="desktopNavExpanded"
          @navigate="onDesktopNavClick"
        />
      </div>
    </aside>

    <div class="settings-view__content">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, markRaw, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import SettingsSubnavList from '@/components/settings/SettingsSubnavList.vue'
import { IconSettings, IconContacts, IconEmployees, IconDashboard, IconActivities, IconMaterials, IconDisplay } from '@/components/icons'
import '@/styles/views/settings-shell.css'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const { mdAndUp } = useDisplay()
const { isUserRole, canManageMaterials } = useDepartmentMemberRole()

const mobileMenuOpen = ref(false)
const desktopNavHovered = ref(false)
const desktopNavPinned = ref(false)

const desktopNavExpanded = computed(() => desktopNavPinned.value || desktopNavHovered.value)

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

function getSettingsLink(path: string): string {
  if (!departmentId.value) return '#'
  return `/${departmentId.value}/settings${path}`
}

function navLinkForItem(itemId: string): string {
  return getSettingsLink(`/${itemId}`)
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
  if (itemId === 'my-department/fixed-dates') {
    return p === `${base}/my-department/fixed-dates`
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

function onDesktopNavLeave() {
  if (!desktopNavPinned.value) {
    desktopNavHovered.value = false
  }
}

function onDesktopRailBackgroundClick(event: MouseEvent) {
  const target = event.target as HTMLElement
  if (target.closest('.v-list-item') || target.closest('a')) return
  desktopNavPinned.value = !desktopNavPinned.value
}

function onDesktopNavClick() {
  if (!desktopNavPinned.value) {
    desktopNavHovered.value = false
  }
}

const allMenuItems = computed(() => [
  { id: 'my-department', label: t('settings.nav.myDepartment'), icon: markRaw(IconDashboard) },
  { id: 'users', label: t('settings.nav.users'), icon: markRaw(IconEmployees) },
  { id: 'groups', label: t('settings.nav.groups'), icon: markRaw(IconContacts) },
  { id: 'categories', label: t('settings.nav.categories'), icon: markRaw(IconDashboard) },
  { id: 'storage', label: t('settings.nav.storage'), icon: markRaw(IconMaterials) },
  { id: 'my-department/join-code', label: t('settings.nav.joinCode'), icon: markRaw(IconEmployees) },
  {
    id: 'my-department/fixed-dates',
    label: t('settings.nav.fixedDates'),
    icon: markRaw(IconSettings),
    requiresMaterialManage: true,
  },
  { id: 'my-department/display-screens', label: t('settings.nav.displayScreens'), icon: markRaw(IconDisplay) },
  { id: 'activities', label: t('settings.nav.activities'), icon: markRaw(IconActivities) },
  { id: 'my-department/storage-locations', label: t('settings.nav.storageLocations'), icon: markRaw(IconMaterials) },
  { id: 'my-department/billing-address', label: t('settings.nav.billingAddress'), icon: markRaw(IconContacts) },
  {
    id: 'my-department/public-material-page',
    label: t('settings.nav.publicMaterialPage'),
    icon: markRaw(IconMaterials),
  },
  { id: 'templates', label: t('settings.nav.templates'), icon: markRaw(IconSettings) },
  {
    id: 'material-import',
    label: t('settings.nav.materialImport'),
    icon: markRaw(IconMaterials),
    requiresMaterialManage: true,
  },
  { id: 'zeit', label: t('settings.nav.timeLocation'), icon: markRaw(IconSettings) },
  { id: 'addons', label: t('settings.nav.addons'), icon: markRaw(IconActivities) },
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

const SETTINGS_NAV_ITEM_ROW_PX = 46
const SETTINGS_NAV_TITLE_PX = 40
const SETTINGS_NAV_PADDING_PX = 16

function settingsNavContentHeight(itemCount: number): number {
  return SETTINGS_NAV_TITLE_PX + itemCount * SETTINGS_NAV_ITEM_ROW_PX + SETTINGS_NAV_PADDING_PX
}

const mobileMenuCardStyle = computed(() => {
  const contentH = settingsNavContentHeight(visibleMenuItems.value.length)
  const maxH = Math.round((typeof window !== 'undefined' ? window.innerHeight : 800) * 0.75)
  return {
    minHeight: `${Math.min(contentH, maxH)}px`,
    maxHeight: `${maxH}px`,
  }
})

watch(
  () => route.path,
  () => {
    mobileMenuOpen.value = false
    desktopNavPinned.value = false
    desktopNavHovered.value = false
  },
)
</script>
