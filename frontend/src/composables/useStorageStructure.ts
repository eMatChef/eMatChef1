import { computed, ref } from 'vue'
import {
  createStorageRack,
  createStorageSlot,
  deleteStorageRack,
  deleteStorageSlot,
  getStorageRacks,
  getStorageSlots,
  type StorageRack,
  type StorageSlot,
} from '@/api/storageLocations'

type CreateRackInput = {
  departmentId: string
  storageAddressId: string
  name: string
  initialSlotName?: string
}

type CreateSlotInput = {
  rackId: string
  name: string
}

export function useStorageStructure(departmentId: () => string) {
  const racks = ref<StorageRack[]>([])
  const slotsByRackId = ref<Record<string, StorageSlot[]>>({})
  const isLoadingRacks = ref(false)
  const loadingRackSlots = ref<Set<string>>(new Set())
  const error = ref('')

  const storageLocations = computed(() => {
    const map = new Map<string, string>()
    for (const rack of racks.value) {
      const key = rack.storage_address_id || '__unknown__'
      const label = rack.storage_address_name?.trim() || 'Ohne Lagerstandort'
      if (!map.has(key)) map.set(key, label)
    }
    return Array.from(map.entries())
      .map(([id, name]) => ({ id, name }))
      .sort((a, b) => a.name.localeCompare(b.name, 'de'))
  })

  function getSlots(rackId: string): StorageSlot[] {
    return slotsByRackId.value[rackId] || []
  }

  async function loadRacks(storageAddressId?: string): Promise<StorageRack[]> {
    const depId = departmentId()
    if (!depId) {
      racks.value = []
      slotsByRackId.value = {}
      return []
    }
    isLoadingRacks.value = true
    error.value = ''
    try {
      const data = await getStorageRacks(depId, storageAddressId)
      racks.value = data
      if (!storageAddressId) {
        // For full reloads, reset slot cache to avoid stale data.
        slotsByRackId.value = {}
      }
      return data
    } catch (err: any) {
      error.value = err?.response?.data?.error || 'Regale konnten nicht geladen werden.'
      throw err
    } finally {
      isLoadingRacks.value = false
    }
  }

  async function loadSlots(rackId: string, force = false): Promise<StorageSlot[]> {
    if (!rackId) return []
    if (!force && slotsByRackId.value[rackId]) {
      return slotsByRackId.value[rackId]
    }
    loadingRackSlots.value = new Set([...loadingRackSlots.value, rackId])
    try {
      const slots = await getStorageSlots(rackId)
      slotsByRackId.value = { ...slotsByRackId.value, [rackId]: slots }
      return slots
    } finally {
      const next = new Set(loadingRackSlots.value)
      next.delete(rackId)
      loadingRackSlots.value = next
    }
  }

  async function ensureSlotsForRacks(rackIds: string[]): Promise<void> {
    if (!rackIds.length) return
    await Promise.all(rackIds.map((rackId) => loadSlots(rackId)))
  }

  async function createRack(input: CreateRackInput): Promise<StorageRack> {
    const created = await createStorageRack({
      department_id: input.departmentId,
      storage_address_id: input.storageAddressId,
      name: input.name,
      initial_slot_name: input.initialSlotName,
    })
    racks.value = [...racks.value, created]
    return created
  }

  async function createSlot(input: CreateSlotInput): Promise<StorageSlot> {
    const created = await createStorageSlot({
      rack_id: input.rackId,
      name: input.name,
    })
    const existing = slotsByRackId.value[input.rackId] || []
    slotsByRackId.value = {
      ...slotsByRackId.value,
      [input.rackId]: [...existing, created],
    }
    return created
  }

  async function removeRack(rackId: string): Promise<void> {
    if (!rackId) return
    await deleteStorageRack(rackId)
    racks.value = racks.value.filter((rack) => rack.id !== rackId)
    const next = { ...slotsByRackId.value }
    delete next[rackId]
    slotsByRackId.value = next
  }

  async function removeSlot(rackId: string, slotId: string): Promise<void> {
    if (!slotId) return
    await deleteStorageSlot(slotId)
    slotsByRackId.value = {
      ...slotsByRackId.value,
      [rackId]: (slotsByRackId.value[rackId] || []).filter((slot) => slot.id !== slotId),
    }
  }

  return {
    racks,
    slotsByRackId,
    storageLocations,
    isLoadingRacks,
    loadingRackSlots,
    error,
    getSlots,
    loadRacks,
    loadSlots,
    ensureSlotsForRacks,
    createRack,
    createSlot,
    removeRack,
    removeSlot,
  }
}
