<template>
  <PageShell
    class="grossanlass-einstellungen-shell"
    :title="t('grossanlass.einstellungen.title')"
    :subtitle="t('grossanlass.einstellungen.subtitle')"
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
import '@/styles/views/materials-view-tabs.css'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const tabItems = computed(() => [
  { id: 'ressorts', label: t('grossanlass.planung.tabRessorts'), icon: 'mdi-sitemap' },
  { id: 'karten', label: t('grossanlass.planung.tabKarten'), icon: 'mdi-card-account-details' },
  { id: 'stammdaten', label: t('grossanlass.planung.tabStammdaten'), icon: 'mdi-card-account-details-outline' },
  { id: 'standorte', label: t('grossanlass.einstellungen.tabStandorte'), icon: 'mdi-warehouse' },
  { id: 'anfragen-email', label: t('grossanlass.einstellungen.tabAnfragenEmail'), icon: 'mdi-email-edit-outline' },
  { id: 'struktur', label: t('grossanlass.planung.tabStruktur'), icon: 'mdi-file-tree-outline' },
  { id: 'activities', label: t('grossanlass.planung.tabActivities'), icon: 'mdi-calendar-plus' },
  { id: 'freigabe', label: t('grossanlass.planung.tabFreigabe'), icon: 'mdi-check-decagram-outline' },
])

const activeTab = computed(() => (route.meta.einstellungenTab as string) || 'ressorts')

function onTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id || typeof tab !== 'string') return
  void router.push(`/${id}/einstellungen/${tab}`)
}
</script>

<style scoped>
.grossanlass-einstellungen-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}
</style>
