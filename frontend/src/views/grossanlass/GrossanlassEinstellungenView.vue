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
        <v-tab
          v-for="tab in tabItems"
          :key="tab.id"
          :value="tab.id"
          :class="{ 'ga-tab-locked': tab.locked }"
          :aria-disabled="tab.locked ? 'true' : undefined"
          :title="tab.locked ? t('grossanlass.einstellungen.lockedHint') : undefined"
          :data-onboarding="`ga-setup-tab-${tab.id}`"
        >
          <v-icon :icon="tab.locked ? 'mdi-lock-outline' : tab.icon" start size="18" />
          {{ tab.label }}
        </v-tab>
      </v-tabs>
      <p v-if="setupPending" class="ga-tab-locked-note">{{ t('grossanlass.einstellungen.lockedNote') }}</p>
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
import { gaIsMailboxOnly } from '@/utils/grossanlassAccess'
import { buildGrossanlassEinstellungenTabs } from '@/utils/grossanlassEinstellungenTabs'
import { useToast } from '@/composables/useToast'
import PageShell from '@/components/layout/PageShell.vue'
import '@/styles/views/materials-view-tabs.css'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const { hasGuestDepartments, known, refresh } = useGrossanlassGuestDepartments(() => departmentId.value)

const setupPending = computed(() => authStore.isGrossanlassSetupPending(departmentId.value))

const tabItems = computed(() =>
  buildGrossanlassEinstellungenTabs({
    role: authStore.currentDepartmentRole,
    setupPending: setupPending.value,
    guestTabsVisible: !known.value || hasGuestDepartments.value,
  }).map((tab) => ({ ...tab, label: t(tab.labelKey) })),
)

const activeTab = computed(() => (route.meta.einstellungenTab as string) || 'general')

function redirectIfGuestTabHidden() {
  const id = departmentId.value
  if (!id) return
  if (!gaIsMailboxOnly(authStore.currentDepartmentRole) && !known.value) return
  const usable = tabItems.value.filter((tab) => !tab.locked)
  const allowed = new Set(usable.map((tab) => tab.id))
  if (allowed.has(activeTab.value as never)) return
  const fallback = usable[0]?.id || 'general'
  void router.replace(`/${id}/ga/activity-settings/${fallback}`)
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
  if (tabItems.value.find((item) => item.id === tab)?.locked) {
    toast.info(t('grossanlass.einstellungen.lockedHint'))
    return
  }
  void router.push(`/${id}/ga/activity-settings/${tab}`)
}
</script>

<style scoped>
.ga-tab-locked {
  opacity: 0.5;
  cursor: not-allowed;
}

.ga-tab-locked-note {
  margin: 8px 0 0;
  font-size: 13px;
  color: #6b7280;
}

.grossanlass-einstellungen-shell :deep(.page-shell__header) {
  margin-bottom: 16px;
}
</style>
