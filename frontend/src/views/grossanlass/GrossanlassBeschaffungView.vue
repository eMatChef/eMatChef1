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
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import PageShell from '@/components/layout/PageShell.vue'
import '@/styles/views/materials-view-tabs.css'
import { getGrossanlassGroups } from '@/api/grossanlassGroups'
import { useGrossanlassProcurementScope } from '@/composables/useGrossanlassProcurementScope'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const groups = ref<Awaited<ReturnType<typeof getGrossanlassGroups>>>([])
const groupsRef = computed(() => groups.value)
const { canManageProcurement, hasProcurementDelegate } = useGrossanlassProcurementScope(groupsRef)

const allTabItems = [
  { id: 'bedarf', labelKey: 'grossanlass.beschaffung.tabBedarf', icon: 'mdi-clipboard-list-outline' },
  { id: 'anfragen', labelKey: 'grossanlass.beschaffung.tabAnfragen', icon: 'mdi-email-multiple-outline' },
  { id: 'offerten', labelKey: 'grossanlass.beschaffung.tabOfferten', icon: 'mdi-file-document-outline' },
  { id: 'zusagen', labelKey: 'grossanlass.beschaffung.tabZusagen', icon: 'mdi-handshake-outline' },
  { id: 'bestellungen', labelKey: 'grossanlass.beschaffung.tabBestellungen', icon: 'mdi-cart-outline' },
  { id: 'erhalten', labelKey: 'grossanlass.beschaffung.tabErhalten', icon: 'mdi-package-check' },
] as const

const tabItems = computed(() => {
  const items = canManageProcurement.value
    ? allTabItems
    : allTabItems.filter((tab) => tab.id === 'offerten')
  return items.map((tab) => ({
    id: tab.id,
    label: t(tab.labelKey),
    icon: tab.icon,
  }))
})

const activeTab = computed(() => (route.meta.beschaffungTab as string) || 'bedarf')

function onTabChange(tab: unknown) {
  const id = departmentId.value
  if (!id || typeof tab !== 'string') return
  void router.push(`/${id}/beschaffung/${tab}`)
}

async function ensureGroupsLoaded() {
  if (!departmentId.value) return
  try {
    groups.value = await getGrossanlassGroups(departmentId.value)
  } catch {
    groups.value = []
  }
}

watch(
  departmentId,
  async () => {
    await ensureGroupsLoaded()
    if (
      !canManageProcurement.value &&
      hasProcurementDelegate.value &&
      activeTab.value !== 'offerten'
    ) {
      void router.replace(`/${departmentId.value}/beschaffung/offerten`)
    }
  },
  { immediate: true },
)

onMounted(ensureGroupsLoaded)
</script>

<style scoped>
.grossanlass-beschaffung-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}
</style>
