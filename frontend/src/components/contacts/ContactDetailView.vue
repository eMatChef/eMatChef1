<template>
  <div class="contact-detail-view">
    <!-- Header -->
    <header class="detail-header">
      <div class="header-left">
        <EButton variant="secondary" size="small" class="contact-detail-back-btn" @click="$emit('close')">
          <v-icon icon="mdi-arrow-left" start size="20" />
          {{ t('contacts.detail.backToList') }}
        </EButton>
        <div class="header-title" v-if="contact">
          <div class="contact-avatar-lg" :class="contact.type">
            {{ getInitials(contact) }}
          </div>
          <div>
            <h1>{{ contact.name || contact.company || t('contacts.unnamed') }}</h1>
            <span class="header-subtitle" v-if="contact.company && contact.name">{{ contact.company }}</span>
          </div>
        </div>
      </div>
      <div v-if="contact?.is_deleted && canManageDeletedContacts" class="header-actions contact-detail-header-actions">
        <EButton variant="primary" size="small" :disabled="isRestoring" :loading="isRestoring" @click="handleRestore">
          {{ isRestoring ? t('contacts.detail.loading') : t('contacts.restore') }}
        </EButton>
        <EButton
          variant="secondary"
          size="small"
          class="contact-detail-delete-btn"
          :disabled="isPermanentDeleting"
          :loading="isPermanentDeleting"
          @click="confirmPermanentDelete"
        >
          {{ isPermanentDeleting ? t('contacts.permanentDeleting') : t('contacts.permanentDelete') }}
        </EButton>
      </div>
      <div v-else-if="contact && !isReadOnly" class="header-actions contact-detail-header-actions">
        <EButton variant="primary" size="small" @click="openEditModal">
          <v-icon icon="mdi-pencil-outline" start size="18" />
          {{ t('common.edit') }}
        </EButton>
        <EButton
          variant="secondary"
          size="small"
          class="contact-detail-delete-btn"
          :disabled="isDeleting"
          @click="confirmDelete"
        >
          <v-icon icon="mdi-delete-outline" start size="18" />
          {{ isDeleting ? t('contacts.detail.deleting') : t('common.delete') }}
        </EButton>
      </div>
    </header>

    <div v-if="contact?.is_deleted" class="deleted-banner" role="status">
      {{ t('contacts.detail.deletedBanner') }}
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="card"
      :message="t('contacts.detail.loading')"
    />

    <div v-else-if="error" class="contact-detail-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadContact">{{ t('common.retry') }}</EButton>
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
                {{ t('contacts.detail.sectionContactData') }}
              </h2>
              <EButton
                v-if="!isReadOnly"
                variant="text"
                size="small"
                class="section-edit-btn"
                @click="openEditModal"
              >
                <v-icon icon="mdi-pencil-outline" start size="16" />
                {{ t('common.edit') }}
              </EButton>
            </div>
            
            <div class="info-grid">
              <div class="info-item" v-if="contact.name">
                <span class="info-label">{{ t('settings.addressForm.designation') }}</span>
                <span class="info-value">{{ contact.name }}</span>
              </div>
              <div class="info-item" v-if="contact.company">
                <span class="info-label">{{ t('settings.addressForm.company') }}</span>
                <span class="info-value">{{ contact.company }}</span>
              </div>
              <div class="info-item" v-if="contact.contact_first_name || contact.contact_last_name">
                <span class="info-label">{{ t('settings.addressForm.contactPerson') }}</span>
                <span class="info-value">{{ formatContactPerson(contact) }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">{{ t('settings.addressForm.type') }}</span>
                <span class="info-value">
                  <span class="address-type-badge" :class="contact.type">{{ addressTypeLabel(contact.type) }}</span>
                </span>
              </div>
              <div class="info-item" v-if="contact.is_primary">
                <span class="info-label">{{ t('common.status') }}</span>
                <span class="info-value">
                  <span class="primary-badge">{{ t('contacts.detail.primaryAddress') }}</span>
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
                {{ t('contacts.detail.sectionCommunication') }}
              </h2>
              <EButton
                v-if="!isReadOnly"
                variant="text"
                size="small"
                class="section-edit-btn"
                @click="openEditModal"
              >
                <v-icon icon="mdi-pencil-outline" start size="16" />
                {{ t('common.edit') }}
              </EButton>
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
                  <span class="action-label">{{ t('settings.addressForm.email') }}</span>
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
                  <span class="action-label">{{ t('settings.addressForm.phone') }}</span>
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
                  <span class="action-label">{{ t('settings.addressForm.mobile') }}</span>
                  <span class="action-value">{{ contact.mobile }}</span>
                </div>
              </a>
            </div>

            <div v-else class="empty-section">
              <p>{{ t('contacts.detail.noCommunication') }}</p>
              <button v-if="!isReadOnly" class="btn-add-data" @click="openEditModal">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
                  <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                {{ t('contacts.detail.addCommunicationCta') }}
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
                {{ t('contacts.detail.sectionAddress') }}
              </h2>
              <EButton
                v-if="!isReadOnly"
                variant="text"
                size="small"
                class="section-edit-btn"
                @click="openEditModal"
              >
                <v-icon icon="mdi-pencil-outline" start size="16" />
                {{ t('common.edit') }}
              </EButton>
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

              <div v-if="showAddressMapActions" class="address-actions">
                <a 
                  :href="'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(contact.full_address)" 
                  target="_blank" 
                  rel="noopener noreferrer"
                  class="map-link"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                  </svg>
                  {{ t('contacts.detail.openGoogleMaps') }}
                </a>
                <button @click="copyAddress" class="copy-btn">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                  </svg>
                  {{ copySuccess ? t('contacts.detail.copied') : t('contacts.detail.copyAddress') }}
                </button>
              </div>
            </div>
          </div>

          <!-- Karte -->
          <div class="section-card section-card--location">
            <div class="section-header-row">
              <h2 class="section-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                  <line x1="8" y1="2" x2="8" y2="18"/>
                  <line x1="16" y1="6" x2="16" y2="22"/>
                </svg>
                {{ t('contacts.detail.sectionLocation') }}
              </h2>
            </div>
            <MapView
              v-if="contact.latitude != null && contact.longitude != null"
              ref="mapRef"
              :latitude="contact.latitude"
              :longitude="contact.longitude"
              :address="contact.full_address"
              :editable="false"
              :interactive="false"
              :prefer-swiss-map="isSwiss"
              :show-coordinates="true"
              :show-layer-control="false"
              :show-external-map-links="true"
              height="350px"
            />
            <p v-else class="map-no-coords">{{ t('contacts.detail.noCoordinates') }}</p>
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
                {{ t('contacts.detail.sectionNotes') }}
              </h2>
              <EButton
                v-if="!isReadOnly"
                variant="text"
                size="small"
                class="section-edit-btn"
                @click="openEditModal"
              >
                <v-icon icon="mdi-pencil-outline" start size="16" />
                {{ t('common.edit') }}
              </EButton>
            </div>
            <p v-if="contact.additional_info" class="additional-info-text">{{ contact.additional_info }}</p>
            <div v-else class="empty-section">
              <p>{{ t('contacts.detail.noAdditionalInfo') }}</p>
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
      :allowed-types="editAllowedTypes"
      @close="showEditModal = false"
      @saved="handleEdited"
    />

    <EDialog
      v-model="showPermanentDeleteConfirm"
      :max-width="440"
      :title="t('contacts.permanentDeleteTitle')"
    >
      <p class="text-muted">
        {{ t('contacts.permanentDeleteMessage', { name: contact?.name || contact?.company || t('contacts.detail.deleteNameFallback') }) }}
      </p>
      <template #actions>
        <EButton variant="secondary" @click="showPermanentDeleteConfirm = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="danger" :disabled="isPermanentDeleting" :loading="isPermanentDeleting" @click="handlePermanentDelete">
          {{ isPermanentDeleting ? t('contacts.permanentDeleting') : t('contacts.permanentDelete') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="showDeleteConfirm"
      :max-width="440"
      :title="t('contacts.detail.deleteTitle')"
    >
      <p class="text-muted">
        {{ t('contacts.detail.deleteMessage', { name: contact?.name || contact?.company || t('contacts.detail.deleteNameFallback') }) }}
      </p>
      <template #actions>
        <EButton variant="secondary" @click="showDeleteConfirm = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="danger" :disabled="isDeleting" :loading="isDeleting" @click="handleDelete">
          {{ isDeleting ? t('contacts.detail.deleting') : t('common.delete') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import {
  getAddress,
  deleteAddress,
  restoreAddress,
  permanentDeleteAddress,
  SWISS_CANTONS,
  type Address,
} from '@/api/addresses'
import MapView from '@/components/MapView.vue'
import AddressModal from '@/components/AddressModal.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog } from '@/components/form/base'
import {
  useDepartmentMemberRole,
  canUserManageContactType,
  USER_CONTACT_CREATE_TYPES,
} from '@/composables/useDepartmentMemberRole'

interface Props {
  contactId: string
  departmentId: string
}

const props = defineProps<Props>()
const { isUserRole, canManageDeletedContacts } = useDepartmentMemberRole()

const emit = defineEmits<{
  close: []
  updated: []
  deleted: []
}>()

// State
const toast = useToast()
const { t, te } = useI18n()

function addressTypeLabel(type: string): string {
  const path = `settings.addressForm.types.${type}` as const
  return te(path) ? t(path) : type
}
const contact = ref<Address | null>(null)
const isLoading = ref(false)
const error = ref<string | null>(null)

// Edit
const showEditModal = ref(false)

// Delete / restore
const showDeleteConfirm = ref(false)
const showPermanentDeleteConfirm = ref(false)
const isDeleting = ref(false)
const isRestoring = ref(false)
const isPermanentDeleting = ref(false)

// Copy
const copySuccess = ref(false)

// Map (nur Anzeige; Bearbeitung über AddressModal)
const mapRef = ref<InstanceType<typeof MapView>>()

// Computed: Ist der Kontakt in der Schweiz?
const isSwiss = computed(() => {
  if (!contact.value) return true
  const country = contact.value.country?.toLowerCase() || ''
  return country === 'schweiz' || country === 'switzerland' || country === 'suisse' || country === 'ch' || country === ''
})

/** Google Maps / Kopieren nur, wenn Strasse oder PLZ gesetzt sind */
const showAddressMapActions = computed(() => {
  const c = contact.value
  if (!c) return false
  return Boolean(c.street?.trim() || c.postal_code?.trim())
})

const isReadOnly = computed(() => {
  if (!contact.value) return true
  if (contact.value.is_deleted) return true
  return !canUserManageContactType(contact.value.type, isUserRole.value)
})

const editAllowedTypes = computed(() =>
  isUserRole.value ? [...USER_CONTACT_CREATE_TYPES] : null
)

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
    const msg = err.response?.data?.error || t('contacts.detail.loadError')
    error.value = msg
    toast.error(msg)
  } finally {
    isLoading.value = false
  }
}

function formatContactPerson(c: Address): string {
  return [c.contact_first_name, c.contact_last_name].filter(Boolean).join(' ')
}

function getInitials(c: Address): string {
  const contactName = formatContactPerson(c)
  if (contactName) {
    const parts = contactName.trim().split(/\s+/)
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
    return contactName.substring(0, 2).toUpperCase()
  }
  if (c.name) return c.name.substring(0, 2).toUpperCase()
  if (c.company) return c.company.substring(0, 2).toUpperCase()
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
    toast.success(t('contacts.detail.deleteSuccess'))
    emit('deleted')
  } catch (err: any) {
    const msg = err.response?.data?.error || t('contacts.detail.deleteError')
    error.value = msg
    toast.error(msg)
  } finally {
    isDeleting.value = false
  }
}

function confirmPermanentDelete() {
  showPermanentDeleteConfirm.value = true
}

async function handlePermanentDelete() {
  if (!contact.value) return
  isPermanentDeleting.value = true
  try {
    await permanentDeleteAddress(contact.value.id)
    showPermanentDeleteConfirm.value = false
    emit('deleted')
  } catch (err: any) {
    const msg = err.response?.data?.error || t('contacts.permanentDeleteError')
    toast.error(msg)
  } finally {
    isPermanentDeleting.value = false
  }
}

async function handleRestore() {
  if (!contact.value) return
  isRestoring.value = true
  try {
    const { address } = await restoreAddress(contact.value.id)
    contact.value = address
    toast.success(t('contacts.restoreSuccess'))
    emit('updated')
  } catch (err: any) {
    const msg = err.response?.data?.error || t('contacts.restoreError')
    toast.error(msg)
  } finally {
    isRestoring.value = false
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
  width: 100%;
  min-width: 0;
  margin: 0 auto;
  box-sizing: border-box;
}

.deleted-banner {
  margin: -16px 0 24px;
  padding: 12px 16px;
  border-radius: 8px;
  background: #fef3c7;
  color: #92400e;
  font-size: 0.9rem;
}

/* Header */
.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  margin-bottom: 32px;
  gap: 16px;
}

.header-left {
  display: flex;
  flex-direction: column;
  gap: 16px;
  flex: 1 1 220px;
  min-width: 0;
}

.contact-detail-back-btn {
  align-self: flex-start;
}

.contact-detail-error {
  padding: 24px;
  text-align: center;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
}

.header-title > div:last-child {
  min-width: 0;
}

.header-title h1 {
  font-size: 26px;
  font-weight: 700;
  color: #111827;
  margin: 0;
  overflow-wrap: anywhere;
  word-break: break-word;
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

/* Avatar-Farben → styles/components/address-type-badge.css (global) */

.header-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  flex: 0 1 auto;
  justify-content: flex-end;
  align-items: center;
  max-width: 100%;
}

.contact-detail-header-actions {
  gap: 8px;
}

.contact-detail-delete-btn {
  color: #6b7280;
}

.contact-detail-delete-btn:hover:not(:disabled) {
  background: #fef2f2;
  color: #dc2626;
  border-color: transparent;
}

.contact-detail-delete-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

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
  min-width: 0;
}

/* Section Card */
.section-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
  min-width: 0;
  max-width: 100%;
  box-sizing: border-box;
}

.section-card--location {
  overflow: visible;
}

.section-card--location :deep(.map-wrapper) {
  max-width: 100%;
  height: auto;
  overflow: visible;
}

.section-card--location :deep(.map-container) {
  overflow: hidden;
}

.section-card--location :deep(.external-map-links) {
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-top: 12px;
  padding-bottom: 4px;
}

.section-card--location :deep(.external-map-links .btn) {
  flex: 1 1 0;
  min-width: min(12rem, 100%);
  width: auto;
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
.map-no-coords {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
  text-align: center;
  padding: 24px 16px;
  background: #f9fafb;
  border: 1px dashed #e5e7eb;
  border-radius: 8px;
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

/* Type & Primary Badges — Adress-Typ → styles/components/address-type-badge.css (global) */

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
    width: 100%;
    justify-content: stretch;
  }

  .contact-detail-header-actions .btn {
    flex: 1 1 auto;
    min-width: 0;
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
