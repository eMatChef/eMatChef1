<template>
  <div
    v-if="windowRange"
    class="zusage-align"
    :style="{ '--align-cols': String(columns.length) }"
  >
    <div v-if="showHead" class="zusage-align__head">
      <span class="zusage-align__lane-label" />
      <button
        type="button"
        class="zusage-align__nav"
        :aria-label="t('grossanlass.beschaffung.zusagen.alignPrev')"
        @click="shiftBy(-1)"
      >
        <v-icon icon="mdi-chevron-left" size="18" />
      </button>
      <div ref="headStackEl" class="zusage-align__head-stack" @scroll="syncScroll">
          <GrossanlassCalendarAxis
            :scale="scale"
            :columns="columns"
            :day-picker-columns="dayPickerColumns"
            :month-bands="monthBands"
            :anchor-ymd="anchorYmd"
            :hours-grid-style="hoursGridStyle"
            :caption="dayCaption"
            @select-day="onSelectDay"
          />
        </div>
      <button
        type="button"
        class="zusage-align__nav"
        :aria-label="t('grossanlass.beschaffung.zusagen.alignNext')"
        @click="shiftBy(1)"
      >
        <v-icon icon="mdi-chevron-right" size="18" />
      </button>
    </div>
    <template v-if="!headOnly">
    <div class="zusage-align__lane">
      <span class="zusage-align__lane-label">{{ t('grossanlass.beschaffung.zusagen.alignFirm') }}</span>
      <span class="zusage-align__nav-spacer" />
      <div class="zusage-align__track-scroll" @scroll="syncScroll">
        <div class="zusage-align__track">
          <span
            v-for="column in columns"
            :key="`t-${column.key}`"
            class="zusage-align__tick"
            :class="{ 'zusage-align__tick--month-start': column.monthStart }"
          />
          <span
            v-if="wishStyle"
            class="zusage-align__bar zusage-align__bar--wish"
            :class="{ 'zusage-align__bar--wide': wide }"
            :style="wishStyle"
            :title="wishTitle"
          />
          <span
            v-if="presentStyle"
            class="zusage-align__bar zusage-align__bar--firm"
            :style="presentStyle"
            :title="presentTitle"
          />
          <span
            v-if="handoverStyle"
            class="zusage-align__bar zusage-align__bar--handover"
            :style="handoverStyle"
            :title="handoverTitle"
          />
          <span
            v-if="returnStyle"
            class="zusage-align__bar zusage-align__bar--giveback"
            :style="returnStyle"
            :title="returnTitle"
          />
        </div>
      </div>
      <span class="zusage-align__nav-spacer" />
    </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  barStyleInWindow,
  calendarColumns,
  calendarWindow,
  dateToYmd,
  parseLocalDate,
  shiftCalendarAnchor,
  spanningMonthWindow,
  type GaCalendarScale,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import {
  monthBandsFromColumns,
  resolveCalendarWindow,
  withAxisMeta,
} from '@/views/grossanlass/gaCalendarAxis'
import GrossanlassCalendarAxis from '@/views/grossanlass/GrossanlassCalendarAxis.vue'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'

const props = withDefaults(defineProps<{
  scale: GaCalendarScale
  anchorYmd: string
  wishFromIso?: string
  wishToIso?: string
  presentFromIso?: string
  presentToIso?: string
  handoverFromIso?: string
  handoverToIso?: string
  returnFromIso?: string
  returnToIso?: string
  wide?: boolean
  showHead?: boolean
  headOnly?: boolean
  spanMonths?: boolean
}>(), {
  wishFromIso: '',
  wishToIso: '',
  presentFromIso: '',
  presentToIso: '',
  handoverFromIso: '',
  handoverToIso: '',
  returnFromIso: '',
  returnToIso: '',
  wide: false,
  showHead: false,
  headOnly: false,
  spanMonths: true,
})

const emit = defineEmits<{
  'open-day': [ymd: string]
  shift: [ymd: string]
}>()

const { t, locale } = useI18n()
const headStackEl = ref<HTMLElement | null>(null)
let syncingScroll = false

function syncScroll(event: Event) {
  const source = event.target as HTMLElement
  if (syncingScroll) return
  syncingScroll = true
  const left = source.scrollLeft
  if (headStackEl.value && headStackEl.value !== source) {
    headStackEl.value.scrollLeft = left
  }
  const root = source.closest('.zusage-align')
  root?.querySelectorAll('.zusage-align__track-scroll').forEach((el) => {
    if (el !== source) (el as HTMLElement).scrollLeft = left
  })
  syncingScroll = false
}

type AlignColumn = ReturnType<typeof withAxisMeta>

const windowRange = computed(() => {
  if (props.scale === 'month' && props.spanMonths) {
    const span = spanningMonthWindow(spanDates())
    if (span) return span
  }
  if (!props.anchorYmd) return null
  const date = parseLocalDate(`${props.anchorYmd}T00:00:00`)
  if (Number.isNaN(date.getTime())) return null
  return resolveCalendarWindow(props.scale, date, [], false)
})

const columns = computed((): AlignColumn[] => {
  if (!windowRange.value) return []
  return calendarColumns(props.scale, windowRange.value.start, windowRange.value.end, locale.value)
    .map((column) => withAxisMeta(column, props.scale))
})

const dayPickerColumns = computed((): AlignColumn[] => {
  if (props.scale !== 'day' || !props.anchorYmd) return []
  const date = parseLocalDate(`${props.anchorYmd}T00:00:00`)
  if (Number.isNaN(date.getTime())) return []
  const week = calendarWindow('week', date)
  return calendarColumns('week', week.start, week.end, locale.value)
    .map((column) => withAxisMeta(column, 'week'))
})

const monthBands = computed(() =>
  props.scale === 'day' ? [] : monthBandsFromColumns(columns.value, locale.value),
)

const hoursGridStyle = computed(() => ({
  gridTemplateColumns: `repeat(${Math.max(1, columns.value.length)}, minmax(14px, 1fr))`,
  minWidth: `${Math.max(1, columns.value.length) * 14}px`,
}))

function onSelectDay(ymd: string) {
  emit('open-day', ymd)
}

function spanDates(): Date[] {
  return [
    props.wishFromIso,
    props.wishToIso,
    props.presentFromIso,
    props.presentToIso,
    props.handoverFromIso,
    props.handoverToIso,
    props.returnFromIso,
    props.returnToIso,
  ]
    .filter((iso): iso is string => Boolean(iso))
    .map((iso) => parseLocalDate(iso))
    .filter((date) => Number.isFinite(date.getTime()))
}

function shiftBy(direction: -1 | 1) {
  if (!windowRange.value) return
  const next = shiftCalendarAnchor(props.scale, windowRange.value.start, direction)
  emit('shift', dateToYmd(next))
}

function barStyle(fromIso: string, toIso: string) {
  if (!windowRange.value || !fromIso || !toIso) return null
  return barStyleInWindow(
    { fromIso, toIso } as GaPreviewEinsatz,
    windowRange.value.start,
    windowRange.value.end,
    props.scale,
  )
}

const wishStyle = computed(() => barStyle(props.wishFromIso, props.wishToIso))
const presentStyle = computed(() => barStyle(props.presentFromIso, props.presentToIso))
const handoverStyle = computed(() => barStyle(props.handoverFromIso, props.handoverToIso || props.handoverFromIso))
const returnStyle = computed(() => barStyle(props.returnFromIso, props.returnToIso || props.returnFromIso))

const dayCaption = computed(() => {
  if (props.scale !== 'day' || !props.anchorYmd) return ''
  const date = parseLocalDate(`${props.anchorYmd}T00:00:00`)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleDateString(locale.value, {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
})

const wishTitle = computed(() =>
  props.wishFromIso
    ? t('grossanlass.beschaffung.zusagen.alignWishTitle', {
      from: formatGaIsoLabel(props.wishFromIso, locale.value),
      to: formatGaIsoLabel(props.wishToIso, locale.value),
    })
    : t('grossanlass.planung.feinPartner.delta.none'),
)
const presentTitle = computed(() =>
  props.presentFromIso
    ? t('grossanlass.beschaffung.zusagen.alignFirmTitle', {
      from: formatGaIsoLabel(props.presentFromIso, locale.value),
      to: formatGaIsoLabel(props.presentToIso, locale.value),
    })
    : t('grossanlass.beschaffung.zusagen.windowAction'),
)
const handoverTitle = computed(() =>
  props.handoverFromIso
    ? t('grossanlass.beschaffung.zusagen.alignHandoverTitle', {
      from: formatGaIsoLabel(props.handoverFromIso, locale.value),
      to: formatGaIsoLabel(props.handoverToIso || props.handoverFromIso, locale.value),
    })
    : t('grossanlass.materialUebersicht.status.handover'),
)
const returnTitle = computed(() =>
  props.returnFromIso
    ? t('grossanlass.beschaffung.zusagen.alignReturnTitle', {
      from: formatGaIsoLabel(props.returnFromIso, locale.value),
      to: formatGaIsoLabel(props.returnToIso || props.returnFromIso, locale.value),
    })
    : t('grossanlass.materialUebersicht.status.giveback'),
)
</script>

<style scoped>
.zusage-align {
  display: grid;
  gap: 4px;
  min-width: 0;
  overflow-x: hidden;
}
.zusage-align__head,
.zusage-align__lane {
  display: grid;
  grid-template-columns: 5.6rem 22px minmax(0, 1fr) 22px;
  gap: 4px 6px;
  align-items: center;
}
.zusage-align__head {
  align-items: stretch;
}
.zusage-align__lane-label {
  font-size: 0.7rem;
  font-weight: 700;
  color: #64748b;
  line-height: 1.2;
}
.zusage-align__nav {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  margin: 0;
  padding: 0;
  border: 1px solid #c7d2fe;
  border-radius: 6px;
  background: #eef2ff;
  color: #4338ca;
  cursor: pointer;
}
.zusage-align__nav-spacer {
  display: block;
}
.zusage-align__nav:hover {
  background: #e0e7ff;
}
.zusage-align__head-stack,
.zusage-align__track-scroll {
  min-width: 0;
  overflow-x: auto;
}
.zusage-align__head-stack :deep(.ga-cal-axis__months),
.zusage-align__head-stack :deep(.ga-cal-axis__hours) {
  min-width: calc(var(--align-cols, 1) * 14px);
}
.zusage-align__track-scroll {
  scrollbar-width: none;
}
.zusage-align__track-scroll::-webkit-scrollbar {
  height: 0;
}
.zusage-align__months,
.zusage-align__hours,
.zusage-align__track {
  position: relative;
  display: flex;
  min-height: 22px;
  min-width: calc(var(--align-cols, 1) * 14px);
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  overflow: hidden;
  background: #fff;
}
.zusage-align__lane .zusage-align__track {
  min-height: 28px;
}
.zusage-align__months {
  min-height: 20px;
  margin-bottom: -1px;
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
  background: #eef2ff;
}
.zusage-align__month {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  min-width: 0;
  padding: 0 6px;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #4338ca;
  border-left: 1px solid #c7d2fe;
}
.zusage-align__hours {
  min-height: 28px;
  background: #f9fafb;
  border-bottom-width: 2px;
  border-bottom-color: #9ca3af;
}
.zusage-align__months + .zusage-align__hours,
.zusage-align__picker + .zusage-align__hours,
.zusage-align__day-caption + .zusage-align__hours {
  border-top-left-radius: 0;
  border-top-right-radius: 0;
}
.zusage-align__picker {
  display: flex;
  justify-content: flex-start;
  min-height: 32px;
  min-width: 0;
  margin-bottom: 4px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  overflow: hidden;
  background: #fff;
}
.zusage-align__picker .zusage-align__hour {
  flex: 0 0 2.5rem;
}
.zusage-align__day-caption {
  margin: 0 0 2px;
  padding: 0 2px;
  font-size: 0.72rem;
  font-weight: 700;
  color: #4338ca;
}
.zusage-align__month:first-child {
  border-left: 0;
}
.zusage-align__hour,
.zusage-align__tick {
  flex: 1 1 0;
  min-width: 0;
  border-left: 1px solid #e5e7eb;
}
.zusage-align__hour {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  font-family: inherit;
  font-size: 0.62rem;
  font-weight: 400;
  font-variant-numeric: tabular-nums;
  letter-spacing: normal;
  color: #6b7280;
  line-height: 1.1;
}
.zusage-align__hour--day {
  font-size: 0.55rem;
  font-weight: 600;
}
.zusage-align__hour--btn {
  appearance: none;
  margin: 0;
  padding: 0;
  border: 0;
  border-left: 1px solid #e5e7eb;
  border-radius: 0;
  background: transparent;
  cursor: pointer;
}
.zusage-align__hour--btn:hover {
  background: #e0e7ff;
  color: #3730a3;
}
.zusage-align__hour--active {
  background: #c7d2fe;
  color: #312e81;
  font-weight: 700;
}
.zusage-align__hour--weekend {
  background: #f3f4f6;
}
.zusage-align__hour--month-start,
.zusage-align__tick--month-start {
  border-left-color: #9ca3af;
  border-left-width: 2px;
}
.zusage-align__hour small {
  font-size: 0.55rem;
  font-weight: 400;
  text-transform: uppercase;
  color: inherit;
}
.zusage-align__tick:first-child,
.zusage-align__hour:first-child {
  border-left: 0;
}
.zusage-align__bar {
  position: absolute;
  top: 4px;
  bottom: 4px;
  border-radius: 4px;
  min-width: 4px;
  box-sizing: border-box;
  z-index: 1;
}
.zusage-align__bar--wish {
  top: 2px;
  bottom: 2px;
  background: color-mix(in srgb, var(--color-primary) 16%, transparent);
  border: 2px solid var(--color-primary);
  z-index: 1;
}
.zusage-align__bar--wide {
  background: color-mix(in srgb, #fdba74 28%, transparent);
  border-color: #c2410c;
  box-shadow: none;
}
.zusage-align__bar--firm {
  top: 7px;
  bottom: 7px;
  background: var(--activity-status-packing, #0ea5e9);
  z-index: 2;
}
.zusage-align__bar--handover {
  top: 2px;
  bottom: 2px;
  background: #0f766e;
  z-index: 3;
}
.zusage-align__bar--giveback {
  top: 2px;
  bottom: 2px;
  background: #7c3aed;
  z-index: 3;
}
</style>
