<template>
  <div class="profile-page" data-onboarding="profile-page">
    <header class="profile-page__header">
      <h1 class="profile-page__title">{{ t('profile.page.title') }}</h1>
      <button type="button" class="btn-secondary btn-sm" data-testid="profile-back" @click="goBack">
        {{ t('profile.page.back') }}
      </button>
    </header>

    <nav class="profile-page__tabs" :aria-label="t('profile.page.tabsAria')">
      <router-link
        v-for="tab in tabs"
        :key="tab.name"
        :to="{ name: tab.name, query: tabQuery }"
        class="profile-page__tab"
        :data-testid="`profile-tab-${tab.slug}`"
      >
        {{ t(`profile.page.tabs.${tab.slug}`) }}
      </router-link>
    </nav>

    <section class="profile-page__panel">
      <router-view />
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY } from '@/config/onboardingTours'
import { PROFILE_FROM_PARAM, profileFallbackPath, profileFromQuery } from '@/utils/profileReturn'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const tabs = [
  { name: 'Profile', slug: 'basics' },
  { name: 'ProfileSecurity', slug: 'security' },
  { name: 'ProfileEmails', slug: 'emails' },
  { name: 'ProfileIdentities', slug: 'identities' },
  { name: 'ProfileDepartments', slug: 'departments' },
] as const

/** Tabs keep the validated return path and a running onboarding tour. */
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

function goBack() {
  void router.push(profileFromQuery(route.query) ?? profileFallbackPath(authStore.activeDepartmentId))
}
</script>

<style scoped>
.profile-page {
  max-width: 760px;
  margin: 0 auto;
  padding: 16px 16px 32px;
}

.profile-page__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.profile-page__title {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #1f2937;
}

.profile-page__tabs {
  display: flex;
  gap: 4px;
  overflow-x: auto;
  border-bottom: 1px solid #e5e7eb;
  scrollbar-width: thin;
}

.profile-page__tab {
  flex: 0 0 auto;
  padding: 10px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #64748b;
  text-decoration: none;
  white-space: nowrap;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}

.profile-page__tab:hover {
  color: #334155;
}

.profile-page__tab.router-link-exact-active {
  color: #1d4ed8;
  border-bottom-color: #1d4ed8;
}

.profile-page__panel {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-top: 0;
  border-radius: 0 0 10px 10px;
  padding: 14px 16px;
}
</style>
