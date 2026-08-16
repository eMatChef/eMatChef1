<template>
  <div class="settings-view" :class="{ 'settings-view--desktop': mdAndUp }">
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
            menu-title-key="help.menuTitle"
            :items="menuItems"
            :get-link="navLinkForItem"
            :is-active="isHelpItemActive"
            list-density="default"
            @navigate="mobileMenuOpen = false"
          />
        </v-card>
      </v-menu>
      <span class="settings-view__mobile-title">{{ activeSectionLabel }}</span>
    </header>

    <aside
      v-if="mdAndUp"
      class="settings-subnav-rail"
      :class="{ 'settings-subnav-rail--expanded': desktopNavExpanded }"
      @mouseenter="onDesktopNavEnter"
      @mouseleave="onDesktopNavLeave"
    >
      <div class="settings-subnav-rail__panel" @click="onDesktopRailBackgroundClick">
        <SettingsSubnavList
          menu-title-key="help.menuTitle"
          :items="menuItems"
          :get-link="navLinkForItem"
          :is-active="isHelpItemActive"
          show-title
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
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentOnboardingAccess } from '@/composables/useDepartmentOnboardingAccess'
import SettingsSubnavList, { type SettingsNavItem } from '@/components/settings/SettingsSubnavList.vue'
import '@/styles/views/settings-shell.css'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const { mdAndUp } = useDisplay()
const { canUseHelpEinrichtung } = useDepartmentOnboardingAccess()

const mobileMenuOpen = ref(false)
const desktopNavHovered = ref(false)
const desktopNavPinned = ref(false)
let desktopNavLeaveTimer: ReturnType<typeof setTimeout> | null = null

const desktopNavExpanded = computed(() => desktopNavPinned.value || desktopNavHovered.value)

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const menuItems = computed<SettingsNavItem[]>(() => {
  const items: SettingsNavItem[] = []
  if (canUseHelpEinrichtung.value) {
    items.push({
      id: 'tours',
      label: t('help.nav.tours'),
      mdiIcon: 'mdi-compass-outline',
    })
  }
  items.push({
    id: 'dokumentation',
    label: t('help.nav.dokumentation'),
    mdiIcon: 'mdi-book-open-variant',
  })
  return items
})

const activeSectionLabel = computed(() => {
  const active = menuItems.value.find((item) => isHelpItemActive(item.id))
  return active?.label || t('help.menuTitle')
})

function getHelpLink(path: string): string {
  if (!departmentId.value) return '#'
  return `/${departmentId.value}/help${path}`
}

function navLinkForItem(itemId: string): string {
  return getHelpLink(`/${itemId}`)
}

function isHelpItemActive(itemId: string): boolean {
  const base = departmentId.value ? `/${departmentId.value}/help`.replace(/\/$/, '') : ''
  const p = (route.path || '').replace(/\/$/, '') || '/'
  if (itemId === 'dokumentation') {
    return p === base || p === `${base}/dokumentation` || p === `${base}/overview`
  }
  if (itemId === 'tours') {
    return p === `${base}/tours` || p === `${base}/einrichtung`
  }
  return p === `${base}/${itemId}`
}

function onDesktopNavEnter() {
  if (desktopNavLeaveTimer) {
    clearTimeout(desktopNavLeaveTimer)
    desktopNavLeaveTimer = null
  }
  desktopNavHovered.value = true
}

function onDesktopNavLeave() {
  if (desktopNavPinned.value) return
  if (desktopNavLeaveTimer) clearTimeout(desktopNavLeaveTimer)
  desktopNavLeaveTimer = setTimeout(() => {
    desktopNavHovered.value = false
    desktopNavLeaveTimer = null
  }, 160)
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

onUnmounted(() => {
  if (desktopNavLeaveTimer) clearTimeout(desktopNavLeaveTimer)
})

const NAV_ITEM_ROW_PX = 46
const NAV_TITLE_PX = 40
const NAV_PADDING_PX = 16

function navContentHeight(itemCount: number): number {
  return NAV_TITLE_PX + itemCount * NAV_ITEM_ROW_PX + NAV_PADDING_PX
}

const mobileMenuCardStyle = computed(() => {
  const contentH = navContentHeight(menuItems.value.length)
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
