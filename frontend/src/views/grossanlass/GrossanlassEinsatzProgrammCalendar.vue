<template>
  <div class="prog-cal" :class="{ 'is-moving': moving }">
    <header class="prog-cal__header">
      <div class="prog-cal__kinds">
        <button
          v-for="kind in kindFilters"
          :key="kind.id"
          type="button"
          class="prog-cal__kind"
          :class="{ 'is-on': kindOn[kind.id] }"
          :style="kindOn[kind.id] ? { background: kind.color } : undefined"
          @click="kindOn[kind.id] = !kindOn[kind.id]"
        >
          <i :style="{ background: kind.color }" />
          {{ kind.label }}
        </button>
      </div>
      <div class="prog-cal__focus">
        <v-menu location="bottom center">
          <template #activator="{ props: menuProps }">
            <button type="button" class="prog-cal__period" v-bind="menuProps">
              <span>{{ periodLabel }}</span>
              <small v-if="periodRangeLabel">{{ periodRangeLabel }}</small>
              <v-icon icon="mdi-chevron-down" size="18" />
            </button>
          </template>
          <v-list density="compact" min-width="280">
            <v-list-item
              v-for="period in periods"
              :key="period.id"
              :title="period.name"
              :subtitle="periodRange(period)"
              :active="selectedPeriodId === period.id"
              @click="selectPeriod(period)"
            />
            <v-list-item v-if="!periods.length" :title="t('grossanlass.planung.calFixedEmpty')" disabled />
          </v-list>
        </v-menu>
        <div class="prog-cal__zoom">
          <button type="button" :aria-label="t('grossanlass.planung.calZoomLess')" :disabled="zoomDays <= 1" @click="changeZoom(-1)">−</button>
          <span>{{ zoomDays }} {{ t('grossanlass.planung.calZoomDays') }}</span>
          <button type="button" :aria-label="t('grossanlass.planung.calZoomMore')" :disabled="zoomDays >= 21" @click="changeZoom(1)">+</button>
        </div>
      </div>
      <div class="prog-cal__tools">
        <v-menu location="bottom end" :close-on-content-click="false">
          <template #activator="{ props: menuProps }">
            <button
              type="button"
              class="prog-cal__icon"
              :class="{ 'is-on': filterActive }"
              v-bind="menuProps"
              :aria-label="t('grossanlass.planung.calFilterMine', { n: mineCount })"
            >
              <v-icon icon="mdi-filter-variant" size="20" />
            </button>
          </template>
          <div class="prog-cal__filter">
            <label class="prog-cal__check">
              <input v-model="onlyMine" type="checkbox">
              <span>{{ t('grossanlass.planung.calFilterMine', { n: mineCount }) }}</span>
            </label>
            <p class="prog-cal__filter-label">{{ t('grossanlass.planung.calFilterMore') }}</p>
            <label v-for="row in ressortOptions" :key="row.id" class="prog-cal__check">
              <input v-model="selectedRessortIds" type="checkbox" :value="row.id">
              <span>{{ row.name }} ({{ row.count }})</span>
            </label>
          </div>
        </v-menu>
        <button
          type="button"
          class="prog-cal__icon"
          :class="{ 'is-on': !locked }"
          :aria-label="locked ? t('grossanlass.planung.calUnlock') : t('grossanlass.planung.calLock')"
          @click="locked = !locked"
        >
          <v-icon :icon="locked ? 'mdi-lock-outline' : 'mdi-lock-open-variant-outline'" size="20" />
        </button>
        <v-menu location="bottom end">
          <template #activator="{ props: menuProps }">
            <button type="button" class="prog-cal__icon" v-bind="menuProps" :aria-label="t('grossanlass.planung.calMenu')">
              <v-icon icon="mdi-dots-vertical" size="20" />
            </button>
          </template>
          <v-list density="compact" min-width="220">
            <v-list-item :title="t('grossanlass.planung.calExportPdf')" prepend-icon="mdi-file-pdf-box" @click="later" />
            <v-list-item :title="t('grossanlass.planung.calPrintPool')" prepend-icon="mdi-printer" @click="later" />
          </v-list>
        </v-menu>
      </div>
    </header>

    <div class="prog-cal__scroll">
      <div class="prog-cal__grid" :style="gridStyle">
        <div class="prog-cal__corner">
          <button type="button" class="prog-cal__nav" @click="shiftDays(-1)">
            <v-icon icon="mdi-chevron-left" size="18" />
          </button>
        </div>
        <div
          v-for="(day, index) in days"
          :key="day.key"
          class="prog-cal__head"
          :class="{ 'is-today': day.today }"
        >
          {{ day.label }}
          <button
            v-if="index === days.length - 1"
            type="button"
            class="prog-cal__nav prog-cal__nav--end"
            @click="shiftDays(1)"
          >
            <v-icon icon="mdi-chevron-right" size="18" />
          </button>
        </div>
        <div class="prog-cal__hours">
          <span
            v-for="(hour, index) in hours"
            :key="hour"
            :style="{ top: `${index * 42}px` }"
          >{{ hourLabel(hour) }}</span>
        </div>
        <div
          v-for="day in days"
          :key="`col-${day.key}`"
          class="prog-cal__col"
          :class="{ 'is-today': day.today }"
          :data-day="day.key"
          @click="onColumnClick(day.date, $event)"
        >
          <span
            v-for="hour in hours"
            :key="`line-${day.key}-${hour}`"
            class="prog-cal__hline"
            :style="{ top: `${(hour - 7) * 42}px` }"
          />
          <div
            v-for="slot in laidBlocks(day.date)"
            :key="slot.block.key"
            class="prog-cal__block"
            :class="{ 'is-locked': locked }"
            :style="blockStyle(slot.block, slot.col, slot.cols, slot.z)"
            @mousedown="startDrag(slot.block, $event)"
            @click="openBlock(slot.block)"
          >
            <template v-if="!isTiming(slot.block)">
              <strong class="prog-cal__name">{{ blockCaption(slot.block).name }}</strong>
              <span v-if="blockCaption(slot.block).no" class="prog-cal__num">{{ blockCaption(slot.block).no }}</span>
            </template>
            <span v-else class="prog-cal__clock">{{ clockLabel(slot.block) }}</span>
            <button
              v-if="!isTiming(slot.block)"
              type="button"
              class="prog-cal__edit"
              :aria-label="t('grossanlass.planung.calEditTask')"
              @mousedown.stop
              @click.stop="editBlock(slot.block)"
            >
              <v-icon icon="mdi-pencil" size="16" />
            </button>
            <button
              v-if="!locked"
              type="button"
              class="prog-cal__resize"
              :aria-label="t('grossanlass.planung.calResize')"
              @mousedown.stop.prevent="startResize(slot.block, $event, 'end')"
            >
              <v-icon icon="mdi-drag-horizontal" size="16" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <EDialog v-model="createOpen" :title="t('grossanlass.planung.calNewTask')" max-width="440">
      <div class="prog-cal__create">
        <ETextField v-model="createTitle" :label="t('grossanlass.planung.calNewTaskTitle')" />
        <ESelect
          v-model="createGroupId"
          :items="projectItems"
          :label="t('grossanlass.planung.calNewTaskProject')"
        />
      </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="createOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!createTitle.trim() || !createGroupId" @click="saveNewTask">
          {{ t('common.save') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import {
  calendarPeriodSortStamp,
  listDepartmentCalendarPeriods,
  type DepartmentCalendarPeriod,
} from '@/api/calendarPeriods'
import {
  createGrossanlassBauprojektTask,
  getGrossanlassBauprojekt,
  updateGrossanlassBauprojektTask,
  type GaBauprojektTask,
} from '@/api/grossanlassBauprojekt'
import { updateGrossanlassEinsatz } from '@/api/grossanlassUebersicht'
import { updateGrossanlassGroup, type GrossanlassGroup } from '@/api/grossanlassGroups'

const emit = defineEmits<{
  open: [payload: { kind: 'bau' | 'fahrt'; id: string }]
}>()

const props = defineProps<{
  departmentId: string
  groups: GrossanlassGroup[]
  trips: Array<{
    id: string
    name: string
    detail: string
    from: string
    to: string
    groupId: string | null
  }>
}>()

const { t, locale } = useI18n()
const toast = useToast()
const authStore = useAuthStore()

const HOUR_START = 7
const HOUR_END = 23
const PX = 42

type CalLane = 'bauprojekt' | 'ressort' | 'fahrt'

type Block = {
  key: string
  kind: 'bau' | 'fahrt' | 'fenster'
  lane: CalLane
  taskId: string
  groupId: string
  projectName: string
  title: string
  sortOrder: number
  start: Date
  durationMin: number
  windowStart?: string
  windowEnd?: string
}

const LANE_COLOR: Record<CalLane, string> = {
  bauprojekt: '#86efac',
  ressort: '#93c5fd',
  fahrt: '#fdba74',
}

const anchor = ref(startOfDay(new Date()))
const periods = ref<DepartmentCalendarPeriod[]>([])
const blocks = ref<Block[]>([])
const windows = ref<Record<string, { start: string; end: string }>>({})
const selectedPeriodId = ref<string | null>(null)
const zoomDays = ref(7)
const onlyMine = ref(false)
const kindOn = reactive<Record<CalLane, boolean>>({
  bauprojekt: true,
  ressort: true,
  fahrt: true,
})
const selectedRessortIds = ref<string[]>([])
const locked = ref(true)
const moving = ref(false)
const skipClick = ref(false)
const resizing = ref<{
  block: Block
  edge: 'start' | 'end'
  startY: number
  startDuration: number
  origin: Date
} | null>(null)
const spanClock = ref<Record<string, { startMin: number; durationMin: number }>>({})
const createOpen = ref(false)
const createTitle = ref('')
const createGroupId = ref('')
const createSlot = ref<{ day: Date; minutes: number } | null>(null)
const dragging = ref<{ block: Block; startY: number; origin: Date } | null>(null)
const hours = computed(() => {
  const list: number[] = []
  for (let hour = HOUR_START; hour <= HOUR_END; hour += 1) list.push(hour)
  return list
})

const selectedPeriod = computed(() =>
  periods.value.find((period) => period.id === selectedPeriodId.value) ?? null,
)

const days = computed(() => {
  const todayKey = ymd(new Date())
  const list = []
  for (let i = 0; i < zoomDays.value; i += 1) {
    const date = addDays(anchor.value, i)
    const key = ymd(date)
    list.push({
      key,
      date,
      today: key === todayKey,
      label: date.toLocaleDateString(locale.value, { weekday: 'short', day: '2-digit', month: '2-digit' }),
    })
  }
  return list
})

const periodLabel = computed(() => selectedPeriod.value?.name || t('grossanlass.planung.calPeriodPlaceholder'))

const periodRangeLabel = computed(() => selectedPeriod.value ? periodRange(selectedPeriod.value) : '')

const groupById = computed(() => new Map(props.groups.map((group) => [group.id, group])))

const gridStyle = computed(() => ({
  gridTemplateColumns: `56px repeat(${days.value.length}, minmax(0, 1fr))`,
}))

const modeBlocks = computed(() => blocks.value.filter((block) => block.kind === 'bau' || block.kind === 'fahrt'))

const kindFilters = computed(() => ([
  { id: 'bauprojekt' as const, label: t('grossanlass.planung.calKindBau'), color: LANE_COLOR.bauprojekt },
  { id: 'ressort' as const, label: t('grossanlass.planung.calKindRessort'), color: LANE_COLOR.ressort },
  { id: 'fahrt' as const, label: t('grossanlass.planung.calKindFahrt'), color: LANE_COLOR.fahrt },
]))

const visibleBlocks = computed(() =>
  modeBlocks.value.filter((block) => {
    if (!kindOn[block.lane]) return false
    if (onlyMine.value && !isMine(block.groupId)) return false
    if (selectedRessortIds.value.length) {
      const ressort = ressortOf(block.groupId)
      if (!ressort || !selectedRessortIds.value.includes(ressort.id)) return false
    }
    return true
  }),
)

const mineCount = computed(() => modeBlocks.value.filter((block) => isMine(block.groupId)).length)

const ressortOptions = computed(() => {
  const map = new Map<string, { id: string; name: string; count: number }>()
  for (const block of modeBlocks.value) {
    const ressort = ressortOf(block.groupId)
    if (!ressort) continue
    const row = map.get(ressort.id) ?? { id: ressort.id, name: ressort.name, count: 0 }
    row.count += 1
    map.set(ressort.id, row)
  }
  return [...map.values()].sort((a, b) => a.name.localeCompare(b.name, locale.value))
})

const filterActive = computed(() =>
  onlyMine.value || selectedRessortIds.value.length > 0 || !kindOn.bauprojekt || !kindOn.ressort || !kindOn.fahrt,
)

function later() {
  toast.info(t('grossanlass.planung.calLater'))
}

function laneOf(group: GrossanlassGroup): CalLane {
  return group.node_type === 'bauprojekt' ? 'bauprojekt' : 'ressort'
}

function parseDay(value: string): Date {
  return new Date(`${value.slice(0, 10)}T00:00:00`)
}

function periodDayCount(period: DepartmentCalendarPeriod): number {
  const start = parseDay(period.start_date)
  const end = parseDay(period.end_date || period.start_date)
  const diff = Math.round((end.getTime() - start.getTime()) / 86400000) + 1
  if (!Number.isFinite(diff) || diff < 1) return 1
  return diff
}

function periodRange(period: DepartmentCalendarPeriod): string {
  const start = parseDay(period.start_date)
  const end = parseDay(period.end_date || period.start_date)
  const fmt = (date: Date) => date.toLocaleDateString(locale.value, { day: '2-digit', month: '2-digit', year: 'numeric' })
  const from = fmt(start)
  const to = fmt(end)
  return from === to ? from : `${from} – ${to}`
}

function changeZoom(delta: number) {
  zoomDays.value = Math.min(21, Math.max(1, zoomDays.value + delta))
}

function ymd(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

function localIso(date: Date): string {
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}:00`
}

function startOfDay(date: Date): Date {
  const next = new Date(date)
  next.setHours(0, 0, 0, 0)
  return next
}

function addDays(date: Date, amount: number): Date {
  const next = new Date(date)
  next.setDate(next.getDate() + amount)
  return next
}

function sameDay(a: Date, b: Date): boolean {
  return ymd(a) === ymd(b)
}

function minutesOf(date: Date): number {
  return date.getHours() * 60 + date.getMinutes()
}

function hourLabel(hour: number): string {
  return `${String(hour).padStart(2, '0')}:00`
}

function chainOf(groupId: string): GrossanlassGroup[] {
  const chain: GrossanlassGroup[] = []
  let current = groupById.value.get(groupId)
  while (current) {
    chain.push(current)
    current = current.parent_id ? groupById.value.get(current.parent_id) : undefined
  }
  return chain
}

function userOn(group: GrossanlassGroup): boolean {
  const userId = authStore.userId
  if (!userId) return false
  return [...group.members, ...group.leaders].some((member) => member.user_id === userId)
}

function isMine(groupId: string): boolean {
  return chainOf(groupId).some(userOn)
}

function ressortOf(groupId: string): GrossanlassGroup | null {
  const chain = chainOf(groupId)
  return chain.find((group) => group.node_type === 'ressort') ?? chain[chain.length - 1] ?? null
}

function blockCaption(block: Block): { name: string; no: string } {
  const project = groupById.value.get(block.groupId)
  const name = project?.node_type === 'bauprojekt' ? project.name : block.projectName
  if (block.kind !== 'bau') return { name, no: '' }
  const tasks = blocks.value
    .filter((item) => item.kind === 'bau' && item.groupId === block.groupId)
    .slice()
    .sort((a, b) => a.sortOrder - b.sortOrder || a.start.getTime() - b.start.getTime())
  const taskNo = tasks.findIndex((item) => item.key === block.key) + 1
  return { name, no: String(taskNo || 1) }
}

function blocksOn(date: Date): Block[] {
  return visibleBlocks.value.filter((block) => sameDay(block.start, date))
}

function blockEnd(block: Block): number {
  return block.start.getTime() + block.durationMin * 60_000
}

function rangesOverlap(a: Block, b: Block): boolean {
  return a.start.getTime() < blockEnd(b) && b.start.getTime() < blockEnd(a)
}

function laidBlocks(date: Date): Array<{ block: Block; col: number; cols: number; z: number }> {
  const sorted = blocksOn(date).slice().sort((a, b) => a.start.getTime() - b.start.getTime() || b.durationMin - a.durationMin)
  const background = new Set<string>()
  for (const block of sorted) {
    const earlier = sorted.some((other) => other.key !== block.key && other.start.getTime() < block.start.getTime() && rangesOverlap(block, other))
    const later = sorted.some((other) => other.key !== block.key && other.start.getTime() > block.start.getTime() && rangesOverlap(block, other))
    if (later && !earlier) background.add(block.key)
  }
  const foreground = sorted.filter((block) => !background.has(block.key))
  const columnEnds: number[] = []
  const placed: Array<{ block: Block; col: number }> = []
  for (const block of foreground) {
    const start = block.start.getTime()
    let col = columnEnds.findIndex((end) => end <= start)
    if (col < 0) {
      col = columnEnds.length
      columnEnds.push(blockEnd(block))
    } else {
      columnEnds[col] = blockEnd(block)
    }
    placed.push({ block, col })
  }
  const front = placed.map((item) => {
    const cluster = placed.filter((other) => rangesOverlap(item.block, other.block))
    const cols = Math.max(1, ...cluster.map((other) => other.col + 1))
    return { block: item.block, col: item.col, cols, z: 4 }
  })
  const back = sorted
    .filter((block) => background.has(block.key))
    .map((block) => ({ block, col: 0, cols: 1, z: 2 }))
  return [...back, ...front]
}

function topFor(minutes: number): number {
  return ((minutes - HOUR_START * 60) / 60) * PX
}

function blockStyle(block: Block, col = 0, cols = 1, z = 2): Record<string, string> {
  const top = Math.max(0, topFor(minutesOf(block.start)))
  const height = Math.max(28, (block.durationMin / 60) * PX)
  const slice = 100 / Math.max(cols, 1)
  return {
    top: `${top}px`,
    height: `${height}px`,
    background: LANE_COLOR[block.lane],
    left: `calc(${col * slice}% + 2px)`,
    width: `calc(${slice}% - 4px)`,
    right: 'auto',
    zIndex: String(z),
  }
}

function shiftDays(amount: number) {
  anchor.value = addDays(anchor.value, amount)
}

function selectPeriod(period: DepartmentCalendarPeriod) {
  selectedPeriodId.value = period.id
  const start = parseDay(period.start_date)
  if (!Number.isNaN(start.getTime())) anchor.value = start
  zoomDays.value = Math.min(21, Math.max(1, periodDayCount(period)))
}

function applyDefaultPeriod() {
  if (selectedPeriodId.value || !periods.value.length) return
  const [first] = [...periods.value].sort((a, b) =>
    calendarPeriodSortStamp(a.start_date, a.start_time, '00:00')
      .localeCompare(calendarPeriodSortStamp(b.start_date, b.start_time, '00:00')),
  )
  if (first) selectPeriod(first)
}

function snapMinutes(deltaPx: number): number {
  return Math.round((deltaPx / PX) * 60 / 15) * 15
}

const projectItems = computed(() =>
  props.groups
    .filter((group) => group.node_type === 'bauprojekt')
    .map((group) => ({ title: group.name, value: group.id })),
)

function onColumnClick(day: Date, event: MouseEvent) {
  if (moving.value || skipClick.value) return
  const target = event.target as HTMLElement
  if (target.closest('.prog-cal__block')) return
  const column = event.currentTarget as HTMLElement
  const y = event.clientY - column.getBoundingClientRect().top
  const raw = HOUR_START * 60 + Math.round((y / PX) * 60 / 15) * 15
  const minutes = Math.min((HOUR_END * 60) - 15, Math.max(HOUR_START * 60, raw))
  createSlot.value = { day: new Date(day), minutes }
  createTitle.value = ''
  createGroupId.value = projectItems.value[0]?.value || ''
  if (!projectItems.value.length) {
    toast.error(t('grossanlass.planung.calNewTaskEmpty'))
    return
  }
  createOpen.value = true
}

async function saveNewTask() {
  const slot = createSlot.value
  const groupId = createGroupId.value
  const title = createTitle.value.trim()
  if (!slot || !groupId || !title) return
  const start = new Date(slot.day)
  start.setHours(Math.floor(slot.minutes / 60), slot.minutes % 60, 0, 0)
  try {
    await createGrossanlassBauprojektTask(props.departmentId, groupId, {
      title,
      starts_at: localIso(start),
      duration_minutes: 60,
    })
    createOpen.value = false
    await loadBlocks()
    await syncWindowFromTasks(groupId)
  } catch {
    toast.error(t('grossanlass.planung.ressorts.errorSave'))
  }
}

function startResize(block: Block, event: MouseEvent, edge: 'start' | 'end') {
  if (locked.value) return
  resizing.value = {
    block,
    edge,
    startY: event.clientY,
    startDuration: block.durationMin,
    origin: new Date(block.start),
  }
  moving.value = true
  window.addEventListener('mousemove', onPointerMove)
  window.addEventListener('mouseup', onPointerUp)
}

function openBlock(block: Block) {
  if (!locked.value) return
  editBlock(block)
}

function editBlock(block: Block) {
  emit('open', {
    kind: block.kind === 'fahrt' ? 'fahrt' : 'bau',
    id: block.kind === 'fahrt' ? block.taskId : block.groupId,
  })
}

function isTiming(block: Block): boolean {
  return resizing.value?.block.key === block.key || dragging.value?.block.key === block.key
}

function clockLabel(block: Block): string {
  const end = new Date(block.start)
  end.setMinutes(end.getMinutes() + block.durationMin)
  const fmt = (date: Date) => `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
  return `${fmt(block.start)}–${fmt(end)}`
}

function startDrag(block: Block, event: MouseEvent) {
  if (locked.value) return
  if ((event.target as HTMLElement).closest('.prog-cal__resize')) return
  event.preventDefault()
  dragging.value = { block, startY: event.clientY, origin: new Date(block.start) }
  moving.value = true
  window.addEventListener('mousemove', onPointerMove)
  window.addEventListener('mouseup', onPointerUp)
}

function onPointerMove(event: MouseEvent) {
  const resize = resizing.value
  if (resize) {
    const delta = snapMinutes(event.clientY - resize.startY)
    if (resize.edge === 'end') {
      resize.block.durationMin = Math.max(15, resize.startDuration + delta)
      return
    }
    const duration = Math.max(15, resize.startDuration - delta)
    const applied = resize.startDuration - duration
    const next = new Date(resize.origin)
    next.setMinutes(next.getMinutes() + applied)
    clampStart(next)
    resize.block.start = next
    resize.block.durationMin = duration
    return
  }
  const drag = dragging.value
  if (!drag) return
  const delta = snapMinutes(event.clientY - drag.startY)
  const next = new Date(drag.origin)
  next.setMinutes(next.getMinutes() + delta)
  const column = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-day]')
  const dayKey = column?.getAttribute('data-day')
  if (dayKey) {
    const [year, month, day] = dayKey.split('-').map(Number)
    next.setFullYear(year, month - 1, day)
  }
  clampStart(next)
  drag.block.start = next
}

function clampStart(next: Date) {
  const minStart = HOUR_START * 60
  const maxStart = HOUR_END * 60 - 15
  const minutes = minutesOf(next)
  if (minutes < minStart) next.setHours(HOUR_START, 0, 0, 0)
  if (minutes > maxStart) next.setHours(Math.floor(maxStart / 60), maxStart % 60, 0, 0)
}

async function onPointerUp() {
  const resize = resizing.value
  const drag = dragging.value
  resizing.value = null
  dragging.value = null
  moving.value = false
  if (resize || drag) {
    skipClick.value = true
    window.setTimeout(() => { skipClick.value = false }, 0)
  }
  window.removeEventListener('mousemove', onPointerMove)
  window.removeEventListener('mouseup', onPointerUp)
  if (resize?.block.kind === 'fenster') await saveFensterShape(resize.block, resize.origin)
  else if (resize) await saveBlock(resize.block)
  if (drag?.block.kind === 'fenster') await saveFensterShape(drag.block, drag.origin)
  else if (drag) await saveBlock(drag.block)
}

async function saveFensterShape(block: Block, origin: Date) {
  const dayDelta = Math.round((startOfDay(block.start).getTime() - startOfDay(origin).getTime()) / 86400000)
  const minuteDelta = minutesOf(block.start) - minutesOf(origin)
  spanClock.value = {
    ...spanClock.value,
    [block.groupId]: { startMin: minutesOf(block.start), durationMin: block.durationMin },
  }
  try {
    if (block.windowStart && block.windowEnd && dayDelta) {
      const start = ymd(addDays(parseDay(block.windowStart), dayDelta))
      const end = ymd(addDays(parseDay(block.windowEnd), dayDelta))
      windows.value = { ...windows.value, [block.groupId]: { start, end } }
      block.windowStart = start
      block.windowEnd = end
      await updateGrossanlassGroup(props.departmentId, block.groupId, {
        window_start: start,
        window_end: end,
      })
    }
    if (dayDelta || minuteDelta) await shiftProjectTasks(block.groupId, dayDelta, minuteDelta)
    await syncWindowFromTasks(block.groupId)
  } catch {
    toast.error(t('grossanlass.planung.ressorts.errorSave'))
    await loadBlocks()
  }
}

async function shiftProjectTasks(groupId: string, dayDelta: number, minuteDelta: number) {
  const tasks = blocks.value.filter((block) => block.kind === 'bau' && block.groupId === groupId)
  await Promise.all(tasks.map(async (task) => {
    const next = new Date(task.start)
    next.setDate(next.getDate() + dayDelta)
    next.setMinutes(next.getMinutes() + minuteDelta)
    clampStart(next)
    task.start = next
    await updateGrossanlassBauprojektTask(props.departmentId, groupId, task.taskId, {
      starts_at: localIso(next),
      duration_minutes: task.durationMin,
    })
  }))
}

async function syncWindowFromTasks(groupId: string) {
  const tasks = blocks.value.filter((block) => block.kind === 'bau' && block.groupId === groupId)
  if (!tasks.length) return
  let start = ymd(tasks[0].start)
  let end = start
  for (const task of tasks) {
    const from = ymd(task.start)
    const finish = new Date(task.start)
    finish.setMinutes(finish.getMinutes() + task.durationMin)
    const to = ymd(finish)
    if (from < start) start = from
    if (to > end) end = to
  }
  windows.value = { ...windows.value, [groupId]: { start, end } }
  await updateGrossanlassGroup(props.departmentId, groupId, {
    window_start: start,
    window_end: end,
  })
}

async function saveBlock(block: Block) {
  try {
    if (block.kind === 'fahrt') {
      const end = new Date(block.start)
      end.setMinutes(end.getMinutes() + block.durationMin)
      await updateGrossanlassEinsatz(props.departmentId, block.taskId, {
        from: localIso(block.start),
        to: localIso(end),
      })
      return
    }
    await updateGrossanlassBauprojektTask(props.departmentId, block.groupId, block.taskId, {
      starts_at: localIso(block.start),
      duration_minutes: block.durationMin,
    })
    await syncWindowFromTasks(block.groupId)
  } catch {
    toast.error(t('grossanlass.planung.ressorts.errorSave'))
    await loadBlocks()
  }
}

async function loadBlocks() {
  const projects = props.groups.filter((group) =>
    group.node_type === 'bauprojekt' || group.node_type === 'ressort' || group.node_type === 'unterressort',
  )
  const packs = await Promise.all(projects.map(async (project) => {
    try {
      const briefing = await getGrossanlassBauprojekt(props.departmentId, project.id)
      return (briefing.tasks ?? []).map((task) => toBlock(project, task)).filter((row): row is Block => !!row)
    } catch {
      return [] as Block[]
    }
  }))
  blocks.value = [...packs.flat(), ...tripBlocks()]
}

function tripBlocks(): Block[] {
  return props.trips.flatMap((trip) => {
    const start = new Date(trip.from)
    if (Number.isNaN(start.getTime())) return []
    const end = new Date(trip.to)
    const durationMin = Number.isNaN(end.getTime())
      ? 60
      : Math.max(15, Math.round((end.getTime() - start.getTime()) / 60000))
    return [{
      key: `fahrt-${trip.id}`,
      lane: 'fahrt' as const,
      kind: 'fahrt' as const,
      taskId: trip.id,
      groupId: trip.groupId || trip.id,
      projectName: trip.name,
      title: trip.detail,
      sortOrder: 0,
      start,
      durationMin,
    }]
  })
}

function toBlock(project: GrossanlassGroup, task: GaBauprojektTask): Block | null {
  if (!task.starts_at) return null
  const start = new Date(task.starts_at)
  if (Number.isNaN(start.getTime())) return null
  return {
    key: task.id,
    kind: 'bau',
    lane: laneOf(project),
    taskId: task.id,
    groupId: project.id,
    projectName: project.name,
    title: task.title.trim() || task.description?.trim() || project.name,
    sortOrder: task.sort_order ?? 0,
    start,
    durationMin: task.duration_minutes && task.duration_minutes > 0 ? task.duration_minutes : 60,
  }
}

async function load() {
  if (!props.departmentId) return
  const [periodRows] = await Promise.all([
    listDepartmentCalendarPeriods(props.departmentId).catch(() => [] as DepartmentCalendarPeriod[]),
    loadBlocks(),
  ])
  periods.value = periodRows
  applyDefaultPeriod()
}

watch(
  () => props.groups,
  (groups) => {
    const next: Record<string, { start: string; end: string }> = {}
    for (const group of groups) {
      if (group.node_type !== 'bauprojekt' || !group.window_start) continue
      next[group.id] = {
        start: group.window_start.slice(0, 10),
        end: (group.window_end || group.window_start).slice(0, 10),
      }
    }
    windows.value = next
  },
  { immediate: true },
)

watch(
  () => [props.departmentId, props.groups.map((group) => group.id).join('|'), props.trips.map((trip) => trip.id).join('|')],
  () => { void load() },
)

onMounted(() => { void load() })
onBeforeUnmount(() => {
  window.removeEventListener('mousemove', onPointerMove)
  window.removeEventListener('mouseup', onPointerUp)
})
</script>

<style scoped>
.prog-cal__header {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  min-height: 48px;
  margin: 0 0 0;
  padding: 4px 4px 8px;
  border-bottom: 1px solid #e5e7eb;
}
.prog-cal__kinds {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.prog-cal__kind {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid var(--color-border);
  background: #fff;
  color: var(--color-text-muted);
  border-radius: 999px;
  padding: 3px 10px 3px 6px;
  font: inherit;
  font-size: 0.75rem;
  cursor: pointer;
}
.prog-cal__kind i {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  opacity: 0.45;
}
.prog-cal__kind.is-on {
  color: var(--color-text);
  font-weight: 600;
  border-color: transparent;
}
.prog-cal__kind.is-on i { opacity: 1; }
.prog-cal__brand {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  min-width: 0;
}
.prog-cal__create {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.prog-cal__switch {
  position: relative;
  display: grid;
  grid-template-columns: 1fr 1fr;
  background: var(--color-primary-muted-bg);
  border: 1px solid var(--color-primary-muted-border);
  border-radius: 999px;
  padding: 2px;
}
.prog-cal__switch-knob {
  position: absolute;
  top: 2px;
  bottom: 2px;
  left: 2px;
  width: calc(50% - 2px);
  border-radius: 999px;
  background: var(--color-primary);
  box-shadow: 0 1px 2px var(--color-primary-shadow);
  transition: transform 0.18s ease;
}
.prog-cal__switch-knob.is-tasks { transform: translateX(100%); }
.prog-cal__switch button {
  position: relative;
  z-index: 1;
  border: 0;
  background: transparent;
  border-radius: 999px;
  padding: 4px 12px;
  font: inherit;
  font-size: 0.75rem;
  cursor: pointer;
  color: var(--color-primary-dark);
  white-space: nowrap;
}
.prog-cal__switch button.is-on {
  color: var(--emc-logo-fg);
  font-weight: 600;
}
.prog-cal__focus {
  justify-self: center;
  display: flex;
  align-items: center;
  gap: 12px;
}
.prog-cal__period {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 0;
  background: transparent;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  padding: 6px 10px;
  border-radius: 8px;
}
.prog-cal__period small {
  font-weight: 500;
  color: #64748b;
}
.prog-cal__zoom {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
}
.prog-cal__zoom button {
  width: 28px;
  height: 28px;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
}
.prog-cal__zoom button:disabled {
  opacity: 0.4;
  cursor: default;
}
.prog-cal__period:hover { background: #f1f5f9; }
.prog-cal__tools {
  justify-self: end;
  display: flex;
  align-items: center;
  gap: 2px;
}
.prog-cal__icon {
  width: 36px;
  height: 36px;
  border: 0;
  background: transparent;
  border-radius: 8px;
  cursor: pointer;
  color: #334155;
}
.prog-cal__icon:hover,
.prog-cal__icon.is-on { background: #f1f5f9; }
.prog-cal__filter {
  min-width: 240px;
  padding: 10px 12px;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.prog-cal__filter-label {
  margin: 4px 0 0;
  font-size: 0.75rem;
  color: #64748b;
}
.prog-cal__check {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.88rem;
}
.prog-cal__scroll { overflow-x: auto; }
.prog-cal__grid { display: grid; width: 100%; }
.prog-cal__corner {
  height: 40px;
  display: flex;
  align-items: center;
}
.prog-cal__nav {
  border: 0;
  background: transparent;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  cursor: pointer;
}
.prog-cal__nav:hover { background: #f1f5f9; }
.prog-cal__head {
  position: relative;
  min-height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-size: 0.72rem;
  line-height: 1.15;
  text-align: center;
  border-bottom: 1px solid #e5e7eb;
  border-left: 1px solid #e5e7eb;
  background: #fff;
  padding: 4px 16px;
}
.prog-cal__head.is-today,
.prog-cal__col.is-today { background: #eef8f1; }
.prog-cal__nav--end { position: absolute; right: 0; }
.prog-cal__hours {
  position: relative;
  height: calc(17 * 42px);
}
.prog-cal__hours span {
  position: absolute;
  left: 0;
  height: 42px;
  font-size: 0.72rem;
  color: #64748b;
}
.prog-cal__col {
  position: relative;
  height: calc(17 * 42px);
  border-left: 1px dashed #cbd5e1;
  cursor: pointer;
}
.prog-cal__col.is-today { background: #eef8f1; }
.prog-cal__hline {
  position: absolute;
  left: 0;
  right: 0;
  border-top: 1px dashed #cbd5e1;
  pointer-events: none;
  z-index: 0;
}
.prog-cal__band {
  position: absolute;
  left: 4px;
  right: 4px;
  background: #e5e7eb;
  color: #334155;
  font-size: 0.72rem;
  padding: 2px 6px;
  border-radius: 4px;
  z-index: 1;
  pointer-events: none;
}
.prog-cal__block {
  position: absolute;
  left: 3px;
  right: 3px;
  z-index: 2;
  color: #0f172a;
  border-radius: 4px;
  padding: 2px 16px 12px 4px;
  overflow: hidden;
  font-size: 0.68rem;
  line-height: 1.15;
  text-align: left;
  cursor: grab;
}
.prog-cal__block.is-locked { cursor: pointer; }
.prog-cal.is-moving .prog-cal__block { pointer-events: none; }
.prog-cal__block strong,
.prog-cal__block span {
  display: block;
  white-space: normal;
  overflow-wrap: anywhere;
}
.prog-cal__name {
  font-size: 0.68rem;
  font-weight: 700;
}
.prog-cal__num {
  font-size: 0.68rem;
  font-weight: 600;
  white-space: nowrap;
}
.prog-cal__clock {
  position: absolute;
  top: 3px;
  left: 4px;
  right: 4px;
  font-weight: 600;
  font-size: 0.62rem;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  pointer-events: none;
}
.prog-cal__edit {
  position: absolute;
  top: 2px;
  right: 2px;
  width: 24px;
  height: 24px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #0f172a;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.prog-cal__edit:hover { background: rgba(255, 255, 255, 0.45); }
.prog-cal__resize {
  position: absolute;
  left: 50%;
  bottom: 0;
  width: 28px;
  height: 16px;
  transform: translateX(-50%);
  border: 0;
  background: transparent;
  color: #0f172a;
  cursor: ns-resize;
  pointer-events: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}
</style>
