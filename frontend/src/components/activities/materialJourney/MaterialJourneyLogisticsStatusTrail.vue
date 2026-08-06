<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  activityStatus: string
}>()

const { t } = useI18n()

type LogisticsStatusKey =
  | 'packed'
  | 'transport_out'
  | 'at_event'
  | 'transport_back'
  | 'returned'
  | 'storing'

const LOGISTICS_STATUS_ORDER: LogisticsStatusKey[] = [
  'packed',
  'transport_out',
  'at_event',
  'transport_back',
  'returned',
  'storing',
]

const activeKey = computed((): LogisticsStatusKey | null => {
  const s = props.activityStatus
  if (s === 'packing') return null
  if (s === 'packed') return 'packed'
  if (s === 'transport_out') return 'transport_out'
  if (s === 'at_event') return 'at_event'
  if (s === 'transport_back') return 'transport_back'
  if (s === 'returned') return 'returned'
  if (s === 'storing' || s === 'completed') return 'storing'
  return null
})

const activeIndex = computed(() => {
  const key = activeKey.value
  if (!key) return -1
  return LOGISTICS_STATUS_ORDER.indexOf(key)
})

function stepState(index: number): 'done' | 'active' | 'future' {
  const active = activeIndex.value
  if (active < 0) return 'future'
  if (index < active) return 'done'
  if (index === active) return 'active'
  return 'future'
}

function labelFor(key: LogisticsStatusKey): string {
  return t(`activities.status.${key}`)
}
</script>

<template>
  <nav
    v-if="activeKey"
    class="material-journey-logistics-status-trail"
    :aria-label="t('activities.materialJourney.logisticsStatusTrail.aria')"
  >
    <ol class="material-journey-logistics-status-trail__list">
      <li
        v-for="(key, index) in LOGISTICS_STATUS_ORDER"
        :key="key"
        class="material-journey-logistics-status-trail__item"
        :class="`material-journey-logistics-status-trail__item--${stepState(index)}`"
      >
        <span class="material-journey-logistics-status-trail__label">{{ labelFor(key) }}</span>
        <span
          v-if="index < LOGISTICS_STATUS_ORDER.length - 1"
          class="material-journey-logistics-status-trail__sep"
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

.material-journey-logistics-status-trail {
  margin-bottom: 12px;
}

.material-journey-logistics-status-trail__list {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 6px;
  margin: 0;
  padding: 10px 14px;
  list-style: none;
  border-radius: 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgba(var(--v-theme-surface-variant), 0.35);
  font-size: 12px;
}

.material-journey-logistics-status-trail__item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.material-journey-logistics-status-trail__label {
  padding: 2px 7px;
  border-radius: 999px;
  font-weight: 500;
  white-space: nowrap;
}

.material-journey-logistics-status-trail__item--future .material-journey-logistics-status-trail__label {
  color: rgba(var(--v-theme-on-surface), 0.45);
}

.material-journey-logistics-status-trail__item--active .material-journey-logistics-status-trail__label {
  background: var(--activity-status-at_event-bg, #a7f3d0);
  color: var(--activity-status-at_event-fg, #047857);
}

.material-journey-logistics-status-trail__item--done .material-journey-logistics-status-trail__label {
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.material-journey-logistics-status-trail__sep {
  color: rgba(var(--v-theme-on-surface), 0.35);
  font-size: 11px;
}
</style>
