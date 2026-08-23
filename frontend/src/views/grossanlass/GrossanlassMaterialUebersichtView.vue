<template>
  <PageShell
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
          <span v-if="tab.badge" class="materials-view-tab-count">{{ tab.badge }}</span>
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
import { createGrossanlassEinsatzPreview } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import '@/styles/views/materials-view-tabs.css'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const conflictCount = computed(() => createGrossanlassEinsatzPreview(tr).conflicts.length)

const tabItems = computed(() => [
  { id: 'bestand', label: t('grossanlass.materialUebersicht.tabBestand'), icon: 'mdi-warehouse', badge: '' },
  { id: 'einsaetze', label: t('grossanlass.materialUebersicht.tabEinsaetze'), icon: 'mdi-calendar-range', badge: '' },
  {
    id: 'konflikte',
    label: t('grossanlass.materialUebersicht.tabKonflikte'),
    icon: 'mdi-alert-outline',
    badge: String(conflictCount.value),
  },
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
