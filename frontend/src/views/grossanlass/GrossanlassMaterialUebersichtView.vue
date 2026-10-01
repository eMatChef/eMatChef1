<template>
  <div v-if="isDetail" class="ga-uebersicht-detail-host">
    <router-view />
  </div>
  <PageShell
    v-else
    class="grossanlass-material-uebersicht-shell"
    :title="t('grossanlass.materialUebersicht.title')"
    :subtitle="t('grossanlass.materialUebersicht.subtitle')"
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
import { gaCanOperateAusgabe } from '@/utils/grossanlassAccess'
import { gaBestandListPath } from '@/views/grossanlass/gaBestandPaths'
import '@/styles/views/materials-view-tabs.css'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

provideGaCommitmentCatalog()
provideGaUebersicht()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const isDetail = computed(() => route.name === 'GrossanlassMaterialsArtikel')

const tabItems = computed(() => {
  const tabs = [
    { id: 'bestand', label: t('grossanlass.materialUebersicht.tabBestand'), icon: 'mdi-warehouse' },
    { id: 'wareneingang', label: t('grossanlass.materialUebersicht.tabWareneingang'), icon: 'mdi-truck-delivery-outline' },
    { id: 'ausgabe', label: t('grossanlass.materialUebersicht.tabAusgabe'), icon: 'mdi-export-variant' },
    { id: 'pack', label: t('grossanlass.materialUebersicht.tabPack'), icon: 'mdi-package-variant-closed' },
    { id: 'retour', label: t('grossanlass.materialUebersicht.tabRetour'), icon: 'mdi-keyboard-return' },
  ]
  if (gaCanOperateAusgabe(authStore.currentDepartmentRole)) return tabs
  return tabs.filter((tab) => tab.id !== 'ausgabe')
})

const activeTab = computed(() => (route.meta.materialUebersichtTab as string) || 'bestand')

function onTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id || typeof tab !== 'string') return
  if (tab === 'bestand') {
    void router.push(gaBestandListPath(id))
    return
  }
  void router.push(`/${id}/material-uebersicht/${tab}`)
}
</script>

<style scoped>
.grossanlass-material-uebersicht-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}
.ga-uebersicht-detail-host {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
}
.ga-uebersicht-detail-host :deep(.material-detail-view) {
  flex: 1;
  min-height: 0;
  overflow: hidden;
}
</style>
