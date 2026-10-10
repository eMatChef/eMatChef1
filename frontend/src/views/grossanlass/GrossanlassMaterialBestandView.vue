<template>
  <div class="ga-bestand">
    <v-tabs
      :model-value="subTab"
      class="materials-view-tabs ga-bestand__tabs"
      color="primary"
      show-arrows
      @update:model-value="onSubTab"
    >
      <v-tab v-for="tab in subTabs" :key="tab.id" :value="tab.id">
        {{ tab.label }}
      </v-tab>
    </v-tabs>
    <router-view v-slot="{ Component }">
      <transition name="fade" mode="out-in">
        <component :is="Component" />
      </transition>
    </router-view>
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { gaBestandListPath } from '@/views/grossanlass/gaBestandPaths'
import '@/styles/views/materials-view-tabs.css'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const subTabs = computed(() => [
  { id: 'alles', label: t('grossanlass.material.tabAlles') },
  { id: 'eigen', label: t('grossanlass.materials.tabEigen') },
  { id: 'leihweise', label: t('grossanlass.materials.tabLeihweise') },
  { id: 'gaeste', label: t('grossanlass.materials.tabGaeste') },
  { id: 'js', label: t('grossanlass.materials.tabJs') },
])

const subTab = computed(() => (route.meta.bestandSubTab as string) || 'alles')

function onSubTab(tab: unknown) {
  const id = departmentId.value
  if (!id || typeof tab !== 'string') return
  void router.push(gaBestandListPath(id, tab))
}

watch(
  () => String(route.query.family || ''),
  (family) => {
    const id = departmentId.value
    if (family === 'vehicle' && id) {
      void router.replace(`/${id}/ga/fahrzeuge`)
    }
  },
  { immediate: true },
)
</script>

<style scoped>
.ga-bestand__tabs {
  margin-bottom: 8px;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.12s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
