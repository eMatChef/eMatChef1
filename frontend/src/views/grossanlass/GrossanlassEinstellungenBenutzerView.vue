<template>
  <div class="ga-users-settings">
    <v-tabs v-model="activeTab" class="materials-view-tabs ga-users-tabs" color="primary" show-arrows>
      <v-tab value="ga-persons">{{ t('grossanlass.einstellungen.usersTabGaPersons') }}</v-tab>
      <v-tab value="helpers">{{ t('grossanlass.einstellungen.usersTabHelpers') }}</v-tab>
    </v-tabs>
    <!-- Eine Instanz für beide Tabs: Tabwechsel lädt nichts neu und verliert keine Eingaben.
         Im Helfer-Tab liefert der Helferpool die Liste (Konto- und externe Helfer); «Benutzer als Helfer» nutzt den Dialog der Benutzerverwaltung. -->
    <UsersSettingsView :member-scope="activeTab" hide-header @changed="helperDirectory?.reload()">
      <template #helpers="{ openAddUser }">
        <GrossanlassHelferDirectory ref="helperDirectory" :department-id="departmentId" @add-account="openAddUser()" />
      </template>
    </UsersSettingsView>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import UsersSettingsView from '@/views/settings/UsersSettingsView.vue'
import GrossanlassHelferDirectory from '@/views/grossanlass/helferpool/GrossanlassHelferDirectory.vue'
import '@/styles/views/materials-view-tabs.css'

const { t } = useI18n()
const route = useRoute()
const authStore = useAuthStore()
const activeTab = ref<'ga-persons' | 'helpers'>('ga-persons')
const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')
const helperDirectory = ref<InstanceType<typeof GrossanlassHelferDirectory> | null>(null)
</script>

<style scoped>
.ga-users-tabs {
  margin-bottom: 12px;
}
</style>
