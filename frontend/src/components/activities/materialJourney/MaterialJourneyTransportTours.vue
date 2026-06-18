<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  createActivityTransportTour,
  deleteActivityTransportTour,
  directionForJourneyStep,
  getActivityTransportTours,
  updateActivityTransportTour,
  type ActivityTransportTour,
  type TransportTourDirection,
  type TransportTourItemInput,
} from '@/api/activityTransportTours'
import {
  createDepartmentVehicle,
  getDepartmentVehicles,
  type DepartmentVehicle,
} from '@/api/departmentVehicles'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import EButton from '@/components/form/base/EButton.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps<{
  activityId: string
  departmentId: string
  journeyStep: string
  listEditable: boolean
  assignableTasks: MaterialJourneyTaskRow[]
}>()

const { t } = useI18n()
const toast = useToast()

const direction = computed((): TransportTourDirection | null =>
  directionForJourneyStep(props.journeyStep),
)

const loading = ref(false)
const tours = ref<ActivityTransportTour[]>([])
const vehicles = ref<DepartmentVehicle[]>([])
const expandedTourId = ref<string | null>(null)
const createOpen = ref(false)
const createVehicleId = ref('')
const createBusy = ref(false)
const quickVehicleName = ref('')
const quickVehiclePayload = ref<number | null>(null)
const savingTourId = ref<string | null>(null)

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

async function loadAll(): Promise<void> {
  if (!direction.value) return
  loading.value = true
  try {
    const [tourList, vehicleList] = await Promise.all([
      getActivityTransportTours(props.activityId, direction.value),
      getDepartmentVehicles(props.departmentId, { activityId: props.activityId }),
    ])
    tours.value = tourList
    vehicles.value = vehicleList
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

function isAssigned(tour: ActivityTransportTour, row: (typeof assignableRows.value)[number]): boolean {
  return tour.items.some(
    (item) =>
      (row.containerId && item.pack_container_id === row.containerId) ||
      (row.packItemId && item.pack_item_id === row.packItemId),
  )
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
  if (!props.listEditable) return
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

async function onQuickAddVehicle(): Promise<void> {
  const name = quickVehicleName.value.trim()
  if (!name) return
  createBusy.value = true
  try {
    const created = await createDepartmentVehicle(props.departmentId, {
      name,
      max_payload_kg: quickVehiclePayload.value ?? undefined,
    })
    vehicles.value = [...vehicles.value, created]
    createVehicleId.value = created.id
    quickVehicleName.value = ''
    quickVehiclePayload.value = null
    toast.success(t('activities.materialJourney.transportTours.vehicleCreated'))
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  } finally {
    createBusy.value = false
  }
}

async function onDeleteTour(tour: ActivityTransportTour): Promise<void> {
  if (!props.listEditable) return
  try {
    await deleteActivityTransportTour(props.activityId, tour.id)
    tours.value = tours.value.filter((t) => t.id !== tour.id)
    if (expandedTourId.value === tour.id) expandedTourId.value = null
  } catch (e) {
    toast.error(e instanceof Error ? e.message : String(e))
  }
}

function toggleExpanded(tourId: string): void {
  expandedTourId.value = expandedTourId.value === tourId ? null : tourId
}
</script>

<template>
  <section v-if="direction" class="material-journey-transport-tours section-card">
    <div class="material-journey-transport-tours__header">
      <div>
        <h2 class="material-journey-transport-tours__title">
          {{ t('activities.materialJourney.transportTours.title') }}
        </h2>
        <p class="text-muted material-journey-transport-tours__hint">
          {{ t('activities.materialJourney.transportTours.hint') }}
        </p>
      </div>
      <EButton
        v-if="listEditable"
        variant="secondary"
        size="small"
        @click="createOpen = true"
      >
        {{ t('activities.materialJourney.transportTours.addTour') }}
      </EButton>
    </div>

    <p v-if="loading" class="text-muted">{{ t('common.loading') }}</p>
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
          <span class="material-journey-transport-tours__fit" :class="fitClass(tour.load_summary.fit)">
            {{ fitLabel(tour.load_summary.fit) }}
          </span>
          <span v-if="tour.load_summary.max_payload_kg" class="text-muted material-journey-transport-tours__weight">
            {{ t('activities.materialJourney.transportTours.weightHint', {
              weight: tour.load_summary.estimated_weight_kg,
              max: tour.load_summary.max_payload_kg,
            }) }}
          </span>
        </button>

        <div v-show="expandedTourId === tour.id" class="material-journey-transport-tours__card-body">
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
          <EButton
            v-if="listEditable"
            variant="secondary"
            size="small"
            class="material-journey-transport-tours__delete"
            @click="onDeleteTour(tour)"
          >
            {{ t('activities.materialJourney.transportTours.deleteTour') }}
          </EButton>
        </div>
      </article>
    </div>

    <v-dialog v-model="createOpen" max-width="480">
      <div class="material-journey-transport-tours__dialog section-card">
        <h3 class="material-journey-transport-tours__dialog-title">
          {{ t('activities.materialJourney.transportTours.createTitle') }}
        </h3>
        <label class="material-journey-transport-tours__field">
          <span>{{ t('activities.materialJourney.transportTours.vehicleLabel') }}</span>
          <select v-model="createVehicleId" class="material-journey-transport-tours__select">
            <option value="">{{ t('activities.materialJourney.transportTours.chooseVehicle') }}</option>
            <option v-for="v in vehicles" :key="v.id" :value="v.id">
              {{ v.name }}{{ v.plate ? ` (${v.plate})` : '' }}
            </option>
          </select>
        </label>
        <div v-if="listEditable" class="material-journey-transport-tours__quick-add">
          <p class="text-muted">{{ t('activities.materialJourney.transportTours.quickAddHint') }}</p>
          <div class="material-journey-transport-tours__quick-row">
            <input
              v-model="quickVehicleName"
              type="text"
              class="material-journey-transport-tours__input"
              :placeholder="t('activities.materialJourney.transportTours.quickAddName')"
            />
            <input
              v-model.number="quickVehiclePayload"
              type="number"
              min="0"
              class="material-journey-transport-tours__input material-journey-transport-tours__input--short"
              :placeholder="t('activities.materialJourney.transportTours.quickAddPayload')"
            />
            <EButton variant="secondary" size="small" :disabled="createBusy" @click="onQuickAddVehicle">
              {{ t('common.add') }}
            </EButton>
          </div>
        </div>
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

.material-journey-transport-tours__delete {
  margin-top: 4px;
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

.material-journey-transport-tours__input--short {
  max-width: 120px;
}

.material-journey-transport-tours__quick-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.material-journey-transport-tours__dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
}
</style>
