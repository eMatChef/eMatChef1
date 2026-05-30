<template>
  <div class="contacts-view">
    <!-- Detail View (ersetzt Liste wenn Kontakt ausgewählt) -->
      <ContactDetailView
      v-if="showDetailView && selectedContactId"
      :contact-id="selectedContactId"
      :department-id="currentDepartmentId"
      @close="closeDetailView"
      @updated="handleContactUpdated"
      @deleted="handleContactDeleted"
    />

    <!-- Liste View -->
    <template v-else>
      <!-- Header -->
      <header class="page-header">
        <div class="header-content">
          <div>
            <h1>{{ t('contacts.title') }}</h1>
            <p class="description">
              {{ isUserRole ? t('contacts.descriptionUser') : t('contacts.description') }}
            </p>
          </div>
          <button v-if="canCreateContact" @click="openCreateModal" class="btn-primary">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span>{{ t('contacts.newAddress') }}</span>
          </button>
        </div>
      </header>

      <!-- Search & Filter Bar -->
      <div class="filter-bar">
        <div class="search-box">
          <svg class="search-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16zM19 19l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <input 
            v-model="searchQuery"
            type="text" 
            :placeholder="t('contacts.searchPlaceholder')"
            class="search-input"
          />
          <button v-if="searchQuery" @click="clearSearch" class="clear-btn">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
              <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
        
        <div class="filter-group">
          <select v-model="selectedType" class="filter-select">
            <option value="">{{ t('contacts.allTypes') }}</option>
            <option v-for="key in visibleAddressTypeKeys" :key="key" :value="key">
              {{ t('settings.addressForm.types.' + key) }}
            </option>
          </select>
          
          <select v-model="selectedCanton" class="filter-select">
            <option value="">{{ t('contacts.allCantons') }}</option>
            <option v-for="(name, code) in SWISS_CANTONS" :key="code" :value="code">
              {{ code }} - {{ name }}
            </option>
          </select>
          
          <button
            @click="resetFilters"
            class="reset-btn"
            :style="{ visibility: hasActiveFilters ? 'visible' : 'hidden' }"
            :aria-hidden="!hasActiveFilters"
          >
            {{ t('contacts.resetFilters') }}
          </button>

          <label v-if="canManageDeletedContacts" class="show-deleted-toggle">
            <input v-model="showDeleted" type="checkbox" />
            {{ t('contacts.showDeleted') }}
          </label>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>{{ t('contacts.loadingList') }}</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <p class="error-message">{{ error }}</p>
        <button @click="loadContacts" class="retry-btn">{{ t('common.retry') }}</button>
      </div>

      <!-- Empty State -->
      <div v-else-if="contacts.length === 0" class="empty-state">
        <div class="empty-illustration">
          <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
            <circle cx="60" cy="45" r="20" stroke="#d1d5db" stroke-width="2" stroke-dasharray="4 4"/>
            <path d="M35 90c0-13.807 11.193-25 25-25s25 11.193 25 25" stroke="#d1d5db" stroke-width="2" stroke-dasharray="4 4"/>
            <circle cx="90" cy="85" r="20" fill="#10b981" fill-opacity="0.15"/>
            <path d="M90 75V95M80 85H100" stroke="#10b981" stroke-width="3" stroke-linecap="round"/>
          </svg>
        </div>
        <h2>{{ t('contacts.emptyTitle') }}</h2>
        <p>{{ t('contacts.emptyText') }}</p>
        <button v-if="canCreateContact" @click="openCreateModal" class="btn-primary btn-large">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
          {{ t('contacts.emptyCta') }}
        </button>
      </div>

      <!-- No Results State -->
      <div v-else-if="filteredContacts.length === 0" class="empty-state">
        <div class="empty-illustration">
          <svg width="100" height="100" viewBox="0 0 100 100" fill="none">
            <circle cx="45" cy="45" r="25" stroke="#d1d5db" stroke-width="3"/>
            <line x1="63" y1="63" x2="80" y2="80" stroke="#d1d5db" stroke-width="3" stroke-linecap="round"/>
            <line x1="35" y1="45" x2="55" y2="45" stroke="#e5e7eb" stroke-width="3" stroke-linecap="round"/>
          </svg>
        </div>
        <h2>{{ t('contacts.noResultsTitle') }}</h2>
        <p>{{ t('contacts.noResultsText') }}</p>
        <button @click="resetFilters" class="btn-secondary">{{ t('contacts.resetFilters') }}</button>
      </div>

      <!-- Contacts Table -->
      <div v-else class="contacts-table-wrapper">
        <table class="contacts-table">
          <thead>
            <tr>
              <th class="col-name">{{ t('contacts.colName') }}</th>
              <th class="col-contact">{{ t('contacts.colContact') }}</th>
              <th class="col-address">{{ t('contacts.colAddress') }}</th>
              <th class="col-type">{{ t('contacts.colType') }}</th>
              <th class="col-actions"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="contact in filteredContacts"
              :key="contact.id"
              class="contact-row"
              :class="{ 'contact-row--deleted': contact.is_deleted }"
              @dblclick="openContactDetail(contact)"
            >
              <!-- Name -->
              <td class="col-name">
                <div class="name-cell">
                  <div class="contact-avatar" :class="contact.type">
                    {{ getInitials(contact) }}
                  </div>
                  <div class="name-info">
                    <span class="contact-name">
                      {{ contact.name || contact.company || t('contacts.unnamed') }}
                      <span v-if="contact.is_primary" class="primary-badge">{{ t('contacts.primaryBadge') }}</span>
                      <span v-if="contact.is_deleted" class="deleted-badge">{{ t('contacts.deletedBadge') }}</span>
                    </span>
                    <span v-if="contact.company && contact.name" class="contact-company">{{ contact.company }}</span>
                  </div>
                </div>
              </td>

              <!-- Kontaktinfos -->
              <td class="col-contact">
                <div class="contact-info-cell" v-if="contact.email || contact.phone || contact.mobile">
                  <div v-if="contact.email" class="contact-detail">
                    <svg class="table-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="2" y="4" width="20" height="16" rx="2"/>
                      <path d="M22 4l-10 8L2 4"/>
                    </svg>
                    <a :href="'mailto:' + contact.email" @click.stop>{{ contact.email }}</a>
                  </div>
                  <div v-if="contact.phone" class="contact-detail">
                    <svg class="table-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                    </svg>
                    <a :href="'tel:' + contact.phone" @click.stop>{{ contact.phone }}</a>
                  </div>
                  <div v-if="contact.mobile" class="contact-detail">
                    <svg class="table-icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                      <line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                    <a :href="'tel:' + contact.mobile" @click.stop>{{ contact.mobile }}</a>
                  </div>
                </div>
                <span v-else class="no-contact">-</span>
              </td>

              <!-- Adresse -->
              <td class="col-address">
                <div class="address-cell">
                  <span class="address-street">{{ contact.street_line }}</span>
                  <span class="address-city">{{ contact.city_line }}</span>
                </div>
              </td>

              <!-- Typ -->
              <td class="col-type">
                <span class="address-type-badge" :class="contact.type">{{ addressTypeLabel(contact.type) }}</span>
              </td>

              <!-- Aktionen -->
              <td class="col-actions">
                <div class="action-buttons">
                  <template v-if="contact.is_deleted && canManageDeletedContacts">
                    <button
                      class="action-btn"
                      :title="t('contacts.restore')"
                      @click.stop="restoreContact(contact)"
                    >
                      <svg class="table-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12a9 9 0 0115-6.7L21 8"/>
                        <path d="M21 3v5h-5M21 12a9 9 0 01-15 6.7L3 16"/>
                        <path d="M3 21v-5h5"/>
                      </svg>
                    </button>
                    <button
                      class="action-btn action-btn--danger"
                      :title="t('contacts.permanentDelete')"
                      @click.stop="confirmPermanentDelete(contact)"
                    >
                      <svg class="table-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                      </svg>
                    </button>
                  </template>
                  <button v-else class="action-btn" @click.stop="openContactDetail(contact)" :title="t('contacts.openDetailsTitle')">
                    <svg class="table-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        
        <p class="table-hint">{{ t('contacts.tableHint') }}</p>
      </div>
    </template>

    <!-- Address Modal (Create only) -->
    <AddressModal
      v-if="showModal"
      :department-id="currentDepartmentId"
      :address="null"
      :default-type="createDefaultType"
      :allowed-types="createAllowedTypes"
      @close="closeModal"
      @saved="handleSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  getAddresses,
  restoreAddress,
  permanentDeleteAddress,
  ADDRESS_TYPES,
  SWISS_CANTONS,
  type Address,
} from '@/api/addresses'
import { useToast } from '@/composables/useToast'
import AddressModal from '@/components/AddressModal.vue'
import ContactDetailView from '@/components/contacts/ContactDetailView.vue'
import {
  useDepartmentMemberRole,
  USER_CONTACT_VIEW_TYPES,
  USER_CONTACT_CREATE_TYPES,
} from '@/composables/useDepartmentMemberRole'
import '@/styles/contacts-view.css'

const route = useRoute()
const router = useRouter()
const { t, te } = useI18n()
const toast = useToast()
const { isUserRole, canManageContacts, canManageDeletedContacts } = useDepartmentMemberRole()
const currentDepartmentId = computed(() => route.params.departmentId as string)

const canCreateContact = computed(() => canManageContacts.value || isUserRole.value)

const createDefaultType = computed(() => (isUserRole.value ? 'meeting' : 'customer'))

const createAllowedTypes = computed(() =>
  isUserRole.value ? [...USER_CONTACT_CREATE_TYPES] : null
)

const visibleAddressTypeKeys = computed(() => {
  const all = Object.keys(ADDRESS_TYPES) as (keyof typeof ADDRESS_TYPES)[]
  if (!isUserRole.value) return all
  return all.filter((key) =>
    USER_CONTACT_VIEW_TYPES.includes(key as (typeof USER_CONTACT_VIEW_TYPES)[number])
  )
})

function addressTypeLabel(type: string): string {
  const path = `settings.addressForm.types.${type}` as const
  return te(path) ? t(path) : type
}

// State
const contacts = ref<Address[]>([])
const isLoading = ref(false)
const error = ref<string | null>(null)

// Filter State
const searchQuery = ref('')
const selectedType = ref('')
const selectedCanton = ref('')
const showDeleted = ref(false)

// Modal State
const showModal = ref(false)

// Detail View State (gesteuert über Route-Parameter)
const selectedContactId = computed(() => route.params.contactId as string | undefined || null)
const showDetailView = computed(() => !!selectedContactId.value)

// Computed: Filtered Contacts
const filteredContacts = computed(() => {
  let result = [...contacts.value]
  
  // Textsuche
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(c => 
      (c.name && c.name.toLowerCase().includes(query)) ||
      (c.company && c.company.toLowerCase().includes(query)) ||
      (c.email && c.email.toLowerCase().includes(query)) ||
      (c.phone && c.phone.includes(query)) ||
      (c.mobile && c.mobile.includes(query)) ||
      (c.city && c.city.toLowerCase().includes(query)) ||
      (c.street && c.street.toLowerCase().includes(query)) ||
      (c.postal_code && c.postal_code.includes(query))
    )
  }
  
  // Typ-Filter
  if (selectedType.value) {
    result = result.filter(c => c.type === selectedType.value)
  }
  
  // Kanton-Filter
  if (selectedCanton.value) {
    result = result.filter(c => c.canton === selectedCanton.value)
  }
  
  return result
})

const hasActiveFilters = computed(() => {
  return searchQuery.value || selectedType.value || selectedCanton.value
})

// Methods
async function loadContacts() {
  if (!currentDepartmentId.value) return
  
  isLoading.value = true
  error.value = null
  
  try {
    const data = await getAddresses(currentDepartmentId.value, {
      includeDeleted: showDeleted.value && canManageDeletedContacts.value,
    })
    contacts.value = data.addresses
  } catch (err: any) {
    error.value = err.response?.data?.error || t('contacts.loadError')
  } finally {
    isLoading.value = false
  }
}

function getInitials(contact: Address): string {
  if (contact.name) {
    return contact.name.substring(0, 2)
  }
  if (contact.company) {
    return contact.company.substring(0, 2)
  }
  return '??'
}

function clearSearch() {
  searchQuery.value = ''
}

function resetFilters() {
  searchQuery.value = ''
  selectedType.value = ''
  selectedCanton.value = ''
}

// Detail View
function openContactDetail(contact: Address) {
  router.push(`/${currentDepartmentId.value}/contacts/${contact.id}`)
}

function closeDetailView() {
  router.push(`/${currentDepartmentId.value}/contacts`)
}

async function handleContactUpdated() {
  await loadContacts()
}

async function handleContactDeleted() {
  closeDetailView()
  await loadContacts()
}

// Modal (nur für Neu-Erstellung)
function openCreateModal() {
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSaved() {
  closeModal()
  await loadContacts()
}

async function restoreContact(contact: Address) {
  try {
    await restoreAddress(contact.id)
    toast.success(t('contacts.restoreSuccess'))
    await loadContacts()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('contacts.restoreError'))
  }
}

async function confirmPermanentDelete(contact: Address) {
  const name = contact.name || contact.company || t('contacts.unnamed')
  if (!window.confirm(t('contacts.permanentDeleteMessage', { name }))) return
  try {
    await permanentDeleteAddress(contact.id)
    await loadContacts()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('contacts.permanentDeleteError'))
  }
}

// Watchers
watch(showDeleted, () => {
  loadContacts()
})

watch(currentDepartmentId, () => {
  if (selectedContactId.value) {
    router.replace(`/${currentDepartmentId.value}/contacts`)
  }
  loadContacts()
})

// Lifecycle
onMounted(() => {
  loadContacts()
})
</script>
