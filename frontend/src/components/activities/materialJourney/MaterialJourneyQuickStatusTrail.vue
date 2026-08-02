<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  activityStatus: string
}>()

const { t } = useI18n()

type QuickStatusKey = 'packed' | 'at_event' | 'returned' | 'storing'

const QUICK_STATUS_ORDER: QuickStatusKey[] = ['packed', 'at_event', 'returned', 'storing']

const activeKey = computed((): QuickStatusKey | null => {
  const s = props.activityStatus
  if (s === 'packing') return null
  if (s === 'packed') return 'packed'
  if (s === 'at_event') return 'at_event'
  if (s === 'returned') return 'returned'
  if (s === 'storing' || s === 'completed') return 'storing'
  return null
})

const activeIndex = computed(() => {
  const key = activeKey.value
  if (!key) return -1
  return QUICK_STATUS_ORDER.indexOf(key)
})

function stepState(index: number): 'done' | 'active' | 'future' {
  const active = activeIndex.value
  if (active < 0) return 'future'
  if (index < active) return 'done'
  if (index === active) return 'active'
  return 'future'
}

function labelFor(key: QuickStatusKey): string {
  if (key === 'at_event') {
    return t('activities.status.at_event_quick')
  }
  const statusKey = key === 'storing' ? 'storing' : key
  return t(`activities.status.${statusKey}`)
}
</script>

<template>
  <nav
    v-if="activeKey"
    class="material-journey-quick-status-trail"
    :aria-label="t('activities.materialJourney.quickStatusTrail.aria')"
  >
    <ol class="material-journey-quick-status-trail__list">
      <li
        v-for="(key, index) in QUICK_STATUS_ORDER"
        :key="key"
        class="material-journey-quick-status-trail__item"
        :class="`material-journey-quick-status-trail__item--${stepState(index)}`"
      >
        <span class="material-journey-quick-status-trail__label">{{ labelFor(key) }}</span>
        <span
          v-if="index < QUICK_STATUS_ORDER.length - 1"
          class="material-journey-quick-status-trail__sep"
          aria-hidden="true"
        >
          →
        </span>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-journey-quick-status-trail {
  margin-bottom: 12px;
}

.material-journey-quick-status-trail__list {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 8px;
  margin: 0;
  padding: 10px 14px;
  list-style: none;
  border-radius: 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-surface-variant), 0.35);
  font-size: 13px;
}

.material-journey-quick-status-trail__item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.material-journey-quick-status-trail__label {
  padding: 2px 8px;
  border-radius: 999px;
  font-weight: 500;
}

.material-journey-quick-status-trail__item--future .material-journey-quick-status-trail__label {
  color: rgba(var(--v-theme-on-surface), 0.45);
}

.material-journey-quick-status-trail__item--active .material-journey-quick-status-trail__label {
  background: var(--activity-status-at_event-bg, #a7f3d0);
  color: var(--activity-status-at_event-fg, #047857);
}

.material-journey-quick-status-trail__item--done .material-journey-quick-status-trail__label {
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.material-journey-quick-status-trail__sep {
  color: rgba(var(--v-theme-on-surface), 0.35);
  font-size: 12px;
}
</style>
