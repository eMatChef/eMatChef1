<template>
  <nav class="profile-tabs" role="tablist" :aria-label="t('profile.page.tabsAria')">
    <component
      :is="mode === 'page' ? 'router-link' : 'button'"
      v-for="item in PROFILE_TABS"
      :key="item.tab"
      v-bind="itemProps(item)"
      class="profile-tabs__tab"
      :class="{ 'profile-tabs__tab--active': item.tab === active }"
      role="tab"
      :aria-selected="item.tab === active"
      :data-testid="`profile-tab-${item.tab}`"
      @click="mode === 'modal' && emit('select', item.tab)"
    >
      {{ t(`profile.page.tabs.${item.tab}`) }}
    </component>
  </nav>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { PROFILE_TABS, type ProfileTab } from '@/composables/useProfileContext'

const props = defineProps<{
  mode: 'page' | 'modal'
  active: ProfileTab
  /** Query kept across the routed tabs (return path, running tour). */
  query?: Record<string, string>
}>()
const emit = defineEmits<{ (e: 'select', tab: ProfileTab): void }>()
const { t } = useI18n()

function itemProps(item: { tab: ProfileTab; routeName: string }) {
  return props.mode === 'page' ? { to: { name: item.routeName, query: props.query } } : { type: 'button' }
}
</script>

<style scoped>
.profile-tabs {
  display: flex;
  gap: 4px;
  overflow-x: auto;
  border-bottom: 1px solid var(--color-border);
  scrollbar-width: thin;
}

.profile-tabs__tab {
  flex: 0 0 auto;
  padding: 10px 14px;
  margin-bottom: -1px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  font: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text-muted);
  text-decoration: none;
  white-space: nowrap;
  cursor: pointer;
}

.profile-tabs__tab:hover {
  color: var(--color-text);
}

.profile-tabs__tab:focus-visible {
  outline: 2px solid var(--color-primary-light);
  outline-offset: -2px;
}

.profile-tabs__tab--active {
  color: var(--color-primary-dark);
  border-bottom-color: var(--color-primary);
}
</style>
