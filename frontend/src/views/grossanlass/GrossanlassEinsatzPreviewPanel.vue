<template>
  <div class="ga-gantt">
    <div class="ga-gantt__pin">
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
      <div class="ga-gantt__nav">
        <button type="button" class="ga-gantt__nav-btn" :aria-label="t('grossanlass.materialUebersicht.prevPeriod')" @click="shift(-1)">
          <v-icon icon="mdi-chevron-left" size="20" />
        </button>
        <strong class="ga-gantt__title">{{ windowTitle }}</strong>
        <button type="button" class="ga-gantt__nav-btn" :aria-label="t('grossanlass.materialUebersicht.nextPeriod')" @click="shift(1)">
          <v-icon icon="mdi-chevron-right" size="20" />
        </button>
      </div>
      <ESearchField
        v-model="searchQuery"
        class="ga-gantt__search"
        :label="t('grossanlass.materialUebersicht.searchPlaceholder')"
      />
      <ul class="ga-gantt__legend" aria-label="Einsatzstatus">
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
      v-if="!(searchQuery.trim() && !filteredBlocks.length)"
      class="ga-gantt__head"
      :style="gridTemplateStyle"
    >
      <div class="ga-gantt__corner" aria-hidden="true" />
      <button
        v-for="column in columns"
        :key="column.key"
        type="button"
        class="ga-gantt__col-head"
        :class="{
          'ga-gantt__col-head--weekend': column.weekend,
          'ga-gantt__col-head--hour': scale === 'day',
        }"
        :disabled="scale === 'day'"
        @click="goToDay(column.startMs)"
      >
        <span v-if="column.sub" class="ga-gantt__col-sub">{{ column.sub }}</span>
        <span>{{ column.label }}</span>
      </button>
    </div>
    </div>

    <EEmptyState
      v-if="searchQuery.trim() && !filteredBlocks.length"
      variant="search"
      compact
      :title="t('grossanlass.materialUebersicht.emptySearchTitle')"
      :description="t('grossanlass.materialUebersicht.emptySearchText', { q: searchQuery.trim() })"
    />
    <div v-else class="ga-gantt__body" :style="gridTemplateStyle">
        <template v-for="ring in filteredRings" :key="ring.id">
          <div
            class="ga-gantt__ring"
            :style="{ gridColumn: `1 / span ${columns.length + 1}` }"
          >
            {{ ring.label }}
          </div>
          <template v-for="block in ring.blocks" :key="block.id">
          <button
            type="button"
            class="ga-gantt__cat"
            :class="{ 'ga-gantt__cat--closed': !isBlockOpen(block.id) }"
            :style="{ gridColumn: `1 / span ${columns.length + 1}` }"
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
            <button
              v-show="isBlockOpen(block.id)"
              type="button"
              class="ga-gantt__name"
              :class="{ 'ga-gantt__name--open': expandedId === resource.id }"
              @click="toggle(resource.id)"
            >
              <v-icon
                :icon="expandedId === resource.id ? 'mdi-chevron-down' : 'mdi-chevron-right'"
                size="16"
              />
              <span class="ga-gantt__name-text">
                <strong>{{ resource.name }}</strong>
                <small>{{ kindLabel(resource) }}</small>
              </span>
            </button>
            <div
              v-show="isBlockOpen(block.id)"
              class="ga-gantt__track"
              :class="{ 'ga-gantt__track--open': expandedId === resource.id }"
              :style="{ gridColumn: `2 / span ${columns.length}`, minHeight: `${trackHeight(resource.lanes)}px` }"
              @click="toggle(resource.id)"
            >
              <span
                v-for="column in columns"
                :key="`${resource.id}-${column.key}`"
                class="ga-gantt__cell"
                :class="{ 'ga-gantt__cell--weekend': column.weekend }"
                :style="cellStyle(column)"
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
                  open-on-click
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
                      @click.stop
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
                    <span v-if="einsatzBarKind(booking) === 'pending_approval'" class="ga-gantt-bar-tip__conflict">
                      {{ t('grossanlass.materialUebersicht.pendingMwHint') }}
                    </span>
                  </div>
                </VTooltip>
              </span>
            </div>
            <div
              v-if="isBlockOpen(block.id) && expandedId === resource.id"
              class="ga-gantt__detail"
              :style="{ gridColumn: `1 / span ${columns.length + 1}` }"
            >
              <p v-if="!resource.bookings.length" class="ga-gantt__empty">
                {{ t('grossanlass.materialUebersicht.emptyRow') }}
              </p>
              <ul v-else class="ga-gantt__bookings">
                <li
                  v-for="booking in resource.bookings"
                  :key="booking.id"
                  :class="{ 'ga-gantt__booking--conflict': einsatzBarKind(booking) === 'pending_approval' }"
                >
                  <strong>{{ booking.who }}</strong>
                  <span>{{ booking.ressort }}<template v-if="booking.bauprojekt"> · {{ booking.bauprojekt }}</template></span>
                  <span>{{ booking.fromLabel }} – {{ booking.toLabel }}</span>
                  <span v-if="booking.kind === 'quantity'">{{ t('grossanlass.materialUebersicht.qty', { n: booking.qty }) }}</span>
                  <span class="ga-einsatz-status" :class="`ga-einsatz-status--${resource.stayMode}`">
                    {{ stayLabel(resource.stayMode) }}
                  </span>
                  <span class="ga-einsatz-status" :class="`ga-einsatz-status--${einsatzBarKind(booking)}`">
                    {{ statusLabel(booking) }}
                  </span>
                </li>
              </ul>
            </div>
          </template>
          </template>
        </template>
      </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { VTooltip } from 'vuetify/components'
import { ESearchField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import {
  barStyleInWindow,
  buildEinsatzCalendarBlocks,
  calendarColumns,
  calendarWindow,
  createGrossanlassEinsatzPreview,
  einsatzBarKind,
  formatCalendarTitle,
  groupEinsatzBlocksByRing,
  parseLocalDate,
  resourcePresenceShades,
  shiftCalendarAnchor,
  GA_EINSATZ_ANCHOR_ISO,
  type GaCalendarScale,
  type GaEinsatzCategoryBlock,
  type GaEinsatzKind,
  type GaEinsatzStayMode,
  type GaPresenceShade,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { mergedEinsatzResources } from '@/views/grossanlass/grossanlassZusagePreviewStore'

const props = withDefaults(defineProps<{
  rows?: GaPreviewEinsatz[]
}>(), {
  rows: undefined,
})

const { t, locale } = useI18n()

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}

const scale = ref<GaCalendarScale>('week')
const anchor = ref(parseLocalDate(GA_EINSATZ_ANCHOR_ISO))
const expandedId = ref<string | null>(null)
const collapsedBlocks = ref<Set<string>>(new Set())
const searchQuery = ref('')

const scales = computed(() => [
  { id: 'month' as const, label: t('grossanlass.materialUebersicht.scaleMonth') },
  { id: 'week' as const, label: t('grossanlass.materialUebersicht.scaleWeek') },
  { id: 'day' as const, label: t('grossanlass.materialUebersicht.scaleDay') },
])

const preview = computed(() => createGrossanlassEinsatzPreview(tr))
const sourceRows = computed(() => props.rows ?? preview.value.einsaetze)
const onlyWithBookings = computed(() => !!props.rows)

const windowRange = computed(() => calendarWindow(scale.value, anchor.value))
const columns = computed(() =>
  calendarColumns(scale.value, windowRange.value.start, windowRange.value.end, locale.value),
)
const windowTitle = computed(() =>
  formatCalendarTitle(scale.value, windowRange.value.start, windowRange.value.end, locale.value),
)
const colMinWidth = computed(() => {
  if (scale.value === 'month') return 28
  if (scale.value === 'day') return 28
  return 72
})
const gridTemplateStyle = computed(() => ({
  gridTemplateColumns: `minmax(200px, 240px) repeat(${columns.value.length}, minmax(${colMinWidth.value}px, 1fr))`,
}))

const blocks = computed(() =>
  buildEinsatzCalendarBlocks(
    mergedEinsatzResources(tr),
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

const filteredRings = computed(() => groupEinsatzBlocksByRing(filteredBlocks.value))

watch(filteredBlocks, (next) => {
  if (!expandedId.value) return
  const stillVisible = next.some((block) =>
    block.resources.some((resource) => resource.id === expandedId.value),
  )
  if (!stillVisible) expandedId.value = null
})

function shift(direction: -1 | 1) {
  anchor.value = shiftCalendarAnchor(scale.value, anchor.value, direction)
}

function goToDay(startMs: number) {
  if (scale.value === 'day') return
  anchor.value = new Date(startMs)
  scale.value = 'day'
}

function toggle(id: string) {
  expandedId.value = expandedId.value === id ? null : id
}

function isBlockOpen(id: string): boolean {
  if (searchQuery.value.trim()) return true
  return !collapsedBlocks.value.has(id)
}

function toggleBlock(block: GaEinsatzCategoryBlock) {
  const next = new Set(collapsedBlocks.value)
  if (next.has(block.id)) {
    next.delete(block.id)
  } else {
    next.add(block.id)
    if (block.resources.some((resource) => resource.id === expandedId.value)) {
      expandedId.value = null
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

function kindLabel(resource: { kind: GaEinsatzKind; stock: number; stayMode: GaEinsatzStayMode }): string {
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

function cellStyle(column: { startMs: number; endMs: number }): { left: string; width: string } {
  const startMs = windowRange.value.start.getTime()
  const span = windowRange.value.end.getTime() - startMs
  return {
    left: `${((column.startMs - startMs) / span) * 100}%`,
    width: `${((column.endMs - column.startMs) / span) * 100}%`,
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
  if (role === 'handover' || role === 'giveback' || role === 'service') {
    return `${statusLabel(booking)} ${barTime(booking)}`
  }
  return `${booking.ressort} ${barTime(booking)}`
}

function barTitle(booking: GaPreviewEinsatz): string {
  return `${booking.objectName} · ${booking.who} · ${booking.ressort} · ${booking.fromLabel} – ${booking.toLabel}`
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

.ga-gantt__pin {
  position: sticky;
  top: 0;
  z-index: 8;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: #f5f5f5;
  padding: 4px 0 0;
  box-shadow: 0 8px 12px -8px rgb(17 24 39 / 18%);
}

.ga-gantt__pin:not(:has(.ga-gantt__head)) {
  padding-bottom: 12px;
}

.ga-gantt__head,
.ga-gantt__body {
  display: grid;
  min-width: 720px;
}

.ga-gantt__head {
  z-index: 5;
  background: var(--ga-table-head);
  border: 1px solid var(--ga-table-border);
  border-bottom: 2px solid var(--ga-col-stroke);
  border-radius: 10px 10px 0 0;
}

.ga-gantt__body {
  border: 1px solid var(--ga-table-border);
  border-top: 0;
  border-radius: 0 0 10px 10px;
  background: var(--ga-table-row);
}

.ga-gantt__corner,
.ga-gantt__col-head {
  background: var(--ga-table-head);
  border-bottom: 2px solid var(--ga-col-stroke);
  padding: 4px 2px;
  font-size: 0.68rem;
  color: var(--color-text-muted, #6b7280);
}

.ga-gantt__corner {
  position: sticky;
  left: 0;
  z-index: 6;
  font-weight: 600;
  color: var(--color-text, #111827);
}

.ga-gantt__col-head {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
  min-width: 0;
  overflow: hidden;
  border-left: 3px solid var(--ga-col-stroke);
  cursor: pointer;
  font: inherit;
  font-size: 0.68rem;
  line-height: 1.15;
  font-variant-numeric: tabular-nums;
}

.ga-gantt__col-head--hour {
  font-size: 0.55rem;
  font-weight: 600;
  padding: 3px 0;
  letter-spacing: 0;
}

.ga-gantt__col-head:disabled {
  cursor: default;
}

.ga-gantt__col-head--weekend {
  background: var(--ga-table-hover);
}

.ga-gantt__col-sub {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.ga-gantt__ring {
  padding: 8px 10px 6px;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-text, #111827);
  background: #eef2f7;
  border-top: 2px solid var(--ga-col-stroke);
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
  padding: 5px 8px;
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
  position: sticky;
  left: 0;
  z-index: 3;
  border: 0;
  border-bottom: 1px solid var(--ga-table-line);
  background: var(--ga-table-row);
  color: #374151;
  text-align: left;
  padding: 6px 8px;
  cursor: pointer;
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
  cursor: pointer;
}

.ga-gantt__track--open {
  background-color: var(--ga-table-hover);
}

.ga-gantt__cell {
  position: absolute;
  inset: 0 auto 0 0;
  border-left: 3px solid var(--ga-col-stroke);
  pointer-events: none;
}

.ga-gantt__cell--weekend {
  background: color-mix(in srgb, var(--ga-table-hover) 70%, transparent);
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

.ga-gantt__detail {
  border-bottom: 1px solid var(--ga-table-border);
  background: var(--ga-table-head);
  padding: 8px 12px 12px;
}

.ga-gantt__empty {
  margin: 0;
  font-size: 0.82rem;
  color: var(--color-text-muted, #6b7280);
}

.ga-gantt__bookings {
  list-style: none;
  margin: 0;
  padding: 0;
}

.ga-gantt__bookings li {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  padding: 8px 0;
  font-size: 0.82rem;
  border-top: 1px solid var(--ga-table-line);
}

.ga-gantt__bookings li:first-child {
  border-top: 0;
}

.ga-gantt__booking--conflict {
  background: var(--color-error-bg);
  margin: 0 -8px;
  padding-inline: 8px;
  border-radius: 6px;
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
