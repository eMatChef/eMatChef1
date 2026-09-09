<template>
  <div v-if="dayTitle" class="ga-slot" :class="{ 'ga-slot--compact': compact }">
    <div v-if="compact || showLegend" class="ga-slot__head">
      <strong>{{ dayTitle }}</strong>
      <span v-if="showLegend">{{ legend }}</span>
    </div>
    <div v-if="showHours" class="ga-slot__hours" aria-hidden="true">
      <span v-for="column in columns" :key="column.key">{{ showHour(column.label) ? column.label : '' }}</span>
    </div>
    <div class="ga-slot__track">
      <span
        v-for="column in columns"
        :key="column.key"
        class="ga-slot__tick"
      />
      <span
        v-for="bar in busyBars"
        :key="bar.id"
        class="ga-slot__busy"
        :style="bar.style"
        :title="bar.title"
      />
      <span
        v-if="pickStyle"
        class="ga-slot__pick"
        :class="{ 'ga-slot__pick--clash': clash }"
        :style="pickStyle"
        :title="pickTitle"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  barStyleInWindow,
  calendarColumns,
  calendarWindow,
  formatCalendarTitle,
  parseLocalDate,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'

const props = withDefaults(defineProps<{
  objectName: string
  fromDate: string
  toDate: string
  fromIso: string
  toIso: string
  bookings: GaPreviewEinsatz[]
  clash: boolean
  compact?: boolean
  showHours?: boolean
  showLegend?: boolean
}>(), {
  compact: false,
  showHours: true,
  showLegend: true,
})

const { t, locale } = useI18n()

const windowRange = computed(() => {
  if (!props.fromDate) return null
  const date = parseLocalDate(`${props.fromDate}T00:00:00`)
  if (Number.isNaN(date.getTime())) return null
  return calendarWindow('day', date)
})

const columns = computed(() => {
  if (!windowRange.value) return []
  return calendarColumns('day', windowRange.value.start, windowRange.value.end, locale.value)
})

const dayTitle = computed(() => {
  if (!windowRange.value) return ''
  if (props.compact) {
    return windowRange.value.start.toLocaleDateString(locale.value, {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
    }).replace(/\.$/, '')
  }
  return formatCalendarTitle('day', windowRange.value.start, windowRange.value.end, locale.value)
})

const legend = computed(() =>
  props.clash
    ? t('grossanlass.materialUebersicht.slotLegendClash')
    : t('grossanlass.materialUebersicht.slotLegendFree'),
)

const busyBars = computed(() => {
  if (!windowRange.value) return []
  return props.bookings.flatMap((booking) => {
    const style = barStyleInWindow(booking, windowRange.value!.start, windowRange.value!.end, 'day')
    if (!style) return []
    return [{
      id: booking.id,
      style,
      title: `${booking.who} · ${booking.ressort} · ${booking.fromLabel} – ${booking.toLabel}`,
    }]
  })
})

const pickStyle = computed(() => {
  if (!windowRange.value || !props.fromIso || !props.toIso) return null
  return barStyleInWindow(
    { fromIso: props.fromIso, toIso: props.toIso } as GaPreviewEinsatz,
    windowRange.value.start,
    windowRange.value.end,
    'day',
  )
})

const pickTitle = computed(() =>
  t('grossanlass.materialUebersicht.slotPickTitle', { name: props.objectName }),
)

function showHour(label: string): boolean {
  const hour = Number(label)
  return Number.isFinite(hour) && hour % 3 === 0
}
</script>

<style scoped>
.ga-slot {
  margin: 4px 0 12px;
}

.ga-slot--compact {
  display: grid;
  grid-template-columns: 5.6rem minmax(0, 1fr);
  gap: 6px;
  align-items: center;
  margin: 4px 0;
}

.ga-slot--compact .ga-slot__head {
  margin: 0;
}

.ga-slot--compact .ga-slot__hours {
  display: none;
}

.ga-slot__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 4px 12px;
  margin-bottom: 6px;
  font-size: 0.78rem;
  color: #6b7280;
}

.ga-slot__head strong {
  color: #111827;
  font-weight: 600;
}

.ga-slot--compact .ga-slot__head strong {
  font-size: 0.68rem;
  line-height: 1.2;
  white-space: nowrap;
}

.ga-slot__hours {
  display: grid;
  grid-template-columns: repeat(24, minmax(0, 1fr));
  margin-bottom: 2px;
  font-size: 0.55rem;
  font-variant-numeric: tabular-nums;
  color: #6b7280;
  text-align: center;
}

.ga-slot__track {
  position: relative;
  display: grid;
  grid-template-columns: repeat(24, minmax(0, 1fr));
  height: 32px;
  overflow: hidden;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
}

.ga-slot--compact .ga-slot__track {
  height: 26px;
}

.ga-slot__tick {
  border-left: 1px solid #d1d5db;
}

.ga-slot__tick:first-child {
  border-left: 0;
}

.ga-slot__busy,
.ga-slot__pick {
  position: absolute;
  left: 0;
  box-sizing: border-box;
  border-radius: 3px;
}

.ga-slot__busy {
  top: 3px;
  height: 11px;
  background: #6b7280;
}

.ga-slot__pick {
  top: 16px;
  height: 12px;
  background: var(--color-primary, #0f766e);
}

.ga-slot--compact .ga-slot__busy {
  top: 2px;
  height: 9px;
}

.ga-slot--compact .ga-slot__pick {
  top: 13px;
  height: 10px;
}

.ga-slot__pick--clash {
  background: var(--color-error, #b91c1c);
}
</style>
