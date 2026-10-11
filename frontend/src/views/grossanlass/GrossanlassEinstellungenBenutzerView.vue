<template>
  <div class="ga-users-settings">
    <v-tabs v-model="activeTab" class="materials-view-tabs ga-users-tabs" color="primary" show-arrows>
      <v-tab value="ga-persons">{{ t('grossanlass.einstellungen.usersTabGaPersons') }}</v-tab>
      <v-tab value="helpers">{{ t('grossanlass.einstellungen.usersTabHelpers') }}</v-tab>
    </v-tabs>
    <!-- Eine Instanz für beide Tabs: Tabwechsel lädt nichts neu und verliert keine Eingaben. -->
    <UsersSettingsView :member-scope="activeTab" hide-header />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import UsersSettingsView from '@/views/settings/UsersSettingsView.vue'
import '@/styles/views/materials-view-tabs.css'

const { t } = useI18n()
const activeTab = ref<'ga-persons' | 'helpers'>('ga-persons')
</script>

<style scoped>
.ga-users-tabs {
  margin-bottom: 12px;
}
</style>
