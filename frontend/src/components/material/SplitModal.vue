<template>
  <Teleport to="body">
    <div v-if="modelValue" class="split-modal-overlay">
      <div class="split-modal">
        <div class="split-modal-header">
          <h2>{{ t('components.splitModal.title') }}</h2>
          <button class="split-modal-close" @click="$emit('update:modelValue', false)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
        <div class="split-modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label>{{ t('components.splitModal.labelSourceBatch') }}</label>
              <select v-model="form.source_batch_id" class="form-select" :class="{ 'is-invalid': submitted && !form.source_batch_id }">
                <option v-for="batch in sourceBatches" :key="batch.id" :value="batch.id">
                  {{ formatDate(batch.acquired_on) }} • {{ t('components.splitModal.qtyPcs', { n: batch.qty }) }} •
                  {{ formatBatchLocation(batch) }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>{{ t('components.splitModal.labelQuantity') }}</label>
              <input v-model.number="form.quantity" type="number" min="1" class="form-input" :class="{ 'is-invalid': submitted && form.quantity < 1 }" />
            </div>
            <div class="form-group">
              <label>{{ t('components.splitModal.labelPrefix') }}</label>
              <input
                v-model="form.serial_prefix"
                type="text"
                class="form-input"
                :placeholder="t('components.splitModal.prefixPlaceholder')"
              />
            </div>
            <div class="form-group">
              <label>{{ t('components.splitModal.labelStartNumber') }}</label>
              <input v-model.number="form.start_number" type="number" min="1" class="form-input" />
            </div>
            <div class="form-group">
              <label>{{ t('components.splitModal.labelPadLength') }}</label>
              <input
                v-model.number="form.pad_length"
                type="number"
                min="1"
                max="6"
                class="form-input"
                :placeholder="t('components.splitModal.padPlaceholder')"
              />
            </div>
            <div v-if="locationSuggestions.length > 0" class="form-group form-group-full">
              <label>{{ t('components.splitModal.labelLocationSuggestions') }}</label>
              <div class="location-suggestions">
                <button
                  v-for="(suggestion, idx) in locationSuggestions"
                  :key="idx"
                  type="button"
                  class="location-suggestion-btn"
                  @click="applyLocationSuggestion(suggestion)"
                >
                  {{ suggestion.label }}
                </button>
              </div>
            </div>
            <div class="form-group form-group-full">
              <label class="split-row-checkbox split-row-checkbox--inline">
                <input type="checkbox" v-model="serialLocationSameForAll" />
                <span>{{ t('components.splitModal.sameLocationForAll') }}</span>
              </label>
            </div>

            <template v-if="serialLocationSameForAll">
              <div class="form-group">
                <label>{{ t('components.splitModal.labelStorageLocation') }}</label>
                <select v-model="form.location_mode" class="form-select" @change="form.rack_id = ''; form.slot_id = ''; form.container_batch_id = ''">
                  <option value="slot">{{ t('components.splitModal.locationModeSlot') }}</option>
                  <option value="kiste">{{ t('components.splitModal.locationModeBox') }}</option>
                </select>
              </div>
              <template v-if="form.location_mode === 'slot'">
                <div class="form-group form-group-full">
                  <StorageLocationPicker
                    :rack-id="form.rack_id"
                    :slot-id="form.slot_id"
                    :racks="racks"
                    :slot-list="selectedSlots"
                    :rack-label="t('components.splitModal.rackLabel')"
                    :slot-label="t('components.splitModal.slotLabel')"
                    :rack-placeholder="t('components.splitModal.rackPlaceholder')"
                    :slot-placeholder="t('components.splitModal.slotPlaceholder')"
                    @update:rackId="form.rack_id = $event"
                    @rackChange="handleRackChange"
                    @update:slotId="form.slot_id = $event"
                  />
                </div>
              </template>
              <div v-else class="form-group">
                <label>{{ t('components.splitModal.locationModeBox') }}</label>
                <select v-model="form.container_batch_id" class="form-select" :class="{ 'is-invalid': submitted && !form.container_batch_id }">
                  <option value="">{{ t('components.splitModal.selectBoxPrompt') }}</option>
                <option
                  v-for="cb in containerBatches"
                  :key="cb.id"
                  :value="cb.id"
                  :title="formatContainerBatchOptionFullLabel(cb)"
                >
                  {{ formatContainerBatchOptionFullLabel(cb) }}
                </option>
                </select>
              </div>
            </template>
          </div>
          <p class="split-hint">{{ t('components.splitModal.hintQrReprint') }}</p>
          <div class="split-entries-section">
            <label class="split-entries-label">{{
              t('components.splitModal.serialNumbersHeading', { count: serialEntries.length })
            }}</label>
            <div class="split-entries-table-wrap">
              <table class="split-entries-table">
                <thead>
                  <tr>
                    <th>{{ t('components.splitModal.thSerialQr') }}</th>
                    <th>{{ t('components.splitModal.thLabelOptional') }}</th>
                    <th v-if="!serialLocationSameForAll" class="col-type">{{ t('components.splitModal.thType') }}</th>
                    <th v-if="!serialLocationSameForAll">{{ t('components.splitModal.thStorageLocation') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(entry, i) in serialEntries" :key="i">
                    <td>
                      <input
                        v-model="entry.serial_number"
                        type="text"
                        class="form-input form-input-sm"
                        :class="{ 'is-invalid': submitted && !(entry.serial_number || '').trim() }"
                        :placeholder="t('components.splitModal.phSerial')"
                      />
                    </td>
                    <td>
                      <input
                        v-model="entry.label"
                        type="text"
                        class="form-input form-input-sm"
                        :placeholder="t('components.splitModal.phLabel')"
                      />
                    </td>
                    <td v-if="!serialLocationSameForAll" class="col-type">
                      <select
                        v-model="entry.location_mode"
                        class="form-select form-select-sm"
                        @change="entry.rack_id = ''; entry.slot_id = ''; entry.container_batch_id = ''"
                      >
                        <option value="slot">{{ t('components.splitModal.locationModeSlot') }}</option>
                        <option value="kiste">{{ t('components.splitModal.locationModeBox') }}</option>
                      </select>
                    </td>
                    <td v-if="!serialLocationSameForAll">
                      <div class="split-row-location">
                        <template v-if="entry.location_mode === 'slot'">
                          <div class="split-row-location-grid">
                            <select
                              v-model="entry.rack_id"
                              class="form-select form-select-sm"
                              @change="entry.slot_id = ''; handleRowRackChange(entry)"
                            >
                              <option value="">{{ t('components.splitModal.rackPlaceholder') }}</option>
                              <option v-for="rack in racks" :key="rack.id" :value="rack.id">{{ rack.name }}</option>
                            </select>
                            <select v-model="entry.slot_id" class="form-select form-select-sm">
                              <option value="">{{ t('components.splitModal.slotPlaceholder') }}</option>
                              <option v-for="slot in getSlotsForRack(entry.rack_id)" :key="slot.id" :value="slot.id">{{ slot.name }}</option>
                            </select>
                          </div>
                        </template>
                        <template v-else>
                          <select v-model="entry.container_batch_id" class="form-select form-select-sm">
                            <option value="">{{ t('components.splitModal.selectBoxPrompt') }}</option>
                <option
                  v-for="cb in containerBatches"
                  :key="cb.id"
                  :value="cb.id"
                  :title="formatContainerBatchOptionFullLabel(cb)"
                >
                  {{ formatContainerBatchOptionFullLabel(cb) }}
                </option>
                          </select>
                        </template>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-if="submitted && duplicateHint" class="split-error-hint">{{ duplicateHint }}</p>
          </div>
          <p class="split-hint">{{ t('components.splitModal.hintPerRowLocation') }}</p>
        </div>
        <div class="split-modal-actions">
          <div v-if="submitted && missingFields.length > 0" class="split-missing">
            <span class="split-missing-icon">⚠️</span>
            <span>{{ missingFields[0] }}</span>
          </div>
          <div class="split-footer-actions">
            <button class="btn-outline btn-sm" @click="$emit('update:modelValue', false)">{{
              t('common.cancel')
            }}</button>
            <button class="btn-primary btn-sm" @click="submit" :disabled="isSplitting">
              {{ isSplitting ? t('components.splitModal.splitting') : t('components.splitModal.splitSubmit') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { splitToSerialized } from '@/api/materials'
import { getContainerBatches, type StorageSlot } from '@/api/storageLocations'
import { formatContainerBatchOptionFullLabel } from '@/utils/containerBatchLabel'
import type { MaterialBatch } from '@/api/materials'
import { useToast } from '@/composables/useToast'
import { useI18n } from 'vue-i18n'
import StorageLocationPicker from '@/components/storage/StorageLocationPicker.vue'
import { useStorageStructure } from '@/composables/useStorageStructure'

interface SerialEntry {
  serial_number: string
  label: string
  location_mode: 'slot' | 'kiste'
  rack_id: string
  slot_id: string
  container_batch_id: string
}

const props = defineProps<{
  modelValue: boolean
  materialId: string
  departmentId: string
  materialName: string
  sourceBatches: MaterialBatch[]
  existingBatches?: MaterialBatch[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: []
}>()

const error = ref('')
const isSplitting = ref(false)
const submitted = ref(false)
const containerBatches = ref<import('@/api/storageLocations').ContainerBatch[]>([])
const toast = useToast()
const { t, locale } = useI18n()
const serialLocationSameForAll = ref(true)
const { racks, getSlots, loadRacks, loadSlots } = useStorageStructure(() => props.departmentId)

const form = reactive({
  source_batch_id: '',
  quantity: 1,
  serial_prefix: '',
  start_number: 1,
  pad_length: 3,
  location_mode: 'slot' as 'slot' | 'kiste',
  rack_id: '',
  slot_id: '',
  container_batch_id: ''
})

const serialEntries = ref<SerialEntry[]>([])

const selectedSlots = computed<StorageSlot[]>(() => {
  if (!form.rack_id) return []
  return getSlots(form.rack_id)
})

function getSlotsForRack(rackId: string): StorageSlot[] {
  if (!rackId) return []
  return getSlots(rackId)
}

type LocationSuggestion = {
  kind: 'slot' | 'kiste'
  rackId?: string
  slotId?: string
  containerBatchId?: string
  label: string
}

const selectedSourceBatch = computed(() =>
  props.sourceBatches.find((batch) => batch.id === form.source_batch_id) || null
)

function buildSerialEntries(): SerialEntry[] {
  const qty = Math.max(1, form.quantity)
  const prefix = (form.serial_prefix || '').trim() || 'SER-'
  const start = Math.max(1, form.start_number)
  const pad = Math.max(1, Math.min(6, form.pad_length || 3))
  const entries: SerialEntry[] = []
  const defaultMode = form.location_mode
  const defaultRackId = form.rack_id || ''
  const defaultSlotId = form.slot_id || ''
  const defaultContainerBatchId = form.container_batch_id || ''
  for (let i = 0; i < qty; i++) {
    const num = String(start + i)
    const padded = num.padStart(pad, '0')
    entries.push({
      serial_number: prefix + padded,
      label: '',
      location_mode: defaultMode,
      rack_id: defaultRackId,
      slot_id: defaultSlotId,
      container_batch_id: defaultContainerBatchId,
    })
  }
  return entries
}

function regenerateSerialEntries() {
  serialEntries.value = buildSerialEntries()
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

const locationSuggestions = computed<LocationSuggestion[]>(() => {
  const batch = selectedSourceBatch.value
  if (!batch) return []
  const suggestions: LocationSuggestion[] = []
  const seen = new Set<string>()

  const pushSuggestion = (suggestion: LocationSuggestion) => {
    const key = suggestion.kind === 'kiste'
      ? `kiste:${suggestion.containerBatchId || ''}`
      : `slot:${suggestion.rackId || ''}:${suggestion.slotId || ''}`
    if (!seen.has(key)) {
      seen.add(key)
      suggestions.push(suggestion)
    }
  }

  if (Array.isArray(batch.allocations) && batch.allocations.length > 0) {
    const sortedAllocs = [...batch.allocations].sort((a, b) => (b.qty || 0) - (a.qty || 0))
    for (const allocation of sortedAllocs) {
      const container = allocation.container_batch
      if (container?.id) {
        const containerLabel = container.label || container.serial_number || t('components.splitModal.fallbackBox')
        const rackName = container.rack?.name
        const slotName = container.slot?.name
        const locInner = rackName ? (slotName ? `${rackName} / ${slotName}` : rackName) : ''
        const locSuffix = locInner ? t('components.splitModal.allocLocSuffix', { loc: locInner }) : ''
        pushSuggestion({
          kind: 'kiste',
          containerBatchId: container.id,
          label: t('components.splitModal.suggestionBox', { label: containerLabel, loc: locSuffix }),
        })
        continue
      }
      if (allocation.rack_id) {
        const rackName = allocation.rack?.name || allocation.rack_name || allocation.rack_id
        const slotName = allocation.slot?.name || allocation.slot_name || ''
        pushSuggestion({
          kind: 'slot',
          rackId: allocation.rack_id,
          slotId: allocation.slot_id || undefined,
          label: slotName
            ? t('components.splitModal.suggestionSlot', { rack: rackName, slot: slotName })
            : t('components.splitModal.suggestionRack', { rack: rackName }),
        })
      }
    }
  } else if (batch.rack_id) {
    const rackName = batch.rack?.name || batch.rack_id
    const slotName = batch.slot?.name || batch.slot_id || ''
    pushSuggestion({
      kind: 'slot',
      rackId: batch.rack_id,
      slotId: batch.slot_id || undefined,
      label: slotName
        ? t('components.splitModal.suggestionSlot', { rack: rackName, slot: slotName })
        : t('components.splitModal.suggestionRack', { rack: rackName }),
    })
  }

  return suggestions.slice(0, 4)
})

const missingFields = computed(() => {
  const missing: string[] = []
  if (!form.source_batch_id) missing.push(t('components.splitModal.valPickSourceBatch'))
  if (form.quantity < 1) missing.push(t('components.splitModal.valQtyMin1'))
  const emptySerials = serialEntries.value.filter(e => !(e.serial_number || '').trim())
  if (emptySerials.length > 0) missing.push(t('components.splitModal.valFillSerials', { n: emptySerials.length }))
  if (duplicateHint.value) missing.push(duplicateHint.value)
  if (serialLocationSameForAll.value) {
    if (form.location_mode === 'slot' && (!form.rack_id || !form.slot_id)) missing.push(t('components.splitModal.valRackSlot'))
    if (form.location_mode === 'kiste' && !form.container_batch_id) missing.push(t('components.splitModal.valPickBox'))
  } else {
    const invalidRows = serialEntries.value
      .filter((entry) => (entry.serial_number || '').trim())
      .some((entry) =>
        entry.location_mode === 'kiste'
          ? !entry.container_batch_id
          : (!entry.rack_id || !entry.slot_id)
      )
    if (invalidRows) missing.push(t('components.splitModal.valPerSerialLocation'))
  }
  return missing
})

const duplicateHint = computed(() => {
  const existing = new Set(
    (props.existingBatches || [])
      .filter(b => b.serial_number)
      .map(b => (b.serial_number || '').trim())
      .filter(Boolean)
  )
  const duplicates = serialEntries.value
    .map(e => e.serial_number.trim())
    .filter(sn => sn && existing.has(sn))
  if (duplicates.length > 0) {
    const list =
      duplicates.slice(0, 3).join(', ') + (duplicates.length > 3 ? '…' : '')
    return t('components.splitModal.duplicateTaken', { list })
  }
  const seen = new Set<string>()
  for (const e of serialEntries.value) {
    const sn = e.serial_number.trim()
    if (sn && seen.has(sn)) return t('components.splitModal.duplicateInList')
    if (sn) seen.add(sn)
  }
  return ''
})

function formatDate(d: string | null | undefined): string {
  if (!d) return '-'
  try {
    const locTag = locale.value?.toString().startsWith('de') ? 'de-CH' : 'en-CH'
    return new Date(d).toLocaleDateString(locTag, { day: '2-digit', month: '2-digit', year: 'numeric' })
  } catch {
    return String(d)
  }
}

function formatBatchLocation(batch: any): string {
  if (batch.allocations?.length) {
    return batch.allocations.map((a: any) => {
      const cb = a.container_batch
      const containerLabel = cb?.label || cb?.serial_number
      const containerMaterial = cb?.material_name
      if (containerLabel) {
        const loc = cb?.rack?.name ? (cb?.slot?.name ? `${cb.rack.name} / ${cb.slot.name}` : cb.rack.name) : ''
        const materialSuffix =
          containerMaterial && containerMaterial !== containerLabel
            ? t('components.splitModal.allocMaterialSuffix', { name: containerMaterial })
            : ''
        const locSuffix = loc ? t('components.splitModal.allocLocSuffix', { loc }) : ''
        return t('components.splitModal.allocBox', {
          label: containerLabel,
          material: materialSuffix,
          loc: locSuffix,
          qty: a.qty,
        })
      }
      return t('components.splitModal.allocRack', {
        rack: a.rack_name || a.rack?.name || a.rack_id,
        qty: a.qty,
      })
    }).join(', ')
  }
  if (batch.rack_id && batch.slot_id) return `${batch.rack_name || batch.rack_id} / ${batch.slot_name || batch.slot_id}`
  if (batch.rack_id) return batch.rack_name || batch.rack_id
  return '-'
}

watch(() => props.modelValue, async (open) => {
  if (open && props.sourceBatches.length > 0) {
    error.value = ''
    submitted.value = false
    form.source_batch_id = props.sourceBatches[0].id
    const firstBatch = props.sourceBatches[0]
    form.quantity = Math.max(1, firstBatch.qty || 1)
    form.serial_prefix = `${(props.materialName || 'SER').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6)}-`
    form.pad_length = 3
    form.location_mode = 'slot'
    form.rack_id = ''
    form.slot_id = ''
    form.container_batch_id = ''
    serialLocationSameForAll.value = true
    form.start_number = suggestedStartNumber.value
    await loadRacks().catch(() => [])
    containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
    if (locationSuggestions.value.length > 0) {
      await applyLocationSuggestion(locationSuggestions.value[0])
    }
    regenerateSerialEntries()
  }
})

watch(
  () => [form.quantity, form.serial_prefix, form.start_number, form.pad_length] as const,
  () => regenerateSerialEntries(),
  { immediate: true }
)

watch(
  () => form.source_batch_id,
  async (batchId) => {
    if (batchId && props.sourceBatches.length > 0) {
      const batch = props.sourceBatches.find(b => b.id === batchId)
      if (batch) {
        form.quantity = Math.max(1, batch.qty || 1)
      }
      if (locationSuggestions.value.length > 0) {
        await applyLocationSuggestion(locationSuggestions.value[0])
      } else {
        form.location_mode = 'slot'
        form.rack_id = ''
        form.slot_id = ''
        form.container_batch_id = ''
        if (!serialLocationSameForAll.value) {
          for (const entry of serialEntries.value) {
            entry.location_mode = 'slot'
            entry.rack_id = ''
            entry.slot_id = ''
            entry.container_batch_id = ''
          }
        }
      }
    }
  }
)

watch(
  () => serialLocationSameForAll.value,
  async (sameForAll) => {
    if (sameForAll) return
    const defaultMode = form.location_mode
    const defaultRack = form.rack_id || ''
    const defaultSlot = form.slot_id || ''
    const defaultContainer = form.container_batch_id || ''
    for (const entry of serialEntries.value) {
      entry.location_mode = defaultMode
      entry.rack_id = defaultRack
      entry.slot_id = defaultSlot
      entry.container_batch_id = defaultContainer
      if (entry.location_mode === 'slot' && entry.rack_id) {
        await loadSlots(entry.rack_id).catch(() => [])
      }
    }
  }
)

async function handleRackChange() {
  form.slot_id = ''
  if (!form.rack_id) {
    return
  }
  await loadSlots(form.rack_id).catch(() => [])
}

async function handleRowRackChange(entry: SerialEntry) {
  if (!entry.rack_id) return
  await loadSlots(entry.rack_id).catch(() => [])
}

async function applyLocationSuggestion(suggestion: LocationSuggestion) {
  if (suggestion.kind === 'kiste' && suggestion.containerBatchId) {
    form.location_mode = 'kiste'
    form.container_batch_id = suggestion.containerBatchId
    form.rack_id = ''
    form.slot_id = ''
    if (!serialLocationSameForAll.value) {
      for (const entry of serialEntries.value) {
        entry.location_mode = 'kiste'
        entry.container_batch_id = suggestion.containerBatchId
        entry.rack_id = ''
        entry.slot_id = ''
      }
    }
    return
  }
  form.location_mode = 'slot'
  form.container_batch_id = ''
  form.rack_id = suggestion.rackId || ''
  await handleRackChange()
  if (suggestion.slotId) form.slot_id = suggestion.slotId
  if (!serialLocationSameForAll.value) {
    for (const entry of serialEntries.value) {
      entry.location_mode = 'slot'
      entry.container_batch_id = ''
      entry.rack_id = form.rack_id
      entry.slot_id = form.slot_id
    }
  }
}

async function submit() {
  submitted.value = true
  if (missingFields.value.length > 0 || isSplitting.value) return
  const entries = serialEntries.value.filter(e => (e.serial_number || '').trim())
  if (entries.length !== form.quantity) {
    toast.error(t('components.splitModal.toastFillSerials'))
    return
  }
  isSplitting.value = true
  try {
    await splitToSerialized(props.materialId, {
      source_batch_id: form.source_batch_id,
      quantity: form.quantity,
      serial_entries: entries.map(e => ({
        serial_number: e.serial_number.trim(),
        label: (e.label || '').trim() || undefined
      })),
      rack_id: serialLocationSameForAll.value
        ? (form.location_mode === 'slot' ? form.rack_id || null : undefined)
        : undefined,
      slot_id: serialLocationSameForAll.value
        ? (form.location_mode === 'slot' ? form.slot_id || null : undefined)
        : undefined,
      container_batch_id: serialLocationSameForAll.value
        ? (form.location_mode === 'kiste' ? form.container_batch_id || undefined : undefined)
        : undefined,
      serial_allocations: serialLocationSameForAll.value
        ? undefined
        : entries.map((e) => ({
            serial_number: e.serial_number.trim(),
            rack_id: e.location_mode === 'slot' ? e.rack_id || undefined : undefined,
            slot_id: e.location_mode === 'slot' ? e.slot_id || undefined : undefined,
            container_batch_id: e.location_mode === 'kiste' ? e.container_batch_id || undefined : undefined,
          })),
    })
    emit('saved')
    emit('update:modelValue', false)
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('components.splitModal.toastSplitFailed'))
  } finally {
    isSplitting.value = false
  }
}
</script>

<style scoped>
.split-modal-overlay {
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

.split-modal {
  background: white;
  border-radius: 12px;
  width: min(96vw, 980px);
  max-width: 980px;
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

.split-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.split-modal-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.split-modal-close {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.15s;
}

.split-modal-close:hover {
  background: #f3f4f6;
  color: #374151;
}

.split-modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group-full {
  grid-column: 1 / -1;
}

.form-group label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.form-select,
.form-input {
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
}

.error-text {
  color: #dc2626;
  font-size: 14px;
  margin: 0 0 16px;
}

.split-modal-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
  border-radius: 0 0 12px 12px;
}

.split-missing {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #d97706;
}

.split-missing-icon {
  font-size: 14px;
}

.split-footer-actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.form-input.is-invalid,
.form-select.is-invalid {
  border-color: #dc2626;
  box-shadow: 0 0 0 1px #dc2626;
}

.split-hint {
  font-size: 12px;
  color: #6b7280;
  margin: 0 0 12px;
}

.split-entries-section {
  margin: 16px 0;
}

.split-entries-label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  display: block;
  margin-bottom: 8px;
}

.split-entries-table-wrap {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  max-height: 200px;
  overflow-y: auto;
}

.split-entries-table {
  width: 100%;
  border-collapse: collapse;
}

.split-entries-table th {
  text-align: left;
  padding: 8px 12px;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
}

.split-entries-table td {
  padding: 6px 12px;
  border-bottom: 1px solid #f3f4f6;
}

.split-entries-table .form-input-sm {
  width: 100%;
  padding: 6px 10px;
  font-size: 13px;
}

.split-error-hint {
  font-size: 12px;
  color: #dc2626;
  margin: 6px 0 0;
}

.split-checkbox-row {
  margin: 12px 0;
}

.split-row-checkbox {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  cursor: pointer;
  white-space: nowrap;
}

.split-row-checkbox input {
  width: 14px;
  height: 14px;
}

.split-row-checkbox--inline {
  font-size: 13px;
  font-weight: 500;
}

.location-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.location-suggestion-btn {
  border: 1px solid #d1d5db;
  background: #f9fafb;
  color: #374151;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  cursor: pointer;
}

.location-suggestion-btn:hover {
  background: #f3f4f6;
  border-color: #9ca3af;
}

.split-entries-table .col-type {
  width: 180px;
}

.split-row-location-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.form-select-sm {
  padding: 6px 10px;
  font-size: 13px;
}
</style>
