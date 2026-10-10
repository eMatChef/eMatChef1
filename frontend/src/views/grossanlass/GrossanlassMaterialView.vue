<template>
  <div v-if="isDetail" class="ga-uebersicht-detail-host">
    <router-view />
  </div>
  <PageShell
    v-else
    class="grossanlass-material-shell"
    :title="t('grossanlass.material.title')"
    :subtitle="t('grossanlass.material.subtitle')"
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
    { id: 'stock', label: t('grossanlass.material.tabBestand'), icon: 'mdi-warehouse' },
    { id: 'goods-receipt', label: t('grossanlass.material.tabWareneingang'), icon: 'mdi-truck-delivery-outline' },
    { id: 'issue', label: t('grossanlass.material.tabAusgabe'), icon: 'mdi-export-variant' },
    { id: 'pack', label: t('grossanlass.material.tabPack'), icon: 'mdi-package-variant-closed' },
    { id: 'teardown', label: t('grossanlass.material.tabRueckbau'), icon: 'mdi-package-variant-remove' },
    { id: 'resale', label: t('grossanlass.material.tabWeiterverkauf'), icon: 'mdi-tag-multiple-outline' },
  ]
  if (gaCanOperateAusgabe(authStore.currentDepartmentRole)) return tabs
  return tabs.filter((tab) => tab.id !== 'issue')
})

const activeTab = computed(() => (route.meta.materialTab as string) || 'stock')

function onTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id || typeof tab !== 'string') return
  if (tab === 'stock') {
    void router.push(gaBestandListPath(id))
    return
  }
  void router.push(`/${id}/ga/material/${tab}`)
}
</script>

<style scoped>
.grossanlass-material-shell :deep(.page-shell__header) {
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
