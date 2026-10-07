<template>
  <aside class="feed" :aria-label="t('grossanlass.displays.feed.title')">
    <h3 class="feed__title"><span class="feed__live" />{{ t('grossanlass.displays.feed.title') }}</h3>
    <ul>
      <li v-for="event in events" :key="event.id" class="feed__item" :class="`feed__item--${event.severity}`">
        <time>{{ clock(event.at) }}</time>
        <div>
          <strong>{{ event.actor }}</strong>
          <span>{{ event.text }}</span>
          <small v-if="event.detail">{{ event.detail }}</small>
        </div>
      </li>
      <li v-if="!events.length" class="feed__empty">{{ t('grossanlass.displays.feed.empty') }}</li>
    </ul>
  </aside>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { GaLiveEvent } from '@/views/grossanlass/live/gaLiveEvents'

defineProps<{ events: GaLiveEvent[] }>()
const { t } = useI18n()
const pad = (n: number) => String(n).padStart(2, '0')
const clock = (date: Date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`
</script>

<style scoped>
.feed {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 0;
  padding: 18px;
  border-radius: 18px;
  background: #111c33;
  overflow: hidden;
}
.feed__title {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  font-size: 1.15rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #94a3b8;
}
.feed__live {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #ef4444;
  animation: blink 1.6s ease-in-out infinite;
}
@keyframes blink {
  50% {
    opacity: 0.25;
  }
}
ul {
  display: grid;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
  overflow: hidden;
}
.feed__item {
  display: grid;
  grid-template-columns: 64px minmax(0, 1fr);
  gap: 10px;
  padding-left: 10px;
  border-left: 4px solid #38bdf8;
  font-size: 1.1rem;
}
.feed__item--success {
  border-left-color: #22c55e;
}
.feed__item--warning {
  border-left-color: #f59e0b;
}
.feed__item--error {
  border-left-color: #ef4444;
}
.feed__item time {
  color: #94a3b8;
  font-variant-numeric: tabular-nums;
}
.feed__item div {
  display: flex;
  flex-direction: column;
}
.feed__item small {
  color: #94a3b8;
  font-size: 0.9rem;
}
.feed__empty {
  color: #64748b;
}
</style>
