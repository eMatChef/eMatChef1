<template>
  <div class="global-addresses-page">
    <header class="page-header">
      <h1>{{ t('globalAddressesPage.title') }}</h1>
      <p>
        {{ t('globalAddressesPage.subtitle') }}
      </p>
    </header>

    <section class="card">
      <div class="title-row">
        <h2>{{ t('globalAddressesPage.supplierSectionTitle') }}</h2>
      </div>
      <p class="description">
        {{ t('globalAddressesPage.supplierSectionDescription') }}
      </p>

      <div class="controls">
        <button class="btn btn-primary" :disabled="supplierLoading" @click="openCreateSupplierModal">
          {{ t('globalAddressesPage.newSupplierCompany') }}
        </button>
        <button class="btn btn-secondary" :disabled="supplierLoading" @click="loadSupplierCompanies">
          {{ t('globalAddressesPage.refresh') }}
        </button>
      </div>

      <p v-if="supplierLoading">{{ t('globalAddressesPage.loading') }}</p>
      <p v-else-if="supplierCompanies.length === 0">{{ t('globalAddressesPage.supplierEmpty') }}</p>

      <table v-if="supplierCompanies.length > 0">
        <thead>
          <tr>
            <th>{{ t('globalAddressesPage.tableCompany') }}</th>
            <th>{{ t('globalAddressesPage.supplierTableKey') }}</th>
            <th>{{ t('globalAddressesPage.tableStatus') }}</th>
            <th>{{ t('globalAddressesPage.supplierTableMembers') }}</th>
            <th>{{ t('globalAddressesPage.tableActions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="company in supplierCompanies" :key="company.id">
            <td>{{ company.name }}</td>
            <td>{{ company.manufacturer_key || '–' }}</td>
            <td>{{ company.status }}</td>
            <td>{{ company.membership_count ?? 0 }}</td>
            <td class="actions">
              <button
                class="btn btn-secondary btn-inline"
                :disabled="supplierLoading"
                @click="openEditSupplier(company)"
              >
                {{ t('common.edit') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="card">
      <div class="title-row">
        <h2>{{ t('globalAddressesPage.sectionTitle') }}</h2>
        <span class="badge">{{ t('globalAddressesPage.roleBadge') }}</span>
      </div>
      <p class="description">
        {{ t('globalAddressesPage.sectionDescription') }}
      </p>

      <div class="controls">
        <button class="btn btn-primary" :disabled="globalLoading" @click="openCreateGlobalAddressModal">
          {{ t('globalAddressesPage.newAddress') }}
        </button>
        <input
          v-model.trim="globalSearch"
          class="search-input"
          type="text"
          :placeholder="t('globalAddressesPage.searchPlaceholder')"
          @keyup.enter="loadGlobalAddresses"
        />
        <button class="btn btn-secondary" :disabled="globalLoading" @click="loadGlobalAddresses">
          {{ t('globalAddressesPage.refresh') }}
        </button>
      </div>

      <p v-if="globalError" class="error">{{ globalError }}</p>
      <p v-if="globalSuccess" class="success">{{ globalSuccess }}</p>

      <div class="preview">
        <h3>{{ t('globalAddressesPage.entries') }}</h3>
        <p v-if="globalLoading">{{ t('globalAddressesPage.loading') }}</p>
        <p v-else-if="globalAddresses.length === 0">{{ t('globalAddressesPage.empty') }}</p>

        <table v-if="globalAddresses.length > 0">
          <thead>
            <tr>
              <th>{{ t('globalAddressesPage.tableCompany') }}</th>
              <th>{{ t('globalAddressesPage.tableName') }}</th>
              <th>{{ t('globalAddressesPage.tableEmail') }}</th>
              <th>{{ t('globalAddressesPage.tablePhone') }}</th>
              <th>{{ t('globalAddressesPage.tableCity') }}</th>
              <th>{{ t('globalAddressesPage.tableActions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="address in globalAddresses" :key="address.id">
              <td>{{ address.company || '-' }}</td>
              <td>{{ address.name || '-' }}</td>
              <td>{{ address.email || '-' }}</td>
              <td>{{ address.phone || '-' }}</td>
              <td>{{ address.city_line || '-' }}</td>
              <td class="actions">
                <button
                  class="btn btn-primary btn-inline"
                  :disabled="globalLoading"
                  @click="startPromote(address)"
                >
                  {{ t('globalAddressesPage.promoteToSupplier') }}
                </button>
                <button
                  class="btn btn-secondary btn-inline"
                  :disabled="globalLoading"
                  @click="startGlobalEdit(address)"
                >
                  {{ t('common.edit') }}
                </button>
                <button
                  class="btn btn-danger btn-inline"
                  :disabled="globalLoading"
                  @click="removeGlobalAddress(address.id)"
                >
                  {{ t('common.delete') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <AddressModal
      v-if="isGlobalAddressModalOpen"
      department-id=""
      :address="editingGlobalAddress"
      :edit-address-id="editingGlobalAddress?.id || null"
      default-type="supplier"
      api-mode="global"
      @close="closeGlobalAddressModal"
      @saved="handleGlobalAddressSaved"
    />

    <SupplierCompanyOnboardModal
      v-if="isCreateSupplierModalOpen"
      :title="t('globalAddressesPage.supplierModal.createTitle')"
      :submit-label="t('globalAddressesPage.supplierModal.createSubmit')"
      @close="closeCreateSupplierModal"
      @submit="handleCreateSupplier"
    />

    <SupplierCompanyOnboardModal
      v-if="promoteTarget"
      :title="t('globalAddressesPage.supplierModal.promoteTitle')"
      :submit-label="t('globalAddressesPage.supplierModal.promoteSubmit')"
      :initial-name="promoteTarget.company || promoteTarget.name || ''"
      :show-name-field="false"
      :manufacturer-key-placeholder="promoteTarget.company || ''"
      @close="closePromoteModal"
      @submit="handlePromote"
    />
    <SupplierCompanyAdminModal
      v-if="editingSupplier"
      :company="editingSupplier"
      @close="closeEditSupplier"
      @saved="handleSupplierSaved"
      @deleted="handleSupplierDeleted"
    />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  createAdminSupplierCompany,
  listAdminSupplierCompanies,
  promoteGlobalAddressToSupplierCompany,
  type AdminSupplierCompany,
} from '@/api/adminSupplierCompanies'
import { deleteGlobalAddress, getGlobalAddresses, type GlobalAddress } from '@/api/globalAddresses'
import AddressModal from '@/components/AddressModal.vue'
import SupplierCompanyOnboardModal from '@/components/supplier/SupplierCompanyOnboardModal.vue'
import SupplierCompanyAdminModal from '@/components/supplier/SupplierCompanyAdminModal.vue'

const { t } = useI18n()

const globalLoading = ref(false)
const globalError = ref<string | null>(null)
const globalSuccess = ref<string | null>(null)
const globalAddresses = ref<GlobalAddress[]>([])
const globalSearch = ref('')
const isGlobalAddressModalOpen = ref(false)
const editingGlobalAddress = ref<GlobalAddress | null>(null)

const supplierLoading = ref(false)
const supplierCompanies = ref<AdminSupplierCompany[]>([])
const isCreateSupplierModalOpen = ref(false)
const editingSupplier = ref<AdminSupplierCompany | null>(null)
const promoteTarget = ref<GlobalAddress | null>(null)

async function loadGlobalAddresses() {
  globalLoading.value = true
  globalError.value = null
  try {
    const response = await getGlobalAddresses(globalSearch.value)
    globalAddresses.value = response.addresses
  } catch (err: any) {
    globalError.value = err?.response?.data?.error || t('globalAddressesPage.errorLoad')
  } finally {
    globalLoading.value = false
  }
}

async function loadSupplierCompanies() {
  supplierLoading.value = true
  try {
    const response = await listAdminSupplierCompanies()
    supplierCompanies.value = response.supplier_companies
  } catch (err: any) {
    globalError.value = err?.response?.data?.error || t('globalAddressesPage.supplierErrorLoad')
  } finally {
    supplierLoading.value = false
  }
}

function startGlobalEdit(address: GlobalAddress) {
  editingGlobalAddress.value = address
  isGlobalAddressModalOpen.value = true
  globalSuccess.value = null
  globalError.value = null
}

function openCreateGlobalAddressModal() {
  editingGlobalAddress.value = null
  isGlobalAddressModalOpen.value = true
  globalSuccess.value = null
  globalError.value = null
}

function closeGlobalAddressModal() {
  isGlobalAddressModalOpen.value = false
  editingGlobalAddress.value = null
}

async function handleGlobalAddressSaved() {
  closeGlobalAddressModal()
  await loadGlobalAddresses()
  globalSuccess.value = t('globalAddressesPage.successSaved')
}

async function removeGlobalAddress(id: string) {
  const confirmed = window.confirm(t('globalAddressesPage.confirmDelete'))
  if (!confirmed) return

  globalLoading.value = true
  globalError.value = null
  globalSuccess.value = null
  try {
    await deleteGlobalAddress(id)
    globalSuccess.value = t('globalAddressesPage.successDeleted')
    if (editingGlobalAddress.value?.id === id) {
      closeGlobalAddressModal()
    }
    await loadGlobalAddresses()
  } catch (err: any) {
    globalError.value = err?.response?.data?.error || t('globalAddressesPage.errorDelete')
  } finally {
    globalLoading.value = false
  }
}

function openEditSupplier(company: AdminSupplierCompany) {
  editingSupplier.value = company
  globalError.value = null
}

function closeEditSupplier() {
  editingSupplier.value = null
}

async function handleSupplierSaved() {
  await loadSupplierCompanies()
}

async function handleSupplierDeleted() {
  editingSupplier.value = null
  await Promise.all([loadSupplierCompanies(), loadGlobalAddresses()])
}

function openCreateSupplierModal() {
  isCreateSupplierModalOpen.value = true
  globalError.value = null
}

function closeCreateSupplierModal() {
  isCreateSupplierModalOpen.value = false
}

async function handleCreateSupplier(payload: {
  name: string
  manufacturer_key: string
  admin_user_email: string
}) {
  globalLoading.value = true
  globalError.value = null
  try {
    await createAdminSupplierCompany({
      name: payload.name,
      manufacturer_key: payload.manufacturer_key || null,
      status: 'active',
      admin_user_email: payload.admin_user_email || null,
    })
    closeCreateSupplierModal()
    globalSuccess.value = t('globalAddressesPage.supplierCreateSuccess')
    await loadSupplierCompanies()
  } catch (err: any) {
    globalError.value = err?.response?.data?.error || t('globalAddressesPage.supplierErrorCreate')
  } finally {
    globalLoading.value = false
  }
}

function startPromote(address: GlobalAddress) {
  promoteTarget.value = address
  globalError.value = null
}

function closePromoteModal() {
  promoteTarget.value = null
}

async function handlePromote(payload: {
  name: string
  manufacturer_key: string
  admin_user_email: string
}) {
  if (!promoteTarget.value) return

  globalLoading.value = true
  globalError.value = null
  try {
    await promoteGlobalAddressToSupplierCompany(promoteTarget.value.id, {
      manufacturer_key: payload.manufacturer_key || null,
      admin_user_email: payload.admin_user_email || null,
      status: 'active',
    })
    closePromoteModal()
    globalSuccess.value = t('globalAddressesPage.promoteSuccess')
    await Promise.all([loadGlobalAddresses(), loadSupplierCompanies()])
  } catch (err: any) {
    globalError.value = err?.response?.data?.error || t('globalAddressesPage.promoteError')
  } finally {
    globalLoading.value = false
  }
}

onMounted(() => {
  loadGlobalAddresses()
  loadSupplierCompanies()
})
</script>

<style scoped>
.global-addresses-page {
  padding: 0;
  max-width: 1100px;
}

.page-header h1 {
  margin: 0;
  font-size: 1.75rem;
}

.page-header p {
  color: #6b7280;
  margin-top: 8px;
}

.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  margin-top: 16px;
}

.title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.title-row h2 {
  margin: 0;
}

.badge {
  font-size: 12px;
  color: #065f46;
  background: #d1fae5;
  padding: 4px 8px;
  border-radius: 999px;
}

.description {
  color: #4b5563;
}

.controls {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin: 14px 0;
}

.search-input {
  width: 280px;
}

.btn-inline {
  padding: 6px 10px;
  font-size: 12px;
}

.error {
  color: #b91c1c;
}

.success {
  color: #166534;
}

.preview h3 {
  margin-bottom: 8px;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

th,
td {
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  padding: 8px;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
