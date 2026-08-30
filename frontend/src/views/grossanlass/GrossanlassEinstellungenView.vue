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
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useGrossanlassGuestDepartments } from '@/composables/useGrossanlassGuestDepartments'
import { gaCanManageProcurement, gaIsMailboxOnly } from '@/utils/grossanlassAccess'
import PageShell from '@/components/layout/PageShell.vue'
import '@/styles/views/materials-view-tabs.css'

const GUEST_TABS = new Set(['teilnehmer', 'freigabe'])

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const { hasGuestDepartments, known, refresh } = useGrossanlassGuestDepartments(() => departmentId.value)

const tabItems = computed(() => {
  if (gaIsMailboxOnly(authStore.currentDepartmentRole)) {
    return [
      { id: 'anfragen-email', label: t('grossanlass.einstellungen.tabAnfragenEmail'), icon: 'mdi-email-edit-outline' },
    ]
  }
  const all = [
    { id: 'stammdaten', label: t('grossanlass.planung.tabStammdaten'), icon: 'mdi-card-account-details-outline' },
    { id: 'ressorts', label: t('grossanlass.planung.tabRessorts'), icon: 'mdi-sitemap' },
    { id: 'karten', label: t('grossanlass.planung.tabKarten'), icon: 'mdi-card-account-details' },
    { id: 'standorte', label: t('grossanlass.einstellungen.tabStandorte'), icon: 'mdi-warehouse' },
    ...(gaCanManageProcurement(authStore.currentDepartmentRole)
      ? [{ id: 'kategorien', label: t('grossanlass.einstellungen.tabKategorien'), icon: 'mdi-folder-outline' }]
      : []),
    { id: 'anfragen-email', label: t('grossanlass.einstellungen.tabAnfragenEmail'), icon: 'mdi-email-edit-outline' },
    { id: 'teilnehmer', label: t('grossanlass.planung.tabTeilnehmer'), icon: 'mdi-account-group-outline' },
    { id: 'freigabe', label: t('grossanlass.planung.tabFreigabe'), icon: 'mdi-check-decagram-outline' },
  ]
  if (!known.value || hasGuestDepartments.value) return all
  return all.filter((tab) => !GUEST_TABS.has(tab.id))
})

const activeTab = computed(() => (route.meta.einstellungenTab as string) || 'stammdaten')

function redirectIfGuestTabHidden() {
  const id = departmentId.value
  if (!id) return
  if (!gaIsMailboxOnly(authStore.currentDepartmentRole) && !known.value) return
  const allowed = new Set(tabItems.value.map((tab) => tab.id))
  if (allowed.has(activeTab.value)) return
  const fallback = tabItems.value[0]?.id || 'stammdaten'
  void router.replace(`/${id}/einstellungen/${fallback}`)
}

onMounted(() => {
  void refresh().then(redirectIfGuestTabHidden)
})
watch(departmentId, () => {
  void refresh().then(redirectIfGuestTabHidden)
})
watch([activeTab, hasGuestDepartments], redirectIfGuestTabHidden)

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
