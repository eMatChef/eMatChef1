<template>
  <Teleport to="body">
    <div class="batch-modal-overlay">
      <div class="batch-modal">
        <!-- Header -->
        <div class="batch-modal-header">
          <h2>{{ isEditMode ? 'Charge bearbeiten' : 'Charge hinzufügen' }}</h2>
          <button class="batch-modal-close" @click="$emit('close')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <!-- Content -->
        <div class="batch-modal-body">
          <!-- Kaufdatum -->
          <div class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>Kaufdatum <span class="required" v-if="!isEditMode">*</span></label>
              <input 
                v-if="!isEditMode"
                v-model="form.acquired_on" 
                type="date" 
                class="batch-form-input"
                :class="{ 'is-invalid': submitted && !form.acquired_on }"
                required
              />
              <div v-else class="batch-readonly-value">
                {{ formatDate(form.acquired_on) }}
                <span class="batch-readonly-hint">Kaufdatum kann nicht geändert werden (in ID eingebettet)</span>
              </div>
            </div>
          </div>

          <!-- Menge + Preis -->
          <div class="batch-form-row">
            <div class="batch-form-group">
              <label>Menge <span class="required" v-if="!isEditMode">*</span></label>
              <input 
                v-model.number="form.qty" 
                type="number" 
                min="1" 
                class="batch-form-input"
                :class="{ 'is-invalid': submitted && form.qty < 1 }"
                placeholder="1"
              />
            </div>
            <div class="batch-form-group">
              <label>Stückpreis (CHF)</label>
              <div class="batch-price-input">
                <span class="batch-currency">Fr.</span>
                <input 
                  v-model="form.unit_price" 
                  type="text" 
                  class="batch-form-input"
                  placeholder="0.00"
                />
              </div>
            </div>
          </div>

          <!-- Seriennummer(n) bei serialisierten Materialien -->
          <template v-if="isSerialized">
            <!-- qty = 1: Einzelne Seriennummer -->
            <div v-if="form.qty <= 1" class="batch-form-row">
              <div class="batch-form-group full-width">
                <label>Seriennummer</label>
                <input 
                  v-model="form.serial_number" 
                  type="text" 
                  class="batch-form-input"
                  placeholder="Seriennummer eingeben..."
                />
              </div>
            </div>
            <div v-if="form.qty <= 1" class="batch-form-row">
              <div class="batch-form-group full-width">
                <label>Bezeichnung (optional)</label>
                <input 
                  v-model="form.label" 
                  type="text" 
                  class="batch-form-input"
                  placeholder="z.B. Kochbox, Kochkiste Falk..."
                />
                <p class="batch-field-hint">Anzeigename in der Lagerübersicht – kann jederzeit geändert werden.</p>
              </div>
            </div>
            <!-- qty > 1: Mehrere Seriennummern mit Vorschlägen -->
            <template v-else>
              <div class="batch-form-row">
                <div class="batch-form-group">
                  <label>Prefix (QR-Tag)</label>
                  <input v-model="form.serial_prefix" type="text" class="batch-form-input" placeholder="z.B. KISTE-" />
                </div>
                <div class="batch-form-group">
                  <label>Startnummer</label>
                  <input v-model.number="form.start_number" type="number" min="1" class="batch-form-input" />
                </div>
                <div class="batch-form-group">
                  <label>Stellen (Pad)</label>
                  <input v-model.number="form.pad_length" type="number" min="1" max="6" class="batch-form-input" placeholder="3" />
                </div>
              </div>
              <p class="batch-field-hint">Seriennummern erscheinen auf dem QR-Tag. Änderung erfordert neue Tags.</p>
              <div class="batch-form-row">
                <div class="batch-form-group full-width">
                  <div class="serial-entries-header">
                    <label>Seriennummern ({{ serialEntries.length }})</label>
                  </div>
                  <div class="serial-entries-table-wrap">
                    <table class="serial-entries-table">
                      <thead>
                        <tr>
                          <th>Seriennummer (QR-Tag)</th>
                          <th>Label (optional)</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(entry, i) in serialEntries" :key="i">
                          <td>
                            <input
                              v-model="entry.serial_number"
                              type="text"
                              class="batch-form-input form-input--sm"
                              placeholder="z.B. KISTE-001"
                            />
                          </td>
                          <td>
                            <input
                              v-model="entry.label"
                              type="text"
                              class="batch-form-input form-input--sm"
                              placeholder="z.B. Kochkiste Falk"
                            />
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <p v-if="serialDuplicateHint" class="batch-field-hint is-invalid">{{ serialDuplicateHint }}</p>
                </div>
              </div>
              <div class="batch-form-row">
                <label class="batch-toggle-label">
                  <input type="checkbox" v-model="form.create_slot_per_serial" class="batch-toggle-input" />
                  <span>Kisten als Lagerplätze anlegen</span>
                </label>
              </div>
              <p class="batch-field-hint">Nicht alle Kisten müssen Lagerplätze sein – z.B. wenn sie nur zum Packen für Aktivitäten genutzt werden.</p>
            </template>
          </template>

          <!-- Auf mehrere Lagerplätze aufteilen (nur bei Bulk) -->
          <div v-if="!isSerialized" class="batch-form-row">
            <label class="batch-toggle-label">
              <input type="checkbox" v-model="form.split_allocations" class="batch-toggle-input" />
              <span>Auf mehrere Lagerplätze aufteilen</span>
            </label>
          </div>

          <!-- Allokations-Tabelle -->
          <div v-if="!isSerialized && form.split_allocations" class="batch-form-row">
            <div class="batch-form-group full-width">
              <div class="allocations-header">
                <label>Lagerplätze (Summe = {{ form.qty }} Stk.)</label>
                <button type="button" class="add-serial-btn" @click="addAllocationRow">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                  </svg>
                  Zeile hinzufügen
                </button>
              </div>
              <div class="allocations-table-wrap">
                <table class="allocations-table">
                  <thead>
                    <tr>
                      <th>Menge</th>
                      <th>Art</th>
                      <th>Lagerort</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in allocationRows" :key="row.id">
                      <td>
                        <input
                          v-model.number="row.qty"
                          type="number"
                          min="1"
                          class="batch-form-input form-input--sm"
                          placeholder="0"
                        />
                      </td>
                      <td>
                        <select v-model="row.mode" class="batch-form-input form-select--sm" @change="row.rack_id = ''; row.slot_id = ''; row.container_batch_id = ''">
                          <option value="slot">Slot</option>
                          <option value="kiste">Kiste</option>
                        </select>
                      </td>
                      <td>
                        <template v-if="row.mode === 'slot'">
                          <StorageLocationPicker
                            variant="compact"
                            :show-storage-address="true"
                            :storage-address-id="row.storage_address_id"
                            :storage-address-options="storageAddressOptions"
                            :rack-id="row.rack_id"
                            :slot-id="row.slot_id"
                            :racks="getAllocationRacks(row)"
                            :slots="row.rack_id ? (slotsByRackId[row.rack_id] || []) : []"
                            storage-address-label="Standort"
                            rack-label="Gestell"
                            slot-label="Fach"
                            storage-address-placeholder="– Standort –"
                            rack-placeholder="– Gestell –"
                            slot-placeholder="– optional –"
                            @update:storageAddressId="row.storage_address_id = $event"
                            @storageAddressChange="onAllocationStorageAddressChange(row)"
                            @update:rackId="row.rack_id = $event"
                            @rackChange="row.slot_id = ''; loadSlotsForAllocationRack(row.rack_id)"
                            @update:slotId="row.slot_id = $event"
                          />
                        </template>
                        <select
                          v-else
                          v-model="row.container_batch_id"
                          class="batch-form-input form-select--sm"
                        >
                          <option value="">– Kiste wählen –</option>
                                <option v-for="cb in containerBatches" :key="cb.id" :value="cb.id">
                                  {{ (cb.label || cb.serial_number || cb.material_name) }}{{ cb.material_name && cb.material_name !== (cb.label || cb.serial_number) ? ` – ${cb.material_name}` : '' }}{{ cb.rack ? ` (${cb.rack.name}${cb.slot ? ' / ' + cb.slot.name : ''})` : '' }}
                                </option>
                        </select>
                      </td>
                      <td>
                        <button type="button" class="remove-row-btn" @click="removeAllocationRow(row.id)" title="Entfernen">×</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-if="allocationRows.length > 0 && !allocationSumValid" class="batch-field-hint is-invalid">
                Summe muss {{ form.qty }} Stk. ergeben (aktuell: {{ allocationSum }})
              </p>
            </div>
          </div>

          <!-- Einzelner Lagerplatz (Bulk ohne Split-Allokationen, oder Serialisiert) -->
          <div v-if="isSerialized || !form.split_allocations" class="batch-form-row">
            <div class="batch-form-group full-width">
              <StorageLocationPicker
                :show-storage-address="true"
                :storage-address-id="form.storage_address_id"
                :storage-address-options="storageAddressOptions"
                :rack-id="form.rack_id"
                :slot-id="form.slot_id"
                :racks="filteredRacks"
                :slots="slots"
                storage-address-label="Standort"
                rack-label="Gestell"
                slot-label="Fach"
                storage-address-placeholder="Standort auswaehlen..."
                rack-placeholder="Gestell auswaehlen..."
                slot-placeholder="Fach auswaehlen..."
                @update:storageAddressId="form.storage_address_id = $event"
                @storageAddressChange="onStorageAddressChange"
                @update:rackId="form.rack_id = $event"
                @rackChange="onRackChange"
                @update:slotId="form.slot_id = $event"
                @slotChange="onSlotChange"
              />
            </div>
          </div>

          <!-- Lieferant (Autocomplete) -->
          <div class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>Gekauft von (Lieferant)</label>
              <div class="batch-autocomplete-wrapper">
                <input 
                  v-model="supplierSearch" 
                  type="text" 
                  class="batch-form-input"
                  placeholder="Lieferant suchen..."
                  @input="filterSuppliers"
                  @focus="showSupplierDropdown = true"
                  @blur="hideSupplierDropdownDelayed"
                />
                <button type="button" class="batch-add-inline-btn" @click="openAddSupplierModal" title="Neuen Lieferanten hinzufügen">+</button>
                <div v-if="showSupplierDropdown && supplierSearch.length >= 1" class="batch-autocomplete-dropdown">
                  <div 
                    v-for="addr in filteredSuppliers" 
                    :key="addr.id"
                    class="batch-autocomplete-item"
                    @mousedown="selectSupplier(addr)"
                  >
                    <span class="batch-ac-name">{{ addr.name || addr.company }}</span>
                    <span v-if="addr.city" class="batch-ac-city">{{ addr.city }}</span>
                  </div>
                  <!-- Keine Ergebnisse → Neu erstellen -->
                  <div 
                    v-if="filteredSuppliers.length === 0" 
                    class="batch-autocomplete-item batch-ac-create"
                    @mousedown="openAddSupplierModal"
                  >
                    <span class="batch-ac-name">+ "{{ supplierSearch }}" als Lieferant anlegen</span>
                  </div>
                </div>
              </div>
              <p v-if="selectedSupplier" class="batch-selected-supplier">
                ✓ {{ selectedSupplier.name || selectedSupplier.company }}
                <button type="button" class="batch-clear-btn" @click="clearSupplier">×</button>
              </p>
            </div>
          </div>

          <!-- Notizen -->
          <div class="batch-form-row">
            <div class="batch-form-group full-width">
              <label>Notiz</label>
              <textarea 
                v-model="form.notes" 
                class="batch-form-textarea"
                rows="2"
                placeholder="Optionale Notiz zur Charge..."
              ></textarea>
            </div>
          </div>

          <!-- Fehlermeldung -->
          <div v-if="errorMsg" class="batch-error">
            {{ errorMsg }}
          </div>
        </div>

        <!-- Footer -->
        <div class="batch-modal-footer">
          <div v-if="missingFields.length > 0" class="batch-missing">
            <span class="batch-missing-icon">⚠️</span>
            <span>{{ missingFields[0] }}</span>
          </div>
          <div class="batch-footer-actions">
            <button class="btn-secondary btn-sm" @click="$emit('close')">Abbrechen</button>
            <button 
              class="btn-primary btn-sm" 
              @click="handleSubmit"
              :disabled="!canSubmit || isSaving"
            >
              {{ isSaving ? 'Speichern...' : (isEditMode ? 'Speichern' : 'Hinzufügen') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Adress-Modal für neuen Lieferanten -->
    <AddressModal
      v-if="showAddressModal"
      :department-id="departmentId"
      default-type="supplier"
      :default-name="supplierSearch"
      @close="showAddressModal = false"
      @saved="handleAddressSaved"
    />
  </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useToast } from '@/composables/useToast'
import { addBatch, updateBatch, type MaterialBatch, type AddBatchRequest, type UpdateBatchRequest, type AddBatchMultiResponse } from '@/api/materials'
import { getAddresses, type Address } from '@/api/addresses'
import { getStorageRacks, getStorageSlots, getContainerBatches, getStorageOverview, type StorageRack, type StorageSlot, type StorageOverviewResponse } from '@/api/storageLocations'
import AddressModal from '@/components/AddressModal.vue'
import StorageLocationPicker from '@/components/storage/StorageLocationPicker.vue'

interface Props {
  materialId: string
  departmentId: string
  batch?: MaterialBatch | null // null = Add-Modus, batch = Edit-Modus
  initialContainerBatchId?: string
  isSerialized?: boolean
  materialName?: string
  existingBatches?: MaterialBatch[]
}

const props = withDefaults(defineProps<Props>(), {
  batch: null,
  initialContainerBatchId: '',
  isSerialized: false,
  materialName: '',
  existingBatches: () => []
})

const emit = defineEmits<{
  close: []
  saved: [result: MaterialBatch | AddBatchMultiResponse]
}>()

const toast = useToast()
const isEditMode = computed(() => !!props.batch)
const isSaving = ref(false)
const submitted = ref(false)
const errorMsg = ref('')

// Lieferant Autocomplete
const allSuppliers = ref<Address[]>([])
const filteredSuppliers = ref<Address[]>([])
const supplierSearch = ref('')
const showSupplierDropdown = ref(false)
const selectedSupplier = ref<Address | null>(null)
const storageAddresses = ref<Address[]>([])
const racks = ref<StorageRack[]>([])
const slots = ref<StorageSlot[]>([])
const rackPreviewTitles = ref<Record<string, string>>({})
const slotPreviewTitles = ref<Record<string, string>>({})
const storageOverviewCache = ref<StorageOverviewResponse | null>(null)

const form = reactive({
  acquired_on: '',
  qty: 1,
  unit_price: '',
  serial_number: '',
  label: '',
  serial_prefix: 'KISTE-',
  start_number: 1,
  pad_length: 3,
  create_slot_per_serial: false,
  storage_address_id: '',
  rack_id: '',
  slot_id: '',
  supplier_id: '',
  notes: '',
  split_allocations: false
})

const filteredRacks = computed(() => {
  if (!form.storage_address_id) return racks.value
  return racks.value.filter((rack) => rack.storage_address_id === form.storage_address_id)
})

const storageAddressOptions = computed(() =>
  storageAddresses.value.map((addr) => ({
    id: addr.id,
    label: addr.name || addr.street_line || addr.full_address || addr.id,
  }))
)

// Seriennummern bei qty > 1 (serialisiert)
interface SerialEntry {
  serial_number: string
  label: string
}
const serialEntries = ref<SerialEntry[]>([])

function buildSerialEntries(): SerialEntry[] {
  const qty = Math.max(1, form.qty)
  const prefix = (form.serial_prefix || '').trim() || 'KISTE-'
  const start = Math.max(1, form.start_number)
  const pad = Math.max(1, Math.min(6, form.pad_length || 3))
  const entries: SerialEntry[] = []
  for (let i = 0; i < qty; i++) {
    const num = String(start + i)
    const padded = num.padStart(pad, '0')
    entries.push({ serial_number: prefix + padded, label: '' })
  }
  return entries
}

function regenerateSerialEntries() {
  if (props.isSerialized && form.qty > 1) {
    serialEntries.value = buildSerialEntries()
  }
}

const suggestedStartNumber = computed(() => {
  const existing = (props.existingBatches || [])
    .filter(b => b.serial_number)
    .map(b => {
      const sn = b.serial_number || ''
      const match = sn.match(/(\d+)$/)
      return match ? parseInt(match[1], 10) : 0
    })
  const max = existing.length ? Math.max(...existing) : 0
  return max + 1
})

const serialDuplicateHint = computed(() => {
  if (!props.isSerialized || form.qty <= 1) return ''
  const existing = new Set((props.existingBatches || []).map(b => (b.serial_number || '').trim()).filter(Boolean))
  const duplicates = serialEntries.value
    .map(e => e.serial_number.trim())
    .filter(sn => sn && existing.has(sn))
  if (duplicates.length > 0) {
    return `Seriennummer(n) bereits vergeben: ${duplicates.slice(0, 3).join(', ')}${duplicates.length > 3 ? '…' : ''}`
  }
  const seen = new Set<string>()
  for (const e of serialEntries.value) {
    const sn = e.serial_number.trim()
    if (sn && seen.has(sn)) return 'Doppelte Seriennummern in der Liste'
    if (sn) seen.add(sn)
  }
  return ''
})

// Allokationen für mehrere Lagerplätze
interface AllocationRow {
  id: number
  mode: 'slot' | 'kiste'
  storage_address_id: string
  rack_id: string
  slot_id: string
  container_batch_id: string
  qty: number
}
let allocationIdCounter = 0
const allocationRows = ref<AllocationRow[]>([])
const slotsByRackId = ref<Record<string, StorageSlot[]>>({})
const containerBatches = ref<import('@/api/storageLocations').ContainerBatch[]>([])
const prefilledContainerMode = ref(false)

function addAllocationRow() {
  allocationRows.value.push({
    id: ++allocationIdCounter,
    mode: 'slot',
    storage_address_id: '',
    rack_id: '',
    slot_id: '',
    container_batch_id: '',
    qty: 0
  })
}

function removeAllocationRow(id: number) {
  allocationRows.value = allocationRows.value.filter((r) => r.id !== id)
}

async function loadSlotsForAllocationRack(rackId: string) {
  if (!rackId) return
  if (slotsByRackId.value[rackId]) return
  slotsByRackId.value[rackId] = await getStorageSlots(rackId).catch(() => [])
  slotsByRackId.value = { ...slotsByRackId.value }
  await prefetchSlotPreviewsForRack(rackId)
}

function getAllocationRacks(row: AllocationRow): StorageRack[] {
  if (!row.storage_address_id) return racks.value
  return racks.value.filter((rack) => rack.storage_address_id === row.storage_address_id)
}

function onAllocationStorageAddressChange(row: AllocationRow) {
  row.rack_id = ''
  row.slot_id = ''
}

function buildContentPreviewTitle(items: Array<{ material_name: string; qty: number }>): string {
  if (!items.length) return 'Leer'
  const lines = items.slice(0, 5).map((item) => `${item.material_name} (${item.qty})`)
  if (items.length > 5) lines.push(`+${items.length - 5} weitere`)
  return lines.join('\n')
}

async function prefetchStorageOverview() {
  if (storageOverviewCache.value) return
  storageOverviewCache.value = await getStorageOverview(props.departmentId).catch(() => null)
}

async function prefetchRackPreview(rackId: string) {
  if (!rackId || rackPreviewTitles.value[rackId]) return
  await prefetchStorageOverview()
  const rack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  const items = (rack?.slots || []).flatMap((slot) => slot.contents || []).map((c) => ({
    material_name: c.material_name || 'Material',
    qty: Number(c.qty || 0),
  }))
  rackPreviewTitles.value = {
    ...rackPreviewTitles.value,
    [rackId]: buildContentPreviewTitle(items),
  }
}

async function prefetchSlotPreview(rackId: string, slotId: string) {
  if (!rackId || !slotId) return
  const key = `${rackId}:${slotId}`
  if (slotPreviewTitles.value[key]) return
  await prefetchStorageOverview()
  const rack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  const slot = rack?.slots?.find((s) => String(s.id) === String(slotId))
  const items = (slot?.contents || []).map((c) => ({
    material_name: c.material_name || 'Material',
    qty: Number(c.qty || 0),
  }))
  slotPreviewTitles.value = {
    ...slotPreviewTitles.value,
    [key]: buildContentPreviewTitle(items),
  }
}

async function prefetchSlotPreviewsForRack(rackId: string) {
  if (!rackId) return
  await prefetchStorageOverview()
  const rack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  if (!rack?.slots?.length) return
  const next = { ...slotPreviewTitles.value }
  for (const slot of rack.slots) {
    const key = `${rackId}:${String(slot.id)}`
    if (next[key]) continue
    const items = (slot.contents || []).map((c) => ({
      material_name: c.material_name || 'Material',
      qty: Number(c.qty || 0),
    }))
    next[key] = buildContentPreviewTitle(items)
  }
  slotPreviewTitles.value = next
}

function getRackPreviewTitle(rackId: string): string {
  if (!rackId) return ''
  return rackPreviewTitles.value[rackId] || 'Inhalt wird geladen...'
}

function getSlotPreviewTitle(rackId: string, slotId: string): string {
  if (!rackId || !slotId) return ''
  return slotPreviewTitles.value[`${rackId}:${slotId}`] || 'Inhalt wird geladen...'
}

async function prefetchVisibleRackPreviews(list: StorageRack[]) {
  const sample = list.slice(0, 20)
  await Promise.all(sample.map((rack) => prefetchRackPreview(rack.id)))
}

const allocationSum = computed(() =>
  allocationRows.value.reduce((sum, r) => sum + (r.qty || 0), 0)
)
const allocationSumValid = computed(() =>
  form.qty > 0 && allocationSum.value === form.qty
)

// Form befüllen
onMounted(async () => {
  // Lieferanten laden
  try {
    const result = await getAddresses(props.departmentId, 'supplier')
    allSuppliers.value = result.addresses || []
  } catch (err) {
    console.error('Fehler beim Laden der Lieferanten:', err)
  }

  try {
    const storageResult = await getAddresses(props.departmentId, 'storage')
    storageAddresses.value = storageResult.addresses || []
  } catch (err) {
    console.error('Fehler beim Laden der Lagerstandorte:', err)
    storageAddresses.value = []
  }

  try {
    racks.value = await getStorageRacks(props.departmentId)
    containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
    await prefetchVisibleRackPreviews(racks.value)
  } catch (err) {
    console.error('Fehler beim Laden der Gestelle:', err)
    racks.value = []
  }

  if (props.batch) {
    // Edit-Modus: Werte aus bestehendem Batch übernehmen
    form.acquired_on = props.batch.acquired_on || ''
    form.qty = props.batch.qty
    form.unit_price = props.batch.unit_price || ''
    form.serial_number = props.batch.serial_number || ''
    form.label = (props.batch as any).label || ''
    form.rack_id = props.batch.rack_id || ''
    form.slot_id = props.batch.slot_id || ''
    form.notes = props.batch.notes || ''
    if (form.rack_id) {
      const selectedRack = racks.value.find((rack) => rack.id === form.rack_id)
      form.storage_address_id = selectedRack?.storage_address_id || ''
    }
    // Lieferant aus Batch vorbelegen (wenn vorhanden)
    if ((props.batch as any).supplier_id) {
      form.supplier_id = (props.batch as any).supplier_id
      const match = allSuppliers.value.find(s => s.id === form.supplier_id)
      if (match) {
        selectedSupplier.value = match
        supplierSearch.value = match.name || match.company || ''
      }
    }
  } else if (props.isSerialized && props.existingBatches?.length) {
    form.start_number = suggestedStartNumber.value
    regenerateSerialEntries()
  } else if (!props.isSerialized && props.initialContainerBatchId) {
    form.split_allocations = true
    const initialQty = Math.max(1, form.qty || 1)
    allocationRows.value = [{
      id: ++allocationIdCounter,
      mode: 'kiste',
      storage_address_id: '',
      rack_id: '',
      slot_id: '',
      container_batch_id: props.initialContainerBatchId,
      qty: initialQty,
    }]
    prefilledContainerMode.value = true
  }

  if (form.rack_id) {
    await loadSlots(form.rack_id)
  }
})

watch(
  () => [form.qty, form.serial_prefix, form.start_number, form.pad_length] as const,
  () => regenerateSerialEntries(),
  { immediate: true }
)

watch(() => form.qty, (qty) => {
  if (!prefilledContainerMode.value) return
  if (allocationRows.value.length !== 1) return
  const row = allocationRows.value[0]
  if (row.mode !== 'kiste' || !row.container_batch_id) return
  row.qty = Math.max(1, qty || 1)
})

async function loadSlots(rackId: string) {
  if (!rackId) {
    slots.value = []
    form.slot_id = ''
    return
  }
  try {
    slots.value = await getStorageSlots(rackId)
    await prefetchSlotPreviewsForRack(rackId)
  } catch (err) {
    console.error('Fehler beim Laden der Slots:', err)
    slots.value = []
  }
}

async function onRackChange() {
  form.slot_id = ''
  const selectedRack = racks.value.find((r) => r.id === form.rack_id)
  form.storage_address_id = selectedRack?.storage_address_id || form.storage_address_id
  await loadSlots(form.rack_id)
}

function onStorageAddressChange() {
  form.rack_id = ''
  form.slot_id = ''
  slots.value = []
}

function onSlotChange() {
  // v-model already updates form.slot_id; this hook keeps template API stable.
}

// Lieferant Suche
function filterSuppliers() {
  const query = supplierSearch.value.toLowerCase().trim()
  if (!query) {
    filteredSuppliers.value = allSuppliers.value.slice(0, 10)
    return
  }
  filteredSuppliers.value = allSuppliers.value
    .filter(a => (a.name?.toLowerCase().includes(query)) || (a.company?.toLowerCase().includes(query)))
    .slice(0, 10)
}

function selectSupplier(addr: Address) {
  selectedSupplier.value = addr
  form.supplier_id = addr.id
  supplierSearch.value = addr.name || addr.company || ''
  showSupplierDropdown.value = false
}

function clearSupplier() {
  selectedSupplier.value = null
  form.supplier_id = ''
  supplierSearch.value = ''
}

function hideSupplierDropdownDelayed() {
  setTimeout(() => {
    showSupplierDropdown.value = false
  }, 200)
}

// Adress-Modal
const showAddressModal = ref(false)

function openAddSupplierModal() {
  showSupplierDropdown.value = false
  showAddressModal.value = true
}

async function handleAddressSaved() {
  const savedName = supplierSearch.value.toLowerCase().trim()
  showAddressModal.value = false

  // Lieferanten neu laden
  try {
    const result = await getAddresses(props.departmentId, 'supplier')
    allSuppliers.value = result.addresses || []
  } catch (err) {
    console.error('Fehler beim Neuladen der Lieferanten:', err)
  }

  // Neu erstellten Lieferanten automatisch auswählen
  if (savedName) {
    const newAddr = allSuppliers.value.find(a =>
      (a.name?.toLowerCase() === savedName) ||
      (a.company?.toLowerCase() === savedName)
    )
    if (newAddr) {
      selectSupplier(newAddr)
    }
  }
}

const canSubmit = computed(() => {
  if (isEditMode.value) {
    return form.qty >= 1
  }
  if (form.qty < 1 || !form.acquired_on) return false
  if (props.isSerialized && form.qty > 1) {
    if (serialDuplicateHint.value) return false
    const allFilled = serialEntries.value.every(e => (e.serial_number || '').trim().length > 0)
    if (!allFilled) return false
    if (form.create_slot_per_serial && !form.rack_id) return false
  }
  if (form.split_allocations && (!allocationSumValid.value || allocationRows.value.every((r) => (r.mode === 'slot' ? !r.rack_id : !r.container_batch_id) || r.qty <= 0))) return false
  return true
})

const missingFields = computed(() => {
  const missing: string[] = []
  if (!isEditMode.value && !form.acquired_on) {
    missing.push('Kaufdatum eingeben')
  }
  if (form.qty < 1) {
    missing.push('Menge muss mindestens 1 sein')
  }
  if (props.isSerialized && form.qty > 1) {
    if (serialDuplicateHint.value) missing.push(serialDuplicateHint.value)
    else if (!serialEntries.value.every(e => (e.serial_number || '').trim().length > 0)) {
      missing.push('Alle Seriennummern müssen ausgefüllt sein')
    } else if (form.create_slot_per_serial && !form.rack_id) {
      missing.push('Gestell wählen (für Lagerplätze)')
    }
  }
  if (form.split_allocations && (!allocationSumValid.value || allocationRows.value.every((r) => (r.mode === 'slot' ? !r.rack_id : !r.container_batch_id) || r.qty <= 0))) {
    missing.push('Lagerplätze: Summe muss ' + form.qty + ' Stk. ergeben')
  }
  return missing
})

function formatDate(dateStr: string): string {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('de-CH')
}

async function handleSubmit() {
  submitted.value = true
  errorMsg.value = ''
  
  if (!canSubmit.value) return
  
  isSaving.value = true
  
  try {
    let result: MaterialBatch | AddBatchMultiResponse

    if (isEditMode.value && props.batch) {
      // Update
      const payload: UpdateBatchRequest = {}
      if (form.qty !== props.batch.qty) payload.qty = form.qty
      if (form.unit_price !== (props.batch.unit_price || '')) payload.unit_price = form.unit_price || null
      if (form.notes !== (props.batch.notes || '')) payload.notes = form.notes || null
      if (form.serial_number !== (props.batch.serial_number || '')) payload.serial_number = form.serial_number || null
      if (form.rack_id !== (props.batch.rack_id || '')) payload.rack_id = form.rack_id || null
      if (form.slot_id !== (props.batch.slot_id || '')) payload.slot_id = form.slot_id || null
      if (form.label !== ((props.batch as any).label || '')) payload.label = form.label.trim() || null
      if (form.supplier_id) payload.supplier_id = form.supplier_id
      
      result = await updateBatch(props.materialId, props.batch.id, payload)
    } else {
      // Add
      const payload: AddBatchRequest = {
        qty: form.qty,
        acquired_on: form.acquired_on,
        unit_price: form.unit_price || null,
        supplier_id: form.supplier_id || null,
        notes: form.notes || null,
        ...(form.split_allocations && allocationRows.value.length > 0 && allocationSumValid.value
          ? {
              allocations: allocationRows.value
                .filter((r) => r.qty > 0 && (r.mode === 'slot' ? r.rack_id : r.container_batch_id))
                .map((r) =>
                  r.mode === 'kiste'
                    ? { container_batch_id: r.container_batch_id, qty: r.qty }
                    : { rack_id: r.rack_id, slot_id: r.slot_id || undefined, qty: r.qty }
                )
            }
          : {
              rack_id: form.rack_id || null,
              slot_id: form.slot_id || null
            }),
      }

      if (props.isSerialized && form.qty > 1) {
        payload.serial_entries = serialEntries.value
          .filter(e => (e.serial_number || '').trim())
          .map(e => ({
            serial_number: e.serial_number.trim(),
            label: (e.label || '').trim() || undefined
          }))
        payload.create_slot_per_serial = form.create_slot_per_serial
      } else if (props.isSerialized && form.qty === 1 && form.serial_number) {
        payload.serial_numbers = [form.serial_number.trim()]
      }

      result = await addBatch(props.materialId, payload)
    }

    emit('saved', result)
  } catch (err: any) {
    const msg = err.response?.data?.error || 'Fehler beim Speichern der Charge'
    errorMsg.value = msg
    toast.error(msg)
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

.batch-toggle-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
}

.batch-toggle-input {
  width: 18px;
  height: 18px;
}

.batch-field-hint {
  font-size: 12px;
  color: #6b7280;
  margin-top: 6px;
}

.batch-field-hint.is-invalid {
  color: #ef4444;
}

.allocations-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.allocations-header label {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.add-serial-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #10b981;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: white;
  cursor: pointer;
  transition: all 0.2s;
}

.add-serial-btn:hover {
  background: #059669;
}

.allocations-table-wrap,
.serial-entries-table-wrap {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: white;
}

.serial-entries-header {
  margin-bottom: 8px;
}

.serial-entries-header label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.serial-entries-table {
  width: 100%;
  border-collapse: collapse;
}

.serial-entries-table th {
  text-align: left;
  padding: 10px 12px;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.serial-entries-table td {
  padding: 8px 12px;
  border-bottom: 1px solid #f3f4f6;
}

.serial-entries-table tr:last-child td {
  border-bottom: none;
}

.serial-entries-table .form-input--sm {
  width: 100%;
  min-width: 100px;
  padding: 6px 10px;
  font-size: 13px;
}

.allocations-table {
  width: 100%;
  border-collapse: collapse;
}

.allocations-table th {
  text-align: left;
  padding: 10px 12px;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.allocations-table td {
  padding: 8px 12px;
  border-bottom: 1px solid #f3f4f6;
}

.allocations-table tr:last-child td {
  border-bottom: none;
}

.allocations-table .form-input--sm,
.allocations-table .form-select--sm {
  width: 100%;
  min-width: 80px;
  padding: 6px 10px;
  font-size: 13px;
}

.remove-row-btn {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
  padding: 4px 8px;
  border-radius: 4px;
}

.remove-row-btn:hover {
  color: #ef4444;
  background: #fef2f2;
}

.batch-form-group label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.required {
  color: #ef4444;
  font-weight: 600;
}

.batch-form-input {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: white;
  transition: border-color 0.15s;
}

.batch-form-input:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

.batch-form-input.is-invalid {
  border-color: #ef4444;
  background: #fef2f2;
}

.batch-form-select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: white;
  cursor: pointer;
}

.batch-form-select:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

.batch-form-textarea {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  resize: vertical;
  font-family: inherit;
}

.batch-form-textarea:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
}

.batch-price-input {
  display: flex;
  align-items: center;
  gap: 6px;
}

.batch-currency {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
  white-space: nowrap;
}

.batch-readonly-value {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  padding: 8px 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.batch-readonly-hint {
  font-size: 11px;
  color: #9ca3af;
  font-weight: 400;
  font-style: italic;
}

.batch-error {
  background: #fef2f2;
  color: #991b1b;
  padding: 10px 14px;
  border-radius: 6px;
  font-size: 13px;
  border: 1px solid #fecaca;
  margin-top: 4px;
}

.batch-modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
  border-radius: 0 0 12px 12px;
}

.batch-missing {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #d97706;
}

.batch-missing-icon {
  font-size: 14px;
}

.batch-footer-actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

/* Autocomplete Lieferant */
.batch-autocomplete-wrapper {
  position: relative;
}

.batch-autocomplete-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #d1d5db;
  border-top: none;
  border-radius: 0 0 6px 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  max-height: 180px;
  overflow-y: auto;
  z-index: 50;
}

.batch-autocomplete-item {
  padding: 8px 12px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
  transition: background 0.1s;
}

.batch-autocomplete-item:hover {
  background: #f3f4f6;
}

.batch-autocomplete-item.batch-ac-empty {
  color: #9ca3af;
  cursor: default;
  font-style: italic;
}

.batch-autocomplete-item.batch-ac-empty:hover {
  background: transparent;
}

.batch-ac-name {
  color: #111827;
  font-weight: 500;
}

.batch-ac-city {
  color: #9ca3af;
  font-size: 12px;
}

.batch-selected-supplier {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
  font-size: 13px;
  color: #059669;
  font-weight: 500;
}

.batch-clear-btn {
  background: none;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  color: #9ca3af;
  cursor: pointer;
  font-size: 14px;
  line-height: 1;
  padding: 1px 5px;
  transition: all 0.15s;
}

.batch-clear-btn:hover {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #ef4444;
}

/* + Button neben Suchfeld */
.batch-autocomplete-wrapper {
  display: flex;
  gap: 6px;
  align-items: center;
}

.batch-autocomplete-wrapper .batch-form-input {
  flex: 1;
}

.batch-autocomplete-wrapper .batch-autocomplete-dropdown {
  left: 0;
  right: 40px;
}

.batch-add-inline-btn {
  width: 34px;
  height: 34px;
  border: 1px solid #d1d5db;
  background: white;
  border-radius: 6px;
  font-size: 18px;
  font-weight: 600;
  color: #059669;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.15s;
}

.batch-add-inline-btn:hover {
  background: #ecfdf5;
  border-color: #10b981;
}

.batch-autocomplete-item.batch-ac-create {
  color: #059669;
  font-weight: 500;
  cursor: pointer;
}

.batch-autocomplete-item.batch-ac-create:hover {
  background: #ecfdf5;
}

.batch-autocomplete-item.batch-ac-create .batch-ac-name {
  color: #059669;
}
</style>
