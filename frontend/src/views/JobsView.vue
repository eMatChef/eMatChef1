<template>
  <div class="jobs-page">
    <header class="jobs-header">
      <h1>Jobs & Verwaltung</h1>
      <p>Globale Herstelleradressen verwalten und Wartungsjobs ausfuehren.</p>
    </header>

    <div class="tabs">
      <button
        class="tab-btn"
        :class="{ active: activeTab === 'global-addresses' }"
        @click="activeTab = 'global-addresses'"
      >
        Globale Adressen
      </button>
      <button
        v-if="isSuperAdmin"
        class="tab-btn"
        :class="{ active: activeTab === 'cleanup' }"
        @click="activeTab = 'cleanup'"
      >
        System Jobs
      </button>
    </div>

    <section v-if="activeTab === 'global-addresses'" class="job-card">
      <div class="job-title-row">
        <h2>Globale Herstelleradressen</h2>
        <span class="badge">ROLE_SU / ROLE_ORG / ROLE_SUB</span>
      </div>
      <p class="job-description">
        Diese Liste wird als globale Quelle fuer Hersteller/Lieferanten verwendet und kann hier zentral gepflegt werden.
      </p>

      <div class="controls">
        <button class="btn btn-primary" :disabled="globalLoading" @click="openCreateGlobalAddressModal">
          Neue Adresse
        </button>
        <input
          v-model.trim="globalSearch"
          class="search-input"
          type="text"
          placeholder="Nach Firma oder Name suchen..."
          @keyup.enter="loadGlobalAddresses"
        />
        <button class="btn btn-secondary" :disabled="globalLoading" @click="loadGlobalAddresses">
          Aktualisieren
        </button>
      </div>

      <p v-if="globalError" class="error">{{ globalError }}</p>
      <p v-if="globalSuccess" class="success">{{ globalSuccess }}</p>

      <div class="preview">
        <h3>Eintraege</h3>
        <p v-if="globalLoading">Lade...</p>
        <p v-else-if="globalAddresses.length === 0">Keine globalen Adressen vorhanden.</p>

        <table v-if="globalAddresses.length > 0">
          <thead>
            <tr>
              <th>Firma</th>
              <th>Name</th>
              <th>E-Mail</th>
              <th>Telefon</th>
              <th>Ort</th>
              <th>Status</th>
              <th>Aktionen</th>
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
                  Bearbeiten
                </button>
                <button
                  class="btn btn-danger btn-inline"
                  :disabled="globalLoading"
                  @click="removeGlobalAddress(address.id)"
                >
                  Loeschen
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="activeTab === 'cleanup' && isSuperAdmin" class="job-card">
      <div class="job-title-row">
        <h2>Unzugeordnete User bereinigen</h2>
        <span class="badge">app:cleanup-unassigned-users</span>
      </div>
      <p class="job-description">
        Loescht Benutzerkonten ohne Department-Zuordnung nach einer Frist (Standard: 21 Tage).
      </p>

      <div class="controls">
        <label for="days">Frist (Tage)</label>
        <input id="days" v-model.number="days" type="number" min="1" max="365" />

        <button class="btn btn-secondary" :disabled="loading" @click="loadPreview">
          Vorschau laden
        </button>
        <button class="btn btn-secondary" :disabled="loading || previewItems.length === 0" @click="downloadCsv">
          Liste herunterladen
        </button>
        <button class="btn btn-danger" :disabled="loading || selectedCount === 0" @click="runCleanup">
          Daten loeschen ({{ selectedCount }})
        </button>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="success" class="success">{{ success }}</p>

      <div class="preview">
        <h3>Vorschau</h3>
        <p v-if="loading">Lade...</p>
        <p v-else-if="previewCount === 0">Keine passenden User gefunden.</p>
        <p v-else>
          {{ previewCount }} User waeren betroffen, {{ selectedCount }} davon sind aktuell markiert.
        </p>

        <table v-if="previewItems.length > 0">
          <thead>
            <tr>
              <th>
                <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll($event)" />
              </th>
              <th>User ID</th>
              <th>E-Mail</th>
              <th>Erstellt am</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in previewItems" :key="item.user_id">
              <td>
                <input type="checkbox" v-model="selectedUserMap[item.user_id]" />
              </td>
              <td>{{ item.user_id }}</td>
              <td>{{ item.email || '-' }}</td>
              <td>{{ formatDate(item.created_at) }}</td>
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
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  previewUnassignedUsersCleanup,
  runUnassignedUsersCleanup,
  type UnassignedCleanupItem
} from '@/api/jobs'
import {
  deleteGlobalAddress,
  getGlobalAddresses,
  type GlobalAddress
} from '@/api/globalAddresses'
import AddressModal from '@/components/AddressModal.vue'

const authStore = useAuthStore()
const isSuperAdmin = computed(() =>
  authStore.userRoles.includes('ROLE_SUPERADMIN') || authStore.currentDepartmentRole === 'sa'
)
const activeTab = ref<'global-addresses' | 'cleanup'>('global-addresses')
const GLOBAL_DEPARTMENT_ID = 'GLOBAL000000'

const globalLoading = ref(false)
const globalError = ref<string | null>(null)
const globalSuccess = ref<string | null>(null)
const globalAddresses = ref<GlobalAddress[]>([])
const globalSearch = ref('')
const isGlobalAddressModalOpen = ref(false)
const editingGlobalAddress = ref<GlobalAddress | null>(null)

const days = ref(21)
const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const previewCount = ref(0)
const previewItems = ref<UnassignedCleanupItem[]>([])
const selectedUserMap = ref<Record<string, boolean>>({})
const selectedIds = computed(() =>
  previewItems.value
    .filter((item) => !!selectedUserMap.value[item.user_id])
    .map((item) => item.user_id)
)
const selectedCount = computed(() => selectedIds.value.length)
const isAllSelected = computed(() => previewItems.value.length > 0 && selectedCount.value === previewItems.value.length)

async function loadGlobalAddresses() {
  globalLoading.value = true
  globalError.value = null
  try {
    const response = await getGlobalAddresses(globalSearch.value)
    globalAddresses.value = response.addresses
  } catch (err: any) {
    globalError.value = err?.response?.data?.error || 'Globale Adressen konnten nicht geladen werden'
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
  globalSuccess.value = 'Globale Adresse wurde gespeichert'
}

async function removeGlobalAddress(id: string) {
  const confirmed = window.confirm('Globale Adresse wirklich loeschen?')
  if (!confirmed) return

  globalLoading.value = true
  globalError.value = null
  globalSuccess.value = null
  try {
    await deleteGlobalAddress(id)
    globalSuccess.value = 'Globale Adresse wurde geloescht'
    if (editingGlobalAddress.value?.id === id) {
      closeGlobalAddressModal()
    }
    await loadGlobalAddresses()
  } catch (err: any) {
    globalError.value = err?.response?.data?.error || 'Loeschen fehlgeschlagen'
  } finally {
    globalLoading.value = false
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('de-CH')
}

async function loadPreview() {
  loading.value = true
  error.value = null
  success.value = null
  try {
    const response = await previewUnassignedUsersCleanup(days.value)
    previewCount.value = response.count
    previewItems.value = response.items
    const nextMap: Record<string, boolean> = {}
    response.items.forEach((item) => {
      nextMap[item.user_id] = true
    })
    selectedUserMap.value = nextMap
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Vorschau konnte nicht geladen werden'
  } finally {
    loading.value = false
  }
}

function toggleSelectAll(event: Event) {
  const checked = (event.target as HTMLInputElement).checked
  const nextMap: Record<string, boolean> = {}
  previewItems.value.forEach((item) => {
    nextMap[item.user_id] = checked
  })
  selectedUserMap.value = nextMap
}

function downloadCsv() {
  const header = ['user_id', 'email', 'created_at']
  const rows = previewItems.value.map((item) => [
    item.user_id,
    item.email || '',
    item.created_at
  ])
  const csvContent = [header, ...rows]
    .map((row) => row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  const date = new Date().toISOString().slice(0, 10)
  link.href = url
  link.download = `unassigned-users-preview-${date}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

async function runCleanup() {
  if (selectedCount.value === 0) return

  const confirmed = window.confirm(
    `Wirklich ${selectedCount.value} ausgewaehlte unzugeordnete User loeschen? Dieser Schritt ist nicht rueckgaengig.`
  )
  if (!confirmed) return

  loading.value = true
  error.value = null
  success.value = null
  try {
    const result = await runUnassignedUsersCleanup(days.value, false, selectedIds.value)
    success.value = `Cleanup abgeschlossen: ${result.deleted_users || 0} User und ${result.deleted_profiles || 0} Profile geloescht.`
    await loadPreview()
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Cleanup konnte nicht ausgefuehrt werden'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadGlobalAddresses()
  if (isSuperAdmin.value) {
    await loadPreview()
  }
})
</script>

<style scoped>
.jobs-page {
  padding: 24px;
  max-width: 1100px;
}

.jobs-header h1 {
  margin: 0;
  font-size: 1.75rem;
}

.jobs-header p {
  color: #6b7280;
  margin-top: 8px;
}

.tabs {
  display: flex;
  gap: 8px;
  margin-top: 16px;
}

.tab-btn {
  border: 1px solid #d1d5db;
  background: #f9fafb;
  color: #374151;
  padding: 8px 14px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}

.tab-btn.active {
  background: #e0f2fe;
  border-color: #38bdf8;
  color: #0c4a6e;
}

.job-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  margin-top: 16px;
}

.job-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.job-title-row h2 {
  margin: 0;
}

.badge {
  font-size: 12px;
  color: #065f46;
  background: #d1fae5;
  padding: 4px 8px;
  border-radius: 999px;
}

.job-description {
  color: #4b5563;
}

.controls {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin: 14px 0;
}

.controls input {
  width: 90px;
  padding: 8px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
}

/* Search input base uses shared ui/page-layout.css */

.search-input {
  width: 280px;
}

/* Buttons use shared ui/buttons.css */

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
