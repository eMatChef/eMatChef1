<template>
  <SidebarNavigation v-model="drawerOpen" />
  <TopHeader v-if="!isActivityDetailView" v-model:drawer-open="drawerOpen" />

  <v-main
    class="page-main"
    :class="{
      'page-main--activity-detail': isActivityDetailView,
      'page-main--material-detail': isMaterialDetailView,
    }"
  >
    <TopHeader
      v-if="isActivityDetailView"
      v-model:drawer-open="drawerOpen"
      scroll-with-content
    />
    <div
      class="page-content"
      :class="{
        'page-content--activity-detail': isActivityDetailView,
        'page-content--material-detail': isMaterialDetailView,
      }"
    >
      <router-view v-slot="{ Component }">
        <keep-alive :include="['MaterialsView', 'ActivitiesView']" :max="8">
          <component :is="Component" :key="route.path" />
        </keep-alive>
      </router-view>
    </div>
  </v-main>

  <OnboardingTourOverlay v-if="canUseTours" />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUnsavedChangesReminder } from '@/composables/useUnsavedChangesReminder'
import { useDepartmentOnboardingAccess } from '@/composables/useDepartmentOnboardingAccess'
import { refreshOnboardingCompletionStatus } from '@/utils/onboardingChecklist'
import OnboardingTourOverlay from '@/components/onboarding/OnboardingTourOverlay.vue'
import SidebarNavigation from './SidebarNavigation.vue'
import TopHeader from './TopHeader.vue'

const route = useRoute()
const authStore = useAuthStore()

const isActivityDetailView = computed(() => {
  const name = route.name
  return name === 'ActivityDetail' || name === 'ActivityDetailTab' || name === 'ActivityPackJourney'
})

const isMaterialDetailView = computed(() => {
  if (route.name === 'MaterialDetail') return true
  return typeof route.params.materialId === 'string' && route.params.materialId.length > 0
})

useUnsavedChangesReminder()
const drawerOpen = ref(false)

const { departmentId, profileId, canUseOnboarding, canUseTours } = useDepartmentOnboardingAccess()

watch(
  [departmentId, profileId, () => authStore.isLoggedIn, () => authStore.currentDepartmentRole],
  ([depId, profId, loggedIn]) => {
    if (!loggedIn || !depId || !canUseOnboarding.value || !profId) return
    void refreshOnboardingCompletionStatus(profId, depId).catch(() => {})
  },
  { immediate: true }
)
</script>

<style scoped>
.page-main {
  flex: 1 1 auto !important;
  min-height: 0 !important;
  overflow-y: auto !important;
}

.page-main--activity-detail {
  --v-layout-top: 0px !important;
  padding-top: 0 !important;
  display: flex !important;
  flex-direction: column !important;
}

.page-main--activity-detail .page-content--activity-detail {
  flex: 1 1 auto;
  min-height: 0;
}

.page-content {
  padding: 8px 24px calc(24px + var(--emc-safe-bottom));
}

.page-content :deep(.v-container.page-shell) {
  padding: 0 !important;
}

@media (max-width: 599px) {
  .page-content {
    padding: 8px 12px calc(12px + var(--emc-safe-bottom));
  }
}

.page-content--activity-detail {
  padding: 0;
  padding-bottom: calc(12px + var(--emc-safe-bottom));
}

.page-main--material-detail {
  overflow: hidden !important;
  display: flex !important;
  flex-direction: column !important;
  min-height: 0 !important;
}

.page-main--material-detail :deep(.v-main__wrap) {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.page-content--material-detail {
  flex: 1;
  min-height: 0;
  height: 100%;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  padding: 0;
  padding-bottom: var(--emc-safe-bottom);
}

.page-content--material-detail > * {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
</style>
