<template>
  <div ref="rootEl" class="ga-cal-axis">
    <div v-if="monthBands.length" class="ga-cal-axis__months" :style="hoursGridStyle">
      <span
        v-for="band in monthBands"
        :key="band.key"
        class="ga-cal-axis__month"
        :style="{ gridColumn: `span ${band.days}` }"
      >
        {{ band.label }}
      </span>
    </div>
    <div v-if="dayPickerColumns.length" class="ga-cal-axis__picker">
      <button
        v-for="column in dayPickerColumns"
        :key="`pick-${column.key}`"
        type="button"
        class="ga-cal-axis__tick ga-cal-axis__tick--btn"
        :class="{
          'ga-cal-axis__tick--weekend': column.weekend,
          'ga-cal-axis__tick--active': column.ymd === anchorYmd,
        }"
        @click="emit('select-day', column.ymd, column.startMs)"
      >
        {{ column.label }}
        <small>{{ column.sub }}</small>
      </button>
    </div>
    <p v-if="caption" class="ga-cal-axis__caption">{{ caption }}</p>
    <div class="ga-cal-axis__hours" :style="hoursGridStyle">
      <template v-if="scale === 'day'">
        <span
          v-for="column in columns"
          :key="column.key"
          class="ga-cal-axis__tick ga-cal-axis__tick--hour"
        >
          {{ showHour(column.label) ? column.label : '' }}
        </span>
      </template>
      <button
        v-else
        v-for="column in columns"
        :key="column.key"
        type="button"
        class="ga-cal-axis__tick ga-cal-axis__tick--btn"
        :class="{
          'ga-cal-axis__tick--weekend': column.weekend,
          'ga-cal-axis__tick--month-start': column.monthStart,
        }"
        @click="emit('select-day', column.ymd, column.startMs)"
      >
        {{ column.label }}
        <small v-if="column.sub">{{ column.sub }}</small>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { GaCalendarScale } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import type { GaAxisColumn } from '@/views/grossanlass/gaCalendarAxis'

defineProps<{
  scale: GaCalendarScale
  columns: GaAxisColumn[]
  dayPickerColumns: GaAxisColumn[]
  monthBands: { key: string; label: string; days: number }[]
  anchorYmd: string
  hoursGridStyle?: Record<string, string>
  caption?: string
}>()

const emit = defineEmits<{
  'select-day': [ymd: string, startMs: number]
}>()

const rootEl = ref<HTMLElement | null>(null)

function showHour(label: string): boolean {
  const hour = Number(label)
  return Number.isFinite(hour) && hour % 3 === 0
}

defineExpose({ rootEl })
</script>

<style scoped>
.ga-cal-axis {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.ga-cal-axis__picker {
  display: flex;
  min-width: 0;
}

.ga-cal-axis__months,
.ga-cal-axis__hours {
  display: grid;
  min-width: 0;
}

.ga-cal-axis__month {
  padding: 2px 4px 0;
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--color-text, #111827);
  border-left: 1px solid #9ca3af;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ga-cal-axis__month:first-child {
  border-left: 0;
}

.ga-cal-axis__picker {
  border-bottom: 1px solid var(--ga-table-border, #e5e7eb);
}

.ga-cal-axis__caption {
  margin: 0 0 2px;
  padding: 0 2px;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--color-text-muted, #6b7280);
}

.ga-cal-axis__tick {
  flex: 1 1 0;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin: 0;
  padding: 2px 0;
  font: inherit;
  font-size: 0.68rem;
  line-height: 1.15;
  font-variant-numeric: tabular-nums;
  color: var(--color-text-muted, #6b7280);
  border: 0;
  border-left: 1px solid var(--ga-table-border, #e5e7eb);
  background: transparent;
}

.ga-cal-axis__tick:first-child {
  border-left: 0;
}

.ga-cal-axis__tick--btn {
  cursor: pointer;
}

.ga-cal-axis__tick--btn:hover {
  background: var(--ga-table-hover, #f3f4f6);
}

.ga-cal-axis__tick--hour {
  font-size: 0.55rem;
  font-weight: 600;
}

.ga-cal-axis__tick--weekend {
  background: var(--ga-table-hover, #f3f4f6);
}

.ga-cal-axis__tick--month-start {
  border-left-color: #9ca3af;
  border-left-width: 2px;
}

.ga-cal-axis__tick--active {
  background: var(--color-primary-muted-bg, #dcfce7);
  color: var(--color-primary-dark, #166534);
  font-weight: 700;
}

.ga-cal-axis__tick small {
  font-size: 0.55rem;
  font-weight: 600;
  text-transform: uppercase;
  color: inherit;
}
</style>
