import { computed, ref, watch, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackItem, PackMoveSource } from '@/api/activityPackItems'
import { postMovePackItem, postMoveBackPackItem } from '@/api/activityPackItems'
import {
  createActivityTransportTour,
  directionForJourneyStep,
  formatTourDisplayLabel,
  getActivityTransportTours,
  transportTourUiModeForJourneyStep,
  updateActivityTransportTour,
  mapTourItemsForPatch,
  type ActivityTransportTour,
  type TransportTourDirection,
  type TransportTourItemInput,
} from '@/api/activityTransportTours'
import { getActivityVehicles, type ActivityVehicleAssignment } from '@/api/activityVehicles'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'
import { isMaterialJourneyCrateKind } from '@/components/activities/materialJourneyTaskList'
import type { PackStage } from '@/components/activities/packStageQuantities'
import { getBackendStage } from '@/components/activities/packStageQuantities'
import { useToast } from '@/composables/useToast'

export function useMaterialJourneyTransportTourTarget(options: {
  activityId: Ref<string>
  journeyStep: Ref<JourneyStep>
  listEditable: Ref<boolean>
  canManageMaterials: Ref<boolean>
  assignableTasks: Ref<MaterialJourneyTaskRow[]>
  packStage: Ref<PackStage>
  shellPackItemForContainer: (containerId: string) => ActivityPackItem | undefined
  applyUpdatedItem: (pi: ActivityPackItem) => void
  reload: () => Promise<void>
}) {
  const { t } = useI18n()
  const toast = useToast()

  const selectedTourId = ref<string | null>(null)
  const simpleTourMode = ref(false)
  const tours = ref<ActivityTransportTour[]>([])
  const activityVehicles = ref<ActivityVehicleAssignment[]>([])
  const toursLoading = ref(false)
  const assignTourSubmitting = ref(false)
  const chooseTourModalOpen = ref(false)
  let chooseTourResolver: ((chosen: boolean) => void) | null = null

  const direction = computed((): TransportTourDirection | null =>
    directionForJourneyStep(options.journeyStep.value),
  )

  const isPlanMode = computed(
    () => transportTourUiModeForJourneyStep(options.journeyStep.value) === 'plan',
  )

  const transportTourSelectMode = computed(
    () =>
      isPlanMode.value &&
      direction.value != null &&
      options.listEditable.value &&
      options.canManageMaterials.value &&
      options.assignableTasks.value.length > 0,
  )

  const hasTourTargetChosen = computed(
    () => simpleTourMode.value || selectedTourId.value != null,
  )

  const selectedTour = computed(
    () => tours.value.find((tour) => tour.id === selectedTourId.value) ?? null,
  )

  const selectedTourLabel = computed(() =>
    selectedTour.value ? formatTourDisplayLabel(selectedTour.value) : null,
  )

  const transportTourAssignActive = computed(
    () => transportTourSelectMode.value && hasTourTargetChosen.value && !simpleTourMode.value,
  )

  async function loadToursAndVehicles(): Promise<void> {
    if (!direction.value || !isPlanMode.value) {
      tours.value = []
      activityVehicles.value = []
      return
    }
    toursLoading.value = true
    try {
      const [tourList, vehicleAssignments] = await Promise.all([
        getActivityTransportTours(options.activityId.value, direction.value),
        getActivityVehicles(options.activityId.value),
      ])
      tours.value = tourList
      activityVehicles.value = vehicleAssignments
      if (selectedTourId.value && !tourList.some((tour) => tour.id === selectedTourId.value)) {
        selectedTourId.value = null
      }
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
    } finally {
      toursLoading.value = false
    }
  }

  watch(
    [options.activityId, options.journeyStep, isPlanMode],
    () => {
      void loadToursAndVehicles()
    },
    { immediate: true },
  )

  watch(options.journeyStep, (step) => {
    if (step !== 'transport_out' && step !== 'transport_back') {
      selectedTourId.value = null
      simpleTourMode.value = false
    }
  })

  watch(transportTourSelectMode, (active) => {
    if (!active) {
      selectedTourId.value = null
      simpleTourMode.value = false
    }
  })

  watch(activityVehicles, (vehicles) => {
    if (!selectedTourId.value) return
    const tour = tours.value.find((entry) => entry.id === selectedTourId.value)
    if (!tour) {
      selectedTourId.value = null
      return
    }
    if (!vehicles.some((assignment) => assignment.vehicle_id === tour.vehicle_id)) {
      selectedTourId.value = null
      simpleTourMode.value = false
    }
  })

  function clearTourTarget(): void {
    selectedTourId.value = null
    simpleTourMode.value = false
  }

  function selectSimpleTour(fromModal = false): void {
    if (!fromModal && simpleTourMode.value) {
      simpleTourMode.value = false
      return
    }
    selectedTourId.value = null
    simpleTourMode.value = true
    resolveChooseTourModal(true)
  }

  function toggleTourSelection(tourId: string): void {
    simpleTourMode.value = false
    selectedTourId.value = selectedTourId.value === tourId ? null : tourId
  }

  function tourForVehicle(vehicleId: string): ActivityTransportTour | undefined {
    return tours.value.find(
      (tour) => tour.vehicle_id === vehicleId && tour.status === 'planned',
    )
  }

  async function ensureTourForVehicle(vehicleId: string): Promise<ActivityTransportTour | null> {
    if (!direction.value) return null
    const existing = tourForVehicle(vehicleId)
    if (existing) return existing
    try {
      const created = await createActivityTransportTour(options.activityId.value, {
        vehicle_id: vehicleId,
        direction: direction.value,
      })
      tours.value = [...tours.value, created]
      return created
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
      return null
    }
  }

  async function selectVehicleTarget(vehicleId: string, fromModal = false): Promise<void> {
    const existing = tourForVehicle(vehicleId)
    if (!fromModal && existing && selectedTourId.value === existing.id) {
      selectedTourId.value = null
      return
    }
    const tour = await ensureTourForVehicle(vehicleId)
    if (!tour) return
    simpleTourMode.value = false
    selectedTourId.value = tour.id
    if (fromModal) resolveChooseTourModal(true)
  }

  function resolveChooseTourModal(chosen: boolean): void {
    if (!chooseTourResolver) return
    chooseTourResolver(chosen)
    chooseTourResolver = null
    chooseTourModalOpen.value = false
  }

  function ensureTourTargetBeforeMove(): Promise<boolean> {
    if (!transportTourSelectMode.value || hasTourTargetChosen.value) {
      return Promise.resolve(true)
    }
    if (activityVehicles.value.length === 0) {
      simpleTourMode.value = true
      return Promise.resolve(true)
    }
    chooseTourModalOpen.value = true
    return new Promise((resolve) => {
      chooseTourResolver = resolve
    })
  }

  function cancelChooseTourModal(): void {
    resolveChooseTourModal(false)
  }

  function resolvePackItemForRow(row: MaterialJourneyTaskRow): ActivityPackItem | undefined {
    if (row.packItem) return row.packItem
    if (isMaterialJourneyCrateKind(row.kind) && row.container) {
      return options.shellPackItemForContainer(row.container.id)
    }
    return undefined
  }

  function tourItemKey(row: MaterialJourneyTaskRow): Pick<TransportTourItemInput, 'pack_container_id' | 'pack_item_id'> {
    if (isMaterialJourneyCrateKind(row.kind) && row.container) {
      return { pack_container_id: row.container.id }
    }
    if (row.packItem) {
      return { pack_item_id: row.packItem.id }
    }
    return {}
  }

  function isRowOnTour(tour: ActivityTransportTour, row: MaterialJourneyTaskRow): boolean {
    const key = tourItemKey(row)
    return tour.items.some(
      (item) =>
        (key.pack_container_id && item.pack_container_id === key.pack_container_id) ||
        (key.pack_item_id && item.pack_item_id === key.pack_item_id),
    )
  }

  async function moveTransportPipeline(
    row: MaterialJourneyTaskRow,
    qty: number,
    source: PackMoveSource,
  ): Promise<boolean> {
    const pi = resolvePackItemForRow(row)
    const activityId = options.activityId.value
    if (!pi || !activityId || row.maxForwardQty < 1) return false

    const moveQty =
      isMaterialJourneyCrateKind(row.kind)
        ? 1
        : Math.min(row.maxForwardQty, Math.max(1, Math.floor(qty)))
    try {
      const updated = await postMovePackItem(activityId, pi.id, {
        stage: getBackendStage(options.packStage.value),
        quantity: moveQty,
        source,
      })
      options.applyUpdatedItem(updated)
      return true
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
      return false
    }
  }

  async function addRowToSelectedTour(
    row: MaterialJourneyTaskRow,
    qty: number,
  ): Promise<boolean> {
    const tourId = selectedTourId.value
    const activityId = options.activityId.value
    const tour = tours.value.find((t) => t.id === tourId)
    if (!tourId || !activityId || !tour) return false

    const key = tourItemKey(row)
    if (!key.pack_container_id && !key.pack_item_id) return false

    const addQty = isMaterialJourneyCrateKind(row.kind) ? 1 : Math.max(1, Math.floor(qty))
    const items = mapTourItemsForPatch(tour.items)
    const existingIdx = items.findIndex(
      (item) =>
        (key.pack_container_id && item.pack_container_id === key.pack_container_id) ||
        (key.pack_item_id && item.pack_item_id === key.pack_item_id),
    )
    if (existingIdx >= 0) {
      const existing = items[existingIdx]!
      items[existingIdx] = {
        ...existing,
        quantity: Math.max(1, (existing.quantity ?? 0) + addQty),
      }
    } else {
      items.push({
        ...key,
        quantity: addQty,
      })
    }

    try {
      const updated = await updateActivityTransportTour(activityId, tourId, { items })
      tours.value = tours.value.map((t) => (t.id === updated.id ? updated : t))
      return true
    } catch (e) {
      toast.error(e instanceof Error ? e.message : String(e))
      return false
    }
  }

  async function assignRowToSelectedTour(
    row: MaterialJourneyTaskRow,
    qty: number,
    source: PackMoveSource = 'tap',
  ): Promise<boolean> {
    assignTourSubmitting.value = true
    const pi = resolvePackItemForRow(row)
    const moveQty =
      isMaterialJourneyCrateKind(row.kind)
        ? 1
        : Math.min(row.maxForwardQty, Math.max(1, Math.floor(qty)))
    try {
      const moved = await moveTransportPipeline(row, qty, source)
      if (!moved) return false
      const assigned = await addRowToSelectedTour(row, qty)
      if (!assigned) {
        // Pipeline schon gebucht — Tour-Zuordnung fehlgeschlagen → Move rückgängig
        if (pi && options.activityId.value && moveQty > 0) {
          try {
            const rolledBack = await postMoveBackPackItem(options.activityId.value, pi.id, {
              stage: getBackendStage(options.packStage.value),
              quantity: moveQty,
            })
            options.applyUpdatedItem(rolledBack)
          } catch {
            /* Rollback-Fehler separat; Zuordnungsfehler schon getoastet */
          }
        }
        return false
      }
      toast.success(
        t('activities.materialJourney.transportTours.assignedToast', {
          label: selectedTourLabel.value ?? '',
        }),
      )
      return true
    } finally {
      assignTourSubmitting.value = false
    }
  }

  async function bookTransportRow(
    row: MaterialJourneyTaskRow,
    qty: number,
    source: PackMoveSource = 'tap',
  ): Promise<boolean> {
    if (!transportTourSelectMode.value) return false
    const ready = await ensureTourTargetBeforeMove()
    if (!ready) return false

    if (simpleTourMode.value) {
      assignTourSubmitting.value = true
      try {
        return await moveTransportPipeline(row, qty, source)
      } finally {
        assignTourSubmitting.value = false
      }
    }

    if (selectedTourId.value) {
      return assignRowToSelectedTour(row, qty, source)
    }

    return false
  }

  return {
    selectedTourId,
    simpleTourMode,
    tours,
    activityVehicles,
    toursLoading,
    assignTourSubmitting,
    chooseTourModalOpen,
    transportTourSelectMode,
    transportTourAssignActive,
    hasTourTargetChosen,
    selectedTourLabel,
    selectedTour,
    loadToursAndVehicles,
    clearTourTarget,
    selectSimpleTour,
    toggleTourSelection,
    selectVehicleTarget,
    tourForVehicle,
    ensureTourTargetBeforeMove,
    cancelChooseTourModal,
    bookTransportRow,
  }
}
