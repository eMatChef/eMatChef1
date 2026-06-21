<template>
  <PageShell
    class="grossanlass-beschaffung-shell"
    :title="t('grossanlass.beschaffung.title')"
    :subtitle="t('grossanlass.beschaffung.subtitle')"
  >
    <div class="grossanlass-beschaffung-view" :class="{ 'grossanlass-beschaffung-view--desktop': mdAndUp }">
      <header v-if="!mdAndUp" class="grossanlass-beschaffung-view__mobile-bar">
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
              class="grossanlass-beschaffung-view__menu-btn"
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
              menu-title-key="grossanlass.beschaffung.menuTitle"
              list-density="default"
              @navigate="mobileMenuOpen = false"
            />
          </v-card>
        </v-menu>
        <span class="grossanlass-beschaffung-view__mobile-title">{{ activeTabLabel }}</span>
      </header>

      <aside
        v-if="mdAndUp"
        class="settings-subnav-rail grossanlass-beschaffung-view__rail"
        :class="{ 'settings-subnav-rail--expanded': desktopNavExpanded }"
        @mouseenter="desktopNavHovered = true"
        @mouseleave="onDesktopNavLeave"
      >
        <div class="settings-subnav-rail__panel" @click="onDesktopRailBackgroundClick">
          <SettingsSubnavList
            :items="tabItems"
            :get-link="navLinkForTab"
            :is-active="isTabActive"
            menu-title-key="grossanlass.beschaffung.menuTitle"
            show-title
            @navigate="onDesktopNavClick"
          />
        </div>
      </aside>

      <div class="grossanlass-beschaffung-view__content">
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
  { id: 'bedarf', label: t('grossanlass.beschaffung.tabBedarf'), mdiIcon: 'mdi-clipboard-list-outline' },
  { id: 'uebersicht', label: t('grossanlass.beschaffung.tabUebersicht'), mdiIcon: 'mdi-chart-box-outline' },
  { id: 'offerten', label: t('grossanlass.beschaffung.tabOfferten'), mdiIcon: 'mdi-file-document-outline' },
  { id: 'bestellungen', label: t('grossanlass.beschaffung.tabBestellungen'), mdiIcon: 'mdi-cart-outline' },
  { id: 'erhalten', label: t('grossanlass.beschaffung.tabErhalten'), mdiIcon: 'mdi-package-check' },
])

const activeTabLabel = computed(() => {
  const tab = tabItems.value.find((item) => isTabActive(item.id))
  return tab?.label || t('grossanlass.beschaffung.title')
})

const mobileMenuCardStyle = computed(() => ({
  maxHeight: 'min(70vh, 520px)',
  overflowY: 'auto' as const,
}))

function getBeschaffungLink(tab: string): string {
  if (!departmentId.value) return '#'
  return `/${departmentId.value}/beschaffung/${tab}`
}

function navLinkForTab(tabId: string): string {
  return getBeschaffungLink(tabId)
}

function isTabActive(tabId: string): boolean {
  const base = departmentId.value ? `/${departmentId.value}/beschaffung` : ''
  const path = (route.path || '').replace(/\/$/, '')
  if (tabId === 'bedarf') {
    return path === `${base}/bedarf` || path === base
  }
  if (tabId === 'uebersicht') {
    return path === `${base}/uebersicht`
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
.grossanlass-beschaffung-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}

.grossanlass-beschaffung-view {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-height: 320px;
}

.grossanlass-beschaffung-view--desktop {
  flex-direction: row;
  align-items: flex-start;
  gap: 0;
}

.grossanlass-beschaffung-view__mobile-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
}

.grossanlass-beschaffung-view__mobile-title {
  font-weight: 600;
  font-size: 1rem;
}

.grossanlass-beschaffung-view__content {
  flex: 1 1 auto;
  min-width: 0;
}

.grossanlass-beschaffung-view__rail {
  flex-shrink: 0;
}
</style>
