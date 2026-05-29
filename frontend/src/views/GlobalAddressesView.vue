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
          {{ t('common.refresh') }}
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
              <th>{{ t('common.name') }}</th>
              <th>{{ t('globalAddressesPage.tableEmail') }}</th>
              <th>{{ t('globalAddressesPage.tablePhone') }}</th>
              <th>{{ t('globalAddressesPage.tableCity') }}</th>
              <th>{{ t('common.status') }}</th>
              <th>{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="address in globalAddresses" :key="address.id">
              <td>{{ address.company || '-' }}</td>
              <td>{{ address.name || '-' }}</td>
              <td>{{ address.email || '-' }}</td>
              <td>{{ address.phone || '-' }}</td>
              <td>{{ address.city_line || '-' }}</td>
              <td>-</td>
              <td class="actions">
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
      :department-id="GLOBAL_DEPARTMENT_ID"
      :address="editingGlobalAddress"
      :edit-address-id="editingGlobalAddress?.id || null"
      default-type="supplier"
      api-mode="global"
      @close="closeGlobalAddressModal"
      @saved="handleGlobalAddressSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { deleteGlobalAddress, getGlobalAddresses, type GlobalAddress } from '@/api/globalAddresses'
import AddressModal from '@/components/AddressModal.vue'

const GLOBAL_DEPARTMENT_ID = 'GLOBAL000000'
const { t } = useI18n()

const globalLoading = ref(false)
const globalError = ref<string | null>(null)
const globalSuccess = ref<string | null>(null)
const globalAddresses = ref<GlobalAddress[]>([])
const globalSearch = ref('')
const isGlobalAddressModalOpen = ref(false)
const editingGlobalAddress = ref<GlobalAddress | null>(null)

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

onMounted(() => {
  loadGlobalAddresses()
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
  gap: 8px;
}
</style>
