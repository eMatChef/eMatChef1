<template>
  <div class="contact-detail-view">
    <!-- Header -->
    <header class="detail-header">
      <div class="header-left">
        <button class="back-btn" @click="$emit('close')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
          </svg>
          Zurück zur Liste
        </button>
        <div class="header-title" v-if="contact">
          <div class="contact-avatar-lg" :class="contact.type">
            {{ getInitials(contact) }}
          </div>
          <div>
            <h1>{{ contact.name || contact.company || 'Ohne Name' }}</h1>
            <span class="header-subtitle" v-if="contact.company && contact.name">{{ contact.company }}</span>
          </div>
        </div>
      </div>
      <div class="header-actions" v-if="contact">
        <button class="btn-danger-outline" @click="confirmDelete" :disabled="isDeleting">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3 6 5 6 21 6"/>
            <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
          </svg>
          Löschen
        </button>
        <button class="btn-outline" @click="openEditModal">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
          </svg>
          Bearbeiten
        </button>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="isLoading" class="loading-container">
      <div class="spinner"></div>
      <p>Kontakt wird geladen...</p>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-container">
      <p>{{ error }}</p>
      <button @click="loadContact" class="btn-outline">Erneut versuchen</button>
    </div>

    <!-- Content -->
    <div v-else-if="contact" class="detail-content">
      <div class="content-layout">
        <!-- Main Content -->
        <main class="content-main">
          <!-- Kontaktdaten -->
          <div class="section-card">
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
                Kontaktdaten
              </h2>
              <button class="section-edit-btn" @click="openEditModal" title="Bearbeiten">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Bearbeiten
              </button>
            </div>
            
            <div class="info-grid">
              <div class="info-item" v-if="contact.name">
                <span class="info-label">Bezeichnung</span>
                <span class="info-value">{{ contact.name }}</span>
              </div>
              <div class="info-item" v-if="contact.company">
                <span class="info-label">Firma/Organisation</span>
                <span class="info-value">{{ contact.company }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Typ</span>
                <span class="info-value">
                  <span class="type-badge" :class="contact.type">{{ contact.type_label }}</span>
                </span>
              </div>
              <div class="info-item" v-if="contact.is_primary">
                <span class="info-label">Status</span>
                <span class="info-value">
                  <span class="primary-badge">Primäre Adresse</span>
                </span>
              </div>
            </div>
          </div>

          <!-- Kommunikation -->
          <div class="section-card">
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                </svg>
                Kommunikation
              </h2>
              <button class="section-edit-btn" @click="openEditModal" title="Bearbeiten">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Bearbeiten
              </button>
            </div>

            <div v-if="contact.email || contact.phone || contact.mobile" class="contact-actions-grid">
              <a v-if="contact.email" :href="'mailto:' + contact.email" class="contact-action-card">
                <div class="action-icon email">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="4" width="20" height="16" rx="2"/>
                    <path d="M22 4l-10 8L2 4"/>
                  </svg>
                </div>
                <div class="action-info">
                  <span class="action-label">E-Mail</span>
                  <span class="action-value">{{ contact.email }}</span>
                </div>
              </a>

              <a v-if="contact.phone" :href="'tel:' + contact.phone" class="contact-action-card">
                <div class="action-icon phone">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                  </svg>
                </div>
                <div class="action-info">
                  <span class="action-label">Telefon</span>
                  <span class="action-value">{{ contact.phone }}</span>
                </div>
              </a>

              <a v-if="contact.mobile" :href="'tel:' + contact.mobile" class="contact-action-card">
                <div class="action-icon mobile">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                    <line x1="12" y1="18" x2="12.01" y2="18"/>
                  </svg>
                </div>
                <div class="action-info">
                  <span class="action-label">Mobil</span>
                  <span class="action-value">{{ contact.mobile }}</span>
                </div>
              </a>
            </div>

            <div v-else class="empty-section">
              <p>Keine Kontaktdaten hinterlegt.</p>
              <button class="btn-add-data" @click="openEditModal">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                  <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                E-Mail / Telefon hinzufügen
              </button>
            </div>
          </div>

          <!-- Adresse -->
          <div class="section-card">
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
                Adresse
              </h2>
              <button class="section-edit-btn" @click="openEditModal" title="Bearbeiten">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Bearbeiten
              </button>
            </div>

            <div class="address-display">
              <div class="address-lines">
                <span v-if="contact.company" class="address-line bold">{{ contact.company }}</span>
                <span v-if="contact.address_line2" class="address-line">{{ contact.address_line2 }}</span>
                <span class="address-line">{{ contact.street_line }}</span>
                <span class="address-line">{{ contact.city_line }}</span>
                <span v-if="contact.canton" class="address-line">{{ SWISS_CANTONS[contact.canton] || contact.canton }}</span>
                <span v-if="contact.country !== 'Schweiz'" class="address-line">{{ contact.country }}</span>
              </div>

              <div class="address-actions">
                <a 
                  :href="'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(contact.full_address)" 
                  target="_blank" 
                  class="map-link"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                  </svg>
                  Auf Google Maps öffnen
                </a>
                <button @click="copyAddress" class="copy-btn">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                  </svg>
                  {{ copySuccess ? 'Kopiert!' : 'Adresse kopieren' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Karte -->
          <div class="section-card">
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                  <line x1="8" y1="2" x2="8" y2="18"/>
                  <line x1="16" y1="6" x2="16" y2="22"/>
                </svg>
                Standort
              </h2>
              <div class="section-header-actions">
                <button 
                  class="section-edit-btn" 
                  @click="searchAndSetLocation" 
                  :disabled="isSearchingLocation"
                  title="Standort anhand der Adresse suchen"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                  </svg>
                  {{ isSearchingLocation ? 'Suche...' : 'Adresse suchen' }}
                </button>
                <span v-if="coordinatesSaved" class="save-indicator">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                  Gespeichert
                </span>
              </div>
            </div>
            <MapView
              ref="mapRef"
              :latitude="contact.latitude"
              :longitude="contact.longitude"
              :address="contact.full_address"
              :editable="true"
              :prefer-swiss-map="isSwiss"
              :show-coordinates="true"
              :show-layer-control="true"
              height="350px"
              @update:latitude="onLatitudeChange"
              @update:longitude="onLongitudeChange"
              @coordinates-changed="onCoordinatesChanged"
            />
            <p class="map-hint">Klicken Sie auf die Karte, um den Standort zu setzen. Marker kann verschoben werden.</p>
          </div>

          <!-- Zusätzliche Informationen -->
          <div class="section-card">
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="12" y1="16" x2="12" y2="12"/>
                  <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
                Zusätzliche Informationen
              </h2>
              <button class="section-edit-btn" @click="openEditModal" title="Bearbeiten">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Bearbeiten
              </button>
            </div>
            <p v-if="contact.additional_info" class="additional-info-text">{{ contact.additional_info }}</p>
            <div v-else class="empty-section">
              <p>Keine zusätzlichen Informationen.</p>
            </div>
          </div>
        </main>
      </div>
    </div>

    <!-- Edit Modal -->
    <AddressModal
      v-if="showEditModal"
      :department-id="departmentId"
      :address="contact"
      @close="showEditModal = false"
      @saved="handleEdited"
    />

    <!-- Delete Confirmation -->
    <div v-if="showDeleteConfirm" class="delete-overlay">
      <div class="delete-dialog">
        <h3>Kontakt löschen?</h3>
        <p>
          Möchten Sie <strong>{{ contact?.name || contact?.company || 'diesen Kontakt' }}</strong> wirklich löschen? 
          Diese Aktion kann nicht rückgängig gemacht werden.
        </p>
        <div class="delete-dialog-actions">
          <button @click="showDeleteConfirm = false" class="btn-secondary">Abbrechen</button>
          <button @click="handleDelete" class="btn-danger" :disabled="isDeleting">
            {{ isDeleting ? 'Löschen...' : 'Löschen' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useToast } from '@/composables/useToast'
import { 
  getAddress, 
  updateAddress,
  deleteAddress, 
  SWISS_CANTONS, 
  type Address 
} from '@/api/addresses'
import MapView from '@/components/MapView.vue'
import AddressModal from '@/components/AddressModal.vue'

interface Props {
  contactId: string
  departmentId: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  close: []
  updated: []
  deleted: []
}>()

// State
const toast = useToast()
const contact = ref<Address | null>(null)
const isLoading = ref(false)
const error = ref<string | null>(null)

// Edit
const showEditModal = ref(false)

// Delete
const showDeleteConfirm = ref(false)
const isDeleting = ref(false)

// Copy
const copySuccess = ref(false)

// Map
const mapRef = ref<InstanceType<typeof MapView>>()
const isSearchingLocation = ref(false)
const coordinatesSaved = ref(false)
let saveTimeout: ReturnType<typeof setTimeout> | null = null
let pendingLat: number | null = null
let pendingLng: number | null = null

// Computed: Ist der Kontakt in der Schweiz?
const isSwiss = computed(() => {
  if (!contact.value) return true
  const country = contact.value.country?.toLowerCase() || ''
  return country === 'schweiz' || country === 'switzerland' || country === 'suisse' || country === 'ch' || country === ''
})

// Methods
async function loadContact() {
  isLoading.value = true
  error.value = null
  
  try {
    const data = await getAddress(props.contactId)
    contact.value = data.address
    
    // Nach dem Laden: Karte initialisieren
    await nextTick()
    
    // MapView braucht etwas Zeit zum Initialisieren
    setTimeout(() => {
      mapRef.value?.invalidateSize()
    }, 600)
  } catch (err: any) {
    const msg = err.response?.data?.error || 'Fehler beim Laden des Kontakts'
    error.value = msg
    toast.error(msg)
  } finally {
    isLoading.value = false
  }
}

function getInitials(c: Address): string {
  if (c.name) return c.name.substring(0, 2)
  if (c.company) return c.company.substring(0, 2)
  return '??'
}

function openEditModal() {
  showEditModal.value = true
}

async function handleEdited() {
  showEditModal.value = false
  await loadContact()
  emit('updated')
}

function confirmDelete() {
  showDeleteConfirm.value = true
}

async function handleDelete() {
  if (!contact.value) return
  isDeleting.value = true
  
  try {
    await deleteAddress(contact.value.id)
    showDeleteConfirm.value = false
    emit('deleted')
  } catch (err: any) {
    const msg = err.response?.data?.error || 'Fehler beim Löschen'
    error.value = msg
    toast.error(msg)
  } finally {
    isDeleting.value = false
  }
}

async function copyAddress() {
  if (!contact.value) return
  try {
    await navigator.clipboard.writeText(contact.value.full_address)
    copySuccess.value = true
    setTimeout(() => { copySuccess.value = false }, 2000)
  } catch {
    // Fallback: nichts tun
  }
}

// === Map Functions ===

// Adresse auf der Karte suchen
async function searchAndSetLocation() {
  if (!mapRef.value || !contact.value) return
  isSearchingLocation.value = true
  
  try {
    await mapRef.value.searchAddress()
    // Karte nochmal neu berechnen nach Suche
    setTimeout(() => {
      mapRef.value?.invalidateSize()
    }, 300)
  } catch (err) {
    console.error('Location search failed:', err)
  } finally {
    isSearchingLocation.value = false
  }
}

// Koordinaten-Updates von der Karte
function onLatitudeChange(lat: number) {
  pendingLat = lat
}

function onLongitudeChange(lng: number) {
  pendingLng = lng
}

function onCoordinatesChanged(lat: number, lng: number) {
  pendingLat = lat
  pendingLng = lng
  
  // Debounced speichern (1.5 Sek nach letzter Aenderung)
  if (saveTimeout) clearTimeout(saveTimeout)
  coordinatesSaved.value = false
  
  saveTimeout = setTimeout(() => {
    saveCoordinates()
  }, 1500)
}

// Koordinaten im Backend speichern
async function saveCoordinates() {
  if (!contact.value || pendingLat === null || pendingLng === null) return
  
  try {
    await updateAddress(contact.value.id, {
      latitude: pendingLat,
      longitude: pendingLng
    })
    
    // Lokalen State aktualisieren
    if (contact.value) {
      contact.value.latitude = pendingLat
      contact.value.longitude = pendingLng
      contact.value.has_coordinates = true
    }
    
    coordinatesSaved.value = true
    setTimeout(() => { coordinatesSaved.value = false }, 3000)
    
    emit('updated')
  } catch (err) {
    console.error('Failed to save coordinates:', err)
  }
}

// Watch
watch(() => props.contactId, () => {
  loadContact()
})

// Lifecycle
onMounted(() => {
  loadContact()
})
</script>

<style scoped>
.contact-detail-view {
  max-width: 900px;
  margin: 0 auto;
}

/* Header */
.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 32px;
  gap: 16px;
}

.header-left {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.back-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
  background: none;
  border: none;
  color: #6b7280;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: color 0.2s;
}

.back-btn:hover {
  color: #111827;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-title h1 {
  font-size: 26px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.header-subtitle {
  font-size: 14px;
  color: #6b7280;
  display: block;
  margin-top: 2px;
}

.contact-avatar-lg {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  flex-shrink: 0;
  text-transform: uppercase;
}

.contact-avatar-lg.customer { background: #dbeafe; color: #1d4ed8; }
.contact-avatar-lg.billing { background: #fef3c7; color: #92400e; }
.contact-avatar-lg.delivery { background: #d1fae5; color: #065f46; }
.contact-avatar-lg.storage { background: #e0e7ff; color: #4338ca; }
.contact-avatar-lg.event { background: #fce7f3; color: #be185d; }
.contact-avatar-lg.meeting { background: #ede9fe; color: #6d28d9; }
.contact-avatar-lg.office { background: #f3f4f6; color: #374151; }
.contact-avatar-lg.private { background: #fef2f2; color: #991b1b; }
.contact-avatar-lg.general { background: #e5e7eb; color: #6b7280; }
.contact-avatar-lg.postal { background: #ccfbf1; color: #0f766e; }

.header-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
  padding-top: 40px;
}

/* Header actions use shared ui/buttons.css */

/* Loading */
.loading-container {
  text-align: center;
  padding: 80px 20px;
}

.loading-container p {
  color: #6b7280;
  font-size: 15px;
}

.error-container {
  text-align: center;
  padding: 60px 20px;
  color: #dc2626;
}

/* Content */
.detail-content {
  display: flex;
  flex-direction: column;
}

.content-layout {
  display: flex;
  gap: 24px;
}

.content-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Section Card */
.section-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
}

.section-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-header-row .section-title {
  margin-bottom: 0;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 20px;
}

.section-title svg {
  color: #6b7280;
}

.section-edit-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  transition: all 0.2s;
}

.section-edit-btn:hover {
  background: #e5e7eb;
  border-color: #d1d5db;
  color: #111827;
}

/* Empty Sections */
.empty-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 20px;
  text-align: center;
}

.empty-section p {
  color: #9ca3af;
  font-size: 14px;
  margin: 0;
}

.btn-add-data {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: white;
  border: 1px dashed #d1d5db;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-add-data:hover {
  background: #f9fafb;
  border-color: #10b981;
  color: #059669;
}

/* Section Header Actions */
.section-header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.save-indicator {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 500;
  color: #059669;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateX(4px); }
  to { opacity: 1; transform: translateX(0); }
}

/* Map Hint */
.map-hint {
  font-size: 12px;
  color: #9ca3af;
  margin: 8px 0 0;
  text-align: center;
}

/* Info Grid */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.info-value {
  font-size: 15px;
  color: #111827;
  font-weight: 500;
}

/* Type & Primary Badges */
.type-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}

.type-badge.customer { background: #dbeafe; color: #1d4ed8; }
.type-badge.billing { background: #fef3c7; color: #92400e; }
.type-badge.delivery { background: #d1fae5; color: #065f46; }
.type-badge.storage { background: #e0e7ff; color: #4338ca; }
.type-badge.event { background: #fce7f3; color: #be185d; }
.type-badge.meeting { background: #ede9fe; color: #6d28d9; }
.type-badge.office { background: #f3f4f6; color: #374151; }
.type-badge.private { background: #fef2f2; color: #991b1b; }
.type-badge.general { background: #e5e7eb; color: #6b7280; }
.type-badge.postal { background: #ccfbf1; color: #0f766e; }

.primary-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: #dcfce7;
  color: #166534;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 600;
}

/* Contact Action Cards */
.contact-actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 12px;
}

.contact-action-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  text-decoration: none;
  transition: all 0.2s;
  cursor: pointer;
}

.contact-action-card:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.action-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.action-icon.email {
  background: #dbeafe;
  color: #2563eb;
}

.action-icon.phone {
  background: #d1fae5;
  color: #059669;
}

.action-icon.mobile {
  background: #ede9fe;
  color: #7c3aed;
}

.action-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.action-label {
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.action-value {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Address Display */
.address-display {
  display: flex;
  justify-content: space-between;
  gap: 24px;
}

.address-lines {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.address-line {
  font-size: 15px;
  color: #374151;
  line-height: 1.5;
}

.address-line.bold {
  font-weight: 600;
  color: #111827;
}

.address-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex-shrink: 0;
}

.map-link,
.copy-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.map-link {
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
  text-decoration: none;
}

.map-link:hover {
  background: #dbeafe;
}

.copy-btn {
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #e5e7eb;
}

.copy-btn:hover {
  background: #e5e7eb;
}

/* Additional Info */
.additional-info-text {
  font-size: 14px;
  color: #374151;
  line-height: 1.6;
  margin: 0;
  white-space: pre-wrap;
}

/* Delete Dialog */
.delete-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  padding: 20px;
}

.delete-dialog {
  background: white;
  border-radius: 12px;
  padding: 24px;
  max-width: 400px;
  width: 100%;
}

.delete-dialog h3 {
  margin: 0 0 8px;
  font-size: 18px;
  font-weight: 600;
  color: #111827;
}

.delete-dialog p {
  color: #6b7280;
  margin: 0 0 20px;
  font-size: 14px;
  line-height: 1.5;
}

.delete-dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

/* Delete dialog buttons use shared ui/buttons.css */

/* Responsive */
@media (max-width: 768px) {
  .detail-header {
    flex-direction: column;
  }

  .header-actions {
    padding-top: 0;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }

  .contact-actions-grid {
    grid-template-columns: 1fr;
  }

  .address-display {
    flex-direction: column;
  }
}
</style>
