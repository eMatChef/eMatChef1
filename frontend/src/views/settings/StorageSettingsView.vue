<template>
  <div class="storage-settings">
    <div class="settings-header">
      <div>
        <h1>{{ t('settings.storage.title') }}</h1>
        <p class="subtitle">{{ t('settings.storage.subtitle') }}</p>
        <p v-if="primaryStorageLabel" class="subtitle primary-storage-hint">
          {{ t('settings.storage.primaryStorageHint', { label: primaryStorageLabel }) }}
        </p>
        <p v-if="!isLoading && storageAddresses.length === 0" class="subtitle warning-text">
          {{ t('settings.storage.needStorageLocationFirst') }}
        </p>
      </div>
      <button class="btn-primary" :disabled="storageAddresses.length === 0" @click="openRackModal()">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/>
          <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        {{ t('settings.storage.newRack') }}
      </button>
    </div>

    <!-- Suchleiste -->
    <div class="search-bar">
      <div class="search-input-wrapper">
        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/>
          <path d="m21 21-4.35-4.35"/>
        </svg>
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="t('settings.storage.searchPlaceholder')"
          class="search-input"
        />
      </div>
      <div class="rack-count">
        {{ t('settings.storage.rackCount', filteredRacks.length) }}
      </div>
    </div>

    <!-- Baum: Lagerstandort -> Regale -> Faecher -->
    <div class="racks-grid" v-if="!isLoading">
      <div
        v-for="location in groupedFilteredRacks"
        :key="location.id"
        class="location-group"
      >
        <div class="location-header">
          <button class="location-toggle-btn" type="button" @click="toggleLocation(location.id)">
            <span class="location-caret" :class="{ expanded: expandedLocations.has(location.id) }">▶</span>
            <span class="location-title">{{ location.name }}</span>
            <span v-if="location.isPrimary" class="location-primary-badge">{{ t('settings.storage.primaryStorage') }}</span>
            <span class="location-count">{{ t('settings.storage.rackCount', location.racks.length) }}</span>
          </button>
          <div class="location-actions">
            <StorageActionButton
              v-if="location.addressId"
              :title="t('settings.storage.editLocation')"
              icon="edit"
              @click="editStorageLocation(location.addressId)"
            />
            <StorageCrudActions
              v-if="location.addressId"
              :show-edit="false"
              :show-delete="false"
              @add="openRackModalForLocation(location.addressId)"
            />
            <button
              v-if="!location.isPrimary && location.addressId"
              class="set-primary-btn"
              type="button"
              :disabled="settingPrimaryAddressId === location.addressId"
              @click="setPrimaryStorageLocation(location.addressId)"
            >
              {{ settingPrimaryAddressId === location.addressId ? t('common.saving') : t('settings.storage.saveAsPrimary') }}
            </button>
          </div>
        </div>

        <div v-if="expandedLocations.has(location.id)" class="location-racks">
          <div
            v-for="rack in location.racks"
            :key="rack.id"
            class="rack-card"
            :class="{ expanded: expandedRacks.has(rack.id) }"
          >
            <div class="rack-header" @click="toggleRack(rack.id)">
              <div class="rack-left">
                <button class="expand-btn" :class="{ expanded: expandedRacks.has(rack.id) }">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                  </svg>
                </button>
                <div class="rack-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                  </svg>
                </div>
                <div class="rack-info">
                  <span class="rack-name">{{ rack.name }}</span>
                  <span class="rack-meta">
                    {{ t('settings.storage.slotsMeta', getSlotCount(rack.id)) }} · {{ t('settings.storage.locationPrefix') }}: {{ getStorageAddressLabel(rack.storage_address_id) }}
                  </span>
                </div>
              </div>
              <div class="rack-actions" @click.stop>
                <StorageCrudActions
                  :show-edit="true"
                  :show-add="true"
                  :show-delete="true"
                  @edit="openRackModal(rack)"
                  @add="openSlotModal(rack)"
                  @delete="confirmDeleteRack(rack)"
                />
              </div>
            </div>

            <!-- Slots -->
            <transition name="expand">
              <div v-if="expandedRacks.has(rack.id)" class="rack-slots">
                <div
                  v-for="slot in getSlots(rack.id)"
                  :key="slot.id"
                  class="slot-item"
                >
                  <div class="slot-left">
                    <div class="slot-icon">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                      </svg>
                    </div>
                    <span class="slot-name">{{ slot.name }}</span>
                  </div>
                  <div class="slot-actions">
                    <StorageCrudActions
                      size="sm"
                      :show-edit="true"
                      :show-add="false"
                      :show-delete="true"
                      @edit="openSlotModal(rack, slot)"
                      @delete="confirmDeleteSlot(slot)"
                    />
                  </div>
                </div>
                <div v-if="getSlots(rack.id).length === 0" class="slots-empty">
                  <p>{{ t('settings.storage.noSlots') }}</p>
                  <button class="btn-link" @click="openSlotModal(rack)">
                    {{ t('settings.storage.createFirstSlot') }}
                  </button>
                </div>
              </div>
            </transition>
          </div>
        </div>
      </div>

      <!-- Leerer Zustand -->
      <div v-if="groupedFilteredRacks.length === 0" class="empty-state">
        <div class="empty-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>
        <h3>{{ t('settings.storage.noRacksTitle') }}</h3>
        <p>{{ t('settings.storage.noRacksDescription') }}</p>
        <button class="btn-primary" :disabled="storageAddresses.length === 0" @click="openRackModal()">
          {{ t('settings.storage.createFirstRack') }}
        </button>
      </div>
    </div>

    <!-- Ladezustand -->
    <div v-else class="loading-state">
      <div class="spinner"></div>
      <p>{{ t('settings.storage.loadingOverview') }}</p>
    </div>

    <!-- Rack Edit Modal -->
    <div v-if="showRackModal && editingRack" class="modal-overlay">
      <div class="modal-dialog">
        <h3>{{ t('settings.storage.editRackTitle') }}</h3>
        <div class="form-group">
          <label>{{ t('settings.storage.fieldStorageLocationRequired') }}</label>
          <select v-model="rackForm.storage_address_id" class="form-input">
            <option value="">{{ t('settings.storage.selectStorageLocation') }}</option>
            <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">
              {{ addr.name || addr.street_line || addr.full_address || addr.id }}
            </option>
          </select>
        </div>
        <div class="form-group">
          <label>{{ t('common.name') }}</label>
          <input v-model="rackForm.name" type="text" :placeholder="rackPlaceholder" class="form-input" />
        </div>
        <div class="modal-actions">
          <button class="btn-secondary" @click="closeRackModal">{{ t('common.cancel') }}</button>
          <button class="btn-primary" @click="saveRack" :disabled="!rackForm.name.trim() || !rackForm.storage_address_id || isSaving">
            {{ isSaving ? t('common.saving') : t('common.save') }}
          </button>
        </div>
      </div>
    </div>

    <StorageBulkCreateModal
      :is-open="showRackModal && !editingRack"
      :title="t('settings.storage.newRack')"
      :select-label="t('settings.storage.bulkSelectLocation')"
      :select-placeholder="t('settings.storage.selectStorageLocation')"
      :select-options="storageAddressOptions"
      :selected-value="rackForm.storage_address_id"
      :base-label="t('settings.storage.bulkLabelBaseRacks')"
      :base-name="rackCreateBaseName"
      :base-placeholder="rackPlaceholder"
      :suggestions="rackSuggestions"
      :pair-field-label="t('settings.storage.bulkLabelPairPerRack')"
      :pair-field-placeholder="t('settings.storage.slotPlaceholder')"
      :pair-items="rackGeneratedSlotPairs"
      :count-label="t('settings.storage.bulkLabelCountRacks')"
      :count="rackCreateCount"
      :preview-label="t('settings.storage.bulkPreviewRacks')"
      :generated-names="rackGeneratedNames"
      :save-disabled="rackGeneratedNames.length === 0 || !rackForm.storage_address_id || rackGeneratedSlotPairs.some((pair) => !pair.rightValue.trim()) || isSaving"
      :is-saving="isSaving"
      @close="closeRackModal"
      @save="saveRack"
      @increment="incrementRackCreateCount"
      @decrement="decrementRackCreateCount"
      @update:selectedValue="rackForm.storage_address_id = $event"
      @update:baseName="rackCreateBaseName = $event"
      @update:pairItemValue="updateRackPairItemValue"
    />

    <!-- Slot Edit Modal -->
    <div v-if="showSlotModal && editingSlot" class="modal-overlay">
      <div class="modal-dialog">
        <h3>{{ t('settings.storage.editSlotTitle') }}</h3>
        <p v-if="slotRack" class="modal-context">{{ t('settings.storage.rackContext', { name: slotRack.name }) }}</p>
        <div class="form-group">
          <label>{{ t('common.name') }}</label>
          <input v-model="slotForm.name" type="text" :placeholder="slotFachPlaceholder" class="form-input" />
        </div>
        <div class="modal-actions">
          <button class="btn-secondary" @click="closeSlotModal">{{ t('common.cancel') }}</button>
          <button class="btn-primary" @click="saveSlot" :disabled="!slotForm.name.trim() || isSaving">
            {{ isSaving ? t('common.saving') : t('common.save') }}
          </button>
        </div>
      </div>
    </div>

    <StorageBulkCreateModal
      :is-open="showSlotModal && !editingSlot"
      :title="t('settings.storage.newSlot')"
      :context-text="slotRack ? t('settings.storage.rackContext', { name: slotRack.name }) : ''"
      :base-label="t('settings.storage.bulkLabelBaseSlots')"
      :base-name="slotCreateBaseName"
      :base-placeholder="slotFachPlaceholder"
      :suggestions="slotFachSuggestions"
      :count-label="t('settings.storage.bulkLabelCountSlots')"
      :count="slotCreateCount"
      :preview-label="t('settings.storage.bulkPreviewSlots')"
      :generated-names="slotGeneratedNames"
      :save-disabled="slotGeneratedNames.length === 0 || isSaving"
      :is-saving="isSaving"
      @close="closeSlotModal"
      @save="saveSlot"
      @increment="incrementSlotCreateCount"
      @decrement="decrementSlotCreateCount"
      @update:baseName="slotCreateBaseName = $event"
    />

    <StorageConfirmModal
      :is-open="showDeleteRackConfirm"
      :title="t('settings.storage.deleteRackTitle')"
      :message="deletingRack
        ? getDeleteRackMessage(deletingRack)
        : t('settings.storage.deleteRackFallbackMessage')"
      :is-loading="isDeleting"
      @close="showDeleteRackConfirm = false"
      @confirm="executeDeleteRack"
    />

    <StorageConfirmModal
      :is-open="showDeleteSlotConfirm"
      :title="t('settings.storage.deleteSlotTitle')"
      :message="deletingSlot?.name ? t('settings.storage.deleteSlotMessage', { name: deletingSlot.name }) : t('settings.storage.deleteSlotFallbackMessage')"
      :is-loading="isDeleting"
      @close="showDeleteSlotConfirm = false"
      @confirm="executeDeleteSlot"
    />

    <AddressModal
      v-if="showAddressModal"
      :department-id="departmentId"
      :address="editingAddress"
      default-type="storage"
      @close="closeAddressModal"
      @saved="handleAddressSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { getAddresses, setAddressPrimary, type Address } from '@/api/addresses'
import AddressModal from '@/components/AddressModal.vue'
import StorageConfirmModal from '@/components/storage/StorageConfirmModal.vue'
import StorageBulkCreateModal from '@/components/storage/StorageBulkCreateModal.vue'
import StorageActionButton from '@/components/storage/StorageActionButton.vue'
import StorageCrudActions from '@/components/storage/StorageCrudActions.vue'
import {
  getRackSuggestions,
  generateRackNames,
  getSlotPrefix,
  getSlotSuggestions,
  generateSequentialNames,
} from '@/utils/storageUi'
import {
  getStorageRacks,
  getStorageSlots,
  createStorageRack,
  updateStorageRack,
  deleteStorageRack,
  createStorageSlot,
  updateStorageSlot,
  deleteStorageSlot,
  type StorageRack,
  type StorageSlot
} from '@/api/storageLocations'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const departmentId = computed(() => route.params.departmentId as string)

const racks = ref<StorageRack[]>([])
const slotsByRack = ref<Record<string, StorageSlot[]>>({})
const storageAddresses = ref<Address[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const expandedLocations = ref(new Set<string>())
const expandedRacks = ref(new Set<string>())

// Rack Modal
const showRackModal = ref(false)
const editingRack = ref<StorageRack | null>(null)
const rackForm = ref({ name: '', storage_address_id: '' })
const rackCreateCount = ref(1)
const rackCreateBaseName = ref('')
const rackSlotNameOverrides = ref<Record<string, string>>({})

// Slot Modal
const showSlotModal = ref(false)
const slotRack = ref<StorageRack | null>(null)
const editingSlot = ref<StorageSlot | null>(null)
const slotForm = ref({ name: '' })
const slotCreateCount = ref(1)
const slotCreateBaseName = ref('')

const racksForSelectedStorageAddress = computed(() => {
  const selectedStorageAddressId = rackForm.value.storage_address_id
  if (!selectedStorageAddressId) return racks.value
  return racks.value.filter((rack) => rack.storage_address_id === selectedStorageAddressId)
})

const rackPlaceholder = computed(() => {
  if (racksForSelectedStorageAddress.value.length === 0) return t('settings.storage.rackPlaceholderLetters')
  const last = racksForSelectedStorageAddress.value[racksForSelectedStorageAddress.value.length - 1]?.name || ''
  const m = last.match(/^Regal\s+([A-Z])$/i) || last.match(/^([A-Z])$/i)
  if (m) return t('settings.storage.rackPlaceholderLetters')
  const m2 = last.match(/(\d+)$/)
  if (m2) return t('settings.storage.rackPlaceholderNumbers')
  return t('settings.storage.rackPlaceholderLetters')
})

const rackSuggestions = computed(() => {
  if (editingRack.value) return []
  return getRackSuggestions(racksForSelectedStorageAddress.value.map((r) => r.name))
})

const rackGeneratedNames = computed(() => {
  if (editingRack.value) return []
  return generateRackNames(
    rackCreateBaseName.value,
    rackCreateCount.value,
    racksForSelectedStorageAddress.value.map((r) => r.name)
  )
})

const rackGeneratedSlotPairs = computed(() => {
  return rackGeneratedNames.value.map((rackName) => ({
    id: rackName,
    leftLabel: rackName,
    rightValue: rackSlotNameOverrides.value[rackName] ?? `${getSlotPrefix(rackName)}1`,
  }))
})

const slotFachPlaceholder = computed(() => {
  const rack = slotRack.value
  if (!rack) return t('settings.storage.slotPlaceholderGeneric')
  const prefix = getSlotPrefix(rack.name)
  return t('settings.storage.slotPlaceholderPrefix', { prefix })
})

const slotFachSuggestions = computed(() => {
  const rack = slotRack.value
  if (!rack || editingSlot.value) return []
  return getSlotSuggestions(rack.name, getSlots(rack.id).map((s) => s.name))
})

const slotGeneratedNames = computed(() => {
  const rack = slotRack.value
  if (!rack || editingSlot.value) return []
  return generateSequentialNames(
    slotCreateBaseName.value,
    slotCreateCount.value,
    getSlots(rack.id).map((s) => s.name)
  )
})

// Delete
const showDeleteRackConfirm = ref(false)
const showDeleteSlotConfirm = ref(false)
const deletingRack = ref<StorageRack | null>(null)
const deletingSlot = ref<StorageSlot | null>(null)
const isDeleting = ref(false)
const isSaving = ref(false)
const settingPrimaryAddressId = ref<string | null>(null)
const showAddressModal = ref(false)
const editingAddress = ref<Address | null>(null)

// Gefilterte Regale
const filteredRacks = computed(() => {
  if (!searchQuery.value.trim()) return racks.value
  const query = searchQuery.value.toLowerCase()
  return racks.value.filter(r => r.name.toLowerCase().includes(query))
})

const groupedFilteredRacks = computed(() => {
  const grouped = new Map<string, { id: string; addressId: string | null; name: string; isPrimary: boolean; racks: StorageRack[] }>()
  for (const rack of filteredRacks.value) {
    const id = rack.storage_address_id || '__unknown__'
    const address = rack.storage_address_id
      ? storageAddresses.value.find((addr) => addr.id === rack.storage_address_id)
      : null
    const name = address ? (address.name || address.street_line || address.full_address || address.id) : t('settings.storage.notSet')
    const isPrimary = !!address?.is_primary
    const existing = grouped.get(id)
    if (existing) {
      existing.racks.push(rack)
      continue
    }
    grouped.set(id, { id, addressId: address?.id || null, name, isPrimary, racks: [rack] })
  }
  return Array.from(grouped.values()).sort((a, b) => {
    if (a.isPrimary && !b.isPrimary) return -1
    if (!a.isPrimary && b.isPrimary) return 1
    return a.name.localeCompare(b.name, 'de')
  })
})

const storageAddressOptions = computed(() =>
  storageAddresses.value.map((addr) => ({
    id: addr.id,
    label: addr.name || addr.street_line || addr.full_address || addr.id
  }))
)

function getSlots(rackId: string): StorageSlot[] {
  return slotsByRack.value[rackId] ?? []
}

function getSlotCount(rackId: string): number {
  return getSlots(rackId).length
}

function getDeleteRackMessage(rack: StorageRack): string {
  const count = getSlotCount(rack.id)
  const key =
    count === 1
      ? 'settings.storage.deleteRackMessageOne'
      : 'settings.storage.deleteRackMessageMany'
  return t(key, { name: rack.name, count })
}

const defaultStorageAddressId = computed(() => {
  const primary = storageAddresses.value.find((addr) => addr.is_primary)
  return primary?.id || storageAddresses.value[0]?.id || ''
})

const primaryStorageLabel = computed(() => {
  const primary = storageAddresses.value.find((addr) => addr.is_primary)
  if (!primary) return ''
  return primary.name || primary.street_line || primary.full_address || primary.id
})

const primaryStorageAddressId = computed(() => {
  return storageAddresses.value.find((addr) => addr.is_primary)?.id || ''
})

function getStorageAddressLabel(storageAddressId: string | null): string {
  if (!storageAddressId) return t('settings.storage.notSet')
  const address = storageAddresses.value.find((addr) => addr.id === storageAddressId)
  if (!address) return t('settings.storage.unknown')
  return address.name || address.street_line || address.full_address || address.id
}

function toggleRack(rackId: string) {
  if (expandedRacks.value.has(rackId)) {
    expandedRacks.value.delete(rackId)
  } else {
    expandedRacks.value.add(rackId)
    loadSlotsForRack(rackId)
  }
  expandedRacks.value = new Set(expandedRacks.value)
}

function toggleLocation(locationId: string) {
  const isCurrentlyOpen = expandedLocations.value.has(locationId)
  if (isCurrentlyOpen) {
    expandedLocations.value = new Set()
    expandedRacks.value = new Set()
    return
  }

  expandedLocations.value = new Set([locationId])
  const locationNode = groupedFilteredRacks.value.find((loc) => loc.id === locationId)
  if (locationNode && locationNode.racks.length < 10) {
    expandedRacks.value = new Set(locationNode.racks.map((rack) => rack.id))
  } else {
    expandedRacks.value = new Set()
  }
}

async function setPrimaryStorageLocation(addressId: string) {
  if (!addressId) return
  settingPrimaryAddressId.value = addressId
  try {
    await setAddressPrimary(addressId)
    toast.success(t('settings.storage.toastPrimarySet'))
    await loadRacks()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.storage.toastPrimarySetError'))
  } finally {
    settingPrimaryAddressId.value = null
  }
}

function editStorageLocation(addressId: string) {
  const address = storageAddresses.value.find((a) => a.id === addressId)
  if (!address) return
  editingAddress.value = address
  showAddressModal.value = true
}

function closeAddressModal() {
  showAddressModal.value = false
  editingAddress.value = null
}

async function handleAddressSaved() {
  await loadRacks()
  closeAddressModal()
}

async function loadSlotsForRack(rackId: string, forceRefresh = false) {
  if (!forceRefresh && slotsByRack.value[rackId]) return
  try {
    const slots = await getStorageSlots(rackId)
    slotsByRack.value = { ...slotsByRack.value, [rackId]: slots }
  } catch (err) {
    console.error(t('settings.storage.slotsLoadError'), err)
  }
}

// Rack CRUD
function openRackModal(rack?: StorageRack) {
  editingRack.value = rack ?? null
  rackForm.value = {
    name: rack?.name ?? '',
    storage_address_id: rack?.storage_address_id || defaultStorageAddressId.value
  }
  rackCreateCount.value = 1
  rackCreateBaseName.value = rack?.name ?? rackSuggestions.value[0] ?? t('settings.storage.rackBaseDefault')
  rackSlotNameOverrides.value = {}
  showRackModal.value = true
}

function openRackModalForLocation(storageAddressId: string) {
  editingRack.value = null
  rackForm.value = {
    name: '',
    storage_address_id: storageAddressId
  }
  expandedLocations.value = new Set([storageAddressId])
  const locationNode = groupedFilteredRacks.value.find((loc) => loc.id === storageAddressId)
  if (locationNode && locationNode.racks.length < 10) {
    expandedRacks.value = new Set(locationNode.racks.map((rack) => rack.id))
  } else {
    expandedRacks.value = new Set()
  }
  rackCreateCount.value = 1
  rackCreateBaseName.value = rackSuggestions.value[0] ?? t('settings.storage.rackBaseDefault')
  rackSlotNameOverrides.value = {}
  showRackModal.value = true
}

function closeRackModal() {
  showRackModal.value = false
  editingRack.value = null
}

async function saveRack() {
  const storageAddressId = rackForm.value.storage_address_id
  if (!storageAddressId) {
    toast.error(t('settings.storage.selectStorageLocationError'))
    return
  }
  isSaving.value = true
  try {
    if (editingRack.value) {
      const name = rackForm.value.name.trim()
      if (!name) return
      await updateStorageRack(editingRack.value.id, { name, storage_address_id: storageAddressId })
      toast.success(t('settings.storage.toastRackUpdated'))
    } else {
      const names = rackGeneratedNames.value
      if (names.length === 0) return
      for (const name of names) {
        const initialSlotNameForRack = (rackSlotNameOverrides.value[name] ?? `${getSlotPrefix(name)}1`).trim()
        if (!initialSlotNameForRack) {
          toast.error(t('settings.storage.slotNameRequired'))
          return
        }
        await createStorageRack({
          department_id: departmentId.value,
          storage_address_id: storageAddressId,
          name,
          initial_slot_name: initialSlotNameForRack
        })
      }
      toast.success(t('settings.storage.toastRacksCreated', { count: names.length }))
    }
    closeRackModal()
    await loadRacks(storageAddressId)
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.storage.saveError'))
  } finally {
    isSaving.value = false
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

function decrementRackCreateCount() {
  rackCreateCount.value = Math.max(1, rackCreateCount.value - 1)
}

function incrementRackCreateCount() {
  rackCreateCount.value = Math.min(99, rackCreateCount.value + 1)
}

function confirmDeleteRack(rack: StorageRack) {
  deletingRack.value = rack
  showDeleteRackConfirm.value = true
}

async function executeDeleteRack() {
  if (!deletingRack.value) return
  isDeleting.value = true
  try {
    await deleteStorageRack(deletingRack.value.id)
    toast.success(t('settings.storage.toastRackDeleted'))
    showDeleteRackConfirm.value = false
    deletingRack.value = null
    await loadRacks()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.storage.deleteError'))
  } finally {
    isDeleting.value = false
  }
}

// Slot CRUD
function openSlotModal(rack: StorageRack, slot?: StorageSlot) {
  slotRack.value = rack
  editingSlot.value = slot ?? null
  slotForm.value = { name: slot?.name ?? '' }
  slotCreateCount.value = 1
  slotCreateBaseName.value = getSlotPrefix(rack.name)
  showSlotModal.value = true
  const locationId = rack.storage_address_id || '__unknown__'
  expandedLocations.value = new Set([locationId])
  const locationNode = groupedFilteredRacks.value.find((loc) => loc.id === locationId)
  if (locationNode && locationNode.racks.length < 10) {
    expandedRacks.value = new Set(locationNode.racks.map((entry) => entry.id))
  } else {
    expandedRacks.value = new Set([rack.id])
  }
  loadSlotsForRack(rack.id)
}

function closeSlotModal() {
  showSlotModal.value = false
  slotRack.value = null
  editingSlot.value = null
}

async function saveSlot() {
  const rack = slotRack.value
  if (!rack) return
  const rackId = rack.id
  isSaving.value = true
  try {
    if (editingSlot.value) {
      const name = slotForm.value.name.trim()
      if (!name) return
      await updateStorageSlot(editingSlot.value.id, { name })
      toast.success(t('settings.storage.toastSlotUpdated'))
    } else {
      const names = slotGeneratedNames.value
      if (names.length === 0) return
      for (const name of names) {
        await createStorageSlot({ rack_id: rackId, name })
      }
      toast.success(t('settings.storage.toastSlotsCreated', { count: names.length }))
    }
    closeSlotModal()
    await loadSlotsForRack(rackId, true)
    slotsByRack.value = { ...slotsByRack.value }
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.storage.saveError'))
  } finally {
    isSaving.value = false
  }
}

function decrementSlotCreateCount() {
  slotCreateCount.value = Math.max(1, slotCreateCount.value - 1)
}

function incrementSlotCreateCount() {
  slotCreateCount.value = Math.min(99, slotCreateCount.value + 1)
}

function confirmDeleteSlot(slot: StorageSlot) {
  deletingSlot.value = slot
  showDeleteSlotConfirm.value = true
}

async function executeDeleteSlot() {
  const slot = deletingSlot.value
  if (!slot) return
  isDeleting.value = true
  try {
    await deleteStorageSlot(slot.id)
    toast.success(t('settings.storage.toastSlotDeleted'))
    showDeleteSlotConfirm.value = false
    deletingSlot.value = null
    const rackId = slot.rack_id
    slotsByRack.value[rackId] = (slotsByRack.value[rackId] ?? []).filter(s => s.id !== slot.id)
    slotsByRack.value = { ...slotsByRack.value }
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.storage.deleteError'))
  } finally {
    isDeleting.value = false
  }
}

async function loadRacks(preferredOpenLocationId?: string) {
  isLoading.value = true
  try {
    const addressResult = await getAddresses(departmentId.value, 'storage').catch(() => ({ addresses: [] as Address[] }))
    storageAddresses.value = addressResult.addresses || []
    racks.value = await getStorageRacks(departmentId.value)
    slotsByRack.value = {}
    const targetLocationId = preferredOpenLocationId || primaryStorageAddressId.value
    if (targetLocationId) {
      expandedLocations.value = new Set([targetLocationId])
      const locationNode = groupedFilteredRacks.value.find((loc) => loc.id === targetLocationId)
      if (locationNode && locationNode.racks.length < 10) {
        expandedRacks.value = new Set(locationNode.racks.map((rack) => rack.id))
      } else {
        expandedRacks.value = new Set()
      }
    } else {
      expandedLocations.value = new Set()
      expandedRacks.value = new Set()
    }
    await Promise.all(racks.value.map((r) => loadSlotsForRack(r.id)))
  } catch (err) {
    console.error(t('settings.storage.loadError'), err)
    toast.error(t('settings.storage.loadError'))
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadRacks()
})
</script>

<style scoped>
.storage-settings {
  min-height: 500px;
}

.settings-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}

.settings-header h1 {
  font-size: 24px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.settings-header .subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.warning-text {
  color: #b45309 !important;
  margin-top: 6px !important;
}

.primary-storage-hint {
  color: #0f766e !important;
  font-weight: 600;
  margin-top: 6px !important;
}

/* Primary buttons use shared ui/buttons.css */

.search-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.search-input-wrapper {
  position: relative;
  flex: 1;
  max-width: 400px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
}

/* Search input base uses shared ui/page-layout.css */

.rack-count {
  font-size: 13px;
  color: #6b7280;
}

.racks-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.location-group {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  background: #fff;
}

.location-header {
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid #e5e7eb;
  padding: 8px 10px;
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
  text-align: left;
  padding: 4px;
}

.location-caret {
  display: inline-flex;
  font-size: 11px;
  color: #6b7280;
  transition: transform 0.15s ease;
}

.location-caret.expanded {
  transform: rotate(90deg);
}

.location-title {
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

.set-primary-btn {
  border: 1px solid #99f6e4;
  background: #ecfeff;
  color: #0f766e;
  border-radius: 8px;
  padding: 6px 10px;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.set-primary-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.location-racks {
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rack-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.2s;
}

.rack-card:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.rack-card.expanded {
  border-color: #14b8a6;
  box-shadow: 0 0 0 1px rgba(20, 184, 166, 0.2);
}

.rack-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  cursor: pointer;
  transition: background 0.2s;
}

.rack-header:hover {
  background: #f9fafb;
}

.rack-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

.expand-btn {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  transition: transform 0.2s;
  flex-shrink: 0;
}

.expand-btn.expanded {
  transform: rotate(90deg);
}

.rack-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ccfbf1;
  color: #0d9488;
  border-radius: 10px;
  flex-shrink: 0;
}

.rack-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.rack-name {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.rack-meta {
  font-size: 12px;
  color: #6b7280;
}

.rack-actions {
  display: flex;
  gap: 4px;
  opacity: 0.8;
}

.rack-header:hover .rack-actions {
  opacity: 1;
}

.rack-slots {
  border-top: 1px solid #f3f4f6;
  padding: 8px 16px 12px;
  background: #fafafa;
}

.slot-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  background: white;
  border-radius: 8px;
  margin-bottom: 6px;
  border: 1px solid #f3f4f6;
}

.slot-item:last-child {
  margin-bottom: 0;
}

.slot-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.slot-icon {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
  color: #6b7280;
  border-radius: 6px;
}

.slot-name {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.slot-actions {
  display: flex;
  gap: 4px;
}

.slots-empty {
  padding: 16px;
  text-align: center;
  color: #9ca3af;
  font-size: 13px;
}

.slots-empty p {
  margin: 0 0 8px 0;
}

.btn-link {
  background: none;
  border: none;
  color: #0d9488;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  text-decoration: underline;
}

.btn-link:hover {
  color: #0f766e;
}

/* Expand Transition */
.expand-enter-active,
.expand-leave-active {
  transition: all 0.25s ease;
  overflow: hidden;
}

.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  max-height: 0;
}

.expand-enter-to,
.expand-leave-from {
  opacity: 1;
  max-height: 400px;
}

/* Empty State */
.empty-state {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
}

.empty-icon {
  width: 80px;
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0fdfa;
  border-radius: 50%;
  color: #14b8a6;
  margin-bottom: 16px;
}

/* Empty-state title/text typography uses shared ui/states.css */

/* Loading state base uses shared ui/states.css */

/* Modal overlay/dialog base uses shared ui/modals.css */

.modal-context {
  font-size: 13px;
  color: #6b7280;
  margin: -8px 0 16px 0;
}

/* Form group base uses shared ui/forms.css */

.suggestion-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}

.suggestion-chip {
  padding: 6px 12px;
  font-size: 13px;
  background: #f0fdfa;
  color: #0d9488;
  border: 1px solid #99f6e4;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
}

.suggestion-chip:hover {
  background: #ccfbf1;
  border-color: #5eead4;
}

/* Form input base uses shared ui/forms.css */

.count-stepper {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
}

.stepper-btn {
  width: 30px;
  height: 30px;
  border: 1px solid #d1d5db;
  background: #f9fafb;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 700;
  color: #374151;
}

.stepper-value {
  min-width: 34px;
  text-align: center;
  font-weight: 700;
  color: #111827;
}

.stepper-hint {
  margin: 0 0 8px 0;
  font-size: 12px;
  color: #6b7280;
}

.generated-slot-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.generated-slot-chip {
  padding: 5px 10px;
  border-radius: 999px;
  background: #ecfeff;
  border: 1px solid #a5f3fc;
  color: #0f766e;
  font-size: 12px;
  font-weight: 600;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
}

/* Secondary buttons use shared ui/buttons.css */

.confirm-dialog {
  background: white;
  border-radius: 12px;
  padding: 24px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.confirm-dialog h3 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 12px 0;
}

.confirm-dialog p {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 8px 0;
}

.confirm-dialog .warning {
  background: #fef3c7;
  color: #92400e;
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 13px;
}

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
}

/* Danger buttons use shared ui/buttons.css */
</style>
