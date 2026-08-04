<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  arriveActivityTransportTour,
  arriveAllActivityTransportTours,
  createActivityTransportTour,
  deleteActivityTransportTour,
  directionForJourneyStep,
  formatTourDisplayLabel,
  getActivityTransportTours,
  transportTourUiModeForJourneyStep,
  updateActivityTransportTour,
  mapTourItemsForPatch,
  type ActivityTransportTour,
  type TransportTourDirection,
  type TransportTourStatus,
} from '@/api/activityTransportTours'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { packMaterialDisplayName } from '@/components/activities/packMaterialDisplay'
import { getActivityVehicles, type ActivityVehicleAssignment } from '@/api/activityVehicles'
import {
  materialJourneyAccordionLinesForContainerId,
  type MaterialJourneyAccordionLine,
} from '@/components/activities/materialJourneyAccordionLines'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import { isMaterialJourneyCrateKind } from '@/components/activities/materialJourneyTaskList'
import EButton from '@/components/form/base/EButton.vue'
import TransportTourWeightAutoSave from '@/components/activities/materialJourney/TransportTourWeightAutoSave.vue'
import { updateMaterial } from '@/api/materials'
import { normalizeMaterialMetricInput } from '@/utils/materialMetricUnits'
import { useToast } from '@/composables/useToast'
import {
  buildImplicitOutboundTripPendingLines,
  hasAnyOutboundTransport,
  IMPLICIT_SINGLE_TRIP_ID,
} from '@/utils/transportTourImplicitTrip'

const props = defineProps<{
  activityId: string
  departmentId: string
  journeyStep: string
  listEditable: boolean
  canManageMaterials?: boolean
  assignableTasks: MaterialJourneyTaskRow[]
  packItems?: ActivityPackItem[]
  packContainers?: ActivityPackContainer[]
  containerItemsByContainerId?: Record<string, ActivityPackContainerItem[]>
  transportTourSelectMode?: boolean
  selectedTourId?: string | null
  simpleTourMode?: boolean
  planTours?: ActivityTransportTour[] | null
  planVehicles?: ActivityVehicleAssignment[] | null
}>()

const emit = defineEmits<{
  pipelineChanged: []
  tourItemsChanged: []
  selectVehicle: [vehicleId: string]
  selectTour: [tourId: string]
  selectSimpleTour: []
}>()

const { t } = useI18n()
const toast = useToast()

const direction = computed((): TransportTourDirection | null =>
  directionForJourneyStep(props.journeyStep),
)

const uiMode = computed(() => transportTourUiModeForJourneyStep(props.journeyStep))

const isArrivalMode = computed(() => uiMode.value === 'arrival')
const isPlanMode = computed(() => uiMode.value === 'plan')
const isReturnStep = computed(() => props.journeyStep === 'return')
const weightReviewMode = computed(
  () => isReturnStep.value && Boolean(props.canManageMaterials),
)

const loading = ref(false)
const tours = ref<ActivityTransportTour[]>([])
const activityVehicles = ref<ActivityVehicleAssignment[]>([])
const expandedTourId = ref<string | null>(null)
const createOpen = ref(false)
const createVehicleId = ref('')
const createBusy = ref(false)
const savingTourId = ref<string | null>(null)
const arriveBusyTourId = ref<string | null>(null)
const arriveAllBusy = ref(false)
const weightDraftByItemId = ref<Record<string, string>>({})
const expandedLoadedCrateIds = ref<Set<string>>(new Set())
const savingMaterialWeightId = ref<string | null>(null)

const assignableRows = computed(() =>
  props.assignableTasks
    .filter((row) => row.isOpen && (isMaterialJourneyCrateKind(row.kind) || row.kind === 'loose'))
    .map((row) => ({
      key: row.id,
      label: row.title,
      kind: row.kind,
      containerId: row.container?.id ?? null,
      packItemId: row.packItem?.id ?? null,
    })),
)

const displayTours = computed(() =>
  props.planTours != null && isPlanMode.value ? props.planTours : tours.value,
)

const displayVehicles = computed(() =>
  props.planVehicles != null && isPlanMode.value ? props.planVehicles : activityVehicles.value,
)

function isVehicleSelected(vehicleId: string): boolean {
  if (!props.selectedTourId) return false
  const tour = displayTours.value.find((entry) => entry.id === props.selectedTourId)
  return tour?.vehicle_id === vehicleId
}

function isTourSelected(tour: ActivityTransportTour): boolean {
  return props.selectedTourId === tour.id
}

function onSelectTour(tour: ActivityTransportTour): void {
  if (!props.transportTourSelectMode) return
  emit('selectTour', tour.id)
}

function assignedCountForVehicle(vehicleId: string): number {
  const tour = displayTours.value.find(
    (entry) => entry.vehicle_id === vehicleId && entry.status === 'planned',
  )
  return tour?.items.length ?? 0
}

function vehicleChipLabel(assignment: ActivityVehicleAssignment): string {
  const tour = displayTours.value.find(
    (entry) => entry.vehicle_id === assignment.vehicle_id && entry.status === 'planned',
  )
  if (tour) return formatTourDisplayLabel(tour)
  return assignment.vehicle.name
}

const allToursArrived = computed(
  () => tours.value.length > 0 && tours.value.every((tour) => tour.status === 'arrived'),
)

const hasPendingTours = computed(
  () => tours.value.some((tour) => tour.status !== 'arrived'),
)

const implicitTripPendingLines = computed(() =>
  buildImplicitOutboundTripPendingLines(
    props.packItems ?? [],
    props.packContainers ?? [],
  ),
)

const showImplicitSingleTrip = computed(
  () =>
    isArrivalMode.value &&
    direction.value === 'outbound' &&
    !loading.value &&
    tours.value.length === 0 &&
    hasAnyOutboundTransport(props.packItems ?? []),
)

const implicitTripPending = computed(() => implicitTripPendingLines.value.length > 0)

/** Am Anlass: Sektion nur solange Touren/Pipeline noch nicht angekommen. */
const hasOpenArrivalWork = computed(() => {
  if (implicitTripPending.value) return true
  return tours.value.some((tour) => tour.status !== 'arrived')
})

const showSection = computed(() => {
  if (weightReviewMode.value) {
    return loading.value || weightReviewRows.value.length > 0
  }
  if (!direction.value) return false
  if (!isArrivalMode.value) return true
  if (loading.value) return true
  return hasOpenArrivalWork.value
})

type WeightReviewRow = TourLoadedRow & {
  tourId: string
  tourLabel: string
}

const weightReviewRows = computed((): WeightReviewRow[] => {
  if (!weightReviewMode.value) return []
  const byKey = new Map<string, WeightReviewRow>()
  const orderedTours = [...tours.value].sort((a, b) => {
    if (a.direction === b.direction) return 0
    return a.direction === 'outbound' ? -1 : 1
  })
  for (const tour of orderedTours) {
    for (const loaded of loadedRowsForTour(tour)) {
      if (!loaded.materialItemId || loaded.materialWeightKnown) continue
      const kg = loaded.measuredWeightKg
      if (kg == null || kg <= 0) continue
      const key = weightReviewKey(loaded)
      if (byKey.has(key)) continue
      byKey.set(key, {
        ...loaded,
        tourId: tour.id,
        tourLabel: tour.direction === 'outbound'
          ? t('activities.materialJourney.transportTours.weightFromOutbound')
          : formatTourDisplayLabel(tour),
      })
    }
  }
  return [...byKey.values()]
})

async function loadWeightReviewTours(): Promise<void> {
  loading.value = true
  try {
    const [outbound, inbound] = await Promise.all([
      getActivityTransportTours(props.activityId, 'outbound'),
      getActivityTransportTours(props.activityId, 'inbound'),
    ])
    tours.value = [...outbound, ...inbound]
    syncWeightDraftsFromTours(tours.value)
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    loading.value = false
  }
}

async function loadAll(): Promise<void> {
  if (weightReviewMode.value) {
    await loadWeightReviewTours()
    return
  }
  if (!direction.value) return
  loading.value = true
  try {
    const [tourList, vehicleAssignments] = await Promise.all([
      getActivityTransportTours(props.activityId, direction.value),
      isPlanMode.value
        ? getActivityVehicles(props.activityId)
        : Promise.resolve([] as ActivityVehicleAssignment[]),
    ])
    tours.value = tourList
    activityVehicles.value = vehicleAssignments
    syncWeightDraftsFromTours(tourList)
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.activityId, props.journeyStep] as const,
  () => {
    void loadAll()
  },
  { immediate: true },
)

watch(
  () => props.planTours,
  (tourList) => {
    if (tourList) syncWeightDraftsFromTours(tourList)
  },
  { deep: true },
)

watch(showImplicitSingleTrip, (show) => {
  if (show && implicitTripPending.value && expandedTourId.value === null) {
    expandedTourId.value = IMPLICIT_SINGLE_TRIP_ID
  }
}, { immediate: true })

function fitLabel(fit: string): string {
  if (fit === 'ok') return t('activities.materialJourney.transportTours.fitOk')
  if (fit === 'heavy') return t('activities.materialJourney.transportTours.fitHeavy')
  return t('activities.materialJourney.transportTours.fitUnknown')
}

function fitClass(fit: string): string {
  if (fit === 'ok') return 'material-journey-transport-tours__fit--ok'
  if (fit === 'heavy') return 'material-journey-transport-tours__fit--heavy'
  return 'material-journey-transport-tours__fit--unknown'
}

function statusLabel(status: TransportTourStatus): string {
  return t(`activities.materialJourney.transportTours.status.${status}`)
}

function statusClass(status: TransportTourStatus): string {
  return `material-journey-transport-tours__status--${status}`
}

type TourLoadedRow = {
  id: string
  label: string
  kind: 'crate' | 'loose'
  containerId: string | null
  packItemId: string | null
  quantity: number
  measuredWeightKg: number | null
  measuredWeightInherited: boolean
  materialWeightKnown: boolean
  materialItemId: string | null
}

function weightReviewKey(row: TourLoadedRow): string {
  if (row.materialItemId) return `mi:${row.materialItemId}`
  if (row.containerId) return `pc:${row.containerId}`
  if (row.packItemId) return `pi:${row.packItemId}`
  return row.id
}

function labelForTourItem(item: ActivityTransportTour['items'][number]): string {
  const row = assignableRows.value.find(
    (r) =>
      (item.pack_container_id && r.containerId === item.pack_container_id) ||
      (item.pack_item_id && r.packItemId === item.pack_item_id),
  )
  if (row) return row.label
  if (item.pack_container_id) {
    const container = props.packContainers?.find((c) => c.id === item.pack_container_id)
    if (container) return container.label
  }
  if (item.pack_item_id) {
    const packItem = props.packItems?.find((pi) => pi.id === item.pack_item_id)
    if (packItem) return packMaterialDisplayName(packItem)
  }
  return '—'
}

function loadedRowsForTour(tour: ActivityTransportTour): TourLoadedRow[] {
  return tour.items.map((item) => {
    const containerId = item.pack_container_id ?? null
    const packItemId = item.pack_item_id ?? null
    const assignable = assignableRows.value.find(
      (r) =>
        (containerId && r.containerId === containerId) ||
        (packItemId && r.packItemId === packItemId),
    )
    return {
      id: item.id,
      label: labelForTourItem(item),
      kind:
        assignable &&
        (assignable.kind === 'crate' || assignable.kind === 'virtual_crate')
          ? 'crate'
          : 'loose',
      containerId,
      packItemId,
      quantity: Math.max(1, item.quantity ?? 1),
      measuredWeightKg: item.measured_weight_kg,
      measuredWeightInherited: item.measured_weight_inherited,
      materialWeightKnown: item.material_weight_known,
      materialItemId: item.material_item_id,
    }
  })
}

function parseWeightDraftValue(raw: string): number | null {
  const normalized = raw.trim().replace(',', '.')
  if (normalized === '') return null
  const n = Number(normalized)
  return Number.isFinite(n) && n > 0 ? n : null
}

function loadedCrateAccordionLines(loaded: TourLoadedRow): MaterialJourneyAccordionLine[] {
  if (loaded.kind !== 'crate' || !loaded.containerId) return []
  return materialJourneyAccordionLinesForContainerId(
    loaded.containerId,
    props.containerItemsByContainerId ?? {},
  )
}

function toggleLoadedCrateExpanded(loadedId: string): void {
  const next = new Set(expandedLoadedCrateIds.value)
  if (next.has(loadedId)) next.delete(loadedId)
  else next.add(loadedId)
  expandedLoadedCrateIds.value = next
}

function isLoadedCrateExpanded(loadedId: string): boolean {
  return expandedLoadedCrateIds.value.has(loadedId)
}

function showWeightInput(row: TourLoadedRow): boolean {
  if (row.measuredWeightInherited) return false
  if (row.measuredWeightKg != null && row.measuredWeightKg > 0) return false
  return !row.materialWeightKnown
}

function showInheritedWeight(row: TourLoadedRow): boolean {
  return row.measuredWeightInherited && row.measuredWeightKg != null && row.measuredWeightKg > 0
}

function weightDraftFor(row: TourLoadedRow): string {
  if (row.id in weightDraftByItemId.value) {
    return weightDraftByItemId.value[row.id] ?? ''
  }
  return row.measuredWeightKg != null ? String(row.measuredWeightKg) : ''
}

function onWeightDraft(itemId: string, value: string): void {
  weightDraftByItemId.value = { ...weightDraftByItemId.value, [itemId]: value }
}

function syncWeightDraftsFromTours(tourList: ActivityTransportTour[]): void {
  const next = { ...weightDraftByItemId.value }
  for (const tour of tourList) {
    for (const item of tour.items) {
      if (!(item.id in next) && item.measured_weight_kg != null) {
        next[item.id] = String(item.measured_weight_kg)
      }
    }
  }
  weightDraftByItemId.value = next
}

function applyTourUpdate(updated: ActivityTransportTour): void {
  tours.value = tours.value.map((t) => (t.id === updated.id ? updated : t))
  emit('tourItemsChanged')
}

function assignedItemLabels(tour: ActivityTransportTour): string[] {
  return loadedRowsForTour(tour).map((row) => row.label)
}

async function removeLoadedItem(tour: ActivityTransportTour, loadedId: string): Promise<void> {
  if (!props.listEditable || isArrivalMode.value) return
  savingTourId.value = tour.id
  try {
    const items = mapTourItemsForPatch(tour.items.filter((item) => item.id !== loadedId))
    const updated = await updateActivityTransportTour(props.activityId, tour.id, { items })
    applyTourUpdate(updated)
    const { [loadedId]: _removed, ...rest } = weightDraftByItemId.value
    weightDraftByItemId.value = rest
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    savingTourId.value = null
  }
}

async function saveWeightToMaterial(row: TourLoadedRow): Promise<void> {
  if (!row.materialItemId || !weightReviewMode.value) return
  const kg = parseWeightDraftValue(weightDraftFor(row)) ?? row.measuredWeightKg
  if (kg == null || kg <= 0) return

  savingMaterialWeightId.value = row.id
  try {
    await updateMaterial(row.materialItemId, {
      weight: normalizeMaterialMetricInput(String(kg), 'kg'),
    })
    toast.success(t('activities.materialJourney.transportTours.weightSavedToMaterial'))
    await loadWeightReviewTours()
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    savingMaterialWeightId.value = null
  }
}

async function onCreateTour(): Promise<void> {
  if (!direction.value || !createVehicleId.value) return
  createBusy.value = true
  try {
    const created = await createActivityTransportTour(props.activityId, {
      vehicle_id: createVehicleId.value,
      direction: direction.value,
    })
    tours.value = [...tours.value, created]
    expandedTourId.value = created.id
    createOpen.value = false
    createVehicleId.value = ''
    emit('tourItemsChanged')
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    createBusy.value = false
  }
}

async function onDeleteTour(tour: ActivityTransportTour): Promise<void> {
  if (!props.listEditable || isArrivalMode.value) return
  try {
    await deleteActivityTransportTour(props.activityId, tour.id)
    tours.value = tours.value.filter((t) => t.id !== tour.id)
    if (expandedTourId.value === tour.id) expandedTourId.value = null
    emit('tourItemsChanged')
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  }
}

async function onDepartTour(tour: ActivityTransportTour): Promise<void> {
  if (!props.listEditable || tour.status !== 'planned') return
  savingTourId.value = tour.id
  try {
    const updated = await updateActivityTransportTour(props.activityId, tour.id, {
      status: 'in_transit',
    })
    tours.value = tours.value.map((t) => (t.id === updated.id ? updated : t))
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    savingTourId.value = null
  }
}

async function onArriveTour(tour: ActivityTransportTour): Promise<void> {
  if (!props.listEditable || tour.status === 'arrived') return
  arriveBusyTourId.value = tour.id
  try {
    const updated = await arriveActivityTransportTour(props.activityId, tour.id)
    tours.value = tours.value.map((t) => (t.id === updated.id ? updated : t))
    emit('pipelineChanged')
    toast.success(t('activities.materialJourney.transportTours.arrivedToast'))
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    arriveBusyTourId.value = null
  }
}

async function onArriveAll(): Promise<void> {
  if (!props.listEditable || !direction.value) return
  arriveAllBusy.value = true
  arriveBusyTourId.value = showImplicitSingleTrip.value ? IMPLICIT_SINGLE_TRIP_ID : arriveBusyTourId.value
  try {
    const result = await arriveAllActivityTransportTours(props.activityId, direction.value)
    await loadAll()
    emit('pipelineChanged')
    toast.success(
      t('activities.materialJourney.transportTours.arriveAllToast', {
        units: result.applied_units,
      }),
    )
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    arriveAllBusy.value = false
    if (arriveBusyTourId.value === IMPLICIT_SINGLE_TRIP_ID) {
      arriveBusyTourId.value = null
    }
  }
}

function toggleExpanded(tourId: string): void {
  expandedTourId.value = expandedTourId.value === tourId ? null : tourId
}

defineExpose({ loadAll })
</script>

<template>
  <section
    v-if="weightReviewMode && showSection"
    class="material-journey-transport-tours material-journey-transport-tours--weight-review section-card"
  >
    <h2 class="material-journey-transport-tours__title">
      {{ t('activities.materialJourney.transportTours.weightReviewTitle') }}
    </h2>
    <p class="text-muted material-journey-transport-tours__hint">
      {{ t('activities.materialJourney.transportTours.weightReviewHint') }}
    </p>
    <p v-if="loading" class="text-muted">{{ t('common.loading') }}</p>
    <ul
      v-else-if="weightReviewRows.length > 0"
      class="material-journey-transport-tours__review-list"
    >
      <li
        v-for="row in weightReviewRows"
        :key="row.id"
        class="material-journey-transport-tours__review-row"
      >
        <span class="material-journey-transport-tours__review-label">{{ row.label }}</span>
        <span class="text-muted material-journey-transport-tours__review-tour">{{ row.tourLabel }}</span>
        <span class="material-journey-transport-tours__review-weight">
          {{ row.measuredWeightKg }} kg
        </span>
        <label
          v-if="row.measuredWeightInherited"
          class="material-journey-transport-tours__weight-field material-journey-transport-tours__weight-field--readonly"
        >
          <span class="text-muted material-journey-transport-tours__inherited-note">
            {{ t('activities.materialJourney.transportTours.inheritedWeightNote') }}
          </span>
        </label>
        <label
          v-else
          class="material-journey-transport-tours__weight-field"
        >
          <input
            type="text"
            inputmode="decimal"
            class="material-journey-transport-tours__weight-input"
            :value="weightDraftFor(row)"
            :disabled="savingMaterialWeightId === row.id"
            @input="onWeightDraft(row.id, ($event.target as HTMLInputElement).value)"
          />
          <span class="material-journey-transport-tours__weight-unit">kg</span>
        </label>
        <EButton
          variant="primary"
          size="small"
          :loading="savingMaterialWeightId === row.id"
          :disabled="savingMaterialWeightId !== null && savingMaterialWeightId !== row.id"
          @click="saveWeightToMaterial(row)"
        >
          {{ t('activities.materialJourney.transportTours.saveWeightToMaterial') }}
        </EButton>
      </li>
    </ul>
  </section>

  <section v-else-if="showSection" class="material-journey-transport-tours section-card">
    <div class="material-journey-transport-tours__header">
      <div>
        <h2 class="material-journey-transport-tours__title">
          {{
            isArrivalMode
              ? t('activities.materialJourney.transportTours.titleArrival')
              : t('activities.materialJourney.transportTours.title')
          }}
        </h2>
        <p class="text-muted material-journey-transport-tours__hint">
          {{
            isArrivalMode
              ? t('activities.materialJourney.transportTours.hintArrival')
              : t('activities.materialJourney.transportTours.hint')
          }}
        </p>
      </div>
      <EButton
        v-if="listEditable && isPlanMode"
        variant="secondary"
        size="small"
        @click="createOpen = true"
      >
        {{ t('activities.materialJourney.transportTours.addTour') }}
      </EButton>
    </div>

    <div
      v-if="isPlanMode && transportTourSelectMode && displayVehicles.length > 0"
      class="material-journey-transport-targets"
      role="listbox"
      :aria-label="t('activities.materialJourney.transportTours.targetPickerAria')"
    >
      <button
        v-for="assignment in displayVehicles"
        :key="assignment.id"
        type="button"
        class="material-journey-transport-target"
        :class="{ 'material-journey-transport-target--active': isVehicleSelected(assignment.vehicle_id) }"
        role="option"
        :aria-selected="isVehicleSelected(assignment.vehicle_id)"
        @click="emit('selectVehicle', assignment.vehicle_id)"
      >
        <v-icon icon="mdi-truck-outline" size="20" aria-hidden="true" />
        <span class="material-journey-transport-target__name">{{ vehicleChipLabel(assignment) }}</span>
        <span v-if="assignment.vehicle.plate" class="material-journey-transport-target__plate text-muted">
          {{ assignment.vehicle.plate }}
        </span>
        <span
          v-if="assignment.vehicle.max_payload_kg != null"
          class="material-journey-transport-target__payload text-muted"
        >
          {{ t('activities.vehicles.payloadKg', { kg: assignment.vehicle.max_payload_kg }) }}
        </span>
        <span
          v-if="assignedCountForVehicle(assignment.vehicle_id) > 0"
          class="material-journey-transport-target__count"
        >
          {{ assignedCountForVehicle(assignment.vehicle_id) }}
        </span>
      </button>
      <button
        type="button"
        class="material-journey-transport-target material-journey-transport-target--simple"
        :class="{ 'material-journey-transport-target--active': simpleTourMode }"
        role="option"
        :aria-selected="Boolean(simpleTourMode)"
        @click="emit('selectSimpleTour')"
      >
        <v-icon icon="mdi-truck-fast-outline" size="20" aria-hidden="true" />
        <span>{{ t('activities.materialJourney.transportTours.simpleTour') }}</span>
      </button>
    </div>

    <p
      v-if="isPlanMode && transportTourSelectMode && simpleTourMode"
      class="material-journey-transport-target-hint text-muted"
    >
      {{ t('activities.materialJourney.transportTours.simpleTourActive') }}
    </p>

    <div
      v-if="listEditable && isPlanMode && direction === 'inbound'"
      class="material-journey-transport-tours__bulk"
    >
      <EButton
        variant="primary"
        size="small"
        :loading="arriveAllBusy"
        :disabled="arriveAllBusy || (tours.length > 0 && allToursArrived)"
        @click="onArriveAll"
      >
        {{ t('activities.materialJourney.transportTours.arriveAllInbound') }}
      </EButton>
    </div>

    <div
      v-else-if="listEditable && isArrivalMode && tours.length > 0"
      class="material-journey-transport-tours__bulk"
    >
      <EButton
        variant="primary"
        size="small"
        :loading="arriveAllBusy"
        :disabled="arriveAllBusy"
        @click="onArriveAll"
      >
        {{ t('activities.materialJourney.transportTours.arriveAllOutbound') }}
      </EButton>
    </div>

    <p v-if="loading" class="text-muted">{{ t('common.loading') }}</p>

    <div
      v-else-if="showImplicitSingleTrip"
      class="material-journey-transport-tours__list"
    >
      <article class="material-journey-transport-tours__card">
        <button
          type="button"
          class="material-journey-transport-tours__card-head"
          @click="toggleExpanded(IMPLICIT_SINGLE_TRIP_ID)"
        >
          <span class="material-journey-transport-tours__card-chevron" aria-hidden="true">
            {{ expandedTourId === IMPLICIT_SINGLE_TRIP_ID ? '▾' : '▸' }}
          </span>
          <span class="material-journey-transport-tours__card-label">
            {{ t('activities.materialJourney.transportTours.implicitTrip.label') }}
          </span>
          <span class="text-muted">
            {{ t('activities.materialJourney.transportTours.implicitTrip.subtitle') }}
          </span>
          <span
            class="material-journey-transport-tours__status"
            :class="implicitTripPending ? statusClass('in_transit') : statusClass('arrived')"
          >
            {{
              implicitTripPending
                ? statusLabel('in_transit')
                : statusLabel('arrived')
            }}
          </span>
        </button>

        <div
          v-show="expandedTourId === IMPLICIT_SINGLE_TRIP_ID"
          class="material-journey-transport-tours__card-body"
        >
          <ul
            v-if="implicitTripPendingLines.length > 0"
            class="material-journey-transport-tours__assign-list"
          >
            <li v-for="line in implicitTripPendingLines" :key="line.id">
              {{ line.label }}
              <span v-if="line.kind === 'crate'" class="material-journey-transport-tours__kind">
                {{ t('activities.materialJourney.badge.crate') }}
              </span>
            </li>
          </ul>
          <p v-else class="text-muted">
            {{ t('activities.materialJourney.transportTours.implicitTrip.allArrived') }}
          </p>
          <div
            v-if="listEditable && implicitTripPending"
            class="material-journey-transport-tours__actions"
          >
            <EButton
              variant="primary"
              size="small"
              :loading="arriveAllBusy || arriveBusyTourId === IMPLICIT_SINGLE_TRIP_ID"
              @click="onArriveAll"
            >
              {{ t('activities.materialJourney.transportTours.markArrived') }}
            </EButton>
          </div>
        </div>
      </article>
    </div>

    <p v-else-if="isArrivalMode && tours.length === 0" class="text-muted">
      {{ t('activities.materialJourney.transportTours.implicitTrip.noTransportYet') }}
    </p>
    <p v-else-if="tours.length === 0 && !(isPlanMode && transportTourSelectMode && displayVehicles.length > 0)" class="text-muted">
      {{ t('activities.materialJourney.transportTours.empty') }}
    </p>

    <div v-else-if="displayTours.length > 0" class="material-journey-transport-tours__list">
      <article
        v-for="tour in displayTours"
        :key="tour.id"
        class="material-journey-transport-tours__card"
        :class="{ 'material-journey-transport-tours__card--selected': isTourSelected(tour) }"
      >
        <div class="material-journey-transport-tours__card-head">
          <button
            type="button"
            class="material-journey-transport-tours__card-chevron-btn"
            :aria-expanded="expandedTourId === tour.id"
            :aria-label="t('activities.materialJourney.transportTours.toggleTourAria', { label: formatTourDisplayLabel(tour) })"
            @click="toggleExpanded(tour.id)"
          >
            <span class="material-journey-transport-tours__card-chevron" aria-hidden="true">
              {{ expandedTourId === tour.id ? '▾' : '▸' }}
            </span>
          </button>
          <button
            type="button"
            class="material-journey-transport-tours__card-head-select"
            :class="{
              'material-journey-transport-tours__card-head-select--active': isTourSelected(tour),
              'material-journey-transport-tours__card-head-select--selectable': transportTourSelectMode,
            }"
            @click="onSelectTour(tour)"
          >
            <span class="material-journey-transport-tours__card-label">{{ formatTourDisplayLabel(tour) }}</span>
            <span
              class="material-journey-transport-tours__status"
              :class="statusClass(tour.status)"
            >
              {{ statusLabel(tour.status) }}
            </span>
            <span
              v-if="isPlanMode && tour.items.length > 0"
              class="material-journey-transport-tours__loaded-count text-muted"
            >
              {{ t('activities.materialJourney.transportTours.loadedCount', { count: tour.items.length }) }}
            </span>
            <span
              v-if="isPlanMode"
              class="material-journey-transport-tours__fit"
              :class="fitClass(tour.load_summary.fit)"
            >
              {{ fitLabel(tour.load_summary.fit) }}
            </span>
            <span
              v-if="isPlanMode && tour.load_summary.max_payload_kg && tour.load_summary.known_weight_kg > 0"
              class="material-journey-transport-tours__weight"
              :class="{ 'material-journey-transport-tours__weight--heavy': tour.load_summary.fit === 'heavy' }"
            >
              <span class="material-journey-transport-tours__weight-material">
                {{ t('activities.materialJourney.transportTours.weightMaterialPart', {
                  weight: tour.load_summary.known_weight_kg,
                }) }}
              </span>
              <span class="material-journey-transport-tours__weight-sep">/</span>
              <span class="material-journey-transport-tours__weight-payload">
                {{ t('activities.materialJourney.transportTours.weightPayloadPart', {
                  max: tour.load_summary.max_payload_kg,
                }) }}
              </span>
            </span>
            <span
              v-if="isPlanMode && tour.load_summary.unknown_weight_count > 0"
              class="material-journey-transport-tours__weight-unknown text-muted"
            >
              {{ t('activities.materialJourney.transportTours.unknownWeightNotice', {
                count: tour.load_summary.unknown_weight_count,
              }) }}
            </span>
          </button>
          <EButton
            v-if="listEditable && isPlanMode && tour.status === 'planned'"
            variant="primary"
            size="small"
            class="material-journey-transport-tours__depart"
            :loading="savingTourId === tour.id"
            @click="onDepartTour(tour)"
          >
            {{ t('activities.materialJourney.transportTours.markDeparted') }}
          </EButton>
        </div>

        <div v-show="expandedTourId === tour.id" class="material-journey-transport-tours__card-body">
          <template v-if="isArrivalMode">
            <ul v-if="assignedItemLabels(tour).length > 0" class="material-journey-transport-tours__assign-list">
              <li v-for="(label, idx) in assignedItemLabels(tour)" :key="idx">
                {{ label }}
              </li>
            </ul>
            <p v-else class="text-muted">
              {{ t('activities.materialJourney.transportTours.noAssigned') }}
            </p>
            <div v-if="listEditable && tour.status !== 'arrived'" class="material-journey-transport-tours__actions">
              <EButton
                variant="primary"
                size="small"
                :loading="arriveBusyTourId === tour.id"
                :disabled="arriveBusyTourId !== null && arriveBusyTourId !== tour.id"
                @click="onArriveTour(tour)"
              >
                {{ t('activities.materialJourney.transportTours.markArrived') }}
              </EButton>
            </div>
          </template>

          <template v-else>
            <h3 class="material-journey-transport-tours__subsection-title">
              {{ t('activities.materialJourney.transportTours.loadedTitle') }}
            </h3>
            <ul
              v-if="loadedRowsForTour(tour).length > 0"
              class="material-journey-transport-tours__loaded-list"
            >
              <li
                v-for="loaded in loadedRowsForTour(tour)"
                :key="loaded.id"
                class="material-journey-transport-tours__loaded-row"
                :class="{ 'material-journey-transport-tours__loaded-row--crate': loaded.kind === 'crate' }"
              >
                <div class="material-journey-transport-tours__loaded-main">
                  <span class="material-journey-transport-tours__loaded-label">
                    {{ loaded.label }}
                    <span v-if="loaded.quantity > 1" class="text-muted">
                      × {{ loaded.quantity }}
                    </span>
                  </span>
                  <span v-if="loaded.kind === 'crate'" class="material-journey-transport-tours__kind">
                    {{ t('activities.materialJourney.badge.crate') }}
                  </span>
                  <span
                    v-if="showInheritedWeight(loaded)"
                    class="material-journey-transport-tours__inherited-weight text-muted"
                  >
                    {{ t('activities.materialJourney.transportTours.inheritedWeightHint', {
                      weight: loaded.measuredWeightKg,
                    }) }}
                  </span>
                  <TransportTourWeightAutoSave
                    v-if="listEditable && showWeightInput(loaded)"
                    :activity-id="activityId"
                    :tour="tour"
                    :item-id="loaded.id"
                    :baseline-kg="loaded.measuredWeightKg"
                    :disabled="savingTourId === tour.id"
                    @saved="applyTourUpdate"
                  />
                  <EButton
                    v-if="listEditable"
                    variant="secondary"
                    size="small"
                    class="material-journey-transport-tours__remove"
                    :disabled="savingTourId === tour.id"
                    @click="removeLoadedItem(tour, loaded.id)"
                  >
                    {{ t('activities.materialJourney.transportTours.removeFromTour') }}
                  </EButton>
                </div>
                <div
                  v-if="loaded.kind === 'crate'"
                  class="material-journey-transport-tours__loaded-crate"
                >
                  <button
                    type="button"
                    class="material-journey-transport-tours__loaded-crate-toggle"
                    :aria-expanded="isLoadedCrateExpanded(loaded.id)"
                    @click="toggleLoadedCrateExpanded(loaded.id)"
                  >
                    <span
                      class="material-journey-transport-tours__loaded-crate-chevron"
                      :class="{ 'material-journey-transport-tours__loaded-crate-chevron--open': isLoadedCrateExpanded(loaded.id) }"
                      aria-hidden="true"
                    >▸</span>
                    <span>{{ t('activities.materialJourney.row.accordionToggle') }}</span>
                    <span
                      v-if="loadedCrateAccordionLines(loaded).length > 0"
                      class="text-muted"
                    >
                      {{ t('activities.materialJourney.row.accordionCount', {
                        count: loadedCrateAccordionLines(loaded).length,
                      }) }}
                    </span>
                  </button>
                  <ul
                    v-show="isLoadedCrateExpanded(loaded.id)"
                    class="material-journey-transport-tours__loaded-crate-list"
                  >
                    <li
                      v-for="line in loadedCrateAccordionLines(loaded)"
                      :key="line.id"
                      class="material-journey-transport-tours__loaded-crate-line"
                    >
                      <span>{{ line.name }}</span>
                      <span class="text-muted">× {{ line.quantity }}</span>
                    </li>
                    <li
                      v-if="loadedCrateAccordionLines(loaded).length === 0"
                      class="material-journey-transport-tours__loaded-crate-empty text-muted"
                    >
                      {{ t('activities.materialJourney.row.accordionEmptyPackCrate') }}
                    </li>
                  </ul>
                </div>
              </li>
            </ul>
            <p v-else class="text-muted material-journey-transport-tours__no-loaded">
              {{ t('activities.materialJourney.transportTours.noAssigned') }}
            </p>

            <p
              v-if="isPlanMode && loadedRowsForTour(tour).length > 0"
              class="text-muted material-journey-transport-tours__weight-estimate"
            >
              {{ t('activities.materialJourney.transportTours.weightEstimateHint') }}
            </p>

            <div v-if="listEditable" class="material-journey-transport-tours__actions">
              <EButton
                v-if="tour.status !== 'arrived' && direction === 'inbound'"
                variant="primary"
                size="small"
                :loading="arriveBusyTourId === tour.id"
                @click="onArriveTour(tour)"
              >
                {{ t('activities.materialJourney.transportTours.markArrived') }}
              </EButton>
              <EButton
                variant="secondary"
                size="small"
                class="material-journey-transport-tours__delete"
                @click="onDeleteTour(tour)"
              >
                {{ t('activities.materialJourney.transportTours.deleteTour') }}
              </EButton>
            </div>
          </template>
        </div>
      </article>
    </div>

    <p
      v-if="isArrivalMode && hasPendingTours && listEditable"
      class="text-muted material-journey-transport-tours__pending-hint"
    >
      {{ t('activities.materialJourney.transportTours.pendingArrivalHint') }}
    </p>

    <v-dialog v-model="createOpen" max-width="480">
      <div class="material-journey-transport-tours__dialog section-card">
        <h3 class="material-journey-transport-tours__dialog-title">
          {{ t('activities.materialJourney.transportTours.createTitle') }}
        </h3>
        <label class="material-journey-transport-tours__field">
          <span>{{ t('activities.materialJourney.transportTours.vehicleLabel') }}</span>
          <select v-model="createVehicleId" class="material-journey-transport-tours__select">
            <option value="">{{ t('activities.materialJourney.transportTours.chooseVehicle') }}</option>
            <option v-for="av in activityVehicles" :key="av.id" :value="av.vehicle_id">
              {{ av.vehicle.name }}{{ av.vehicle.plate ? ` (${av.vehicle.plate})` : '' }}
            </option>
          </select>
        </label>
        <p v-if="activityVehicles.length === 0" class="text-muted material-journey-transport-tours__no-vehicles">
          {{ t('activities.materialJourney.transportTours.noActivityVehicles') }}
        </p>
        <div class="material-journey-transport-tours__dialog-actions">
          <EButton variant="secondary" @click="createOpen = false">{{ t('common.cancel') }}</EButton>
          <EButton
            variant="primary"
            :disabled="!createVehicleId || createBusy"
            :loading="createBusy"
            @click="onCreateTour"
          >
            {{ t('activities.materialJourney.transportTours.createConfirm') }}
          </EButton>
        </div>
      </div>
    </v-dialog>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-journey-transport-tours--weight-review {
  margin-bottom: 12px;
}

.material-journey-transport-tours__review-list {
  margin: 12px 0 0;
  padding: 0;
  list-style: none;
}

.material-journey-transport-tours__review-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 8px 0;
  border-bottom: 1px solid #f1f5f9;
}

.material-journey-transport-tours__review-row:last-child {
  border-bottom: 0;
}

.material-journey-transport-tours__review-label {
  flex: 1 1 160px;
  font-weight: 500;
}

.material-journey-transport-tours__review-tour {
  font-size: 12px;
}

.material-journey-transport-tours {
  margin-bottom: 12px;
}

.material-journey-transport-tours__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.material-journey-transport-tours__title {
  margin: 0 0 4px;
  font-size: 1rem;
}

.material-journey-transport-tours__hint {
  margin: 0;
  font-size: 13px;
}

.material-journey-transport-tours__bulk {
  margin-bottom: 12px;
}

.material-journey-transport-tours__pending-hint {
  margin: 8px 0 0;
  font-size: 13px;
}

.material-journey-transport-tours__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.material-journey-transport-tours__card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
}

.material-journey-transport-tours__card--selected {
  border-color: #16a34a;
  box-shadow: 0 0 0 1px #16a34a inset;
}

.material-journey-transport-tours__card-head {
  display: flex;
  align-items: center;
  gap: 4px;
  width: 100%;
  padding: 8px 8px 8px 4px;
  background: #f8fafc;
}

.material-journey-transport-tours__card-chevron-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 44px;
  min-height: 44px;
  padding: 0;
  border: 0;
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
  font: inherit;
  color: #64748b;
}

.material-journey-transport-tours__card-chevron-btn:hover {
  background: #e2e8f0;
}

.material-journey-transport-tours__card-head-select {
  display: flex;
  flex: 1 1 auto;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-width: 0;
  min-height: 44px;
  padding: 4px 8px;
  border: 2px solid transparent;
  border-radius: 8px;
  background: transparent;
  text-align: left;
  cursor: default;
  font: inherit;
}

.material-journey-transport-tours__card-head-select--selectable {
  cursor: pointer;
}

.material-journey-transport-tours__card-head-select--selectable:hover {
  background: #f1f5f9;
}

.material-journey-transport-tours__card-head-select--active {
  border-color: #16a34a;
  background: #f0fdf4;
}

.material-journey-transport-tours__card--selected .material-journey-transport-tours__card-head {
  background: #f0fdf4;
}

.material-journey-transport-tours__depart {
  flex-shrink: 0;
  margin-left: auto;
}

.material-journey-transport-tours__card-label {
  font-weight: 600;
}

.material-journey-transport-tours__status {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.material-journey-transport-tours__status--planned {
  background: #f3f4f6;
  color: #4b5563;
}

.material-journey-transport-tours__status--in_transit {
  background: #dbeafe;
  color: #1d4ed8;
}

.material-journey-transport-tours__status--arrived {
  background: #dcfce7;
  color: #166534;
}

.material-journey-transport-tours__fit {
  margin-left: auto;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.material-journey-transport-tours__fit--ok {
  background: #dcfce7;
  color: #166534;
}

.material-journey-transport-tours__fit--heavy {
  background: #fee2e2;
  color: #991b1b;
}

.material-journey-transport-tours__fit--unknown {
  background: #f3f4f6;
  color: #4b5563;
}

.material-journey-transport-tours__weight {
  font-size: 12px;
  color: #64748b;
}

.material-journey-transport-tours__weight-sep {
  margin: 0 4px;
  color: #94a3b8;
}

.material-journey-transport-tours__weight--heavy .material-journey-transport-tours__weight-material,
.material-journey-transport-tours__weight--heavy .material-journey-transport-tours__weight-payload {
  color: #991b1b;
  font-weight: 600;
}

.material-journey-transport-tours__weight--heavy .material-journey-transport-tours__weight-sep {
  color: #991b1b;
}

.material-journey-transport-tours__loaded-count {
  font-size: 12px;
}

.material-journey-transport-tours__subsection-title {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
}

.material-journey-transport-tours__loaded-list {
  margin: 0 0 12px;
  padding: 0;
  list-style: none;
}

.material-journey-transport-tours__loaded-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 4px 0;
  border-bottom: 1px solid #f1f5f9;
}

.material-journey-transport-tours__loaded-main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-height: 44px;
}

.material-journey-transport-tours__loaded-row:last-child {
  border-bottom: 0;
}

.material-journey-transport-tours__loaded-label {
  flex: 1 1 auto;
}

.material-journey-transport-tours__loaded-crate {
  padding: 0 0 4px 8px;
}

.material-journey-transport-tours__loaded-crate-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 4px 8px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  font: inherit;
  font-size: 13px;
  color: #475569;
  cursor: pointer;
}

.material-journey-transport-tours__loaded-crate-toggle:hover {
  background: #f1f5f9;
}

.material-journey-transport-tours__loaded-crate-chevron {
  display: inline-block;
  transition: transform 0.15s ease;
}

.material-journey-transport-tours__loaded-crate-chevron--open {
  transform: rotate(90deg);
}

.material-journey-transport-tours__loaded-crate-list {
  margin: 0 0 4px;
  padding: 0 0 0 28px;
  list-style: none;
}

.material-journey-transport-tours__loaded-crate-line {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  padding: 4px 0;
  font-size: 13px;
}

.material-journey-transport-tours__loaded-crate-empty {
  padding: 4px 0;
  font-size: 13px;
}

.material-journey-transport-tours__weight-unknown {
  font-size: 12px;
}

.material-journey-transport-tours__inherited-weight {
  margin-left: auto;
  font-size: 12px;
  white-space: nowrap;
}

.material-journey-transport-tours__review-weight {
  font-size: 13px;
  font-weight: 600;
}

.material-journey-transport-tours__inherited-note {
  font-size: 12px;
}

.material-journey-transport-tours__weight-field {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-left: auto;
}

.material-journey-transport-tours__weight-label {
  font-size: 12px;
  color: #64748b;
  white-space: nowrap;
}

.material-journey-transport-tours__weight-input {
  width: 72px;
  min-height: 36px;
  padding: 4px 8px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font: inherit;
  text-align: right;
}

.material-journey-transport-tours__weight-unit {
  font-size: 12px;
  color: #64748b;
}

.material-journey-transport-tours__remove {
  margin-left: auto;
}

.material-journey-transport-tours__no-loaded {
  margin: 0 0 12px;
}

.material-journey-transport-tours__weight-estimate {
  margin: 0 0 16px;
  font-size: 12px;
}

.material-journey-transport-tours__card-body {
  padding: 12px;
}

.material-journey-transport-tours__assign-hint {
  margin: 0 0 8px;
  font-size: 13px;
}

.material-journey-transport-tours__assign-list {
  margin: 0 0 12px;
  padding: 0;
  list-style: none;
}

.material-journey-transport-tours__assign-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 4px 0;
}

.material-journey-transport-tours__kind {
  font-size: 12px;
  color: #64748b;
}

.material-journey-transport-tours__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.material-journey-transport-tours__delete {
  margin-left: auto;
}

.material-journey-transport-tours__dialog {
  padding: 16px;
}

.material-journey-transport-tours__dialog-title {
  margin: 0 0 12px;
}

.material-journey-transport-tours__field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
  font-size: 13px;
  font-weight: 600;
}

.material-journey-transport-tours__select,
.material-journey-transport-tours__input {
  min-height: 44px;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font: inherit;
  font-weight: 400;
}

.material-journey-transport-tours__dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
}
</style>
