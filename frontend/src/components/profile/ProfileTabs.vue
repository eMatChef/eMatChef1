<template>
  <v-tabs
    :model-value="active"
    class="materials-view-tabs profile-tabs"
    color="primary"
    show-arrows
    role="tablist"
    :aria-label="t('profile.page.tabsAria')"
  >
    <v-tab
      v-for="item in PROFILE_TABS"
      :key="item.tab"
      :value="item.tab"
      v-bind="itemProps(item)"
      :data-testid="`profile-tab-${item.tab}`"
      @click="mode === 'modal' && emit('select', item.tab)"
    >
      {{ t(`profile.page.tabs.${item.tab}`) }}
    </v-tab>
  </v-tabs>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY } from '@/config/onboardingTours'
import { PROFILE_TABS, profileTabForRouteName, type ProfileTab } from '@/composables/useProfileContext'
import { PROFILE_FROM_PARAM, profileFromQuery } from '@/utils/profileReturn'
import '@/styles/views/materials-view-tabs.css'

const props = defineProps<{
  mode: 'page' | 'modal'
  /** Modal: active area (the routed page derives it from the route). */
  tab?: ProfileTab
}>()
const emit = defineEmits<{ (e: 'select', tab: ProfileTab): void }>()
const { t } = useI18n()
const route = useRoute()

const active = computed<ProfileTab>(() => (props.mode === 'page' ? profileTabForRouteName(route.name) : (props.tab ?? 'basics')))

/** Routed tabs keep the validated return path and a running onboarding tour. */
const routedQuery = computed(() => {
  const query: Record<string, string> = {}
  const from = profileFromQuery(route.query)
  if (from) query[PROFILE_FROM_PARAM] = from
  for (const key of [ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY]) {
    const value = route.query[key]
    if (typeof value === 'string') query[key] = value
  }
  return query
})

function itemProps(item: { tab: ProfileTab; routeName: string }) {
  return props.mode === 'page' ? { to: { name: item.routeName, query: routedQuery.value } } : {}
}
</script>
