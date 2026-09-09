<template>
  <div class="ga-gantt">
    <div class="ga-gantt__stick">
    <div class="ga-gantt__toolbar">
      <div class="ga-gantt__scales" role="tablist">
        <button
          v-for="item in scales"
          :key="item.id"
          type="button"
          class="ga-gantt__scale-btn"
          :class="{ 'ga-gantt__scale-btn--active': scale === item.id }"
          @click="scale = item.id"
        >
          {{ item.label }}
        </button>
      </div>
      <strong class="ga-gantt__title">{{ windowTitle }}</strong>
      <ESearchField
        v-model="searchQuery"
        class="ga-gantt__search"
        :label="t('grossanlass.materialUebersicht.searchPlaceholder')"
      />
      <ul class="ga-gantt__legend" aria-label="Einsatzstatus">
        <li>
          <span class="ga-gantt__legend-swatch ga-gantt__legend-swatch--fixed" />
          {{ t('grossanlass.materialUebersicht.status.fixed') }}
        </li>
        <li>
          <span class="ga-gantt__legend-swatch ga-gantt__legend-swatch--planned" />
          {{ t('grossanlass.materialUebersicht.status.planned') }}
        </li>
        <li>
          <span class="ga-gantt__legend-swatch ga-gantt__legend-swatch--pending" />
          {{ t('grossanlass.materialUebersicht.status.pending_approval') }}
        </li>
        <li>
          <span class="ga-gantt__legend-swatch ga-gantt__legend-swatch--issued" />
          {{ t('grossanlass.materialUebersicht.status.issued') }}
        </li>
        <li>
          <span class="ga-gantt__legend-swatch ga-gantt__legend-swatch--handover" />
          {{ t('grossanlass.materialUebersicht.status.handover') }}
        </li>
        <li>
          <span class="ga-gantt__legend-swatch ga-gantt__legend-swatch--giveback" />
          {{ t('grossanlass.materialUebersicht.status.giveback') }}
        </li>
        <li>
          <span class="ga-gantt__legend-swatch ga-gantt__legend-swatch--service" />
          {{ t('grossanlass.materialUebersicht.status.service') }}
        </li>
        <li>
          <span class="ga-gantt__legend-swatch ga-gantt__legend-swatch--away" />
          {{ t('grossanlass.materialUebersicht.status.away') }}
        </li>
        <li>
          <span class="ga-gantt__legend-swatch ga-gantt__legend-swatch--unreleased" />
          {{ t('grossanlass.materialUebersicht.status.unreleased') }}
        </li>
      </ul>
    </div>

    <div
      v-if="!(searchQuery.trim() && !hasFilteredRows)"
      class="ga-gantt__pin"
    >
      <div class="ga-gantt__axis" :style="axisChromeStyle">
        <div class="ga-gantt__axis-label" aria-hidden="true" />
        <div v-if="scale === 'month'" class="ga-gantt__axis-nav ga-gantt__axis-nav--pair">
          <button
            type="button"
            class="ga-gantt__axis-nav-btn"
            :class="{ 'ga-gantt__axis-nav-btn--on': jumpMode === 'month' }"
            :aria-label="t('grossanlass.materialUebersicht.jumpMonthPrev')"
            @click="shiftMonth(-1)"
          >
            &lt;&lt;
          </button>
          <button
            type="button"
            class="ga-gantt__axis-nav-btn"
            :class="{ 'ga-gantt__axis-nav-btn--on': jumpMode === 'mid' }"
            :aria-label="t('grossanlass.materialUebersicht.jumpMidPrev')"
            @click="shiftMid(-1)"
          >
            &lt;
          </button>
        </div>
        <button
          v-else
          type="button"
          class="ga-gantt__axis-nav"
          :aria-label="t('grossanlass.materialUebersicht.prevPeriod')"
          @click="shift(-1)"
        >
          <v-icon icon="mdi-chevron-left" size="18" />
        </button>
        <GrossanlassCalendarAxis
          :scale="scale"
          :columns="columns"
          :day-picker-columns="dayPickerColumns"
          :month-bands="monthBands"
          :anchor-ymd="anchorYmd"
          :hours-grid-style="hoursGridStyle"
          @select-day="onSelectDay"
        />
        <div v-if="scale === 'month'" class="ga-gantt__axis-nav ga-gantt__axis-nav--pair">
          <button
            type="button"
            class="ga-gantt__axis-nav-btn"
            :class="{ 'ga-gantt__axis-nav-btn--on': jumpMode === 'mid' }"
            :aria-label="t('grossanlass.materialUebersicht.jumpMidNext')"
            @click="shiftMid(1)"
          >
            &gt;
          </button>
          <button
            type="button"
            class="ga-gantt__axis-nav-btn"
            :class="{ 'ga-gantt__axis-nav-btn--on': jumpMode === 'month' }"
            :aria-label="t('grossanlass.materialUebersicht.jumpMonthNext')"
            @click="shiftMonth(1)"
          >
            &gt;&gt;
          </button>
        </div>
        <button
          v-else
          type="button"
          class="ga-gantt__axis-nav"
          :aria-label="t('grossanlass.materialUebersicht.nextPeriod')"
          @click="shift(1)"
        >
          <v-icon icon="mdi-chevron-right" size="18" />
        </button>
      </div>
      <div v-if="headerFixedBookings.length" class="ga-gantt__fixed" :style="axisChromeStyle">
        <div class="ga-gantt__fixed-name">
          {{ t('grossanlass.materialUebersicht.ringFixed') }}
        </div>
        <span class="ga-gantt__axis-nav ga-gantt__axis-nav--spacer" aria-hidden="true" />
        <div class="ga-gantt__fixed-track" :style="{ minHeight: '28px' }">
          <span
            v-for="(column, colIndex) in columns"
            :key="`fixed-${column.key}`"
            class="ga-gantt__cell"
            :class="{
              'ga-gantt__cell--weekend': column.weekend,
              'ga-gantt__cell--month-start': column.monthStart,
            }"
            :style="cellStyle(colIndex)"
          />
          <span
            v-for="booking in visibleBookings(headerFixedBookings)"
            :key="booking.id"
            class="ga-gantt__bar-wrap"
            :style="barBox(booking, 0, 1)"
          >
            <button
              type="button"
              class="ga-gantt__bar ga-gantt__bar--fixed"
              :class="{ 'ga-gantt__bar--active': selectedBooking?.id === booking.id && einsatzDialogOpen }"
              :aria-label="barTitle(booking)"
              @click.stop="openEinsatz(booking, { id: 'fixed', stayMode: 'stay' })"
            >
              <span class="ga-gantt__bar-label ga-gantt__bar-label--time">{{ booking.objectName }}</span>
              <span class="ga-gantt__bar-label ga-gantt__bar-label--full">{{ booking.objectName }}</span>
            </button>
          </span>
        </div>
        <span class="ga-gantt__axis-nav ga-gantt__axis-nav--spacer" aria-hidden="true" />
      </div>
    </div>
    </div>

    <EEmptyState
      v-if="searchQuery.trim() && !hasFilteredRows"
      variant="search"
      compact
      :title="t('grossanlass.materialUebersicht.emptySearchTitle')"
      :description="t('grossanlass.materialUebersicht.emptySearchText', { q: searchQuery.trim() })"
    />
    <div v-else-if="displayRings.length" class="ga-gantt__body" :style="gridTemplateStyle">
        <template v-for="ring in displayRings" :key="ring.id">
          <button
            type="button"
            class="ga-gantt__ring"
            :class="{ 'ga-gantt__ring--closed': !isRingOpen(ring.id) }"
            :style="{ gridColumn: '1 / -1' }"
            :aria-expanded="isRingOpen(ring.id)"
            @click="toggleRing(ring)"
          >
            <v-icon
              :icon="isRingOpen(ring.id) ? 'mdi-chevron-down' : 'mdi-chevron-right'"
              size="18"
            />
            <span>{{ ring.label }}</span>
          </button>
          <template v-if="isRingOpen(ring.id)">
          <template v-for="block in ring.blocks" :key="block.id">
          <button
            v-if="!ring.skipCategory"
            type="button"
            class="ga-gantt__cat"
            :class="{ 'ga-gantt__cat--closed': !isBlockOpen(block.id) }"
            :style="{ gridColumn: '1 / -1' }"
            :aria-expanded="isBlockOpen(block.id)"
            @click="toggleBlock(block)"
          >
            <v-icon
              :icon="isBlockOpen(block.id) ? 'mdi-chevron-down' : 'mdi-chevron-right'"
              size="16"
            />
            <span>{{ block.label }}</span>
          </button>
          <template v-for="resource in block.resources" :key="resource.id">
            <div
              v-show="isCategoryOpen(ring, block)"
              class="ga-gantt__name"
              :class="{
                'ga-gantt__name--open': focusedId === resource.id,
                'ga-gantt__name--nested': !ring.skipCategory,
              }"
            >
              <span class="ga-gantt__name-text">
                <strong>{{ resource.name }}</strong>
                <small>{{ kindLabel(resource) }}</small>
              </span>
            </div>
            <div
              v-show="isCategoryOpen(ring, block)"
              class="ga-gantt__track"
              :class="{ 'ga-gantt__track--open': focusedId === resource.id }"
              :style="{ gridColumn: `3 / span ${columns.length}`, minHeight: `${trackHeight(resource.lanes)}px` }"
            >
              <span
                v-for="(column, colIndex) in columns"
                :key="`${resource.id}-${column.key}`"
                class="ga-gantt__cell"
                :class="{
                  'ga-gantt__cell--weekend': column.weekend,
                  'ga-gantt__cell--month-start': column.monthStart,
                }"
                :style="cellStyle(colIndex)"
              />
              <span
                v-for="shade in presenceShades(resource)"
                :key="shade.key"
                class="ga-gantt__shade"
                :class="`ga-gantt__shade--${shade.kind}`"
                :style="shadeBox(shade)"
              />
              <span
                v-for="booking in visibleBookings(resource.bookings)"
                :key="booking.id"
                class="ga-gantt__bar-wrap"
                :style="barBox(booking, resource.laneOf[booking.id] ?? 0, resource.lanes)"
              >
                <VTooltip
                  location="top"
                  open-on-hover
                  :open-delay="80"
                  :close-delay="80"
                  scroll-strategy="close"
                  max-width="280"
                  content-class="ga-gantt-bar-tip"
                >
                  <template #activator="{ props: tipProps }">
                    <button
                      v-bind="tipProps"
                      type="button"
                      class="ga-gantt__bar"
                      :class="barClass(booking)"
                      :aria-label="barTitle(booking)"
                      @click.stop="openEinsatz(booking, resource)"
                    >
                      <span class="ga-gantt__bar-label ga-gantt__bar-label--time">{{ barTime(booking) }}</span>
                      <span class="ga-gantt__bar-label ga-gantt__bar-label--full">{{ barLabel(booking) }}</span>
                    </button>
                  </template>
                  <div class="ga-gantt-bar-tip__body">
                    <strong>{{ booking.objectName }}</strong>
                    <span>{{ booking.who }}</span>
                    <span>
                      {{ booking.ressort }}<template v-if="booking.bauprojekt"> · {{ booking.bauprojekt }}</template>
                    </span>
                    <span>{{ booking.fromLabel }} – {{ booking.toLabel }}</span>
                    <span v-if="booking.kind === 'quantity'">{{ t('grossanlass.materialUebersicht.qty', { n: booking.qty }) }}</span>
                    <span>{{ stayLabel(resource.stayMode) }} · {{ statusLabel(booking) }}</span>
                    <span
                      v-if="einsatzBarKind(booking) === 'handover' || einsatzBarKind(booking) === 'giveback'"
                      class="ga-gantt-bar-tip__conflict"
                    >
                      {{ t('grossanlass.materialUebersicht.occupancyFixedHint') }}
                    </span>
                    <span v-if="einsatzBarKind(booking) === 'pending_approval'" class="ga-gantt-bar-tip__conflict">
                      {{ t('grossanlass.materialUebersicht.pendingMwHint') }}
                    </span>
                  </div>
                </VTooltip>
              </span>
            </div>
            <span class="ga-gantt__row-end" aria-hidden="true" />
          </template>
          </template>
          </template>
        </template>
      </div>
  </div>

  <GrossanlassEinsatzDetailDialog
    v-model="einsatzDialogOpen"
    :booking="selectedBooking"
    :stay-mode="selectedStayMode"
  />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { VTooltip } from 'vuetify/components'
import { ESearchField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useGaEventPeriodOrEmpty } from '@/composables/useGaEventPeriod'
import { useCalendarPeriodsCacheRevision } from '@/composables/useCalendarPeriodsCache'
import GrossanlassCalendarAxis from '@/views/grossanlass/GrossanlassCalendarAxis.vue'
import GrossanlassEinsatzDetailDialog from '@/views/grossanlass/GrossanlassEinsatzDetailDialog.vue'
import {
  monthBandsFromColumns,
  resolveCalendarWindow,
  withAxisMeta,
} from '@/views/grossanlass/gaCalendarAxis'
import {
  barStyleInWindow,
  buildEinsatzCalendarBlocks,
  buildFixedDateCalendarRing,
  buildOrgCalendarRings,
  calendarColumns,
  calendarWindow,
  dateToYmd,
  createGrossanlassEinsatzPreview,
  einsatzBarKind,
  enrichEinsatzFromGroups,
  formatCalendarTitle,
  groupEinsatzBlocksByRing,
  parseLocalDate,
  resourcePresenceShades,
  shiftCalendarAnchor,
  shiftMidMonthAnchor,
  midMonthWindow,
  GA_EINSATZ_ANCHOR_ISO,
  type GaCalendarScale,
  type GaEinsatzCategoryBlock,
  type GaEinsatzKind,
  type GaEinsatzOrgGroup,
  type GaEinsatzRingBlock,
  type GaEinsatzStayMode,
  type GaEinsatzResource,
  type GaFixedDatePeriod,
  type GaPresenceShade,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'
import { mergedEinsatzResources } from '@/views/grossanlass/grossanlassZusagePreviewStore'
import { getGrossanlassGroups } from '@/api/grossanlassGroups'
import {
  calendarPeriodTime,
  listDepartmentCalendarPeriods,
  GROSSANLASS_TIME_MODULE_LABELS,
  type DepartmentCalendarPeriod,
} from '@/api/calendarPeriods'

const props = withDefaults(defineProps<{
  rows?: GaPreviewEinsatz[]
  resources?: GaEinsatzResource[]
  groups?: GaEinsatzOrgGroup[]
  focusIso?: string | null
  focusObjectId?: string | null
}>(), {
  rows: undefined,
  resources: undefined,
  groups: undefined,
  focusIso: null,
  focusObjectId: null,
})

const { t, locale } = useI18n()
const route = useRoute()
const eventPeriod = useGaEventPeriodOrEmpty()
const cacheRevision = useCalendarPeriodsCacheRevision()
const departmentId = computed(() => String(route.params.departmentId || ''))
const loadedGroups = ref<GaEinsatzOrgGroup[]>([])
const calendarPeriods = ref<DepartmentCalendarPeriod[]>([])

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}

const scale = ref<GaCalendarScale>('month')
const jumpMode = ref<'event' | 'month' | 'mid'>('event')
const anchorTouched = ref(false)
const anchor = ref(parseLocalDate(GA_EINSATZ_ANCHOR_ISO))
const focusedId = ref<string | null>(null)
const collapsedBlocks = ref<Set<string>>(new Set())
const collapsedRings = ref<Set<string>>(new Set())
const searchQuery = ref('')
const einsatzDialogOpen = ref(false)
const selectedBooking = ref<GaPreviewEinsatz | null>(null)
const selectedStayMode = ref<GaEinsatzStayMode | null>(null)

const scales = computed(() => [
  { id: 'month' as const, label: t('grossanlass.materialUebersicht.scaleMonth') },
  { id: 'week' as const, label: t('grossanlass.materialUebersicht.scaleWeek') },
  { id: 'day' as const, label: t('grossanlass.materialUebersicht.scaleDay') },
])

const preview = computed(() => createGrossanlassEinsatzPreview(tr))
const sourceRows = computed(() => props.rows ?? preview.value.einsaetze)
const orgGroups = computed(() =>
  props.groups?.length ? props.groups : loadedGroups.value,
)
const orgRows = computed(() =>
  sourceRows.value.map((row) => enrichEinsatzFromGroups(row, orgGroups.value)),
)
const onlyWithBookings = computed(() => !(props.resources && props.resources.length > 0))

const windowRange = computed(() => {
  if (scale.value === 'month' && jumpMode.value === 'mid') {
    return midMonthWindow(anchor.value)
  }
  return resolveCalendarWindow(
    scale.value,
    anchor.value,
    eventPeriod.spanDates.value,
    scale.value === 'month' && jumpMode.value === 'event',
  )
})

const columns = computed(() =>
  calendarColumns(scale.value, windowRange.value.start, windowRange.value.end, locale.value)
    .map((column) => withAxisMeta(column, scale.value)),
)
const dayPickerColumns = computed(() => {
  if (scale.value !== 'day') return []
  const week = calendarWindow('week', anchor.value)
  return calendarColumns('week', week.start, week.end, locale.value)
    .map((column) => withAxisMeta(column, 'week'))
})
const monthBands = computed(() =>
  scale.value === 'day' ? [] : monthBandsFromColumns(columns.value, locale.value),
)
const anchorYmd = computed(() => dateToYmd(anchor.value))
const windowTitle = computed(() =>
  formatCalendarTitle(scale.value, windowRange.value.start, windowRange.value.end, locale.value),
)
const colMinWidth = computed(() => {
  if (scale.value === 'month') return 28
  if (scale.value === 'day') return 28
  return 72
})
const axisNavWidth = computed(() => (scale.value === 'month' ? '56px' : '32px'))
const hoursGridStyle = computed(() => ({
  gridTemplateColumns: `repeat(${columns.value.length}, minmax(${colMinWidth.value}px, 1fr))`,
}))
const axisChromeStyle = computed(() => ({
  gridTemplateColumns: `minmax(200px, 240px) ${axisNavWidth.value} minmax(0, 1fr) ${axisNavWidth.value}`,
}))
const gridTemplateStyle = computed(() => ({
  gridTemplateColumns: `minmax(200px, 240px) ${axisNavWidth.value} repeat(${columns.value.length}, minmax(${colMinWidth.value}px, 1fr)) ${axisNavWidth.value}`,
}))

const calendarResources = computed(() =>
  props.resources?.length ? props.resources : mergedEinsatzResources(tr),
)

const blocks = computed(() =>
  buildEinsatzCalendarBlocks(
    calendarResources.value,
    sourceRows.value,
    tr,
    onlyWithBookings.value,
  ),
)

const filteredBlocks = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return blocks.value
  return blocks.value
    .map((block) => {
      if (block.ringLabel.toLowerCase().includes(query) || block.label.toLowerCase().includes(query)) {
        return block
      }
      return {
        ...block,
        resources: block.resources.filter((resource) =>
          resourceMatches(resource, `${block.ringLabel} ${block.label}`, query),
        ),
      }
    })
    .filter((block) => block.resources.length > 0)
})

const KEY_DATE_LABELS = new Set<string>([...GROSSANLASS_TIME_MODULE_LABELS, 'other'])

const fixedDatePeriods = computed((): GaFixedDatePeriod[] =>
  calendarPeriods.value
    .filter((period) => KEY_DATE_LABELS.has(period.label))
    .slice()
    .sort((a, b) => {
      const sa = `${a.start_date}T${calendarPeriodTime(a.start_time, '00:00')}`
      const sb = `${b.start_date}T${calendarPeriodTime(b.start_time, '00:00')}`
      return sa.localeCompare(sb) || a.name.localeCompare(b.name)
    })
    .map((period) => {
      const typeLabel = String(t(`settings.fixedDates.labels.${period.label}`))
      const showName = period.name.trim().toLocaleLowerCase() !== typeLabel.toLocaleLowerCase()
      const fromIso = `${period.start_date.slice(0, 10)}T${calendarPeriodTime(period.start_time, '00:00')}:00`
      const toIso = `${period.end_date.slice(0, 10)}T${calendarPeriodTime(period.end_time, '23:59')}:00`
      return {
        id: period.id,
        typeLabel,
        name: showName ? period.name : '',
        fromIso,
        toIso,
        fromLabel: formatGaIsoLabel(fromIso, locale.value),
        toLabel: formatGaIsoLabel(toIso, locale.value),
      }
    }),
)

const objectRings = computed(() => groupEinsatzBlocksByRing(filteredBlocks.value))
const fixedRing = computed(() => buildFixedDateCalendarRing(fixedDatePeriods.value, tr))
const orgRings = computed(() =>
  buildOrgCalendarRings(calendarResources.value, orgRows.value, tr),
)

function filterRingByQuery(ring: GaEinsatzRingBlock, query: string): GaEinsatzRingBlock | null {
  if (!query) return ring
  if (ring.label.toLowerCase().includes(query)) return ring
  const blocks = ring.blocks
    .map((block) => {
      if (block.label.toLowerCase().includes(query)) return block
      return {
        ...block,
        resources: block.resources.filter((resource) =>
          resourceMatches(resource, `${ring.label} ${block.label}`, query),
        ),
      }
    })
    .filter((block) => block.resources.length > 0)
  if (!blocks.length) return null
  return { ...ring, blocks }
}

const displayRings = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  const rings: GaEinsatzRingBlock[] = []
  rings.push(...objectRings.value)
  for (const ring of orgRings.value) {
    const next = filterRingByQuery(ring, query)
    if (next) rings.push(next)
  }
  return rings
})

const headerFixedBookings = computed(() => {
  const ring = fixedRing.value
  if (!ring) return []
  const query = searchQuery.value.trim().toLowerCase()
  const bookings = ring.blocks.flatMap((block) =>
    block.resources.flatMap((resource) => resource.bookings),
  )
  if (!query) return bookings
  if (ring.label.toLowerCase().includes(query)) return bookings
  return bookings.filter((booking) =>
    [booking.objectName, booking.who, booking.ressort, booking.bauprojekt]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
      .includes(query),
  )
})

const hasFilteredRows = computed(() =>
  displayRings.value.length > 0 || headerFixedBookings.value.length > 0,
)

function periodYears(): number[] {
  const years = new Set<number>()
  const now = new Date().getFullYear()
  years.add(now - 1)
  years.add(now)
  years.add(now + 1)
  years.add(now + 2)
  for (const date of eventPeriod.spanDates.value) {
    years.add(date.getFullYear())
  }
  for (const iso of [eventPeriod.startIso.value, eventPeriod.endIso.value]) {
    const year = iso ? Number.parseInt(iso.slice(0, 4), 10) : NaN
    if (Number.isFinite(year)) years.add(year)
  }
  return [...years].sort((a, b) => a - b)
}

async function loadOverviewMeta() {
  const id = departmentId.value
  if (!id) {
    loadedGroups.value = []
    calendarPeriods.value = []
    return
  }
  const years = periodYears()
  const [periods, groupRows] = await Promise.all([
    listDepartmentCalendarPeriods(id, years).catch(() => [] as DepartmentCalendarPeriod[]),
    getGrossanlassGroups(id).catch(() => [] as GaEinsatzOrgGroup[]),
  ])
  calendarPeriods.value = periods
  loadedGroups.value = groupRows
}

watch(
  [departmentId, cacheRevision, () => eventPeriod.startIso.value, () => eventPeriod.endIso.value],
  () => { void loadOverviewMeta() },
  { immediate: true },
)

watch(() => [props.focusIso, props.focusObjectId] as const, ([iso, objectId]) => {
  if (iso) {
    const date = parseLocalDate(iso)
    if (!Number.isNaN(date.getTime())) {
      anchor.value = date
      anchorTouched.value = true
      if (scale.value === 'month') jumpMode.value = 'month'
    }
  }
  if (objectId) focusedId.value = objectId
}, { immediate: true })

watch(() => eventPeriod.defaultAnchor.value, (date) => {
  if (!date || anchorTouched.value || props.focusIso) return
  anchor.value = date
})

watch(scale, (next) => {
  if (next === 'month') jumpMode.value = 'event'
})

watch(displayRings, (next) => {
  if (!focusedId.value) return
  const stillVisible = next.some((ring) =>
    ring.blocks.some((block) =>
      block.resources.some((resource) => resource.id === focusedId.value),
    ),
  )
  if (!stillVisible) focusedId.value = null
})

function shiftMonth(direction: -1 | 1) {
  anchorTouched.value = true
  const base = jumpMode.value === 'month' ? anchor.value : windowRange.value.start
  jumpMode.value = 'month'
  // Prefer the month currently in view (event span / mid), then step from the 1st.
  const from = new Date(base.getFullYear(), base.getMonth(), 1)
  anchor.value = shiftCalendarAnchor('month', from, direction)
}

function shiftMid(direction: -1 | 1) {
  anchorTouched.value = true
  if (jumpMode.value !== 'mid') {
    const start = windowRange.value.start
    jumpMode.value = 'mid'
    // Enter mid from the visible window so the month divider stays on the 1st.
    const sixteenth = new Date(start.getFullYear(), start.getMonth(), 16)
    if (direction < 0) {
      anchor.value = new Date(sixteenth.getFullYear(), sixteenth.getMonth() - 1, 16)
    } else {
      anchor.value = sixteenth
    }
    return
  }
  anchor.value = shiftMidMonthAnchor(anchor.value, direction)
}

function shift(direction: -1 | 1) {
  if (scale.value === 'month' && jumpMode.value === 'mid') {
    shiftMid(direction)
    return
  }
  if (scale.value === 'month') {
    shiftMonth(direction)
    return
  }
  anchorTouched.value = true
  anchor.value = shiftCalendarAnchor(scale.value, anchor.value, direction)
}

function goToDay(startMs: number) {
  anchorTouched.value = true
  anchor.value = new Date(startMs)
  if (scale.value !== 'day') scale.value = 'day'
}

function goToYmd(ymd: string) {
  const date = parseLocalDate(`${ymd}T00:00:00`)
  if (Number.isNaN(date.getTime())) return
  anchorTouched.value = true
  anchor.value = date
  if (scale.value !== 'day') scale.value = 'day'
}

function onSelectDay(ymd: string, startMs: number) {
  if (scale.value === 'day') {
    goToYmd(ymd)
    return
  }
  goToDay(startMs)
}

function openEinsatz(
  booking: GaPreviewEinsatz,
  resource: { id: string; stayMode: GaEinsatzStayMode },
) {
  selectedBooking.value = booking
  selectedStayMode.value = resource.stayMode
  focusedId.value = resource.id
  einsatzDialogOpen.value = true
}

function isBlockOpen(id: string): boolean {
  if (searchQuery.value.trim()) return true
  return !collapsedBlocks.value.has(id)
}

function isRingOpen(id: string): boolean {
  if (searchQuery.value.trim()) return true
  return !collapsedRings.value.has(id)
}

function isCategoryOpen(ring: GaEinsatzRingBlock, block: GaEinsatzCategoryBlock): boolean {
  return ring.skipCategory || isBlockOpen(block.id)
}

function ringHasFocused(ring: GaEinsatzRingBlock): boolean {
  return ring.blocks.some((block) =>
    block.resources.some((resource) => resource.id === focusedId.value),
  )
}

function toggleRing(ring: GaEinsatzRingBlock) {
  const next = new Set(collapsedRings.value)
  if (next.has(ring.id)) {
    next.delete(ring.id)
  } else {
    next.add(ring.id)
    if (ringHasFocused(ring)) focusedId.value = null
  }
  collapsedRings.value = next
}

function toggleBlock(block: GaEinsatzCategoryBlock) {
  const next = new Set(collapsedBlocks.value)
  if (next.has(block.id)) {
    next.delete(block.id)
  } else {
    next.add(block.id)
    if (block.resources.some((resource) => resource.id === focusedId.value)) {
      focusedId.value = null
    }
  }
  collapsedBlocks.value = next
}

function resourceMatches(
  resource: GaEinsatzCategoryBlock['resources'][number],
  blockLabel: string,
  query: string,
): boolean {
  const parts = [
    blockLabel,
    resource.name,
    resource.categoryId,
    stayLabel(resource.stayMode),
    ...resource.bookings.flatMap((booking) => [
      booking.ressort,
      booking.who,
      booking.bauprojekt,
      booking.objectName,
    ]),
  ]
  return parts.filter(Boolean).join(' ').toLowerCase().includes(query)
}

function kindLabel(resource: { kind: GaEinsatzKind; stock: number; stayMode: GaEinsatzStayMode; categoryId?: string; bookings?: GaPreviewEinsatz[] }): string {
  if (resource.categoryId === 'fixed') {
    const booking = resource.bookings?.[0]
    return booking ? `${booking.fromLabel} – ${booking.toLabel}` : ''
  }
  const stay = stayLabel(resource.stayMode)
  if (resource.kind === 'quantity') {
    return `${t('grossanlass.materialUebersicht.kindQuantity')} · ${t('grossanlass.materialUebersicht.stockQty', { n: resource.stock })} · ${stay}`
  }
  return `${t('grossanlass.materialUebersicht.kindUnique')} · ${stay}`
}

function stayLabel(mode: GaEinsatzStayMode): string {
  return mode === 'return'
    ? t('grossanlass.materialUebersicht.stayReturn')
    : t('grossanlass.materialUebersicht.stayUntilEnd')
}

function statusLabel(booking: GaPreviewEinsatz): string {
  return t(`grossanlass.materialUebersicht.status.${einsatzBarKind(booking)}`)
}

function presenceShades(resource: GaEinsatzCategoryBlock['resources'][number]): GaPresenceShade[] {
  return resourcePresenceShades(resource, windowRange.value.start, windowRange.value.end)
}

function shadeBox(shade: GaPresenceShade): Record<string, string> {
  const pos = barStyleInWindow(
    { fromIso: shade.fromIso, toIso: shade.toIso } as GaPreviewEinsatz,
    windowRange.value.start,
    windowRange.value.end,
    scale.value,
  )
  if (!pos) return { display: 'none' }
  return {
    left: pos.left,
    width: pos.width,
  }
}

function trackHeight(lanes: number): number {
  return 12 + lanes * 22
}

function visibleBookings(bookings: GaPreviewEinsatz[]): GaPreviewEinsatz[] {
  return bookings.filter((booking) =>
    barStyleInWindow(booking, windowRange.value.start, windowRange.value.end, scale.value),
  )
}

function cellStyle(colIndex: number): { left: string; width: string } {
  const count = columns.value.length || 1
  return {
    left: `${(colIndex / count) * 100}%`,
    width: `${(100 / count)}%`,
  }
}

function barBox(booking: GaPreviewEinsatz, lane: number, lanes: number): Record<string, string> {
  const pos = barStyleInWindow(booking, windowRange.value.start, windowRange.value.end, scale.value)
  if (!pos) return { display: 'none' }
  const top = 6 + lane * 22
  return {
    left: pos.left,
    width: pos.width,
    top: `${top}px`,
    height: lanes > 1 ? '18px' : '20px',
  }
}

function barClass(booking: GaPreviewEinsatz): Record<string, boolean> {
  const kind = einsatzBarKind(booking)
  return {
    'ga-gantt__bar--planned': kind === 'planned',
    'ga-gantt__bar--pending': kind === 'pending_approval',
    'ga-gantt__bar--issued': kind === 'issued',
    'ga-gantt__bar--returned': kind === 'returned',
    'ga-gantt__bar--handover': kind === 'handover',
    'ga-gantt__bar--giveback': kind === 'giveback',
    'ga-gantt__bar--service': kind === 'service',
    'ga-gantt__bar--unreleased': kind === 'unreleased',
    'ga-gantt__bar--fixed': kind === 'fixed',
    'ga-gantt__bar--occupancy': kind === 'handover' || kind === 'giveback' || kind === 'service',
    'ga-gantt__bar--active': selectedBooking.value?.id === booking.id && einsatzDialogOpen.value,
  }
}

function formatClock(iso: string): string {
  const date = parseLocalDate(iso)
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

function barTime(booking: GaPreviewEinsatz): string {
  return `${formatClock(booking.fromIso)} – ${formatClock(booking.toIso)}`
}

function barLabel(booking: GaPreviewEinsatz): string {
  const role = einsatzBarKind(booking)
  if (role === 'fixed') {
    return `${booking.objectName} ${barTime(booking)}`
  }
  if (role === 'handover' || role === 'giveback' || role === 'service') {
    return `${statusLabel(booking)} ${barTime(booking)}`
  }
  return `${booking.ressort} ${barTime(booking)}`
}

function barTitle(booking: GaPreviewEinsatz): string {
  const role = einsatzBarKind(booking)
  const base = `${booking.objectName} · ${booking.who} · ${booking.ressort} · ${booking.fromLabel} – ${booking.toLabel}`
  if (role === 'handover' || role === 'giveback') {
    return `${base} · ${t('grossanlass.materialUebersicht.occupancyFixedHint')}`
  }
  return base
}
</script>

<style scoped>
.ga-gantt {
  --ga-table-head: #f9fafb;
  --ga-table-row: #ffffff;
  --ga-table-hover: #f3f4f6;
  --ga-table-line: #f3f4f6;
  --ga-table-border: #e5e7eb;
  --ga-col-stroke: #9ca3af;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.ga-gantt__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 16px;
  margin-bottom: 10px;
}

.ga-gantt__search {
  flex: 1 1 220px;
  min-width: min(100%, 200px);
}

.ga-gantt__legend {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted, #6b7280);
}

.ga-gantt__legend li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.ga-gantt__legend-swatch {
  width: 18px;
  height: 10px;
  border-radius: 3px;
  flex-shrink: 0;
}

.ga-gantt__legend-swatch--fixed {
  background: #334155;
}

.ga-gantt__legend-swatch--planned {
  background: var(--color-primary);
}

.ga-gantt__legend-swatch--pending {
  background: #fdba74;
  box-shadow: 0 0 0 2px #c2410c;
}

.ga-gantt__legend-swatch--issued {
  background: var(--activity-status-packing);
}

.ga-gantt__legend-swatch--handover {
  background: #0f766e;
}

.ga-gantt__legend-swatch--giveback {
  background: #7c3aed;
}

.ga-gantt__legend-swatch--service {
  background: #a16207;
}

.ga-gantt__legend-swatch--away {
  background: repeating-linear-gradient(-45deg, #e5e7eb, #e5e7eb 4px, #f9fafb 4px, #f9fafb 8px);
}

.ga-gantt__legend-swatch--unreleased {
  background: repeating-linear-gradient(-45deg, #fdba74, #fdba74 4px, #fff7ed 4px, #fff7ed 8px);
}

.ga-gantt__scales,
.ga-gantt__nav {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--ga-table-border);
  border-radius: 8px;
  overflow: hidden;
  background: var(--ga-table-row);
}

.ga-gantt__scale-btn,
.ga-gantt__nav-btn {
  border: 0;
  background: transparent;
  padding: 8px 12px;
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--color-text-muted, #6b7280);
  cursor: pointer;
}

.ga-gantt__scale-btn--active {
  background: var(--color-primary-muted-bg);
  color: var(--color-primary-dark);
}

.ga-gantt__title {
  min-width: 180px;
  text-align: center;
  font-size: 0.9rem;
  padding: 0 8px;
  color: var(--color-text, #111827);
}

.ga-gantt__stick {
  position: sticky;
  top: 0;
  z-index: 12;
  background: #fff;
  padding-bottom: 4px;
  box-shadow: 0 8px 12px -8px rgb(17 24 39 / 18%);
}

.ga-gantt__pin {
  background: #fff;
}

.ga-gantt__axis {
  display: grid;
  align-items: stretch;
  min-width: 720px;
  border: 1px solid var(--ga-table-border);
  border-bottom: 2px solid var(--ga-col-stroke);
  border-radius: 10px 10px 0 0;
  background: var(--ga-table-head);
}

.ga-gantt__axis-label {
  min-height: 28px;
}

.ga-gantt__axis-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  border: 0;
  border-left: 1px solid var(--ga-table-border);
  background: var(--ga-table-head);
  color: var(--color-text, #111827);
  cursor: pointer;
}

.ga-gantt__axis-nav--pair {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  gap: 0;
  padding: 0;
  cursor: default;
}

.ga-gantt__axis-nav-btn {
  display: flex;
  flex: 1 1 0;
  align-items: center;
  justify-content: center;
  margin: 0;
  padding: 4px 0;
  border: 0;
  background: transparent;
  color: var(--color-text, #111827);
  font: inherit;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1;
  letter-spacing: -0.06em;
  cursor: pointer;
  min-height: 28px;
}

.ga-gantt__axis-nav-btn:hover,
.ga-gantt__axis-nav:hover {
  background: var(--ga-table-hover);
}

.ga-gantt__axis-nav-btn--on {
  color: var(--color-primary-dark, #166534);
}

.ga-gantt__axis-nav--spacer {
  pointer-events: none;
  cursor: default;
}

.ga-gantt__fixed {
  display: grid;
  align-items: stretch;
  min-width: 720px;
  border: 1px solid var(--ga-table-border);
  border-top: 0;
  background: #eef2f7;
}

.ga-gantt__fixed-name {
  display: flex;
  align-items: center;
  padding: 4px 10px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-text, #111827);
}

.ga-gantt__fixed-track {
  position: relative;
  min-height: 28px;
}

.ga-gantt__axis :deep(.ga-cal-axis) {
  min-width: 0;
}

.ga-gantt__body {
  display: grid;
  min-width: 720px;
  border: 1px solid var(--ga-table-border);
  border-top: 0;
  border-radius: 0 0 10px 10px;
  background: var(--ga-table-row);
}

.ga-gantt__ring {
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  margin: 0;
  border: 0;
  border-top: 2px solid var(--ga-col-stroke);
  padding: 8px 10px 6px;
  font: inherit;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  text-align: left;
  color: var(--color-text, #111827);
  background: #eef2f7;
  cursor: pointer;
}

.ga-gantt__ring:hover,
.ga-gantt__ring--closed {
  background: #e4eaf2;
}

.ga-gantt__cat {
  display: flex;
  align-items: center;
  gap: 4px;
  width: 100%;
  margin: 0;
  border: 0;
  border-top: 1px solid var(--ga-table-border);
  background: var(--ga-table-head);
  color: var(--color-text-muted, #6b7280);
  font: inherit;
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  text-align: left;
  padding: 5px 8px 5px 22px;
  cursor: pointer;
}

.ga-gantt__cat:hover,
.ga-gantt__cat--closed {
  background: var(--ga-table-hover);
}

.ga-gantt__name {
  display: flex;
  align-items: center;
  gap: 4px;
  grid-column: 1;
  position: sticky;
  left: 0;
  z-index: 3;
  border: 0;
  border-bottom: 1px solid var(--ga-table-line);
  background: var(--ga-table-row);
  color: #374151;
  text-align: left;
  padding: 6px 8px 6px 22px;
}

.ga-gantt__name--nested {
  padding-left: 40px;
}

.ga-gantt__name--open {
  background: var(--ga-table-hover);
}

.ga-gantt__name-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.ga-gantt__name-text strong {
  font-size: 0.82rem;
  font-weight: 600;
}

.ga-gantt__name-text small {
  font-size: 0.68rem;
  color: var(--color-text-muted, #6b7280);
}

.ga-gantt__track {
  position: relative;
  border-bottom: 1px solid var(--ga-table-line);
}

.ga-gantt__track--open {
  background-color: var(--ga-table-hover);
}

.ga-gantt__row-end {
  grid-column: 1 / -1;
  height: 0;
  overflow: hidden;
}

.ga-gantt__cell {
  position: absolute;
  inset: 0 auto 0 0;
  border-left: 1px solid var(--ga-table-border);
  pointer-events: none;
}

.ga-gantt__cell--weekend {
  background: color-mix(in srgb, var(--ga-table-hover) 70%, transparent);
}

.ga-gantt__cell--month-start {
  border-left-color: var(--ga-col-stroke);
  border-left-width: 2px;
}

.ga-gantt__shade {
  position: absolute;
  inset: 0 auto 0 0;
  z-index: 1;
  pointer-events: none;
}

.ga-gantt__shade--away {
  background: repeating-linear-gradient(-45deg, #e5e7eb 0 5px, #f3f4f6 5px 10px);
  opacity: 0.7;
}

.ga-gantt__shade--unreleased {
  background: repeating-linear-gradient(-45deg, #fdba74 0 5px, #fff7ed 5px 10px);
  opacity: 0.85;
}

.ga-gantt__bar-wrap {
  position: absolute;
  z-index: 2;
  display: block;
  pointer-events: auto;
}

.ga-gantt__bar {
  position: absolute;
  inset: 0;
  box-sizing: border-box;
  container-type: inline-size;
  container-name: ga-bar;
  width: 100%;
  height: 100%;
  margin: 0;
  border: 0;
  border-radius: 4px;
  background: var(--color-primary);
  color: var(--emc-logo-fg, #fff);
  font: inherit;
  font-size: 0.65rem;
  line-height: 18px;
  text-align: left;
  padding: 0 4px;
  overflow: hidden;
  white-space: nowrap;
  cursor: pointer;
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--emc-logo-fg, #fff) 40%, transparent);
}

.ga-gantt__bar:hover,
.ga-gantt__bar:focus-visible {
  filter: brightness(1.08);
}

.ga-gantt__bar:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--emc-logo-fg, #fff) 80%, transparent);
  outline-offset: 1px;
}

.ga-gantt__bar--occupancy {
  cursor: default;
}

.ga-gantt__bar-label {
  display: none;
  overflow: hidden;
  white-space: nowrap;
}

/* ~59px: "08:00 – 12:00" is readable; shorter bars stay empty */
@container ga-bar (min-width: 3.7rem) {
  .ga-gantt__bar-label--time {
    display: inline;
  }
}

/* ~128px: ressort + range fits without clipping */
@container ga-bar (min-width: 8rem) {
  .ga-gantt__bar-label--time {
    display: none;
  }

  .ga-gantt__bar-label--full {
    display: inline;
  }
}

.ga-gantt__bar--planned {
  background: var(--color-primary);
}

.ga-gantt__bar--fixed {
  background: #334155;
}

.ga-gantt__bar--active {
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--emc-logo-fg, #fff) 85%, transparent);
  filter: brightness(1.08);
}

.ga-gantt__bar--pending {
  background: #f59e0b;
  color: #431407;
  box-shadow: 0 0 0 2px #9a3412;
  outline: 1px dashed #fff7ed;
  outline-offset: -3px;
}

.ga-gantt__bar--issued {
  background: var(--activity-status-packing);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--activity-status-packing) 55%, #111);
}

.ga-gantt__bar--returned {
  background: var(--activity-status-completed);
}

.ga-gantt__bar--handover {
  background: #0f766e;
}

.ga-gantt__bar--giveback {
  background: #7c3aed;
}

.ga-gantt__bar--service {
  background: #a16207;
}

.ga-gantt__bar--unreleased {
  background: #fdba74;
  color: #9a3412;
}

.ga-einsatz-status--return {
  background: var(--color-primary-subtle-bg);
  color: var(--color-primary-dark);
}

.ga-einsatz-status--stay {
  background: var(--activity-status-at_event-bg);
  color: var(--activity-status-at_event-fg);
}

.ga-einsatz-status {
  display: inline-flex;
  align-items: center;
  padding: 1px 8px;
  border-radius: 999px;
  font-weight: 600;
  font-size: 0.72rem;
}

.ga-einsatz-status--planned {
  background: var(--color-primary-subtle-bg);
  color: var(--color-primary-dark);
}

.ga-einsatz-status--pending_approval {
  background: #fff7ed;
  color: #c2410c;
  box-shadow: inset 0 0 0 1px #d97706;
}

.ga-einsatz-status--issued {
  background: var(--activity-status-packing-bg);
  color: var(--activity-status-packing-fg);
}

.ga-einsatz-status--returned {
  background: var(--activity-status-completed-bg);
  color: var(--activity-status-completed-fg);
}

.ga-einsatz-status--handover {
  background: #ccfbf1;
  color: #0f766e;
}

.ga-einsatz-status--giveback {
  background: #ede9fe;
  color: #6d28d9;
}

.ga-einsatz-status--service {
  background: #fef3c7;
  color: #92400e;
}

.ga-einsatz-status--unreleased {
  background: #ffedd5;
  color: #c2410c;
}

.ga-einsatz-status--conflict {
  background: var(--color-error-bg);
  color: var(--color-error);
}
</style>

<style>
.v-overlay:has(.ga-gantt-bar-tip__body) > .v-overlay__content {
  background: #ffffff !important;
  color: #111827 !important;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px 12px !important;
  box-shadow: 0 8px 24px rgb(17 24 39 / 16%);
  opacity: 1 !important;
}

.ga-gantt-bar-tip__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 0.78rem;
  line-height: 1.35;
  color: #111827;
}

.ga-gantt-bar-tip__body strong {
  font-size: 0.82rem;
}

.ga-gantt-bar-tip__conflict {
  color: #c2410c;
  font-weight: 600;
}
</style>
