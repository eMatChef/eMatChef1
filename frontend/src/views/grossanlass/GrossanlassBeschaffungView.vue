<template>
  <PageShell
    class="grossanlass-beschaffung-shell"
    :title="t('grossanlass.beschaffung.title')"
    :subtitle="t('grossanlass.beschaffung.subtitle')"
  >
    <template #filters>
      <div class="materials-view-filters-stack">
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
        <nav v-if="showPaths" class="beschaffung-paths" :aria-label="t('grossanlass.beschaffung.pathsAria')">
          <p class="beschaffung-paths__row">
            <span class="beschaffung-paths__kind">{{ t('grossanlass.beschaffung.pathPartner') }}</span>
            <span class="beschaffung-paths__steps">
              <template v-for="(step, index) in partnerPath" :key="'p-' + step.id">
                <span v-if="index > 0" class="beschaffung-paths__arrow" aria-hidden="true">→</span>
                <router-link
                  :to="`/${departmentId}/beschaffung/${step.id}`"
                  class="beschaffung-paths__step"
                  :class="{ 'is-here': activeTab === step.id }"
                  :aria-current="activeTab === step.id ? 'page' : undefined"
                >{{ step.label }}</router-link>
              </template>
            </span>
          </p>
          <p class="beschaffung-paths__row">
            <span class="beschaffung-paths__kind">{{ t('grossanlass.beschaffung.pathBuy') }}</span>
            <span class="beschaffung-paths__steps">
              <template v-for="(step, index) in buyPath" :key="'b-' + step.id">
                <span v-if="index > 0" class="beschaffung-paths__arrow" aria-hidden="true">→</span>
                <router-link
                  :to="`/${departmentId}/beschaffung/${step.id}`"
                  class="beschaffung-paths__step"
                  :class="{ 'is-here': activeTab === step.id }"
                  :aria-current="activeTab === step.id ? 'page' : undefined"
                >{{ step.label }}</router-link>
              </template>
            </span>
          </p>
        </nav>
      </div>
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
  ]
})

const showPaths = computed(() => tabItems.value.length > 1)

const partnerPath = computed(() => [
  { id: 'bedarf', label: t('grossanlass.beschaffung.tabBedarf') },
  { id: 'anfragen', label: t('grossanlass.beschaffung.tabAnfragen') },
  { id: 'zusagen', label: t('grossanlass.beschaffung.tabZusagen') },
])

const buyPath = computed(() => [
  { id: 'bedarf', label: t('grossanlass.beschaffung.tabBedarf') },
  { id: 'offerten', label: t('grossanlass.beschaffung.tabOfferten') },
  { id: 'bestellungen', label: t('grossanlass.beschaffung.tabBestellungen') },
])

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
.beschaffung-paths {
  display: grid;
  gap: 4px;
  padding: 2px 2px 4px;
  font-size: 0.78rem;
  color: #64748b;
}
.beschaffung-paths__row {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px 10px;
  margin: 0;
}
.beschaffung-paths__kind {
  flex: 0 0 4.5rem;
  font-weight: 700;
  color: #475569;
}
.beschaffung-paths__steps {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 6px;
}
.beschaffung-paths__arrow {
  color: #cbd5e1;
}
.beschaffung-paths__step {
  color: var(--color-primary-dark, #166534);
  text-decoration: none;
}
.beschaffung-paths__step:hover {
  text-decoration: underline;
}
.beschaffung-paths__step.is-here {
  font-weight: 700;
  color: #0f172a;
  text-decoration: none;
}
</style>
