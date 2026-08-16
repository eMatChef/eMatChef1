<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getDepartmentVehicles,
  getRecentDepartmentVehicles,
  type DepartmentVehicle,
} from '@/api/departmentVehicles'
import { ESearchField } from '@/components/form/base'

const props = withDefaults(
  defineProps<{
    departmentId: string
    activityId: string
    excludedVehicleIds?: string[]
    disabled?: boolean
    reloadToken?: number
  }>(),
  {
    excludedVehicleIds: () => [],
    disabled: false,
    reloadToken: 0,
  },
)

const emit = defineEmits<{
  select: [vehicle: DepartmentVehicle]
}>()

const { t } = useI18n()

const RECENT_QUERIES_KEY_PREFIX = 'emc:vehicle-search-queries:'
const RECENT_QUERIES_MAX = 6
/** Backend max für /vehicles/recent */
const HISTORY_LIMIT = 20
const TILE_LIMIT = 20

const searchQuery = ref('')
const dropdownOpen = ref(false)
const recentLoading = ref(false)
const searchLoading = ref(false)
const historyVehicles = ref<DepartmentVehicle[]>([])
const fleetVehicles = ref<DepartmentVehicle[]>([])
const searchResults = ref<DepartmentVehicle[]>([])
const recentQueries = ref<string[]>([])
const searchRef = ref<InstanceType<typeof ESearchField> | null>(null)
const dropdownStyle = ref<Record<string, string>>({})
const dropdownOpensUp = ref(false)

const DROPDOWN_Z_INDEX = 2500
const DROPDOWN_MAX_HEIGHT = 360
const DROPDOWN_GAP = 4
const VIEWPORT_PADDING = 8
const MIN_DROPDOWN_HEIGHT = 80

let searchTimer: ReturnType<typeof setTimeout> | null = null
let positionListenersBound = false

const excludedSet = computed(() => new Set(props.excludedVehicleIds))

/**
 * Kacheln: zuerst zuletzt genutzte (API-Reihenfolge), danach restliche Flotte (Name).
 */
const tileVehicles = computed(() => {
  const seen = new Set<string>()
  const out: DepartmentVehicle[] = []

  for (const vehicle of historyVehicles.value) {
    if (!vehicle.id || excludedSet.value.has(vehicle.id) || seen.has(vehicle.id)) continue
    seen.add(vehicle.id)
    out.push(vehicle)
    if (out.length >= TILE_LIMIT) return out
  }

  const rest = fleetVehicles.value
    .filter((vehicle) => vehicle.id && !excludedSet.value.has(vehicle.id) && !seen.has(vehicle.id))
    .slice()
    .sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }))

  for (const vehicle of rest) {
    seen.add(vehicle.id)
    out.push(vehicle)
    if (out.length >= TILE_LIMIT) break
  }

  return out
})

const filteredSearchResults = computed(() =>
  searchResults.value.filter((vehicle) => !excludedSet.value.has(vehicle.id)),
)

const hasSearchQuery = computed(() => searchQuery.value.trim().length > 0)

const showRecentQueries = computed(
  () => !hasSearchQuery.value && recentQueries.value.length > 0,
)

const showSearchSection = computed(
  () => hasSearchQuery.value && (searchLoading.value || filteredSearchResults.value.length > 0),
)

const showDropdownEmpty = computed(
  () =>
    dropdownOpen.value &&
    !recentLoading.value &&
    !searchLoading.value &&
    ((hasSearchQuery.value && filteredSearchResults.value.length === 0) ||
      (!hasSearchQuery.value && recentQueries.value.length === 0)),
)

function recentQueriesStorageKey(): string {
  return `${RECENT_QUERIES_KEY_PREFIX}${props.departmentId}`
}

function loadRecentQueries(): void {
  if (typeof localStorage === 'undefined') {
    recentQueries.value = []
    return
  }
  try {
    const raw = localStorage.getItem(recentQueriesStorageKey())
    const parsed = raw ? (JSON.parse(raw) as unknown) : []
    recentQueries.value = Array.isArray(parsed)
      ? parsed.map((q) => String(q).trim()).filter(Boolean).slice(0, RECENT_QUERIES_MAX)
      : []
  } catch {
    recentQueries.value = []
  }
}

function persistRecentQuery(query: string): void {
  const q = query.trim()
  if (!q || typeof localStorage === 'undefined') return
  const next = [q, ...recentQueries.value.filter((x) => x.toLowerCase() !== q.toLowerCase())].slice(
    0,
    RECENT_QUERIES_MAX,
  )
  recentQueries.value = next
  try {
    localStorage.setItem(recentQueriesStorageKey(), JSON.stringify(next))
  } catch {
    /* ignore quota */
  }
}

function vehicleLabel(vehicle: DepartmentVehicle): string {
  return vehicle.plate ? `${vehicle.name} (${vehicle.plate})` : vehicle.name
}

function vehicleMeta(vehicle: DepartmentVehicle): string {
  const parts: string[] = []
  if (vehicle.max_payload_kg != null) {
    parts.push(t('activities.vehicles.payloadKg', { kg: vehicle.max_payload_kg }))
  }
  if (vehicle.owner_label) parts.push(vehicle.owner_label)
  return parts.join(' · ')
}

async function loadHistory(): Promise<void> {
  recentLoading.value = true
  try {
    const [recent, fleet] = await Promise.all([
      getRecentDepartmentVehicles(props.departmentId, {
        activityId: props.activityId,
        limit: HISTORY_LIMIT,
      }),
      getDepartmentVehicles(props.departmentId, {
        activityId: props.activityId,
      }),
    ])
    historyVehicles.value = recent
    fleetVehicles.value = fleet
  } catch {
    historyVehicles.value = []
    fleetVehicles.value = []
  } finally {
    recentLoading.value = false
  }
}

async function runSearch(): Promise<void> {
  const q = searchQuery.value.trim()
  if (!q) {
    searchResults.value = []
    return
  }
  searchLoading.value = true
  try {
    searchResults.value = await getDepartmentVehicles(props.departmentId, {
      activityId: props.activityId,
      search: q,
    })
    if (searchResults.value.length > 0) persistRecentQuery(q)
  } catch {
    searchResults.value = []
  } finally {
    searchLoading.value = false
  }
}

function applyRecentQuery(query: string): void {
  searchQuery.value = query
  void runSearch()
}

watch(
  () => [props.departmentId, props.activityId, props.reloadToken] as const,
  () => {
    loadRecentQueries()
    void loadHistory()
  },
  { immediate: true },
)

watch(searchQuery, (q) => {
  if (searchTimer) clearTimeout(searchTimer)
  if (!q.trim()) {
    searchResults.value = []
    return
  }
  searchTimer = setTimeout(() => {
    void runSearch()
  }, 300)
})

function syncDropdownPosition(): void {
  const input = searchRef.value?.inputRef
  if (!input) return

  const rect = input.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const width = Math.min(Math.max(rect.width, 280), vw - VIEWPORT_PADDING * 2)
  const left = Math.max(VIEWPORT_PADDING, Math.min(rect.left, vw - width - VIEWPORT_PADDING))
  const spaceBelow = vh - rect.bottom - VIEWPORT_PADDING
  const spaceAbove = rect.top - VIEWPORT_PADDING
  const openBelow = spaceBelow >= MIN_DROPDOWN_HEIGHT || spaceBelow >= spaceAbove

  dropdownOpensUp.value = !openBelow

  if (openBelow) {
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + DROPDOWN_GAP}px`,
      left: `${left}px`,
      width: `${width}px`,
      maxHeight: `${Math.min(DROPDOWN_MAX_HEIGHT, Math.max(spaceBelow - DROPDOWN_GAP, MIN_DROPDOWN_HEIGHT))}px`,
      zIndex: String(DROPDOWN_Z_INDEX),
    }
    return
  }

  dropdownStyle.value = {
    position: 'fixed',
    left: `${left}px`,
    width: `${width}px`,
    bottom: `${vh - rect.top + DROPDOWN_GAP}px`,
    maxHeight: `${Math.min(DROPDOWN_MAX_HEIGHT, Math.max(spaceAbove - DROPDOWN_GAP, MIN_DROPDOWN_HEIGHT))}px`,
    zIndex: String(DROPDOWN_Z_INDEX),
  }
}

function onPositionChange(): void {
  if (dropdownOpen.value) syncDropdownPosition()
}

function bindPositionListeners(): void {
  if (positionListenersBound) return
  positionListenersBound = true
  window.addEventListener('resize', onPositionChange)
  window.addEventListener('scroll', onPositionChange, true)
}

function unbindPositionListeners(): void {
  if (!positionListenersBound) return
  positionListenersBound = false
  window.removeEventListener('resize', onPositionChange)
  window.removeEventListener('scroll', onPositionChange, true)
}

function onFocus(): void {
  if (props.disabled) return
  dropdownOpen.value = true
  loadRecentQueries()
  void nextTick().then(syncDropdownPosition)
  bindPositionListeners()
}

function onBlur(): void {
  window.setTimeout(() => {
    dropdownOpen.value = false
    unbindPositionListeners()
  }, 150)
}

function onSelect(vehicle: DepartmentVehicle): void {
  const q = searchQuery.value.trim()
  if (q) persistRecentQuery(q)
  emit('select', vehicle)
  searchQuery.value = ''
  searchResults.value = []
  dropdownOpen.value = false
  void loadHistory()
}

watch(dropdownOpen, (open) => {
  if (open) {
    bindPositionListeners()
    void nextTick().then(syncDropdownPosition)
  } else {
    unbindPositionListeners()
  }
})

watch(
  [recentLoading, searchLoading, filteredSearchResults, recentQueries],
  () => {
    if (!dropdownOpen.value) return
    void nextTick().then(syncDropdownPosition)
  },
)

onUnmounted(() => {
  unbindPositionListeners()
  if (searchTimer) clearTimeout(searchTimer)
})
</script>

<template>
  <div class="activity-vehicle-assign-picker">
    <ESearchField
      ref="searchRef"
      v-model="searchQuery"
      :label="t('activities.vehicles.searchPlaceholder')"
      :disabled="disabled"
      :clear-aria-label="t('common.searchClear')"
      @focus="onFocus"
      @blur="onBlur"
    />

    <Teleport to="body">
      <div
        v-if="dropdownOpen"
        class="activity-vehicle-assign-picker__dropdown"
        :class="{ 'activity-vehicle-assign-picker__dropdown--up': dropdownOpensUp }"
        :style="dropdownStyle"
        role="listbox"
        :aria-label="t('activities.vehicles.searchTitle')"
      >
        <p v-if="searchLoading" class="activity-vehicle-assign-picker__hint text-muted">
          {{ t('common.loading') }}
        </p>

        <template v-else-if="!hasSearchQuery && showRecentQueries">
          <p class="activity-vehicle-assign-picker__section-label">
            {{ t('activities.vehicles.recentSearchesTitle') }}
          </p>
          <button
            v-for="query in recentQueries"
            :key="`query-${query}`"
            type="button"
            class="activity-vehicle-assign-picker__option activity-vehicle-assign-picker__option--query"
            role="option"
            @mousedown.prevent="applyRecentQuery(query)"
          >
            <span class="activity-vehicle-assign-picker__option-name">{{ query }}</span>
            <span class="activity-vehicle-assign-picker__option-meta text-muted">
              {{ t('activities.vehicles.recentSearchReuse') }}
            </span>
          </button>
        </template>

        <template v-if="showSearchSection">
          <p class="activity-vehicle-assign-picker__section-label">
            {{ t('activities.vehicles.searchResultsTitle') }}
          </p>
          <button
            v-for="vehicle in filteredSearchResults"
            :key="`search-${vehicle.id}`"
            type="button"
            class="activity-vehicle-assign-picker__option"
            role="option"
            @mousedown.prevent="onSelect(vehicle)"
          >
            <span class="activity-vehicle-assign-picker__option-name">{{ vehicleLabel(vehicle) }}</span>
            <span v-if="vehicleMeta(vehicle)" class="activity-vehicle-assign-picker__option-meta text-muted">
              {{ vehicleMeta(vehicle) }}
            </span>
          </button>
        </template>

        <p v-if="showDropdownEmpty" class="activity-vehicle-assign-picker__hint text-muted">
          {{
            hasSearchQuery
              ? t('activities.vehicles.searchEmpty')
              : t('activities.vehicles.recentSearchesEmpty')
          }}
        </p>
      </div>
    </Teleport>

    <div class="activity-vehicle-assign-picker__tiles" aria-live="polite">
      <div class="activity-vehicle-assign-picker__tiles-head">
        <h4 class="activity-vehicle-assign-picker__tiles-title">
          {{ t('activities.vehicles.historyTitle') }}
        </h4>
        <p class="activity-vehicle-assign-picker__tiles-hint text-muted">
          {{ t('activities.vehicles.historyTilesHint') }}
        </p>
      </div>

      <p v-if="recentLoading" class="activity-vehicle-assign-picker__hint text-muted">
        {{ t('common.loading') }}
      </p>

      <p v-else-if="tileVehicles.length === 0" class="activity-vehicle-assign-picker__hint text-muted">
        {{ t('activities.vehicles.historyEmpty') }}
      </p>

      <ul v-else class="activity-vehicle-assign-picker__tile-grid">
        <li v-for="vehicle in tileVehicles" :key="vehicle.id">
          <button
            type="button"
            class="activity-vehicle-assign-picker__tile"
            :disabled="disabled"
            @click="onSelect(vehicle)"
          >
            <span class="activity-vehicle-assign-picker__tile-name">{{ vehicleLabel(vehicle) }}</span>
            <span v-if="vehicleMeta(vehicle)" class="activity-vehicle-assign-picker__tile-meta text-muted">
              {{ vehicleMeta(vehicle) }}
            </span>
            <span class="activity-vehicle-assign-picker__tile-action">
              {{ t('activities.vehicles.reuseVehicle') }}
            </span>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.activity-vehicle-assign-picker {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.activity-vehicle-assign-picker__dropdown {
  overflow: auto;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
  padding: 6px;
}

.activity-vehicle-assign-picker__section-label {
  margin: 4px 8px 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}

.activity-vehicle-assign-picker__option {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  width: 100%;
  padding: 8px 10px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  text-align: left;
  cursor: pointer;
}

.activity-vehicle-assign-picker__option:hover {
  background: #f1f5f9;
}

.activity-vehicle-assign-picker__option-name {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.activity-vehicle-assign-picker__option-meta {
  font-size: 12px;
}

.activity-vehicle-assign-picker__hint {
  margin: 0;
  font-size: 13px;
}

.activity-vehicle-assign-picker__tiles-head {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-bottom: 10px;
}

.activity-vehicle-assign-picker__tiles-title {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}

.activity-vehicle-assign-picker__tiles-hint {
  margin: 0;
  font-size: 12px;
}

.activity-vehicle-assign-picker__tile-grid {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 10px;
}

.activity-vehicle-assign-picker__tile {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  width: 100%;
  min-height: 88px;
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fafc;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}

.activity-vehicle-assign-picker__tile:hover:not(:disabled) {
  border-color: #86efac;
  background: #f0fdf4;
  box-shadow: 0 4px 12px rgba(22, 163, 74, 0.12);
}

.activity-vehicle-assign-picker__tile:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.activity-vehicle-assign-picker__tile-name {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.3;
}

.activity-vehicle-assign-picker__tile-meta {
  font-size: 12px;
  line-height: 1.35;
}

.activity-vehicle-assign-picker__tile-action {
  margin-top: auto;
  padding-top: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #15803d;
}
</style>
