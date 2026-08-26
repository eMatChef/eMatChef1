<template>
  <PageShell
    class="grossanlass-material-uebersicht-shell"
    :title="t('grossanlass.materialUebersicht.title')"
    :subtitle="t('grossanlass.materialUebersicht.subtitle')"
  >
    <template v-if="activeTab === 'einsaetze'" #actions>
      <EButton variant="primary" size="small" @click="einsatzComposer.open('einsatz')">
        {{ t('grossanlass.materialUebersicht.actionEinsatz') }}
      </EButton>
      <EButton variant="secondary" size="small" @click="einsatzComposer.open('order')">
        {{ t('grossanlass.materialUebersicht.actionOrder') }}
      </EButton>
    </template>
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
import { computed, provide, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { EButton } from '@/components/form/base'
import PageShell from '@/components/layout/PageShell.vue'
import { provideGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { provideGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import {
  gaEinsatzComposerKey,
  type GaEinsatzComposer,
} from '@/views/grossanlass/gaEinsatzComposer'
import '@/styles/views/materials-view-tabs.css'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

provideGaCommitmentCatalog()
provideGaUebersicht()

const einsatzComposer = reactive<GaEinsatzComposer>({
  open: () => {},
})
provide(gaEinsatzComposerKey, einsatzComposer)

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const tabItems = computed(() => [
  { id: 'bestand', label: t('grossanlass.materialUebersicht.tabBestand'), icon: 'mdi-warehouse' },
  { id: 'einsaetze', label: t('grossanlass.materialUebersicht.tabEinsaetze'), icon: 'mdi-calendar-range' },
  { id: 'konflikte', label: t('grossanlass.materialUebersicht.tabKonflikte'), icon: 'mdi-alert-outline' },
  { id: 'ausgabe', label: t('grossanlass.materialUebersicht.tabAusgabe'), icon: 'mdi-export-variant' },
  { id: 'pack', label: t('grossanlass.materialUebersicht.tabPack'), icon: 'mdi-package-variant-closed' },
  { id: 'retour', label: t('grossanlass.materialUebersicht.tabRetour'), icon: 'mdi-keyboard-return' },
])

const activeTab = computed(() => (route.meta.materialUebersichtTab as string) || 'bestand')

function onTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id || typeof tab !== 'string') return
  if (tab === 'bestand') {
    void router.push(`/${id}/material-uebersicht`)
    return
  }
  void router.push(`/${id}/material-uebersicht/${tab}`)
}
</script>

<style scoped>
.grossanlass-material-uebersicht-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}
</style>
