<template>
  <section class="timeline">
    <div class="timeline__controls">
      <EDateField v-model="startDate" :label="t('grossanlass.helferpool.timeline.start')" allow-past />
      <ESelect v-model="days" :items="dayItems" :label="t('grossanlass.helferpool.timeline.days')" hide-details />
    </div>

    <EEmptyState v-if="!helpers.length" variant="generic" icon="mdi-chart-gantt" :title="t('grossanlass.helferpool.timeline.emptyTitle')" :description="t('grossanlass.helferpool.timeline.emptyText')" />

    <div v-else class="timeline__scroll">
      <div class="timeline__row timeline__row--head">
        <span class="timeline__label" />
        <span class="timeline__track" :style="{ minWidth: `${days * 96}px` }">
          <span v-for="(day, index) in dayCells" :key="index" class="timeline__day" :style="{ left: `${(index / days) * 100}%`, width: `${100 / days}%` }">
            {{ day.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'numeric' }) }}
          </span>
        </span>
      </div>
      <div v-for="helper in helpers" :key="helper.helper_id" class="timeline__row">
        <button type="button" class="timeline__label" @click="emit('open', helper.helper_id)">
          <strong>{{ helper.name }}</strong>
          <v-chip v-if="helper.status === 'archived'" size="x-small" variant="flat" color="grey">{{ t('grossanlass.helferpool.helperStatus.archived') }}</v-chip>
        </button>
        <span class="timeline__track" :style="{ minWidth: `${days * 96}px` }">
          <span v-for="window in helper.availability" :key="window.starts_at" class="timeline__avail" :style="barStyle(window.starts_at, window.ends_at, range.start, days)" />
          <button
            v-for="entry in helper.assignments"
            :key="entry.id"
            type="button"
            class="timeline__block"
            :class="{ 'timeline__block--override': entry.has_override }"
            :style="barStyle(entry.starts_at, entry.ends_at, range.start, days)"
            :title="`${entry.task_title ?? t('grossanlass.helferpool.needs.projectNeed')} · ${entry.label} · ${windowLabel(entry.starts_at, entry.ends_at, locale)}`"
            @click="emit('open', helper.helper_id)"
          >{{ entry.task_title ?? entry.label }}</button>
        </span>
      </div>
    </div>
    <ul class="timeline__legend">
      <li><span class="sw sw--avail" /> {{ t('grossanlass.helferpool.legend.available') }}</li>
      <li><span class="sw sw--task" /> {{ t('grossanlass.helferpool.legend.assigned') }}</li>
      <li><span class="sw sw--override" /> {{ t('grossanlass.helferpool.legend.override') }}</li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { GaScheduleHelper } from '@/api/grossanlassStaffing'
import { EDateField, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { barStyle, dayList, scheduleRange, windowLabel } from './gaHelferUi'

/** Zeitplan: Verfügbarkeit (hell) und Zuteilungen (Balken) je Helfer im gewählten Zeitraum. Daten kommen vom Zeitplan-Endpunkt. */
defineProps<{ helpers: GaScheduleHelper[] }>()
const emit = defineEmits<{ open: [helperId: string] }>()
const startDate = defineModel<string>('start', { required: true })
const days = defineModel<number>('days', { default: 7 })
const { t, locale } = useI18n()

const dayItems = computed(() => [7, 14, 30].map((n) => ({ value: n, title: t('grossanlass.helferpool.timeline.nDays', { n }) })))
const range = computed(() => scheduleRange(startDate.value, days.value))
const dayCells = computed(() => dayList(range.value.start, days.value))
</script>

<style scoped>
.timeline__controls {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 12px;
}
.timeline__controls > * {
  flex: 0 1 220px;
}
.timeline__scroll {
  overflow-x: auto;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.timeline__row {
  display: flex;
  border-top: 1px solid #f1f5f9;
}
.timeline__row--head {
  border-top: 0;
  background: #f8fafc;
}
.timeline__label {
  flex: 0 0 160px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 10px;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
  position: sticky;
  left: 0;
  z-index: 1;
  background: #fff;
}
.timeline__row--head .timeline__label {
  background: #f8fafc;
  cursor: default;
}
.timeline__track {
  position: relative;
  flex: 1 1 auto;
  min-height: 44px;
}
.timeline__day {
  position: absolute;
  top: 0;
  bottom: 0;
  padding: 8px 6px;
  border-left: 1px solid #e5e7eb;
  font-size: 0.75rem;
  color: #64748b;
}
.timeline__avail {
  position: absolute;
  top: 8px;
  bottom: 8px;
  background: #dcfce7;
  border-radius: 6px;
}
.timeline__block {
  position: absolute;
  top: 12px;
  bottom: 12px;
  overflow: hidden;
  padding: 0 6px;
  border: 0;
  border-radius: 6px;
  background: #059669;
  color: #fff;
  font-size: 0.74rem;
  white-space: nowrap;
  text-overflow: ellipsis;
  cursor: pointer;
}
.timeline__block--override {
  background: #d97706;
}
.timeline__legend {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin: 10px 0 0;
  padding: 0;
  list-style: none;
  font-size: 0.82rem;
  color: #64748b;
}
.sw {
  display: inline-block;
  width: 14px;
  height: 10px;
  margin-right: 4px;
  border-radius: 3px;
}
.sw--avail {
  background: #dcfce7;
}
.sw--task {
  background: #059669;
}
.sw--override {
  background: #d97706;
}
</style>
