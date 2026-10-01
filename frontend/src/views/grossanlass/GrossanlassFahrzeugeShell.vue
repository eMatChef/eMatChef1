<template>
  <div v-if="isDetail" class="ga-fahrzeuge-detail-host">
    <router-view />
  </div>
  <PageShell
    v-else
    class="grossanlass-fahrzeuge-shell"
    :title="t('grossanlass.fahrzeuge.title')"
    :subtitle="t('grossanlass.fahrzeuge.subtitle')"
  >
    <template #filters>
      <v-tabs
        :model-value="activeTab"
        class="materials-view-tabs"
        color="primary"
        show-arrows
        @update:model-value="onTabChange"
      >
        <v-tab v-for="tab in tabItems" :key="tab.id" :value="tab.id">
          <v-icon :icon="tab.icon" start size="18" />
          {{ tab.label }}
        </v-tab>
      </v-tabs>
    </template>

    <router-view v-slot="{ Component }">
      <transition name="fade" mode="out-in">
        <component :is="Component" />
      </transition>
    </router-view>
  </PageShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import PageShell from '@/components/layout/PageShell.vue'
import { provideGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { provideGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import '@/styles/views/materials-view-tabs.css'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

provideGaCommitmentCatalog()
provideGaUebersicht()

const isDetail = computed(() => route.name === 'GrossanlassFahrzeugArtikel')

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const tabItems = computed(() => [
  { id: 'wuensche', label: t('grossanlass.fahrzeuge.tabWuensche'), icon: 'mdi-lightbulb-on-outline' },
  { id: 'fuhrpark', label: t('grossanlass.fahrzeuge.tabFuhrpark'), icon: 'mdi-truck-outline' },
])

const activeTab = computed(() => (route.meta.fahrzeugeTab as string) || 'wuensche')

function onTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id || typeof tab !== 'string') return
  void router.push(`/${id}/fahrzeuge/${tab}`)
}
</script>

<style scoped>
.grossanlass-fahrzeuge-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}

.ga-fahrzeuge-detail-host {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
}

.ga-fahrzeuge-detail-host :deep(.material-detail-view) {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}
</style>
