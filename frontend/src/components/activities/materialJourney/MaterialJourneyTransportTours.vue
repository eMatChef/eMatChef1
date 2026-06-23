<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  arriveActivityTransportTour,
  arriveAllActivityTransportTours,
  createActivityTransportTour,
  deleteActivityTransportTour,
  directionForJourneyStep,
  getActivityTransportTours,
  transportTourUiModeForJourneyStep,
  updateActivityTransportTour,
  type ActivityTransportTour,
  type TransportTourDirection,
  type TransportTourItemInput,
  type TransportTourStatus,
} from '@/api/activityTransportTours'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import { packMaterialDisplayName } from '@/components/activities/packMaterialDisplay'
import { getActivityVehicles, type ActivityVehicleAssignment } from '@/api/activityVehicles'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import EButton from '@/components/form/base/EButton.vue'
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
  assignableTasks: MaterialJourneyTaskRow[]
  packItems?: ActivityPackItem[]
  packContainers?: ActivityPackContainer[]
}>()

const emit = defineEmits<{
  pipelineChanged: []
}>()

const { t } = useI18n()
const toast = useToast()

const direction = computed((): TransportTourDirection | null =>
  directionForJourneyStep(props.journeyStep),
)

const uiMode = computed(() => transportTourUiModeForJourneyStep(props.journeyStep))

const isArrivalMode = computed(() => uiMode.value === 'arrival')
const isPlanMode = computed(() => uiMode.value === 'plan')

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

const assignableRows = computed(() =>
  props.assignableTasks
    .filter((row) => row.isOpen && (row.kind === 'crate' || row.kind === 'loose'))
    .map((row) => ({
      key: row.id,
      label: row.title,
      kind: row.kind,
      containerId: row.container?.id ?? null,
      packItemId: row.packItem?.id ?? null,
    })),
)

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
  if (!direction.value) return false
  if (!isArrivalMode.value) return true
  if (loading.value) return true
  return hasOpenArrivalWork.value
})

async function loadAll(): Promise<void> {
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

function isAssigned(tour: ActivityTransportTour, row: (typeof assignableRows.value)[number]): boolean {
  return tour.items.some(
    (item) =>
      (row.containerId && item.pack_container_id === row.containerId) ||
      (row.packItemId && item.pack_item_id === row.packItemId),
  )
}

function assignedItemLabels(tour: ActivityTransportTour): string[] {
  return tour.items.map((item) => {
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
  })
}

function buildItemsForTour(
  tour: ActivityTransportTour,
  row: (typeof assignableRows.value)[number],
  checked: boolean,
): TransportTourItemInput[] {
  const next: TransportTourItemInput[] = tour.items
    .filter(
      (item) =>
        !(
          (row.containerId && item.pack_container_id === row.containerId) ||
          (row.packItemId && item.pack_item_id === row.packItemId)
        ),
    )
    .map((item) => ({
      pack_container_id: item.pack_container_id ?? undefined,
      pack_item_id: item.pack_item_id ?? undefined,
      quantity: item.quantity ?? 1,
    }))
  if (checked) {
    next.push({
      pack_container_id: row.containerId ?? undefined,
      pack_item_id: row.packItemId ?? undefined,
      quantity: 1,
    })
  }
  return next
}

async function toggleAssignment(
  tour: ActivityTransportTour,
  row: (typeof assignableRows.value)[number],
  checked: boolean,
): Promise<void> {
  if (!props.listEditable || isArrivalMode.value) return
  savingTourId.value = tour.id
  try {
    const updated = await updateActivityTransportTour(props.activityId, tour.id, {
      items: buildItemsForTour(tour, row, checked),
    })
    tours.value = tours.value.map((t) => (t.id === updated.id ? updated : t))
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    savingTourId.value = null
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
</script>

<template>
  <section v-if="showSection" class="material-journey-transport-tours section-card">
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
    <p v-else-if="tours.length === 0" class="text-muted">
      {{ t('activities.materialJourney.transportTours.empty') }}
    </p>

    <div v-else class="material-journey-transport-tours__list">
      <article
        v-for="tour in tours"
        :key="tour.id"
        class="material-journey-transport-tours__card"
      >
        <button type="button" class="material-journey-transport-tours__card-head" @click="toggleExpanded(tour.id)">
          <span class="material-journey-transport-tours__card-chevron" aria-hidden="true">
            {{ expandedTourId === tour.id ? '▾' : '▸' }}
          </span>
          <span class="material-journey-transport-tours__card-label">{{ tour.label }}</span>
          <span class="text-muted">{{ tour.vehicle_name }}</span>
          <span
            class="material-journey-transport-tours__status"
            :class="statusClass(tour.status)"
          >
            {{ statusLabel(tour.status) }}
          </span>
          <span
            v-if="isPlanMode"
            class="material-journey-transport-tours__fit"
            :class="fitClass(tour.load_summary.fit)"
          >
            {{ fitLabel(tour.load_summary.fit) }}
          </span>
          <span
            v-if="isPlanMode && tour.load_summary.max_payload_kg"
            class="text-muted material-journey-transport-tours__weight"
          >
            {{ t('activities.materialJourney.transportTours.weightHint', {
              weight: tour.load_summary.estimated_weight_kg,
              max: tour.load_summary.max_payload_kg,
            }) }}
          </span>
        </button>

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
            <p v-if="assignableRows.length === 0" class="text-muted">
              {{ t('activities.materialJourney.transportTours.noAssignable') }}
            </p>
            <ul v-else class="material-journey-transport-tours__assign-list">
              <li v-for="row in assignableRows" :key="row.key">
                <label class="material-journey-transport-tours__assign-row">
                  <input
                    type="checkbox"
                    :checked="isAssigned(tour, row)"
                    :disabled="!listEditable || savingTourId === tour.id"
                    @change="toggleAssignment(tour, row, ($event.target as HTMLInputElement).checked)"
                  />
                  <span>{{ row.label }}</span>
                  <span v-if="row.kind === 'crate'" class="material-journey-transport-tours__kind">
                    {{ t('activities.materialJourney.badge.crate') }}
                  </span>
                </label>
              </li>
            </ul>
            <div v-if="listEditable" class="material-journey-transport-tours__actions">
              <EButton
                v-if="tour.status === 'planned'"
                variant="secondary"
                size="small"
                :loading="savingTourId === tour.id"
                @click="onDepartTour(tour)"
              >
                {{ t('activities.materialJourney.transportTours.markDeparted') }}
              </EButton>
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

.material-journey-transport-tours__card-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 12px;
  border: 0;
  background: #f8fafc;
  text-align: left;
  cursor: pointer;
  font: inherit;
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
}

.material-journey-transport-tours__card-body {
  padding: 12px;
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
