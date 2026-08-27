<template>
  <div v-if="dayTitle" class="ga-slot">
    <div class="ga-slot__head">
      <strong>{{ dayTitle }}</strong>
      <span>{{ legend }}</span>
    </div>
    <div class="ga-slot__hours" aria-hidden="true">
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
    <p v-if="continues" class="ga-slot__note">{{ continues }}</p>
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

const props = defineProps<{
  objectName: string
  fromDate: string
  toDate: string
  fromIso: string
  toIso: string
  bookings: GaPreviewEinsatz[]
  clash: boolean
}>()

const { t, locale } = useI18n()

const windowRange = computed(() => {
  if (!props.fromDate) return null
  return calendarWindow('day', parseLocalDate(`${props.fromDate}T00:00:00`))
})

const columns = computed(() => {
  if (!windowRange.value) return []
  return calendarColumns('day', windowRange.value.start, windowRange.value.end, locale.value)
})

const dayTitle = computed(() => {
  if (!windowRange.value) return ''
  return formatCalendarTitle('day', windowRange.value.start, windowRange.value.end, locale.value)
})

const legend = computed(() =>
  props.clash
    ? t('grossanlass.materialUebersicht.slotLegendClash')
    : t('grossanlass.materialUebersicht.slotLegendFree'),
)

const continues = computed(() => {
  if (!props.fromDate || !props.toDate || props.fromDate === props.toDate) return ''
  const to = parseLocalDate(`${props.toDate}T00:00:00`)
  return t('grossanlass.materialUebersicht.slotContinues', {
    to: to.toLocaleDateString(locale.value, { day: 'numeric', month: 'short' }),
  })
})

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

.ga-slot__pick--clash {
  background: var(--color-error, #b91c1c);
}

.ga-slot__note {
  margin: 6px 0 0;
  font-size: 0.75rem;
  color: #6b7280;
}
</style>
