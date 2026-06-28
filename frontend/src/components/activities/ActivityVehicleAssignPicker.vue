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

const searchQuery = ref('')
const dropdownOpen = ref(false)
const recentLoading = ref(false)
const searchLoading = ref(false)
const recentVehicles = ref<DepartmentVehicle[]>([])
const searchResults = ref<DepartmentVehicle[]>([])
const searchRef = ref<InstanceType<typeof ESearchField> | null>(null)
const dropdownStyle = ref<Record<string, string>>({})
const dropdownOpensUp = ref(false)

const DROPDOWN_Z_INDEX = 2500
const DROPDOWN_MAX_HEIGHT = 280
const DROPDOWN_GAP = 4
const VIEWPORT_PADDING = 8
const MIN_DROPDOWN_HEIGHT = 80

let searchTimer: ReturnType<typeof setTimeout> | null = null
let positionListenersBound = false

const excludedSet = computed(() => new Set(props.excludedVehicleIds))

const filteredRecent = computed(() =>
  recentVehicles.value.filter((vehicle) => !excludedSet.value.has(vehicle.id)),
)

const filteredSearchResults = computed(() =>
  searchResults.value.filter((vehicle) => !excludedSet.value.has(vehicle.id)),
)

const hasSearchQuery = computed(() => searchQuery.value.trim().length > 0)

const showRecentSection = computed(
  () => !hasSearchQuery.value && (recentLoading.value || filteredRecent.value.length > 0),
)

const showSearchSection = computed(
  () => hasSearchQuery.value && (searchLoading.value || filteredSearchResults.value.length > 0),
)

const showEmpty = computed(
  () =>
    dropdownOpen.value &&
    !recentLoading.value &&
    !searchLoading.value &&
    ((!hasSearchQuery.value && filteredRecent.value.length === 0) ||
      (hasSearchQuery.value && filteredSearchResults.value.length === 0)),
)

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

async function loadRecent(): Promise<void> {
  recentLoading.value = true
  try {
    recentVehicles.value = await getRecentDepartmentVehicles(props.departmentId, {
      activityId: props.activityId,
      limit: 5,
    })
  } catch {
    recentVehicles.value = []
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
  } catch {
    searchResults.value = []
  } finally {
    searchLoading.value = false
  }
}

watch(
  () => [props.departmentId, props.activityId, props.reloadToken] as const,
  () => {
    void loadRecent()
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
  emit('select', vehicle)
  searchQuery.value = ''
  searchResults.value = []
  dropdownOpen.value = false
}

watch(dropdownOpen, (open) => {
  if (open) {
    bindPositionListeners()
    void nextTick().then(syncDropdownPosition)
  } else {
    unbindPositionListeners()
  }
})

watch([recentLoading, searchLoading, filteredRecent, filteredSearchResults], () => {
  if (!dropdownOpen.value) return
  void nextTick().then(syncDropdownPosition)
})

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
        <p v-if="recentLoading || searchLoading" class="activity-vehicle-assign-picker__hint text-muted">
          {{ t('common.loading') }}
        </p>

        <template v-else-if="showRecentSection">
          <p class="activity-vehicle-assign-picker__section-label">
            {{ t('activities.vehicles.recentTitle') }}
          </p>
          <button
            v-for="vehicle in filteredRecent"
            :key="`recent-${vehicle.id}`"
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

        <template v-if="showSearchSection">
          <p v-if="showRecentSection" class="activity-vehicle-assign-picker__divider" role="separator">
            {{ t('activities.vehicles.searchResultsTitle') }}
          </p>
          <p v-else class="activity-vehicle-assign-picker__section-label">
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

        <p v-if="showEmpty" class="activity-vehicle-assign-picker__hint text-muted">
          {{
            hasSearchQuery
              ? t('activities.vehicles.searchEmpty')
              : t('activities.vehicles.recentEmpty')
          }}
        </p>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.activity-vehicle-assign-picker {
  position: relative;
  min-width: 0;
}

.activity-vehicle-assign-picker__dropdown {
  overflow-y: auto;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
  padding: 6px 0;
}

.activity-vehicle-assign-picker__section-label {
  margin: 0;
  padding: 6px 14px 4px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}

.activity-vehicle-assign-picker__divider {
  margin: 6px 0 0;
  padding: 8px 14px 4px;
  border-top: 1px solid #e2e8f0;
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
  padding: 10px 14px;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
  font: inherit;
}

.activity-vehicle-assign-picker__option:hover,
.activity-vehicle-assign-picker__option:focus-visible {
  background: #f0fdf4;
  outline: none;
}

.activity-vehicle-assign-picker__option-name {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.activity-vehicle-assign-picker__option-meta {
  font-size: 12px;
  line-height: 1.35;
}

.activity-vehicle-assign-picker__hint {
  margin: 0;
  padding: 10px 14px;
  font-size: 13px;
}
</style>
