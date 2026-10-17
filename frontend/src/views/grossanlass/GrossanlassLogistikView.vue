<template>
  <PageShell class="ga-logistik-shell" :title="t('grossanlass.logistik.title')" :subtitle="t('grossanlass.logistik.subtitle')">
    <template #filters>
      <v-tabs
        :model-value="'disposition'"
        class="materials-view-tabs"
        color="primary"
        show-arrows
        @update:model-value="onTabChange"
      >
        <v-tab value="disposition">
          <v-icon icon="mdi-source-branch" start size="18" />
          {{ t('grossanlass.dispo.tab') }}
        </v-tab>
        <v-tab value="vehicles">
          <v-icon icon="mdi-truck-outline" start size="18" />
          {{ t('sidebar.fahrzeuge') }}
        </v-tab>
      </v-tabs>
    </template>
    <router-view />
  </PageShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import PageShell from '@/components/layout/PageShell.vue'
import '@/styles/views/materials-view-tabs.css'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')

/** Fahrzeuge liegen als eigene Seite unter /ga/vehicles, sind aber über diesen Tab innerhalb der Logistik erreichbar. */
function onTabChange(tab: unknown) {
  if (tab === 'vehicles' && departmentId.value) void router.push(`/${departmentId.value}/ga/vehicles`)
}
</script>
