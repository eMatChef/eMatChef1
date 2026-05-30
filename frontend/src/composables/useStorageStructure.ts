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
import { sortByNaturalName } from '@/utils/naturalSort'

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
    const id = String(rackId ?? '').trim()
    return slotsByRackId.value[id] || []
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
    const id = String(rackId ?? '').trim()
    if (!id) return []
    // Wichtig: [] ist in JS truthy — leere Arrays dürfen keinen „Cache-Treffer“ auslösen,
    // sonst werden Fächer nie nachgeladen (z. B. nach Anlegen des ersten Fachs).
    const cached = slotsByRackId.value[id]
    if (!force && cached !== undefined && cached.length > 0) {
      return cached
    }
    loadingRackSlots.value = new Set([...loadingRackSlots.value, id])
    try {
      const slots = await getStorageSlots(id)
      slotsByRackId.value = { ...slotsByRackId.value, [id]: slots }
      return slots
    } finally {
      const next = new Set(loadingRackSlots.value)
      next.delete(id)
      loadingRackSlots.value = next
    }
  }

  /**
   * Lädt Fächer; wenn das Gestell noch keines hat, wird ein Standard-Fach angelegt (wie Material-Wizard).
   * Verhindert leere Fach-Dropdowns nach Gestell-Auswahl.
   */
  async function loadSlotsEnsuringDefault(
    rackId: string,
    defaultSlotName = 'Fach 1'
  ): Promise<StorageSlot[]> {
    const id = String(rackId ?? '').trim()
    if (!id) return []
    let slots = await loadSlots(id)
    if (slots.length > 0) return slots
    try {
      await createStorageSlot({
        rack_id: id,
        name: defaultSlotName,
      })
    } catch {
      // z. B. Rechte — dann bleibt die Liste leer
    }
    return await loadSlots(id, true)
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
      [input.rackId]: sortByNaturalName([...existing, created], (slot) => slot.name),
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
    loadSlotsEnsuringDefault,
    ensureSlotsForRacks,
    createRack,
    createSlot,
    removeRack,
    removeSlot,
  }
}
