<template>
  <PageShell
    class="grossanlass-planung-shell"
    :title="t('grossanlass.planung.title')"
    :subtitle="t('grossanlass.planung.subtitle')"
  >
    <div class="grossanlass-planung-view" :class="{ 'grossanlass-planung-view--desktop': mdAndUp }">
      <header v-if="!mdAndUp" class="grossanlass-planung-view__mobile-bar">
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
              class="grossanlass-planung-view__menu-btn"
              :aria-label="t('layout.header.menuAria')"
              :aria-expanded="mobileMenuOpen"
            >
              <v-icon icon="mdi-menu" size="24" />
            </v-btn>
          </template>
          <v-card class="settings-mobile-menu-card" elevation="12" rounded="lg" :style="mobileMenuCardStyle">
            <SettingsSubnavList
              :items="tabItems"
              :get-link="navLinkForTab"
              :is-active="isTabActive"
              menu-title-key="grossanlass.planung.menuTitle"
              list-density="default"
              @navigate="mobileMenuOpen = false"
            />
          </v-card>
        </v-menu>
        <span class="grossanlass-planung-view__mobile-title">{{ activeTabLabel }}</span>
      </header>

      <aside
        v-if="mdAndUp"
        class="settings-subnav-rail grossanlass-planung-view__rail"
        :class="{ 'settings-subnav-rail--expanded': desktopNavExpanded }"
        @mouseenter="desktopNavHovered = true"
        @mouseleave="onDesktopNavLeave"
      >
        <div class="settings-subnav-rail__panel" @click="onDesktopRailBackgroundClick">
          <SettingsSubnavList
            :items="tabItems"
            :get-link="navLinkForTab"
            :is-active="isTabActive"
            menu-title-key="grossanlass.planung.menuTitle"
            show-title
            @navigate="onDesktopNavClick"
          />
        </div>
      </aside>

      <div class="grossanlass-planung-view__content">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </div>
    </div>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useAuthStore } from '@/stores/auth'
import PageShell from '@/components/layout/PageShell.vue'
import SettingsSubnavList, { type SettingsNavItem } from '@/components/settings/SettingsSubnavList.vue'
import '@/styles/views/settings-shell.css'

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const { mdAndUp } = useDisplay()

const mobileMenuOpen = ref(false)
const desktopNavHovered = ref(false)
const desktopNavPinned = ref(false)

const desktopNavExpanded = computed(() => desktopNavPinned.value || desktopNavHovered.value)

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const tabItems = computed<SettingsNavItem[]>(() => [
  {
    id: 'ressorts',
    label: t('grossanlass.planung.tabRessorts'),
    mdiIcon: 'mdi-sitemap',
  },
  {
    id: 'rounds',
    label: t('grossanlass.planung.tabRounds'),
    mdiIcon: 'mdi-calendar-clock',
  },
])

const activeTabLabel = computed(() => {
  const tab = tabItems.value.find((item) => isTabActive(item.id))
  return tab?.label || t('grossanlass.planung.title')
})

const mobileMenuCardStyle = computed(() => ({
  maxHeight: 'min(70vh, 420px)',
  overflowY: 'auto' as const,
}))

function getPlanungLink(tab: string): string {
  if (!departmentId.value) return '#'
  return `/${departmentId.value}/planung/${tab}`
}

function navLinkForTab(tabId: string): string {
  return getPlanungLink(tabId)
}

function isTabActive(tabId: string): boolean {
  const base = departmentId.value ? `/${departmentId.value}/planung` : ''
  const path = (route.path || '').replace(/\/$/, '')
  if (tabId === 'ressorts') {
    return path === `${base}/ressorts` || path === base
  }
  return path === `${base}/${tabId}`
}

function onDesktopNavLeave() {
  if (!desktopNavPinned.value) {
    desktopNavHovered.value = false
  }
}

function onDesktopNavClick() {
  if (!desktopNavPinned.value) {
    desktopNavHovered.value = false
  }
}

function onDesktopRailBackgroundClick(event: MouseEvent) {
  const target = event.target as HTMLElement
  if (target.closest('.v-list-item') || target.closest('a')) return
  desktopNavPinned.value = !desktopNavPinned.value
}

watch(
  () => route.fullPath,
  () => {
    mobileMenuOpen.value = false
  },
)
</script>

<style scoped>
.grossanlass-planung-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}

.grossanlass-planung-view {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-height: 320px;
}

.grossanlass-planung-view--desktop {
  flex-direction: row;
  align-items: flex-start;
  gap: 0;
}

.grossanlass-planung-view__mobile-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
}

.grossanlass-planung-view__mobile-title {
  font-weight: 600;
  font-size: 1rem;
}

.grossanlass-planung-view__content {
  flex: 1 1 auto;
  min-width: 0;
}

.grossanlass-planung-view__rail {
  flex-shrink: 0;
}
</style>
