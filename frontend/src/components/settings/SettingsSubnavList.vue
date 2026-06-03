<template>
  <div class="settings-subnav-list">
    <div v-if="showTitle" class="settings-shell-drawer__title">
      {{ t('settings.menuTitle') }}
    </div>
    <v-list nav :density="listDensity" class="settings-shell-nav" color="primary">
      <v-list-item
        v-for="item in items"
        :key="item.id"
        :to="getLink(item.id)"
        :active="isActive(item.id)"
        :title="item.label"
        rounded="lg"
        @click="emit('navigate')"
      >
        <template #prepend>
          <component :is="item.icon" class="settings-shell-nav__icon" />
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
  icon: Component
}

withDefaults(
  defineProps<{
    items: SettingsNavItem[]
    getLink: (itemId: string) => string
    isActive: (itemId: string) => boolean
    /** false im eingeklappten Rail (Titel kommt aus Vuetify-Rail-Verhalten) */
    showTitle?: boolean
    /** Mobile: etwas höhere Zeilen für bessere Touch-Ziele */
    listDensity?: 'default' | 'compact' | 'comfortable'
  }>(),
  { showTitle: true, listDensity: 'compact' },
)

const emit = defineEmits<{
  navigate: []
}>()

const { t } = useI18n()
</script>
