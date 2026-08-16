<template>
  <div class="settings-subnav-list">
    <div v-if="showTitle" class="settings-shell-drawer__title">
      {{ t(menuTitleKey) }}
    </div>
    <v-list nav :density="listDensity" class="settings-shell-nav" color="primary">
      <v-list-item
        v-for="item in items"
        :key="item.id"
        :to="getLink(item.id)"
        :active="isActive(item.id)"
        :title="item.label"
        :data-onboarding="`settings-nav-${item.id.replace(/\//g, '-')}`"
        rounded="lg"
        @click="emit('navigate')"
      >
        <template #prepend>
          <v-icon
            v-if="item.mdiIcon"
            :icon="item.mdiIcon"
            class="settings-shell-nav__icon settings-shell-nav__icon--mdi"
            size="20"
          />
          <component v-else-if="item.icon" :is="item.icon" class="settings-shell-nav__icon" />
        </template>
      </v-list-item>
    </v-list>
  </div>
</template>

<script setup lang="ts">
import type { Component } from 'vue'
import { useI18n } from 'vue-i18n'

export interface SettingsNavItem {
  id: string
  label: string
  /** @deprecated Prefer mdiIcon */
  icon?: Component
  mdiIcon?: string
}

withDefaults(
  defineProps<{
    items: SettingsNavItem[]
    getLink: (itemId: string) => string
    isActive: (itemId: string) => boolean
    /** i18n-Key für den Bereichstitel (z. B. settings.menuTitle, help.menuTitle) */
    menuTitleKey?: string
    /** false im eingeklappten Rail (Titel kommt aus Vuetify-Rail-Verhalten) */
    showTitle?: boolean
    /** Mobile: etwas höhere Zeilen für bessere Touch-Ziele */
    listDensity?: 'default' | 'compact' | 'comfortable'
  }>(),
  { menuTitleKey: 'settings.menuTitle', showTitle: true, listDensity: 'compact' },
)

const emit = defineEmits<{
  navigate: []
}>()

const { t } = useI18n()
</script>
