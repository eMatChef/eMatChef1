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
    <PageShell v-else class="contacts-view">
      <template #title>{{ t('contacts.title') }}</template>
      <template #subtitle>
        {{ isUserRole ? t('contacts.descriptionUser') : t('contacts.description') }}
      </template>
      <template v-if="canCreateContact" #actions>
        <button @click="openCreateModal" class="btn-primary">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
          <span>{{ t('contacts.newAddress') }}</span>
        </button>
      </template>

      <template #filters>
        <EFilterRow>
          <v-col class="e-filter-row__search">
            <ESearchField
              v-model="searchQuery"
              :label="t('contacts.searchPlaceholder')"
            />
          </v-col>
          <v-col cols="auto" class="e-filter-row__select">
            <ESelect
              v-model="selectedType"
              :items="typeSelectItems"
              :label="t('contacts.colType')"
              hide-details
            />
          </v-col>
          <v-col cols="auto" class="e-filter-row__select">
            <ESelect
              v-model="selectedCanton"
              :items="cantonSelectItems"
              :label="t('contacts.allCantons')"
              hide-details
            />
          </v-col>
          <v-col cols="auto" class="e-filter-row__actions d-flex align-center ga-2">
            <button
              type="button"
              class="reset-btn"
              :style="{ visibility: hasActiveFilters ? 'visible' : 'hidden' }"
              :aria-hidden="!hasActiveFilters"
              @click="resetFilters"
            >
              {{ t('contacts.resetFilters') }}
            </button>
            <ECheckbox
              v-if="canManageDeletedContacts"
              v-model="showDeleted"
              class="e-filter-row__checkbox"
              density="compact"
              :label="t('contacts.showDeleted')"
              hide-details
            />
          </v-col>
        </EFilterRow>
      </template>

      <!-- Loading State -->
      <ELoadingState
        v-if="isLoading"
        variant="table"
        :rows="8"
        :message="t('contacts.loadingList')"
      />

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
      <EEmptyState
        v-else-if="contacts.length === 0"
        variant="create"
        :title="t('contacts.emptyTitle')"
        :description="t('contacts.emptyText')"
      >
        <template v-if="canCreateContact" #actions>
          <EButton size="large" @click="openCreateModal">
            {{ t('contacts.emptyCta') }}
          </EButton>
        </template>
      </EEmptyState>

      <!-- No Results State -->
      <EEmptyState
        v-else-if="filteredContacts.length === 0"
        variant="search"
        :title="t('contacts.noResultsTitle')"
        :description="t('contacts.noResultsText')"
      >
        <template #actions>
          <EButton variant="secondary" @click="resetFilters">
            {{ t('contacts.resetFilters') }}
          </EButton>
        </template>
      </EEmptyState>

      <!-- Contacts Table -->
      <div v-else class="contacts-table-wrapper">
        <table class="contacts-table">
          <thead>
            <tr>
              <th class="col-name">{{ t('common.name') }}</th>
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
    </PageShell>
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
import PageShell from '@/components/layout/PageShell.vue'
import EFilterRow from '@/components/layout/EFilterRow.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { ESearchField, ESelect, ECheckbox, EButton } from '@/components/form/base'
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

const typeSelectItems = computed(() => [
  { title: t('contacts.allTypes'), value: '' },
  ...visibleAddressTypeKeys.value.map((key) => ({
    title: addressTypeLabel(String(key)),
    value: key,
  })),
])

const cantonSelectItems = computed(() => [
  { title: t('contacts.allCantons'), value: '' },
  ...Object.entries(SWISS_CANTONS).map(([code, name]) => ({
    title: `${code} - ${name}`,
    value: code,
  })),
])

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
