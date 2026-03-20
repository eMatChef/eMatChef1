<template>
  <div class="storage-overview-tab">
    <div v-if="isLoading" class="storage-loading">
      <div class="spinner"></div>
      <p>Regale werden geladen...</p>
    </div>

    <div v-else-if="error" class="storage-error">
      <p>{{ error }}</p>
      <button class="btn-outline" @click="load">Erneut versuchen</button>
    </div>

    <div v-else-if="!filteredOverview || filteredOverview.racks.length === 0" class="storage-empty">
      <template v-if="containerBatchId">
        <p>In dieser Kiste wurden keine passenden Inhalte gefunden.</p>
        <p class="hint">Prüfen Sie die Auswahl oder passen Sie den Suchtext an.</p>
      </template>
      <template v-else-if="materialId">
        <p>Dieses Material ist aktuell keinem Lagerort zugewiesen.</p>
        <p class="hint">Sobald Bestand zugeordnet wird, erscheint er hier gruppiert nach Standort und Lagerplatz.</p>
      </template>
      <template v-else>
        <p>Keine Regale oder Fächer vorhanden.</p>
        <p class="hint">Legen Sie Regale und Fächer in den Einstellungen an.</p>
      </template>
    </div>

    <div v-else class="storage-tree">
      <div v-if="containerBatchId" class="storage-context-hint">
        Inhalt der gewählten Kiste
      </div>
      <div v-for="location in locationNodes" :key="location.id" class="storage-location">
        <div class="location-header">
          <button class="location-toggle-btn" @click="toggleLocation(location.id)">
            <span class="tree-caret" :class="{ expanded: expandedLocations.has(location.id) }">▶</span>
            <span class="location-name">{{ location.name }}</span>
            <span v-if="location.isPrimary" class="location-primary-badge">Hauptlager</span>
            <span class="location-count">{{ location.racks.length }} Regal{{ location.racks.length !== 1 ? 'e' : '' }}</span>
          </button>
          <div class="location-actions" v-if="!readonly">
            <StorageCrudActions
              v-if="location.addressId"
              :show-edit="false"
              :show-delete="false"
              @add="addRackForLocation(location)"
            />
          </div>
        </div>

        <div v-if="expandedLocations.has(location.id)" class="location-racks">
          <div v-for="rack in location.racks" :key="rack.id" class="storage-rack">
            <div class="rack-header">
              <button class="rack-toggle-btn" @click="toggleRack(rack.id)">
                <span class="tree-caret" :class="{ expanded: expandedRacks.has(rack.id) }">▶</span>
                <span class="rack-name">{{ rack.name }}</span>
                <span class="rack-slot-count">{{ rack.slots.length }} Fach{{ rack.slots.length !== 1 ? 'fächer' : '' }}</span>
              </button>
              <div class="rack-actions" v-if="!readonly">
                <StorageCrudActions
                  :show-edit="false"
                  :show-add="true"
                  :show-delete="canDeleteRack(rack)"
                  @add="addSlotForRack(rack, location.id)"
                  @delete="deleteRackFromOverview(rack, location.id)"
                />
              </div>
            </div>

            <div v-if="expandedRacks.has(rack.id)" class="rack-slots">
              <div
                v-for="slot in rack.slots"
                :key="slot.id ?? 'rack-level'"
                class="storage-slot"
              >
                <div class="slot-header">
                  <span class="slot-name">{{ slot.name }}</span>
                  <StorageCrudActions
                    v-if="slot.id && slot.contents.length === 0"
                    size="sm"
                    :show-edit="false"
                    :show-add="false"
                    :show-delete="true"
                    @delete="deleteSlotFromOverview(rack, slot, location.id)"
                  />
                </div>
                <div v-if="slot.contents.length === 0" class="slot-empty">
                  Leer
                </div>
                <ul v-else class="slot-contents">
                  <li
                    v-for="(item, idx) in slot.contents"
                    :key="`${item.batch_id}-${item.allocation_id ?? idx}`"
                    class="content-item"
                    :class="{ 'content-item--container': isContainerStoredItem(item) }"
                  >
                    <div class="content-main">
                      <template v-if="isContainerStoredItem(item)">
                        <span class="container-label">{{ getContainerDisplayLabel(item) }}</span>
                        <span class="container-article">{{ item.material_name }}</span>
                        <span v-if="getContainerOtherItemsCount(item) > 0" class="container-more">
                          +{{ getContainerOtherItemsCount(item) }} weitere in der Kiste
                        </span>
                        <button
                          v-if="canOpenContainerMaterial(item)"
                          class="container-link-btn"
                          @click.stop="openContainerMaterialFromStoredItem(item)"
                        >
                          Kiste öffnen
                        </button>
                      </template>
                      <template v-else>
                        <template v-if="item.container_label">
                          <span class="content-label">{{ item.container_label }}</span>
                          <span class="content-material-name">{{ item.material_name }}</span>
                        </template>
                        <template v-else>
                          <span class="content-name">{{ item.material_name }}</span>
                        </template>
                      </template>
                    </div>
                    <span class="content-qty">{{ item.qty }} Stk.</span>
                    <div class="content-actions" v-if="!readonly || allowMoveActions || allowOpenActions">
                      <StorageActionButton
                        v-if="item.tracking_type !== 'serialized'"
                        title="Menge verschieben"
                        size="sm"
                        icon="move"
                        @click.stop="openMoveForItem(item, rack, slot)"
                      />
                      <StorageActionButton
                        v-if="!readonly || allowOpenActions"
                        title="Material öffnen"
                        size="sm"
                        icon="open"
                        @click.stop="openMaterial(item)"
                      />
                    </div>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <MoveQuantityModal
      v-if="showMoveModal && moveContext"
      :material-id="moveContext.materialId"
      :department-id="departmentId"
      :batch="moveContext.batch"
      :initial-from-allocation-id="moveContext.sourceAllocationId"
      :source-rack-id="moveContext.sourceRackId"
      :source-slot-id="moveContext.sourceSlotId"
      @close="showMoveModal = false; moveContext = null"
      @saved="handleMoveSaved"
    />

    <StorageBulkCreateModal
      :is-open="showRackCreateModal"
      title="Neues Regal"
      :context-text="rackCreateLocation ? `Standort: ${rackCreateLocation.name}` : ''"
      base-label="Basisname für Regale"
      base-placeholder="z.B. Regal A"
      :suggestions="rackCreateSuggestions"
      :base-name="rackCreateBaseName"
      pair-field-label="Fach pro Regal *"
      pair-field-placeholder="z.B. D1"
      :pair-items="rackGeneratedSlotPairs"
      count-label="Anzahl Regale"
      :count="rackCreateCount"
      preview-label="Es werden folgende Regale erstellt:"
      :generated-names="rackGeneratedNames"
      :save-disabled="rackGeneratedNames.length === 0 || rackGeneratedSlotPairs.some((pair) => !pair.rightValue.trim()) || isSubmittingAction"
      :is-saving="isSubmittingAction"
      @close="closeRackCreateModal"
      @save="createRackFromModal"
      @increment="incrementRackCreateCount"
      @decrement="decrementRackCreateCount"
      @update:baseName="rackCreateBaseName = $event"
      @update:pairItemValue="updateRackPairItemValue"
    />

    <StorageBulkCreateModal
      :is-open="showSlotCreateModal"
      title="Neues Fach"
      :context-text="slotCreateRack ? `Regal: ${slotCreateRack.name}` : ''"
      base-label="Basisname für Fächer"
      base-placeholder="z.B. A"
      :suggestions="slotCreateSuggestions"
      :base-name="slotCreateBaseName"
      count-label="Anzahl Fächer"
      :count="slotCreateCount"
      preview-label="Es werden folgende Fächer erstellt:"
      :generated-names="slotGeneratedNames"
      :save-disabled="slotGeneratedNames.length === 0 || isSubmittingAction"
      :is-saving="isSubmittingAction"
      @close="closeSlotCreateModal"
      @save="createSlotFromModal"
      @increment="incrementSlotCreateCount"
      @decrement="decrementSlotCreateCount"
      @update:baseName="slotCreateBaseName = $event"
    />

    <StorageConfirmModal
      :is-open="showDeleteConfirmModal && !!deleteTarget"
      :title="deleteTarget?.type === 'rack' ? 'Regal löschen?' : 'Fach löschen?'"
      :message="`Möchten Sie ${deleteTarget?.name || ''} wirklich löschen?`"
      :is-loading="isSubmittingAction"
      @close="closeDeleteConfirmModal"
      @confirm="executeDeleteTarget"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  getStorageOverview,
  getContainerBatches,
  createStorageRack,
  createStorageSlot,
  deleteStorageRack,
  deleteStorageSlot,
  type ContainerBatch,
  type StorageOverviewResponse,
  type StorageOverviewRack,
  type StorageOverviewSlot,
  type StorageSlotContent
} from '@/api/storageLocations'
import { getAddresses, type Address } from '@/api/addresses'
import { getMaterial } from '@/api/materials'
import MoveQuantityModal from '@/components/material/MoveQuantityModal.vue'
import { useToast } from '@/composables/useToast'
import StorageBulkCreateModal from '@/components/storage/StorageBulkCreateModal.vue'
import StorageConfirmModal from '@/components/storage/StorageConfirmModal.vue'
import StorageActionButton from '@/components/storage/StorageActionButton.vue'
import StorageCrudActions from '@/components/storage/StorageCrudActions.vue'
import {
  getRackSuggestions,
  generateRackNames,
  getSlotPrefix,
  getSlotSuggestions,
  generateSequentialNames,
} from '@/utils/storageUi'

interface Props {
  departmentId: string
  materialId?: string
  readonly?: boolean
  allowMoveActions?: boolean
  allowOpenActions?: boolean
  containerBatchId?: string
  searchQuery?: string
}

const props = withDefaults(defineProps<Props>(), {
  readonly: false,
  allowMoveActions: false,
  allowOpenActions: false,
  containerBatchId: '',
  searchQuery: '',
})
const router = useRouter()
const toast = useToast()

const overview = ref<StorageOverviewResponse | null>(null)
const storageAddresses = ref<Address[]>([])
const isLoading = ref(true)
const error = ref('')
const expandedLocations = ref<Set<string>>(new Set())
const expandedRacks = ref<Set<string>>(new Set())
const showMoveModal = ref(false)
const moveContext = ref<{
  materialId: string
  batch: { id: string; qty: number; rack_id?: string | null; slot_id?: string | null; allocations?: any[] }
  sourceAllocationId?: string | null
  sourceRackId?: string | null
  sourceSlotId?: string | null
} | null>(null)
const showRackCreateModal = ref(false)
const rackCreateLocation = ref<StorageLocationNode | null>(null)
const rackCreateBaseName = ref('Regal ')
const rackCreateCount = ref(1)
const rackSlotNameOverrides = ref<Record<string, string>>({})
const showSlotCreateModal = ref(false)
const slotCreateRack = ref<StorageOverviewRack | null>(null)
const slotCreateLocationId = ref('')
const slotCreateBaseName = ref('A')
const slotCreateCount = ref(1)
const showDeleteConfirmModal = ref(false)
const deleteTarget = ref<{ type: 'rack' | 'slot'; locationId: string; rackId: string; slotId?: string; name: string } | null>(null)
const isSubmittingAction = ref(false)
const containerBatches = ref<ContainerBatch[]>([])

type StorageLocationNode = {
  id: string
  addressId: string | null
  name: string
  isPrimary: boolean
  racks: StorageOverviewRack[]
}

const locationNodes = computed<StorageLocationNode[]>(() => {
  const racks = filteredOverview.value?.racks ?? []
  const map = new Map<string, StorageLocationNode>()
  for (const rack of racks) {
    const locId = rack.storage_address_id || '__unknown__'
    const address = rack.storage_address_id
      ? storageAddresses.value.find((a) => a.id === rack.storage_address_id)
      : null
    const locName = address
      ? (address.name || address.street_line || address.full_address || address.id)
      : (rack.storage_address_name || 'Ohne Lagerstandort')
    const isPrimary = !!address?.is_primary
    const existing = map.get(locId)
    if (existing) {
      existing.racks.push(rack)
      continue
    }
    map.set(locId, {
      id: locId,
      addressId: address?.id || null,
      name: locName,
      isPrimary,
      racks: [rack],
    })
  }
  return Array.from(map.values()).sort((a, b) => {
    if (a.isPrimary && !b.isPrimary) return -1
    if (!a.isPrimary && b.isPrimary) return 1
    return a.name.localeCompare(b.name, 'de')
  })
})

const normalizedSearchQuery = computed(() => (props.searchQuery || '').trim().toLocaleLowerCase('de-CH'))
const containerMaterialByBatchId = computed(() => {
  const map = new Map<string, string>()
  for (const batch of containerBatches.value) {
    const materialId = (batch.material_id || '').trim()
    if (batch.id && materialId) map.set(batch.id, materialId)
  }
  return map
})

function matchesSearch(item: StorageSlotContent): boolean {
  const q = normalizedSearchQuery.value
  if (!q) return true
  const haystack = [
    item.material_name || '',
    item.container_label || '',
    item.container_batch_id || '',
    item.batch_id || '',
  ]
    .join(' ')
    .toLocaleLowerCase('de-CH')
  return haystack.includes(q)
}

const filteredOverview = computed<StorageOverviewResponse | null>(() => {
  const raw = overview.value
  if (!raw) return null
  const byContainer = (props.containerBatchId || '').trim()
  const byMaterial = (props.materialId || '').trim()
  const hasSearch = normalizedSearchQuery.value.length > 0
  if (!byContainer && !byMaterial && !hasSearch) return raw

  const filteredRacks = raw.racks
    .map((rack) => {
      const filteredSlots = (rack.slots || [])
        .map((slot) => ({
          ...slot,
          contents: (slot.contents || []).filter((item) => {
            if (byContainer) {
              if ((item.container_batch_id || '') !== byContainer) return false
            } else if (byMaterial && item.material_id !== byMaterial) {
              return false
            }
            return matchesSearch(item)
          }),
        }))
        .filter((slot) => slot.contents.length > 0)

      return {
        ...rack,
        slots: filteredSlots,
      }
    })
    .filter((rack) => rack.slots.length > 0)

  return { racks: filteredRacks }
})

const primaryStorageAddressId = computed(() => {
  return storageAddresses.value.find((a) => a.is_primary)?.id || ''
})

const rackCreateSuggestions = computed(() => {
  return getRackSuggestions(rackCreateLocation.value?.racks.map((rack) => rack.name) || [])
})

const rackGeneratedNames = computed(() => {
  return generateRackNames(
    rackCreateBaseName.value,
    rackCreateCount.value,
    rackCreateLocation.value?.racks.map((rack) => rack.name) || []
  )
})

const rackGeneratedSlotPairs = computed(() => {
  return rackGeneratedNames.value.map((rackName) => ({
    id: rackName,
    leftLabel: rackName,
    rightValue: rackSlotNameOverrides.value[rackName] ?? `${getSlotPrefix(rackName)}1`,
  }))
})

const slotCreateSuggestions = computed(() => {
  const rack = slotCreateRack.value
  if (!rack) return []
  return getSlotSuggestions(rack.name, (rack.slots || []).map((s) => s.name))
})

const slotGeneratedNames = computed(() => {
  const rack = slotCreateRack.value
  if (!rack) return []
  return generateSequentialNames(
    slotCreateBaseName.value,
    slotCreateCount.value,
    (rack.slots || []).map((s) => s.name)
  )
})

function toggleLocation(locationId: string) {
  const isCurrentlyOpen = expandedLocations.value.has(locationId)
  if (isCurrentlyOpen) {
    expandedLocations.value = new Set()
    expandedRacks.value = new Set()
    return
  }

  const next = new Set(expandedLocations.value)
  next.clear()
  next.add(locationId)
  expandedLocations.value = next

  const location = locationNodes.value.find((node) => node.id === locationId)
  if (location && location.racks.length < 10) {
    expandedRacks.value = new Set(location.racks.map((rack) => rack.id))
  } else {
    expandedRacks.value = new Set()
  }
}

function toggleRack(rackId: string) {
  const next = new Set(expandedRacks.value)
  if (next.has(rackId)) {
    next.delete(rackId)
  } else {
    next.add(rackId)
  }
  expandedRacks.value = next
}

function canDeleteRack(rack: StorageOverviewRack): boolean {
  const totalItems = rack.slots.reduce((sum, slot) => sum + slot.contents.length, 0)
  return totalItems === 0
}

async function addRackForLocation(location: StorageLocationNode) {
  if (!location.addressId) {
    toast.error('Für diesen Standort kann kein Regal erstellt werden.')
    return
  }
  rackCreateLocation.value = location
  rackCreateBaseName.value = rackCreateSuggestions.value[0] || 'Regal '
  rackCreateCount.value = 1
  rackSlotNameOverrides.value = {}
  showRackCreateModal.value = true
}

async function addSlotForRack(rack: StorageOverviewRack, locationId: string) {
  slotCreateRack.value = rack
  slotCreateLocationId.value = locationId
  slotCreateBaseName.value = getSlotPrefix(rack.name)
  slotCreateCount.value = 1
  showSlotCreateModal.value = true
}

function closeRackCreateModal() {
  showRackCreateModal.value = false
  rackCreateLocation.value = null
  rackCreateBaseName.value = ''
  rackCreateCount.value = 1
  rackSlotNameOverrides.value = {}
}

function closeSlotCreateModal() {
  showSlotCreateModal.value = false
  slotCreateRack.value = null
  slotCreateLocationId.value = ''
  slotCreateBaseName.value = ''
  slotCreateCount.value = 1
}

function closeDeleteConfirmModal() {
  showDeleteConfirmModal.value = false
  deleteTarget.value = null
}

async function createRackFromModal() {
  if (!rackCreateLocation.value?.addressId) return
  const names = rackGeneratedNames.value
  if (names.length === 0) return
  isSubmittingAction.value = true
  try {
    for (const name of names) {
      const initialSlotNameForRack = (rackSlotNameOverrides.value[name] ?? `${getSlotPrefix(name)}1`).trim()
      if (!initialSlotNameForRack) {
        toast.error('Bitte für jedes Regal einen Fachnamen angeben.')
        return
      }
      await createStorageRack({
        department_id: props.departmentId,
        storage_address_id: rackCreateLocation.value.addressId,
        name,
        initial_slot_name: initialSlotNameForRack,
      })
    }
    await load(rackCreateLocation.value.id, false)
    closeRackCreateModal()
    toast.success(`${names.length} Regal${names.length !== 1 ? 'e' : ''} erstellt.`)
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Regal konnte nicht erstellt werden.')
  } finally {
    isSubmittingAction.value = false
  }
}

function updateRackPairItemValue(payload: { index: number; value: string }) {
  const rackName = rackGeneratedNames.value[payload.index]
  if (!rackName) return
  rackSlotNameOverrides.value = {
    ...rackSlotNameOverrides.value,
    [rackName]: payload.value,
  }
}

async function createSlotFromModal() {
  if (!slotCreateRack.value) return
  const names = slotGeneratedNames.value
  if (names.length === 0) return
  isSubmittingAction.value = true
  try {
    for (const name of names) {
      await createStorageSlot({
        rack_id: slotCreateRack.value.id,
        name,
      })
    }
    await load(slotCreateLocationId.value, true, slotCreateRack.value.id)
    closeSlotCreateModal()
    toast.success(`${names.length} Fach${names.length !== 1 ? 'fächer' : ''} erstellt.`)
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Fach konnte nicht erstellt werden.')
  } finally {
    isSubmittingAction.value = false
  }
}

function decrementRackCreateCount() {
  rackCreateCount.value = Math.max(1, rackCreateCount.value - 1)
}

function incrementRackCreateCount() {
  rackCreateCount.value = Math.min(99, rackCreateCount.value + 1)
}

function decrementSlotCreateCount() {
  slotCreateCount.value = Math.max(1, slotCreateCount.value - 1)
}

function incrementSlotCreateCount() {
  slotCreateCount.value = Math.min(99, slotCreateCount.value + 1)
}

function deleteRackFromOverview(rack: StorageOverviewRack, locationId: string) {
  if (!canDeleteRack(rack)) return
  deleteTarget.value = { type: 'rack', locationId, rackId: rack.id, name: rack.name }
  showDeleteConfirmModal.value = true
}

function deleteSlotFromOverview(rack: StorageOverviewRack, slot: StorageOverviewSlot, locationId: string) {
  if (!slot.id || slot.contents.length > 0) return
  deleteTarget.value = { type: 'slot', locationId, rackId: rack.id, slotId: slot.id, name: slot.name }
  showDeleteConfirmModal.value = true
}

async function executeDeleteTarget() {
  if (!deleteTarget.value) return
  isSubmittingAction.value = true
  try {
    if (deleteTarget.value.type === 'rack') {
      await deleteStorageRack(deleteTarget.value.rackId)
      await load(deleteTarget.value.locationId, false)
      toast.success('Regal gelöscht.')
    } else if (deleteTarget.value.slotId) {
      await deleteStorageSlot(deleteTarget.value.slotId)
      await load(deleteTarget.value.locationId, true, deleteTarget.value.rackId)
      toast.success('Fach gelöscht.')
    }
    closeDeleteConfirmModal()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Löschen fehlgeschlagen.')
  } finally {
    isSubmittingAction.value = false
  }
}

async function load(
  preferredLocationId?: string,
  keepRackOpen = false,
  preferredRackId?: string
) {
  if (!props.departmentId) return
  isLoading.value = true
  error.value = ''
  try {
    const [addressResult, storageOverview, containerBatchResult] = await Promise.all([
      getAddresses(props.departmentId, 'storage').catch(() => ({ addresses: [] as Address[] })),
      getStorageOverview(props.departmentId),
      getContainerBatches(props.departmentId).catch(() => [] as ContainerBatch[]),
    ])
    storageAddresses.value = addressResult.addresses || []
    overview.value = storageOverview
    containerBatches.value = containerBatchResult || []

    const targetLocationId = preferredLocationId || primaryStorageAddressId.value
    if (targetLocationId) {
      const mainLocation = locationNodes.value.find((loc) => loc.id === targetLocationId)
      expandedLocations.value = new Set([targetLocationId])
      if (keepRackOpen && preferredRackId) {
        expandedRacks.value = new Set([preferredRackId])
      } else if (mainLocation && mainLocation.racks.length < 10) {
        expandedRacks.value = new Set(mainLocation.racks.map((rack) => rack.id))
      } else {
        expandedRacks.value = new Set()
      }
    } else {
      expandedLocations.value = new Set()
      expandedRacks.value = new Set()
    }
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Fehler beim Laden der Regale'
  } finally {
    isLoading.value = false
  }
}

function canOpenContainerMaterial(item: StorageSlotContent): boolean {
  const containerId = (item.container_batch_id || '').trim()
  if (!containerId) return false
  return containerMaterialByBatchId.value.has(containerId)
}

function openContainerMaterialFromStoredItem(item: StorageSlotContent) {
  const containerId = (item.container_batch_id || '').trim()
  if (!containerId) return
  const materialId = containerMaterialByBatchId.value.get(containerId)
  if (!materialId) return
  const deptId = String(router.currentRoute.value.params.departmentId || props.departmentId || '')
  if (!deptId) return
  const query: Record<string, string> = {
    tab: 'container-content',
    containerBatch: containerId,
  }
  router.push({ path: `/${deptId}/materials/${materialId}`, query })
}

async function openMoveForItem(item: StorageSlotContent, rack: StorageOverviewRack, slot: StorageOverviewSlot) {
  try {
    const material = await getMaterial(item.material_id)
    const batch = material.batches?.find((b: any) => b.id === item.batch_id)
    if (!batch) {
      error.value = 'Charge nicht gefunden'
      return
    }
    moveContext.value = {
      materialId: item.material_id,
      batch,
      sourceAllocationId: item.allocation_id ?? null,
      sourceRackId: rack.id,
      sourceSlotId: slot.id ?? null,
    }
    showMoveModal.value = true
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Fehler beim Laden'
  }
}

function openMaterial(item: StorageSlotContent) {
  const deptId = router.currentRoute.value.params.departmentId
  if (deptId) {
    const query = item.batch_id ? { batch: item.batch_id } : {}
    router.push({ path: `/${deptId}/materials/${item.material_id}`, query })
  }
}

function handleMoveSaved() {
  load()
}

function isContainerStoredItem(item: StorageSlotContent): boolean {
  return !!(item.container_batch_id || item.container_label)
}

function getContainerDisplayLabel(item: StorageSlotContent): string {
  if (item.container_label) return item.container_label
  if (item.container_batch_id) return `Kiste ${item.container_batch_id}`
  return 'Kiste'
}

function getContainerOtherItemsCount(item: StorageSlotContent): number {
  const containerId = item.container_batch_id || null
  if (!containerId || !overview.value) return 0
  const allItems = overview.value.racks
    .flatMap((rack) => rack.slots || [])
    .flatMap((slot) => slot.contents || [])
  const others = allItems.filter((entry) =>
    (entry.container_batch_id || null) === containerId &&
    entry.material_id !== item.material_id
  )
  const uniqueOtherMaterials = new Set(others.map((entry) => entry.material_id))
  return uniqueOtherMaterials.size
}

watch(() => props.departmentId, () => { load() }, { immediate: true })
onMounted(() => { load() })
</script>

<style scoped>
.storage-overview-tab {
  padding: 24px;
  background: #f8fafc;
  min-height: 400px;
}

.storage-loading,
.storage-error,
.storage-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 80px 20px;
  gap: 16px;
  color: #6b7280;
}

.storage-error p,
.storage-empty p {
  margin: 0;
}

.storage-empty .hint {
  font-size: 13px;
  color: #9ca3af;
}

.storage-tree {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.storage-context-hint {
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid #bfdbfe;
  background: #eff6ff;
  color: #1e40af;
  font-size: 13px;
  font-weight: 600;
}

.storage-location {
  background: white;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}

.location-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-bottom: 1px solid #e5e7eb;
  background: #f8fafc;
}

.location-toggle-btn {
  flex: 1;
  width: 100%;
  border: none;
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px;
  text-align: left;
}

.location-name {
  font-weight: 700;
  color: #111827;
}

.location-primary-badge {
  padding: 2px 8px;
  border-radius: 999px;
  background: #ccfbf1;
  color: #0f766e;
  font-size: 11px;
  font-weight: 700;
}

.location-count {
  margin-left: auto;
  font-size: 12px;
  color: #6b7280;
}

.location-actions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.location-racks {
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.storage-rack {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
}

.rack-header {
  display: flex;
  align-items: center;
  gap: 6px;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
  padding: 8px 10px;
}

.rack-toggle-btn {
  flex: 1;
  width: 100%;
  border: none;
  background: transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px;
  text-align: left;
}

.rack-actions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.tree-caret {
  display: inline-flex;
  font-size: 11px;
  color: #6b7280;
  transition: transform 0.15s ease;
}

.tree-caret.expanded {
  transform: rotate(90deg);
}

.rack-name {
  font-weight: 600;
  font-size: 15px;
  color: #111827;
}

.rack-slot-count {
  margin-left: auto;
  font-size: 12px;
  color: #6b7280;
}

.rack-slots {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0;
}

.storage-slot {
  border-right: 1px solid #e5e7eb;
  border-bottom: 1px solid #e5e7eb;
  padding: 16px;
  min-height: 80px;
}

.storage-slot:last-child {
  border-right: none;
}

.slot-header {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: space-between;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  margin-bottom: 10px;
}

.slot-empty {
  font-size: 13px;
  color: #9ca3af;
  font-style: italic;
}

.slot-contents {
  list-style: none;
  margin: 0;
  padding: 0;
}

.content-item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 10px;
  background: #f9fafb;
  border-radius: 6px;
  margin-bottom: 6px;
  font-size: 14px;
}

.content-item--container {
  align-items: flex-start;
}

.content-item:last-child {
  margin-bottom: 0;
}

.content-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.content-label {
  font-weight: 600;
  font-size: 14px;
  color: #111827;
}

.container-label {
  font-weight: 700;
  font-size: 13px;
  color: #374151;
}

.container-article {
  font-size: 14px;
  color: #111827;
}

.container-more {
  font-size: 12px;
  color: #9ca3af;
}

.container-link-btn {
  width: fit-content;
  border: none;
  background: transparent;
  color: #2563eb;
  font-size: 12px;
  font-weight: 600;
  padding: 0;
  cursor: pointer;
}

.container-link-btn:hover {
  color: #1d4ed8;
  text-decoration: underline;
}

.content-material-name {
  font-size: 12px;
  color: #6b7280;
}

.content-name {
  font-weight: 500;
  color: #374151;
  overflow: hidden;
  text-overflow: ellipsis;
}

.content-qty {
  flex-shrink: 0;
  color: #6b7280;
  font-size: 13px;
}

.content-actions {
  display: flex;
  gap: 4px;
}

.storage-loading p {
  color: #6b7280;
  font-size: 15px;
}

.modal-context {
  font-size: 13px;
  color: #6b7280;
  margin: -8px 0 12px 0;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 18px;
}
</style>
