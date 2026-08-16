<template>
  <div
    class="department-address-kind-panel"
    :class="`department-address-kind-panel--${addressKind}`"
    :data-onboarding="
      addressKind === 'storage' ? 'settings-dept-storage-panel' : 'settings-dept-billing-panel'
    "
  >
    <div class="panel-toolbar">
      <EButton
        v-if="addressKind === 'storage' || filteredAddresses.length === 0"
        variant="primary"
        size="small"
        @click="openAddressModal()"
      >
        <v-icon icon="mdi-plus" start size="18" />
        {{ t('common.add') }}
      </EButton>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="inline"
      :message="t('settings.myDepartment.loadingAddresses')"
    />

    <template v-else-if="addressKind === 'storage'">
      <div v-if="filteredAddresses.length > 0" class="storage-list">
        <div
          v-for="addr in filteredAddresses"
          :key="addr.id"
          class="storage-item"
          :class="{ 'is-primary': addr.is_primary }"
        >
          <div class="storage-item-main">
            <div class="storage-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M20 7H4C2.9 7 2 7.9 2 9V19C2 20.1 2.9 21 4 21H20C21.1 21 22 20.1 22 19V9C22 7.9 21.1 7 20 7Z" fill="currentColor" />
                <path d="M12 3L2 7H22L12 3Z" fill="currentColor" opacity="0.6" />
              </svg>
            </div>
            <div class="storage-info">
              <div class="storage-name-row">
                <span class="storage-name">{{ addr.name || addr.street_line }}</span>
                <span v-if="addr.is_primary" class="primary-badge">{{ t('settings.myDepartment.primaryShort') }}</span>
              </div>
              <span class="storage-address">{{ addr.full_address }}</span>
            </div>
            <div class="storage-actions">
              <button
                v-if="!addr.is_primary"
                type="button"
                class="action-btn"
                :title="t('settings.myDepartment.titleSetPrimary')"
                @click="makePrimary(addr)"
              >
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path
                    d="M8 1L10 5.5L15 6L11.5 9.5L12.5 14.5L8 12L3.5 14.5L4.5 9.5L1 6L6 5.5L8 1Z"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
              <button type="button" class="action-btn" :title="t('settings.myDepartment.titleEdit')" @click="openAddressModal(addr)">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path
                    d="M11.3333 2C11.5084 1.82489 11.7163 1.68601 11.9444 1.59124C12.1726 1.49648 12.4163 1.44775 12.6625 1.44775C12.9087 1.44775 13.1524 1.49648 13.3806 1.59124C13.6087 1.68601 13.8166 1.82489 13.9917 2C14.1668 2.17511 14.3057 2.383 14.4005 2.61117C14.4952 2.83934 14.5439 3.08305 14.5439 3.32917C14.5439 3.57529 14.4952 3.819 14.4005 4.04717C14.3057 4.27534 14.1668 4.48323 13.9917 4.65833L5.325 13.325L2 14L2.675 10.675L11.3333 2Z"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
              <button type="button" class="action-btn delete" :title="t('settings.myDepartment.titleDelete')" @click="deleteAddressItem(addr)">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path
                    d="M2 4H14M5 4V2H11V4M6 7V12M10 7V12M3 4L4 14H12L13 4H3Z"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </div>
          </div>

          <div v-if="addr.has_coordinates" class="storage-map-accordion">
            <button
              type="button"
              class="map-accordion-toggle"
              :aria-expanded="expandedMaps.has(addr.id)"
              @click="toggleMap(addr.id)"
            >
              <span class="map-accordion-caret" :class="{ expanded: expandedMaps.has(addr.id) }">▶</span>
              <span>{{ t('settings.myDepartment.mapAccordionLabel') }}</span>
            </button>
            <div v-if="expandedMaps.has(addr.id)" class="map-accordion-body">
              <MapView
                :latitude="addr.latitude"
                :longitude="addr.longitude"
                :editable="false"
                :interactive="false"
                :use-swiss-projection="true"
                :show-layer-control="true"
                :show-external-map-links="true"
                height="300px"
                :zoom="21.7"
              />
            </div>
          </div>
        </div>
      </div>

      <div v-else class="empty-storage">
        <p>{{ t('settings.myDepartment.emptyStorage') }}</p>
        <EButton variant="primary" @click="openAddressModal()">
          {{ t('settings.myDepartment.addFirstStorage') }}
        </EButton>
      </div>
    </template>

    <template v-else>
      <div v-if="filteredAddresses.length > 0" class="billing-address">
        <div v-for="addr in filteredAddresses" :key="addr.id" class="address-card">
          <div class="address-content">
            <strong v-if="addr.company">{{ addr.company }}</strong>
            <span>{{ addr.full_address }}</span>
          </div>
          <div class="address-actions">
            <button type="button" class="action-btn" :title="t('common.edit')" @click="openAddressModal(addr)">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path
                  d="M11.3333 2C11.5084 1.82489 11.7163 1.68601 11.9444 1.59124C12.1726 1.49648 12.4163 1.44775 12.6625 1.44775C12.9087 1.44775 13.1524 1.49648 13.3806 1.59124C13.6087 1.68601 13.8166 1.82489 13.9917 2C14.1668 2.17511 14.3057 2.383 14.4005 2.61117C14.4952 2.83934 14.5439 3.08305 14.5439 3.32917C14.5439 3.57529 14.4952 3.819 14.4005 4.04717C14.3057 4.27534 14.1668 4.48323 13.9917 4.65833L5.325 13.325L2 14L2.675 10.675L11.3333 2Z"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </button>
            <button type="button" class="action-btn delete" :title="t('common.delete')" @click="deleteAddressItem(addr)">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path
                  d="M2 4H14M5 4V2H11V4M6 7V12M10 7V12M3 4L4 14H12L13 4H3Z"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
      <p v-else class="empty-billing">{{ t('settings.myDepartment.emptyBilling') }}</p>
    </template>

    <AddressModal
      v-if="isAddressModalOpen"
      :department-id="departmentId"
      :address="editingAddress"
      :default-type="addressKind"
      @close="closeAddressModal"
      @saved="handleAddressSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { getAddresses, deleteAddress as apiDeleteAddress, setAddressPrimary, type Address } from '@/api/addresses'
import MapView from '@/components/MapView.vue'
import AddressModal from '@/components/AddressModal.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton } from '@/components/form/base'

const props = defineProps<{
  departmentId: string
  addressKind: 'storage' | 'billing'
}>()

const emit = defineEmits<{
  changed: []
}>()

const { t, te } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const addresses = ref<Address[]>([])
const isLoading = ref(false)
const isAddressModalOpen = ref(false)
const editingAddress = ref<Address | null>(null)
const expandedMaps = ref(new Set<string>())

const filteredAddresses = computed(() =>
  addresses.value.filter((a) => a.type === props.addressKind)
)

function addressTypeLabel(type: string): string {
  const path = `settings.addressForm.types.${type}` as const
  return te(path) ? t(path) : type
}

function toggleMap(addressId: string) {
  const next = new Set(expandedMaps.value)
  if (next.has(addressId)) next.delete(addressId)
  else next.add(addressId)
  expandedMaps.value = next
}

async function loadAddresses() {
  if (!props.departmentId) return
  isLoading.value = true
  try {
    const result = await getAddresses(props.departmentId)
    addresses.value = result.addresses
  } catch (err: unknown) {
    console.error(err)
    addresses.value = []
  } finally {
    isLoading.value = false
  }
}

function openAddressModal(address?: Address) {
  editingAddress.value = address || null
  isAddressModalOpen.value = true
}

function closeAddressModal() {
  isAddressModalOpen.value = false
  editingAddress.value = null
}

async function handleAddressSaved() {
  await loadAddresses()
  emit('changed')
  closeAddressModal()
}

async function deleteAddressItem(address: Address) {
  const typeLabel = addressTypeLabel(address.type)
  const ok = await confirm.confirm({
    title: t('settings.myDepartment.deleteAddressConfirmTitle', { type: typeLabel }),
    message: t('settings.myDepartment.deleteAddressConfirmMessage', {
      name: address.name || address.street_line,
    }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await apiDeleteAddress(address.id)
    await loadAddresses()
    emit('changed')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('settings.myDepartment.toastDeleteAddressError'))
  }
}

async function makePrimary(address: Address) {
  try {
    await setAddressPrimary(address.id)
    await loadAddresses()
    emit('changed')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('settings.myDepartment.toastSetPrimaryAddressError'))
  }
}

watch(
  () => props.departmentId,
  () => {
    void loadAddresses()
  },
  { immediate: true }
)

defineExpose({
  reload: loadAddresses,
  count: computed(() => filteredAddresses.value.length),
})
</script>

<style scoped>
.panel-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 12px;
  min-height: 32px;
}

.storage-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.storage-item {
  display: flex;
  flex-direction: column;
  padding: 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}
.storage-item-main {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}
.storage-item.is-primary {
  border-color: #3b82f6;
  background: #f0f7ff;
}
.storage-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e5e7eb;
  border-radius: 8px;
  color: #6b7280;
  flex-shrink: 0;
}
.storage-item.is-primary .storage-icon {
  background: #dbeafe;
  color: #3b82f6;
}
.storage-info {
  flex: 1;
  min-width: 0;
}
.storage-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.storage-name {
  font-weight: 600;
  color: #1f2937;
  font-size: 15px;
}
.primary-badge {
  padding: 2px 8px;
  background: #3b82f6;
  color: white;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
}
.storage-address {
  display: block;
  font-size: 13px;
  color: #4b5563;
  margin-top: 6px;
}
.storage-actions,
.address-actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}
.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
}
.action-btn:hover {
  background: #f3f4f6;
  color: #1f2937;
}
.action-btn.delete:hover {
  background: #fef2f2;
  color: #dc2626;
}

.storage-map-accordion {
  margin-top: 12px;
  border-top: 1px solid #e5e7eb;
  padding-top: 12px;
}
.map-accordion-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  background: transparent;
  border: none;
  cursor: pointer;
  color: #4b5563;
  font-size: 13px;
  font-weight: 500;
}
.map-accordion-caret {
  display: inline-flex;
  font-size: 10px;
  color: #9ca3af;
  transition: transform 0.15s ease;
}
.map-accordion-caret.expanded {
  transform: rotate(90deg);
}
.map-accordion-body {
  margin-top: 12px;
}

.empty-storage {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 28px 16px;
  text-align: center;
  color: #9ca3af;
}
.empty-storage p {
  margin: 0;
}

.billing-address {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.address-card {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  gap: 16px;
}
.address-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.address-content strong {
  font-size: 15px;
  color: #1f2937;
}
.address-content span {
  font-size: 14px;
  color: #6b7280;
}
.empty-billing {
  color: #6b7280;
  font-style: italic;
  font-size: 14px;
  margin: 0;
}
</style>
