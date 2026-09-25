<template>
  <PageShell
    class="grossanlass-planung-shell"
    :title="t('grossanlass.planung.menuTitle')"
    :subtitle="t('grossanlass.planung.shellSubtitle')"
  >
    <template v-if="showAddTransport" #actions>
      <EButton variant="primary" size="small" @click="fahrauftragComposer.open()">
        {{ t('grossanlass.materialUebersicht.addFahrauftrag') }}
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
import {
  gaBauauftragComposerKey,
  type GaBauauftragComposer,
} from '@/views/grossanlass/gaBauauftragComposer'
import {
  gaFahrauftragComposerKey,
  type GaFahrauftragComposer,
} from '@/views/grossanlass/gaFahrauftragComposer'
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

const bauauftragComposer = reactive<GaBauauftragComposer>({
  open: () => {},
  canAdd: false,
})
provide(gaBauauftragComposerKey, bauauftragComposer)

const fahrauftragComposer = reactive<GaFahrauftragComposer>({
  open: () => {},
  canAdd: false,
})
provide(gaFahrauftragComposerKey, fahrauftragComposer)

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const tabItems = computed(() => [
  { id: 'wuensche', label: t('grossanlass.planung.tabWishes'), icon: 'mdi-lightbulb-on-outline' },
  { id: 'bauauftraege', label: t('grossanlass.planung.tabBauauftraege'), icon: 'mdi-hammer-wrench' },
  { id: 'transporte', label: t('grossanlass.planung.tabTransporte'), icon: 'mdi-truck-fast-outline' },
  { id: 'belegung', label: t('grossanlass.planung.tabBelegung'), icon: 'mdi-calendar-range' },
  { id: 'konflikte', label: t('grossanlass.planung.tabKonflikte'), icon: 'mdi-alert-outline' },
])

const activeTab = computed(() => (route.meta.planungTab as string) || 'wuensche')
const showAddTransport = computed(() =>
  activeTab.value === 'transporte' && fahrauftragComposer.canAdd,
)

function onTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id || typeof tab !== 'string') return
  void router.push(`/${id}/planung/${tab}`)
}
</script>

<style scoped>
.grossanlass-planung-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}
</style>
