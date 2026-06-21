<template>
  <EDialog
    v-model="open"
    :max-width="560"
    :title="dialogTitle"
  >
    <p class="storage-qr-pdf-intro">{{ t('settings.storage.qrPdfIntro') }}</p>

    <ESelect
      v-if="pickLocation"
      v-model="internalAddressId"
      class="storage-qr-pdf-location-select"
      :items="locationSelectItems"
      :label="t('tasksPrint.exportStorageQrPdfPickLocation')"
      :disabled="isLoadingStructure || locationSelectItems.length === 0"
      hide-details="auto"
    />

    <div v-if="isLoadingStructure" class="storage-qr-pdf-loading">
      <div class="spinner"></div>
      <p>{{ t('common.loading') }}</p>
    </div>

    <template v-else-if="effectiveAddressId">
      <div class="storage-qr-pdf-toolbar">
        <button type="button" class="btn-link" @click="selectAll">{{ t('settings.storage.qrPdfSelectAll') }}</button>
        <span class="storage-qr-pdf-sep">·</span>
        <button type="button" class="btn-link" @click="selectNone">{{ t('settings.storage.qrPdfSelectNone') }}</button>
        <span class="storage-qr-pdf-count">{{ t('settings.storage.qrPdfSelectedCount', { count: selectedCount }) }}</span>
      </div>

      <div class="storage-qr-pdf-tree">
        <label class="storage-qr-pdf-node storage-qr-pdf-node--root">
          <input
            type="checkbox"
            :checked="isChecked('storage_address', effectiveAddressId)"
            @change="toggle('storage_address', effectiveAddressId, ($event.target as HTMLInputElement).checked)"
          />
          <span class="storage-qr-pdf-node-label">
            <span class="storage-qr-pdf-type">{{ t('settings.storage.qrPdfTypeLocation') }}</span>
            {{ effectiveLocationName }}
          </span>
        </label>

        <div v-for="rack in effectiveRacks" :key="rack.id" class="storage-qr-pdf-rack">
          <label class="storage-qr-pdf-node">
            <input
              type="checkbox"
              :checked="isChecked('storage_rack', rack.id)"
              @change="toggle('storage_rack', rack.id, ($event.target as HTMLInputElement).checked)"
            />
            <span class="storage-qr-pdf-node-label">
              <span class="storage-qr-pdf-type">{{ t('settings.storage.qrPdfTypeRack') }}</span>
              {{ rack.name }}
            </span>
          </label>

          <label
            v-for="slot in rack.slots"
            :key="slot.id"
            class="storage-qr-pdf-node storage-qr-pdf-node--slot"
          >
            <input
              type="checkbox"
              :checked="isChecked('storage_slot', slot.id)"
              @change="toggle('storage_slot', slot.id, ($event.target as HTMLInputElement).checked)"
            />
            <span class="storage-qr-pdf-node-label">
              <span class="storage-qr-pdf-type">{{ t('settings.storage.qrPdfTypeSlot') }}</span>
              {{ slot.name }}
            </span>
          </label>
        </div>

        <p v-if="effectiveRacks.length === 0" class="storage-qr-pdf-empty">{{ t('settings.storage.qrPdfNoRacks') }}</p>
      </div>
    </template>

    <p v-else-if="pickLocation && !isLoadingStructure && locationSelectItems.length === 0" class="storage-qr-pdf-empty">
      {{ t('settings.storage.needStorageLocationFirst') }}
    </p>

    <template #actions>
      <EButton variant="secondary" size="small" :disabled="isExporting" @click="close">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :disabled="!effectiveAddressId || selectedCount === 0 || isExporting || isLoadingStructure"
        :loading="isExporting"
        @click="exportPdf"
      >
        {{ isExporting ? t('settings.storage.qrPdfExporting') : t('settings.storage.qrPdfDownload') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog, ESelect } from '@/components/form/base'
import { getAddresses, type Address } from '@/api/addresses'
import { getStorageOverview } from '@/api/storageLocations'
import { downloadStorageQrPdf, type StorageQrSelectionItem, type StorageQrPdfRack } from '@/api/storageQr'
import { useToast } from '@/composables/useToast'

const props = withDefaults(defineProps<{
  modelValue: boolean
  departmentId: string
  addressId?: string
  locationName?: string
  racks?: StorageQrPdfRack[]
  /** Standort im Dialog wählen (z. B. Aufgaben → Drucken) */
  pickLocation?: boolean
}>(), {
  addressId: '',
  locationName: '',
  racks: () => [],
  pickLocation: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const { t } = useI18n()
const toast = useToast()
const isExporting = ref(false)
const isLoadingStructure = ref(false)
const selectedKeys = ref<Set<string>>(new Set())
const internalAddressId = ref('')
const internalLocationName = ref('')
const internalRacks = ref<StorageQrPdfRack[]>([])
const storageAddresses = ref<Address[]>([])

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const effectiveAddressId = computed(() => props.addressId || internalAddressId.value)
const effectiveLocationName = computed(() => props.locationName || internalLocationName.value)
const effectiveRacks = computed(() => (props.racks.length > 0 ? props.racks : internalRacks.value))

const dialogTitle = computed(() => {
  if (effectiveLocationName.value) {
    return t('settings.storage.qrPdfTitle', { location: effectiveLocationName.value })
  }
  return t('tasksPrint.exportStorageQrPdfTitle')
})

const locationSelectItems = computed(() =>
  storageAddresses.value.map((addr) => ({
    title: addr.name || addr.street_line || addr.full_address || addr.id,
    value: addr.id,
  })),
)

function addressLabel(address: Address): string {
  return address.name || address.street_line || address.full_address || address.id
}

function selectionKey(entityType: string, entityId: string): string {
  return `${entityType}|${entityId}`
}

function buildDefaultSelection(): Set<string> {
  const keys = new Set<string>()
  const addressId = effectiveAddressId.value
  if (addressId) {
    keys.add(selectionKey('storage_address', addressId))
  }
  for (const rack of effectiveRacks.value) {
    keys.add(selectionKey('storage_rack', rack.id))
    for (const slot of rack.slots) {
      keys.add(selectionKey('storage_slot', slot.id))
    }
  }
  return keys
}

async function loadAddresses() {
  if (!props.departmentId) {
    storageAddresses.value = []
    return
  }
  const result = await getAddresses(props.departmentId, 'storage').catch(() => ({ addresses: [] as Address[] }))
  storageAddresses.value = result.addresses || []
}

async function loadRacksForAddress(addressId: string) {
  if (!addressId || !props.departmentId) {
    internalRacks.value = []
    internalLocationName.value = ''
    return
  }

  const address = storageAddresses.value.find((a) => a.id === addressId)
  internalLocationName.value = address ? addressLabel(address) : ''

  isLoadingStructure.value = true
  try {
    const overview = await getStorageOverview(props.departmentId)
    internalRacks.value = (overview.racks || [])
      .filter((rack) => rack.storage_address_id === addressId)
      .map((rack) => ({
        id: rack.id,
        name: rack.name,
        slots: (rack.slots || [])
          .filter((slot) => slot.id)
          .map((slot) => ({ id: slot.id as string, name: slot.name })),
      }))
  } catch {
    internalRacks.value = []
    toast.error(t('settings.storage.loadError'))
  } finally {
    isLoadingStructure.value = false
  }
}

async function initializePicker() {
  isLoadingStructure.value = true
  try {
    await loadAddresses()
    if (storageAddresses.value.length === 1) {
      internalAddressId.value = storageAddresses.value[0].id
    } else if (!internalAddressId.value && storageAddresses.value.length > 0) {
      const primary = storageAddresses.value.find((a) => a.is_primary)
      internalAddressId.value = primary?.id || storageAddresses.value[0].id
    }
    if (internalAddressId.value) {
      await loadRacksForAddress(internalAddressId.value)
    } else {
      isLoadingStructure.value = false
    }
  } catch {
    isLoadingStructure.value = false
  }
}

watch(
  () => props.modelValue,
  (visible) => {
    if (!visible) return
    selectedKeys.value = buildDefaultSelection()
    if (props.pickLocation) {
      void initializePicker()
    }
  },
)

watch(
  () => [props.modelValue, effectiveAddressId.value, effectiveRacks.value] as const,
  ([visible]) => {
    if (visible) {
      selectedKeys.value = buildDefaultSelection()
    }
  },
  { deep: true },
)

watch(internalAddressId, (nextId, prevId) => {
  if (!props.pickLocation || !props.modelValue || nextId === prevId) return
  void loadRacksForAddress(nextId)
})

const selectedCount = computed(() => selectedKeys.value.size)

function isChecked(entityType: string, entityId: string): boolean {
  return selectedKeys.value.has(selectionKey(entityType, entityId))
}

function toggle(entityType: string, entityId: string, checked: boolean) {
  const key = selectionKey(entityType, entityId)
  const next = new Set(selectedKeys.value)
  if (checked) next.add(key)
  else next.delete(key)
  selectedKeys.value = next
}

function selectAll() {
  selectedKeys.value = buildDefaultSelection()
}

function selectNone() {
  selectedKeys.value = new Set()
}

function close() {
  open.value = false
}

function buildSelections(): StorageQrSelectionItem[] {
  return Array.from(selectedKeys.value).map((key) => {
    const [entity_type, entity_id] = key.split('|')
    return { entity_type, entity_id }
  })
}

async function exportPdf() {
  const addressId = effectiveAddressId.value
  const locationName = effectiveLocationName.value
  if (!props.departmentId || !addressId || selectedCount.value === 0 || isExporting.value) return
  isExporting.value = true
  try {
    const blob = await downloadStorageQrPdf(props.departmentId, addressId, buildSelections())
    const safeName = locationName.replace(/[/\\?%*:|"<>]/g, '-').replace(/\s+/g, '-').slice(0, 80)
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `lager-qr-${safeName || 'standort'}.pdf`
    anchor.click()
    URL.revokeObjectURL(url)
    toast.success(t('settings.storage.qrPdfSuccess'))
    close()
  } catch (err: any) {
    toast.error(err?.message || err?.response?.data?.error || t('settings.storage.qrPdfError'))
  } finally {
    isExporting.value = false
  }
}
</script>

<style scoped>
.storage-qr-pdf-intro {
  margin: 0 0 12px;
  font-size: 14px;
  color: #6b7280;
}

.storage-qr-pdf-location-select {
  margin-bottom: 14px;
}

.storage-qr-pdf-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px 0;
  color: #6b7280;
  font-size: 14px;
}

.storage-qr-pdf-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  font-size: 14px;
}

.storage-qr-pdf-sep {
  color: #d1d5db;
}

.storage-qr-pdf-count {
  margin-left: auto;
  color: #6b7280;
  font-size: 13px;
}

.storage-qr-pdf-tree {
  max-height: 360px;
  overflow-y: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 10px;
}

.storage-qr-pdf-node {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 6px 4px;
  cursor: pointer;
}

.storage-qr-pdf-node--root {
  font-weight: 600;
  border-bottom: 1px solid #f3f4f6;
  margin-bottom: 4px;
  padding-bottom: 8px;
}

.storage-qr-pdf-node--slot {
  margin-left: 28px;
}

.storage-qr-pdf-node input {
  margin-top: 3px;
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.storage-qr-pdf-node-label {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: baseline;
  line-height: 1.35;
}

.storage-qr-pdf-type {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #9ca3af;
}

.storage-qr-pdf-rack + .storage-qr-pdf-rack {
  margin-top: 4px;
  padding-top: 4px;
  border-top: 1px dashed #f3f4f6;
}

.storage-qr-pdf-empty {
  margin: 8px 0 0;
  font-size: 13px;
  color: #9ca3af;
}
</style>
