<template>
  <div class="my-department-settings">
    <div class="header-section">
      <div>
        <h1>Mein Department</h1>
        <p class="description">Einstellungen für dein aktuelles Department</p>
      </div>
    </div>

    <!-- Department Selector (wenn User in mehreren Departments ist) -->
    <div v-if="userDepartments.length > 1" class="department-selector">
      <label for="department-select" class="selector-label">Department auswählen:</label>
      <div class="dept-select-row">
        <div class="select-wrapper">
          <select 
            id="department-select" 
            v-model="selectedDepartmentId" 
            @change="onDepartmentChange"
            class="department-select"
          >
            <option 
              v-for="dept in userDepartments" 
              :key="dept.department_id" 
              :value="dept.department_id"
            >
              {{ dept.department?.name || dept.department_id }} 
              {{ dept.is_primary ? '⭐ (Primär)' : '' }}
              - {{ formatRole(dept.role) }}
            </option>
          </select>
          <svg class="select-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <button 
          v-if="!isSelectedDeptPrimary"
          @click="setAsPrimary"
          :disabled="isSavingPrimary"
          class="set-primary-btn"
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M8 1L10 5.5L15 6L11.5 9.5L12.5 14.5L8 12L3.5 14.5L4.5 9.5L1 6L6 5.5L8 1Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          {{ isSavingPrimary ? 'Speichern...' : 'Als Primär setzen' }}
        </button>
        <span v-else class="current-primary-badge">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
            <path d="M8 1L10 5.5L15 6L11.5 9.5L12.5 14.5L8 12L3.5 14.5L4.5 9.5L1 6L6 5.5L8 1Z" fill="#f59e0b" stroke="#f59e0b" stroke-width="1"/>
          </svg>
          Primäres Department
        </span>
      </div>
      <p class="selector-hint">Du bist Mitglied in {{ userDepartments.length }} Departments. Dein primäres Department wird beim Login automatisch geladen.</p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>Lade Department-Daten...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p class="error-message">{{ error }}</p>
      <button @click="loadDepartment" class="retry-button">Erneut versuchen</button>
    </div>

    <!-- Department Info -->
    <div v-else-if="department" class="department-content">
      <!-- Info Card -->
      <div class="info-card">
        <div class="card-header">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="card-icon">
            <path
              d="M3 7C3 5.89543 3.89543 5 5 5H9.58579C10.1162 5 10.6249 5.21071 11 5.58579L12.4142 7H19C20.1046 7 21 7.89543 21 9V17C21 18.1046 20.1046 19 19 19H5C3.89543 19 3 18.1046 3 17V7Z"
              fill="#3b82f6"
            />
          </svg>
          <h2>Department-Informationen</h2>
        </div>
        
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Name</span>
            <span class="info-value">{{ department.name }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Department-ID</span>
            <span class="info-value mono">{{ department.id }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Organisation-ID</span>
            <span class="info-value mono">{{ department.organisation_id }}</span>
          </div>
          <div v-if="department.parent_id" class="info-item">
            <span class="info-label">Übergeordnetes Department</span>
            <span class="info-value mono">{{ department.parent_id }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Deine Rolle</span>
            <span class="info-value">
              <span class="role-badge">{{ formatRole(currentRole) }}</span>
            </span>
          </div>
        </div>
      </div>

      <div v-if="canManageJoinCode" class="info-card">
        <div class="card-header">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="card-icon">
            <path d="M12 2L15 8H21L16 12L18 19L12 15L6 19L8 12L3 8H9L12 2Z" fill="#3b82f6"/>
          </svg>
          <h2>Onboarding</h2>
        </div>

        <div class="onboarding-admin-row">
          <p class="onboarding-status">
            Status:
            <strong :class="onboardingStatusClass">
              {{ onboardingStatusLabel }}
            </strong>
          </p>
          <button
            class="onboarding-reset-btn"
            :disabled="isResettingOnboarding"
            @click="resetDepartmentOnboarding"
          >
            {{ isResettingOnboarding ? 'Zuruecksetzen...' : 'Onboarding zuruecksetzen' }}
          </button>
        </div>
        <p v-if="isExemptFromMemberOnboardingUi" class="selector-hint">
          Das Einrichtungs-Onboarding richtet sich an Materialchef/Depchef. Für deine Leitungsrolle ist kein eigener
          Durchlauf nötig — unten kannst du das Onboarding für alle Mitglieder dieses Departments zurücksetzen.
        </p>
        <p v-else class="selector-hint">
          Setzt das Department-Onboarding fuer alle Mitglieder dieses Departments auf "offen".
        </p>
      </div>

      <!-- DB zurücksetzen (nur für Manager) -->
      <div v-if="canManageJoinCode" class="info-card db-reset-card">
        <div class="card-header">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="card-icon card-icon-danger">
            <path d="M4 7V4H20V7M9 20H15V10H9V20M5 7H19V20H5V7Z" fill="#dc2626"/>
          </svg>
          <h2>DB zurücksetzen</h2>
        </div>
        <div class="db-reset-row">
          <p class="db-reset-desc">
            Löscht alle Daten dieses Departments: Aktivitäten, Materialien, Adressen, Kategorien, Gruppen, Lagerplätze, etc.
            <strong>Department und Mitglieder bleiben erhalten.</strong>
          </p>
          <button
            class="db-reset-btn"
            :disabled="isResettingDb"
            @click="resetDepartmentDb"
          >
            {{ isResettingDb ? 'Zurücksetzen...' : 'DB zurücksetzen' }}
          </button>
        </div>
        <p class="selector-hint db-reset-warning">
          Nur für Dev/Test. Diese Aktion kann nicht rückgängig gemacht werden.
        </p>
      </div>

      <!-- Users Card -->
      <div class="info-card">
        <div class="card-header">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="card-icon">
            <path d="M12 12C14.7614 12 17 9.76142 17 7C17 4.23858 14.7614 2 12 2C9.23858 2 7 4.23858 7 7C7 9.76142 9.23858 12 12 12Z" fill="#3b82f6"/>
            <path d="M3 20C3 16.6863 6.13401 14 10 14H14C17.866 14 21 16.6863 21 20V22H3V20Z" fill="#3b82f6"/>
          </svg>
          <h2>Mitglieder ({{ department.users?.length || 0 }})</h2>
        </div>
        
        <div v-if="department.users && department.users.length > 0" class="users-list">
          <div v-for="user in department.users" :key="user.id" class="user-item">
            <div class="user-avatar" :style="{ backgroundColor: getAvatarColor(user.name) }">
              {{ getInitials(user.name) }}
            </div>
            <div class="user-info">
              <span class="user-name">{{ user.name }}</span>
              <span class="user-email">{{ user.email }}</span>
            </div>
            <span class="user-role-badge">{{ formatRole(user.role) }}</span>
          </div>
        </div>
        <p v-else class="empty-users">Keine Mitglieder in diesem Department.</p>
      </div>

      <!-- Statistiken Card -->
      <div class="info-card">
        <div class="card-header">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="card-icon">
            <path d="M4 20V10H8V20H4ZM10 20V4H14V20H10ZM16 20V14H20V20H16Z" fill="#3b82f6"/>
          </svg>
          <h2>Statistiken</h2>
        </div>
        
        <div class="stats-grid">
          <div class="stat-item">
            <span class="stat-value">{{ department.users?.length || 0 }}</span>
            <span class="stat-label">Mitglieder</span>
          </div>
          <div class="stat-item">
            <span class="stat-value">{{ subDepartmentsCount }}</span>
            <span class="stat-label">Unter-Departments</span>
          </div>
          <div class="stat-item">
            <span class="stat-value">{{ storageAddresses.length }}</span>
            <span class="stat-label">Lagerplätze</span>
          </div>
          <div class="stat-item">
            <span class="stat-value">{{ addresses.length }}</span>
            <span class="stat-label">Adressen</span>
          </div>
        </div>
      </div>

      <!-- Lagerplätze Card (Adressen mit type='storage') -->
      <div class="info-card storage-section">
        <div class="card-header">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="card-icon">
            <path d="M20 7H4C2.9 7 2 7.9 2 9V19C2 20.1 2.9 21 4 21H20C21.1 21 22 20.1 22 19V9C22 7.9 21.1 7 20 7Z" fill="#3b82f6"/>
            <path d="M12 3L2 7H22L12 3Z" fill="#60a5fa"/>
          </svg>
          <h2>Lagerplätze ({{ storageAddresses.length }})</h2>
          <button @click="openAddressModal(undefined, 'storage')" class="add-storage-btn">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Hinzufügen
          </button>
        </div>
        
        <div v-if="isLoadingAddresses" class="loading-storage">
          <div class="spinner-sm"></div>
          <span>Lade Adressen...</span>
        </div>
        
        <div v-else-if="storageAddresses.length > 0" class="storage-list">
          <div 
            v-for="addr in storageAddresses" 
            :key="addr.id" 
            class="storage-item"
            :class="{ 'is-primary': addr.is_primary }"
          >
            <div class="storage-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M20 7H4C2.9 7 2 7.9 2 9V19C2 20.1 2.9 21 4 21H20C21.1 21 22 20.1 22 19V9C22 7.9 21.1 7 20 7Z" fill="currentColor"/>
                <path d="M12 3L2 7H22L12 3Z" fill="currentColor" opacity="0.6"/>
              </svg>
            </div>
            <div class="storage-info">
              <div class="storage-name-row">
                <span class="storage-name">{{ addr.name || addr.street_line }}</span>
                <span v-if="addr.is_primary" class="primary-badge">Primär</span>
              </div>
              <span class="storage-address">{{ addr.full_address }}</span>
            </div>
            <div class="storage-actions">
              <button v-if="!addr.is_primary" @click="makePrimary(addr)" class="action-btn" title="Als Primär setzen">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M8 1L10 5.5L15 6L11.5 9.5L12.5 14.5L8 12L3.5 14.5L4.5 9.5L1 6L6 5.5L8 1Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <button @click="openAddressModal(addr)" class="action-btn" title="Bearbeiten">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M11.3333 2C11.5084 1.82489 11.7163 1.68601 11.9444 1.59124C12.1726 1.49648 12.4163 1.44775 12.6625 1.44775C12.9087 1.44775 13.1524 1.49648 13.3806 1.59124C13.6087 1.68601 13.8166 1.82489 13.9917 2C14.1668 2.17511 14.3057 2.383 14.4005 2.61117C14.4952 2.83934 14.5439 3.08305 14.5439 3.32917C14.5439 3.57529 14.4952 3.819 14.4005 4.04717C14.3057 4.27534 14.1668 4.48323 13.9917 4.65833L5.325 13.325L2 14L2.675 10.675L11.3333 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <button 
                v-if="addr.has_coordinates" 
                @click="showOnMap(addr)" 
                class="action-btn" 
                title="Auf Karte zeigen"
              >
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M8 2C5.24 2 3 4.24 3 7C3 10.75 8 14 8 14S13 10.75 13 7C13 4.24 10.76 2 8 2ZM8 8.5C7.17 8.5 6.5 7.83 6.5 7C6.5 6.17 7.17 5.5 8 5.5C8.83 5.5 9.5 6.17 9.5 7C9.5 7.83 8.83 8.5 8 8.5Z" fill="currentColor"/>
                </svg>
              </button>
              <button @click="deleteAddressItem(addr)" class="action-btn delete" title="Löschen">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M2 4H14M5 4V2H11V4M6 7V12M10 7V12M3 4L4 14H12L13 4H3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
        
        <div v-else class="empty-storage">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
            <path d="M20 7H4C2.9 7 2 7.9 2 9V19C2 20.1 2.9 21 4 21H20C21.1 21 22 20.1 22 19V9C22 7.9 21.1 7 20 7Z" fill="#d1d5db"/>
            <path d="M12 3L2 7H22L12 3Z" fill="#e5e7eb"/>
          </svg>
          <p>Noch keine Lagerplätze erfasst.</p>
          <button @click="openAddressModal(undefined, 'storage')" class="add-first-btn">
            Ersten Lagerplatz hinzufügen
          </button>
        </div>

        <!-- Karte für alle Lagerplätze -->
        <div v-if="storageAddressesWithCoords.length > 0" class="storage-map-section">
          <h3>Übersichtskarte</h3>
          <MapView
            :latitude="storageAddressesWithCoords[0]?.latitude"
            :longitude="storageAddressesWithCoords[0]?.longitude"
            :editable="false"
            height="300px"
            :zoom="12"
          />
        </div>
      </div>

      <!-- Rechnungsadresse Card (Adressen mit type='billing') -->
      <div class="info-card">
        <div class="card-header">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="card-icon">
            <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#3b82f6"/>
            <path d="M7 7H17V9H7V7ZM7 11H17V13H7V11ZM7 15H13V17H7V15Z" fill="white"/>
          </svg>
          <h2>Rechnungsadresse</h2>
          <button v-if="billingAddresses.length === 0" @click="openAddressModal(undefined, 'billing')" class="add-storage-btn">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Hinzufügen
          </button>
        </div>
        
        <div v-if="billingAddresses.length > 0" class="billing-address">
          <div v-for="addr in billingAddresses" :key="addr.id" class="address-card">
            <div class="address-content">
              <strong v-if="addr.company">{{ addr.company }}</strong>
              <span>{{ addr.full_address }}</span>
            </div>
            <div class="address-actions">
              <button @click="openAddressModal(addr)" class="action-btn" title="Bearbeiten">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M11.3333 2C11.5084 1.82489 11.7163 1.68601 11.9444 1.59124C12.1726 1.49648 12.4163 1.44775 12.6625 1.44775C12.9087 1.44775 13.1524 1.49648 13.3806 1.59124C13.6087 1.68601 13.8166 1.82489 13.9917 2C14.1668 2.17511 14.3057 2.383 14.4005 2.61117C14.4952 2.83934 14.5439 3.08305 14.5439 3.32917C14.5439 3.57529 14.4952 3.819 14.4005 4.04717C14.3057 4.27534 14.1668 4.48323 13.9917 4.65833L5.325 13.325L2 14L2.675 10.675L11.3333 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <button @click="deleteAddressItem(addr)" class="action-btn delete" title="Löschen">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M2 4H14M5 4V2H11V4M6 7V12M10 7V12M3 4L4 14H12L13 4H3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
        <p v-else class="empty-billing">Noch keine Rechnungsadresse erfasst.</p>
      </div>
    </div>

    <!-- No Department -->
    <div v-else class="empty-state">
      <p>Kein Department ausgewählt.</p>
    </div>

    <!-- Address Modal -->
    <AddressModal
      v-if="isAddressModalOpen"
      :department-id="selectedDepartmentId || ''"
      :address="editingAddress"
      :default-type="newAddressType"
      @close="closeAddressModal"
      @saved="handleAddressSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { getDepartment, getDepartments, type Department } from '@/api/departments'
import { setPrimaryDepartment as apiSetPrimaryDepartment } from '@/api/auth'
import { 
  getAddresses, 
  createAddress,
  updateAddress,
  deleteAddress as apiDeleteAddress,
  setAddressPrimary,
  type Address,
  ADDRESS_TYPES
} from '@/api/addresses'
import {
  deletePendingInvite,
  getDepartmentInvite,
  getPendingInvites,
  regenerateDepartmentInvite,
  type DepartmentInviteData,
  type PendingInvite
} from '@/api/joinRequests'
import {
  getPublicSharingSettings,
  getCalendarSettings,
  getDepartmentOnboardingStatus,
  resetDepartmentOnboardingDone,
  resetDepartmentDb as apiResetDepartmentDb,
  savePublicSharingSettings,
  saveCalendarSettings as saveCalendarSettingsApi,
  type PublicFoundContactDelivery,
} from '@/api/departmentSettings'
import { buildOnboardingDismissedKey, buildOnboardingDoneKey, buildOnboardingStateKey } from '@/utils/departmentOnboarding'
import MapView from '@/components/MapView.vue'
import AddressModal from '@/components/AddressModal.vue'
import QRCode from 'qrcode'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const isLoading = ref(false)
const error = ref<string | null>(null)
const department = ref<Department | null>(null)
const subDepartmentsCount = ref(0)
const selectedDepartmentId = ref<string | null>(null)
const inviteData = ref<DepartmentInviteData | null>(null)
const inviteQrDataUrl = ref('')
const isInviteLoading = ref(false)
const pendingInvites = ref<PendingInvite[]>([])
const onboardingDone = ref(false)
const isResettingOnboarding = ref(false)
const isResettingDb = ref(false)
const isSavingPublicSettings = ref(false)
const publicContactEmail = ref('')
const publicContactNote = ref('')
const publicShowContactForm = ref(true)
const publicShowContactEmail = ref(true)
const publicShowContactNote = ref(true)
const publicFoundContactDelivery = ref<PublicFoundContactDelivery>('both')

const calendarFcalGeoId = ref('')
const savedCalendarGeoId = ref('')
const isSavingCalendar = ref(false)

const calendarDirty = computed(() => calendarFcalGeoId.value.trim() !== savedCalendarGeoId.value.trim())

// Primary Department State
const isSavingPrimary = ref(false)

// Ist das aktuell ausgewählte Department das primäre?
const isSelectedDeptPrimary = computed(() => {
  if (!selectedDepartmentId.value) return false
  const dept = userDepartments.value.find(d => d.department_id === selectedDepartmentId.value)
  return dept?.is_primary === true
})

// Adressen State (Lagerplätze = type='storage', Rechnung = type='billing')
const addresses = ref<Address[]>([])
const isLoadingAddresses = ref(false)
const isAddressModalOpen = ref(false)
const editingAddress = ref<Address | null>(null)
const newAddressType = ref<string>('storage')

// Gefilterte Adressen nach Typ
const storageAddresses = computed(() => addresses.value.filter(a => a.type === 'storage'))
const billingAddresses = computed(() => addresses.value.filter(a => a.type === 'billing'))

// Lagerplätze mit Koordinaten
const storageAddressesWithCoords = computed(() => {
  return storageAddresses.value.filter(a => a.has_coordinates)
})

// Liste aller Departments des Users
const userDepartments = computed(() => authStore.departments || [])

// Aktuelle Rolle für das ausgewählte Department
const currentRole = computed(() => {
  if (!selectedDepartmentId.value) return 'user'
  const dept = userDepartments.value.find(d => d.department_id === selectedDepartmentId.value)
  return dept?.role || 'user'
})

const canManageJoinCode = computed(() => {
  const normalizedRole = String(currentRole.value || '').toLowerCase().trim()
  return ['dc', 'depchef', 'mw', 'matwart', 'sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(normalizedRole)
})

/** SA / Org / Sub — kein persönliches Onboarding; Anzeige im UI */
const isHierarchyLeaderDeptRole = computed(() => {
  const r = String(currentRole.value || '').toLowerCase().trim()
  return ['sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(r)
})

const isExemptFromMemberOnboardingUi = computed(() => {
  return isHierarchyLeaderDeptRole.value || authStore.userRoles.includes('ROLE_SUPERADMIN')
})

const onboardingStatusLabel = computed(() => {
  if (isHierarchyLeaderDeptRole.value) {
    return 'Nicht zutreffend (Leitungsrolle)'
  }
  if (authStore.userRoles.includes('ROLE_SUPERADMIN')) {
    return 'Nicht zutreffend (Superadmin)'
  }
  return onboardingDone.value ? 'Abgeschlossen' : 'Offen'
})

const onboardingStatusClass = computed(() => {
  if (isExemptFromMemberOnboardingUi.value) {
    return 'status-na'
  }
  return onboardingDone.value ? 'status-done' : 'status-open'
})

// Wenn sich das ausgewählte Department ändert – Store + URL, dann voller Seiten-Reload (frischer State)
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
  window.location.reload()
}

// Primäres Department in der DB speichern
async function setAsPrimary() {
  if (!selectedDepartmentId.value || isSavingPrimary.value) return
  
  isSavingPrimary.value = true
  
  try {
    // In der DB speichern
    await apiSetPrimaryDepartment(selectedDepartmentId.value)
    
    // Auth Store lokal aktualisieren (is_primary Flags updaten)
    authStore.departments.forEach(d => {
      d.is_primary = d.department_id === selectedDepartmentId.value
    })
    
    // Auch den aktiven Department-ID im Store setzen
    authStore.setActiveDepartment(selectedDepartmentId.value)
    
    toast.success('Primäres Department erfolgreich gespeichert!')
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Fehler beim Speichern des primären Departments')
  } finally {
    isSavingPrimary.value = false
  }
}

const roleNames: Record<string, string> = {
  'sa': 'Superadmin',
  'superadmin': 'Superadmin',
  'org': 'Organisationschef',
  'organisationschef': 'Organisationschef',
  'sub': 'Suborgchef',
  'suborgchef': 'Suborgchef',
  'mw': 'Materialchef',
  'matwart': 'Materialchef',
  'dc': 'Departmentchef',
  'depchef': 'Departmentchef',
  'l1': 'Leiter 1',
  'leader1': 'Leiter 1',
  'l2': 'Leiter 2',
  'leader2': 'Leiter 2',
  'l3': 'Leiter 3',
  'leader3': 'Leiter 3',
  'u': 'Mitglied',
  'user': 'Mitglied'
}

function formatRole(role: string): string {
  const normalized = String(role).toLowerCase().trim()
  return roleNames[normalized] || role
}

function getInitials(name: string): string {
  if (!name) return '?'
  const parts = name.split(' ')
  if (parts.length >= 2) {
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase()
  }
  return name.charAt(0).toUpperCase()
}

function getAvatarColor(name: string): string {
  const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4']
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return colors[Math.abs(hash) % colors.length]
}

async function loadDepartment(departmentId?: string) {
  const deptId = departmentId || selectedDepartmentId.value || authStore.activeDepartmentId
  if (!deptId) {
    error.value = 'Kein Department ausgewählt'
    return
  }

  isLoading.value = true
  error.value = null

  try {
    // Lade Department mit Users
    department.value = await getDepartment(deptId)
    
    // Zähle Unter-Departments
    const allDepartments = await getDepartments()
    subDepartmentsCount.value = allDepartments.filter(d => d.parent_id === deptId).length
    
    // Lade Adressen (Lagerplätze, Rechnungsadressen, etc.)
    await loadAddresses(deptId)
    await loadOnboardingStatus(deptId)
  } catch (err: any) {
    error.value = err.response?.data?.error || 'Fehler beim Laden des Departments'
  } finally {
    isLoading.value = false
  }
}

async function loadPublicSettings(deptId: string) {
  try {
    const settings = await getPublicSharingSettings(deptId)
    publicContactEmail.value = settings.publicContactEmail
    publicContactNote.value = settings.publicContactNote
    publicShowContactForm.value = settings.publicShowContactForm
    publicShowContactEmail.value = settings.publicShowContactEmail
    publicShowContactNote.value = settings.publicShowContactNote
    publicFoundContactDelivery.value = settings.publicFoundContactDelivery
  } catch {
    publicContactEmail.value = ''
    publicContactNote.value = ''
    publicShowContactForm.value = true
    publicShowContactEmail.value = true
    publicShowContactNote.value = true
    publicFoundContactDelivery.value = 'both'
  }
}

async function loadCalendarSettings(deptId: string) {
  try {
    const c = await getCalendarSettings(deptId)
    calendarFcalGeoId.value = c.fcalGeoId
    savedCalendarGeoId.value = c.fcalGeoId
  } catch {
    calendarFcalGeoId.value = ''
    savedCalendarGeoId.value = ''
  }
}

async function saveCalendarSettingsForDept() {
  if (!selectedDepartmentId.value || isSavingCalendar.value || !calendarDirty.value) return
  isSavingCalendar.value = true
  try {
    await saveCalendarSettingsApi(selectedDepartmentId.value, {
      fcalGeoId: calendarFcalGeoId.value,
    })
    savedCalendarGeoId.value = calendarFcalGeoId.value.trim()
    toast.success('Kalender-Einstellung gespeichert.')
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Kalender-Einstellung konnte nicht gespeichert werden.')
  } finally {
    isSavingCalendar.value = false
  }
}

async function savePublicSettings() {
  if (!selectedDepartmentId.value || isSavingPublicSettings.value) return
  isSavingPublicSettings.value = true
  try {
    await savePublicSharingSettings(selectedDepartmentId.value, {
      publicContactEmail: publicContactEmail.value.trim(),
      publicContactNote: publicContactNote.value.trim(),
      publicShowContactForm: publicShowContactForm.value,
      publicShowContactEmail: publicShowContactEmail.value,
      publicShowContactNote: publicShowContactNote.value,
      publicFoundContactDelivery: publicFoundContactDelivery.value,
    })
    toast.success('Öffentliche Einstellungen gespeichert.')
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Einstellungen konnten nicht gespeichert werden.')
  } finally {
    isSavingPublicSettings.value = false
  }
}

async function loadOnboardingStatus(deptId: string) {
  try {
    const status = await getDepartmentOnboardingStatus(deptId)
    onboardingDone.value = status.doneAll
  } catch (err) {
    console.warn('Onboarding-Status konnte nicht geladen werden:', err)
    onboardingDone.value = false
  }
}

async function resetDepartmentOnboarding() {
  if (!selectedDepartmentId.value || isResettingOnboarding.value) return

  const ok = await confirm.confirm({
    title: 'Onboarding zuruecksetzen?',
    message: 'Das Onboarding wird für dieses Department wieder als offen markiert. Danach wird es erneut angezeigt.',
    confirmText: 'Zuruecksetzen',
    cancelText: 'Abbrechen',
    variant: 'warning',
  })
  if (!ok) return

  isResettingOnboarding.value = true
  try {
    await resetDepartmentOnboardingDone(selectedDepartmentId.value)
    onboardingDone.value = false
    const profileId = authStore.profileId
    const departmentId = selectedDepartmentId.value
    if (profileId && departmentId) {
      localStorage.removeItem(buildOnboardingDoneKey(profileId, departmentId))
      localStorage.removeItem(buildOnboardingDismissedKey(profileId, departmentId))
      localStorage.removeItem(buildOnboardingStateKey(profileId, departmentId))
      sessionStorage.removeItem(`onboarding_prompted_${profileId}_${departmentId}`)
    }
    toast.success('Department-Onboarding wurde zurueckgesetzt.')
    window.location.href = `/${departmentId}`
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Onboarding konnte nicht zurueckgesetzt werden.')
  } finally {
    isResettingOnboarding.value = false
  }
}

async function resetDepartmentDb() {
  if (!selectedDepartmentId.value || isResettingDb.value) return

  const ok = await confirm.confirm({
    title: 'DB zurücksetzen?',
    message: 'Alle Daten dieses Departments werden unwiderruflich gelöscht (Aktivitäten, Materialien, Adressen, etc.). Department und Mitglieder bleiben erhalten.',
    confirmText: 'DB zurücksetzen',
    cancelText: 'Abbrechen',
    variant: 'danger',
  })
  if (!ok) return

  isResettingDb.value = true
  try {
    const result = await apiResetDepartmentDb(selectedDepartmentId.value)
    // Onboarding-LocalStorage für dieses Department löschen, damit Onboarding wieder erscheint
    const profileId = authStore.profileId
    const departmentId = selectedDepartmentId.value
    if (profileId && departmentId) {
      localStorage.removeItem(buildOnboardingDoneKey(profileId, departmentId))
      localStorage.removeItem(buildOnboardingDismissedKey(profileId, departmentId))
      localStorage.removeItem(buildOnboardingStateKey(profileId, departmentId))
      sessionStorage.removeItem(`onboarding_prompted_${profileId}_${departmentId}`)
    }
    toast.success(result.message || 'Department-Daten wurden zurückgesetzt.')
    // Zur Dashboard weiterleiten, damit Onboarding beim nächsten Laden erscheint
    window.location.href = `/${departmentId}`
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'DB konnte nicht zurückgesetzt werden.')
  } finally {
    isResettingDb.value = false
  }
}

async function loadInviteCode(deptId: string) {
  if (!canManageJoinCode.value) {
    inviteData.value = null
    inviteQrDataUrl.value = ''
    return
  }

  isInviteLoading.value = true
  try {
    inviteData.value = await getDepartmentInvite(deptId)
    const qrPayload =
      (inviteData.value.register_qr_payload || inviteData.value.qr_payload || '').trim() ||
      inviteData.value.invite_url
    inviteQrDataUrl.value = await QRCode.toDataURL(qrPayload, {
      width: 180,
      margin: 1,
    })
    pendingInvites.value = await getPendingInvites(deptId)
  } catch (err) {
    console.error('Join-Code konnte nicht geladen werden:', err)
    inviteData.value = null
    inviteQrDataUrl.value = ''
    pendingInvites.value = []
  } finally {
    isInviteLoading.value = false
  }
}

async function regenerateInviteCode() {
  if (!selectedDepartmentId.value) return

  isInviteLoading.value = true
  try {
    inviteData.value = await regenerateDepartmentInvite(selectedDepartmentId.value)
    const qrPayload =
      (inviteData.value.register_qr_payload || inviteData.value.qr_payload || '').trim() ||
      inviteData.value.invite_url
    inviteQrDataUrl.value = await QRCode.toDataURL(qrPayload, {
      width: 180,
      margin: 1,
    })
    toast.success('Join-Code wurde neu generiert.')
  } catch (err) {
    console.error('Join-Code konnte nicht neu erstellt werden:', err)
    toast.error('Join-Code konnte nicht neu erstellt werden.')
  } finally {
    isInviteLoading.value = false
  }
}

async function removePendingInviteItem(inviteId: string) {
  if (!selectedDepartmentId.value) return
  const ok = await confirm.confirm({
    title: 'Pending Einladung loeschen?',
    message: 'Die Einladung wird entfernt und der Link ist nicht mehr in der Liste sichtbar.',
    confirmText: 'Loeschen',
    cancelText: 'Abbrechen',
    variant: 'danger',
  })
  if (!ok) return

  try {
    await deletePendingInvite(selectedDepartmentId.value, inviteId)
    pendingInvites.value = pendingInvites.value.filter((entry) => entry.id !== inviteId)
    toast.success('Pending Einladung geloescht.')
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Pending Einladung konnte nicht geloescht werden.')
  }
}

async function copyJoinCode() {
  if (!inviteData.value) return
  await navigator.clipboard.writeText(inviteData.value.join_code)
  toast.success('Join-Code kopiert.')
}

async function copyInviteLink() {
  if (!inviteData.value) return
  await navigator.clipboard.writeText(inviteData.value.invite_url)
  toast.success('Link zur Join-Seite kopiert (für angemeldete Nutzer).')
}

async function copyRegisterInviteLink() {
  const url = inviteData.value?.register_invite_url?.trim()
  if (!url) return
  await navigator.clipboard.writeText(url)
  toast.success('Registrierungslink kopiert (Organisation und Abteilung vorausgefüllt).')
}

// === Adressen (Lagerplätze = type='storage', Rechnung = type='billing') ===

async function loadAddresses(deptId: string) {
  isLoadingAddresses.value = true
  try {
    const result = await getAddresses(deptId)
    addresses.value = result.addresses
  } catch (err: any) {
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
  // Liste neu laden
  if (selectedDepartmentId.value) {
    await loadAddresses(selectedDepartmentId.value)
  }
  closeAddressModal()
}

async function deleteAddressItem(address: Address) {
  const typeLabel = ADDRESS_TYPES[address.type] || address.type
  const ok = await confirm.confirm({
    title: `${typeLabel} löschen?`,
    message: `"${address.name || address.street_line}" wirklich löschen?`,
    confirmText: 'Löschen',
    cancelText: 'Abbrechen',
    variant: 'danger',
  })
  if (!ok) return
  
  try {
    await apiDeleteAddress(address.id)
    if (selectedDepartmentId.value) {
      await loadAddresses(selectedDepartmentId.value)
    }
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Fehler beim Löschen')
  }
}

async function makePrimary(address: Address) {
  try {
    await setAddressPrimary(address.id)
    if (selectedDepartmentId.value) {
      await loadAddresses(selectedDepartmentId.value)
    }
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Fehler beim Setzen als Primär')
  }
}

function showOnMap(address: Address) {
  if (address.latitude && address.longitude) {
    const url = `https://www.openstreetmap.org/?mlat=${address.latitude}&mlon=${address.longitude}#map=17/${address.latitude}/${address.longitude}`
    window.open(url, '_blank')
  }
}

// Lade Department bei Änderung des aktiven Departments (z.B. über Navigation)
watch(() => authStore.activeDepartmentId, (newId) => {
  if (newId && newId !== selectedDepartmentId.value) {
    selectedDepartmentId.value = newId
    loadDepartment(newId)
  }
})

onMounted(() => {
  // Setze initiales Department auf das aktive Department
  selectedDepartmentId.value = authStore.activeDepartmentId || 
    (userDepartments.value[0]?.department_id ?? null)
  
  if (selectedDepartmentId.value) {
    loadDepartment(selectedDepartmentId.value)
  }
})
</script>

<style scoped>
.my-department-settings {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.header-section h1 {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.description {
  color: #6b7280;
  font-size: 14px;
  margin: 0;
}

/* Department Selector */
.department-selector {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  padding: 20px;
}

.selector-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #1e40af;
  margin-bottom: 8px;
}

.select-wrapper {
  position: relative;
  display: inline-block;
  width: 100%;
  max-width: 400px;
}

.department-select {
  width: 100%;
  padding: 12px 40px 12px 16px;
  font-size: 15px;
  font-weight: 500;
  color: #1f2937;
  background: white;
  border: 2px solid #3b82f6;
  border-radius: 8px;
  cursor: pointer;
  appearance: none;
  transition: all 0.2s;
}

.department-select:hover {
  border-color: #2563eb;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
}

.department-select:focus {
  outline: none;
  border-color: #1d4ed8;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
}

.select-icon {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #3b82f6;
  pointer-events: none;
}

.dept-select-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.set-primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.set-primary-btn:hover:not(:disabled) {
  background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.set-primary-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.current-primary-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fcd34d;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
}

.save-success-msg {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
  padding: 8px 14px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  color: #166534;
  font-size: 13px;
  font-weight: 500;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

.selector-hint {
  margin: 12px 0 0 0;
  font-size: 13px;
  color: #3b82f6;
  font-weight: 500;
}

/* Loading/error base uses shared ui/states.css */

.error-message {
  color: #dc2626;
  margin-bottom: 16px;
}

/* Retry button uses shared ui/states.css (.retry-button) */

.department-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.info-card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
}

.join-share-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 14px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}

.join-share-main {
  flex: 1;
  min-width: 0;
}

.join-code-row {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.join-code {
  display: inline-block;
  padding: 8px 12px;
  border-radius: 8px;
  background: #111827;
  color: #f9fafb;
  font-weight: 700;
  letter-spacing: 1px;
  font-size: 1rem;
}

.join-meta {
  margin: 8px 0 0;
  color: #6b7280;
  font-size: 0.85rem;
  word-break: break-all;
}

.pending-invites-block {
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px dashed #d1d5db;
}

.pending-invite-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 0;
  font-size: 13px;
  color: #374151;
}

.join-qr img {
  width: 120px;
  height: 120px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  padding: 6px;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.card-icon {
  flex-shrink: 0;
}

.card-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.public-visibility-toggles .public-toggle-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 14px;
  color: #374151;
  margin-bottom: 10px;
  cursor: pointer;
}

.public-visibility-toggles .public-toggle-row input {
  margin-top: 3px;
  flex-shrink: 0;
}

.info-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-value {
  font-size: 15px;
  color: #1f2937;
}

.info-value.mono {
  font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
  font-size: 13px;
  color: #374151;
}

.role-badge {
  display: inline-block;
  padding: 4px 12px;
  background: #dbeafe;
  color: #1d4ed8;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
}

.users-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.user-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 600;
  font-size: 14px;
  flex-shrink: 0;
}

.user-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.user-name {
  font-weight: 500;
  color: #1f2937;
  font-size: 14px;
}

.user-email {
  font-size: 12px;
  color: #6b7280;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-role-badge {
  padding: 4px 10px;
  background: #e0e7ff;
  color: #4338ca;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  flex-shrink: 0;
}

.empty-users {
  color: #6b7280;
  font-style: italic;
  text-align: center;
  padding: 20px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 16px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #3b82f6;
}

.stat-label {
  font-size: 12px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Empty state base uses shared ui/states.css */

/* === Storage Locations === */
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

.storage-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.storage-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  transition: all 0.2s;
}

.storage-item:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.storage-item.is-primary {
  border-color: #3b82f6;
  background: #f0f7ff;
}

.storage-item.is-inactive {
  opacity: 0.6;
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

.inactive-badge {
  padding: 2px 8px;
  background: #fecaca;
  color: #dc2626;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
}

.storage-type {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
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

.storage-map-section {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
}

.storage-map-section h3 {
  margin: 0 0 16px 0;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

/* === Billing Address === */
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

.onboarding-admin-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.onboarding-status {
  margin: 0;
  font-size: 14px;
  color: #1f2937;
}

.status-done {
  color: #15803d;
}

.status-open {
  color: #b45309;
}

.status-na {
  color: #64748b;
  font-weight: 600;
}

.onboarding-reset-btn {
  padding: 8px 14px;
  background: #f59e0b;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.onboarding-reset-btn:hover:not(:disabled) {
  background: #d97706;
}

.onboarding-reset-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

/* DB Reset Card */
.db-reset-card {
  border-color: #fecaca;
  background: #fef2f2;
}

.card-icon-danger {
  color: #dc2626;
}

.db-reset-row {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.db-reset-desc {
  margin: 0;
  font-size: 14px;
  color: #374151;
  line-height: 1.5;
}

.db-reset-btn {
  padding: 10px 20px;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  align-self: flex-start;
  transition: background 0.2s;
}

.db-reset-btn:hover:not(:disabled) {
  background: #b91c1c;
}

.db-reset-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.db-reset-warning {
  color: #b91c1c !important;
  margin-top: 12px;
}
</style>
