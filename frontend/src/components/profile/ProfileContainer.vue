<template>
  <div class="profile-container" :class="`profile-container--${mode}`" data-onboarding="profile-page">
    <ProfileTabs :mode="mode" :active="activeTab" :query="tabQuery" @select="selectTab" />
    <section class="profile-container__panel" role="tabpanel">
      <router-view v-if="mode === 'page'" />
      <template v-else>
        <!-- Modal: Tabs wechseln ohne Neuladen; besuchte Bereiche bleiben im Speicher (KeepAlive) -->
        <KeepAlive>
          <component :is="views[activeTab]" :key="activeTab" />
        </KeepAlive>
      </template>
    </section>
  </div>
</template>

<script setup lang="ts">
/**
 * Gemeinsamer Profil-Container für beide Darstellungen: geroutete Seite (`page`) und Dialog über der aktuellen
 * Seite (`modal`). Die Bereiche selbst (Views, Sektionen, API) sind für beide dieselben.
 */
import { computed, defineAsyncComponent, provide } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY } from '@/config/onboardingTours'
import {
  PROFILE_CONTEXT_KEY,
  PROFILE_TABS,
  profileTabForRouteName,
  type ProfileContext,
  type ProfileTab,
} from '@/composables/useProfileContext'
import { PROFILE_FROM_PARAM, profileFallbackPath, profileFromQuery } from '@/utils/profileReturn'
import ProfileTabs from '@/components/profile/ProfileTabs.vue'

const props = withDefaults(defineProps<{ mode: 'page' | 'modal'; tab?: ProfileTab }>(), { tab: 'basics' })
const emit = defineEmits<{
  (e: 'update:tab', tab: ProfileTab): void
  (e: 'close'): void
  (e: 'dirty', dirty: boolean): void
}>()

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const views: Record<ProfileTab, ReturnType<typeof defineAsyncComponent>> = {
  basics: defineAsyncComponent(() => import('@/views/profile/ProfileBasicsView.vue')),
  security: defineAsyncComponent(() => import('@/views/profile/ProfileSecurityView.vue')),
  emails: defineAsyncComponent(() => import('@/views/profile/ProfileEmailsView.vue')),
  identities: defineAsyncComponent(() => import('@/views/profile/ProfileIdentitiesView.vue')),
  departments: defineAsyncComponent(() => import('@/views/profile/ProfileDepartmentsView.vue')),
}

const activeTab = computed<ProfileTab>(() => (props.mode === 'page' ? profileTabForRouteName(route.name) : props.tab))

/** Routed tabs keep the validated return path and a running onboarding tour. */
const tabQuery = computed(() => {
  const query: Record<string, string> = {}
  const from = profileFromQuery(route.query)
  if (from) query[PROFILE_FROM_PARAM] = from
  for (const key of [ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY]) {
    const value = route.query[key]
    if (typeof value === 'string') query[key] = value
  }
  return query
})

function selectTab(tab: ProfileTab) {
  emit('update:tab', tab)
}

const context: ProfileContext = {
  get mode() {
    return props.mode
  },
  openTab(tab) {
    if (props.mode === 'modal') {
      emit('update:tab', tab)
      return
    }
    const routeName = PROFILE_TABS.find((t) => t.tab === tab)?.routeName ?? 'Profile'
    void router.push({ name: routeName, query: tabQuery.value })
  },
  leave() {
    if (props.mode === 'modal') {
      emit('close')
      return
    }
    return router.push(profileFromQuery(route.query) ?? profileFallbackPath(authStore.activeDepartmentId)).then(() => undefined)
  },
  setDirty(dirty) {
    emit('dirty', dirty)
  },
}
provide(PROFILE_CONTEXT_KEY, context)
</script>

<style scoped>
.profile-container__panel {
  padding: 16px 0 0;
}

.profile-container--page .profile-container__panel {
  padding: 16px;
  border: 1px solid var(--color-border);
  border-top: 0;
  border-radius: 0 0 10px 10px;
  background: rgb(var(--v-theme-surface));
}
</style>
