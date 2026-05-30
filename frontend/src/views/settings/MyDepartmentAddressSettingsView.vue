<template>
  <div class="settings-page">
    <div class="header">
      <h1>{{ pageTitle }}</h1>
      <p class="description">{{ pageDescription }}</p>
    </div>

    <div v-if="userDepartments.length > 1" class="card">
      <label class="label" for="department-select">{{ t('settings.common.selectDepartment') }}</label>
      <select id="department-select" v-model="selectedDepartmentId" class="input" @change="onDepartmentChange">
        <option v-for="dept in userDepartments" :key="dept.department_id" :value="dept.department_id">
          {{ dept.department?.name || dept.department_id }}
        </option>
      </select>
    </div>

    <div v-if="!effectiveDepartmentId" class="card">
      <p class="muted">{{ t('settings.myDepartment.noDepartmentSelected') }}</p>
    </div>

    <template v-else>
      <!-- Standorte (Abteilungsadressen Typ storage) -->
      <div v-if="addressKind === 'storage'" class="card info-card storage-section">
        <div class="card-header">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="card-icon">
            <path d="M20 7H4C2.9 7 2 7.9 2 9V19C2 20.1 2.9 21 4 21H20C21.1 21 22 20.1 22 19V9C22 7.9 21.1 7 20 7Z" fill="#3b82f6" />
            <path d="M12 3L2 7H22L12 3Z" fill="#60a5fa" />
          </svg>
          <h2>{{ t('settings.myDepartment.storageTitle', { n: storageAddresses.length }) }}</h2>
          <button type="button" class="add-storage-btn" @click="openAddressModal(undefined, 'storage')">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            {{ t('common.add') }}
          </button>
        </div>

        <div v-if="isLoadingAddresses" class="loading-storage">
          <div class="spinner-sm"></div>
          <span>{{ t('settings.myDepartment.loadingAddresses') }}</span>
        </div>

        <div v-else-if="storageAddresses.length > 0" class="storage-list">
          <div
            v-for="addr in storageAddresses"
            :key="addr.id"
            class="storage-item"
            :class="{ 'is-primary': addr.is_primary }"
          >
            <div class="storage-item-main">
              <div class="storage-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
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
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
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
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
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
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
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
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path
                    d="M8 2C5.24 2 3 4.24 3 7C3 10.75 8 14 8 14S13 10.75 13 7C13 4.24 10.76 2 8 2ZM8 8.5C7.17 8.5 6.5 7.83 6.5 7C6.5 6.17 7.17 5.5 8 5.5C8.83 5.5 9.5 6.17 9.5 7C9.5 7.83 8.83 8.5 8 8.5Z"
                    fill="currentColor"
                  />
                </svg>
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
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
            <path d="M20 7H4C2.9 7 2 7.9 2 9V19C2 20.1 2.9 21 4 21H20C21.1 21 22 20.1 22 19V9C22 7.9 21.1 7 20 7Z" fill="#d1d5db" />
            <path d="M12 3L2 7H22L12 3Z" fill="#e5e7eb" />
          </svg>
          <p>{{ t('settings.myDepartment.emptyStorage') }}</p>
          <button type="button" class="add-first-btn" @click="openAddressModal(undefined, 'storage')">
            {{ t('settings.myDepartment.addFirstStorage') }}
          </button>
        </div>

      </div>

      <!-- Rechnungsadresse -->
      <div v-else class="card info-card">
        <div class="card-header">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="card-icon">
            <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#3b82f6" />
            <path d="M7 7H17V9H7V7ZM7 11H17V13H7V11ZM7 15H13V17H7V15Z" fill="white" />
          </svg>
          <h2>{{ t('settings.myDepartment.billingTitle') }}</h2>
          <button v-if="billingAddresses.length === 0" type="button" class="add-storage-btn" @click="openAddressModal(undefined, 'billing')">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            {{ t('common.add') }}
          </button>
        </div>

        <div v-if="billingAddresses.length > 0" class="billing-address">
          <div v-for="addr in billingAddresses" :key="addr.id" class="address-card">
            <div class="address-content">
              <strong v-if="addr.company">{{ addr.company }}</strong>
              <span>{{ addr.full_address }}</span>
            </div>
            <div class="address-actions">
              <button type="button" class="action-btn" :title="t('common.edit')" @click="openAddressModal(addr)">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
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
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
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
      </div>
    </template>

    <AddressModal
      v-if="isAddressModalOpen"
      :department-id="modalDepartmentId"
      :address="editingAddress"
      :default-type="newAddressType"
      @close="closeAddressModal"
      @saved="handleAddressSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useDepartmentSettingsManagerAccess } from '@/composables/useDepartmentSettingsManagerAccess'
import { getAddresses, deleteAddress as apiDeleteAddress, setAddressPrimary, type Address } from '@/api/addresses'
import MapView from '@/components/MapView.vue'
import AddressModal from '@/components/AddressModal.vue'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const { t, te } = useI18n()

function addressTypeLabel(type: string): string {
  const path = `settings.addressForm.types.${type}` as const
  return te(path) ? t(path) : type
}

const selectedDepartmentId = ref<string | null>(null)
const { effectiveDepartmentId } = useDepartmentSettingsManagerAccess(selectedDepartmentId)

const addressKind = computed(() => (route.meta.addressKind === 'billing' ? 'billing' : 'storage'))

const pageTitle = computed(() =>
  addressKind.value === 'billing'
    ? t('settings.addressPages.billingTitle')
    : t('settings.addressPages.storageTitle')
)
const pageDescription = computed(() =>
  addressKind.value === 'billing'
    ? t('settings.addressPages.billingDescription')
    : t('settings.addressPages.storageDescription')
)

const userDepartments = computed(() => authStore.departments || [])

const addresses = ref<Address[]>([])
const isLoadingAddresses = ref(false)
const isAddressModalOpen = ref(false)
const editingAddress = ref<Address | null>(null)
const newAddressType = ref<string>('storage')

const storageAddresses = computed(() => addresses.value.filter((a) => a.type === 'storage'))
const billingAddresses = computed(() => addresses.value.filter((a) => a.type === 'billing'))

const expandedMaps = ref(new Set<string>())

function toggleMap(addressId: string) {
  const next = new Set(expandedMaps.value)
  if (next.has(addressId)) next.delete(addressId)
  else next.add(addressId)
  expandedMaps.value = next
}

const modalDepartmentId = computed(() => String(effectiveDepartmentId.value || selectedDepartmentId.value || ''))

async function loadAddresses(deptId: string) {
  isLoadingAddresses.value = true
  try {
    const result = await getAddresses(deptId)
    addresses.value = result.addresses
  } catch (err: unknown) {
    console.error('Fehler beim Laden der Adressen:', err)
    addresses.value = []
  } finally {
    isLoadingAddresses.value = false
  }
}

function openAddressModal(address?: Address, type: string = 'storage') {
  editingAddress.value = address || null
  newAddressType.value = type
  isAddressModalOpen.value = true
}

function closeAddressModal() {
  isAddressModalOpen.value = false
  editingAddress.value = null
}

async function handleAddressSaved() {
  const id = effectiveDepartmentId.value
  if (id) await loadAddresses(id)
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
    const id = effectiveDepartmentId.value
    if (id) await loadAddresses(id)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('settings.myDepartment.toastDeleteAddressError'))
  }
}

async function makePrimary(address: Address) {
  try {
    await setAddressPrimary(address.id)
    const id = effectiveDepartmentId.value
    if (id) await loadAddresses(id)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('settings.myDepartment.toastSetPrimaryAddressError'))
  }
}

async function onDepartmentChange() {
  if (!selectedDepartmentId.value) return
  const newDeptId = selectedDepartmentId.value
  await authStore.setActiveDepartment(newDeptId)
  const oldDeptId = route.params.departmentId as string | undefined
  if (oldDeptId && oldDeptId !== newDeptId) {
    const newPath = route.path.replace(`/${oldDeptId}`, `/${newDeptId}`)
    window.location.assign(newPath)
    return
  }
  await loadAddresses(newDeptId)
}

watch(
  [effectiveDepartmentId, () => authStore.departments?.length ?? 0],
  async ([deptId]) => {
    if (!deptId) return
    if (selectedDepartmentId.value !== deptId) {
      selectedDepartmentId.value = deptId
    }
    await loadAddresses(deptId)
  },
  { immediate: true }
)
</script>

<style scoped>
.settings-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.header h1 {
  margin: 0;
  font-size: 24px;
}
.description,
.muted {
  color: #6b7280;
}
.card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
.info-card {
  padding: 20px;
}
.label {
  display: block;
  margin-bottom: 8px;
  font-size: 13px;
  color: #6b7280;
}
.input {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px 12px;
  background: #fff;
  font-size: 14px;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}
.card-header h2 {
  margin: 0;
  flex: 1;
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}
.card-icon {
  flex-shrink: 0;
}

.storage-section .card-header {
  flex-wrap: wrap;
}

.add-storage-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  margin-left: auto;
  transition: background 0.2s;
}
.add-storage-btn:hover {
  background: #2563eb;
}

.loading-storage {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 40px;
  color: #6b7280;
}
.spinner-sm {
  width: 20px;
  height: 20px;
  border: 2px solid #e5e7eb;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
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
  transition: all 0.2s;
}
.storage-item-main {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}
.storage-item:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
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
.storage-actions {
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
  transition: all 0.2s;
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
.map-accordion-toggle:hover {
  color: #1f2937;
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
  padding: 40px 20px;
  text-align: center;
  color: #9ca3af;
}
.empty-storage p {
  margin: 0;
}
.add-first-btn {
  padding: 10px 20px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}
.add-first-btn:hover {
  background: #2563eb;
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
.address-actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}
.empty-billing {
  color: #6b7280;
  font-style: italic;
  font-size: 14px;
}
</style>
