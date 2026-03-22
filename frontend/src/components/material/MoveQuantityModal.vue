<template>
  <Teleport to="body">
    <div class="batch-modal-overlay">
      <div class="batch-modal move-modal">
        <div class="batch-modal-header">
          <h2>Menge verschieben</h2>
          <button class="batch-modal-close" @click="$emit('close')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <div class="batch-modal-body">
          <p class="move-intro">Verschieben Sie eine Menge von einem Lagerplatz zu einem anderen.</p>

          <!-- Quelle (bei Allokationen: Auswahl) -->
          <div v-if="sourceAllocations.length > 1 && !isSourceLocked" class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>Von (Quelle)</label>
              <select v-model="form.from_allocation_id" class="batch-form-input" required>
                <option value="">– wählen –</option>
                <option v-for="a in sourceAllocations" :key="a.id" :value="a.id">
                  {{ formatAllocationSourceInline(a) }} – {{ a.qty }} Stk.
                </option>
              </select>
            </div>
          </div>
          <div v-else-if="sourceAllocations.length > 0" class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>Von (Quelle)</label>
              <div class="batch-readonly-value">
                {{ selectedSourceAllocation ? `${formatAllocationSourceInline(selectedSourceAllocation)} – ${selectedSourceAllocation.qty} Stk.` : '-' }}
              </div>
            </div>
          </div>
          <div v-else class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>Von (Quelle)</label>
              <div class="batch-readonly-value">
                {{ formatDirectLocationSourceInline(batch) }}
              </div>
            </div>
          </div>

          <!-- Zu verschiebende Menge -->
          <div class="batch-form-row">
            <div class="batch-form-group">
              <label>Zu verschiebende Menge</label>
              <input
                v-model.number="form.qty"
                type="number"
                :min="1"
                :max="maxQty"
                class="batch-form-input"
                :class="{ 'is-invalid': submitted && (form.qty < 1 || form.qty > maxQty) }"
              />
              <p v-if="maxQty > 0" class="batch-field-hint">Max. {{ maxQty }} Stk. verfügbar</p>
            </div>
          </div>

          <!-- Ziel (Schrittweise) -->
          <div class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>Ziel-Art</label>
              <div class="move-target-mode">
                <button type="button" class="move-target-btn" :class="{ active: form.target_mode === 'slot' }" @click="setTargetMode('slot')">
                  Gestell/Fach
                </button>
                <button type="button" class="move-target-btn" :class="{ active: form.target_mode === 'kiste' }" @click="setTargetMode('kiste')">
                  Kiste/Tasche
                </button>
              </div>
            </div>
          </div>

          <template v-if="form.target_mode === 'slot'">
            <div class="batch-form-row">
              <div class="batch-form-group full-width">
                <StorageLocationPicker
                  :storage-address-id="form.to_location_id"
                  :storage-address-options="targetLocationOptions"
                  :rack-id="form.to_rack_id"
                  :slot-id="form.to_slot_id"
                  :racks="targetRacks"
                  :slot-list="slotsForTargetRack"
                  :show-storage-address="true"
                  :show-empty-slot-hint="true"
                  empty-slot-hint="Im gewählten Regal gibt es noch kein Fach. Bitte zuerst ein Fach anlegen."
                  storage-address-label="Ziel-Standort"
                  rack-label="Ziel-Gestell"
                  slot-label="Ziel-Fach"
                  storage-address-placeholder="– wählen –"
                  rack-placeholder="– wählen –"
                  slot-placeholder="– wählen –"
                  @update:storageAddressId="form.to_location_id = $event"
                  @storageAddressChange="onLocationChange"
                  @update:rackId="form.to_rack_id = $event"
                  @rackChange="onRackChange"
                  @update:slotId="form.to_slot_id = $event"
                />
              </div>
            </div>
          </template>

          <div v-else class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>Ziel-Kiste/Tasche</label>
              <select v-model="form.to_container_batch_id" class="batch-form-input" required>
                <option value="">– wählen –</option>
                <option v-for="cb in containerBatches" :key="cb.id" :value="cb.id">
                  {{ formatContainerBatchOption(cb) }}
                </option>
              </select>
            </div>
          </div>

          <div v-if="errorMsg" class="batch-error">{{ errorMsg }}</div>
        </div>

        <div class="batch-modal-footer">
          <div class="batch-footer-actions">
            <button class="btn-secondary btn-sm" @click="$emit('close')">Abbrechen</button>
            <button
              class="btn-primary btn-sm"
              :disabled="!canSubmit || isSaving"
              @click="handleSubmit"
            >
              {{ isSaving ? 'Verschieben...' : 'Verschieben' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { moveBatchQuantity, type MaterialBatch, type BatchStorageAllocation } from '@/api/materials'
import { getContainerBatches, type StorageRack } from '@/api/storageLocations'
import { useToast } from '@/composables/useToast'
import { useStorageStructure } from '@/composables/useStorageStructure'
import StorageLocationPicker from '@/components/storage/StorageLocationPicker.vue'

interface Props {
  materialId: string
  departmentId: string
  batch: MaterialBatch
  initialFromAllocationId?: string | null
  sourceRackId?: string | null
  sourceSlotId?: string | null
}

const props = defineProps<Props>()
const emit = defineEmits<{
  close: []
  saved: [result: { id: string; qty: number; rack_id: string | null; slot_id: string | null; allocations?: any[] }]
}>()

const toast = useToast()
const { racks, slotsByRackId, loadRacks: loadStorageRacks, loadSlots } = useStorageStructure(() => props.departmentId)
const containerBatches = ref<import('@/api/storageLocations').ContainerBatch[]>([])
const isSaving = ref(false)
const submitted = ref(false)
const errorMsg = ref('')

const form = reactive({
  from_allocation_id: '' as string,
  target_mode: 'slot' as 'slot' | 'kiste',
  to_location_id: '',
  to_rack_id: '',
  to_slot_id: '',
  to_container_batch_id: '',
  qty: 1
})

const targetLocations = computed(() => {
  const map = new Map<string, string>()
  for (const rack of racks.value) {
    const locationId = rack.storage_address_id || '__unknown__'
    const locationName = rack.storage_address_name?.trim() || 'Ohne Lagerstandort'
    if (!map.has(locationId)) map.set(locationId, locationName)
  }
  return Array.from(map.entries())
    .map(([id, name]) => ({ id, name }))
    .sort((a, b) => a.name.localeCompare(b.name, 'de'))
})

const targetRacks = computed(() => {
  if (!form.to_location_id) return []
  return racks.value.filter((rack) => (rack.storage_address_id || '__unknown__') === form.to_location_id)
})

const targetLocationOptions = computed(() =>
  targetLocations.value.map((loc) => ({ id: loc.id, label: loc.name }))
)

const sourceAllocations = computed((): BatchStorageAllocation[] => {
  const allocs = props.batch?.allocations
  if (allocs && Array.isArray(allocs) && allocs.length > 0) {
    return allocs
  }
  return []
})

const lockedSourceAllocation = computed((): BatchStorageAllocation | null => {
  const allocs = sourceAllocations.value
  if (allocs.length === 0) return null
  const byId = props.initialFromAllocationId
    ? allocs.find((a) => a.id === props.initialFromAllocationId)
    : null
  if (byId) return byId
  const sourceRackId = props.sourceRackId || null
  const sourceSlotId = props.sourceSlotId || null
  if (!sourceRackId) return null
  return allocs.find((a) => a.rack_id === sourceRackId && (a.slot_id || null) === sourceSlotId) || null
})

const isSourceLocked = computed(() => !!lockedSourceAllocation.value)

const selectedSourceAllocation = computed((): BatchStorageAllocation | null => {
  if (sourceAllocations.value.length === 0) return null
  return sourceAllocations.value.find((a) => a.id === form.from_allocation_id)
    || lockedSourceAllocation.value
    || sourceAllocations.value[0]
})

const slotsForTargetRack = computed(() => {
  if (!form.to_rack_id) return []
  return slotsByRackId.value[form.to_rack_id] || []
})

const maxQty = computed(() => {
  if (sourceAllocations.value.length > 0) {
    if (form.from_allocation_id) {
      const a = sourceAllocations.value.find(x => x.id === form.from_allocation_id)
      return a ? a.qty : 0
    }
    return sourceAllocations.value[0]?.qty ?? 0
  }
  return props.batch?.qty ?? 0
})

const canSubmit = computed(() => {
  if (sourceAllocations.value.length > 1 && !form.from_allocation_id) return false
  if (form.target_mode === 'slot') {
    if (!form.to_location_id) return false
    if (!form.to_rack_id) return false
    if (!form.to_slot_id) return false
  } else {
    if (!form.to_container_batch_id) return false
  }
  if (form.qty < 1 || form.qty > maxQty.value) return false
  return true
})

function formatContainerBatchOption(cb: import('@/api/storageLocations').ContainerBatch): string {
  const slotName = (cb.slot?.name || '').trim()
  const rackName = (cb.rack?.name || '').trim()
  const location = slotName ? `${rackName} / ${slotName}` : (rackName || 'Ohne Fach')
  const main = (cb.label || cb.serial_number || cb.material_name || 'Kiste').trim()
  const secondary = cb.material_name && cb.material_name !== main ? ` - ${cb.material_name}` : ''
  return `${location} - ${main}${secondary}`
}

function formatAllocationLocation(a: BatchStorageAllocation): string {
  const fallbackContainer = a.container_batch_id
    ? containerBatches.value.find((entry) => entry.id === a.container_batch_id)
    : null
  const cb = a.container_batch || (fallbackContainer
    ? {
        id: fallbackContainer.id,
        serial_number: fallbackContainer.serial_number || null,
        label: fallbackContainer.label || null,
        material_name: fallbackContainer.material_name || null,
        rack: fallbackContainer.rack || undefined,
        slot: fallbackContainer.slot || null,
      }
    : null)
  const containerLabel = cb?.label || cb?.serial_number
  if (containerLabel) {
    const containerMaterial = cb?.material_name
    const materialSuffix = containerMaterial && containerMaterial !== containerLabel ? ` – ${containerMaterial}` : ''
    const loc = cb?.rack?.name ? (cb?.slot?.name ? `${cb.rack.name} / ${cb.slot.name}` : cb.rack.name) : ''
    return `Kiste ${containerLabel}${materialSuffix}${loc ? ` (${loc})` : ''}`
  }
  const rackName = a.rack?.name || a.rack_id
  const slotName = a.slot?.name || a.slot_id
  return slotName ? `${rackName} / ${slotName}` : (rackName || '-')
}

function getLocationNameForRackId(rackId?: string | null): string {
  if (!rackId) return 'Ohne Lagerstandort'
  const rack = racks.value.find((r) => r.id === rackId)
  return rack?.storage_address_name?.trim() || 'Ohne Lagerstandort'
}

function formatAllocationSourceInline(a: BatchStorageAllocation): string {
  const locationName = getLocationNameForRackId(a.rack?.id || a.rack_id)
  return `${locationName} / ${formatAllocationLocation(a)}`
}

function formatDirectLocation(b: MaterialBatch): string {
  const rackName = b.rack?.name || b.rack_id
  const slotName = b.slot?.name || b.slot_id
  return slotName ? `${rackName} / ${slotName}` : (rackName || '-')
}

function formatDirectLocationSourceInline(b: MaterialBatch): string {
  const locationName = getLocationNameForRackId(b.rack?.id || b.rack_id)
  return `${locationName} / ${formatDirectLocation(b)}`
}

function onRackChange() {
  form.to_slot_id = ''
}

function onLocationChange() {
  form.to_rack_id = ''
  form.to_slot_id = ''
}

function setTargetMode(mode: 'slot' | 'kiste') {
  form.target_mode = mode
  form.to_container_batch_id = ''
  form.to_location_id = ''
  form.to_rack_id = ''
  form.to_slot_id = ''
  if (mode === 'slot' && targetLocations.value.length > 0) {
    form.to_location_id = targetLocations.value[0].id
  }
}

async function loadRacks() {
  try {
    racks.value = await loadStorageRacks()
    containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
    const sourceRackId = props.sourceRackId || null
    const sourceRack = sourceRackId ? racks.value.find((r) => r.id === sourceRackId) : null
    const sourceLocationId = sourceRack ? (sourceRack.storage_address_id || '__unknown__') : ''
    if (sourceLocationId) {
      form.to_location_id = sourceLocationId
    } else if (!form.to_location_id && targetLocations.value.length > 0) {
      form.to_location_id = targetLocations.value[0].id
    }
  } catch (e) {
    console.error('Racks laden fehlgeschlagen:', e)
  }
}

async function loadSlotsForRack(rackId: string) {
  if (!rackId) return
  try {
    await loadSlots(rackId)
  } catch (e) {
    console.error('Slots laden fehlgeschlagen:', e)
  }
}

watch(
  () => form.to_rack_id,
  (rackId) => {
    if (rackId && !slotsByRackId.value[rackId]) {
      loadSlotsForRack(rackId)
    }
  }
)

watch(
  () => [props.batch, props.departmentId],
  () => {
    loadRacks()
    const allocs = sourceAllocations.value
    const preferredAllocation = lockedSourceAllocation.value

    if (preferredAllocation) {
      form.from_allocation_id = preferredAllocation.id
    } else if (allocs.length === 1) {
      form.from_allocation_id = allocs[0].id
    } else if (allocs.length > 1) {
      form.from_allocation_id = ''
    } else {
      form.from_allocation_id = ''
    }
    form.qty = Math.min(form.qty || 1, maxQty.value || 1)
  },
  { immediate: true }
)

watch(
  () => form.to_location_id,
  (locationId) => {
    if (!locationId) return
    const stillValid = targetRacks.value.some((rack) => rack.id === form.to_rack_id)
    if (!stillValid) {
      form.to_rack_id = ''
      form.to_slot_id = ''
    }
  }
)

async function handleSubmit() {
  submitted.value = true
  errorMsg.value = ''
  if (!canSubmit.value) return

  isSaving.value = true
  try {
    const payload: any = {
      qty: form.qty
    }
    if (form.target_mode === 'kiste') {
      payload.to_container_batch_id = form.to_container_batch_id
    } else {
      payload.to_rack_id = form.to_rack_id
      payload.to_slot_id = form.to_slot_id
    }
    if (sourceAllocations.value.length > 0 && form.from_allocation_id) {
      payload.from_allocation_id = form.from_allocation_id
    }

    const result = await moveBatchQuantity(props.materialId, props.batch.id, payload)
    toast.success('Menge erfolgreich verschoben')
    emit('saved', result)
    emit('close')
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.error || 'Fehler beim Verschieben'
    toast.error(errorMsg.value)
  } finally {
    isSaving.value = false
  }
}
</script>

<style scoped>
.batch-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.15s ease;
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.batch-modal {
  background: white;
  border-radius: 12px;
  width: 520px;
  max-width: 95vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  animation: slideUp 0.2s ease;
}
@keyframes slideUp {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
.batch-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}
.batch-modal-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}
.batch-modal-close {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.15s;
}
.batch-modal-close:hover {
  background: #f3f4f6;
  color: #374151;
}
.batch-modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}
.batch-form-row {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
}
.batch-form-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.batch-form-group.full-width {
  flex: 1 1 100%;
}
.batch-form-group label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}
.batch-form-input {
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
}
.batch-form-input.is-invalid {
  border-color: #ef4444;
}
.batch-readonly-value {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  padding: 10px 0;
}
.batch-field-hint {
  font-size: 12px;
  color: #6b7280;
  margin-top: 6px;
}
.batch-error {
  margin-top: 12px;
  padding: 12px;
  background: #fef2f2;
  color: #dc2626;
  border-radius: 8px;
  font-size: 14px;
}
.batch-modal-footer {
  display: flex;
  justify-content: flex-end;
  padding: 20px 24px;
  border-top: 1px solid #e5e7eb;
}
.batch-footer-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: nowrap;
}
.move-modal {
  max-width: 480px;
}
.move-intro {
  margin-bottom: 16px;
  color: #6b7280;
  font-size: 14px;
}

.move-target-mode {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 4px;
  width: 100%;
}

.move-target-btn {
  flex: 1;
  border: none;
  background: transparent;
  color: #6b7280;
  font-size: 13px;
  font-weight: 600;
  padding: 6px 10px;
  border-radius: 6px;
  cursor: pointer;
}

.move-target-btn.active {
  background: #fff;
  color: #111827;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}
</style>
