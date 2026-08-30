<template>
  <PageShell
    class="grossanlass-beschaffung-shell"
    :title="t('grossanlass.beschaffung.title')"
    :subtitle="t('grossanlass.beschaffung.subtitle')"
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
import { gaCanManageProcurement } from '@/utils/grossanlassAccess'
import PageShell from '@/components/layout/PageShell.vue'
import '@/styles/views/materials-view-tabs.css'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const tabItems = computed(() => {
  const anfragen = { id: 'anfragen', label: t('grossanlass.beschaffung.tabAnfragen'), icon: 'mdi-email-multiple-outline' }
  if (!gaCanManageProcurement(authStore.currentDepartmentRole)) {
    return [anfragen]
  }
  return [
    { id: 'bedarf', label: t('grossanlass.beschaffung.tabBedarf'), icon: 'mdi-clipboard-list-outline' },
    anfragen,
    { id: 'offerten', label: t('grossanlass.beschaffung.tabOfferten'), icon: 'mdi-file-document-outline' },
    { id: 'zusagen', label: t('grossanlass.beschaffung.tabZusagen'), icon: 'mdi-handshake-outline' },
    { id: 'bestellungen', label: t('grossanlass.beschaffung.tabBestellungen'), icon: 'mdi-cart-outline' },
    { id: 'erhalten', label: t('grossanlass.beschaffung.tabErhalten'), icon: 'mdi-package-check' },
  ]
})

const activeTab = computed(() => (route.meta.beschaffungTab as string) || 'bedarf')

function onTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id || typeof tab !== 'string') return
  void router.push(`/${id}/beschaffung/${tab}`)
}
</script>

<style scoped>
.grossanlass-beschaffung-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}
</style>
